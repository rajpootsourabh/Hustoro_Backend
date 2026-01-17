<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyStage;

class JobApplicationStatsController extends Controller
{
    public function getApplicationCountsByStage(): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or company not set'
            ], 403);
        }

        // Fetch active company stages for this company
        $companyStages = CompanyStage::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('stage_order')
            ->get(['id', 'name', 'stage_order']);

        // If no stages are configured, return empty results
        if ($companyStages->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'No stages configured for this company'
            ]);
        }

        // Create a mapping of stage IDs to names
        $stageMap = $companyStages->pluck('name', 'id')->toArray();

        // Get application counts by company_stage_id
        $results = DB::table('job_posts as jp')
            ->leftJoin('candidate_applications as ca', function($join) use ($user) {
                $join->on('ca.job_post_id', '=', 'jp.id')
                     ->where('ca.status', 'Active'); // Only count active applications
            })
            ->select(
                'jp.id as job_id',
                'jp.job_title',
                'ca.company_stage_id',
                DB::raw('COUNT(ca.id) as count')
            )
            ->where('jp.company_id', $user->company_id)
            ->groupBy('jp.id', 'jp.job_title', 'ca.company_stage_id')
            ->get();

        // Transform data
        $grouped = [];
        foreach ($results as $row) {
            $jobId = $row->job_id;

            if (!isset($grouped[$jobId])) {
                $grouped[$jobId] = [
                    'job_id' => $jobId,
                    'job_title' => $row->job_title,
                    'total' => 0,
                    'stages' => [],
                ];

                // Initialize all stages with 0 count
                foreach ($stageMap as $stageId => $stageName) {
                    $grouped[$jobId]['stages'][$stageName] = 0;
                }
            }

            $stageName = $stageMap[$row->company_stage_id] ?? 'Unknown';

            $grouped[$jobId]['stages'][$stageName] = (int)$row->count;
            $grouped[$jobId]['total'] += (int)$row->count;
        }

        // Handle jobs with no applications (ensure they appear in results)
        $allJobs = DB::table('job_posts')
            ->where('company_id', $user->company_id)
            ->get(['id', 'job_title']);

        foreach ($allJobs as $job) {
            if (!isset($grouped[$job->id])) {
                $grouped[$job->id] = [
                    'job_id' => $job->id,
                    'job_title' => $job->job_title,
                    'total' => 0,
                    'stages' => [],
                ];

                // Initialize all stages with 0 count
                foreach ($stageMap as $stageId => $stageName) {
                    $grouped[$job->id]['stages'][$stageName] = 0;
                }
            }
        }

        // Convert to array and reindex
        $finalData = array_values($grouped);

        return response()->json([
            'status' => 'success',
            'data' => $finalData,
            'stages_metadata' => $companyStages->map(function($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order' => $stage->stage_order
                ];
            })
        ]);
    }

    /**
     * Get application counts by stage for a specific job
     */
    public function getApplicationCountsByJob($jobId): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or company not set'
            ], 403);
        }

        // Verify job belongs to user's company
        $job = DB::table('job_posts')
            ->where('id', $jobId)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found or access denied'
            ], 404);
        }

        // Fetch active company stages for this company
        $companyStages = CompanyStage::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('stage_order')
            ->get(['id', 'name', 'stage_order']);

        if ($companyStages->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'job_id' => $jobId,
                    'job_title' => $job->job_title,
                    'total' => 0,
                    'stages' => [],
                ]
            ]);
        }

        // Create a mapping of stage IDs to names
        $stageMap = $companyStages->pluck('name', 'id')->toArray();

        // Get application counts for this specific job
        $results = DB::table('candidate_applications as ca')
            ->select(
                'ca.company_stage_id',
                DB::raw('COUNT(ca.id) as count')
            )
            ->where('ca.job_post_id', $jobId)
            ->where('ca.status', 'Active')
            ->groupBy('ca.company_stage_id')
            ->get();

        // Initialize stages with 0 counts
        $stagesData = [];
        foreach ($stageMap as $stageId => $stageName) {
            $stagesData[$stageName] = 0;
        }

        $total = 0;

        // Update counts from query results
        foreach ($results as $row) {
            $stageName = $stageMap[$row->company_stage_id] ?? 'Unknown';
            $stagesData[$stageName] = (int)$row->count;
            $total += (int)$row->count;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'job_id' => $jobId,
                'job_title' => $job->job_title,
                'total' => $total,
                'stages' => $stagesData,
            ],
            'stages_metadata' => $companyStages->map(function($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order' => $stage->stage_order
                ];
            })
        ]);
    }

    /**
     * Get overall application statistics for the company
     */
    public function getCompanyWideStats(): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or company not set'
            ], 403);
        }

        // Fetch active company stages
        $companyStages = CompanyStage::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('stage_order')
            ->get(['id', 'name', 'stage_order']);

        if ($companyStages->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_applications' => 0,
                    'stages' => [],
                    'jobs_count' => 0
                ]
            ]);
        }

        $stageMap = $companyStages->pluck('name', 'id')->toArray();

        // Get total application counts by stage
        $stageCounts = DB::table('candidate_applications as ca')
            ->join('job_posts as jp', 'ca.job_post_id', '=', 'jp.id')
            ->select(
                'ca.company_stage_id',
                DB::raw('COUNT(ca.id) as count')
            )
            ->where('jp.company_id', $user->company_id)
            ->where('ca.status', 'Active')
            ->groupBy('ca.company_stage_id')
            ->get();

        // Get total jobs count
        $jobsCount = DB::table('job_posts')
            ->where('company_id', $user->company_id)
            ->count();

        // Initialize stages data
        $stagesData = [];
        $totalApplications = 0;

        foreach ($stageMap as $stageId => $stageName) {
            $stagesData[$stageName] = 0;
        }

        // Update counts
        foreach ($stageCounts as $row) {
            $stageName = $stageMap[$row->company_stage_id] ?? 'Unknown';
            $stagesData[$stageName] = (int)$row->count;
            $totalApplications += (int)$row->count;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_applications' => $totalApplications,
                'jobs_count' => $jobsCount,
                'stages' => $stagesData,
            ],
            'stages_metadata' => $companyStages->map(function($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order' => $stage->stage_order
                ];
            })
        ]);
    }
}