// 头像框移入移出事件
$(".user-photo").mouseover(function(){
   $(".user-drop").show();
});
$(".user-photo").mouseout(function(){
   $(".user-drop").hide();
});

// 收货地址更多
if ($('#UlHtml div.address-item').length > 4) {
    $('#UlHtml #addressShowHide').show();
} else {
    $('#UlHtml #addressShowHide').hide();
}
$('#addressShowHide').click(function() {
    var showhide = $(this).attr('data-showhide');
    if ('hide' == showhide) {
        $('#UlHtml div.address-item').each(function(index, item) {
            if (index > 3) $(item).show();
        });
        $(this).attr('data-showhide', 'show');
        $(this).find('span').html('收起更多地址<i class="iconfont-users2 icon-users2-arrow-top"></i>');
    } else {
        $('#UlHtml div.address-item').each(function(index, item) {
            if (index > 3) $(item).hide();
        });
        $(this).attr('data-showhide', 'hide');
        $(this).find('span').html('显示更多地址<i class="iconfont-users2 icon-users2-arrow-down"></i>');
    }
});

// 自动加载默认paypal支付的form
$(function() {
    // PC端默认支付方式(商品下单支付页)
    if ($('.pay-type-item')[0]) $($('.pay-type-item')[0]).trigger("click");
});

// 支付方式选择
function selectPayType(obj) {
    var type = $(obj).data('type');
    // 重置支付方式
    $('#payment_type').val(type);
    // 余额支付后剩余显示
    $('#yezf_balance_tips').hide();
    if ('yezf_balance' == type) $('#yezf_balance_tips').show();
    // 在线支付or线下支付
    $('#payment_method').val(0);
    if ('hdfk_payOnDelivery' == type) $('#payment_method').val(1);
    // 重置选中标记
    $('.pay-type-item').removeClass('active');
    $(obj).addClass('active');
    // 订单再次支付页
    $('#PayID').val($(obj).data('id'));
    $('#PayMark').val($(obj).data('mark'));
}