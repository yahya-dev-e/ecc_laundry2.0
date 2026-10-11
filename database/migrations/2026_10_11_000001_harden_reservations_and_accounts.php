<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $emails = DB::table('users')->get(['id', 'email']);
        if ($emails->groupBy(fn ($user) => strtolower(trim($user->email)))->contains(fn ($group) => $group->count() > 1)) {
            throw new RuntimeException('Resolve case-insensitive duplicate email addresses before upgrading; no accounts were merged.');
        }
        foreach ($emails as $user) {
            DB::table('users')->where('id', $user->id)->update(['email' => strtolower(trim($user->email))]);
        }
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'session_version')) {
                $t->unsignedInteger('session_version')->default(0);
            }
            if (! Schema::hasColumn('users', 'weekly_limit_minutes')) {
                $t->unsignedInteger('weekly_limit_minutes')->nullable();
            }
        });
        Schema::table('reservations', function (Blueprint $t) {
            // MySQL commits DDL before the following index statement. Allow recovery
            // from the original overlong-index failure without dropping existing data.
            if (! Schema::hasColumn('reservations', 'quota_minutes')) {
                $t->unsignedInteger('quota_minutes')->default(60);
            }
            if (! Schema::hasColumn('reservations', 'quota_waived')) {
                $t->boolean('quota_waived')->default(false);
            }
            foreach (['started_at', 'completed_at', 'cancelled_at', 'released_at'] as $column) {
                if (! Schema::hasColumn('reservations', $column)) {
                    $t->timestamp($column)->nullable();
                }
            }
            if (! Schema::hasColumn('reservations', 'cancellation_reason')) {
                $t->string('cancellation_reason', 500)->nullable();
            }
            if (! Schema::hasIndex('reservations', ['machine_id', 'cancelled_at', 'released_at', 'start_time'])) {
                $t->index(['machine_id', 'cancelled_at', 'released_at', 'start_time'], 'reservations_availability_index');
            }
        });
        DB::table('reservations')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if ($row->start_time && $row->end_time) {
                    $minutes = max(1, (int) Carbon::parse($row->start_time)->diffInMinutes(Carbon::parse($row->end_time), true));
                    $values = ['quota_minutes' => $minutes];
                    // Legacy versions inferred completion from end_time. Preserve that historical
                    // classification; do not issue retroactive no-show refunds for old reservations.
                    if (Carbon::parse($row->end_time)->lt(now())) {
                        $values['completed_at'] = $row->end_time;
                    }
                    DB::table('reservations')->where('id', $row->id)->update($values);
                }
            }
        });
        Schema::table('machines', function (Blueprint $t) {
            $t->foreignId('active_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
        });
        // Preserve an identifiable already-running cycle during upgrade.
        DB::table('machines')->where('status', 'in-use')->orderBy('id')->each(function ($machine) {
            $reservation = DB::table('reservations')->where('machine_id', $machine->id)
                ->where('start_time', '<=', now())->where('end_time', '>=', now())
                ->orderByDesc('start_time')->first();
            if ($reservation) {
                DB::table('machines')->where('id', $machine->id)->update(['active_reservation_id' => $reservation->id]);
                DB::table('reservations')->where('id', $reservation->id)->update(['started_at' => $reservation->start_time]);
            }
        });

        $foreignKeys = Schema::getForeignKeys('transactions');
        foreach ($foreignKeys as $key) {
            if ($key['columns'] === ['booking_id'] && $key['foreign_table'] !== 'reservations') {
                Schema::disableForeignKeyConstraints();
                try {
                    Schema::table('transactions', function (Blueprint $t) {
                        $t->dropForeign(['booking_id']);
                        $t->foreign('booking_id')->references('id')->on('reservations')->nullOnDelete();
                    });
                } finally {
                    Schema::enableForeignKeyConstraints();
                }
            }
        }
        Schema::table('transactions', function (Blueprint $t) {
            $t->string('event_key')->nullable()->unique();
        });
        // Ledger amounts now have one exact unit: quota minutes.
        DB::table('transactions')->update(['amount' => DB::raw('amount * 60')]);
        Schema::create('password_reset_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->timestamp('created_at');
            $t->timestamp('expires_at')->index();
            $t->timestamp('consumed_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This data-preserving upgrade requires a reviewed restore plan; automatic rollback is disabled.');
    }
};
