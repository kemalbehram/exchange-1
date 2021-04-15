<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerAccountLog extends Model
{
    protected $table = 'seller_account_log';

    protected $appends = [
        'seller_name',
        'account_number',
        'currency_name',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    public function getSellerNameAttribute()
    {
        return $this->seller->name ?? '';
    }

    public function getAccountNumberAttribute()
    {
        return $this->user->account_number ?? '';
    }

    public function getCurrencyNameAttribute()
    {
        return $this->currency->name ?? '';
    }
}
