<?php
// app/Http/Controllers/JobController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Shift;
use App\Models\JobTimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    // Create job with multiple shifts
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'job_title' => 'required|string|max:191',
                'job_startdate' => 'required|date',
                'job_enddate' => 'nullable|date|after_or_equal:job_startdate',
                'job_description' => 'nullable|string',
                'shift_ids' => 'required|array|min:1',
                'shift_ids.*' => 'exists:shifts,id',
                'client_id' => 'required|exists:clients,id',
                'candidate_id' => 'nullable|exists:candidates,id',
                'assignment_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Verify client belongs to the same company
            $client = Client::forCompany($user->company_id)->find($validated['client_id']);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found or does not belong to your company'
                ], 404);
            }

            // Verify shifts belong to the same company
            $shifts = Shift::forCompany($user->company_id)
                ->whereIn('id', $validated['shift_ids'])
                ->get();

            if ($shifts->count() !== count($validated['shift_ids'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more shifts do not belong to your company'
                ], 403);
            }

            // Create the job
            $job = Job::create([
                'job_title' => $validated['job_title'],
                'job_startdate' => $validated['job_startdate'],
                'job_enddate' => $validated['job_enddate'] ?? $validated['job_startdate'],
                'job_description' => $validated['job_description'] ?? null,
                'client_id' => $validated['client_id'],
                'company_id' => $user->company_id, // Auto-set from auth user
                'created_by' => $user->id, // Auto-set from auth user
                'status' => 'draft'
            ]);


            // Attach shifts
            $job->shifts()->attach($validated['shift_ids']);

            // If candidate_id is provided, assign the job to candidate
            if (!empty($validated['candidate_id'])) {
                $job->update([
                    'candidate_id' => $validated['candidate_id'],
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                    'status' => 'assigned',
                    'notes' => $validated['assignment_notes'] ?? null
                ]);
            }

            // Load relationships for response
            $job->load(['shifts', 'client', 'candidate', 'creator', 'assigner']);


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job created successfully' . (!empty($validated['candidate_id']) ? ' and assigned to candidate' : ''),
                'data' => $job
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to update it'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'job_title' => 'required|string|max:191',
                'job_startdate' => 'required|date',
                'job_enddate' => 'nullable|date|after_or_equal:job_startdate',
                'job_description' => 'nullable|string',
                'shift_ids' => 'required|array|min:1',
                'shift_ids.*' => 'exists:shifts,id',
                'client_id' => 'required|exists:clients,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Verify client belongs to the same company
            $client = Client::forCompany($user->company_id)->find($validated['client_id']);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found or does not belong to your company'
                ], 404);
            }

            // Update job details
            $job->update([
                'job_title' => $validated['job_title'],
                'job_startdate' => $validated['job_startdate'],
                'job_enddate' => $validated['job_enddate'] ?? $validated['job_startdate'],
                'job_description' => $validated['job_description'] ?? null,
                'client_id' => $validated['client_id'],
            ]);

            // Sync shifts
            $job->shifts()->sync($validated['shift_ids']);

            // Load relationships for response
            $job->load(['shifts', 'client', 'candidate', 'creator', 'assigner']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job updated successfully',
                'data' => $job
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating job: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete job
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to delete it'
                ], 404);
            }

            // Log the deletion for audit purposes
            Log::info('Deleting job', [
                'job_id' => $job->id,
                'job_title' => $job->job_title,
                'deleted_by' => $user->id,
                'deleted_at' => now()
            ]);

            // Delete the job (cascade will handle related data if configured)
            $job->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting job: ' . $e->getMessage(), [
                'job_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error deleting job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get jobs by candidate ID
    public function getJobsByCandidateId($candidateId)
    {
        try {
            $user = Auth::user();

            $jobs = Job::with(['shifts', 'client', 'creator', 'assigner', 'activeTimeLog'])
                ->forCompany($user->company_id)
                ->where('candidate_id', $candidateId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $jobs
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching candidate jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching candidate jobs'
            ], 500);
        }
    }

    // Assign job to candidate
    public function assignToCandidate(Request $request, $jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to assign it'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'candidate_id' => 'required|exists:candidates,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $job->update([
                'candidate_id' => $validated['candidate_id'],
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'notes' => $validated['notes'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job assigned to candidate successfully',
                'data' => $job->load(['shifts', 'client', 'candidate', 'assigner'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning job to candidate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error assigning job'
            ], 500);
        }
    }

    // Update job status
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to update it'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,assigned,confirmed,rejected,completed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $updateData = ['status' => $validated['status']];

            // Set confirmed_at timestamp if status is 'confirmed'
            if ($validated['status'] === 'confirmed') {
                $updateData['confirmed_at'] = now();
            }

            $job->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Job status updated successfully',
                'data' => $job->load(['shifts', 'client', 'candidate', 'creator', 'assigner'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating job status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating job status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============ TIME TRACKING METHODS ============

    public function startTimeTracking(Request $request, $jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to track time'
                ], 404);
            }

            // Get the candidate ID from the job, not from Auth
            $candidateId = $job->candidate_id;

            if (!$candidateId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No candidate assigned to this job'
                ], 400);
            }

            // Check if there's already an active time log
            $activeLog = JobTimeLog::where('job_id', $jobId)
                ->where('candidate_id', $candidateId)
                ->where('status', 'in_progress')
                ->first();

            if ($activeLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time tracking is already in progress for this job'
                ], 400);
            }

            // Create new time log
            $timeLog = JobTimeLog::create([
                'job_id' => $jobId,
                'candidate_id' => $candidateId,
                'start_time' => now(),
                'status' => 'in_progress'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking started',
                'data' => $timeLog
            ]);
        } catch (\Exception $e) {
            Log::error('Error starting time tracking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error starting time tracking'
            ], 500);
        }
    }

    // Stop time tracking for a job
    public function stopTimeTracking(Request $request, $jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to track time'
                ], 404);
            }

            $candidateId = $job->candidate_id;

            // Look for either 'in_progress' OR 'paused' status
            $timeLog = JobTimeLog::where('job_id', $jobId)
                ->where('candidate_id', $candidateId)
                ->whereIn('status', ['in_progress', 'paused'])
                ->first();

            if (!$timeLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active or paused time tracking session found'
                ], 404);
            }

            // Calculate final total
            $stopTime = now();

            if ($timeLog->status === 'in_progress') {
                // If timer was running, calculate active seconds
                $activeSeconds = $stopTime->diffInSeconds($timeLog->start_time);
                $totalSeconds = ($timeLog->cumulative_seconds ?? 0) + $activeSeconds;
            } else {
                // If timer was paused, use cumulative seconds
                $totalSeconds = $timeLog->cumulative_seconds ?? 0;
            }

            $timeLog->update([
                'end_time' => $stopTime,
                'total_seconds' => $totalSeconds,
                'cumulative_seconds' => $totalSeconds,
                'status' => 'completed',
                'notes' => $request->input('notes', 'Completed work session')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking stopped',
                'data' => $timeLog
            ]);
        } catch (\Exception $e) {
            Log::error('Error stopping time tracking: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'error_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error stopping time tracking: ' . $e->getMessage()
            ], 500);
        }
    }

    // Pause time tracking
    public function pauseTimeTracking(Request $request, $jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to track time'
                ], 404);
            }

            $candidateId = $job->candidate_id;

            $timeLog = JobTimeLog::where('job_id', $jobId)
                ->where('candidate_id', $candidateId)
                ->where('status', 'in_progress')
                ->first();

            if (!$timeLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active time tracking session found'
                ], 404);
            }

            // Calculate active seconds up to now
            $pauseTime = now();
            $activeSeconds = $pauseTime->diffInSeconds($timeLog->start_time);
            $cumulativeSeconds = ($timeLog->cumulative_seconds ?? 0) + $activeSeconds;

            $timeLog->update([
                'last_paused_at' => $pauseTime,
                'cumulative_seconds' => $cumulativeSeconds,
                'total_seconds' => $cumulativeSeconds,
                'status' => 'paused',
                'notes' => $request->input('notes', 'Paused by user')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking paused',
                'data' => $timeLog
            ]);
        } catch (\Exception $e) {
            Log::error('Error pausing time tracking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error pausing time tracking'
            ], 500);
        }
    }

    // Resume time tracking
    public function resumeTimeTracking(Request $request, $jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to track time'
                ], 404);
            }

            $candidateId = $job->candidate_id;

            $timeLog = JobTimeLog::where('job_id', $jobId)
                ->where('candidate_id', $candidateId)
                ->where('status', 'paused')
                ->first();

            if (!$timeLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'No paused time tracking session found'
                ], 404);
            }

            $timeLog->update([
                'start_time' => now(),
                'status' => 'in_progress'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking resumed',
                'data' => $timeLog
            ]);
        } catch (\Exception $e) {
            Log::error('Error resuming time tracking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error resuming time tracking'
            ], 500);
        }
    }

    // Get time logs for a job
    public function getTimeLogs($jobId)
    {
        try {
            $user = Auth::user();
            $job = Job::forCompany($user->company_id)->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or you do not have permission to view time logs'
                ], 404);
            }

            $timeLogs = JobTimeLog::with(['job', 'candidate'])
                ->where('job_id', $jobId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $timeLogs
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching time logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching time logs'
            ], 500);
        }
    }

    // Get candidate's time summary
    public function getCandidateTimeSummary($candidateId)
    {
        try {
            $user = Auth::user();

            $summary = JobTimeLog::join('jobs', 'job_time_logs.job_id', '=', 'jobs.id')
                ->where('jobs.company_id', $user->company_id)
                ->where('job_time_logs.candidate_id', $candidateId)
                ->selectRaw('SUM(job_time_logs.total_seconds) as total_seconds')
                ->selectRaw('COUNT(DISTINCT job_time_logs.job_id) as total_jobs')
                ->selectRaw('COUNT(*) as total_sessions')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_seconds' => $summary->total_seconds ?? 0,
                    'total_jobs' => $summary->total_jobs ?? 0,
                    'total_sessions' => $summary->total_sessions ?? 0,
                    'formatted_time' => $this->formatSeconds($summary->total_seconds ?? 0)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching time summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching time summary'
            ], 500);
        }
    }

    private function formatSeconds($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
