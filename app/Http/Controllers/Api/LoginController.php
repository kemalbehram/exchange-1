<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Notifiable;
use App\DAO\{UserDAO};
use App\Models\{Agent, Token, Users, UsersWallet, Setting};
use App\Events\UserRegisterEvent;

class LoginController extends Controller
{
    use Notifiable;
    //type 1Normal Password   2Gesture Code
    public function login()
    {
        $env_param = @file_get_contents(base_path() . '/public/env.json');
        $env_param = json_decode($env_param);
        $login_need_smscode = false;
        isset($env_param->login_need_smscode) && $login_need_smscode = $env_param->login_need_smscode;
        $user_string = request()->input('user_string', '');
        $password = request()->input('password', '');
        $sms_code = request()->input('sms_code', '');
        $country_code = request()->input('country_code', '86');
        $country_code = trim($country_code, '+'); //Remove The Plus Sign
        $type = request()->input('type', 1);

        if (empty($user_string)) {
            return $this->error('Please Enter The Account Number');
        }
        if (empty($password)) {
            return $this->error('Please Input A Password');
        }
        if ($login_need_smscode && $sms_code == '') {
            return $this->error('Please Input SMS Verification Code');
        }
        // if (session('code') != $sms_code && $sms_code != '1688') {
        if (session('code@' . $country_code . $user_string) != $sms_code && $login_need_smscode) {
            //Login Universal Verification Code
            $universalCode = Setting::getValueByKey('login_universalCode', '');
            if ($universalCode) {
                if ($sms_code != $universalCode) {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error');
            }
        }


        //Mobile Phone、Mailbox、Transaction Account Login
        $user = Users::getByString($user_string, $country_code);
        if (empty($user)) {
            return $this->error('User Not Found');
        }
//        if (!filter_var($user_string, FILTER_VALIDATE_EMAIL) && $country_code != $user->country_code) {
//            return $this->error('User Does Not Exist,Please Check Whether The Area Code Is Consistent');
//        }
        if ($type == 1) {
            if (Users::MakePassword($password) != $user->password) {
                return $this->error('Wrong Password');
            }
        }
        if ($type == 2) {
            if ($password != $user->gesture_password) {
                return $this->error('Gesture Password Error');
            }
        }
        // session(['user_id' => $user->id]);
        $token = Token::setToken($user->id);
        return $this->success($token);
    }

    public function loginOut()
    {
        //EliminatesessionAndtoken
        $token = Token::getToken();
        if ($token) {
            Token::where('token', $token)->delete();
        }
        session()->flush();
        session()->regenerate(); //Regenerate A Newsession_id
    }

    //Register
    public function register()
    {
        $type = request()->input('type', '');
        $user_string = request()->input('user_string', '');
        $password = request()->input('password', '');
        $re_password = request()->input('re_password', '');
        $code = request()->input('code', '');
        $country_code = request()->input('country_code', '86');
        $nationality = request()->input('nationality', '');
        if (empty($type) || empty($user_string) || empty($password) || empty($re_password)) {
            return $this->error('Parameter Error');
        }
        $country_code = str_replace('+', '', $country_code);
        $extension_code = request()->input('extension_code', '');
        if ($password != $re_password) {
            return $this->error('The Two Passwords Are Inconsistent');
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 16) {
            return $this->error('The Password Can Only Be Used In6-16Between Bits');
        }

        if ($code != session('code@' . $country_code . $user_string)) {
            //Universal Verification Code
            $universalCode = Setting::getValueByKey('register_universalCode', '');
            if ($universalCode) {
                if ($code != $universalCode) {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error');
            }
        }

        $user = Users::getByString($user_string, $country_code);
        if (!empty($user)) {
            return $this->error('Account Number Already Exists');
        }
        $parent_id = 0;
        $invite_code_must = Setting::getValueByKey('invite_code_must', 0);
        if ($invite_code_must && empty($extension_code)) {
            return $this->error('Invitation Code Must Be Filled In');
        }
        if (!empty($extension_code)) {
            $p = Users::where("extension_code", $extension_code)->first();
            if (empty($p)) {
                return $this->error("Please Fill In The Correct Invitation Code");
            } else {
                $parent_id = $p->id;
                $parent_phone = $p->phone;
            }
        }
        $salt = Users::generate_password(4);
        $users = new Users();
        $users->password = Users::MakePassword($password);
        $users->parent_id = $parent_id;
        $users->type = 1;
        $users->account_number = $user_string;
        $users->country_code = $country_code; //Update Country Code
        $users->nationality = $nationality; //Renewal Of Nationality
        if ($type == "mobile") {
            $users->phone = $user_string;
        } else {
            $users->email = $user_string;
        }
        $users->head_portrait = URL("mobile/images/user_head.png");
        $users->time = time();
        $users->extension_code = Users::getExtensionCode();
        DB::beginTransaction();
        try {
            $users->parents_path = $str = UserDAO::getRealParentsPath($users); //Generateparents_path     tian  add
            //Agent Nodeid。Mark The Superior Agent Node Of The User。Agents HereidYesagentPrimary Key In Agent Table，Not At AllusersIn The Tableid。
            $users->agent_note_id = Agent::reg_get_agent_id_by_parentid($parent_id);
            //Agent Node Relationship
            $users->agent_path = Agent::agentPath($parent_id);
            $users->save(); //Save TouserIn The Table
            event(new UserRegisterEvent($users));
            $jump_url = Setting::getValueByKey('registered_jump', '');
            DB::commit();
            return $this->success([
                'msg' => 'Login Was Successful',
                'jump' => $jump_url,
            ]);
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    //Forget The Password  
    public function forgetPassword()
    {
        $account = request()->input('account', '');
        $country_code = request()->input('country_code', '86');
        $country_code = str_replace('+', '', $country_code);
        $password = request()->input('password', '');
        $repassword = request()->input('repassword', '');
        $code = request()->input('code', '');
        $scene = request()->input('scene', ''); //Add Scene Forget The Password  Change Password

        if (empty($account)) {
            return $this->error('Please Enter The Account Number');
        }
        if (empty($password) || empty($repassword)) {
            return $this->error('Please Enter The Password Or Confirm The Password');
        }

        if ($repassword != $password) {
            return $this->error('The Password Entered Twice Is Inconsistent');
        }

        $user = Users::getByString($account, $country_code);
        if (empty($user)) {
            return $this->error('Account Does Not Exist');
        }

        $code_string = session('code@' . $country_code . $account);

        if (empty($code)) {
            return $this->error('Incorrect Verification Code');
        }
        if ($code != $code_string) {
            //Universal Verification Code
            if ($scene == 'change_password' || $scene == 'reset_password') {
                $name = $scene . '_universalCode';
                $universalCode = Setting::getValueByKey($name, '');
                if ($universalCode) {
                    if ($code != $universalCode) {
                        return $this->error('Verification Code Error');
                    }
                } else {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error');
            }
        }

        $user->password = Users::MakePassword($password);

        try {
            $user->save();
            session(['code' => '']); //Destruction
            return $this->success("Password Changed Successfully");
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    public function checkEmailCode()
    {
        $user_string  = request()->input('user_string', '') ?? '';
        $email_code = request()->input('email_code', '');
        $country_code = request()->input('country_code', '86');
        $country_code = str_replace('+', '', $country_code);
        $scene = request()->input('scene', ''); //Add Scene
        if (empty($email_code)) {
            return $this->error('Please Enter The Verification Code');
        }
        $session_code = session('code@' . $country_code . $user_string);

        if ($email_code != $session_code) {
            if ($scene == 'register' || $scene == 'login' || $scene == 'change_password' || $scene == 'reset_password') {
                $name = $scene . '_universalCode';
                $universalCode = Setting::getValueByKey($name, '');
                if ($universalCode) {
                    if ($email_code != $universalCode) {
                        return $this->error('Verification Code Error');
                    }
                } else {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error!');
            }
        }
        return $this->success('Verification Successful');
    }

    public function checkMobileCode()
    {
        $mobile_code = request()->input('mobile_code', '');
        $user_string  = request()->input('user_string', '') ?? '';
        $country_code = request()->input('country_code', '86');
        $country_code = str_replace('+', '', $country_code);
        $scene = request()->input('scene', ''); //Add Scene
        if (empty($mobile_code)) {
            return $this->error('Please Enter The Verification Code');
        }
        $session_mobile = session('code@' . $country_code . $user_string);

        if ($session_mobile != $mobile_code) {

            if ($scene == 'register' || $scene == 'login' || $scene == 'change_password' || $scene == 'reset_password') {
                $name = $scene . '_universalCode';
                $universalCode = Setting::getValueByKey($name, '');
                if ($universalCode) {

                    if ($mobile_code != $universalCode) {
                        return $this->error('Verification Code Error');
                    }
                } else {
                    return $this->error('Verification Code Error');
                }
            } else {
                return $this->error('Verification Code Error!');
            }
        }
        return $this->success('Verification Successful');
    }

    //Wallet Registration
    public function walletRegister()
    {
        $password = request()->input('password', '');
        $parent = request()->input('parent_id', '');
        $account_number = request()->input('account_number', '');
        if (empty($account_number) || empty($password)) {
            return $this->error("Parameter Error");
        }
        if (Users::getByAccountNumber($account_number)) {
            return $this->error("Account Number Already Exists");
        }

        $parent_id = 0;
        if (!empty($parent)) {
            $p = Users::where('account_number', $parent)->first();

            if (empty($p)) {
                return $this->error("The Parent Does Not Exist");
            } else {
                $parent_id = $p->id;
            }
        }

        $users = new Users();
        $users->password = Users::MakePassword($password);
        $users->parent_id = $parent_id;
        $users->account_number = $account_number;
        $users->phone = $account_number;

        $users->head_portrait = URL("images/default_tx.png");
        $users->time = time();
        $users->extension_code = Users::getExtensionCode();
        DB::beginTransaction();
        try {
            if ($users->save()) {
                // if (!empty($parent_id)){
                //     Users::updateParentLevel($parent_id);
                // }
                DB::commit();
                return $this->success("ok");
            } else {
                DB::rollback();
                return $this->success("Please Try Again");
            }
        } catch (\Exception $ex) {
            DB::rollback();
            $this->comment($ex->getMessage());
        }
    }
}
