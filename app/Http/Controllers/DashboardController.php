<?php

namespace App\Http\Controllers;

use App\Enums\MachineStatus;
use App\Models\Machine;
use App\Models\Reservation;
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
     * Display the main ECC Laundry real-time dashboard / calendar.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. Determine selected date for the calendar navigation
        $dateParam = $request->query('date', Carbon::today()->toDateString());
        try {
            $selectedDate = Carbon::parse($dateParam)->startOfDay();
        } catch (\Exception $e) {
            $selectedDate = Carbon::today()->startOfDay();
        }

        $prevDate = $selectedDate->copy()->subDay()->toDateString();
        $nextDate = $selectedDate->copy()->addDay()->toDateString();
        $todayDate = Carbon::today()->toDateString();

        // French date labels
        $dayNames = [
            0 => 'dimanche',
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
        ];
        $monthNames = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        $dayOfWeek = $dayNames[$selectedDate->dayOfWeek] ?? $selectedDate->format('l');
        $dateFormatted = $selectedDate->day . ' ' . ($monthNames[$selectedDate->month] ?? $selectedDate->format('F')) . ' ' . $selectedDate->year;

        // 7-day week strip calculation (Monday to Sunday)
        $startOfWeek = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $shortDayNames = [
            1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu',
            5 => 'Ven', 6 => 'Sam', 0 => 'Dim'
        ];
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $startOfWeek->copy()->addDays($i);
            $weekDays[] = [
                'date' => $day->toDateString(),
                'dayNumber' => $day->day,
                'shortName' => $shortDayNames[$day->dayOfWeek] ?? $day->format('D'),
                'isToday' => $day->toDateString() === $todayDate,
                'isSelected' => $day->toDateString() === $selectedDate->toDateString(),
            ];
        }

        // 2. Fetch all reservations for the selected calendar day
        $dayStart = $selectedDate->copy()->startOfDay();
        $dayEnd = $selectedDate->copy()->endOfDay();

        $dayReservations = Reservation::with(['machine', 'user'])
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('start_time', [$dayStart, $dayEnd])
                  ->orWhereBetween('end_time', [$dayStart, $dayEnd])
                  ->orWhere(function ($sub) use ($dayStart, $dayEnd) {
                      $sub->where('start_time', '<=', $dayStart)
                          ->where('end_time', '>=', $dayEnd);
                  });
            })
            ->get();

        // Map reservations by hour of day (0 to 23)
        $reservationsByHour = [];
        for ($h = 0; $h < 24; $h++) {
            $hourStart = $selectedDate->copy()->setTime($h, 0, 0);
            $hourEnd = $selectedDate->copy()->setTime($h, 59, 59);

            $reservationsByHour[$h] = $dayReservations->filter(function ($res) use ($hourStart, $hourEnd) {
                return $res->start_time && $res->end_time && $res->start_time <= $hourEnd && $res->end_time >= $hourStart;
            })->values();
        }

        // 3. Fetch user's currently active cycles (time-derived: start_time <= now <= end_time)
        $activeCycles = $user ? $user->reservations()
            ->with('machine')
            ->where('start_time', '<=', Carbon::now())
            ->where('end_time', '>=', Carbon::now())
            ->get() : collect();

        // 4. Fetch user's next upcoming reservation (time-derived: start_time > now)
        $nextBooking = $user ? $user->reservations()
            ->with('machine')
            ->where('start_time', '>', Carbon::now())
            ->orderBy('start_time')
            ->first() : null;

        // 5. Filter machines by type or status (strictly ordering by name)
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');

        $machinesQuery = Machine::query()->orderBy('name');

        if ($typeFilter && in_array($typeFilter, ['washing-machine', 'dryer'])) {
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
            'selectedDate' => $selectedDate->toDateString(),
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'todayDate' => $todayDate,
            'weekDays' => $weekDays,
            'dateFormatted' => $dateFormatted,
            'dayName' => $dayOfWeek,
            'dayReservations' => $dayReservations,
            'reservationsByHour' => $reservationsByHour,
        ]);
    }
}
