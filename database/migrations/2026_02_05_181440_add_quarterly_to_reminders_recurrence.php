<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE reminders MODIFY COLUMN recurrence ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE reminders MODIFY COLUMN recurrence ENUM('daily', 'weekly', 'monthly', 'yearly') NULL");
    }
};
