define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'daifu.dforder/index' + location.search,
                    //add_url: 'order.order/add',
                    edit_url: 'daifu.dforder/edit',
                    del_url: 'daifu.dforder/del',
                    multi_url: 'daifu.dforder/multi',
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
                        {field: 'agent.username', title: __('Agent_id')},
                        {field: 'merchant.username', title: __('Mer_id')},
                        {field: 'user.username', title: __('User_id')},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'fees', title: __('Fees'),visible:false, operate:false},
                        {field: 'bank_user', title: __('Bank_user')},
                        {field: 'bank_type', title: __('Bank_type'), operate:false},
                        {field: 'bank_number', title: __('Bank_number')},
                        {field: 'order_type', title: __('Order_type'), searchList: {"0":__('Order_type 0'),"1":__('Order_type 1')},custom:  {"0": 'info', "1": 'success'}, formatter: Table.api.formatter.flag},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')},custom:  {"1": 'success', "2": 'info', "3": 'danger', "4": 'warning'}, formatter: Table.api.formatter.flag},
                        
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        
                        {field: 'is_third_df', title: __('Is_third_df'), searchList: {"0":__('Is_third_df 0'),"1":__('Is_third_df 1'),"2":__('Is_third_df 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        
                        {field: 'error_msg', title: __('Error_msg')},

                        
                        {field: 'callback_count', title: __('Callback_count'), operate:false,visible:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ip_address', title: __('Ip_address'), operate:false},
                        {field: 'deal_ip_address', title: __('Deal_ip_address'), operate:false},
                        {field: 'deal_username', title: __('Deal_username')},
                        {field: 'remark', title: __('Remark')},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                /*{
                                    name: 'detail',
                                    text: '详情',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    //icon: 'fa fa-list',
                                    url: 'order.order/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },*/
                                {
                                    name: 'ajax',
                                    text: '转入',
                                    title: __('转入'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/toThirdDf',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定该订单转入三方代付吗？',
                                    hidden:function(row){

                                        if(row.is_third_df == 0){
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
                                        //table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: '冲正',
                                    title: __('冲正'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/reversalOrder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定冲正该订单吗？',
                                    hidden:function(row){

                                        if(row.status != 1){
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
                                {
                                    name: 'ajax',
                                    text: '回调',
                                    title: __('回调'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-warning',

                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定回调该订单吗？',
                                    hidden:function(row){
                                        if(row.is_callback == 1 ||  row.status == 2){
                                            return true;
                                        }


                                    },
                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'daifu.dforder/sendNotify',
                                            data:{'ids':ret.id}
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
                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: '驳回',
                                    title: __('驳回'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-click',
                                    //icon: 'fa fa-exclamation-circle',

                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定驳回该订单吗？',
                                    hidden:function(row){
                                        if(row['status'] != 2){
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'daifu.dforder/abnormal',
                                            data:{'ids':ret.id}
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
                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
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
                //$("#success_rate").text(data.extend.success_rate);

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

    };
    return Controller;
});