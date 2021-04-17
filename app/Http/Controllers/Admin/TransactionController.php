<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FromArrayExport;
use App\Models\Token;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\{AccountLog, Currency, CurrencyMatch, Transaction, TransactionComplete, TransactionIn, TransactionOut, LeverTransaction, TransactionInDel, TransactionOrder, TransactionOutDel, Users, UsersWallet};

class TransactionController extends Controller
{

    public function index()
    {
        $currency = Currency::all();
        return view("admin.transaction.index", ['currency' => $currency]);
    }

    public function lists()
    {
        $limit = request()->input('limit', 10);
        $account_number = request()->input('account_number', ''); //用户交易账号
        $type = request()->input('type', '');
        $currency = request()->input('currency', '');
        $status = request()->input('status', '');
        $result = new Transaction();
        if (!empty($account_number)) {

            $users = Users::where('account_number', 'like', '%' . $account_number . '%')->get()->pluck('id');
            $result = $result->where(function ($query) use ($users) {
                $query->whereIn('from_user_id', $users);
            });
        }

        if (!empty($type)) {
            $result = $result->where('type', '=', $type);
        }
        if (!empty($currency)) {
            $result = $result->where('currency', $currency);
        }
        if (!empty($status)) {
            $result = $result->where('status', $status);
        }


        $list = $result->orderBy('id', 'desc')->paginate($limit);
        return response()->json(['code' => 0, 'data' => $list->items(), 'count' => $list->total()]);
    }

    public function completeIndex()
    {
        $legal_currencies = Currency::where('is_legal', 1)->get();
        $currencies = Currency::get();
        return view("admin.transaction.complete", [
            'legal_currencies' => $legal_currencies,
            'currencies' => $currencies,
        ]);
    }

    public function inIndex()
    {
        $legal_currencies = Currency::where('is_legal', 1)->get();
        $currencies = Currency::get();
        return view("admin.transaction.in", [
            'legal_currencies' => $legal_currencies,
            'currencies' => $currencies,
        ]);
    }

    public function outIndex()
    {
        $legal_currencies = Currency::where('is_legal', 1)->get();
        $currencies = Currency::get();
        return view("admin.transaction.out", [
            'legal_currencies' => $legal_currencies,
            'currencies' => $currencies,
        ]);
    }

    public function cnyIndex()
    {
        return view("admin.transaction.cny");
    }

    public function trade()
    {
        return view('admin.transaction.trade');
    }

    public function completeList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account_number = $request->input('account_number', '');
        $result = TransactionComplete::whereHas('user', function ($query) use ($request) {
            $account_number = $request->input('buy_account_number', '');
            $account_number != '' && $query->where('account_number', 'like', '%' . $account_number . '%');
        })->whereHas('fromUser', function ($query) use ($request) {
            $account_number = $request->input('sell_account_number', '');
            $account_number != '' && $query->where('account_number', 'like', '%' . $account_number . '%');
        })->where(function ($query) use ($request) {
            $legal = $request->input('legal', -1);
            $currency = $request->input('currency', -1);
            $legal != -1 && $query->where('legal', $legal);
            $currency != -1 && $query->where('currency', $currency);
            $start_time = $request->input('start_time', '');
            $end_time = $request->input('end_time', '');
            if (!empty($start_time)) {
                $start_time = strtotime($start_time);
                $query->where('create_time', '>=', $start_time);
            }
            if (!empty($end_time)) {
                $end_time = strtotime($end_time);
                $query->where('create_time', '<=', $end_time);
            }
        })->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('number');
        return $this->layuiData($result, $sum);
    }

    public function inList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $result = TransactionIn::whereHas('user', function ($query) use ($request) {
            $account_number = $request->input('account_number', '');
            $account_number != '' && $query->where('account_number', 'like', '%' . $account_number . '%');
        })->where(function ($query) use ($request) {
            $legal = $request->input('legal', -1);
            $currency = $request->input('currency', -1);
            $legal != -1 && $query->where('legal', $legal);
            $currency != -1 && $query->where('currency', $currency);
            $start_time = $request->input('start_time', '');
            $end_time = $request->input('end_time', '');
            if (!empty($start_time)) {
                $start_time = strtotime($start_time);
                $query->where('create_time', '>=', $start_time);
            }
            if (!empty($end_time)) {
                $end_time = strtotime($end_time);
                $query->where('create_time', '<=', $end_time);
            }
        })->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('number');
        return $this->layuiData($result, $sum);
    }

    public function outList(Request $request)
    {
        $limit = $request->input('limit', 10);

        $result = TransactionOut::whereHas('user', function ($query) use ($request) {
            $account_number = $request->input('account_number', '');
            $account_number != '' && $query->where('account_number', 'like', '%' . $account_number . '%');
        })->where(function ($query) use ($request) {
            $legal = $request->input('legal', -1);
            $currency = $request->input('currency', -1);
            $legal != -1 && $query->where('legal', $legal);
            $currency != -1 && $query->where('currency', $currency);
            $start_time = $request->input('start_time', '');
            $end_time = $request->input('end_time', '');
            if (!empty($start_time)) {
                $start_time = strtotime($start_time);
                $query->where('create_time', '>=', $start_time);
            }
            if (!empty($end_time)) {
                $end_time = strtotime($end_time);
                $query->where('create_time', '<=', $end_time);
            }
        })->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('number');
        return $this->layuiData($result, $sum);
    }

    public function cnyList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account_number = $request->input('account_number', '');
        $result = new AccountLog();
        if (!empty($account_number)) {
            $users = Users::where('account_number', 'like', '%' . $account_number . '%')->get()->pluck('id');
            $result = $result->whereIn('user_id', $users);
        }
        $types = array(13, 14, 15, 20, 22, 24);
        $result = $result->whereIn('type', $types)->orderBy('id', 'desc')->paginate($limit);
        return $this->layuiData($result);
    }

    public function Leverdeals_show()
    {
        $matches = CurrencyMatch::where('open_lever', 1)->get();
        return view("admin.leverdeals.list", [
            'matches' => $matches,
        ]);
    }

    //合约交易
    public function Leverdeals(Request $request)
    {
        $limit = $request->input("limit", 10);
        $match_id = $request->input('match_id', 0);
        $account_number = $request->input("account_number", '');
        $status = $request->input("status", -1);
        $type = $request->input("type", 0);
        $start_time = $request->input("start_time", '');
        $end_time = $request->input("end_time", '');
        $legal_id = 0;
        $currency_id = 0;
        if ($match_id > 0) {
            $match = CurrencyMatch::find($match_id);
            $legal_id = $match->legal_id ?? 0;
            $currency_id = $match->currency_id ?? 0;
        }
        $order_list = LeverTransaction::when($legal_id > 0, function ($query) use ($legal_id) {
            $query->where('legal', $legal_id);
        })->when($currency_id > 0, function ($query) use ($currency_id) {
            $query->where('currency', $currency_id);
        })->when($account_number != '', function ($query) use ($account_number) {
            $query->whereHas('user', function ($query) use ($account_number) {
                $query->where('account_number', $account_number)
                    ->orWhere('phone', $account_number)
                    ->orWhere('email', $account_number);
            });
        })->when($type > 0, function ($query) use ($type) {
            $query->where('type', $type);
        })->when($status <> -1, function ($query) use ($status) {
            $query->where('status', $status);
        })->when($start_time != '', function ($query) use ($start_time) {
            $query->where('create_time', '>=', strtotime($start_time));
        })->when($end_time != '', function ($query) use ($end_time) {
            $query->where('create_time', '<=', strtotime($end_time));
        })->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->layuiData($order_list);
    }

    //导出合约交易 团队所有订单excel
    public function csv(Request $request)
    {
        $id = $request->input("id", 0);
        $username = $request->input("phone", '');
        $status = $request->input("status", 10);
        $type = $request->input("type", 0);

        $start = $request->input("start", '');
        $end = $request->input("end", '');
        $where = [];
        if ($id > 0) {
            $where[] = ['lever_transaction.id', '=', $id];
        }
        if (!empty($username)) {
            $s = DB::table('users')->where('account_number', $username)->first();
            if ($s !== null) {
                $where[] = ['lever_transaction.user_id', '=', $s->id];
            }
        }

        if ($status != -1 && in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::BUY, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING])) {
            $where[] = ['lever_transaction.status', '=', $status];
        }

        if ($type > 0 && in_array($type, [1, 2])) {
            $where[] = ['type', '=', $type];
        }
        if (!empty($start) && !empty($end)) {
            $where[] = ['lever_transaction.create_time', '>', strtotime($start . ' 0:0:0')];
            $where[] = ['lever_transaction.create_time', '<', strtotime($end . ' 23:59:59')];
        }

        $order_list = TransactionOrder::leftjoin("users", "lever_transaction.user_id", "=", "users.id")->select("lever_transaction.*", "users.phone")->whereIn('lever_transaction.status', [LeverTransaction::ENTRUST, LeverTransaction::BUY, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING])->where($where)->get();

        foreach ($order_list as $key => $value) {
            $order_list[$key]["create_time"] = date("Y-m-d H:i:s", $value->create_time);
            $order_list[$key]["transaction_time"] = date("Y-m-d H:i:s", substr($value->transaction_time, 0, strpos($value->transaction_time, '.')));
            $order_list[$key]["update_time"] = date("Y-m-d H:i:s", substr($value->update_time, 0, strpos($value->update_time, '.')));
            $order_list[$key]["handle_time"] = date("Y-m-d H:i:s", substr($value->handle_time, 0, strpos($value->handle_time, '.')));
            $order_list[$key]["complete_time"] = date("Y-m-d H:i:s", substr($value->complete_time, 0, strpos($value->complete_time, '.')));
        }
        $data = $order_list;
        return Excel::download(new FromArrayExport($data), '交易明细.xlsx');
    }

    /**
     * 后台强制平仓
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function close()
    {
        $id = request()->input("id");
        if (empty($id)) {
            return $this->error("参数错误");
        }

        DB::beginTransaction();
        try {
            $lever_transaction = LeverTransaction::lockForupdate()->find($id);
            if (empty($lever_transaction)) {
                throw new \Exception("数据未找到");
            }

            if ($lever_transaction->status != LeverTransaction::TRANSACTION) {
                throw new \Exception("交易状态异常,请勿重复提交");
            }
            $return = LeverTransaction::leverClose($lever_transaction, LeverTransaction::CLOSED_BY_ADMIN);
            if (!$return) {
                throw new \Exception("平仓失败,请重试");
            }
            DB::commit();
            return $this->success("操作成功");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    /**
     * 取消撮合交易挂单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request)
    {
        $id = $request->input('id', 0);
        $type = $request->input('type', '');
        try {
            throw_if(!in_array($type, ['in', 'out']), new \Exception('交易类型异常'));
            DB::transaction(function () use ($id, $type) {
                $transaction_class = $type == 'in' ? TransactionIn::class : TransactionOut::class;
                $transaction_del_class = $type == 'in' ? TransactionInDel::class : TransactionOutDel::class;
                $trade = $transaction_class::lockForupdate()->findOrFail($id);
                $user = Users::findOrFail($trade->user_id);
                $currency_match = CurrencyMatch::where('currency_id', $trade->currency)
                    ->where('legal_id', $trade->legal)
                    ->firstOrFail();
                // 退回原冻结数量
                if ($type == 'in') {
                    $shoud_refund_number = bc_mul($trade->price, $trade->number, 8);
                    $currency_id = $trade->legal;
                    $type_name = "挂买";
                    $currency_name = $currency_match->legal_name;
                } else {
                    $shoud_refund_number = $trade->number;
                    $currency_id = $trade->currency;
                    $type_name = "挂卖";
                    $currency_name = $currency_match->currency_name;
                }
                $user_wallet = UsersWallet::where('user_id', $user->id)
                    ->where('currency', $currency_id)
                    ->firstOrFail();
                if (bc_comp($user_wallet->lock_change_balance, $shoud_refund_number) < 0) {
                    throw new \Exception("撤回{$type_name}失败,冻结余额不足");
                }
                change_wallet_balance(
                    $user_wallet,
                    2,
                    -$shoud_refund_number,
                    AccountLog::TRANSACTIONIN_IN_DEL,
                    "币币交易:SYS取消{$type_name}{$currency_match->symbol},解除锁定{$currency_name},交易号:{$trade->id}",
                    true
                );
                change_wallet_balance(
                    $user_wallet,
                    2,
                    $shoud_refund_number,
                    AccountLog::TRANSACTIONIN_IN_DEL,
                    "币币交易:SYS取消{$type_name}{$currency_match->symbol},退回{$currency_name},交易号:{$trade->id}"
                );
                // 插入挂单备份
                $trade_del = $transaction_del_class::unguarded(function () use ($trade, $transaction_del_class) {
                    return $transaction_del_class::create([
                        'transaction_id' => $trade->id,
                        'type' => 2,
                        'user_id'  => $trade->user_id,
                        'price'  => $trade->price,
                        'total'  => $trade->total,
                        'number' => $trade->number,
                        'currency'  => $trade->currency,
                        'legal'  => $trade->legal,
                        'rate' => $trade->rate,
                        'is_auto' => $trade->is_auto,
                        'auto_id' => $trade->auto_id,
                        'is_active' => $trade->is_active,
                        'create_time' => $trade->getOriginal('create_time'),
                    ]);
                });
                throw_if(!isset($trade_del->id), new \Exception('撤回失败:记录交易信息失败'));
                // 删除该挂单
                throw_unless($trade->delete(), new \Exception('撤回失败:清除交易失败'));
            });
            return $this->success('撤回成功!');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * 删除撮合交易挂单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function del(Request $request)
    {
        $id = $request->input('id', 0);
        $type = $request->input('type', '');
        try {
            throw_if(!in_array($type, ['in', 'out']), new \Exception('交易类型异常'));
            $transaction = $type == 'in' ? TransactionIn::class : TransactionOut::class;
            DB::transaction(function () use ($id, $transaction) {
                $trade = $transaction::lockForupdate()->findOrFail($id);
                $trade->delete();
            });
            return $this->success('删除成功!');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * 手动撮合交易挂单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function match(Request $request)
    {
        $id = $request->input('id', 0);
        $type = $request->input('type', '');

        try {
            throw_if(!in_array($type, ['in', 'out']), new \Exception('交易类型异常'));
            $transaction_class = $type == 'in' ? TransactionIn::class : TransactionOut::class;

            //查询本单信息
            $trade = $transaction_class::lockForupdate()->findOrFail($id);

            //查询是否存在可以撮合的订单
            if($type == 'in'){
                $this->out(1068036,$trade->price,$trade->number,$trade->legal,$trade->currency);
            }else{
                $this->in(1068036,$trade->price,$trade->number,$trade->legal,$trade->currency);
            }

            return $this->success('撮合成功!');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * On Sale
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function out($user_id,$price,$num,$legal_id,$currency_id)
    {
        $user_id = Users::getUserId();
        $price = request()->input("price");
        $num = request()->input("num");
        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");
        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("Parameter Error");
        }
        $currency_match = CurrencyMatch::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if (!$currency_match) {
            return $this->error('The Specified Transaction Pair Does Not Exist');
        }
        if ($currency_match->open_transaction != 1) {
            return $this->error('You Have Not Opened The Transaction Function Of The Transaction Pair');
        }
        $exchange_rate = $currency_match->exchange_rate;
        $quantity = bc_div(bc_mul($num, $exchange_rate), 100); //Transaction Rate
        $real_quantity = $num + $quantity;
        $has_num = 0;
        $user = Users::find($user_id);

        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();
        if (empty($user) || empty($legal) || empty($currency)) {
            return $this->error("Data Not Found");
        }

        //UseJAVAConduct Matchmaking
        $use_java_match_trade = config('app.use_java_match_trade', 0);
        if ($use_java_match_trade) {
            $java_match_url = config('app.java_match_url', '');
            $request_client = new Client();
            $response = $request_client->post($java_match_url . '/api/transaction/out', [
                'headers' => [
                    'Authorization' => Token::getToken(),
                ],
                'form_params' => [
                    'legal_id' => $legal_id,
                    'currency_id' => $currency_id,
                    'price' => $price,
                    'num' => $num,
                    'type' => 1,
                ],
            ]);

            $result = $response->getBody()->getContents();
            $result = json_decode($result);
            if (!isset($result->type) || $result->type != 'ok') {
                return $this->error($result->message);
            }
            DB::commit();
            return $this->success("Operation Successful");
        }

        try {
            DB::beginTransaction();
            $user_currency = UsersWallet::where("user_id", $user_id)
                ->where("currency", $currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($user_currency)) {
                throw new \Exception("Please Add Your Wallet First");
            }
            if (bc_comp($price, '0') <= 0 || bc_comp($num, '0') <= 0) {
                throw new \Exception("Price And Quantity Must Be Greater Than0");
            }
            if (bc_comp($user_currency->change_balance, $real_quantity) < 0) {
                throw new \Exception("Your Balance Is Insufficient,Please Make Sure That The Handling Charge Is Sufficient{$exchange_rate}%({$quantity})");
            }
            if (bc_comp($user_currency->lock_change_balance, '0') < 0) {
                throw new \Exception("Your Frozen Fund Is Abnormal，No Selling");
            }

            //Service Charge Deducted In Advance
            $result = change_wallet_balance($user_currency, 2, -$quantity, AccountLog::MATCH_TRANSACTION_SELL_FEE, 'Service Charge Deducted For Hanging Sale,Quantity On Sale:' . $num . ',Rate:' . $exchange_rate . '%');
            if ($result !== true) {
                throw new \Exception($result);
            }
            //Find All Buy Orders Whose Price Is Higher Than Or Equal To The Current Sell Price
            $in = TransactionIn::where("price", ">=", $price)
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->where("number", ">", "0")
                ->orderBy('price', 'desc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();
            //dd($in);
            if (count($in) > 0) {
                foreach ($in as $i) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num);
                        $this_num = 0;
                        if (bc_comp($i->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $i->number;
                        }
                        $has_num = bc_add($has_num, $this_num);
                        if (bc_comp($this_num, '0') > 0) {
                            TransactionOut::transaction($i, $this_num, $user, $user_currency, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }
            $num = bc_sub($num, $has_num);
            if (bc_comp($num, '0') > 0) {
                $out = new TransactionOut();
                $out->user_id = $user_id;
                $out->price = $price;
                $out->number = $num;
                $out->currency = $currency_id;
                $out->legal = $legal_id;
                $out->create_time = time();
                $out->rate = $exchange_rate;
                $out->save();
                //Submit Sales Record Minus Transaction Currency
                $result = change_wallet_balance($user_currency, 2, -$num, AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE, 'Submit For Sale' . $currency_match->symbol . 'Deduction');
                if ($result !== true) {
                    throw new \Exception($result);
                }
                //Submit Sales Record(Increase Freeze)
                $result = change_wallet_balance($user_currency, 2, $num, AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE, 'Submit For Sale' . $currency_match->symbol . 'Frozen', true);
                if ($result !== true) {
                    throw new \Exception($result);
                }
            }
            if ($currency_match->market_from != 2) {
                Transaction::pushNews($currency_id, $legal_id);
            }
            DB::commit();
            return $this->success("Operation Successful");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    /**
     * Hang Up
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function in($user_id,$price,$num,$legal_id,$currency_id)
    {
//        $user_id = Users::getUserId();
//        $price = request()->input("price");
//        $num = request()->input("num");
//        $legal_id = request()->input("legal_id");
//        $currency_id = request()->input("currency_id");

        if (empty($user_id) || empty($price) || empty($num) || empty($legal_id) || empty($currency_id)) {
            return $this->error("Parameter Error");
        }
        $currency_match = CurrencyMatch::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if (!$currency_match) {
            return $this->error('The Specified Transaction Pair Does Not Exist');
        }
        if ($currency_match->open_transaction != 1) {
            return $this->error('You Have Not Opened The Transaction Function Of The Transaction Pair');
        }
        $has_num = 0;
        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();
        $user = Users::find($user_id);
        if (empty($user) || empty($legal) || empty($currency)) {
            return $this->error("Data Not Found");
        }

        if (bc_comp($price, '0') <= 0 || bc_comp($num, '0') <= 0) {
            return $this->error("Price And Quantity Must Be Greater Than0");
        }

        //UseJAVAConduct Matchmaking
        $use_java_match_trade = config('app.use_java_match_trade', 0);
        if ($use_java_match_trade) {
            $java_match_url = config('app.java_match_url', '');
            $request_client = new Client();
            $response = $request_client->post($java_match_url . '/api/transaction/in', [
                'headers' => [
                    'Authorization' => Token::getToken(),
                ],
                'form_params' => [
                    'legal_id' => $legal_id,
                    'currency_id' => $currency_id,
                    'price' => $price,
                    'num' => $num,
                    'type' => 1,
                ],
            ]);
            $result = $response->getBody()->getContents();
            $result = json_decode($result);
            if (!isset($result->type) || $result->type != 'ok') {
                return $this->error($result->message);
            }
            DB::commit();
            return $this->success("Operation Successful");
        }

        try {
            DB::beginTransaction();
            //How To Buy Coin Wallet
            $user_legal = UsersWallet::where("user_id", $user_id)
                ->where("currency", $legal_id)
                ->lockForUpdate()
                ->first();
            $all_balance = bc_mul($price, $num);
            if (bc_comp($user_legal->change_balance, $all_balance) < 0) {
                throw new \Exception('Sorry, Your Credit Is Running Low');
            }

            //Find All Sell Orders Whose Price Is Less Than Or Equal To The Current Price
            $out = TransactionOut::where("price", "<=", $price)
                ->where("number", ">", "0")
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->lockForUpdate()
                ->orderBy('price', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            if (count($out) > 0) {
                foreach ($out as $o) {
                    if (bc_comp($has_num, $num) < 0) {
                        $shengyu_num = bc_sub($num, $has_num);
                        $this_num = 0;
                        if (bc_comp($o->number, $shengyu_num) > 0) {
                            $this_num = $shengyu_num;
                        } else {
                            $this_num = $o->number;
                        }
                        $has_num = bc_add($has_num, $this_num);
                        if (bc_comp($this_num, '0') > 0) {
                            TransactionIn::transaction($o, $this_num, $user, $legal_id, $currency_id);
                        }
                    } else {
                        break;
                    }
                }
            }

            $remain_num = bcsub($num, $has_num); //Remaining Quantity After Matching
            if (bc_comp($remain_num, '0') > 0) {
                $in = new TransactionIn();
                $in->user_id = $user_id;
                $in->price = $price;
                $in->number = $remain_num;
                $in->currency = $currency_id;
                $in->legal = $legal_id;
                $in->create_time = time();
                $in->save();
                $all_balance = bc_mul($price, $remain_num);
                //Submit Purchase Record Deduction
                $result = change_wallet_balance($user_legal, 2, -$all_balance, AccountLog::TRANSACTIONIN_SUBMIT_REDUCE, 'Submit Linked Purchase' . $currency_match->symbol . 'Deduction');
                if ($result !== true) {
                    throw new \Exception($result);
                }
                //Submit Purchase Record Deduction Freeze
                $result = change_wallet_balance($user_legal, 2, $all_balance, AccountLog::TRANSACTIONIN_SUBMIT_REDUCE, 'Submit Linked Purchase' . $currency_match->symbol . 'Frozen', true);
                if ($result !== true) {
                    throw new \Exception($result);
                }
            }
            if ($currency_match->market_from != 2) {
                Transaction::pushNews($currency_id, $legal_id);
            }

            DB::commit();
            return $this->success("Operation Successful");
        } catch (\Exception $ex) {
            DB::rollback();
            return $this->error($ex->getMessage());
        }
    }
}
