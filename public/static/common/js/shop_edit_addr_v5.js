// 获取联动地址
function GetRegionData(t,type){
    var parent_id = $(t).val();
    if(!parent_id > 0){
        return false ;
    }
    
    var url = $('#GetRegionDataS').val();
    $.ajax({
        url: url,
        data: {parent_id:parent_id,_ajax:1},
        type:'post',
        dataType:'json',
        success:function(res){
            if ('province' == type) {
                res = '<option value="0">Please select a city</option>'+ res;
                $('#city').empty().html(res);
                $('#district').empty().html('<option value="0">Please select county/district/town</option>');
            } else if ('city' == type) {
                res = '<option value="0">Please select county/district/town</option>'+ res;
                $('#district').empty().html(res);
            }
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5});
        }
    });
}

// 更新收货地址
function EditAddress(){
    var parentObj = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
    
    var url   = $('#ShopEditAddress').val();
    if (url.indexOf('?') > -1) {
        url += '&';
    } else {
        url += '?';
    }
    url += '_ajax=1';
    
    $.ajax({
        url: url,
        data: $('#theForm').serialize(),
        type:'post',
        dataType:'json',
        success:function(res){
            if(res.code == 1){
                parent.layer.close(parentObj);
                EditHtml(res.data);
                parent.layer.msg(res.msg, {time: 1000});
            }else{
                layer.closeAll();
                layer.msg(res.msg, {icon: 5});
            }
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5});
        }
    });
}

// 删除收货地址
function DelAddress(addr_id, obj) {
    unifiedConfirmBox('Are you sure to delete the shipping address?', '380px;', '200px;', function() {
        var parentObj = parent.layer.getFrameIndex(window.name);
        $.ajax({
            url : $('#DelAddress').val(),
            data: {addr_id: addr_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    var _parent = parent;
                    _parent.layer.close(parentObj);
                    _parent.$('#UlHtml').find("#"+addr_id+'_ul_li').remove();
                    _parent.showSuccessMsg(res.msg);
                } else {
                    showErrorMsg(res.msg);
                }
            },
            error: function (e) {
                layer.closeAll();
                showErrorAlert(e.responseText);
            }
        });
    });
}

// 更新收货地址html
function EditHtml(data) {   
    // 获取修改后的值
    var consignee = data.consignee;
    var mobile    = data.mobile;
    var info      = data.country+' '+data.province+' '+data.city+' '+data.district;
    var address   = data.address;
    // 赋值到相应的ID下
    parent.$('#'+data.addr_id+'_consignee').html(consignee);
    parent.$('#'+data.addr_id+'_mobile').html(mobile);
    parent.$('#'+data.addr_id+'_info').html(info);
    parent.$('#'+data.addr_id+'_address').html(address);

    // 设置为默认地址 -- 兼容第二套会员中心
    if (1 == data.is_default) {
        parent.$('#UlHtml').find('.defaultTxt_1610180641').attr('data-is_default', 0).hide();
        var currentObj = parent.$('#UlHtml').find('#'+data.addr_id+'_ul_li');
        currentObj.find('.defaultTxt_1610180641').attr('data-is_default', 1).show();
    }
}