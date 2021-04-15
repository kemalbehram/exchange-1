<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\AutoCancelLegal::class,
        Commands\UpdateBalance::class,
        Commands\ClearMarketVolume::class,
        Commands\UpdateHashStatus::class,
        Commands\ClearExpiredToken::class,
        Commands\UpdateCharge::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('update_hash_status')->everyFiveMinutes()->withoutOverlapping(); //更新哈希值状态
        $schedule->command('market:clear:volume')->withoutOverlapping()->dailyAt('00:00'); //清空24小时成交量
        $schedule->command('lever:overnight')->dailyAt('00:01'); //收取隔夜费
        $schedule->command('clear:tokens')->everyMinute()->withoutOverlapping(); // 清除过期token
        $schedule->command('auto_cancel_legal')->everyMinute()->withoutOverlapping()->runInBackground()->appendOutputTo('./storage/logs/auto_cancel_legal.log');
        $schedule->command('auto_confirm_legal')->everyMinute()->withoutOverlapping()->runInBackground()->appendOutputTo('./storage/logs/auto_confirm_legal.log');
        $schedule->command('update_charge')->everyMinute()->appendOutputTo('./update_charge.log')->withoutOverlapping(); //根据充币hash更新链上余额
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
