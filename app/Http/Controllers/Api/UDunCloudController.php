<?php

namespace App\Http\Controllers\Api;

use App\Models\Users;
use App\Models\Currency;
use App\Models\UsersWallet;
use App\Models\UsersWalletIn;
use App\Models\UsersWalletOut;
use App\Models\AccountLog;
use App\Service\UDunCloud;
use Illuminate\Http\Request;

class UDunCloudController extends Controller
{
    public function callback(Request $request)
    {

        $body = $request->post('body');
        if (empty($body)) {
            return [];
        }

        logger()->info('uDunCloudCallback', [
            'data' => \GuzzleHttp\json_decode($body, true),
        ]);

        $data = \GuzzleHttp\json_decode($body, true);
        if (!isset($data['status']) || intval($data['status']) !== 3) {
            exit('status');
        }

        if (!isset($data['tradeType'])) {
            exit('tradeType');
        }

        try {

            $currency = 0;
            $user_wallet = NULL;

            if ($data['mainCoinType'] == $data['coinType']) {
                
                //Main Chain Currency
                $currency = Currency::where('ud_coin_no', $data['mainCoinType'])->first();
                $user_wallet = UsersWallet::where('address', $data['address'])
                    ->where('currency', $currency->id)
                    ->first();
            }else{

                //USDT
                if (($data['mainCoinType'] == 0 && $data['coinType'] == 31) || ($data['mainCoinType'] == 60 && $data['coinType'] == '0xdac17f958d2ee523a2206206994597c13d831ec7')) {
                    
                    $currency = Currency::find(3);

                    $parentWallet = NULL;
                    
                    if ($data['coinType'] == 31) {
                        
                        $parentWallet = UsersWallet::where('address', $data['address'])->where('currency', 1)->first();
                    }else{

                        $parentWallet = UsersWallet::where('address', $data['address'])->where('currency', 2)->first();
                    }
                    
                    $user_wallet = UsersWallet::where('user_id', $parentWallet->user_id)->where('currency', 3)->first();

                //ERC20Not IncludedUSDT
                }else if($data['mainCoinType'] == 60){

                    $currency = Currency::where('ud_coin_no', 60)->where('contract_address', $data['coinType'])->first();

                    $parentWallet = UsersWallet::where('address', $data['address'])->where('currency', 2)->first();

                    $user_wallet = UsersWallet::where('user_id', $parentWallet->user_id)->where('currency', $currency->id)->first();

                //Unknown Currency，To Be Expanded
                }else{

                    $user_wallet = UsersWallet::where('address', $data['address'])
                        ->where('currency', $currency->id)
                        ->first();
                }
            }

            if (!$user_wallet) {
                throw new \Exception('User Wallet Not Found');
                exit('User Wallet Not Found');
            }

            $walletIn = UsersWalletIn::where('txid', $data['txId'])->first();

            if ($walletIn) {
                throw new \Exception('Already Exists');
                exit('Already Exists');
            }

            $walletIn = new UsersWalletIn();
            $walletIn->user_id = $user_wallet->user_id;
            $walletIn->currency = $currency->id;
            $walletIn->txid = $data['txId'];
            $walletIn->address = $data['address'];
            $walletIn->number = $walletIn->real_number = bc_div($data['amount'], bc_pow(10, $data['decimals']) , $data['decimals']);
            $walletIn->rate = $data['address'];
            $walletIn->status = 1;
            $walletIn->notes = $data['tradeId'];
            $walletIn->create_time = time();
            $walletIn->save();

            $result = change_wallet_balance($user_wallet, 2, $walletIn->number, AccountLog::CHAIN_RECHARGE, 'Charge Money To Account');
            if ($result !== true) {
                throw new \Exception('Balance Update Failed');
                exit('Balance Update Failed');
            }

            // switch ($data['tradeType']) {
            //     case 0x1:        // Recharge
            //         $user_wallet->change_balance = bc_add(
            //             $user_wallet->change_balance,
            //             bc_div($data['amount'], bc_pow(10, $data['decimals']) , $data['decimals']));
            //         $user_wallet->save();
            //         break;
            //     case 0x2:        // Withdrawal
            //         $order = UsersWalletOut::where('address', $data['address'])
            //             ->where('id', $data['businessId'])
            //             ->where('user_id', $user_wallet->user_id)
            //             ->first();
            //         if (!$order || $order->status != 1) {
            //             return [];
            //         }
            //         $order->status = 2;
            //         $order->save();
            //         $user_wallet->change_balance = bc_sub(
            //             $user_wallet->change_balance,
            //             bc_div($data['amount'], bc_pow(10, $data['decimals']) , $data['decimals']));
            //         $user_wallet->save();
            //         break;
            // }
        } catch (\Exception $ex) {
            logger()->error('uDunCloudCallback', [
                'error' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'message' => $ex->getFile(),
                'trace' => $ex->getTrace(),
            ]);
            exit('Abnormal');
        }

        exit();
        return [];
    }

    public function test()
    {
        $cloud = new UDunCloud();
        $res = $cloud->support();
        return $res;
    }
}