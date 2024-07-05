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
                        {field: 'number', title: __('Number')},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'block_money', title: __('Block_money'), operate:false},
                        {field: 'last_money_time', title: __('Last_money_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'logintime', title: __('Logintime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'loginip', title: __('Loginip'), operate:false},
                        {field: 'joinip', title: __('Joinip'), operate:false},
                        {field: 'jointime', title: __('Jointime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Status Normal'),"hidden":__('Status Hidden')}, formatter: Table.api.formatter.status},


                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});