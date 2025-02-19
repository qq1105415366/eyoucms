$(function () {
    $("input[name=mobile]").keyup(function(event){
        var mobile = $(this).val();
        if (11 == mobile.length && 32 <= event.keyCode) {
            $('#vertify_div').css('display','block');
        }
    });
    $("input[name=mobile_vertify]").keyup(function(event){
        var mobile_vertify = $(this).val();
        if (4 <= mobile_vertify.length && 32 <= event.keyCode) {
            send_mobile_code();
        }
    });

})
function send_mobile_code() {

    var mobile = $("input[name=mobile]").val();
    // 手机号是否为空
    if (!mobile) {
        $("input[name=mobile]").focus();
        layer.msg(ey_foreign_users28, {time: 1500});
        return false;
    }

    // 手机格式不正确
    var reg = /^1[0-9]{10}$/i;
    if (!reg.test(mobile)) {
        $("input[name=mobile]").focus();
        layer.msg('请输入正确的手机号码！', {time: 1500});
        return false;
    }
    $('#vertify_div').css('display','block');
    var mobile_vertify = $("input[name=mobile_vertify]").val();
    if (!mobile_vertify) {
        return false;
    }

    // 设置为不可点击
    $("#mobile_code_button").val('获取中…').attr('disabled', 'disabled');
    var __mobile_1_token__ = $('input[name=__mobile_1_token__]').val();

    $.ajax({
        url: __root_dir__+'/index.php?m=api&c=Ajax&a=SendMobileCode&_ajax=1',
        // source:2 登录   source:0  注册
        data: {type:'users_mobile_reg', mobile:mobile, is_mobile:true, title:'账号注册', source:0,IsVertify:1, vertify:mobile_vertify, __mobile_1_token__:__mobile_1_token__},
        type:'post',
        dataType:'json',
        success:function(res){
            if (res.code == 1) {
                code_countdown();
                layer.msg(res.msg, {time: 1500});
            } else {
                $("#mobile_code_button").val('获取验证码').removeAttr("disabled");
                layer.alert(res.msg, {icon: 2, title: false, closeBtn: 0});
            }
        },
        error : function() {
            $("#mobile_code_button").val('获取验证码').removeAttr("disabled");
            layer.alert('发送失败，请尝试重新发送！', {icon: 5, title: false, closeBtn: 0});
        }
    });
}
function mobile_fleshVerify(){
    var src =  __root_dir__+'/index.php?m=api&c=Ajax&a=vertify&type=users_mobile_reg';
    if (src.indexOf('?') > -1) {
        src += '&';
    } else {
        src += '?';
    }
    src += 'r='+ Math.floor(Math.random()*100);
    $('#mobile_imgVerifys').attr('src', src);

    $.ajax({
        async:false,
        url: __root_dir__+'/index.php?m=api&c=Ajax&a=get_token&name=__mobile_1_token__',
        data: {_ajax:1},
        type:'GET',
        dataType:'html',
        success:function(res1){
            $('input[name=__mobile_1_token__]').val(res1);
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5, title:false});
        }
    });
}

function code_countdown(){
    // 倒计时
    var setTime;
    var time = 120;
    setTime = setInterval(function() {
        if(0 >= time) {
            clearInterval(setTime);
            return false;
        }

        time--;
        $("#mobile_code_button").val(time + '秒').attr('disabled', 'disabled');
        if(time == 0) $("#mobile_code_button").val('获取验证码').removeAttr("disabled");
    }, 1000);
}
function checkMobileUser1649732103() {
    var mobile = $('#theMobileForm input[name=mobile]');
    var mobile_code = $('#theMobileForm input[name=mobile_code]');

    if (mobile.val() == '') {
        layer.msg(ey_foreign_users28, {
            time: 1500
        });
        mobile.focus();
        return false;
    }

    if (mobile_code.val() == '') {
        layer.msg(ey_foreign_users30, {
            time: 1500
        });
        mobile_code.focus();
        return false;
    }

    layer_loading('正在处理');
    $.ajax({
        url:  __root_dir__+'/index.php?m=user&c=Users&a=mobile_reg',
        data: $('#theMobileForm').serialize(),
        type: 'post',
        dataType: 'json',
        success: function(response) {
            layer.closeAll();
            var res = response.data;
            if (res.status == 0) {
                layer.msg(response.msg, {
                    time: 1500
                }, function() {
                    window.location = response.url;
                });
            } else if ('vertify' == res.status) {
                mobile_fleshVerify();
                layer.msg(response.msg, {
                    time: 2000
                });
            } else if (res.status == 1) {
                layer.msg(response.msg, {
                    time: 2000
                });
            } else if (res.status == 2) {
                layer.msg(response.msg, {
                    time: 1500
                }, function() {
                    window.location = response.url;
                });
            } else if (res.status == 3) {
                layer.msg(response.msg, {
                    time: 1500
                }, function() {
                    window.location = response.url;
                });
            } else {
                mobile_fleshVerify();
                layer.msg(response.msg, {
                    time: 2000
                });
            }
        },
        error: function(e) {
            layer.closeAll();
            mobile_fleshVerify();
            showErrorAlert(e.responseText);
        }
    });
};