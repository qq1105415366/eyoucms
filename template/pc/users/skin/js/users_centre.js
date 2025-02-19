// 上传头像
function uploadHeadPicFile(obj) {
    var file = $(obj)[0].files[0];
    if (!file) return false;
    var formData = new FormData();
    var max_file_size = 2 * 1024 * 1024;
    formData.append('file', file);
    formData.append('compress', '250-250');
    formData.append('max_file_size', max_file_size);
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url : eyou_basefile + "?m=user&c=Uploadify&a=imageUp&_ajax=1",
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (res) {
            layer.closeAll();
            if (res.state == 'SUCCESS') {
                showSuccessMsg(ey_foreign_system10);
                $('#uploadHeadPicDdit').val(1);
                $('#uploadHeadPic').val(res.url);
                $('#uploadHeadPicImg').attr('src', res.url);
                // $.ajax({
                //     type: 'post',
                //     url : eyou_basefile + "?m=user&c=Users&a=edit_users_head_pic&_ajax=1",
                //     data: {filename: res.url},
                //     dataType: 'json',
                //     success: function(result) {
                //         layer.closeAll();
                //         if (1 === parseInt(result.code)) {
                //             showSuccessMsg(result.msg);
                //             $('#uploadHeadPicImg').attr('src', result.data.head_pic);
                //         } else {
                //             showErrorAlert(result.msg);
                //         }
                //     },
                //     error: function(e) {
                //         layer.closeAll();
                //         showErrorAlert(e.responseText);
                //     }
                // });
            } else {
                showErrorAlert(res.state);
            }
        },
        error: function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    })
}
