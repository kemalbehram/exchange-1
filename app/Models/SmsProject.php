<?php

namespace App\Models;

class SmsProject extends Model
{
    //
    protected $table = 'sms_project';
    //自动时间戳
    protected $dateFormat = 'U';

    protected $appends = [
        'created_date',
        'updated_date'
    ];

    protected static $sceneList = [
        'register' => '用户注册',
        'login' => '用户登录',
        'change_password' => '修改密码',
        'reset_password' => '重置密码',
    ];

    protected static $regionList = [
        86 => '中国大陆',
        886 => '中国台湾',
        852 => '中国香港',
        853 => '中国澳门',
        81 => '日本',
        1 => '美国',
        44 => '英国',
        60 => '马来西亚',
        65 => '新加坡',
        82 => '韩国',
        855 => '柬埔寨',
        856 => '老挝',
    ];

    public static function enumScene()
    {
        return self::$sceneList;
    }

    public static function enumRegion()
    {
        return self::$regionList;
    }

    public function getSceneNameAttribute()
    {
        $scene = $this->getAttribute('scene');
        return array_key_exists($scene, self::$sceneList) ? self::$sceneList[$scene] : '';
    }

    public function getRegionNameAttribute()
    {
        $country_code = $this->getAttribute('country_code');
        return array_key_exists($country_code, self::$regionList) ? self::$regionList[$country_code] : '';
    }

    public function getCreatedDateAttribute()
    {
        $created_at = $this->getAttribute('created_at');
        return $created_at->toDateTimeString();
    }

    public function getUpdatedDateAttribute()
    {
        $updated_at = $this->getAttribute('updated_at');
        return $updated_at->toDateTimeString();
    }
}
