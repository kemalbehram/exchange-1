<?php

/**
 * swl
 *
 * 20180705
 */
namespace App\Console\Commands;

use App\Utils\RPC;
use App\Models\Users;
use App\Models\UsersWallet;
use App\Models\HuobiSymbol;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
class UpdateHuobiSymbol extends Command
{
    protected $signature = 'update_huobi_symbol';
    protected $description = '更新火币交易对';


    public function handle()
    {

        $this->comment("start1");
        $url='api.huobi.br.com/v1/common/symbols';
        // $content = RPC::apihttp($url);
        $cli= new Client();
        $content=$cli->get($url)->getBody()->getContents();
        $content = json_decode($content, true);

        
        //dd($content);  
        HuobiSymbol::getSymbolsData($content['data']);
       
        $this->comment("end");
    }

    
    
}
