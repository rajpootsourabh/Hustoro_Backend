<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            if (Schema::hasColumn('candidate_applications', 'stage_id')) {
                $table->dropColumn('stage_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('stage_id')->default(1);
        });
    }
};
