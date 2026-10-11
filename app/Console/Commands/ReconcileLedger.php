<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileLedger extends Command
{
    protected $signature = 'laundry:reconcile-ledger {--apply : Insert only unambiguous missing charge/refund entries}';

    protected $description = 'Review legacy accounting; dry run by default; never delete or guess duplicate entries';

    public function handle(): int
    {
        $missing = 0;
        $ambiguous = 0;
        Reservation::orderBy('id')->chunkById(200, function ($rows) use (&$missing, &$ambiguous) {
            foreach ($rows as $reservation) {
                DB::transaction(function () use ($reservation, &$missing, &$ambiguous) {
                    User::whereKey($reservation->user_id)->lockForUpdate()->firstOrFail();
                    Machine::whereKey($reservation->machine_id)->lockForUpdate()->firstOrFail();
                    $r = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                    $types = ['booking_charge' => [-$r->quota_minutes, 'charge:'.$r->id]];
                    if ($r->cancelled_at || $r->released_at) {
                        $types['refund'] = [$r->quota_minutes, 'refund:'.$r->id];
                    }
                    foreach ($types as $type => [$amount, $key]) {
                        $entries = Transaction::where('booking_id', $r->id)->where('type', $type)->get();
                        if ($entries->count() > 1 || ($entries->isNotEmpty() && ((int) $entries->first()->amount !== $amount || $entries->first()->user_id !== $r->user_id))) {
                            $ambiguous++;
                            $this->warn("Review reservation #{$r->id}, {$type}; no automatic change.");

                            continue;
                        }
                        if ($entries->isNotEmpty()) {
                            continue;
                        }
                        $missing++;
                        $this->line("Missing {$type}: reservation #{$r->id}, {$amount} minutes");
                        if ($this->option('apply')) {
                            Transaction::create([
                                'booking_id' => $r->id, 'user_id' => $r->user_id, 'type' => $type,
                                'amount' => $amount, 'event_key' => $key, 'description' => 'Reviewed legacy reconciliation',
                            ]);
                        }
                    }
                }, 5);
            }
        });
        $orphans = DB::table('transactions')->whereNotNull('booking_id')->whereNotIn('booking_id', Reservation::select('id'))->count();
        $this->info("Missing: {$missing}; ambiguous: {$ambiguous}; orphan entries: {$orphans}.");

        return $ambiguous || $orphans ? self::FAILURE : self::SUCCESS;
    }
}
