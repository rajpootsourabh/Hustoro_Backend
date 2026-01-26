<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_invoices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_id')->nullable()->constrained()->onDelete('set null');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft');

            // Billing details
            $table->string('billing_name');
            $table->string('billing_email');
            $table->string('billing_phone')->nullable();
            $table->text('billing_address')->nullable();

            // Invoice items
            $table->json('items'); // Store as JSON array
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);

            // Hours worked
            $table->decimal('total_hours', 8, 2);
            $table->decimal('hourly_rate', 10, 2);

            // Time period
            $table->date('period_start');
            $table->date('period_end');

            // Payment details
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->json('client_details')->nullable(); // Store client details as JSON
            $table->string('pdf_path')->nullable(); // Store PDF path

            // Add employee_id for tracking which employee created the invoice
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null');

            // Created by user - must be nullable for set null to work
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['candidate_id', 'invoice_date']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
