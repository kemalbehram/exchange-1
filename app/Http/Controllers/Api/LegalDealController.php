<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\{AccountLog, Currency, LegalDeal, LegalDealSend, Seller, Users, UsersWallet, UserReal, UserCashInfo, Setting, SellerAccountLog};

class LegalDealController extends Controller
{
    /**
     * Merchants Release Legal Currency Transaction Information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postSend(Request $request)
    {
        $type = $request->input('type', null);
        $way = $request->input('way', null);
        $price = $request->input('price', null);
        $total_number = $request->input('total_number', null);
        $min_number = $request->input('min_number', null);
        $currency_id = $request->input('currency_id', null);
        $max_number = $request->input('max_number', $total_number);
        $coin_code = strtoupper($request->input('coin_code', ''));
        $pay_ways = $request->input('pay_ways', []); //Payment Method Multiple Choice
        if (empty($coin_code) || !in_array($coin_code, ['CNY', 'USD', 'JPY'])) {
            return $this->error('Invalid Currency Code');
        }
        if (empty($type)) return $this->error('Please Select Requirement Type');
        if (empty($price)) return $this->error('Please Fill In The Unit Price');
        if (empty($total_number)) return $this->error('Please Fill In The Quantity');
        if (empty($min_number)) return $this->error('Please Fill In The Minimum Transaction Quantity');
        if (empty($currency_id)) return $this->error('Please Select Currency');
        if ($min_number > $total_number) return $this->error('The Minimum Transaction Quantity Cannot Be Greater Than The Total Quantity');

        if (empty($max_number)) {
            return $this->error('Please Fill In The Maximum Trading Volume');
        }
        if ($max_number > $total_number || $max_number <= 0 || !is_numeric($max_number)) {
            return $this->error('Please Fill In The Correct Maximum Trading Volume');
        }
        try {
            DB::BeginTransaction();
            $user_id = Users::getUserId();
            $seller = Seller::lockForUpdate()
                ->where('user_id', $user_id)
                ->where('currency_id', $currency_id)
                ->first();
            if (empty($seller)) {
                throw new \Exception('Im Sorry，You Are Not The Merchant Of The Coin');
            }
            $fee = 0;
            if ($type == 'sell') {   //If The Merchant Publishes The Sale Information
                $legal_sell_fee = Setting::getValueByKey('legal_sell_fee', 0);
                $legal_sell_fee = bc_div($legal_sell_fee, 100);
                $fee = bc_mul($total_number, $legal_sell_fee);
                $should_deduct_number = bc_add($total_number, $fee);
                if ($seller->seller_balance < $should_deduct_number) {
                    throw new \Exception('Im Sorry，Your Merchant Account Is Insufficient');
                }
                change_seller_balance(
                    $seller,
                    -$total_number,
                    AccountLog::LEGAL_DEAL_SEND_SELL,
                    "Legal Currency Transaction:Decrease In Sales Of Legal Coins Issued By Merchants"
                );
                change_seller_balance(
                    $seller,
                    $total_number,
                    AccountLog::LEGAL_DEAL_SEND_SELL,
                    "Legal Currency Transaction:Merchants Issue Legal Coins For Sale,Freeze Increase",
                    true
                );
                if (bc_comp_zero($seller, 0) > 0) {
                    change_seller_balance(
                        $seller,
                        -$fee,
                        AccountLog::LEGAL_TRADE_FREE,
                        "Legal Currency Transaction:Merchants Issue Legal Coins For Sale,Deduction Of Handling Charge"
                    );
                }
            }
            $legal_deal_send_data = [
                'seller_id' => $seller->id,
                'currency_id' => $currency_id,
                'type' => $type,
                'way' => $way,
                'price' => $price,
                'total_number' => $total_number,
                'surplus_number' => $total_number,
                'min_number' => $min_number,
                'max_number' => $max_number,
                'coin_code' => $coin_code,
                'out_fee' => $fee,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $legal_send = LegalDealSend::unguarded(function () use ($legal_deal_send_data) {
                return LegalDealSend::create($legal_deal_send_data);
            });
            throw_unless(isset($legal_send->id), new \Exception('Publishing Failed'));
            DB::commit();
            return $this->success('Successfully Published');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Merchant Details
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sellerInfo(Request $request)
    {
        $id = $request->input('id', null);
        $type = $request->input('type', null);
        $was_done = $request->get('was_done', null);
        $is_done = $request->input('is_done', -1);
        $limit = $request->input('limit', 10);

        if (empty($id)) return $this->error('Parameter Error');
        $seller = Seller::find($id);
        if (empty($seller)) return $this->error('No Such Business');
        $beforeThirtyDays = Carbon::today()->subDay(30)->timestamp;   //30Days Ago
        $results = Seller::withCount(['legalDeal as total', 'legalDeal as done' => function ($query) {
            $query->where('is_sure', 1);
        }, 'legalDeal as thirtyDays' => function ($query) use ($beforeThirtyDays) {
            $query->where('is_sure', 1)->where('update_time', '>=', $beforeThirtyDays);
        }])->find($id);

        $lists = LegalDealSend::where('seller_id', $id);
        if ($is_done > -1) {
            $lists = $lists->where('is_done', $is_done);
        } else {
            //Is It Complete
            if ($was_done == 'true') {
                $lists = $lists->where('is_done', 1);
            } elseif ($was_done == 'false') {
                $lists = $lists->where('is_done', 0);
            }
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
        //Add User Balance
        $wallet = UsersWallet::where('currency', $seller->currency_id)->where('user_id', $seller->user_id)->first();
        $results->user_legal_balance = $wallet->legal_balance ?? 0;
        return $this->success($results);
    }

    public function tradeList(Request $request)
    {
        $type = $request->input('type', null);
        $is_done = $request->input('is_done', -1);
        $seller_id = $request->input('id', 0);
        $limit = $request->input('limit', 10);

        $user_id = Users::getUserId();
        $seller = Seller::where('user_id', $user_id)
            ->where('id', $seller_id)
            ->first();

        if (empty($seller)) {
            return $this->error('You Are Not A Business');
        }
        $lists = LegalDealSend::where('seller_id', $seller->id);
        //Is It Complete
        if ($is_done > -1) {
            $lists = $lists->where('is_done', $is_done);
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
        $result = array('data' => $lists->items(), 'page' => $lists->currentPage(), 'pages' => $lists->lastPage(), 'total' => $lists->total());
        return $this->success($result);
    }


    /**
     * Merchant Releases Legal Currency Transaction Information List
     *
     * @param Request $request
     *
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

        $results = LegalDealSend::where('currency_id', $currency_id)
            ->where('is_shelves', 1)
            ->where('is_done', 0)
            ->where('type', $type)
            ->where('surplus_number', '>', 0)
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->pageDate($results);
    }

    /**
     * Legal Currency Transaction Details
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function legalDealSendInfo(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $legal_deal_send = LegalDealSend::find($id);

        $userWallet = UsersWallet::where('currency', $legal_deal_send->currency_id)
            ->where('user_id', Users::getUserId())->first();

        //Add The User's Balance
        $legal_deal_send->user_legal_balance = $userWallet->legal_balance;

        if (empty($legal_deal_send)) return $this->error('No Such Record');
        return $this->success($legal_deal_send);
    }

    /**
     * Coin Trading Button
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function doDeal(Request $request)
    {
        $deal_send_id = $request->input('id', null);
        $value = $request->input('value', 0);
        $means = $request->input('means', '');
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

        if (!in_array($means, ['number', 'money'])) {
            return $this->error('Purchase Parameter Error');
        }

        if (empty($value)) {
            return $this->error('Please Fill In The Purchase Amount');
        }
        if (!is_numeric($value)) {
            return $this->error('Please Fill In The Figure For The Purchase Amount');
        }

        //Limit The Number Of Uncompleted Singular To3Single
        $is_morethan = LegalDeal::where("user_id", "=", $user_id)->whereIn('is_sure', [0, 3])->count();
        if ($is_morethan >= 3) {
            return $this->error('Incomplete List More Than3Single，Please Complete The Operation!');
        }
        try {
            DB::beginTransaction();
            $fee = 0;
            $legal_deal_send = LegalDealSend::lockForUpdate()->find($deal_send_id);
            if (empty($legal_deal_send)) {
                throw new \Exception('No Such Record');
            }
            if ($legal_deal_send->is_shelves != 1 || $legal_deal_send->is_done != 0) {
                throw new \Exception('Abnormal Status Of Business Registration,It Cant Be Traded For The Time Being');
            }
            if (bc_comp_zero($legal_deal_send->surplus_number) <= 0) {
                throw new \Exception('The Remaining Number Of Business Orders Can Be Traded Is Insufficient');
            }
            if ($means == 'money') {
                $number = bc_div($value, $legal_deal_send->price, 5);
            } else {
                $number = $value;
            }
            if ($number <= 0) {
                throw new \Exception('Illegal Submission，Quantity Must Be Greater Than0');
            }
            $money = bc_mul($number, $legal_deal_send->price, 5);
            if (bc_comp($legal_deal_send->surplus_number, $legal_deal_send->min_number) >= 0) {
                // If The Remaining Quantity Is Less Than The Minimum Transaction Amount, There Is No Limit
                if (bc_comp($money, $legal_deal_send->limitation['min']) < 0) {
                    throw new \Exception('You Are Below The Minimum');
                }
            } else {
                $min_money =  bc_mul($legal_deal_send->surplus_number, $legal_deal_send->price, 5);
                if ($money < $min_money) {
                    throw new \Exception('You Do Not Meet The Minimum Requirement(Last Remaining Quantity*Price)');
                }
            }
            if ($money > $legal_deal_send->limitation['max']) {
                throw new \Exception('You Are Above The Maximum Limit');
            }
            if ($number > $legal_deal_send->max_number) {
                throw new \Exception('You Are Above The Maximum Limit');
            }
            $seller = Seller::find($legal_deal_send->seller_id);
            if (empty($seller)) {
                throw new \Exception('The Merchant Was Not Found');
            }

            if ($user_id == $seller->user_id) {
                throw new \Exception('You Cant Trade With Yourself');
            }
            $users_wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $legal_deal_send->currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($users_wallet)) {
                throw new \Exception('You Dont Have This Wallet Account');
            }
            if (!empty($users_wallet->status)) {
                throw new \Exception('Your Wallet Is Locked，Please Contact The Administrator');
            }

            //Check Whether The Purchase Quantity Is Greater Than The Remaining Balance
            if ($number > $legal_deal_send->surplus_number) {
                throw new \Exception('Your Transaction Quantity Is Greater Than The Remaining Quantity Released By The Merchant!');
            }
            if ($legal_deal_send->type == 'buy') {
                // Shop For,User Selling
                // $hasNonDone = LegalDeal::where('user_id', $user_id)
                //     ->whereIn('is_sure', [0, 3])
                //     ->first();
                // if($hasNonDone) {
                //     throw new \Exception('Detect Incomplete Transactions，Please Come Back When You Are Finished！');
                // }
                // The Seller Charges A Service Charge
                $legal_sell_fee = Setting::getValueByKey('legal_sell_fee', 0);
                $legal_sell_fee = bc_div($legal_sell_fee, 100);
                $fee = bc_mul($number, $legal_sell_fee);
                $should_deduct_number = bc_add($number, $fee);
                if ($users_wallet->legal_balance < $should_deduct_number) {
                    throw new \Exception('Your Balance Of Legal Currency Is Insufficient');
                }
                if ($users_wallet->lock_legal_balance < 0) {
                    throw new \Exception('Your Legal Currency Freezing Fund Is Abnormal,Please Check If You Have Any Registration In Progress');
                }
                change_wallet_balance(
                    $users_wallet,
                    1,
                    -$number,
                    AccountLog::LEGAL_DEAL_USER_SELL,
                    'Legal Currency Transaction:Legal Money Sold To Merchants:Deduction Balance'
                );
                change_wallet_balance(
                    $users_wallet,
                    1,
                    $number,
                    AccountLog::LEGAL_DEAL_USER_SELL,
                    'Legal Currency Transaction:Legal Money Sold To Merchants:Increase Freeze',
                    true
                );
                //Deduction Of Handling Charge
                if (bc_comp($fee, '0') > 0) {
                    change_wallet_balance(
                        $users_wallet,
                        1,
                        -$fee,
                        AccountLog::LEGAL_TRADE_FREE,
                        'Legal Currency Transaction:Legal Money Sold To Merchants,Deduction Of Handling Charge'
                    );
                }
            }
            // This Is Only Off The Shelf Trading,Because The Number Of Transactions Published By Businesses Is Only Occupied By The Matched Users，It's Not All Done
            $legal_deal_send->surplus_number = bc_sub($legal_deal_send->surplus_number, $number, 8);
            // $legal_deal_send->surplus_number <= 0 &&  $legal_deal_send->is_shelves = 2; // Controversial
            $legal_deal_send->save();
            $legal_deal = new LegalDeal();
            $legal_deal->legal_deal_send_id = $deal_send_id;
            $legal_deal->user_id = $user_id;
            $legal_deal->seller_id = $seller->id;
            $legal_deal->number = $number; //Number Of Transactions
            $legal_deal->out_fee = $fee;
            $legal_deal->create_time = time();
            $legal_deal->update_time = time();
            $legal_deal->save();
            DB::commit();
            return $this->success([
                'msg' => 'Operation Successful，Please Contact The Merchant To Confirm The Order',
                'data' => $legal_deal,
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage() . ',Error On Page' . $exception->getLine() . 'Thats Ok');
        }
    }

    /**
     * Merchant Side List Of Legal Currency Transaction
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sellerLegalDealList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $type = $request->input('type', 'sell');
        $currency_id = $request->input('currency_id', '');

        if (empty($currency_id)) {
            return $this->error('Parameter Error');
        }
        if (empty($type)) {
            return $this->error('Parameter Error2');
        }
        $currency = Currency::find($currency_id);
        if (empty($currency)) {
            return $this->error('No Such Currency');
        }
        if (empty($currency->is_legal)) {
            return $this->error('The Currency Is Not Legal');
        }
        $user_id = Users::getUserId();
        $seller = Seller::where('user_id', $user_id)->where('currency_id', $currency_id)->first();
        if (empty($seller)) {
            return $this->error('You Are Not A Merchant Of This Currency');
        }
        $results = LegalDeal::where('seller_id', $seller->id)
            ->whereHas('legalDealSend', function ($query) use ($type) {
                $query->where('type', $type);
            })->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->pageDate($results);
    }

    /**
     * Client List Of Legal Currency Transaction
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $type = $request->input('type', null);
        $currency_id = $request->input('currency_id', '');
        $is_sure = $request->input('is_sure', null); //0Unconfirmed 1Confirmed 2Cancelled 3Paid
        if (!empty($currency_id)) {
            $currency = Currency::find($currency_id);
            if (empty($currency)) return $this->error('No Such Currency');
            if (empty($currency->is_legal)) return $this->error('The Currency Is Not Legal');
        }
        $user_id = Users::getUserId();
        $results = LegalDeal::where('user_id', $user_id);
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
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function legalDealInfo(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $legal_deal = LegalDeal::find($id);
        if (empty($legal_deal)) {
            return $this->error('No Such Record');
        }
        return $this->success($legal_deal);
    }

    /**
     * User Confirms Payment
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealPay(Request $request)
    {
        $id = $request->input('id', null);

        DB::beginTransaction();
        try {
            if (empty($id)) return $this->error('Parameter Error');
            $legal_deal = LegalDeal::lockForUpdate()->find($id);
            if (empty($legal_deal)) {
                throw new \Exception('No Such Record');
            }
            if ($legal_deal->is_sure > 0) {
                throw new \Exception('The Order Has Been Operated，Do Not Repeat');
            }
            $user_id = Users::getUserId();
            if ($legal_deal->type == 'sell') { //Client-Purchase
                if ($user_id != $legal_deal->user_id) {
                    throw new \Exception('Im Sorry，You Are Not Authorized To Operate');
                }
            } elseif ($legal_deal->type == 'buy') {
                $seller = Seller::find($legal_deal->seller_id);
                if ($user_id != $seller->user_id) {
                    throw new \Exception('Im Sorry，You Are Not Authorized To Operate');
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
     * Cancel The Deal(Limited To Buyers,Non Payment Will Be Cancelled Automatically)
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLegalDealCancel(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }

        try {
            DB::beginTransaction();
            $legal_deal = LegalDeal::lockForUpdate()->find($id);
            if (empty($legal_deal)) {
                return $this->error('No Such Record');
            }
            if ($legal_deal->is_sure > 0) {
                throw new \Exception('The Order Has Been Processed，Please Do Not Cancel');
            }
            $user_id = Users::getUserId();
            if ($legal_deal->type == 'sell') {
                //Client-Purchase
                if ($user_id != $legal_deal->user_id) {
                    throw new \Exception('Im Sorry，You Are Not Authorized To Operate');
                }
            } elseif ($legal_deal->type == 'buy') {
                //Client Selling
                $seller = Seller::find($legal_deal->seller_id);
                if ($user_id == $legal_deal->user_id) {
                    throw new \Exception('Im Sorry，You Are Not Authorized To Operate');
                }
            }
            LegalDeal::cancelLegalDealById($id);
            DB::commit();
            return $this->success('Operation Successful，Order Cancelled');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    public function mySellerList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $user_id = Users::getUserId();
        $user = Users::find($user_id);
        if (empty($user->is_seller)) {
            return $this->error('Sorry, You Are Not A Businessman');
        }
        $results = Seller::where('user_id', $user_id)->orderBy('id', 'desc')->paginate($limit);
        // foreach ($results->items() as &$value) {
        //     $wallet = UsersWallet::where('currency', $value->currency_id)->where('user_id', $value->user_id)->first();
        //     $value->user_legal_balance = $wallet->legal_balance ?? 0;
        // }

        return $this->pageDate($results);
    }

    public function legalDealSellerList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $id = $request->input('id', null);
        $is_sure = $request->input('is_sure', null);
        if (empty($id)) return $this->error('Parameter Error');
        $legal_send = LegalDealSend::find($id);
        if (empty($legal_send)) {
            return $this->error('Parameter Error2');
        }
        $seller = Seller::find($legal_send->seller_id);
        if (empty($seller->is_myseller)) {
            return $this->error('Im Sorry，You Are Not The Merchant');
        }
        if (!is_null($is_sure)) {
            $results = LegalDeal::where('legal_deal_send_id', $id)
                ->where('is_sure', $is_sure)
                ->orderBy('id', 'desc')
                ->paginate($limit);
        } else {
            $results = LegalDeal::where('legal_deal_send_id', $id)
                ->orderBy('id', 'desc')
                ->paginate($limit);
        }
        return $this->pageDate($results);
    }

    /**
     * Merchant Confirms Transaction
     *
     * @param Request $request
     * @return void
     */
    public function doSure(Request $request)
    {
        $id = $request->input('id', null);
        $user_id = Users::getUserId();
        try {
            DB::beginTransaction();
            throw_if(empty($id), new \Exception('Parameter Error'));
            $legal_deal = LegalDeal::lockForUpdate()->findOrFail($id);
            throw_if($legal_deal->seller->user_id != $user_id, new \Exception('Im Sorry，You Are Not Authorized To Operate'));
            throw_if($legal_deal->is_sure != 3, new \Exception('The Order Has Not Been Paid Or Has Been Operated'));
            $legal_send = LegalDealSend::findOrFail($legal_deal->legal_deal_send_id);
            throw_if($legal_send->type == 'buy', new \Exception('You Are Not Authorized To Confirm The Order'));
            LegalDeal::confirmLegalDealById($id);
            DB::commit();
            return $this->success('Confirm Success');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            DB::rollBack();
            return $this->error($ex->getModel() . "Data Not Found");
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * User Confirms Transaction
     *
     * @param Request $request
     * @return void
     */
    public function userDoSure(Request $request)
    {
        $id = $request->input('id', null);
        $user_id = Users::getUserId();
        try {
            DB::beginTransaction();
            throw_if(empty($id), new \Exception('Parameter Error'));
            $legal_deal = LegalDeal::lockForUpdate()->findOrFail($id);
            throw_if($legal_deal->user_id != $user_id, new \Exception('Im Sorry，You Are Not Authorized To Operate'));
            throw_if($legal_deal->is_sure != 3, new \Exception('The Order Has Not Been Paid Or Has Been Operated'));
            $legal_send = LegalDealSend::findOrFail($legal_deal->legal_deal_send_id);
            throw_if($legal_send->type == 'sell', new \Exception('You Are Not Authorized To Confirm The Order'));
            Seller::lockForUpdate()->findOrFail($legal_deal->seller_id);
            LegalDeal::confirmLegalDealById($id);
            DB::commit();
            return $this->success('Confirm Success');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            DB::rollBack();
            return $this->error($ex->getModel . "Data Not Found");
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Mark Trade Off Shelves
     *
     */
    public function down(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) return $this->error('Parameter Error');

        try {
            DB::beginTransaction();

            $legal_send = LegalDealSend::lockForUpdate()->find($id);

            if (empty($legal_send)) {
                throw new \Exception('No Such Record');
            }
            if ($legal_send->is_done != 0 || $legal_send->is_shelves != 1) {
                throw new \Exception('Unable To Get Off The Shelf In This State');
            }
            $user_id = Users::getUserId();
            $seller = Seller::where('user_id', $user_id)
                ->where('currency_id', $legal_send->currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($seller)) {
                throw new \Exception('Im Sorry，You Are Not The Merchant Of The Coin');
            }
            $legal_send->is_shelves = 2;
            $legal_send->save();
            DB::commit();
            return $this->success('Successfully Released,Will No Longer Match New Users');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Merchant Withdraws Release
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function backSend(Request $request)
    {
        $id = $request->input('id', null);
        try {
            DB::transaction(function () use ($id) {
                $user_id = Users::getUserId();
                throw_if(empty($id), new \Exception('Parameter Error'));
                $legal_send = LegalDealSend::lockForUpdate()->find($id);
                $seller = $legal_send->seller;
                throw_if($seller->user_id != $user_id, new \Exception('Im Sorry，You Are Not The Merchant Of The Coin'));
                LegalDealSend::sendBack($id, 2);
            });
            return $this->success('Withdrawal Successful');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Exception Release Recall
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse 
     * @deprecated 20191201 This Function Has Been Replaced By The Normal Recall Function
     */
    public function errorSend(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        return $this->error('This Function Is Not Available,Please Withdraw The Release After The Shelf');
        try {
            DB::beginTransaction();
            $legal_send = LegalDealSend::lockForUpdate()->find($id);
            if (empty($legal_send)) {
                throw new \Exception('No Such Record');
            }
            if (LegalDealSend::isHasIncompleteness($id)) {
                throw new \Exception('There Are Unfinished Transactions Under The Release Information，Cannot Be Marked As Exception');
            }
            if (bc_comp($legal_send->surplus_number, $legal_send->min_number) >= 0) {
                throw new \Exception('The Published Information Is Normal');
            }
            if (bc_comp($legal_send->surplus_number, 0) <= 0) {
                throw new \Exception('There Is Not Enough Quantity Left In The Release,Illegal Withdrawal');
            }
            $user_id = Users::getUserId();
            $seller = Seller::where('user_id', $user_id)
                ->where('currency_id', $legal_send->currency_id)
                ->lockForUpdate()
                ->first();
            if (empty($seller)) {
                throw new \Exception('Im Sorry，You Are Not The Merchant Of The Coin');
            }
            if ($legal_send->type == 'sell') {
                // If The Merchant Publishes The Sale Information
                if (bc_comp($seller->lock_seller_balance, $legal_send->surplus_number) < 0) {
                    throw new \Exception('Im Sorry，Your Merchant Account Is Short Of Frozen Funds');
                }
                change_seller_balance(
                    $seller,
                    -$legal_send->surplus_number,
                    AccountLog::LEGAL_DEAL_ERROR_SEND_SELL,
                    "Legal Currency Transaction:Merchant Handling Exception Publishing,Reduce Freezing",
                    true
                );
                change_seller_balance(
                    $seller,
                    $legal_send->surplus_number,
                    AccountLog::LEGAL_DEAL_ERROR_SEND_SELL,
                    "Legal Currency Transaction:Merchant Handling Exception Publishing,Return Balance",
                    true
                );
            }
            $legal_send->is_done = 2;
            $legal_send->save();
            DB::commit();
            return $this->success('Successfully Processed');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Submit For Rights Protection
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitArbitrate(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $user_id = Users::getUserId();
                $id = $request->input('id', 0);
                $legal_deal = LegalDeal::lockForUpdate()->findOrFail($id);
                throw_if($legal_deal->is_sure != 3, new \Exception('You Cannot Apply For Rights Protection In The Current Status'));
                // Only The Seller Can Apply For Rights Protection
                if ($legal_deal->type == 'sell') {
                    // The Seller Is The Merchant
                    $sell_user_id = $legal_deal->seller->user_id;
                    $arbitrated_from = 2;
                } else {
                    // The Seller Is The User
                    $sell_user_id = $legal_deal->user_id;
                    $arbitrated_from = 1;
                }
                throw_if($user_id != $sell_user_id, new \Exception('At Present, Only The Seller Can Protect His Rights'));
                $legal_deal->is_sure = 4;
                $legal_deal->arbitrated_from = $arbitrated_from;
                $legal_deal->save();
            });
            return $this->success('Submitted Successfully,The Transaction Has Been Frozen,Please Wait For Background Processing');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return $this->error('Transaction Information Does Not Exist,Please Refresh And Try Again');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * The Balance Of The Merchant And The Balance Of The User's Legal Currency Are Transferred To Each Other
     * @param Request $request 
     * @return Illuminate\Http\JsonResponse 
     */
    public function transfer(Request $request)
    {
        $user_id = Users::getUserId();
        $seller_id = $request->input("seller_id", 0);
        $number = $request->input("number", 0);
        $type = $request->input("type", 0); //1Business To User  2User To Merchant
        if (empty($user_id) || empty($seller_id) || empty($type)) {
            return $this->error('Parameter Error');
        }
        if ($number <= 0) {
            return $this->error('The Amount Entered Cannot Be Negative');
        }
        if (!in_array($type, [1, 2])) {
            return $this->error('Transfer Type Parameter Error');
        }
        try {
            DB::beginTransaction();
            $seller = Seller::lockForUpdate()->find($seller_id);
            if (!$seller) {
                throw new \Exception('The Business Information Is Wrong');
            }
            if ($seller->user_id != $user_id) {
                throw new \Exception('Unauthorized Operation');
            }
            $user_wallet = UsersWallet::where('user_id', $user_id)
                ->lockForUpdate()
                ->where('currency', $seller->currency_id)
                ->first();
            if (!$user_wallet) {
                throw new \Exception('The Wallet Doesnt Exist');
            }
            if ($type == 1) {
                if ($seller->seller_balance < $number) {
                    throw new \Exception('Insufficient Business Balance');
                }
                $user_number = $number;
                $seller_number = -$number;
                $log_type = AccountLog::SELLER_TRANSFER_USER_BALANCE;

                $memo = 'Transfer Business Balance To User Balance';
            } else if ($type == 2) {
                if ($user_wallet->legal_balance < $number) {
                    throw new \Exception('Insufficient Legal Currency Balance');
                }
                $user_number = -$number;
                $seller_number = $number;
                $log_type = AccountLog::USER_TRANSFER_SELLER_BALANCE;
                $memo = 'User Balance Transferred To Merchant';
            }
            change_wallet_balance(
                $user_wallet,
                1,
                $user_number,
                $log_type,
                $memo
            );
            change_seller_balance(
                $seller,
                $seller_number,
                $log_type,
                $memo
            );
            DB::commit();
            return $this->success('Successful Transfer');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Operation Failed:' . $e->getMessage());
        }
    }

    /**
     * Merchant Balance Log
     * @param Request $request 
     * @return Illuminate\Http\JsonResponse 
     */
    public function balanceLog(Request $request)
    {
        $limit = $request->input('limit', 10);
        $seller_id = $request->input('seller_id', 0);
        $is_lock = $request->input('is_lock', -1);
        $user_id = Users::getUserId();
        $seller = Seller::find($seller_id);
        if (empty($seller) || $seller->user_id != $user_id) {
            return $this->error('The Business Information Is Wrong');
        }
        $list = SellerAccountLog::where('seller_id', $seller_id)
            ->where('user_id', $user_id)
            ->when($is_lock > -1, function ($query) use ($is_lock) {
                $query->where('is_lock', $is_lock);
            })
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->pageDate($list);
    }
}
