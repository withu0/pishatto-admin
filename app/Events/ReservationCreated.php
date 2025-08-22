<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReservationCreated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('reservation.' . $this->reservation->id)];

        // Also notify the guest via their user channel
        if ($this->reservation->guest_id) {
            $channels[] = new Channel('guest.' . $this->reservation->guest_id);
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'ReservationCreated';
    }
}



