var jsonData = b82ac06cf24687eba9bc5a7ba92be4c8;
var showHeight = '200px;';
var showWidth = 1 === parseInt(jsonData.is_wap) ? '380px;' : '480px;';

$(function() {
    if (1 === parseInt($('#AllSelected').val())) $('#AllChecked').prop('checked', 'true');
});

// 数量加减
function CartUnifiedAlgorithm(is_sold_out, aid, symbol, selected, spec_value_id, cart_id) {
    if ('IsSoldOut' == is_sold_out) {
        showErrorMsg('The product is sold out!');
        return false;
    }
    if ('IsDel' == is_sold_out) {
        showErrorMsg('Invalid product!');
        return false;
    }
    
    var NumV = $('#'+cart_id+'_num'); //数量
    var CartNum = NumV.val();
    if ('+' == symbol) {
        CartNum = Number(NumV.val()) + 1;
    } else if ('-' == symbol) {
        CartNum = Number(NumV.val()) - 1;
    }
    if (Number(is_sold_out) < Number(CartNum)) {
        showErrorMsg('The inventory of the product is only '+is_sold_out+' pieces');
        var pre_value = NumV.attr('data-pre_value');
        NumV.val(pre_value);
        return false;
    }
    NumV.attr('data-pre_value', CartNum);

    var PriceV       = $('#'+cart_id+'_price');     //单价
    var SubTotalV    = $('#'+cart_id+'_subtotal');  //小计
    var TotalNumberV = $('#TotalNumber');           //总数
    var TotalAmountV = $('#TotalAmount');           //总价

    // 手动输入数量
    if ('change' == symbol) {
        if ('1' > NumV.val() || '' == NumV.val()) {
            NumV.val(1);
            showErrorMsg('The minimum quantity of goods is 1');
        }
        // 计算单品小计
        var SubTotalNums = Number(PriceV.html()) * Number(NumV.val());
        SubTotalV.html(parseFloat(SubTotalNums.toFixed(2)));
    }
    // 数量加
    else if ('+' == symbol) {
        // 计算单品数量
        NumV.val(Number(NumV.val()) + 1);
        // 计算单品小计
        var SubTotalNums = Number(PriceV.html()) * Number(NumV.val());
        SubTotalV.html(parseFloat(SubTotalNums.toFixed(2)));
    }
    // 数量减
    else if ('-' == symbol && NumV.val() > '1') {
        // 计算单品数量
        NumV.val(Number(NumV.val()) - 1);
        // 计算单品小计
        var SubTotalNums = Number(PriceV.html()) * Number(NumV.val());
        SubTotalV.html(parseFloat(SubTotalNums.toFixed(2)));
    } 
    // 商品数量最少为1
    else {
        showErrorMsg('The minimum quantity of goods is 1');
        return false;
    }

    $.ajax({
        url : jsonData.cart_unified_algorithm_url,
        data: {aid:aid,symbol:symbol,num:Number(NumV.val()),spec_value_id:spec_value_id,_ajax:1},
        type: 'post',
        dataType: 'json',
        success: function(res) {
            // 返回错误信息
            if (0 === parseInt(res.code)) {
                showSuccessMsg(res.msg, function() {
                    // 购物车不存在商品，非法操作则刷新页面
                    if (0 === parseInt(res.data.error)) window.location.reload();
                });
            } else {
                TotalNumberV.html(parseInt(res.data.NumberVal));
                TotalAmountV.html(parseFloat(res.data.AmountVal));
                $('#TotalNumberDel').html(parseInt(res.data.NumberVal));
                $('#TotalCartNumber').html(parseInt(res.data.CartAmountVal));
            }
        }
    });
}

// 购物车选中产品
function Checked(cart_id, selected) {
    // html无刷新更新选中状态
    var TotalNumberV = $('#TotalNumber'); // 获取总数对象
    var TotalAmountV = $('#TotalAmount'); // 获取总价对象
    var NumberVal    = 0; // 初始化参数
    var AmountVal    = 0; // 初始化参数
    if ('*' == cart_id) {
        // 全选中或全撤销选中
        selected = $('#AllSelected').val();
        var div_inputs = $('input[name=ey_buynum]');
        if (0 === parseInt(selected)) {
            div_inputs.each(function(){
                // 赋值单选框
                $('#'+this.id).prop('checked', true);
                $('#'+this.id).val(1);
                // 赋值隐藏域
                var NewCartId = $('#'+this.id).attr('cart-id');
                $('#'+NewCartId+'_Selected').val(1);
                // 计算总数总额
                NumberVal +=  + Number($('#'+NewCartId+'_num').val());
                AmountVal +=  + Number($('#'+NewCartId+'_subtotal').html());
            });
            // 赋值主选框
            $('#AllSelected').val(1);
        } else {
            div_inputs.each(function(){
                // 赋值单选框
                $('#'+this.id).prop("checked", false);
                $('#'+this.id).val(0);
                // 赋值隐藏域
                var NewCartId = $('#'+this.id).attr('cart-id');
                $('#'+NewCartId+'_Selected').val(0);
            });
            // 赋值主选框
            $('#AllSelected').val(0);
        }
    } else {
        selected = $('#'+cart_id+'_Selected').val();
        var div_inputs = $('input[name=ey_buynum]');
        var CheckedNum = 0; // 初始化参数
        div_inputs.each(function(){
            if ( $('#'+this.id).is(':checked') ) {
                // 计算选中数量
                CheckedNum++;
                // 计算总数总额
                NumberVal +=  + Number($('#'+$('#'+this.id).attr('cart-id')+'_num').val());
                AmountVal +=  + Number($('#'+$('#'+this.id).attr('cart-id')+'_subtotal').html());
            }
        });

        if (0 === parseInt(selected)) {
            $('#'+cart_id+'_Selected').val(1);
            if (div_inputs.length == CheckedNum) {
                // 全部选中
                $('#AllSelected').val(1);
                $('#AllChecked').prop('checked', true);
            } else {
                // 非全部选中
                $('#AllSelected').val(0);
                $('#AllChecked').prop("checked", false);
            }
        } else {
            // 撤销选中
            $('#'+cart_id+'_Selected').val(0);
            $('#AllSelected').val(0);
            $('#AllChecked').prop("checked", false);
        }
    }

    // 赋值总额总数
    TotalNumberV.html(NumberVal);
    $('#TotalNumberDel').html(parseInt(NumberVal));
    TotalAmountV.html(parseFloat(AmountVal.toFixed(2)));
    
    // 修改购物车选中数据
    $.ajax({
        url : jsonData.cart_checked_url,
        data: {cart_id: cart_id, selected: selected, _ajax: 1},
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (0 === parseInt(res.code)) showSuccessMsg(res.msg);
        }
    });
}

// 删除购物车产品
function CartDel(cart_id, title) {
    unifiedConfirmBox('Are you sure to delete items from your shopping cart?', showWidth, showHeight, function() {
        $.ajax({
            url : jsonData.cart_del_url,
            data: {cart_id: cart_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    showSuccessMsg(res.msg);
                    $('#' + cart_id + '_product').remove();
                    $('#' + cart_id + '_product_spec').remove();
                    $('#TotalNumber').html(parseInt(res.data.NumberVal));
                    $('#TotalAmount').html(parseFloat(res.data.AmountVal));
                    $('#TotalNumberDel').html(parseInt(res.data.NumberVal));
                    $('#TotalCartNumber').html(parseInt(res.data.CartAmountVal));
                    if (0 == res.data.CartCount) window.location.reload();
                } else {
                    showErrorMsg(res.msg);
                }
            }
        });
    });
}

// 删除选中的购物车商品
function selectCartDel() {
    unifiedConfirmBox('Are you sure to delete the selected product?', showWidth, showHeight, function() {
        $.ajax({
            url : jsonData.select_cart_del_url,
            data: {_ajax: 1},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                if (1 == res.code) {
                    showSuccessMsg(res.msg, function() {
                        window.location.reload();
                    });
                } else {
                    showErrorAlert(res.msg);
                }
            }
        });
    });
}

// 移入收藏
function MoveToCollection(cart_id, title) {
    unifiedConfirmBox('Confirm the product: '+title+', Move to collection?', showWidth, showHeight, function() {
        $.ajax({
            url : jsonData.move_to_collection_url,
            data: {cart_id: cart_id},
            type: 'post',
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 === parseInt(res.code)) {
                    showSuccessMsg(res.msg);
                    $('#' + cart_id + '_product').remove();
                    $('#' + cart_id + '_product_spec').remove();
                    $('#TotalNumber').html(res.data.NumberVal);
                    $('#TotalAmount').html(parseFloat(res.data.AmountVal));
                    if (0 == res.data.CartCount) window.location.reload();
                } else {
                    showErrorMsg(res.msg);
                }
            }
        });
    });
}

// 检查购物车商品是否库存都充足，不足时提示
function SubmitOrder(GetUrl) {
    $.ajax({
        url : jsonData.cart_stock_detection,
        data: {_ajax:1},
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (1 === parseInt(res.code)) {
                if (1 === parseInt(res.data)) {
                    unifiedConfirmBox('Some products have insufficient inventory, are you sure to submit?', showWidth, showHeight, function() {
                        window.location.href = GetUrl;
                    });
                } else {
                    window.location.href = GetUrl;
                }
            } else {
                showErrorMsg(res.msg);
            }
        }
    });
}

function toSplitGoods(jumpUrl) {
    $.ajax({
        url : jsonData.toSplitGoods,
        data: {_ajax: 1},
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (1 === parseInt(res.code)) {
                window.location.href = jumpUrl;
            } else {
                var area = ['1220px', '90%'];
                if (1 === parseInt(jsonData.is_wap)) area = ['100%', '100%'];
                layer.open({
                    type: 2,
                    title: 'Select settlement product',
                    shadeClose: false,
                    maxmin: false, //开启最大化最小化按钮
                    area: area,
                    content: jsonData.toSplitGoods
                });
            }
        },
        error: function(e) {
            showErrorAlert(e.responseText);
        }
    });
}