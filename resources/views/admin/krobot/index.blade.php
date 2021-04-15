@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <table id="data_table" lay-filter="data_table"></table>
    <script type="text/html" id="operate_bar">
        <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
        <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
    </script>
    <script type="text/html" id="topbar">
        <div class="layui-inline" lay-event="add">
            <i class="layui-icon layui-icon-add-1"></i>
        </div>
    </div>
    </script>
    <script type="text/html" id="is_start">
        <input type="checkbox" name="" value="@{{ d.kr_id }}" lay-skin="switch" lay-text="开启|停止" lay-filter="is_start" @{{ d.kr_status == 1 ? 'checked' : '' }}>
    </script>
@endsection

@section('scripts')
    <script>
        layui.use(['table', 'form', 'layer'], function(){
            var table = layui.table
                ,layer = layui.layer
                ,$ = layui.$
                ,form = layui.form
            //第一个实例
            table.render({
                elem: '#data_table'
                ,url: '{{url('admin/krobot/lists')}}' //数据接口
                ,page: true //开启分页
                ,toolbar: '#topbar'
                ,cols: [[ //表头
                    {field: 'kr_id', title: 'ID', width:80, sort: true}
                    ,{field: 'kr_user', title: '机器人帐号', width:120}
                    ,{field: 'kr_stock', title: '交易币', width:120}
                    ,{field:'kr_money', title:'法币', width:120}
                    ,{field:'kr_min_price', title:'价格下限', width:120}
                    ,{field: 'kr_max_price', title: '价格上限', width:120}
                    ,{field: 'kr_min_number', title: '数量下限', width:120}
                    ,{field: 'kr_max_number', title: '数量上限', width:120}
                    ,{field: 'kr_status', title: '状态', width:100, templet: '#is_start'}
                    ,{title:'操作', minWidth:100, toolbar: '#operate_bar'}
                ]]
            });

            form.on('switch(is_start)', function(data) {
                var symbol = data.elem.checked ? 1 : 0;
                var kr_id = data.value
                $.ajax({
                    url: '/admin/krobot/change_start'
                    ,type: 'POST'
                    ,data: {kr_id: kr_id, symbol: symbol}
                    ,success: function (res) {
                        layer.msg(res.message);
                    }
                    ,error: function (res) {
                        layer.msg('网络错误');
                    }
                });
                
            })

            table.on('toolbar(data_table)', function(obj) {
                switch(obj.event) {
                    case 'add':
                        layer_show('添加机器人','{{url('admin/krobot/add')}}', 720, 720)
                        break;
                }
            });

            table.on('tool(data_table)', function(obj) {
                var data = obj.data;
                if (obj.event === 'del') { //删除
                    layer.confirm('真的要删除吗？', function (index) {
                        //向服务端发送删除指令
                        $.ajax({
                            url: "{{url('admin/krobot/del')}}",
                            type: 'post',
                            dataType: 'json',
                            data: {kr_id: data.kr_id},
                            success: function (res) {
                                if (res.type == 'ok') {
                                    obj.del(); //删除对应行（tr）的DOM结构，并更新缓存
                                    layer.close(index);
                                } else {
                                    layer.close(index);
                                    layer.alert(res.message);
                                }
                            }
                        });
                    });
                } else if(obj.event === 'edit') {
                    layer_show('添加机器人','{{url('admin/krobot/add')}}?kr_id='+data.kr_id, 720, 720);
                }
            });
        });
    </script>

@endsection