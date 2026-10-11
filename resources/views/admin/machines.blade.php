@extends('layouts.app')
@section('content')
<div class="max-w-4xl mx-auto space-y-4">
<h1 class="text-xl font-bold">État des machines</h1>
@foreach($machines as $machine)
<form method="POST" action="{{ route('machines.update-status', $machine) }}" class="flex flex-wrap gap-3 items-end bg-white rounded-xl border p-4">
    @csrf @method('PATCH')
    <div class="flex-1"><label for="status-{{ $machine->id }}" class="block font-semibold mb-1">{{ $machine->name }} — {{ $machine->status->label() }}</label>
    <select id="status-{{ $machine->id }}" name="status" class="border rounded-lg p-2" @disabled($machine->active_reservation_id)>
        @foreach(['available' => 'Disponible', 'under maintenance' => 'En maintenance', 'out of order' => 'Hors service'] as $value => $label)
        <option value="{{ $value }}" @selected($machine->status->value === $value)>{{ $label }}</option>
        @endforeach
    </select></div>
    @if($machine->active_reservation_id)<p>Cycle en cours : modification indisponible.</p>@else<button class="btn-primary" type="submit">Enregistrer <span class="sr-only">{{ $machine->name }}</span></button>@endif
</form>
@endforeach
{{ $machines->links() }}
</div>
@endsection
