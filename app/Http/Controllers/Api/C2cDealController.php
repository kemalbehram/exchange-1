<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AccountLog;
use App\Models\Currency;
use App\Models\C2cDeal;
use App\Models\C2cDealSend;
use App\Models\Setting;
use App\Models\Users;
use App\Models\UsersWallet;
use App\Models\UserReal;
use App\Models\UserCashInfo;

class C2cDealController extends Controller
{

    /**
     * User PublishingC2CTransaction Information
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postSend(Request $request)
    {
        $type = $request->input('type', null);
        $way = $request->input('way', null);
        $price = $request->input('price', null);
        $total_number = $request->input('total_number', null);
        // $min_number = $request->input('min_number', null);
        $currency_id = $request->input('currency_id', null);
        $coin_code = strtoupper($request->input('coin_code', ''));
        if (empty($coin_code) || !in_array($coin_code, ['CNY', 'USD', 'JPY'])) {
            return $this->error('Invalid Currency Code');
        }
        if (empty($type)) return $this->error('Please Select Requirement Type');
        if (empty($way)) return $this->error('Please Choose The Transaction Method');
        if (empty($price)) return $this->error('Please Fill In The Unit Price');
        if (empty($total_number)) return $this->error('Please Fill In The Quantity');
        // if (empty($min_number)) return $this->error('Please Fill In The Minimum Transaction Quantity');
        if (empty($currency_id)) return $this->error('Please Select Currency');
        // if ($min_number > $total_number) return $this->error('The Minimum Transaction Quantity Cannot Be Greater Than The Total Quantity');
        if ($price < 0 || $total_number < 0) {
            return $this->error('Please Enter The Correct Transaction Quantity Or Price');
        }

        $c2c_sell_fee = Setting::getValueByKey('c2c_sell_fee', 0);
        $c2c_sell_fee = bc_div($c2c_sell_fee, 100);
        $fee = 0;

        try {
            DB::beginTransaction();
            $user_id = Users::getUserId();
            //Judgment Of Collection Method
            $user_cash_info = UserCashInfo::where('user_id', $user_id)->first();
            if (!$user_cash_info) {
                DB::rollback();
                return response()->json(['type' => '997', 'message' => 'You Have Not Set The Collection Information']);
            }

            if ($type == 'sell') {   //If The Sale Information Is Released

                $wallet = UsersWallet::where('user_id', $user_id)
                    ->where('currency', $currency_id)
                    ->lockForUpdate()
                    ->first();
                if (empty($wallet)) {
                    return $this->error('User Wallet Does Not Exist');
                }
                $fee = bc_mul($total_number, $c2c_sell_fee);
                $should_deduct_number = bc_add($total_number, $fee);
                if (bc_comp($wallet->legal_balance, $should_deduct_number) < 0) {
                    return $this->error('Sorry, Your Credit Is Running Low,Please Make Sure That The Handling Charge Is Sufficient');
                }
                //Deduction Of Legal Currency Balance
                $result = change_wallet_balance($wallet, 1, -$total_number, AccountLog::C2C_DEAL_SEND_SELL, 'User Publishingc2cSale Of Legal Currency，Deduction Of Legal Currency Balance');
                if ($result !== true) {
                    throw new \Exception($result);
                }
                //Increase Legal Currency Frozen Balance
                $result = change_wallet_balance($wallet, 1, $total_number, AccountLog::C2C_DEAL_SEND_SELL, 'User Publishingc2cSale Of Legal Currency，Deduction Of Legal Currency Balance', true);
                if ($result !== true) {
                    throw new \Exception($result);
                }
                //Deduction Of Handling Charge
                $result = change_wallet_balance($wallet, 1, -$fee, AccountLog::C2C_TRADE_FEE, 'Transaction Fee Deduction');
                if ($result !== true) {
                    throw new \Exception($result);
                }
            } else {
                //Purchase Without Deduction Of Funds
            }

            $legal_deal_send = new C2cDealSend();
            $legal_deal_send->seller_id = $user_id;
            $legal_deal_send->currency_id = $currency_id;
            $legal_deal_send->type = $type;
            $legal_deal_send->way = $way;
            $legal_deal_send->price = $price;
            $legal_deal_send->total_number = $total_number;
            $legal_deal_send->surplus_number = $total_number;
            $legal_deal_send->out_fee = $fee;
            $legal_deal_send->coin_code = $coin_code;
            // $legal_deal_send->min_number = $min_number;
            $legal_deal_send->create_time = time();
            $legal_deal_send->save();
            DB::commit();
            
            return $this->success('Successfully Published');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }


    }

    /**
     * Publisher User Details  
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sellerInfo(Request $request)
    {
        $id = $request->input('id', null);
        $type = $request->input('type', null);
        $was_done = $request->input('was_done', 'false');
        $limit = $request->input('limit', 10);

        if (empty($id)) return $this->error('Parameter Error');
        $seller = Users::find($id);
        if (empty($seller)) return $this->error('No Such User');
        $beforeThirtyDays = Carbon::today()->subDay(30)->timestamp;   //30Days Ago
        $results = Users::withCount(['legalDeal as total', 'legalDeal as done' => function ($query) {
            $query->where('is_sure', 1);
        }, 'legalDeal as thirtyDays' => function ($query) use ($beforeThirtyDays) {
            $query->where('is_sure', 1)->where('update_time', '>=', $beforeThirtyDays);
        }])->find($id);
        $lists = C2cDealSend::where('seller_id', $id);
        //Is It Complete
        if ($was_done == 'true') {
            $lists = $lists->where('is_done', '=', '1');
        } elseif ($was_done == 'false') {
            $lists = $lists->where('is_done', '=', '0');
        }
        //Sell Or Buy
        if ($type == 'buy') {
            $type = 'buy';
            $lists = $lists->where('type', $type);
        } elseif ($type == 'sell') {
            $type = 'sell';
            $lists = $lists->where('type', $type);
        }

        $lists = $lists->orderBy('id', 'desc')->paginate($limit);
        $results->lists = array('data' => $lists->items(), 'page' => $lists->currentPage(), 'pages' => $lists->lastPage(), 'total' => $lists->total());
        return $this->success($results);
    }

    //My Published List li  
    public function tradeList(Request $request)
    {
        $currency_id = $request->input('currency_id', null);
        $type = $request->input('type', null);
        //$was_done =  $request->input('was_done',null);
        $limit = $request->input('limit', 10);
        $id = Users::getUserId();
        $user = Users::find($id);
        // $seller = Seller::where('user_id', $id)->first();

        if (empty($user)) {
            return $this->error('User Does Not Exist');
        }
        $lists = C2cDealSend::where('seller_id', $id)->where('is_done', '<', 2);
        //Is It Complete
        // if ($was_done == 'true') {
        //     $lists = $lists->where('is_done','=','1');
        // } elseif ($was_done == 'false') {
        //     $lists = $lists->where('is_done','=','0');
        // }
        //Sell Or Buy
        if ($type == 'buy') {
            $type = 'buy';
            $lists = $lists->where('type', $type);
        } elseif ($type == 'sell') {
            $type = 'sell';
            $lists = $lists->where('type', $type);
        }
        if ($currency_id) {
            $lists = $lists->where('currency_id', $currency_id);
        }
        $lists = $lists->whereDoesntHave('legalDeal', function ($query) {
            $query->where('is_sure', '=', 1);
        });

        $lists = $lists->orderBy('id', 'desc')->paginate($limit);
        $result = array('data' => $lists->items(), 'page' => $lists->currentPage(), 'pages' => $lists->lastPage(), 'total' => $lists->total());
        return $this->success($result);
    }


    /**
     * List Of Legal Currency Transaction Information Released By Users
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function legalDealPlatform(Request $request)
    {
        $limit = $request->input('limit', 10);
        $currency_id = $request->input('currency_id', '');
        $type = $request->input('type', 'sell');
        if (empty($currency_id)) return $this->error('Parameter Error');
        if (empty($type)) return $this->error('Parameter Error2');
        $currency = Currency::find($currency_id);
        if (empty($currency)) return $this->error('No Such Currency');
        if (empty($currency->is_legal)) return $this->error('The Currency Is Not Legal');

        $results = C2cDealSend::where('currency_id', $currency_id)->where('is_done', 0)->where('type', $type)->orderBy('id', 'desc')->paginate($limit);
        return $this->pageDate($results);
    }

    /**
     * Legal Currency Transaction Details
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function legalDealSendInfo(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $legal_deal_send = C2cDealSend::find($id);
        if (empty($legal_deal_send)) return $this->error('No Such Record');
        // $legal_deal_send['sell_cash_info'] = UserCashInfo::where('user_id',$legal_deal_send)->first();
        return $this->success($legal_deal_send);
    }

    /**
     * Transaction Button
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function doDeal(Request $request)
    {
        $deal_send_id = $request->input('id', null);
        if (empty($deal_send_id)) {
            return $this->error('Parameter Error');
        }
        $user_id = Users::getUserId();
        //Real Name Authentication Detection
        $user_real = UserReal::where('user_id', $user_id)
            ->where('review_status', 2)
            ->first();
        if (!$user_real) {
            return response()->json(['type' => '998', 'message' => 'You Have Not Passed The Real Name Authentication']);
        }
        //Collection Information Detection
        $user_cash_info = UserCashInfo::where('user_id', $user_id)->first();
        if (!$user_cash_info) {
            return response()->json(['type' => '997', 'message' => 'You Have Not Set The Collection Information']);
        }

        DB::beginTransaction();
        try {
            $legal_deal_send = C2cDealSend::lockForUpdate()->find($deal_send_id);
            if (empty($legal_deal_send)) {
                DB::rollback();
                return $this->error('No Such Record');
            }
            if (!empty($legal_deal_send->is_done)) {
                DB::rollback();
                return $this->error('This Transaction Has Been Completed');
            }

            $money = bc_mul($legal_deal_send->total_number, $legal_deal_send->price, 6);
            $number = $legal_deal_send->total_number;

            $seller = Users::find($legal_deal_send->seller_id);
            if (empty($seller)) {
                DB::rollback();
                return $this->error('The Publishing User Was Not Found');
            }

            if ($user_id == $seller->id) {
                DB::rollback();
                return $this->error('You Cant Operate Your Own');
            }
            $users_wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $legal_deal_send->currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($users_wallet)) {
                DB::rollback();
                return $this->error('You Dont Have This Wallet Account');
            }
            if (!empty($users_wallet->status)) {
                DB::rollback();
                return $this->error('Your Wallet Is Locked，Please Contact The Administrator');
            }

            $hasNonDone = C2cDeal::where([
                ['user_id', '=', $user_id],
            ])->whereIn('is_sure', [0, 3])->first();
            if (!empty($hasNonDone)) {
                DB::rollBack();
                return $this->error('Detect Incomplete Transactions，Please Come Back When You Are Finished！');
            }
            $fee = 0;
            if ($legal_deal_send->type == 'buy') { //Want To Buy
                $c2c_sell_fee = Setting::getValueByKey('c2c_sell_fee', 0);
                $c2c_sell_fee = bc_div($c2c_sell_fee, 100);
                
                $fee = bc_mul($number, $c2c_sell_fee);
                $should_deduct_number = bc_add($number, $fee);

                // do something
                if ($users_wallet->legal_balance < $should_deduct_number) {
                    DB::rollback();
                    return $this->error('Your Balance Is Insufficient');
                }
                if ($users_wallet->lock_legal_balance < 0) {
                    DB::rollback();
                    return $this->error('Your Legal Currency Freezing Fund Is Abnormal,Please Check If You Have Any Registration In Progress');
                }

                $legal_deal_send->is_done = 1;
                $legal_deal_send->save();
                $result = change_wallet_balance($users_wallet, 1, -$number, AccountLog::C2C_DEAL_USER_SELL, 'C2CSale To Buyer,Decrease In Balance');
                if ($result !== true) {
                    throw new \Exception($result);
                }
                $result = change_wallet_balance($users_wallet, 1, $number, AccountLog::C2C_DEAL_USER_SELL, 'C2CSale To Buyer,Lock Balance Increase', true);
                if ($result !== true) {
                    throw new \Exception($result);
                }
                $result = change_wallet_balance($users_wallet, 1, -$fee, AccountLog::C2C_TRADE_FEE, 'C2CSale To Buyer,Deduction Of Handling Charge');
                if ($result !== true) {
                    throw new \Exception($result);
                }
            } elseif ($legal_deal_send->type == 'sell') {
                //Sell
                // $legal_deal_send->surplus_number -= $number;
                // $legal_deal_send->surplus_number = bc_sub($legal_deal_send->surplus_number,$number,5);
                // if ($legal_deal_send->surplus_number == 0) {
                //     $legal_deal_send->is_done = 1;
                // }
                $fee = $legal_deal_send->out_fee;
                $legal_deal_send->is_done = 1;
                $legal_deal_send->save();
            }
            $legal_deal = new C2cDeal();
            $legal_deal->legal_deal_send_id = $deal_send_id;
            $legal_deal->user_id = $user_id;
            $legal_deal->seller_id = $seller->id;
            $legal_deal->out_fee = $fee;
            $legal_deal->number = $number; //Number Of Transactions
            $legal_deal->create_time = time();
            $legal_deal->save();

            if ($legal_deal_send->type == 'buy') {
                //Setting::sendSmsForSmsBao($seller->account_number, 'Your Purchase Information Is For Sale，Please Go APP Check It Out～');
            } else {
                //Setting::sendSmsForSmsBao($seller->account_number, 'Your Sale Information Has Been Purchased By Users，Please Go APP Check It Out～');
            }

            DB::commit();
            return $this->success([
                'msg' => 'Operation Successful，Please Contact The Seller To Confirm The Order',
                'data' => $legal_deal,
            ]);

        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage() . ',Error On Page' . $exception->getLine() . 'Thats Ok');
        }
    }

    /**
     * Merchant Side List Of Legal Currency Transaction
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sellerLegalDealList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $type = $request->input('type', '');
        $currency_id = $request->input('currency_id', '');

        if (empty($currency_id)) {
            return $this->error('Parameter Error');
        }

        $currency = Currency::find($currency_id);
        if (empty($currency)) {
            return $this->error('No Such Currency');
        }
        if (empty($currency->is_legal)) {
            return $this->error('The Currency Is Not Legal');
        }
        $user_id = Users::getUserId();
        $seller = Users::find($user_id);
        if (empty($seller)) {
            return $this->error('Incorrect User Information');
        }

        $results = C2cDeal::where('seller_id', $seller->id);
        if (!empty($type)) {
            $results = $results->whereHas('legalDealSend', function ($query) use ($type) {
                $query->where('type', $type);
            });
        }

        if (!empty($currency_id)) {
            $results = $results->whereHas('legalDealSend', function ($query) use ($currency_id) {
                $query->where('currency_id', $currency_id);
            });
        }
        $results = $results->where('is_sure', 1)->orderBy('id', 'desc')->paginate($limit);
        return $this->pageDate($results);

    }

    /**
     * Client List Of Legal Currency Transaction
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $type = $request->input('type', null);
        $currency_id = $request->input('currency_id', '');
        $is_sure = $request->input('is_sure', null);

        if (!empty($currency_id)) {
            $currency = Currency::find($currency_id);
            if (empty($currency)) return $this->error('No Such Currency');
            if (empty($currency->is_legal)) return $this->error('The Currency Is Not Legal');
        }

        $user_id = Users::getUserId();

        $results = C2cDeal::where('user_id', $user_id)->whereHas('legalDealSend');
        if (!empty($type)) {
            $results = $results->whereHas('legalDealSend', function ($query) use ($type) {
                $query->where('type', $type);
            });
        }

        if (!empty($currency_id)) {
            $results = $results->whereHas('legalDealSend', function ($query) use ($currency_id) {
                $query->where('currency_id', $currency_id);
            });
        }

        if (!is_null($is_sure)) {
            $results = $results->where('is_sure', $is_sure);
        }
        $results = $results->orderBy('id', 'desc')->paginate($limit);
        return $this->pageDate($results);
    }

    /**
     * Order Details Page
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function legalDealInfo(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $legal_deal = C2cDeal::find($id);
        if (empty($legal_deal)) {
            return $this->error('No Such Record');
        }
        return $this->success($legal_deal);
    }

    /**
     * User Confirms Payment
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealPay(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');
        $legal_deal = C2cDeal::find($id);
        if (empty($legal_deal)) {
            return $this->error('No Such Record');
        }
        DB::beginTransaction();
        try {
            if ($legal_deal->is_sure > 0) {
                DB::rollback();
                return $this->error('The Order Has Been Operated，Do Not Repeat');
            }
            $user_id = Users::getUserId();
            if ($legal_deal->type == 'sell') { //Client-Purchase
                if ($user_id != $legal_deal->user_id) {
                    DB::rollback();
                    return $this->error('Im Sorry，You Are Not Authorized To Operate');
                }
            } elseif ($legal_deal->type == 'buy') {
                 //??
                // $seller = Seller::find($legal_deal->seller_id);
                // if ($user_id != $seller->user_id) {
                //     DB::rollback();
                //     return $this->error('I'm Sorry，You Are Not Authorized To Operate');
                // }
                $seller = Users::find($legal_deal->seller_id);
                if ($user_id != $seller->id) {
                    DB::rollback();
                    return $this->error('Im Sorry，You Are Not Authorized To Operate');
                }

            }
            $legal_deal->is_sure = 3;
            $legal_deal->save();
            DB::commit();
            return $this->success('Operation Successful，Please Contact The Seller For Confirmation');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * User Cancels Order
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealCancel(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $legal_deal = C2cDeal::find($id);
        if (empty($legal_deal)) {
            return $this->error('No Such Record');
        }
        DB::beginTransaction();
        try {
            if ($legal_deal->is_sure > 0) {
                DB::rollback();
                return $this->error('The Order Has Been Processed，Please Do Not Cancel');
            }
            $user_id = Users::getUserId();
            if ($legal_deal->type == 'sell') { //Client-Purchase
                if ($user_id != $legal_deal->user_id) {
                    DB::rollback();
                    return $this->error('Im Sorry，You Are Not Authorized To Operate');
                }
            } elseif ($legal_deal->type == 'buy') {
                //Client Selling
                // $seller = Seller::find($legal_deal->seller_id);
                // if ($user_id == $legal_deal->user_id) {
                //     DB::rollback();
                //     return $this->error('I'm Sorry，You Are Not Authorized To Operate');
                // }

                if ($user_id != $legal_deal->seller_id) {
                    DB::rollback();
                    return $this->error('Im Sorry，You Are Not Authorized To Operate');
                }
            }
            C2cDeal::cancelLegalDealById($id);
            DB::commit();
            return $this->success('Operation Successful，Order Cancelled');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    public function legalDealSellerList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');
        $legal_send = C2cDealSend::find($id);
        if (empty($legal_send)) {
            return $this->error('Parameter Error2');
        }
        //$seller = Users::find($legal_send->seller_id);
        $user_id = Users::getUserId();
        if ($user_id != $legal_send->seller_id) {
            return $this->error('Im Sorry，This Is Not Your Release');
        }
        // $seller = Seller::find($legal_send->seller_id);
        // if (empty($seller->is_myseller)) {
        //     return $this->error('Im Sorry，You Are Not The Merchant');
        // }
        $results = C2cDeal::where('legal_deal_send_id', $id)
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->pageDate($results);
    }

    //Confirmation By Publisher
    public function doSure(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');
        DB::beginTransaction();
        try {
            $legal_deal = C2cDeal::find($id);
            if (empty($legal_deal)) {
                DB::rollback();
                return $this->error('No Such Record');
            }
            if ($legal_deal->is_sure != 3) {
                DB::rollback();
                return $this->error('The Order Has Not Been Paid Or Has Been Operated');
            }
            // $seller = Seller::find($legal_deal->seller_id);
            // if (empty($seller->is_myseller)) {
            //     DB::rollback();
            //     return $this->error('I'm Sorry，You Are Not Authorized To Operate');
            // }
            $user_id = Users::getUserId();
            $user = Users::find($user_id);
            if ($user_id != $legal_deal->seller_id) {
                DB::rollback();
                return $this->error('Im Sorry，You Are Not Authorized To Operate');
            }

            $legal_send = C2cDealSend::find($legal_deal->legal_deal_send_id);
            if (empty($legal_send)) {
                DB::rollback();
                return $this->error('Order Exception');
            }
            if ($legal_send->type == 'buy') {
                DB::rollback();
                return $this->error('You Cannot Confirm The Order');
            }
            $user_wallet = UsersWallet::where('user_id', $legal_deal->user_id)->where('currency', $legal_send->currency_id)->first();
            if (empty($user_wallet)) {
                DB::rollback();
                return $this->error('The User Does Not Have This Currency');
            }
            $from_wallet = UsersWallet::where('user_id', $legal_deal->seller_id)->where('currency', $legal_send->currency_id)->first();
            if (empty($from_wallet)) {
                DB::rollback();
                return $this->error('The User Does Not Have This Currency');
            }

            $data_wallet1 = [
                'balance_type' => 2,
                'wallet_id' => $from_wallet->id,
                'lock_type' => 1,
                'create_time' => time(),
                'before' => $from_wallet->lock_legal_balance,
                'change' => -$legal_deal->number,
                'after' => bc_sub($from_wallet->lock_legal_balance, $legal_deal->number, 5),
            ];
            $data_wallet2 = [
                'balance_type' => 2,
                'wallet_id' => $user_wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $user_wallet->legal_balance,
                'change' => $legal_deal->number,
                'after' => bc_add($user_wallet->legal_balance, $legal_deal->number, 5),
            ];
            //Update Transaction Status
            $legal_deal->is_sure = 1;
            $legal_deal->update_time = time();

            //Reduce The Amount Of Legal Money Locked By Businesses
            //$seller->lock_seller_balance = bc_sub($seller->lock_seller_balance,$legal_deal->number,5);
            $from_wallet->lock_legal_balance = bc_sub($from_wallet->lock_legal_balance, $legal_deal->number, 5);
            //Increase User's Legal Currency Balance
            $user_wallet->legal_balance = bc_add($user_wallet->legal_balance, $legal_deal->number, 5);
            //Journal
            AccountLog::insertLog(
                [
                    'user_id' => $from_wallet->user_id,
                    'value' => $legal_deal->number * (-1),
                    'info' => 'Successful Sale Of Legal Coins,Deduction Of Locked Balance',
                    'type' => AccountLog::C2C_USER_BUY,
                    'currency' => $legal_send->currency_id
                ],
                $data_wallet1
            );
            AccountLog::insertLog(
                [
                    'user_id' => $user_wallet->user_id,
                    'value' => $legal_deal->number,
                    'info' => 'Stay ' . $user->account_number . ' Successful Purchase Of Legal Currency，Increase The Balance Of Legal Currency',
                    'type' => AccountLog::C2C_USER_BUY,
                    'currency' => $legal_send->currency_id
                ],
                $data_wallet2
            );

            $legal_deal->save();
            //$seller->save();
            $from_wallet->save();
            $user_wallet->save();
            DB::commit();
            return $this->success('Confirm Success');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    //User Confirmation 
    public function userDoSure(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');
        DB::beginTransaction();
        try {
            $legal_deal = C2cDeal::find($id);
            if (empty($legal_deal)) {
                DB::rollback();
                return $this->error('No Such Record');
            }
            if ($legal_deal->is_sure != 3) {
                DB::rollback();
                return $this->error('The Order Has Not Been Paid Or Has Been Operated');
            }
            $user_id = Users::getUserId();
            $user = Users::find($user_id);
            if ($legal_deal->user_id != $user_id) {
                DB::rollback();
                return $this->error('Im Sorry，You Are Not Authorized To Operate');
            }
            $legal_send = C2cDealSend::find($legal_deal->legal_deal_send_id);
            if (empty($legal_send)) {
                DB::rollback();
                return $this->error('Order Exception');
            }
            if ($legal_send->type == 'sell') {
                DB::rollback();
                return $this->error('You Cannot Confirm The Order');
            }
            $user_wallet = UsersWallet::where('user_id', $legal_deal->user_id)
                ->where('currency', $legal_send->currency_id)
                ->first();
            if (empty($user_wallet)) {
                DB::rollback();
                return $this->error('The User Does Not Have This Currency');
            }
            // $seller = Seller::find($legal_deal->seller_id);
            // if (empty($seller)) {
            //     DB::rollback();
            //     return $this->error('The Merchant Does Not Exist');
            // }
            $seller = Users::find($legal_deal->seller_id);
            $seller_wallet = UsersWallet::where('user_id', $legal_deal->seller_id)
                ->where('currency', $legal_send->currency_id)
                ->first();
            if (empty($seller_wallet)) {
                DB::rollback();
                return $this->error('The Buyer Does Not Have A Wallet In This Currency');
            }

            $data_wallet1 = [
                'balance_type' => 2,
                'wallet_id' => $user_wallet->id,
                'lock_type' => 1,
                'create_time' => time(),
                'before' => $user_wallet->lock_legal_balance,
                'change' => -$legal_deal->number,
                'after' => bc_sub($user_wallet->lock_legal_balance, $legal_deal->number, 5),
            ];
            $data_wallet2 = [
                'balance_type' => 2,
                'wallet_id' => $seller_wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $seller_wallet->legal_balance,
                'change' => $legal_deal->number,
                'after' => bc_add($seller_wallet->legal_balance, $legal_deal->number, 5),
            ];
            //Update Transaction Status
            $legal_deal->is_sure = 1;
            $legal_deal->update_time = time();
            //Reduce Users' Legal Currency Lock Balance
//            $user_wallet->lock_legal_balance -= $legal_deal->number;
            $user_wallet->lock_legal_balance = bc_sub($user_wallet->lock_legal_balance, $legal_deal->number, 5);

            //Increase The Balance Of Business Legal Currency
//            $seller->seller_balance += $legal_deal->number;
            $seller_wallet->legal_balance = bc_add($seller_wallet->legal_balance, $legal_deal->number, 5);
            //Journal
            AccountLog::insertLog(
                [
                    'user_id' => $user->id,
                    'value' => -$legal_deal->number,
                    'info' => $user->account_number . 'Successful Sale Of French Currency',
                    'type' => AccountLog::LEGAL_SELLER_BUY,
                    'currency' => $legal_send->currency_id
                ],
                $data_wallet1
            );
            AccountLog::insertLog(
                [
                    'user_id' => $seller->id,
                    'value' => $legal_deal->number,
                    'info' => $seller->account_number . ' Successful Purchase Of Legal Currency',
                    'type' => AccountLog::LEGAL_SELLER_BUY,
                    'currency' => $legal_send->currency_id
                ],
                $data_wallet2
            );

            $legal_deal->save();
            $seller_wallet->save();
            $user_wallet->save();
            DB::commit();
            return $this->success('Confirm Success');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    //Revoke C2cDealSend
    public function backSend(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');
        DB::beginTransaction();
        try {
            $legal_send = C2cDealSend::lockForUpdate()->find($id);
            if (empty($legal_send)) {
                DB::rollback();
                return $this->error('No Such Record');
            }
            $is_deal = C2cDeal::where('legal_deal_send_id', $id)->where('is_sure', '!=', 2)->first();
            if (!empty($is_deal)) {
                DB::rollback();
                return $this->error('The Published Information Is In The Process Of Transaction And Cannot Be Revoked');
            }
            $user_id = Users::getUserId();
            // $seller = Seller::where('user_id', $user_id)->where('currency_id', $legal_send->currency_id)->first();
            // if (empty($seller)) {
            //     DB::rollback();
            //     return $this->error('I'm Sorry，You Are Not The Merchant Of The Coin');
            // }
            if ($user_id != $legal_send->seller_id) {
                DB::rollback();
                return $this->error('Im Sorry，You Have No Right To Revoke This Record');
            }

            if ($legal_send->type == 'sell') {
                $wallet = UsersWallet::where('user_id', $user_id)
                    ->lockForUpdate()
                    ->where('currency', $legal_send->currency_id)
                    ->first();
                if (empty($wallet)) {
                    DB::rollback();
                    return $this->error('User Wallet Does Not Exist');

                }
                if ($wallet->lock_legal_balance < $legal_send->total_number) {
                    DB::rollback();
                    return $this->error('Im Sorry，There Is Not Enough Frozen Funds In Your Account');
                }
                //Frozen Return Balance
                $result = change_wallet_balance($wallet, 1, -$legal_send->total_number, AccountLog::C2C_DEAL_BACK_SEND_SELL, 'Seller WithdrawsC2CIssue Legal Money For Sale,Frozen Funds Reduced', true);
                if ($result !== true) {
                    throw new \Exception($result);
                }
                $result = change_wallet_balance($wallet, 1, $legal_send->total_number, AccountLog::C2C_DEAL_BACK_SEND_SELL, 'Seller WithdrawsC2CIssue Legal Money For Sale,Balance Returned From Frozen Funds');
                if ($result !== true) {
                    throw new \Exception($result);
                }
                //Refund Of Service Charge
                if (bc_comp($legal_send->out_fee, '0') > 0) {
                    $result = change_wallet_balance($wallet, 1, $legal_send->out_fee, AccountLog::C2C_CANCEL_TRADE_FEE, 'Seller WithdrawsC2CIssue Legal Money For Sale,Refund Of Service Charge');
                    if ($result !== true) {
                        throw new \Exception($result);
                    }
                }
            }

            $legal_send->delete();
            C2cDeal::where('legal_deal_send_id', $id)->delete();

            DB::commit();
            return $this->success('Withdrawal Successful');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }
}
