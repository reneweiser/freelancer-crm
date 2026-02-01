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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Basic info
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();

            // Pricing
            $table->string('type')->default('fixed');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();

            // Workflow status
            $table->string('status')->default('draft');

            // Offer details
            $table->date('offer_date')->nullable();
            $table->date('offer_valid_until')->nullable();
            $table->timestamp('offer_sent_at')->nullable();
            $table->timestamp('offer_accepted_at')->nullable();

            // Project dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
