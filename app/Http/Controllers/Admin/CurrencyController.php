<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\{Currency, CurrencyMatch, CurrencyPlate, Setting, UsersWallet, HuobiSymbol};

class CurrencyController extends Controller
{
    public function index()
    {
        return view('admin.currency.index');
    }

    public function add(Request $request)
    {
        $id = $request->input('id', 0);
        $parent_id = $request->input('parent_id', 0);
        $result = Currency::findOrNew($id);
        $multi_protocol_currencies = Currency::where('multi_protocol', 1)
            ->where('id', '<>', $id)
            ->get();
        $result->parent_id > 0 && $parent_id = $result->parent_id;
        return view('admin.currency.add', [
            'result' => $result,
            'parent_id' => $parent_id,
            'multi_protocol_currencies' => $multi_protocol_currencies,
        ]);
    }

    public function postAdd(Request $request)
    {
        try {
            DB::beginTransaction();
            $currency_data = $request->except(['id', 'total_account', 'collect_account', 'key', 'file']);
            $currency_data = array_filter($currency_data, function ($val) {
                return !is_null($val);
            });
            $id = $request->input('id', 0) ?? 0;
            $default_data = [
                'logo' => '',
                'type' => '',
                'contract_address' => '',
                'clone_name' => '',
                'is_lever' => 0,
                'is_legal' => 0,
                'is_match' => 0,
                'show_legal' => 0,
            ];
            $currency_data = array_merge($default_data, $currency_data);
            $clone_name = $currency_data['clone_name'];
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:currency,name,' . $id,
                'price' => 'required|numeric|gt:0',
                'sort' => 'required|integer',
                'min_number' => 'required|numeric|gte:0',
                'max_number' => 'required|numeric|gte:0',
                'decimal_scale' => 'required|numeric|gte:0',
            ], [], [
                'name' => '????????????',
                'price' => '????????????',
                'sort' => '??????',
                'type' => '????????????',
                'min_number' => '??????????????????',
                'max_number' => '??????????????????',
                'decimal_scale' => '????????????',
            ]);
            //?????????????????????
            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }           
            $currency = Currency::findOrNew($id);
            if (!empty($clone_name)) {
                $has_symbol = HuobiSymbol::where('base-currency', $clone_name)->get();
                if(count($has_symbol) == 0){
                    throw new \Exception('???????????????????????????');
                }
            }
            Currency::unguarded(function () use ($currency, $currency_data) {
                $currency->fill($currency_data)->save();
            });
            DB::commit();
            return $this->success('????????????');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception->getMessage());
        }
    }

    public function setInAddress(Request $request)
    {
        $id = $request->route('id', 0);
        $currency = Currency::findOrFail($id);
        return view('admin.currency.set_in_address', [
            'currency' => $currency,
        ]);
    }

    public function setOutAddress(Request $request)
    {
        $id = $request->route('id', 0);
        $currency = Currency::findOrFail($id);
        return view('admin.currency.set_out_address', [
            'currency' => $currency,
        ]);
    }

    /**
     * ????????????????????????
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function postSetInAddress(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $verificationcode = $request->input('verificationcode', '');
            $collect_account = $request->input('collect_account', '');
            $currency = Currency::findOrFail($id);
            if ($verificationcode == '') {
                throw new \Exception('??????????????????????????????');
            }
            if ($collect_account == '' || $collect_account == $currency->total_account) {
                throw new \Exception('????????????????????????????????????????????????');
            }
            $projectname = config('app.name');
            $chain_client = app('LbxChainServer');
            // ??????????????????
            $uri = '/v3/wallet/changeinaddress';
            $response = $chain_client->request('post', $uri, [
                'form_params' => [
                    'projectname' => $projectname,
                    'coin' => strtoupper($currency->type),
                    'address' => $collect_account,
                    'verificationcode' => $verificationcode,
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (!isset($result['code']) || $result['code'] != 0) {
                throw new \Exception($result['msg'] ?? '??????????????????');
            }
            $currency->collect_account = $collect_account;
            $currency->save();
            return $this->success('????????????');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * ????????????????????????
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function postSetOutAddress(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $verificationcode = $request->input('verificationcode', '');
            $total_account = $request->input('total_account', '');
            $key = $request->input('key', '');
            $encrypt_key = '';
            $currency = Currency::findOrFail($id);
            if ($verificationcode == '') {
                throw new \Exception('??????????????????????????????');
            }
            if ($total_account == '' || $total_account == $currency->collect_account) {
                throw new \Exception('??????????????????????????????????????????????????????');
            }
            $projectname = config('app.name');
            $chain_client = app('LbxChainServer');
            // ??????????????????
            $uri = '/v3/wallet/changeoutaddress';
            $response = $chain_client->request('post', $uri, [
                'form_params' => [
                    'projectname' => $projectname,
                    'coin' => strtoupper($currency->type),
                    'address' => $total_account,
                    'verificationcode' => $verificationcode,
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (!isset($result['code']) || $result['code'] != 0) {
                throw new \Exception($result['msg'] ?? '??????????????????');
            }
            $auto_encrypt_private = Setting::getValueByKey('auto_encrypt_private', 1);
            if ($key != '********' && $key != '') {
                if ($auto_encrypt_private) {
                    $uri = '/v3/wallet/encrypt';
                    $response = $chain_client->request('post', $uri, [
                        'form_params' => [
                            'projectname' => $projectname,
                            'p' => $key,
                        ],
                    ]);
                    $result = json_decode($response->getBody()->getContents(), true);
                    if (!isset($result['code']) || $result['code'] != 0) {
                        throw new \Exception($result['msg'] ?? '??????????????????');
                    }
                    $encrypt_key = $result['data']['k'];     
                } else {
                    $encrypt_key = '';
                }
            }
            $currency->key = $encrypt_key;
            $currency->total_account = $total_account;
            $currency->save();
            return $this->success('????????????');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function lists(Request $request)
    {
        $limit = $request->input('limit', 10);
        $parent_id = $request->input('parent_id', 0);
        $result = Currency::when($parent_id > -1, function ($query) use ($parent_id) {
                $query->where('parent_id', $parent_id);
            })
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($limit);
        foreach ($result as $k => $v) {
            $legal_balance = UsersWallet::where('currency', $v->id)->sum('legal_balance');
            $lock_legal_balance = UsersWallet::where('currency', $v->id)->sum('lock_legal_balance');
            $change_balance = UsersWallet::where('currency', $v->id)->sum('change_balance');
            $lock_change_balance = UsersWallet::where('currency', $v->id)->sum('lock_change_balance');
            $lever_balance = UsersWallet::where('currency', $v->id)->sum('lever_balance');
            $lock_lever_balance = UsersWallet::where('currency', $v->id)->sum('lock_lever_balance');
            $sum = bcadd($legal_balance, $lock_legal_balance);
            $sum = bcadd($sum, $change_balance);
            $sum = bcadd($sum, $lock_change_balance);
            $sum = bcadd($sum, $lever_balance);
            $sum = bcadd($sum, $lock_lever_balance);
            $v->sum = $sum;
            $result[$k] = $v;
        }
        return $this->layuiData($result);
    }

    public function delete(Request $request)
    {
        $id = $request->input('id', 0);
        $acceptor = Currency::find($id);
        if (empty($acceptor)) {
            return $this->error('????????????');
        }
        try {
            $acceptor->delete();
            return $this->success('????????????');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function isDisplay(Request $request)
    {
        $id = $request->input('id', 0);
        $currency = Currency::find($id);
        if (empty($currency)) {
            return $this->error('????????????');
        }
        if ($currency->is_display == 1) {
            $currency->is_display = 0;
        } else {
            $currency->is_display = 1;
        }
        try {
            $currency->save();
            return $this->success('????????????');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function executeCurrency(Request $request)
    {
        $id = intval($request->input('id', 0));
        $now = Carbon::now();
        $key = "currency_{$id}_make_wallet";
        try {
            throw_if(Cache::has($key), new \Exception('?????????????????????????????????????????????,???????????????'));
            Cache::put($key , $now, $now->addMinutes(30));
            Artisan::queue('execute_currency', [
                'id' => $id
            ])->onQueue('currency:execute');
            return $this->success('????????????,????????????????????????,????????????????????????,???????????????');
        } catch (\Throwable $th) {
            return $this->error('????????????:' . $th->getMessage());
        }
    }

    /**
     * ???????????????
     *
     * @return void
     */
    public function match()
    {
        $currency_plates = CurrencyPlate::all();
        return view('admin.currency.match', [
            'currency_plates' => $currency_plates,
        ]);
    }

    public function matchList(Request $request)
    {
        $legal_id = $request->route('legal_id');
        $limit = $request->input('limit', 10);
        $plate_id = $request->input('plate_id', 0);
        $legal = Currency::find($legal_id);
        $matchs = $legal->quotation()->when($plate_id > 0, function ($query) use ($plate_id) {
                $query->where('plate_id', $plate_id);
            })->paginate($limit);
        return $this->layuiData($matchs);
    }

    public function addMatch($legal_id)
    {
        $is_legal = Currency::where('id', $legal_id)->value('is_legal');
        if (!$is_legal) {
            abort(403, '????????????????????????,?????????????????????');
        }
        $currencies = Currency::where('id', '<>', $legal_id)
            ->where('parent_id', 0)
            ->get();
        $market_from_names = CurrencyMatch::enumMarketFromNames();
        $plates = CurrencyPlate::all();
        return view('admin.currency.match_add')
            ->with('currencies', $currencies)
            ->with('market_from_names', $market_from_names)
            ->with('plates', $plates);
    }

    public function postAddMatch(Request $request, $legal_id)
    {
        $is_legal = Currency::where('id', $legal_id)->value('is_legal');
        if (!$is_legal) {
            return $this->error('????????????????????????,?????????????????????');
        }
        $plate_id = $request->input('plate_id', 0) ?? 0;
        $currency_id = $request->input('currency_id', 0) ?? 0;
        $is_display = $request->input('is_display', 1) ?? 1;
        $market_from = $request->input('market_from', 0) ?? 0;
        $open_transaction = $request->input('open_transaction', 0);
        $open_lever = $request->input('open_lever', 0);
        $lever_share_num = $request->input('lever_share_num', 1);
        $spread = $request->input('spread', 0);
        $sort = $request->input('sort', 0);
        $overnight = $request->input('overnight', 0);
        $lever_trade_fee = $request->input('lever_trade_fee', 0);
        $lever_min_share = $request->input('lever_min_share', 0);
        $lever_max_share = $request->input('lever_max_share', 0);
        $exchange_rate = $request->input('exchange_rate', 0);
        if ($exchange_rate < 0 || $exchange_rate > 100) {
            return $this->error('????????????????????????????????????0??????100');
        }
        if ($lever_trade_fee < 0 || $lever_trade_fee > 100) {
            return $this->error('????????????????????????????????????0??????100');
        }
        //??????????????????????????????
        $exist = CurrencyMatch::where('currency_id', $currency_id)
            ->where('legal_id', $legal_id)
            ->first();
        if ($exist) {
            return $this->error('????????????????????????');
        }
        CurrencyMatch::unguard();
        $currency_match = CurrencyMatch::create([
            'plate_id' => $plate_id,
            'legal_id' => $legal_id,
            'currency_id' => $currency_id,
            'is_display' => $is_display,
            'market_from' => $market_from,
            'open_transaction' => $open_transaction,
            'open_lever' => $open_lever,
            'lever_share_num' => $lever_share_num,
            'lever_trade_fee' => $lever_trade_fee,
            'sort' => $sort,
            'spread' => $spread,
            'overnight' => $overnight,
            'lever_min_share' => $lever_min_share,
            'lever_max_share' => $lever_max_share,
            'exchange_rate' => $exchange_rate,
            'create_time' => time(),
        ]);
        CurrencyMatch::reguard();
        if ($market_from == 2) {
            \Channel\Client::connect(config('socket.channel_ip'), config('socket.channel_port'));
            \Channel\Client::publish('market_from_change', [
                'before' => 0,
                'after' => 2,
                'currency_match' => $currency_match,
            ]);
        }
        return isset($currency_match->id) ? $this->success('????????????') : $this->error('????????????');
    }

    public function editMatch($id)
    {
        $currency_match = CurrencyMatch::find($id);
        if (!$currency_match) {
            abort(403, '????????????????????????');
        }
        $market_from_names = CurrencyMatch::enumMarketFromNames();
        $plates = CurrencyPlate::all();
        $currencies = Currency::where('id', '<>', $currency_match->legal_id)
            ->where('parent_id', 0)
            ->get();
        $var = compact('currency_match', 'currencies', 'market_from_names', 'plates');
        return view('admin.currency.match_add', $var);
    }

    public function postEditMatch(Request $request, $id)
    {
        $plate_id = $request->input('plate_id', 0) ?? 0;
        $currency_id = $request->input('currency_id', 0) ?? 0;
        $is_display = $request->input('is_display', 1) ?? 1;
        $market_from = $request->input('market_from', 0) ?? 0;
        $open_transaction = $request->input('open_transaction', 0);
        $open_lever = $request->input('open_lever', 0);
        $lever_share_num = $request->input('lever_share_num', 1);
        $spread = $request->input('spread', 0);
        $sort = $request->input('sort', 0);
        $overnight = $request->input('overnight', 0);
        $lever_trade_fee = $request->input('lever_trade_fee', 0);
        $lever_min_share = $request->input('lever_min_share', 0);
        $lever_max_share = $request->input('lever_max_share', 0);
        $exchange_rate = $request->input('exchange_rate', 0);
        if ($exchange_rate < 0 || $exchange_rate > 100) {
            return $this->error('????????????????????????????????????0??????100');
        }
        if ($lever_trade_fee < 0 || $lever_trade_fee > 100) {
            return $this->error('????????????????????????????????????0??????100');
        }
        $currency_match = CurrencyMatch::find($id);
        if (!$currency_match) {
            abort(403, '????????????????????????');
        }
        $before_market_from = $currency_match->market_from;
        CurrencyMatch::unguard();
        $result = $currency_match->fill([
            'plate_id' => $plate_id,
            'currency_id' => $currency_id,
            'is_display' => $is_display,
            'market_from' => $market_from,
            'open_transaction' => $open_transaction,
            'open_lever' => $open_lever,
            'lever_share_num' => $lever_share_num,
            'lever_trade_fee' => $lever_trade_fee,
            'spread' => $spread,
            'sort' => $sort,
            'overnight' => $overnight,
            'lever_min_share' => $lever_min_share,
            'lever_max_share' => $lever_max_share,
            'exchange_rate' => $exchange_rate,
            'create_time' => time(),
        ])->save();
        CurrencyMatch::reguard();
        if ($before_market_from != $market_from) {
            \Channel\Client::connect(config('socket.channel_ip'), config('socket.channel_port'));
            \Channel\Client::publish('market_from_change', [
                'before' => $before_market_from,
                'after' => $market_from,
                'currency_match' => $currency_match,
            ]);
        }
        return $result ? $this->success('????????????') : $this->error('????????????');
    }

    public function delMatch($id)
    {
        $result = CurrencyMatch::destroy($id);
        return $result ? $this->success('????????????') : $this->error('????????????');
    }
}
