<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\{AccountLog, Currency, Setting, Users};

class AccountLogController extends Controller
{
    public function index()
    {
        //获取type类型
        $type = [
            AccountLog::ADMIN_LEGAL_BALANCE => '后台调节法币账户余额',
            AccountLog::ADMIN_LOCK_LEGAL_BALANCE => '后台调节法币账户锁定余额',
            AccountLog::ADMIN_CHANGE_BALANCE => '后台调节币币账户余额',
            AccountLog::ADMIN_LOCK_CHANGE_BALANCE => '后台调节币币账户锁定余额',
            AccountLog::ADMIN_LEVER_BALANCE => '后台调节合约账户余额',
            AccountLog::ADMIN_LOCK_LEVER_BALANCE => '后台调节合约账户锁定余额',
            AccountLog::WALLET_CURRENCY_OUT => '法币账户转出至交易账户',
            AccountLog::WALLET_CURRENCY_IN => '交易账户转入至法币账户',
            AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE => '提交卖出，扣除',
            AccountLog::TRANSACTIONIN_REDUCE => '币币交易:买入扣除',
            AccountLog::TRANSACTIONIN_OUT_DEL => '币币交易:挂卖撤单',
            AccountLog::TRANSACTIONIN_IN_DEL => '币币交易:挂买撤单',
        ];
        $currency_type = Currency::where('parent_id', 0)->get();
        return view("admin.account.index", [
            'types' => $type,
            'currency_type' => $currency_type
        ]);
    }

    public function lists(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account = $request->input('account', '');
        $start_time = strtotime($request->input('start_time', 0));
        $end_time = strtotime($request->input('end_time', 0));
        $currency = $request->input('currency_type', 0);
        $type = $request->input('type', 0);
        $balance_type = $request->input('balance_type', 0);
        $lock_type = $request->input('lock_type', -1);

        $list = AccountLog::query();
        $list = $list->with(['user']);
//        $list = $list->with(['user', 'walletLog']);
        if (!empty($currency)) {
            $list = $list->where('currency', $currency);
        }
        if (!empty($type)) {
            $list = $list->where('type', $type);
        }
        if (!empty($start_time)) {
            $list = $list->where('created_time', '>=', $start_time);
        }
        if (!empty($end_time)) {
            $list = $list->where('created_time', '<=', $end_time);
        }
        if (!empty($account)) {
            $user = Users::where("phone", 'like', '%' . $account . '%')->orWhere('email', '%' . $account . '%')->first();
            $list = $list->where(function ($query) use ($user) {
                if ($user) {
                    $query->where('user_id', $user->id);
                }
            });
        }

//        if (!empty($balance_type)) {
//            $list = $list->whereHas('walletLog', function ($query) use ($balance_type) {
//                $query->where('balance_type', $balance_type);
//            });
//        }

//        if ($lock_type > -1) {
//            $list = $list->whereHas('walletLog', function ($query) use ($lock_type) {
//                $query->where('lock_type', $lock_type);
//            });
//        }

        $list = $list->orderBy('id', 'desc')->paginate($limit);
        return $this->layuiData($list);
    }

    public function view(Request $request)
    {
        $id = $request->get('id', null);
        $results = new AccountLog();
        $results = $results->where('id', $id)->first();
        if (empty($results)) {
            return $this->error('无此记录');
        }
        return view('admin.account.viewDetail', ['results' => $results]);
    }

    public function recharge()
    {
        $balance_from = Setting::getValueByKey('withdraw_from_balance', 1); // 从哪个账户提币(1.法币,2.币币,3.合约)
        $balance_type = [
            1 => ['legal', '法币', 'is_legal'],
            2 => ['change', '币币', 'is_match'],
            3 => ['lever', '合约币', 'is_lever'],
        ];
        $currencies = Currency::where($balance_type[$balance_from][2], 1)
            ->where('parent_id', 0)
            ->where('is_display', 1)
            ->get();
        return view('admin.account.recharge')->with('currencies', $currencies);
    }

    public function rechargeList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $lists = AccountLog::where(function ($query) {
                $query->where('type', AccountLog::ETH_EXCHANGE)->where('user_id', '>', 0);
            })->whereHas('user', function ($query) use ($request) {
                $account_number = $request->input('account_number', '');
                $account_number != '' && $query->where('account_number', $account_number);
            })->where(function ($query) use ($request) {
                $currency = $request->input('currency', -1);
                $start_time = strtotime($request->input('start_time', null));
                $end_time = strtotime($request->input('end_time', null));
                $currency != -1 && $query->where('currency', $currency);
                $start_time && $query->where('created_time', '>=', $start_time);
                $end_time && $query->where('created_time', '<=', $end_time);
            })->orderBy('id', 'desc')
            ->paginate($limit);
        $sum = $lists->sum('value');
        return $this->layuiData($lists, $sum);
    }

    public function indexprofits()
    {
        $scene_list = AccountLog::where("type", AccountLog::PROFIT_LOSS_RELEASE)
            ->orderBy("created_time", "desc")
            ->get()
            ->toArray();
        return view('admin.profits.index')->with('scene_list', $scene_list);
    }

    public function listsprofits(Request $request)
    {
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
        })->where("type", AccountLog::PROFIT_LOSS_RELEASE)->orderBy('id', 'desc')->paginate($limit);

        return $this->layuiData($prize_pool);
    }

    public function countprofits(Request $request)
    {
        $count_data = AccountLog::selectRaw('1 as user_count')
            ->selectRaw('sum(`value`) as value')
            ->whereHas('user', function ($query) use ($request) {
                $account_number = $request->input('account_number');
                if ($account_number) {
                    $query->where('account_number', $account_number)
                        ->orWhere('phone', $account_number)
                        ->orWhere('email', $account_number);
                }
            })->where(function ($query) use ($request) {
                $start_time = strtotime($request->input('start_time', null));
                $end_time = strtotime($request->input('end_time', null));
                $start_time && $query->where('created_time', '>=', $start_time);
                $end_time && $query->where('created_time', '<=', $end_time);
            })->where("type", AccountLog::PROFIT_LOSS_RELEASE)->groupBy('user_id')->get();
        $user_count = $count_data->pluck('user_count')->sum();
        $reward_total = 0;
        $count_data->pluck('value')->each(function ($item, $key) use (&$reward_total) {
            $reward_total = bc_add($reward_total, $item);
        });
        return response()->json([
            'user_count' => $user_count,
            'reward_total' => $reward_total,
        ]);
    }
}
