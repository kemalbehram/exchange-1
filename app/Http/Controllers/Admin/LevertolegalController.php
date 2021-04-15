<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LeverMultiple;
use App\Models\UsersWallet;
use App\Models\Currency;
use App\Models\Levertolegal;
use App\Models\AccountLog;


class LevertolegalController extends Controller
{
    public function index()
    {
        return view('admin.levertolegal.index');
    }

    public function add()
    {
        return view('admin.levertolegal.add');
    }

    public function doadd(Request $request)
    {
        $lever_multiple = new LeverMultiple();
        $lever_multiple->value = $request->input('value', '');
        $lever_multiple->type = $request->input('type', '');
        try {
            $lever_multiple->save();
        } catch (\Exception $ex) { }
        return $this->success('添加成功');
    }

    public function lists(Request $request)
    {
        $result = new Levertolegal();
        $count = $result::all()->count();
        $result = $result->leftjoin("users", "lever_tolegal.user_id", "=", "users.id")->where("lever_tolegal.type", "=", 1)->orderBy("lever_tolegal.add_time", "desc")->select("lever_tolegal.*", "users.phone")->get()->toArray();
        foreach ($result as $key => $value) {
            $result[$key]["add_time"] = date("Y-m-d H:i:s", $value["add_time"]);
            $result[$key]["type"] = "合约转法币";
        }
        return response()->json(['code' => 0, 'data' => $result, 'count' => $count]);
    }

    //审核
    public function addshow(Request $request)
    {
        $id = $request->input('id', null);
        $res = Levertolegal::find($id);
        $data = $res->toArray();
        return view('admin.levertolegal.update', ['result' => $data]);
    }

    ////审核通过
    public function postAddyes(Request $request)
    {
        $id = $request->post('id', null);
        $user_id = $request->post('user_id', null);
        $number = $request->post('number', null);

        //查询出usdt币的id
        $usdt_id = Currency::where("name", "=", "USDT")->first()->id;


        //查询出对应的钱包对象
        $usdt_users_wallet = UsersWallet::where("currency", "=", $usdt_id)->where("user_id", "=", $user_id)->first();
        $data_wallet1 = [
            'balance_type' => 2,
            'wallet_id' => $usdt_users_wallet->id,
            'lock_type' => 0,
            'create_time' => time(),
            'before' => $usdt_users_wallet->lever_balance,
            'change' => -$number,
            'after' => bc_sub($usdt_users_wallet->lever_balance, $number, 5),
        ];
        $data_wallet2 = [
            'balance_type' => 1,
            'wallet_id' => $usdt_users_wallet->id,
            'lock_type' => 0,
            'create_time' => time(),
            'before' => $usdt_users_wallet->legal_balance,
            'change' => $number,
            'after' => bc_add($usdt_users_wallet->legal_balance, $number, 5),
        ];
        DB::beginTransaction();
        try {
            $usdt_users_wallet->legal_balance = $usdt_users_wallet->legal_balance + $number;
            //解冻冻结余额
            $usdt_users_wallet->lock_lever_balance = $usdt_users_wallet->lock_lever_balance - $number;
            $usdt_users_wallet->save();

            //更改审核状态为通过
            $status_res = new Levertolegal();
            $status11 = $status_res->find($id);
            $status11->status = 2; //1:未审核   2：审核通过  3:审核不通过
            $status11->save();

            AccountLog::insertLog([
                'user_id' => $user_id,
                'value' => $number,
                'currency' => $usdt_id,
                'info' => AccountLog::getTypeInfo(AccountLog::WALLET_LEVEL_LEGAL_OUT),
                'type' => AccountLog::WALLET_LEVEL_LEGAL_OUT,
            ], $data_wallet2);
            AccountLog::insertLog([
                'user_id' => $user_id,
                'value' => -$number,
                'currency' => $usdt_id,
                'info' => AccountLog::getTypeInfo(AccountLog::WALLET_LEVEL_LEGAL_IN),
                'type' => AccountLog::WALLET_LEVEL_LEGAL_IN,
            ], $data_wallet1);
            DB::commit();
            return $this->success('划转成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    //审核不通过
    public function postAddno(Request $request)
    {
        $id = $request->post('id', null);
        $user_id = $request->post('user_id', null);
        $number = $request->post('number', null);
        //查询出usdt币的id
        $usdt_id = Currency::where("name", "=", "USDT")->first()->id;

        //查询出对应的钱包对象
        $usdt_users_wallet = UsersWallet::where("currency", "=", $usdt_id)->where("user_id", "=", $user_id)->first();

        try {
            $usdt_users_wallet->lever_balance = $usdt_users_wallet->lever_balance + $number;
            //审核不通过解冻冻结余额
            $usdt_users_wallet->lock_lever_balance = $usdt_users_wallet->lock_lever_balance - $number;
            $usdt_users_wallet->save();

            //更改审核状态为通过
            $status_res = new Levertolegal();
            $status11 = $status_res->find($id);
            $status11->status = 3; //1:未审核   2：审核通过  3:审核不通过
            $status11->save();

            $usdt_users_wallet->save();
            AccountLog::newinsertLog([
                'user_id' => $user_id,
                'value' => $number,
                'currency' => $usdt_id,
                'info' => AccountLog::getTypeInfo(AccountLog::WALLET_JIEDONGGANGGAN),
                'type' => AccountLog::WALLET_JIEDONGGANGGAN,
            ]);
            DB::commit();

            return $this->success('审核不通过!');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    public function del()
    {
        $admin = LeverMultiple::find(request()->input('id'));
        if ($admin == null) {
            abort(404);
        }
        $bool = $admin->delete();
        if ($bool) {
            return $this->success('删除成功');
        } else {
            return $this->error('删除失败');
        }
    }

    public function edit(Request $request)
    {

        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error("参数错误");
        }
        $result = LeverMultiple::find($id);
        return view('admin.levermultiple.edit', ['result' => $result]);
    }

    //编辑信息
    public function doedit()
    {
        $password = request()->input("value");
        $id = request()->input("id");
        if (empty($id)) return $this->error("参数错误");
        $lever_multiple = LeverMultiple::find($id);
        $lever_multiple->value = $password;
        if (empty($lever_multiple)) return $this->error("数据未找到");
        try {
            $lever_multiple->save();
            return $this->success('编辑成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }
}
