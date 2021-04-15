<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

use App\Events\WithdrawAuditEvent;
use App\DAO\GoChainDAO;
use Illuminate\Support\Carbon;

class WithdrawAuditListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  WithdrawAuditEvent  $event
     * @return void
     */
    public function handle(WithdrawAuditEvent $event)
    {

    }
}
