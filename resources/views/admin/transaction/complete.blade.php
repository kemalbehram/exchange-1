@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <div style="margin-top: 10px;width: 100%;margin-left: 10px;">
        <!-- <div class="layui-form-item">
            <label class="layui-form-label">撮合交易（完成）合计</label>
            <div class="layui-input-block" style="width:90%">
                <blockquote class="layui-elem-quote layui-quote-nm" id="sum">0</blockquote>
            </div>
        </div> -->
        <form class="layui-form layui-form-pane layui-inline" action="">
            <div class="layui-inline">
                <label class="layui-form-label">买方</label>
                <div class="layui-input-inline" style="width: 120px;">
                    <input type="text" name="buy_account_number" autocomplete="off" class="layui-input">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">卖方</label>
                <div class="layui-input-inline" style="width: 120px;">
                    <input type="text" name="sell_account_number" autocomplete="off" class="layui-input">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">法币</label>
                <div class="layui-input-inline" style="width:100px;">
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
                <div class="layui-input-inline" style="width:100px;">
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

    <script type="text/html" id="switchTpl">
        <input type="checkbox" name="is_recommend" value="@{{d.id}}" lay-skin="switch" lay-text="是|否" lay-filter="sexDemo" @{{ d.is_recommend == 1 ? 'checked' : '' }}>
    </script>

    <table id="demo" lay-filter="test"></table>

@endsection

@section('scripts')
    <script>

        layui.use(['table','form','laydate'], function(){
            var table = layui.table
                ,$ = layui.jquery
                ,form = layui.form
                ,laydate = layui.laydate;
            laydate.render({
                elem: '#start_time'
            });
            laydate.render({
                elem: '#end_time'
            });
            //第一个实例
            table.render({
                elem: '#demo'
                ,url: '{{url('admin/complete_list')}}' //数据接口
                ,page: true //开启分页
                ,toolbar: true
                ,totalRow: true
                ,id:'mobileSearch'
                ,height: 'full-60'
                ,cols: [[ //表头
                    {field: 'id', title: 'ID', width: 90, sort: true}
                    ,{field: 'currency_name', title: '交易币', width: 90}
                    ,{field: 'legal_name', title: '法币', width: 90}
                    ,{field: 'account_number', title: '买家', width: 120}
                    ,{field: 'from_number', title: '卖家', width: 120}
                    ,{field: 'price', title: '价格', width: 150, templet: '<div><div style="text-align: right;">@{{Number(d.price).toFixed(8)}}</div></div>'}
                    ,{field: 'number', title: '数量', width: 150, totalRow: true, templet: '<div><div style="text-align: right;">@{{Number(d.number).toFixed(8)}}</div></div>'}
                    ,{field: '', title: '价值', width: 160, templet: '<div><div style="text-align: right;">@{{Number(d.price * d.number).toFixed(8)}}</div></div>'}
                    ,{field: 'time', title: '创建时间', width: 180}
                ]], done: function(res){
                    $("#sum").text(res.extra_data);
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