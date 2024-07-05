define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thirdacc.tbshop/index' + location.search,
                    add_url: 'thirdacc.tbshop/add',
                    edit_url: 'thirdacc.tbshop/edit',
                    del_url: 'thirdacc.tbshop/del',
                    multi_url: 'thirdacc.tbshop/multi',
                    table: 'tb_shop',
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
                        {field: 'id', title: __('Id')},
                        //{field: 'user_id', title: __('User_id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'name', title: __('Name')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'is_online', title: __('Is_online'), searchList: {"0":__('Is_online 0'),"1":__('Is_online 1')}, formatter: Table.api.formatter.status},
                        {field: 'remark', title: __('Remark')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        
                        
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'ajax',
                                    text: '获取商品',
                                    title: __('获取商品'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-click',
                                    confirm: '确定获取该店铺商品吗？',

                                    click: function (data, ret) {

                                        Fast.api.ajax({
                                            url:'thirdacc.tbshop/getGoods',
                                            data:{'ids':ret.id}
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
                                },
                            ],
                        }
                    ]
                ]
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
            }
        }
    };
    return Controller;
});