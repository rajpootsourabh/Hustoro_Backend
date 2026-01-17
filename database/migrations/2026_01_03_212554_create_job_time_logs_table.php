<?php
// database/migrations/[timestamp]_create_job_time_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('job_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('total_seconds')->default(0);
            $table->integer('cumulative_seconds')->default(0);
            $table->enum('status', ['in_progress', 'completed', 'paused'])->default('in_progress');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['job_id', 'candidate_id']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_time_logs');
    }
};