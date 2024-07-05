define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'daifu.dfacc/index' + location.search,
                    add_url: 'daifu.dfacc/add',
                    edit_url: 'daifu.dfacc/edit',
                    del_url: 'daifu.dfacc/del',
                    multi_url: 'daifu.dfacc/multi',
                    table: 'df_acc',
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
                        {field: 'name', title: __('Name')},
                        {field: 'merchant_no', title: __('Merchant_no')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'), "1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'code', title: __('Code')},
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