define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.alipayzhutiuser/index' + location.search,
                    add_url: 'thirdacc.alipayzhutiuser/add',
                    edit_url: 'thirdacc.alipayzhutiuser/edit',
                    del_url: 'thirdacc.alipayzhutiuser/del',
                    multi_url: 'thirdacc.alipayzhutiuser/multi',
                    table: 'alipay_zhuti_user',
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
                        
                        {field: 'alipayzhuti.name', title: __('Alipayzhuti.name')},
                        {field: 'user.nickname', title: __('User.nickname')},
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