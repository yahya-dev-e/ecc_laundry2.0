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
        $maxDate = Carbon::now()->addDays(config('laundry.max_advance_booking_days', 7))->endOfDay();

        return [
            'machine_id' => ['required', 'exists:machines,id'],
            'start_time' => [
                'required',
                'date',
                'after_or_equal:now - 5 minutes',
                'before_or_equal:' . $maxDate->toIso8601String(),
            ],
            'duration_minutes' => ['nullable', 'integer', 'min:20', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'machine_id.required' => 'Please select a laundry machine.',
            'start_time.required' => 'Please select a start date and time slot.',
            'start_time.after_or_equal' => 'You cannot book a time slot in the past.',
            'start_time.before_or_equal' => 'You can only book up to 7 days in advance.',
        ];
    }
}
