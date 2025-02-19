/**
 * 引入layer弹窗
 * @type {Boolean}
 */
var ey_layer_1609663499 = false;
if (window.jQuery && !window.layer) {
    ey_layer_1609663499 = true;
}
if (ey_layer_1609663499) {
    document.write(unescape("%3Cscript src='"+fe912b5dac71082e12c1827a3107f9b.root_dir+"/public/plugins/layer-v3.1.0/layer.js' type='text/javascript'%3E%3C/script%3E"));
}

// 加入购物车
function shop_add_cart() {
    var JsonData    = fe912b5dac71082e12c1827a3107f9b;
    var QuantityObj = document.getElementById(JsonData.quantity);
    var SelectValueIds = document.getElementById(JsonData.SelectValueIds);
    var aid = JsonData.aid;
    var num = QuantityObj.value;
    var url = JsonData.shop_add_cart_url;
    var ajaxdata = 'aid='+aid+'&num='+num+'&spec_value_id='+SelectValueIds.value;

    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_v602291').value;
    if (parseInt(StockCountObj) == 0) {
        if (!window.layer) {
            alert('商品已售罄！');
        } else {
            layer.alert('商品已售罄！', {icon: 5, title: false, closeBtn: false});
        }
        return false;
    } else if (parseInt(StockCountObj) < parseInt(num)) {
        if (!window.layer) {
            alert('商品库存不足！');
        } else {
            layer.alert('商品库存不足！', {icon: 5, title: false, closeBtn: false});
        }
        return false;
    }

    //创建异步对象
    var ajaxObj = new XMLHttpRequest();
    ajaxObj.open("post", url, true);
    ajaxObj.setRequestHeader("X-Requested-With","XMLHttpRequest");
    ajaxObj.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //发送请求
    ajaxObj.send(ajaxdata);

    ajaxObj.onreadystatechange = function () {
        // 这步为判断服务器是否正确响应
        if (ajaxObj.readyState == 4 && ajaxObj.status == 200) {
            var json = ajaxObj.responseText;  
            var res = JSON.parse(json);
            if ('1' == res.code) {
                // 是否要去购物车 
                shop_cart_list(JsonData.shop_cart_list_url);
            }else{
                if (-1 == res.data.code) {
                    if (!window.layer) {
                        confirm(res.msg);
                    } else {
                        layer.alert(res.msg, {icon: 5, title: false, closeBtn: false});
                    }
                }else{
                    // 去登陆
                    is_login(JsonData.login_url);
                }
            }
        } 
    };
}

/**
 * 获取url参数值的方法
 * @param  {[type]} name [description]
 * @return {[type]}      [description]
 */
function getUrlParam_v507428(name)
{
    var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
    var r = window.location.search.substr(1).match(reg);
    if (r!=null) return unescape(r[2]); return null;
}

// 表单提交
function submitForm_v507428(formname)
{
    $("form[name="+formname+"]").submit();
}

// 选择支付方式
function paySelect_v507428(formname)
{
    var JsonData    = fe912b5dac71082e12c1827a3107f9b;
    var QuantityObj = document.getElementById(JsonData.quantity);
    var SelectValueIds = document.getElementById(JsonData.SelectValueIds);

    var aid = JsonData.aid;
    var spec_value_id = SelectValueIds.value;
    var mini_id = getUrlParam_v507428('mini_id');
    if ($('select[name=mini_id]') && 0 < $('select[name=mini_id]').val()) {
        mini_id = $('select[name=mini_id]').val();
    }
    if (mini_id > 0) {
        mini_id = parseInt(mini_id);
    } else {
        mini_id = 0;
    }

    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_v602291').value;
    if (parseInt(StockCountObj) == 0) {
        layer.alert('商品已售罄！', {icon: 5, title: false, closeBtn: false});
        return false;
    } else if (parseInt(StockCountObj) < parseInt(QuantityObj.value)) {
        layer.alert('商品库存不足！', {icon: 5, title: false, closeBtn: false});
        return false;
    }

    var formhtml = '';
    formhtml += '<form name="'+formname+'" action="'+JsonData.buyFormUrl+'" method="post" style="display: none;">';
    formhtml += '    <input type="hidden" name="aid_v507428" value="'+aid+'" />';
    formhtml += '    <input type="hidden" name="mini_id_v507428" value="'+mini_id+'" />';
    formhtml += '    <input type="hidden" name="spec_value_id_v507428" value="'+spec_value_id+'" />';
    formhtml += '    <input type="hidden" name="pay_code_v507428" value="alipay" />';
    formhtml += '</form>';
    $('body').append(formhtml);

    var content = '';
    content += '<style type="text/css">body .WeChatScanCode_v507428 .layui-layer-content{padding:0px;}</style>';
    content += '<div style="margin-top:20px;">';
    content += '<a href="javascript:void(0);" onclick="layer.closeAll();parent.submitForm_v507428(\''+formname+'\');" style="float: left;">';
    content += ' <img src="'+JsonData.root_dir+'/public/static/common/images/alipay.png" width="150" height="50" alt="支付宝支付" />';
    content += '</a>';
    content += '<a href="javascript:void(0);" onclick="layer.closeAll();parent.WeChatScanCode_v507428(\''+aid+'\',\''+mini_id+'\',\''+spec_value_id+'\',\'weipay\');" style="float: right;">';
    content += ' <img src="'+JsonData.root_dir+'/public/static/common/images/weipay.png" width="150" height="50" alt="微信支付" />';
    content += '</a>';
    content += '</div>';

    layer.open({
        type: 1,
        title: '选择支付方式',
        shadeClose: false,
        maxmin: false, //开启最大化最小化按钮
        skin: 'WeChatScanCode_v507428',
        area: ['320px', '150px'],
        content: content
    });
}

// 微信扫码支付
function WeChatScanCode_v507428(aid, mini_id, spec_value_id, pay_code)
{
    var formData = new FormData();
    formData.append('aid_v507428', aid);
    formData.append('mini_id_v507428', mini_id);
    formData.append('spec_value_id_v507428', spec_value_id);
    formData.append('pay_code_v507428', pay_code);

    weipay_v507428(formData);
}

// 微信扫码支付，用于PC端
function weipay_v507428(formData)
{
    formData.append('_ajax', 1);
    layer_loading('正在处理');
    $.ajax({
        url: fe912b5dac71082e12c1827a3107f9b.buyFormUrl,
        type: 'POST',
        data: formData,
        dataType: "json", //声明成功使用json数据类型回调
        //如果传递的是FormData数据类型，那么下来的三个参数是必须的，否则会报错
        cache: false,  //默认是true，但是一般不做缓存
        processData: false, //用于对data参数进行序列化处理，这里必须false；如果是true，就会将FormData转换为String类型
        contentType: false,  //一些文件上传http协议的关系，自行百度，如果上传的有文件，那么只能设置为false
        success: function(res){
            layer.closeAll();
            if (res.code == 1) {
                AlertPayImg_v507428(res.data);
            } else {
                layer.alert(res.msg, {icon:5, title: false, closeBtn: false});
            }
        },
        error: function(e){
            layer.closeAll();
            layer.alert(e.responseText, {icon:5, title: false, closeBtn: false});
        }
    });
}

var PayPolling_v507428;
// 装载显示扫码支付的二维码
function AlertPayImg_v507428(data) {
    var html = "<img src='"+data.url_qrcode+"' style='width: 250px; height: 250px;'><br/><span style='color: red; display: inline-block; width: 100%; text-align: center;'>正在支付中...请勿刷新</span>";
    layer.alert(html, {
        title: '微信扫码支付',
        btn: [],
        success: function() {
            PayPolling_v507428 = window.setInterval(function(){ OrderPayPolling_v507428(data); }, 2000);
        },
        cancel: function() {
            window.clearInterval(PayPolling_v507428);
        }
    });
}

// 订单轮询
function OrderPayPolling_v507428(data) {
    var pay_id = data.pay_id;
    var pay_mark = data.pay_mark;
    var unified_id = data.unified_id;
    var unified_number = data.unified_number;
    var transaction_type = data.transaction_type;
    if (!pay_id || !pay_mark || !unified_id || !unified_number || !transaction_type) {
        layer.msg('订单异常，刷新重试', {time: 1500}, function(){
            window.location.reload();
        });
    }
    $.ajax({
        url: fe912b5dac71082e12c1827a3107f9b.OrderPayPolling,
        data: {
            pay_id: pay_id,
            pay_mark: pay_mark,
            unified_id: unified_id,
            unified_number: unified_number,
            transaction_type: transaction_type
        },
        type:'post',
        dataType:'json',
        success:function(res){
            if (1 == res.code) {
                if (res.data) {
                    layer_loading('正在处理');
                    window.clearInterval(PayPolling_v507428);
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
                }
            } else {
                layer.alert(res.msg, {icon:5, title: false, closeBtn: false});
            }
        },
        error: function(e){
            layer.closeAll();
            layer.alert(e.responseText, {icon:5, title: false, closeBtn: false});
        }
    });
}

// 发送短信
function SendMobile(result) {
    if (result) {
        $.ajax({
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
            url: result.url,
            data: result.data,
            type:'post',
            dataType:'json'
        });
    }
}

// 立即购买
function BuyNow(aid){
    var JsonData    = fe912b5dac71082e12c1827a3107f9b;
    var QuantityObj = document.getElementById(JsonData.quantity);
    var SelectValueIds = document.getElementById(JsonData.SelectValueIds);

    var url = JsonData.shop_buy_now_url;
    var aid = JsonData.aid;
    var num = QuantityObj.value;
    var spec_value_id = SelectValueIds.value;
    var ajaxdata = 'aid='+aid+'&num='+num+'&spec_value_id='+spec_value_id;

    try {
        if (document.getElementsByName("mini_id")[0]) {
            var mini_id = getUrlParam_v507428('mini_id');
            if (0 < document.getElementsByName("mini_id")[0].value) {
                mini_id = document.getElementsByName("mini_id")[0].value;
            }
            if (mini_id > 0) {
                ajaxdata += '&mini_id='+parseInt(mini_id);
            }
        }
    }catch(err){}

    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_v602291').value;
    if (parseInt(StockCountObj) == 0) {
        if (!window.layer) {
            alert('商品已售罄！');
        } else {
            layer.alert('商品已售罄！', {icon: 5, title: false, closeBtn: false});
        }
        return false;
    } else if (parseInt(StockCountObj) < parseInt(num)) {
        if (!window.layer) {
            alert('商品库存不足！');
        } else {
            layer.alert('商品库存不足！', {icon: 5, title: false, closeBtn: false});
        }
        return false;
    }

    //创建异步对象
    var ajaxObj = new XMLHttpRequest();
    ajaxObj.open("post", url, true);
    ajaxObj.setRequestHeader("X-Requested-With","XMLHttpRequest");
    ajaxObj.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //发送请求
    ajaxObj.send(ajaxdata);

    ajaxObj.onreadystatechange = function () {
        // 这步为判断服务器是否正确响应
        if (ajaxObj.readyState == 4 && ajaxObj.status == 200) {
            var json = ajaxObj.responseText;  
            var res  = JSON.parse(json);
            if ('1' == res.code) {
                // 去购买
                window.location.href = res.url;
            }else{
                if (-1 == res.data.code) {
                    if (!window.layer) {
                        confirm(res.msg);
                    } else {
                        layer.alert(res.msg, {icon: 5, title: false, closeBtn: false});
                    }
                }else{
                    // 去登陆
                    is_login(JsonData.login_url);
                }
            }
        } 
    };
}

// 数量加减处理
function CartUnifiedAlgorithm(symbol){
    // 数量
    var QuantityObj = document.getElementById(fe912b5dac71082e12c1827a3107f9b.quantity);
    // 库存数量
    var StockCountObj = document.getElementById('ey_stock_v602291');
    // 默认数量
    var quantity = '';
    if ('change' == symbol) {
        // 直接修改数量
        if ('1' > QuantityObj.value || '' == QuantityObj.value) {
            quantity = 1;
            // if (!window.layer) {
            //     alert('商品数量最少为1');
            // } else {
            //     layer.alert('商品数量最少为1', {icon: 5, title: false, closeBtn: false});
            // }
        }else{
            if (Number(QuantityObj.value) > Number(StockCountObj.value)) {
                quantity = Number(StockCountObj.value);
            }else{
                quantity = Number(QuantityObj.value);
            }
        }
    }else if ('+' == symbol) {
        // 加数量
        quantity = Number(QuantityObj.value) + 1;
    }else if ('-' == symbol && QuantityObj.value > '1') {
        // 减数量
        quantity = Number(QuantityObj.value) - 1;
    }else{
        quantity = 1;
        // 如果数量小于1则自动填充1
        // if (!window.layer) {
        //     alert('商品数量最少为1');
        // } else {
        //     layer.alert('商品数量最少为1', {icon: 5, title: false, closeBtn: false});
        // }
    }
    // 数量是否大于库存量
    if (StockCountObj.value < quantity) {
        if (!window.layer) {
            alert('这件产品库存仅为：'+StockCountObj.value);
        } else {
            layer.alert('这件产品库存仅为：'+StockCountObj.value, {icon: 5, title: false, closeBtn: false});
        }
        return false;
    }
    // 加载数量
    QuantityObj.value = quantity;
}

// 去购车去
function shop_cart_list(url) {
    window.location.href = url;
    
/*
    if (!window.layer) {
        var mymessage = confirm('加入购物车成功，前往购物车！');
        if (mymessage == true) window.location.href = url;
    } else {
        var confirms = layer.confirm('已加入购物车成功！', {
            title: false,
            btn: ['前往购物车']
        }, function (index) {
            layer.close(confirms);
            window.location.href = url;
        });
    }
    */
}

// 去登陆
function is_login(url){
    if (document.getElementById('ey_login_id_v665117')) {
        $('#ey_login_id_v665117').trigger('click');
    } else {
        if (!window.layer) {
            var mymessage = confirm('您还没未登录，请登录后购买！');
            if(mymessage == true){
                window.location.href = url;
            }
        } else {
            layer.alert('您还没未登录，请登录后购买！', {icon: 5, title: false}, function(){
                window.location.href = url;
            });
        }
    }
}

function sortNumber(a, b) { 
    return a - b 
}

function SpecSelect(spec_mark_id, spec_value_id, discount_price) {
    var JsonData = fe912b5dac71082e12c1827a3107f9b;
    var currentstyle = JsonData.currentstyle;
    // 清除同一类下的所有选中参数class
    var ClassArray = document.getElementsByClassName("spec_mark_"+spec_mark_id);
    for (var i = 0; i < ClassArray.length; i++) {
        ClassArray[i].classList.remove(currentstyle);
    }

    // 当前点击的添加上class
    document.getElementsByClassName('spec_value_'+spec_value_id)[0].classList.add(currentstyle);

    /*规格值ID处理*/
    // 获取所有选中的规格值ID
    var SelectValueIds = '';
    var danger = document.getElementsByClassName(currentstyle);
    for (var i = 0; i < danger.length; i++) {
        if (danger[i].dataset.spec_value_id) {
            SelectValueIds += danger[i].dataset.spec_value_id;
            SelectValueIds += '_';
        }
    }
    // 去除最后一个字符
    SelectValueIds = SelectValueIds.substring(0, SelectValueIds.length-1);
    // 字符串转数组
    SelectValueIds = SelectValueIds.split('_');
    // 从小到大排序
    SelectValueIds = SelectValueIds.sort(sortNumber);
    // 数组转字符串
    SelectValueIds = SelectValueIds.join('_');
    /* END */

    // 解析json数据
    var SpecData = JSON.parse(JsonData.SpecData);
    
    // 更新价格及库存
    for(var i = 0; i < SpecData.length; i++){
        if (SelectValueIds == SpecData[i]['spec_value_id']) {
            document.getElementById('SelectValueIds').value  = SelectValueIds;
            if (discount_price) {
                if (document.getElementById('old_price')) {
                    var old_price   = Number(SpecData[i]['spec_price']);
                    document.getElementById('old_price').innerText   = parseFloat(old_price.toFixed(2));
                }
                var users_price = Number(SpecData[i]['spec_price']) * discount_price;
                if (document.getElementById('users_price')) {
                    document.getElementById('users_price').innerText = parseFloat(users_price.toFixed(2));
                }
                if (document.getElementById('totol_price')) {
                    document.getElementById('totol_price').innerText = parseFloat(users_price.toFixed(2));
                }
            }else{
                var users_price = Number(SpecData[i]['spec_price']);
                if (document.getElementById('users_price')) {
                    document.getElementById('users_price').innerText = parseFloat(users_price.toFixed(2));
                }
                if (document.getElementById('totol_price')) {
                    document.getElementById('totol_price').innerText = parseFloat(users_price.toFixed(2));
                }
            }
            if (document.getElementById('spec_price')) {
                document.getElementById('spec_price').innerText = parseFloat(users_price.toFixed(2));
            }
            if (document.getElementById('stock_count')) {
                document.getElementById('stock_count').innerText = SpecData[i]['spec_stock'];
            }
            if (document.getElementById('sales_num')) {
                document.getElementById('sales_num').innerText = SpecData[i]['spec_sales_num'];
            }
            if (document.getElementById('stock_code')) {
                document.getElementById('stock_code').innerText = SpecData[i]['stock_code'];
            }
            if (document.getElementById('ey_stock_v602291')) {
                document.getElementById('ey_stock_v602291').value = SpecData[i]['spec_stock'];
            }
            // 更新价格和库存后，购买数量重置为 1 
            document.getElementById(fe912b5dac71082e12c1827a3107f9b.quantity).value = 1;
        }
    }
}