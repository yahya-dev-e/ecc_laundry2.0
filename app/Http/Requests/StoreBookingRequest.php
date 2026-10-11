<?php

namespace App\Http\Requests;

use App\Services\BookingWindow;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $maximum = BookingWindow::end()->subSecond();

        return [
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'start_time' => ['required_without:start_times', 'nullable', 'date', 'after_or_equal:'.now()->toIso8601String(), 'before_or_equal:'.$maximum->toIso8601String()],
            'start_times' => ['required_without:start_time', 'nullable', 'array', 'min:1', 'max:24'],
            'start_times.*' => ['required', 'distinct', 'date', 'after_or_equal:'.now()->toIso8601String(), 'before_or_equal:'.$maximum->toIso8601String()],
            'duration_minutes' => ['nullable', 'integer', 'min:20', 'max:120'],
        ];
    }
}
