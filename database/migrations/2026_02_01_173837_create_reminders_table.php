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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation (Client, Project, Invoice, or null for standalone)
            $table->nullableMorphs('remindable');

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Recurrence (null = one-time)
            $table->enum('recurrence', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();

            // Priority for sorting
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');

            // Auto-generated reminders
            $table->boolean('is_system')->default(false);
            $table->string('system_type')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'due_at']);
            $table->index(['user_id', 'completed_at', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
