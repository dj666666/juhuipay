define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order.order/index' + location.search,
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
                        //{field: 'merchant.username', title: __('Mer_id'), operate:false,visible:false},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'xl_order_id', title: __('Xl_order_id'),visible:false},
                        {field: 'qrcode_name', title: __('Qrcode_name')},
                        {field: 'pay_type', title: __('Pay_type')},
                        
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false,visible:false},
                        {field: 'pay_remark', title: __('Pay_remark')},
                        {field: 'zfb_code', title: __('Zfb_code'), operate:false,visible:false},
                        {field: 'zfb_nickname', title: __('Zfb_nickname')},
                        {field: 'is_gmm_close', title: __('Is_gmm_close'),visible:false, searchList: {"0":__('Is_gmm_close 0'),"1":__('Is_gmm_close 1')}, formatter: Table.api.formatter.normal},
                        
                        {field: 'pay_qrcode_image', title: __('Pay_qrcode_image'), operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')},custom:  {"1": 'success', "2": 'info', "3": 'danger'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'callback_count', title: __('Callback_count'), operate:false,visible:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'expire_time', title: __('Expire_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'callback_time', title: __('Callback_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'user_ip_address', title: __('User_ip_address'), operate:false,visible:false},
                        {field: 'device_type', title: __('Device_type'), operate:false,visible:false},
                        {field: 'remark', title: __('Remark'), operate:'LIKE',visible:false},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            
                            buttons: [
                                
                                {
                                    name: 'ajax',
                                    text: '成功',
                                    title: __('支付成功'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-click',
                                    extend:'style="width:70px;height:25px;padding-top:3px"',
                                    //confirm: '确定成功该订单吗？',
                                    hidden:function(row){
                                        if(row.status == 1 || row.status == 3){
                                            return true;
                                        }
                                        
                                    },
                                    click: function (data, ret) {
                                        
                                        var that = this;
                                        Layer.prompt({title: '请输入备注', formType: 0}, function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/complete",
                                                data: "ids=" + ret.id + "&pay_remark=" + value
                                            },function (data, ret) {
                                                table.bootstrapTable('refresh');
                                                Layer.close(index);
                                            });

                                        });
                                        
                                       
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    }
                                },
                                
                                /*{
                                    name: 'ajax',
                                    text: '成功',
                                    title: __('支付成功'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-click',
                                    //icon: 'fa fa-check',

                                    extend:'style="width:70px;height:25px;padding-top:3px"',
                                    confirm: '确定成功该订单吗？',
                                    hidden:function(row){
                                        if(row.status == 1 || row.status == 3){
                                            return true;
                                        }
                                        if(row.pay_type == '1018'){
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'order.order/complete',
                                            data:{'ids':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //alert(ret.msg);
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //alert(ret.msg);
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });
                                        
                                       
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    }
                                },*/
                                
                                {
                                    name: 'ajax',
                                    text: '失败',
                                    title: __('支付失败'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    extend:'style="width:70px;height:25px;padding-top:3px"',
                                    confirm: '确定该订单为失败吗？',
                                    hidden:function(row){

                                        if( row.status == 1 || row.status == 3){
                                            return true;
                                        }

                                    },
                                    click: function (data, ret) {

                                        // var that = this;
                                        // Layer.prompt({title: '请输入驳回原因', formType: 0}, function (value, index) {
                                        //     Backend.api.ajax({
                                        //         url: "order.index/abnormal",
                                        //         data: "id=" + ret.id + "&remark=" + value
                                        //     },function (data, ret) {
                                        //         table.bootstrapTable('refresh');
                                        //         Layer.close(index);
                                        //     });

                                        // });
                                        Fast.api.ajax({
                                            url:'order.order/abnormal',
                                            data:{'id':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //alert(ret.msg);
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //alert(ret.msg);
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });

                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                {
                                    name: 'ajax',
                                    text: '补单',
                                    title: __('补单'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //url: 'order.order/resetOrder',
                                    extend:'style="width:70px;height:25px;padding-top:3px"',
                                    //confirm: '确定补单吗？',
                                    hidden:function(row){

                                        if(row.status != 3 ){
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {

                                        var that = this;
                                        Layer.prompt({title: '补单原因', formType: 0}, function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/resetOrder",
                                                data: "id=" + ret.id + "&remark=" + value
                                            },function (data, ret) {
                                                table.bootstrapTable('refresh');
                                                Layer.close(index);
                                            });

                                        });

                                        /*Fast.api.ajax({
                                            url:'order.order/abnormal',
                                            data:{'id':ret.id,'outbank':''}
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //成功的回调
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            table.bootstrapTable('refresh');
                                            //失败的回调
                                            //Toastr.error(ret.msg);
                                            //return false;
                                        });*/

                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                
                                {
                                    name: 'ajax',
                                    text: '补发通知',
                                    title: __('补发通知'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //url: 'order.order/resetOrder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    //confirm: '确定补单吗？',
                                    hidden:function(row){
                                        if(row.status == 2 || row.status == 3 || row.is_callback == 1 ){
                                            return true;
                                        }
                                    },
                                    
                                    click: function (data, ret) {

                                        var that = this;
                                        Layer.prompt({title: '原因', formType: 0}, function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/reissueNotice",
                                                data: "id=" + ret.id + "&remark=" + value
                                            },function (data, ret) {
                                                table.bootstrapTable('refresh');
                                                Layer.close(index);
                                            });

                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                
                            ],
                            
                        }
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
                $("#balance").text(data.extend.balance);
                $("#allmoney").text(data.extend.allmoney);
                $("#allorder").text(data.extend.allorder);
                $("#allfees").text(data.extend.allfees);
                //$("#mer_money").text(data.extend.mer_money);
                $("#success_rate").text(data.extend.success_rate);
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

            function startTimer(){
                timer = setInterval(function(){
                    $(".btn-refresh").trigger("click");
                    
                    Fast.api.ajax({
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
                    });
                    
                }, Config.ordertime);
            }

            $(function(){
                if(Config.is_refresh == 1){
                    startTimer();
                }
                
            });
            
            // 启动接单 和 停止按钮
            $(document).on("click", "#start", function () {
                console.log('11');
                Fast.api.ajax({
                    url:'user.user/changereceive',
                    data:{id:Config.uid}
                }, function(data, ret){
                    if(ret.code == 1){
                        Toastr.info(ret.msg);
                        window.location.reload();
                    }
                    return false;
                }, function(data, ret){

                    //alert(ret.msg);
                    return false;
                });
            });

            // 启动和暂停按钮
            $(document).on("click", "#stop", function () {
                console.log('11');
                Fast.api.ajax({
                    url:'user.user/changereceive',
                    data:{id:Config.uid}
                }, function(data, ret){
                    if(ret.code == 1){
                        Toastr.info(ret.msg);
                        window.location.reload();
                    }
                    return false;
                }, function(data, ret){
                    //alert(ret.msg);
                    return false;
                });
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