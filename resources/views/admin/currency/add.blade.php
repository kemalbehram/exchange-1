@extends('admin._layoutNew')

@section('page-head')
<style>
    .hide {
        display: none;
    }
    .icon-tips-help {
        font-weight: bolder;
        border: 1px solid #b7b7b7;
        font-size: 12px;
        border-radius: 50%;
        padding: 1px;
        color: #f9c83f;
    }
</style>
@endsection

@section('page-content')
    <form class="layui-form" action="">
        <div class="layui-tab" lay-filter="currency_tab">
            <ul class="layui-tab-title">
                <li class="layui-this">基础参数</li>
                <li>提币参数</li>
                <li>U盾钱包</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">
                    <div class="layui-form-item">
                        <label class="layui-form-label">币种名称</label>
                        <div class="layui-input-inline">
                            <input type="text" name="name" lay-verify="required" autocomplete="off" placeholder="" class="layui-input" value="{{$result->name}}">
                        </div>
                        <div class="layui-form-mid layui-word-aux">请确保在交易所中该币种名称是惟一的</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">克隆名称</label>
                        <div class="layui-input-inline">
                            <input type="text" name="clone_name"  autocomplete="off" placeholder="克隆行情的币种" class="layui-input" value="{{$result->clone_name}}">
                        </div>
                        <div class="layui-form-mid layui-word-aux">将填入的币种的火币行情作为该币种火币行情</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">排序</label>
                        <div class="layui-input-inline">
                            <input type="number" class="layui-input" id="sort" name="sort" value="{{$result->sort}}" placeholder="排序为升序">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">市值价格</label>
                        <div class="layui-input-inline">
                            <input type="number" class="layui-input" id="price" name="price" value="{{$result->price}}" placeholder="市值价格">
                        </div>
                        <div class="layui-form-mid layui-word-aux">$</div>
                        <div class="layui-form-mid layui-word-aux">
                            <span>主流币价格建议参考</span>
                            <a href="https://coinmarketcap.com/" target="_blank" >coinmarketcap</a>
                            <span>网站</span>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">账户类型</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="is_legal" title="法币资产" value="1" lay-skin="primary" @if($result->is_legal == 1) checked @endif @if($parent_id > 0) disabled @endif>
                            <input type="checkbox" name="is_lever" title="合约资产" value="1" lay-skin="primary" @if($result->is_lever == 1) checked @endif @if($parent_id > 0) disabled @endif> 
                            <input type="checkbox" name="is_match" title="撮合资产" value="1" lay-skin="primary" @if($result->is_match == 1) checked @endif @if($parent_id > 0) disabled @endif>
                        </div>
                        <div class="layui-form-mid layui-word-aux"></div>
                    </div>
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">币种logo</label>
                        <div class="layui-input-block">
                            <button class="layui-btn" type="button" id="upload_test">选择图片</button>
                            <br>
                            <img src="@if(!empty($result->logo)){{$result->logo}}@endif" id="img_thumbnail" class="thumbnail" style="display: @if(!empty($result->logo)){{"block"}}@else{{"none"}}@endif;max-width: 200px;height: auto;margin-top: 5px;">
                            <input type="hidden" name="logo" id="thumbnail" value="@if(!empty($result->logo)){{$result->logo}}@endif">
                        </div>
                    </div>
                </div>

                <div class="layui-tab-item">
                    <div class="layui-form-item">
                        <label class="layui-form-label">提币数量</label>
                        <div class="layui-input-inline">
                            <input type="number" class="layui-input" name="min_number" value="{{$result->min_number}}" placeholder="最小数量">
                        </div>
                        <div class="layui-form-mid">-</div>
                        <div class="layui-input-inline">
                            <input type="number" class="layui-input" name="max_number" value="{{$result->max_number}}" placeholder="最大数量">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">提币费率</label>
                        <div class="layui-input-inline">
                            <input type="number" class="layui-input" name="rate" value="{{$result->rate}}" placeholder="百分比">
                        </div>
                        <div class="layui-form-mid">%</div>
                    </div>
                </div>

                <div class="layui-tab-item">
                    <!-- <div class="layui-form-item">
                        <label class="layui-form-label">多协议支持</label>
                        <div class="layui-input-block">
                            <div class="layui-input-inline">
                                <input type="radio" name="multi_protocol" value="0" title="否" lay-filter="multi_protocol" @if (isset($result)) {{$result->multi_protocol == 0 ? 'checked' : ''}} @else checked @endif @if($result->is_protected == 1 || $parent_id > 0) disabled @endif>
                                <input type="radio" name="multi_protocol" value="1" title="是" lay-filter="multi_protocol" @if (isset($result)) {{$result->multi_protocol == 1 ? 'checked' : ''}} @endif  @if($result->is_protected == 1 || $parent_id > 0) disabled @endif>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">所属币种</label>
                        <div class="layui-input-inline">
                            <select name="parent_id" @if ($result->is_protected == 1 || $parent_id > 0) disabled @endif >
                                <option value="0">无</option>
                                @foreach ($multi_protocol_currencies as $currency)
                                <option value="{{$currency->id}}" @if ($parent_id == $currency->id) selected="selected" @endif >{{$currency->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">所属上级主币种,一般无须选择</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">链上协议</label>
                        <div class="layui-input-inline">
                            <select name="type" @if($result->is_protected == 1) disabled="disabled" title="当前币种受保护不可编辑" @endif>
                                <option value="" @if($result->type == '') selected @endif>无</option>
                                <option value="btc" @if($result->type == 'btc') selected @endif>BTC</option>
                                <option value="omni" @if($result->type == 'omni') selected @endif>OMNI</option>
                                <option value="bch" @if($result->type == 'bch') selected @endif>BCH</option>
                                <option value="eth" @if($result->type == 'eth') selected @endif>ETH</option>
                                <option value="erc20" @if($result->type == 'erc20') selected @endif>ERC20</option>
                                <option value="eostoken" @if($result->type == 'eostoken') selected @endif>EOSTOKEN</option>
                                <option value="xrp" @if($result->type =='xrp') selected @endif>XRP</option>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">新添加币种必须指定正确所属区块链币种</div>
                    </div-->
                    <div class="layui-form-item">
                        <label class="layui-form-label">钱包策略</label>
                        <div class="layui-inline">
                            <select name="make_wallet" lay-verify="required" @if ($result->is_protected == 1) disabled="disabled" title="当前币种受保护不可编辑" @endif>
                                <option value="-1">请选择</option>
                                <option value="0" @if($result->make_wallet == 0) selected @endif>不生成</option>
                                <!--option value="1" @if($result->make_wallet == 1) selected @endif>接口生成</option>
                                <option value="2" @if($result->make_wallet == 2) selected @endif>继承归拢地址</option-->
                                <option value="3" @if($result->make_wallet == 3) selected @endif>空钱包</option>
                            </select>
                        </div>
                        <div class="layui-inline">
                            @if (isset($result->id) && $result->id > 0)
                            <button class="layui-btn layui-btn-primary layui-btn-sm" type="button" id="make_wallet">生成钱包</button><div class="layui-form-mid layui-word-aux">添加新币，在我的资产显示币种</div>
                            @endif
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">主币种编号</label>
                        <div class="layui-input-inline">
                            <input type="text" name="ud_coin_no"  autocomplete="off" placeholder="" class="layui-input {{$result->id == 3 ? 'layui-disabled' : ''}}" value="{{$result->ud_coin_no ?? 0}}" @if ($result->id == 3) readonly @endif >
                        </div>
                        <div class="layui-form-mid layui-word-aux">必填，填写错误将影响充提币，<a href="https://www.uduncloud.com/gateway-interface#exp1" target="_blank" style="color: #23B7EE;">参考</a></div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">链上协议</label>
                        <div class="layui-input-inline">
                            <select name="type" @if($result->is_protected == 1) disabled="disabled" title="当前币种受保护不可编辑" @endif>
                                <option value="" @if($result->type == '') selected @endif>无</option>
                                <option value="btc" @if($result->type == 'btc') selected @endif>BTC</option>
                                <option value="omni" @if($result->type == 'omni') selected @endif>OMNI</option>
                                <option value="bch" @if($result->type == 'bch') selected @endif>BCH</option>
                                <option value="eth" @if($result->type == 'eth') selected @endif>ETH</option>
                                <option value="erc20" @if($result->type == 'erc20') selected @endif>ERC20</option>
                                <option value="eostoken" @if($result->type == 'eostoken') selected @endif>EOSTOKEN</option>
                                <option value="xrp" @if($result->type =='xrp') selected @endif>XRP</option>
                                <option value="ltc" @if($result->type =='ltc') selected @endif>LTC</option>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">新添加币种必须指定正确所属区块链币种</div>
                    </div>
                    <div class="layui-form-item">
                            <label class="layui-form-label">合约标识<i id="contract_address_help" title="ERC20代币必填,OMNI需要填写Property(例如:USDT填31),EOSTOKEN需要填写发币账户(例如:EOS填:eosio.token)" class="layui-icon layui-icon-help icon-tips-help"></i></label>
                            <div class="layui-input-block">
                            <input type="text" name="contract_address"  autocomplete="off" placeholder="合约标识" class="layui-input {{$result->id == 3 ? 'layui-disabled' : ''}}" value="{{$result->contract_address}}" @if ($result->id == 3) readonly @endif >
                            </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">发币位数</label>
                        <div class="layui-input-inline">
                            <input type="text" name="decimal_scale"  autocomplete="off" placeholder="" class="layui-input {{$result->is_protected == 1 ? 'layui-disabled' : ''}}" value="{{$result->decimal_scale ?? 18}}" @if($result->is_protected ==1) disabled @endif @if ($result->is_protected == 1) readonly="readonly" title="当前币种受保护不可编辑" @endif>
                        </div>
                        <div class="layui-form-mid layui-word-aux">请务必保证与区域链上小数位一致</div>
                    </div>
                    
                    <!-- <div class="layui-form-item">
                        <label class="layui-form-label">链上手续费</label>
                        <div class="layui-input-block">
                            <div class="layui-input-inline">
                                <input type="text" name="chain_fee"  autocomplete="off" placeholder="" class="layui-input" value="{{$result->chain_fee ?? 0 }}">
                            </div>
                            <div class="layui-form-mid layui-word-aux">链上归拢、提币的手续费</div>
                        </div>
                    </div>
                    @if (isset($result->id) && $result->id > 0)
                    <div class="layui-form-item" data-multi_protocol="{{$result->multi_protocol ?? 0}}" id="total_address" @if ($result->multi_protocol == 1) style="display: none;" @endif>
                        <label class="layui-form-label">总账号地址</label>
                        <div class="layui-input-block">
                            <button id="set_out_address" class="layui-btn layui-btn-warm layui-btn-sm" type="button" data-id="{{$result->id}}">转出地址</button>
                            <button id="set_in_address" class="layui-btn layui-btn-danger layui-btn-sm" type="button" data-id="{{$result->id}}">归拢地址</button>
                        </div>
                    </div>
                    @endif -->
                </div>
            </div>
        </div>
       
        <input id="currency_id" type="hidden" name="id" value="{{$result->id}}">
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" type="button" lay-submit="" lay-filter="*">保存</button>
                @if ($result->id > 0)
                <button class="layui-btn layui-btn-danger @if($result->is_protected == 1) layui-btn-disabled @endif" type="button" id="del" @if($result->is_protected == 1) disabled="disabled" title="当前币种受保护不可删除" @endif>删除</button>
                @endif
            </div>
        </div>
    </form>

@endsection

@section('scripts')
<script>
    layui.use(['upload', 'form', 'laydate', 'element', 'layer'], function () {
        // layui模块
        var upload = layui.upload 
            ,form = layui.form
            ,layer = layui.layer
            ,$ = layui.$
            ,laydate = layui.laydate
            ,element = layui.element
        var currency_id = $('#currency_id').val()
            ,currency_name = $('input[name=name]').val()
            ,index = parent.layer.getFrameIndex(window.name)
            ,is_protected = "{{$result->is_protected ?? 0}}"
            ,parent_id = "{{$parent_id ?? 0}}"
            upload_inst = upload.render({
                elem: '#upload_test' //绑定元素
                ,url: '{{URL("api/upload")}}' //上传接口
                ,done: function(res){
                    //上传完毕回调
                    if (res.type == "ok"){
                        $("#thumbnail").val(res.message)
                        $("#img_thumbnail").show()
                        $("#img_thumbnail").attr("src",res.message)
                    } else{
                        alert(res.message)
                    }
                }
                ,error: function() {
                    //请求异常回调
                }
            });
        element.on('tab(currency_tab)', function(data){
            if (data.index == 2 && is_protected == 1) {
                layer.alert('为了系统稳定,当前币种受保护,部分参数不可编辑', {
                    title: '提示',
                    skin: 'layui-layer-lan'
                })
            }
        });
        $('#contract_address_help').click(function () {
            layer.tips($(this).prop('title'), this);
        });
        form.on('radio(multi_protocol)', function (data) {
            updateFromByMultiProtocol(data.value, true);
        });
        var multi_protocol_init = layui.$('input[name=multi_protocol]:checked').val()
        if (currency_id > 0 && parent_id == 0 && is_protected == 0) {
            updateFromByMultiProtocol(multi_protocol_init, false);
        }
        function updateFromByMultiProtocol(status, reset) {
            if (status == 1) {
                reset && $('select[name=parent_id]').val(0)
                $('select[name=parent_id]').prop('disabled', 'disabled')
                reset && $('select[name=type]').val(0)
                $('select[name=type]').prop('disabled', 'disabled')
                reset && $('select[name=make_wallet]').val(3)
                $('select[name=make_wallet]').prop('disabled', 'disabled')
                $('input[name=contract_address]').prop('readonly', 'readonly')
                $('#total_address').hide();
            } else {
                $('select[name=parent_id]').removeProp('disabled', 'disabled')
                $('select[name=type]').removeProp('disabled', 'disabled')
                reset && $('select[name=make_wallet]').val(-1)
                $('select[name=make_wallet]').removeProp('disabled', 'disabled')
                $('input[name=contract_address]').removeProp('readonly', 'readonly')
                $('#total_address').show();
            }
            form.render('select');
        }
        // 监听提交
        form.on('submit(*)', function(data) {
            var data = data.field;
            $.ajax({
                url: '/admin/currency_add'
                ,type: 'post'
                ,dataType: 'json'
                ,data : data
                ,success: function(res) {
                    layer.msg(res.message, {
                        time: 2000
                        ,end: function () {
                            if (res.type == 'ok') {
                                var parent_index = parent.layer.getFrameIndex(window.name); // 先得到当前iframe层的索引
                                parent.layer.close(parent_index); // 再执行关闭
                                parent.window.layui.table.reload('data_table', {});
                            }
                        }
                    })
                }
            });
            return false;
        });
        // 设置转出地址
        $('#set_out_address').click(function () {
            parent.layui.layer.open({
                title: '设置转出地址'
                ,type: 2
                ,content: '/admin/currency/set_out_address/' + currency_id
                ,area: ['490px', '350px']
            });
        });
        // 设置转入地址
        $('#set_in_address').click(function () {
            parent.layui.layer.open({
                title: '设置转入地址'
                ,type: 2
                ,content: '/admin/currency/set_in_address/' + currency_id
                ,area: ['490px', '250px']
            });
        });
        // 删除币种
        $('#del').click(function () {
            layer.confirm('删除币种将可能会造成系统错误,真的删除币种吗?', {
                title: '警告'
                ,skin: 'layui-layer-lan' 
            }, function(index) {
                layer.close(index);
                $.ajax({
                    url: '/admin/currency_del',
                    type: 'post',
                    dataType: 'json',
                    data: {id: currency_id},
                    success:function (res) {
                        layer.msg(res.message, {
                            time: 2000
                            ,end: function () {
                                if (res.type == 'ok') {
                                    var parent_index = parent.layer.getFrameIndex(window.name); // 先得到当前iframe层的索引
                                    parent.layer.close(parent_index); // 再执行关闭
                                    parent.window.layui.table.reload('data_table', {});
                                }   
                            }
                        });                        
                    }
                });
                
            });
        });
        // 生成钱包
        $('#make_wallet').click(function () {
            layer.confirm('确定执行上币脚本？', function(index){
                $.ajax({
                    url:'/admin/currency_execute',
                    type:'post',
                    dataType:'json',
                    data:{id: currency_id},
                    success:function (res) {
                        layer.msg(res.message);
                    }
                });
            });
        });
    });
</script>
@endsection