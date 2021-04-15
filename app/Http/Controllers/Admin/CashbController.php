<?php

/**
 * 提币控制器
 */

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\WithdrawAuditEvent;
use App\Exports\FromQueryExport;
use App\Models\{UsersWalletOut, UsersWallet, AccountLog, Currency, Setting};

class CashbController extends Controller
{
    public function index()
    {
        $balance_from = Setting::getValueByKey('withdraw_from_balance', 1); // 从哪个账户提币(1.法币,2.币币,3.合约)
        $balance_type = [
            1 => ['legal', '法币', 'is_legal'],
            2 => ['change', '币币', 'is_match'],
            3 => ['lever', '合约币', 'is_lever'],
        ];
        $currencies = Currency::where($balance_type[$balance_from][2], 1)->where('parent_id',0)->get();
        return view('admin.cashb.index')->with('currencies' , $currencies);
    }

    public function cashbList(Request $request)
    {
        $limit = $request->input('limit', 20);
        $account_number = $request->input('account_number', '');
        $start_time = $request->input('start_time', '');
        $end_time = $request->input('end_time', '');
        $status = $request->input('status', -1);
        $currency = $request->input('currency', -1);
        $lists = UsersWalletOut::whereHas('user', function ($query) use ($account_number) {
                if ($account_number != '') {
                    $query->where('phone', $account_number)
                        ->orWhere('account_number', $account_number)
                        ->orWhere('email', $account_number);
                }
            })->where(function ($query) use ($start_time, $end_time) {
                if (!empty($start_time)) {
                    $start_time = strtotime($start_time);
                    $query->where('create_time', '>=', $start_time);
                }
                if (!empty($end_time)) {
                    $end_time = strtotime($end_time);
                    $query->where('create_time', '<=', $end_time);
                }
            })->when($status > -1, function ($query) use ($status) {
                $query->where('status', $status);
            })->when($currency > -1, function ($query) use ($currency) {
                $query->where('currency', $currency);
            })->orderBy('id', 'desc')
            ->paginate($limit);
        $sum = $lists->sum('number');
        return $this->layuiData($lists, $sum);
    }

    public function show(Request $request)
    {
        $id = $request->input('id', '');
        if (!$id) {
            return $this->error('参数小错误');
        }
        $walletout = UsersWalletOut::find($id);
        $in = AccountLog::where('type', AccountLog::ETH_EXCHANGE)
            ->where('user_id', $walletout->user_id)
            ->where('currency', $walletout->currency)
            ->sum('value');
        $out = UsersWalletOut::where('currency', $walletout->currency)
            ->where('user_id', $walletout->user_id)
            ->where('status', 2)
            ->sum('real_number');
        $use_chain_api = Setting::getValueByKey('use_chain_api', 0);
        return view('admin.cashb.edit', [
            'wallet_out' => $walletout,
            'out' => $out,
            'in' => $in,
            'use_chain_api' => $use_chain_api,
        ]);
    }

    public function done(Request $request)
    {
        set_time_limit(0);
        $id = $request->input('id', 0);
        $method = $request->input('method', '');
        $txid =  $request->input('txid', '');
        $notes = $request->input('notes', '');
        $verificationcode = $request->input('verificationcode', '') ?? '';
        try {
            DB::beginTransaction();
            throw_if(empty($id), new \Exception('参数错误'));
            $balance_from = Setting::getValueByKey('withdraw_from_balance', 1); // 从哪个账户提币(1.法币,2.币币,3.合约)
            // $balance_type = [
            //     1 => ['legal', '法币'],
            //     2 => ['change', '币币'],
            //     3 => ['lever', '合约币'],
            // ];
            // 限制只有未操作过的提币才能进行操作
            $wallet_out = UsersWalletOut::where('status', '<=', 1)
                ->lockForUpdate()
                ->findOrFail($id);
            $number = $wallet_out->number;
            $real_number = bc_mul($wallet_out->number, bc_sub(1, bc_div($wallet_out->rate, 100)));
            // $real_number = bc_sub($number, $wallet_out->rate); // 手续费为固定
            $user_id = $wallet_out->user_id;
            $currency_model = $wallet_out->currencyCoin;
            $currency_id = $currency_model->id;
            // 查找提币的钱包(中心化的)
            $user_wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $currency_id)
                ->lockForUpdate()
                ->firstOrFail();
            // 所提币种是多协议的时候,查找子协议类型匹配的币种
            if ($currency_model->multi_protocol == 1) {
                $currency_model = Currency::where('parent_id', $currency_id)
                    ->where('multi_protocol', 0)
                    ->where('type', $wallet_out->type)
                    ->firstOrFail();
            }
            $contract_address = $currency_model->contract_address;
            $total_account = $currency_model->total_account;
            $key = $currency_model->origin_key;
            $currency_type = $currency_model->type;
            if ($method == 'done') {
                //确认提币
                // if (empty($total_account) || empty($key)) {
                //     throw new \Exception('请检查您的总账号转出地址和私钥设置是否正确');
                // }
                if (!in_array($currency_type, ['eth', 'erc20', 'usdt', 'btc', 'omni', 'bch', 'xrp', 'eostoken'])) {
                    throw new \Exception("{$currency_type}币种类型暂不支持!");
                }
                // if (in_array($currency_type, ['erc20', 'omni', 'eostoken']) && empty($contract_address)) {
                //     throw new \Exception('币种设置缺少合约地址!');
                // }
                change_wallet_balance($user_wallet, $balance_from, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                // $use_chain_api = Setting::getValueByKey('use_chain_api', 0);

                if ($txid == '') {
                    throw new Exception('请填写交易哈希以便于用户查询');
                }
                $wallet_out->txid = $txid;
                $wallet_out->use_chain_api = 0;
                $wallet_out->status = 2; //提币成功状态
            } else {
                change_wallet_balance($user_wallet, $balance_from, -$number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                change_wallet_balance($user_wallet, $balance_from, $number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回');
                $wallet_out->status = 3; //提币失败状态
            }
            $wallet_out->notes = $notes; //反馈的信息
            $wallet_out->verificationcode = $verificationcode;
            $wallet_out->update_time = time();
            $wallet_out->save();
            event(new WithdrawAuditEvent($wallet_out, $currency_model));
            DB::commit();
            return $this->success('操作成功:)');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error('操作失败:' . 'File:' . $ex->getFile() . ',Line:' . $ex->getLine() . ',Message:' . $ex->getMessage());
        }
    }

    //导出用户列表至excel
    public function csv()
    {
        $builder = UsersWalletOut::where('currency', 3);
        return Excel::download(new FromQueryExport($builder), '提币明细.xlsx');
    }
}
