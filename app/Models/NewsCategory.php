<?php
/**
 * create by vscode
 * @author lion
 */
namespace App\Models;

class NewsCategory extends Model
{

    protected $table = 'news_category';

    protected $dateFormat = 'U';
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    public function getCreateTimeAttribute()
    {
        $value = $this->attributes['create_time'];
        return $value ? date('Y-m-d H:i:s', $value ) : '';
    }

    public function getUpdateTimeAttribute()
    {
        $value = $this->attributes['update_time'];
        return $value ? date('Y-m-d H:i:s', $value ) : '';
    }

    /**
     *定义分类和新闻的一对多关联
     */
    public function news()
    {
        return $this->hasMany(News::class, 'c_id');
    }

     /**
     * 获取当前时间
     *
     * @return int
     */

    public function freshTimestamp()
    {
        return time();
    }

    /**
     * 避免转换时间戳为时间字符串
     *
     * @param DateTime|int $value
     * @return DateTime|int
     */
    
    public function fromDateTime($value)
    {
        return $value;
    }

    /**
     * 直接从POST变量批量赋值，忽略不存在的字段和主键
     * @return bool
     */
    public function batchAssign($data)
    {   
        if(is_array($data)) {
            foreach($data as $key => $value) {
                //判定$key是否在模型字段中，如果不在则忽略
                $fields = Schema::getColumnListing($this->table);
                if(in_array($key, $fields) && $key != $this->primaryKey) {
                    $this->$key = $value;
                }
            }
            return true;
        } else {
            return false;
        }
    }
}
