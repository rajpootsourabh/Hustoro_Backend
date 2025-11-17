<?php

namespace App\Http\Controllers;

use App\Mail\EmployeeNotificationMail;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateApplicationLog;
use App\Models\CompensationDetail;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\JobDetail;
use App\Models\LegalDocument;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CandidateApplicationStageController extends Controller
{
    public function moveToNextStage($applicationId)
    {
        $application = CandidateApplication::findOrFail($applicationId);
        $currentStageId = $application->stage_id;
        $nextStage = Stage::where('id', '>', $currentStageId)->orderBy('id')->first();

        if (!$nextStage) {
            return response()->json(['message' => 'Already at final stage'], 400);
        }

        $application->stage_id = $nextStage->id;
        $application->save();

        CandidateApplicationLog::create([
            'candidate_application_id' => $application->id,
            'from_stage' => $currentStageId,
            'to_stage' => $nextStage->id,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);

        // Handle hiring
        if (strcasecmp($nextStage->name, 'Hired') === 0) {
            $this->createEmployeeFromCandidate($application);
        }

        return response()->json([
            'message' => 'Moved to next stage',
            'new_stage' => $nextStage->name,
        ]);
    }

    public function setStage(Request $request, $applicationId)
    {
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
        ]);

        $application = CandidateApplication::findOrFail($applicationId);
        $fromStage = $application->stage_id;
        $toStage = $request->input('stage_id');

        if ($fromStage != $toStage) {
            $application->stage_id = $toStage;
            $application->save();

            CandidateApplicationLog::create([
                'candidate_application_id' => $application->id,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'changed_by' => Auth::id(),
                'changed_at' => now(),
                'note' => $request->input('note'),
            ]);
        }

        // Automatically create employee when stage set manually
        $stage = Stage::find($toStage);
        if ($stage && strcasecmp($stage->name, 'Hired') === 0) {
            $this->createEmployeeFromCandidate($application);
        }

        return response()->json([
            'message' => 'Stage updated successfully',
            'application' => $application,
        ]);
    }

    private function createEmployeeFromCandidate(CandidateApplication $application)
    {
        DB::beginTransaction();

        try {
            $candidate = Candidate::find($application->candidate_id);
            if (!$candidate) {
                throw new \Exception('Candidate not found');
            }


            // --- Step 1: Create Employee ---
            $employee = Employee::create([
                'company_id'     => Auth::user()->company_id ?? $candidate->company_id ?? 1,
                'first_name'     => $candidate->first_name ?? 'N/A',
                'last_name'      => $candidate->last_name ?? 'N/A',
                'middle_name'    => $candidate->middle_name,
                'preferred_name' => $candidate->preferred_name,
                'country'        => $candidate->country,
                'address'        => $candidate->address,
                'gender'         => $candidate->gender,
                'birthdate'      => $candidate->birthdate,
                'marital_status' => $candidate->marital_status ?? 'Single',
                'phone'          => $candidate->phone,
                'work_email'     => $candidate->email,
                'personal_email' => $candidate->personal_email,
                'profile_image'  => $candidate->profile_image,
            ]);

            // --- Step 2: Create related data ---
            JobDetail::create([
                'employee_id'     => $employee->id,
                'job_title'       => 'New Hire',
                'start_date'      => now(),
                'effective_date'  => now(),
                'employment_type' => 'Full-Time',
            ]);

            CompensationDetail::create([
                'employee_id'    => $employee->id,
                'bank_name'      => '',
                'iban'           => '',
                'account_number' => '',
                'salary_details' => 0.00,
            ]);

            EmergencyContact::create([
                'employee_id'   => $employee->id,
                'contact_name'  => '',
                'contact_phone' => '',
            ]);

            LegalDocument::create([
                'employee_id'            => $employee->id,
                'social_security_number' => '',
                'national_id'            => '',
                'tax_id'                 => '',
            ]);

            // --- Step 3: Create login + send mail ---
            $tempPassword = Str::random(10);
            $email = $employee->work_email ?? $employee->personal_email;

            if ($email) {
                User::create([
                    'company_id'  => $employee->company_id,
                    'employee_id' => $employee->id,
                    'email'       => $email,
                    'password'    => Hash::make($tempPassword),
                    'role'        => 5,
                ]);

                $emailData = [
                    'name'             => $employee->first_name . ' ' . $employee->last_name,
                    'email'            => $email,
                    'temp_password'    => $tempPassword,
                    'it_support_email' => 'itsupport@hustoro.com',
                    'sender_name'      => 'Anwar Kazi',
                    'sender_position'  => 'CEO',
                    'company_name'     => 'Hustoro',
                    'contact_info'     => 'contact@hustoro.com | +91-1234567890',
                ];

                try {
                    Mail::to($email)->send(new EmployeeNotificationMail($emailData));
                } catch (\Throwable $e) {
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
