<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Machine;
use App\Models\Reservation;
use App\Services\BookingService;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected MachineSchedulerService $scheduler
    ) {}

    /**
     * Display a listing of the user's laundry reservations and transactions.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Active reservations currently in progress (start_time <= now <= end_time)
        $activeBookings = $user ? $user->reservations()
            ->with('machine')
            ->inProgress()
            ->latest('start_time')
            ->get() : collect();

        // Upcoming reservations (start_time > now)
        $upcomingBookings = $user ? $user->reservations()
            ->with('machine')
            ->upcoming()
            ->orderBy('start_time')
            ->get() : collect();

        // Completed reservations in the past (end_time < now)
        $pastBookings = $user ? $user->reservations()
            ->with('machine')
            ->completed()
            ->latest('start_time')
            ->paginate(10) : collect();

        $recentTransactions = $user ? $user->transactions()
            ->latest()
            ->limit(8)
            ->get() : collect();

        return view('bookings.index', [
            'activeBookings' => $activeBookings,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Show form to reserve a new laundry machine slot.
     */
    public function create(Request $request): View
    {
        $selectedMachineId = $request->query('machine_id');
        $selectedMachine = $selectedMachineId ? Machine::find($selectedMachineId) : null;

        $machines = Machine::where('status', MachineStatus::AVAILABLE)
            ->orWhere('id', $selectedMachineId)
            ->orderBy('code')
            ->get();

        $availableSlots = [];
        if ($selectedMachine) {
            $availableSlots = $this->scheduler->getAvailableSlots($selectedMachine, Carbon::today());
        }

        return view('bookings.create', [
            'machines' => $machines,
            'selectedMachine' => $selectedMachine,
            'availableSlots' => $availableSlots,
        ]);
    }

    /**
     * Store a newly created reservation in storage.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $machine = Machine::findOrFail($validated['machine_id']);
        $startTime = Carbon::parse($validated['start_time']);

        try {
            $booking = $this->bookingService->createBooking(
                user: $request->user(),
                machine: $machine,
                startTime: $startTime,
                durationMinutes: $validated['duration_minutes'] ?? null
            );

            return redirect()->route('bookings.index')
                ->with('success', "Reservation confirmed for {$machine->code} at {$startTime->format('M d, H:i')}!");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Start the washing/drying cycle on the machine.
     */
    public function start(Request $request, Reservation $booking): RedirectResponse
    {
        if ($booking->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $this->bookingService->startCycle($booking);

            return redirect()->route('dashboard')
                ->with('success', "Cycle started on {$booking->machine->code}! The timer is now running.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an existing reservation and release slot.
     */
    public function cancel(Request $request, Reservation $booking): RedirectResponse
    {
        if ($booking->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $reason = $request->input('reason', 'Cancelled by user');

        try {
            $this->bookingService->cancelBooking($booking, $reason);

            return redirect()->route('bookings.index')
                ->with('success', "Reservation #{$booking->id} cancelled successfully.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
