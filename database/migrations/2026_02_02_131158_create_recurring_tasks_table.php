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
        Schema::create('recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Scheduling
            $table->string('frequency'); // weekly, monthly, quarterly, yearly
            $table->date('next_due_at');
            $table->date('last_run_at')->nullable();
            $table->date('started_at')->nullable();
            $table->date('ends_at')->nullable();

            // Billing info (optional)
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('billing_notes')->nullable();

            // Status
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'active', 'next_due_at']);
            $table->index(['client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_tasks');
    }
};
