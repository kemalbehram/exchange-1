@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <form class="layui-form" action="">
        <input type="hidden" name="id" value="{{$seller->id}}">
        <div class="layui-form-item">
            <label class="layui-form-label">商家名称</label>
            <div class="layui-input-block">
                <input type="text" name="name" autocomplete="off" class="layui-input layui-disabled" value="{{$seller->name ?? ''}}" disabled="disabled" readonly="readonly">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">余额类型</label>
            <div class="layui-input-inline">
                <select name="type" lay-verify="required" lay-filter="type">
                    <option value="" data-balance="">请选择</option>
                    <option value="seller_balance" data-balance="{{number_format($seller->seller_balance ?? 0, 8, '.', ',')}}">自由余额</option>
                    <option value="lock_seller_balance" data-balance="{{number_format($seller->lock_seller_balance ?? 0, 8, '.', ',')}}">锁定余额</option>
                </select>
            </div>
            <div class="layui-form-mid layui-word-aux" style="display: none;" id="balance_area">
                <span>余额:</span><span class="current_balance">0.00000000</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">调整金额</label>
            <div class="layui-input-block">
                <input type="text" name="change" autocomplete="off" lay-verify="required" placeholder="增加输入正数,减少输入负数" class="layui-input" value="">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">调整说明</label>
            <div class="layui-input-block">
                <textarea name="memo" placeholder="请输入内容" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="form">立即提交</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
            </div>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    layui.use(['element', 'form', 'layer'], function () {
        var element = layui.element
            ,form = layui.form
            ,layer = layui.layer
            ,$ = layui.$
        form.on('select(type)', function (data) {
            let balance = $('select[lay-filter=type] option:selected').data('balance')
            $('#balance_area .current_balance').text(balance)
            if (data.value != '') {
                $('#balance_area').show();
            } else {
                $('#balance_area').hide();
            }
        })
        form.on('submit(form)', function (data) {
            $.ajax({
                url: ''
                ,type: 'POST'
                ,data: data.field
                ,success: function (res) {
                    layer.msg(res.message, {
                        time: 2000
                        ,end: function () {
                            if (res.type == 'ok') {
                                var index = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
                                parent.layer.close(index); //再执行关闭 
                                parent.layui.table.reload('mobileSearch');       
                            }
                        }
                    });
                }
                ,error: function (res) {
                    layer.msg('网络错误');
                }
            });
            return false;
        });
    });
</script>
@endsection