define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'moneylog.moneylog/index' + location.search,
                    add_url: 'moneylog.moneylog/add',
                    edit_url: 'moneylog.moneylog/edit',
                    del_url: 'moneylog.moneylog/del',
                    multi_url: 'moneylog.moneylog/multi',
                    table: 'money_log',
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
                        {field: 'merchant.username', title: __('Merchant.username')},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'type', title: __('Type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'fees', title: __('Fees'), operate:false},
                        {field: 'before_amount', title: __('Before_amount'), operate:false},
                        {field: 'after_amount', title: __('After_amount'), operate:false},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'remark', title: __('Remark'), operate:"LIKE"},
                        {field: 'ip_address', title: __('Ip_address'), operate:false, visible:false},
                        {field: 'is_automatic', title: __('Is_automatic'), visible:false, searchList: {"0":__('Is_automatic 0'),"1":__('Is_automatic 1')}, formatter: Table.api.formatter.normal},

                        //{field: 'is_recharge', title: __('Is_recharge'), visible:false, searchList: {"0":__('Is_recharge 0'),"1":__('Is_recharge 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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