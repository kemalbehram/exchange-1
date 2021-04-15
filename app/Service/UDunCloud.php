<?php

namespace App\Service;

require_once __DIR__ . '/Bipay.php';

class UDunCloud extends \Bipay
{
    const KEY = '2303962ebd602cf9a1a5413f54604649';
    // const KEY = '646d4585f8df16b50a0004e7355ad9b4';
    
    const MERCHANT_ID = 303612;
    // const MERCHANT_ID = 300384;
    
    const RECHARGE_CALLBACK = 'http://www.castprofit.com/mch/callBack';
    //const WITHDRAW_CALLBACK = 'https://dev.x-coin.pro/mch/withdrawcallBack';
    
    const WALLETID = 'WT_303612_830107884359516160';

    public function genderAddress($uid, $coinType = 60)
    {
        $timestamp = time();
        $nonce = 10000;
        $key = self::KEY;
        $body = array(
            'merchantId' => self::MERCHANT_ID,
            'coinType' => $coinType,
            'callUrl' => self::RECHARGE_CALLBACK,
            'walletld' => self::WALLETID,
            'alias' => $uid,
        );
        $body = '[' . json_encode($body) . ']';
        $sign = md5($body . $key . $nonce . $timestamp);
        $res = $this->createAddress($timestamp, $nonce, $sign, $body);
        logger()->info('uDunCloud', [
            'method' => __FUNCTION__,
            'res' => $res,
        ]);
        return $res;
    }

    public function support()
    {
        $timestamp = time();
        $nonce = 10000;
        $key = self::KEY;
        $body = array(
            'merchantId' => self::MERCHANT_ID,
            'showBalance' => true
        );
        $body = json_encode($body);
        $sign = md5($body . $key . $nonce . $timestamp);
        $res = $this->supportCoins($timestamp, $nonce, $sign, $body);
        return $res;
    }

    public function callback()
    {

    }

    public function withDraw(string $address, $amount, $businessId, $currency=3)
    {
        $timestamp = time();
        $nonce = 10000;
        $key = self::KEY;

        $coinType = 31;
        $mainCoinType = 0;
        if (false !== strpos($address, '0x')) {
            $coinType = '0xdac17f958d2ee523a2206206994597c13d831ec7';
            $mainCoinType = 60;
        }

        $body = array(
            'merchantId' => self::MERCHANT_ID,
            'coinType' => $coinType,
            'mainCoinType' => $mainCoinType,
            'address' => $address,
            'amount' => $amount,
            'callUrl' => self::WITHDRAW_CALLBACK,
            'businessId' => $businessId,
        );
        $body = '[' . json_encode($body) . ']';
        $sign = md5($body . $key . $nonce . $timestamp);
        $res = $this->transfer($timestamp, $nonce, $sign, $body);
        return $res;
    }
}