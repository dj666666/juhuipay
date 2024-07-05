define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'daifu.dforder/index' + location.search,
                    /*add_url: 'order.order/add',
                    edit_url: 'order.order/edit',
                    del_url: 'order.order/del',
                    multi_url: 'order.order/multi',*/
                    table: 'order',
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
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'merchant.nickname', title: __('Mer_id')},
                        {field: 'user.nickname', title: __('User_id')},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false},
                        {field: 'bank_user', title: __('Bank_user')},
                        //{field: 'bank_type', title: __('Bank_type'), operate:false},
                        {field: 'bank_number', title: __('Bank_number')},
                        {field: 'pz_img', title: __('Pz_img'), operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'order_type', title: __('Order_type'), operate:false,visible:false, searchList: {"0":__('Order_type 0'),"1":__('Order_type 1')},custom:  {"0": 'info', "1": 'success'}, formatter: Table.api.formatter.flag},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')},custom:  {"1": 'success', "2": 'info', "3": 'danger', "4": 'warning'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), operate:false, searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'is_lock', title: __('Is_lock'), searchList: {"0":__('is_lock 0'),"1":__('is_lock 1')},custom:  {"0": 'primary', "1": 'danger'}, formatter: Table.api.formatter.flag},
                        {field: 'callback_count', title: __('Callback_count'), operate:false,visible:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ip_address', title: __('Ip_address'), operate:false},
                        {field: 'deal_ip_address', title: __('Deal_ip_address'), operate:false},
                        {field: 'deal_username', title: __('Deal_username'), operate:false},
                        {field: 'remark', title: __('Remark'), operate:"LIKE"},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'ajax',
                                    text: '冲正',
                                    title: __('冲正'),
                                    classname: 'btn btn-xs btn-primary btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/reversal',
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
                                    text: '驳回',
                                    title: __('驳回'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/abnormal',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定驳回该订单吗？',
                                    hidden:function(row){

                                        if(row.status == 1 || row.status == 3 || row.status == 4){
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
                                    text: '锁定',
                                    title: '锁定',
                                    classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/lockorder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定锁定订单吗？',
                                    hidden:function(row){
                                        if(row.status == 1 || row.status == 3 || row.status == 4 || row.is_lock == 1){
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
                                    text: '解锁',
                                    title: '解锁',
                                    classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.dforder/unlockorder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    confirm: '确定解锁吗？',
                                    hidden:function(row){
                                        if(row.status == 1 || row.status == 3 || row.status == 4 || row.is_lock == 0){
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
        }
    };
    return Controller;
});