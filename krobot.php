<?php

set_time_limit(0);

$tradePriceRateMin = 0.005;
$tradePriceRateMax = 0.01;
$cronRate = 60;
$cronTradePriceRate = 0.8;

$db_host = '172.19.125.182';
$db_name = 'exchange';
$db_user = 'exchange';
$db_password = 'dPySFx2FcGnwdt6h';

$mysqliHelper = new MysqliHelper($db_host, $db_user, $db_password, $db_name);


$startTime = time();

while (TRUE) {

    $robotList = $mysqliHelper->queryToArray('select * from k_robot where kr_status=1');

    if ($robotList && count($robotList)) {

        foreach ($robotList as $robot) {

            $cron_end_update = FALSE;

            $user_auth = getAuth();
            $tradeType = mt_rand(0, 1);

            $robotPriceDecimal = intval($robot['kr_price_decimal']);
            $robotNumberDecimal = intval($robot['kr_number_decimal']);

            $now_price = $mysqliHelper->queryOne('select now_price from currency_quotation where legal_id=' . $robot['kr_money'] . ' and currency_id=' . $robot['kr_stock'] . ';')['now_price'];
            $now_price = $now_price ?: 0;

            $tradePrice = randomFloat($robot['kr_min_price'], $robot['kr_max_price'], $robotPriceDecimal);
            $tradeNumber = randomFloat($robot['kr_min_number'], $robot['kr_max_number'], $robotNumberDecimal);

            $tradePrice = bccomp($now_price, 0, $robotPriceDecimal) > 0 ? $now_price : $tradePrice;

            if ($robot['kr_cron_status'] > 0 && time() > $robot['kr_cron_start']) {

                if (time() <= $robot['kr_cron_end']) {

                    $needCron = mt_rand(0, $cronRate);

                    if ($needCron > 0) {

                        //概率不执行计划

                        $priceHeight = bcsub($robot['kr_max_price'], $robot['kr_min_price'], $robotPriceDecimal);

                        $tradePriceRate = randomFloat($tradePriceRateMin, $tradePriceRateMax, $robotPriceDecimal);

                        $priceChange = mt_rand(0, 1);

                        if ($priceChange > 0) {

                            $tradePrice = bcadd($tradePrice, bcmul($priceHeight, $tradePriceRate, $robotPriceDecimal), $robotPriceDecimal);
                        }else{

                            $tradePrice = bcsub($tradePrice, bcmul($priceHeight, $tradePriceRate, $robotPriceDecimal), $robotPriceDecimal);
                        }
                    }else{

                        //剩余时间比例
                        $cronTimeRate = bcdiv(time() - $robot['kr_cron_start'], $robot['kr_cron_end'] - $robot['kr_cron_start'], 2);

                        $cronPriceSub = $tradePrice;


                        if (bccomp($robot['kr_cron_end_price'], $tradePrice, 8) >= 0) {

                            $tradePrice = bcadd(bcmul(bcsub($robot['kr_cron_end_price'], $tradePrice, 8), $cronTimeRate, 8), $tradePrice, 8);
                        }else{

                            $tradePrice = bcsub($tradePrice, bcmul(bcsub($tradePrice, $robot['kr_cron_end_price'], 8), $cronTimeRate, 8), 8);
                        }
                    }
                }else{

                    if ((time() - $robot['kr_cron_end']) < 60) {

                        //超出时间，必须直接到达预定价格
                        $tradePrice = $robot['kr_cron_end_price'];
                    }

                    $cron_end_update = TRUE;
                }
            }else{

                $priceHeight = bcsub($robot['kr_max_price'], $robot['kr_min_price'], $robotPriceDecimal);

                $tradePriceRate = randomFloat($tradePriceRateMin, $tradePriceRateMax, $robotPriceDecimal);

                $priceChange = mt_rand(0, 1);

                if ($priceChange > 0) {

                    $tradePrice = bcadd($tradePrice, bcmul($priceHeight, $tradePriceRate, $robotPriceDecimal), $robotPriceDecimal);
                }else{

                    $tradePrice = bcsub($tradePrice, bcmul($priceHeight, $tradePriceRate, $robotPriceDecimal), $robotPriceDecimal);
                }
            }

            if (bccomp($robot['kr_max_price'], $tradePrice, $robotPriceDecimal) < 0) {

                $tradePrice = $robot['kr_max_price'];
            }

            if (bccomp($robot['kr_min_price'], $tradePrice, $robotPriceDecimal) > 0) {

                $tradePrice = $robot['kr_min_price'];
            }

            $sellDepth = getDepth(0, $robot['kr_money'], $robot['kr_stock'], $user_auth);
            $buyDepth = getDepth(1, $robot['kr_money'], $robot['kr_stock'], $user_auth);

            $tradeNumbertTemp = 0;

            if ($tradeType === 0 && count($buyDepth)) {

                foreach ($buyDepth as $buy) {

                    if (bccomp($tradePrice, $buy['price'], $robotPriceDecimal) <= 0) {

                        $tradeNumbertTemp = bcadd($tradeNumbertTemp, $buy['number'], $robotNumberDecimal);
                    }
                }
            }

            if ($tradeType === 1 && count($sellDepth)) {

                foreach ($sellDepth as $sell) {

                    if (bccomp($tradePrice, $sell['price'], $robotPriceDecimal) >= 0) {

                        $tradeNumbertTemp = bcadd($tradeNumbertTemp, $sell['number'], $robotNumberDecimal);
                    }
                }
            }

            if (bccomp($tradeNumbertTemp, 0, $robotNumberDecimal) > 0) {

                $tradeNumber = bcadd($tradeNumbertTemp, $tradeNumber, $robotNumberDecimal);
            }

            echo "\r\n【" . date('Y-m-d H:i:s', time()) . "】 【" . $robot['kr_stock'] . "-" . $robot['kr_money'] . "】 【" . ($tradeType > 0 ? '买单' : '卖单') . "】 【价格：" . $tradePrice . "】 【数量：" . $tradeNumber . "】 ";

            $exResult = doEx($robot['kr_stock'], $robot['kr_money'], $tradePrice, $tradeNumber, $robot['kr_user_ex_password'], $tradeType, $user_auth);

            if ($exResult === TRUE) {

                echo "【交易成功】";

                $user_id = $mysqliHelper->queryOne("select id from users where account_number='" . $robot['kr_user'] . "';")['id'];
                $mysqliHelper->execQuery('delete from account_log where user_id=' . $user_id);

                if ($cron_end_update) {

                    $mysqliHelper->execQuery('update k_robot set kr_max_price=kr_cron_end_max_price,kr_min_price=kr_cron_end_min_price,kr_cron_status=0 where kr_id=' . $robot['kr_id']);
                }
            }else{

                if ($exResult == '请登录') {

                    echo "【登陆超时】 正在重新登陆...  ";

                    $loginResult = doLogin($robot['kr_user'], $robot['kr_user_password']);

                    if ($loginResult === FALSE) {

                        echo "【登陆失败：" . $loginResult . "】";
                    }else{

                        echo "【登陆成功】";
                    }
                }else{

                    echo "【交易失败：" . $exResult . "】";
                }
            }

            sleep(1);
        }
    }

    sleep(5);

    echo "\r\n";
}


function getDepth($type, $money, $stock, $auth, $limit = 50){

    $result = FALSE;

    $url = 'http://47.242.108.153/api/transaction_' . ($type > 0 ? 'in' : 'out') . '?_timespan=' . msectime();

    $params = array(

        'legal_id' => $money,
        'currency_id' => $stock,
        'page' => 1,
        'limit' => $limit
    );

    $header = array('AUTHORIZATION:' . $auth);

    $requestResult = doPost($url, $params, $header);

    if ($requestResult && count($requestResult) && isset($requestResult['type']) && isset($requestResult['message']) && $requestResult['type'] == 'ok') {

        $result = $requestResult['message']['list'];
    }else{

        $result = FALSE;
    }

    return $result;
}


function doEx($stock, $money, $price, $number, $expassword, $exType, $auth){

    $result = FALSE;

    $url = 'http://47.242.108.153/api/transaction/' . ($exType > 0 ? 'in' : 'out') . '?_timespan=' . msectime();

    $params = array(

        'legal_id' => $money,
        'currency_id' => $stock,
        'price' => $price,
        'num' => $number,
        'password' => $expassword
    );

    $header = array('AUTHORIZATION:' . $auth);

    $requestResult = doPost($url, $params, $header);

    if ($requestResult && count($requestResult) && isset($requestResult['type']) && isset($requestResult['message']) && $requestResult['type'] == 'ok') {

        $result = TRUE;
    }else{

        $result = $requestResult['message'];
    }

    return $result;
}


function getAuth(){

    $redis_host = '172.19.125.182';
    $redis_port = 6379;

    $redis = new Redis();
    $redis->connect($redis_host, $redis_port);

    return strval($redis->get('krobot_user_auth'));
}


function setAuth($auth){

    $redis_host = '172.19.125.182';
    $redis_port = 6379;

    $redis = new Redis();
    $redis->connect($redis_host, $redis_port);

    return $redis->set('krobot_user_auth', $auth);
}


function doLogin($account_number, $password){

    $result = FALSE;

    $url = 'http://47.242.108.153/api/user/login?_timespan=' . msectime();

    $params = array(

        'user_string' => $account_number,
        'password' => $password,
        'sms_code' => '',
        'country_code' => '+86'
    );

    $requestResult = doPost($url, $params);

    if ($requestResult && count($requestResult) && isset($requestResult['type']) && isset($requestResult['message']) && $requestResult['type'] == 'ok') {

        $result = $requestResult['message'];
        setAuth($result);
    }

    return $result;
}


function msectime() {

    list($msec, $sec) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
}


function doPost($url, $params = array(), $header = array()){

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,FALSE);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($curl);
    curl_close($curl);

    return json_decode($output, true);
}


function randomFloat($min, $max, $decimal){

    return bcadd($min, bcmul(bcdiv(mt_rand(), mt_getrandmax(), $decimal), bcsub($max, $min, $decimal), $decimal), $decimal);
}

function dump($var, $isCmd = true) {

    ob_start();
    var_dump($var);
    $output = ob_get_clean();
    if (!extension_loaded('xdebug')) {
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (!$isCmd) {

            $output = '<pre>' . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }

    echo($output);
}


/**
 * Mysqli数据库操作类
 * @author HuangYu
 */
class MysqliHelper{

    //mysqli实例对象的容器
    private $mysqli = null;

    //对应数据库属性的成员变量
    private $dbHost = '';
    private $dbUser = '';
    private $dbPwd = '';
    private $dbName = '';
    private $dbPort = '';
    private $dbEncode = '';

    /**
     * 构造函数，初始化mysqli的实例对象
     * @param unknown $dbHost   数据库地址
     * @param unknown $dbUser   数据库用户名
     * @param unknown $dbPwd    数据库密码
     * @param unknown $dbName   数据库名
     * @param number $dbPort    数据库端口，默认3306
     * @param string $dbEncode  数据库编码，默认utf8
     */
    public function __construct($dbHost, $dbUser, $dbPwd, $dbName, $dbPort = 3306, $dbEncode = 'utf8'){

        $this->dbHost = $dbHost;
        $this->dbUser = $dbUser;
        $this->dbPwd = $dbPwd;
        $this->dbName = $dbName;
        $this->dbPort = $dbPort;
        $this->dbEncode = $dbEncode;
    }

    /**
     * 创建连接
     */
    private function getConnect(){
        //实例化mysqli对象，mysqli::__construct()创建数据库连接
        $this->mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPwd, $this->dbName, $this->dbPort);
        //mysqli::set_charset()设置连接的字符集编码
        $this->mysqli->set_charset($this->dbEncode);
    }

    /**
     * 获取若干关于Mysql的信息
     * @return unknown  返回信息数组
     */
    public function getMysqlInfo(){
        $this->getConnect();

        $info['client_info'] = $this->mysqli->client_info;
        $info['client_version'] = $this->mysqli->client_version;
        $info['server_info'] = $this->mysqli->server_info;
        $info['server_version'] = $this->mysqli->server_version;
        $info['host_info'] = $this->mysqli->host_info;
        $info['protocol_version'] = $this->mysqli->protocol_version;

        $this->closeConn();
        return $info;
    }

    /**
     * 将SELECT或SHOW等sql查询语句结果以数组的形式返回
     * @param unknown $sql  sql语句
     * @return Ambigous <NULL, unknown> array，返回数组，以字段名做键值
     */
    public function queryToArray($sql){
        $this->getConnect();

        //mysqli::query()执行查询语句时，返回一个mysqli_result对象
        $result = $this->mysqli->query($sql);
        $resultTemp = null;

        //mysqli_result::fetch_assoc()返回关联数组
        //mysqli_result::fetch_array()返回索引数组
        //mysqli_result::fetch_object()返回当前行对象
        while($row = $result->fetch_assoc()){
            $resultTemp[] = $row;
        }

        $this->closeConn($result);
        return $resultTemp;
    }

    /**
     * 将SELECT或SHOW等sql查询语句结果的第一行数据以一维数组的形式返回
     * @param unknown $sql  sql语句
     * @return Ambigous <NULL, unknown, mixed> array，返回一维数组
     */
    public function queryOne($sql){
        $this->getConnect();
        $result = $this->mysqli->query($sql);

        if($row = $result->fetch_assoc()){
            $this->closeConn($result);
            return $row;
        }

        return null;
    }

    /**
     * 返回非查询的SQL语句的执行结果
     * @param unknown $sql  sql语句
     * @return mixed        array，返回数组，包含操作结果true或false，操作影响的行数，如果是insert操作还将返回添加的ID（如果不是Insert操作返回0）
     */
    public function execQuery($sql){
        $this->getConnect();

        //mysqli::query()执行操作语句时，返回操作结果true和false
        $result['status'] = $this->mysqli->query($sql);
        //mysqli::query()执行的是insert操作时，mysqli::insert_id可以获取到上一次操作的插入ID
        $result['insert_id'] = $this->mysqli->insert_id;
        //mysqli::query()执行操作语句时，mysqli::affected_rows返回上一次操作时的受影响行数
        $result['affected'] = $this->mysqli->affected_rows;

        $this->closeConn();
        return $result;
    }

    /**
     * 释放结果集，关闭连接
     * @param unknown $result
     */
    private function closeConn($result = null){
        if($result)
            //释放结果集
            $result->free();
        //关闭数据库连接
        $this->mysqli->close();
    }
}

