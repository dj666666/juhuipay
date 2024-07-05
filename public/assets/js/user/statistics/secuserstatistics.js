define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'statistics.secuserstatistics/index' + location.search,
                    /*add_url: 'statistics.userstatistics/add',
                    edit_url: 'statistics.userstatistics/edit',
                    del_url: 'statistics.userstatistics/del',
                    multi_url: 'statistics.userstatistics/multi',*/
                    table: 'user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),operate:false},
                        {field: 'nickname', title: __('名称'),operate:false},
                        {field: 'time', title: __('时间'),visible: false,operate:false},
                        {field: 'createtime', title: __('时间'),visible: false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime,defaultValue:this.today(0)+' 00:00:00 - '+this.today(0)+ ' 23:59:59' },
                        {field: 'money', title: __('余额'),visible: false,operate:false},
                        {field: 'success_rate', title: __('成功率'),operate:false},
                        {field: 'all_money', title: __('总金额'),operate:false},
                        {field: 'all_success_money', title: __('成功金额'),operate:false},
                        {field: 'all_order', title: __('总订单数量'),operate:false},
                        
                        {field: 'all_success_order', title: __('成功订单'),operate:false},
                        //{field: 'all_fail_order', title: __('失败订单'),operate:false},
                        //{field: 'all_fail_money', title: __('失败金额'),operate:false},
                    ]
                ],
                earch:false,
                search:false,
                showSearch: false,
                searchFormVisible: true,
                showToggle: false,
                showColumns: false,
                showExport: false,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#allsuccessmoney").text(data.extend.allsuccessmoney);
                $("#allsuccessorder").text(data.extend.allsuccessorder);
            });
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
        },
        today:function(AddDayCount){
            var dd = new Date();
            dd.setDate(dd.getDate() + AddDayCount);
            var y = dd.getFullYear();
            var m = dd.getMonth()+1;
            var d = dd.getDate();

            //判断月
            if(m <10){
                m = "0" + m;
            }else{
                m = m;
            }

            //判断日
            if(d<10){ //如果天数小于10
                d = "0" + d;
            }else{
                d = d;
            }
            return y+"-"+m+"-"+d;
        }
    };
    return Controller;
});