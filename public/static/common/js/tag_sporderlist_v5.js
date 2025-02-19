var jsonData = d62a4a8743a94dc0250be0c53f833b;
var showHeight = '200px;';
var showWidth = 1 === parseInt(jsonData.is_wap) ? '380px;' : '480px;';

// 取消订单
function CancelOrder(order_id) {
    layer.confirm('Are you sure you want to cancel the order?', {
        title: false,
        skin: 'xin-demo-btn',
        btn: [ey_foreign_system2, ey_foreign_system3], //按钮
        closeBtn: 0,
        shadeClose: true
    }, function() {
        $.ajax({
            url : jsonData.shop_order_cancel,
            data: {order_id: order_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    showSuccessMsg(res.msg, function() {
                        window.location.reload();
                    });
                } else {
                    showErrorMsg(res.msg);
                }
            }
        });
    });
}

// 提醒发货
function OrderRemind(order_id, order_code) {
    layer.confirm('Remind the administrator to ship?', {
        title: false,
        skin: 'xin-demo-btn',
        btn: [ey_foreign_system2, ey_foreign_system3], //按钮
        closeBtn: 0,
        shadeClose: true
    }, function() {
        $.ajax({
            url : jsonData.shop_order_remind,
            data: {order_id: order_id, order_code: order_code},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                showSuccessMsg(res.msg);
            }
        });
    });
}

// 确认收货
function Confirm(order_id, order_code) {
    layer.confirm('Are you sure you have received the goods?', {
        title: false,
        skin: 'xin-demo-btn',
        btn: [ey_foreign_system2, ey_foreign_system3], //按钮
        closeBtn: 0,
        shadeClose: true
    }, function() {
        $.ajax({
            url : jsonData.shop_member_confirm,
            data: {order_id: order_id, order_code: order_code},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    window.location.reload();
                } else {
                    showErrorMsg(res.msg);
                }
            }
        });
    });
}

function LogisticsInquiry(url) {
    //iframe窗
    layer.open({
        type: 2,
        title: 'Logistics inquiry',
        shadeClose: false,
        maxmin: false, //开启最大化最小化按钮
        area: ['90%', '90%'],
        content: url
    });
}