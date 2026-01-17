<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('path')->after('description')->nullable();
            $table->string('file_name')->after('path')->nullable();
            $table->string('mime_type')->after('file_name')->nullable();
            $table->integer('file_size')->after('mime_type')->nullable();
            $table->boolean('is_fillable')->after('file_size')->default(false);
            $table->boolean('is_active')->after('is_fillable')->default(true);
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['path', 'file_name', 'mime_type', 'file_size', 'is_fillable', 'is_active']);
        });
    }
};