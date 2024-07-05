define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Table, Form, Echarts) {

    var Controller = {
        index: function () {

            // 基于准备好的dom，初始化echarts实例
            var myChart1 = Echarts.init($('#echarts1')[0], 'walden');

            // 指定图表的配置项和数据
            var option1 = {
                title: {
                    text: '',
                    subtext: ''
                },
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: ['金额', '手续费']
                },
                toolbox: {
                    show: true,
                    feature: {
                        dataView: {show: true, readOnly: false},
                        magicType: {show: true, type: ['line', 'bar']},
                        restore: {show: true},
                        saveAsImage: {show: true}
                    }
                },
                calculable: true,
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.category
                },
                yAxis: {},
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [
                    {
                        name: "金额",
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            normal: {}
                        },
                        lineStyle: {
                            normal: {
                                width: 1.5
                            }
                        },
                        data: Config.incomeData
                    },
                    {
                        name: "手续费",
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            normal: {}
                        },
                        lineStyle: {
                            normal: {
                                width: 1.5
                            }
                        },
                        data: Config.payData
                    }/*,
                    {
                        name: "提现金额",
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            normal: {}
                        },
                        lineStyle: {
                            normal: {
                                width: 1.5
                            }
                        },
                        data: Config.balanceData
                    },*/
                ]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart1.setOption(option1);

            $(window).resize(function () {
                myChart1.resize();
            });

            $(".datetimerange").data("callback", function (start, end) {
                var date = start.format(this.locale.format) + " - " + end.format(this.locale.format);
                $(this.element).val(date);
                refresh_echart($(this.element).data("type"), date);
            });

            Form.api.bindevent($("#form1"));

            var si = {};
            var refresh_echart = function (type, date) {
                si[type] && clearTimeout(si[type]);
                si[type] = setTimeout(function () {
                    Fast.api.ajax({
                        url: 'statistics.record/index',
                        data: {date: date, type: type},
                        loading: false
                    }, function (data) {
                        if (type == 'order') {

                            option1.xAxis.data = data.category;
                            option1.series[0].data = data.incomeData;
                            option1.series[1].data = data.payData;
                            //option1.series[2].data = data.balanceData;
                            myChart1.clear();
                            myChart1.setOption(option1, true);
                            $("#all_income").text(data.extend.allIncome);
                            /*$("#all_pay").text(data.extend.allPay);
                            $("#all_balance").text(data.extend.allBalance);*/
                        }
                        return false;
                    });
                }, 50);
            };

            //点击按钮
            $(document).on("click", ".btn-filter", function () {
                var label = $(this).text();
                var obj = $(this).closest("form").find(".datetimerange").data("daterangepicker");
                var dates = obj.ranges[label];
                obj.startDate = dates[0];
                obj.endDate = dates[1];

                obj.clickApply();
            });

            //点击刷新
            $(document).on("click", ".btn-refresh", function () {
                if ($(this).data("type")) {
                    refresh_echart($(this).data("type"), "");
                } else {
                    var input = $(this).closest("form").find(".datetimerange");
                    var type = $(input).data("type");
                    var date = $(input).data("date");
                    refresh_echart(type, date);
                }
            });
            //每隔一分钟定时刷新图表
            setInterval(function () {
                $(".btn-refresh").trigger("click");
            }, 60000);

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});