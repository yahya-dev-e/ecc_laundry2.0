<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Machine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CycleStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Machine $machine,
        public Booking $booking
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('machines.'.$this->machine->id),
            new PrivateChannel('users.'.$this->booking->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'machine_id' => $this->machine->id,
            'machine_code' => $this->machine->code,
            'booking_id' => $this->booking->id,
            'ends_at' => $this->machine->current_cycle_ends_at?->toIso8601String(),
            'status' => $this->machine->status->value,
        ];
    }
}
