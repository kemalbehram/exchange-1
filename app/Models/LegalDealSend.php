<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class LegalDealSend extends Model
{
    protected $table = 'legal_deal_send';
    public $timestamps = false;

    protected $appends = [
        'seller_name',
        'currency_name',
        'limitation',
        'way_name',
        'currency_logo',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }

    public function getCreateTimeAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['create_time']);
    }

    public function getSellerNameAttribute()
    {
        return $this->seller->name ?? '';
    }

    public function getCurrencyNameAttribute()
    {
        return $this->currency->name ?? '';
    }

    public function getCurrencyLogoAttribute()
    {
        return $this->currency->logo ?? '';
    }

    public function getLimitationAttribute()
    {
        $surplus_number = $this->attributes['surplus_number'];
        // 当最小交易数量大于剩余数量时,最小交易额应以最后剩余数量为基数
        if (bc_comp($surplus_number, $this->attributes['min_number']) < 0) {
            $limit_data = [
                'min' => bc_mul($surplus_number, $this->attributes['price'], 5),
                'max' => bc_mul($this->attributes['max_number'], $this->attributes['price'], 5)
            ];
        } else {
            $limit_data = [
                'min' => bc_mul($this->attributes['min_number'], $this->attributes['price'], 5),
                'max' => bc_mul($this->attributes['max_number'], $this->attributes['price'], 5)
            ];
        }
        return $limit_data;
    }

    public function getWayNameAttribute()
    {
        if ($this->attributes['way'] == 'ali_pay') {
            return '支付宝';
        } elseif ($this->attributes['way'] == 'we_chat') {
            return '微信';
        } elseif ($this->attributes['way'] == 'bank') {
            return Seller::find($this->attributes['seller_id'])->bank_name;
        }
    }

    /**
     * 检测该发布信息下是否有未完成的订单
     *
     * @param integer $id
     * @return boolean
     */
    public static function isHasIncompleteness($id)
    {
        // 0未确认,1已确认,2已取消,3已付款,4维权
        return LegalDeal::where('legal_deal_send_id', $id)
            ->whereNotIn('is_sure', [1, 2])
            ->exists();
    }

    /**
     * 撤回发布
     *
     * @param integer $id
     * @param integer $from
     * @return bool
     * @throws \Exception
     */
    public static function sendBack($id, $from = 0)
    {
        try {
            DB::transaction(function () use ($id, $from) {
                $from == 0 && $from = 2;
                $operate_from = LegalDeal::getOperateFromList();
                $from_name = array_key_exists($from, $operate_from) ? $operate_from[$from] : '';
                $legal_send = self::lockForUpdate()->findOrFail($id);
                if ($legal_send->is_shelves != 2) {
                    throw new \Exception('必须下架后才可以撤销');
                }
                if ($legal_send->is_done != 0) {
                    throw new \Exception('该发布已撤回或已完成,无法撤销');
                }
                if (bc_comp_zero($legal_send->surplus_number) <= 0) {
                    throw new \Exception('当前发布剩余数量不足,无法撤销');
                }
                if (LegalDealSend::isHasIncompleteness($id)) {
                    // 0未确认,1已确认,2已取消,3已付款,4维权
                    throw new \Exception('该发布信息下有交易未结束,请等待结束再撤回,如不想继续匹配新交易可以选择下架交易');
                }
                if ($legal_send->type == 'sell') {
                    $seller = Seller::LockForUpdate()->findOrFail($legal_send->seller_id);
                    change_seller_balance(
                        $seller,
                        -$legal_send->surplus_number,
                        AccountLog::SELLER_BACK_SEND,
                        "法币交易:{$from_name}撤回发布,减少冻结,需求号:{$legal_send->id}",
                        true
                    );
                    change_seller_balance(
                        $seller,
                        $legal_send->surplus_number,
                        AccountLog::SELLER_BACK_SEND,
                        "法币交易:{$from_name}撤回发布,退回余额,需求号:{$legal_send->id}"
                    );
                    // 手续费撤回
                    $surplus_ratio = bc_div($legal_send->surplus_number, $legal_send->total_number); // 剩余数量所占发布的比例
                    $should_refund_fee = bc_mul($legal_send->out_fee, $surplus_ratio);
                    if (bc_comp_zero($should_refund_fee) > 0) {
                        change_seller_balance(
                            $seller,
                            $should_refund_fee,
                            AccountLog::LEGAL_CANCEL_TRADE_FREE,
                            "法币交易:{$from_name}撤回发布,退回手续费,需求号:{$legal_send->id}"
                        );
                    }
                }
                $legal_send->is_done = 2;
                $legal_send->save();
                return $legal_send;
            });
            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
