define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.agentacc/index' + location.search,
                    add_url: 'thirdacc.agentacc/add',
                    edit_url: 'thirdacc.agentacc/edit',
                    del_url: 'thirdacc.agentacc/del',
                    multi_url: 'thirdacc.agentacc/multi',
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
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'agent.username', title: __('Agent_name')},
                        {field: 'acc.name', title: __('Acc.name')},
                        {field: 'acc_code', title: __('Acc_code')},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'ajax',
                                    text: '同步码商和商户',
                                    title: __('同步码商和商户'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    
                                    url: 'thirdacc.acc/syncuseracc',
                                    confirm:'确定要把该通道同步给所有码商和商户?',
                                    success: function (data,ret) {

                                    },
                                    error: function (data,ret) {

                                    }
                                },
                            ]
                        }
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