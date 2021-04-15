<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use App\BlockChain\Coin\CoinManager;
use App\Models\{Users, UsersWallet};

class UpdatePrivate extends Command
{
    protected $signature = 'update_private';
    protected $description = '更新私钥以及钱包地址';


    public function handle()
    {

        $this->comment("start1");
        foreach (Users::cursor() as $user) {
            $n = 0;
            $return = $this->updateWallet($user);

            while (!$return && $n < 3) {
                $n++;
                $return = $this->updateWallet($user);
            }
        }
        $this->comment("end");
    }

    public function updateWallet($user)
    {
        $address_url = '/v3/wallet/address';
        $project_name = config('app.name');
        $http_client = app('LbxChainServer');
        $response = $http_client->post($address_url, [
            'form_params' => [
                'userid' => $user->id,
                'projectname' => $project_name,
            ]
        ]);
        $result = json_decode($response->getBody()->getContents());
        if (!isset($result->code) || $result->code != 0) {
            //throw new \Exception('请求钱包接口发生异常');
            $this->error('请求钱包接口发生异常');
            return false;
        }
        $wallet_data = $result->data;
        $wallets = UsersWallet::where('user_id', $user->id)->get();
        foreach ($wallets as $wallet) {
            if (empty($wallet->currencyCoin)) {
                continue;
            }
            $currency_type = $wallet->currencyCoin->type;
            $make_wallet = $wallet->currencyCoin->make_wallet;
            if ($make_wallet == 1 && $wallet_data) {
                // 不支持多协议的才从接口生成钱包
                if ($wallet->currencyCoin->multi_protocol == 0) {
                    if (!in_array($currency_type, CoinManager::getMakeWalletCoinList())) {
                        $this->error("暂不支持生成{$currency_type}协议的钱包");
                        continue;
                    }
                    $wallet->address = $wallet_data->{"{$currency_type}_address"};
                    $wallet->private = $wallet_data->{"{$currency_type}_private"};
                }
            } elseif ($make_wallet == 2) {
                $wallet->address = $wallet->currencyCoin->collect_account;
                $wallet->private = '';
            } elseif ($make_wallet == 3) {
                $wallet->address = '';
                $wallet->private = '';
            } else {
                continue;
            }
            $wallet->save();
            $this->comment("user_id:" . $wallet->user_id . ',' . $currency_type . '钱包私钥更新成功');
        }

        $this->comment("user_id:" . $user->id . '用户私钥更新成功！');

        return true;
    }
}
