<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\{LegalDeal, Setting};

class AutoCancelLegal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto_cancel_legal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动取消超时法币交易';

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
     * Execute console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info('开始执行自动取消法币交易脚本-' . $now->toDateTimeString());
        $legal_timeout = Setting::getValueByKey('legal_timeout', 0);
        if ($legal_timeout <= 0) {
            return;
        }
        $before = $now->subMinutes($legal_timeout)->timestamp;
        //找到XX分钟前的所有未完成订单
        $results = LegalDeal::where('create_time', '<=', $before)
            ->where('is_sure', 0)
            ->get();
        $count = count($results);
        $this->info('共有 ' . $count . ' 条超时记录');
        
        try {
            DB::beginTransaction();
            if (!empty($results)) {
                $i = 1;
                foreach ($results as $result) {
                    $this->info('执行第 ' . $i . ' 条记录');
                    LegalDeal::cancelLegalDealById($result->id, 4);
                    $i++;
                }
            }
            DB::commit();
            $this->info('执行成功');
        } catch (\Exception $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());
        }
    }
}
