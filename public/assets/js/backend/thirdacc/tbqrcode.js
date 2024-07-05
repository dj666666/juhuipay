define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.tbqrcode/index' + location.search,
                    add_url: 'thirdacc.tbqrcode/add',
                    edit_url: 'thirdacc.tbqrcode/edit',
                    del_url: 'thirdacc.tbqrcode/del',
                    multi_url: 'thirdacc.tbqrcode/multi',
                    table: 'tbqrcode',
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
                        {field: 'groupqrcode.name', title: __('Groupqrcode.name')},
                        {field: 'group_qrcode_id', title: __('Group_qrcode_id'), operate:false},
                        {field: 'amount', title: __('Amount')},
                        {field: 'pay_url', title: __('Pay_url'), formatter: Table.api.formatter.url},
                        {field: 'good_name', title: __('Good_name'), operate:false,visible:false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'is_lock', title: __('Is_use'), searchList: {"0":__('Is_use 0'),"1":__('Is_use 1')}, formatter: Table.api.formatter.status},
                        {field: 'use_num', title: __('Use_num'), operate:false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},

                        {field: 'expire_status', title: __('Expire_status'), operate:false,visible:false, searchList: {"0":__('Expire_status 0'),"1":__('Expire_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'expire_time', title: __('Expire_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});