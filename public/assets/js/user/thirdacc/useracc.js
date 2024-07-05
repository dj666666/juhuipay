define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.useracc/index/ids/'  + Config.user_id  + location.search,
                    add_url: 'thirdacc.useracc/add',
                    edit_url: 'thirdacc.useracc/edit',
                    //del_url: 'thirdacc.useracc/del',
                    multi_url: 'thirdacc.useracc/multi',
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
                        //{checkbox: true},
                        //{field: 'id', title: __('Id'), operate:false},
                        {field: 'acc.name', title: __('Acc_name'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
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
                    url: "thirdacc.useracc/syncUserAcc",

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
        myacc: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.useracc/myacc' + location.search,
                    //add_url: 'thirdacc.useracc/add',
                    //edit_url: 'thirdacc.useracc/edit',
                    //del_url: 'thirdacc.useracc/del',
                    multi_url: 'thirdacc.useracc/multi',
                    table: 'user_acc',
                }
            });

            var table = $("#myacctable");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                templateView: true,
                columns: [
                    [
                        {field: 'id', title: 'ID', operate: false},
                        {field: 'acc.name', title: __('Acc_name'), operate:false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'today_success_rate', title: __('Today_success_rate'), operate:false},
                        //通过Ajax渲染searchList
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ],
                ],
                search:false,
                showSearch: false,
                searchFormVisible: false,
                visible: false,
                showExport: false,
                showToggle: false,
                showColumns: false,
                //分页大小
                pageSize: 12
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            //指定搜索条件
            $(document).on("click", ".btn-toggle-view", function () {
                var options = table.bootstrapTable('getOptions');
                table.bootstrapTable('refreshOptions', {templateView: !options.templateView});
            });

            //点击前往码列表
            $(document).on("click", ".btn-qrcodemanage[data-id]", function () {
                //Fast.api.addtabs("链接","标题");
                Backend.api.addtabs('thirdacc.userqrcode/myqrcode/ids/' + $(this).data('id'), '账号管理');
            });

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});