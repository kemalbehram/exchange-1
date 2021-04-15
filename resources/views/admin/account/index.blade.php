@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
   <div class="layui-inline layui-form">
        <label class="layui-form-label">用户账号</label>
        <div class="layui-input-inline" >
            <input type="datetime" name="account" placeholder="请输入手机号或邮箱" autocomplete="off" class="layui-input" value="">
        </div>
       <div class="layui-input-inline" style="margin-left: 10px;">
           <input type="text" name="start_time" id="start_time" placeholder="请输入开始时间" autocomplete="off" class="layui-input" value="">
       </div>
       <div class="layui-input-inline" style="margin-left: 10px;">
           <input type="text" name="end_time" id="end_time" placeholder="请输入结束时间" autocomplete="off" class="layui-input" value="">
       </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>日志类型&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 90px;">
                <select name="type" id="type" class="layui-input">
                    <option value="">所有类型</option>
                    @foreach ($types as $key=>$type)
                    <option value="{{ $key }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>货币类型&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 90px;">
                <select name="currency_type" id="currency_type" class="layui-input">
                    <option value="0">所有</option>
                    @foreach ($currency_type as $key=>$type)
                        <option value="{{ $type['id'] }}" class="ww">{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
           <label>账户类型&nbsp;&nbsp;</label>
           <div class="layui-input-inline" style="width: 90px;">
                <select name="balance_type" id="balance_type" class="layui-input">
                    <option value="0">所有</option>
                    <option value="1">法币</option>
                    <option value="2">币币</option>
                    <option value="3">合约</option>
                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
           <label>是否锁定&nbsp;&nbsp;</label>
           <div class="layui-input-inline" style="width: 90px;">
                <select name="lock_type" id="lock_type" class="layui-input">
                    <option value="-1">所有</option>
                    <option value="1">是</option>
                    <option value="0">否</option>
                </select>
            </div>
        </div>
        <button class="layui-btn btn-search" id="mobile_search" lay-submit lay-filter="mobile_search"> <i class="layui-icon">&#xe615;</i> </button>
    </div>
    <table id="accountlist" lay-filter="accountlist"></table>

@endsection

@section('scripts')
<script>
    window.onload = function() {
        document.onkeydown = function(event) {
            var e = event || window.event || arguments.callee.caller.arguments[0];
            if(e && e.keyCode == 13) { // enter 键
                $('#mobile_search').click();
            }
        };
        layui.use(['element', 'form', 'layer', 'table','laydate'], function () {
            var element = layui.element;
            var layer = layui.layer;
            var table = layui.table;
            var $ = layui.$;
            var form = layui.form;
            var laydate = layui.laydate;
            laydate.render({
                elem: '#start_time'
            });
            laydate.render({
                elem: '#end_time'
            });
            var data_table = table.render({
                elem: '#accountlist'
                ,url: '/admin/account/list'
                ,page: true
                ,limit: 20
                ,toolbar: true
                ,height: 'full-80'
                ,cols: [[
                    {field: 'id', title: 'ID', width: 100}
                    ,{field: 'account', title: '用户账号',width: 150}
                    ,{field: 'account_number', title: '用户交易账号',width: 150}
                    // ,{field: 'before', title:'变动前', width:180, templet: '<div><div style="text-align: right;">@{{d.before}}</div></div>'}
                    ,{field: 'value', title:'变动量', width:180, templet: '<div><div style="text-align: right;">@{{d.value}}</div></div>'}
                    // ,{field: 'after', title:'变动后', width:180, templet: '<div><div style="text-align: right;">@{{d.after}}</div></div>'}
                    ,{field: 'currency_name', title:'币种', width:100}
                    // ,{field: 'balance_type_name', title: '账户名称', width: 100}
                    ,{field: 'lock_type_name', title: '是否锁定', width: 100, hide: true}
                    ,{field: 'info', title:'说明', width:260}
                    ,{field: 'created_time', title: '变动时间', width:170}
                ]]
            });

            form.on('submit(mobile_search)', function (obj) {
                console.log(obj)
                data_table.reload({
                    where: obj.field
                    ,page: {curr: 1}
                })
            });
            //监听工具条
            table.on('tool(accountlist)', function (obj) { //注：tool是工具条事件名，test是table原始容器的属性 lay-filter="对应的值"
                var data = obj.data;
                var layEvent = obj.event;
                var tr = obj.tr;

                if (layEvent === 'viewDetail') { //编辑
                    var index = layer.open({
                        title: '查看详情'
                        , type: 2
                        , content: '{{url('admin/account/viewDetail')}}?id=' + data.id
                        , maxmin: true
                    });
                    layer.full(index);
                }
            });
        });
    }
</script>    
@endsection