<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\{CurrencyMatch, CurrencyQuotation, AccountLog, Currency, Token, Transaction, TransactionComplete, TransactionIn, TransactionInDel, TransactionOut, TransactionOutDel, Users, UsersWallet};

class TransactionController extends Controller
{
    //Buying Record
    public function TransactionInList(Request $request)
    {
        $user_id = Users::getUserId();
        $legal_id = $request->input('legal_id', 0);
        $currency_id = $request->input('currency_id', 0);
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $limit = request()->input('limit', 10);
        $page = request()->input('page', 1);
        $transactionIn = TransactionIn::where('user_id', $user_id)
            ->when($legal_id > 0, function ($query) use ($legal_id) {
                $query->where('legal', $legal_id);
            })
            ->when($currency_id > 0, function ($query) use ($currency_id) {
                $query->where('currency', $currency_id);
            })
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        if (empty($transactionIn)) {
            return $this->error('You Dont Have A Transaction Yet');
        }
        return $this->success(array(
            "list" => $transactionIn->items(), 'count' => $transactionIn->total(),
            "page" => $page, "limit" => $limit
        ));
    }

    //Selling Record
    public function TransactionOutList(Request $request)
    {
        $user_id = Users::getUserId();
        $legal_id = $request->input('legal_id', 0);
        $currency_id = $request->input('currency_id', 0);
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $limit = request()->input('limit', 10);
        $page = request()->input('page', 1);
        $transactionOut = TransactionOut::where('user_id', $user_id)
            ->when($legal_id > 0, function ($query) use ($legal_id) {
                $query->where('legal', $legal_id);
            })
            ->when($currency_id > 0, function ($query) use ($currency_id) {
                $query->where('currency', $currency_id);
            })
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        if (empty($transactionOut)) {
            return $this->error('You Dont Have A Transaction Yet');
        }
        return $this->success(array(
            "list" => $transactionOut->items(), 'count' => $transactionOut->total(),
            "page" => $page, "limit" => $limit
        ));
    }

    //Transaction Completion Record
    public function TransactionCompleteList()
    {
        $user_id = Users::getUserId();
        $limit = request()->input('limit', 10);
        $page = request()->input('page', 1);
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $TransactionComplete = TransactionComplete::where('user_id', $user_id)
            ->orwhere('from_user_id', $user_id)
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        if (empty($TransactionComplete)) {
            return $this->error('You Dont Have A Transaction Yet');
        }
        foreach ($TransactionComplete->items() as $key => &$value) {
            if ($value['user_id'] == $user_id) {
                $value['type'] = 'in';
            } else {
                $value['type'] = 'out';
            }
        }
        return $this->success([
            "list" => $TransactionComplete->items(),
            'count' => $TransactionComplete->total(),
            "page" => $page, "limit" => $limit
        ]);
    }

    //Cancel The Deal
    public function TransactionDel(Request $request)
    {
        $user_id = Users::getUserId();
        $id = $request->input('id', 0);
        $type = $request->input('type', ''); //in Buy Trade outSell Transaction
        try {
            $user = Users::findOrFail($user_id);
            $validator = Validator::make($request->only(['id', 'type']), [
                'id' => 'required|integer|gt:0',
                'type' => 'required|in:in,out',
            ], [], [
                'id' => 'Transactionid',
                'type' => 'Transaction Type',
            ]);
            $transaction_class = $type == 'in' ? TransactionIn::class : TransactionOut::class;
            $transaction_del_class = $type == 'in' ? TransactionInDel::class : TransactionOutDel::class;
            $validator->after(function ($validator) use ($id, $transaction_class, $user) {
                try {
                    $trade = $transaction_class::lockForupdate()->findOrFail($id);
                    throw_if($trade->user_id != $user->id, new \Exception('You Cannot Withdraw Transactions That Are Not Published By Yourself'));
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
                    return $validator->errors()->add('notfound', 'Transaction Not Found,May Have Been Matched Or Withdrawn,Please Refresh And Try Again');
                } catch (\Throwable $th) {
                    return $validator->errors()->add('exception', $th->getMessage());
                }
            });
            throw_if($validator->fails(), new \Exception($validator->errors()->first()));
            DB::transaction(function () use ($user, $id, $type, $transaction_class, $transaction_del_class) {
                $trade = $transaction_class::lockForupdate()->findOrFail($id);
                $currency_match = CurrencyMatch::where('currency_id', $trade->currency)
                    ->where('legal_id', $trade->legal)
                    ->firstOrFail();
                // Return The Original Frozen Quantity
                if ($type == 'in') {
                    $shoud_refund_number = bc_mul($trade->price, $trade->number, 8);
                    $currency_id = $trade->legal; // Purchase Of Returned Legal Currency
                    $type_name = "Hang Up";
                    $currency_name = $currency_match->legal_name;
                } else {
                    $shoud_refund_number = $trade->number;
                    $currency_id = $trade->currency; // Selling Returned Transaction Currency
                    $type_name = "On Sale";
                    $currency_name = $currency_match->currency_name;
                }
                $user_wallet = UsersWallet::where('user_id', $user->id)
                    ->where('currency', $currency_id)
                    ->firstOrFail();
                if (bc_comp($user_wallet->lock_change_balance, $shoud_refund_number) < 0) {
                    throw new \Exception("Withdraw{$type_name}Fail,Insufficient Frozen Balance");
                }
                change_wallet_balance(
                    $user_wallet,
                    2,
                    -$shoud_refund_number,
                    AccountLog::TRANSACTIONIN_IN_DEL,
                    "Currency Transaction:User Cancel{$type_name}{$currency_match->symbol},Unlock{$currency_name},Transaction Number:{$trade->id}",
                    true
                );
                change_wallet_balance(
                    $user_wallet,
                    2,
                    $shoud_refund_number,
                    AccountLog::TRANSACTIONIN_IN_DEL,
                    "Currency Transaction:User Cancel{$type_name}{$currency_match->symbol},Return{$currency_name},Transaction Number:{$trade->id}"
                );
                // Insert The Backup Of The Bill
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
                throw_if(!isset($trade_del->id), new \Exception('Withdrawal Failed:Failed To Record Transaction Information'));
                // Delete The Registration Form
                throw_unless($trade->delete(), new \Exception('Withdrawal Failed:Clearing Transaction Failed'));
            });
            return $this->success('Withdrawal Successful!');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return $validator->errors()->add('notfound', $ex->getModel() . 'Information Not Found,Please Refresh And Try Again');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * On Sale
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function out()
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
    public function in()
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

    public function deal()
    {
        $user_id = Users::getUserId();

        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");

        if (empty($legal_id) || empty($currency_id)) {
            return $this->error("Parameter Error");
        }
        $legal_currency = Currency::find($legal_id);
        $currency_match = CurrencyMatch::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if (!$currency_match) {
            return $this->error('AppointsymbolsYes, It Doesnt Exist');
        }
        $in = TransactionIn::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->where('is_active', 1)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'desc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit(10)
            ->get()
            ->toArray();
        $out = TransactionOut::with(['legalcoin', 'currencycoin'])
            ->where("number", ">", 0)
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->where('is_active', 1)
            ->groupBy('currency', 'legal', 'price')
            ->orderBy('price', 'asc')
            ->select([
                'currency',
                'legal',
                'price',
            ])->selectRaw('sum(`number`) as `number`')
            ->limit(10)
            ->get()
            ->toArray();
        $in = array_map(function ($item) {
            $item['number'] = number_format($item['number'], 4, '.', '');
            $item['price'] = number_format($item['price'], 6, '.', '');
            return $item;
        }, $in);

        $out = array_map(function ($item) {
            $item['number'] = number_format($item['number'], 4, '.', '');
            $item['price'] = number_format($item['price'], 6, '.', '');
            return $item;
        }, $out);

        krsort($out);
        $out_data = array();
        foreach ($out as $o) {
            array_push($out_data, $o);
        }

        $complete = TransactionComplete::orderBy('id', 'desc')
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->take(20)
            ->get();

        $last_price = 0;
        //Take The Latest Price From The Market
        $last = CurrencyQuotation::where('legal_id', $legal_id)
            ->where('currency_id', $currency_id)
            ->first();
        if (!$last) {
            $last = TransactionComplete::orderBy('id', 'desc')
                ->where("currency", $currency_id)
                ->where("legal", $legal_id)
                ->first();
            if (!empty($last)) {
                $last_price = $last->price;
            }
        } else {
            $last && $last_price = $last->now_price;
        }
        $user_legal = 0;
        $user_currency = 0;
        if (!empty($user_id)) {
            $legal = UsersWallet::where("user_id", $user_id)->where("currency", $legal_id)->first();
            if ($legal) {
                $user_legal = $legal->change_balance;
            }
            $currency = UsersWallet::where("user_id", $user_id)->where("currency", $currency_id)->first();
            if ($currency) {
                $user_currency = $currency->change_balance;
            }
        }

        return $this->success([
            "in" => $in,
            "out" => $out_data,
            'legal_currency' => $legal_currency,
            //all_legal"=>$all_legal,
            //"all_currency"=>$all_currency,
            "last_price" => $last_price,
            "user_legal" => $user_legal,
            "user_currency" => $user_currency,
            "complete" => $complete,
            'currency_match' => $currency_match,
        ]);
    }

    public function introduction(Request $request)
    {
        $currency_id = $request->input('currency_id', "");
        if (empty($currency_id)) {
            return $this->error('Parameter Error');
        }
        $currency = Currency::where('id', $currency_id)->select()->get();
        $data = [];
        if (empty($currency_id)) {
            $data['status'] = 0;
            $data['introduction'] = 0;
        } else {
            $data['status'] = 1;
            $data['introduction'] = $currency;
        }
        return $this->success($data);
    }
}
