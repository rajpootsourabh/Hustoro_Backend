<?php
// database/migrations/2024_01_01_000008_fix_company_stage_foreign_key_on_delete.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixCompanyStageForeignKeyOnDelete extends Migration
{
    public function up()
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            // Drop the dangerous foreign key
            $table->dropForeign(['company_stage_id']);
            
            // Re-add with safe onDelete behavior
            $table->foreign('company_stage_id')
                  ->references('id')
                  ->on('company_stages')
                  ->onDelete('set null'); // Safe: sets to NULL instead of deleting
        });
    }

    public function down()
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->dropForeign(['company_stage_id']);
            $table->foreign('company_stage_id')
                  ->references('id')
                  ->on('company_stages')
                  ->onDelete('cascade'); // Revert to original
        });
    }
}