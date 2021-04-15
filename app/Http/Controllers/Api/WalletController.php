<?php

namespace App\Http\Controllers\Api;

use App\Service\UDunCloud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Utils\RPC;
use App\Models\{Currency, Address, AccountLog, Setting, Users, UsersWallet, UsersWalletOut, LeverTransaction};
use App\Events\WithdrawSubmitEvent;

class WalletController extends Controller
{
    //My Assets
    public function walletList(Request $request)
    {
        $currency_name = $request->input('currency_name', '');
        $user_id = Users::getUserId();
        if (empty($user_id)) {
            return $this->error('Parameter Error');
        }
        $cache_key_name = "user_wallet_data_{$user_id}";
        if (Cache::has($cache_key_name)) {
            $wallet_data = Cache::get($cache_key_name);
        } else {
            $user_wallet = UsersWallet::with(['currencyCoin'])->where('user_id', $user_id)
                ->whereHas('currencyCoin', function ($query) use ($currency_name) {
                    empty($currency_name) || $query->where('name', 'like', '%' . $currency_name . '%');
                })->get();
            $user_wallet->transform(function ($item, $key) {
                $item->setVisible([
                    'id', 'currency', 'currency_name',
                    'currency_type', 'contract_address',
                    'usdt_price', 'usd_price', 'multi_protocol',
                    'legal_balance', 'lock_legal_balance',
                    'lever_balance', 'lock_lever_balance',
                    'change_balance', 'lock_change_balance',
                    'is_legal', 'is_lever' , 'is_match',
                ]);
                return $item;
            });

            $legal_wallet['balance'] = $user_wallet->where('is_legal', 1)->values()->all();
            $legal_wallet['totle'] = 0;
            $legal_wallet['CNY'] = '';
            foreach ($legal_wallet['balance'] as $k => $v) {
                $num = $v['legal_balance'] + $v['lock_legal_balance'];
                $legal_wallet['totle'] += $num * $v['usdt_price'];
            }

            $change_wallet['balance'] = $user_wallet->where('is_match', 1)->values()->all();
            $change_wallet['totle'] = 0;
            $change_wallet['CNY'] = '';
            foreach ($change_wallet['balance'] as $k => $v) {
                $num = $v['change_balance'] + $v['lock_change_balance'];
                $change_wallet['totle'] += $num * $v['usdt_price'];
            }

            $lever_wallet['balance'] = $user_wallet->where('is_lever', 1)->values()->all();
            $lever_wallet['totle'] = 0;
            $lever_wallet['CNY'] = '';
            foreach ($lever_wallet['balance'] as $k => $v) {
                $num = $v['lever_balance'] + $v['lock_lever_balance'];
                $lever_wallet['totle'] += $num * $v['usdt_price'];
            }
            $USDTRate = Setting::getValueByKey('USDTRate', 7.06);

            //Read Whether To Turn On The Charging Currency
            $is_open_ctbi = Setting::getValueByKey("is_open_CTbi");
            $wallet_data = [
                'legal_wallet' => $legal_wallet,
                'change_wallet' => $change_wallet,
                'lever_wallet' => $lever_wallet,
                "is_open_ctbi" => $is_open_ctbi,
                'is_open_CTbi' => $is_open_ctbi,
                'ExRate' => $USDTRate,
                'USDTRate' => $USDTRate,
            ];
            Cache::put($cache_key_name, $wallet_data, 60);
        }
        return $this->success($wallet_data);
    }

    //Currency List
    public function currencyList()
    {
        $user_id = Users::getUserId();
        $currency = Currency::where('is_display', 1)->orderBy('sort', 'asc')->get()->toArray();
        if (empty($currency)) {
            return $this->error("Currency Has Not Been Added Yet");
        }
        foreach ($currency as $k => $c) {
            $w = Address::where("user_id", $user_id)->where("currency", $c['id'])->count();
            $currency[$k]['has_address_num'] = $w; //Number Of Withdrawal Addresses Added
        }
        return $this->success($currency);
    }

    public function updateAddress()
    {
        $user_id = Users::getUserId();
        $note = request()->input('coin_name');
        if ($note === 'USDT_ERC20') {
            $currency = 3;
        } else if ($note === 'USDT_OMNI') {
            $currency = 4;
        } else {
            $currencyInfo = Currency::where('name', $note)->first();
            if ($currencyInfo) {
                $currency = $currencyInfo->id;
            } else {
                $currency = 0;
            }
        }
        $address = Address::where('user_id', $user_id)
            ->where('currency', $currency)
            ->first();
        if (!$address) {
            $address = new Address();
            $address->notes = $note;
            $address->user_id = $user_id;
            $address->currency = $currency;
        }
        $address->address = request()->input('address');
        $address->save();
        return $this->success("Add Withdrawal Address Successfully");
    }

    //Add Withdrawal Address
    public function addAddress()
    {
        if (request()->input('coin_name')) {
            return $this->updateAddress();
        }

        $user_id = Users::getUserId();
        $id = request()->input("currency_id", '');
        $address = request()->input("address", "");
        $notes = request()->input("notes", "");
        if (empty($user_id) || empty($id) || empty($address)) {
            return $this->error("Parameter Error");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("User Not Found");
        }
        $currency = Currency::find($id);
        if (empty($currency)) {
            return $this->error("This Currency Does Not Exist");
        }
        $has = Address::where("user_id", $user_id)->where("currency", $id)->where('address', $address)->first();
        if ($has) {
            return $this->error("You Already Have This Withdrawal Address");
        }
        try {
            $currency_address = new Address();
            $currency_address->address = $address;
            $currency_address->notes = $notes;
            $currency_address->user_id = $user_id;
            $currency_address->currency = $id;
            $currency_address->save();
            return $this->success("Add Withdrawal Address Successfully");
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    //Delete Withdrawal Address
    public function addressDel()
    {
        $user_id = Users::getUserId();
        $address_id = request()->input("address_id", '');

        if (empty($user_id) || empty($address_id)) {
            return $this->error("Parameter Error");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("User Not Found");
        }
        $address = Address::find($address_id);

        if (empty($address)) {
            return $this->error("This Withdrawal Address Does Not Exist");
        }
        if ($address->user_id != $user_id) {
            return $this->error("You Do Not Have Permission To Delete This Address");
        }

        try {
            $address->delete();
            return $this->success("Delete The Withdrawal Address Successfully");
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    /**
     *Transfer Of Legal Currency Account To Transaction Account
     *Transfer The Legal Currency Account Can Only Be Transferred To The Transaction Account  The Contract Account Can Only Be Transferred With The Transaction Account
     *Transfer Typetype 1 Legal Currency(c2c)Transfer To Contract Currency 2 The Contract Was Transferred To French Currency 3Legal Currency To Transaction Currency 4Transaction Currency To Legal Currency
     *Log
     */
    public function changeWallet()  //BY tian
    {
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency_id", '');
        $number = request()->input("number", '');
        $type = request()->input("type", ''); //1Transfer From Legal Currency To Transaction Account Number
        if (empty($currency_id) || empty($number) || empty($type)) {
            return $this->error('Parameter Error');
        }
        if ($number < 0) {
            return $this->error('The Amount Entered Cannot Be Negative');
        }

        switch ($type) {
            case 1:
                $from_field = 1;
                $to_field = 3;
                $from_account_log_type = AccountLog::WALLET_LEGAL_OUT;
                $to_account_log_type = AccountLog::WALLET_LEVER_IN;
                $memo = 'Transfer Of Legal Currency To Contract Currency';
                break;
            case 2:
                $from_field = 3;
                $to_field = 1;
                $from_account_log_type = AccountLog::WALLET_LEVER_OUT;
                $to_account_log_type = AccountLog::WALLET_LEGAL_IN;
                $memo = 'Transfer Of Contract Currency To Legal Currency';
                if ($this->hasLeverTrade($user_id)) {
                    return $this->error('Do You Have A Leverage Deal In Progress,This Operation Cannot Be Performed');
                }
                break;
            case 3:
                $from_field = 1;
                $to_field = 2;
                $from_account_log_type = AccountLog::WALLET_LEGAL_OUT;
                $to_account_log_type = AccountLog::WALLET_CHANGE_IN;
                $memo = 'Transfer Of Legal Currency To Transaction Currency';
                break;
            case 4:
                $from_field = 2;
                $to_field = 1;
                $from_account_log_type = AccountLog::WALLET_CHANGE_OUT;
                $to_account_log_type = AccountLog::WALLET_LEGAL_IN;
                $memo = 'Transfer Of Transaction Currency To Legal Currency';
                break;
            default:
                return $this->error('Transfer Type Error');
                break;
        }
        try {
            DB::beginTransaction();
            $user_wallet = UsersWallet::where('user_id', $user_id)
                ->lockForUpdate()
                ->where('currency', $currency_id)
                ->first();
            if (!$user_wallet) {
                throw new \Exception('The Wallet Doesnt Exist');
            }
            $result = change_wallet_balance($user_wallet, $from_field, -$number, $from_account_log_type, $memo);
            if ($result !== true) {
                throw new \Exception($result);
            }
            $result = change_wallet_balance($user_wallet, $to_field, $number, $to_account_log_type, $memo);
            if ($result !== true) {
                throw new \Exception($result);
            }
            //Increase Exchange Record Of Legal Currency And Contract
            if ($type == 1 || $type == 2) {
                // $res11 = new Levertolegal();
                // $res11->user_id = $user_id;
                // $res11->number = $number;
                // $res11->type = $type;
                // $res11->status = 2; //2：Approved
                // $res11->add_time = time();
                // $res11->save();
            }
            DB::commit();
            return $this->success('Successful Transfer');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Operation Failed:' . $e->getMessage());
        }
    }

    /**
     * Transfer In The Same Account
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function accountTransfer(Request $request)
    {
        $wallet_id = $request->input('wallet_id', 0);
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        $number = $request->input('number', 0);
        $user_id = Users::getUserId();
        $balance_types = [
            'legal' => [1, 'Legal Currency Account'],
            'change' => [2, 'Currency Account'],
            'lever' => [3, 'Contract Account'],
        ];
        $keys  = array_keys($balance_types);
        $values = array_values($balance_types);
        $balance_name = array_column($values, 1);
        try {
            DB::beginTransaction();
            if ($from == '' || $to == '') {
                throw new \Exception('Transfer Account Type Must Be Selected');
            }
            if ($from == $to) {
                throw new \Exception('Transfer Account Type Cannot Be The Same');
            }

            if (!in_array($from, $keys) || !in_array($to, $keys)) {
                throw new \Exception('Illegal Transfer Account Type,It Can Only Be:' . implode('、', $balance_name));
            }
            if (bc_comp_zero($number) <= 0) {
                throw new \Exception('Transfer Quantity Must Be Greater Than0');
            }
            $wallet = UsersWallet::where('user_id', $user_id)
                ->lockForUpdate()
                ->findOrFail($wallet_id);
            // Judge Whether The Transferred Balance Is Sufficient
            if (bc_comp($wallet->{$from . '_balance'}, $number) < 0) {
                throw new \Exception(end($balance_types[$from]) . 'Insufficient Operational Balance');
            }
            $extra_data = [
                'from' => $from,
                'to' => $to,
            ];
            change_wallet_balance(
                $wallet,
                reset($balance_types[$from]),
                -$number,
                AccountLog::WALLET_ACCOUNT_TRANSFER_OUT,
                end($balance_types[$from]) . 'Draw Out',
                false,
                0,
                0,
                serialize($extra_data)
            );
            change_wallet_balance(
                $wallet,
                reset($balance_types[$to]),
                $number,
                AccountLog::WALLET_ACCOUNT_TRANSFER_IN,
                end($balance_types[$to]) . 'Into',
                false,
                0,
                0,
                serialize($extra_data)
            );
            DB::commit();
            return $this->success('Operation Successful');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error('Operation Failed:' . $th->getMessage());
        }
    }

    public function hasLeverTrade($user_id)
    {
        $exist_close_trade = LeverTransaction::where('user_id', $user_id)
            ->whereNotIn('status', [LeverTransaction::CLOSED, LeverTransaction::CANCEL])
            ->count();
        return $exist_close_trade > 0 ? true : false;
    }

    public function hzhistory()
    {
        //         $user_id = Users::getUserId();
        //         $result = new Levertolegal();
        //         $count = $result::all()->count();
        //         $result = $result->orderBy("add_time", "desc")->where("user_id", "=", $user_id)->get()->toArray();
        //         foreach ($result as $key => $value) {
        //             $result[$key]["add_time"] = date("Y-m-d H:i:s", $value["add_time"]);
        //             if ($value["type"] == 1) {
        //                 $result[$key]["type"] = "Contract To Legal Currency";
        //             } elseif ($value["type"] == 2) {
        //                 $result[$key]["type"] = "Legal Currency To Contract";
        //             }

        //         }
        // //        var_dump($result);die;

        //         return response()->json(['type' => "ok", 'data' => $result, 'count' => $count]);
    }

    /**
     * Get Currency Related Information
     * 
     * @return Illuminate\Http\JsonResponse 
     */
    public function getCurrencyInfo()
    {
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency", '');
        try {
            throw_if(empty($currency_id), new \Exception('Parameter Error'));
            $user = Users::findOrFail($user_id);
            $currency_info = Currency::findOrFail($currency_id);
            //Multiprotocol  
            $data = [];
            $type_data = [];
            $wallet_data = [];
            $wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $currency_id)
                ->firstOrFail();

            if ($currency_info->multi_protocol == 1) {
                $res = Currency::where('parent_id', $currency_id)->get()->toArray();
                foreach ($res as $k => $v) {
//                    if ($v['name'] != 'USDT_ERC20') {
//                        unset($type_data[$k]);
//                        continue;
//                    }
                    $type_data[] = $v;
                    $son_wallet = UsersWallet::where('user_id', $user_id)
                        ->where('currency', 3)
                        ->first();
                    if ($son_wallet && $son_wallet->address) {
                        $wallet_data[] = $son_wallet;
                    }
                }
            } else {
                $wallet_data[] = $wallet;
            }
            $data = [
                'name' => $currency_info->name,
                'type' => $currency_info->type,
                'multi_protocol' => $currency_info->multi_protocol, // Is Multi Protocol Supported
                'make_wallet' => $currency_info->make_wallet, // Strategies For Generating User Wallets:0.Not Generated,1.Interface Generation,2.Inherit From The Total Purse,3.Empty Purse
                'rate' => $currency_info->rate,
                'min_number' => $currency_info->min_number,
                'contract_address' => $currency_info->contract_address,
                'change_balance' => $wallet->change_balance ?? 0,
                'legal_balance' => $wallet->legal_balance ?? 0,
                'lever_balance' => $wallet->lever_balance ?? 0,
                'type_data' => $type_data,
                'wallet_data' => $wallet_data,
                'label' => $user->extension_code,
            ];
            return $this->success($data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return $this->error($ex->getModel() . "Data Not Found");
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    //Withdrawal Address，According Tocurrency_idList Address,You Need To Choose The Address When You Pick Up The Coin
    public function getAddressByCurrency()
    {
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('Parameter Error');
        }
        $address = Address::where('user_id', $user_id)->where('currency', $currency_id)->get()->toArray();
        if (empty($address)) {
            return $this->error('You Have Not Added The Withdrawal Address');
        }
        return $this->success($address);
    }

    //Submit Withdrawal Information。Number。
    public function postWalletOut(Request $request)
    {
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency", 0);
        $number = $request->input("number", 0);
        $address = $request->input("address", '') ?? '';
        $memo = $request->input("memo", '') ?? '';
        $type = $request->input("type", '') ?? ''; //Protocol Type
        $label = $request->input("label", '') ?? ''; //Label

        try {
            DB::beginTransaction();
            if (empty($currency_id) || empty($currency_id) || empty($address)) {
                throw new \Exception('Parameter Error');
            }
            $user = Users::findOrFail($user_id);

            $currency = Currency::findOrFail($currency_id);
            if ($currency->multi_protocol == 1) {
                if ($type == '') {
                    throw new \Exception('Please Select Agreement Type');
                }
                $currency = Currency::where('parent_id', $currency_id)->where('multi_protocol', 0)->where('type', $type)->firstOrFail();
            } else {
                $type = $currency->type;
            }
            //Label
            if ($currency->make_wallet == 2 && $label == '' && $memo == '') {
                throw new \Exception('Current Currency Withdrawal Label Cannot Be Empty');
            }
            $rate = $currency->rate;
            $rate = bc_div($rate, 100);
            $wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $currency_id)
                ->lockForUpdate()
                ->first();
            $balance_from = Setting::getValueByKey('withdraw_from_balance', 1); // Which Account Should I Withdraw Money From(1.Legal Currency,2.Coins,3.Contract)
            $balance_type = [
                1 => ['legal', 'Legal Currency'],
                2 => ['change', 'Coins'],
                3 => ['lever', 'Contract Currency'],
            ];
            $field_name = $balance_type[$balance_from][0] . '_balance';
            $balance_name = $balance_type[$balance_from][1];

            throw_if(bc_comp_zero($number) <= 0, new \Exception('The Amount Entered Must Be Greater Than0'));
            throw_if(bc_comp($number, $currency->min_number) < 0, new \Exception('The Withdrawal Quantity Cannot Be Less Than The Minimum Value'));
            throw_if(bc_comp($number, $currency->max_number) > 0 && bc_comp_zero($currency->max_number) > 0, new \Exception('The Withdrawal Quantity Cannot Be Higher Than The Maximum Value'));
            throw_if(bc_comp($number, $wallet->{$field_name}) > 0, new \Exception($balance_name . 'Sorry, Your Credit Is Running Low'));
            $fee = bc_mul($number, $rate);
            $real_number = bc_sub($number, $fee);
            throw_if(bc_comp_zero($real_number) <= 0, new \Exception($balance_name . 'The Balance Is Not Enough To Cover The Handling Charge'));
            $walletOut = new UsersWalletOut();
            $walletOut->user_id = $user_id;
            $walletOut->currency = $currency_id;
            $walletOut->number = $number;
            $walletOut->address = $address;
            $walletOut->rate = $rate;
            $walletOut->real_number = $real_number;
            $walletOut->create_time = time();
            $walletOut->update_time = time();
            $walletOut->status = 1; //1Submit Withdrawal2The Money Has Been Withdrawn3Fail
            $walletOut->type = $type; //Protocol Type
            $walletOut->memo = $label ?: $memo; //Label

            $walletOut->save();
            $result = change_wallet_balance($wallet, $balance_from, -$number, AccountLog::WALLETOUT, 'Apply For Withdrawal Of Currency To Deduct Balance');
            if ($result !== true) {
                throw new \Exception($result);
            }

            $result = change_wallet_balance($wallet, $balance_from, $number, AccountLog::WALLETOUT, 'Apply For Withdrawal Of Currency To Freeze Balance', true);
            if ($result !== true) {
                throw new \Exception($result);
            }
           // event(new WithdrawSubmitEvent($walletOut));
            /*$cloud = new UDunCloud();
            $res = $cloud->withDraw($address, $number, $walletOut->id, $currency_id);
            if (isset($res['code']) && $res['code'] != 0) {

                return $this->error($res['message']);
            }*/
            DB::commit();
            return $this->success('The Withdrawal Application Has Been Successful，Waiting For Review');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error(/*'File:' . $ex->getFile() . ',Line:'. $ex->getLine() . ',Message:'.*/$ex->getMessage());
        }

    }

    //Charging Address
    public function getWalletAddressIn()
    {
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('Parameter Error');
        }
        $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first();
        if (empty($wallet)) {
            return $this->error('The Wallet Doesnt Exist');
        }
        return $this->success($wallet->address);
    }

    //Balance Page Details
    public function getWalletDetail()
    {
        // return $this->error('Parameter Error');
        $user_id = Users::getUserId();
        $currency_id = request()->input("currency", '');
        $type = request()->input("type", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('Parameter Error');
        }
        $ExRate = Setting::getValueByKey('USDTRate', 6.5);
        // $userWallet = new UsersWallet();
        // return $this->error('Parameter Error');
        // $wallet = $userWallet->where('user_id', $user_id)->where('currency', $currency_id);
        if ($type == 'legal') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first();
        } else if ($type == 'change') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first();
        } else if ($type == 'lever') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first();
        } else {
            return $this->error('Error In Type');
        }
        if (empty($wallet)) {
            return $this->error("Wallet Not Found");
        }
        $wallet->ExRate = $ExRate;
        return $this->success($wallet);
    }

    public function legalLog(Request $request)
    {
        $limit = $request->input('limit', 10);
        $account = $request->input('account', '');
        $currency = $request->input('currency', 0);
        $type = $request->input('type', '');//1Legal Currency；2Coins；3Contract
        $user_id = Users::getUserId();
        $list = new AccountLog();
        if (!empty($currency)) {
            $list = $list->where('currency', $currency);
        }
        if (!empty($user_id)) {
            $list = $list->where('user_id', $user_id);
        }
//        if(!empty($type)){
//            $list = $list->whereHas('walletLog',function ($query) use ($type){
//                $query->where('balance_type', $type);
//            });
//        }
        $list = $list->orderBy('id', 'desc')->paginate($limit);
        //Read Whether To Turn On The Charging Currency

        $is_open_CTbi = Setting::where("key", "=", "is_open_CTbi")->first()->value;

        //遍历翻译
        foreach ($list->items() as $k=>$v){
            switch ($v->info)
            {
                case '币币交易，增加余额':
                    $v->info = 'Currency transaction to increase balance';
                    break;
                case '币币交易，减少余额':
                    $v->info = 'Currency transactions, reduce the balance';
                    break;
                case '币币交易，减少锁定余额':
                    $v->info = 'Currency transaction reduces locked balance';
                    break;
                case '币币交易，增加锁定余额':
                    $v->info = 'Currency transaction, increase the locked balance';
                    break;
                case '币币交易，扣除手续费':
                    $v->info = 'Currency transaction, deducting handling charges';
                    break;
                case '法币账户划入':
                    $v->info = 'Transfer into legal currency account';
                    break;
                case '币币账户划出':
                    $v->info = 'Transfer out of currency account';
                    break;
                case '平仓资金处理':
                    $v->info = 'Disposal of closing fund';
                    break;
                default:
                    break;
            }
        }

        return $this->success(array(
            "list" => $list->items(), 'count' => $list->total(),
            "limit" => $limit,
            "is_open_CTbi" => $is_open_CTbi
        ));
    }

    //Withdrawal Record
    public function walletOutLog()
    {
        $id = request()->input("id", '');
        $walletOut = UsersWalletOut::find($id);
        return $this->success($walletOut);
    }

    //Receive Information From Your WalletPB
    public function getLtcKMB()
    {
        $address = request()->input('address', '');
        $money = request()->input('money', '');
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('address', $address)->first();
        if (empty($wallet)) {
            return $this->error('The Wallet Doesnt Exist');
        }
        DB::beginTransaction();
        try {

            $data_wallet1 = array(
                'balance_type' => 1,
                'wallet_id' => $wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $wallet->change_balance,
                'change' => $money,
                'after' => $wallet->change_balance + $money,
            );
            $wallet->change_balance = $wallet->change_balance + $money;
            $wallet->save();
            AccountLog::insertLog([
                'user_id' => $wallet->user_id,
                'value' => $money,
                'currency' => $wallet->currency,
                'info' => 'Transfer The Balance From The Wallet',
                'type' => AccountLog::LTC_IN,
            ], $data_wallet1);
            DB::commit();
            return $this->success('Transfer Successful');
        } catch (\Exception $rex) {
            DB::rollBack();
            return $this->error($rex);
        }
    }

    public function sendLtcKMB()
    {
        $user_id = Users::getUserId();
        $account_number = request()->input('account_number', '');
        $money = request()->input('money', '');
        if (empty($account_number) || empty($money) || $money < 0) {
            return $this->error('Parameter Error');
        }
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('user_id', $user_id)->first();
        if ($wallet->change_balance < $money) {
            return $this->error('Sorry, Your Credit Is Running Low');
        }

        DB::beginTransaction();
        try {

            $data_wallet1 = array(
                'balance_type' => 1,
                'wallet_id' => $wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $wallet->change_balance,
                'change' => $money,
                'after' => $wallet->change_balance - $money,
            );
            $wallet->change_balance = $wallet->change_balance - $money;
            $wallet->save();
            AccountLog::insertLog([
                'user_id' => $wallet->user_id,
                'value' => $money,
                'currency' => $wallet->currency,
                'info' => 'Transfer Balance To Wallet',
                'type' => AccountLog::LTC_SEND,
            ], $data_wallet1);

            $url = "/api/ltcGet?account_number=" . $account_number . "&money=" . $money;
            $data = RPC::apihttp($url);
            $data = @json_decode($data, true);
            //            var_dump($data);die;
            if ($data["type"] != 'ok') {
                DB::rollBack();
                return $this->error($data["message"]);
            }
            DB::commit();
            return $this->success('Transfer Successful');
        } catch (\Exception $rex) {
            DB::rollBack();
            return $this->error($rex->getMessage());
        }
    }

    //ObtainpbTransaction Balance
    public function PB()
    {
        $user_id = Users::getUserId();
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('user_id', $user_id)->first();
        return $this->success($wallet->change_balance);
    }

    public function getLocalAddress()
    {
        $currency = '';
        $coinType = request()->input('coin_type');
        if ($coinType === 'erc20') {
            $currency = 2;
        } else if ($coinType === 'omni') {
            $currency = 1;
        } else if ($coinType === 'btc') {
            $currency = 1;
        } else if ($coinType === 'eth') {
            $currency = 2;
        } else if ($coinType === 'bch') {
            $currency = 5;
        } else if ($coinType === 'eostoken') {
            $currency = 8;
        } else if ($coinType === 'xrp') {
            $currency = 10;
        }
        $currency_info = Currency::find($currency);

        if (!$currency_info) {
            
            return $this->success([
                'address' => '',
            ]);
        }

        $user_id = Users::getUserId();
        $son_wallet = UsersWallet::where('user_id', $user_id)
            ->where('currency', $currency_info->id)
            ->first();
        if ((!$son_wallet) || ($son_wallet->address == '' && $currency_info->id != 3)) {
            
            $uDunCloud = new UDunCloud();
            
            $res = $uDunCloud->genderAddress($user_id, $currency_info->ud_coin_no);
            if (!isset($res['code'])) {
                return $this->error($res);
            } else if ($res['code'] != 200) {
                return $this->error($res['message']);
            }else{


                $son_wallet->create_time = time();
                $son_wallet->address = $res['data']['address'];
                $son_wallet->save();
            }
        }

        return $this->success([
            'address' => $son_wallet->address,
        ]);
    }

    public function getDrawAddress()
    {
        $coinType = request()->input('coin_type');
        if ($coinType) {
            return $this->getLocalAddress();
        }
        $coinName = request()->input('coin_name');
        $user_id = Users::getUserId();
        $son_wallet = Address::where('user_id', $user_id)
            ->where('notes', $coinName)
            ->first();
        return $this->success([
            'address' => $son_wallet->address ?? '',
        ]);
    }
}
