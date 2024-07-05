define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'systemlog.systemlog/index' + location.search,
                    /*add_url: 'systemlog.systemlog/add',
                    edit_url: 'systemlog.systemlog/edit',*/
                    del_url: 'systemlog.systemlog/del',
                    multi_url: 'systemlog.systemlog/multi',
                    table: 'system_log',
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
                        {field: 'id', title: __('Id')},
                        {field: 'username', title: __('Username')},
                        {field: 'modulename', title: __('Modulename')},
                        {field: 'path', title: __('Path'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'content', title: __('Content'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'ip', title: __('Ip'),events: Table.api.events.ip,formatter: Table.api.formatter.search},
                        {field: 'useragent', title: __('Useragent'), operate: false, formatter: Controller.api.formatter.browser},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'detail',
                                text: __('Detail'),
                                icon: 'fa fa-list',
                                classname: 'btn btn-info btn-xs btn-detail btn-dialog',
                                url: 'systemlog.systemlog/detail'
                            }],
                        }
                    ]
                ],
                search: false,
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
            formatter: {
                browser: function (value, row, index) {
                    return '<a class="btn btn-xs btn-browser">' + row.useragent.split(" ")[0] + '</a>';
                },
                content: function (value, row, index) {
                    return  + row.content.split("code")[0];
                },
            },
        }
    };
    return Controller;
});