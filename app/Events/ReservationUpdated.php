<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;

class ReservationUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $reservation;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
        
        // Log the event construction
        Log::info('ReservationUpdated: Event constructed', [
            'reservation_id' => $reservation->id ?? 'unknown',
            'guest_id' => $reservation->guest_id ?? 'unknown',
            'cast_id' => $reservation->cast_id ?? 'unknown'
        ]);
    }

    public function broadcastOn()
    {
        $channels = [new Channel('reservation.' . $this->reservation->id)];

        // Also notify the assigned cast and the guest via their user channels if present
        try {
            if (!empty($this->reservation->cast_id)) {
                $channels[] = new Channel('cast.' . $this->reservation->cast_id);
            }
            if (!empty($this->reservation->guest_id)) {
                $channels[] = new Channel('guest.' . $this->reservation->guest_id);
            }
        } catch (\Throwable $e) {
            Log::error('ReservationUpdated: Error getting user IDs', [
                'error' => $e->getMessage(),
                'reservation_id' => $this->reservation->id ?? 'unknown'
            ]);
        }

        Log::info('ReservationUpdated: Broadcasting to channels', [
            'reservation_id' => $this->reservation->id ?? 'unknown',
            'channel_count' => count($channels),
            'channels' => array_map(function($channel) {
                return $channel->name;
            }, $channels)
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        return 'ReservationUpdated';
    }
}
