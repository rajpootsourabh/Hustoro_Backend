<?php

// database/migrations/xxxx_create_shifts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShiftsTable extends Migration
{
    public function up()
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->time('start_time_utc');
            $table->time('end_time_utc');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // REMOVE THIS SECTION - Don't insert default records
        // Or keep it but make it idempotent
    }

    public function down()
    {
        Schema::dropIfExists('shifts');
    }
}