define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user.userhxacc/index' + location.search,
                    add_url: 'user.userhxacc/add',
                    edit_url: 'user.userhxacc/edit',
                    del_url: 'user.userhxacc/del',
                    multi_url: 'user.userhxacc/multi',
                    table: 'userhxacc',
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
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'user.username', title: __('User.username')},
                        {field: 'name', title: __('Name'), operate: 'LIKE %...%'},
                        {field: 'hxacc.name', title: __('Hx_code'), operate:false},
                        {field: 'user.money', title: __('User.money'), operate:false},

                        {field: 'type', title: __('Type'), operate:false,visible:false, searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                        {field: 'today_amount', title: __('Today_amount'), operate:false},
                        {field: 'all_amount', title: __('All_amount'), operate:false},

                        {field: 'status', title: __('Status'),formatter: Controller.api.formatter.custom},
                        {field: 'create_time', title: __('Create_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法

                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="user.userhxacc/change" data-id="' + row.id + '"><i class="fa ' + (row.status == '0' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});