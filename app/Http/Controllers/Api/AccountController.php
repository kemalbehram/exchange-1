<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\{AccountLog, Users};

class AccountController extends Controller
{
    public function list()
    {
        $address = Users::getUserId(request()->input('address', ''));
        $limit = request()->input('limit', '12');
        $page = request()->input('page', '1');
        if (empty($address)) {
            return $this->error("Parameter Error");
        }
        $user = Users::where("id", $address)->first();
        if (empty($user)) {
            return $this->error("Data Not Found");
        }
        $data = AccountLog::where("user_id", $user->id)->orderBy('id', 'DESC')->paginate($limit);
        return $this->success(array(
            "user_id" => $user->id,
            "data" => $data->items(),
            "limit" => $limit,
            "page" => $page,
        ));
    }

    public function show_profits(Request $request)
    {
        $user_id = Users::getUserId();
        $limit = $request->input('limit', 10);
        $prize_pool = AccountLog::whereHas('user', function ($query) use ($request) {
            $account_number = $request->input('account_number');
            if ($account_number) {
                $query->where('account_number', $account_number);
            }
        })->where(function ($query) use ($request) {
            $start_time = strtotime($request->input('start_time', null));
            $end_time = strtotime($request->input('end_time', null));
            $start_time && $query->where('created_time', '>=', $start_time);
            $end_time && $query->where('created_time', '<=', $end_time);
        })->where("type", AccountLog::PROFIT_LOSS_RELEASE)->where("user_id", "=", $user_id)->orderBy('id', 'desc')->paginate($limit);

        return $this->success($prize_pool);
    }
}
