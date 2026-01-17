<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyStage;
use App\Models\Document;
use App\Models\StageDocument;
use App\Models\CandidateApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CompanyStageController extends Controller
{
    /**
     * Get all stages for a company
     */
    public function getCompanyStages($companyId): JsonResponse
    {
        $company = Company::with(['companyStages.documents'])->findOrFail($companyId);
        $availableDocuments = Document::all();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company,
                // 'stages' => $company->companyStages,
                // 'available_documents' => $availableDocuments
            ]
        ]);
    }

    /**
     * Create custom stages for a company (used during registration)
     */
    public function createCompanyStages(Company $company, array $stagesData)
    {
        foreach ($stagesData as $stageData) {
            $this->createCompanyStage($company, $stageData);
        }
    }

    /**
     * Create a single company stage with documents
     */
    private function createCompanyStage(Company $company, array $stageData)
    {
        $stage = CompanyStage::create([
            'company_id' => $company->id,
            'name' => $stageData['name'],
            'type' => $stageData['type'],
            'stage_order' => $stageData['order'],
            'is_active' => true
        ]);

        if (isset($stageData['documents']) && is_array($stageData['documents'])) {
            foreach ($stageData['documents'] as $documentData) {
                $document = Document::where('code', $documentData['code'])->first();
                if ($document) {
                    StageDocument::create([
                        'company_stage_id' => $stage->id,
                        'document_id' => $document->id,
                        'is_required' => $documentData['isRequired'],
                        'document_order' => $documentData['order']
                    ]);
                }
            }
        }

        return $stage;
    }

    /**
     * Create default stages for a company
     */
    public function createDefaultStages(Company $company)
    {
        $defaultStages = [
            [
                'name' => 'Pre-Hire',
                'type' => 'hiring',
                'order' => 1,
                'documents' => [
                    ['code' => '1020', 'isRequired' => true, 'order' => 1],
                    ['code' => '1021', 'isRequired' => true, 'order' => 2],
                    ['code' => '1050', 'isRequired' => true, 'order' => 3],
                    ['code' => '1060', 'isRequired' => true, 'order' => 4],
                    ['code' => '1070', 'isRequired' => true, 'order' => 5],
                    ['code' => '1204', 'isRequired' => true, 'order' => 6]
                ]
            ],
            [
                'name' => 'Onboarding',
                'type' => 'onboarding',
                'order' => 2,
                'documents' => [
                    ['code' => '1010', 'isRequired' => true, 'order' => 1],
                    ['code' => '1201', 'isRequired' => true, 'order' => 2],
                    ['code' => '1202', 'isRequired' => true, 'order' => 3],
                    ['code' => '1203', 'isRequired' => true, 'order' => 4],
                    ['code' => '1220', 'isRequired' => true, 'order' => 5],
                    ['code' => '1530', 'isRequired' => true, 'order' => 6],
                    ['code' => '1600', 'isRequired' => true, 'order' => 7],
                    ['code' => '1720', 'isRequired' => true, 'order' => 8],
                    ['code' => '1740', 'isRequired' => true, 'order' => 9],
                    ['code' => '2900', 'isRequired' => true, 'order' => 10],
                    ['code' => '4000', 'isRequired' => true, 'order' => 11],
                    ['code' => 'I-9', 'isRequired' => true, 'order' => 12],
                    ['code' => 'W-4', 'isRequired' => true, 'order' => 13]
                ]
            ]
        ];

        $this->createCompanyStages($company, $defaultStages);
    }

    /**
     * Create a new stage for a company (API endpoint)
     */
    public function createStage(Request $request, $companyId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:hiring,onboarding,custom',
            'stage_order' => 'required|integer',
            'documents' => 'sometimes|array',
            'documents.*.code' => ['required_with:documents', 'exists:documents,code'],
            'documents.*.isRequired' => ['required_with:documents', 'boolean'],
            'documents.*.order' => ['required_with:documents', 'integer', 'min:1'],
        ]);

        $company = Company::findOrFail($companyId);

        DB::transaction(function () use ($company, $request) {
            $stageData = [
                'name' => $request->name,
                'type' => $request->type,
                'order' => $request->stage_order,
                'documents' => $request->documents ?? []
            ];
            $this->createCompanyStage($company, $stageData);
        });

        return response()->json(['success' => true, 'message' => 'Stage created successfully']);
    }

    /**
     * Update a stage (SAFE)
     */
    public function updateStage(Request $request, $companyId, $stageId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:hiring,onboarding,custom',
            'stage_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean'
        ]);

        $stage = CompanyStage::where('company_id', $companyId)->where('id', $stageId)->firstOrFail();

        // Prevent disabling stage with active candidates
        if (isset($request->is_active) && $request->is_active === false) {
            $activeCount = $stage->candidateApplications()->where('status', 'Active')->count();
            if ($activeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot disable stage with {$activeCount} active candidate(s)"
                ], 422);
            }
        }

        $stage->update($request->only(['name', 'type', 'stage_order', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Stage updated successfully',
            'data' => $stage
        ]);
    }

    /**
     * Update stage documents (SAFE)
     */
    public function updateStageDocuments(Request $request, $companyId, $stageId): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array',
            'documents.*.code' => ['required', 'exists:documents,code'],
            'documents.*.isRequired' => ['required', 'boolean'],
            'documents.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $stage = CompanyStage::where('company_id', $companyId)->where('id', $stageId)->firstOrFail();
        $activeCount = $stage->candidateApplications()->where('status', 'Active')->count();

        DB::transaction(function () use ($stage, $request) {
            $stage->stageDocuments()->delete();
            foreach ($request->documents as $documentData) {
                $document = Document::where('code', $documentData['code'])->first();
                if ($document) {
                    StageDocument::create([
                        'company_stage_id' => $stage->id,
                        'document_id' => $document->id,
                        'is_required' => $documentData['isRequired'],
                        'document_order' => $documentData['order']
                    ]);
                }
            }
        });

        $message = 'Stage documents updated successfully';
        if ($activeCount > 0) {
            $message .= ". Note: {$activeCount} active candidate(s) affected.";
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Delete a stage (EXTRA SAFE - with your current dangerous migration)
     */
    public function deleteStage($companyId, $stageId): JsonResponse
    {
        $stage = CompanyStage::where('company_id', $companyId)->where('id', $stageId)->firstOrFail();

        // EXTRA PROTECTION because your migration has onDelete('cascade')
        $totalApplications = $stage->candidateApplications()->count();
        $activeApplications = $stage->candidateApplications()->where('status', 'Active')->count();

        if ($totalApplications > 0) {
            return response()->json([
                'success' => false,
                'message' => "DANGER: Cannot delete stage. This would DELETE {$totalApplications} candidate application(s) ({$activeApplications} active). This action is blocked for safety.",
                'affected_applications' => $totalApplications,
                'active_applications' => $activeApplications
            ], 422);
        }

        $stage->delete();
        return response()->json(['success' => true, 'message' => 'Stage deleted successfully']);
    }

    /**
     * Reorder stages
     */
    public function reorderStages(Request $request, $companyId): JsonResponse
    {
        $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:company_stages,id',
            'stages.*.order' => 'required|integer'
        ]);

        $company = Company::findOrFail($companyId);

        DB::transaction(function () use ($company, $request) {
            foreach ($request->stages as $stageData) {
                CompanyStage::where('company_id', $company->id)
                    ->where('id', $stageData['id'])
                    ->update(['stage_order' => $stageData['order']]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Stages reordered successfully']);
    }

    /**
     * Get stage safety info
     */
    public function getStageSafetyInfo($companyId, $stageId): JsonResponse
    {
        $stage = CompanyStage::where('company_id', $companyId)->where('id', $stageId)->firstOrFail();

        $safetyInfo = [
            'total_applications' => $stage->candidateApplications()->count(),
            'active_applications' => $stage->candidateApplications()->where('status', 'Active')->count(),
            'can_delete' => $stage->candidateApplications()->count() === 0,
            'can_disable' => $stage->candidateApplications()->where('status', 'Active')->count() === 0,
            'warning' => 'WARNING: Stage deletion would DELETE all candidate applications due to database cascade setting.'
        ];

        return response()->json(['success' => true, 'data' => $safetyInfo]);
    }
}