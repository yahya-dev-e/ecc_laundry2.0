<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Http\Requests\UpdateMachineStatusRequest;
use App\Models\Machine;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MachineController extends Controller
{
    public function __construct(protected MachineSchedulerService $scheduler) {}

    public function index()
    {
        return view('admin.machines', ['machines' => Machine::orderBy('name')->paginate(25)]);
    }

    public function slots(Machine $machine, Request $request)
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['machine_id' => $machine->id, 'name' => $machine->name,
            'date' => $data['date'] ?? now()->toDateString(), 'timezone' => config('app.timezone'),
            'remaining_minutes' => $request->user()->weeklyRemainingMinutes(Carbon::parse($data['date'] ?? now()->toDateString())),
            'max_new_bookings' => max(0, config('laundry.max_simultaneous_reservations', 3) - $request->user()->reservations()->activeOrUpcoming()->count()),
            'slots' => $this->scheduler->getAvailableSlots($machine, Carbon::parse($data['date'] ?? now()->toDateString()))])->header('Cache-Control', 'no-store');
    }

    public function updateStatus(UpdateMachineStatusRequest $request, Machine $machine)
    {
        $status = MachineStatus::from($request->validated('status'));
        DB::transaction(function () use ($machine, $status) {
            $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            if ($machine->active_reservation_id) {
                throw ValidationException::withMessages(['status' => 'Un cycle est encore en cours. Attendez sa fin avant de changer l’état.']);
            }
            $machine->update(['status' => $status]);
        }, 5);

        return back()->with('success', 'État de la machine mis à jour.');
    }
}
