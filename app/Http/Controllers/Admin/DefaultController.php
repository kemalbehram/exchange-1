<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\{Admin, AdminRole, AdminRolePermission, AdminToken, Users};

class DefaultController extends Controller
{
    public function postLogin()
    {
        $username = request()->input('username', '');
        $password = request()->input('password', '');
        if (empty($username)) {
            return $this->error('用户名必须填写');
        }
        if (empty($password)) {
            return $this->error('密码必须填写');
        }
        $password = Users::MakePassword($password);
        $admin = Admin::where('username', $username)->where('password', $password)->first();
        if (empty($admin)) {
            return $this->error('用户名密码错误');
        } else {
            $role = AdminRole::find($admin->role_id);
            if (empty($role)) {
                return $this->error('账号异常');
            } else {
                session()->put('admin_username', $admin->username);
                session()->put('admin_id', $admin->id);
                session()->put('admin_role_id', $admin->role_id);
                session()->put('admin_is_super', $role->is_super);
                AdminToken::setToken($admin->id);
                return $this->success('登陆成功');
            }
        }
    }

    public function login()
    {
        session()->put('admin_username', '');
        session()->put('admin_id', '');
        session()->put('admin_role_id', '');
        session()->put('admin_is_super', '');
        return redirect('/admin/login.html');
    }

    public function login1()
    {
        return view('admin.login1');
    }

    public function index()
    {
        $admin_role = AdminRolePermission::where("role_id", session()->get('admin_role_id'))->get();
        $admin_role_data = array();
        foreach ($admin_role as $r) {
            array_push($admin_role_data, $r->action);
        }
        return view('admin.indexnew')->with("admin_role_data", $admin_role_data);;
    }

    public function indexnew()
    {
        $admin_role = AdminRolePermission::where("role_id", session()->get('admin_role_id'))->get();
        $admin_role_data = array();
        foreach ($admin_role as $r) {
            array_push($admin_role_data, $r->action);
        }
        $admin_id = session()->get('admin_id');
        $admin = Admin::find($admin_id);
        return view('admin.index')
            ->with("admin_role_data", $admin_role_data)
            ->with('admin', $admin);
    }

    public function getVerificationCode(Request $request)
    {
        $http_client = app('LbxChainServer');
        $type = $request->input('type', 1);

        $uri = '/v3/wallet/verification';

        $response = $http_client->request('post', $uri, [
            'form_params' => [
                'projectname' => config('app.name'),
                'type' => $type,
            ],
        ]);
        $result = json_decode($response->getBody()->getContents(), true);
        if (isset($result['code']) && $result['code'] == 0) {
            return $this->success('发送成功');
        } else {
            return $this->error($result['msg']);
        }
    }
}
