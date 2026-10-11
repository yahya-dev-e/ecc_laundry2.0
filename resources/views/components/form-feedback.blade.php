@if ($errors->any())
    <div role="alert" tabindex="-1" class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
        <p class="font-semibold">Veuillez corriger les champs suivants :</p>
        <ul class="list-disc pl-5 mt-2">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
@foreach (['status', 'success', 'error'] as $feedback)
    @if(session($feedback))
        <div role="{{ $feedback === 'error' ? 'alert' : 'status' }}" class="mb-4 p-3 rounded-lg {{ $feedback === 'error' ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800' }}">{{ session($feedback) }}</div>
    @endif
@endforeach
