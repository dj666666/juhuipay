define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user.user/index' + location.search,
                    add_url: 'user.user/add',
                    edit_url: 'user.user/edit',
                    // del_url: 'user.user/del',
                    // multi_url: 'user.user/multi',
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
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname'), operate:false},
                        {field: 'number', title: __('Number'), operate:false},
                        //{field: 'rate', title: __('Rate'), operate:false},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'parent_name', title: __('Parent_name'), operate:false},
                        {field: 'level', title: __('Level'), operate:false},
                        {field: 'status', title: __('Status'), operate:false, formatter: Table.api.formatter.status},
                        {field: 'is_receive', title: __('Is_receive'), operate:false,formatter: Controller.api.formatter.custom},
                        {field: 'logintime', title: __('Logintime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        //{field: 'loginip', title: __('Loginip'), operate:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate/*, formatter: function (value, row, index) {
                                if(row.level != 1){
                                    return '';
                                }
                                return Table.api.formatter.operate.call(this, value, row, index);
                            }*/,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: '费率配置',
                                    title: '费率配置',
                                    classname: 'btn btn-xs btn-warning  btn-dialog',
                                    //icon: 'fa fa-folder-o',
                                    url: 'thirdacc.useracc/index',
                                    /*hidden:function(row){
                                        if(row.level != 1){
                                            return true;
                                        }

                                    },*/
                                },
                                {
                                    name: 'detail',
                                    text: '余额操作',
                                    title: __('余额操作'),
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    //icon: 'fa fa-folder-o',
                                    url: 'moneylog.usermoneylog/addmoney',
                                    // hidden:function(row){
                                    //     if(row.level != 1){
                                    //         return true;
                                    //     }

                                    // },
                                },
                            ]
                        }
                    ]
                ],
                search:false,
                showSearch: true,
                searchFormVisible: false,
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
                    return '<a class="btn-change text-success" data-url="user.user/change" data-id="' + row.id + '"><i class="fa ' + (row.is_receive == '2' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});