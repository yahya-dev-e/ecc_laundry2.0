<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Models\Machine;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected MachineSchedulerService $scheduler
    ) {}

    /**
     * Display the main ECC Laundry real-time dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. Fetch user's currently active cycles (time-derived: start_time <= now <= end_time)
        $activeCycles = $user ? $user->reservations()
            ->with('machine')
            ->inProgress()
            ->get() : collect();

        // 2. Fetch user's next upcoming reservation (time-derived: start_time > now)
        $nextBooking = $user ? $user->reservations()
            ->with('machine')
            ->upcoming()
            ->orderBy('start_time')
            ->first() : null;

        // 3. Filter machines by type or status
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');

        $machinesQuery = Machine::query()->orderBy('code');

        if ($typeFilter && in_array($typeFilter, ['washer', 'dryer'])) {
            $machinesQuery->where('type', $typeFilter);
        }

        if ($statusFilter && MachineStatus::tryFrom($statusFilter)) {
            $machinesQuery->where('status', $statusFilter);
        }

        $machines = $machinesQuery->get();
        $metrics = $this->scheduler->getMachineMetrics();

        return view('dashboard', [
            'user' => $user,
            'activeCycles' => $activeCycles,
            'nextBooking' => $nextBooking,
            'machines' => $machines,
            'metrics' => $metrics,
            'currentType' => $typeFilter,
            'currentStatus' => $statusFilter,
        ]);
    }
}
