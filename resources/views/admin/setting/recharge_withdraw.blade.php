<div class="layui-form-item">
    <label class="layui-form-label">是否开启充提币功能</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="is_open_CTbi" value="1" title="打开" @if (isset($setting['is_open_CTbi'])) {{$setting['is_open_CTbi'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="is_open_CTbi" value="0" title="关闭" @if (isset($setting['is_open_CTbi'])) {{$setting['is_open_CTbi'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">提币使用链上接口</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="use_chain_api" value="1" title="打开" @if (isset($setting['use_chain_api'])) {{$setting['use_chain_api'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="use_chain_api" value="0" title="关闭" @if (isset($setting['use_chain_api'])) {{$setting['use_chain_api'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">充币账户</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <select name="recharge_to_balance" {{$setting['recharge_to_balance'] > 0 ? 'disabled' : ''}}>
                <option value="1" {{$setting['recharge_to_balance'] == 1 ? 'selected' : ''}}>法币资产</option>
                <option value="2" {{$setting['recharge_to_balance'] == 2 ? 'selected' : ''}}>币币资产</option>
                <option value="3" {{$setting['recharge_to_balance'] == 3 ? 'selected' : ''}}>合约资产</option>
            </select>
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">提币账户</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <select name="withdraw_from_balance" {{$setting['withdraw_from_balance'] > 0 ? 'disabled' : ''}}>
                <option value="1" {{$setting['withdraw_from_balance'] == 1 ? 'selected' : ''}}>法币资产</option>
                <option value="2" {{$setting['withdraw_from_balance'] == 2 ? 'selected' : ''}}>币币资产</option>
                <option value="3" {{$setting['withdraw_from_balance'] == 3 ? 'selected' : ''}}>合约资产</option>
            </select>
        </div>
    </div>
</div>

