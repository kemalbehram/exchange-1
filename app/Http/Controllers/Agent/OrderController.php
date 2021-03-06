<?php

namespace App\Http\Controllers\Agent;

use App\Exports\FromArrayExport;
use App\Exports\FromQueryExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\{Agent, AgentMoneylog, Currency, LeverTransaction, TransactionComplete, TransactionOrder, Users,UsersWallet};

/**
 * 该类处理所有的订单与结算。
 * Class ReportController
 * @package App\Http\Controllers\Agent
 */
class OrderController extends Controller
{

    //合约订单管理
    public function leverIndex()
    {
        $legal_currencies = Currency::where('is_legal', 1)->get();
        return view("agent.order.leverlist",[
            'legal_currencies' => $legal_currencies,
            
        ]);
    }

    //撮合单
    public function transactionIndex()
    {
        $legal_currencies = Currency::where('is_legal', 1)->get();
        $currencies = Currency::get();
        return view("agent.order.transaction", [
            'legal_currencies' => $legal_currencies,
            'currencies' => $currencies,
        ]);
    }

    //撮合订单
    public function transactionList(Request $request)
    {
        $limit = $request->input('limit', 10);
        //当前代理商信息
        $agent_id = Agent::getAgentId();
        $users = new Users();
        $users = $users->orWhereRaw("FIND_IN_SET($agent_id,`agent_path`)");

        //根据父级ID查询出全部子级
        $childAll = Agent::recursion($agent_id);
        foreach ($childAll as $k=>$v){
            $users = $users->orWhereRaw("FIND_IN_SET($v,`agent_path`)");
        }

        $node_users = $users->pluck('id')->all();

        $account_number = $request->input('account_number', '');


        $result = TransactionComplete::when($account_number != '', function ($query) use ($account_number) {

            $query->whereHas('user', function ($query) use ($account_number) {
                $query->where('account_number', 'like', '%' . $account_number . '%');
            })->orWhereHas('fromUser', function ($query) use ($account_number) {
                $query->where('account_number', 'like', '%' . $account_number . '%');
            });
        })->where(function ($query) use ($request) {
            $legal = $request->input('legal', -1);
            $currency = $request->input('currency', -1);
            $legal != -1 && $query->where('legal', $legal);
            $currency != -1 && $query->where('currency', $currency);
            $start_time = $request->input('start_time', '');
            $end_time = $request->input('end_time', '');
            if (!empty($start_time)) {
                $start_time = strtotime($start_time);
                $query->where('create_time', '>=', $start_time);
            }
            if (!empty($end_time)) {
                $end_time = strtotime($end_time);
                $query->where('create_time', '<=', $end_time);
            }
        })->where(function ($query) use ($node_users) {
            $query->where(function ($query) use ($node_users) {
                $query->whereIn('user_id', $node_users);
            })->orwhere(function ($query) use ($node_users) {
                $query->whereIn('from_user_id', $node_users);
            });
            //})->orderBy('id', 'desc')->toSql();
            //dd($result);
        })->orderBy('id', 'desc')->paginate($limit);
        $sum = $result->sum('number');
        return $this->layuiData($result, $sum);
    }


    //合约
    public function order_list(Request $request)
    {

        $limit = $request->input("limit", 10);
        $id = $request->input("id", 0);
        $username = $request->input("username", '');
        $agentusername = $request->input("agentusername", '');
        $status = $request->input("status", 10);
        $type = $request->input("type", 0);

        $start = $request->input("start", '');
        $end = $request->input("end", '');
        $legal_id = $request->input("legal_id",-1);


        //当前代理商信息
        $agent_id = Agent::getAgentId();


        if ($agentusername != '') {

            $search_agent = Agent::where('username', $agentusername)->first();
            if (!$search_agent) {
                return $this->error('代理商不存在');
            }

            $parent_agent = explode(',', $search_agent->agent_path);

            if (!in_array($agent_id, $parent_agent)) {
                return $this->error('该代理商并不属于您的团队');
            }

            $now_agent_id = $search_agent->id;
        } else {
            $now_agent_id = $agent_id;
        }

        $query = TransactionOrder::whereHas('user', function ($query) use ($username) {

            $username != '' && $query->where('account_number', $username)->orWhere('phone', $username);
        })->where(function ($query) use ($id, $status, $type) {

            $id != 0 && $query->where('id', $id);

            $status != 10 && in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING]) && $query->where('status', $status);

            $type > 0 && in_array($type, [1, 2]) && $query->where('type', $type);
        })->where(function ($query) use ($start, $end) {

            !empty($start) && $query->where('create_time', '>=', strtotime($start . ' 0:0:0'));

            !empty($end) && $query->where('create_time', '<=', strtotime($end . ' 23:59:59'));
        })->where(function ($query) use ($now_agent_id) {

            $now_agent_id > 0 && $query->orWhereRaw("FIND_IN_SET($now_agent_id,`agent_path`)");

            if($now_agent_id > 0){
                //根据父级ID查询出全部子级
                $childAll = Agent::recursion($now_agent_id);
                foreach ($childAll as $k=>$v){
                    $query->orWhereRaw("FIND_IN_SET($v,`agent_path`)");
                }
            }
        })->when($legal_id > 0, function ($query) use ($legal_id) {

            $query->where('legal',$legal_id);
            
        });



        // $query_total = clone $query;
        // $total = $query_total->select([
        //     //DB::raw('sum(fact_profits) as balance1'),
        //     DB::raw('SUM((CASE `type` WHEN 1 THEN `update_price` - `price` WHEN 2 THEN `price` - `update_price` END) * `number`) AS `balance1`'),
        //     DB::raw('sum(origin_caution_money) as balance2'),
        //     DB::raw('sum(trade_fee) as balance3'),

        // ])->first();
        // $total = $total->setAppends([]);

        $order_list = $query->orderBy('id', 'desc')->paginate($limit);
        

        return $this->layuiData($order_list);
    }


    /**
     *获取合约统计数据
     */
    public function get_order_account(Request $request)
    {

        $id = $request->input("id", 0);
        $username = $request->input("username", '');
        $agentusername = $request->input("agentusername", '');
        $status = $request->input("status", 10);
        $type = $request->input("type", 0);

        $start = $request->input("start", '');
        $end = $request->input("end", '');

        $legal_id = $request->input("legal_id",-1);

        //当前代理商信息
        $agent_id = Agent::getAgentId();


        if ($agentusername != '') {

            $search_agent = Agent::where('username', $agentusername)->first();
            if (!$search_agent) {
                return $this->error('代理商不存在');
            }

            $parent_agent = explode(',', $search_agent->agent_path);

            if (!in_array($agent_id, $parent_agent)) {
                return $this->error('该代理商并不属于您的团队');
            }

            $now_agent_id = $search_agent->id;
        } else {
            $now_agent_id = $agent_id;
        }

        $query1 = TransactionOrder::whereHas('user', function ($query) use ($username) {

            $username != '' && $query->where('account_number', $username)->orWhere('phone', $username);
        })->where(function ($query) use ($id, $status, $type) {

            $id != 0 && $query->where('id', $id);

            $status != 10 && in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING]) && $query->where('status', $status);

            $type > 0 && in_array($type, [1, 2]) && $query->where('type', $type);
        })->where(function ($query) use ($start, $end) {

            !empty($start) && $query->where('create_time', '>=', strtotime($start . ' 0:0:0'));

            !empty($end) && $query->where('create_time', '<=', strtotime($end . ' 23:59:59'));
        })->where(function ($query) use ($now_agent_id) {

            $now_agent_id > 0 && $query->whereRaw("FIND_IN_SET($now_agent_id,`agent_path`)");

            if($now_agent_id > 0){
                //根据父级ID查询出全部子级
                $childAll = Agent::recursion($now_agent_id);
                foreach ($childAll as $k=>$v){
                    $query->orWhereRaw("FIND_IN_SET($v,`agent_path`)");
                }
            }
        })->when($legal_id > 0, function ($query) use ($legal_id) {

            $query->where('legal',$legal_id);
            
        });

        //可用保证金 未平仓
        $_lock = $query1->selectRaw('sum(if(status <= 2,caution_money,0)) as caution_money')->value('caution_money') ?? 0;

        //总订单数
        $_count = $query1->count();

        //头寸收益（平仓最终盈亏） 
        $_toucun =$query1->whereIn('status', [LeverTransaction::CLOSED])->sum('fact_profits');
        //手续费收益（已平仓手续费）
        $_shouxu = $query1->whereIn('status', [LeverTransaction::CLOSED])->sum('trade_fee');

        //查询当前代理商的头寸  手续费百分比
        $now_agent=Agent::getAgentById($now_agent_id);

        $data = [];
        $data['_num'] = $_count;
        $data['_toucun'] = bc_mul(bc_mul($_toucun,$now_agent->pro_loss/100),-1);// 乘以代理商头寸百分比 取负数
        $data['_shouxu'] =bc_mul($_shouxu,$now_agent->pro_ser/100);// 乘以代理商手续费百分比

        $_all = bc_add($data['_toucun'], $data['_shouxu']);

        $data['_all'] = $_all;
        //可用保证金
        $data['_lock'] = $_lock;



        return $this->ajaxReturn($data);
    }


    /**
     * 获取该用户的团队所有的订单
     */
    public function get_my_all_orders()
    {
        $_self = $this->get_my_sons();

        $all = TransactionOrder::whereIn('status', [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING])->whereIn('user_id', $_self['all'])->get()->toArray();

        $data = [];
        $ids = [];
        $account_numbers = [];

        if (!empty($all)) {
            foreach ($all as $key => $value) {
                $ids[] = $value['id'];

                $info = DB::table('users')->where('id', $value['user_id'])->first();
                if ($info) {
                    $account_numbers[] = $info->account_number;
                }
            }
            $data['ids'] = $ids;
            $data['account_number'] = $account_numbers;
        }

        return $data;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    //导出订单记录Excel 
    public function order_excel(Request $request)
    {
        $limit = $request->input("limit", 10);
        $id = $request->input("id", 0);
        $username = $request->input("username", '');
        $agentusername = $request->input("agentusername", '');
        $status = $request->input("status", 10);
        $type = $request->input("type", 0);

        $start = $request->input("start", '');
        $end = $request->input("end", '');
        $legal_id = $request->input("legal_id",-1);
        //echo $id.'-'.$username.'-'.$agentusername.'-'.$status.'-'. $type.'-'.$start.'-'.$end;exit;

        /*
        $where = [];
        if ($id > 0){
            $where[] = ['id' , '=' , $id];
        }
        if (!empty($username)){
            $s = DB::table('users')->where('account_number' , $username)->first();
            if ($s !== null){
                $where[] = ['user_id' , '=' , $s->id];
            }
        }
        if ($status  != 10   && in_array($status , [LeverTransaction::ENTRUST,LeverTransaction::TRANSACTION,LeverTransaction::CLOSED,LeverTransaction::CANCEL,LeverTransaction::CLOSING])){
            $where[] = ['status' , '=' , $status];
        }
        if ($type > 0 && in_array($type , [1,2])){
            $where[] = ['type' , '=' , $type];
        }
        if (!empty($start) && !empty($end)) {
            $where[] = ['create_time' , '>' , strtotime($start . ' 0:0:0')];
            $where[] = ['create_time' , '<' , strtotime($end . ' 23:59:59')];
        }

        $_self = Agent::getAgent();
        if ($_self === null){
            return $this->outmsg('发生错误！请重新登录');
        }
        $sons = $this->get_my_sons();

        if (!empty($agentusername)){
            $s = DB::table('agent')->where('username' , $agentusername)->first();

            if (!in_array($s->id , $sons['all'])){
                return $this->error('该代理商并不属于您的团队');
            }else{

                $p_s_s = $this->get_my_sons($s->id);

                if (!empty($p_s_s)){
                    $order_list = TransactionOrder::whereIn('status' , [LeverTransaction::ENTRUST,LeverTransaction::TRANSACTION,LeverTransaction::CLOSED,LeverTransaction::CANCEL,LeverTransaction::CLOSING])->whereIn('user_id' , $p_s_s['all'])->where($where)->get()->toArray();
                }else{

                    $order_list = TransactionOrder::whereIn('status' , [LeverTransaction::ENTRUST,LeverTransaction::TRANSACTION,LeverTransaction::CLOSED,LeverTransaction::CANCEL,LeverTransaction::CLOSING])->whereIn('user_id' , $sons['all'])->where($where)->get()->toArray();

                }
            }
        }else{

            $order_list = TransactionOrder::whereIn('status' , [LeverTransaction::ENTRUST,LeverTransaction::TRANSACTION,LeverTransaction::CLOSED,LeverTransaction::CANCEL,LeverTransaction::CLOSING])->whereIn('user_id' , $sons['all'])->where($where)->get()->toArray();


        }
        */

        //当前代理商信息
        $agent_id = Agent::getAgentId();


        if ($agentusername != '') {

            $search_agent = Agent::where('username', $agentusername)->first();
            if (!$search_agent) {
                return $this->error('代理商不存在');
            }

            $parent_agent = explode(',', $search_agent->agent_path);

            if (!in_array($agent_id, $parent_agent)) {
                return $this->error('该代理商并不属于您的团队');
            }

            $now_agent_id = $search_agent->id;
        } else {
            $now_agent_id = $agent_id;
        }

        $query = TransactionOrder::whereHas('user', function ($query) use ($username) {

            $username != '' && $query->where('account_number', $username)->orWhere('phone', $username);
        })->where(function ($query) use ($id, $status, $type) {

            $id != 0 && $query->where('id', $id);

            $status != 10 && in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING]) && $query->where('status', $status);

            $type > 0 && in_array($type, [1, 2]) && $query->where('type', $type);
        })->where(function ($query) use ($start, $end) {

            !empty($start) && $query->where('create_time', '>=', strtotime($start . ' 0:0:0'));

            !empty($end) && $query->where('create_time', '<=', strtotime($end . ' 23:59:59'));
        })->where(function ($query) use ($now_agent_id) {

            $now_agent_id > 0 && $query->whereRaw("FIND_IN_SET($now_agent_id,`agent_path`)");
        })->when($legal_id > 0, function ($query) use ($legal_id) {

            $query->where('legal',$legal_id);
            
        });
        $order_list = $query->orderBy('id', 'desc')->get()->toArray();
        $data = $order_list;
        return Excel::download(new FromArrayExport($data), '订单数据.xlsx');
    }


    //导出用户记录Excel
    public function user_excel(Request $request)
    {

        $id             = request()->input('id', 0);
        $parent_id            = request()->input('parent_id', 0);
        $account_number = request()->input('account_number', '');
        $start = request()->input('start', '');
        $end = request()->input('end', '');

        $users = new Users();

        if ($id) {
            $users = $users->where('id', $id);
        }
        if ($parent_id > 0) {
            $users = $users->where('agent_note_id', $parent_id);
        }
        if ($account_number) {
            $users = $users->where('account_number', $account_number);
        }
        if (!empty($start) && !empty($end)) {
            $users->whereBetween('time', [strtotime($start . ' 0:0:0'), strtotime($end . ' 23:59:59')]);
        }

        // $my_agent_list = Agent::getLevel4AgentId(Agent::getAgentId(), [Agent::getAgentId()]);

        // $users = $users->whereIn('agent_note_id', $my_agent_list);

        $agent_id = Agent::getAgentId();
        $users = $users->whereRaw("FIND_IN_SET($agent_id,`agent_path`)");
        $query = $users->query();
        return Excel::download(new FromQueryExport($query), '用户列表.xlsx');
    }

    /**
     * 结算列表首页
     */
    public function jieIndex(Request $request){
        //法币
        $legal_currencies = Currency::where('is_legal', 1)->get();
        //下级代理
        $son_agents = Agent::getAllChildAgent(Agent::getAgentId());
        $self=Agent::getAgent();
        $is_admin=$self?$self->is_admin:0;
        return view('agent.order.jie_index',[
            'legal_currencies' => $legal_currencies,
            'son_agents' => $son_agents,
            'is_admin'=>$is_admin
        ]);
    }

    public function jie_list(Request $request)
    {

        $limit = $request->input("limit", 10);
        $start = $request->input("start", '');
        $end = $request->input("end", '');

        $agent_id = Agent::getAgentId();
        //$node_users = Users::whereRaw("FIND_IN_SET($agent_id,`agent_path`)")->pluck('id')->all();
        $child_agents = Agent::getAllChildAgent($agent_id);
        $son_agents = $child_agents->pluck('id')->all();
      
        $lists = AgentMoneylog::whereIn('agent_id', $son_agents)
            ->where(function ($query) use ($request) {

                $id = $request->input("id", 0);
                $username = $request->input("username", '');
                $belong_agent = $request->input('belong_agent', '');
                $legal_id = $request->input('legal_id', -1);
                
                $type = $request->input("type", -1);//1 头寸  2手续费

                $query->when($id > 0,function($query) use ($id){
                    $query->where('id',$id);
                })->when($username != '', function ($query) use ($username) {
                    $query->whereHas('user', function ($query) use ($username) {
                        $query->where('account_number', $username);
                    });
                })->when($belong_agent != '', function ($query) use ($belong_agent) {
                    $query->whereHas('agent', function ($query) use ($belong_agent) {
                        $query->where('username', $belong_agent);    
                    });
                })->when($legal_id > 0, function ($query) use ($legal_id) {
                    $query->where('legal_id', $legal_id);
                })->when($type > 0, function ($query) use ($type) {
                    $query->where('type', $type);
                });
            })->where(function ($query) use ($start, $end) {

                !empty($start) && $query->where('created_time', '>=', strtotime($start . ' 0:0:0'));
    
                !empty($end) && $query->where('created_time', '<=', strtotime($end . ' 23:59:59'));
            })
            ->orderBy('id', 'desc')
            ->paginate($limit);

        return $this->layuiData($lists);
    }


    /**
     * 订单详情
     */
    public function order_info(Request $request)
    {
        $order_id = $request->input("order_id", 0);

        if ($order_id > 0) {
            // $sons = $this->get_my_sons();

            //$orderinfo = TransactionOrder::where('id', $order_id)->whereIn('user_id', $sons['all'])->first();
            $orderinfo = LeverTransaction::where('id', $order_id)->first();

            if (empty($orderinfo)) {
                return $this->error('订单编号错误或者您无权查看订单详情');
            } else {
                //dd($orderinfo);
                return view("agent.order.info", ['info' => $orderinfo]);
                // $data['info'] = $orderinfo;
                // return $this->ajaxReturn($data);
            }
        } else {
            return $this->error('非法参数');
        }

      
        
    }

    /**
     * 获取我的所有的下级。包括所有的散户和各级代理商
     */
    public function get_my_sons($agent_id = 0)
    {

        if ($agent_id === 0) {
            $_self = Agent::getAgent();
        } else {
            $_self = Agent::getAgentById($agent_id);
        }

        $_one = [];
        $_one_sons = [];
        $_two = [];
        $_two_sons = [];
        $_three = [];
        $_three_sons = [];
        $_four = [];
        $_four_sons = [];
        switch ($_self->level) {
            case 0:
                $_one = DB::table('agent')->where('level', 1)->select('user_id', 'id')->get()->toArray();
                $_two = DB::table('agent')->where('level', 2)->select('user_id', 'id')->get()->toArray();
                $_three = DB::table('agent')->where('level', 3)->select('user_id', 'id')->get()->toArray();
                $_four = DB::table('agent')->where('level', 4)->select('user_id', 'id')->get()->toArray();
                break;

            case 1:
                $_two = DB::table('agent')->where('parent_agent_id', $_self->id)->get()->toArray();
                $_one_sons = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $_self->id)->get()->toArray();

                if (!empty($_two)) {
                    foreach ($_two as $key => $value) {
                        $_a = DB::table('agent')->where('parent_agent_id', $value->id)->get()->toArray();
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_three = array_merge($_three, $_a);
                        $_two_sons = array_merge($_two_sons, $_b);
                    }
                }

                if (!empty($_three)) {
                    foreach ($_three as $key => $value) {
                        $_a = DB::table('agent')->where('parent_agent_id', $value->id)->get()->toArray();
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_four = array_merge($_four, $_a);
                        $_three_sons = array_merge($_three_sons, $_b);
                    }
                }

                if (!empty($_four)) {
                    foreach ($_four as $key => $value) {
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_three_sons = array_merge($_three_sons, $_b);
                    }
                }

                break;
            case 2:
                $_three = DB::table('agent')->where('parent_agent_id', $_self->id)->get()->toArray();
                $_two_sons = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $_self->id)->get()->toArray();
                if (!empty($_two)) {
                    foreach ($_two as $key => $value) {
                        $_a = DB::table('agent')->where('parent_agent_id', $value->id)->get()->toArray();
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_four = array_merge($_four, $_a);
                        $_three_sons = array_merge($_three_sons, $_b);
                    }
                }

                if (!empty($_four)) {
                    foreach ($_four as $key => $value) {
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_four_sons = array_merge($_four_sons, $_b);
                    }
                }
                break;
            case 3:
                $_four = DB::table('agent')->where('parent_agent_id', $_self->id)->get()->toArray();
                $_three_sons = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $_self->id)->get()->toArray();

                if (!empty($_four)) {
                    foreach ($_four as $key => $value) {
                        $_b = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $value->id)->get()->toArray();
                        $_four_sons = array_merge($_four_sons, $_b);
                    }
                }
                break;
            case 4:
                $_four_sons = DB::table('users')->where('agent_id', 0)->where('agent_note_id', $_self->id)->get()->toArray();
                break;
        }

        if ($_self->level == 0  && $_self->is_admin == 1) {
            $san_user = DB::table('users')->where('agent_id', 0)->get()->toArray();  //所有的散户
            $san_user = $this->sel_agent_arr($san_user);
        } else {
            $a = $this->sel_agent_arr($_one_sons);
            $b = $this->sel_agent_arr($_two_sons);
            $c = $this->sel_agent_arr($_three_sons);
            $d = $this->sel_agent_arr($_four_sons);
            $san_user = array_merge($a, $b, $c, $d);
        }


        $data = [];
        $data['san'] = $san_user;
        $data['one'] = $this->sel_arr($_one);
        $data['one_agent'] = $this->sel_agent_arr($_one);
        $data['two'] = $this->sel_arr($_two);
        $data['two_agent'] = $this->sel_agent_arr($_two);
        $data['three'] = $this->sel_arr($_three);
        $data['three_agent'] = $this->sel_agent_arr($_three);
        $data['four'] = $this->sel_arr($_four);
        $data['four_agent'] = $this->sel_agent_arr($_four);
        $all = array_merge($data['san'], $data['one'], $data['two'], $data['three'], $data['four']);
        $data['all'] = !empty($all) ? $all : [0];

        $all_agent = array_merge($data['one_agent'], $data['two_agent'], $data['three_agent'], $data['four_agent']);
        $data['all_agent'] = !empty($all_agent) ? $all_agent : [0];

        return $data;
    }

    /**
     * @param $san_user
     *
     */
    public function sel_arr($arr = array())
    {
        if (!empty($arr)) {
            $new_arr = [];
            foreach ($arr as $k => $val) {
                $new_arr[] = $val->user_id;
            }
            return $new_arr;
        } else {
            return [];
        }
    }

    /**
     * @param $san_user
     *
     */
    public function sel_agent_arr($arr = array())
    {
        if (!empty($arr)) {
            $new_arr = [];
            foreach ($arr as $k => $val) {
                $new_arr[] = $val->id;
            }
            return $new_arr;
        } else {
            return [];
        }
    }

    //我的合约订单
    public function userLeverIndex(Request $request)
    {
        $id = $request->input('id', null);
        if (empty($id)) {
            return $this->error('参数错误');
        }

        return view("agent.user.leverlist", ['user_id' => $id]);
    }

    public function userLeverList(Request $request)
    {

        $limit = $request->input("limit", 10);

        $status = $request->input("status", 10);
        $type = $request->input("type", 0);

        $start = $request->input("start", '');
        $end = $request->input("end", '');
        $user_id = $request->input("user_id", '');


        $query = TransactionOrder::where('user_id', $user_id)->where(function ($query) use ($status, $type) {


            $status != 10 && in_array($status, [LeverTransaction::ENTRUST, LeverTransaction::TRANSACTION, LeverTransaction::CLOSED, LeverTransaction::CANCEL, LeverTransaction::CLOSING]) && $query->where('status', $status);

            $type > 0 && in_array($type, [1, 2]) && $query->where('type', $type);
        })->where(function ($query) use ($start, $end) {

            !empty($start) && $query->where('create_time', '>=', strtotime($start . ' 0:0:0'));

            !empty($end) && $query->where('create_time', '<=', strtotime($end . ' 23:59:59'));
        });


        $order_list = $query->orderBy('id', 'desc')->paginate($limit);

        return $this->layuiData($order_list);
    }
}
