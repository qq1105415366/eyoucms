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

// 添加收货地址
function AddAddress(){
    var parentObj = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
    var types = $('#types').val();
    var url   = $('#ShopAddAddress').val();
    if (url.indexOf('?') > -1) {
        url += '&';
    } else {
        url += '?';
    }
    url += '_ajax=1';

    var _parent = parent;
    layer_loading('正在处理');
    $.ajax({
        url: url,
        data: $('#theForm').serialize(),
        type:'post',
        dataType:'json',
        success:function(res){
            layer.closeAll();
            if(res.code == 1){
                if (res.data.url) {
                    parent.layer.close(parentObj);
                    parent.layer.msg(res.msg, {time: 1000}, function(){
                        _parent.returnUrl(res.data.url);
                    });
                } else {
                    parent.layer.close(parentObj);
                    if ('order' == types && 1 == res.data.is_mobile) {
                        setOrderAddr(res.data);
                    } else {
                        AddHtml(res.data,types);
                    }
                    parent.layer.msg(res.msg, {time: 1000});    
                }
            }else{
                layer.msg(res.msg, {icon: 5});
            }
        },
        error : function(e) {
            layer.closeAll();
            layer.alert(e.responseText, {icon: 5});
        }
    });
};

// 地址管理页追加地址html
function AddHtml(data,types)
{   
    var divhtml = $('#divhtml').html();
    var strings = '';
    // 替换ID值
    strings = divhtml.replace('#ul_li_id#',    data.addr_id + "_ul_li");
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
    strings = strings.replace('#selected#',     "SelectEd('addr_id','" + data.addr_id + "');");
    strings = strings.replace('#setdefault#',   "SetDefault(this, '" + data.addr_id + "');\" data-is_default=\"0\" id=\"" + data.addr_id + "_color\" data-setbtn=\"1\" data-attr_id=\"" + data.addr_id + "\"");
    strings = strings.replace('#shopeditaddr#', "ShopEditAddress('" + data.addr_id + "');");
    strings = strings.replace('#shopdeladdr#',  "ShopDelAddress('" + data.addr_id + "');");
    // 隐藏域，下单页第一次添加收货地址则出现一次，存在则替换数据
    strings = strings.replace('#name#',  "addr_id");
    strings = strings.replace('#id#',    "addr_id");
    strings = strings.replace('#value#', data.addr_id);
    // 追加到页面
    var is_default = divhtml.indexOf('data-is_default="1"'); // 兼容第二套会员中心
    if (is_default == -1) {
        parent.$('#UlHtml').append(strings);
    } else { // 兼容第二套会员中心
        parent.$('#UlHtml').find('.defaultTxt_1610180641').attr('data-is_default', 0).hide();
        parent.$('#UlHtml').prepend(strings);
    }
    if ('order' == types) {
        // 下单页新增地址后，调用选择方法，选择新增的地址
        parent.SelectEd('addr_id',data.addr_id);
    }
}

/**
 * 下单页新增收货地址后，默认是选中的收货地址
 * @param {[type]} data [description]
 */
function setOrderAddr(data)
{
    parent.SelectEd('addr_id',data.addr_id,data);
}

function layer_colse() {
    var parentObj = parent.layer.getFrameIndex(window.name);
    parent.layer.close(parentObj);
}