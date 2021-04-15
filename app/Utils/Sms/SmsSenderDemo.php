<?php

namespace Kewail\Sms\Demo;

require_once "SmsSingleSender.php";

use Kewail\Sms\SmsSingleSender;

try {
    // 请根据实际 accesskey 和 secretkey 进行开发，以下只作为演示 sdk 使用
    $accesskey = "5fc9c7e2e85f087cde8cb39e";
    $secretkey = "f2196b92b25b4b408e8d77a0aa69dbfe ";
    $phoneNumber = "13439302541";
    
    $singleSender = new SmsSingleSender($accesskey, $secretkey);

    // 普通单发
    $result = $singleSender->send(0, "86", $phoneNumber , "【Kewail科技】您注册的验证码：128128有效时间30分钟。", "", "");
    $rsp = json_decode($result);
    echo $result;
    echo "<br>";

} catch (\Exception $e) {
    echo var_dump($e);
}
