var json612627 = b1decefec6b39feb3be1064e27be2a9;

$(function() {
    // 自动加载默认的运费
    // var addr_id = $('#addr_id').val();
    // if (addr_id && !json612627.onlyVerify) SelectEd('addr_id', addr_id);

    // 自动加载默认物流方式
    if (true === json612627.onlyVerify) {
        $('#selectVerify').click();
    } else {
        $('#selectDelivery').click();
    }
});

// 在微信端时，跳转至选择添加收货地址方式页面
function GetWeChatAddr() {
    window.location.href = json612627.shop_add_address;
}

// 选择配送方式
function selectLogisticsType(logisticsType) {
    if (!logisticsType) {
        layer.alert('illegal operation', {icon:0, title: false, closeBtn: 0});
        return false;
    }
    $('#logistics_type').val(logisticsType);
    // 快递配送
    if (1 === parseInt(logisticsType)) {
        $("#shop_prompt").show();
        $("#selectDelivery").addClass('on active');
        $("#selectDeliveryID").css('display', '');
        $("#selectDeliveryAddress").css('display', '');
        $("#selectVerify").removeClass('on active');
        $("#selectVerifyID").css('display', 'none');
        $("#selectVerifyInfo").css('display', 'none');
        // 运费计算逻辑
        SelectEd('addr_id', $('#addr_id').val());
    }
    // 到店自提
    else if (2 === parseInt(logisticsType)) {
        $("#shop_prompt").hide();
        var store_id = $('#store_id').val();
        if (0 < parseInt(store_id)) {
            $("#selectVerifyInfo").css('display', '');
            if (0 === parseInt(json612627.is_wap)) $("#selectVerifyID").css('display', '');
        } else {
            $("#selectVerifyID").css('display', '');
        }
        $("#selectVerify").addClass('on active');
        $("#selectDelivery").removeClass('on active');
        $("#selectDeliveryID").css('display', 'none');
        $("#selectDeliveryAddress").css('display', 'none');
        // 运费、订单总价、支付剩余余额计算
        orderFreightCountLogic(0);
    }

    // 在线支付 OR 线下支付处理
    $('.pay-type-item').each(function() {
        if ($(this).attr('data-type') == 'hdfk_payOnDelivery' && 1 === parseInt(logisticsType)) {
            $(this).show();
        } else if ($(this).attr('data-type') == 'hdfk_payOnDelivery' && 2 === parseInt(logisticsType)) {
            $(this).hide();
            if (1 == $('#payment_method').val() && 'hdfk_payOnDelivery' == $('#payment_type').val()) {
                $($('.pay-type-item')[0]).trigger("click");
            }
        }
    });
}

// 选择到店自提门店
function selectVerifyStore(confirm, obj) {
    if (confirm) {
        $('#store_id').val($(obj).data('store_id'));
        if (0 < parseInt($(obj).data('store_id'))) {
            $("#selectVerifyID").css('display', '');
            $("#selectVerifyInfo").css('display', '');
            $("#verify_store_name").html($(obj).data('store_name'));
            $("#verify_store_address").html($(obj).data('store_address'));
            $("#verify_prov_city_area").html($(obj).data('prov_city_area'));
            if (1 === parseInt(json612627.is_wap)) $("#selectVerifyID").css('display', 'none');
        } else {
            $("#verify_store_name").html('');
            $("#verify_store_address").html('');
            $("#verify_prov_city_area").html('');
            $("#selectVerifyID").css('display', '');
            $("#selectVerifyInfo").css('display', 'none');
        }
        layer.closeAll();
    } else {
        var area = ['1240px', '60%'];
        if (1 === parseInt(json612627.is_wap)) area = ['100%', '100%'];
        layer.open({
            type: 2,
            title: '选择门店',
            shadeClose: false,
            maxmin: false, //开启最大化最小化按钮
            area: area,
            content: json612627.verifyStore
        });
    }
}

// 添加收货地址
function ShopAddAddress() {
    var width  = json612627.addr_width;
    var height = json612627.addr_height;
    var url = json612627.shop_add_address;
    if (url.indexOf('?') > -1) {
        url += '&';
    } else {
        url += '?';
    }
    url += 'type=order';
    //iframe窗
    layer.open({
        type: 2,
        title: 'Add shipping address',
        shadeClose: false,
        maxmin: false, //开启最大化最小化按钮
        area: [width, height],
        content: url
    });
}

// 更新收货地址
function ShopEditAddress(addr_id) {
    var width  = json612627.addr_width;
    var height = json612627.addr_height;
    var url = json612627.shop_edit_address;
    if (url.indexOf('?') > -1) {
        url += '&';
    } else {
        url += '?';
    }
    url += 'addr_id='+addr_id;
    //iframe窗
    layer.open({
        type: 2,
        title: 'Change shipping address',
        shadeClose: false,
        maxmin: false, //开启最大化最小化按钮
        area: [width, height],
        content: url
    });
}

// 删除收货地址
function ShopDelAddress(addr_id) {
    unifiedConfirmBox('Are you sure to delete the shipping address?', '', '', function() {
        $.ajax({
            url : json612627.shop_del_address,
            data: {addr_id: addr_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    showSuccessMsg(res.msg);
                    $("#"+addr_id+'_ul_li').remove();
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

// 选中收货地址
function SelectEd(idname, addr_id, addrData) {
    if (addr_id) {
        $('#'+idname).val(addr_id);
        if (addrData && $('#addr_consignee')) {
            $('#addr_consignee').html(addrData.consignee);
            $('#addr_mobile').html(addrData.mobile);
            $('#addr_Info').html(addrData.Info);
            $('#addr_address').html(addrData.address);
        } else {
            var id = addr_id+'_ul_li';
            $('#'+id).addClass("selected");
            if ('v5' == json612627.usersTplVersion) {
                $('#UlHtml .address-item').each(function(){
                    if (id != this.id) $('#'+this.id).removeClass("selected");
                });
            } else {
                $('#UlHtml li').each(function(){
                    if (id != this.id) $('#'+this.id).removeClass("selected");
                });
            }
        }

        // 查询运费
        var url = json612627.shop_inquiry_shipping;
        $.ajax({
            url : url,
            data: {addr_id: addr_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                // 运费、订单总价、支付剩余余额计算
                orderFreightCountLogic(res.data);
            }
        });
    }
}

// 运费、订单总价、支付剩余余额计算
function orderFreightCountLogic(freight) {
    // 运费
    $('#shipping_money').html(0 === parseFloat(freight) ? 0 : freight);
    $('#template_money').html(0 === parseFloat(freight) ? 'Free Shipping' : '$' + freight);
    
    // 计算总价+运费
    var amountNew = (Number(json612627.totalAmountOld) + Number(freight)).toFixed(2);
    $('#TotalAmount, #PayTotalAmountID').html(parseFloat(amountNew));

    // 计算支付后剩余余额
    var usersMoney = (Number(json612627.UsersMoney) - Number(amountNew)).toFixed(2);
    $('#UsersSurplusMoneyID').html(parseFloat(usersMoney));
}

// 提交订单
function ShopPaymentPage() {
    layer_loading('<font id="loading_tips_230111">Processing</font>');
    var timer = setTimeout(function() {
        $.ajax({
            url : json612627.shop_payment_page,
            data: $('#theForm').serialize(),
            type: 'post',
            dataType: 'json',
            success: function(res) {
                clearTimeout(timer); // 清理定时任务
                if (1 == res.code) {
                    if (res.data.code && 'order_status_0' == res.data.code) { // 兼容第二套会员中心
                        SelectPayMethod_2(res.data.pay_id, res.data.pay_mark, res.data.unified_id, res.data.unified_number, res.data.transaction_type);
                    } else {
                        if (res.data.email) SendEmail_1608628263(res.data.email);
                        if (res.data.mobile) SendMobile_1608628263(res.data.mobile);
                        window.location.href = res.url;
                    }
                } else {
                    layer.closeAll();
                    if (1 == res.data.add_addr) {
                        ShopAddAddress();
                    } else if (res.data.url) { // 兼容第二套会员中心
                        layer.msg(res.msg, {icon: 5,time: 1500}, function(){
                            window.location.href = res.data.url;
                        });
                    } else {
                        layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
                    }
                }
            }
        });
    }, 100);
}

// 邮箱发送
function SendEmail_1608628263(result) {
    var ResultID = 1;
    if (result) {
        $.ajax({
            url: result.url,
            data: result.data,
            type:'post',
            dataType:'json'
        });
    }
    return ResultID;
}
 
// 手机发送
function SendMobile_1608628263(result) {
    var ResultID = 1;
    if (result) {
        $.ajax({
            url: result.url,
            data: result.data,
            type:'post',
            dataType:'json'
        });
    }
    return ResultID;
}

function goAddressList(obj) {
    var url = $(obj).data('url');
    if (url.indexOf('?') > -1) {
        url += '&';
    } else {
        url += '?';
    }
    url += 'gourl='+encodeURIComponent(window.location.href);
    window.location.href = url;
}