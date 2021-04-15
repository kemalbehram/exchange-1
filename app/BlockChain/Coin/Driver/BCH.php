<?php

namespace App\BlockChain\Coin\Driver;

use App\BlockChain\Coin\BaseCoin;

class BCH extends BaseCoin
{
    protected $coinCode = 'BCH';

    protected $decimalScale = 8; //小数位数

    protected $generateUri = '/v3/wallet/address'; //生成钱包

    protected $balanceUri = '/wallet/bch/balance'; //查询余额

    protected $transferUri = '/v3/wallet/bch/sendto'; //转账

    protected $transactionUri = '/wallet/bch/tx'; //交易记录

    protected $billsUri = ''; //账单
}
