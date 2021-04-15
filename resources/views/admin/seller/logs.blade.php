@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <div style="margin-top: 10px;width: 100%;margin-left: 10px;">
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
            <div class="layui-inline" style="margin-left: 10px;">
                <label>是否锁定&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 90px;">
                    <select name="is_lock" id="is_lock" class="layui-input">
                        <option value="-1">所有</option>
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label>开始日期&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width:100px;">
                    <input type="text" class="layui-input" id="start_time" value="" name="start_time">
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label>结束日期&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width:100px;">
                    <input type="text" class="layui-input" id="end_time" value="" name="end_time">
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label >商家名称&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 110px;">
                    <input type="text" name="seller_name" autocomplete="off" class="layui-input">
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label >交易账号&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 110px;">
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
    <table id="demo" lay-filter="test"></table>
@endsection

@section('scripts')
    <script text="text/html" id="is_lock_templet">
        <div>
            @{{# if(d.is_lock) { }}
                <span class="layui-badge layui-bg-black">是</span
            @{{# } else { }}
                <span class="layui-badge-rim">否</span>
            @{{# } }}
        </div>
    </script>
    <script>
        layui.use(['table', 'form', 'layer', 'laydate'], function(){
            var table = layui.table;
            var $ = layui.jquery;
            var form = layui.form;
            var layer = layui.layer;
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
                ,url: '/admin/seller/logs_list' //数据接口
                ,page: true //开启分页
                ,id: 'mobileSearch'
                ,height: 'full-80'
                ,toolbar: true
                ,limit: 20
                ,cols: [[ //表头
                    {field: 'id', title: 'ID', width: 80, sort: true}
                    ,{field: 'account_number', title: '交易账号', width: 120}
                    ,{field: 'seller_name', title: '商家名称', width: 200}
                    ,{field: 'currency_name', title: '币种', width: 80}
                    ,{field: 'is_lock', title: '锁定', width: 80, templet: "#is_lock_templet"}
                    ,{field: 'before', title: '原余额', width: 200, templet: '<div><div style="text-align: right;">@{{d.before}}</div></div>'}
                    ,{field: 'value', title: '变动额', width: 200, templet: '<div><div style="text-align: right;">@{{d.value}}</div></div>'}
                    ,{field: 'after', title: '现余额', width: 200, templet: '<div><div style="text-align: right;">@{{d.after}}</div></div>'}
                    ,{field: 'memo', title: '说明', width: 300}
                    ,{field: 'created_at', title: '变动时间', width: 170}
                ]]
            });

            //监听提交
            form.on('submit(mobile_search)', function (data) {
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