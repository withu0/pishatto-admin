<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReservationUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $reservation;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('reservation.' . $this->reservation->id);
    }
}
