function selectSpecType(spec_type) {
    if (1 === parseInt(spec_type)) {
        $('.spec_type_1').show();
        $('.spec_type_2').hide();
        $('#dl_virtual_sales').show();
        $('#users_price, #stock_count').removeAttr("readonly");
        // var IsSpecData = $('#SpecTempLateDiv').html();
        // if (IsSpecData && IsSpecData != ' ') {
        //     layer.confirm('<span style="color: red;">请注意：您已添加多规格，若切换单规格并保存商品，则清空多规格数据！</span>', {
        //         shade: layer_shade,
        //         area: ['480px', '190px'],
        //         move: false,
        //         title: '提示',
        //         btnAlign:'r',
        //         closeBtn: 3,
        //         btn: ['确定', '取消'] ,//按钮
        //         success: function () {
        //             $(".layui-layer-content").css('text-align', 'left');
        //         }
        //     }, function (index) {
        //         $('#spec_type1').attr('checked', 'checked');
        //         $('#spec_type2').removeAttr('checked');
        //         layer.close(index);
        //     }, function (index) {
        //         $('.spec_type_1').hide();
        //         $('.spec_type_2').show();
        //         $('#users_price, #stock_count').attr("readonly", "readonly");
        //         $('#spec_type1').removeAttr('checked');
        //         $('#spec_type2').attr('checked', 'checked');
        //         layer.close(index);
        //     });
        // }
    } else {
        $('.spec_type_1').hide();
        $('.spec_type_2').show();
        $('#dl_virtual_sales').hide();
        $('#users_price, #stock_count').attr("readonly", "readonly");
    }
}

// 初始化规格，清除多余规格数据
if (typeof initializationSpec != 'undefined') ajaxInitializationSpec();
function ajaxInitializationSpec() {
    $.ajax({
        type: 'post',
        url : initializationSpec,
        data: {initialization: true},
        dataType: 'json',
        success: function(res) {
            // 自动增加一个自定义规格
            if (1 === parseInt(res.code)) $('#addCustomSpec').click();
        },
        error: function(e) {
            showErrorAlert(e.responseText);
        }
    });
}

function goodsSpecDetection() {
    var result = 1;
    if (typeof goodsSpecDetectionUrl != 'undefined') {
        $.ajax({
            async: false,
            type: 'post',
            url : goodsSpecDetectionUrl,
            data: $('#post_form').serialize(),
            dataType: 'json',
            success: function(res) {
                if (0 === parseInt(res.code)) {
                    showErrorMsg(res.msg);
                    result = 0;
                }
            }
        });
    }
    return parseInt(result);
}

// 自定义规格
function addCustomSpec(obj) {
    // 本次使用的规格数量
    var specValue = $(obj).attr('data-value');
    if (3 <= specValue) {
        showErrorMsg('最多使用三种商品规格大类');
        return false;
    }
    // 自定义规格数量+1覆盖原值
    $(obj).attr('data-value', ++specValue);
    // if (3 == specValue) $('#addSpecButton').hide();
    // 创建一个自定义规格空数据和表格
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url : addProductCustomSpec,
        data: {aid: aid, action: 'name'},
        dataType: 'json',
        success: function(res) {
            layer.closeAll();
            if (1 == res.code) {
                // 最大的规格标记ID
                var maxSpecMarkID = res.data.spec_mark_id;
                // 规格框
                var html = [
                    '<div class="prset-box">'+
                        '<span class="set-preset-bt mr10 specNameSpan" style="display: block;">'+
                            '<input type="text" name="set_spec_name" class="zdy-ggname w150" placeholder="规格名称.." onchange="setSpecName(this, ' + maxSpecMarkID + ');">'+
                            '<em onclick="setSpecNameClear(this, ' + maxSpecMarkID + ');">'+
                                '<i class="fa fa-times-circle" title="关闭" style="margin-left: -20px; margin-top: 8px;"></i>'+
                            '</em>'+
                            '<label style="display: none; margin-left: 10px;"><input type="checkbox" onclick="openGoodsSpecImage(this);" value="' + maxSpecMarkID + '">添加图片</label>'+
                        '</span>'+
                        '<span class="set-preset-box"></span>'+
                        '<span class="set-preset-con">'+
                            '<span class="set-preset-bt mr10">'+
                                '<div class="spec-dan-pane openGoodsSpecImage_' + maxSpecMarkID + '" style="display: none;">'+
                                    '<div class="images_upload">'+
                                        '<a href="javascript:void(0);" onclick="goodsSpecImageAdd(' + maxSpecMarkID + ', ' + res.data.spec_value_id + ');" class="img-upload" title="上传图片" style="width: 30px; height: 30px;">'+
                                            '<div class="y-line spec_image_y_line_' + res.data.spec_value_id + '"></div>'+
                                            '<div class="x-line spec_image_x_line_' + res.data.spec_value_id + '"></div>'+
                                            '<img src="" class="pic_con spec_image_src_' + res.data.spec_value_id + '" style="display: none;">'+
                                        '</a>'+
                                        // '<a href="javascript:void(0)" onclick="goodsSpecImageClear(' + maxSpecMarkID + ', ' + res.data.spec_value_id + ');" class="delect spec_image_delect_' + res.data.spec_value_id + '" style="display: none;"></a>'+
                                    '</div>'+
                                '</div>'+
                                '<input type="hidden" value="' + res.data.spec_value_id + '">'+
                                '<input type="text" class="zdy-ggshuzi w150" placeholder="规格值.." onchange="setSpecValue(this, ' + maxSpecMarkID + ');">'+
                            '</span>'+
                            '<a href="javascript:void(0);" onclick="addCustomSpecValue(this, ' + maxSpecMarkID + ');" class="preset-bt-shuzi mr10">+增加规格值</a>'+
                        '</span>'+
                    '</div>'
                ];
                $('#SpecSelectName').show().append(html);
                if (1 === parseInt($('#SpecSelectName .prset-box').eq(0).length)) {
                    $('#SpecSelectName .prset-box').eq(0).find('.specNameSpan label').show();
                }
                // 价格/库存HTML
                if (res.data.htmlTable) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data.htmlTable);
                    // 合并单元格
                    MergeCells();
                }
            } else {
                showErrorMsg(res.msg, 2);
            }
        },
        error: function(e) {
            layer.closeAll();
            showErrorAlert(e.responseText);
        }
    });
}

// 输入完规格名称后执行
function setSpecName(obj, spec_mark_id) {
    var set_spec_name = $(obj).val();
    if (spec_mark_id && set_spec_name) {
        $.ajax({
            type: 'post',
            url : addProductCustomSpecName,
            data: {aid: aid, spec_mark_id: spec_mark_id, set_spec_name: set_spec_name},
            dataType: 'json',
            success: function(res) {
                if (1 == res.code) {
                    // 价格/库存HTML
                    if (res.data.htmlTable) {
                        // 加载规格框架
                        $('#SpecTempLateDiv').html(res.data.htmlTable);
                        // 合并单元格
                        MergeCells();
                    }
                } else {
                    showErrorMsg(res.msg, 2);
                }
            },
            error: function(e) {
                layer.closeAll();
                showErrorAlert(e.responseText);
            }
        });
    } else if (spec_mark_id && !set_spec_name) {
        $(obj).val($('.spec_name_input_' + spec_mark_id).val());
    }
}

// 清除数据库指定数据
function setSpecNameClear(obj, spec_mark_id) {
    if (spec_mark_id) {
        // 执行处理
        $.ajax({
            type: 'post',
            url : delProductCustomSpec,
            data: {aid: aid, spec_mark_id: spec_mark_id, del: 'name'},
            dataType: 'json',
            success: function(res) {
                // 删除当前点击规格块的顶级html
                $(obj).parent().parent().remove();
                // 如果存在则执行覆盖新的规格列表框
                if (res.data.htmlTable) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data.htmlTable);
                    // 合并单元格
                    MergeCells();
                }
                // 使用规格数量+1覆盖原值
                var specValue = $('#addCustomSpec').attr('data-value');
                $('#addCustomSpec').attr('data-value', --specValue);
                // 显示添加按钮
                // $('#addSpecButton').show();
                if (1 === parseInt($('#SpecSelectName .prset-box').eq(0).length)) {
                    $('#SpecSelectName .prset-box').eq(0).find('.specNameSpan label').show();
                }
            }
        });
    }
}

// 规格规格值
function addCustomSpecValue(obj, spec_mark_id) {
    if (spec_mark_id) {
        layer_loading('正在处理');
        $.ajax({
            type: 'post',
            url : addProductCustomSpec,
            data: {aid: aid, spec_mark_id: spec_mark_id, action: 'value'},
            dataType: 'json',
            success: function(res) {
                layer.closeAll();
                if (1 == res.code) {
                    var display = $(obj).parent().parent().find('.set-preset-bt label input').is(':checked') ? '' : 'style="display: none;"';
                    // 规格值HTML
                    var html = [
                        '<span class="set-preset-bt mr10">'+
                            '<div class="spec-dan-pane openGoodsSpecImage_' + spec_mark_id + '" ' + display + '>'+
                                '<div class="images_upload">'+
                                    '<a href="javascript:void(0);" onclick="goodsSpecImageAdd(' + spec_mark_id + ', ' + res.data.spec_value_id + ');" class="img-upload" title="上传图片" style="width: 30px; height: 30px;">'+
                                        '<div class="y-line spec_image_y_line_' + res.data.spec_value_id + '"></div>'+
                                        '<div class="x-line spec_image_x_line_' + res.data.spec_value_id + '"></div>'+
                                        '<img src="" class="pic_con spec_image_src_' + res.data.spec_value_id + '" style="display: none;">'+
                                    '</a>'+
                                    // '<a href="javascript:void(0)" onclick="goodsSpecImageClear(' + spec_mark_id + ', ' + res.data.spec_value_id + ');" class="delect spec_image_delect_' + res.data.spec_value_id + '" style="display: none;"></a>'+
                                '</div>'+
                            '</div>'+
                            '<input type="hidden" value="' + res.data.spec_value_id + '">'+
                            '<input type="text" class="zdy-ggshuzi w150" placeholder="规格值.." onchange="setSpecValue(this, ' + res.data.spec_mark_id + ');">'+
                            '<em data-spec_mark_id="' + res.data.spec_mark_id + '" data-spec_value_id="' + res.data.spec_value_id + '" onclick="setSpecValueClear(this);"><i class="fa fa-times-circle" title="关闭" style="margin-left: -22px;margin-top: 8px;cursor: pointer;"></i></em>'+
                        '</span>'
                    ];
                    // 加载一个规格值 input
                    $(obj).before(html);
                    // 价格/库存HTML
                    if (res.data.htmlTable) {
                        // 加载规格框架
                        $('#SpecTempLateDiv').html(res.data.htmlTable);
                        // 合并单元格
                        MergeCells();
                    }
                } else {
                    showErrorMsg(res.msg, 2);
                }
            },
            error: function(e) {
                layer.closeAll();
                showErrorAlert(e.responseText);
            }
        });
    }
}

// 输入完规格值后执行
function setSpecValue(obj, spec_mark_id) {
    // 指定的FormID
    var set_spec_value = $(obj).val();
    var spec_value_id = $(obj).prev().val();
    if (set_spec_value && spec_value_id && spec_mark_id) {
        $.ajax({
            type: 'post',
            url : addProductCustomSpecValue,
            data: {aid: aid, set_spec_value: set_spec_value, spec_value_id: spec_value_id, spec_mark_id: spec_mark_id},
            dataType: 'json',
            success: function(res) {
                if (1 == res.code) {
                    // 价格/库存HTML
                    if (res.data.htmlTable) {
                        // 加载规格框架
                        $('#SpecTempLateDiv').html(res.data.htmlTable);
                        // 合并单元格
                        MergeCells();
                    }
                } else {
                    showErrorMsg(res.msg, 2);
                }
            },
            error: function(e) {
                layer.closeAll();
                showErrorAlert(e.responseText);
            }
        });
    } else if (spec_value_id && !set_spec_value) {
        $(obj).val($('.spec_value_input_' + spec_value_id).val());
    }
}

// 清除数据库指定数据
function setSpecValueClear(obj) {
    // 当选对象
    var spec_mark_id = $(obj).attr('data-spec_mark_id');
    var spec_value_id = $(obj).attr('data-spec_value_id');
    // 执行处理
    $.ajax({
        type: 'post',
        url : delProductCustomSpec,
        data: {aid: aid, spec_mark_id: spec_mark_id, spec_value_id: spec_value_id, del: 'value'},
        dataType: 'json',
        success: function(res) {
            // 删除当前点击规格块的顶级html
            $(obj).parent().remove();
            // 如果存在则执行覆盖新的规格列表框
            if (res.data.htmlTable) {
                // 加载规格框架
                $('#SpecTempLateDiv').html(res.data.htmlTable);
                // 合并单元格
                MergeCells();
            }
        }
    });
}

// 生成选中规格名称html并更新加载规格值
function addPresetSpec(obj) {
    var preset_mark_id = $(obj).val();
    if (0 === parseInt(preset_mark_id)) return false;
    // 本次使用的规格数量
    var specValue = $('#addCustomSpec').attr('data-value');
    if (3 <= specValue) {
        showErrorMsg('最多使用三种商品规格大类');
        return false;
    }
    // 自定义规格数量+1覆盖原值
    $('#addCustomSpec').attr('data-value', ++specValue);
    // if (3 == specValue) $('#addSpecButton').hide();
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url : addProductCustomSpec,
        data: {aid: aid, preset_mark_id: preset_mark_id, action: 'specName'},
        dataType: 'json',
        success: function(res) {
            layer.closeAll();
            if (0 == res.code) {
                showErrorMsg(res.msg);
            } else {
                // 主DIV加载
                var html = [
                    '<div class="prset-box" id="preset_'+preset_mark_id+'">'+
                        '<div id="div_'+preset_mark_id+'">'+
                            '<div>'+
                                '<span class="mr10 specNameSpan" style="display: flex;align-items: baseline;">'+
                                    '<span class="preset-bt w150 mr10">'+
                                        '<span class="spec_name_span_'+preset_mark_id+'">'+res.data.spec_name+'</span>'+
                                        '<em data-name="'+res.data.spec_name+'" data-mark_id="'+preset_mark_id+'" onclick="clearPresetSpec(this, '+preset_mark_id+')"><i class="fa fa-times-circle" title="关闭"></i>'+
                                        '</em>'+
                                    '</span>'+
                                
                                    '<label style="display: none; margin-left: 10px;"><input type="checkbox" onclick="openGoodsSpecImage(this);" value="' + preset_mark_id + '">添加图片</label>'+
                                '</span>'+
                                '<span class="set-preset-box"></span>'+
                                '<span class="set-preset-con">'+
                                    '<span class="d-flex" id="SelectEd_'+preset_mark_id+'"></span>'+
                                    '<select class="preset-select" name="spec_value" id="spec_value_'+preset_mark_id+'" onchange="addPresetSpecValue(this, '+preset_mark_id+')">'+
                                    '</select>'+
                                '</span>'+
                            '</div>'+
                        '</div>'+
                    '</div>'
                ];
                $('#SpecSelectName').append(html);
                if (1 === parseInt($('#SpecSelectName .prset-box').eq(0).length)) {
                    $('#SpecSelectName .prset-box').eq(0).find('.specNameSpan label').show();
                }
                // 规格值加载
                if (res.data.spec_value_option) $('#spec_value_' + preset_mark_id).empty().html(res.data.spec_value_option);
                // 更新预设名称下拉框
                if (res.data.preset_name_option) $('#spec_value').empty().html(res.data.preset_name_option);
                // 价格/库存HTML
                if (res.data.htmlTable) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data.htmlTable);
                    // 合并单元格
                    MergeCells();
                }
            }
        }
    });
}

// 清除数据库指定数据
function clearPresetSpec(obj, spec_mark_id) {
    if (spec_mark_id) {
        // 执行处理
        $.ajax({
            type: 'post',
            url : delProductCustomSpec,
            data: {aid: aid, spec_mark_id: spec_mark_id, del: 'specName'},
            dataType: 'json',
            success: function(res) {
                // 删除当前点击规格块的顶级html
                $(obj).parent().parent().parent().parent().parent().remove();
                // 如果存在则执行覆盖新的规格列表框
                if (res.data.htmlTable) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data.htmlTable);
                    // 合并单元格
                    MergeCells();
                }
                // 更新预设名称下拉框
                if (res.data.preset_name_option) $('#spec_value').empty().html(res.data.preset_name_option);
                // 使用规格数量+1覆盖原值
                var specValue = $('#addCustomSpec').attr('data-value');
                $('#addCustomSpec').attr('data-value', --specValue);
                // 显示添加按钮
                // $('#addSpecButton').show();
                if (1 === parseInt($('#SpecSelectName .prset-box').eq(0).length)) {
                    $('#SpecSelectName .prset-box').eq(0).find('.specNameSpan label').show();
                }
            }
        });
    }
}

// 追加预设规格名称和规格值
function addPresetSpecValue(obj, spec_mark_id) {
    var spec_value_id = $(obj).val();
    layer_loading('正在处理');
    $.ajax({
        type: 'post',
        url : addProductCustomSpec,
        data: {aid: aid, spec_value_id: spec_value_id, spec_mark_id: spec_mark_id, action: 'specValue'},
        dataType: 'json',
        success: function(res){
            layer.closeAll();
            if (0 == res.code) {
                showErrorMsg(res.msg);
            } else {
                var display = $(obj).parent().parent().find('label input').is(':checked') ? '' : 'style="display: none;"';
                // 追加选中的值
                $('#SelectEd_'+spec_mark_id).append(
                    '<span class="d-flex mr10" id="preset-bt2_'+spec_value_id+'">'+
                        '<div class="spec-dan-pane openGoodsSpecImage_' + spec_mark_id + '" ' + display + '>'+
                            '<div class="images_upload">'+
                                '<a href="javascript:void(0);" onclick="goodsSpecImageAdd(' + spec_mark_id + ', ' + spec_value_id + ');" class="img-upload" title="上传图片" style="width: 30px; height: 30px;">'+
                                    '<div class="y-line spec_image_y_line_' + spec_value_id + '"></div>'+
                                    '<div class="x-line spec_image_x_line_' + spec_value_id + '"></div>'+
                                    '<img src="" class="pic_con spec_image_src_' + spec_value_id + '" style="display: none;">'+
                                '</a>'+
                            '</div>'+
                        '</div>'+
                        '<span class="preset-bt2">'+
                            '<span class="preset_value_span_'+spec_value_id+'">'+res.data.spec_value+'</span>'+
                            '<em data-value="'+res.data.spec_value+'" data-spec_mark_id="'+spec_mark_id+'" data-spec_value_id="'+spec_value_id+'" onclick="clearPresetSpecValue(this)"><i class="fa fa-times-circle" title="关闭"></i></em> &nbsp; '+
                        '</span>'+
                    '</span>'
                );
                // 规格值加载
                if (res.data.spec_value_option) $('#spec_value_'+spec_mark_id).empty().html(res.data.spec_value_option);
                // 价格/库存HTML
                if (res.data.htmlTable) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data.htmlTable);
                    // 合并单元格
                    MergeCells();
                }
            }
        }
    });
}

// 清除数据库指定数据
function clearPresetSpecValue(obj) {
    // 当选对象
    var spec_mark_id = $(obj).attr('data-spec_mark_id');
    var spec_value_id = $(obj).attr('data-spec_value_id');
    // 执行处理
    $.ajax({
        type: 'post',
        url : delProductCustomSpec,
        data: {aid: aid, spec_mark_id: spec_mark_id, spec_value_id: spec_value_id, del: 'specValue'},
        dataType: 'json',
        success: function(res) {
            // 删除当前点击规格块的顶级html
            $(obj).parent().parent().remove();
            // 规格值加载
            if (res.data.spec_value_option) $('#spec_value_'+spec_mark_id).empty().html(res.data.spec_value_option);
            // 如果存在则执行覆盖新的规格列表框
            if (res.data.htmlTable) {
                // 加载规格框架
                $('#SpecTempLateDiv').html(res.data.htmlTable);
                // 合并单元格
                MergeCells();
            }
        }
    });
}

// 批量设置价格
function BulkSetPrice(obj) {
    layer.prompt({
        title: false, 
        shade: layer_shade,
        formType: 3,
        id: 'BulkSetPrice',
        btn: ['确定', '关闭'],
        closeBtn: 0,
        success: function(layero, index){
            $("#BulkSetPrice").find('input').attr('placeholder', '批量设置价格');
            $("#BulkSetPrice").find('input').attr('onkeyup', "this.value=this.value.replace(/^\\D*([0-9]\\d*\.\?\\d{0,2})?.*$/, '$1');");
            $("#BulkSetPrice").find('input').attr('onpaste', "this.value=this.value.replace(/^\\D*([0-9]\\d*\\.?\\d{0,2})?.*$/, '$1');");
        }
    }, function(price, index){
        layer.close(index);
        // 规格中的价格
        $('.users_price').val(price);
        // 产品价格
        $('#users_price').val(price);

        // 保存编辑商品价格库存
        setProductSpecPrice();
    });
}

// 批量设置划线价
function BulkSetCrossedPrice() {
    layer.prompt({
        title: false, 
        shade: layer_shade,
        formType: 3,
        id: 'BulkSetCrossedPrice',
        btn: ['确定', '关闭'],
        closeBtn: 0,
        success: function(layero, index){
            $("#BulkSetCrossedPrice").find('input').attr('placeholder', '批量设置划线价');
            $("#BulkSetCrossedPrice").find('input').attr('onkeyup', "this.value=this.value.replace(/^\\D*([0-9]\\d*\.\?\\d{0,2})?.*$/, '$1');");
            $("#BulkSetCrossedPrice").find('input').attr('onpaste', "this.value=this.value.replace(/^\\D*([0-9]\\d*\\.?\\d{0,2})?.*$/, '$1');");
        }
    }, function(price, index){
        layer.close(index);
        // 规格中的划线价
        $('.crossed_price').val(price);
        // 商品划线价
        $('#crossed_price').val(price);

        // 保存编辑商品价格库存
        setProductSpecPrice();
    });
}

// 保存编辑商品价格库存
function setProductSpecPrice() {
    $.ajax({
        type: 'post',
        url : editProductSpecPrice,
        data: $('#post_form').serialize(),
        dataType: 'json',
        success: function(res) {
            if (1 === parseInt(res.code)) {
                // 价格/库存HTML
                if (res.data) {
                    // 加载规格框架
                    $('#SpecTempLateDiv').html(res.data);
                    // 合并单元格
                    MergeCells();
                }
            } else {
                showErrorMsg(res.msg);
            }
        }
    });
}

// 更新价格，取出最小的值赋值到价格中
function UpPrice(usersPrice) {
    var usersPriceArray = new Array();
    $('.users_price').each(function(){
        usersPriceArray.push($(this).val());
    });
    // 取出最小值
    usersPrice = usersPrice ? usersPrice : 0;
    var minValue = usersPriceArray.length > 0 ? Math.min.apply(Math, usersPriceArray) : usersPrice;
    $('#users_price').val(minValue);
}

// 批量设置价格
function BulkSetStock(obj) {
    layer.prompt({
        title: false, 
        shade: layer_shade,
        formType: 3,
        id: 'BulkSetStock',
        btn: ['确定', '关闭'],
        closeBtn: 0,
        success: function(layero, index){
            $("#BulkSetStock").find('input').attr('placeholder', '批量设置库存');
            $("#BulkSetStock").find('input').attr('onkeyup', "this.value=this.value.replace(/[^\\d]/g,'')");
            $("#BulkSetStock").find('input').attr('onpaste', "this.value=this.value.replace(/[^\\d]/g,'')");
        }
    }, function(stock, index){
        layer.close(index);
        // 单个库存
        $('.stock_count').val(stock);
        $('.stock_count').attr('data-old_stock',stock);
        stock_count = Number($('.stock_count').length) * Number(stock);
        $('#stock_count').val(stock_count);
        // 禁用库存框
        $('#stock_count').attr('readonly','readonly');
    });
}
// 批量虚拟销量
function BulkSetSales(obj) {
    layer.prompt({
        formType: 3,
        shade: layer_shade,
        id: 'BulkSetSales',
        title: '批量虚拟销量',
        btn: ['确定', '关闭'],
        btnAlign:'r',
        closeBtn: 3,
        success: function(layero, index){
            $("#BulkSetSales").find('input').attr('placeholder', '批量虚拟销量');
            $("#BulkSetSales").find('input').attr('onkeyup', "this.value=this.value.replace(/[^\\d.]/g,'')");
            $("#BulkSetSales").find('input').attr('onpaste', "this.value=this.value.replace(/[^\\d.]/g,'')");
        }
    }, function(stock, index) {
        layer.close(index);
        // 单个库存
        $('.spec_sales_num').val(stock);
        $('.spec_sales_num').attr('data-old_stock',stock);
        stock_count = Number($('.spec_sales_num').length) * Number(stock);
        $('#spec_sales_num').val(stock_count);
        // 禁用库存框
        $('#spec_sales_num').attr('readonly', 'readonly');
    });
}
// 批量商品编码
function BulkSetCode(obj) {
    layer.prompt({
        formType: 3,
        shade: layer_shade,
        id: 'BulkSetCode',
        title: '批量商品编码',
        btn: ['确定', '关闭'],
        btnAlign:'r',
        closeBtn: 3,
        success: function(layero, index){
            $("#BulkSetCode").find('input').attr('placeholder', '批量商品编码');
            $("#BulkSetCode").find('input').attr('onkeyup', "this.value=this.value.replace(/[^\\d.]/g,'')");
            $("#BulkSetCode").find('input').attr('onpaste', "this.value=this.value.replace(/[^\\d.]/g,'')");
        }
    }, function(stock, index) {
        layer.close(index);
        // 单个库存
        $('.stock_code').val(stock);
        $('.stock_code').attr('data-old_stock',stock);
        stock_count = Number($('.stock_code').length) * Number(stock);
        $('#stock_code').val(stock_count);
        // 禁用库存框
        $('#stock_code').attr('readonly', 'readonly');
    });
}
// 更新库存量
function UpStock(obj) {
    var stock_count = $('#stock_count').val();
    var new_stock = $(obj).val();
    var old_stock = $(obj).attr('data-old_stock');
    $(obj).attr('data-old_stock',new_stock);
    if (Number(old_stock) > Number(new_stock)) {
        new_stock   = Number(old_stock) - Number(new_stock);
        stock_count = Number(stock_count) - Number(new_stock);
    }else{
        new_stock   = Number(new_stock) - Number(old_stock);
        stock_count = Number(stock_count) + Number(new_stock);
    }
    $('#stock_count').val(stock_count);
    $('#stock_count').attr('readonly','readonly');
}


function openGoodsSpecImage(obj) {
    var checked = $(obj).is(':checked') ? 1 : 0;
    $.ajax({
        type: 'post',
        url : goodsSpecImage,
        data: {action: 'open', aid: aid, checked: checked, spec_mark_id: $(obj).val()},
        dataType: 'json',
        success: function(res) {
            if (1 === parseInt(res.code)) {
                if ($(obj).is(':checked')) {
                    $('.openGoodsSpecImage_' + $(obj).val()).css('display', 'block');
                } else {
                    $('.openGoodsSpecImage_' + $(obj).val()).css('display', 'none');
                }
            } else {    
                showErrorMsg(res.msg);
            }
        }
    });
}

var spec_mark_id;
var spec_value_id;
function goodsSpecImageAdd(specMarkID, specValueID) {
    spec_mark_id = specMarkID;
    spec_value_id = specValueID;
    GetUploadify(1, '', 'allimg', 'goodsSpecImageCallBack');
}

function goodsSpecImageCallBack(spec_image) {
    if (spec_mark_id && spec_value_id) {
        $.ajax({
            type: 'post',
            url : goodsSpecImage,
            data: {aid: aid, spec_image: spec_image, spec_mark_id: spec_mark_id, spec_value_id: spec_value_id},
            dataType: 'json',
            success: function(res) {
                if (1 === parseInt(res.code)) {
                    $('.spec_image_src_'+spec_value_id).show().attr('src', spec_image);
                    $('.spec_image_y_line_'+spec_value_id+', .spec_image_x_line_'+spec_value_id).hide();
                    if (spec_image) $('.spec_image_delect_'+spec_value_id).attr('style', '');
                } else {    
                    showErrorMsg(res.msg);
                }
            }
        });
    } else {
        showErrorMsg('规格数据异常，上传失败，刷新重试');
    }
}

function goodsSpecImageClear(specMarkID, specValueID) {
    if (specMarkID && specValueID) {
        $.ajax({
            type: 'post',
            url : goodsSpecImage,
            data: {aid: aid, spec_image: '', spec_mark_id: specMarkID, spec_value_id: specValueID},
            dataType: 'json',
            success: function(res) {
                if (1 === parseInt(res.code)) {
                    $('.spec_image_src_'+specValueID).hide().attr('src', '');
                    $('.spec_image_y_line_'+specValueID+', .spec_image_x_line_'+specValueID).show();
                    $('.spec_image_delect_'+specValueID).attr('style', 'display: none;');
                } else {    
                    showErrorMsg(res.msg);
                }
            }
        });
    } else {
        showErrorMsg('规格数据异常，删除失败，刷新重试');
    }
}

// 合并单元格
function MergeCells() {
    // return false;
    var tab = document.getElementById("spec_input_tab");
    var maxCol = 3, val, count, start; 
    if (tab != null) {
        for (var col = maxCol - 1; col >= 0; col--) {
            count = 1;
            val = "";
            for (var i = 0; i < tab.rows.length; i++) {
                if (val == tab.rows[i].cells[col].innerHTML) {
                    count++;
                } else {
                    if (count > 1) { //合并
                        start = i - count;
                        tab.rows[start].cells[col].rowSpan = count;
                        for (var j = start + 1; j < i; j++) {
                            tab.rows[j].cells[col].style.display = "none";
                        }
                        count = 1;
                    }
                    val = tab.rows[i].cells[col].innerHTML;
                }
            }
            if (count > 1) { //合并，最后几行相同的情况下
                start = i - count;
                tab.rows[start].cells[col].rowSpan = count;
                for (var j = start + 1; j < i; j++) {
                    tab.rows[j].cells[col].style.display = "none";
                }
            }
        }
    }
}