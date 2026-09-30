<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Matches the exact existing MySQL 'reservations' table schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('end_time')->nullable();
                $table->boolean('notified_start')->default(false);
                $table->boolean('notified_end')->default(false);
                $table->integer('weekly_session_limit_remaining')->default(8);
                $table->timestamps();

                $table->index(['machine_id', 'start_time', 'end_time']);
                $table->index(['user_id', 'start_time']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Frozen database schema: do not drop existing table
    }
};
