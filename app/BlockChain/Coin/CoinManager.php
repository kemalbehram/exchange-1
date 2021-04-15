<?php

namespace App\BlockChain\Coin;

class CoinManager
{
    protected static $makeWalletCoinList = ['btc', 'omni', 'usdt', 'bch', 'eth', 'erc20']; // 支持从接口生成钱包的币种列表

    public static function getMakeWalletCoinList()
    {
        return self::$makeWalletCoinList;
    }

    public static function resolve($name, $decimal_scale = 0, $contract_token = '', $project_name = '', $api_base_url = '')
    {
        $class_name = '\\App\\BlockChain\\Coin\\Driver\\' . $name;
        return new $class_name($decimal_scale, $contract_token, $project_name, $api_base_url);
    }
}
