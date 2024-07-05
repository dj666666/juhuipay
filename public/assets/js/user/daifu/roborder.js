define(['jquery', 'bootstrap', 'backend', 'table', 'form','clipboard.min'], function ($, undefined, Backend, Table, Form, ClipboardJS) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'daifu.roborder/index' + location.search,
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'asc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        //{field: 'merchant.username', title: __('Mer_id'), operate:false},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'amount', title: __('Amount'), operate:false, events: Controller.api.xiaobao, formatter:function(value,row,index){

                                if(row.status === '2' && row.is_robbed === 1){
                                    return '<a href="javascript:;"  data-clipboard-text="'+value+'"class="btn btn-xs btn-fuzhi btn-success" data-toggle="tooltip" title="" data-table-id="table" data-field-index="20" data-row-index="0" data-button-index="1" data-original-title="点击复制">'+value+'</a></br>';
                                }else{
                                    return value;
                                }
                            }
                        },
                        {field: 'bank_user', title: __('Bank_user'), events: Controller.api.xiaobao, formatter:function(value,row,index){

                                if(row.status === '2' && row.is_robbed === 1){
                                    return '<a href="javascript:;"  data-clipboard-text="'+value+'"class="btn btn-xs btn-fuzhi btn-success" data-toggle="tooltip" title="" data-table-id="table" data-field-index="20" data-row-index="0" data-button-index="1" data-original-title="点击复制">'+value+'</a></br>';
                                }else{
                                    return value;
                                }
                            }
                        },
                        {field: 'bank_type', title: __('Bank_type'), operate:false, events: Controller.api.xiaobao, formatter:function(value,row,index){

                                if(row.status === '2' && row.is_robbed === 1){
                                    return '<a href="javascript:;"  data-clipboard-text="'+value+'"class="btn btn-xs btn-fuzhi btn-success" data-toggle="tooltip" title="" data-table-id="table" data-field-index="20" data-row-index="0" data-button-index="1" data-original-title="点击复制">'+value+'</a></br>';
                                }else{
                                    return value;
                                }
                            }
                        },
                        {field: 'bank_number', title: __('Bank_number'), events: Controller.api.xiaobao, formatter:function(value,row,index){

                                if(row.status === '2' && row.is_robbed === 1){
                                    return '<a href="javascript:;"  data-clipboard-text="'+value+'"class="btn btn-xs btn-fuzhi btn-success" data-toggle="tooltip" title="" data-table-id="table" data-field-index="20" data-row-index="0" data-button-index="1" data-original-title="点击复制">'+value+'</a></br>';
                                }else{
                                    return value;
                                }
                            }
                        },
                        {field: 'is_robbed', title: __('Is_robbed'), operate:false,visible:false, searchList: {"1":__('Is_robbed 1'),"0":__('Is_robbed 0')},custom:  {"1": 'success', "2": 'warning'}, formatter: Table.api.formatter.flag},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')},custom:  {"1": 'success', "2": 'info', "3": 'danger', "4": 'warning'}, formatter: Table.api.formatter.flag,defaultValue:2},

                        {field: 'is_callback', title: __('Is_callback'), operate:false,visible:false, searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'remark', title: __('Remark'), operate:'LIKE',visible:false},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'ajax',
                                    text: '抢单',
                                    title: __('抢单'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    //icon: 'fa fa-close',
                                    url: 'daifu.roborder/robOrder',
                                    extend:'style="width:100px;height:30px;padding-top:5px"',
                                    //confirm: '确定抢该订单吗？',
                                    hidden:function(row){

                                        if(row.is_robbed == 1){
                                            return true;
                                        }

                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');

                                        //Toastr.info(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        //Toastr.error(ret.msg);
                                        table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },

                            ],
                        }
                    ]
                ],
                search:false,
                showSearch: false,
                commonSearch: false,
                searchFormVisible: true,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                // $("#allmoney").text(data.extend.allmoney);
                // $("#allorder").text(data.extend.allorder);
                // $("#todaymoney").text(data.extend.todayMoney);
                // $("#todayorder").text(data.extend.todayOrder);
                //$("#allfees").text(data.extend.allfees);
                //$("#mer_money").text(data.extend.mer_money);
                //$("#success_rate").text(data.extend.success_rate);

                /*var myAuto = document.getElementById("myaudio");
                if(data.extend.ordernum > 0){
                    //Toastr.info('您有新的代付订单');
                    myAuto.play();
                }else{
                    myAuto.pause();
                }*/
            });

            var clipboard = new ClipboardJS('.btn-fuzhi');
            clipboard.on('success', function(e) {
                Toastr.info('复制成功');
            });

            clipboard.on('error', function(e) {
                Toastr.danger('复制成功');
            });


            var myAuto = document.getElementById("myaudio");
            var applyaudio = document.getElementById("applyaudio");

            function startTimer(){
                timer = setInterval(function(){
                    $(".btn-refresh").trigger("click");
                    /*Fast.api.ajax({
                        url:"order.order/getorder",
                        loading:false,
                    }, function(data, ret){
                        //成功回调
                        return false;
                    },function(data, ret){

                        if(ret.ordernum > 0){
                            console.log(ret.ordernum);
                            myAuto.play();
                        }
                        if(ret.applynum > 0){
                            console.log(ret.applynum);
                            applyaudio.play();
                        }
                        return false;

                    });*/
                }, Config.ordertime);
            }

            $(function(){
                startTimer();
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