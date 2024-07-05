define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order.order/index' + location.search,
                    add_url: 'order.order/add',
                    edit_url: 'order.order/edit',
                    del_url: 'order.order/del',
                    multi_url: 'order.order/multi',
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                //启用固定列
                fixedColumns: true,
                //固定右侧列数
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'agent.username', title: __('Agent.username'), operate:false,visible:false},
                        {field: 'merchant.username', title: __('Mer_id'),addclass: "selectpage",extend:'data-source="merchant.merchant/index" data-primary-key="username" data-field="username" data-multiple="false" data-select-only="false" data-order-by="id"'},
                        {field: 'user.username', title: __('User_id'),addclass: "selectpage",extend:'data-source="user.user/index" data-primary-key="username" data-field="username" data-multiple="false" data-select-only="false" data-order-by="id"'},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'xl_order_id', title: __('Xl_order_id')},
                        {field: 'qrcode_name', title: __('Qrcode_name'), operate: 'LIKE %...%'},
                        {field: 'pay_remark', title: __('Pay_remark'), operate:false, visible:false},
                        {field: 'zfb_code', title: __('Zfb_code'), visible:false},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false, visible:false },
                        {field: 'mer_fees', title: __('Mer_fees'), operate:false, visible:false},
                        
                        {field: 'pay_type', title: __('Pay_type'),visible:false,addclass: "selectpage",extend:'data-source="thirdacc.acc/index" data-primary-key="code" data-params=\'{"custom[status]":"1"}\'  data-multiple="false" data-select-only="true" data-order-by="id" '},

                        {field: 'third_hx_status', title: __('Third_hx_status'), visible:false, searchList: {"0":__('Third_hx_status 0'),"1":__('Third_hx_status 1'),"2":__('Third_hx_status 2'),"3":__('Third_hx_status 3'),"4":__('Third_hx_status 4')}, formatter: Table.api.formatter.normal},
                        
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')},custom:  {"1": 'success', "2": 'info', "3": 'danger'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'callback_count', title: __('Callback_count'), operate:false, visible:false },
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime,defaultValue:this.today(0)+' 00:00:00 - '+this.today(0)+ ' 23:59:59' },
                        {field: 'expire_time', title: __('Expire_time'),visible:false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'return_type', title: __('Return_type'), operate:false,visible:false},
                        {field: 'callback_time', title: __('Callback_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ip_address', title: __('Ip_address'), operate:false, visible:false },
                        {field: 'deal_ip_address', title: __('Deal_ip_address'), operate:false, visible:false },
                        {field: 'deal_username', title: __('Deal_username')},
                        {field: 'remark', title: __('Remark'), operate:false, visible:false },
                        {field: 'user_ip_address', title: __('User_ip_address')},
                        {field: 'device_type', title: __('Device_type')},
                        {field: 'request_num', title: __('Request_num'), operate:false},

                        {field: 'is_resetorder', title: __('Is_resetorder'),visible:false, searchList: {"0":__('Is_resetorder 0'),"1":__('Is_resetorder 1')}, formatter: Table.api.formatter.normal},
                        

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: '详情',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    //icon: 'fa fa-list',
                                    url: 'order.order/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: '查单',
                                    title: __('查单'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    /*icon: 'fa fa-exclamation',*/
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/xlqueryorder',
                                    success: function (data, ret) {
                                        //table.bootstrapTable('refresh');

                                        //Toastr.info(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        //Toastr.error(ret.msg);
                                        //table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: 'gmm取消订单',
                                    title: __('gmm取消订单'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    /*icon: 'fa fa-exclamation',*/
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/closeOrder',
                                    confirm: '确定取消该订单中的gmm订单吗？',
                                    hidden:function(row){
                                        if(row.pay_type != '1028' || row.is_gmm_close == 1){
                                            return true;
                                        }

                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');

                                        //Toastr.info(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        //Toastr.error(ret.msg);
                                        //table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: '成功',
                                    title: __('支付成功'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-click',
                                    //icon: 'fa fa-check',

                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定成功该订单吗？',
                                    hidden:function(row){
                                        if(row.status == 1 ||row.status == 3){
                                            return true;
                                        }

                                    },
                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'order.order/complete',
                                            data:{'ids':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //alert(ret.msg);
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //alert(ret.msg);
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: '失败',
                                    title: __('支付失败'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定驳回该订单吗？',
                                    hidden:function(row){

                                        if( row.status == 1 || row.status == 3){
                                            return true;
                                        }

                                    },
                                    click: function (data, ret) {

                                        // var that = this;
                                        // Layer.prompt({title: '请输入驳回原因', formType: 0}, function (value, index) {
                                        //     Backend.api.ajax({
                                        //         url: "order.index/abnormal",
                                        //         data: "id=" + ret.id + "&remark=" + value
                                        //     },function (data, ret) {
                                        //         table.bootstrapTable('refresh');
                                        //         Layer.close(index);
                                        //     });

                                        // });
                                        Fast.api.ajax({
                                            url:'order.order/abnormal',
                                            data:{'id':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //alert(ret.msg);
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //alert(ret.msg);
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });

                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                {
                                    name: 'ajax',
                                    text: '补单',
                                    title: __('补单'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //url: 'order.order/resetOrder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    //confirm: '确定补单吗？',
                                    hidden:function(row){

                                        if(row.status != 3 ){
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {

                                        var that = this;
                                        Layer.prompt({title: '补单原因', formType: 0}, function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/resetOrder",
                                                data: "id=" + ret.id + "&remark=" + value
                                            },function (data, ret) {
                                                table.bootstrapTable('refresh');
                                                Layer.close(index);
                                            });

                                        });

                                        /*Fast.api.ajax({
                                            url:'order.order/abnormal',
                                            data:{'id':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });*/

                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                {
                                    name: 'ajax',
                                    text: '补发通知',
                                    title: __('补发通知'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    /*icon: 'fa fa-exclamation',*/
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/reissueNotice',
                                    confirm: '确定补发通知吗？',
                                    hidden:function(row){
                                        if(row.status == 2 || row.status == 3 || row.is_callback == 1 ){
                                            return true;
                                        }
                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');

                                        //Toastr.info(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        //Toastr.error(ret.msg);
                                        table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },
                            ],
                        }
                    ]
                ],
                search:false,//快速搜索
                showSearch: true,//显示搜索
                searchFormVisible: false,//通用搜索
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#allmoney").text(data.extend.allmoney);
                $("#allorder").text(data.extend.allorder);
                $("#allfees").text(data.extend.allfees);
                $("#success_rate").text(data.extend.success_rate);
                $("#today_success_rate").text(data.extend.today_success_rate);

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
            },
            formatter: {
                notify: function(value, row, index){
                    if (value == 1){
                        return '<span class="label label-success">'+row.is_callback_text+'</span>';
                    }
                    if (value == 2){
                        return '<span class="label label-danger">'+row.is_callback_text+'</span>';
                    }
                    if (value == 0){
                        return '<span class="label label-primary">'+row.is_callback_text+'</span>';
                    }
                }
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