<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Models\{News,Setting, NewsCategory};

class NewsController extends Controller
{
    public function get(Request $request)
    {
        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error('Parameter Error');
        }
        $news = News::find($id);
        return $this->success($news);
    }

    //Help Center,News Classification
    public function getCategory()
    {
        $results = NewsCategory::where('is_show', 1)->orderBy('sorts')->get(['id', 'name'])->toArray();
        return $this->success($results);
    }

    //Recommended News
    public function recommend()
    {
        $results = News::where('recommend', 1)->orderBy('id', 'desc')->get(['id', 'title', 'c_id'])->toArray();
        return $this->success($results);
    }

    // Get Articles Under Category
    public function getArticle(Request $request)
    {
        $limit = $request->get('limit', 15);
        $page = $request->get('page', 1);
        $category_id = $request->get('c_id');
        $lang = $request->get('lang', '') ?: session()->get('lang');
        $lang == '' && $lang = 'zh';
        $cache_key_name = "news_cid_{$category_id}_lang_{$lang}_page_{$page}";
        if (Cache::has($cache_key_name)) {
            $article = Cache::get($cache_key_name);
        } else {
            if (empty($category_id)) {
                $article = News::where('lang', $lang)
                    ->orderBy('sorts', 'desc')
                    ->orderBy('id', 'desc')
                    ->paginate($limit);
            } else {
                $article = News::where('lang', $lang)
                    ->where('c_id', $category_id)
                    ->orderBy('sorts', 'desc')
                    ->orderBy('id', 'desc')
                    ->paginate($limit, ['*'], 'page', $page);
            }
            foreach ($article->items() as &$value) {
                unset($value->content);
                unset($value->recommend);
                unset($value->display);
                unset($value->discuss);
                unset($value->author);
                unset($value->audit);
                unset($value->browse_grant);
                unset($value->keyword);
                unset($value->abstract);
                unset($value->views);
                unset($value->create_time);
                unset($value->update_time);
            }
            Cache::put($cache_key_name, $article, Carbon::now()->addMinutes(15));
        }
        
        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $key => $value) {
            $setting[$value['key']] = $value['value'];
        }
        
        return $this->success([
            "list" => $article->items(),
            'count' => $article->total(),
            "page" => $page,
            "limit" => $limit,
            'contact_us' => $setting['contact_us']
        ]);
    }

    //Get The News Of Commission Refund Rules
    public function getInviteReturn()
    {

        $c_id = 23;//Types Of Returned Commission
        $news = News::where('c_id', $c_id)->orderBy('id', 'desc')->first();
        if (empty($news)) {
            return $this->error('News Doesnt Exist');
        }
        $data['news'] = $news;
        //Related News
        $article = News::where('c_id', $c_id)->where('id', '<>', $news->id)->orderBy('id', 'desc')->get(['id', 'c_id', 'title'])->toArray();

        $data['relation_news'] = $article;
        return $this->success($data);
    }
}
