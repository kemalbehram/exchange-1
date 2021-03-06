layui.config({
    base: '../../lib/' //指定 lib 路径
    , version: '1.0.0-beta'
}).extend({
    echarts: 'echarts/echarts',
    echartsTheme: 'echarts/echartsTheme',
    winui: 'winui/winui'
}).define(['winui', 'echarts'], function (exports) {
    winui.renderColor();

    var $ = layui.jquery,
        echartDom = [$('#bar')[0], $('#line')[0], $('#area')[0], $('#pie')[0]],
        echartInstance = [];

    var echartsOption = [{
        title: {
            text: 'ECharts入门示例',
            textStyle: {
                fontSize: 14
            }
        },
        tooltip: {},
        legend: {
            data: ['销量']
        },
        xAxis: {
            data: ["衬衫", "羊毛衫", "雪纺衫", "裤子", "高跟鞋", "袜子"]
        },
        yAxis: {},
        series: [{
            name: '销量',
            type: 'bar',
            data: [5, 20, 36, 10, 10, 20]
        }]
    }, {
        title: {
            text: 'ECharts入门示例',
            x: 'center',
            textStyle: {
                fontSize: 14
            }
        },
        tooltip: {},
        legend: {
            orient: 'vertical',
            left: 'left',
            data: ['销量']
        },
        xAxis: {
            data: ["衬衫", "羊毛衫", "雪纺衫", "裤子", "高跟鞋", "袜子"]
        },
        yAxis: {},
        series: [{
            name: '销量',
            type: 'line',
            data: [5, 20, 36, 10, 10, 20]
        }]
    }, {
        title: {
            text: '今日流量趋势',
            x: 'center',
            textStyle: {
                fontSize: 14
            }
        },
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: ['', '']
        },
        xAxis: [{
            type: 'category',
            boundaryGap: false,
            data: ['06:00', '06:30', '07:00', '07:30', '08:00', '08:30', '09:00', '09:30', '10:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00', '23:30']
        }],
        yAxis: [{
            type: 'value'
        }],
        series: [{
            name: 'PV',
            type: 'line',
            smooth: true,
            itemStyle: { normal: { areaStyle: { type: 'default' } } },
            data: [111, 222, 333, 444, 555, 666, 3333, 33333, 55555, 66666, 33333, 3333, 6666, 11888, 26666, 38888, 56666, 42222, 39999, 28888, 17777, 9666, 6555, 5555, 3333, 2222, 3111, 6999, 5888, 2777, 1666, 999, 888, 777]
        }, {
            name: 'UV',
            type: 'line',
            smooth: true,
            itemStyle: { normal: { areaStyle: { type: 'default' } } },
            data: [11, 22, 33, 44, 55, 66, 333, 3333, 5555, 12666, 3333, 333, 666, 1188, 2666, 3888, 6666, 4222, 3999, 2888, 1777, 966, 655, 555, 333, 222, 311, 699, 588, 277, 166, 99, 88, 77]
        }]
    }, {
        title: {
            text: '用户访问来源',
            x: 'center'
        },
        tooltip: {
            trigger: 'item',
            formatter: "{a} <br/>{b} : {c} ({d}%)"
        },
        legend: {
            orient: 'vertical',
            left: 'left',
            data: ['直接访问', '邮件营销', '联盟广告', '视频广告', '搜索引擎']
        },
        series: [
            {
                name: '访问来源',
                type: 'pie',
                radius: '55%',
                center: ['50%', '60%'],
                data: [
                    { value: 335, name: '直接访问' },
                    { value: 310, name: '邮件营销' },
                    { value: 234, name: '联盟广告' },
                    { value: 135, name: '视频广告' },
                    { value: 1548, name: '搜索引擎' }
                ],
                itemStyle: {
                    emphasis: {
                        shadowBlur: 10,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }
        ]
    }];

    loadECharts(0);

    //监听Winui的左右Tab切换
    winui.tab.on('tabchange(winuitab)', function (data) {
        loadECharts(data.index);
    });


    function loadECharts(i) {
        echartInstance[i] = echarts.init(echartDom[i], layui.echartsTheme);
        echartInstance[i].clear();
        echartInstance[i].resize();
        echartInstance[i].setOption(echartsOption[i]);
        window.onresize = echartInstance[i].resize;
    }

    exports('echartsdemo', {});
});
