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

        $query = Machine::query()->orderBy('code');

        if ($typeFilter && in_array($typeFilter, ['washer', 'dryer'])) {
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
        $machine->load(['bookings' => function ($query) {
            $query->upcoming()->orderBy('start_time')->limit(10);
        }]);

        $availableSlots = $this->scheduler->getAvailableSlots($machine, Carbon::today());

        return view('bookings.create', [
            'selectedMachine' => $machine,
            'availableSlots' => $availableSlots,
            'machines' => Machine::where('status', MachineStatus::AVAILABLE)->get(),
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
            'code' => $machine->code,
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

        $machine->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $machine->notes,
            'last_maintenance_at' => $validated['status'] === MachineStatus::MAINTENANCE->value ? Carbon::now() : $machine->last_maintenance_at,
        ]);

        return back()->with('success', "Machine {$machine->code} status changed to {$machine->status->label()}.");
    }
}
