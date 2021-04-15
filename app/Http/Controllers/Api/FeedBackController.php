<?php

/**
 * Created by Vscode
 * User: LDH
 * Complaints And Suggestions
 *  */

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\FeedBack;
use App\Models\Users;

class FeedBackController extends Controller
{
    //Feedback Information List
    public function myFeedBackList(Request $request)
    {
        $limit = request()->input('limit', 10);
        $page = request()->input('page', 1);
        $user_id = Users::getUserId();
        $feedBackList = FeedBack::where('user_id', $user_id)
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
        foreach ($feedBackList->items() as &$value) {
            unset($value->replay_content);
        }
        return $this->success(array(
            "list" => $feedBackList->items(), 'count' => $feedBackList->total(),
            "page" => $page, "limit" => $limit
        ));
    }
    //Content Of Feedback Information，Include Reply Message
    public function feedBackDetail()
    {
        $id = request()->input('id', 10);
        $feedBack = FeedBack::find($id);
        return $this->success($feedBack);
    }
    //Submit Feedback
    public function feedBackAdd()
    {
        $user_id = Users::getUserId();
        $content = request()->input('content', '');
        if (empty($content)) {
            return $this->error('Content Cannot Be Empty');
        }
        $img = request()->input('img', '');
        try {
            $feedBack = new FeedBack();
            $feedBack->user_id = $user_id;
            $feedBack->content = $content;
            $feedBack->is_reply = 0;
            $feedBack->img = $img;
            $feedBack->create_time = time();
            $feedBack->save();
            return $this->success('Submitted Successfully，Well Get Back To You As Soon As Possible');
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }
}
