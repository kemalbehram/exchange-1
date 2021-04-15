<?php

/**
 * Created by PhpStorm.
 * User: swl
 * Date: 2018/7/3
 * Time: 10:23
 */

namespace App\Models;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use App\BlockChain\Coin\CoinManager;
use App\Jobs\UpdateBalance;

class UsersWallet extends Model
{
    protected $table = 'users_wallet';
    public $timestamps = false;

    const CURRENCY_DEFAULT = "USDT";

    protected static $balanceTypeList = [
        1 => '法币账户',
        2 => '币币账户',
        3 => '合约账户',
    ];

    protected $hidden = [
        'private',
    ];

    protected $appends = [
        'currency_name',
        'currency_type',
        'contract_address',
        'is_legal',
        'is_lever',
        'is_match',
        'usdt_price',
        'multi_protocol',
        'label', //标签
    ];

    /**
     * 返回账户类型列表
     *
     * @return array
     */
    public static function getBalanceTypeList()
    {
        return self::$balanceTypeList;
    }

    public function getCreateTimeAttribute()
    {
        $value = $this->attributes['create_time'];
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    public function getCurrencyTypeAttribute()
    {
        return $this->currencyCoin->type ?? '';
    }

    public function getMultiProtocolAttribute()
    {
        return $this->currencyCoin->multi_protocol ?? 0;
    }

    public function getLabelAttribute()
    {
       $type = $this->currencyCoin->make_wallet ?? 0;
       if ($type == 2) {
           return $this->user->extension_code ?? '';
       }
       return '';
    }

    public function getCurrencyNameAttribute()
    {
        return $this->currencyCoin->name ?? '';
    }

    public function getContractAddressAttribute()
    {
        return $this->currencyCoin->contract_address ?? '';
    }

    public function getIsLegalAttribute()
    {
        return $this->currencyCoin->is_legal ?? 0;
    }

    public function getIsLeverAttribute()
    {
        return $this->currencyCoin->is_lever ?? 0;
    }

    public function getIsMatchAttribute()
    {
        return $this->currencyCoin->is_match ?? 0;
    }

    /*public function getAddressAttribute($value)
    {
        $make_wallet_type = $this->currencyCoin->make_wallet ?? 0; // 生成用户钱包的策略:0.不生成,1.接口生成,2.从归拢地址继承,3.空钱包
        switch ($make_wallet_type) {
            case 0:
                $address = null;
                break;
            case 1:
                $address = $value ?? 'ERROR';
                break;
            case 2:
                $address = $this->currencyCoin->collect_account ?? 'UNDEFINED';
                break;
            case 3:
                $address = $value;
                break;
            default:
                $address = '';
                break;
        }
        return $address;
    }*/

    public function currencyCoin()
    {
        return $this->belongsTo(Currency::class, 'currency', 'id');
    }

    /**
     * 根据用户ID来生成钱包
     * @param mixed $user_id 
     * @return bool 
     */
    public static function makeWallet($user_id)
    {
        $currency = Currency::all();
        $uri = '/v3/wallet/address';
        $project_name = config('app.name');
        $http_client = app('LbxChainServer');

        if (config('app.debug')) {
            //return true;
        }
        /** @var Response $response */
        $response = $http_client->post($uri, [
            'form_params' => [
                'userid' => $user_id,
                'projectname' => $project_name,
            ]
        ]);
        $result = json_decode($response->getBody()->getContents());
        if ($result->code != 0) {
            throw new \Exception($result->msg);
        }
        $address = $result->data;
        foreach ($currency as $key => $value) {
            // 判断对应币种钱包是否已存在
            try {
                if (self::where('user_id', $user_id)->where('currency', $value->id)->exists()) {
                    continue;
                }
                $user_wallet = new self();
                $user_wallet->user_id = $user_id;
                $user_wallet->currency = $value->id;
                $type_name = strtolower($value->type);

                if ($value->make_wallet == 0) {
                    continue;
                } elseif ($value->make_wallet == 1) {
                    if (!in_array($type_name, CoinManager::getMakeWalletCoinList())) {
                        continue;
                    }
                    $user_wallet->address = $address->{"{$type_name}_address"};
                    $user_wallet->private = $address->{"{$type_name}_private"};
                } elseif ($value->make_wallet == 2) {
                    $user_wallet->address = $value->collect_account;
                    $user_wallet->private = '';
                } elseif ($value->make_wallet == 3) {
                    $user_wallet->address = '';
                    $user_wallet->private = '';
                }

                $user_wallet->create_time = time();
                $user_wallet->save(); //默认生成所有币种的钱包
            } catch (\Exception $ex) {
                logger()->error('createWallet', [
                    'error' => $ex->getMessage(),
                ]);
            }

        }
    }

    /**
     * 从链上监听余额变动
     * @param mixed $user_wallet 
     * @return void 
     */
    public static function queryChainBalance($user_wallet)
    {
        $wallet_list = [];
        $policy = [0, 1, 5, 10, 20, 30];
        $currency = $user_wallet->currencyCoin;
        if ($currency->multi_protocol == 1) {
            $wallet_list = self::whereHas('currencyCoin', function ($query) use ($currency) {
                    $query->where('parent_id', $currency->id);
                })->where('user_id', $user_wallet->user_id)
                ->get();
        } else {
            $wallet_list[] = $user_wallet;
        }
        foreach ($wallet_list as $wallet) {
            foreach ($policy as $value) {
                UpdateBalance::dispatch($wallet)
                    ->onQueue('update:block:balance')
                    ->delay(Carbon::now()->addMinutes($value));
            }
        }
    }

    public function getUsdtPriceAttribute()
    {
        $currency_id = $this->attributes['currency'];
        return Currency::getUsdtPrice($currency_id);
    }

    public function getPbPriceAttribute()
    {
        $currency_id = $this->attributes['currency'];
        return Currency::getPbPrice($currency_id);
    }

    public function getCnyPriceAttribute()
    {
        $currency_id = $this->attributes['currency'];
        return Currency::getCnyPrice($currency_id);
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function getPrivateAttribute($value)
    {
        return $value;
        return empty($value) ? '' : decrypt($value);
    }

    public function setPrivateAttribute($value)
    {
        $this->attributes['private'] = $value;//encrypt($value);
    }

    public function getAccountNumberAttribute($value)
    {
        return $this->user()->value('account_number') ?? '';
    }
}
