if (typeof __isMobile__ == 'undefined') {
    // 如果未定义则通过JQ判断是否为移动端
    var __isMobile__ = navigator.userAgent.match(/mobile/i) ? 1 : 0;
}

// 收货地址url
var shop_add_address = eyou_basefile + "?m=user&c=Shop&a=shop_add_address&_ajax=1";
var shop_edit_address = eyou_basefile + "?m=user&c=Shop&a=shop_edit_address&_ajax=1";

// 显示地址选择框
function showAddressWindow() {
    $('.address-select-box').css('display', 'block');
    if (0 === parseInt($('#province').val())) {
        showAddressList('country');
    } else if (0 === parseInt($('#city').val())) {
        showAddressList('province');
    } else if (0 === parseInt($('#district').val())) {
        showAddressList('city');
    } else if (0 !== parseInt($('#district').val())) {
        showAddressList('district');
        $(".address-selectd .gray").text('');
    }
}

// 显示地址列表
function showAddressList(type) {
    // 隐藏全部地址列表
    $('.address-list-country, .address-list-province, .address-list-city, .address-list-district').css('display', 'none');
    // 显示对应地址列表
    if (type) $('.address-list-' + type).css('display', 'block');
}

// 查询选中地址下级地址列表
function showSelectAddress(obj, type) {
    var parent_id = $(obj).val();
    if (!parent_id) parent_id = $(obj).data('id');
    if (!parent_id) return false;

    if ('country' == type) {
        // 处理页面显示
        var text = $(obj).text();
        if (0 < parseInt($(".address-selectd .country").length)) {
            $(".address-selectd .country").text(text);
        } else {
            $(".address-selectd").prepend("<span class='country' onclick=\"showAddressList('country');\">"+text+"</span>");
        }
        $(".address-selectd .province").remove();
        $(".address-selectd .city").remove();
        $(".address-selectd .district").remove();
        showAddressList();
        $('#country').val(parent_id);
        $('#province, #city, #district').val(0);
        $('.address-select-box').css('display', 'none');
        // 加载选中地址名
        $('#address-title').val(text);
        handleParam(false);
        return false;
    }

    /*$.ajax({
        url : eyou_basefile + "?m=user&c=Shop&a=get_region_data&_ajax=1",
        data: {parent_id: parent_id},
        type: 'post',
        dataType: 'json',
        success: function(res) {
            // 选择省份地址处理
            if ('province' == type) {
                if (1 === parseInt(__isMobile__)) {
                    var options = '<option value="0">请选择城市</option>';
                    $.each(res.data, function(k,e) {
                        options += "<option value='"+e.id+"'>"+e.name+"</option>";
                    });
                    $('#city').empty().html(options);
                    $('#district').empty().html('<option value="0">请选择县/区/镇</option>');
                } else {
                    // 处理页面显示
                    var text = $(obj).text();
                    if (0 < parseInt($(".address-selectd .province").length)) {
                        $(".address-selectd .province").text(text);
                    } else {
                        $(".address-selectd").prepend("<span class='province' onclick=\"showAddressList('province');\">"+text+"</span>");
                    }
                    $(".address-selectd .city").remove();
                    $(".address-selectd .district").remove();
                    $(".address-selectd .gray").text('选择城市/地区');
                    // 加载地址列表
                    var html = '';
                    $.each(res.data, function(k,e) {
                        html += "<span data-id='"+e.id+"' onclick=\"showSelectAddress(this, 'city')\">"+e.name+"</span>";
                    });
                    showAddressList('city');
                    $('#city, #district').val(0);
                    $('#province').val(parent_id);
                    $('.address-list-city').empty().html(html);
                    // 加载选中地址名
                    $('#address-title').val(text);
                }
            }
            // 选择市区地址处理
            else if ('city' == type) {
                if (1 === parseInt(__isMobile__)) {
                    var options = '<option value="0">请选择县/区/镇</option>';
                    $.each(res.data, function(k,e) {
                        options += "<option value='"+e.id+"'>"+e.name+"</option>";
                    });
                    $('#district').empty().html(options);
                } else {
                    // 处理页面显示
                    var text = $(obj).text();
                    if (0 < parseInt($(".address-selectd .city").length)) {
                        $(".address-selectd .city").text(text);
                    } else {
                        $(".address-selectd .province").after("<span class='city' onclick=\"showAddressList('city');\">"+text+"</span>");
                    }
                    $(".address-selectd .district").remove();
                    $(".address-selectd .gray").text('选择区县');
                    // 加载地址列表
                    var html = '';
                    $.each(res.data, function(k,e) {
                        html += "<span data-id='"+e.id+"' onclick=\"showSelectAddress(this, 'district')\">"+e.name+"</span>";
                    });
                    showAddressList('district');
                    $('#district').val(0);
                    $('#city').val(parent_id);
                    $('.address-list-district').empty().html(html);
                    // 加载选中地址名
                    $('#address-title').val($(".address-selectd .province").text() + ' ' + text);
                }
            }
            // 选择县区地址处理
            else if ('district' == type) {
                if (0 === parseInt(__isMobile__)) {
                    // 处理页面显示
                    var text = $(obj).text();
                    if (0 < parseInt($(".address-selectd .district").length)) {
                        $(".address-selectd .district").text(text);
                    } else {
                        $(".address-selectd .city").after("<span class='district' onclick=\"showAddressList('district');\">"+text+"</span>");
                    }
                    showAddressList();
                    $('#district').val(parent_id);
                    $('.address-select-box').css('display', 'none');
                    // 加载选中地址名
                    $('#address-title').val($(".address-selectd .province").text() + ' ' + $(".address-selectd .city").text() + ' ' + text);
                }
                handleParam(false);
            }
        },
        error: function(e) {
            layer.closeAll();
            if (1 === parseInt(__isMobile__)) {
                showLayerAlert(e.responseText);
            } else {
                showErrorAlert(e.responseText);
            }
        }
    });*/
}

// 添加收货地址(PC端)
function addPcAddress(types) {
    if (!handleParam(true)) return false;
    $.ajax({
        url : shop_add_address,
        data: $('#theForm').serialize(),
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (res.code == 1) {
                var loadContent = addressLoadContent(res.data);
                addressLoadHandle(loadContent, 'add', types);
            } else {
                layer.closeAll();
                showErrorMsg(res.msg);
            }
        },
        error: function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    });
}

// 编辑收货地址(PC端)
function editPcAddress() {
    if (!handleParam(true)) return false;
    $.ajax({
        url : shop_edit_address,
        data: $('#theForm').serialize(),
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (res.code == 1) {
                addressLoadHandle(res.data, 'edit');
            } else {
                layer.closeAll();
                showErrorMsg(res.msg);
            }
        },
        error: function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    });
}

// 添加收货地址(移动端)
function addMoveAddress() {
    if (!handleParam(true)) return false;
    $.ajax({
        url : shop_add_address,
        data: $('#theForm').serialize(),
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (res.code == 1) {
                var _parent = parent;
                var parentObj = parent.layer.getFrameIndex(window.name);
                parent.layer.close(parentObj);
                parent.showLayerMsg(res.msg, 2, function() {
                    _parent.returnUrl(res.data.url);
                });
            } else {
                layer.closeAll();
                showLayerMsg(res.msg);
            }
        },
        error: function(e) {
            layer.closeAll();
            showLayerAlert(e.responseText);
        }
    });
}

// 添加收货地址(移动端)
function editMoveAddress() {
    if (!handleParam(true)) return false;
    $.ajax({
        url : shop_edit_address,
        data: $('#theForm').serialize(),
        type: 'post',
        dataType: 'json',
        success: function(res) {
            if (res.code == 1) {
                var _parent = parent;
                var parentObj = parent.layer.getFrameIndex(window.name);
                parent.layer.close(parentObj);
                parent.showLayerMsg(res.msg, 2, function() {
                    _parent.returnUrl(res.data.url);
                });
            } else {
                layer.closeAll();
                showLayerMsg(res.msg);
            }
        },
        error: function(e) {
            layer.closeAll();
            showLayerAlert(e.responseText);
        }
    });
}

// 地址管理页追加地址html
function addressLoadContent(data) {
    var divhtml = $('#divhtml').html();
    var strings = '';
    // 替换ID值
    if (1 === parseInt(parent.pointsShop)) {
        strings = divhtml.replace('#ul_li_id#', 'address-list-' + data.addr_id);
    } else {
        strings = divhtml.replace('#ul_li_id#', data.addr_id + "_ul_li");
    }
    strings = strings.replace('#consigneeid#', data.addr_id + "_consignee");
    strings = strings.replace('#mobileid#',    data.addr_id + "_mobile");
    strings = strings.replace('#infoid#',      data.addr_id + "_info");
    strings = strings.replace('#addressid#',   data.addr_id + "_address");
    // 替换地址内容信息
    strings = strings.replace('#consignee#', data.consignee);
    strings = strings.replace('#mobile#',    data.mobile);
    strings = strings.replace('#info#',      data.country+" "+data.province+" "+data.city+" "+data.district);
    strings = strings.replace('#address#',   data.address);
    // 替换JS方法
    if (1 === parseInt(parent.pointsShop)) {
        strings = strings.replace('#selected#',     "selectAddress('" + data.addr_id + "');");
        strings = strings.replace('#shopeditaddr#', "editAddress('" + data.addr_id + "');");
        strings = strings.replace('#shopdeladdr#',  "delAddress('" + data.addr_id + "');");
    } else {
        strings = strings.replace('#selected#',     "SelectEd('addr_id','" + data.addr_id + "');");
        strings = strings.replace('#setdefault#',   "SetDefault(this, '" + data.addr_id + "');\" data-is_default=\"0\" id=\"" + data.addr_id + "_color\" data-setbtn=\"1\" data-attr_id=\"" + data.addr_id + "\"");
        strings = strings.replace('#shopeditaddr#', "ShopEditAddress('" + data.addr_id + "');");
        strings = strings.replace('#shopdeladdr#',  "ShopDelAddress('" + data.addr_id + "');");
    }
    // 隐藏域，下单页第一次添加收货地址则出现一次，存在则替换数据
    strings = strings.replace('#name#',  "addr_id");
    strings = strings.replace('#id#',    "addr_id");
    strings = strings.replace('#value#', data.addr_id);
    return strings;
}

// 收货地址加载处理
function addressLoadHandle(loadContent, action, types) {
    if ('add' == action) {
        // 加载指定收货地址信息
        if ('list' == types) {
            parent.$('#UlHtml').find('div.address-item:last').after(loadContent);
            parent.$('#address-list-all').find('div.address-item:last').after(loadContent);
        } else if ('order' == types) {
            if (3 <= parseInt(parent.$('#UlHtml div.address-item').length) || 3 <= parseInt(parent.$('#address-list-all div.address-item').length)) {
                parent.$('#addressShowHide').attr('data-showhide', 'hide').show().click();
            }
            parent.$('#UlHtml').find('div.address-item:last').before(loadContent);
            parent.$('#address-list-all').find('div.address-item:last').before(loadContent);
        }
    } else if ('edit' == action) {
        // 更新指定收货地址信息
        parent.$('#'+loadContent.addr_id+'_mobile').html(loadContent.mobile);
        parent.$('#'+loadContent.addr_id+'_address').html(loadContent.address);
        parent.$('#'+loadContent.addr_id+'_consignee').html(loadContent.consignee);
        parent.$('#'+loadContent.addr_id+'_info').html(loadContent.country +' '+loadContent.province +' '+ loadContent.city +' '+ loadContent.district);
    }
    parent.layer.closeAll();
}

// 参数处理
function handleParam(showMsg) {
    if (!$("#consignee").val()) {
        $("#consignee").focus();
        if (showMsg && 1 === parseInt(__isMobile__)) showLayerMsg('Please enter the contact person');
        return false;
    }
    if (!$("#mobile").val()) {
        $("#mobile").focus();
        if (showMsg && 1 === parseInt(__isMobile__)) showLayerMsg('Please enter your contact phone number');
        return false;
    }
    if ($("#country").val() == 0) {
        if (showMsg && 1 === parseInt(__isMobile__)) showLayerMsg('Please select a country');
        return false;
    }
    /*if ($("#province").val() == 0 || $("#city").val() == 0 || $("#district").val() == 0) {
        if (showMsg && 1 === parseInt(__isMobile__)) showLayerMsg('请选择完整省市区');
        return false;
    }*/
    if (!$("#address").val()) {
        $("#address").focus();
        if (showMsg && 1 === parseInt(__isMobile__)) showLayerMsg('Please enter detailed address');
        return false;
    }
    if (showMsg) {
        if (1 === parseInt(__isMobile__)) {
            showLayerLoad();
        } else {
            layer_loading('正在处理');
        }
    }
    return true;
}

// 地址选择框
function layerColse() {
    var parentObj = parent.layer.getFrameIndex(window.name);
    parent.layer.close(parentObj);
}