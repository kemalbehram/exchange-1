<?php

//代理商管理员操作后台
Route::namespace('Agent')->group(function () {
    Route::get('agent/index', 'AgentController@index')->name('agent');
    Route::get('agent', 'AgentController@agent');
    Route::post('agent/login', 'MemberController@login');//登录
    Route::any('order/order_excel', 'OrderController@order_excel');//导出订单记录Excel
    Route::any('agent/users_excel', 'OrderController@user_excel');//导出用户记录Excel
    Route::any('agent/dojie', 'ReportController@dojie');//阶段订单图表
});

//管理后台
Route::prefix('agent')->namespace('Agent')->middleware(['agent_auth'])->group(function () {
    Route::get('home', 'ReportController@home');//主页
    Route::get('user/index', 'UserController@index');//用户管理列表
    Route::get('salesmen/index', 'UserController@salesmenIndex');//代理商管理列表
    Route::get('salesmen/add', 'UserController@salesmenAdd');//添加代理商页面

    Route::get('transfer/index', 'UserController@transferIndex');//出入金列表页
    Route::get('set_password', 'MemberController@setPas');//修改密码
    Route::get('set_info', 'MemberController@setInfo');//基本信息

    Route::get('order_statistics', 'ReportController@orderSt');//订单统计
    Route::get('user_statistics', 'ReportController@userSt');//用户统计
    Route::get('money_statistics', 'ReportController@moneySt');//收益统计
    //首页
    Route::any('get_statistics', 'AgentIndexController@getStatistics');//首页获取统计信息

    Route::post('change_password', 'MemberController@changePWD');//修改密码

    Route::get('user_info', 'MemberController@getUserInfo');//获取用户信息
    Route::post('save_user_info', 'MemberController@saveUserInfo');//保存用户信息
    Route::any('lists', 'MemberController@lists');//代理商列表
    Route::post('addagent', 'MemberController@addAgent');//添加代理商
    Route::post('addsonagent', 'MemberController@addSonAgent');//给代理商添加代理商
    Route::post('update', 'MemberController@updateAgent');//添加代理商
    Route::post('searchuser', 'MemberController@searchuser');//查询用户
    Route::post('search_agent_son', 'MemberController@search_agent_son');//查询用户

    Route::any('logout', 'MemberController@logout');//退出登录
    Route::any('menu', 'MemberController@getMenu');//获取指定身份的菜单

    Route::post('jie', 'ReportController@jie');//阶段订单图表

    Route::post('day', 'ReportController@day');//阶段订单图表

    Route::post('order', 'ReportController@order');//阶段订单图表
    Route::post('order_num', 'ReportController@order_num');//阶段订单图表
    Route::post('order_money', 'ReportController@order_money');//阶段订单图表

    Route::post('user', 'ReportController@user');//阶段用户图表
    Route::post('user_num', 'ReportController@user_num');//阶段订单图表
    Route::post('user_money', 'ReportController@user_money');//阶段订单图表

    Route::post('agental', 'ReportController@agental');//阶段订单图表
    Route::post('agental_t', 'ReportController@agental_t');//阶段订单图表
    Route::post('agental_s', 'ReportController@agental_s');//阶段订单图表

    Route::get('order/lever_index', 'OrderController@leverIndex');//合约订单页面
    Route::post('order/list', 'OrderController@order_list');//团队所有订单
    Route::get('order/info', 'OrderController@order_info');//订单详情

    //撮合订单
    Route::get('order/transaction_index', 'OrderController@transactionIndex');
    Route::get('order/transaction_list', 'OrderController@transactionList');
    Route::get('order/jie_index', 'OrderController@jieIndex');


    Route::post('jie/list', 'OrderController@jie_list');//团队所有结算
    Route::post('jie/info', 'OrderController@jie_info');//结算详情

    Route::post('get_order_account' , 'OrderController@get_order_account');
    Route::post('get_user_num' , 'UserController@get_user_num');
    Route::post('get_my_invite_code' , 'UserController@get_my_invite_code');

    Route::any('user/lists', 'UserController@lists');//用户列表
    Route::any('lever_transaction/lists', 'LeverTransactionController@lists');//用户的订单
    Route::any('account/money_log', 'AccountController@moneyLog');//结算
    Route::any('agent/info', 'AgentController@info');//代理商信息

    //划转出入列表
    Route::any('user/huazhuan_lists', 'UserController@huazhuan_lists');//用户列表

    //出入金（充币、提币)
    Route::any('recharge/index', 'CapitalController@rechargeIndex');
    Route::any('withdraw/index', 'CapitalController@withdrawIndex');
    Route::get('capital/recharge', 'CapitalController@rechargeList');
    Route::get('capital/withdraw', 'CapitalController@withdrawList');

    //用户资金
    Route::get('user/users_wallet', 'CapitalController@wallet');
    Route::get('users_wallet_total', 'CapitalController@wallettotalList');

    //用户订单
    Route::get('user/lever_order', 'OrderController@userLeverIndex');
    Route::get('user/lever_order_list', 'OrderController@userLeverList');

    //结算 提现到账
    Route::post('wallet_out/done', 'CapitalController@walletOut');
    
    //修改用户所属代理商
    Route::post('user/choise_agent', 'UserController@choise_agent');
});
