<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Http\Requests\UpdateMachineStatusRequest;
use App\Models\Machine;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function __construct(
        protected MachineSchedulerService $scheduler
    ) {}

    /**
     * Display a listing of laundry machines.
     */
    public function index(Request $request): View
    {
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');

        // Order strictly by name (never by code)
        $query = Machine::query()->orderBy('name');

        if ($typeFilter && in_array($typeFilter, ['washing-machine', 'dryer'])) {
            $query->where('type', $typeFilter);
        }

        if ($statusFilter && MachineStatus::tryFrom($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        $machines = $query->get();
        $metrics = $this->scheduler->getMachineMetrics();

        return view('dashboard', [
            'machines' => $machines,
            'metrics' => $metrics,
            'currentType' => $typeFilter,
            'currentStatus' => $statusFilter,
        ]);
    }

    /**
     * Display machine details and upcoming schedule.
     */
    public function show(Machine $machine): View
    {
        $machine->load(['reservations' => function ($query) {
            $query->where('start_time', '>', Carbon::now())->orderBy('start_time')->limit(10);
        }]);

        $availableSlots = $this->scheduler->getAvailableSlots($machine, Carbon::today());

        return view('bookings.create', [
            'selectedMachine' => $machine,
            'availableSlots' => $availableSlots,
            'machines' => Machine::orderBy('name')->get(),
        ]);
    }

    /**
     * Get available booking slots as JSON for dynamic date pickers.
     */
    public function slots(Machine $machine, Request $request): JsonResponse
    {
        $dateStr = $request->query('date', Carbon::today()->toDateString());
        $date = Carbon::parse($dateStr);

        $slots = $this->scheduler->getAvailableSlots($machine, $date);

        return response()->json([
            'machine_id' => $machine->id,
            'name' => $machine->name,
            'date' => $date->toDateString(),
            'slots' => $slots,
        ]);
    }

    /**
     * Update machine operational status (Staff / Admin).
     */
    public function updateStatus(UpdateMachineStatusRequest $request, Machine $machine): RedirectResponse
    {
        $validated = $request->validated();

        // Strictly update columns present in the MySQL schema (status)
        $machine->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', "Machine {$machine->name} status changed to {$machine->status->label()}.");
    }
}
