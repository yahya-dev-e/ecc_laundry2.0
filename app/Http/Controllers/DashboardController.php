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
        $dayEndExclusive = $selectedDate->copy()->addDay()->startOfDay();

        $dayReservations = Reservation::with(['machine', 'user'])
            ->where(function ($q) use ($dayStart, $dayEndExclusive) {
                $q->where('start_time', '<', $dayEndExclusive)
                  ->where('end_time', '>', $dayStart);
            })
            ->get();

        // Map reservations by hour of day (0 to 23) using half-open intervals [hourStart, nextHourStart)
        // This ensures a 9am-11am reservation appears in hour 9 and 10, never hour 11
        $reservationsByHour = [];
        for ($h = 0; $h < 24; $h++) {
            $hourStart = $selectedDate->copy()->setTime($h, 0, 0);
            $nextHourStart = $h === 23 ? $dayEndExclusive : $selectedDate->copy()->setTime($h + 1, 0, 0);

            $reservationsByHour[$h] = $dayReservations->filter(function ($res) use ($hourStart, $nextHourStart) {
                return $res->start_time && $res->end_time && $res->start_time < $nextHourStart && $res->end_time > $hourStart;
            })->values();
        }

        // Build continuous blocks:
        // Coalesce contiguous / back-to-back reservations for the SAME machine & SAME user
        $hourHeight = 52; // Height in pixels for each 1-hour slot

        $sortedReservations = $dayReservations->filter(function ($res) {
            return $res->start_time && $res->end_time && $res->end_time > $res->start_time;
        })->sortBy([
            ['machine_id', 'asc'],
            ['start_time', 'asc'],
        ])->values();

        $mergedBlocks = [];
        foreach ($sortedReservations as $res) {
            $lastIndex = count($mergedBlocks) - 1;
            if ($lastIndex >= 0) {
                $sameMachine = (int)$mergedBlocks[$lastIndex]['machine_id'] === (int)$res->machine_id;
                $sameUser = ($mergedBlocks[$lastIndex]['user_id'] === $res->user_id) && ($mergedBlocks[$lastIndex]['user_id'] !== null);
                $isContiguous = $res->start_time->lte($mergedBlocks[$lastIndex]['end_time']);

                if ($sameMachine && $sameUser && $isContiguous) {
                    if ($res->end_time->gt($mergedBlocks[$lastIndex]['end_time'])) {
                        $mergedBlocks[$lastIndex]['end_time'] = $res->end_time->copy();
                    }
                    $mergedBlocks[$lastIndex]['reservation_ids'][] = $res->id;
                    continue;
                }
            }

            $mergedBlocks[] = [
                'id' => $res->id,
                'reservation_ids' => [$res->id],
                'machine_id' => $res->machine_id,
                'machine' => $res->machine,
                'user_id' => $res->user_id,
                'user' => $res->user,
                'start_time' => $res->start_time->copy(),
                'end_time' => $res->end_time->copy(),
            ];
        }

        // Calculate time offsets in minutes clamped to the current day
        $blocksWithTime = [];
        foreach ($mergedBlocks as $block) {
            $clampedStart = $block['start_time']->lt($dayStart) ? $dayStart->copy() : $block['start_time']->copy();
            $clampedEnd = $block['end_time']->gt($dayEndExclusive) ? $dayEndExclusive->copy() : $block['end_time']->copy();

            $startMinutes = max(0, (int) $dayStart->diffInMinutes($clampedStart, false));
            $durationMinutes = max(30, (int) $clampedStart->diffInMinutes($clampedEnd, false));
            $endMinutes = $startMinutes + $durationMinutes;

            $durationFormatted = $durationMinutes >= 60 
                ? ($durationMinutes % 60 === 0 ? ($durationMinutes / 60) . ' h' : sprintf('%dh%02d', floor($durationMinutes/60), $durationMinutes%60))
                : $durationMinutes . ' min';

            $blocksWithTime[] = array_merge($block, [
                'startMinutes' => $startMinutes,
                'endMinutes' => $endMinutes,
                'durationMinutes' => $durationMinutes,
                'durationFormatted' => $durationFormatted,
                'timeFormatted' => $block['start_time']->format('H:i') . ' - ' . $block['end_time']->format('H:i'),
            ]);
        }

        // Cluster overlapping blocks for responsive side-by-side columns
        usort($blocksWithTime, function ($a, $b) {
            if ($a['startMinutes'] === $b['startMinutes']) {
                return $b['durationMinutes'] <=> $a['durationMinutes'];
            }
            return $a['startMinutes'] <=> $b['startMinutes'];
        });

        $clusters = [];
        $currentCluster = [];
        $clusterEnd = 0;

        foreach ($blocksWithTime as $block) {
            if (empty($currentCluster)) {
                $currentCluster[] = $block;
                $clusterEnd = $block['endMinutes'];
            } else {
                if ($block['startMinutes'] < $clusterEnd) {
                    $currentCluster[] = $block;
                    $clusterEnd = max($clusterEnd, $block['endMinutes']);
                } else {
                    $clusters[] = $currentCluster;
                    $currentCluster = [$block];
                    $clusterEnd = $block['endMinutes'];
                }
            }
        }
        if (!empty($currentCluster)) {
            $clusters[] = $currentCluster;
        }

        $calendarBlocks = [];
        foreach ($clusters as $cluster) {
            $columns = [];
            $assignments = [];

            foreach ($cluster as $idx => $b) {
                $placed = false;
                foreach ($columns as $colIdx => $colEnd) {
                    if ($b['startMinutes'] >= $colEnd) {
                        $columns[$colIdx] = $b['endMinutes'];
                        $assignments[$idx] = $colIdx;
                        $placed = true;
                        break;
                    }
                }
                if (!$placed) {
                    $newCol = count($columns);
                    $columns[$newCol] = $b['endMinutes'];
                    $assignments[$idx] = $newCol;
                }
            }

            $numCols = max(1, count($columns));

            foreach ($cluster as $idx => $b) {
                $colIdx = $assignments[$idx];
                $widthPct = 100 / $numCols;
                $leftPct = $colIdx * $widthPct;

                $top = ($b['startMinutes'] / 60) * $hourHeight;
                $height = ($b['durationMinutes'] / 60) * $hourHeight;

                $b['top'] = $top;
                $b['height'] = max(34, $height);
                $b['leftPct'] = $leftPct;
                $b['widthPct'] = $widthPct;
                $b['numCols'] = $numCols;
                $b['colIdx'] = $colIdx;

                $calendarBlocks[] = $b;
            }
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
            'calendarBlocks' => $calendarBlocks,
        ]);
    }
}
