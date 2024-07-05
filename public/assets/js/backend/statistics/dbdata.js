define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'statistics.dbdata/index' + location.search,
                    /*add_url: 'statistics.userstatistics/add',
                    edit_url: 'statistics.userstatistics/edit',*/
                    del_url: 'statistics.dbdata/del',
                    /*multi_url: 'statistics.userstatistics/multi',*/
                    table: 'user',
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
                        {field: 'id', title: __('Id'),operate:false},
                        {field: 'name', title: '名称',operate:false},
                        {field: 'createtime', title: '时间',visible: false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},

                    ]
                ],
                earch:false,
                search:false,
                showSearch: false,
                searchFormVisible: true,
                showToggle: false,
                showColumns: false,
                showExport: false,
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.off('dbl-click-row.bs.table');

            $(document).on("click", ".btn-delData", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                //var ids = Table.api.selectedids(table);
                //Table.api.multi("changestatus", ids.join(","), table, this);

                var that = this;
                var ids = Table.api.selectedids(table);

                /*Backend.api.ajax({
                    url: "statistics.dbdata/delData",
                    data:{'id':ids,'time':$('#createtime').val()}
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
                });*/

                Layer.confirm(
                    '确认清楚选中的数据吗?', {
                        icon: 3,
                        title: __('Warning'),
                        offset: '40%',
                        shadeClose: true
                    },
                    function (index) {
                        Backend.api.ajax({
                            url: "statistics.dbdata/delData",
                            data: {
                                ids: ids,
                                time:$('#createtime').val(),
                            }
                        }, function (data, ret) {
                            //成功的回调
                            if (ret.code === 1) {

                                table.bootstrapTable('refresh');
                                Layer.close(index);
                            } else {
                                Layer.close(index);
                                Toastr.error(ret.msg);
                            }
                        }, function (data, ret) { //失败的回调
                            console.log(ret);
                            // Toastr.error(ret.msg);
                            Layer.close(index);
                        });
                    }
                );


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
    };
    return Controller;
});