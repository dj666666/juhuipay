define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order.order/index' + location.search,
                    /*add_url: 'order.order/add',
                    edit_url: 'order.order/edit',
                    del_url: 'order.order/del',
                    multi_url: 'order.order/multi',*/
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                //启用固定列
                fixedColumns: true,
                //固定右侧列数
                fixedRightNumber: 1,
                escape: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'merchant.nickname', title: __('Mer_id')},
                        {field: 'user.nickname', title: __('User_id')},
                        {field: 'out_trade_no', title: __('Out_trade_no')},
                        {field: 'trade_no', title: __('Trade_no')},
                        {field: 'xl_order_id', title: __('Xl_order_id')},
                        {field: 'pay_type', title: __('Pay_type'),addclass: "selectpage",extend:'data-source="thirdacc.agentacc/getAccForSelect" data-primary-key="code" data-multiple="false" data-select-only="true"'},
                        {field: 'qrcode_name', title: __('Qrcode_name'), operate: 'LIKE %...%'},
                        {field: 'pay_remark', title: __('Pay_remark'), operate:false,visible:false},
                        {field: 'zfb_code', title: __('Zfb_code'), operate:false,visible:false},
                        {field: 'zfb_nickname', title: __('Zfb_nickname'), operate:false,visible:false},
                        
                        {field: 'is_gmm_close', title: __('Is_gmm_close'),visible:false, searchList: {"0":__('Is_gmm_close 0'),"1":__('Is_gmm_close 1')}, formatter: Table.api.formatter.normal},
                        
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:false},
                        {field: 'fees', title: __('Fees'), operate:false,visible:false},
                        {field: 'mer_fees', title: __('Mer_fees'), operate:false,visible:false},
                        {field: 'pay_qrcode_image', title: __('Pay_qrcode_image'), operate:false,visible:false, events: Table.api.events.image, formatter: Table.api.formatter.image},

                        
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')},custom:  {"1": 'success', "2": 'info', "3": 'danger'}, formatter: Table.api.formatter.flag},
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"0":__('Is_callback 0'),"1":__('Is_callback 1'),"2":__('Is_callback 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},
                        {field: 'callback_count', title: __('Callback_count'), operate:false,visible:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'ordertime', title: __('Ordertime'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'callback_time', title: __('Callback_time'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'expire_time', title: __('Expire_time'),visible:false, operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'ip_address', title: __('Ip_address'), operate:false,visible:false},
                        {field: 'deal_ip_address', title: __('Deal_ip_address'), operate:false,visible:false},
                        {field: 'deal_username', title: __('Deal_username')},
                        {field: 'remark', title: __('Remark'), operate:false, visible:false },
                        
                        {field: 'is_third_pay', title: __('Is_third_pay'), searchList: {"0":__('Is_third_pay 0'),"1":__('Is_third_pay 1'),"2":__('Is_third_pay 2')}, custom:  {"1": 'success', "2": 'danger', "0": 'primary'}, formatter: Table.api.formatter.flag},

                        {field: 'user_ip_address', title: __('User_ip_address')},
                        {field: 'device_type', title: __('Device_type'), operate:false},
                        
                        
                        {field: 'is_resetorder', title: __('Is_resetorder'),visible:false, searchList: {"0":__('Is_resetorder 0'),"1":__('Is_resetorder 1')}, formatter: Table.api.formatter.normal},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                
                                {
                                    name: 'ajax',
                                    text: '查单',
                                    title: __('查单'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    /*icon: 'fa fa-exclamation',*/
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/xlqueryorder',
                                    success: function (data, ret) {
                                        //table.bootstrapTable('refresh');

                                        //Toastr.info(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        //Toastr.error(ret.msg);
                                        //table.bootstrapTable('refresh');
                                        //return false;
                                    }
                                },
                                {
                                    name: 'ajax',
                                    text: 'gmm取消订单',
                                    title: __('gmm取消订单'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    /*icon: 'fa fa-exclamation',*/
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/closeOrder',
                                    confirm: '确定取消该订单中的gmm订单吗？',
                                    hidden:function(row){
                                        if(row.pay_type != '1028' || row.is_gmm_close == 1){
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
                                {
                                    name: 'ajax',
                                    text: '补单',
                                    title: __('补单'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //url: 'order.order/resetOrder',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    //confirm: '确定补单吗？',
                                    // hidden:function(row){

                                    //     if(row.status != 3 ){
                                    //         return true;
                                    //     }
                                    // },
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
                                    text: '拉黑ip',
                                    title: __('拉黑ip'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-click',
                                    //icon: 'fa fa-exclamation',
                                    //extend:'style="width:100px;height:30px;padding-top:5px"',
                                    url: 'order.order/blockip',
                                    confirm: '确定拉黑该ip吗？',
                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'order.order/blockip',
                                            data:{'ids':ret.id,'ip':ret.user_ip_address}
                                        }, function(data, ret){
                                            //table.bootstrapTable('refresh');
                                            //成功的回调
                                            //alert(ret.msg);
                                            //Toastr.success(ret.msg);
                                            //return false;
                                        }, function(data, ret){
                                            //table.bootstrapTable('refresh');
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
                                
                                {
                                    name: 'detail',
                                    text: '详情',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    //icon: 'fa fa-list',
                                    url: 'order.order/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                            ],
                        }
                    ]
                ],
                search:false,
                showSearch: true,
                searchFormVisible: false,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#allmoney").text(data.extend.allmoney);
                $("#allorder").text(data.extend.allorder);
                //$("#allfees").text(data.extend.allfees);
                $("#success_rate").text(data.extend.success_rate);
                $("#today_success_rate").text(data.extend.today_success_rate);
                
                
                
                
                
                /*var acc_list = data.extend.acc_list;
                
                // 获取容器元素
                var container = document.getElementById('acccontainer');
                
                // 清空容器中的现有内容
                container.innerHTML = '';
                
                // 遍历数据并渲染模板
                for (var i = 0; i < acc_list.length; i++) {
                    // 创建外层容器元素
                    var colDiv = document.createElement('div');
                    colDiv.className = 'col-xs-2 col-md-2';
                    
                    // 创建内层容器元素
                    var panelBodyDiv = document.createElement('div');
                    panelBodyDiv.className = 'panel-body';
                    
                    // 创建名称元素
                    var nameP = document.createElement('p');
                    nameP.className = 'no-margins';
                    nameP.textContent = '名称: ' + acc_list[i].acc_name;
                    
                    // 创建开启数量元素
                    var onNumP = document.createElement('p');
                    onNumP.className = 'no-margins';
                    onNumP.textContent = '开启: ' + acc_list[i].on_num + '    关闭: ' + acc_list[i].off_num;*/
                    
                    /*// 创建关闭数量元素
                    var offNumP = document.createElement('p');
                    offNumP.className = 'no-margins';
                    offNumP.textContent = '关闭: ' + acc_list[i].off_num;*/
                    
                    // 创建成率元素
                    /*var rate = document.createElement('p');
                    rate.className = 'no-margins';
                    rate.textContent = '今日成率: ' + acc_list[i].today_rate;
                    
                    // 将元素添加到内层容器中
                    panelBodyDiv.appendChild(nameP);
                    panelBodyDiv.appendChild(onNumP);
                    //panelBodyDiv.appendChild(offNumP);
                    panelBodyDiv.appendChild(rate);
            
                    // 将内层容器添加到外层容器中
                    colDiv.appendChild(panelBodyDiv);
            
                    // 将外层容器添加到容器元素中
                    container.appendChild(colDiv);
                }*/
                
                
            });
            
            // 监听事件
            $(document).on("click", ".btn-acclist", function () {
                
                //发送给控制器
                Fast.api.open("thirdacc.agentacc/index", "通道列表", {
                    callback: function (value) {
                        
                    }
                });
                
            });
            
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
            }
        }
    };
    return Controller;
});