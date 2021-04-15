<?php

/**
 * Created by PhpStorm.
 * User: LDH
 */

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Token extends Model
{
    protected $table = 'tokens';
    public $timestamps = false;

    /**
     * 删除过期Token
     * @param array $attributes
     */
    public static function clearExpiredToken()
    {
        self::where('time_out', '<', time())->delete();
    }

    //获取token值
    public static function getToken()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if ('HTTP_' == substr($key, 0, 5)) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        if (isset($headers["AUTHORIZATION"]) && $headers["AUTHORIZATION"] != '') {
            return $headers["AUTHORIZATION"];
        } else {
            return "";
        }
    }

    //设置token
    public static function setToken($user_id)
    {
        //$token = new static();
        $token = new self();
        $token_str = md5($user_id . time() . mt_rand(0, 99999));

        $token->user_id = $user_id;
        $token->time_out = self::getTimeOut(15);
        $token->token = $token_str;

        return $token->save() ? $token_str : false;
    }

    //过期时间 只保留30天登录记录
    public static function getTimeOut($day = 15)
    {
        return time() + 60 * 60 * 24 * $day;
    }

    public static function getUserIdByToken($token)
    {
        if (empty($token)) {
            return false;
        }
        $cache_key_name = "token_user_id_$token";
        if (Cache::has($cache_key_name)) {
            return Cache::get($cache_key_name);
        }
        $token = self::where('token', $token)->first();
        if (empty($token)) {
            return false;
        }
        Cache::put($cache_key_name, $token->user_id, Carbon::createFromTimestamp($token->time_out));
        return $token->user_id;
    }

    /**
     * 根据user_id删除token
     * @param $user_id
     */
    public static function deleteTokenByUserId($user_id)
    {
        $tokens = self::where('user_id', $user_id)->get();
        $tokens->each(function ($item, $key) {
            $token = $item->token;
            $cache_key_name = "token_user_id_$token";
            if (Cache::has($cache_key_name)) {
                Cache::forget($cache_key_name);
            }
            $item->delete();
        });
    }

    /**根据user_id token删除当前的token
     *
     * @param $user_id  $token
     */
    public static function deleteToken($user_id, $token)
    {
        self::where('user_id', $user_id)->where('token', $token)->delete();
        $cache_key_name = "token_user_id_$token";
        if (Cache::has($cache_key_name)) {
            Cache::forget($cache_key_name);
        }
    }

    public static function setTokenLang($lang = 'zh')
    {
        $token = self::where('token', self::getToken())->first();
        if ($token) {
            $token->update([
                'lang' => $lang,
            ]);
            $cache_key_name = "token_user_id_$token";
            Cache::put($cache_key_name, $token->user_id, Carbon::createFromTimestamp($token->time_out));
        }
    }
}
