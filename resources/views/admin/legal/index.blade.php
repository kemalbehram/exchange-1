@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <div style="margin-top: 10px;width: 100%;margin-left: 10px;">
        <!-- <div class="layui-form-item">
            <label class="layui-form-label">法币交易需求合计</label>
            <div class="layui-input-block" style="width:90%">
                <blockquote class="layui-elem-quote layui-quote-nm" id="sum">0</blockquote>
            </div>
        </div> -->

        <form class="layui-form layui-inline" action="">
            <!-- <div class="layui-inline" style="margin-left: 50px;">
                <label >交易账号&nbsp;&nbsp;</label>
                <div class="layui-input-inline">
                    <input type="text" name="account_number" autocomplete="off" class="layui-input">
                </div>
            </div> -->
            <div class="layui-inline" style="margin-left: 10px;">
                <label>状态&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 90px;">
                    <select name="is_done" id="is_done_type">
                        <option value="">全部</option>
                        <option value="0">未完成</option>
                        <option value="1">已完成</option>
                        <option value="2">已撤单</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label>方向&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 90px;">
                    <select name="type" id="type_type">
                        <option value="">全部</option>
                        <option value="buy">求购</option>
                        <option value="sell">出售</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline" style="margin-left: 10px;">
                <label>币种&nbsp;&nbsp;</label>
                <div class="layui-input-inline" style="width: 90px;">
                    <select name="currency_id" id="currency_id">
                        <option value="0">全部</option>
                        @foreach ($currency as $value)
                            <option value="{{$value->id}}">{{$value->name}}</option>
                        @endforeach
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
<script type="text/html" id="switchTpl">
    <input type="checkbox" name="is_recommend" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="sexDemo" @{{ d.is_recommend == 1 ? 'checked' : '' }}>
</script>
<script type="text/html" id="barDemo">
    <button class="layui-btn layui-btn-xs @{{(d.is_done == 1 || d.is_done == 2) ? 'layui-btn-disabled' : '' }}" lay-event="back" @{{(d.is_done == 1 || d.is_done == 2) ? 'disabled' : '' }}>撤回</button>
</script>
<script type="text/html" id="type">
    @{{d.type=="buy" ? '<span class="layui-badge layui-bg-green">求购</span>' : '' }}
    @{{d.type=="sell" ? '<span class="layui-badge layui-bg-red">出售</span>' : '' }}

</script>
<script type="text/html" id="is_done">
    @{{d.is_done == 0 ? '<span class="layui-badge-rim">未完成</span>' : '' }}
    @{{d.is_done == 1 ? '<span class="layui-badge layui-bg-green">已完成</span>' : '' }}
    @{{d.is_done ==2 ? '<span class="layui-badge layui-bg-gray">已撤单</span>' : '' }}
</script>
<script type="text/html" id="is_shelves">
    <input type="checkbox" name="is_shelves" lay-skin="switch" value="@{{d.id}}" lay-text="上架|下架" @{{d.is_shelves == 1 ? 'checked' : ''}} @{{(d.is_done == 1 || d.is_done == 2) ? 'disabled' : '' }} lay-filter="is_shelves">
</script>
<script type="text/html" id="limitation">
    <span class="layui-badge layui-bg-gray">@{{d.limitation.min}}--@{{d.limitation.max}}</span>
</script>
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
            ,url: '/admin/legal/list' //数据接口
            ,page: true //开启分页
            ,id:'mobileSearch'
            ,height: 'full-80'
            ,toolbar: true
            ,cols: [[ //表头
                {field: 'id', title: 'ID', width: 80, sort: true}
                ,{field: 'seller_name', title: '商家名称', width: 200}
                ,{field: 'type', title: '发布方向', width: 100, templet: '#type'}
                ,{field: 'way_name', title: '支付方式', width: 100}
                ,{field: 'price', title: '单价', width: 100 }
                ,{field: 'total_number', title: '数量', width: 150}
                ,{field: 'surplus_number', title: '剩余数量', width: 150}
                ,{field: 'currency_name', title: '交易币', width: 80}
                ,{field: 'is_done', title: '状态', width: 100, templet: '#is_done'}
                ,{field: 'is_shelves', title: '上架', width: 100, templet: '#is_shelves'}
                ,{field: 'create_time', title: '创建时间', width: 170}
                ,{title: '操作', width: 140, toolbar: '#barDemo'}

            ]],done: function(res){
                //$("#sum").text(res.extra_data);
            }
        });

        form.on('switch(is_shelves)', function(data) {
            $.ajax({
                url: '/admin/send/is_shelves',
                type: 'post',
                dataType: 'json',
                data: {id: data.value},
                success: function (res) {
                    layer.msg(res.message);
                }
            });
        }); 


        table.on('tool(test)', function(obj) {
            var data = obj.data;
            if (obj.event === 'del') {
                layer.confirm('该操作极其危险请谨慎操作,真确认要删除吗?', function (index) {
                    var loading = layer.load(1, {
                        shade: [0.1,'#fff'] //0.1透明度的白色背景
                    });
                    $.ajax({
                        url:'/admin/send/del',
                        type:'post',
                        dataType:'json',
                        data:{id:data.id},
                        success:function (res) {
                            layer.close(loading);
                            if (res.type == 'error') {
                                layer.msg(res.message);
                            } else {
                                obj.del();
                                layer.close(index);
                            }
                        }
                    });
                });
            } else if(obj.event === 'back') {
                layer.confirm('撤回前请先取消该发布下未支付的交易,确认要撤回发布吗?', function (index) {
                    var loading = layer.load(1, {
                        shade: [0.1,'#fff'] //0.1透明度的白色背景
                    });
                    $.ajax({
                        url: '/admin/send/back',
                        type: 'post',
                        dataType: 'json',
                        data: {id: data.id},
                        success:function (res) {
                            layer.close(loading);
                            layer.msg(res.message, {
                                time: 2000
                                ,end: function () {
                                    layer.close(index);
                                }
                            });
                            
                        }
                    });
                });
            }
        });

        //监听提交
        form.on('submit(mobile_search)', function(data){
            var seller_name = data.field.seller_name
                ,type = $('#type_type').val()
                ,currency_id = $('#currency_id').val()
                ,end_time=$('#end_time').val()
                ,start_time = $('#start_time').val()
                ,is_done = $('#is_done_type').val()

            table.reload('mobileSearch',{
                where:{
                    seller_name:seller_name,
                    type:type,
                    currency_id:currency_id,
                    start_time:start_time,
                    end_time:end_time,
                    is_done:is_done
                },
                page: {curr: 1}         //重新从第一页开始
            });
            return false;
        });

    });
</script>

@endsection