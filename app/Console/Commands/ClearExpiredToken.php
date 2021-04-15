<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Token;

class ClearExpiredToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清除过期token';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Token::clearExpiredToken();
    }
}
