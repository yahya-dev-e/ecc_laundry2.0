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
        $now = Carbon::now();

        // Active reservations currently in progress: start_time <= now AND end_time >= now
        $activeBookings = $user ? $user->reservations()
            ->with('machine')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->latest('start_time')
            ->get() : collect();

        // Upcoming reservations: start_time > now
        $upcomingBookings = $user ? $user->reservations()
            ->with('machine')
            ->where('start_time', '>', $now)
            ->orderBy('start_time')
            ->get() : collect();

        // Completed reservations in the past: end_time < now
        $pastBookings = $user ? $user->reservations()
            ->with('machine')
            ->where('end_time', '<', $now)
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

        // Fetch all campus machines (including ML3-PE) ordered strictly by name so users can reserve future open slots
        $machines = Machine::orderBy('name')->get();

        $reservations = Reservation::where('start_time', '>=', Carbon::today()->startOfDay())
            ->where('start_time', '<=', Carbon::today()->addDays(8)->endOfDay())
            ->get(['machine_id', 'start_time', 'end_time'])
            ->map(function ($r) {
                return [
                    'machine_id' => $r->machine_id,
                    'start' => $r->start_time?->format('Y-m-d H:i'),
                    'end' => $r->end_time?->format('Y-m-d H:i'),
                    'date' => $r->start_time?->format('Y-m-d'),
                ];
            });

        return view('bookings.create', [
            'machines' => $machines,
            'selectedMachine' => $selectedMachine,
            'reservations' => $reservations,
        ]);
    }

    /**
     * Store a newly created reservation in storage.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $machine = Machine::findOrFail($validated['machine_id']);
        
        $startTimes = [];
        if (!empty($validated['start_times'])) {
            $startTimes = array_map(fn($t) => Carbon::parse($t), $validated['start_times']);
        } elseif (!empty($validated['start_time'])) {
            $startTimes = [Carbon::parse($validated['start_time'])];
        }

        if (empty($startTimes)) {
            return back()->withInput()->with('error', 'Veuillez sélectionner au moins un créneau horaire.');
        }

        $totalSlots = count($startTimes);
        $user = $request->user();
        if ($user && !$user->hasCredits($totalSlots)) {
            $remaining = $user->weeklyRemainingLimit();
            $limit = $user->weeklyLimit();
            return back()->withInput()->with('error', "Quota hebdomadaire insuffisant : Vous avez sélectionné {$totalSlots} créneau(x) mais il ne vous reste que {$remaining} crédit(s) sur vos {$limit} crédits cette semaine.");
        }

        try {
            $createdCount = 0;
            foreach ($startTimes as $startTime) {
                $this->bookingService->createBooking(
                    user: $request->user(),
                    machine: $machine,
                    startTime: $startTime,
                    durationMinutes: $validated['duration_minutes'] ?? 60
                );
                $createdCount++;
            }

            $slotCountMsg = $createdCount > 1 ? "{$createdCount} créneaux réservés" : "Créneau réservé";
            return redirect()->route('dashboard')
                ->with('success', "{$slotCountMsg} avec succès pour {$machine->name} !");
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
                ->with('success', "Cycle started on {$booking->machine->name}! The timer is now running.");
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
