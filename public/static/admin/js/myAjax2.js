/**
 * Created by admin on 2015/9/21.
 */

/**
 *  Ajax通用提交表单
 *  @var  form表单的id属性值  form_id
 *  @var  提交地址 subbmit_url
 */

function post_form(form_id,subbmit_url){
    if(form_id == '' && subbmit_url == ''){
        alert('参数有误');
        return false;
    }
    //  序列化表单值
    var data = $('#'+form_id).serialize();

    $.post(subbmit_url,data,function(result){
        var obj = $.parseJSON(result);
        if(obj.status == 0){
            alert(obj.msg);
            return false;
        }
        if(obj.status == 1){
            alert(obj.msg);
            if(obj.data.return_url){
                //  返回跳转链接
                location.href = obj.data.return_url;
            }else{
                //  刷新页面
                location.reload();
            }
            return;
        }
    })
}


/**
 * 删除
 * @returns {void}
 */
function del_fun(del_url)
{
    if(confirm("此操作不可恢复，确认删除？"))
        location.href = del_url;
}  


// 修改指定表的指定字段值 包括有按钮点击切换是否 或者 排序 或者输入框文字
function changeTableVal(table,id_name,id_value,field,obj)
{
    var src = "";
    if($(obj).hasClass('no')) // 图片点击是否操作
    {
        //src = '/public/images/yes.png';
        var text = "<i class='fa fa-check-circle'></i>是";
        if ($(obj).attr('data-yestext')) {
            text = $(obj).attr('data-yestext');
        }
        var value = 1;
        try {  
            if ($(obj).attr('data-value')) {
                value = $(obj).attr('data-value');
                if ('weapp' == table && 'status' == field) {
                    $(obj).attr('data-value', -1); // 插件的禁用
                    if ('Diyminipro' == $(obj).attr('data-weapp_code')) {
                        $('#Diyminipro_theme_index', window.parent.document).show();
                    }
                }
            }
        } catch(e) {  
            // 出现异常以后执行的代码  
            // e:exception，用来捕获异常的信息  
        } 
            
    }else if($(obj).hasClass('yes')){ // 图片点击是否操作
        var text = "<i class='fa fa-ban'></i>否";
        if ($(obj).attr('data-notext')) {
            text = $(obj).attr('data-notext');
        }
        var value = 0;
        try {  
            if ($(obj).attr('data-value')) {
                value = $(obj).attr('data-value');
                if ('weapp' == table && 'status' == field) {
                    $(obj).attr('data-value', 1); // 插件的启用
                    if ('Diyminipro' == $(obj).attr('data-weapp_code')) {
                        $('#Diyminipro_theme_index', window.parent.document).hide();
                    }
                }
            }
        } catch(e) {  
            // 出现异常以后执行的代码  
            // e:exception，用来捕获异常的信息  
        } 
    }else{ // 其他输入框操作
        var value = $(obj).val();
    }

    if (parseInt($(obj).attr('data-value')) === 0) {
        $(obj).attr('data-value', 1);
    } else if (parseInt($(obj).attr('data-value')) === 1) {
        $(obj).attr('data-value', 0);
    }

    var url = eyou_basefile + "?m="+module_name+"&c=Index&a=changeTableVal&_ajax=1";
    var lang = $.cookie('admin_lang');
    if (!lang) lang = __lang__;
    if ($.trim(lang) != '') {
        url = url + '&lang=' + lang;
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: {table:table,id_name:id_name,id_value:id_value,field:field,value:value},
        dataType: 'json',
        success: function(res){
            if (res.code == 1) {
                var inputype = $(obj).attr('data-inputype');
                if ('int' == inputype) {
                    $(obj).val(parseInt($(obj).val()));
                }
                if ($(obj).hasClass('no')) {
                    $(obj).removeClass('no').addClass('yes');
                    $(obj).html(text);
                }else if($(obj).hasClass('yes')) {
                    $(obj).removeClass('yes').addClass('no');
                    $(obj).html(text);
                }
                var seo_pseudo = $(obj).attr('data-seo_pseudo');
                if(table == 'archives' && 2 == seo_pseudo){
                    /*生成静态页面代码*/
                    layer_loading('生成页面');
                    var typeid = $(obj).attr('data-typeid');
                    $.ajax({
                        url:__root_dir__+"/index.php?m=home&c=Buildhtml&a=upHtml&lang="+__lang__+"&id="+id_value+"&t_id="+typeid+"&type=view&ctl_name=Archives&_ajax=1",
                        type:'GET',
                        dataType:'json',
                        data:{},
                        success:function(res1){
                            $.ajax({
                                url:__root_dir__+"/index.php?m=home&c=Buildhtml&a=upHtml&lang="+__lang__+"&id="+id_value+"&t_id="+typeid+"&type=lists&ctl_name=Archives&_ajax=1",
                                type:'GET',
                                dataType:'json',
                                data:{},
                                success:function(res2){
                                    layer.closeAll();
                                    layer.msg('生成完成', {icon: 1, time: 1500});
                                },
                                error: function(e){
                                    layer.closeAll();
                                    layer.alert('生成当前栏目HTML失败，请手工生成栏目静态！', {icon: 5, title: false});
                                }
                            });
                        },
                        error: function(e){
                            layer.closeAll();
                            layer.alert('生成HTML失败，请手工生成静态HTML！', {icon: 5, title: false});
                        }
                    });
                    /*end*/
                } else {
                    uphtml_index_20221207();
                    if(!$(obj).hasClass('no') && !$(obj).hasClass('yes')){
                        var time = 1500;
                        if (res.data.time && 0 < res.data.time) {
                            time = res.data.time;
                        }
                        layer.msg(res.msg, {icon: 1, time: time}, function(){
                            if (1 == res.data.refresh) {
                                window.location.reload();
                            }
                        });
                    } else {
                        if (1 == res.data.refresh) {
                            window.location.reload();
                        }
                    }
                }
            } else {
                var time = parseFloat(res.wait) * 1000;
                layer.msg(res.msg, {icon: 5, time: time}, function(){
                    window.location.reload();
                });  
            }
        }
    }); 
}

// 保存后，生成首页
function uphtml_index_20221207()
{
    $.ajax({
        url:__root_dir__+"/index.php?clear=1",
        type:'GET',
        dataType:'html',
        data:{},
        success:function(res){
            console.log('生成首页html')
        }
    });
}

// 修改指定表的指定字段值 包括有按钮点击切换是否 或者 排序 或者输入框文字
function ProductStatus(table,id_name,id_value,field,obj)
{   
    var src = "";
    if($(obj).hasClass('no')) // 图片点击是否操作
    {          
        //src = '/public/images/yes.png';
        $(obj).removeClass('no').addClass('yes');
        $(obj).html("<i class='fa fa-check-circle'></i>启用");
        var value = 1;
        try {  
            if ($(obj).attr('data-value')) {
                value = $(obj).attr('data-value');
                if ('weapp' == table && 'status' == field) {
                    $(obj).attr('data-value', -1); // 插件的禁用
                }
            }
        } catch(e) {  
            // 出现异常以后执行的代码  
            // e:exception，用来捕获异常的信息  
        } 
            
    }else if($(obj).hasClass('yes')){ // 图片点击是否操作                     
        $(obj).removeClass('yes').addClass('no');
        $(obj).html("<i class='fa fa-ban'></i>禁用");
        var value = 0;
        try {  
            if ($(obj).attr('data-value')) {
                value = $(obj).attr('data-value');
                $(obj).attr('data-value', 1); // 插件的启用
            }
        } catch(e) {  
            // 出现异常以后执行的代码  
            // e:exception，用来捕获异常的信息  
        } 
    }else{ // 其他输入框操作
        var value = $(obj).val();            
    }

    var url = eyou_basefile + "?m="+module_name+"&c=Index&a=changeTableVal&_ajax=1";
    var lang = $.cookie('admin_lang');
    if (!lang) lang = __lang__;
    if ($.trim(lang) != '') {
        url = url + '&lang=' + lang;
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: {table:table,id_name:id_name,id_value:id_value,field:field,value:value},
        dataType: 'json',
        success: function(res){
            if (res.code == 1) {
                var seo_pseudo = $(obj).attr('data-seo_pseudo');
                if(table == 'archives' && 2 == seo_pseudo){
                    /*生成静态页面代码*/
                    layer_loading('生成页面');
                    var typeid = $(obj).attr('data-typeid');
                    $.ajax({
                        url:__root_dir__+"/index.php?m=home&c=Buildhtml&a=upHtml&lang="+__lang__+"&id="+id_value+"&t_id="+typeid+"&type=view&ctl_name=Archives&_ajax=1",
                        type:'GET',
                        dataType:'json',
                        data:{},
                        success:function(res1){
                            $.ajax({
                                url:__root_dir__+"/index.php?m=home&c=Buildhtml&a=upHtml&lang="+__lang__+"&id="+id_value+"&t_id="+typeid+"&type=lists&ctl_name=Archives&_ajax=1",
                                type:'GET',
                                dataType:'json',
                                data:{},
                                success:function(res2){
                                    layer.closeAll();
                                    layer.msg('生成完成', {icon: 1, time: 1500});
                                },
                                error: function(e){
                                    layer.closeAll();
                                    layer.alert('生成当前栏目HTML失败，请手工生成栏目静态！', {icon: 5, title: false});
                                }
                            });
                        },
                        error: function(e){
                            layer.closeAll();
                            layer.alert('生成HTML失败，请手工生成静态HTML！', {icon: 5, title: false});
                        }
                    });
                    /*end*/
                } else {
                    if(!$(obj).hasClass('no') && !$(obj).hasClass('yes')){
                        layer.msg(res.msg, {icon: 1});
                    }
                    if (1 == res.data.refresh) {
                        window.location.reload();
                    }
                }
            } else {
                var time = parseFloat(res.wait) * 1000;
                layer.msg(res.msg, {icon: 2, time: time}, function(){
                    if (res.data && res.data.refresh && 1 == res.data.refresh) {
                        window.location.reload();
                    }
                });  
            }
        }
    }); 
}