<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use App\Models\Seller;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function lists(Request $request){
        $limit = $request->input('limit',10);
        $currency_id = $request->input('currency_id',0);
        if (empty($currency_id)){
            return $this->error('Parameter Error');
        }
        $currency = Currency::find($currency_id);
        if (empty($currency)){
            return $this->error('No Such Currency');
        }
        if (empty($currency->is_legal)){
            return $this->error('The Currency Is Not Legal');
        }
        $results = Seller::where('currency_id',$currency->id)->orderBy('id','desc')->paginate($limit);
        return $this->pageDate($results);
    }
}
