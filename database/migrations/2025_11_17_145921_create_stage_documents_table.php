<?php
// database/migrations/2024_01_01_000003_create_stage_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStageDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('stage_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_stage_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_id')->constrained();
            $table->boolean('is_required')->default(true);
            $table->integer('document_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stage_documents');
    }
}