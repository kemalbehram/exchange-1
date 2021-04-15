<?php

namespace App\Listeners;

use App\BlockChain\Coin\CoinManager;
use App\Models\Users;
use App\Models\Currency;
use App\Service\UDunCloud;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\UserRegisterEvent;
use App\DAO\GoChainDAO;
use App\Models\UsersWallet;

class UserRegisterListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(UserRegisterEvent $event)
    {
        $user = $event->user;
        $user->refresh();


        /*$uDunCloud = new UDunCloud();

        $user_wallet = UsersWallet::where('user_id', $user->id)
            ->where('currency', 3)
            ->first();
        if (!$user_wallet) {
            $user_wallet = new UsersWallet();
            $res = $uDunCloud->genderAddress($user->id);
            if (!isset($res['code'])) {
                logger()->error('createWallet', [
                    'data' => $res,
                ]);
                return;
            } else if ($res['code'] != 200) {
                throw new \Exception($res['message']);
            }
            $user_wallet->currency = 3;
            $user_wallet->user_id = $user->id;
            $user_wallet->create_time = time();
            $user_wallet->address = $res['data']['address'];
            $user_wallet->private = '';
            $user_wallet->save();
        }

        $user_wallet = UsersWallet::where('user_id', $user->id)
            ->where('currency', 2)
            ->first();
        if (!$user_wallet) {
            $user_wallet = new UsersWallet();
            $res = $uDunCloud->genderAddress($user->id, 0);
            if (!isset($res['code'])) {
                logger()->error('createWallet', [
                    'data' => $res,
                ]);
                return;
            } else if ($res['code'] != 200) {
                throw new \Exception($res['message']);
            }
            $user_wallet->currency = 2;
            $user_wallet->user_id = $user->id;
            $user_wallet->create_time = time();

            $user_wallet->address = $res['data']['address'];
            $user_wallet->private = '';
            $user_wallet->save();
        }*/


        //默认生成所有币种的钱包
        $currency_list = Currency::get();

        $user_wallet_array = array();

        if ($currency_list) {
            
            foreach ($currency_list as $currency) {
                
                if ($currency->parent_id == 0) {
                    
                    $user_wallet_array[] = array(

                        'user_id' => $user->id,
                        'currency' => $currency->id,
                        'address' => '',
                        'legal_balance' => 0,
                        'lock_legal_balance' => 0,
                        'change_balance' => 0,
                        'lock_change_balance' => 0,
                        'lever_balance' => 0,
                        'lock_lever_balance' => 0,
                        'define_balance' => 0,
                        'lock_define_balance' => 0,
                        'status' => 0,
                        'memorizing_words' => '',
                        'eth_address' => '',
                        'password' => '',
                        'old_balance' => 0,
                        'private' => '',
                        'cost' => 0,
                        'txid' => '',
                        'gl_time' => 0,
                        'create_time' => time()
                    );
                }
            }
        }

        if (count($user_wallet_array)) {
            
            UsersWallet::insert($user_wallet_array);
        }
    }
}
