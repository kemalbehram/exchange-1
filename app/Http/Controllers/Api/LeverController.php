<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{AccountLog, CurrencyMatch, LeverTransaction, LeverMultiple, Setting, TransactionComplete, TransactionIn, TransactionOut, Users, UsersWallet};
use App\Events\LeverSubmitOrderEvent;
use App\Jobs\LeverClose;

class LeverController extends Controller
{
    /**
     * Get Transaction Information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deal()
    {
        $user_id = Users::getUserId();
        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");
        if (empty($legal_id) || empty($currency_id)) {
            return $this->error("Parameter Error:(");
        }
        $lever_share_limit = [
            'min' => 1,
            'max' => 0,
        ];
        $curreny_match = CurrencyMatch::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if ($curreny_match) {
            $lever_share_limit = array_merge($lever_share_limit, [
                'min' => $curreny_match->lever_min_share,
                'max' => $curreny_match->lever_max_share,
            ]);
        }
        $my_transaction = LeverTransaction::with('user')
            ->orderBy('id', 'desc')
            ->where("user_id", $user_id)
            ->where("status", LeverTransaction::TRANSACTION)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->orderBy("id", "desc")
            ->take(10)
            ->get();
        $last_price = LeverTransaction::getLastPrice($legal_id, $currency_id);
        $user_lever = 0;
        $all_levers = 0;
        if (!empty($user_id)) {
            $legal = UsersWallet::where("user_id", $user_id)->where("currency", $legal_id)->first();
            if ($legal) {
                $user_lever = $legal->lever_balance;
            }
            $all_levers = LeverTransaction::where("legal", $legal_id)
                ->where("currency", $currency_id)
                ->where("user_id", $user_id)
                ->where("status", LeverTransaction::TRANSACTION)
                ->selectRaw('sum(`number` * `price`) as `all_levers`')
                ->value('all_levers');
            $all_levers || $all_levers = 0;
        }
        //$match_transaction = $this->getLastMathTransaction($legal_id, $currency_id);
        $lever_transaction = $this->getLastLeverTransaction($legal_id, $currency_id);
        $ustd_price = 0;
        $last = TransactionComplete::orderBy('id', 'desc')
            ->where("currency", $legal_id)
            ->where("legal", 3)
            ->first();
        if (!empty($last)) {
            $ustd_price = $last->price;
        }
        if ($legal_id == 3) {
            $ustd_price = 1;
        }
        return $this->success([
            "lever_transaction" => $lever_transaction,
            "my_transaction" => $my_transaction,
            "lever_share_limit" => $lever_share_limit,
            "multiple" => LeverTransaction::leverMultiple(),
            "last_price" => $last_price,
            "user_lever" => $user_lever,
            "all_levers" => $all_levers,
            "ustd_price" => $ustd_price,
            "ExRate" => Setting::getValueByKey('USDTRate', 6.5),
        ]);
    }

    /**
     * Transaction List
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dealAll()
    {
        $user_id = Users::getUserId();
        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");
        $limit = request()->input("limit", 10);
        $page = request()->input("page", 1);
        if (empty($legal_id) || empty($currency_id)) {
            return $this->error("Parameter Error");
        }
        $lever_transaction = LeverTransaction::with('user')
            ->orderBy('id', 'desc')
            ->where("user_id", $user_id)
            ->where("status", LeverTransaction::TRANSACTION)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->paginate($limit);
        $user_wallet = UsersWallet::where('currency', $legal_id)->where('user_id', $user_id)->first();
        $balance = $user_wallet ? $user_wallet->lever_balance : 0;
        //Total Profit And Loss
        list(
            'caution_money_total' => $caution_money_all,
            'origin_caution_money_total' => $origin_caution_money_all,
            'profits_total' => $profits_all
        ) = LeverTransaction::getUserProfit($user_id, $legal_id);
        //Take The Total Profit And Loss Of The Transaction
        list(
            'caution_money_total' => $caution_money,
            'origin_caution_money_total' => $origin_caution_money,
            'profits_total' => $profits
        ) = LeverTransaction::getUserProfit($user_id, $legal_id, $currency_id);
        $total_all_money = bc_add($caution_money_all, $balance);
        $hazard_rate = LeverTransaction::getWalletHazardRate($user_wallet);
        $data = [
            'balance' => $balance,
            'hazard_rate' => $hazard_rate,
            'caution_money_total' => $caution_money_all,
            'origin_caution_money_total' => $origin_caution_money_all,
            'profits_total' => $profits_all,
            'caution_money' => $caution_money,
            'origin_caution_money' => $origin_caution_money,
            'profits' => $profits,
            'order' => $lever_transaction,
        ];
        return $this->success($data);
    }

    /**
     * My Deal
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function myTrade()
    {
        $user_id = Users::getUserId();
        $legal_id = request()->input("legal_id", 0);
        $currency_id = request()->input("currency_id", 0);
        $status = request()->input("status", -1);
        $limit = request()->input("limit", 10);
        $param = compact('status', 'legal_id', 'currency_id');
        $lever_transaction = LeverTransaction::where(function ($query) use ($param) {
            extract($param);
            $status != -1 && $query->where('status', $status);
            $legal_id > 0 && $query->where('legal', $legal_id);
            $currency_id > 0 && $query->where('currency', $currency_id);
        })->where('user_id', $user_id)
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->success($lever_transaction);
    }

    /**
     * Submit Leverage Transaction
     *
     * @return void
     */
    public function submit()
    {
        $user_id = Users::getUserId();
        $share = request()->input("share");
        $multiple = request()->input("multiple");
        $type = request()->input("type", "1");
        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");
        $status = request()->input('status', LeverTransaction::TRANSACTION); //The Default Is Market Price,By0It's A Registered Transaction
        $target_price = request()->input('target_price', 0); //Target Price
        $password = request()->input('password', ''); //Payment Password
        $now = time();
        $user_lever = 0;

        if (empty($legal_id) || empty($currency_id) || empty($share) || empty($multiple)) {
            return $this->error("Missing Parameter Or Wrong Value");
        }
        $currency_match = CurrencyMatch::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if (!$currency_match) {
            return $this->error('The Specified Transaction Pair Does Not Exist');
        }
        if ($currency_match->open_lever != 1) {
            return $this->error('You Have Not Opened The Trading Function Of This Trading Pair');
        }
        //Hand Count Judgment:Greater Than0Integer Of,And In The Range Of Interval
        if ($share != intval($share) || !is_numeric($share) || $share <= 0) {
            return $this->error('Hands Must Be Greater Than0Integer Of');
        }
        if (bc_comp($currency_match->lever_min_share, $share) > 0) {
            return $this->error('The Number Of Hands Cannot Be Less Than' . $currency_match->lever_min_share);
        }
        if (bc_comp($currency_match->lever_max_share, $share) < 0 && bc_comp($currency_match->lever_max_share, '0') > 0) {
            return $this->error('Hand Count Cannot Be Higher Than' . $currency_match->lever_max_share);
        }
        //Multiple Judgment
        $multiples = LeverMultiple::where("type", 1)->pluck('value')->all();
        if (!in_array($multiple, $multiples)) {
            return $this->error('The Selection Multiple Is Not In The System Range');
        }
        //$lever_min_share->lever_max_share
        $exist_close_trade = LeverTransaction::where('user_id', $user_id)->where('status', LeverTransaction::CLOSING)->count();
        if ($exist_close_trade > 0) {
            return $this->error('Do You Have A Transaction In The Process Of Closing,No Business For The Time Being');
        }
        if (!in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION])) {
            return $this->error('Transaction Type Error');
        }
        if ($status == LeverTransaction::ENTRUST) {
            $open_lever_entrust = Setting::getValueByKey('open_lever_entrust', 0);
            if ($open_lever_entrust <= 0) {
                return $this->error('This Function Is Not Open Yet');
            }
        }
        //Judge Whether To Entrust Transaction (Price Limit Trading)
        if ($status == LeverTransaction::ENTRUST && $target_price <= 0) {
            return $this->error('Limit Transaction Price Must Be Greater Than0');
        }
        $overnight = $currency_match->overnight ?? 0;
        //Priority To Get The Latest Price From The Market
        $last_price = LeverTransaction::getLastPrice($legal_id, $currency_id);
        if (bc_comp_zero($last_price) <= 0) {
            return $this->error('There Is No Market Price At Present,Please Try Again Later');
        }
        //Registered Order Entrustment(Price Limit Trading)The Price Is Set By The User
        if ($status == LeverTransaction::ENTRUST) {
            if ($type == LeverTransaction::SELL && $target_price <= $last_price) {
                return $this->error('The Selling Price Of Limit Transaction Cannot Be Lower Than The Current Price');
            } elseif ($type == LeverTransaction::BUY && $target_price >= $last_price) {
                return $this->error('The Purchase Price Of Limit Trading Cannot Be Higher Than The Current Price');
            }
            $origin_price = $target_price;
        } else {
            $origin_price = $last_price;
        }
        //Transaction Number Conversion
        $lever_share_num = $currency_match->lever_share_num ?? 1;
        $num = bc_mul($share, $lever_share_num);
        //Point Difference Rate
        $spread = $currency_match->spread;
        $spread_price = bc_div(bc_mul($origin_price, $spread), 100);
        $type == LeverTransaction::SELL && $spread_price = bc_mul(-1, $spread_price); //Buying Should Add A Spread,Sell And Subtract The Difference
        $fact_price = bc_add($origin_price, $spread_price); //The Actual Price After The Difference Is Charged
        $all_money = bc_mul($fact_price, $num, 5);
        //Calculation Of Handling Charge
        $lever_trade_fee_rate = bc_div($currency_match->lever_trade_fee ?? 0, 100);
        $trade_fee = bc_mul($all_money, $lever_trade_fee_rate);
        DB::beginTransaction();
        try {
            $legal = UsersWallet::where("user_id", $user_id)
                ->where("currency", $legal_id)
                ->lockForUpdate()
                ->first();
            if (!$legal) {
                throw new \Exception("Wallet Not Found,Please Add Your Wallet First");
            }
            $user_lever = $legal->lever_balance;
            $caution_money = bc_div($all_money, $multiple); //Bond
            $shoud_deduct = bc_add($caution_money, $trade_fee); //Bond+Service Charge
            if (bc_comp($user_lever, $shoud_deduct) < 0) {
                throw new \Exception($currency_match->legal_name . "Sorry, Your Credit Is Running Low,Cannot Be Less Than" . $shoud_deduct . '(Service Charge:' . $trade_fee . ')');
            }
            $lever_transaction = new LeverTransaction();
            $lever_transaction->user_id = $user_id;
            $lever_transaction->type = $type;
            $lever_transaction->overnight = $overnight;
            $lever_transaction->origin_price = $origin_price;
            $lever_transaction->price = $fact_price;
            $lever_transaction->update_price = $last_price;
            $lever_transaction->share = $share;
            $lever_transaction->number = $num;
            $lever_transaction->origin_caution_money = $caution_money;
            $lever_transaction->caution_money = $caution_money;
            $lever_transaction->currency = $currency_id;
            $lever_transaction->legal = $legal_id;
            $lever_transaction->multiple = $multiple;
            $lever_transaction->trade_fee = $trade_fee;
            $lever_transaction->transaction_time = $now;
            $lever_transaction->create_time = $now;
            $lever_transaction->status = $status;

            //Agent Relationship Of Additional Users
            $user = Users::find($user_id);
            $lever_transaction->agent_path = $user->agent_path;

            $result = $lever_transaction->save();
            if (!$result) {
                throw new \Exception("Failed To Submit");
            }
            //Deduction Of Deposit
            $result = change_wallet_balance(
                $legal,
                3,
                -$caution_money,
                AccountLog::LEVER_TRANSACTION,
                'Submit' . $currency_match->symbol . 'Contract Transaction,Price' . $fact_price . ',Deduction Of Deposit',
                false,
                0,
                0,
                serialize([
                    'trade_id' => $lever_transaction->id,
                    'all_money' => $all_money,
                    'multiple' => $multiple,
                ])
            );
            if ($result !== true) {
                throw new \Exception('Failure To Deduct Margin:' . $result);
            }
            //Deduction Of Handling Charge
            $result = change_wallet_balance(
                $legal,
                3,
                -$trade_fee,
                AccountLog::LEVER_TRANSACTION_FEE,
                'Submit' . $currency_match->symbol . 'Contract Transaction,Deduction Of Handling Charge',
                false,
                0,
                0,
                serialize([
                    'trade_id' => $lever_transaction->id,
                    'all_money' => $all_money,
                    'lever_trade_fee_rate' => $lever_trade_fee_rate,
                ])
            );
            if ($result !== true) {
                throw new \Exception('Deduction Of Service Charge Failed:' . $result);
            }
            DB::commit();
            //Recommendation Award:Handling Charge Settlement
            event(new LeverSubmitOrderEvent($lever_transaction));
            return $this->success("Submitted Successfully");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    /**
     * Set Up Profit And Loss Control
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setStopPrice()
    {
        $user_set_stopprice = Setting::getValueByKey('user_set_stopprice', 0);
        if (!$user_set_stopprice) {
            return $this->error('This Function System Is Not Open');
        }
        $id = request()->input('id', 0);
        $user_id = Users::getUserId();
        $target_profit_price = request()->input('target_profit_price', 0);
        $stop_loss_price = request()->input('stop_loss_price', 0);
        if ($target_profit_price <= 0 || $stop_loss_price <= 0) {
            return $this->error('Stop Loss Price Cannot Be0');
        }
        $lever_transaction = LeverTransaction::where('user_id', $user_id)
            ->where('status', LeverTransaction::TRANSACTION)
            ->find($id);
        if (!$lever_transaction) {
            return $this->error('The Transaction Was Not Found');
        }
        if ($lever_transaction->type == 1) {
            //Purchase
            if ($target_profit_price <= $lever_transaction->price || $target_profit_price <= $lever_transaction->update_price) {
                return $this->error('Purchase(Long)Stop Profit Price Cannot Be Lower Than Opening Price And Current Price');
            }
            if ($stop_loss_price >= $lever_transaction->price || $stop_loss_price >= $lever_transaction->update_price) {
                return $this->error('Purchase(Long)Stop Loss Price Cannot Be Higher Than Opening Price And Current Price');
            }
        } else {
            //Sell Out
            if ($target_profit_price >= $lever_transaction->price || $target_profit_price >= $lever_transaction->update_price) {
                return $this->error('Sell Out(Short)Stop Profit Price Cannot Be Higher Than Opening Price And Current Price');
            }
            if ($stop_loss_price <= $lever_transaction->price || $stop_loss_price <= $lever_transaction->update_price) {
                return $this->error('Sell Out(Short)Stop Loss Price Cannot Be Lower Than Opening Price And Current Price');
            }
        }
        $target_profit_price > 0 && $lever_transaction->target_profit_price = $target_profit_price;
        $stop_loss_price > 0 && $lever_transaction->stop_loss_price = $stop_loss_price;
        $result = $lever_transaction->save();
        return $result ? $this->success('Set Successfully') : $this->error('Setup Failed');
    }

    /**
     * Close A Position
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function close()
    {
        $user_id = Users::getUserId();
        $id = request()->input("id");
        if (empty($id)) {
            return $this->error("Parameter Error");
        }
        DB::beginTransaction();
        try {
            $lever_transaction = LeverTransaction::lockForupdate()->find($id);
            if (empty($lever_transaction)) {
                throw new \Exception("Data Not Found");
            }
            if ($lever_transaction->user_id != $user_id) {
                throw new \Exception("Unauthorized Operation");
            }
            if ($lever_transaction->status != LeverTransaction::TRANSACTION) {
                throw new \Exception("Abnormal Transaction Status,Please Do Not Submit Repeatedly");
            }
            $return = LeverTransaction::leverClose($lever_transaction, 1);
            if (!$return) {
                throw new \Exception("Closing Position Failed,Please Try Again");
            }
            DB::commit();
            return $this->success("Operation Successful");
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    /**
     * Batch Closing(According To The Buying And Selling Direction)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchCloseByType(Request $request)
    {
        $user_id = Users::getUserId();
        $legal_id = $request->input('legal_id', 0);
        $currency_id = $request->input('currency_id', 0);
        $type = $request->input('type', 0); //0.All,1.Purchase(Long),2.Sell Out(Short)
        if (!in_array($type, [0, 1, 2])) {
            return $this->error('Wrong Parameter Transmission In Buying Direction');
        }
        $lever = LeverTransaction::where('status', LeverTransaction::TRANSACTION)
            ->where('user_id', $user_id)
            ->where(function ($query) use ($type, $legal_id, $currency_id) {
                !empty($legal_id) && $query->where('legal', $legal_id);
                !empty($currency_id) && $query->where('currency', $currency_id);
                !empty($type) && $query->where('type', $type);
            })->get();
        $task_list = $lever->pluck('id')->all();
        $result = LeverTransaction::where('status', LeverTransaction::TRANSACTION)
            ->whereIn('id', $task_list)
            ->update([
                'closed_type' => 1,
                'status' => LeverTransaction::CLOSING,
                'handle_time' => microtime(true),
            ]);
        if ($result > 0) {
            LeverClose::dispatch($task_list, true)->onQueue('lever:close');
        }
        return $result > 0 ? $this->success('Submitted Successfully,Please Wait For The System To Process') : $this->error('No Transaction To Close');
    }

    /**
     * Batch Closing(According To Profit And Loss)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchCloseByProfit(Request $request)
    {
        $user_id = Users::getUserId();
        $type = $request->input('type'); //0.All,1.Surplus,2.Deficit
        $lever = LeverTransaction::where('status', LeverTransaction::TRANSACTION)
            ->where('user_id', $user_id)
            ->get();
        switch ($type) {
            case 1:
                $lever = $lever->where('profits', '>', 0);
                break;
            case 2:
                $lever = $lever->where('profits', '<', 0);
                break;
            default:
        }
        $task_list = $lever->pluck('id')->all();
        $result = LeverTransaction::where('status', LeverTransaction::TRANSACTION)
            ->whereIn('id', $task_list)
            ->update([
                'closed_type' => 1,
                'status' => LeverTransaction::CLOSING,
                'handle_time' => microtime(true),
            ]);
        if ($result > 0) {
            LeverClose::dispatch($task_list, true)->onQueue('lever:close');
        }
        return $result > 0 ? $this->success('Submitted Successfully,Please Wait For The System To Process') : $this->error('No Transaction To Close');
    }

    /**
     * Take The Latest Matchmaking Deals
     *
     * @param integer $legal_id Legal Currencyid
     * @param integer $currency_id Transaction Currencyid
     * @param integer $limit Limit The Number Of Items,Default5
     * @return array
     */
    public function getLastMathTransaction($legal_id, $currency_id, $limit = 5)
    {
        $in = TransactionIn::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'desc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit($limit)
            ->get();
        $out = TransactionOut::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'asc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit($limit)
            ->get()
            ->sortByDesc('price')
            ->values();
        return [
            'in' => $in,
            'out' => $out,
        ];
    }

    /**
     * Take The Latest Contracts
     *
     * @param integer $legal_id Legal Currencyid
     * @param integer $currency_id Transaction Currencyid
     * @param integer $limit Limit The Number Of Items,Default5
     * @return array
     */
    public function getLastLeverTransaction($legal_id, $currency_id, $limit = 5)
    {
        $in = LeverTransaction::with('user')
            ->where('legal', $legal_id)
            ->where('currency', $currency_id)
            ->where('type', LeverTransaction::BUY)
            ->where('status', LeverTransaction::TRANSACTION)
            ->orderBy('price', 'desc')
            ->limit($limit)
            ->get();
        $out = LeverTransaction::with('user')
            ->where('legal', $legal_id)
            ->where('currency', $currency_id)
            ->where('type', LeverTransaction::SELL)
            ->where('status', LeverTransaction::TRANSACTION)
            ->orderBy('price', 'asc')
            ->limit($limit)
            ->get()
            ->sortByDesc('price')
            ->values();
        return [
            'in' => $in,
            'out' => $out,
        ];
    }

    /**
     * Cancel Registration(Cancel The Order)
     *
     * @return boolean
     */
    public function cancelTrade(Request $request)
    {
        $user_id = Users::getUserId();
        $id = $request->input('id');
        try {
            //Refund Of Service Charge And Deposit
            DB::transaction(function () use ($user_id, $id) {
                $lever_trade = LeverTransaction::where('user_id', $user_id)
                    ->where('status', LeverTransaction::ENTRUST)
                    ->lockForUpdate()
                    ->find($id);
                if (!$lever_trade) {
                    throw new \Exception('Transaction Does Not Exist Or Cancelled,Please Refresh And Try Again');
                }
                $legal_id = $lever_trade->legal;
                $refund_trade_fee = $lever_trade->trade_fee;
                $refund_caution_money = $lever_trade->caution_money;
                $legal_wallet = UsersWallet::where('user_id', $user_id)
                    ->where('currency', $legal_id)
                    ->first();
                if (!$legal_wallet) {
                    throw new \Exception('Cancellation Failed:' . 'User Wallet Does Not Exist');
                }
                $result = change_wallet_balance(
                    $legal_wallet,
                    3,
                    $refund_trade_fee,
                    AccountLog::LEVER_TRANSACTION_FEE_CANCEL,
                    'Contract' . $lever_trade->type_name . 'Consignment Cancellation,Refund Of Service Charge',
                    false,
                    0,
                    0,
                    '',
                    true
                );
                if ($result !== true) {
                    throw new \Exception('Cancellation Failed:' . $result);
                }
                $result = change_wallet_balance(
                    $legal_wallet,
                    3,
                    $refund_caution_money,
                    AccountLog::LEVER_TRANSACTIO_CANCEL,
                    'Contract' . $lever_trade->type_name . 'Consignment Cancellation,Refund Of Deposit',
                    false,
                    0,
                    0,
                    '',
                    true
                );
                if ($result !== true) {
                    throw new \Exception('Cancellation Failed:' . $result);
                }
                $lever_trade->status = LeverTransaction::CANCEL;
                $lever_trade->complete_time = time();
                $result = $lever_trade->save();
                if (!$result) {
                    throw new \Exception('Cancellation Failed:Change Status Failed');
                }
                $lever_trades = collect([$lever_trade]);
                LeverTransaction::pushDeletedTrade($lever_trades);
            });
            return $this->success('Cancellation Successful');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
