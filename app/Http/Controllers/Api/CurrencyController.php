<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\{Currency, CurrencyPlate, CurrencyMatch, CurrencyQuotation, MarketHour, TransactionComplete};
use App\Jobs\{LeverUpdate, SendMarket};

class CurrencyController extends Controller
{
    public function lists()
    {
        $currency = Currency::where('is_display', 1)->orderBy('sort', 'asc')->get()->toArray();
        $legal = array();
        foreach ($currency as $c) {
            if ($c["is_legal"]) {
                array_push($legal, $c);
            }
        }

        return $this->success(array(
            "currency" => $currency,
            "legal" => $legal
        ));
    }

    public function lever()
    {
        $currency = Currency::where('is_display', 1)->orderBy('sort', 'asc')->get()->toArray();
        $legal = array();
        foreach ($currency as $c) {
            if ($c["is_lever"]) {
                array_push($legal, $c);
            }
        }
        $time = strtotime(date("Y-m-d"));

        foreach ($legal as &$l) {
            $quotation = array();

            foreach ($currency as $cc) {
                if ($cc["id"] != $l["id"]) {
                    $last_price = 0;
                    $yesterday_last_price = 0;
                    $last = "";
                    $yesterday_last = "";
                    $proportion = 0.00;

                    $last = TransactionComplete::orderBy('create_time', 'desc')
                        ->where("currency", $cc["id"])
                        ->where("legal", $l["id"])
                        ->first();
                    $yesterday_last = TransactionComplete::orderBy('create_time', 'desc')
                        ->where("create_time", '<', $time)
                        ->where("currency", $cc["id"])
                        ->where("legal", $l["id"])
                        ->first();
                    !empty($last) && $last_price = $last->price;
                    !empty($yesterday_last) && $yesterday_last_price = $yesterday_last->price;

                    if (empty($last_price)) {
                        if ($yesterday_last_price) {
                            $proportion = -100.00;
                        }
                    } else {
                        if ($yesterday_last_price) {
                            $proportion = ($last_price - $yesterday_last_price) / $yesterday_last_price;
                        } else {
                            $proportion = +100.00;
                        }
                    }

                    array_push($quotation, array(
                        "id" => $cc["id"],
                        "name" => $cc["name"],
                        "last_price" => $last_price,
                        "proportion" => $proportion,
                        "yesterday_last_price" => $yesterday_last_price
                    ));
                }
            }
            $l["quotation"] = $quotation;
        }

        return $this->success($legal);
    }

    //BY tiandongliang
    public function quotation_tian()
    {
        $currency = Currency::where('is_display', 1)->orderBy('sort', 'asc')->get()->toArray();
        $legal = array();
        foreach ($currency as $c) {
            if ($c["is_legal"]) {
                array_push($legal, $c);
            }
        }
        $time = strtotime(date("Y-m-d"));
        foreach ($legal as &$l) {
            $quotation = array();
            foreach ($currency as $key => $cc) {
                $l['quotation'] = CurrencyQuotation::orderBy('add_time', 'desc')->where("legal_id", $l["id"])->get()->toArray();
            }
        }
        return $this->success($legal);
    }

    /**
     * New Marketfor tradingview
     *
     * @return void
     */
    public function newTimeshars(Request $request)
    {
        $symbol = $request->input('symbol');
        $period = $request->input('period');
        $start = $request->input('from', null);
        $end = $request->input('to', null);
        $symbol = strtoupper($symbol);
        //Type，1=15Minute，2=1Hour，3=4Hour,4=One Day,5=Time Sharing,6=5Minute，7=30Minute,8=A Week，9=January,10=A Year
        $period_list = [
            '1min' => 5,
            '5min' => 6,
            '15min' => 1,
            '30min' => 7,
            '60min' => 2,
            '1D' => 4,
            '1W' => 8,
            '1M' => 9,
            '1day' => 4,
            '1week' => 8,
            '1mon' => 9,
            '1year' => 10,
        ];
        $periods = array_keys($period_list);
        $types = array_values($period_list);
        if ($start == null || $end == null) {
            return [
                'code' => -1,
                'msg' => 'error: start time or end time must be filled in',
                'data' => null
            ];
        }

        if ($start > $end) {
            return [
                'code' => -1,
                'msg' => 'error: start time should not exceed the end time.',
                'data' => null
            ];
        }
        if ($symbol == '' || stripos($symbol, '/') === false) {
            return [
                'code' => -1,
                'msg' => 'error: symbol invalid',
                'data' => null
            ];
        }

        if ($period == '' || !in_array($period, $periods)) {
            return [
                'code' => -1,
                'msg' => 'error: period invalid',
                'data' => null
            ];
        }
        $now = strtotime(date('Y-m-d H:i'));
        if ($period == '1min' && $end >= $now) {
            //Last Minute Data Not Available
            $end = $now - 1;
        }
        $type = $period_list[$period];
        $symbol = explode('/', $symbol);
        list($base_currency, $quote_currency) = $symbol;
        $base_currency = Currency::where('name', $base_currency)
            ->where("is_display", 1)
            ->first();
        $quote_currency = Currency::where('name', $quote_currency)
            ->where("is_display", 1)
            ->where("is_legal", 1)
            ->first();
        if (!$base_currency || !$quote_currency) {
            return [
                'code' => -1,
                'msg' => 'error: symbol not exist',
                'data' => null
            ];
        }
        $legal_id = $quote_currency->id;
        $currency_id = $base_currency->id;
        //1Minute Data
        $minutes_quotation = MarketHour::orderBy('day_time', 'asc')
            ->where("currency_id", $currency_id)
            ->where("legal_id", $legal_id)->where('type', $type)
            ->where('day_time', '>=', $start)
            ->where('day_time', '<=', $end)
            ->get();
        $return = array();
        if ($minutes_quotation) {
            foreach ($minutes_quotation as $k => $v) {
                $arr = array(
                    "open" => $v->start_price,
                    "close" => $v->end_price,
                    "high" => $v->highest,
                    "low" => $v->mminimum,
                    "volume" => $v->number,
                    "time" => $v->day_time * 1000,
                );
                array_push($return, $arr);
            }
        } else {
            foreach ($minutes_quotation as $k => $v) {
                $arr = null;
                array_push($return, $arr);
            }
        }
        return [
            "code" => 1,
            "msg" => 'success:)',
            "data" => $return,
        ];
    }

    public function klineMarket(Request $request)
    {
        $symbol = $request->input('symbol');
        $period = $request->input('period');
        $from = $request->input('from', null);
        $to = $request->input('to', null);
        $symbol = strtoupper($symbol);
        $result = [];
        //Type，1=15Minute，2=1Hour，3=4Hour,4=One Day,5=Time Sharing,6=5Minute，7=30Minute,8=A Week，9=January,10=A Year
        $period_list = [
            '1min' => '1min',
            '5min' => '5min',
            '15min' => '15min',
            '30min' => '30min',
            '60min' => '60min',
            '1H' => '60min',
            '1D' => '1day',
            '1W' => '1week',
            '1M' => '1mon',
            '1Y' => '1year',
            '1day' => '1day',
            '1week' => '1week',
            '1mon' => '1mon',
            '1year' => '1year',
        ];
        if ($from == null || $to == null) {
            return [
                'code' => -1,
                'msg' => 'error: from time or to time must be filled in',
                'data' => $result,
            ];
        }
        if ($from > $to) {
            return [
                'code' => -1,
                'msg' => 'error: from time should not exceed the to time.',
                'data' => $result,
            ];
        }
        $periods = array_keys($period_list);
        if ($period == '' || !in_array($period, $periods)) {
            return [
                'code' => -1,
                'msg' => 'error: period invalid',
                'data' => $result,
            ];
        }
        if ($symbol == '' || stripos($symbol, '/') === false) {
            return [
                'code' => -1,
                'msg' => 'error: symbol invalid',
                'data' => $result,
            ];
        }
        $period = $period_list[$period];
        list($base_currency, $quote_currency) = explode('/', $symbol);
        $base_currency_model = Currency::where('name', $base_currency)
            ->first();
        $quote_currency_model = Currency::where('name', $quote_currency)
            ->where("is_legal", 1)
            ->first();
        if (!$base_currency_model || !$quote_currency_model) {
            return [
                'code' => -1,
                'msg' => 'error: symbol not exist',
                'data' => null
            ];
        }
        $result = MarketHour::getEsearchMarket($base_currency, $quote_currency, $period, $from, $to);
        $result = array_map(function ($value) {
            $value['time'] = $value['id'] * 1000;
            $value['volume'] = $value['amount'];
            return $value;
        }, $result);
        return [
            'code' => 1,
            'msg' => 'success',
            'data' => $result
        ];
    }

    public function newQuotation(Request $request)
    {
        $plate_id = $request->input('', 0) ?? 0;
        $cache_key_name = 'currency/quotation';
        if (Cache::has($cache_key_name)) {
            $currencies = Cache::get($cache_key_name);
        } else {
            $currencies = Currency::with('quotation')
                ->whereHas('quotation', function ($query) use ($plate_id) {
                    $query->where('is_display', 1)
                        ->when($plate_id > 0, function ($query) use ($plate_id) {
                            $query->where('plate_id', $plate_id);
                        });
                })
                ->where('is_display', 1)
                ->where('is_legal', 1)
                ->orderBy('sort', 'asc')
                ->get();
            $currencies->transform(function ($currency, $key) {
                // $currency->quotation->transform(function ($item, $key) {
                //     $item->addHidden([
                //         'currency', 'legal', 'plate', 'lever_max_share', 'lever_min_share',
                //         'lever_share_num', 'lever_trade_fee',  'exchange_rate', 
                //         'open_lever', 'open_transaction', 'overnight', 'spread',
                //         'optional_status',  'market_from', 'market_from_name', 'create_time'
                //     ]);
                //     return $item;
                // });
                $currency->addHidden([
                    'created_at', 'updated_at', 'parent_id', 'contract_address', 'allow_game_exchange',
                    'allow_transfer', 'transfer_fee', 'chain_fee', 'clone_name', 'chain_fee', 'black_limt',
                    'decimal_scale', 'make_wallet', 'type',
                ]);
                return $currency;
            });
            Cache::put($cache_key_name, $currencies, Carbon::now()->addMinute(1));
        }
        
        return $this->success($currencies);
    }

    public function plates(Request $request)
    {
        $plates = CurrencyPlate::where('status', 1)
            ->orderBy('sorts', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $matches = CurrencyMatch::whereIn('plate_id', $plates->pluck('id')->all())->get();
        $plates->transform(function ($item, $key) use ($matches) {
            $plate = $item;
            $new_matches = $matches;
            $legal_currencies = Currency::where('is_display', 1)
                ->where('is_legal', 1)
                ->get();
            // Additional Legal Currency
            $legal_currencies->transform(function ($item, $key) use ($new_matches, $plate) {
                $legal_plate_matches = $new_matches->where('plate_id', $plate->id)
                    ->where('legal_id', $item->id)
                    ->values()
                    ->all();
                // Additional Transaction Pair
                $item->setAttribute('plate_matches', $legal_plate_matches);
                return $item;
            });
            // Hide The Legal Currency Without Trading Right
            $legal_currencies = $legal_currencies->filter(function ($item, $key) {
                if (count($item->plate_matches) > 0) {
                    return $item;
                }
            })->values();
            $item->setAttribute('legal_group', $legal_currencies);
            return $item;
        });

        return $this->success($plates);
    }

    public function javaData(Request $request)
    {
        $data = $request->all();
        $type = $data['type'] ?? '';
        $queue_map = [
            'daymarket' => 'kline.all',
            'kline' => 'kline.all',
            'match_trade' => 'send:match:trade',
            'market_depth' => 'market.depth',
        ];
        if (array_key_exists($type, $queue_map)) {
            $queue_name = $queue_map[$type];
            SendMarket::dispatch($data)->onQueue($queue_name);
        }
    }

    public function writeEsearchKline(Request $request)
    {
        $id = $request->input('id', 0);
        $base_currency = strtoupper($request->input('base-currency', ''));
        $quote_currency = strtoupper($request->input('quote-currency', ''));
        $period = $request->input('period', '');
        $open = $request->input('open', 0);
        $close = $request->input('close', 0);
        $high = $request->input('high', 0);
        $low = $request->input('low', 0);
        $vol = $request->input('vol', 0);
        $amount = $request->input('amount', 0);
        $now = time();
        $market_data = [
            'id' => $id, //Time Stamp
            'base-currency' => $base_currency,
            'quote-currency' => $quote_currency,
            'period' => $period, //'1min', '5min', '15min', '30min', '60min', '1day', '1mon', '1week'
            'open' => $open,
            'close' => $close,
            'high' => $high,
            'low' => $low,
            'vol' => $vol,
            'amount' => $amount,
        ];
        if ($period == '1day') {
            $currency_match = CurrencyMatch::whereHas('currency', function ($query) use ($base_currency) {
                $query->where('name', $base_currency);
            })->whereHas('legal', function ($query) use ($quote_currency) {
                $query->where('name', $quote_currency);
            })->first();
            $params = [
                'legal_id' => $currency_match->legal_id,
                'currency_id' => $currency_match->currency_id,
                'now_price' => $market_data['close'],
                'now' => $now,
                'legal_name' => $market_data['base-currency'],
                'currency_name' => $market_data['quote-currency']
            ];
            if (bc_comp($market_data['close'], 0) > 0) {
                LeverUpdate::dispatch($params)->onQueue('lever:update');
            }
            CurrencyQuotation::unguarded(function () use ($currency_match, $open, $close, $high, $low, $vol) {
                CurrencyQuotation::updateOrCreate([
                    'legal_id' => $currency_match->legal_id,
                    'currency_id' => $currency_match->currency_id,
                ], [
                    'match_id' => $currency_match->id,
                    'change' => bc_mul(bc_div(bc_sub($close, $open), $open, 4), 100),
                    'volume' => $vol,
                    'open' => $open,
                    'close' => $close,
                    'high' => $high,
                    'low' => $low,
                    'now_price' => $close,
                    'add_time' => time(),
                ]);
            });
        }
        $response = MarketHour::setEsearchMarket($market_data);
        return $response;
    }

    public function dealInfo()
    {
        $legal_id = request()->input("legal_id");
        $currency_id = request()->input("currency_id");

        if (empty($legal_id) || empty($currency_id))
            return $this->error("Parameter Error");

        $legal = Currency::where("is_display", 1)
            ->where("id", $legal_id)
            ->where("is_legal", 1)
            ->first();
        $currency = Currency::where("is_display", 1)
            ->where("id", $currency_id)
            ->first();
        if (empty($legal) || empty($currency)) {
            return $this->error("Currency Not Found");
        }
        $type = request()->input("type", "1");
        $seconds = 60;
        switch ($type) {
            case 2:
                $seconds = 15 * 60;
                break;
            case 3:
                $seconds = 60 * 60;
                break;
            case 4:
                $seconds = 4 * 60 * 60;
                break;
            case 5:
                $seconds = 24 * 60 * 60;
                break;
            default:
                $seconds = 60;
        }
        $time = time();
        $last_price = 0;
        $last = TransactionComplete::orderBy('create_time', 'desc')
            ->where("currency", $currency_id)
            ->where("legal", $legal_id)
            ->first();
        $last && $last_price = $last->price;

        $now_quotation = TransactionComplete::getQuotation($legal_id, $currency_id, ($time - $seconds), $time);
        //$now_quotation = TransactionComplete::getQuotation_two($currency->name,$legal->name,$type);
        $quotation = array();
        for ($i = 0; $i < 10; $i++) {
            $end_time = $time - $i * $seconds;
            $start_time = $end_time - $seconds;

            $data = array();
            $data = $now_quotation = TransactionComplete::getQuotation($legal_id, $currency_id, $start_time, $end_time);
            array_push($quotation, $data);
        }
        return $this->success(array(
            "legal" => $legal,
            "currency" => $currency,
            "last_price" => $last_price,
            "now_quotation" => $now_quotation,
            "quotation" => $quotation
        ));
    }

    public function getCurrentMarket(Request $request)
    {
        $base_currency = strtoupper($request->input('base_currency', ''));
        $quote_currency = strtoupper($request->input('quote_currency', ''));
        $period = $request->input('period', '1day') ?? '1day';
        $market = MarketHour::getLastEsearchMarket($base_currency, $quote_currency, $period);
        if (empty($market)) {
            return $this->error('The Market Does Not Exist');
        }
        $latest_market = reset($market);
        $change = bc_sub($latest_market['close'], $latest_market['open']);
        $latest_market['change'] = bc_mul(bc_div($change, $latest_market['open']), 100, 4);
        return $this->success($latest_market);
    }
}
