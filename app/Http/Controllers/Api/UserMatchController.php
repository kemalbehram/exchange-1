<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\UserMatch;

class UserMatchController extends Controller
{
    public function lists(Request $request)
    {
        $user_id = Users::getUserId();
        $limit = 50;
        $user_matches = UserMatch::where('user_id', $user_id)
            ->paginate($limit);
        return $this->success($user_matches);
    }

    public function add(Request $request)
    {
        $user_id = Users::getUserId();
        $currency_match_id = $request->input('id', 0);
        if ($currency_match_id <= 0) {
            return $this->error('Deal ForIDError');
        }
        try {
            $user_match = UserMatch::where('user_id', $user_id)
                ->where('currency_match_id', $currency_match_id)
                ->first();
            if ($user_match) {
                return $this->error('Transaction Pair Has Been Added Optional,Please Do Not Add It Repeatedly');
            }
            UserMatch::unguard();
            $user_match = UserMatch::create([
                'user_id' => $user_id,
                'currency_match_id' => $currency_match_id,
            ]);
            if (!isset($user_match->id)) {
                throw new \Exception('Add Optional Failed');
            }
            return $this->success('Add Optional');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        } finally {
            UserMatch::reguard();
        }
    }

    public function del(Request $request)
    {
        $user_id = Users::getUserId();
        $id = $request->input('id', 0);
        $user_match = UserMatch::where('user_id', $user_id)
            ->where('currency_match_id', $id)
            ->first();
        if (!$user_match) {
            return $this->error('Error:The Specified Optional Transaction Pair Does Not Exist');
        }
        $result = $user_match->delete();
        return $result > 0 ? $this->success('Delete Optional Transaction Pair Succeeded') : $this->error('Failed To Delete Optional Transaction Pair');
    }
}
