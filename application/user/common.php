<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

// 模板错误提示
use think\Db;

switch_exception();
if (!function_exists('users_log_off')) {

    //会员注销前台标签
    function users_log_off()
    {
        $users_open_log_off = getUsersConfigData('users.users_open_log_off','', 'cn'); // 开启注销

        //检测插件
        if (empty($users_open_log_off)){
            return false;
        }

        $field['display'] = '';
        $field['text'] = '申请注销';
        $field['func'] = " onclick='ajax_users_log_off_1017(this);' ";
        $users_id = session('users_id');
        if (empty($users_open_log_off)) {
            $field['display'] = 'none';
        }
        $where['users_id'] = $users_id;
        $where['status'] = ['in', [0, 2]];
        $info = Db::name('users_log_off')->where($where)->order('id desc')->find();
        if (!empty($info) && 2 == $info['status']) {
            $field['text'] = '拒绝注销<span style="color: red;">(拒绝原因:' . $info['refuse_reason'] . ')</span>';
        } elseif (!empty($info) && 0 == $info['status']) {
            $field['text'] = '审核中';
            $field['func'] = '';
        }
        $url = url('user/Users/log_off');
        $field['hidden'] = <<<EOF
<script>
function ajax_users_log_off_1017(obj) {
    var title = '此操作不可恢复，确定注销账号？';
    var btn = ['确定', '取消']; //按钮
    // 删除按钮
    layer.confirm(title, {
        title: false,
        btn: btn //按钮
    }, function () {
        $.ajax({
            type: 'POST',
            url: '{$url}',
            data: {_ajax:1},
            dataType: 'json',
            success: function (data) {
                layer.closeAll();
                if(data.code == 1){
                    layer.msg(data.msg, {icon:1, time: 1000}, function(){
                        window.location.reload();
                    });
                }else{
                    layer.alert(data.msg, {icon: 2, title:false});
                }
            },
            error:function(){
                layer.closeAll();
            }
        });
    }, function (index) {
        layer.closeAll(index);
    });
}
</script>
EOF;

        if (empty($field['display'])) {
            return [$field];
        } else {
            return [];
        }
    }
}

if (!function_exists('show_foreign_lang')) 
{
    /**
     * 第五套会员中心的中文处理
     */
    function show_foreign_lang($text = '')
    {
        $arr = [];
        $arr[md5('会员确认收货')] = "Member confirms receipt of goods";
        $arr[md5('提醒成功')] = "Reminder successful";
        $arr[md5('待核销')] = "Pending verification";
        $arr[md5('货到付款')] = "cash on delivery";
        $arr[md5('请填写收货人姓名')] = "Please fill in the recipient's name";
        $arr[md5('请填写收货人手机')] = "Please fill in the recipient's mobile phone number";
        $arr[md5('请完善收货地址')] = "Please provide a complete shipping address";
        $arr[md5('请填写详细地址')] = "Please provide a detailed address";
        $arr[md5('操作成功')] = "Operation successful";
        $arr[md5('操作失败')] = "Operation failed";
        $arr[md5('数据有误')] = "Data is incorrect";
        $arr[md5('非法访问')] = "illegal access";
        $arr[md5('请选择要购买的商品！')] = "Please select the product you want to purchase!";
        $arr[md5('请选择数量！')] = "Please select the quantity!";
        $arr[md5('立即购买！')] = "Buy now!";
        $arr[md5('该商品不存在或已下架！')] = "This product does not exist or has been taken down!";
        $arr[md5('加入购物车成功！')] = "Successfully added to shopping cart!";
        $arr[md5('加入购物车失败！')] = "Failed to add to shopping cart!";
        $arr[md5('商品数量最少为1')] = "The minimum quantity of goods is 1";
        $arr[md5('订单支付异常，请刷新重新下单~')] = "Order payment exception, please refresh and place a new order~";
        $arr[md5('订单生成失败，没有相应的商品！')] = "Order generation failed, there are no corresponding products!";
        $arr[md5('订单生成失败，商品数据有误')] = "Order generation failed, product data is incorrect";
        $arr[md5('请选择自提门店')] = "Please select a self pickup store";
        $arr[md5('请输入用户姓名')] = "Please enter the user's name";
        $arr[md5('请输入预留手机号')] = "Please enter the reserved phone number";
        $arr[md5('预留手机号格式不正确')] = "The reserved phone number format is incorrect";
        $arr[md5('订单生成失败，商品数量有误！')] = "Order generation failed, incorrect quantity of goods!";
        $arr[md5('订单生成失败，提交来源有误！')] = "Order generation failed, submission source is incorrect!";
        $arr[md5('不可为空！')] = "Cannot be empty!";
        $arr[md5('不可连续提交订单！')] = "Orders cannot be submitted consecutively!";
        $arr[md5('余额不足，支付失败！')] = "Insufficient balance, payment failed!";
        $arr[md5('请选择正确的支付方式！')] = "Please choose the correct payment method!";
        $arr[md5('订单号错误')] = "Order number incorrect";
        $arr[md5('商品已售罄！')] = "The product is sold out!";
        $arr[md5('商品最大库存：')] = "Maximum inventory of goods:";
        $arr[md5('[商品已停售]')] = "[The product has been discontinued]";
        $arr[md5('重复密码与新密码不一致！')] = "The repeated password does not match the new password!";
        $arr[md5('原密码错误，请重新输入！')] = "The original password is incorrect, please re-enter!";
        $arr[md5('登录失效，请重新登录！')] = "Login failed, please log in again!";
        $arr[md5('上传失败')] = "Upload failed";
        $arr[md5('上传成功')] = "Upload successful";
        if ('v5' == getUsersTplVersion()) {
            $text = empty($arr[md5($text)]) ? $text : $arr[md5($text)];
        }
        return $text;
    }
}