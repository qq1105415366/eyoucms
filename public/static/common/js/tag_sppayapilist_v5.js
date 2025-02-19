var PayPolling;
var json627847 = eyou_data_json_v627847;
var unified_id = json627847.unified_id;
var unified_number = json627847.unified_number;
var transaction_type = json627847.transaction_type;

// 自动加载默认paypal支付的form
$(function() {
    // 加载PayPal
    $('body').append(json627847.submitForm);

    // PC端默认支付方式(商品下单支付页)
    if ($('.pay-type-item')[0]) $($('.pay-type-item')[0]).trigger("click");
    // 移动端默认支付方式(商品下单支付页)
    if ($('.phpSelectPayRadio')[0]) $($('.phpSelectPayRadio')[0]).trigger("click");
});

// 支付方式选择
function selectPayType(obj) {
    var type = $(obj).data('type');
    // 重置支付方式
    $('#payment_type').val(type);
    // 余额支付后剩余显示
    $('#yezf_balance_tips').hide();
    if ('yezf_balance' == type) $('#yezf_balance_tips').show();
    // 余额支付后剩余显示
    $('#yezf_scores_tips').hide();
    if ('yezf_scores' == type) $('#yezf_scores_tips').show();
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

// 商品购买、余额充值调用
function SelectPayMethod(pay_id, pay_mark) {
    if (!pay_id || !pay_mark || !unified_id || !unified_number || !transaction_type) {
        if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
            showLayerMsg('Order payment parameters are missing, refresh and retry', 2, function() {
                window.location.reload();
            });
            return false;
        } else {
            layer.msg('Order payment parameters are missing, refresh and retry', {time: 1500}, function() {
                window.location.reload();
            });
            return false;
        }
    }
    var a_alipay_url = "";
    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
        showLayerLoad();
    } else {
        layer_loading('Processing');
    }
    $.ajax({
        url: json627847.SelectPayMethod,
        async: false,
        data: {
            pay_id: pay_id,
            pay_mark: pay_mark,
            unified_id: unified_id,
            unified_number: unified_number,
            transaction_type: transaction_type
        },
        type:'post',
        dataType:'json',
        success:function(res) {
            layer.closeAll();
            if (1 == res.code) {
                $('#PayID').val(pay_id);
                $('#PayMark').val(pay_mark);
                if (res.data.appId) {
                    callpay(res.data);
                } else if (res.data.is_applets && 1 == res.data.is_applets) {
                    WeChatInternal(res.data);
                } else if (res.data.url_qrcode && 0 == json627847.IsMobile) {
                    AlertPayImg(res.data);
                } else if (res.data.url && 1 == json627847.IsMobile) {
                    a_alipay_url = res.data.url;
                    // window.open(res.data.url);
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else if (1 == res.data.is_paypal) {
                    if (res.data.item_name && res.data.amount && res.data.invoice) {
                        $('#eyou_paypalForm, #eyou_itemName').val(res.data.item_name);
                        $('#eyou_paypalForm, #eyou_amount').val(res.data.amount);
                        $('#eyou_paypalForm, #eyou_invoice').val(res.data.invoice);
                        if (res.data.eyou_cancel_return) $('#eyou_paypalForm, #eyou_cancel_return').val(res.data.eyou_cancel_return);
                        $('#eyou_paypalForm, #eyou_submitForm').click();
                    }
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else if ('unionPay' == res.data.pay_mark) {
                    $('body').append(res.data.htmlForm);
                    $('#eyou_UnionPayForm, #eyou_UnionPaySubmit').click();
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else {
                    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                        showLayerLoad();
                    } else {
                        layer_loading('Paymenting');
                    }
                    if (1 === parseInt(json627847.IsMobile)) {
                        window.location.href = res.url;
                    } else {
                        a_alipay_url = res.url;
                        // window.open(res.url);
                    }
                    PayPolling = window.setInterval(OrderPayPolling, 2000);
                }
            } else {
                if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                    showLayerAlert(res.msg);
                } else {
                    layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
                }
            }
        }
    });

    if (a_alipay_url != "") {
        /*if ('alipay' == pay_mark) {
            // 打开支付提示确认框
            unifiedShowPayConfirmBox(json627847.is_wap, false, function() {
                OrderPayPolling('showMsgCode');
            });
        }*/
        newWinarticlepay2(a_alipay_url);
    }
    return false;
}

// 装载显示扫码支付的二维码
function AlertPayImg(data) {
    var html = "<img src='"+data.url_qrcode+"' style='width: 250px; height: 250px;'><br/><span style='color: red; display: inline-block; width: 100%; text-align: center;'>Paymenting...Do not refresh</span>";
    layer.alert(html, {
        title: false,
        btn: [],
        success: function() {
            PayPolling = window.setInterval(function(){ OrderPayPolling(); }, 2000);
        },
        cancel: function() {
            window.clearInterval(PayPolling);
            var submit_order_type = $('#submit_order_type').val();
            if (undefined != submit_order_type && '0' === submit_order_type) {
                if (b1decefec6b39feb3be1064e27be2a9.shop_centre_url) {
                    window.location.href = b1decefec6b39feb3be1064e27be2a9.shop_centre_url;
                } else {
                    window.location.reload();
                }
            }
        }
    });
}

// 订单轮询
function OrderPayPolling(showMsgCode) {
    var pay_id = $('#PayID').val();
    var pay_mark = $('#PayMark').val();
    var pay_type = $('#PayType').val();
    if (!pay_id || !pay_mark || !unified_id || !unified_number || !transaction_type) {
        if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
            showLayerMsg('Order query parameters are missing, refresh and retry', 2, function() {
                window.location.reload();
            });
            return false;
        } else {
            layer.msg('Order query parameters are missing, refresh and retry', {time: 1500}, function() {
                window.location.reload();
            });
            return false;
        }
    }
    $.ajax({
        url: json627847.OrderPayPolling,
        data: {
            pay_id: pay_id,
            pay_mark: pay_mark,
            pay_type: pay_type,
            unified_id: unified_id,
            unified_number: unified_number,
            transaction_type: transaction_type
        },
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (1 == res.code) {
                if (res.data) {
                    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                        showLayerLoad();
                    } else {
                        layer_loading('Processing');
                    }
                    window.clearInterval(PayPolling);
                    if (2 == transaction_type) {
                        if (!res.data.mobile && !res.data.email) {
                            layer.closeAll();
                            layer.msg(res.msg, {time: 1500}, function() {
                                window.location.href = res.url;
                            });
                        }
                        if (res.data.mobile) SendMobile(res.data.mobile);
                        if (res.data.email) SendEmail(res.data.email);
                    }
                    layer.closeAll();
                    layer.msg(res.msg, {time: 1500}, function() {
                        window.location.href = res.url;
                    });
                } else {
                    /*if (showMsgCode && res.msg) {
                        $('#' + showMsgCode).show().html(res.msg);
                        setTimeout(function() {
                            $('#' + showMsgCode).hide().html('');
                        }, 3000);
                    }*/
                }
            } else if (0 == res.code) {
                layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
            }
        }
    });
}

// 发送短信
function SendMobile(result) {
    if (result) {
        $.ajax({
            async: false,
            url: result.url,
            data: result.data,
            type:'post',
            dataType:'json'
        });
    }
}

// 发送邮件
function SendEmail(result) {
    if (result) {
        $.ajax({
            async: false,
            url: result.url,
            data: result.data,
            type:'post',
            dataType:'json'
        });
    }
}

// 微信内部支付时，先进行数据判断
function callpay(data) {
    if (typeof WeixinJSBridge == "undefined") {
        if ( document.addEventListener ) {
            document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
        } else if (document.attachEvent) {
            document.attachEvent('WeixinJSBridgeReady', jsApiCall);
            document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
        }
    } else {
        jsApiCall(data);
    }
}

// 调用微信JS api 支付
function jsApiCall(data) {
    WeixinJSBridge.invoke(
        'getBrandWCPayRequest', data,
        function(res) {
            if (res.err_msg == "get_brand_wcpay_request:ok") {
                layer.msg('WeChat payment completed!', {time: 1000}, function() {
                    OrderPayPolling();
                });
            } else if (res.err_msg == "get_brand_wcpay_request:cancel") {
                layer.alert('User cancels payment!', {icon:0, title: false, closeBtn: 0});
            } else {
                layer.alert('Payment failed!', {icon:0, title: false, closeBtn: 0});
            }
        }
    );
}

function pay_deal_with() {
    $.ajax({
        async: false,
        url: json627847.PayDealWith,
        data: {unified_number: unified_number, transaction_type: transaction_type},
        type:'post',
        dataType:'json',
        success:function(res){
            if (1 == res.data.status) {
                if (!res.data.mobile && !res.data.email) {
                    layer.msg(res.msg, {time: 1000}, function() {
                        window.location.href = res.url;
                    });
                }
                if (res.data.mobile) SendMobile(res.data.mobile);
                if (res.data.email) SendEmail(res.data.email);
                layer.msg(res.msg, {time: 1000}, function() {
                    window.location.href = res.url;
                });
            }
        }
    });
}

/*-------------会员升级调用---------开始----------*/
// 会员升级调用
function UsersUpgradePay(obj) {
    // 禁用支付按钮
    $(obj).prop("disabled", true).css("pointer-events", "none");
    var a_alipay_url = "";
    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
        showLayerLoad();
    } else {
        layer_loading('正在处理');
    }
    $.ajax({
        async: false,
        url: json627847.UsersUpgradePay,
        data: $('#theForm').serialize(),
        type:'POST',
        dataType:'json',
        success:function(res) {
            layer.closeAll();
            $(obj).prop("disabled", false).css("pointer-events", "");
            if (1 == res.code) {
                if (0 == res.msg.ReturnCode) {
                    // 余额支付逻辑
                    if (0 == res.msg.ReturnPay) {
                        // 余额不足支付
                        IsRecharge(res.msg);
                    } else {
                        // 支付完成
                        layer.msg(res.msg.ReturnMsg, {time: 1500}, function(){
                            window.location.href = res.msg.ReturnUrl;
                        });
                    }
                }
                else if ('unionPay' == res.data.pay_mark) {
                    $('body').append(res.data.htmlForm);
                    $('#eyou_UnionPayForm, #eyou_UnionPaySubmit').click();
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                }
                else if (1 == res.msg.ReturnCode) {
                    // 微信支付逻辑
                    if (0 == res.msg.ReturnPay) {
                        // // 加载订单号到隐藏域
                        $('#PayID').val(res.data.pay_id);
                        $('#PayMark').val(res.data.pay_mark);
                        $('#UnifiedNumber').val(res.msg.ReturnOrder);
                        unified_id = res.data.unified_id;
                        unified_number = res.data.unified_number;
                        transaction_type = res.data.transaction_type;
                        if (res.data.PayData.appId) {
                            // 手机端微信内支付
                            callpay(res.data.PayData);
                        } else if (res.data.is_applets && 1 == res.data.is_applets) {
                            // 微信小程序内支付
                            $('#unified_id').val(unified_id);
                            $('#unified_number').val(unified_number);
                            $('#transaction_type').val(transaction_type);
                            WeChatInternal(res.data);
                        } else if (res.msg.url_qrcode) {
                            // PC端浏览器扫码支付
                            AlertPayImg(res.msg);
                        } else {
                            // 手机端浏览器支付
                            if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                                showLayerLoad();
                            } else {
                                layer_loading('Paymenting');
                            }
                            if (1 == json627847.IsMobile) {
                                window.location.href = res.url;
                            } else {
                                a_alipay_url = res.url;
                                // window.open(res.url);
                            }
                            PayPolling = window.setInterval(OrderPayPolling, 2000);
                        }
                    } else {
                        // 支付完成
                        layer.msg(res.msg.ReturnMsg, {time: 1500}, function(){
                            window.location.href = res.msg.ReturnUrl;
                        });
                    }
                }
                else if (2 == res.msg.ReturnCode) {
                    // 支付宝支付逻辑
                    if (0 == res.msg.ReturnPay) {
                        $('#PayID').val(res.msg.pay_id);
                        $('#PayMark').val(res.msg.pay_mark);
                        $('#UnifiedNumber').val(res.msg.ReturnOrder);
                        unified_id = res.msg.ReturnOrderID;
                        unified_number = res.msg.ReturnOrder;
                        transaction_type = 3;
                        if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                            showLayerLoad();
                        } else {
                            layer_loading('Paymenting');
                        }
                        if (1 == json627847.IsMobile) {
                            window.location.href = res.msg.ReturnUrl;
                        } else {
                            a_alipay_url = res.msg.ReturnUrl;
                            // window.open(res.msg.ReturnUrl);
                        }
                        PayPolling = window.setInterval(OrderPayPolling, 2000);
                    }
                }
                else {
                    $('#PayID').val(res.data.pay_id);
                    $('#PayMark').val(res.data.pay_mark);
                    $('#PayType').val(res.data.pay_type);
                    $('#UnifiedNumber').val(res.data.unified_number);
                    unified_id = res.data.unified_id;
                    unified_number = res.data.unified_number;
                    transaction_type = 3;
                    if (res.data.url_qrcode && 0 == json627847.IsMobile) {
                        AlertPayImg(res.data);
                    } else if (res.data.url && 1 == json627847.IsMobile) {
                        a_alipay_url = res.data.url;
                        // window.open(res.data.url);
                        PayPolling = window.setInterval(OrderPayPolling, 1000);
                    } else {
                        if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                            showLayerLoad();
                        } else {
                            layer_loading('Paymenting');
                        }
                        if (1 == json627847.IsMobile) {
                            window.location.href = res.url;
                        } else {
                            a_alipay_url = res.url;
                            // window.open(res.url);
                        }
                        PayPolling = window.setInterval(OrderPayPolling, 2000);
                    }
                }
            } else {
                layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
            }
        }
    });

    if (a_alipay_url != "") {
        /*if ('alipay' == res.data.pay_mark) {
            // 打开支付提示确认框
            unifiedShowPayConfirmBox(json627847.is_wap, function() {
                OrderPayPolling('showMsgCode');
            });
        }*/
        newWinarticlepay2(a_alipay_url);
    }
    return false;
}

// 是否要去充值
function IsRecharge(data) {
    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
        showConfirmBox(data.ReturnMsg, ['Go recharge', 'Other payment methods'], function() {
            window.location.href = data.ReturnUrl;
        }, function(index) {
            // 选择其他方式支付时，恢复禁用的余额支付按钮
            $('#Pay').prop("disabled", false).css("pointer-events", "");
            layer.closeAll(index);
        });
    } else {
        layer.confirm(data.ReturnMsg, {
            title: false,
            closeBtn: 0,
            btn: ['Go recharge', 'Other payment methods']
        }, function() {
            // 去充值
            window.location.href = data.ReturnUrl;
        }, function(index) {
            // 选择其他方式支付时，恢复禁用的余额支付按钮
            $('#Pay').prop("disabled", false).css("pointer-events", "");
            layer.closeAll(index);
        });
    }
}
/*-------------会员升级调用---------结束----------*/

// 弹框支付 开始
function SelectPayMethodLayer(pay_id, pay_mark) {
    var parentObj = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
    var _parent = parent;

    if (!pay_id || !pay_mark || !unified_id || !unified_number || !transaction_type) {
        _parent.layer.close(parentObj);
        _parent.layer.msg('Order exception 003, refresh and retry', {time: 1500}, function(){
            window.location.reload();
        });
    }

    layer_loading('Processing');
    $.ajax({
        async: false,
        url: json627847.SelectPayMethod,
        data: {
            pay_id: pay_id,
            pay_mark: pay_mark,
            unified_id: unified_id,
            unified_number: unified_number,
            transaction_type: transaction_type
        },
        type: 'post',
        dataType: 'json',
        success: function(res) {
            layer.closeAll();
            _parent.layer.close(parentObj);
            var pollingData = {
                pay_id: pay_id,
                pay_mark: pay_mark,
                unified_id: unified_id,
                unified_number: unified_number,
                transaction_type: transaction_type,
                OrderPayPolling: json627847.OrderPayPolling,
            };
            if (1 == res.code) {
                $('#PayID').val(pay_id);
                $('#PayMark').val(pay_mark);
                if (res.data.appId) {
                    callpay(res.data);
                } else if (res.data.url_qrcode) {
                    AlertPayImgLayer(res.data, pollingData);
                } else if (1 == res.data.is_paypal) {
                    if (res.data.item_name && res.data.amount && res.data.invoice) {
                        $('#eyou_paypalForm, #eyou_itemName').val(res.data.item_name);
                        $('#eyou_paypalForm, #eyou_amount').val(res.data.amount);
                        $('#eyou_paypalForm, #eyou_invoice').val(res.data.invoice);
                        if (res.data.eyou_cancel_return) $('#eyou_paypalForm, #eyou_cancel_return').val(res.data.eyou_cancel_return);
                        $('#eyou_paypalForm, #eyou_submitForm').click();
                    }
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else if ('unionPay' == res.data.pay_mark) {
                    $('body').append(res.data.htmlForm);
                    $('#eyou_UnionPayForm, #eyou_UnionPaySubmit').click();
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else {
                    if (1 == json627847.IsMobile) {
                        _parent.window.location.href = res.url;
                    } else {
                        // 打开支付提示确认框
                        if (0 === parseInt(json627847.is_wap)) {
                            _parent.layer.closeAll();
                            _parent.layer.confirm('Please make payment on the newly opened page!', {
                                move: false,
                                closeBtn: 3,
                                title: ey_foreign_system4,
                                btnAlign: 'r',
                                shade: layer_shade,
                                area: ['480px;', '200px;'],
                                btn: ['pay success', 'Payment failed'],
                                success: function () {
                                    _parent.$(".layui-layer-content").css('text-align', 'left');
                                },
                                cancel: function() {
                                    _parent.window.location.reload();
                                }
                            }, function () {
                                // 确认操作
                                _parent.OrderPayPolling(JSON.stringify(pollingData), 'showMsgCode');
                            }, function (index) {
                                // 取消操作
                                _parent.window.location.reload();
                            });
                        }
                        _parent.window.open(res.url);
                    }
                    pollingData = JSON.stringify(pollingData);
                    _parent.PayPolling = _parent.setInterval("parent.OrderPayPolling('"+pollingData+"');", 3000);
                }
            } else {
                _parent.layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
            }
        }
    });
}

// 装载显示扫码支付的二维码
function AlertPayImgLayer(data,pollingData) {
    var _parent = parent;
    var html = "<img src='"+data.url_qrcode+"' style='width: 250px; height: 250px;'><br/><span style='color: red; display: inline-block; width: 100%; text-align: center;'>Paymenting...Do not refresh</span>";
    _parent.layer.alert(html, {
        title: false,
        btn: [],
        success: function() {
            pollingData = JSON.stringify(pollingData);
            _parent.PayPolling = _parent.setInterval("parent.OrderPayPolling('"+pollingData+"');", 3000);
        },
        cancel: function() {
            _parent.clearInterval(_parent.PayPolling);
        }
    });
}
// 弹框支付 结束

// 商品购买、余额充值调用
function SelectPayMethod_2(pay_id, pay_mark, unifiedId, unifiedNumber, transactionType) {
    if (!pay_id || !pay_mark || !unifiedId || !unifiedNumber || !transactionType) {
        if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
            showLayerMsg('Order payment parameters are missing, refresh and retry', 2, function() {
                window.location.reload();
            });
            return false;
        } else {
            layer.msg('Order payment parameters are missing, refresh and retry', {time: 1500}, function() {
                window.location.reload();
            });
            return false;
        }
    }

    unified_id = json627847.unified_id = unifiedId;
    unified_number = json627847.unified_number = unifiedNumber;
    transaction_type = json627847.transaction_type = transactionType;

    var a_alipay_url = "";
    if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
        showLayerLoad();
    } else {
        layer_loading('Processing');
    }
    $.ajax({
        url: json627847.SelectPayMethod,
        async: false,
        data: {
            pay_id: pay_id,
            pay_mark: pay_mark,
            unified_id: unified_id,
            unified_number: unified_number,
            transaction_type: transaction_type
        },
        type:'post',
        dataType:'json',
        success:function(res) {
            layer.closeAll();
            if (1 == res.code) {
                $('#PayID').val(pay_id);
                $('#PayMark').val(pay_mark);
                if (res.data.appId) {
                    callpay(res.data);
                } else if (res.data.is_applets && 1 == res.data.is_applets) {
                    WeChatInternal(res.data);
                } else if (res.data.url_qrcode && 0 == json627847.IsMobile) {
                    AlertPayImg(res.data);
                } else if (res.data.url && 1 == json627847.IsMobile) {
                    a_alipay_url = res.data.url;
                    // window.open(res.data.url);
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else if (1 == res.data.is_paypal) {
                    if (res.data.item_name && res.data.amount && res.data.invoice) {
                        $('#eyou_paypalForm, #eyou_itemName').val(res.data.item_name);
                        $('#eyou_paypalForm, #eyou_amount').val(res.data.amount);
                        $('#eyou_paypalForm, #eyou_invoice').val(res.data.invoice);
                        if (res.data.eyou_cancel_return) $('#eyou_paypalForm, #eyou_cancel_return').val(res.data.eyou_cancel_return);
                        $('#eyou_paypalForm, #eyou_submitForm').click();
                    }
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else if ('unionPay' == res.data.pay_mark) {
                    $('body').append(res.data.htmlForm);
                    $('#eyou_UnionPayForm, #eyou_UnionPaySubmit').click();
                    PayPolling = window.setInterval(OrderPayPolling, 1000);
                } else {
                    $('#loading_tips_230111').html('Paymenting');
                    if (1 === parseInt(json627847.IsMobile)) {
                        window.location.href = res.url;
                    } else {
                        a_alipay_url = res.url;
                    }
                    PayPolling = window.setInterval(OrderPayPolling, 2000);
                }
            } else {
                if (1 === parseInt(json627847.is_wap) && 'v5' == json627847.usersTplVersion) {
                    showLayerAlert(res.msg);
                } else {
                    layer.alert(res.msg, {icon:0, title: false, closeBtn: 0});
                }
            }

            $.ajax({
                url: json627847.get_token_url,
                async: false,
                data: {_ajax:1},
                type:'get',
                dataType:'html',
                success:function(res) {
                    $('#__token__dfbfa92d4c447bf2c942c7d99a223b49').val(res);
                }
            });
        }
    });

    if (a_alipay_url != "") {
        /*if ('alipay' == pay_mark) {
            // 打开支付提示确认框
            unifiedShowPayConfirmBox(json627847.is_wap, function() {
                OrderPayPolling('showMsgCode');
            });
        }*/
        newWinarticlepay2(a_alipay_url);
    }
    return false;
}

//通过a标签点击事件弹出支付宝支付页面
function newWinarticlepay2(url) {
    var a = document.createElement("a");
    a.setAttribute("href", url);
    a.setAttribute("target", "_blank");
    a.setAttribute('style', 'display:none');
    document.body.appendChild(a);
    a.click();
    a.parentNode.removeChild(a);
}