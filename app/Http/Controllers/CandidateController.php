<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateEmployeeAssignment;
use App\Models\Job;
use App\Models\JobTimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;

class CandidateController extends Controller
{
    use ApiResponse;

    /**
     * Get candidates assigned to an employee with their job details
     */
    public function getCandidatesByEmployee(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'status' => 'nullable|in:active,inactive,all',
            'with_job_details' => 'nullable|boolean',
            'with_time_logs' => 'nullable|boolean',
            'search' => 'nullable|string',
            'job_id' => 'nullable|exists:jobs,id',
        ]);

        $companyId = Auth::user()->company_id;
        $employeeId = $request->employee_id;

        // Get assigned candidate IDs
        $assignedQuery = CandidateEmployeeAssignment::where('employee_id', $employeeId)
            ->select('candidate_id');

        // Main query for candidates
        $query = Candidate::where('company_id', $companyId)
            ->whereIn('id', $assignedQuery)
            ->when($request->status === 'active', function ($q) {
                $q->whereHas('jobs', function ($jobQuery) {
                    $jobQuery->whereIn('status', ['assigned', 'confirmed', 'in_progress']);
                });
            })
            ->when($request->status === 'inactive', function ($q) {
                $q->whereDoesntHave('jobs', function ($jobQuery) {
                    $jobQuery->whereIn('status', ['assigned', 'confirmed', 'in_progress']);
                });
            });

        // Search filter
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('designation', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Job filter
        if ($request->job_id) {
            $query->whereHas('jobs', function ($q) use ($request) {
                $q->where('id', $request->job_id);
            });
        }

        // Select specific fields
        $query->select([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'designation',
            'experience',
            'country',
            'location',
            'education',
            'current_ctc',
            'expected_ctc',
            'profile_pic',
            'resume',
            'source_id',
            'created_at'
        ]);

        // Load relationships based on request
        if ($request->with_job_details || $request->with_time_logs) {
            $query->with([
                'jobs' => function ($jobQuery) {
                    $jobQuery->select([
                        'id',
                        'job_title',
                        'job_startdate',
                        'job_enddate',
                        'status',
                        'candidate_id',
                        'client_id',
                        'company_id'
                    ])->with(['client:id,name', 'shifts']);
                },
                'candidateEmployeeAssignments' => function ($assignQuery) use ($employeeId) {
                    $assignQuery->where('employee_id', $employeeId)
                        ->with(['employee:id,first_name,last_name,work_email']);
                }
            ]);

            if ($request->with_time_logs) {
                $query->with(['jobTimeLogs' => function ($logQuery) {
                    $logQuery->select([
                        'id',
                        'job_id',
                        'candidate_id',
                        'start_time',
                        'end_time',
                        'total_seconds',
                        'status',
                        'notes'
                    ])->orderBy('start_time', 'desc');
                }]);
            }
        }

        // Get candidates
        $candidates = $query->get();

        // Transform data with calculated fields
        $candidates->transform(function ($candidate) use ($request) {
            // Add calculated fields
            $candidate->total_assigned_jobs = $candidate->jobs ? $candidate->jobs->count() : 0;
            $candidate->active_jobs = $candidate->jobs ?
                $candidate->jobs->whereIn('status', ['assigned', 'confirmed', 'in_progress'])->count() : 0;

            // Calculate total work hours if time logs are loaded
            if ($candidate->jobTimeLogs) {
                $totalSeconds = $candidate->jobTimeLogs->where('status', 'completed')->sum('total_seconds');
                $candidate->total_work_hours = round($totalSeconds / 3600, 2);
                $candidate->last_work_date = $candidate->jobTimeLogs->where('status', 'completed')->max('start_time');
            }

            // Get current job
            $candidate->current_job = $candidate->jobs
                ? $candidate->jobs->whereIn('status', ['assigned', 'confirmed', 'in_progress'])->first()
                : null;

            // Format file URLs
            if ($candidate->profile_pic) {
                $candidate->profile_pic_url = $this->generateFileUrl($candidate->profile_pic);
            }
            if ($candidate->resume) {
                $candidate->resume_url = $this->generateFileUrl($candidate->resume);
            }

            return $candidate;
        });

        return $this->successResponse(
            $candidates,
            'Candidates assigned to employee retrieved successfully'
        );
    }

    /**
     * Get candidate work summary for dashboard
     */
    public function getCandidateWorkSummary(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'candidate_id' => 'required|exists:candidates,id',
            'period' => 'nullable|in:today,week,month,quarter,year,custom',
            'start_date' => 'nullable|date|required_if:period,custom',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $employeeId = $request->employee_id;
        $candidateId = $request->candidate_id;

        // Verify candidate is assigned to employee
        $isAssigned = CandidateEmployeeAssignment::where('employee_id', $employeeId)
            ->where('candidate_id', $candidateId)
            ->exists();

        if (!$isAssigned) {
            return $this->errorResponse('Candidate is not assigned to this employee', 403);
        }

        // Determine date range
        $dateRange = $this->getDateRangeForPeriod($request);

        // Query time logs
        $timeLogsQuery = JobTimeLog::where('candidate_id', $candidateId)
            ->where('status', 'completed');

        if ($dateRange) {
            $timeLogsQuery->whereBetween('start_time', [$dateRange['start'], $dateRange['end']]);
        }

        $timeLogs = $timeLogsQuery->get();

        // Calculate summary
        $totalSeconds = $timeLogs->sum('total_seconds');
        $totalHours = round($totalSeconds / 3600, 2);

        // Group by job
        $jobsSummary = [];
        foreach ($timeLogs->groupBy('job_id') as $jobId => $logs) {
            $job = Job::find($jobId);
            if ($job) {
                $jobSeconds = $logs->sum('total_seconds');
                $jobsSummary[] = [
                    'job_id' => $jobId,
                    'job_title' => $job->job_title,
                    'total_hours' => round($jobSeconds / 3600, 2),
                    'work_sessions' => $logs->count(),
                    'first_work_date' => $logs->min('start_time'),
                    'last_work_date' => $logs->max('end_time'),
                ];
            }
        }

        // Daily summary
        $dailySummary = [];
        foreach (
            $timeLogs->groupBy(function ($log) {
                return $log->start_time->format('Y-m-d');
            }) as $date => $dayLogs
        ) {
            $daySeconds = $dayLogs->sum('total_seconds');
            $dailySummary[] = [
                'date' => $date,
                'total_hours' => round($daySeconds / 3600, 2),
                'sessions' => $dayLogs->count(),
                'jobs_worked' => $dayLogs->groupBy('job_id')->count(),
            ];
        }

        // Get candidate details
        $candidate = Candidate::find($candidateId, [
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'designation'
        ]);

        return $this->successResponse([
            'candidate' => $candidate,
            'summary' => [
                'total_hours' => $totalHours,
                'total_sessions' => $timeLogs->count(),
                'total_jobs_worked' => $timeLogs->groupBy('job_id')->count(),
                'period' => $request->period ?? 'all',
                'date_range' => $dateRange,
            ],
            'jobs_summary' => $jobsSummary,
            'daily_summary' => $dailySummary,
            'recent_logs' => $timeLogs->take(10)->map(function ($log) {
                return [
                    'date' => $log->start_time->format('Y-m-d'),
                    'start_time' => $log->start_time->format('H:i'),
                    'end_time' => $log->end_time ? $log->end_time->format('H:i') : null,
                    'hours' => round($log->total_seconds / 3600, 2),
                    'job_title' => $log->job ? $log->job->job_title : null,
                    'notes' => $log->notes,
                ];
            }),
        ], 'Candidate work summary retrieved successfully');
    }

    /**
     * Get all candidates for the authenticated user's company.
     */
    public function listCandidates(Request $request)
    {
        $user = Auth::user();

        // Closure to generate public URLs for stored files
        $generateFileUrl = function (?string $filePath) {
            if (!$filePath) {
                return null;
            }
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));
            return url('api/v.1/files/' . $encodedPath);
        };

        // Start your base query scoped to the user's company
        $query = Candidate::where('company_id', $user->company_id);

        // If a search term is provided, filter across name, email & designation
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name',  'LIKE', "%{$search}%")
                    ->orWhere('email',      'LIKE', "%{$search}%")
                    ->orWhere('designation', 'LIKE', "%{$search}%");
            });
        }

        // Fetch & format
        $candidates = $query->get()->map(function ($candidate) use ($generateFileUrl) {
            $candidate->profile_pic = $generateFileUrl($candidate->profile_pic);
            $candidate->resume      = $generateFileUrl($candidate->resume);
            return $candidate;
        });

        return $this->successResponse(
            $candidates,
            'Candidate list retrieved successfully.'
        );
    }

    /**
     * Helper method to generate file URL
     */
    private function generateFileUrl($filePath)
    {
        if (!$filePath) {
            return null;
        }
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));
        return url('api/v.1/files/' . $encodedPath);
    }

    /**
     * Helper method to get date range for period
     */
    private function getDateRangeForPeriod(Request $request)
    {
        switch ($request->period) {
            case 'today':
                return [
                    'start' => now()->startOfDay(),
                    'end' => now()->endOfDay()
                ];
            case 'week':
                return [
                    'start' => now()->startOfWeek(),
                    'end' => now()->endOfWeek()
                ];
            case 'month':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth()
                ];
            case 'quarter':
                return [
                    'start' => now()->startOfQuarter(),
                    'end' => now()->endOfQuarter()
                ];
            case 'year':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear()
                ];
            case 'custom':
                return [
                    'start' => $request->start_date . ' 00:00:00',
                    'end' => $request->end_date . ' 23:59:59'
                ];
            default:
                return null;
        }
    }
}
