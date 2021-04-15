<?php

namespace App\DAO;

class ImageFilterDAO
{
    protected static $imageTypesList = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_BMP => 'bmp',
        IMAGETYPE_WBMP => 'wbmp',
        IMAGETYPE_XBM => 'xbm',
        IMAGETYPE_GIF => 'gif',

    ];

    public static function getImageTypesList()
    {
        return self::$imageTypesList;
    }

    public static function handle($filename, $file_ext_name)
    {
        if (function_exists('exif_imagetype')) {
            $file_type = exif_imagetype($filename);
            throw_unless($file_type, new \Exception("不是有效的图片"));
            $file_key_index = array_key_exists($file_type, self::$imageTypesList);
            throw_unless($file_key_index, new \Exception("不支持的图片类型"));
            $file_ext_name = self::$imageTypesList[$file_key_index];
        }
        $func = "imagecreatefrom{$file_ext_name}";
        $savefunc = "image{$file_ext_name}";
        throw_unless(function_exists($func) && function_exists($savefunc), new \Exception("系统不支持处理图片文件,可能缺少相关扩展"));
        $file_resource = call_user_func($func, $filename);
        return call_user_func($savefunc, $file_resource, $filename);
    }
}
