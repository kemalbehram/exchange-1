<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LegalDeal extends Model
{
    protected $table = 'legal_deal';
    public $timestamps = false;

    protected $appends = [
        'deal_money',
        'currency_name',
        'type',
        'account_number',
        'phone',
        'seller_name',
        'price',
        'hes_account',
        'hes_realname',
        'way_name',
        'format_create_time',
        'is_seller',
        'user_cash_info',
        'seller_phone',
        'user_realname',
        'bank_address',
        'coin_code',
        'sell_info',
    ];

    protected static $operateFrom = [
        0 => '默认',
        1 => '用户',
        2 => '商家',
        3 => '后台管理员',
        4 => '系统自动',
    ];

    public static function getOperateFromList()
    {
        return self::$operateFrom;
    }

    public function getSellInfoAttribute()
    {
        return $this->seller ?? [];
    }

    public function getUserCashInfoAttribute()
    {
        $user = $this->user()->getResults();
        if (!$user) {
            return [];
        }
        $cashinfo = $user->cashinfo;
        if (!$cashinfo) {
            return [];
        }
        return $cashinfo;
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }

    public function legalDealSend()
    {
        return $this->belongsTo(LegalDealSend::class, 'legal_deal_send_id', 'id');
    }

    public function getCreateTimeAttribute()
    {
        return date('H:i m/d', $this->attributes['create_time']);
    }
    public function getFormatCreateTimeAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['create_time']);
    }

    public function getDealMoneyAttribute()
    {
        $legal = LegalDealSend::find($this->attributes['legal_deal_send_id']);
        if (!empty($legal)) {
            return bcmul($this->attributes['number'], $legal->price, 6);
        }
        return 0;
    }


    public function getCurrencyNameAttribute()
    {
        $legal = LegalDealSend::find($this->attributes['legal_deal_send_id']);
        if (!empty($legal)) {
            return $legal->currency_name;
        }
        return '';
    }

    public function getSellerPhoneAttribute()
    {
        $seller = Seller::find($this->attributes['seller_id']);
        if (empty($seller)) return null;
        return $seller->mobile;
        $user = Users::find($seller->user_id);
        if (!empty($user)) {
            return $user->account_number;
        } else {
            return null;
        }
    }

    public function getTypeAttribute()
    {
        return $this->legalDealSend->type ?? '';
    }

    public function getAccountNumberAttribute()
    {
        return $this->user->account_number ?? '';
    }

    public function getPhoneAttribute()
    {
        return $this->user->phone ?? ($this->user->account_number ?? '');
    }

    public function getSellerNameAttribute()
    {
        return $this->seller->name ?? '';
    }

    public function getBankAddressAttribute()
    {
        return $this->seller->bank_address ?? '';
    }

    public function getPriceAttribute()
    {
        return $this->hasOne(LegalDealSend::class, 'id', 'legal_deal_send_id')->value('price');
    }

    public function getHesAccountAttribute()
    {
        $legal_send = LegalDealSend::find($this->attributes['legal_deal_send_id']);
        if (!empty($legal_send)) {
            $seller = Seller::find($legal_send->seller_id);
            if (!empty($seller)) {
                if ($legal_send->way == 'bank') {
                    return $seller->bank_account;
                } elseif ($legal_send->way == 'we_chat') {
                    return $seller->wechat_account;
                } elseif ($legal_send->way == 'ali_pay') {
                    return $seller->ali_account;
                }
            }
        }
        return '';
    }

    public function getHesRealnameAttribute()
    {
        $seller = Seller::find($this->attributes['seller_id']);
        if (!empty($seller)) {
            $real = UserReal::where('user_id', $seller->user_id)->where('review_status', 2)->first();
            if (!empty($real)) {
                return $real->name;
            }
        }
        return '';
    }

    public function getUserRealnameAttribute()
    {
        $user_real = UserReal::where('user_id', $this->attributes['user_id'])->first();
        if (empty($user_real)) {
            return '';
        }
        return $user_real->name;
    }

    public function getWayNameAttribute()
    {
        return LegalDealSend::find($this->attributes['legal_deal_send_id'])->way_name;
    }

    // 是否卖方
    public function getIsSellerAttribute()
    {
        $user_id = Users::getUserId();
        $legal_send = LegalDealSend::find($this->attributes['legal_deal_send_id']);
        $seller = Seller::find($this->attributes['seller_id']);
        if ($legal_send == null || $seller == null) {
            return 0;
        }
        if (($this->attributes['user_id'] == $user_id) && ($legal_send->type == 'buy')) {
            return 1;
        } elseif (($legal_send->type == 'sell') && ($user_id == $seller->user_id)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 根据ID取消交易
     *
     * @param integer $legal_deal_id
     * @param integer $from
     * @return \App\Models\LegalDeal
     */
    public static function cancelLegalDealById($legal_deal_id, $from = 0)
    {
        try {
            return DB::transaction(function () use ($legal_deal_id, $from) {
                $legal_deal = self::lockForUpdate()->findOrFail($legal_deal_id);
                // 0未确认,1已确认,2已取消,3已付款,4维权
                if (in_array($legal_deal->is_sure, [1, 2])) {
                    throw new \Exception('当前状态不能取消交易');
                }
                // 如果不是后台操作的,要检测交易是否已付款
                if (in_array($from, [0, 1, 2]) && $legal_deal->is_sure == 3) {
                    throw new \Exception('当前已支付,不能取消交易,只能卖方维权后由平台取消');
                }
                $legal_deal_send = $legal_deal->legalDealSend->lockForUpdate()
                    ->findOrFail($legal_deal->legalDealSend->getKey());
                // 0.未完成,1.已完成,2.已撤销
                if ($legal_deal_send->is_done != 0) {
                    throw new \Exception('交易所属发布商家已完成或已撤销,不能取消该交易');
                }
                // 取消的逻辑调对应的方法
                if ($legal_deal->type == 'sell') {
                    // 买方是用户,卖方是商家
                    self::userCancelDeal($legal_deal, $from);
                } else {
                    // 买方是商家，卖方是用户
                    self::sellerCancelDeal($legal_deal, $from);
                }
                // 增加商家的发布数量(不能改变商家的发布状态,商家可能下架了交易)
                $legal_deal_send = $legal_deal->legalDealSend->lockForUpdate()->findOrFail($legal_deal->legalDealSend->getKey());
                $legal_deal_send->surplus_number = bc_add($legal_deal_send->surplus_number, $legal_deal->number);
                $legal_deal_send->save();
                return $legal_deal;
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 商家取消交易(买方是商家)
     *
     * @param \App\Models\LegalDeal $legal_deal
     * @param integer $from 操作来源
     * @return void
     */
    private static function sellerCancelDeal(&$legal_deal, $from)
    {
        try {
            DB::transaction(function () use (&$legal_deal, $from) {
                $from == 0 && $from = 2;
                $from_name = array_key_exists($from, self::$operateFrom) ? self::$operateFrom[$from] : '';
                $legal_deal = $legal_deal->lockForUpdate()->findOrFail($legal_deal->getKey());
                $legal_deal_send = $legal_deal->legalDealSend;
                $user = Users::findOrFail($legal_deal->user_id);
                $user_wallet = UsersWallet::where('currency', $legal_deal_send->currency_id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                // 退回用户冻结的法币资金到余额
                change_wallet_balance(
                    $user_wallet,
                    1,
                    -$legal_deal->number,
                    AccountLog::LEGAL_CANCEL_DEAL,
                    "法币交易:{$from_name}取消交易,冻结资金减少,交易号:{$legal_deal->id}",
                    true
                );
                change_wallet_balance(
                    $user_wallet,
                    1,
                    $legal_deal->number,
                    AccountLog::LEGAL_CANCEL_DEAL,
                    "法币交易:{$from_name}取消交易,冻结资金退回,交易号:{$legal_deal->id}"
                );
                // 退回手续费
                if (bc_comp_zero($legal_deal->out_fee) > 0) {
                    change_wallet_balance(
                        $user_wallet,
                        1,
                        $legal_deal->out_fee,
                        AccountLog::LEGAL_CANCEL_DEAL,
                        "法币交易:{$from_name}取消交易,退还手续费,交易号:{$legal_deal->id}"
                    );
                }
                // 如果是维权的添加相关信息
                if ($legal_deal->is_sure == 4) {
                    $legal_deal->handled_from = $from;
                    $legal_deal->handled_at = Carbon::now();
                }
                // 变更交易状态
                $legal_deal->is_sure = 2;
                $legal_deal->canceled_at = Carbon::now();
                $legal_deal->update_time = time();
                $legal_deal->save();
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 用户取消交易(买方是用户)
     *
     * @param \App\Models\LegalDeal $legal_deal
     * @param integer $from 操作来源
     * @return void
     */
    private static function userCancelDeal(&$legal_deal, $from)
    {
        try {
            DB::transaction(function () use (&$legal_deal, $from) {
                $from == 0 && $from = 1;
                // $from_name = array_key_exists($from, self::$operateFrom) ? self::$operateFrom[$from] : '';
                $legal_deal = $legal_deal->lockForUpdate()->findOrFail($legal_deal->getKey());
                // 如果是维权的添加相关信息
                if ($legal_deal->is_sure == 4) {
                    $legal_deal->handled_from = $from;
                    $legal_deal->handled_at = Carbon::now();
                }
                // 变更交易状态
                $legal_deal->is_sure = 2;
                $legal_deal->canceled_at = Carbon::now();
                $legal_deal->update_time = time();
                $legal_deal->save();
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 根据ID确认交易
     *
     * @param integer $legal_deal_id
     * @param integer $from
     * @return \App\Models\LegalDeal
     */
    public static function confirmLegalDealById($legal_deal_id, $from = 0)
    {
        try {
            return DB::transaction(function () use ($legal_deal_id, $from) {
                $legal_deal = self::lockForUpdate()->find($legal_deal_id);
                // 0未确认,1已确认,2已取消,3已付款,4维权
                if (in_array($legal_deal->is_sure, [1, 2])) {
                    throw new \Exception('当前状态不能确认交易');
                }
                // 如果不是后台操作的,要检测交易是否已付款
                if (in_array($from, [0, 1, 2]) && $legal_deal->is_sure != 3) {
                    throw new \Exception('当前买家未提交支付信息不能确认交易');
                }
                // 确认的逻辑：卖方的冻结币种减少，买方的币种增加，然后更改交易状态
                if ($legal_deal->type == 'sell') {
                    // 卖方是商家，买方是用户
                    self::sellerConfirmDeal($legal_deal, $from);
                } else {
                    // 买方是商家，卖方是用户
                    self::userConfirmDeal($legal_deal, $from);
                }
                return $legal_deal;
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 商家确认交易(卖方是商家)
     *
     * @param \App\Models\LegalDeal $legal_deal
     * @param integer $from 操作来源
     * @return void
     */
    private static function sellerConfirmDeal(&$legal_deal, $from = 0)
    {
        try {
            DB::transaction(function () use (&$legal_deal, $from) {
                $from == 0 && $from = 2;
                $from_name = array_key_exists($from, self::$operateFrom) ? self::$operateFrom[$from] : '';
                $legal_deal = $legal_deal->lockForUpdate()->findOrFail($legal_deal->getKey());
                // 减少商家的冻结余额
                $seller = $legal_deal->seller;
                change_seller_balance(
                    $seller,
                    -$legal_deal->number,
                    AccountLog::LEGAL_USER_BUY,
                    "法币交易:{$from_name}确认交易,减少冻结余额,交易号:{$legal_deal->id}",
                    true
                );
                // 增加用户的余额
                $user_wallet = UsersWallet::where('user_id', $legal_deal->user_id)
                    ->where('currency', $legal_deal->legalDealSend->currency_id ?? 0)
                    ->firstOrFail();
                change_wallet_balance(
                    $user_wallet,
                    1,
                    $legal_deal->number,
                    AccountLog::LEGAL_USER_BUY,
                    "法币交易:{$from_name}确认交易,余额增加,交易号:{$legal_deal->id}"
                );
                // 如果是维权的添加相关信息
                if ($legal_deal->is_sure == 4) {
                    $legal_deal->handled_from = $from;
                    $legal_deal->handled_at = Carbon::now();
                }
                // 变更交易的状态
                $legal_deal->is_sure = 1;
                $legal_deal->update_time = time();
                throw_unless($legal_deal->save(), new \Exception('交易状态变更异常'));
                return $legal_deal;
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 用户确认交易(卖方是用户)
     *
     * @param \App\Models\LegalDeal $legal_deal
     * @param integer $from 操作来源
     * @return void
     */
    private static function userConfirmDeal(&$legal_deal, $from = 0)
    {
        try {
            DB::transaction(function () use (&$legal_deal, $from) {
                $from == 0 && $from = 1;
                $from_name = array_key_exists($from, self::$operateFrom) ? self::$operateFrom[$from] : '';
                $legal_deal = $legal_deal->lockForUpdate()->findOrFail($legal_deal->getKey());
                // 减少用户的冻结余额
                $user_wallet = UsersWallet::where('user_id', $legal_deal->user_id)
                    ->where('currency', $legal_deal->legalDealSend->currency_id ?? 0)
                    ->firstOrFail();
                change_wallet_balance(
                    $user_wallet,
                    1,
                    -$legal_deal->number,
                    AccountLog::LEGAL_SELLER_BUY,
                    "法币交易:{$from_name}确认交易,减少冻结余额,交易号:{$legal_deal->id}",
                    true
                );
                // 增加商家的余额
                $seller = $legal_deal->seller;
                change_seller_balance(
                    $seller,
                    $legal_deal->number,
                    AccountLog::LEGAL_SELLER_BUY,
                    "法币交易:{$from_name}确认交易,余额增加,交易号:{$legal_deal->id}",
                );
                // 如果是维权的添加相关信息
                if ($legal_deal->is_sure == 4) {
                    $legal_deal->handled_from = $from;
                    $legal_deal->handled_at = Carbon::now();
                }
                // 变更交易的状态
                $legal_deal->is_sure = 1;
                $legal_deal->update_time = time();
                throw_unless($legal_deal->save(), new \Exception('交易状态变更异常'));
                return $legal_deal;
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getCoinCodeAttribute()
    {
        $send = $this->legalDealSend()->getResults();
        return $send->coin_code ?? '';
    }

    public function setIsSureAttribute($value)
    {
        $this->attributes['is_sure']  = $value;
        $this->attributes['update_time'] = time();
        $value == 1 && $this->attributes['confirmed_at'] = Carbon::now(); // 记录交易确认时间
        $value == 3 && $this->attributes['payed_at'] = Carbon::now(); // 记录支付时间
        $value == 4 && $this->attributes['arbitrated_at'] = Carbon::now(); // 记录维权时间
    }
}
