<?php

namespace App\Listeners;

use App\Events\WithdrawSubmitEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\DAO\GoChainDAO;
use App\Models\Currency;

class WithdrawSubmitListener
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
     * @param  WithdrawSubmitEvent  $event
     * @return void
     */
    public function handle(WithdrawSubmitEvent $event)
    {
        
    }
}
