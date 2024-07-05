define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.userqrcode/index' + location.search,
                    add_url: 'thirdacc.userqrcode/add',
                    edit_url: 'thirdacc.userqrcode/edit',
                    del_url: 'thirdacc.userqrcode/del',
                    multi_url: 'thirdacc.userqrcode/multi',
                    import_url : 'thirdacc.userqrcode/import', 
                    table: 'group_qrcode',
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
                        {field: 'acc_code', title: __('Acc_code'),visible:false,addclass: "selectpage",extend:'data-source="thirdacc.userqrcode/getAccForSelect" data-primary-key="code" data-multiple="false" data-select-only="true" data-order-by="id" '},
                        {field: 'acc_type', title: __('Acc_type'), operate:false},
                        {field: 'name', title: __('Name'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'android_key', title: __('Android_key'), operate:false,visible:false},
                        
                        {field: 'image', title: __('Image'), operate:false,visible:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'zfb_pid', title: __('Zfb_pid')},
                        {field: 'tb_good_price', title: __('Tb_good_price'), operate:false,visible:false},
                        //{field: 'type', title: __('Type'), operate:false,visible:false, searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'rule', title: __('Rule'), operate:false},
                        /*{field: 'all_money', title: __('All_money'), operate:false},
                        {field: 'success_rate', title: __('Success_rate'), operate:false},
                        {field: 'today_money', title: __('Today_money'), operate:false},
                        {field: 'today_success_rate', title: __('Today_success_rate'), operate:false},*/
                        {field: 'statistics', title: __('Statistics'), operate:false},

                        {field: 'yd_is_diaoxian', title: __('Yd_is_diaoxian'), operate:false,visible:false, searchList: {"0":__('Yd_is_diaoxian 0'),"1":__('Yd_is_diaoxian 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'status', title: __('Status'),formatter: Controller.api.formatter.custom},
                        //{field: 'is_use', title: __('Is_use'), searchList: {"0":__('Is_use 0'),"1":__('Is_use 1')}, formatter: Table.api.formatter.normal},
                        {field: 'remark', title: __('Remark'), operate:false},
                        {field: 'android_heart', title: __('Android_heart'), operate:false ,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            
                            buttons:[
                                {
                                    name: 'ajax',
                                    text: '测试',
                                    title: __('单通道测试'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //extend:'style="width:80px;height:25px;padding-top:2px"',
                                    click: function (data, ret) {

                                        var that = this;
                                        var ids = Table.api.selectedids(table);

                                        Layer.prompt({title: '请输入金额', formType: 0, value:'1.00'},function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/payTest",
                                                data: "id=" + ret.id + "&amount=" + value
                                            },function (data, ret) {
                                                console.log(ret.data);
                                                //table.bootstrapTable('refresh');
                                                //Layer.close(index);
                                                
                                                if (ret.code == 1) {
                                                    var pay_url = ret.data;
                                                    var qc_img = '/qr.php?text='+pay_url+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                                    layer.open({
                                                      type: 1,
                                                      title: '支付地址',
                                                      area: ['350px', '300px'],
                                                      closeBtn: 1, 
                                                      anim: 2,
                                                      shadeClose: true,
                                                      content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                                    });
                                                    
                                                    window.open(ret.data);
                                                }
                                                
                                            },function (data, ret) {
                                               
                                            });

                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                {
                                    name: '登录',
                                    text: '登录',
                                    title: '登录',
                                    classname: 'btn btn-xs btn-primary btn-click',
                                    //icon: 'fa fa-list',
                                    url: 'thirdacc.userqrcode/ydqrcode',
                                    //extend: 'data-area="["1000px", "1000px"]"',
                                    hidden:function(row){
                                        if(row.acc_code == '1008' || row.acc_code == '1041' || row.acc_code == '1025'){
                                            return false;
                                        }else{
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {
                                        //Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                        var that = this;
                                        var ids = Table.api.selectedids(table);
                                        var acc_id = ret.id;
                                        Backend.api.ajax({
                                                url: "thirdacc.userqrcode/cloud",
                                                data: {type: 'getqrcode'},
                                            },function (data, ret) {
                                                console.log(ret.data);
                                                var qc_img = '/qr.php?text='+ret.qrcodeurl+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                                layer.open({
                                                  type: 1,
                                                  title: '支付宝本地登录',
                                                  area: ['350px', '300px'],
                                                  closeBtn: 1, 
                                                  anim: 2,
                                                  shadeClose: true,
                                                  content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                                });
                                                login_id = ret.loginid;
                                                
                                                //开启定时器检测
                                                alipay_time =window.setInterval(function() {
                                                	$.ajax({
                                                        url: 'thirdacc.userqrcode/cloud',
                                                        dataType: 'json',
                                                        data: {acc_id:acc_id,type: 'getcookie',loginid:login_id},
                                                        cache: false,
                                                        success: function (res) {
                                                            if(res.code==1)
                                                            {
                                                                window.clearInterval(alipay_time);
                                                                layer.msg(res.msg, {icon: 1});
                                                                //获取成功,执行保存代码
                                                               //table.bootstrapTable('refresh');
                                                                window.location.reload();
                                                            }
                                                        }, error: function () {
                                                            Toastr.error(__('Network error'));
                                                        }
                                                    });
                                                    
                                                },
                                                5000);
                                                
                                                
                                            },function (data, ret) {
                                               
                                            });
                                            
                                    }
                                },
                                {
                                    name: '授权',
                                    text: '授权',
                                    title: '授权',
                                    classname: 'btn btn-xs btn-primary btn-click',
                                    //icon: 'fa fa-list',
                                    url: 'thirdacc.userqrcode/alisqqrcode',
                                    //extend: 'data-area="["1000px", "1000px"]"',
                                    hidden:function(row){
                                        if(row.acc_code !== '1050'){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                    },
                                    click: function (data, ret) {
                                        //Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                        var that = this;
                                        var ids = Table.api.selectedids(table);
                                        var acc_id = ret.id;
                                        Backend.api.ajax({
                                            url: "thirdacc.userqrcode/cloud",
                                            data: {type: 'getqrcode'},
                                        },function (data, ret) {
                                            console.log(ret.data);
                                            var qc_img = '/qr.php?text='+ret.qrcodeurl+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                            layer.open({
                                                type: 1,
                                                title: '支付宝登录',
                                                area: ['350px', '300px'],
                                                closeBtn: 1,
                                                anim: 2,
                                                shadeClose: true,
                                                content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                            });
                                            login_id = ret.loginid;

                                            //开启定时器检测
                                            alipay_time =window.setInterval(function() {
                                                    $.ajax({
                                                        url: 'thirdacc.userqrcode/cloud',
                                                        dataType: 'json',
                                                        data: {acc_id:acc_id,type: 'getcookie',loginid:login_id},
                                                        cache: false,
                                                        success: function (res) {
                                                            if(res.code==1)
                                                            {
                                                                window.clearInterval(alipay_time);
                                                                layer.msg(res.msg, {icon: 1});
                                                                //获取成功,执行保存代码
                                                                //table.bootstrapTable('refresh');
                                                                window.location.reload();
                                                            }
                                                        }, error: function () {
                                                            Toastr.error(__('Network error'));
                                                        }
                                                    });

                                                },
                                                5000);


                                        },function (data, ret) {

                                        });

                                    }
                                },
                            ]
                        }
                    ]
                ],
                search:false,
                //showSearch: false,
                //searchFormVisible: true,
            });

            /*// 监听事件
            $(document).on("click", ".btn-batchedit", function () {

                //发送给控制器 打开弹窗
                Fast.api.open("thirdacc.userqrcode/batchedit", "一键修改", {area: ['80%', '90%']});

            });*/
            
            // 监听事件
            $(document).on("click", ".btn-batchedit", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;

                //发送给控制器
                Fast.api.open("thirdacc.userqrcode/batchedit"+ids, "一键修改", {
                    callback: function (value) {

                    }
                });

            });
            
            // 为表格绑定事件
            Table.api.bindevent(table);
            
            //双击事件
            //table.off('dbl-click-row.bs.table');
        },
        add: function () {
            Controller.api.bindevent();
    		 //绑定change事件，当下拉框内容发生变化时启动事件
            /*$("#c-acc_code").bind("change",function(){
                var val = $("#c-acc_code").val();
    		    console.log(val)
            });*/
                
        },
        batchedit: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
            
            require.config({
                paths : {
                    //"jquery" : ["/assets/css/dfpay/jquery"],
                    "qrcode" : ["/assets/css/dfpay/qrcode.min"],
                }
            });
            
            var appconfig = Config.app_config;
            console.log(Config.app_config)
            require(['qrcode'], function (Qrcode){

                var qrcode = new QRCode("configdiv", {
                    
                    render: "canvas",
                    text: appconfig,
                    width: 100,
                    height: 100,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.L
                });
            
            });
            
            
            
            
        
        },
        /*ydqrcode: function () {
            Controller.api.bindevent();
            require.config({
                paths : {
                    //"jquery" : ["/assets/css/dfpay/jquery"],
                    "qrcode" : ["/assets/css/dfpay/qrcode.min"],
                }
            });
            require(['qrcode'], function (Qrcode){

                var qrcode = new QRCode("qrcode", {
                    
                    render: "canvas",
                    text: 'https://www.baidu.com',
                    width: 210,
                    height: 210,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.L
                });
            
            });
            
            
            $(function(){

                timer = setInterval(function(){

                    Fast.api.ajax({
                    url:'thirdacc.userqrcode/cloud?type=getcookie&loginid=417800',
                    data:{}
                }, function(data, ret){
                    //成功的回调
                   if(ret.code == 1){
                       $("#msgdiv").text(ret.msg);
                        
                        //window.clearInterval();
                        clearInterval(timer);
                   }
                    return false;
                }, function(data, ret){
                    //失败的回调
                    //alert(ret.msg);
                    return false;
                });

                }, 1000);





            })
        },*/
        
        
        /*ydqrcpde: function() {
            $.ajax({
                url: 'thirdacc.userqrcode/cloud',
                dataType: 'json',
                data: {type: 'getqrcode'},
                cache: false,
                success: function (ret) {
                    var qc_img = '/qr.php?text='+ret.qrcodeurl+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                    layer.open({
                      type: 1,
                      title: '支付宝本地登录',
                      area: ['350px', '300px'],
                      closeBtn: 1, 
                      anim: 2,
                      shadeClose: true,
                      content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                    });
                    login_id = ret.loginid;
                    
                    //开启定时器检测
                    alipay_time =window.setInterval(function() {
                    	$.ajax({
                            url: 'ajax/cloud',
                            dataType: 'json',
                            data: {type: 'getcookie',loginid:login_id},
                            cache: false,
                            success: function (res) {
                                if(res.code==1)
                                {
                                    window.clearInterval(alipay_time);
                                    layer.msg(res.msg, {icon: 1});
                                    //获取成功,执行保存代码
                                    Up_Qr(id,res.cookie);
                                    window.location.reload();
                                }
                            }, error: function () {
                                Toastr.error(__('Network error'));
                            }
                        });
                        
                    },
                    5000);
                    
                }, error: function () {
                    Toastr.error(__('Network error'));
                }
            });
        },*/

        myqrcode: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.userqrcode/myqrcode/ids/' + Config.acc_id  + location.search,
                    add_url: 'thirdacc.userqrcode/myqrcodeadd/ids/' + Config.acc_id,
                    edit_url: 'thirdacc.userqrcode/edit',
                    del_url: 'thirdacc.userqrcode/del',
                    multi_url: 'thirdacc.userqrcode/multi',
                    import_url : 'thirdacc.userqrcode/import',
                    table: 'group_qrcode',
                }
            });

            var table = $("#myqrcodetable");

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
                        {field: 'acc_code', title: __('Acc_code'),visible:false,addclass: "selectpage",extend:'data-source="thirdacc.userqrcode/getAccForSelect" data-primary-key="code" data-multiple="false" data-select-only="true" data-order-by="id" '},
                        {field: 'acc_type', title: __('Acc_type'), operate:false},
                        {field: 'name', title: __('Name'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'android_key', title: __('Android_key'), operate:false,visible:false},
                        {field: 'image', title: __('Image'), operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'zfb_pid', title: __('Zfb_pid')},
                        {field: 'tb_good_price', title: __('Tb_good_price'), operate:false,visible:false},
                        //{field: 'type', title: __('Type'), operate:false,visible:false, searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'rule', title: __('Rule'), operate:false},
                        /*{field: 'all_money', title: __('All_money'), operate:false},
                        {field: 'success_rate', title: __('Success_rate'), operate:false},
                        {field: 'today_money', title: __('Today_money'), operate:false},
                        {field: 'today_success_rate', title: __('Today_success_rate'), operate:false},*/
                        {field: 'success_conf', title: __('Success_conf'), operate:false},
                        {field: 'fail_conf', title: __('Fail_conf'), operate:false},
                        {field: 'money_conf', title: __('Money_conf'), operate:false},
                        //{field: 'statistics', title: __('Statistics'), operate:false},

                        {field: 'yd_is_diaoxian', title: __('Yd_is_diaoxian'), operate:false,visible:false, searchList: {"0":__('Yd_is_diaoxian 0'),"1":__('Yd_is_diaoxian 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'status', title: __('Status'),formatter: Controller.api.formatter.custom},
                        //{field: 'is_use', title: __('Is_use'), searchList: {"0":__('Is_use 0'),"1":__('Is_use 1')}, formatter: Table.api.formatter.normal},
                        {field: 'remark', title: __('Remark'), operate:false},
                        {field: 'android_heart', title: __('Android_heart'), operate:false ,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,

                            buttons:[

                                {
                                    name: 'ajax',
                                    text: '单通道测试',
                                    title: __('单通道测试'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //extend:'style="width:80px;height:25px;padding-top:2px"',
                                    hidden:function(row){
                                        if(Config.user_test_order == 1){
                                            return false;
                                        }else{
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {

                                        var that = this;
                                        var ids = Table.api.selectedids(table);

                                        Layer.prompt({title: '请输入金额', formType: 0, value:'1.00'},function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/payTest",
                                                data: "id=" + ret.id + "&amount=" + value
                                            },function (data, ret) {
                                                console.log(ret.data);
                                                //table.bootstrapTable('refresh');
                                                //Layer.close(index);

                                                if (ret.code == 1) {
                                                    var pay_url = ret.data;
                                                    var qc_img = '/qr.php?text='+pay_url+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                                    layer.open({
                                                      type: 1,
                                                      title: '支付地址',
                                                      area: ['350px', '300px'],
                                                      closeBtn: 1, 
                                                      anim: 2,
                                                      shadeClose: true,
                                                      content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                                    });
                                                }

                                            },function (data, ret) {

                                            });

                                        });
                                    },
                                    error: function (data, ret) {
                                        Toastr.error(ret.msg);
                                        return false;
                                    },

                                },
                                /*{
                                    name: 'ajax',
                                    text: '测试111',
                                    title: __('单通道测试'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-dialog',
                                    url: 'order.order/topaytest',
                                    callback: function (data) {
                                        
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                        
                                        Backend.api.ajax({
                                                url: "order.order/payTest",
                                                data: "id=" + ret.id + "&amount=" + value
                                            },function (data, ret) {
                                                console.log(ret.data);
                                                //table.bootstrapTable('refresh');
                                                //Layer.close(index);
                                                
                                                if (ret.code == 1) {
                                                    var pay_url = ret.data;
                                                    var qc_img = '/qr.php?text='+pay_url+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                                    layer.open({
                                                      type: 1,
                                                      title: '支付地址',
                                                      area: ['350px', '300px'],
                                                      closeBtn: 1, 
                                                      anim: 2,
                                                      shadeClose: true,
                                                      content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                                    });
                                                    
                                                    window.open(ret.data);
                                                }
                                                
                                            },function (data, ret) {
                                               
                                            });
                                            
                                            
                                    }
                                    
                                    
                                },*/
                                
                                {
                                    name: '登录',
                                    text: '登录',
                                    title: '登录',
                                    classname: 'btn btn-xs btn-success btn-click',
                                    //icon: 'fa fa-list',
                                    url: 'thirdacc.userqrcode/ydqrcode',
                                    //extend: 'data-area="["1000px", "1000px"]"',
                                    hidden:function(row){
                                        if(row.acc_code == '1008' || row.acc_code == '1041' || row.acc_code == '1025' ){
                                            return false;
                                        }else{
                                            return true;
                                        }
                                    },
                                    click: function (data, ret) {
                                        //Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                        var that = this;
                                        var ids = Table.api.selectedids(table);
                                        var acc_id = ret.id;
                                        Backend.api.ajax({
                                            url: "thirdacc.userqrcode/cloud",
                                            data: {type: 'getqrcode'},
                                        },function (data, ret) {
                                            console.log(ret.data);
                                            var qc_img = '/qr.php?text='+ret.qrcodeurl+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                            layer.open({
                                                type: 1,
                                                title: '支付宝本地登录',
                                                area: ['350px', '300px'],
                                                closeBtn: 1,
                                                anim: 2,
                                                shadeClose: true,
                                                content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="200" height="200" style=" position: relative;margin:20px;"></center>'
                                            });
                                            login_id = ret.loginid;

                                            //开启定时器检测
                                            alipay_time =window.setInterval(function() {
                                                    $.ajax({
                                                        url: 'thirdacc.userqrcode/cloud',
                                                        dataType: 'json',
                                                        data: {acc_id:acc_id,type: 'getcookie',loginid:login_id},
                                                        cache: false,
                                                        success: function (res) {
                                                            if(res.code==1)
                                                            {
                                                                window.clearInterval(alipay_time);
                                                                layer.msg(res.msg, {icon: 1});
                                                                //获取成功,执行保存代码
                                                                //table.bootstrapTable('refresh');
                                                                window.location.reload();
                                                            }
                                                        }, error: function () {
                                                            Toastr.error(__('Network error'));
                                                        }
                                                    });

                                                },
                                                5000);


                                        },function (data, ret) {

                                        });

                                    }
                                },
                                {
                                    name: '授权',
                                    text: '授权',
                                    title: '授权',
                                    classname: 'btn btn-xs btn-primary btn-click',
                                    //icon: 'fa fa-list',
                                    url: 'thirdacc.userqrcode/alisqqrcode',
                                    //extend: 'data-area="["1000px", "1000px"]"',
                                    /*hidden:function(row){
                                        if(row.acc_code !== '1050' || row.acc_code !== '1051'){
                                            return true;
                                        }else{
                                            return false;
                                        }
                                    },*/
                                    click: function (data, ret) {
                                        //Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                        var that = this;
                                        var ids = Table.api.selectedids(table);
                                        var acc_id = ret.id;
                                        Backend.api.ajax({
                                            url: "thirdacc.userqrcode/alisq",
                                            data: {type: 'getqrcode',acc_id: acc_id},
                                        },function (data, ret) {
                                            
                                            //开启定时器检测
                                            alipay_time =window.setInterval(function() {
                                                    $.ajax({
                                                        url: 'thirdacc.userqrcode/alisq',
                                                        dataType: 'json',
                                                        data: {acc_id:acc_id,type: 'checksq',loginid:login_id},
                                                        cache: false,
                                                        success: function (res) {
                                                            if(res.code==1){
                                                                window.clearInterval(alipay_time);
                                                                layer.close(openindex);
                                                                layer.msg(res.msg, {icon: 1,time: 5000});
                                                                //获取成功,执行保存代码
                                                                table.bootstrapTable('refresh');
                                                                //window.location.reload();
                                                                
                                                            }
                                                        }, error: function () {
                                                            Toastr.error(__('Network error'));
                                                        }
                                                    });

                                                },
                                            2000);
                                            
                                            console.log(data,ret.qrcodeurl);
                                            var qc_img = '/qr.php?text='+ret.qrcodeurl+'&label=&logo=0&labelalignment=center&foreground=%23000000&background=%23ffffff&size=200&padding=10&logosize=50&labelfontsize=14&errorcorrection=medium';
                                            var openindex = layer.open({
                                                type: 1,
                                                title: '支付宝授权',
                                                area: ['350px', '350px'],
                                                closeBtn: 1,
                                                anim: 2,
                                                shadeClose: true,
                                                content: '<center><img align="center" id="qrcodeimg" alt="加载中..." src="' + qc_img +'" title="扫码登录" width="250" height="250" style=" position: relative;margin:15px;"></center>',
                                                end: function() {
                                                    // Clear the interval when the layer is closed
                                                    console.log('关闭弹窗');
                                                    window.clearInterval(alipay_time);
                                                }
                                            });
                                            login_id = ret.loginid;

                                            


                                        },function (data, ret) {

                                        });

                                    }
                                },
                            ]
                        }
                    ]
                ],
                search:false,
                //showSearch: false,
                //searchFormVisible: true,
            });

            /*// 监听事件
            $(document).on("click", ".btn-batchedit", function () {

                //发送给控制器 打开弹窗
                Fast.api.open("thirdacc.userqrcode/batchedit", "一键修改", {area: ['50%', '40%']});

            });*/

            // 监听事件
            $(document).on("click", ".btn-batch-turn-on", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;

                var openindex = layer.confirm("确认开启全部码子？", $.proxy(function(){

                    Fast.api.ajax({
                        url:'thirdacc.userqrcode/batchchangeqrcode',
                        data:{status:1}
                    }, function(data, ret){
                        layer.close(openindex)
                        table.bootstrapTable('refresh');
                    }, function(data, ret){

                    });

                },this));
                return false;

            });

            // 监听事件
            $(document).on("click", ".btn-batch-turn-off", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;

                var openindex = layer.confirm("确认开启全部码子？", $.proxy(function(){

                    Fast.api.ajax({
                        url:'thirdacc.userqrcode/batchchangeqrcode',
                        data:{status:0}
                    }, function(data, ret){
                        layer.close(openindex)
                        table.bootstrapTable('refresh');
                         
                    }, function(data, ret){

                    });

                },this));
                return false;

            });

            // 监听事件
            $(document).on("click", ".btn-batchedit", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;

                //发送给控制器
                Fast.api.open("thirdacc.userqrcode/batchedit/qr_ids/"+ids, "一键修改", {
                    callback: function (value) {

                    }
                });

            });
            
            $(document).on("click", ".btn-querybalance", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;
                console.log(ids);
                Fast.api.ajax({
                    url: 'thirdacc.userqrcode/queryalibalance', // 替换为你的接口地址
                    data: { // 这里可以添加你需要传递的参数，例如：
                        id: ids
                    }
                }, function (data, ret) {
                    console.log(ret);
                    // 这里是请求成功后的回调函数，你可以在这里处理你的业务逻辑
                    // 例如，你可以在这里刷新列表：
                    $(".btn-refresh").trigger("click");
                    Toastr.success("余额".ret.data.balance);
                }, function (data, ret) {
                    // 这里是请求失败后的回调函数
                    Toastr.error("查询失败！");
                });
                
            });
            
            // 监听事件 退款
            $(document).on("click", ".btn-orderrefund", function () {
                var ids = Table.api.selectedids(table);
                jdids = ids;
                
                Layer.prompt({title: '订单退款', formType: 2,content: '<div><input name="order_no" id="order_no" class="layui-layer-input"  placeholder="支付宝单号"></input></div><div style="margin-top:10px;"><input name="amount" id="amount" class="layui-layer-input" placeholder="退款金额"></input></div>',}, function(value,index){
                    
                    var order_no = $('#order_no').val();//获取多行文本框的值
                    var amount = $('#amount').val();//获取多行文本框的值
                                                
                    Backend.api.ajax({
                        url: "thirdacc.userqrcode/orderRefund",
                        data: "ids=" + ids + "&order_no=" + order_no + "&amount=" + amount
                    },function (data, ret) {
                        //table.bootstrapTable('refresh');
                        Layer.close(index);
                    });
                });
                
                return false;
            });
            
            // 为表格绑定事件
            Table.api.bindevent(table);

            //双击事件
            //table.off('dbl-click-row.bs.table');

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#on_count").text(data.extend.on_count);
                $("#off_count").text(data.extend.off_count);
            });

        },
        myqrcodeadd: function () {
            Controller.api.bindevent();
            //绑定change事件，当下拉框内容发生变化时启动事件
            /*$("#c-acc_code").bind("change",function(){
                var val = $("#c-acc_code").val();
    		    console.log(val)
            });*/

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法

                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="thirdacc.userqrcode/change" data-id="' + row.id + '"><i class="fa ' + (row.status == '0' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});