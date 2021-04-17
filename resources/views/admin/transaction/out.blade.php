@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <div style="margin-top: 10px;width: 100%;margin-left: 10px;">
        <!-- <div class="layui-form-item">
            <label class="layui-form-label">撮合交易（挂卖）合计</label>
            <div class="layui-input-block" style="width:90%">
                <blockquote class="layui-elem-quote layui-quote-nm" id="sum">0</blockquote>
            </div>
        </div> -->
        <form class="layui-form layui-form-pane layui-inline" action="">
            <div class="layui-inline" style="margin-left: -10px;">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-inline">
                    <input type="text" name="account_number" autocomplete="off" class="layui-input">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">法币</label>
                <div class="layui-input-inline" style="width:130px;">
                    <select name="legal" id="type_type">
                        <option value="-1">全部</option>
                        @foreach ($legal_currencies as $currency)
                        <option value="{{$currency->id}}">{{$currency->name}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">交易币</label>
                <div class="layui-input-inline" style="width:130px;">
                    <select name="currency" id="type_type">
                        <option value="-1">全部</option>
                        @foreach ($currencies as $currency)
                        <option value="{{$currency->id}}">{{$currency->name}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">开始日期：</label>
                <div class="layui-input-inline" style="width:120px;">
                    <input type="text" class="layui-input" id="start_time" value="" name="start_time">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">结束日期：</label>
                <div class="layui-input-inline" style="width:120px;">
                    <input type="text" class="layui-input" id="end_time" value="" name="end_time">
                </div>
            </div>
            <div class="layui-inline">
                <div class="layui-input-inline">
                    <button class="layui-btn" lay-submit="" lay-filter="mobile_search"><i class="layui-icon">&#xe615;</i></button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/html" id="operate">
        <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="match">撮合</a>
        <a class="layui-btn layui-btn-xs" lay-event="cancel">撤回</a>
        <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
    </script>

    <table id="demo" lay-filter="test"></table>

@endsection

@section('scripts')
    <script>

        layui.use(['table','form','laydate'], function(){
            var table = layui.table;
            var $ = layui.jquery;
            var form = layui.form;
            var laydate = layui.laydate;
            laydate.render({
                elem: '#start_time'
            });
            laydate.render({
                elem: '#end_time'
            });
            //第一个实例
            table.render({
                elem: '#demo'
                ,url: '{{url('admin/out_list')}}' //数据接口
                ,page: true //开启分页
                ,id:'mobileSearch'
                ,toolbar: true
                ,totalRow: true
                ,height: 'full-60'
                ,cols: [[ //表头
                    {field: 'id', title: 'ID', width: 90, sort: true}
                    ,{field: 'account_number', title: '用户名', width: 120}
                    ,{field: 'currency_name', title: '交易币', width: 90}
                    ,{field: 'legal_name', title: '法币', width: 90}
                    ,{field: 'price', title: '价格', width: 150, templet: '<div><div style="text-align: right;">@{{Number(d.price).toFixed(8)}}</div></div>'}
                    ,{field: 'total', title: '挂单数量', width: 150, totalRow: true, templet: '<div><div style="text-align: right;">@{{Number(d.total).toFixed(8)}}</div></div>'}
                    ,{field: 'number', title: '剩余数量', width: 150, totalRow: true, templet: '<div><div style="text-align: right;">@{{Number(d.number).toFixed(8)}}</div></div>'}
                    ,{field: '', title: '价值', width: 160, templet: '<div><div style="text-align: right;">@{{Number(d.price * d.number).toFixed(8)}}</div></div>'}
                    ,{field: 'create_time', title: '创建时间', width: 170}
                    ,{field: 'operate', title: '操作', width: 170, templet: '#operate'}
                ]], done: function(res){
                    $("#sum").text(res.extra_data);
                }
            });

            var data_table = table.on('tool(test)', function(obj) {
                var data = obj.data;

                if(obj.event === 'del') {
                    layer.confirm('删除后余额不退回,确定要删除吗?', function(index) {
                        // return layer.msg('此操作太危险,暂不支持');
                        $.ajax({
                            url:'/admin/exchange_del',
                            type:'get',
                            dataType:'json',
                            data:{id: data.id, "type": 'out'},
                            success:function (res) {
                                if(res.type == 'error'){
                                    layer.msg(res.message);
                                } else {
                                    obj.del();
                                    layer.close(index);
                                }
                            }
                        });
                    });
                } else if(obj.event === 'cancel') {
                    layer.confirm('真的确认要撤回吗?', function (index) {
                        $.ajax({
                            url:'/admin/exchange_cancel',
                            type:'get',
                            dataType:'json',
                            data:{id: data.id, "type": 'out'},
                            success:function (res) {
                                if(res.type == 'error'){
                                    layer.msg(res.message);
                                } else {
                                    obj.del();
                                    layer.close(index);
                                }
                            }
                        });
                    });
                }else if(obj.event === 'match') {
                    layer.confirm('真的确认要手动撮合吗?', function (index) {
                        $.ajax({
                            url:'/admin/exchange_match',
                            type:'get',
                            dataType:'json',
                            data:{id: data.id, "type": 'out'},
                            success:function (res) {
                                if(res.type == 'error'){
                                    layer.msg(res.message);
                                } else {
                                    // obj.del();
                                    layer.close(index);
                                }
                            }
                        });
                    });
                }
            });

            //监听提交
            form.on('submit(mobile_search)', function(data){
                var account_number = data.field.account_number;
                table.reload('mobileSearch',{
                    where: data.field,
                    page: {curr: 1}         //重新从第一页开始
                });
                return false;
            });

        });
    </script>

@endsection