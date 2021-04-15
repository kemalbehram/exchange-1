@extends('admin._layoutNew')

@section('page-head')
<style>
    element.style {
        color: #fff;
        background-color: #01AAED;
        display: block;
    }
</style>
@endsection

@section('page-content')
<div style="margin-top: 10px;width: 100%;margin-left: 10px;">
    <!-- <div class="layui-form-item">
        <label class="layui-form-label">法币交易合计</label>
        <div class="layui-input-block" style="width:90%">
            <blockquote class="layui-elem-quote layui-quote-nm" id="sum">0</blockquote>
        </div>
    </div> -->
    <form class="layui-form layui-inline" action="">
        <div class="layui-inline" style="margin-left: 10px;">
            <label>交易币&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 90px;">
                <select name="currency_id" id="currency_id">
                    <option value="0">全部</option>
                    @foreach ($currency as $value)
                        <option value="{{$value->id}}" >{{$value->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>交易方向&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 90px;">
                <select name="type" id="type_type">
                    <option value="">全部</option>
                    <option value="sell">买入</option>
                    <option value="buy">卖出</option>

                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>交易状态&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 90px;">
                <select name="is_sure" id="is_sure_type">
                    <option value="-1">全部</option>
                    <option value="0">未确认</option>
                    <option value="1">已确认</option>
                    <option value="2">已取消</option>
                    <option value="3">已付款</option>
                    <option value="4">维权中</option>
                </select>
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>开始日期&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width:100px;">
                <input type="text" class="layui-input" id="start_time" value="">
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label>结束日期&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width:100px;">
                <input type="text" class="layui-input" id="end_time" value="">
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label >需求id&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 60px;">
                <input type="text" name="legal_deal_send_id" autocomplete="off" class="layui-input">
            </div>
        </div>
        <div class="layui-inline" style="margin-left: 10px;">
            <label >用户账号&nbsp;&nbsp;</label>
            <div class="layui-input-inline" style="width: 110px;">
                <input type="text" name="account_number" autocomplete="off" class="layui-input">
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
<script type="text/html" id="barDemo">
    <button class="layui-btn layui-btn-xs @{{ [0, 3, 4].indexOf(d.is_sure) == -1 ? 'layui-btn-disabled' : ''}}" lay-event="confirm" @{{ [0, 3, 4].indexOf(d.is_sure) == -1 ? 'disabled' : ''}}>确认</button>
    <button class="layui-btn layui-btn-xs layui-btn-danger @{{ [0, 3, 4].indexOf(d.is_sure) == -1 ? 'layui-btn-disabled' : ''}}" lay-event="cancel" @{{ [0, 3, 4].indexOf(d.is_sure) == -1 ? 'disabled' : ''}}>取消</button>
</script>

<script type="text/html" id="type">
    @{{d.type=="buy" ? '<span class="layui-badge layui-bg-green">'+'卖出'+'</span>' : '' }}
    @{{d.type=="sell" ? '<span class="layui-badge layui-bg-red">'+'买入'+'</span>' : '' }}
</script>
<script type="text/html" id="is_sure">
    @{{d.is_sure == 0 ? '' : '' }}
    @{{d.is_sure == 1 ? '<span class="layui-badge layui-bg-blue "  >已确认</span>' : '' }}
    @{{d.is_sure == 2 ? '<span class="layui-badge layui-bg-orange">已取消</span>' : '' }}
    @{{d.is_sure == 3 ? '<span class="layui-badge layui-bg-green">已付款</span>' : '' }}
    @{{d.is_sure == 4 ? '<span class="layui-badge">维权中</span>' : '' }}
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
            ,url: '/admin/legal_deal/list' //数据接口
            ,page: true //开启分页
            ,id: 'mobileSearch'
            ,height: 'full-80'
            ,toolbar: true
            ,cols: [[ //表头
                {field: 'id', title: 'ID', width: 80, sort: true}
                ,{field: 'legal_deal_send_id', title: '需求id', width: 90}
                ,{field: 'seller_name', title: '商家名称', width:120}
                ,{field: 'account_number', title: '用户账号', width:120}
                ,{field: 'user_realname', title: '真实姓名', width:120}
                ,{field: 'currency_name', title: '交易币', width: 90}
                ,{field: 'type', title: '发布方向', width:90, templet: '#type'}
                ,{field: 'way_name', title: '支付方式', width: 90}
                ,{field: 'price', title: '单价', width: 100 }
                ,{field: 'number', title: '交易数量', width: 100}
                ,{field: 'deal_money', title: '交易金额', width :120}
                ,{field: 'is_sure', title: '状态', width: 90, templet: '#is_sure'}
                ,{field: 'format_create_time', title: '交易时间', width: 170}
                ,{field: 'payed_at', title: '支付时间', width: 170}
                ,{field: 'arbitrated_at', title: '申请维权时间', width: 170}
                ,{field: 'confirmed_at', title: '确认时间', width: 170}
                ,{fixed: 'right', title: '操作', minWidth: 130, align: 'center', toolbar: '#barDemo'}
            ]], done: function(res) {
                // $("#sum").text(res.extra_data);
            }
        });

        table.on('tool(test)', function(obj) {
            var data = obj.data;
            if(obj.event == 'cancel') {
                layer.confirm('真的确定要取消交易吗?', function(index){
                    $.ajax({
                        url: '/admin/admin_legal_pay_cancel',
                        type: 'post',
                        dataType: 'json',
                        data: {id:data.id},
                        success: function (res) {
                            if (res.type == 'error') {
                                layer.msg(res.message);
                            } else {
                                layer.close(index);
                                window.location.reload();
                                layer.alert(res.message);
                            }
                        }
                    });
                });
            } else if(obj.event == 'confirm') {
                if (data.is_sure == 0) {
                    layer.confirm('当前交易买方还没有提交付款,真的不等买方提交付款信息就进行确认吗?', function (index) {
                        layer.close(index);
                        confirmDeal(data.id);
                    });
                } else if ([3, 4].indexOf(data.is_sure) != -1) {
                    confirmDeal(data.id);
                } else {
                    layer.msg('当前状态不能再进行确认了');
                }
            }
        });
        // 确认交易
        function confirmDeal(id) {
            layer.confirm('进行确认后无论卖方是否收到付款,都会将卖方出售的币发放给买方,是否真的要确认？', function(index) {
                layer.close(index);
                $.ajax({
                    url: '/admin/legal_deal_admin_sure',
                    type: 'post',
                    dataType: 'json',
                    data: {id: id},
                    success:function (res) {
                        if (res.type == 'error') {
                            layer.msg(res.message);
                        } else {
                            layer.close(index);
                            window.location.reload();
                            layer.alert(res.message);
                        }
                    }
                });
            });
        }

        //监听提交
        form.on('submit(mobile_search)', function (data) {
            table.reload('mobileSearch', {
                where: data.field,
                page: {curr: 1}         //重新从第一页开始
            });
            return false;
        });

    });
</script>

@endsection