<?php

namespace App\Events;

use App\Models\Machine;
use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CycleCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Machine $machine,
        public ?Reservation $booking = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('machines.'.$this->machine->id)];

        if ($this->booking) {
            $channels[] = new PrivateChannel('users.'.$this->booking->user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'machine_id' => $this->machine->id,
            'machine_name' => $this->machine->name,
            'booking_id' => $this->booking?->id,
            'status' => 'available',
            'message' => "Cycle on {$this->machine->name} completed! Please collect your laundry.",
        ];
    }
}
