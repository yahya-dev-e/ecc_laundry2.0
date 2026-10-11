<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Machine;
use App\Models\Reservation;
use App\Services\BookingService;
use App\Services\BookingWindow;
use App\Services\MachineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService, protected MachineSchedulerService $scheduler) {}

    public function create(Request $request)
    {
        $machines = Machine::orderBy('name')->get();
        $selectedMachine = $machines->firstWhere('id', (int) $request->query('machine_id'));
        $minDate = now()->toDateString();
        $maxDate = BookingWindow::lastDate();
        $initialDate = $minDate;
        try {
            $candidate = Carbon::parse($request->query('date', $request->query('start_time', $minDate)))->toDateString();
            if ($candidate >= $minDate && $candidate <= $maxDate) {
                $initialDate = $candidate;
            }
        } catch (\Throwable) {
        }
        $reservations = []; // Availability is read from the authoritative slots endpoint.

        return view('bookings.create', compact('machines', 'selectedMachine', 'minDate', 'maxDate', 'initialDate', 'reservations'));
    }

    public function store(StoreBookingRequest $request)
    {
        $data = $request->validated();
        $machine = Machine::findOrFail($data['machine_id']);
        $starts = array_map(fn ($t) => Carbon::parse($t), $data['start_times'] ?? [$data['start_time']]);
        try {
            $created = $this->bookingService->createBookings($request->user(), $machine, $starts, $data['duration_minutes'] ?? 60);

            return redirect()->route('dashboard')->with('success', count($created).' créneau(x) réservé(s) avec succès.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function start(Request $request, Reservation $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        try {
            $this->bookingService->startCycle($booking);

            return redirect()->route('dashboard')->with('success', 'Le cycle a démarré.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function destination(Request $request): string
    {
        $value = $request->input('redirect_to');
        if (! is_string($value)) {
            return route('dashboard');
        }
        $url = parse_url($value);
        if (! $url || (isset($url['host']) && $url['host'] !== parse_url(config('app.url'), PHP_URL_HOST))
            || (isset($url['scheme']) && $url['scheme'] !== parse_url(config('app.url'), PHP_URL_SCHEME))) {
            return route('dashboard');
        }
        if (($url['path'] ?? '') === '/dashboard') {
            return route('dashboard');
        }
        if (($url['path'] ?? '') === '/calendrier') {
            parse_str($url['query'] ?? '', $params);
            $params = array_filter(Arr::only($params, ['date', 'machine_id']), 'is_scalar');

            return route('calendrier', $params);
        }

        return route('dashboard');
    }

    public function cancel(Request $request, Reservation $booking)
    {
        $data = $request->validate([
            'reservation_ids' => ['nullable', 'array', 'min:1', 'max:24'],
            'reservation_ids.*' => ['integer', 'distinct', 'exists:reservations,id'],
            'reason' => ['nullable', 'string', 'max:500'], 'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);
        $ids = $data['reservation_ids'] ?? [$booking->id];
        $bookings = Reservation::whereIn('id', $ids)->orderBy('user_id')->orderBy('machine_id')->get();
        foreach ($bookings as $item) {
            abort_unless($item->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        }
        try {
            $count = DB::transaction(function () use ($bookings, $request, $data) {
                $count = 0;
                foreach ($bookings as $item) {
                    $count += (int) $this->bookingService->cancelBooking($item, $data['reason'] ?? 'Annulation par #'.$request->user()->id);
                }

                return $count;
            }, 5);

            return redirect($this->destination($request))->with($count ? 'success' : 'status',
                $count ? $count.' réservation(s) annulée(s) avec succès. Votre quota a été restitué.' : 'Ces réservations étaient déjà annulées.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, Reservation $booking)
    {
        return $this->cancel($request, $booking);
    }
}
