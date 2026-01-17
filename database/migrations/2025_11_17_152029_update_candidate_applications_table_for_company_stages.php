<?php
// database/migrations/2024_01_01_000005_update_candidate_applications_table_for_company_stages.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCandidateApplicationsTableForCompanyStages extends Migration
{
    public function up()
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            // Add company_stage_id and make stage_id nullable
            $table->foreignId('company_stage_id')->nullable()->after('stage_id')
                  ->constrained('company_stages')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->dropForeign(['company_stage_id']);
            $table->dropColumn('company_stage_id');
        });
    }
}