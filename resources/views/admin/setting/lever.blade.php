<div class="layui-form-item">
    <label class="layui-form-label">交易手数倍数</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <button class="layui-btn layui-btn-sm" type="button" id="handle_multi_set">点击设置</button>
        </div>
        <div class="layui-form-mid layui-word-aux">设置交易的手数和倍数</div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">止盈止亏功能</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="user_set_stopprice" value="1" title="打开" @if (isset($setting['user_set_stopprice'])) {{$setting['user_set_stopprice'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="user_set_stopprice" value="0" title="关闭" @if (isset($setting['user_set_stopprice'])) {{$setting['user_set_stopprice'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
        <div class="layui-form-mid layui-word-aux">打开用户将可以针对交易设置止盈止亏价</div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">合约交易委托功能</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="open_lever_entrust" value="1" title="打开" @if (isset($setting['open_lever_entrust'])) {{$setting['open_lever_entrust'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="open_lever_entrust" value="0" title="关闭" @if (isset($setting['open_lever_entrust'])) {{$setting['open_lever_entrust'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
        <div class="layui-form-mid layui-word-aux">打开后前台可以进行合约交易委托,即限价交易</div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">爆仓风险率指标</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="text" name="lever_burst_hazard_rate" class="layui-input" value="{{$setting['lever_burst_hazard_rate'] ?? 0 }}" placeholder="合约交易风险率达到或低于设定值时爆仓">
        </div>
        <div class="layui-form-mid layui-word-aux">%</div>
        <div class="layui-form-mid layui-word-aux">用户的风险率达到或低于该值时触发爆仓</div>
    </div>
</div>
<div class="layui-form-item" hidden>
    <label class="layui-form-label">赠送虚拟账户</label>
    <div class="layui-input-block">
        <input type="text" name="give_virtual_account" autocomplete="off" class="layui-input"
            value="@if(isset($setting['give_virtual_account'])){{$setting['give_virtual_account']}}@endif">
    </div>
</div>
<div class="layui-form-item" hidden>
    <label class="layui-form-label">头寸</label>
    <div class="layui-input-block">
        <input type="text" name="lever_position" autocomplete="off" class="layui-input" value="@if(isset($setting['lever_position'])){{$setting['lever_position']}}@endif">
    </div>
</div>