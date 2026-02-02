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
        Schema::create('recurring_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_task_id')->constrained()->cascadeOnDelete();

            $table->date('due_date');
            $table->string('action'); // reminder_created, manually_completed, skipped
            $table->foreignId('reminder_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['recurring_task_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_task_logs');
    }
};
