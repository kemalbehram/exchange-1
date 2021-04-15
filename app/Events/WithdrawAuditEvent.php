<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\UsersWalletOut;

class WithdrawAuditEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $withdraw;
    public $currency;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($withdraw, $currency)
    {
        $this->withdraw = $withdraw;
        $this->currency = $currency;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
