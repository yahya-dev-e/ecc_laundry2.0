<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Matches the exact frozen MySQL 'machines' table schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('machines')) {
            Schema::create('machines', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('type', ['washing-machine', 'dryer']);
                $table->enum('status', ['reserved', 'in-use', 'available', 'under maintenance', 'out of order'])->default('available');
                $table->string('color')->nullable();
                $table->timestamps();

                $table->index(['type', 'status']);
                $table->index('name');
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
