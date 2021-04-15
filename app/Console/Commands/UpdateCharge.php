<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\DAO\BlockChainDAO;
use App\Models\{AccountLog, ChargeHash, Setting, LbxHash, UsersWallet};

class UpdateCharge extends Command
{
    protected $signature = 'update_charge';
    protected $description = '更新用户的充币余额';
    protected $chainRechargeType = null;

    public function handle()
    {
        $this->comment("开始执行充币处理");
        $this->chainRechargeType = Setting::getValueByKey('chain_recharge_type', 0); //0主动扫 1交易推送
        $n = ChargeHash::where('status', 0)->count();
        if ($n <= 0) {
            $this->comment("暂时没有要处理的充币记录");
            return false;
        }
        foreach (ChargeHash::where('status', 0)->orderBy('id', 'asc')->cursor() as $c) {

            $this->updateWallet($c);
        }
        $this->comment("全部结束");
    }

    public function updateWallet($c)
    {
        try {
            DB::beginTransaction();
            // 检测对应哈希在打入手续费列表中是否存在，存在则跳过
            $fee_txid = LbxHash::where('type', 2)
                ->where('txid', $c->txid)
                ->exists();
            if ($fee_txid) {
                $c->status = 1;
                $c->save();
                DB::commit();
                return true;
            }
            $c = $c->lockForUpdate()->findOrFail($c->getKey());
            $wallet = UsersWallet::where('address', $c->recipient)
                ->where('currency', $c->currency_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($this->chainRechargeType == 1) {
                //充币到账 如果币种有主币则应对主币钱包到账
                $c_balance = $c->amount;
                $balance_to = Setting::getValueByKey('recharge_to_balance', 1); // 充币到哪个账户(1.法币,2.币币,3.合约)
                if ($wallet->currencyCoin->parent_id != 0) {
                    $parent_wallet = UsersWallet::where('user_id', $wallet->user_id)
                        ->where('currency', $wallet->currencyCoin->parent_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    change_wallet_balance(
                        $parent_wallet,
                        $balance_to,
                        $c_balance,
                        AccountLog::CHAIN_RECHARGE,
                        "{$parent_wallet->currencyCoin->name}/{$wallet->currencyCoin->type}链上充币增加,交易哈希:{$c->txid}"
                    );
                } else {
                    change_wallet_balance(
                        $wallet,
                        $balance_to,
                        $c_balance,
                        AccountLog::CHAIN_RECHARGE,
                        "{$wallet->currencyCoin->type}链上充币增加,交易哈希:{$c->txid}"
                    );
                }
                $c->status = 1;
                $c->save();
                // 顺带刷新一下链上余额，方便归拢
                BlockChainDAO::refreshChainBalance($wallet);
            } else {
                if ($wallet) {
                    //查询链上钱包余额
                    try {
                        BlockChainDAO::updateWalletBalance($this->wallet);
                    } catch (\Throwable $th) {
                        //throw $th;
                    } finally {
                        //chargehash 表更新为已处理
                        $c->status = 1;
                        $c->save();
                    }
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            echo 'File:' . $ex->getFile() . PHP_EOL;
            echo 'Line:' . $ex->getLine() . PHP_EOL;
            echo 'Message:' . $ex->getMessage() . PHP_EOL;
        }
    }
}
