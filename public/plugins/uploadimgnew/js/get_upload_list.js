
var axupimgs = new Array();
var arrimg = new Array();
var arrimgname = new Array();
$(function() {
    // 左侧分组里的图片数量
    $('#count_'+type_id, window.parent.document).html(countimg);
    var img_id_upload = $.cookie("img_id_upload");
    if (undefined != img_id_upload && img_id_upload.length > 0) {
        arrimg = img_id_upload.split(",");
    }
    if (arrimg.length == 0) {
        var select_img = $.cookie('uploadimgnew_select_img');
        if (undefined != select_img && select_img) {
            arrimg.push(select_img);
            $.cookie("img_id_upload", arrimg.join());
        }
    }
    var imgname_id_upload = $.cookie("imgname_id_upload");
    if (undefined != imgname_id_upload && imgname_id_upload.length > 0){
        arrimgname = imgname_id_upload.split(",");
    }
    // 检测是否选择
    if (num > 1) {
        $("#file_list li").each(function(index, item) {
            $(item).removeClass('up-over');
            var val = $(item).attr("data-img");
            for (var i = arrimg.length - 1; i >= 0; i--) {
                if (arrimg[i].indexOf(val) != -1 || val.indexOf(arrimg[i]) != -1) {
                    $(item).addClass('up-over');
                }
            }
        });
        // 选中两张图片或以上才显示删除选中按钮
        /*$('.removeall').html('删除选中('+arrimg.length+')');
        if (arrimg.length > 1) {
            $('.removeall').show();
        } else {
            $('.removeall').hide();
        }*/
    } else {
        $("#file_list li").each(function(index, item) {
            $(item).removeClass('up-over');
            var val = $(item).attr("data-img");
            for (var i = arrimg.length - 1; i >= 0; i--) {
                if (arrimg[i].indexOf(val) != -1 || val.indexOf(arrimg[i]) != -1) {
                    $(item).addClass('up-over');
                    break;
                }
            }
        });
    }
});

// 删除列表图片
function delimg(obj, img_id) {
    layer_loading('正在处理');
    var img_id_arr = [img_id];
    $.ajax({
        type: 'post',
        url: eyou_basefile + "?m="+module_name+"&c=Uploadimgnew&a=del_uploadsimg&lang=" + __lang__,
        data: {img_id:img_id_arr, _ajax:1},
        dataType: 'json',
        success: function (res) {
            if (res.code == 1) {
                layer.msg(res.msg, {icon: 6, time: 1000}, function() {
                    window.location.reload();
                });
            } else {
                layer.closeAll();
                layer.msg(res.msg, {icon: 5});
            }
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5, title: false, closeBtn: false});
        }
    });
}

// 删除选中的图片记录
function batch_delimg(obj) {
    var img_id_arr = [];
    $('#file_list li').each(function(i,o){
        if($(o).hasClass('up-over')){
            img_id_arr.push($(o).attr('data-id'));
        }
    })
    if(img_id_arr.length == 0){
        layer.msg('请至少选择一张！', {icon: 5});
        return;
    }

    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url : eyou_basefile + "?m="+module_name+"&c=Uploadimgnew&a=del_uploadsimg&lang=" + __lang__,
        data: {img_id: img_id_arr, _ajax: 1},
        dataType: 'json',
        success: function (res) {
            if (res.code == 1) {
                $.cookie("img_id_upload", "");
                $.cookie("imgname_id_upload", "");
                layer.msg(res.msg, {icon: 6, time: 1000}, function() {
                    window.location.reload();
                });
            } else {
                layer.closeAll();
                layer.msg(res.msg, {icon: 5});
            }
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5, title: false, closeBtn: false});
        }
    });
}

function getLastMonth(){
    var now=new Date();
    var year = now.getFullYear();//getYear()+1900=getFullYear()
    var month = now.getMonth() +1;//0-11表示1-12月
    var day = now.getDate();
    var dateObj = {};
    if(parseInt(month)<10){
        month = "0"+month;
    }
    if(parseInt(day)<10){
        day = "0"+day;
    }

    dateObj.now = year + '-'+ month + '-' + day;

    if (parseInt(month) ==1) {//如果是1月份，则取上一年的12月份
        dateObj.last = (parseInt(year) - 1) + '-12-' + day;
        return dateObj;
    }

    var  preSize= new Date(year, parseInt(month)-1, 0).getDate();//上月总天数
    if (preSize < parseInt(day)) {//上月总天数<本月日期，比如3月的30日，在2月中没有30
        dateObj.last = year + '-' + month + '-01';
        return dateObj;
    }

    if(parseInt(month) <=10){
        dateObj.last = year + '-0' + (parseInt(month)-1) + '-' + day;
        return dateObj;
    }else{
        dateObj.last = year + '-' + (parseInt(month)-1) + '-' + day;
        return dateObj;
    }
}

// layui 操作
layui.use(function() {
    var layer = layui.layer,
    form = layui.form,
    $ = layui.$,
    element = layui.element,
    laydate = layui.laydate;
    var timeObj = getLastMonth();

    // 日期时间范围
    var laydate_value = '';
    if (eytime == '') {
        laydate_value = timeObj.last + ' - ' + timeObj.now;
    }
    laydate.render({
        elem: '#eytime',
        type: 'date',
        range: true,
        value: laydate_value,
        calendar: true,
        max: timeObj.now,//默认最大值为当前日期
        done: function(value) {
            $('#eytime').val(value);
            $('#searchForm').submit();
        }
    });
    if (eytime == '') {
        lay('#eytime').val('');
    }

    // 点击选中保存图片
    $(document).on("click", ".image-list li .picbox", function() {
        var li = $(this).parent('.image-list li');
        var val = li.attr("data-img");
        var title =  li.attr("data-title");
        var img_id = li.attr("data-id");
        var selectlayer = li.hasClass('up-over');
        if (selectlayer) {
            li.removeClass('up-over');
            var indx = arrimg.indexOf(val); 
            if(indx != -1) arrimg.splice(indx, 1);
            var indx = arrimgname.indexOf(title);
            if(indx != -1) arrimgname.splice(indx, 1);
        }

        if (num > 1) {
            if (!selectlayer) {
                li.addClass('up-over');
                arrimg.push(val);
                arrimgname.push(title);
            }
        } else {
            $.cookie("img_id_upload", "");
            $.cookie("imgname_id_upload", "");
            $("#file_list li").removeClass('up-over');
            if (!selectlayer) {
                li.addClass('up-over');
                arrimg = [];
                arrimg.push(val);
                arrimgname = [];
                arrimgname.push(title);
            }
        }
        $.cookie("img_id_upload", arrimg.join());
        $.cookie("imgname_id_upload", arrimgname.join());
        // 选中两张图片或以上才显示删除选中按钮
        /*document.querySelector('.removeall').innerText = '删除选中('+arrimg.length+')';
        if (arrimg.length > 1) {
            $('.removeall').show();
        } else {
            $('.removeall').hide();
        }*/
    });
});

//全选图片/全部取消选择
function choose_all(obj){
    var checked_val = $(obj).is(':checked');
    $("#file_list li").each(function(index, item) {
        $(item).removeClass('up-over');
        var val = $(item).attr("data-img");
        var title = $(item).attr("data-title");
        if (checked_val) {
            $(item).addClass('up-over');
            // if (arrimg[i].indexOf(val) != -1 || val.indexOf(arrimg[i]) != -1) {
            if ($.inArray(val, arrimg) < 0) {
                arrimg.push(val);
                arrimgname.push(title);
                $.cookie("img_id_upload", arrimg.join());
                $.cookie("imgname_id_upload", arrimgname.join());
            }
        }else{
            $.cookie("img_id_upload", "");
            $.cookie("imgname_id_upload", "");
        }
    });
}

//移动
function moving(){
    var length = $("#file_list .up-over").length;
    if (0 == length) {
        layer.msg('请先至少选择一张图片', {icon: 5,time: 2000});
        return false;
    }
    var img_id_arr = [];
    $("#file_list .up-over").each(function(index, item) {
        var up_id = $(item).attr("data-id");
        img_id_arr.push(up_id);
    });
    //获取分类
    $.ajax({
        type : 'post',
        url : UploadTypeListUrl,
        data : {_ajax:1},
        dataType : 'json',
        success : function(res){
            layer.closeAll();
            if(res.code == 1){
                var html = "<div>选择目录: <select class='ey-select' id='upload_type_select'><option value='0'>默认分组</option>";
                res.data.forEach(function( val, index ) {
                    var selected = "";
                    if (type_id == val.id) {
                        selected = " selected ";
                    }
                    html += "<option value='"+val.id+"' "+selected+">"+val.upload_type+"</option> ";

                });
                html += "</select></div>";
                layer.confirm(html, {
                    // shade: layer_shade,
                    area: ['480px', '220px'],
                    move: false,
                    title: '移动',
                    btnAlign:'r',
                    closeBtn: 1,
                    btn: ['确定', '取消'] ,//按钮
                    success: function () {
                        $(".layui-layer-content").css('text-align', 'left');
                    }
                }, function (index, layero) {
                    var type_id = $("#upload_type_select").val();
                    $.ajax({
                        type : 'post',
                        url : UpdateTypeIdUrl,
                        data : {_ajax:1,type_id:type_id,img_id:img_id_arr},
                        dataType : 'json',
                        success : function(res){
                            if(res.code == 1){
                                layer.msg(res.msg, {time: 1000}, function(){
                                    $.cookie("img_id_upload", "");
                                    $.cookie("imgname_id_upload", "");
                                    $("#file_list li").removeClass('up-over');
                                    parent.window.location.reload();
                                });
                            }else{
                                layer.closeAll();
                                layer.msg(res.msg, {icon: 5,time: 2000});
                            }
                        },
                        error: function(e){
                            layer.closeAll();
                            layer.alert(e.responseText, {icon: 5, title: false, closeBtn: false});
                        }
                    });
                }, function (index) {
                    layer.close(index);
                });
            }else{
                layer.msg(res.msg, {icon: 5,time: 2000});
            }
        },
        error: function(e){
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5, title: false, closeBtn: false});
        }
    });
}

// 添加文件
document.querySelector('#topbar .addfile').addEventListener("click", function(){
    var input = document.createElement('input');
    input.setAttribute('type', 'file');
    if (upload_num > 1) {
        input.setAttribute('multiple', 'multiple');
    }
    input.setAttribute('accept', image_accept);
    input.setAttribute('onchange', "addfileChange(this);");
    input.click();
});

function addfileChange(obj)
{
    var files = obj.files;
    if (files.length > upload_num) {
        layer.alert('每次最多可上传'+upload_num+'张！', {icon: 5, title: false, closeBtn: false});
        return false;
    }
    if (document.querySelector('.addfiletext').innerText != '上传图片') return false;
    // addList(files);
    if ($('#file_list li').length == 0) {
        $('#file_list').html('');
    }
    for (let i = 0; i < files.length; i++) {
        axupimgs.push(files[i]);
    }
    if (axupimgs.length > 0) {
        layer_loading('正在上传');
        $('#file_list li.up-no').each(function(item) {
            var el = item.get(0);
            el.classList ? el.classList.add('up-now') : el.className+=' up-now';
        });
        document.querySelector('.addfiletext').innerText = '上传中...';
        upAllFiles(0);
    }
}

// 添加列表
function addList(files) {
    var files_sum = files.length;
    var vDom = document.createDocumentFragment();
    for (let i = 0; i < files_sum; i++) {
        let file = files[i];
        let blobUrl = window.URL.createObjectURL(file);
        axupimgs.unshift(file);

        var add_time = formatDate();
        let li = document.createElement('li');
        li.setAttribute('class','up-no');
        li.setAttribute('data-time',file.lastModified);
        li.setAttribute('data-img',blobUrl);
        li.innerHTML='<div class="picbox"><img src="'+blobUrl+'"><div class="image-select-layer"><i class="layui-icon layui-icon-ok-circle"></i></div></div><div class="namebox" style="height: 15px;"><span class="eyou_imgtime">'+file.name+'</span></div>';
        vDom.appendChild(li);
    }
    if ($('#file_list li').length == 0) {
        $('#file_list').html('');
    }
    var list = document.querySelector('#file_list');
    list.insertBefore(vDom, list.childNodes[0]);
}

// 当前的日期时间格式
function formatDate() {
    var date = new Date();
    var YY = date.getFullYear() + '-';
    var MM = (date.getMonth() + 1 < 10 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1) + '-';
    var DD = (date.getDate() < 10 ? '0' + (date.getDate()) : date.getDate());
    var hh = (date.getHours() < 10 ? '0' + date.getHours() : date.getHours()) + ':';
    var mm = (date.getMinutes() < 10 ? '0' + date.getMinutes() : date.getMinutes()) + ':';
    var ss = (date.getSeconds() < 10 ? '0' + date.getSeconds() : date.getSeconds());
    return YY + MM + DD +" "+hh + mm + ss;
}

// 图片上传
var file_i = 0;
function upAllFiles(n) {
    var len = axupimgs.length;
    file_i = n;
    if (len == n) {
        file_i = 0;
        // layer_loading('正在上传');
        document.querySelector('#topbar .addfiletext').innerText = '上传图片';
        return true;
    }

    // 上传的图片数量
    var img_len = file_i + 1;
    if (axupimgs[n] != '') {
        if (n > upload_num - 1) {
            layer.alert('最多一次性上传'+upload_num+'张！', {icon: 5, title: false, closeBtn: false});
            return false;
        }
        var img_id_upload_tmp = $.cookie("img_id_upload");
        if (undefined != img_id_upload_tmp && img_id_upload_tmp.length > 0) {
            arrimg = img_id_upload_tmp.split(",");
        } else {
            arrimg = [];
        }
        var imgname_id_upload_tmp = $.cookie("imgname_id_upload");
        if (undefined != imgname_id_upload_tmp && imgname_id_upload_tmp.length > 0) {
            arrimgname =  imgname_id_upload_tmp.split(",");
        } else {
            arrimgname = [];
        }
        var form = new FormData();
        var file = axupimgs[n];
        form.append('_ajax', 1);
        form.append('file', file);
        form.append('type_id', type_id);
        $.ajax({
            type: 'post',
            url : UploadUpUrl,
            data: form,
            contentType: false,
            processData: false,
            dataType: 'json',
            // async: false,
            success: function (res) {
                if (res.state == 'SUCCESS') {
                    var class_up_over = ' up-over ';
                    if (num == 1) {
                        $.cookie("img_id_upload", "");
                        arrimg = [];
                        $.cookie("imgname_id_upload", "");
                        arrimgname = [];

                        if (n == len - 1) {
                            class_up_over = ' up-over ';
                        } else {
                            class_up_over = '';
                        }
                    }
                    arrimg.push(res.url);
                    arrimgname.push(file.name);
                    $.cookie("img_id_upload", arrimg.join());
                    $.cookie("imgname_id_upload", arrimgname.join());

                    var html = '';
                    html += '<li class="up-no '+class_up_over+'" data-time="'+file.lastModified+'" data-img="'+res.url+'" data-title="'+file.name+'">';
                    html += '   <div class="picbox">';
                    html += '       <img src="'+res.url+'">';
                    html += '       <div class="image-select-layer"><i class="layui-icon layui-icon-ok-circle"></i></div>';
                    html += '   </div>';
                    html += '   <div class="namebox" style="height: 15px;"><span class="eyou_imgtime">'+file.name+'</span></div>';
                    html += '</li>';
                    $('#file_list').prepend(html);

                    if (img_len == len) {
                        layer.msg('上传成功', {icon: 6, time: 1000, shade: [0.2]}, function() {
                            window.location.reload();
                        });
                    }
                } else {
                    layer.closeAll();
                    layer.alert(res.state, {icon: 5, title: false, closeBtn: false});
                }
                n++;
                upAllFiles(n);
            },
            error : function(e) {
                $('#file_list li.up-now').each(function(item) {
                    var el = item.get(0);
                    el.setAttribute('class','up-no');
                });
                layer.closeAll();
                layer.alert(e.responseText, {icon: 5, title: false, closeBtn: false});
            }
        })
    }
}

// 加载框
function layer_loading(msg) {
    var loading = layer.msg(
    msg+'...&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;请勿刷新页面', 
    {
        icon: 6,
        time: 3600000, //1小时后后自动关闭
        shade: [0.2] //0.1透明度的白色背景
    });
    //loading层
    var index = layer.load(3, {
        shade: [0.1,'#fff'] //0.1透明度的白色背景
    });
    return loading;
}