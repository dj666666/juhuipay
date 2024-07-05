define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'systemlog.queuejobs/index' + location.search,
                    /*add_url: 'systemlog.queuejobs/add',
                    edit_url: 'systemlog.queuejobs/edit',*/
                    del_url: 'systemlog.queuejobs/del',
                    multi_url: 'systemlog.queuejobs/multi',
                    table: 'queue_jobs',
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
                        {field: 'queue', title: __('Queue')},
                        {field: 'payload', title: __('Payload'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'attempts', title: __('Attempts')},
                        {field: 'reserved_time', title: __('Reserved_at'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'available_time', title: __('Available_at'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'create_time', title: __('Created_at'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ],
                search:false,//快速搜索
                showSearch: true,//显示搜索
                searchFormVisible: false,//通用搜索
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
            }
        }
    };
    return Controller;
});