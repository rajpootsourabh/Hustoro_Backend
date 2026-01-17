<?php
// database/migrations/[timestamp]_create_job_shifts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('job_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['job_id', 'shift_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_shifts');
    }
};