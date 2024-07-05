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
                        {field: 'user.nickname', title: __('User.username')},

                        {field: 'acc_code', title: __('Acc_code'),visible:false,addclass: "selectpage",extend:'data-source="thirdacc.agentacc/getAccForSelect" data-primary-key="code" data-multiple="false" data-select-only="true" data-order-by="id" '},
                        {field: 'acc_type', title: __('Acc_type'), operate:false},
                        {field: 'name', title: __('Name'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'user.money', title: __('User.money')},
                        {field: 'android_key', title: __('Android_key'), operate:false,visible:false},
                        {field: 'image', title: __('Image'), operate:false,visible:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'money', title: __('Money'), operate:false},

                        {field: 'zfb_pid', title: __('Zfb_pid'), operate:false,visible:false},
                        {field: 'type', title: __('Type'), operate:false,visible:false, searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'rule', title: __('Rule')},
                        //{field: 'today_money', title: __('Today_money'), operate:false},
                        //{field: 'today_order', title: __('Today_order'), operate:false},
                        
                        {field: 'success_conf', title: __('Success_conf'), operate:false},
                        {field: 'fail_conf', title: __('Fail_conf'), operate:false},
                        {field: 'money_conf', title: __('Money_conf'), operate:false},
                        
                        {field: 'statistics', title: __('Statistics'), operate:false,visible:false},

                        {field: 'yd_is_diaoxian', title: __('Yd_is_diaoxian'), operate:false, visible:false,searchList: {"0":__('Yd_is_diaoxian 0'),"1":__('Yd_is_diaoxian 1')}, formatter: Table.api.formatter.normal},

                        {field: 'status', title: __('Status'),formatter: Controller.api.formatter.custom},
                        {field: 'android_heart', title: __('Android_heart'), operate:false, visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
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
                                                    //window.open(ret.data);
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

                            ]
                        }
                    ]
                ],
                search:false,
                //showSearch: false,
                //searchFormVisible: true,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                //这里我们手动设置底部的值
                $("#on_count").text(data.extend.on_count);
                $("#off_count").text(data.extend.off_count);
                
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
                    onNumP.textContent = '开启: ' + acc_list[i].on_num;
            
                    // 创建关闭数量元素
                    var offNumP = document.createElement('p');
                    offNumP.className = 'no-margins';
                    offNumP.textContent = '关闭: ' + acc_list[i].off_num;
            
                    // 将元素添加到内层容器中
                    panelBodyDiv.appendChild(nameP);
                    panelBodyDiv.appendChild(onNumP);
                    panelBodyDiv.appendChild(offNumP);
            
                    // 将内层容器添加到外层容器中
                    colDiv.appendChild(panelBodyDiv);
            
                    // 将外层容器添加到容器元素中
                    container.appendChild(colDiv);
                }*/
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

                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="thirdacc.userqrcode/change" data-id="' + row.id + '"><i class="fa ' + (row.status == '0' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});