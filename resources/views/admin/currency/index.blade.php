@extends('admin._layoutNew')

@section('page-head')
<style>
    p.percent {
        text-align: right;
        margin-right: 10px;
    }
    p.percent::after {
        content: '%';
    }
</style>
@endsection

@section('page-content')
    <table id="data_table" lay-filter="data_table"></table>        
@endsection

@section('scripts')
    <script type="text/html" id="toolbar">
        <div class="layui-btn-group">
            @if (Request::input('parent_id', 0) > 0)
            <button class="layui-btn layui-btn-sm layui-btn-primary" lay-event="return" title="返回">
                <i class="layui-icon layui-icon-return"></i>
            </button>
            @endif
            <button class="layui-btn layui-btn-sm" lay-event="add" title="添加币种">
                <i class="layui-icon layui-icon-add-1"></i>
            </button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" lay-event="edit" title="编辑币种">
                <i class="layui-icon layui-icon-edit"></i>
            </button>
            @if (Request::input('parent_id', 0) <= 0)
            <button class="layui-btn layui-btn-sm layui-btn-primary" lay-event="plate_list" title="板块管理">
                <i class="layui-icon layui-icon-chart-screen"></i>
            </button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" lay-event="match" title="交易对管理">
                <i class="layui-icon layui-icon-note"></i>
            </button>
            @endif
        </div>
    </script>
    <script type="text/html" id="switchTpl">
        <input type="checkbox" name="is_display" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="isDisplay" @{{ d.is_display == 1 ? 'checked' : '' }}>
    </script>
    <script type="text/html" id="legal">
        <input type="checkbox" name="is_legal" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="isLegal" disabled @{{ d.is_legal == 1 ? 'checked' : '' }}>
    </script>
    <script type="text/html" id="lever">
        <input type="checkbox" name="is_lever" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="isLever" disabled @{{ d.is_lever == 1 ? 'checked' : '' }}>
    </script>
    <script type="text/html" id="has_son">
        <div style="text-align: center">
            @{{# if(d.multi_protocol == 1) { }}
            <span class="layui-badge layui-bg-green">是</span>
            @{{# } else if(d.multi_protocol == 0) { }}
            <span class="layui-badge layui-bg-gray">否</span>
            @{{# } }}
        </div>
    </script>
    <script type="text/html" id="multi_protocol_jump">
        @{{# if (d.multi_protocol == 1) { }}
            <span title="点击查看多协议子币种">
                <a style="color: #3396bd; font-weight: bold;" href="/admin/currency?parent_id=@{{d.id}}">@{{d.name}}</a>
            </span>
        @{{# } else { }}
            <span>@{{d.name}}</span>
        @{{# } }}
    </script>
    <script>
        layui.use(['table','form'], function(){
            var table = layui.table;
            var $ = layui.jquery;
            var form = layui.form;
            var data_table = table.render({
                elem: '#data_table'
                ,toolbar: '#toolbar'
                ,url: '/admin/currency_list' //数据接口
                ,page: true //开启分页
                ,cols: [[ //表头
                    {field: '', type: 'radio', width: 60}
                    ,{field: 'id', title: 'ID', width: 60, sort: true}
                    ,{field: 'name', title: '名称', width: 160, templet: '#multi_protocol_jump'}
                    ,{field: 'min_number', title: '最少提币量', width: 120}
                    ,{field: 'rate', title: '提币费率', width: 100, templet: '<div><p class="percent">@{{d.rate}}</p></div>'}
                    ,{field: 'contract_address', title: '合约标识', width: 360, hide: true}
                    ,{field: 'sort', title: '排序', width: 60}
                    ,{field: 'type', title: '基于', width: 90, templet: '#typetml'}
                    ,{field: 'is_legal', title: '法币', width: 90, templet: '#legal', hide: true}
                    ,{field: 'is_lever', title: '合约', width: 90, templet: '#lever', hide: true}
                    ,{field: 'is_display', title:'显示', width: 90, templet: '#switchTpl'}
                    ,{field: 'multi_protocol', title:'多协议支持', width: 120, templet: '#has_son', hide: true}
                    ,{field: 'created_at', title: '添加时间', width:170, hide: true}
                    ,{field: 'updated_at', title: '修改时间', width:170, hide: true}
                ]]
                ,where: {
                    parent_id: {{Request::input("parent_id") ?? 0}}
                }
            });
            //监听是否显示操作
            form.on('switch(isDisplay)', function(obj){
                var id = this.value;
                $.ajax({
                    url:'{{url('admin/currency_display')}}',
                    type:'post',
                    dataType:'json',
                    data:{id:id},
                    success:function (res) {
                        if(res.error != 0){
                            layer.msg(res.message);
                        }
                    }
                });
            });

            table.on('toolbar(data_table)', function (obj) {
                switch (obj.event) {
                    case 'add':
                        layer.open({
                            title: '添加币种'
                            ,skin: 'layui-layer-lan'
                            ,type: 2
                            ,content: '/admin/currency_add?parent_id={{Request::input("parent_id") ?? 0}}'
                            ,area: ['800px', '650px']
                        });
                        break;
                    case 'plate_list':
                        layer.open({
                            title: '板块管理'
                            ,type: 2
                            ,content: '/admin/currency_plates/index'
                            ,area: ['80%', '80%']
                        });
                        break;
                    case 'edit':
                        var check_status = table.checkStatus('data_table')
                        if (check_status.data.length != 1) {
                            return layer.msg('请选择要编辑的币种')
                        }
                        layer.open({
                            title: '编辑币种'
                            ,skin: 'layui-layer-molv'
                            ,type: 2
                            ,content: '/admin/currency_add?id=' + check_status.data[0].id
                            ,area: ['800px', '650px']
                        });
                        break;
                    case 'match':
                        var check_status = table.checkStatus('data_table')
                        if (check_status.data.length != 1) {
                            return layer.msg('请选择要编辑的币种')
                        }
                        if (check_status.data[0].is_legal == 0) {
                            return layer.msg('所选币种不是法币,无法设置交易对')
                        }
                        layer.open({
                            title: check_status.data[0].name + '交易对管理'
                            ,type: 2
                            ,content: '/admin/currency/match/' + check_status.data[0].id
                            ,area: ['960px', '600px']
                        });
                        break;
                    case 'return':
                        window.location = '/admin/currency';
                        break;
                    default:
                        break;
                }
            });

            table.on('tool(data_table)', function(obj){
                var data = obj.data;
                if (obj.event == 'match') {
                    layer.open({
                        title: '交易对管理'
                        ,type: 2
                        ,content: '/admin/currency/match/' + data.id
                        ,area: ['960px', '600px']
                    });
                } else if(obj.event === 'son'){
                    layer_show('添加子币', '/admin/currency_add?parent_id='+data.id);
                }
            });
        });
    </script>

@endsection