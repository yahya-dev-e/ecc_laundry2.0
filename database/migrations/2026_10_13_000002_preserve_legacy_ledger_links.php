<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'legacy_booking_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_booking_id')->nullable();
            });
        }
        // Keep the original ID as historical evidence; never invent a replacement booking.
        DB::transaction(function () {
            DB::table('transactions')->whereNotNull('booking_id')->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('reservations')->whereColumn('reservations.id', 'transactions.booking_id');
            })->update(['legacy_booking_id' => DB::raw('booking_id'), 'booking_id' => null]);
        });
        $foreignKey = collect(Schema::getForeignKeys('transactions'))
            ->first(fn ($key) => $key['columns'] === ['booking_id']);
        if ($foreignKey && $foreignKey['foreign_table'] !== 'reservations') {
            throw new RuntimeException('Unexpected ledger foreign key after upgrade; inspect before proceeding.');
        }
        if (! $foreignKey) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('booking_id')->references('id')->on('reservations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restoring dangling references would corrupt the ledger; use a reviewed restore plan.');
    }
};
