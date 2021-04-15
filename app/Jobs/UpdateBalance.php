<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\DAO\BlockChainDAO;

class UpdateBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $wallet;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo '当前处理的队列名称:' . $this->queue . ',币种:' . $this->wallet->currencyCoin->name . '/' . $this->wallet->currencyCoin->type .',钱包id:' . $this->wallet->id . ',用户id:' . $this->wallet->user->id . PHP_EOL;
        $chain_recharge_type = \App\Models\Setting::getValueByKey('chain_recharge_type', 0);//0主动扫 1交易推送
        if ($chain_recharge_type == 1) {
            BlockChainDAO::refreshChainBalance($this->wallet);
        } else {
            BlockChainDAO::updateWalletBalance($this->wallet);
            usleep(0.15 * 1000000); // 休眠请求队列任务，减少失败率
        }
    }
}
