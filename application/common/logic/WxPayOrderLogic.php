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

namespace app\common\logic;

use think\Db;


class WxPayOrderLogic
{

    public function __construct()
    {
    }

    /**
     * 接口转化 需要与可视化微信商城weapp\DiyminiproMall\logic\DiyminiproMallLogic.php get_api_url()一致;
     */
    public function get_api_url($query_str)
    {
        $apiUrl = 'aHR0cDovL3NlcnZpY2UuZXl5c3ouY24=';
        return base64_decode($apiUrl) . $query_str;
    }

    /**
     * shop_order 发货推送微信小程序
     * 订单来源(1:会员普通充值订单; 2:会员商城商品订单; 3:会员升级订单; 8:会员视频订单;9-文章订单;10-下载订单; 20:会员套餐充值订单;)
     * @return boolean
     */
    public function minipro_send_goods($order = [], $orderSource = 2, $item_desc = '')
    {
        if (2 === intval($orderSource)) {
            $where = [
                'users_id' => intval($order['users_id']),
                'order_code' => trim($order['order_code'])
            ];
            $order = Db::name('shop_order')->where($where)->find();
        }
        $weixin_data = model('ShopPublicHandle')->getWxShippingInfo($order['users_id'], $order['order_code'], $orderSource);
        if (empty($weixin_data) || empty($weixin_data['pay_config'])) return false;
        if (0 === intval($weixin_data['errcode']) && !empty($weixin_data['errmsg'])) return true;

        $post_data['order_key']['order_number_type'] = 1; //订单单号类型 - 枚举值1，使用下单商户号和商户侧单号；枚举值2，使用微信支付单号。
        $post_data['order_key']['out_trade_no'] = $order['order_code'];
        $post_data['order_key']['mchid'] = $weixin_data['pay_config']['mchid'];

        //物流模式，发货方式枚举值：
        // 1、实体物流配送采用快递公司进行实体物流配送形式
        // 2、同城配送
        // 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式
        // 4、用户自提
        $post_data['delivery_mode'] = 1; //发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
        if (2 == $orderSource) {
            //快递发货
            if (0 == $order['prom_type'] && 1 == $order['logistics_type']) {
                $post_data['logistics_type'] = 1;
                $shipping_list['tracking_no'] = $order['express_order'];
                $shipping_list['express_company'] = Db::name('shop_express')->where('express_name', $order['express_name'])->value('wx_delivery_id');
                if (empty($shipping_list['express_company'])) {
                    model('ShopPublicHandle')->updateWxShippingInfo($order['users_id'], $order['order_code'], $orderSource, -1, '无法获取该物流公司编号,请手动发货');
                    return false;
                }
                //当发货的物流公司为顺丰时，联系方式为必填
                if ('SF' == $shipping_list['express_company']) $shipping_list['contact']['receiver_contact'] = $order['mobile'];
            } elseif (0 == $order['prom_type'] && 2 == $order['logistics_type']) { //核销
                $post_data['logistics_type'] = 4;
            }else{ //虚拟商品 prom_type 为1或2或3
                $post_data['logistics_type'] = 3;
            }
        } else {
            $post_data['logistics_type'] = 3;//虚拟商品
        }

        if (2 == $orderSource) {
            $product_data = \think\Db::name('shop_order_details')->where('order_id', $order['order_id'])->field('product_name,num')->select();
            foreach ($product_data as $k => $v) {
                $item_desc .= "{$v['product_name']} * {$v['num']};";
            }
            if (120 < count($item_desc)) {
                $item_desc = substr($item_desc, 0, 115);
                $item_desc .= '...';
            }
        }

        $shipping_list['item_desc'] = $item_desc;
        $post_data['shipping_list'][] = $shipping_list;
        $post_data['upload_time'] = date(\DateTime::RFC3339);

        $params = [];
        //可视化小程序走第三方服务商推送
        if (!empty($weixin_data['pay_config']) && 'DiyminiproMall' == $weixin_data['pay_config']['plugins']) {
            $url = $this->get_api_url("/index.php?m=api&c=Minipro&a=get_authorizer_access_token");
            $data['appid'] = $weixin_data['pay_config']['appid'];
            $post_data['payer']['openid'] = \think\Db::name('weapp_diyminipro_mall_users')->where('users_id', $order['users_id'])->value('openid');
            $data['post_data'] = $post_data;
            $response = httpRequest($url, "POST", $data);
            $params = json_decode($response, true);
            if (0 === $params['errcode']) {
                model('ShopPublicHandle')->updateWxShippingInfo($order['users_id'], $order['order_code'], $orderSource, $params['errcode'], $params['errmsg']);
                return false;
            }
            model('ShopPublicHandle')->updateWxShippingInfo($order['users_id'], $order['order_code'], $orderSource, -1, '第三方服务商推送发货请求失败');
            return false;
        }
        $appid = $weixin_data['pay_config']['appid'];
        $appsecret = $weixin_data['pay_config']['appsecret'];
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
        $response = httpRequest($url);
        $params = json_decode($response, true);
        if (isset($params['access_token'])) {
            $post_data['payer']['openid'] = \think\Db::name('wx_users')->where('users_id', $order['users_id'])->value('openid');
            $url = "https://api.weixin.qq.com/wxa/sec/order/upload_shipping_info?access_token={$params['access_token']}";
            $response = httpRequest($url, 'POST', json_encode($post_data, JSON_UNESCAPED_UNICODE));
            $params = json_decode($response, true);
            if (48001 == $params['errcode']) $params['errmsg'] .= "(该小程序没有发货信息管理能力)";
            model('ShopPublicHandle')->updateWxShippingInfo($order['users_id'], $order['order_code'], $orderSource, $params['errcode'], $params['errmsg']);
        } else {
            model('ShopPublicHandle')->updateWxShippingInfo($order['users_id'], $order['order_code'], $orderSource, -1, '获取access_token失败');
        }
        return true;
    }
}