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
                        {field: 'user.username', title: __('User.username')},
                        {field: 'acc_code', title: __('Acc_code'),visible:false,addclass: "selectpage",extend:'data-source="thirdacc.acc/index" data-primary-key="code" data-multiple="false" data-select-only="true" data-order-by="id" '},
                        {field: 'acc_type', title: __('Acc_type'), operate:false},
                        {field: 'name', title: __('Name'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'android_key', title: __('Android_key'), operate:false,visible:false},
                        {field: 'image', title: __('Image'), operate:false,visible:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'zfb_pid', title: __('Zfb_pid')},
                        {field: 'tb_good_price', title: __('Tb_good_price'), operate:false,visible:false},

                        //{field: 'type', title: __('Type'), operate:false,visible:false, searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'rule', title: __('Rule')},
                        //{field: 'today_money', title: __('Today_money'), operate:false},
                        //{field: 'success_rate', title: __('Success_rate'), operate:false},
                        {field: 'statistics', title: __('Statistics'), operate:false},

                        {field: 'yd_is_diaoxian', title: __('Yd_is_diaoxian'), operate:false,visible:false, searchList: {"0":__('Yd_is_diaoxian 0'),"1":__('Yd_is_diaoxian 1')}, formatter: Table.api.formatter.normal},

                        //{field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'status', title: __('Status'),formatter: Controller.api.formatter.custom},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE %...%', placeholder: '模糊搜索'},
                        {field: 'is_use', title: __('Is_use'), operate:false,visible:false, searchList: {"0":__('Is_use 0'),"1":__('Is_use 1')}, formatter: Table.api.formatter.normal},
                        {field: 'android_heart', title: __('Android_heart'), operate:false,visible:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},


                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons:[
                                /*{
                                    name: 'ajax',
                                    text: '单通道测试',
                                    title: __('单通道测试'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    //icon: 'fa fa-close',
                                    //extend:'style="width:80px;height:25px;padding-top:2px"',
                                    click: function (data, ret) {

                                        var that = this;
                                        var ids = Table.api.selectedids(table);

                                        Layer.prompt({title: '请输入金额', formType: 0, value:'100.00'},function (value, index) {
                                            Backend.api.ajax({
                                                url: "order.order/payTest",
                                                data: "id=" + ret.id + "&amount=" + value
                                            },function (data, ret) {
                                                console.log(ret.data);
                                                //table.bootstrapTable('refresh');
                                                //Layer.close(index);
                                                
                                                if (ret.code == 1) {
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

                                },*/
                            ]
                        }
                    ]
                ],
                search:false,//快速搜索
                showSearch: true,//显示搜索
                searchFormVisible: false,//通用搜索
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