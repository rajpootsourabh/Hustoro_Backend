<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_title', 191);
            $table->date('job_startdate');
            $table->date('job_enddate');
            $table->text('job_description')->nullable();

            // Candidate assignment fields (can be null if job is not assigned yet)
            $table->foreignId('candidate_id')->nullable()->constrained('candidates');
            $table->enum('status', ['draft', 'assigned', 'confirmed', 'rejected', 'completed'])->default('draft');
            $table->text('notes')->nullable();

            // User references
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('assigned_by')->nullable()->constrained('users');

            // Timestamps
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('candidate_id');
            $table->index('created_by');
            $table->index('assigned_by');
            $table->index('status');
            $table->index('job_startdate');
            $table->index('job_enddate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // // Recreate the original tables if needed
        // Schema::create('jobs', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('job_title', 191);
        //     $table->date('job_date');
        //     $table->text('job_description')->nullable();
        //     $table->foreignId('created_by')->nullable()->constrained('users');
        //     $table->timestamps();
        // });
    }
};
