<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $minDate = Carbon::today()->startOfDay();
        $maxDate = config('laundry.restrict_to_current_week', true)
            ? Carbon::now()->endOfWeek() // Sunday 23:59:59 of current week
            : Carbon::now()->addDays(config('laundry.max_advance_booking_days', 7))->endOfDay();

        return [
            'machine_id' => ['required', 'exists:machines,id'],
            'start_time' => [
                'nullable',
                'date',
                'after_or_equal:' . $minDate->toIso8601String(),
                'before_or_equal:' . $maxDate->toIso8601String(),
            ],
            'start_times' => ['nullable', 'array', 'min:1'],
            'start_times.*' => [
                'date',
                'after_or_equal:' . $minDate->toIso8601String(),
                'before_or_equal:' . $maxDate->toIso8601String(),
            ],
            'duration_minutes' => ['nullable', 'integer', 'min:20', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'machine_id.required' => 'Veuillez sélectionner une machine.',
            'start_time.required' => 'Veuillez sélectionner une date et un créneau horaire.',
            'start_time.after_or_equal' => 'Vous ne pouvez pas réserver un créneau dans le passé.',
            'start_times.*.after_or_equal' => 'Vous ne pouvez pas réserver un créneau dans le passé.',
            'start_time.before_or_equal' => 'Les réservations sont limitées à la semaine en cours (jusqu\'à dimanche 23h59).',
            'start_times.*.before_or_equal' => 'Les réservations sont limitées à la semaine en cours (jusqu\'à dimanche 23h59).',
        ];
    }
}
