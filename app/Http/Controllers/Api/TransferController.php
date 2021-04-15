<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{AccountLog, Currency, Transfer, Users, UsersWallet};

class TransferController extends Controller
{
    public function getAllowTransferFee()
    {
        $currencies = Currency::where('allow_transfer', 1)->all();
        return $this->success($currencies);
    }

    public function submit(Request $request)
    {
        $user_id = Users::getUserId();
        $currency_id = $request->input('currency_id', 0);
        $account_number = $request->input('account_number', '');
        $number = $request->input('number', 0);
        $memo = $request->input('memo', '');
        try {
            DB::beginTransaction();
            $to_user = Users::getByString($account_number);
            if (empty($to_user)) {
                throw new \Exception('User Not Found');
            }
            if ($to_user->id == $user_id) {
                throw new \Exception('You Are Not Allowed To Transfer Money To Yourself');
            }
            $currency = Currency::findOrFail($currency_id);
            if (!$currency->allow_transfer) {
                throw new \Exception('Internal Transfer Is Not Allowed In Current Currency');
            }
            if (bc_comp_zero($number) <= 0) {
                throw new \Exception('Please Input Quantity Must Be Greater Than0');
            }
            
            $from_wallet = UsersWallet::where('user_id', $user_id)
                ->where('currency', $currency_id)
                ->lockForUpdate()
                ->first();
            $to_wallet = UsersWallet::where('user_id', $to_user->id)
                ->where('currency', $currency_id)
                ->lockForUpdate()
                ->first();
            if (!$from_wallet) {
                throw new \Exception('Your' . $currency->name . 'The Wallet Doesnt Exist');
            }
            if (!$to_wallet) {
                throw new \Exception('Receiver' . $currency->name . 'The Wallet Doesnt Exist');
            }
            //1.Legal Currency,2.Currency Transaction,3.Contract Transaction
            $from_result = change_wallet_balance($from_wallet, 2, -$number, AccountLog::TRANSFER_TO, "Transfer Out By Internal Transfer");
            if ($from_result !== true) {
                throw new \Exception($from_result);
            }
            $to_result = change_wallet_balance($to_wallet, 2, $number, AccountLog::TRANSFER_TO, "On Site Transfer Collection");
            if ($to_result !== true) {
                throw new \Exception($to_result);
            }
            $transfer_data = [
                'currency_id' => $currency_id,
                'from_user_id' => $user_id,
                'to_user_id' => $to_user->id,
                'from_number' => $number,
                'to_number' => $number,
                'fact_fee' => 0,
                'memo' => $memo,
            ];
            Transfer::unguarded(function () use ($transfer_data) {
                return Transfer::create($transfer_data);
            });
            DB::commit();
            return $this->success('On Site Transaction Successful');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Operation Failed:' . $e->getMessage());
        }
    }

    public function logs(Request $request)
    {
        $user_id = Users::getUserId();
        $limit = $request->input('limit', 10);
        $logs = Transfer::where('from_user_id', $user_id)
            ->where(function ($query) use ($request) {
                $currency_id = $request->input('currency_id', 0);
                $currency_id > 0 && $query->where('currency_id', $currency_id);
            })
            ->orderBy('id', 'desc')
            ->paginate($limit);
        return $this->submit($logs);
    }
}
