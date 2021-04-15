<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Currency, AutoList};
use GuzzleHttp\Client;
use App\Models\AdminToken;

class KRobotController extends Controller
{
    protected static $httpClient = null;

    public static function getHttpClient()
    {
        if (!self::$httpClient) {
            $admin_id = session('admin_id');
            $admin_token = AdminToken::getToken($admin_id);
            self::$httpClient = new Client([
                'headers' => [
                    'Authorization' => $admin_token,
                ]
            ]);
        }
        return self::$httpClient;
    }

    public function index()
    {
        return view('admin.krobot.index');
    }

    public function lists(Request $request)
    {

        $coinList = json_decode(json_encode(DB::table('currency')->select('name', 'id')->get()), TRUE);
        $coinList = array_column($coinList, NULL, 'id');

        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);

        $list = json_decode(json_encode(DB::table('k_robot')->orderBy('kr_id', 'desc')->paginate($limit)), TRUE);

        if (isset($list['data'])) {
            
            $list = $list['data'];
        }

        if ($coinList && $list && count($list)) {
            
            foreach ($list as $key => $krobot) {

                if (isset($coinList[$krobot['kr_stock']])) {
                    
                    $list[$key]['kr_stock'] = $coinList[$krobot['kr_stock']]['name'];
                    $list[$key]['kr_money'] = $coinList[$krobot['kr_money']]['name'];
                }
            }
        }

        $result = array(

            'code' => 0,
            'msg' => '',
            'count' => count($list),
            'data' => $list,
            'extra_data' => ''
        );

        exit(json_encode($result));
    }

    public function add(Request $request)
    {
        $kr_id = $request->input('kr_id', 0);
        $currencies = Currency::where('is_display', 1)->get();
        $legals = Currency::where('is_display', 1)->where('is_legal', 1)->get();
        $result = DB::table('k_robot')->where('kr_id', $kr_id)->first();

        if ($result) {

            if ($result->kr_cron_start > 0) {
                
                $result->kr_cron_start = date('Y-m-d H:i:s', $result->kr_cron_start);
            }else{

                $result->kr_cron_start = '';
            }

            if ($result->kr_cron_end > 0) {
                
                $result->kr_cron_end = date('Y-m-d H:i:s', $result->kr_cron_end);
            }else{

                $result->kr_cron_end = '';
            }
        }

        return view('admin.krobot.add', [
            'currencies' => $currencies,
            'legals' => $legals,
            'result' => $result,
        ]);
    }

    public function postAdd(Request $request)
    {

        $base_url = config('app.java_match_url');
        $url = $base_url . '/api/auto/add_or_update';
        $params = $request->all();

        $message = FALSE;

        if (isset($params['kr_cron_status'])) {
            
            $params['kr_cron_status'] = 1;

            if (!(is_numeric($params['kr_cron_end_min_price']) && is_numeric($params['kr_cron_end_max_price']))) {

                $message = '【结束后价格上限】和【结束后价格下限】必须是数字';
            }else{

                if (!(bccomp($params['kr_cron_end_max_price'], $params['kr_cron_end_min_price'], 8) > 0)) {
                    
                    $message = '【结束后价格上限】必须大于【结束后价格下限】';
                }

                if (!(bccomp($params['kr_cron_end_min_price'], 0, 8) > 0 && bccomp($params['kr_cron_end_max_price'], 0, 8) > 0)) {
                    
                    $message = '【结束后价格上限】和【结束后价格下限】必须大于0';
                }

                if (bccomp($params['kr_cron_end_price'], $params['kr_cron_end_max_price'], 8) > 0 || bccomp($params['kr_cron_end_price'], $params['kr_cron_end_min_price'], 8) < 0) {
                    
                    $message = '【目标价格】必须在【结束后价格下限】和【结束后价格上限】的区间内';
                }

                if (bccomp($params['kr_cron_end_max_price'], $params['kr_max_price'], 8) > 0 || bccomp($params['kr_cron_end_min_price'], $params['kr_min_price'], 8) < 0) {
                    
                    $message = '【结束后价格下限】和【结束后价格上限】的区间必须在【价格下限】和【价格上限】的区间内';
                }
            }

            if (!(is_numeric($params['kr_cron_end_price']))) {

                $message = '【目标价格】必须是数字';
            }else{

                if (bccomp($params['kr_cron_end_price'], 0, 8) <= 0) {
                    
                    $message = '【目标价格】必须大于0';
                }
            }

            if ($params['kr_cron_start'] >= $params['kr_cron_end']) {
                
                $message = '【结束时间】必须比【开始时间】晚';
            }

            if ($params['kr_cron_end'] == '') {
                
                $message = '请选择【结束时间】';
            }else{

                $params['kr_cron_end'] = strtotime($params['kr_cron_end']);
            }

            if ($params['kr_cron_start'] == '') {
                
                $message = '请选择【开始时间】';
            }else{

                $params['kr_cron_start'] = strtotime($params['kr_cron_start']);
            }
        }else{

            $params['kr_cron_status'] = 0;

            if ($params['kr_cron_start'] != '') {

                $params['kr_cron_start'] = strtotime($params['kr_cron_start']);
            }

            if ($params['kr_cron_end'] != '') {
                
                $params['kr_cron_end'] = strtotime($params['kr_cron_end']);
            }
        }

        if (!(is_numeric($params['kr_max_number']) && is_numeric($params['kr_min_number']))) {

            $message = '【数量上限】和【数量下限】必须是数字';
        }else{

            if (!(bccomp($params['kr_max_number'], $params['kr_min_number'], 8) > 0)) {
                
                $message = '【数量上限】必须大于【数量下限】';
            }

            if (!(bccomp($params['kr_max_number'], 0, 8) > 0 && bccomp($params['kr_min_number'], 0, 8) > 0)) {
                
                $message = '【数量上限】和【数量下限】必须大于0';
            }
        }

        if (!(is_numeric($params['kr_max_price']) && is_numeric($params['kr_min_price']))) {

            $message = '【价格上限】和【价格下限】必须是数字';
        }else{

            if (!(bccomp($params['kr_max_price'], $params['kr_min_price'], 8) > 0)) {
                
                $message = '【价格上限】必须大于【价格下限】';
            }

            if (!(bccomp($params['kr_max_price'], 0, 8) > 0 && bccomp($params['kr_min_price'], 0, 8) > 0)) {
                
                $message = '【价格上限】和【价格下限】必须大于0';
            }
        }

        if (!(check_number($params['kr_price_decimal']) && check_number($params['kr_number_decimal']))) {
            
            $message = '【价格精度】和【数量精度】必须是正整数';
        }

        if (!(check_number($params['kr_stock']) && check_number($params['kr_money']))) {

            $message = '请选择【交易币】和【法币】';
        }else{

            if ($params['kr_stock'] === $params['kr_money']) {
                
                $message = '【交易币】和【法币】不能相同';
            }

            $exist_where = array(
                'kr_stock' => $params['kr_stock'],
                'kr_money' => $params['kr_money']
            );

            if (check_number($params['kr_id'])) {

                $exist_where[] = array(

                    'kr_id',
                    '<>',
                    $params['kr_id']
                );
            }

            $kr_exist = DB::table('k_robot')
                        ->where('kr_stock', $params['kr_stock'])
                        ->where('kr_money', $params['kr_money'])
                        ->where('kr_id', '<>', $params['kr_id'])
                        ->count();

            if ($kr_exist > 0) {
                
                $message = '当前交易对已经存在机器人';
            }
        }

        $loginResult = doLogin($params['kr_user'], $params['kr_user_password']);

        if (! $loginResult) {
            
            $message = '【机器人账号】登陆失败，请检查帐号密码';
        }

        if ((!$params) || (!count($params))) {
            
            $message = '请完整填写机器人配置项';
        }

        if ($message === FALSE) {

            $dbResult = FALSE;

            if (check_number($params['kr_id'])) {
                
                $kr_id = $params['kr_id'];

                $dbResult = DB::table('k_robot')->where('kr_id', $kr_id)->update($params);
            }else{

                $params['kr_status'] = 0;

                $dbResult = DB::table('k_robot')->insert($params);
            }
            
            if ($dbResult) {
                
                return $this->success('操作成功');
            }else{

                return $this->error('操作失败，请稍后再试');
            }
        }else{

            return $this->error($message);
        }
    }

    public function changeStart(Request $request)
    {
        $kr_id = $request->input('kr_id', 0);
        $symbol = $request->input('symbol', 0);

        if (DB::table('k_robot')->where('kr_id', $kr_id)->update(['kr_status' => $symbol])) {
            
            return $this->success(($symbol > 0 ? '开启' : '关闭') . '成功');
        }else{

            return $this->error(($symbol > 0 ? '开启' : '关闭') . '失败，请稍后再试');
        }
    }

    /**
     * 删除机器人
     *
     * @param Request $request
     * @return array
     */
    public function del(Request $request)
    {
        $kr_id = $request->input('kr_id', 0);

        if (DB::table('k_robot')->where('kr_id', $kr_id)->delete()) {
            
            return $this->success('删除成功');
        }else{

            return $this->error('删除失败，请稍后再试');
        }
    }
}


//正整数
function check_number($var){

    $result = FALSE;

    if (is_numeric($var) && ((floor($var) - $var) == 0) && bccomp($var, 0, 8) > 0){
        
        $result = TRUE;
    }

    return $result;
}


function dump($var, $isCmd = false) {

    ob_start();
    var_dump($var);
    $output = ob_get_clean();
    if (!extension_loaded('xdebug')) {
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (!$isCmd) {
            
            $output = '<pre>' . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }
    
    echo($output);
}


function doLogin($account_number, $password){

    $result = FALSE;

    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/user/login?_timespan=' . msectime();

    $params = array(

        'user_string' => $account_number,
        'password' => $password,
        'sms_code' => '',
        'country_code' => '+86'
    );

    $requestResult = doPost($url, $params);

    if ($requestResult && count($requestResult) && isset($requestResult['type']) && isset($requestResult['message']) && $requestResult['type'] == 'ok') {
        
        $result = $requestResult['message'];
    }

    return $result;
}


function msectime() {

    list($msec, $sec) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
}


function doPost($url, $params = array(), $header = array()){

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,FALSE);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($curl);
    curl_close($curl);

    return json_decode($output, true);
}