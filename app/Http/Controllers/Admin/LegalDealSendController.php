<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\{Currency, LegalDealSend};

class LegalDealSendController extends Controller
{
    public function all()
    {
        return view('admin.legal.all');
    }

    public function index()
    {
        $currency = Currency::where('is_legal', 1)
            ->orderBy('id', 'desc')
            ->get(); //获取法币
        return view('admin.legal.index', [
            'currency' => $currency
        ]);
    }

    public function list(Request $request)
    {
        $limit = $request->input('limit', 10);
        //$account_number = $request->input('account_number', '');
        $seller_name = $request->input('seller_name', '');
        $type = $request->input('type', '');
        $currency_id = $request->input('currency_id', 0);
        $is_done = $request->input('is_done', '');
        $result = new LegalDealSend();

        if (!empty($seller_name)) {

            $result = $result->whereHas('seller', function ($query) use ($seller_name) {
                $query->where('name', 'like', '%' . $seller_name . '%');
            });
        }
        $start_time = $request->input('start_time', '');
        $end_time = $request->input('end_time', '');
        if (!empty($start_time)) {
            $start_time = strtotime($start_time);
            $result = $result->where('create_time', '>=', $start_time);
        }
        if (!empty($end_time)) {
            $end_time = strtotime($end_time);
            $result = $result->where('create_time', '<=', $end_time);
        }
        if (!empty($is_done)) {
            $result = $result->where('is_done', $is_done);
        }
        if (!empty($type)) {

            $result = $result->where('type', $type);
        }
        if (!empty($currency_id)) {
            $result = $result->where('currency_id', $currency_id);
        }
        $result = $result->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('total_number');
        return $this->layuiData($result, $sum);
    }
}
