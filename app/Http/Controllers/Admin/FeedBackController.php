<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FromQueryExport;
use Illuminate\Http\Request;
use App\Models\FeedBack;
use Maatwebsite\Excel\Facades\Excel;

class FeedBackController extends Controller
{
    public function index(){
        return view('admin.feedback.index');
    }
    public function feedbackList(Request $request){
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);
        $account_number = $request->input('account_number', '');
        $feedback = new FeedBack();
        if(!empty($account_number)){
            $feedback = $feedback->whereHas('user', function ($query) use ($account_number) {
                $query->where("account_number", 'like', '%' . $account_number . '%');
            });
        }
        $feedbackList = $feedback->orderBy('id', 'desc')->paginate($limit, ['*'], 'page', $page);
        // dd($result);
        return $this->layuiData($feedbackList);
    }
    public function feedBackDetail(Request $request){
        $id = $request->input('id', '');
        $feedback = FeedBack::find($id);
        return view('admin.feedback.detail',['feedback'=> $feedback]);
    }
    public function feedBackDel(Request $request){
        $id = $request->input('id', '');
        $res = FeedBack::deatory($id);
        if($res){
            return $this->success('删除成功');
        }else{
            return $this->error('请重试');
        }
    }
    public function reply(Request $request){
        $id = $request->input('id', '');
        $reply_content = $request->input('reply_content', '');
        if(empty($id)||empty($reply_content)){
            return $this->error('参数错误');
        }
        $feedback = FeedBack::find($id);
        $feedback->reply_content = $reply_content;
        $feedback->is_reply = 1;
        $feedback->reply_time = time();
        $feedback->save();
        return $this->success('回复成功');
    }
    //导出用户列表至excel
    public function csv()
    {
        $builder = FeedBack::query();
        return Excel::download(new FromQueryExport($builder), '反馈数据.xlsx');
    }
}