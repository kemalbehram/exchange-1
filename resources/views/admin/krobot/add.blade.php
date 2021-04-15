@extends('admin._layoutNew')

@section('page-head')

@endsection

@section('page-content')
    <form class="layui-form" action="">
        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">机器人账号</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_user" lay-verify="required" autocomplete="off" placeholder="机器人账号" class="layui-input" value="{{$result->kr_user ?? ''}}">
                </div>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">机器人密码</label>
                <div class="layui-input-inline">
                    <input type="password" name="kr_user_password" lay-verify="required" autocomplete="off" placeholder="机器人登陆密码" class="layui-input" value="{{$result->kr_user_password ?? ''}}">
                </div>
                <label class="layui-form-label">交易密码</label>
                <div class="layui-input-inline">
                    <input type="password" name="kr_user_ex_password" lay-verify="required" autocomplete="off" placeholder="机器人交易密码" class="layui-input" value="{{$result->kr_user_ex_password ?? ''}}">
                </div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">交易币</label>
                <div class="layui-input-inline">
                    <select name="kr_stock" lay-filter="" lay-search>
                        <option value=""></option>
                        @if(!empty($currencies))
                        @foreach($currencies as $currency)
                        <option value="{{$currency->id}}" @if($currency->id == ($result->kr_stock ?? 0)) selected @endif>{{$currency->name}}</option>
                        @endforeach
                        @endif
                    </select>
                </div>
                <label class="layui-form-label">法币</label>
                <div class="layui-input-inline">
                    <select name="kr_money" lay-filter="">
                        <option value=""></option>
                        @if(!empty($currencies))
                        @foreach($legals as $legal)
                            <option value="{{$legal->id}}" @if($legal->id == ($result->kr_money ?? 0)) selected @endif>{{$legal->name}}</option>
                        @endforeach
                        @endif
                    </select>
                </div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">价格精度</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_price_decimal" lay-verify="required" autocomplete="off" placeholder="价格精度" class="layui-input" value="{{$result->kr_price_decimal ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
                <label class="layui-form-label">数量精度</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_number_decimal" lay-verify="required" autocomplete="off" placeholder="数量精度" class="layui-input" value="{{$result->kr_number_decimal ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">价格下限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_min_price" lay-verify="required" autocomplete="off" placeholder="价格浮动下限" class="layui-input" value="{{$result->kr_min_price ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
                <label class="layui-form-label">价格上限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_max_price" lay-verify="required" autocomplete="off" placeholder="价格浮动上限" class="layui-input" value="{{$result->kr_max_price ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>


        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">数量下限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_min_number" lay-verify="required" autocomplete="off" placeholder="数量随机下限" class="layui-input" value="{{$result->kr_min_number ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
                <label class="layui-form-label">数量上限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_max_number" lay-verify="required" autocomplete="off" placeholder="数量随机上限" class="layui-input" value="{{$result->kr_max_number ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>


        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">行情计划</label>
                <div class="layui-input-inline">
                    <input type="checkbox" name="kr_cron_status" value="{{ $result->kr_cron_status ?? '' }}" lay-skin="switch" lay-text="开启|关闭"
                    @if(!empty($result))
                    {{$result->kr_cron_status == 1 ? 'checked' : ''}}
                    @endif
                     />
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">开始时间</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_cron_start" id="kr_cron_start" autocomplete="off" placeholder="开始时间" class="layui-input" value="{{$result->kr_cron_start ?? ''}}" readonly style="cursor: pointer;">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
                <label class="layui-form-label">结束时间</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_cron_end" id="kr_cron_end" autocomplete="off" placeholder="结束时间" class="layui-input" value="{{$result->kr_cron_end ?? ''}}" readonly style="cursor: pointer;">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">目标价格</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_cron_end_price" id="kr_cron_end_price" autocomplete="off" placeholder="目标价格" class="layui-input" value="{{$result->kr_cron_end_price ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-inline">
                <label class="layui-form-label">计划结束后价格下限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_cron_end_min_price" autocomplete="off" placeholder="计划结束后价格下限" class="layui-input" value="{{$result->kr_cron_end_min_price ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
                <label class="layui-form-label">计划结束后价格上限</label>
                <div class="layui-input-inline">
                    <input type="text" name="kr_cron_end_max_price" autocomplete="off" placeholder="计划结束后价格上限" class="layui-input" value="{{$result->kr_cron_end_max_price ?? ''}}">
                </div>
                <div class="layui-form-mid layui-word-aux"></div>
            </div>
        </div>
        
        <input type="hidden" name="kr_id" value="{{$result->kr_id ?? 0}}">
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit="" lay-filter="demo1">立即提交</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
            </div>
        </div>
    </form>

@endsection

@section('scripts')
    <script>


        layui.use(['form','laydate'],function () {
            var form = layui.form
                ,$ = layui.jquery
                ,laydate = layui.laydate
                ,index = parent.layer.getFrameIndex(window.name);

            laydate.render({
                elem: '#kr_cron_start', //指定元素
                type: 'datetime',
                isInitValue : true,
                value: '{{$result->kr_cron_start ?? ''}}'
            });

            laydate.render({
                elem: '#kr_cron_end', //指定元素
                type: 'datetime',
                isInitValue : true,
                value: '{{$result->kr_cron_end ?? ''}}'
            });

            //监听提交
            form.on('submit(demo1)', function(data){
                var data = data.field;
                console.log(data);
                $.ajax({
                    url:'{{url('admin/krobot/add')}}'
                    ,type:'post'
                    ,dataType:'json'
                    ,data : data
                    ,success:function(res){
                        if(res.type=='error') {
                            layer.msg(res.message);
                        }else{
                            parent.layer.close(index);
                            parent.window.location.reload();
                        }
                    }
                });
                return false;
            });
        });
    </script>

@endsection