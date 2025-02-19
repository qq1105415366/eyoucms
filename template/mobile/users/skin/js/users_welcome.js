$(function() {
    // 如果拓展应用中没有应用则隐藏，有应用则显示
    if (0 === parseInt($('#eyExpandTpl').find('a').length)) {
        $('#eyExpandTpl').hide();
    } else {
        $('#eyExpandTpl').show();
    }
});

// 会员签到
function userSignin(obj) {
    showLayerLoad();
    $.ajax({
        url : $(obj).attr('data-url'),
        url : eyou_basefile + "?m=api&c=Ajax&a=signin_save",
        data: {_ajax: 1},
        type: "POST",
        dataType: 'json',
        success: function (res) {
            layer.closeAll();
            if (res.code == 1) {
                showLayerMsg(res.msg + " +" +res.data.score + res.data.score_name);
                $('#usersScores').html(res.data.scores);
                $(obj).removeAttr('onclick').find('#user_signin').html('已签到');
            } else {
                showLayerAlert(res.msg);
            }
        },
        error: function(e) {
            layer.closeAll();
            showLayerAlert(e.responseText);
        }
    });
}