<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-4-13
 */

namespace think\template\taglib\eyou;

use think\Config;
use think\Db;
use think\Cookie;

/**
 * 订单明细
 */
load_trait('controller/Jump');
class TagSporder extends Base
{
    use \traits\controller\Jump;

    public $users_id = 0;
    public $usersTplVersion    = '';
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->users_id = session('users_id');
        $this->usersTplVersion = getUsersTplVersion();
        // 是否安装核销插件
        $this->weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
    }

    /**
     * 获取订单明细数据
     */
    public function getSporder($order_id)
    {
        if (empty($order_id)) {
            $order_id = input('param.order_id');
        }

        if (!empty($order_id)) {
            // 公共条件
            $Where = [
                'a.order_id' => $order_id,
                'a.users_id' => $this->users_id,
                'a.lang'     => self::$home_lang,
            ];

            // 订单主表
            $result['OrderData'] = Db::name("shop_order")->alias('a')->where($Where)->find();
            if (empty($result['OrderData'])) $this->error('订单不存在');

            $jsStr = '';
            $result['OrderData']['verifyData'] = [];
            if (!empty($this->weappInfo) && 2 === intval($result['OrderData']['logistics_type'])) {
                // 调用核销逻辑层方法
                $verifyLogic = new \weapp\Verify\logic\VerifyLogic;
                $result['OrderData']['verifyData'] = $verifyLogic->verifyOrderShowHandle($this->users_id, $result['OrderData'], $this->weappInfo);
                if (1 === intval($result['OrderData']['order_status'])) {
                    $jsStr = "<script language='javascript' type='text/javascript' src='{$this->root_dir}/public/static/common/js/qrcode.js'></script>
                    <script language='javascript' type='text/javascript' src='{$this->root_dir}/public/static/common/js/qrcode.min.js'></script>
                    <script type='text/javascript'>
                        outputQRCod('{$result['OrderData']['verifyData']['verify_code']}');
                    </script>";
                }
            }
            // dump($result);exit;

            // 如果是未付款订单则执行
            $result['OrderData']['paymentExpire'] = 0;
            $result['OrderData']['eyCountdownTimes'] = 'eyCountdownTimes';
            if (isset($result['OrderData']['order_status']) && 0 === intval($result['OrderData']['order_status'])) {
                // 查询 自动关闭未付款订单 时长
                $orderUnpayCloseTime = getUsersConfigData('order.order_unpay_close_time');
                if (!empty($orderUnpayCloseTime)) {
                    // 未付款过期时间
                    $result['OrderData']['paymentExpire'] = $result['OrderData']['add_time'] + (intval($orderUnpayCloseTime) * 60) - getTime();
                }
            }
            //  else if (isset($result['OrderData']['order_status']) && 2 === intval($result['OrderData']['order_status'])) {
            //     // 查询 自动关闭未付款订单 时长
            //     $orderAutoReceiptTime = getUsersConfigData('order.order_auto_receipt_time');
            //     if (!empty($orderAutoReceiptTime)) {
            //         // 未付款过期时间
            //         $express_time = getTime() - $result['OrderData']['express_time'];
            //         $result['OrderData']['autoReceipt'] = (intval($orderAutoReceiptTime) * 86400) - $express_time;
            //     }
            // }

            //虚拟商品拼接回复
            $virtual_delivery = '';
            // 获取当前链接及参数，用于手机端查询快递时返回页面
            $ReturnUrl = request()->url(true);
            // 封装查询物流链接
            $result['OrderData']['LogisticsInquiry'] = $MobileExpressUrl = '';
            if (('2' == $result['OrderData']['order_status'] || '3' == $result['OrderData']['order_status']) && empty($result['OrderData']['prom_type'])) {
                // 移动端查询物流链接
                $result['OrderData']['MobileExpressUrl'] = "//m.kuaidi100.com/index_all.html?type=".$result['OrderData']['express_code']."&postid=".$result['OrderData']['express_order']."&callbackurl=".$ReturnUrl;

                if (isMobile()) {
                    $MobileExpressUrl = "//m.kuaidi100.com/index_all.html?type=".$result['OrderData']['express_code']."&postid=".$result['OrderData']['express_order']."&callbackurl=".$ReturnUrl;
                } else {
                    $MobileExpressUrl = "https://www.kuaidi100.com/chaxun?com=".$result['OrderData']['express_code']."&nu=".$result['OrderData']['express_order'];
                }

                $result['OrderData']['LogisticsInquiry'] = " onclick=\"LogisticsInquiry('{$MobileExpressUrl}');\" ";
            }
            // 是否移动端，1表示移动端，0表示PC端
            $result['OrderData']['IsMobile'] = isMobile() ? 1 : 0;

            // 获取订单状态列表
            $order_status_arr = Config::get('global.order_status_arr');
            // 封装获取订单支付方式名称
            $pay_method_arr = Config::get('global.pay_method_arr');
            if ('v5' == $this->usersTplVersion) {
                foreach ($order_status_arr as $key => $val) {
                    if (isset($this->order_status_arr[$key])) {
                        $val = $this->order_status_arr[$key];
                    }
                    $order_status_arr[$key] = $val;
                }
                foreach ($pay_method_arr as $key => $val) {
                    if (isset($this->pay_method_arr[$key])) {
                        $val = $this->pay_method_arr[$key];
                    }
                    $pay_method_arr[$key] = $val;
                }
            }

            $result['OrderData']['order_status_name'] = $order_status_arr[$result['OrderData']['order_status']];
            if (!empty($this->weappInfo) && 2 === intval($result['OrderData']['logistics_type']) && 1 === intval($result['OrderData']['order_status'])) {
                $result['OrderData']['order_status_name'] = show_foreign_lang('待核销');
            }
            $result['OrderData']['TotalAmount'] = '0';
            
            if (!empty($result['OrderData'])) {
                // 订单明细表
                $result['DetailsData'] = Db::name("shop_order_details")
                    ->alias('a')
                    ->field('a.*, b.is_del')
                    ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                    ->order('product_price desc, product_name desc')
                    ->where($Where)
                    ->select();

                $array_new = get_archives_data($result['DetailsData'], 'product_id');
                $virtual_delivery_status = false;
                // 产品处理
                foreach ($result['DetailsData'] as $key => $value) {
                    //虚拟需要自动发货的商品卖家回复拼接
                    if ($value['prom_type'] == 2 && 2 <= intval($result['OrderData']['order_status'])) {
                        $virtual_delivery_status = true;
                        //查询商品名称
                        $product_title = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                        //查网盘信息
                        $netdisk = Db::name("product_netdisk")->where('aid', $value['product_id'])->find();
                        if ($netdisk) {
                            $virtual_delivery .= "<b>商品标题：</b>" . $product_title . "</br>";
                            $virtual_delivery .= "<b>网盘地址：</b> <a target='_blank' href=" . $netdisk['netdisk_url'] . ">" . $netdisk['netdisk_url'] . "</a></br>";
                            if (!empty($netdisk['netdisk_pwd'])) {
                                $virtual_delivery .= "<b>提取码：</b>" . $netdisk['netdisk_pwd'] . "</br>";
                            }
                            if (!empty($netdisk['unzip_pwd'])) {
                                $virtual_delivery .= "<b>解压密码：</b>" . $netdisk['unzip_pwd'] . "</br>";
                            }
                            $virtual_delivery .= "--------------------</br>";
                        }
                    } elseif ($value['prom_type'] == 3 && 2 <= intval($result['OrderData']['order_status'])) {
                        $virtual_delivery_status = true;
                        //查询商品名称
                        $product_title = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                        //查网盘信息
                        $netdisk = Db::name("product_netdisk")->where('aid', $value['product_id'])->find();
                        if ($netdisk) {
                            $virtual_delivery .= "<b>商品标题：</b>" . $product_title . "</br>";
                            $virtual_delivery .= "<b>文本内容：</b>" . $netdisk['text_content'] . "</br>";
                            $virtual_delivery .= "--------------------</br>";
                        }
                    }elseif ($value['prom_type'] == 1) {
                        $virtual_delivery_status = true;
                        //查询商品名称
                        if (!empty($result['OrderData']['virtual_delivery'])){
                            $product_title = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                            $virtual_delivery .= "<b>商品标题：</b>" . $product_title . "</br>";
                            $virtual_delivery .= $result['OrderData']['virtual_delivery'] . "</br>";
                            $virtual_delivery .= "--------------------</br>";
                        }
                    }
                    // 产品属性处理
                    $ValueData = unserialize($value['data']);
                    $result['DetailsData'][$key]['pointsGoodsBuyField'] = !empty($ValueData['pointsGoodsBuyField']) ? json_decode($ValueData['pointsGoodsBuyField'], true) : [];
                    $spec_value = !empty($ValueData['spec_value']) ? htmlspecialchars_decode($ValueData['spec_value']) : '';
                    $spec_value = htmlspecialchars_decode($spec_value);
                    $product_spec_list = [];
                    $spec_value_arr = explode('<br/>', $spec_value);
                    foreach ($spec_value_arr as $sp_key => $sp_val) {
                        $sp_arr = explode('：', $sp_val);
                        if (trim($sp_arr[0]) && !empty($sp_arr[0])) {
                            $product_spec_list[] = [
                                'name'  => !empty($sp_arr[0]) ? trim($sp_arr[0]) : '',
                                'value' => !empty($sp_arr[1]) ? trim($sp_arr[1]) : '',
                            ];
                        }
                    }
                    $result['DetailsData'][$key]['product_spec_list'] = $product_spec_list;

                    // 旧参数+规格值
                    $attr_value = !empty($ValueData['attr_value']) ? htmlspecialchars_decode($ValueData['attr_value']) : '';
                    $attr_value = htmlspecialchars_decode($attr_value);
                    $result['DetailsData'][$key]['data'] = $spec_value . $attr_value;

                    // 新参数+规格值
                    $attr_value_new = !empty($ValueData['attr_value_new']) ? htmlspecialchars_decode($ValueData['attr_value_new']) : '';
                    $attr_value_new = htmlspecialchars_decode($attr_value_new);
                    $result['DetailsData'][$key]['new_data'] = $spec_value . $attr_value_new;
                    
                    // 产品内页地址
                    if (!empty($array_new[$value['product_id']]) && 0 == $value['is_del']) {
                        // 商品存在
                        $arcurl = urldecode(arcurl('home/Product/view', $array_new[$value['product_id']]));
                        $has_deleted = 0;
                        $msg_deleted = '';
                    } else {
                        // 商品不存在
                        $arcurl = urldecode(url('home/View/index', ['aid'=>$value['product_id']]));
                        $has_deleted = 1;
                        $msg_deleted = '[商品已停售]';
                    }
                    $result['DetailsData'][$key]['arcurl'] = $arcurl;
                    $result['DetailsData'][$key]['has_deleted'] = $has_deleted;
                    $result['DetailsData'][$key]['msg_deleted'] = $msg_deleted;
                    $result['DetailsData'][$key]['product_price'] = floatval($value['product_price']);

                    // 图片处理
                    $result['DetailsData'][$key]['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));

                    // 小计
                    $result['DetailsData'][$key]['subtotal'] = $value['product_price'] * $value['num'];
                    $result['DetailsData'][$key]['subtotal'] = floatval(sprintf("%.2f", $result['DetailsData'][$key]['subtotal']));
                    // 合计金额
                    $result['OrderData']['TotalAmount'] += $result['DetailsData'][$key]['subtotal'];
                    $result['OrderData']['TotalAmount'] = floatval(sprintf("%.2f", $result['OrderData']['TotalAmount']));

                    // 去掉金额小数点末尾的0
                    $result['OrderData']['shipping_fee'] = floatval(sprintf("%.2f", $result['OrderData']['shipping_fee']));
                    $result['OrderData']['order_amount'] = floatval(sprintf("%.2f", $result['OrderData']['order_amount']));
                }
                if (!empty($virtual_delivery)){
                    $result['OrderData']['virtual_delivery'] = $virtual_delivery;
                }
                if (empty($result['OrderData']['order_status'])) {
                    // 付款地址处理，对ID和订单号加密，拼装url路径
                    $Paydata = [
                        'order_id'   => $result['OrderData']['order_id'],
                        'order_code' => $result['OrderData']['order_code'],
                    ];
                    // 先 json_encode 后 md5 加密信息
                    $Paystr = md5(json_encode($Paydata));
                    // 清除之前的 cookie
                    Cookie::delete($Paystr);
                    // 存入 cookie
                    cookie($Paystr, $Paydata);
                    // 跳转链接
                    $result['OrderData']['PaymentUrl'] = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$Paystr]));
                }

                // 存在积分商城订单则执行
                if (!empty($result['OrderData']['points_shop_order'])) {
                    $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
                    if (!empty($weappInfo)) {
                        $list = !empty($result['OrderData']) ? $result['OrderData'] : [];
                        $list['Details'] = !empty($result['DetailsData']) ? $result['DetailsData'] : [];
                        $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                        $pointsShopLogic->pointsShopOrderDataHandle([$list], $result['OrderData'], $result['DetailsData']);
                    }
                }

                // 处理订单主表的地址数据
                $result['OrderData']['country']  = ('v5' == $this->usersTplVersion) ? get_country_name($result['OrderData']['country']) : '中国';
                $result['OrderData']['province'] = get_province_name($result['OrderData']['province']);
                $result['OrderData']['city']     = get_city_name($result['OrderData']['city']);
                $result['OrderData']['district'] = get_area_name($result['OrderData']['district']);

                if (!empty($result['OrderData']['payment_method'])) {
                    $result['OrderData']['pay_name'] = $pay_method_arr['delivery_pay'];
                } else {
                    $pay_name = '未支付';
                    if (!empty($result['OrderData']['pay_name'])){
                        $pay_name = !empty($pay_method_arr[$result['OrderData']['pay_name']]) ? $pay_method_arr[$result['OrderData']['pay_name']] : '第三方支付';
                    }
                    $result['OrderData']['pay_name'] = $pay_name;
                }
                // 封装取消订单JS
                $result['OrderData']['CancelOrder']   = " onclick=\"CancelOrder('{$order_id}');\" ";
                // 封装收货地址
                if ($this->usersTplVersion == 'v3') {
                    $result['OrderData']['ConsigneeInfo'] = $result['OrderData']['province'].' '.$result['OrderData']['city'].' '.$result['OrderData']['district'].' '.$result['OrderData']['address'];
                } else {
                    $result['OrderData']['ConsigneeInfo'] = $result['OrderData']['consignee'].' '.$result['OrderData']['mobile'].' '.$result['OrderData']['country'].' '.$result['OrderData']['province'].' '.$result['OrderData']['city'].' '.$result['OrderData']['district'].' '.$result['OrderData']['address'];
                }
                //售后
                $result['OrderData']['ServiceList'] = urldecode(url('user/Shop/service_list', ['order_id' => $order_id]));
                // 封装订单催发货JS
                $result['OrderData']['OrderRemind'] = " onclick=\"OrderRemind('{$order_id}','{$result['OrderData']['order_code']}');\" ";
                //评价
                $result['OrderData']['AddProduct'] = urldecode(url('user/ShopComment/comment_list', ['order_id' => $order_id]));
                //确认收货
                $result['OrderData']['Confirm'] = " onclick=\"Confirm('{$order_id}','{$result['OrderData']['order_code']}');\" ";

                // 传入JS参数
                $data['is_wap'] = isWeixin() || isMobile() ? 1 : 0;
                $data['shop_centre'] = url('user/Shop/shop_centre');
                $data['shop_order_cancel'] = url('user/Shop/shop_order_cancel', ['_ajax'=>1], true, false, 1, 1, 0);
                $data['shop_order_remind'] = url('user/Shop/shop_order_remind', ['_ajax'=>1], true, false, 1, 1, 0);
                $data['shop_member_confirm'] = url('user/Shop/shop_member_confirm', ['_ajax'=>1], true, false, 1, 1, 0);
                // $data['autoReceipt'] = $result['OrderData']['autoReceipt'];
                $data['paymentExpire'] = $result['OrderData']['paymentExpire'];
                $data['eyCountdownTimes'] = $result['OrderData']['eyCountdownTimes'];
                $data_json = json_encode($data);
                $version   = getCmsVersion();
                if (empty($this->usersTplVersion) || 'v1' == $this->usersTplVersion) {
                    $jsfile = "tag_sporder.js";
                } else {
                    $jsfile = "tag_sporder_{$this->usersTplVersion}.js";
                }
                // 循环中第一个数据带上JS代码加载
                if ($result['OrderData']['prom_type'] == 0 && $virtual_delivery_status) {
                    $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/tag_sporder.js?v={$version}");
                    $result['OrderData']['hidden'] = <<<EOF
<div style="display: none;" id="virtual_delivery_v423534">
<div class='col-xs-4 col-md-3 col-xl-2 text-sm-left order-info-name'>商家回复 :</div><div class='col-xs-8 col-md-9 col-xl-10' >{$result['OrderData']['virtual_delivery']}</div>
</div>
<script type="text/javascript">
    var eeb8a85ee533f74014310e0c0d12778 = {$data_json};
    var panel_body = document.getElementsByClassName("panel-body order-info")[0];
    var dom=document.createElement('div');
    dom.className='row m-t-10';
    dom.innerHTML=document.getElementById('virtual_delivery_v423534').innerHTML;
    panel_body.appendChild(dom);
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
                }else{
                    $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
                    $result['OrderData']['hidden'] = <<<EOF
<script type="text/javascript">
    var eeb8a85ee533f74014310e0c0d12778 = {$data_json};
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
{$jsStr}
EOF;
                }
                // dump($result);exit;
                return $result;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
}