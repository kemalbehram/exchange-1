<?php


require_once __DIR__ . '/function.php';

class Bipay {

    const URL = 'https://hd01-hk-node.uduncloud.com'; //请求域名
    // const URL = 'https://hk01-hk-node.uduncloud.com'; //请求域名
    const CREATE_ADDRESS_URL = '/mch/address/create'; //根商户向下产生地址
    const TRANSFER_URL = '/mch/withdraw'; //商户发起转账申请
    const QUERY_URL = '/mch/transaction'; //查询账单总记录及详细记录
    const CREATE_BATCH_ADDRESS_URL = '/mch/address/create/batch'; //生成批量地址
    const PROXYPAY = '/mch/withdraw/proxypay'; //代付
    const SUPPORT_COINS = '/mch/support-coins'; //支持币种
    const CHECK_ADDRESS = '/mch/check-address'; //检查是否为商户内部地址

    /**
     * 根商户向下产生地址
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function createAddress($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::CREATE_ADDRESS_URL;
        return $this->post($url, json_encode($param));
    }

    /**
     * 转账申请
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function transfer($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::TRANSFER_URL;
        return $this->post($url, json_encode($param));
    }

    /**
     * 查询转账记录
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function queryTransferLog($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::QUERY_URL;
        return $this->post($url, json_encode($param));
    }
	
	/**
     * 生成批量地址
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function createBatchAddress($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::CREATE_BATCH_ADDRESS_URL;
        return $this->post($url, json_encode($param));
    }
	
	/**
     * 代付
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function proxypay($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::PROXYPAY;
        return $this->post($url, json_encode($param));
    }
	
	/**
     * 支持币种
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function supportCoins($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::SUPPORT_COINS;
        return $this->post($url, json_encode($param));
    }
	
	/**
     * 检查是否为商户内部地址
     * @author Hevin <3903390302@qq.com>
     * @return array
     */
    public function checkAddress($timestamp = '', $nonce = '', $sign = '', $body = '') {
        $param = array(
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'body' => $body
        );

        $url = self::URL . self::CHECK_ADDRESS;
        return $this->post($url, json_encode($param));
    }
    
    public function post($url, $param) {
        $ch = curl_init();

        //如果$param是数组的话直接用
        curl_setopt($ch, CURLOPT_URL, $url);

        //如果$param是json格式的数据，则打开下面这个注释
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($param))
        );

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //如果用的协议是https则打开下面这个注释
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data,  true);
    }

}
