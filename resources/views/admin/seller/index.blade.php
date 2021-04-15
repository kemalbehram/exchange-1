@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <div style="margin-top: 10px;width: 100%;margin-left: 10px;">
        <button class="layui-btn layui-btn-normal layui-btn-radius" onclick="layer_show('添加商家', '/admin/seller_add')">添加商家</button>
        <form class="layui-form layui-form-pane layui-inline" action="">
            <div class="layui-inline" style="margin-left: 10px;">
                <label>币种&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 90px;">
                    <select name="currency_id" id="currency_id">
                        <option value="0">全部</option>
                        @foreach ($currencies as $currency)
                            <option value="{{$currency->id}}">{{$currency->name}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 0px;">
                <label class="layui-form-label">交易账号</label>
                <div class="layui-input-inline">
                    <input type="text" name="account_number" autocomplete="off" class="layui-input">
                </div>
            </div>
            <div class="layui-inline">
                <div class="layui-input-inline">
                    <button class="layui-btn" lay-submit="" lay-filter="mobile_search"><i class="layui-icon">&#xe615;</i></button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/html" id="switchTpl">
        <input type="checkbox" name="is_recommend" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="sexDemo" @{{ d.is_recommend == 1 ? 'checked' : '' }}>
    </script>

    <table id="demo" lay-filter="test"></table>
    <script type="text/html" id="barDemo">
        <a class="layui-btn layui-btn-primary layui-btn-xs" lay-event="detail">调节</a>
        <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
        <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
    </script>

@endsection

@section('scripts')
    <script>

        layui.use(['table','form'], function(){
            var table = layui.table;
            var $ = layui.jquery;
            var form = layui.form;
            //第一个实例
            table.render({
                elem: '#demo'
                ,url: '/admin/seller_list' //数据接口
                ,page: true //开启分页
                ,id: 'mobileSearch'
                ,toolbar: true
                ,height: 'full-80'
                ,cols: [[ //表头
                    {field: 'id', title: 'ID', width: 80, sort: true}
                    ,{field: 'account_number', title: '交易账号', width: 120}
                    ,{field: 'name', title: '名称', width: 200}
                    ,{field: 'seller_balance', title: '商家余额', width: 170}
                    ,{field: 'lock_seller_balance', title: '锁定余额', width: 170}
                    ,{field: 'currency_name', title: '币名', width: 90}
                    ,{field: 'create_time', title: '添加时间', width: 170}
                    // ,{field: 'wechat_nickname', title: '微信昵称', minWidth:80}
                    // ,{field: 'wechat_account', title: '微信账号', minWidth:80}
                    // ,{field: 'ali_nickname', title: '支付宝昵称', minWidth:80}
                    // ,{field: 'ali_account', title: '支付宝账号', minWidth:80}
                    // ,{field: 'bank_name', title: '银行名称', minWidth:80}
                    ,{title:'操作', width: 170, toolbar: '#barDemo'}

                ]]
            });

            table.on('tool(test)', function(obj) {
                var data = obj.data;
                if(obj.event === 'del') {
                    layer.confirm('真的确认要删除商家吗?', function(index) {
                        $.ajax({
                            url:'/admin/seller_del',
                            type:'post',
                            dataType:'json',
                            data:{id:data.id},
                            success:function (res) {
                                if(res.type == 'error') {
                                    layer.msg(res.message);
                                } else {
                                    obj.del();
                                    layer.close(index);
                                }
                            }
                        });
                    });
                } else if(obj.event === 'edit') {
                    layer_show('编辑商家', '/admin/seller_add?id=' + data.id);
                } else if(obj.event === 'detail') {
                    layer_show('调节商家余额', '/admin/seller/adjust_balance?id=' + data.id, 590, 400);
                }
            });

            //监听提交
            form.on('submit(mobile_search)', function(data) {
                var data = data.field;
                table.reload('mobileSearch', {
                    where: data,
                    page: {curr: 1}         //重新从第一页开始
                });
                return false;
            });
        });
    </script>

@endsection