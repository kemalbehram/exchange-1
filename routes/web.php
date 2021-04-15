<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

// 无api前缀无需登录的
Route::namespace('Api')->group(function () {
    Route::get('/', 'DefaultController@jumpDist'); // 首页跳转到pc
    Route::any('update', 'DefaultController@checkUpdate')->middleware(['cross']);
    Route::any('user/walletaddress', 'UserController@walletaddress'); //钱包地址
    Route::any('/mch/callBack', 'UDunCloudController@callback');
    Route::any('/mch/test', 'UDunCloudController@test');
});

// api前缀无需登录的
Route::prefix('api')->namespace('Api')->group(function () {

    Route::get('env.json', 'DefaultController@env')->middleware(['cross']); //取env.json
    Route::post('lang/get', 'DefaultController@getlang')->middleware(['cross']);
    Route::post('lang/set', 'DefaultController@setLang')->middleware(['cross']);
    Route::get('get_version', 'DefaultController@getVersion'); //获取版本号
    Route::any('block', 'CommonController@block')->middleware(['valid_chain_push']); // 区块交易推送接口

    Route::any('upload', 'DefaultController@upload'); //上传图片接口

    Route::any('get_setting', 'DefaultController@getSetting'); //获取配置信息
    Route::any('base64_upload', 'DefaultController@base64ImageUpload')->middleware(['cross']);; //base64上传图片接口

    Route::any('market/get_current', 'CurrencyController@getCurrentMarket')->middleware(['cross']);
    Route::post('exchange/shift_to', 'UserController@shiftToByExchange')->middleware(['cross']);

    Route::prefix('user')->group(function () {
        Route::post('login', 'LoginController@login'); //登录
        Route::post('register', 'LoginController@register'); //注册
        Route::post('forget', 'LoginController@forgetPassword'); //忘记密码
        Route::post('check_mobile', 'LoginController@checkMobileCode'); //验证短信验证码
        Route::post('check_email', 'LoginController@checkEmailCode'); //验证邮件验证码
        Route::post('walletRegister', 'LoginController@walletRegister'); //钱包注册
    });

    Route::prefix('news')->group(function () {
        Route::post('list', 'NewsController@getArticle'); //获取文章列表
        Route::post('detail', 'NewsController@get'); //获取文章详情
        Route::post('help', 'NewsController@getCategory'); //帮助中心分类
        Route::post('recommend', 'NewsController@recommend'); //推荐文章
        Route::post('get_invite_return_news', 'NewsController@getInviteReturn'); //获取邀请规则详情
    });

    Route::post('sms_send', 'SmsController@mordulaSend')->middleware('throttle:30:1'); //获取短信验证码
    Route::post('sms_mail', 'SmsController@sendMail'); //获取邮箱验证码

    Route::post('transaction/legal_list', 'TransactionController@legalList'); //法币交易市场
    Route::get('seller_list', 'SellerController@lists'); //商家列表

    Route::get('legal_deal_platform', 'LegalDealController@legalDealPlatform')->middleware(['demo_limit']); //商家发布法币交易信息列表
    Route::get('c2c_deal_platform', 'C2cDealController@legalDealPlatform')->middleware(['demo_limit']); //用户发布c2c法币交易信息列表
    Route::post('deal/info', 'CurrencyController@dealInfo'); //行情详情

    Route::prefix('currency')->group(function () {
        Route::get('list', 'CurrencyController@lists'); //币种列表
        Route::get('quotation', 'CurrencyController@quotation'); //币种列表带行情
        Route::any('quotation_new', 'CurrencyController@newQuotation'); //币种列表带行情(支持交易对)
        Route::any('plates', 'CurrencyController@plates'); //币种版块
        Route::any('new_timeshar', 'CurrencyController@klineMarket')->middleware(['cross']); //K线分时数据，对接tradeingview
        Route::any('kline_market', 'CurrencyController@klineMarket')->middleware(['cross']); //K线分时数据，对接tradeingview
        Route::any('timeshar', 'CurrencyController@timeshar'); //分时
        Route::any('write_kline', 'CurrencyController@writeEsearchKline')->middleware(['cross']); //JAVA写入K线
        Route::any('java_data', 'CurrencyController@javaData')->middleware(['cross']); //对接JAVA撮合引擎的数据转发
        Route::get('lever', 'CurrencyController@lever'); //行情详情
    });

    Route::get('getLtcKMB', 'WalletController@getLtcKMB');
    Route::post('getNode', 'DefaultController@getNode'); //节点关系
    Route::get('ltcGet', 'WalletController@ltcGet'); //钱包获取交易所的转账
});

//******************************api接口需要登录的**********************
Route::prefix('api')->namespace('Api')->middleware('check_api')->group(function () {
    //退出登录
    Route::any('logout', 'UserController@logout');
    Route::any('is_first_login', 'UserController@isFirstLogin'); //是否首次登录

    Route::any('/profits/show_profits', 'AccountController@show_profits'); //盈亏返还记录
    Route::post('/checkpassword', 'UserController@checkPayPassword'); //验证法币交易密码

    Route::get('index', 'DefaultController@index');
    //发送短信
    Route::post('vip', 'UserController@vip');

    Route::any('/user_match/list', 'UserMatchController@lists');
    Route::any('/user_match/add', 'UserMatchController@add');
    Route::any('/user_match/del', 'UserMatchController@del');

    Route::post('/historical_data', 'DefaultController@historicalData');
    Route::post('/quotation', 'DefaultController@quotation');
    Route::post('/quotation/info', 'DefaultController@quotationInfo');

    //反馈建议
    Route::prefix('feedback')->group(function () {
        Route::post('list', 'FeedBackController@myFeedBackList'); //反馈信息列表
        Route::post('detail', 'FeedBackController@feedBackDetail'); //反馈信息内容，包括回复信息
        Route::post('add', 'FeedBackController@feedBackAdd'); //添加反馈信息
    });

    //安全中心
    Route::prefix('safe')->group(function () {
        
        Route::post('safe_center', 'UserController@safeCenter'); //安全中心绑定信息
        Route::post('gesture_add', 'UserController@gesturePassAdd'); //添加手势密码
        Route::post('gesture_del', 'UserController@gesturePassDel'); //删除手势密码
        Route::post('update_password', 'UserController@updatePayPassword'); //修改交易密码
        Route::post('mobile', 'UserController@setMobile'); //绑定电话
        Route::post('email', 'UserController@setEmail'); //绑定邮箱
    });

    //钱包相关
    Route::prefix('wallet')->group(function () {
        //资产
        Route::post('list', 'WalletController@walletList'); //用户账户资产信息
        Route::post('detail', 'WalletController@getWalletDetail'); //用户账户资产详情
        Route::post('change', 'WalletController@changeWallet')->middleware(['demo_limit']); //账户划转
        Route::post('transfer', 'WalletController@accountTransfer')->middleware(['demo_limit']); //账户划转
        Route::any('hzhistory', 'WalletController@hzhistory'); //账户历史记录
        Route::post('get_info', 'WalletController@getCurrencyInfo'); //获取提币信息
        Route::post('get_address', 'WalletController@getAddressByCurrency'); //获取提币地址
        Route::post('out', 'WalletController@postWalletOut')->middleware(['demo_limit', 'validate_user_locked', 'lever_hold_check', 'check_pay_password:withdraw']); //提交提币信息
        Route::post('get_in_address', 'WalletController@getWalletAddressIn')->middleware(['demo_limit']); //充币地址
        Route::any('legal_log', 'WalletController@legalLog'); //财务记录
        Route::any('out_log', 'WalletController@walletOutLog'); //提币记录
        Route::get('currencylist', 'WalletController@currencyList'); //币种列表
        Route::post('addaddress', 'WalletController@addAddress'); //添加提币地址
        Route::post('deladdress', 'WalletController@addressDel'); //删除提币地址
        Route::post('ltcSend', 'WalletController@ltcSend')->middleware(['demo_limit']);
        Route::post('real_name', 'UserController@walletRealName'); //钱包身份认证
        Route::get('GetDrawAddress', 'WalletController@getDrawAddress'); //钱包身份认证
    });

    // 站内转账
    Route::prefix('transfer')->group(function () {
        Route::get('currencies', 'TransferController@getAllowTransferFee');
        Route::post('submit', 'TransferController@submit');
        Route::get('logs', 'TransferController@logs');
    });

    // 用户相关
    Route::prefix('user')->group(function () {
        Route::post('update_address', 'UserController@updateAddress'); //更新地址
        Route::post('getuserbyaddress', 'UserController@getUserByAddress'); //根据地址获取用户信息
        Route::post('chat', 'UserController@sendchat'); //发送聊天
        Route::post('chatlist', 'UserController@chatlist'); //发送聊天
        Route::post('invite_list', 'UserController@inviteList')->middleware(['demo_limit']); //邀请返佣榜单
        Route::get('invite', 'UserController@invite')->middleware(['demo_limit']); //我的邀请
        Route::post('my_invite_list', 'UserController@myInviteList')->middleware(['demo_limit']); //我的邀请会员列表
        Route::post('my_account_return', 'UserController@myAccountReturn')->middleware(['demo_limit']); //我的邀请返佣列表
        Route::get('my_poster', 'UserController@posterBg')->middleware(['demo_limit']); //我的专属海报
        Route::get('my_share', 'UserController@share')->middleware(['demo_limit']); //邀请好友分享
        Route::get('info', 'UserController@info'); //我的
        Route::get('center', 'UserController@userCenter'); //个人中心
        Route::post('real_name', 'UserController@realName')->middleware(['demo_limit']); //身份认证
        Route::get('logout', 'UserController@logout'); //退出登录
        Route::post('setaccount', 'UserController@setAccount')->middleware(['demo_limit']); //设置法币交易账号
        Route::get('into_tra_log', 'UserController@into_tra_log'); //用户转入记录
        Route::get('authorization_code', 'UserController@authCode'); //添加代理商时用户的授权码
        Route::post('cash_info', 'UserController@cashInfo')->middleware(['demo_limit']); //个人收款信息
        Route::post('cash_save', 'UserController@saveCashInfo')->middleware(['demo_limit']); //添加修改收款方式
    });

    //C2C相关
    Route::prefix('c2c')->group(function () {
        Route::get('seller_info', 'C2cDealController@sellerInfo')->middleware(['demo_limit']); //用户c2c店铺详情信息
        Route::get('seller_trade', 'C2cDealController@tradeList')->middleware(['demo_limit']); //我的发布交易列表
        Route::post('do_legal_deal', 'C2cDealController@doDeal')->middleware(['check_user', 'demo_limit', 'validate_user_locked', 'check_pay_password:c2c']); //法币交易信息详情
        Route::post('user_legal_pay_cancel', 'C2cDealController@userLegalDealCancel')->middleware(['check_user', 'demo_limit', 'check_pay_password:c2c']); //C2C交易用户取消订单
        Route::post('user_legal_pay', 'C2cDealController@userLegalDealPay')->middleware(['check_user', 'demo_limit', 'check_pay_password:c2c']); //C2c交易用户确认付款
        Route::post('legal_deal_sure', 'C2cDealController@doSure')->middleware(['check_user', 'demo_limit', 'check_pay_password:c2c']); //C2C发布者确认收款
        Route::post('legal_deal_user_sure', 'C2cDealController@userDoSure')->middleware(['check_user', 'demo_limit', 'check_pay_password:c2c']); //C2C用户确认收款
        Route::post('back_send', 'C2cDealController@backSend')->middleware(['check_user', 'demo_limit', 'check_pay_password:c2c']); //C2C撤回发布
        Route::get('legal_send_deal_list', 'C2cDealController@legalDealSellerList')->middleware(['check_user', 'demo_limit']); //发布交易列表
    });

    //c2c交易
    Route::post('c2c_send', 'C2cDealController@postSend')->middleware(['check_user', 'validate_user_locked', 'demo_limit', 'check_pay_password:c2c']); //用户发布交易信息
    Route::get('c2c_deal_info', 'C2cDealController@legalDealSendInfo')->middleware(['check_user', 'demo_limit']); //c2c法币交易信息详情
    Route::get('c2c_seller_deal', 'C2cDealController@sellerLegalDealList')->middleware(['check_user', 'demo_limit']); //法币交易商家端交易列表
    Route::get('c2c_user_deal', 'C2cDealController@userLegalDealList')->middleware(['check_user', 'demo_limit']); //法币交易用户端交易列表
    Route::get('c2c_deal', 'C2cDealController@legalDealInfo')->middleware(['demo_limit', 'check_user']); //交易详情信息
    
    Route::prefix('transaction')->group(function () {
        Route::post('deal', 'TransactionController@deal'); //deal
        // Route::post('in', 'TransactionController@in')->middleware(['check_user', 'validate_user_locked', 'check_pay_password:match']); //买入
        Route::post('in', 'TransactionController@in')->middleware(['validate_user_locked', 'check_pay_password:match']); //买入
        // Route::post('out', 'TransactionController@out')->middleware(['check_user', 'validate_user_locked', 'check_pay_password:match']); //卖出
        Route::post('out', 'TransactionController@out')->middleware(['validate_user_locked', 'check_pay_password:match']); //卖出
        Route::post('add', 'TransactionController@add'); //提交交易
        Route::post('list', 'TransactionController@list'); //交易列表
        Route::post('info', 'TransactionController@info'); //交易详情
        Route::get('checkinout', 'TransactionController@checkInOut'); //验证法币交易购买 出售按钮
    });
    //交易记录
    Route::post('transaction_in', 'TransactionController@TransactionInList');
    Route::post('transaction_out', 'TransactionController@TransactionOutList');
    Route::post('transaction_complete', 'TransactionController@TransactionCompleteList');
    Route::post('transaction_del', 'TransactionController@TransactionDel'); //取消交易

    //合约交易
    Route::prefix('lever')->group(function () {
        Route::post('deal', 'LeverController@deal'); //合约deal
        Route::post('dealall', 'LeverController@dealAll'); //合约全部
        Route::post('submit', 'LeverController@submit')->middleware(['validate_user_locked', 'check_pay_password:lever']); //合约下单
        Route::post('close', 'LeverController@close'); //合约平仓
        Route::post('cancel', 'LeverController@cancelTrade'); //撤销委托(取消)
        Route::post('batch_close', 'LeverController@batchCloseByType'); //一键平仓
        Route::post('setstop', 'LeverController@setStopPrice'); //设置止盈止损价
        Route::post('my_trade', 'LeverController@myTrade'); //我的交易记录
    });

    Route::post('/data/graph', 'DefaultController@dataGraph'); //数据图
    Route::post('/account/list', 'AccountController@list'); //账目明细
    Route::post('legal_send', 'LegalDealController@postSend')->middleware(['demo_limit', 'validate_user_locked', 'check_pay_password:otc']); //商家发布法币交易信息
    Route::get('legal_deal_info', 'LegalDealController@legalDealSendInfo')->middleware(['check_user', 'demo_limit']); //法币交易信息详情
    Route::post('do_legal_deal', 'LegalDealController@doDeal')->middleware(['check_user', 'demo_limit', 'validate_user_locked', 'check_pay_password:otc']); //法币交易信息详情
    Route::get('legal_seller_deal', 'LegalDealController@sellerLegalDealList')->middleware(['check_user', 'demo_limit']); //法币交易商家端交易列表
    Route::get('legal_user_deal', 'LegalDealController@userLegalDealList')->middleware(['check_user', 'demo_limit']); //法币交易用户端交易列表
    Route::get('seller_info', 'LegalDealController@sellerInfo')->middleware(['demo_limit']); //商家详情信息
    Route::get('seller_trade', 'LegalDealController@tradeList')->middleware(['demo_limit']); //商家交易

    Route::get('legal_deal', 'LegalDealController@legalDealInfo')->middleware(['check_user', 'demo_limit']); //交易详情信息
    Route::post('user_legal_pay', 'LegalDealController@userLegalDealPay')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //法币交易用户确认付款
    Route::post('user_legal_pay_cancel', 'LegalDealController@userLegalDealCancel')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //法币交易用户取消订单
    Route::get('my_seller', 'LegalDealController@mySellerList')->middleware(['check_user', 'demo_limit']); //我的商铺
    Route::get('legal_send_deal_list', 'LegalDealController@legalDealSellerList')->middleware(['check_user', 'demo_limit']); //发布交易列表
    Route::post('legal_deal_sure', 'LegalDealController@doSure')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //商家确认收款
    Route::post('legal_deal_user_sure', 'LegalDealController@userDoSure')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //用户确认收款
    Route::post('back_send', 'LegalDealController@backSend')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //商家撤回发布
    Route::post('error_send', 'LegalDealController@errorSend')->middleware(['check_user', 'demo_limit', 'check_pay_password:otc']); //商家撤回异常发布
    Route::post('down_send', 'LegalDealController@down')->middleware(['demo_limit', 'check_user', 'check_pay_password:otc']); //商家下架发布
    Route::any('legal/arbitrate', 'LegalDealController@submitArbitrate'); // 卖方提交维权
    Route::post('seller/transfer', 'LegalDealController@transfer')->middleware(['demo_limit']); // 商家余额 用户余额划转
    Route::get('seller/balance_log', 'LegalDealController@balanceLog')->middleware(['demo_limit']); // 商家余额日志
});

