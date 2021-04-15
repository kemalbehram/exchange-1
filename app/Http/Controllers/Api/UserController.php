<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Models\{AppApi,
    UserChat,
    Users,
    UserReal,
    Token,
    AccountLog,
    UsersWallet,
    Currency,
    InviteBg,
    Setting,
    UserCashInfo,
    ExchangeShiftTo
};
use GuzzleHttp\Client;

class UserController extends Controller
{
    protected function saveShopInfo(Request $request)
    {
        $alipay_account = $request->input('alipay_account', ''); //Alipay Account
        $alipay_collect = $request->input('alipay_collect', ''); //Alipay Payment Code
        $wechat_nickname = $request->input('wechat_nickname', ''); //Wechat Nickname
        $wechat_account = $request->input('wechat_account', ''); //Wechat Account
        $wechat_collect = $request->input('wechat_collect', ''); //Wechat Collection Code
        $id_card_front = $request->input('id_card_front', ''); //
        $id_card_back = $request->input('id_card_back', ''); //

        $user_id = Users::getUserId();
        if (
            (empty($wechat_nickname) || empty($wechat_account) || empty($wechat_collect))
            && (empty($alipay_account) || empty($alipay_collect))
        ) {
            return $this->error('Fill In At Least One Item Of Collection Information');
        }

        if (empty($id_card_front)) {
            return $this->error('Please Upload The Front Of Your ID Card');
        }

        if (empty($id_card_back)) {
            return $this->error('Please Upload The Reverse Side Of Your ID Card');
        }

        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $data = [
            'email' => '17797190092@163.com',
            'name' => 'test',
            'uid' => $user_id,
            'alipay_account' => $alipay_account,
            'alipay_collect' => $alipay_collect,
            'wechat_nickname' => $wechat_nickname,
            'wechat_account' => $wechat_account,
            'wechat_collect' => $wechat_collect,
            'id_card_front' => $id_card_front,
            'id_card_back' => $id_card_back,
        ];
        \Mail::send('shop-create', $data, function ($message) use ($data) {
            $message->to($data['email'], $data['name'])->subject('Apply To Open Shop');
        });
        return $this->success('Operation Successful');
    }

    //Add To/Modify Collection Method
    public function saveCashInfo(Request $request)
    {
        $type = $request->input('type', false);
        if ($type) {
            return $this->saveShopInfo($request);
        }

        $bank_name = $request->input('bank_name', ''); //Bank Of Deposit
        $bank_account = $request->input('bank_account', ''); //Bank Account
        $real_name = $request->input('real_name', ''); //Real Name,Render It
        $alipay_account = $request->input('alipay_account', ''); //Alipay Account
        $alipay_collect = $request->input('alipay_collect', ''); //Alipay Payment Code
        $wechat_nickname = $request->input('wechat_nickname', ''); //Wechat Nickname
        $wechat_account = $request->input('wechat_account', ''); //Wechat Account
        $user_id = Users::getUserId();
        if (empty($real_name)) {
            return $this->error('Real Name Is Required');
        }
        if (
            (empty($bank_name) || empty($bank_account))
            && (empty($wechat_nickname) || empty($wechat_account) || empty($wechat_collect))
            && (empty($alipay_account) || empty($alipay_collect))
        ) {
            return $this->error('Fill In At Least One Item Of Collection Information');
        }
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $cash_info = UserCashInfo::where('user_id', $user_id)->first();
        if (empty($cash_info)) {
            $cash_info = new UserCashInfo();
            $cash_info->user_id = $user_id;
            $cash_info->create_time = time();
        }
        if (!empty($bank_name)) {
            $cash_info->bank_name = $bank_name;
        }
        if (!empty($bank_account)) {
            $cash_info->bank_account = $bank_account;
        }
        $cash_info->real_name = $real_name;
        if (!empty($alipay_account)) {
            $cash_info->alipay_account = $alipay_account;
        }
        if (!empty($wechat_account)) {
            $cash_info->wechat_account = $wechat_account;
        }
        if (!empty($wechat_nickname)) {
            $cash_info->wechat_nickname = $wechat_nickname;
        }
        if (!empty($alipay_collect)) {
            $cash_info->alipay_collect = $alipay_collect;
        }
        if (!empty($wechat_collect)) {
            $cash_info->wechat_collect = $wechat_collect;
        }
        try {
            $cash_info->save();
            return $this->success('Saved Successfully');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function checkPayPassword()
    {
        $password = request()->input('password', '');
        $user = Users::getById(Users::getUserId());
        if ($user->pay_password != Users::MakePassword($password)) {
            return $this->error('Wrong Password');
        } else {
            return $this->success('Operation Successful');
        }
    }

    //Get My Collection Method Information
    public function cashInfo()
    {
        $user_id = Users::getUserId();
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $result = UserCashInfo::where('user_id', $user_id)->firstOrFail();
        return $this->success($result);
    }

    //Set The Account And Password Of Legal Currency Transaction
    public function setAccount()
    {
        $account = request()->input('account', '');
        $password = request()->input('password', '');
        $repassword = request()->input('repassword', '');
        if (empty($account) || empty($password) || empty($repassword)) {
            return $this->error('The Required Information Is Incomplete');
        }
        if ($password != $repassword) {
            return $this->error('The Two Passwords Are Inconsistent');
        }
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error('This User Does Not Exist');
        }
        if ($user->account_number) {
            return $this->error('This Transaction Account Has Been Set');
        }
        $res = Users::where('account_number', $account)->first();
        if ($res) {
            return $this->error('This Account Already Exists');
        }
        try {
            $user->account_number = $account;
            $user->pay_password = Users::MakePassword($password, $user->type);
            $user->save();
            return $this->success('Transaction Account Set Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Security Center-->Phone Mailbox Binding Information
    public function safeCenter()
    {
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        $safeInfo = array(
            'mobile' => $user->phone, //If It Is Empty，Unbound
            'email' => $user->email,
            'gesture_password' => $user->gesture_password,
            //Gesture Code, If It Exists, Is A Blue Box，The Default Login Time Is Gesture Password Login
            //Click The Blue Box Again To Cancel The Gesture Password，If You Cancel, You Dont Have To Sign In With A Gesture Password，Delete The Value In The Field
            //If It Doesnt Exist，Its A Gray Box。After Clicking, You Can Reset And Add Gesture Password
        );
        return $this->success($safeInfo);
    }

    //Security Center-->Binding Phone
    public function setMobile()
    {
        $user_id = Users::getUserId();
        $mobile = request()->input('mobile', '');
        $code = request()->input('code', '');
        if (empty($user_id) || empty($mobile) || empty($code)) {
            return $this->error('Parameter Error');
        }
        if ($code != session('code')) {
            return $this->error('Verification Code Error');
        }
        try {
            $user = Users::find($user_id);
            $user->phone = $mobile;
            $user->save();
            return $this->success('Mobile Phone Binding Succeeded');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Security Center-->Bind Mailbox
    public function setEmail()
    {
        $user_id = Users::getUserId();
        $email = request()->input('email', '');
        $code = request()->input('code', '');
        if (empty($user_id) || empty($email) || empty($code)) {
            return $this->error('Parameter Error');
        }
        if ($code != session('code')) {
            return $this->error('Verification Code Error');
        }
        try {
            $user = Users::find($user_id);
            $user->email = $email;
            $user->save();
            return $this->success('The Mailbox Was Bound Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Security Center-->Gesture Code-->Add Gesture Password
    public function gesturePassAdd()
    {
        $password = request()->input('password', ''); //Gets An Array[1,2,3]
        $re_password = request()->input('re_password', '');
        if (mb_strlen($password) < 6) {
            return $this->error('Gesture Password At Least Connect6A Point');
        }
        if ($password != $re_password) {
            return $this->error('The Two Gestures Have Different Passwords');
        }
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        $user->gesture_password = $password;
        try {
            $user->save();
            return $this->success('Gesture Password Added Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Security Center-->Gesture Code-->Cancel Gesture Password
    public function gesturePassDel()
    {
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        $user->gesture_password = "";
        try {
            $user->save();
            return $this->success('Cancel Gesture Password Successfully'); //The Button Turns Grey
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Security Center-->Change Transaction Password
    public function updatePayPassword()
    {
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        // $oldpassword =request()->input('oldpassword', '');
        $password = request()->input('password', '');
        $re_password = request()->input('re_password', '');
        $code = request()->input('code', '');
        $country_code = $user->country_code;
        if ($code == '') {
            return $this->error('Verification Code Must Be Filled In');
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 16) {
            return $this->error('The Password Can Only Be Used In6-16Between Bits');
        }
        if ($password != $re_password) {
            return $this->error('The Two Passwords Are Inconsistent');
        }

        if ($code != session('code@' . $country_code . $user->account_number)) {
            //Universal Verification Code
            $universalCode = Setting::getValueByKey('change_password_universalCode', '');
            if ($universalCode) {
                if ($code != $universalCode) {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error');
            }
        }

        // if (Users::MakePassword($oldpassword) != $user->pay_password) {
        //        return $this->error('Original Password Error');
        // }

        $user->pay_password = Users::MakePassword($password);
        try {
            $user->save();
            return $this->success('Transaction Password Set Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //List Of Domestic Helpers Invited  Front20Name
    public function inviteList()
    {
        $time = request()->input('time', ''); //Invitation Period
        if ($time) {
            $time = strtotime($time);
        } else {
            $time = 0;
        }


        $list = AccountLog::has('user')
            ->select(DB::raw('sum(value) as total, user_id'))
            ->where('type', AccountLog::INVITATION_TO_RETURN)
            ->where('created_time', '>=', $time)
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        if (empty($list)) {
            return $this->error("There Is No Invitation List Yet，Lets Invite Them");
        }


        foreach ($list as $key => $val) {

            $user = Users::find($val['user_id']);


            $list[$key]['account'] = $user->account;
        }

        return $this->success($list);
    }


    //Invitation 
    public function invite()
    {

        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first();

        if (empty($user)) {
            return $this->error("Member Not Found");
        }


        //Invitation List Front3
        $list = AccountLog::has('user')
            ->select(DB::raw('sum(value) as total, user_id'))
            ->where('type', AccountLog::INVITATION_TO_RETURN)
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get()
            ->toArray();
        if (empty($list)) {
            $list = [];
        } else {

            foreach ($list as $key => $val) {

                $users = Users::find($val['user_id']);

                $list[$key]['account'] = $users->account;
            }
        }

        //Invite Advertising Pictures And Links 
        $ad = [];
        $ad['image'] = "/upload/invite.png";

        $data = [];
        $data['extension_code'] = $user['extension_code'];
        $data['ad'] = $ad;
        $data['inviteList'] = $list;


        //Get The Number Of Users Invited
        //Amount Of Commission Returned By Invitation
        $num = Users::where('parent_id', $user_id)->count();

        if ($num > 0) {
            $data['invite_num'] = $num;
            $total = AccountLog::where('user_id', $user_id)->where('type', AccountLog::INVITATION_TO_RETURN)->sum('value');
            $data['invite_return_total'] = $total;
        } else {
            $data['invite_num'] = 0;
            $data['invite_return_total'] = 0;
        }

        return $this->success($data);
    }


    //My Invitation Record  0Enable  1Disable 2Whole 
    public function myInviteList()
    {

        $status = request()->input('status', 2); //Invite Member Status
        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first();

        if (empty($user)) {
            return $this->error("Member Not Found");
        }

        $list = Users::where('parent_id', $user_id);
        if ($status != 2) {
            $list = $list->where('status', $status);
        }
        $list = $list->orderBy('id', 'desc')->get()->toArray();

        return $this->success($list);
    }

    //My Record Of Return Commission
    public function myAccountReturn()
    {

        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first();

        if (empty($user)) {
            return $this->error("Member Not Found");
        }

        $time = request()->input('time', ''); //Invitation Period
        if ($time) {
            $time = strtotime($time);
        } else {
            $time = 0;
        }


        $list = AccountLog::where('user_id', $user_id)
            ->where('type', AccountLog::INVITATION_TO_RETURN)
            ->where('created_time', '>=', $time)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();


        return $this->success($list);
    }

    //My  
    public function info()
    {
        $user_id = Users::getUserId();
        //$user = Users::where("id",$user_id)->first(['id','phone','email','head_portrait','status']);

        try {
            $user = Users::where("id", $user_id)->first();
            if (empty($user)) {
                throw new \Exception("Member Not Found");
            }

            //User Authentication Status
            $res = UserReal::where('user_id', $user_id)->first();
            if (empty($res)) {
                $user['review_status'] = 0;
                $user['name'] = '';
            } else {
                $user['review_status'] = $res['review_status'];
                $user['name'] = $res['name'];
            }

            return $this->success($user);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    //Identity Authentication
    public function realName()
    {

        $user_id = Users::getUserId();
        $name = request()->input("name", ""); //Real Name
        $card_id = request()->input("card_id", ""); //ID Number
        $front_pic = request()->input("front_pic", ""); //Front View
        $reverse_pic = request()->input("reverse_pic", ""); //Reverse Photo
        $hand_pic = request()->input("hand_pic", ""); //Photo Of Holding ID Card

        if (empty($name) || empty($card_id) || empty($front_pic) || empty($reverse_pic) || empty($hand_pic)) {
            return $this->error("Please Submit Complete Information");
        }

        //Check  ID Number Validity
        /*
        $idcheck = new IdCardIdentity();
        $res = $idcheck->check_id($card_id);
        if (!$res) {
            return $this->error("Please Enter A Legal ID Number.");
        }
        */
        $userreal_number = UserReal::where('card_id', $card_id)->count();
        if ($userreal_number > 0) {
            return $this->error("The ID Number Has Been Used.!");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        $userreal = UserReal::where('user_id', $user_id)->first();
        if (!empty($userreal)) {
            return $this->error("You Have Already Applied");
        }

        try {
            $userreal = new UserReal();
            $userreal->user_id = $user_id;
            $userreal->name = $name;
            $userreal->card_id = $card_id;
            $userreal->create_time = time();
            $userreal->front_pic = $front_pic;
            $userreal->reverse_pic = $reverse_pic;
            $userreal->hand_pic = $hand_pic;
            $userreal->save();
            return $this->success('Submitted Successfully，Waiting For Review');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }


    //Personal Center  Authentication Information  
    public function userCenter()
    {
        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first(['id', 'phone', 'email']);
        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        $userreal = UserReal::where('user_id', $user_id)->first();

        if (empty($userreal)) {
            $user['review_status'] = 0;
            $user['name'] = '';
            $user['card_id'] = '';
        } else {
            $user['review_status'] = $userreal['review_status'];
            $user['name'] = $userreal['name'];
            $user['card_id'] = $userreal['card_id'];
        }

        if (!empty($user['card_id'])) {
            $user['card_id'] = mb_substr($user['card_id'], 0, 2) . '******' . mb_substr($user['card_id'], -2, 2);
        }
        return $this->success($user);
    }

    //Exclusive Poster Information
    public function posterBg()
    {
        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first(['id', 'extension_code']);
        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        $pics = InviteBg::all(['id', 'pic'])->toArray();

        $data['extension_code'] = $user['extension_code'];
        $data['share_url'] = Setting::getValueByKey('share_url', '');
        $data['pics'] = $pics;

        return $this->success($data);
    }

    //My Invitation To Share
    public function share()
    {
        $user_id = Users::getUserId();
        $user = Users::where("id", $user_id)->first(['id', 'extension_code']);
        if (empty($user)) {
            return $this->error("Member Not Found");
        }

        $data['share_title'] = Setting::getValueByKey('share_title', '');
        $data['share_content'] = Setting::getValueByKey('share_content', '');
        $data['share_url'] = Setting::getValueByKey('share_url', '');
        $data['extension_code'] = $user['extension_code'];

        return $this->success($data);
    }


    //Log In For The First Time
    public function isFirstLogin()
    {
        $user_id = Users::getUserId();

        //Obtaintoken
        $count = Token::where('user_id', $user_id)->count();
        $count >1 ? $is_first_login = 0 : $is_first_login = 1;

        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $key => $value) {
            $setting[$value['key']] = $value['value'];
        }

        return $this->success([
            'is_first_login' => intval($is_first_login),
            'is_open_login_prompt' => intval($setting['is_open_login_prompt']),
            'login_prompt' => $setting['login_prompt']
        ]);
    }
    
    //Sign Out  
    public function logout()
    {

        $user_id = Users::getUserId();
        $user = Users::find($user_id);

        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        //Clear Usersession
        session()->flush();
        session()->regenerate(); //Regenerate A Newsession_id
        $token = Token::getToken();
        //Delete Currenttoken 
        Token::deleteToken($user_id, $token);
        return $this->success('Log Out Successfully');
    }

    public function vip()
    {
        $user_id = Users::getUserId(request()->input("user_id"));
        $password = request()->input('password', '');


        if (empty($password)) return $this->error("Please Enter The Payment Password");

        $vip = request()->input("vip");
        if (empty($user_id) || empty($vip)) {
            return $this->error("Parameter Error");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        if ($user->vip >= $vip) {
            return $this->error("No Upgrade Required");
        }
        if ($vip == "2") {
            if ($user->vip == 1) {
                $money = 9000;
            } else {
                $money = 9999;
            }
        } else {
            $money = 999;
        }

        $wallet = UsersWallet::where("user_id", $user_id)
            ->where("token", Users::TOKEN_DEFAULT)
            ->select("id", "user_id", "password", "address", "balance", "lock_balance", "remain_lock_balance", "create_time", "wallet_name", "password_prompt")
            ->first();
        if (empty($wallet)) {
            return $this->error("No Wallet");
        }
        if ($password != $wallet->password) {
            return $this->error("Payment Password Error");
        }
        if ($wallet->balance < $money) {
            return $this->error("Sorry, Your Credit Is Running Low");
        }

        $walletn = UsersWallet::find($wallet->id);
        $data_wallet = [
            'balance_type' => AccountLog::UPDATE_VIP,
            'wallet_id' => $walletn->id,
            'lock_type' => 0,
            'create_time' => time(),
            'before' => $walletn->balance,
            'change' => -$money,
            'after' => bc_sub($walletn->balance, $money, 5),
        ];
        $user->vip = $vip;
        $walletn->balance = $walletn->balance - $money;
        $user->save();
        $walletn->save();
        AccountLog::insertLog(
            array(
                "user_id" => $user_id,
                "value" => -$money,
                "type" => AccountLog::UPDATE_VIP,
                "info" => "Upgrade Members"
            ),
            $data_wallet
        );
        return $this->success("Upgrade Successful");
    }

    //Submit Virtual Currency Receiving Address
    public function updateCurrencyAddress()
    {
    }

    public function updateAddress()
    {
        $address = Users::getUserId();

        $eth_address = trim(request()->input('eth_address'));
        if (empty($address) || empty($eth_address)) {
            return $this->error('Parameter Error');
        }
        $user = Users::find($address);
        if (empty($user)) {
            return $this->error('There Is No Such User');
        }

        if ($other = Users::where('eth_address', $eth_address)->first()) {
            if ($other->id != $user->id) {
                return $this->error('The Address Has Been Bound By Someone Else');
            }
        }
        try {
            $user->eth_address = $eth_address;
            $user->save();
            return $this->success('Update Succeeded');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getUserByAddress()
    {
        $user_id = Users::getUserId();
        if (empty($user_id))
            return $this->error("Parameter Error");
        $user = Users::where("id", $user_id)->first();
        if (empty($user)) {
            return $this->error("Member Not Found");
        }
        if (empty($user->extension_code)) {
            $user->extension_code = Users::getExtensionCode();
            $user->save();
        }

        $wallet = UsersWallet::where("user_id", $user_id)
            ->where("token", Users::TOKEN_DEFAULT)
            ->select("id", "user_id", "address", "balance", "lock_balance", "remain_lock_balance", "create_time", "wallet_name", "password_prompt")
            ->first();
        $user->wallet = $wallet;
        return $this->success($user);
    }

    public function chatlist()
    {
        $user_id = Users::getUserId(request()->input('user_id', ''));
        if (empty($user_id)) return $this->error("Parameter Error");

        $user = Users::find($user_id);
        if (empty($user)) return $this->error("User Not Found");

        $chat_list = UserChat::orderBy('id', 'DESC')->paginate(20);

        $datas = $chat_list->items();

        krsort($datas);
        $return = array();
        foreach ($datas as $d) {
            array_push($return, $d);
        }
        return $this->success(array(
            "user" => $user,
            "chat_list" => [
                'total' => $chat_list->total(),
                'per_page' => $chat_list->perPage(),
                'current_page' => $chat_list->currentPage(),
                'last_page' => $chat_list->lastPage(),
                'next_page_url' => $chat_list->nextPageUrl(),
                'prev_page_url' => $chat_list->previousPageUrl(),
                'from' => $chat_list->firstItem(),
                'to' => $chat_list->lastItem(),
                'data' => $return,
            ]
        ));
    }

    public function sendchat()
    {
        $user_id = Users::getUserId(request()->input('user_id', ''));

        $content = request()->input('content', '');
        if (empty($user_id) || empty($content)) return $this->error("Parameter Error");

        $user = Users::find($user_id);
        if (empty($user)) return $this->error("Member Not Found");

        $data["user_id"] = $user_id;
        $data["user_name"] = $user->account_number;
        $data["head_portrait"] = $user->head_portrait;
        $data["content"] = $content;
        $data["type"] = "1";


        try {
            $res = UserChat::sendChat($data);
            if ($res == "ok") {
                $user_chat = new UserChat();
                $user_chat->from_user_id = $user_id;
                $user_chat->to_user_id = 0;
                $user_chat->content = $content;
                $user_chat->type = 1;
                $user_chat->add_time = time();
                $user_chat->save();
                return $this->success("ok");
            } else {
                return $this->error("Please Try Again");
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Exchange Transfer In Interface
     *
     * @return void
     */
    public function shiftToByExchange(Request $request)
    {
        try {
            $exchange_shift_to = DB::transaction(function () use ($request) {
                $appid = $request->input('appid', '');
                $address = $request->input('address', '');
                $number = $request->input('number', 0);
                $currency_name = $request->input('currency_name', '');
                $voucher_no = $request->input('voucher_no', '');
                $timestamp = $request->input('timestamp', 0);
                $nonce = $request->input('nonce', '');
                $signature = $request->input('signature', '');
                $ip = $request->ip();
                $now = Carbon::now();
                $validator = Validator::make($request->all(), [
                    'appid' => 'required|string|min:1',
                    'address' => 'required|string|min:1',
                    'number' => 'required|numeric|min:0.1',
                    'currency_name' => 'required|string|min:1',
                    'voucher_no' => 'required|string|min:1',
                    'timestamp' => 'required|integer|min:0',
                    'nonce' => 'required|string|min:6',
                    'signature' => 'required|string|min:1',
                ], [], [
                    'appid' => 'appid',
                    'address' => 'Wallet Address',
                    'number' => 'Number',
                    'currency_name' => 'Currency Name',
                    'voucher_no' => 'Voucher No',
                    'timestamp' => 'Time Stamp',
                    'nonce' => 'Random Password',
                    'signature' => 'Autograph',
                ]);

                throw_if($validator->fails(), new \Exception($validator->errors()->first()));

                throw_if($now->getTimestamp() < $timestamp, new \Exception('The Request Is Invalid'));

                throw_if($now->subSeconds(10)->getTimestamp() > $timestamp, new \Exception('The Request Has Expired'));

                $currency = Currency::where('name', $currency_name)
                    ->where('allow_game_exchange', 1)
                    ->first();

                throw_unless($currency, new \Exception('Currency Does Not Exist'));

                $user_wallet = UsersWallet::whereHas('currencyCoin', function ($query) use ($currency_name) {
                    $query->where('name', $currency_name);
                })->where('address', $address)
                    ->first();

                throw_unless($user_wallet, new \Exception('Wallet Address Not Found'));

                $api = AppApi::where('appid', $appid)
                    ->where('status', 1)
                    ->first();

                throw_unless($api, new \Exception('appidInvalid'));

                if ($api->bind_ip != '') {
                    $ip_list = explode(',', $api->bind_ip);
                    throw_if(!in_array($ip, $ip_list), new \Exception('IPInvalid'));
                }
                throw_unless($this->signatureCheck($request->all()), new \Exception('The Signature Is Invalid'));
                //Verify The Validity Of The Voucher
                throw_unless($this->checkVoucher($voucher_no, $request->all()), new \Exception('The Voucher Is Invalid'));
                ExchangeShiftTo::unguard();
                $exchange_shift_to = ExchangeShiftTo::create([
                    'user_id' => $user_wallet->user_id,
                    'appid' => $appid,
                    'currency_id' => $currency->id,
                    'voucher_no' => $voucher_no,
                    'number' => $number,
                ]);
                throw_unless(isset($exchange_shift_to->id), new \Exception('Failed To Create Voucher'));
                /*
                //Should Be Paid48Its Only Two Hours
                $result = change_wallet_balance($user_wallet, 1, $number, AccountLog::GAME_SHIFT_TO, 'Game Transferred To Stock Exchange,Voucher No:' . $voucher_no);
                throw_if($result !== true, new \Exception($result));
                */
                return $exchange_shift_to;
            });
            return $this->success('Submitted Successfully,Voucher No:' . $exchange_shift_to->id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        } finally {
            ExchangeShiftTo::reguard();
        }
    }

    /**
     * Verify The Validity Of The Voucher
     *
     * @param string $voucher_no Voucher No
     * @param array $param Content Of Voucher
     * @return boolean
     */
    public function checkVoucher($voucher_no, $param)
    {
        //Please Enter The Interface Of The Game On Request，Check Whether The Status Of The Opposite Voucher Is Consistent With The Information
        $http_client = new Client();
        return true;
    }

    /**
     * Verify Signature
     *
     * @param array $param
     * @return bool
     */
    public function signatureCheck($param)
    {
        if (!isset($param['signature']) || !isset($param['appid'])) {
            return false;
        }
        $signature = $param['signature'];
        $content = $this->makeSignature($param);
        return $signature === $content;
    }

    public function makeSignature($param)
    {
        if (!isset($param['appid'])) {
            return false;
        }
        if (isset($param['signature'])) {
            unset($param['signature']);
        }
        $appid = $param['appid'];
        $api = AppApi::where('appid', $appid)
            ->where('status', 1)
            ->first();
        if (!$api) {
            return false;
        }
        ksort($param, SORT_STRING);
        $content = $appid . http_build_query($param) . $api->appsecret;
        return md5($content);
    }


    //User Authorization Code Acquisition(It Is Necessary To Add Agents)
    public function authCode()
    {
        $user_id = Users::getUserId();
        if (Cache::has('authorization_code_' . $user_id)) {

            $code = Cache::get('authorization_code_' . $user_id);
        } else {
            //Get Random Authorization Code
            $code = Users::generate_password(6);
            //Cache
            Cache::put('authorization_code_' . $user_id, $code, 600);
        }

        return $this->success($code);
    }
}
