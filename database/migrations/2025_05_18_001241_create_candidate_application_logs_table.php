<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidate_application_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('candidate_application_id')->constrained()->onDelete('cascade');

            // Numeric stage tracking: 1 = Sourced, 2 = Applied, etc.
            $table->unsignedTinyInteger('from_stage')->nullable(); // nullable for first entry
            $table->unsignedTinyInteger('to_stage'); // required

            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('changed_at')->useCurrent();
            $table->text('note')->nullable(); // optional note
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_application_logs');
    }
};
