define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.agentacc/index' + location.search,
                    /*add_url: 'thirdacc.agentacc/add',
                    edit_url: 'thirdacc.agentacc/edit',
                    del_url: 'thirdacc.agentacc/del',
                    multi_url: 'thirdacc.agentacc/multi',*/
                    table: 'agent_acc',
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
                        
                        {field: 'acc.name', title: __('Acc.name')},
                        {field: 'acc_code', title: __('Acc_code')},
                        {field: 'on_num', title: __('On_num'), operate:false},
                        {field: 'off_num', title: __('Off_num'), operate:false},
                        {field: 'today_rate', title: __('Today_rate'), operate:false},
                    ]
                ],
                earch:false,
                search:false,
                showSearch: false,
                searchFormVisible: false,
                showToggle: false,
                showColumns: false,
                showExport: false,
                
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