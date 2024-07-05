define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'merchant.merchant/index' + location.search,
                    add_url: 'merchant.merchant/add',
                    edit_url: 'merchant.merchant/edit',
                    del_url: 'merchant.merchant/del',
                    multi_url: 'merchant.merchant/multi',
                    table: 'merchant',
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
                        //{field: 'agent.username', title: __('Agent_id'), operate:false},
                        {field: 'number', title: __('Number')},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        /*{field: 'add_money', title: __('Add_money'), operate:false},*/
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'block_money', title: __('Block_money'), operate:false},
                        {field: 'last_money_time', title: __('Last_money_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'logintime', title: __('Logintime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'loginip', title: __('Loginip'), operate:false},
                        /*{field: 'joinip', title: __('Joinip'), operate:false},*/
                        {field: 'jointime', title: __('Jointime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Status Normal'),"hidden":__('Status Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'sub_order_status', title: __('Sub_order_status'), operate:false,formatter: Controller.api.formatter.custom},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'acclist',
                                    text: '通道列表',
                                    title: __('通道列表'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    //icon: 'fa fa-folder-o',
                                    url: 'thirdacc.meracc/index',
                                    // hidden:function(row){
                                    //     if(row.level != 1){
                                    //         return true;
                                    //     }

                                    // },
                                },
                                {
                                    name: 'yes',
                                    text: '重置谷歌',
                                    title: __('重置谷歌'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'merchant.merchant/resetGoogleKey',
                                    confirm:'确定要重置谷歌密钥?',
                                    success: function (data,ret) {

                                    },
                                    error: function (data,ret) {

                                    }
                                },
                                {
                                    name: 'yes',
                                    text: '重置密钥',
                                    title: __('重置密钥'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'merchant.merchant/resetKey',
                                    confirm:'确定要重置商户密钥?',
                                    success: function (data,ret) {

                                    },
                                    error: function (data,ret) {

                                    }
                                },
                                {
                                    name: 'detail',
                                    text: '费率',
                                    title: __('费率'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    icon: 'fa fa-check',
                                    url: 'merchant.merchant/rate',
                                    success: function (data,ret) {

                                    },
                                    error: function (data,ret) {

                                    }
                                },
                            ]
                        }
                    ]
                ],
                search:false,
                //showSearch: false,
                //searchFormVisible: true,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);



        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
            
            require.config({
                paths : {
                    //"jquery" : ["/assets/css/dfpay/jquery"],
                    "qrcode" : ["/assets/css/dfpay/qrcode.min"],
                }
            });
            
            var google_code_url = Config.google_code_url;
            require(['qrcode'], function (Qrcode){

                var qrcode = new QRCode("googlediv", {
                    
                    render: "canvas",
                    text: google_code_url,
                    width: 120,
                    height: 120,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: 3
                });
            
            });
            
        },
        rate: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法

                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="merchant.merchant/change" data-id="' + row.id + '"><i class="fa ' + (row.sub_order_status == '0' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        },
    };
    return Controller;
});