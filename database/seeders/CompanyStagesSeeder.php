<?php
// database/seeders/CompanyStagesSeeder.php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyStage;
use App\Models\StageDocument;
use Illuminate\Database\Seeder;

class CompanyStagesSeeder extends Seeder
{
    public function run()
    {
        $companies = Company::all();
        
        foreach ($companies as $company) {
            $this->createCompanyStages($company);
        }
    }

    private function createCompanyStages(Company $company)
    {
        $stages = [
            ['stage_id' => 1, 'name' => 'Sourced', 'order' => 1, 'documents' => []],
            ['stage_id' => 2, 'name' => 'Applied', 'order' => 2, 'documents' => []],
            ['stage_id' => 3, 'name' => 'Phone Screen', 'order' => 3, 'documents' => []],
            ['stage_id' => 4, 'name' => 'Assessment', 'order' => 4, 'documents' => []],
            ['stage_id' => 5, 'name' => 'Interview', 'order' => 5, 'documents' => []],
            ['stage_id' => 6, 'name' => 'Offer', 'order' => 6, 'documents' => []],
            ['stage_id' => 7, 'name' => 'Hired', 'order' => 7, 'documents' => [
                '1010', '1201', '1202', '1203', '1220', '1530', '1600', 
                '1720', '1740', '2900', '4000', 'I-9', 'W-4'
            ]],
        ];
        
        foreach ($stages as $stageData) {
            $companyStage = CompanyStage::create([
                'company_id' => $company->id,
                'stage_id' => $stageData['stage_id'],
                'name' => $stageData['name'],
                'stage_order' => $stageData['order'],
                'is_active' => true
            ]);

            // Add documents to stage
            $this->addDocumentsToStage($companyStage, $stageData['documents']);
        }
    }

    private function addDocumentsToStage(CompanyStage $companyStage, array $documentCodes)
    {
        foreach ($documentCodes as $order => $docCode) {
            $document = \App\Models\Document::where('code', $docCode)->first();
            if ($document) {
                StageDocument::create([
                    'company_stage_id' => $companyStage->id,
                    'document_id' => $document->id,
                    'is_required' => true,
                    'document_order' => $order + 1
                ]);
            }
        }
    }
}