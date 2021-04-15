<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{UsersWallet, AccountLog, Currency, LegalDealSend, LegalDeal, Seller, Users};

class LegalDealController extends Controller
{
    public function index()
    {
        $currency = Currency::where('is_legal', 1)->orderBy('id', 'desc')->get(); //获取法币
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d H:i:s'));
        //获取当天购买USDT
        $aaaa = LegalDeal::leftJoin("legal_deal_send", "legal_deal.legal_deal_send_id", "=", "legal_deal_send.id")->where("legal_deal_send.type", "=", "sell")->where("legal_deal.create_time", ">", $start)->where("legal_deal.is_sure", "=", 1)->select("legal_deal.*", "legal_deal_send.type")->get();
        $todaybuy_usdt = 0;
        foreach ($aaaa as $key => $value) {
            $todaybuy_usdt = $todaybuy_usdt + $value->number;
        }

        //获取当天出售USDT
        $bbbb = LegalDeal::leftJoin("legal_deal_send", "legal_deal.legal_deal_send_id", "=", "legal_deal_send.id")->where("legal_deal_send.type", "=", "buy")->where("legal_deal.create_time", ">", $start)->where("legal_deal.is_sure", "=", 1)->select("legal_deal.*", "legal_deal_send.type")->get();
        $todaysell_usdt = 0;
        foreach ($bbbb as $key => $value) {
            $todaysell_usdt = $todaysell_usdt + $value->number;
        }

        //USDT购买总数
        $cccc = LegalDeal::leftJoin("legal_deal_send", "legal_deal.legal_deal_send_id", "=", "legal_deal_send.id")->where("legal_deal_send.type", "=", "sell")->where("legal_deal.is_sure", "=", 1)->select("legal_deal.*", "legal_deal_send.type")->get();
        $buyall_usdt = 0;
        foreach ($cccc as $key => $value) {
            $buyall_usdt = $buyall_usdt + $value->number;
        }

        //USDT出售总数
        $dddd = LegalDeal::leftJoin("legal_deal_send", "legal_deal.legal_deal_send_id", "=", "legal_deal_send.id")->where("legal_deal_send.type", "=", "buy")->where("legal_deal.is_sure", "=", 1)->select("legal_deal.*", "legal_deal_send.type")->get();
        $sellall_usdt = 0;
        foreach ($dddd as $key => $value) {
            $sellall_usdt = $sellall_usdt + $value->number;
        }

        //usdt总冻结数量
        $eeee = UsersWallet::leftjoin("currency", "users_wallet.currency", "=", "currency.id")->where("currency.name", "=", "USDT")->select("users_wallet.*", "currency.name")->get();
        $all_lock_legal_balance = 0;
        foreach ($eeee as $key => $value) {
            $all_lock_legal_balance = $all_lock_legal_balance + $value->lock_legal_balance;
        }


        //usdt总可用余额
        $ffff = UsersWallet::leftjoin("currency", "users_wallet.currency", "=", "currency.id")->where("currency.name", "=", "USDT")->select("users_wallet.*", "currency.name")->get();
        $all_usdt_can_use = 0;
        foreach ($ffff as $key => $value) {
            $all_usdt_can_use = $all_usdt_can_use + $value->legal_balance;
        }

        return view('admin.legal.deal', ['currency' => $currency, 'todaybuy_usdt' => $todaybuy_usdt, 'todaysell_usdt' => $todaysell_usdt, 'buyall_usdt' => $buyall_usdt, 'sellall_usdt' => $sellall_usdt, 'all_lock_legal_balance' => $all_lock_legal_balance, 'all_usdt_can_use' => $all_usdt_can_use]);
    }

    public function list(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account_number = $request->input('account_number', '');
        $seller_name = $request->input('seller_name', '');
        $type = $request->input('type', '');
        $currency_id = $request->input('currency_id', 0);
        $is_sure = $request->input('is_sure', -1);
        $legal_deal_send_id = $request->input('legal_deal_send_id', 0);
        $result = new LegalDeal();
        if (!empty($account_number)) {
            $result = $result->whereHas('user', function ($query) use ($account_number) {
                $query->where('account_number', 'like', '%' . $account_number . '%');
            });
        }
        if ($legal_deal_send_id > 0) {
            $result = $result->where('legal_deal_send_id', $legal_deal_send_id);
        }
        if ($is_sure != -1) {
            $result = $result->where('is_sure', $is_sure);
        }
        if (!empty($seller_name)) {
            $result = $result->whereHas('seller', function ($query) use ($seller_name) {
                $query->where('name', 'like', '%' . $seller_name . '%');
            });
        }

        if (!empty($type)) {
            $result = $result->whereHas('legalDealSend', function ($query) use ($type) {
                $query->where('type', $type);
            });
        }
        if (!empty($currency_id)) {
            $result = $result->whereHas('legalDealSend', function ($query) use ($currency_id) {
                $query->where('currency_id', $currency_id);
            });
        }
        $start_time = $request->input('start_time', '');
        $end_time = $request->input('end_time', '');
        if (!empty($start_time)) {
            $start_time = strtotime($start_time);
            $result = $result->where('create_time', '>=', $start_time);
        }
        if (!empty($end_time)) {
            $end_time = strtotime($end_time);
            $result = $result->where('create_time', '<=', $end_time);
        }
        $result = $result->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('number');
        return $this->layuiData($result, $sum);
    }

    /**
     * 后台取消订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminLegalDealCancel(Request $request)  //tian add
    {
        $id = $request->get('id', null);
        try {
            if (empty($id)) {
                throw new \Exception('参数错误');
            }
            $legal_deal = LegalDeal::LockForUpdate()->find($id);
            if (empty($legal_deal)) {
                return $this->error('无此记录');
            }
            DB::beginTransaction();
            LegalDeal::cancelLegalDealById($id, 3);
            DB::commit();
            return $this->success('操作成功，订单已取消');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    //后台确认收到付款，订单状态改为完成
    public function adminDoSure(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            LegalDeal::confirmLegalDealById($id, 3);
            return $this->success('确认成功');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
