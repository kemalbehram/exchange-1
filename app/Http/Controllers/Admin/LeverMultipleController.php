<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\{Currency, LeverMultiple};

class LeverMultipleController extends Controller
{
    public function index()
    {
        return view('admin.levermultiple.index');
    }
    public function add()
    {
        return view('admin.levermultiple.add');
    }

    public function doadd(Request $request)
    {
        $lever_multiple = new LeverMultiple();
        $lever_multiple->value = request()->input('value', '');
        $lever_multiple->type = request()->input('type', '');
        try {
            $lever_multiple->save();
            return $this->success('添加成功');
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    public function postAdd(Request $request)
    {
        $id = $request->input('id', 0);
        $name = $request->input('name', '');
        $sort = $request->input('sort', 0);
        $logo = $request->input('logo', '');
        $type = $request->input('type', '');
        $is_legal = $request->input('is_legal', '');
        $is_lever = $request->input('is_lever', '');
        $is_match = $request->input('is_match', '');
        $min_number = $request->input('min_number', 0);
        $rate = $request->input('rate', 0);
        $total_account = $request->input('total_account', 0);
        $key = $request->input('key', 0);
        $contract_address = $request->input('contract_address', 0);
        //自定义验证错误信息
        $messages = [
            'required' => ':attribute 为必填字段',
        ];
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'sort' => 'required',
            'type' => 'required',
            'is_legal' => 'required',
            'is_lever' => 'required',
        ], $messages);

        //如果验证不通过
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $has = Currency::where('name', $name)->first();
        if (empty($id) && !empty($has)) {
            return $this->error($name . ' 已存在');
        }
        if (empty($id)) {
            $currency = new Currency();
            $currency->create_time = time();
        } else {
            $currency = Currency::find($id);
        }
        $currency->name = $name;
        // $acceptor->token = $token;
        // $acceptor->get_address = $get_address;
        $currency->sort = intval($sort);
        $currency->logo = $logo;
        $currency->is_legal = $is_legal;
        $currency->is_lever = $is_lever;
        $currency->is_match = $is_match;
        $currency->min_number = $min_number;
        $currency->rate = $rate;
        $currency->total_account = $total_account;
        $currency->key = $key;
        $currency->contract_address = $contract_address;
        $currency->type = $type;
        $currency->is_display = 1;
        DB::beginTransaction();
        try {
            $currency->save(); //保存币种
            DB::commit();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    public function lists(Request $request)
    {
        $result = new LeverMultiple();
        $count = $result::all()->count();
        $result = $result->orderBy("type", "asc")->get()->toArray();
        foreach ($result as $key => $value) {
            if ($value['type'] == 1) {
                $result[$key]['type'] = "倍数";
            } else {
                $result[$key]['type'] = "手数";
            }
        }

        return response()->json(['code' => 0, 'data' => $result, 'count' => $count]);
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

    //编辑用户信息
    public function doedit(Request $request)
    {
        $password = $request->input("value");
        $id = $request->input("id");
        if (empty($id)) return $this->error("参数错误");
        $lever_multiple = LeverMultiple::find($id);
        $lever_multiple->value = $password;
        if (empty($lever_multiple)) return $this->error("数据未找到");
        try {
            $aa = $lever_multiple->save();
            return $this->success('编辑成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }
}
