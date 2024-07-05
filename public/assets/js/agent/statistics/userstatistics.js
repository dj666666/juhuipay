define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'statistics.userstatistics/index' + location.search,
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
                        {field: 'nickname', title: __('码商名称'),operate:false},
                        {field: 'time', title: __('时间'),visible: false,operate:false},
                        {field: 'createtime', title: __('时间'),visible: false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime,defaultValue:this.today(0)+' 00:00:00 - '+this.today(0)+ ' 23:59:59' },
                        {field: 'money', title: __('余额'),visible: false,operate:false},
                        {field: 'success_rate', title: __('成功率'),operate:false},
                        {field: 'all_money', title: __('总金额'),operate:false},
                        {field: 'all_order', title: __('总订单数量'),operate:false},
                        {field: 'all_success_money', title: __('成功金额'),operate:false},
                        {field: 'all_success_order', title: __('成功订单'),operate:false},
                        {field: 'all_fail_order', title: __('失败订单'),operate:false},
                        {field: 'all_fail_money', title: __('失败金额'),operate:false},
                        //操作栏,默认有编辑、删除或排序按钮,可自定义配置buttons来扩展按钮
                        {
                            field: 'operate',
                            width: "150px",
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'acclist',
                                    text: '通道统计',
                                    title: __('通道统计'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    //icon: 'fa fa-folder-o',
                                    url: 'statistics.userstatistics/useraccstatistics',
                                    // hidden:function(row){
                                    //     if(row.level != 1){
                                    //         return true;
                                    //     }

                                    // },
                                },
                                {
                                    name: 'detail',
                                    text: '数据详情',
                                    title: __('数据详情'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    //icon: 'fa fa-list',
                                    url: 'statistics.userstatistics/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                

                            ],
                            formatter: Table.api.formatter.operate
                        },
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
                $("#allmoney").text(data.extend.allmoney);
                $("#allsuccessmoney").text(data.extend.allsuccessmoney);
                $("#allfailemoney").text(data.extend.allfailemoney);
                $("#allorder").text(data.extend.allorder);
                $("#allsuccessorder").text(data.extend.allsuccessorder);
                $("#allfaileorder").text(data.extend.allfaileorder);
                $("#allfees").text(data.extend.allfees);
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        useraccstatistics: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'statistics.userstatistics/useraccstatistics/ids/' + Config.user_id + location.search,
                    
                    table: 'user_acc',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                escape: false,
                columns: [
                    [
                        {checkbox: true},
                        //{field: 'id', title: __('Id'), operate:false},
                        {field: 'acc_code', title: __('Acc_code')},
                        {field: 'acc.name', title: __('Acc.name'), operate:false},
                        {field: 'today_all_order', title: __('Today_all_order'), operate:false},
                        {field: 'today_suc_order', title: __('Today_suc_order'), operate:false},
                        {field: 'today_rate', title: __('Today_rate'), operate:false},
                        {field: 'today_suc_money', title: __('Today_suc_money'), operate:false},
                        {field: 'yesterday_suc_money', title: __('Yesterday_suc_money'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        
                    ]
                ],
                search:false,
                showSearch: false,
                searchFormVisible: false,
                visible: false,
                showExport: false,
                showToggle: false,
                showColumns: false,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
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