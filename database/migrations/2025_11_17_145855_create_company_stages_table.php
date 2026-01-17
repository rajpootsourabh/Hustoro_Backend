<?php
// database/migrations/2024_01_01_000002_create_company_stages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyStagesTable extends Migration
{
    public function up()
    {
        Schema::create('company_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Custom stage name defined by company
            $table->string('type')->default('hiring'); // hiring, onboarding, etc.
            $table->integer('stage_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']); // Prevent duplicate stage names per company
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_stages');
    }
}