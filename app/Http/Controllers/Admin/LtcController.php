<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Ltc;
use App\Models\LtcBuy;

class LtcController extends Controller
{

    public function index()
    {
        return view("admin.ltc.index");
    }

    public function lists(Request $request)
    {
        $limit = $request->input('limit', 10);
        $list = new Ltc();
        $list = $list->orderBy('id', 'desc')->paginate($limit);
        return response()->json(['code' => 0, 'data' => $list->items(), 'count' => $list->total()]);
    }

    public function add()
    {
        $level = Ltc::getLevelName();
        return view('admin.ltc.add', ['levelName' => $level]);
    }

    public function postAdd(Request $request)
    {
        $name = $request->input('name', '');
        $profile = $request->input('profile', '');
        $detail = $request->input('detail', '');
        $price = $request->input('price', '');
        $number = $request->input('number', '');
        $thumbnail = $request->input('thumbnail', '');
        if (empty($name)) {
            return $this->error('请输入名称');
        }
        if (empty($profile)) {
            return $this->error('请输入简介');
        }
        if (empty($detail)) {
            return $this->error('请输入详情');
        }
        if (empty($price)) {
            return $this->error('请输入价格');
        }
        if (empty($number)) {
            return $this->error('请输入数量');
        }
        if (empty($thumbnail)) {
            return $this->error("请上传图片");
        }

        $id = $request->input('id', '');
        if (empty($id)) {
            $result = new Ltc();
        } else {
            $result = Ltc::find($id);
        }
        try {
            $result->name = $name;
            $result->profile = $profile;
            $result->detail = $detail;
            $result->price = $price;
            $result->number = $number;
            $result->thumbnail = $thumbnail;
            $result->create_time = time();
            $result->save();
            return $this->success('添加成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function edit()
    {
        $id = request()->input('id', null);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $result = Ltc::find($id);
        if (empty($result)) {
            return $this->error('无此数据');
        }
        $level = Ltc::getLevelName();
        return view('admin.ltc.add', ['result' => $result, 'levelName' => $level]);
    }

    public function del(Request $request)
    {
        $id = $request->input('id', '');
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $result = Ltc::find($id);
        try {
            $result->delete();
            return $this->success('删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function buyList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $result = new LtcBuy();
        $result = $result->orderBy('id', 'desc')->paginate($limit);
        return response()->json(['code' => 0, 'data' => $result->items(), 'count' => $result->total()]);
    }
}
