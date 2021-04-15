<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Notifications\Notifiable;
use PHPMailer\PHPMailer\PHPMailer;
use App\Utils\RPC;
use App\Models\{SmsProject, Setting, Users};
use App\Utils\Sms\SmsSingleSender;

class SmsController extends Controller
{
    use Notifiable;
    private $_sms_ip_check_expire_time = 60;
    /**
     * Send SMS
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        $ALIYUN_SMS_AK = env("ALIYUN_SMS_AK");
        $ALIYUN_SMS_AS = env("ALIYUN_SMS_AS");
        $ALIYUN_SMS_SIGN_NAME = env("ALIYUN_SMS_SIGN_NAME");
        $ALIYUN_SMS_VARIABLE = env("ALIYUN_SMS_VARIABLE");  //Content Variable
        $tplId = env('ALIYUN_SMS_CODE');                //TemplateID TemplateCODE The Format Is SMS_140736882

        if (empty($tplId) || empty($ALIYUN_SMS_AK) || empty($ALIYUN_SMS_AS) || empty($ALIYUN_SMS_SIGN_NAME) || empty($ALIYUN_SMS_VARIABLE)) {
            return $this->error('System Configuration Error，Please Contact The System Administrator');
        }
        Config::set("aliyunsms.access_key", $ALIYUN_SMS_AK);
        Config::set("aliyunsms.access_secret", $ALIYUN_SMS_AS);
        Config::set("aliyunsms.sign_name", $ALIYUN_SMS_SIGN_NAME);
        $mobile = request()->input('mobile', '');
        if (empty($mobile)) {
            return $this->error('Mobile Phone Number Cannot Be Empty');
        }
        //Inspect1Within MinutesipHas The Captcha Been Sent
        // if ($this->checkSmsIp($request->ip().$mobile)) {
        //     return $this->error('Captcha Sent Too Frequently');
        // }
        $verification_code = $this->createSmsCode(6);
        $params = [
            $ALIYUN_SMS_VARIABLE => $verification_code
        ];

        try {
            $smsService = \App::make('Curder\LaravelAliyunSms\AliyunSms');
            $return = $smsService->send(strval($mobile), $tplId, $params);

            if ($return->Message == "OK") {
                //Creditsession
                session(['sms_captcha' => $verification_code]);
                session(['sms_mobile' => $mobile]);

                //Set Cachekey
                //$this->setSmsIpKey($request->ip().$mobile, $mobile);
                return $this->success("Sent Successfully");
            } else {
                return $this->error($return->Message);
            }
        } catch (\ErrorException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function mordulaSend(Request $request){
        $mobile = $request->get('user_string');
        if (empty($mobile)) return $this->error('Phone Number Cannot Be Empty');
        $type = $request->get('type');//
        if ($type == 'forget') {
            $user = Users::getByString($mobile);
            if (empty($user)) return $this->error('Account Number Error');
        } else {
            $user = Users::getByString($mobile);
            if (!empty($user)) return $this->error('Account Number Already Exists');
        }
        if (empty($mobile)) {
            return $this->error('Please Fill In Your Mobile Number');
        }
        $country_code = $request->get('country_code',86);
        $country_code = str_replace('+', '', $country_code);

//        $mobile='+'.$area_code.$mobile;
        // $mobile=urlencode($str);
        $verification_code = $this->createSmsCode(6);
        session()->put('code@' . $country_code . $mobile, $verification_code);

        $singleSender = new SmsSingleSender(config('app.mordula_accesskey'), config('app.mordula_secretkey'));

        // Ordinary Single Shot
        $result = $singleSender->send(0, $country_code, $mobile , "【castprofit】 your registration code ：".$verification_code, "", "");
        $res = json_decode($result);
    
        if($res->result == 0){
            Cache::put("{$mobile}@{$country_code}", 1, Carbon::now()->addSeconds(59));

            return $this->success('Sent Successfully');
        }else{
            //   var_dump($res->code());exit;
            return $this->error('Fail In Send'.$res->errmsg);
        }
    }
    
    /**
     * Send SMS
     */
    public function smsSend(Request $request)
    {
        $mobile = $request->input('user_string', '');
        $country_code = $request->input('country_code', 86);
        $scene = $request->input('scene', '');
        $scene_list = SmsProject::enumScene();
        $region_list = SmsProject::enumRegion();
        $country_code = str_replace('+', '', $country_code);
        if (Cache::has("{$mobile}@$country_code")) {
            return $this->error('Please Do Not Click Repeatedly');
        }
        if (empty($scene) || !in_array($scene, array_keys($scene_list))) {
            return $this->error('SMS Scene Error');
        }
        if (empty($country_code) || !in_array($country_code, array_keys($region_list))) {
            return $this->error('International Area Code Error');
        }
        if (empty($mobile)) {
            return $this->error('Phone Number Cannot Be Empty');
        }
        $has_user_scene_list = [
            'login',
            'change_password',
            'reset_password',
        ];
        if (in_array($scene, $has_user_scene_list)) {
            // Change The Password Directly From The LoginsessionPick Up Users
            if ($scene == 'change_password') {
                $user_id = Users::getUserId();
                $user = Users::findOrFail($user_id);
                $country_code = $user->country_code;
            } else {
                $user = Users::getByString($mobile, $country_code);
            }
            if (empty($user)) {
                return $this->error('Account Does Not Exist');
            }
        } else {
            $user = Users::getByString($mobile, $country_code);
            if (!empty($user)) {
                return $this->error('Account Number Already Exists');
            }
        }
        //Take SMS Template,If It Doesn't Exist, It Will Be The Default
        $sms_project = SmsProject::where('scene', $scene)->get();
        if (count($sms_project) <= 0) {
            return $this->error('SMS Template Does Not Exist');
        }
        $project = $sms_project->where('country_code', $country_code)->first();
        $project || $project = $sms_project->where('is_default', 1)->first();
        if (!$project) {
            return $this->error('SMS Template Does Not Exist');
        }
        if (empty($mobile)) {
            return $this->error('Please Fill In Your Mobile Number');
        }
        $content = $project->project ?? $project->contents; //Only Templates Are Needed For SaiYouid,No Need To Splice Content
        $verification_code = $this->createSmsCode(6);
        session()->put('code@' . $country_code . $mobile, $verification_code);
        $class_name = '\\App\Notifications\\';
        if ($scene == 'register') {
            $class_name .= 'UserRegisterCode';
        } elseif ($scene == 'login') {
            $class_name .= 'UserLoginCode';
        } elseif ($scene == 'change_password') {
            $class_name .= 'ChangePasswordCode';
        } elseif ($scene == 'reset_password') {
            $class_name .= 'ResetPasswordCode';
        }
        $notification = new $class_name($mobile, $content, ['code' => $verification_code], $country_code);
        try {
            $this->notify($notification);
            Cache::put("{$mobile}@{$country_code}", 1, Carbon::now()->addSeconds(59));
            return $this->success('Sent Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * SMS Bao Sends SMS
     */
    public function smsBaoSend(Request $request)
    {
        $mobile = $request->input('user_string');
        if (empty($mobile)) {
            return $this->error('Phone Number Cannot Be Empty');
        }
        $type = $request->input('type'); //
        if ($type == 'forget' || $type == 'login') {
            $user = Users::getByString($mobile);
            if (empty($user)) {
                return $this->error('Account Number Error');
            }
        } else {
            $user = Users::getByString($mobile);
            if (!empty($user)) {
                return $this->error('Account Number Already Exists');
            }
        }

        /* $user = Users::getByString($mobile);
        if(!empty($user)) return $this->error('Account Number Already Exists'); */
        $username = Setting::getValueByKey('smsBao_username', '');
        $password = Setting::getValueByKey('password', '');
        $sms_signature = Setting::getValueByKey('sms_signature', '【X-coin】');
        if (empty($mobile)) {
            return $this->error('Please Fill In Your Mobile Number');
        }
        $verification_code = $this->createSmsCode(4);

        $content = $sms_signature . 'Your Verification Code Is [' . $verification_code . ']，Do Not Leak。';
        $api = 'http://api.smsbao.com/sms';
        $send_url = $api . "?u=" . $username . "&p=" . md5($password) . "&m=" . $mobile . "&c=" . urlencode($content);
        $return_message = RPC::apihttp($send_url);
        if ($return_message == 0) {
            session(['code' => $verification_code]);
            return $this->success('Sent Successfully');
        } else {
            $statusStr = array(
                "-1" => "Incomplete Parameters",
                "-2" => "Server Space Is Not Supported,Please Confirm Your SupportcurlPerhapsfsocket，Contact Your Space Provider To Solve Or Replace The Space！",
                "30" => "Wrong Password",
                "40" => "Account Does Not Exist",
                "41" => "Sorry, Your Credit Is Running Low",
                "42" => "Account Expired",
                "43" => "IPAddress Restrictions",
                "44" => "Account Disabled",
                "50" => "The Content Contains Sensitive Words",
            );
            return $this->error($statusStr[$return_message]);
        }
    }

    /**
     * Inspect1Within Minutes$ipHas The Captcha Been Sent
     * @param $ip
     * @return bool|\Illuminate\Http\JsonResponse
     */
    private function checkSmsIp($ip)
    {
        if (empty($ip)) {
            return $this->error('ipParameter Is Incorrect');
        }
        return $this->checkSmsIpKey($ip);
    }

    /**
     * Generate SMS Verification Code
     * @param int $num  Number Of Verification Codes
     * @return string
     */
    public function createSmsCode($num = 6)
    {
        //Captcha Character Array
        $n_array = range(0, 9);
        //Random Generation$numBit Captcha Character
        $code_array = array_rand($n_array, $num);
        //Reorder The Array Of Captcha Characters
        shuffle($code_array);
        //Generate Verification Code
        $code = implode('', $code_array);
        return $code;
    }

    /**
     * Set UpsmsSend SMSIpCache Limit
     * @param $ip
     * @param $mobile
     */
    public function setSmsIpKey($ip, $mobile)
    {
        $key = Config::get('cache.keySmsIpCheck') . $ip;
        Redis::setex($key, $this->_sms_ip_check_expire_time, $mobile); //Has Been Sent

    }

    /**
     * InspectsmsSend SMSIpCache Limit
     * @param $ip
     * @return bool
     */
    public function checkSmsIpKey($ip)
    {
        $key = Config::get('cache.keySmsIpCheck') . $ip;

        if (Redis::exists($key)) {
            return true;
        }
        return false;
    }

    /**
     * Send Email Verification composer Installedphpmailer
     */
    public function sendMail(Request $request)
    {
        $email = $request->input('user_string');
        $country_code = $request->input('country_code', '86');
        $country_code = str_replace('+', '', $country_code);
        $scene = $request->input('scene');
        if (empty($email)) {
            return $this->error('Mailbox Cannot Be Empty');
        }
        $user = Users::getByString($email);
        if ($scene == 'login' || $scene == 'change_password' || $scene == 'reset_password') {
            if (empty($user)) {
                return $this->error('Account Number Error');
            }
        } else {
            if (!empty($user)) {
                return $this->error('Account Number Already Exists');
            }
        }
        //  Take Values From Settings
        $username = Setting::getValueByKey('phpMailer_username', '');
        $host = Setting::getValueByKey('phpMailer_host', '');
        $password = Setting::getValueByKey('phpMailer_password', '');
        $port = Setting::getValueByKey('phpMailer_port', 465);
        $from_name = Setting::getValueByKey('phpMailer_from_name', "[X-Coin]");
        $port == '' && $port = 465;
        //InstantiationphpMailer
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->CharSet = "utf-8";
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = "ssl";
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->Username = $username;
            $mail->Password = $password; //Go To Open ItqqOr163Found In The Mailbox,This Is Not The Password Of The Mailbox，It's One After It's Openedtoken
            $mail->setFrom($username, $from_name); //Set Mail Source  //From
            $mail->Subject = "Verification code"; //Email Title
            $code = $this->createSmsCode(4);
            $mail->MsgHTML('Your verification code is' . '【' . $code . '】');   //Email Content
            $mail->addAddress($email);  //Addressee（Email Address Entered By The User）
            $res = $mail->send();
            if ($res) {
                session(['code@' . $country_code . $email => $code]);
                return $this->success('Sent Successfully');
            } else {
                return $this->error('Operation Error');
            }
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }
}
