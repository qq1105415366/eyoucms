
function showErrorMsg(msg){
    layer.msg(msg, {icon: 5,time: 2000});
}
function showErrorAlert(msg, icon){
    if (!icon && icon != 0) {
        icon = 5;
    }
    layer.alert(msg, {icon: icon, title: false, closeBtn: false});
}

/*
 * 上传图片 后台专用
 * @access  public
 * @null int 一次上传图片张图
 * @elementid string 上传成功后返回路径插入指定ID元素内
 * @path  string 指定上传保存文件夹,默认存在public/upload/temp/目录
 * @callback string  回调函数(单张图片返回保存路径字符串，多张则为路径数组 )
 */
var layer_GetUploadify;
// PC端上传头像
function GetUploadify(num,elementid,path,callback,url,title)
{
    if (layer_GetUploadify){
        layer.close(layer_GetUploadify);
    }
    if (num > 0) {
        if (!title) {
            if (callback.indexOf('head_pic_call_back') > -1) {
                title = '上传头像';
            } else {
                title = '选择上传';
            }
        }

        if (!url) {
            url = GetUploadify_url;
        }
        
        if (url.indexOf('?') > -1) {
            url += '&';
        } else {
            url += '?';
        }

        var upurl = url+'num='+num+'&input='+elementid+'&path='+path+'&func='+callback;
        layer_GetUploadify = layer.open({
            type: 2,
            title: title,
            shadeClose: false,
            shade: 0.3,
            maxmin: true, //开启最大化最小化按钮
            area: ['50%', '60%'],
            content: upurl
         });
    } else {
        showErrorAlert('允许上传0张图片');
        return false;
    }
}

// 手机端上传头像
function GetUploadify_mobile(num,url)
{
    var scriptUrl = '/public/plugins/layer_mobile/layer.js';
    // 支持子目录
    if (typeof __root_dir__ != "undefined") {
        scriptUrl = __root_dir__ + scriptUrl;
    }
    if (typeof __version__ != "undefined") {
        scriptUrl = scriptUrl + '?v=' + __version__;
    }
    // end
    $.getScript(scriptUrl, function(){
        if (num > 0) {
            if (!url) {
                url = GetUploadify_url;
            }
            
            if (url.indexOf('?') > -1) {
                url += '&';
            } else {
                url += '?';
            }

            var content = $('#update_mobile_file').html();
            content = content.replace(/up_f/g, 'upfile');
            content = content.replace(/form1/g,'form2'); 
            layer_GetUploadify = layer.open({
                type:1,
                title:'头像',
                anim:'up',
                style:'position:fixed; bottom:0; left:0; width: 100%; padding:10px 0; border:none;max-width: 100%;',
                content:content,
             });
        } else {
            layer.open({
                content: '允许上传0张图片',
                skin: 'footer',
            });
            return false;
        }
    });
}

// 加载层
function layer_loading(msg){
    if (!msg || '正在处理' == msg) {
        if (typeof ey_foreign_system8 === 'undefined') {
            ey_foreign_system8 = "正在处理";
        }
        msg = ey_foreign_system8;
    }
    if (typeof ey_foreign_system9 === 'undefined') {
        ey_foreign_system9 = "请勿刷新页面";
    }
    var loading = layer.msg(
    msg+'...&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'+ey_foreign_system9, 
    {
        icon: 1,
        time: 3600000, //1小时后后自动关闭
        shade: [0.2] //0.1透明度的白色背景
    });
    //loading层
    var index = layer.load(3, {
        shade: [0.1,'#fff'] //0.1透明度的白色背景
    });

    return loading;
}

// 加载层
function layer_loading_mini(icon){
    //loading层
    if (!icon) {
        icon = 2;
    }
    var loading = layer.load(icon, {
        shade: [0.2,'#000'] //0.1透明度的白色背景
    });

    return loading;
}

// 上传头像
function upload_head_pic(e){
    var file = $(e)[0].files[0];
    if (!file) {
        return false;
    }
    var formData = new FormData();
    formData.append('file',file);
    var max_file_size = $(e).attr('data-max_file_size') * 1024 * 1024;
    formData.append('max_file_size', max_file_size);
    formData.append('_ajax',1);
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url: eyou_basefile + "?m=user&c=Uploadify&a=imageUp",
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (res) {
            if (res.state == 'SUCCESS') {
                $.ajax({
                    url: eyou_basefile + "?m=user&c=Users&a=edit_users_head_pic",
                    data: {filename:res.url, _ajax:1},
                    type:'post',
                    dataType:'json',
                    success:function(res2){
                        layer.closeAll();
                        if (1 == res2.code) {
                            $('#ey_head_pic_a').attr('src', res2.data.head_pic);
                        } else {
                            showErrorAlert(res2.msg);
                        }
                    },
                    error : function(e) {
                        layer.closeAll();
                        showErrorAlert(e.responseText);
                    }
                });
            } else {
                layer.closeAll();
                showErrorAlert(res.state);
            }
        },
        error : function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    })
}


// 单图上传  2021.01.05
function upload_single_pic_1609837252(e,input_id){
    var file = $(e)[0].files[0];
    if (!file) {
        return false;
    }
    var formData = new FormData();
    formData.append('file',file);
    formData.append('_ajax',1);
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url: eyou_basefile + "?m=user&c=Uploadify&a=imageUp",
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (res) {
            if (res.state == 'SUCCESS') {
                $("#single_pic_input_"+input_id).val(res.url)
                $(".img1_"+input_id).attr('src',res.url);
                layer.closeAll();
            } else {
                layer.closeAll();
                showErrorAlert(res.state);
            }
        },
        error : function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    })
}