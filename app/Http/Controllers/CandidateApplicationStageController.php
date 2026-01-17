<?php

namespace App\Http\Controllers;

use App\Mail\EmployeeNotificationMail;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateApplicationLog;
use App\Models\CompanyStage;
use App\Models\CompensationDetail;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\JobDetail;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CandidateApplicationStageController extends Controller
{
    /**
     * Move candidate to next stage automatically
     */
    public function moveToNextStage($applicationId)
    {
        try {
            $application = CandidateApplication::with(['jobPost.company.companyStages'])
                ->findOrFail($applicationId);

            $companyId = $application->jobPost->company_id;
            $currentStage = $application->companyStage;

            if (!$currentStage) {
                return response()->json(['message' => 'Current stage not found'], 400);
            }

            // Get next stage in order
            $nextStage = CompanyStage::with(['documents'])->where('company_id', $companyId)
                ->where('stage_order', '>', $currentStage->stage_order)
                ->where('is_active', true)
                ->orderBy('stage_order')
                ->first();

            if (!$nextStage) {
                return response()->json(['message' => 'Already at final stage'], 400);
            }

            $isHireAction = false;
            $documentsSent = false;
            $userAlreadyExists = false; // Changed from employeeAlreadyExists
            $userCreated = false; // Changed from employeeCreated

            DB::transaction(function () use ($application, $currentStage, $nextStage, &$isHireAction, &$documentsSent, &$userAlreadyExists, &$userCreated) {
                $oldStageId = $application->company_stage_id;
                $application->company_stage_id = $nextStage->id;
                $application->save();

                // Log the stage change
                CandidateApplicationLog::create([
                    'candidate_application_id' => $application->id,
                    'from_stage' => $oldStageId,
                    'to_stage' => $nextStage->id,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);

                // Check if this is the final stage
                $isFinalStage = !CompanyStage::where('company_id', $application->jobPost->company_id)
                    ->where('stage_order', '>', $nextStage->stage_order)
                    ->where('is_active', true)
                    ->exists();

                if ($isFinalStage) {
                    try {
                        $user = $this->createUserFromCandidate($application);
                        $isHireAction = true;
                        $userCreated = true;
                        Log::info("User created successfully for candidate {$application->candidate->id}");
                    } catch (\Exception $e) {
                        // Update error message check
                        if (str_contains($e->getMessage(), 'User already exists')) {
                            $userAlreadyExists = true;
                            Log::warning("User already exists for candidate {$application->candidate->id}, continuing stage change");
                        } else {
                            throw $e;
                        }
                    }
                }
            });

            $response = [
                'message' => 'Moved to next stage',
                'new_stage' => $nextStage->name,
                'new_stage_id' => $nextStage->id
            ];

            if ($isHireAction) {
                if ($userAlreadyExists) {
                    $response['user_status'] = 'already_exists';
                    $response['message'] = 'Candidate moved to final stage (user account already exists)';
                } elseif ($userCreated) {
                    $response['user_created'] = true;
                    $response['message'] = 'Candidate moved to final stage and user account created';
                }
            }

            if ($documentsSent) {
                $response['documents_sent'] = true;
                $response['message'] .= ' and document links sent to candidate';
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to move to next stage: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to move to next stage: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Move candidate to specific stage
     */
    public function setStage(Request $request, $applicationId)
    {
        try {
            // Accept both object payload and direct stage_id
            $stageId = null;
            if ($request->has('stage_id')) {
                $stageId = $request->input('stage_id');
                // If stage_id is an array/object, extract the value
                if (is_array($stageId)) {
                    $stageId = $stageId['stage_id'] ?? null;
                }
            }

            if (!$stageId) {
                return response()->json(['message' => 'Stage ID is required'], 400);
            }

            $request->merge(['stage_id' => $stageId]);

            $request->validate([
                'stage_id' => 'required|exists:company_stages,id',
                'note' => 'sometimes|string|max:1000',
            ]);

            $application = CandidateApplication::with(['jobPost.company'])->findOrFail($applicationId);
            $fromStageId = $application->company_stage_id;
            $toStageId = $request->input('stage_id');

            // Verify the target stage belongs to the same company
            $targetStage = CompanyStage::with(['documents'])->where('id', $toStageId)
                ->where('company_id', $application->jobPost->company_id)
                ->where('is_active', true)
                ->first();

            if (!$targetStage) {
                return response()->json(['message' => 'Invalid stage for this company'], 400);
            }

            // Check if currently at final stage
            $currentStage = $application->companyStage;
            $isCurrentlyFinal = false;
            if ($currentStage) {
                $isCurrentlyFinal = !CompanyStage::where('company_id', $application->jobPost->company_id)
                    ->where('stage_order', '>', $currentStage->stage_order)
                    ->where('is_active', true)
                    ->exists();
            }

            // Check if moving to a non-final stage (stage with lower order than current)
            $isMovingToNonFinal = false;
            if ($currentStage && $targetStage) {
                $isMovingToNonFinal = $targetStage->stage_order < $currentStage->stage_order;
            }

            $isHireAction = false;
            $documentsSent = false;
            $userDeactivated = false;
            $userAlreadyExists = false;
            $userCreated = false;

            if ($fromStageId != $toStageId) {
                DB::transaction(function () use ($application, $fromStageId, $targetStage, $toStageId, $request, &$isHireAction, &$documentsSent, &$userDeactivated, &$userAlreadyExists, &$userCreated, $isCurrentlyFinal, $isMovingToNonFinal) {
                    // AUTOMATICALLY deactivate user when moving back from final stage to a non-final stage
                    if ($isCurrentlyFinal && $isMovingToNonFinal) {
                        // Find and deactivate the user created for this candidate
                        $candidate = $application->candidate;
                        if ($candidate && $candidate->user_id) {
                            $user = User::find($candidate->user_id);

                            if ($user) {
                                // Deactivate the user instead of deleting
                                $user->is_active = false;
                                $user->save();
                                $userDeactivated = true;

                                Log::info("Automatically deactivated user {$user->id} (set is_active=false) when moving candidate {$candidate->id} back from final stage to stage order {$targetStage->stage_order}");
                            }
                        }
                    }

                    $application->company_stage_id = $toStageId;
                    $application->save();

                    CandidateApplicationLog::create([
                        'candidate_application_id' => $application->id,
                        'from_stage' => $fromStageId,
                        'to_stage' => $toStageId,
                        'changed_by' => Auth::id(),
                        'changed_at' => now(),
                        'note' => $request->input('note'),
                    ]);

                    // Check if moving to final stage
                    $isFinalStage = !CompanyStage::where('company_id', $application->jobPost->company_id)
                        ->where('stage_order', '>', $targetStage->stage_order)
                        ->where('is_active', true)
                        ->exists();

                    if ($isFinalStage) {
                        try {
                            $user = $this->createUserFromCandidate($application);
                            $isHireAction = true;
                            $userCreated = true;
                            Log::info("User created successfully for candidate {$application->candidate->id}");
                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), 'User already exists')) {
                                $userAlreadyExists = true;
                                Log::warning("User already exists for candidate {$application->candidate->id}, continuing stage change");

                                // If user exists but is inactive, reactivate it
                                $candidate = $application->candidate;
                                if ($candidate && $candidate->user_id) {
                                    $existingUser = User::find($candidate->user_id);
                                    if ($existingUser && !$existingUser->is_active) {
                                        $existingUser->is_active = true;
                                        $existingUser->save();
                                        Log::info("Reactivated user {$existingUser->id} when moving candidate {$candidate->id} to final stage");
                                    }
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }
                });
            }

            $response = [
                'message' => 'Stage updated successfully',
                'application' => $application->load('companyStage'),
            ];

            if ($isHireAction) {
                if ($userAlreadyExists) {
                    $response['user_status'] = 'already_exists';
                    $response['message'] = 'Candidate moved to final stage (user account already exists)';
                } elseif ($userCreated) {
                    $response['user_created'] = true;
                    $response['message'] = 'Candidate moved to final stage and user account created';
                }
            }

            if ($userDeactivated) {
                $response['user_deactivated'] = true;
                $response['message'] = 'Stage updated and user account automatically deactivated';
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to set stage: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update stage: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get available stages for a candidate application
     */
    public function getAvailableStages($applicationId)
    {
        $application = CandidateApplication::with(['jobPost.company.companyStages' => function ($query) {
            $query->where('is_active', true)->orderBy('stage_order');
        }])->findOrFail($applicationId);

        $stages = $application->jobPost->company->companyStages;

        return response()->json([
            'success' => true,
            'data' => $stages
        ]);
    }

    /**
     * Get stage history for a candidate
     */
    public function getStageHistory($applicationId)
    {
        $history = CandidateApplicationLog::with([
            'fromStage',
            'toStage',
            'changedBy',
            'changedBy.employee'
        ])
            ->where('candidate_application_id', $applicationId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $user = $log->changedBy;
                $employee = $user?->employee;

                $fullName = $employee
                    ? trim($employee->first_name . ' ' . $employee->last_name)
                    : ($user?->first_name . ' ' . $user?->last_name ?? 'System');

                return [
                    'id' => $log->id,
                    'candidate_application_id' => $log->candidate_application_id,
                    'from_stage' => $log->from_stage,
                    'from_stage_label' => $log->from_stage_label,
                    'to_stage' => $log->to_stage,
                    'to_stage_label' => $log->to_stage_label,
                    'changed_by' => $fullName,
                    'changed_by_id' => $log->changed_by,
                    'changed_by_profile_image' => $this->generateFileUrl($employee?->profile_image) ?? $user?->profile_image,
                    'changed_at' => $log->changed_at,
                    'note' => $log->note,
                    'is_hire_action' => $log->isHireAction(),
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Check if candidate is at final stage
     */
    public function checkFinalStage($applicationId)
    {
        $application = CandidateApplication::with(['jobPost.company', 'companyStage'])->findOrFail($applicationId);

        $currentStage = $application->companyStage;
        if (!$currentStage) {
            return response()->json(['is_final_stage' => false]);
        }

        $isFinalStage = !CompanyStage::where('company_id', $application->jobPost->company_id)
            ->where('stage_order', '>', $currentStage->stage_order)
            ->where('is_active', true)
            ->exists();

        return response()->json([
            'is_final_stage' => $isFinalStage,
            'current_stage' => $currentStage->name,
            'current_stage_order' => $currentStage->stage_order
        ]);
    }


    /**
     * Create user account for candidate at final stage
     */
    private function createUserFromCandidate(CandidateApplication $application)
    {
        DB::beginTransaction();

        try {
            $candidate = $application->candidate;
            if (!$candidate) {
                throw new \Exception('Candidate not found');
            }

            // Check if user already exists for this candidate's email
            $existingUser = User::where('email', $candidate->email)->first();

            if ($existingUser) {
                // User already exists, link candidate to this user
                $candidate->user_id = $existingUser->id;
                $candidate->save();

                // If user is inactive, activate it
                if (!$existingUser->is_active) {
                    $existingUser->is_active = true;
                    $existingUser->save();
                    Log::info("Reactivated existing user {$existingUser->id} for candidate {$candidate->id}");
                }

                Log::info("User already exists for candidate {$candidate->id}, linked to existing user {$existingUser->id}");

                // Return the existing user
                DB::commit();
                return $existingUser;
            }

            // --- Create User for the candidate ---
            $tempPassword = Str::random(10);
            $email = $candidate->email;

            if (!$email) {
                throw new \Exception('Candidate email is required to create user account');
            }

            $user = User::create([
                'first_name'  => $candidate->first_name ?? "Unknown",
                'last_name'   => $candidate->last_name ?? "",
                'company_id'  => $application->jobPost->company_id ?? Auth::user()->company_id,
                'employee_id' => null, // No employee yet - they're still a candidate
                'email'       => $email,
                'password'    => Hash::make($tempPassword),
                'role'        => 6, // Role for candidates
                'is_active'   => true, // Ensure new users are active
            ]);

            // Link candidate to the user
            $candidate->user_id = $user->id;
            $candidate->save();

            Log::info("Created user {$user->id} for candidate {$candidate->id}");

            // Send welcome email using the dedicated method
            $this->sendCandidateWelcomeEmail($candidate, $tempPassword);

            DB::commit();
            return $user; // Return the created user

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user for candidate: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateFileUrl(?string $filePath)
    {
        if (!$filePath) {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));

        return url('api/v.1/files/' . $encodedPath);
    }

    /**
     * Send welcome email to candidate with their credentials
     */
    private function sendCandidateWelcomeEmail(Candidate $candidate, string $tempPassword)
    {
        try {
            // Get the job from the candidate's latest application
            $application = $candidate->applications()->latest()->first();
            $job = $application ? $application->jobPost : null;

            // Get company from application or candidate
            $company = null;
            if ($application && $application->jobPost) {
                $company = $application->jobPost->company;
            } elseif ($candidate->company) {
                $company = $candidate->company;
            }

            $mailData = [
                'candidate_name' => $candidate->first_name . ' ' . $candidate->last_name,
                'email' => $candidate->email,
                'temp_password' => $tempPassword,
                'job_title' => $job->job_title ?? 'the position',
                'company_name' => $company->name ?? 'Our Company',
                'login_url' => config('app.frontend_url', 'https://hustoro.com') . '/signin',
            ];

            // Create and send email
            Mail::send('emails.candidate-welcome', $mailData, function ($message) use ($candidate, $company) {
                $message->to($candidate->email)
                    ->subject('Welcome to ' . ($company->name ?? 'Our Platform') . ' - Your Candidate Account');
            });

            Log::info('Candidate welcome email sent to: ' . $candidate->email);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send candidate welcome email: ' . $e->getMessage());
            // Don't throw error, just log it
            return false;
        }
    }
}
