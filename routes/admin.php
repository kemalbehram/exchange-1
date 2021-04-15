<?php

Route::namespace('Admin')->group(function () {
    Route::get('/login', 'DefaultController@login');
    Route::post('/admin/login', 'DefaultController@postLogin');
});

//管理后台
Route::namespace('Admin')->prefix('admin')->middleware(['admin_auth'])->group(function () {
    Route::get('/index', 'DefaultController@indexnew');

    Route::get('/safe/verificationcode', 'DefaultController@getVerificationCode'); //获取链上操作安全验证码
    Route::any('/ueditor/uploader', 'UeditorController@ueditor');
    Route::any('chain/balance_collect', 'ChainController@collectBalance');
    Route::any('chain/send_fee', 'ChainController@sendFee');

    Route::prefix('Leverdeals')->group(function () {
        Route::any('Leverdeals_show', 'TransactionController@Leverdeals_show');
        Route::any('close', 'TransactionController@close');
        Route::any('list', 'TransactionController@Leverdeals');//合约交易 团队所有订单
        Route::get('csv', 'TransactionController@csv');//导出合约交易 团队所有订单
    });

    // 法币
    Route::group([], function () {
        Route::get('/legal', 'LegalDealSendController@index')->middleware(['demo_limit']);
        Route::get('/legal/list', 'LegalDealSendController@list');
        Route::get('/legal_deal', 'LegalDealController@index')->middleware(['demo_limit']);
        Route::get('/legal_deal/list', 'LegalDealController@list');
        Route::get('/legal/all', 'LegalDealSendController@all');
        Route::any('admin_legal_pay_cancel', 'LegalDealController@adminLegalDealCancel'); // 管理员后台取消交易
        Route::any('legal_deal_admin_sure', 'LegalDealController@adminDoSure'); // 管理员后台确认收款
        //商家
        Route::get('seller', 'SellerController@index');//商家首页
        Route::get('seller_list', 'SellerController@lists');
        Route::get('seller_add', 'SellerController@add')->middleware(['demo_limit']);
        Route::post('seller_add', 'SellerController@postAdd')->middleware(['demo_limit']);
        Route::get('seller/adjust_balance', 'SellerController@adjustBalance')->middleware(['demo_limit']);
        Route::post('seller/adjust_balance', 'SellerController@postAdjustBalance')->middleware(['demo_limit']);
        Route::post('seller_del', 'SellerController@delete')->middleware(['demo_limit']);
        Route::post('send/back', 'SellerController@sendBack');//撤回发布
        Route::post('send/is_shelves', 'SellerController@is_shelves');//下架
        Route::get('seller/logs', 'SellerController@logs');
        Route::get('seller/logs_list', 'SellerController@logsList');
    });

    //C2C
    Route::group([], function () {
        Route::get('/c2c', 'C2cDealSendController@index')->middleware(['demo_limit']);
        Route::get('/c2c/list', 'C2cDealSendController@list');
        Route::get('/c2c_deal', 'C2cDealController@index')->middleware(['demo_limit']);
        Route::get('/c2c/all', 'C2cDealController@all');
        Route::get('/c2c_deal/list', 'C2cDealController@list');
        Route::post('c2c/send/back', 'C2cDealSendController@sendBack');//撤回发布
        Route::post('c2c/send/del', 'C2cDealSendController@sendDel');//删除
    });

    //投诉建议

    Route::prefix('feedback')->group(function () {
        Route::get('detail', 'FeedBackController@feedBackDetail');
        Route::get('del', 'FeedBackController@feedBackDel');
        Route::post('reply', 'FeedBackController@reply');
        Route::get('index', 'FeedBackController@index');
        Route::get('list', 'FeedBackController@feedbackList');
        Route::get('/feedback/csv', 'FeedBackController@csv')->middleware(['demo_limit']);
    });

    //系统设置
    Route::prefix('setting')->group(function () {
        Route::get('index', 'SettingController@index');//设置首页
        Route::get('list', 'SettingController@list');//设置首页
        Route::get('add', 'SettingController@add');//设置奖金
        Route::post('postadd', 'SettingController@postAdd');//设置奖金
        Route::get('set_base', 'SettingController@base');//基础设置
        Route::post('basesite', 'SettingController@setBase');//提交基础设置
        Route::get('data/index', 'SettingController@dataSetting');//提交基础设置
    });

    //提币
    Route::group([], function () {
        Route::get('cashb', 'CashbController@index')->middleware(['demo_limit']);
        Route::get('cashb_list', 'CashbController@cashbList');
        Route::get('cashb_show', 'CashbController@show')->middleware(['demo_limit']);//提币详情页面
        Route::post('cashb_done', 'CashbController@done')->middleware(['demo_limit']);//确认提币成功
        Route::get('cashb_back', 'CashbController@back')->middleware(['demo_limit']);//执行退回申请
        //导出数据到excel文件
        Route::get('/cashb/csv', 'CashbController@csv')->middleware(['demo_limit']);//导出提币记录
    });


    Route::prefix('wallet')->group(function () {
        Route::get('index', 'WalletController@index'); //钱包管理页面
        Route::get('list', 'WalletController@lists'); //钱包列表搜索
        Route::get('make', 'WalletController@makeWallet'); //生成钱包
        Route::get('update_balance', 'WalletController@updateBalance'); //更新链上余额
        Route::get('transfer_poundage', 'WalletController@transferPoundage'); //打入手续费
        Route::get('collect', 'WalletController@collect'); //余额归拢
    });

    Route::prefix('user')->group(function () {
        Route::get('false_data', 'UserController@falseData');
        Route::get('chart_data', 'UserController@chartData');
        Route::post('chart_data', 'UserController@dochartData');
        Route::get('count_index', 'UserController@countData');
        //实名认证管理
        Route::get('real_index', 'UserRealController@index');
        Route::get('real_list', 'UserRealController@list');
        Route::get('real_info', 'UserRealController@detail');
        Route::post('real_del', 'UserRealController@del');
        Route::post('real_auth', 'UserRealController@auth');

        Route::get('editltc', 'UserController@editltc');
        Route::post('editltc', 'UserController@doeditltc');

        Route::get('edit', 'UserController@edit');
        Route::post('edit', 'UserController@doedit');

        Route::get('address', 'UserController@address');//提币地址信息
        Route::post('address_edit', 'UserController@addressEdit');//修改地址信息

        Route::get('user_index', 'UserController@index');
        Route::get('list', 'UserController@lists');
        Route::get('users_wallet', 'UserController@wallet');
        Route::get('walletList', 'UserController@walletList');
        Route::post('wallet_lock', 'UserController@walletLock');//钱包锁定

        Route::get('conf', 'UserController@conf');
        Route::post('conf', 'UserController@postConf')->middleware(['demo_limit']);//调节钱包账户
        Route::post('del', 'UserController@del')->middleware(['demo_limit']); //删除用户
        Route::post('delw', 'UserController@delw')->middleware(['demo_limit']); //删除指定id钱包
        Route::post('lock', 'UserController@lock')->middleware(['demo_limit']);//账号锁定
        Route::post('allow_exchange', 'UserController@allowExchange'); //允许积分兑换
        Route::post('blacklist', 'UserController@blacklist')->middleware(['demo_limit']);//加入黑名单
        Route::get('candy_conf/{id}', 'UserController@candyConf'); //
        Route::post('candy_conf/{id}', 'UserController@postCandyConf'); //
        Route::get('csv', 'UserController@csv')->middleware(['demo_limit']);//导出会员

    });

    Route::prefix('account')->group(function () {
        Route::get('account_index', 'AccountLogController@index');
        Route::get('list', 'AccountLogController@lists');
        Route::get('viewDetail', 'AccountLogController@view');
        Route::get('recharge', 'AccountLogController@recharge');
        Route::get('recharge/lists', 'AccountLogController@rechargeList');
    });

    //邀请返佣
    Route::prefix('invite')->group(function () {
        Route::get('account_return', 'InviteController@return');//邀请返佣
        Route::get('return_list', 'InviteController@returnList');//邀请返佣列表
        Route::get('childs', 'InviteController@childs');//会员邀请关系图
        Route::get('share', 'InviteController@share');//邀请分享设置
        Route::post('share', 'InviteController@postShare');//邀请分享设置提交

        Route::get('getTree', 'InviteController@getTree');//
        Route::post('del', 'InviteController@del');

        Route::get('edit', 'InviteController@edit');
        Route::post('edit', 'InviteController@doedit');
        Route::post('bgdel', 'InviteController@bgdel');
    });

    Route::get('/transaction/tran_index', 'TransactionController@index');
    Route::get('/transaction/list', 'TransactionController@lists');

    //后台管理员、角色管理
    Route::prefix('manager')->group(function () {
        Route::get('manager_index', 'AdminController@managerIndex');
        Route::get('users', 'AdminController@users');
        Route::get('add', 'AdminController@add');//添加管理员
        Route::post('add', 'AdminController@postAdd');//添加管理员
        Route::post('delete', 'AdminController@del');//删除管理员
        Route::get('manager_roles', 'AdminController@adminRoles');
        Route::get('manager_roles_api', 'AdminRoleController@users');
        Route::get('role_add', 'AdminRoleController@add');
        Route::post('role_add', 'AdminRoleController@postAdd');
        Route::post('role_delete', 'AdminRoleController@del');
        Route::get('role_permission', 'AdminRolePermissionController@update');
        Route::post('role_permission', 'AdminRolePermissionController@postUpdate');
    });

    //新闻
    Route::group([], function () {
        //新闻路由
        Route::get('news_index', 'NewsController@index');
        Route::get('news_add', 'NewsController@add');
        Route::post('news_add', 'NewsController@postAdd');
        Route::get('news_edit/{id}', 'NewsController@edit');
        Route::post('news_edit/{id}', 'NewsController@postEdit');
        Route::get('news_del/{id}/{togetherDel?}', 'NewsController@del');
        //新闻分类路由
        Route::get('news_cate_index', 'NewsController@cateIndex');
        Route::get('news_cate_add', 'NewsController@cateAdd');
        Route::get('news_cate_list', 'NewsController@getCateList');
        Route::post('news_cate_add', 'NewsController@postCateAdd');
        Route::get('news_cate_edit/{id}', 'NewsController@cateEdit');
        Route::post('news_cate_edit/{id}', 'NewsController@postCateEdit');
        Route::get('news_cate_del/{id}', 'NewsController@cateDel');
    });

    //交易
    Route::group([], function () {
        Route::get('complete', 'TransactionController@completeIndex');
        Route::get('in', 'TransactionController@inIndex');
        Route::get('out', 'TransactionController@outIndex');
        Route::get('cny', 'TransactionController@cnyIndex');
        Route::get('complete_list', 'TransactionController@completeList');
        Route::get('in_list', 'TransactionController@inList');
        Route::get('out_list', 'TransactionController@outList');
        Route::get('cny_list', 'TransactionController@cnyList');
        Route::get('trade', 'TransactionController@trade'); //撮合交易
        Route::get('exchange_cancel', 'TransactionController@cancel'); //后台撤单
        Route::get('exchange_del', 'TransactionController@del'); //后台撤单
    });

    //币种
    Route::group([], function () {
        Route::get('currency', 'CurrencyController@index');//首页
        Route::get('currency_add', 'CurrencyController@add')->middleware(['demo_limit']);//添加币种
        Route::post('currency_add', 'CurrencyController@postAdd')->middleware(['demo_limit']);//添加币种
        Route::get('currency/set_in_address/{id}', 'CurrencyController@setInAddress')->middleware(['demo_limit']);
        Route::get('currency/set_out_address/{id}', 'CurrencyController@setOutAddress')->middleware(['demo_limit']);
        Route::post('currency/set_in_address', 'CurrencyController@postSetInAddress')->middleware(['demo_limit']);
        Route::post('currency/set_out_address', 'CurrencyController@postSetOutAddress')->middleware(['demo_limit']);
        Route::get('currency_list', 'CurrencyController@lists');//币种
        Route::post('currency_del', 'CurrencyController@delete')->middleware(['demo_limit']);//删除币种
        Route::post('currency_display', 'CurrencyController@isDisplay');//币种显示
        Route::post('currency_execute', 'CurrencyController@executeCurrency');//币种显示
        Route::get('currency/match/{legal_id}', 'CurrencyController@match'); //交易对
        Route::get('currency/match_list/{legal_id}', 'CurrencyController@matchList'); //交易对列表
        Route::get('currency/match_add/{legal_id}', 'CurrencyController@addMatch'); //添加交易对页
        Route::post('currency/match_add/{legal_id}', 'CurrencyController@postAddMatch')->middleware(['demo_limit']); //添加交易对
        Route::get('currency/match_edit/{id}', 'CurrencyController@editMatch'); //编辑交易对页
        Route::post('currency/match_edit/{id}', 'CurrencyController@postEditMatch'); //编辑交易对
        Route::any('currency/match_del/{id}', 'CurrencyController@delMatch')->middleware(['demo_limit']); //删除交易对
        //板块管理
        Route::get('/currency_plates/index', 'CurrencyPlatesController@index');
        Route::get('/currency_plates/list', 'CurrencyPlatesController@list');
        Route::get('/currency_plates/add', 'CurrencyPlatesController@add');
        Route::post('/currency_plates/postadd', 'CurrencyPlatesController@postadd');
        Route::post('/currency_plates/is_show', 'CurrencyPlatesController@showStatus');
        Route::post('/currency_plates/del', 'CurrencyPlatesController@delete');
    });

    //APP版本管理
    /*Route::group([], function () {
        Route::get('app_version', 'AppVersionController@index');//首页
        Route::get('app_version_add', 'AppVersionController@add');//添加版本
        Route::post('app_version_add', 'AppVersionController@postAdd');//添加版本
        Route::get('app_version_list', 'AppVersionController@lists');//版本列表
        Route::post('app_version_del', 'AppVersionController@delete');//删除版本
    });*/

    //合约交易风险率
    Route::prefix('hazard')->group(function () {
        Route::get('index', 'HazardRateController@index');
        Route::get('lists', 'HazardRateController@lists');
        Route::get('total', 'HazardRateController@total');
        Route::get('total_lists', 'HazardRateController@totalLists');
        Route::get('handle', 'HazardRateController@handle');
        Route::post('handle', 'HazardRateController@postHandle');
    });

    //合约做单列表
    Route::prefix('lever')->group(function () {
        Route::get('index', 'LeverTransactionController@index');
        Route::get('lists', 'LeverTransactionController@lists');
    });

    //合约交易倍数手数设置
    Route::prefix('levermultiple')->group(function () {
        Route::get('index', 'LeverMultipleController@index');
        Route::get('list', 'LeverMultipleController@lists');
        Route::post('del', 'LeverMultipleController@del');
        Route::any('edit', 'LeverMultipleController@edit');
        Route::any('doedit', 'LeverMultipleController@doedit');
        Route::any('add', 'LeverMultipleController@add');
        Route::any('doadd', 'LeverMultipleController@doadd');
    });

    //短信模板管理
    Route::prefix('sms_project')->group(function () {
        Route::get('index', 'SmsProjectController@index');//首页
        Route::get('add', 'SmsProjectController@add');//添加模板
        Route::post('add', 'SmsProjectController@postAdd');//保存模板
        Route::get('edit', 'SmsProjectController@edit');//编辑模板
        Route::post('del', 'SmsProjectController@del');//删除模板
        Route::get('lists', 'SmsProjectController@lists');//模板列表数据
        Route::get('send_test', 'SmsProjectController@send_test');//测试模板界面
        Route::post('send_sms', 'SmsProjectController@send_sms');//测试模板
    });

    //JAVA机器人
    Route::prefix('javarobot')->group(function () {
        Route::get('index', 'JavaRobotController@index');
        Route::get('lists', 'JavaRobotController@lists');
        Route::get('add', 'JavaRobotController@add');
        Route::post('add', 'JavaRobotController@postAdd');
        Route::post('change_start', 'JavaRobotController@changeStart'); //打开关闭机器人
        Route::post('del', 'JavaRobotController@del'); //删除
        Route::post('cancel', 'JavaRobotController@cancel'); //撤单机器人所有单
    });

    //自定义机器人
    Route::prefix('krobot')->group(function () {
        Route::get('index', 'KRobotController@index');
        Route::get('lists', 'KRobotController@lists');
        Route::get('add', 'KRobotController@add');
        Route::post('add', 'KRobotController@postAdd');
        Route::post('change_start', 'KRobotController@changeStart'); //打开关闭机器人
        Route::post('del', 'KRobotController@del'); //删除
    });

    //手续费
    Route::get('poundage_index', 'PoundageController@index');//首页
    Route::get('poundage/lists', 'PoundageController@lists');//列表
    Route::get('poundage/sum', 'PoundageController@sum');//列表

    //好用户统计
    Route::group([], function () {
        Route::get('/good_user/index', 'GoodUserController@index');
        Route::post('/good_user/data', 'GoodUserController@data');
    });
});