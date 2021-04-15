<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Utils\RPC;
use App\Models\{AppVersion, Bank, Setting, Token, Users};
use App\DAO\UploaderDAO;

class DefaultController extends Controller
{
    public function jumpDist()
    {
        return redirect('/dist');
    }

    public function getLang()
    {
        return response()->json([
            'type' => 'ok',
            'message' => session()->get('lang'),
            'session_id' => session()->getId(),
        ]);
    }

    public function setLang()
    {
        $lang = request()->input('lang', '');
        if ($lang == '') {
            return response()->json([
                'type' => 'error',
                'message' => 'Error: lang cannot be empty',
            ]);
        }
        session()->put('lang', $lang);
        $token = Token::getToken();
        if (!empty($token)) {
            Token::setTokenLang($lang);
        }
        return response()->json([
            'type' => 'ok',
            'message' => 'Switch lange success!(' . session()->get('lang') . ')',
            'session_id' => session()->getId(),
        ]);
    }

    public function env()
    {
        $result = file_get_contents('./env.json');
        return response()->json(json_decode($result, true));
    }

    public function dataGraph()
    {
        $data = Setting::getValueByKey("chart_data");
        if (empty($data)) return $this->error("No Data Available");

        $data = json_decode($data, true);
        return $this->success(
            array(
                "data" => array(
                    $data["time_one"], $data["time_two"], $data["time_three"], $data["time_four"], $data["time_five"], $data["time_six"], $data["time_seven"]
                ),
                "value" => array(
                    $data["price_one"], $data["price_two"], $data["price_three"], $data["price_four"], $data["price_five"], $data["price_six"], $data["price_seven"]
                ),
                "all_data" => $data
            )
        );
    }

    public function index()
    {
        $coin_list = RPC::apihttp("https://api.coinmarketcap.com/v2/ticker?limit=10");
        $coin_list = @json_decode($coin_list, true);
        if (!empty($coin_list["data"])) {
            foreach ($coin_list["data"] as &$d) {
                if ($d["total_supply"] > 10000) {
                    $d["total_supply"] = substr($d["total_supply"], 0, -4) . "Ten Thousand";
                }
            }
        }
        return $this->success(
            array(
                "coin_list" => $coin_list["data"]
            )
        );
    }

    public function getSetting()
    {
        $key = request()->input('key', '');
        if (!$key) {
            return $this->error('key Does Not Exist');
        }

        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $k => $v) {
            $setting[$v['key']] = $v['value'];
        }

        return $this->success([
            $key => $setting[$key]
        ]);
    }

    public function upload(Request $request)
    {
        $file = $request->file('file');
        $scene = $request->input('scene', ''); //Scene,Subfolders
        if (!$file) {
            return $this->error('File Does Not Exist');
        }
        if($file->getClientOriginalExtension() != 'pdf'){
            //File Type Validation
            $validator = Validator::make($request->all(), [
                'file' => 'required|image',
            ], [], [
                'file' => 'Upload Attachment',
            ]);
            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }
        }

        $result = UploaderDAO::fileUpload($file, $scene);
        if ($result['state'] != 'SUCCESS') {
            return $this->error($result['state']);
        }
        return $this->success($result['url']);
    }

    public function getNode(\Illuminate\Http\Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $show_message["real_teamnumber"] = Users::find($user_id)->real_teamnumber;
        $show_message["top_upnumber"] = Users::find($user_id)->top_upnumber;
        $account_number = $request->input('account_number', null);
        if (!empty($account_number)) {
            $user_id_search = Users::where('account_number', $account_number)->first();
            if (!empty($user_id_search)) {
                $user_id = $user_id_search->id;
            } else {
                $user_id = 0;
            }
        }
        $users = Users::where('parent_id', $user_id)->get();
        $results = array();
        foreach ($users as $key => $user) {
            $results[$key]['name'] = $user->account_number;
            $results[$key]['id'] = $user->id;
            $results[$key]['parent_id'] = $user->parent_id;
        }
        $data["show_message"] = $show_message;
        $data["results"] = $results;
        return $this->success($data);
    }

    public function getVersion()
    {
        $version = Setting::getValueByKey('version', '1.0');
        return $this->success($version);
    }

    public function getBanks()
    {
        $result = Bank::all();
        return $this->success($result);
    }

    public function checkUpdate(Request $request)
    {
        $name = $request->input('name', '');
        $version = $request->input('version', '');
        $os = strtolower($request->input('os', 'android') ?? 'android');
        $type = $os == 'android' ? 1 : 2;
        try {
            $app_version = AppVersion::where('type', $type)
                ->orderBy('version_num', 'desc')
                ->firstOrFail();
            if (version_compare($app_version->version_name, $version) > 0) {
                list($main_version) = explode('.', $version);
                list($app_main_version) = explode('.', $app_version->version_name);
                $main_version = intval($main_version);
                $app_main_version = intval($app_main_version);
                if ($app_main_version > $main_version) {
                    $pkg_url = $app_version->pkg_url;
                    $wgt_url = '';
                } else {
                    $pkg_url = '';
                    $wgt_url = $app_version->wgt_url;
                }
                return [
                    'code' => 0,
                    'msg' => $os . 'New Version Found',
                    'data' => [
                        'update' => true,
                        'wgtUrl' => $wgt_url,
                        'pkgUrl' => $pkg_url,
                        'downUrl' => $app_version->down_url,
                    ],
                ];
            } else {
                throw new \Exception('YourAppIts The Latest Version');
            }
        } catch (\Throwable $th) {
            return [
                'code' => 0,
                'msg' => $th->getMessage(),
                'data' => [
                    'update' => false,
                    'wgtUrl' => '',
                    'pkgUrl' => '',
                    'downUrl' => $app_version->down_url ?? '',
                ],
            ];
        }
    }

    public function base64ImageUpload(Request $request)
    {
        $base64_image_content = $request->input('base64_file', '');
        $res = self::base64ImageContent($base64_image_content);
        if (!$res) {
            return $this->error('Upload Failed');
        }
        return $this->success($res);
    }

    public static function base64ImageContent($base64_image_content)
    {
        //Match The Format Of The Picture
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            if (!in_array($type, ['jpg', 'jpeg', 'png',])) {
                return false;
            }
            $path = '/upload/' . date('Ymd') . '/';
            $new_file  = public_path() . $path;
            if (!file_exists($new_file)) {
                //Check If The Folder Exists，If Not, Create It，And Give The Highest Authority
                mkdir($new_file, 0700);
            }
            $filename = time() . rand(0, 999999) . ".{$type}";
            $full_file = $new_file . $filename;
            if (file_put_contents($full_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return $path . $filename;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
