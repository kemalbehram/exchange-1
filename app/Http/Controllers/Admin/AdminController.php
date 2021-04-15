<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\{Admin, AdminRole, Agent, Users};

class AdminController extends Controller
{

    public function users()
    {
        if (session()->get('admin_is_super') != '1') {
            abort(403);
        }
        $adminuser = Admin::all();
        $count = $adminuser->count();
        return response()->json(['code' => 0, 'count' => $count, 'msg' => '', 'data' => $adminuser]);
    }

    public function managerIndex()
    {
        return view('admin.manager.index');
    }

    public function adminRoles()
    {
        return view('admin.manager.admin_roles');
    }

    public function add()
    {
        if (session()->get('admin_is_super') != '1') {
            abort(403);
        }
        $id = request()->input('id', null);
        if (empty($id)) {
            $adminUser = new Admin();
        } else {
            $adminUser = Admin::find($id);
            if ($adminUser == null) {
                abort(404);
            }
        }
        $roles = AdminRole::all();
        return view('admin.manager.add', ['admin_user' => $adminUser, 'roles' => $roles]);
    }

    public function postAdd(Request $request)
    {
        if (session()->get('admin_is_super') != '1') {
            abort(403);
        }
        $id = $request->input('id', null);
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'role_id' => 'required|numeric'
        ], [
            'username.required' => '姓名必须填写',
            'role_id.required'  => '角色必须选择',
            'role_id.numeric'   => '角色必须为数字'
        ]);
        if (empty($id)) {
            $adminUser = new Admin();
        } else {
            $adminUser = Admin::find($id);
            if ($adminUser == null) {
                return redirect()->back();
            }
        }
        $password = request()->input('password', '');
        $adminUser->role_id = request()->input('role_id', '0');
        if (request()->input('password', '') != '') {
            $adminUser->password = Users::MakePassword($password);
        }
        $validator->after(function ($validator) use ($adminUser, $id) {
            if (empty($id)) {
                if (Admin::where('username', request()->input('username'))->exists()) {
                    $validator->errors()->add('username', '用户已经存在');
                }
            }
        });

        $adminUser->username = request()->input('username', '');
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        try {
            $adminUser->save();
        } catch (\Exception $ex) {
            $validator->errors()->add('error', $ex->getMessage());
            return $this->error($validator->errors()->first());
        }
        return $this->success('添加成功');
    }

    public function del()
    {
        $admin = Admin::find(request()->input('id'));
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

    public function agent()
    {
        $admin = Agent::where('is_admin', 1)->where('level', 0)->first();
        if ($admin != null) {
            return redirect(route('agent'));
        } else {
            $hkok = DB::table('admin')->where('id', 1)->first();
            if ($hkok != null) {
                $insertData = [];
                $insertData['user_id'] = $hkok->id;
                $insertData['username'] = $hkok->username;
                $insertData['password'] = $hkok->password;
                $insertData['level'] = 0;
                $insertData['is_admin'] = 1;
                $insertData['reg_time'] = time();
                $insertData['pro_loss'] = 100.00;
                $insertData['pro_ser'] = 100.00;

                $id = DB::table('agent')->insertGetId($insertData);

                if ($id > 0) {
                    return redirect(route('agent'));
                } else {
                    return $this->error('失败');
                }
            }
        }
    }
}
