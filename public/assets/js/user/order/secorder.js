define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order.secorder/index' + location.search,
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                escape: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'user.username', title: __('User_id')},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'xl_order_id', title: __('Xl_order_id')},
                        {field: 'qrcode_name', title: __('Qrcode_name')},
                        
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false,visible:false},
                        {field: 'pay_remark', title: __('Pay_remark')},
                        {field: 'zfb_code', title: __('Zfb_code'), operate:false,visible:false},
                        {field: 'zfb_nickname', title: __('Zfb_nickname')},
                        {field: 'third_hx_status', title: __('Third_hx_status'), visible:false, searchList: {"0":__('Third_hx_status 0'),"1":__('Third_hx_status 1'),"2":__('Third_hx_status 2'),"3":__('Third_hx_status 3'),"4":__('Third_hx_status 4')}, formatter: Table.api.formatter.normal},
                        
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')},custom:  {"1": 'success', "2": 'info', "3": 'danger'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'callback_count', title: __('Callback_count'), operate:false,visible:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'expire_time', title: __('Expire_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'remark', title: __('Remark'), operate:'LIKE',visible:false},

                    ]
                ],
                search:false,
                //showSearch: false,
                //searchFormVisible: true,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            table.off('dbl-click-row.bs.table');



            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#today_success_amount").text(data.extend.today_success_amount);
                $("#today_success_order").text(data.extend.today_success_order);
                $("#today_fees").text(data.extend.today_fees);
                $("#today_success_rate").text(data.extend.today_success_rate);

                /*var myAuto = document.getElementById("myaudio");
                if(data.extend.ordernum > 0){
                    //Toastr.info('您有新的代付订单');
                    myAuto.play();
                }else{
                    myAuto.pause();
                }*/
            });
            var myAuto = document.getElementById("myaudio");
            var applyaudio = document.getElementById("applyaudio");

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
            },
            formatter: {//渲染的方法
                ip: function (value, row, index) {
                    return '<a href="javascript:;"  data-clipboard-text="'+value+'"class="btn btn-xs btn-fuzhi btn-success" data-toggle="tooltip" title="" data-table-id="table" data-field-index="13" data-row-index="0" data-button-index="1" data-original-title="点击复制">'+value+'</a>';
                },
            },
            xiaobao:{
                'click .btn-fuzhi': function (e, value, row, index) {

                }
            },
            today:function(AddDayCount){
                var dd = new Date();
                dd.setDate(dd.getDate() + AddDayCount);
                var y = dd.getFullYear();
                var m = dd.getMonth()+1;
                var d = dd.getDate();

                //判断月
                if(m <10){
                    m = "0" + m;
                }else{
                    m = m;
                }

                //判断日
                if(d<10){ //如果天数小于10
                    d = "0" + d;
                }else{
                    d = d;
                }
                return y+"-"+m+"-"+d;
            }
        }
    };
    return Controller;
});