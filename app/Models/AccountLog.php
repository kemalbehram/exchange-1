<?php

/**
 * Created by PhpStorm.
 * User: swl
 * Date: 2018/7/3
 * Time: 10:23
 */

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AccountLog extends Model
{
    protected $table = 'account_log';
    public $timestamps = false;
    const CREATED_AT = 'created_time';
    protected $appends = [
        'account_number',
        'account',
        'currency_name', // 币种
//        'before', // 交易前
//        'after', // 交易后
//        'balance_type_name', // 账户类型
//        'lock_type_name',
    ];

    const ADMIN_LEGAL_BALANCE = 1; //后台调节法币账户余额
    const ADMIN_LOCK_LEGAL_BALANCE = 2; //后台调节法币账户锁定余额
    const ADMIN_CHANGE_BALANCE = 3; //后台调节币币账户余额
    const ADMIN_LOCK_CHANGE_BALANCE = 4; //后台调节币币账户锁定余额
    const ADMIN_LEVER_BALANCE = 5; //后台调节合约账户余额
    const ADMIN_LOCK_LEVER_BALANCE = 6; //后台调节合约账户锁定余额

    const WALLET_CURRENCY_OUT = 7; //提币记录
    const WALLET_CURRENCY_IN = 8; //充币记录

    const WALLET_LEGAL_OUT = 9; //法币划出
    const WALLET_LEGAL_IN = 10; //法币划入
    const WALLET_CHANGE_IN = 11; //币币划入
    const WALLET_CHANGE_OUT = 12; //币币划出
    const WALLET_LEVER_IN = 13; //合约划入
    const WALLET_LEVER_OUT = 14; //合约划出

    const WALLET_ACCOUNT_TRANSFER_OUT = 15; // 钱包账户划出
    const WALLET_ACCOUNT_TRANSFER_IN = 16; // 钱包账户划入

    const INVITATION_TO_RETURN = 33; //邀请返佣
    const LEGAL_DEAL_SEND_SELL = 60; //商家发布法币出售
    const LEGAL_DEAL_USER_SELL = 61; //出售给商家法币
    const LEGAL_USER_BUY = 62; //用户购买商家法币成功
    const LEGAL_SELLER_BUY = 63; //商家购买用户法币成功
    const LEGAL_DEAL_USER_SELL_CANCEL = 64; //出售给商家法币-取消
    const LEGAL_CANCEL_DEAL = 65; // 取消法币交易
    const LEGAL_DEAL_AUTO_CANCEL = 68; //自动取消法币交易
    const LEGAL_DEAL_AUTO_CONFIRM = 69; //自动确认法币交易
    const ADMIN_SELLER_BALANCE = 70; //后台调节商家余额
    const LEGAL_DEAL_BACK_SEND_SELL = 71; //商家撤回发布法币出售
    const LEGAL_DEAL_ERROR_SEND_SELL = 72; //商家标记异常

    const BTC_TRANSFER_FEE = 80; //打入BTC手续费
    const ETH_TRANSFER_FEE = 81; //打入ETH手续费
    const TOKENS_WRAPPING = 82; //代币归拢

    const TRANSACTIONOUT_SUBMIT_REDUCE = 21; //提交卖出，扣除

    const TRANSACTIONIN_REDUCE = 22; //买入扣除
    const TRANSACTIONIN_SELLER = 23; //扣除卖方
    const TRANSACTIONIN_SUBMIT_REDUCE = 24; //提交买入，扣除

    const TRANSACTIONIN_REDUCE_ADD = 25; //买方增加币
    const TRANSACTIONIN_SELLER_ADD = 26; //卖方增加cny

    const TRANSACTIONIN_REVOKE_ADD = 27; //撤销增加
    const TRANSACTIONOUT_REVOKE_ADD = 28; //撤销增加

    const TRANSACTION_FEE = 29; //卖出手续费

    const LEVER_TRANSACTION = 30; //合约交易扣除保证金
    const LEVER_TRANSACTION_ADD = 31; //平仓增加
    const LEVER_TRANSACTION_FROZEN = 32; //爆仓冻结
    const LEVER_TRANSACTION_OVERNIGHT = 34; //隔夜费
    const LEVER_TRANSACTION_FEE = 35; //交易手续费
    const LEVER_TRANSACTIO_CANCEL = 36; //合约交易取消
    const CANDY_LEVER_BALANCE = 37; //通证兑换合约币增加
    const LEVER_TRANSACTION_FEE_CANCEL = 38; //交易手续费

    const WALLETOUT = 99; //用户申请提币
    const WALLETOUTDONE = 100; //用户提币成功
    const WALLETOUTBACK = 101; //用户提币失败
    const TRANSACTIONIN_IN_DEL = 102; //取消买入交易
    const TRANSACTIONIN_OUT_DEL = 103; //取消买出交易

    const CHANGE_LEVER_BALANCE = 104; //合约交易账户变化

    const REWARD_CANDY = 105; //奖励通证
    const REWARD_CURRENCY = 106; //奖励数字货币

    const CANDY_TOUSDT_CANDY = 107; //通证兑换USDT
    const ADMIN_CANDY_BALANCE = 108; //后台调节通证

    const SELLER_BACK_SEND = 299; //合约交易账户变化
    const CHANGEBALANCE = 401; //转账
    const LTC_IN = 301; //来自矿机的转账
    const LTC_SEND = 302; //转账余额至矿机

    const ETH_EXCHANGE = 200; //充币增加余额

    const CHAIN_RECHARGE = 200;

    //c2c交易
    const C2C_DEAL_SEND_SELL = 201; //用户发布法币出售
    const C2C_DEAL_AUTO_CANCEL = 202; //自动取消c2c法币交易
    const C2C_DEAL_USER_SELL = 203; //出售给用户法币
    const C2C_USER_BUY = 204; //用户购买法币成功
    const C2C_DEAL_BACK_SEND_SELL = 205; //商家撤回发布法币出售

    const C2C_TRADE_FEE = 230; //C2C交易手续费
    const C2C_CANCEL_TRADE_FEE = 231; //C2C交易取消撤回手续费
    const LEGAL_TRADE_FREE = 232; //法币交易手续费
    const LEGAL_CANCEL_TRADE_FREE = 233; //法币交易取消撤回手续费

    const WALLET_LEGAL_LEVEL_OUT = 206; //法币(c2c)转入合约
    const WALLET_LEGAL_LEVEL_IN = 207; //法币(c2c)转入合约
    const WALLET_LEVEL_LEGAL_OUT = 208; //合约转入法币(c2c)
    const WALLET_LEVEL_LEGAL_IN = 209; //合约转入法币(c2c)
    const WALLET_DONGJIEGANGGAN = 210;
    const WALLET_JIEDONGGANGGAN = 211; //审核不通过解冻合约冻结

    const PROFIT_LOSS_RELEASE = 212; //历史盈亏释放,增加合约币

    const JC_INTEGRAL_EXCHANGE_CURRENCY = 220;

    const MATCH_TRANSACTION_SELL_FEE = 301; //撮合交易[卖出]手续费
    const MATCH_TRANSACTION_BUY_FEE = 302; //撮合交易<买入>成功手续费

    const MATCH_TRANSACTION_CANCEL_SELL_FEE = 303; //撮合交易取消[卖出]撤回手续费
    const MATCH_TRANSACTION_CANCEL_BUY_FEE = 304; //撮合交易取消<买入>撤回手续费

    const GAME_SHIFT_TO = 305; //游戏转入

    const AGENT_JIE_TC_MONEY = 306; //代理商结算头寸收益
    const AGENT_JIE_SX_MONEY = 307; //代理商结算手续费收益

    const TRANSFER_TO = 888; // 站内转账

    const SELLER_TRANSFER_USER_BALANCE=400;//商家余额划转到用户余额
    const USER_TRANSFER_SELLER_BALANCE=401;//用户余额划转到商家

    public function currencyCoin()
    {
        return $this->belongsTo(Currency::class, 'currency', 'id');
    }

    public function getAccountNumberAttribute()
    {
        return $this->user->account_number ?? '';
    }

    public function getAccountAttribute()
    {
        return $this->user->phone ?? ($this->user->email ?? '');
    }

    public function getCreatedTimeAttribute()
    {
        $value = $this->attributes['created_time'];
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    public function getBeforeAttribute()
    {
        return $this->walletLog->before ?? 00;
    }
    public function getAfterAttribute()
    {
        return $this->walletLog->after  ?? 00;
    }

    public function getBalanceTypeNameAttribute()
    {
        $balance_type = $this->walletLog->balance_type ?? 0;
        $balance_list = UsersWallet::getBalanceTypeList();
        return array_key_exists($balance_type, $balance_list) ? $balance_list[$balance_type] : '';
    }

    public function getLockTypeNameAttribute()
    {
        return $this->walletLog->lock_type ? '是' : '否';
    }

    public function getCurrencyNameAttribute()
    {
        return $this->currencyCoin->name ?? '';
    }

    public static function insertLog($data = array(), $data2 = array())
    {
        $data = is_array($data) ? $data : func_get_args();
        $log = new self();
        $log->user_id = $data['user_id'] ?? false;;
        $log->value = $data['value'] ?? '';
        $log->created_time = $data['created_time'] ?? time();
        $log->info = $data['info'] ?? '';
        $log->type = $data['type'] ?? 0;
        $log->currency = $data['currency'] ?? 0;
        $data_wallet['balance_type'] = $data2['balance_type'] ?? 0;
        $data_wallet['wallet_id'] = $data2['wallet_id'] ?? 0;
        $data_wallet['lock_type'] = $data2['lock_type'] ?? 0;
        $data_wallet['before'] = $data2['before'] ?? 0;
        $data_wallet['change'] = $data2['change'] ?? 0;
        $data_wallet['after'] = $data2['after'] ?? 0;
        $data_wallet['memo'] = $data['info'] ?? 0;
        $data_wallet['create_time'] = $data2['create_time'] ?? time();
        //dd($data_wallet);
        try {
            DB::transaction(function () use ($log, $data_wallet) {
                $log->save();
                $log->walletLog()->create($data_wallet);
            });
            return true;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
            return false;
        }
    }

    public static function newinsertLog($data = array(), $data2 = array())
    {
        $data = is_array($data) ? $data : func_get_args();
        $log = new self();
        $log->user_id = $data['user_id'] ?? false;;
        $log->value = $data['value'] ?? '';
        $log->created_time = $data['created_time'] ?? time();
        $log->info = $data['info'] ?? '';
        $log->type = $data['type'] ?? 0;
        $log->currency = $data['currency'] ?? 0;
        try {
            DB::transaction(function () use ($log) {
                $log->save();
                //                $log->walletLog()->create($data_wallet);
            });
            return true;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
            return false;
        }
    }


    public static function getTypeInfo($type)
    {
        switch ($type) {

            case self::ADMIN_LEGAL_BALANCE:
                return '后台调节法币账户余额';
                break;
            case self::ADMIN_LOCK_LEGAL_BALANCE:
                return '后台调节法币账户锁定余额';
                break;
            case self::ADMIN_CHANGE_BALANCE:
                return '后台调节币币账户余额';
                break;
            case self::ADMIN_LOCK_CHANGE_BALANCE:
                return '后台调节币币账户锁定余额';
                break;
            case self::ADMIN_LEVER_BALANCE:
                return '后台调节合约账户余额';
                break;
            case self::ADMIN_LOCK_LEVER_BALANCE:
                return '后台调节合约账户锁定余额';
                break;
            case self::WALLET_LEGAL_OUT:
                return '法币账户转出至交易账户';
                break;
            case self::WALLET_LEGAL_IN:
                return '交易账户转入至法币账户';
                break;
            case self::WALLET_CHANGE_OUT:
                return '交易账户转出至法币账户';
                break;
            case self::WALLET_CHANGE_IN:
                return '法币账户转入交易账户';
                break;
            case self::INVITATION_TO_RETURN:
                return '邀请返佣金';
                break;
            case self::WALLETOUT:
                return '用户提币';
                break;
            case self::TRANSACTIONIN_IN_DEL:
                return '取消买入交易';
                break;
            case self::TRANSACTIONIN_OUT_DEL:
                return '取消卖出交易';
                break;
            case self::WALLET_LEGAL_LEVEL_OUT:
                return '法币转入合约,法币减少';
                break;
            case self::WALLET_LEGAL_LEVEL_IN:
                return '法币转入合约，合约增加';
                break;
            case self::WALLET_LEVEL_LEGAL_OUT:
                return '合约转法币审核通过,合约减少';
                break;
            case self::WALLET_LEVEL_LEGAL_IN:
                return '合约转法币审核通过，法币增加';
                break;
            case self::WALLET_DONGJIEGANGGAN:
                return '合约转法币,冻结合约转化值';
                break;
            case self::WALLET_JIEDONGGANGGAN:
                return '合约转法币,审核不通过解冻';
                break;
            case self::CANDY_TOUSDT_CANDY:
                return '通证兑换USDT';
                break;
            case self::CANDY_LEVER_BALANCE:
                return '通证兑换，合约币增加';
                break;
            case self::PROFIT_LOSS_RELEASE:
                return '历史盈亏释放,增加合约币';
                break;
            case self::REWARD_CANDY:
                return '奖励通证';
                break;
            case self::REWARD_CURRENCY:
                return '奖励数字货币';
                break;
            case self::ADMIN_CANDY_BALANCE:
                return '后台调节通证';
                break;
            default:
                return '暂无此类型';
                break;
        }
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    //关联钱包记录模型
    public function walletLog()
    {
         return $this->hasOne(WalletLog::class, 'account_log_id', 'id')->withDefault();
    }
}
