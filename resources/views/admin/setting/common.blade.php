<div class="layui-form-item">
    <label class="layui-form-label">版本号</label>
    <div class="layui-input-inline">
        <input type="text" name="version" autocomplete="off" class="layui-input"
            value="@if(isset($setting['version'])){{$setting['version'] ?? ''}}@endif">
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">USDT汇率</label>
    <div class="layui-input-inline">
        <input type="text" name="USDTRate" autocomplete="off" class="layui-input"
            value="@if(isset($setting['USDTRate'])){{$setting['USDTRate']}}@endif">
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">用户id超始值</label>
    <div class="layui-input-inline">
        <input type="text" name="uid_begin_value" autocomplete="off" class="layui-input"
            value="@if(isset($setting['uid_begin_value'])){{$setting['uid_begin_value']}}@endif">
    </div>
    <div class="layui-form-mid layui-word-aux">用户id的起始值,仅用于显示</div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">邀请码必填</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="invite_code_must" value="1" title="是" @if (isset($setting['invite_code_must'])) {{$setting['invite_code_must'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="invite_code_must" value="0" title="否" @if (isset($setting['invite_code_must'])) {{$setting['invite_code_must'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">注册后跳转</label>
    <div class="layui-input-block">
        <div class="layui-input-inline" style="width: 400px">
            <input type="text" class="layui-input" name="registered_jump" value="{{$setting['registered_jump'] ?? ''}}" placeholder="用于注册后跳转到指定地址,留空不跳转" >
        </div>
    </div>
</div>
<div class="layui-form-item" style="display: none;">
    <label class="layui-form-label">总账号自动加密私钥</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="auto_encrypt_private" value="1" title="打开" @if (isset($setting['auto_encrypt_private'])) {{$setting['auto_encrypt_private'] == 1 ? 'checked' : ''}} @else checked @endif >
            <input type="radio" name="auto_encrypt_private" value="0" title="关闭" @if (isset($setting['auto_encrypt_private'])) {{$setting['auto_encrypt_private'] == 0 ? 'checked' : ''}} @endif >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">是否开启登录提示</label>
    <div class="layui-input-block">
        <div class="layui-input-inline">
            <input type="radio" name="is_open_login_prompt" value="1" title="打开" @if (isset($setting['is_open_login_prompt'])) {{$setting['is_open_login_prompt'] == 1 ? 'checked' : ''}} @endif >
            <input type="radio" name="is_open_login_prompt" value="0" title="关闭" @if (isset($setting['is_open_login_prompt'])) {{$setting['is_open_login_prompt'] == 0 ? 'checked' : ''}} @else checked @endif >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">登录提示语</label>
    <div class="layui-input-block">
        <div class="layui-input-inline" style="width: 400px">
            <input type="text" class="layui-input" name="login_prompt" value="{{$setting['login_prompt'] ?? ''}}" placeholder="用于登录后弹窗提示" >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">联系我们</label>
    <div class="layui-input-block">
        <div class="layui-input-inline" style="width: 400px">
            <input type="text" class="layui-input" name="contact_us" value="{{$setting['contact_us'] ?? ''}}" placeholder="联系我们" >
        </div>
    </div>
</div>
<div class="layui-form-item">
    <label class="layui-form-label">上传白皮书</label>
    <div class="layui-input-block">
        <div class="layui-input-inline" style="width: 400px">
            <input type="text" class="layui-input" name="white_paper" id="white_paper" value="{{$setting['white_paper'] ?? ''}}">
            <br>
            <button class="layui-btn" type="button" id="upload_test">选择PDF</button>
        </div>
    </div>
</div>
@include('admin.setting.recharge_withdraw')

