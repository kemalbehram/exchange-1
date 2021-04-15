<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\{LegalDeal, Setting};

class AutoConfirmLegal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto_confirm_legal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动确认超时未确认法币交易';

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
        $this->info('开始执行自动确认法币交易脚本-' . $now->toDateTimeString());
        $legal_confirm_timeout = Setting::getValueByKey('legal_confirm_timeout', 0);
        if ($legal_confirm_timeout <= 0) {
            return;
        }
        $before = $now->subMinutes($legal_confirm_timeout);
        //找到XX分钟前的所有已支付未确认的交易
        $results = LegalDeal::whereNotNull('payed_at')
            ->where('payed_at', '<=', $before)
            ->where('is_sure', 3)
            ->get();
        $count = count($results);
        $this->info('共有 ' . $count . ' 条超时未确认交易');
        try {
            DB::beginTransaction();
            if (!empty($results)) {
                $i = 1;
                foreach ($results as $result) {
                    $this->info('执行第 ' . $i . ' 条记录');
                    LegalDeal::confirmLegalDealById($result->id, 4);
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
