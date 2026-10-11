@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <section class="bg-[#00796b] rounded-xl p-5 sm:p-7 text-white">
        <h1 class="text-2xl font-bold">Bonjour, {{ auth()->user()->name }} !</h1>
        <p class="mt-2">{{ auth()->user()->weeklyRemainingLimit() }}h disponibles sur {{ auth()->user()->weeklyLimit() }}h cette semaine.</p>
        <p class="text-sm mt-2">Le quota se renouvelle le lundi. Les annulations et absences libèrent les heures réservées. Les cycles terminés restent comptabilisés.</p>
        <div class="flex flex-wrap gap-3 mt-5"><a href="{{ route('bookings.create') }}" class="rounded-lg px-4 py-2.5 bg-white text-[#00695c] font-semibold">Réserver une machine</a>
        <a href="{{ route('calendrier') }}" class="rounded-lg px-4 py-2.5 border border-white font-semibold">Voir le calendrier</a></div>
    </section>
    @include('components.live-revision')
    <section aria-labelledby="history-title" class="space-y-3">
        <h2 id="history-title" class="text-xl font-bold">Historique des réservations</h2>
        @forelse($userReservations as $reservation)
        <article class="bg-white rounded-xl p-4 border border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="font-bold">{{ $reservation->machine?->name ?? 'Machine' }}</h3>
                <p class="text-sm">{{ $reservation->start_time->format('d/m/Y H:i') }} – {{ $reservation->end_time->format('H:i') }} · {{ $reservation->quota_minutes / 60 }}h</p>
                <p class="text-sm mt-1">{{ ['upcoming' => 'À venir', 'in_progress' => 'En cours', 'completed' => 'Terminé', 'cancelled' => 'Annulé', 'no_show' => 'Absence / délai dépassé'][$reservation->status] }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($reservation->canBeStarted())
                <form method="POST" action="{{ route('bookings.start', $reservation) }}">@csrf<button class="btn-primary" type="submit">Démarrer le cycle <span class="sr-only">{{ $reservation->machine?->name }}</span></button></form>
                @endif
                @if($reservation->canBeCancelled())
                <form method="POST" action="{{ route('bookings.cancel', $reservation) }}" onsubmit="return confirm('Annuler cette réservation ?')">@csrf<button class="px-3 py-2 border border-rose-200 rounded-lg text-rose-700" type="submit">Annuler <span class="sr-only">{{ $reservation->machine?->name }} à {{ $reservation->start_time->format('H:i') }}</span></button></form>
                @endif
                <a href="{{ route('calendrier', ['date' => $reservation->start_time->toDateString(), 'machine_id' => $reservation->machine_id]) }}" class="px-3 py-2 underline text-[#00796b]">Voir au calendrier</a>
            </div>
        </article>
        @empty<p class="p-4 rounded-lg bg-white">Vous n’avez aucune réservation.</p>@endforelse
        {{ $userReservations->links() }}
    </section>
</div>
@endsection
