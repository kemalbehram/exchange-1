<?php

namespace App\Http\Controllers\Admin;

use App\DAO\UserDAO;
use App\Events\UserRegisterEvent;
use App\Exports\FromQueryExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\{Address, AccountLog, Agent, Currency, Setting, Users, UserCashInfo, UserReal, UsersWallet};

class UserController extends Controller
{
    public function index()
    {
        return view("admin.user.index");
    }

    //导出用户列表至excel
    public function csv()
    {
        $query = Users::query();
        return Excel::download(new FromQueryExport($query), '用户数据.xlsx');
    }

    //用户列表
    public function lists(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account = $request->input('account', '');
        $list = Users::when($account != '', function ($query) use ($account) {
                $query->where("phone", 'like', '%' . $account . '%')
                    ->orwhere('email', 'like', '%' . $account . '%')
                    ->orWhere('account_number', 'like', '%' . $account . '%');
            })->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->layuiData($list);
    }

    public function add(Request $request)
    {
        return view('admin.user.add');
    }

    public function doadd(Request $request)
    {
        $phone = request()->input("phone", '');
        $email = request()->input("email", '');
        $password = request()->input("password", '');
        $parent = request()->input("parent", '');
        $country_code = request()->input('country_code', '86');
        $nationality = request()->input('nationality', '');

//        if (empty($phone) || empty($email) || empty($password) || empty($parent)) {
//            return $this->error("参数错误");
//        }

        if (!empty($phone) && !empty($email)) {
            return $this->error("手机号和邮箱只能填一个");
        }

        if(!empty($phone)){
            $user_string = $phone;
        }

        if(!empty($email)){
            $user_string = $email;
        }

        $country_code = str_replace('+', '', $country_code);

        if (mb_strlen($password) < 6 || mb_strlen($password) > 16) {
            return $this->error('The Password Can Only Be Used In6-16Between Bits');
        }

        $user = Users::getByString($user_string, $country_code);
        if (!empty($user)) {
            return $this->error('Account Number Already Exists');
        }

        //获取推荐人id
        $parent_id = 0;
        if(!empty($parent)){
            $parentUser = Users::where('email' , '=' , "$parent")->orwhere('phone' , '=' , "$parent")->first();
            if(!empty($parentUser)){
                $parent_id =   $parentUser->id;
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
        if (!empty($phone)) {
            $users->phone = $phone;
        } else {
            $users->email = $email;
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

            DB::commit();
            return $this->success('添加成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    public function edit(Request $request)
    {
        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error("参数错误");
        }
        $result = Users::leftjoin("user_real", "users.id", "=", "user_real.user_id")
            ->select("users.*", "user_real.card_id", "user_real.name")
            ->findOrFail($id);
        $cashinfo = UserCashInfo::unguarded(function () use ($id) {
            return UserCashInfo::firstOrNew(['user_id' => $id]);
        });

        return view('admin.user.edit', [
            'result' => $result,
            'cashinfo' => $cashinfo,
        ]);
    }

    //编辑用户信息  
    public function doedit()
    {
        $id = request()->input("id");
        $name = request()->input("name", '');
        $card_id = request()->input("card_id", '');
        $password = request()->input("password", '');
        $account_number = request()->input("account_number", '');
        $pay_password = request()->input("pay_password", '');
        $bank_account = request()->input("bank_account", '');
        $bank_name = request()->input("bank_name", '');
        $alipay_account = request()->input("alipay_account", '');
        $wechat_nickname = request()->input("wechat_nickname", '');
        $wechat_account = request()->input("wechat_account", '');
        $wechat_collect = request()->input("wechat_collect", '');
        $alipay_collect = request()->input("alipay_collect", '');

        if (empty($id)) {
            return $this->error("参数错误");
        }

        try {
            DB::beginTransaction();
            // 用户账号
            $user = Users::findOrFail($id);
            $user->account_number = $account_number;
            $password != '' && $user->password = Users::MakePassword($password);
            $pay_password != '' && $user->pay_password = Users::MakePassword($pay_password);
            $user->save();
            // 收款信息
            $cashinfo = UserCashInfo::unguarded(function () use ($id) {
                return UserCashInfo::firstOrNew(['user_id' => $id]);
            });
            $bank_name != '' && $cashinfo->bank_name = $bank_name;
            $bank_account != '' && $cashinfo->bank_account = $bank_account;
            $alipay_account != '' && $cashinfo->alipay_account = $alipay_account;
            $alipay_collect != '' && $cashinfo->alipay_collect = $alipay_collect;
            $wechat_account != '' && $cashinfo->wechat_account = $wechat_account;
            $wechat_nickname != '' && $cashinfo->wechat_nickname = $wechat_nickname;
            $wechat_collect != '' && $cashinfo->wechat_collect = $wechat_collect;
            $cashinfo->save();
            // 实名信息
            $real = UserReal::unguarded(function () use ($id) {
                return UserReal::firstOrNew(['user_id' => $id], ['review_status' => 1]);
            });
            $name != '' && $real->name = $name;
            $card_id != '' && $real->card_id = $card_id;
            $real->save();
            DB::commit();
            return $this->success('编辑成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    public function del(Request $request)
    {
//        return $this->error('禁止删除用户,将会造成系统崩溃');
        $id = $request->input('id');
        $user = Users::getById($id);
        if (empty($user)) {
            $this->error("用户未找到");
        }
        try {
            $user->delete();
            return $this->success('删除成功');
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    public function lock(Request $request)
    {
        $id = $request->input('id', 0);

        $user = Users::find($id);
        if (empty($user)) {
            return $this->error('参数错误');
        }
        if ($user->status == 1) {
            $user->status = 0;
        } else {
            $user->status = 1;
        }
        try {
            $user->save();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function allowExchange(Request $request)
    {
        $id = $request->input('id', 0);
        $user = Users::find($id);
        if (empty($user)) {
            return $this->error('参数错误');
        }
        try {
            $user->type = 1 - $user->type;
            $user->save();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function wallet(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $currencies = Currency::where('parent_id', 0)->get();
        return view("admin.user.user_wallet", [
            'user_id' => $id,
            'currencies' => $currencies,
        ]);
    }

    public function walletList(Request $request)
    {
        $limit = $request->get('limit', 10);
        $user_id = $request->get('user_id', null);
        $currency_id = $request->input('currency_id', 0);
        if (empty($user_id)) {
            return $this->error('参数错误');
        }
        $list = UsersWallet::where('user_id', $user_id)
            ->when($currency_id > 0, function ($query) use ($currency_id) {
                $query->where('currency', $currency_id);
            })
            ->whereHas('currencyCoin', function ($query) {
                $query->where('parent_id', 0); // 不显示子协议钱包
            })
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return  $this->layuiData($list);
    }

    //钱包锁定状态
    public function walletLock(Request $request)
    {
        $id = $request->input('id', 0);

        $wallet = UsersWallet::find($id);
        if (empty($wallet)) {
            return $this->error('参数错误');
        }
        if ($wallet->status == 1) {
            $wallet->status = 0;
        } else {
            $wallet->status = 1;
        }
        try {
            $wallet->save();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /*
     * 调节账户
     * */
    public function conf(Request $request)
    {
        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $result = UsersWallet::find($id);
        if (empty($result)) {
            return $this->error('无此结果');
        }
        $account = Users::where('id', $result->user_id)->value('phone');
        if (empty($account)) {
            $account = Users::where('id', $result->user_id)->value('email');
        }
        $result['account'] = $account;
        return view('admin.user.conf', ['results' => $result]);
    }

    //调节账号  type  1法币交易余额  2法币交易锁定余额 3币币交易余额 4币币交易锁定余额  5合约交易余额 6合约交易锁定余额
    public function postConf(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'way' => 'required|string', //增加 increment；减少 decrement
                'type' => 'required|integer|min:1',
                'conf_value' => 'required|numeric|min:0', //值
                'info' => 'required'
            ], [
                'required' => ':attribute 不能为空',
            ], [
                'info' => '调节备注'
            ]);

            $wallet = UsersWallet::find($request->input('id'));
            $user = Users::getById($wallet->user_id);

            //以上验证通过后 继续验证
            $validator->after(function ($validator) use ($wallet, $user) {
                if (empty($wallet)) {
                    return $validator->errors()->add('wallet', '没有此钱包');
                }

                if (empty($user)) {
                    return $validator->errors()->add('user', '没有此用户');
                }
            });

            //如果验证不通过
            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            $way = $request->input('way', 'increment');
            $type = $request->input('type', 1);
            $conf_value = $request->input('conf_value', 0);
            $info = $request->input('info', ':');

            $balance_type = ceil($type / 2);
            $is_lock = $type % 2 ? false : true;
            $scene_list = [
                1 => AccountLog::ADMIN_LEGAL_BALANCE,
                2 => AccountLog::ADMIN_LOCK_LEGAL_BALANCE,
                3 => AccountLog::ADMIN_CHANGE_BALANCE,
                4 => AccountLog::ADMIN_LOCK_CHANGE_BALANCE,
                5 => AccountLog::ADMIN_LEVER_BALANCE,
                6 => AccountLog::ADMIN_LOCK_LEVER_BALANCE,
            ];

            $way == 'decrement' &&  $conf_value = -$conf_value;

            $result = change_wallet_balance($wallet, $balance_type, $conf_value, $scene_list[$type], $info, $is_lock);
            if ($result !== true) {
                throw new \Exception($result);
            }
            DB::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    //删除钱包
    public function delw(Request $request)
    {
        $id = $request->input('id');
        $wallet = UsersWallet::find($id);
        if (empty($wallet)) {
            $this->error("钱包未找到");
        }
        try {
            $wallet->delete();
            return $this->success('删除成功');
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    /*
     * 提币地址信息
     * */
    public function address(Request $request)
    {
        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $result = UsersWallet::find($id);
        if (empty($result)) {
            return $this->error('无此结果');
        }


        $list = Address::where('user_id', $result->user_id)->where('currency', $result->currency)->get()->toArray();

        return view('admin.user.address', ['results' => $result, 'list' => $list]);
    }
    /*
     * 修改提币地址信息
     * */
    public function addressEdit(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $currency = $request->input('currency', 0);
        $total_arr = $request->input('total_arr', '');
        if (empty($user_id) || empty($currency)) {
            return $this->error('参数错误');
        }
        DB::beginTransaction();
        try {
            Address::where('user_id', $user_id)->where('currency', $currency)->delete();
            if (!empty($total_arr)) {
                foreach ($total_arr as $key => $val) {
                    $ads = new Address();
                    $ads->user_id = $user_id;
                    $ads->currency = $currency;
                    $ads->address = $val['address'];
                    $ads->notes = $val['notes'];
                    $ads->save();
                }
            }
            DB::commit();
            return $this->success('修改提币地址成功');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e->getMessage());
        }
    }

    //加入黑名单
    public function blacklist(Request $request)
    {
        $id = $request->input('id', 0);

        $user = Users::find($id);
        if (empty($user)) {
            return $this->error('参数错误');
        }
        if ($user->is_blacklist == 1) {
            $user->is_blacklist = 0;
        } else {
            $user->is_blacklist = 1;
        }
        try {
            $user->save();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function candyConf(Request $request, $id)
    {
        $user = Users::find($id);
        return view('admin.user.candy_conf')->with('user', $user);
    }

    public function postCandyConf(Request $request, $id)
    {
        $user = Users::find($id);
        $way = $request->input('way', 0);
        $change = $request->input('change', 0);
        $memo = $request->input('memo', '');
        if (!in_array($way, [1, 2])) {
            return $this->error('调整方式传参错误');
        }
        if ($change <= 0) {
            return $this->error('调整金额必须大于0');
        }
        if ($way == 2) {
            $change = bc_mul($change, -1);
        }
        $result = change_user_candy($user, $change, AccountLog::ADMIN_CANDY_BALANCE, '后台调整' . ($way == 2 ? '减少' : '增加') . '通证 ' . $memo);
        return $result === true ? $this->success('调整成功') : $this->error('调整失败:' . $result);
    }
}
