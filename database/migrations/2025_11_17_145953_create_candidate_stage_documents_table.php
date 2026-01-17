<?php
// database/migrations/2024_01_01_000004_create_candidate_stage_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateStageDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('candidate_stage_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_id')->constrained();
            $table->string('file_path', 500)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('candidate_stage_documents');
    }
}