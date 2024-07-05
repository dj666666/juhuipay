define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.meracc/index/ids/' + Config.user_id + location.search,
                    add_url: 'thirdacc.meracc/add',
                    edit_url: 'thirdacc.meracc/edit',
                    del_url: 'thirdacc.meracc/del',
                    multi_url: 'thirdacc.meracc/multi',
                    table: 'user_acc',
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
                        {field: 'acc_code', title: __('Acc_code')},
                        {field: 'acc.name', title: __('Acc.name'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'status', title: __('Status'), operate:false, searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        //{field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ],
                search:false,
                showSearch: false,
                searchFormVisible: false,
                visible: false,
                showExport: false,
                showToggle: false,
                showColumns: false,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            // 启动和暂停按钮
            $(document).on("click", ".btn-sync", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                //var ids = Table.api.selectedids(table);
                //Table.api.multi("changestatus", ids.join(","), table, this);

                var that = this;
                var ids = Table.api.selectedids(table);

                Backend.api.ajax({
                    url: "thirdacc.meracc/syncUserAcc",

                });
                table.bootstrapTable('refresh');

            });

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