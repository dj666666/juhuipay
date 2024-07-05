define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'daifu.order/index' + location.search,
                    add_url: 'daifu.order/add',
                    batch_add_url: 'daifu.order/batch_add',
                    /*edit_url: 'order.order/edit',
                    del_url: 'order.order/del',
                    multi_url: 'order.order/multi',*/
                    import_url : 'daifu.order/import',
                    import_sec_url : 'daifu.order/importsec',
                    table: 'order',
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
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false},
                        {field: 'bank_user', title: __('Bank_user')},
                        //{field: 'bank_type', title: __('Bank_type'), operate:false},
                        {field: 'bank_number', title: __('Bank_number')},
                        {field: 'pz_img', title: __('Pz_img'), operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')},custom:  {"1": 'success', "2": 'info', "3": 'danger', "4": 'warning'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), operate:false, searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ip_address', title: __('Ip_address'), operate:false},
                        {field: 'remark', title: __('Remark'), operate:'LIKE'},
                        {field: 'outbank', title: __('Outbank')},
                    ]
                ],
                search:false,
                showSearch: false,
                searchFormVisible: true,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#block_money").text(data.extend.block_money);
                $("#money").text(data.extend.money);
                $("#allmoney").text(data.extend.allmoney);
                $("#allorder").text(data.extend.allorder);
                $("#allfees").text(data.extend.allfees);

            });

            // 监听事件
            $(document).on("click", ".btn-batch_add", function () {

                //发送给控制器
                Fast.api.open("daifu.order/batch_add", "批量提交代付单", {
                    callback: function (value) {

                    }
                });

            });

            /*// 监听事件
            $(document).on("click", ".btn-batch_add", function () {

                Upload.api.plupload($(Table.config.importbtn, toolbar), function (data, ret) {
                    Fast.api.ajax({
                        url: options.extend.import_url,
                        data: {file: data.url},
                    }, function (data, ret) {
                        table.bootstrapTable('refresh');
                    });
                });

            });*/

            $(function(){
                startTimer();
            });

            function startTimer(){
                timer = setInterval(function(){
                    $(".btn-refresh").trigger("click");
                }, Config.refreshtime);
            }

        },
        add: function () {
            Controller.api.bindevent();
        },
        batch_add: function () {
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