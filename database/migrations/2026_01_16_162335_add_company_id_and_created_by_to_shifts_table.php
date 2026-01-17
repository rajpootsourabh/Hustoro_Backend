<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {

            // Company relation
            $table->foreignId('company_id')
                  ->nullable()
                  ->after('id')
                  ->constrained()
                  ->onDelete('cascade');

            // User who created the shift
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('users')
                  ->onDelete('set null');

            // Indexes
            $table->index('company_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['created_by']);

            $table->dropColumn(['company_id', 'created_by']);
        });
    }
};
