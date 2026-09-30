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
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('washer'); // washer, dryer
            $table->string('status')->default('available'); // available, reserved, in_use, maintenance, out_of_order
            $table->decimal('capacity_kg', 4, 1)->default(8.0);
            $table->unsignedInteger('cost_per_cycle')->default(2);
            $table->unsignedInteger('default_duration_minutes')->default(45);
            $table->string('location')->nullable();
            $table->timestamp('current_cycle_ends_at')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
