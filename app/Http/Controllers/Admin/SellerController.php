<?php

namespace App\Http\Controllers\Admin;


use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\{AccountLog, Bank, Currency, LegalDeal, LegalDealSend, Seller, SellerAccountLog, UserReal, Users};

class SellerController extends Controller
{
    public function index()
    {
        $currencies = Currency::where('is_legal', 1)->get();
        return view('admin.seller.index', [
            'currencies' => $currencies,
        ]);
    }

    public function lists(Request $request)
    {
        $limit = $request->input('limit', 10);
        $currency_id = $request->input('currency_id', 0);
        $account_number = $request->input('account_number', '');
        $result = new Seller();
        if (!empty($account_number)) {
            $users = Users::where('account_number', 'like', '%' . $account_number . '%')->get()->pluck('id');
            $result = $result->whereIn('user_id', $users);
        }
        if ($currency_id > 0) {
            $result = $result->where('currency_id', $currency_id);
        }
        $result = $result->orderBy('id', 'desc')->paginate($limit);
        return $this->layuiData($result);
    }

    public function add(Request $request)
    {
        $id = $request->input('id', 0);
        $acceptor = Seller::findOrNew($id);
        $banks = Bank::all();
        $currencies = Currency::where('is_legal', 1)
            ->orderBy('id', 'desc')
            ->get();
        return view('admin.seller.add', [
            'result' => $acceptor,
            'banks' => $banks,
            'currencies' => $currencies,
        ]);
    }

    public function postAdd(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $account_number = $request->input('account_number', '') ?? '';
            $name = $request->input('name', '') ?? '';
            $mobile = $request->input('mobile', '') ?? '';
            $currency_id = $request->input('currency_id', '') ?? '';
            $seller_balance = $request->input('seller_balance', 0) ?? 0;
            $wechat_nickname = $request->input('wechat_nickname', '') ?? '';
            $wechat_account = $request->input('wechat_account', '') ?? '';
            $wechat_collect = $request->input('wechat_collect', '') ?? '';
            $ali_nickname = $request->input('ali_nickname', '') ?? '';
            $ali_account = $request->input('ali_account', '') ?? '';
            $alipay_collect = $request->input('alipay_collect', '') ?? '';
            $bank_id = $request->input('bank_id', 0) ?? 0;
            $bank_account = $request->input('bank_account', '') ?? '';
            $bank_address = $request->input('bank_address', '') ?? '';
            //自定义验证错误信息
            $messages = [
                'required' => ':attribute 为必填字段',
            ];
            $validator = Validator::make($request->all(), [
                'account_number' => 'required',
                'name' => 'required',
                'mobile' => 'required',
                'currency_id' => 'required',
                'seller_balance' => 'required',
                'wechat_nickname' => 'required',
                'wechat_account' => 'required',
                'wechat_collect' => 'required',
                'ali_nickname' => 'required',
                'ali_account' => 'required',
                'alipay_collect' => 'required',
                'bank_id' => 'required',
                'bank_account' => 'nullable',
                'bank_address' => 'nullable',
            ], $messages);
            //如果验证不通过
            throw_if($validator->fails(), new \Exception($validator->errors()->first()));
            $self = Users::where('account_number', $account_number)->first();
            throw_if(empty($self), new \Exception('找不到此交易账号的用户'));
            $real = UserReal::where('user_id', $self->id)->where('review_status', 2)->first();
            throw_if(empty($real), new \Exception('此用户还未通过实名认证'));
            $currency = Currency::find($currency_id);
            throw_if(empty($currency), new \Exception('币种不存在'));
            throw_if(empty($currency->is_legal), new \Exception('该币不是法币'));
            $has = Seller::where('name', $name)
                ->where('user_id', '<>', $self->id)
                ->where('currency_id', $currency_id)
                ->first();
            if (empty($id) && !empty($has)) {
                throw new \Exception("法币商家名称:`{$name}`已存在");
            }
            $has_user = Seller::where('user_id', $self->id)
                ->where('currency_id', $currency_id)
                ->first();
            if (!empty($has_user) && empty($id)) {
                throw new \Exception('此用户已是该法币商家');
            }
            if (empty($id)) {
                $acceptor = new Seller();
                $acceptor->create_time = time();
            } else {
                $acceptor = Seller::find($id);
            }
            $acceptor->user_id = $self->id;
            $acceptor->name = $name;
            $acceptor->mobile = $mobile;
            $acceptor->currency_id = $currency_id;
            $acceptor->wechat_nickname = $wechat_nickname;
            $acceptor->wechat_account = $wechat_account;
            $acceptor->wechat_collect = $wechat_collect;
            $acceptor->ali_nickname = $ali_nickname;
            $acceptor->alipay_collect = $alipay_collect;
            $acceptor->ali_account = $ali_account;
            $acceptor->bank_id = intval($bank_id);
            $acceptor->bank_account = $bank_account;
            $acceptor->bank_address = $bank_address;
            $acceptor->save();
            if (empty($id)) {
                // 新增的才允许直接编辑商家余额
                change_seller_balance(
                    $acceptor,
                    $seller_balance,
                    AccountLog::ADMIN_SELLER_BALANCE,
                    '后台添加商家,初始化余额'
                );
            }
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function adjustBalance(Request $request)
    {
        $id = $request->input('id', 0);
        $seller = Seller::findOrNew($id);
        return view('admin.seller.adjust_balance', [
            'seller' => $seller,
        ]);
    }

    public function postAdjustBalance(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $type = $request->input('type', '');
            $change = $request->input('change', 0);
            $memo = $request->input('memo', '');
            throw_if(bc_comp_zero($change) == 0, new \Exception('调整金额不能为0'));
            throw_unless(in_array($type, ['seller_balance', 'lock_seller_balance']), new \Exception('余额类型不正确'));
            DB::transaction(function () use ($id, $change, $memo, $type) {
                $seller = Seller::lockForUpdate()->findOrFail($id);
                change_seller_balance(
                    $seller,
                    $change,
                    AccountLog::ADMIN_SELLER_BALANCE,
                    "系统调节商家余额:{$memo}",
                    $type == 'lock_seller_balance' ? true : false
                );
            });
            return $this->success('调节成功');
        } catch(\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return $this->error($ex->getMessage() . "数据未找到");
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function logs()
    {
        $currencies = Currency::where('is_legal', 1)->get();
        return view('admin.seller.logs', [
            'currencies' => $currencies,
        ]);
    }

    public function logsList(Request $request)
    {
        $limit = $request->input('limit', 10);
        $lists = SellerAccountLog::where(function ($query) use ($request) {
            $currency_id = $request->input('currency_id', 0);
            $is_lock = $request->input('is_lock', -1);
            $start_time = $request->input('start_time', '');
            $end_time = $request->input('end_time', '');
            $seller_name = $request->input('seller_name', '');
            $account_number = $request->input('account_number', '');
            $start_time != '' && $query->where('created_at', '>=', Carbon::createFromTimestamp(strtotime($start_time)));
            $end_time != '' && $query->where('created_at', '<=', Carbon::createFromTimestamp(strtotime($end_time)));
            $currency_id > 0 && $query->where('currency_id', $currency_id);
            $is_lock != -1 && $query->where('is_lock', $is_lock);
            $seller_name != '' && $query->whereHas('seller', function ($query) use ($seller_name) {
                $query->where('name', $seller_name);
            });
            $account_number != '' && $query->whereHas('user', function ($query) use ($account_number) {
                $query->where('account_number', $account_number);
            });
        })->orderBy('id', 'desc')->paginate($limit);
        return $this->layuiData($lists);
    }

    public function delete(Request $request)
    {
        $id = $request->input('id', 0);
        
        try {
            $acceptor = Seller::find($id);
            if (empty($acceptor)) {
                return $this->error('无此用户');
            }
            // 检测是否有发布过需求
            $exist_send = LegalDealSend::where('seller_id', $acceptor->id)->where('is_done', 0)->exists();
            if ($exist_send) {
                throw new \Exception('该商家已经发布了需求,无法删除!');
            }
            $acceptor->delete();
            return $this->success('删除成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function sendBack(Request $request)
    {
        $id = $request->input('id', 0);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $send = LegalDealSend::find($id);
        if (empty($send)) {
            return $this->error('无此记录');
        }
        try {
            LegalDealSend::sendBack($id, 3);
            return $this->success('发布撤回成功!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    public function sendDel(Request $request)
    {
        $id = $request->input('id', 0);
        DB::beginTransaction();
        try {
            return $this->error('该功能过于危险无法使用');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }

    public function is_shelves(Request $request)
    {
        $id = $request->input('id', 0);
        $is_shelves = $request->input('is_shelves', 1);
        if (empty($id)) {
            return $this->error('参数错误');
        }
        $send = LegalDealSend::find($id);
        if (empty($send)) {
            return $this->error('无此记录');
        }
        if (empty($send->is_shelves)) {
            $send->is_shelves = 1;
            $send->save();
        }
        DB::beginTransaction();
        try {
            if ($send->is_shelves == 1) {
                $send->is_shelves = 2;
            } elseif ($send->is_shelves == 2) {
                $send->is_shelves = 1;
            }
            $send->save();
            DB::commit();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            DB::rollback();
            return $this->error($exception->getMessage());
        }
    }
}
