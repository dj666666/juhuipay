define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'agent.agent/index' + location.search,
                    add_url: 'agent.agent/add',
                    edit_url: 'agent.agent/edit',
                    del_url: 'agent.agent/del',
                    multi_url: 'agent.agent/multi',
                    table: 'agent',
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
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname'), operate:false},
                        {field: 'number', title: __('Number')},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'block_money', title: __('Block_money'), operate:false},
                        
                        {field: 'sub_order_rate', title: __('Sub_order_rate'),formatter: Controller.api.formatter.custom},
                        
                        {field: 'last_money_time', title: __('Last_money_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'logintime', title: __('Logintime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'loginip', title: __('Loginip'), operate:false},
                        {field: 'joinip', title: __('Joinip'), operate:false},
                        {field: 'jointime', title: __('Jointime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Status Normal'),"hidden":__('Status Hidden')}, formatter: Table.api.formatter.status},


                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'yes',
                                    text: '重置谷歌',
                                    title: __('重置谷歌'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-check',
                                    url: 'agent.agent/resetGoogleKey',
                                    confirm:'确定要重置谷歌密钥?',
                                    success: function (data,ret) {

                                    },
                                    error: function (data,ret) {

                                    }
                                },
                            ]}
                    ]
                ]
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法

                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="agent.agent/change" data-id="' + row.id + '"><i class="fa ' + (row.sub_order_rate == '0' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});