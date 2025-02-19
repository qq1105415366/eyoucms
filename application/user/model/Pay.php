<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-2-20
 */
namespace app\user\model;

use think\Model;
use think\Config;
use think\Db;

/**
 * 会员
 */
class Pay extends Model
{
    private $home_lang = 'cn';
    private $key = ''; // key密钥

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->home_lang = get_home_lang();
    }

    /**
     * 虚拟网盘支付后自动发货,收货
     * @param array $where =[ 'users_id'=>'','lang'=> $this->home_lang,'order_id'=> ''] 用户id  语言  订单id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function afterVirtualProductPay($orderData = [], $config = [])
    {
        //判断是否为需要自动发货的虚拟商品  archives表prom_type=2,3
        $details_data = \think\Db::name('shop_order_details')->where([
            'order_id'  => $orderData['order_id'],
            'users_id'  => $orderData['users_id'],
        ])->select();
        $autoSendGoods       = true;//多商品合并的订单，需要先判断是否都是需要自动发货的
        foreach ($details_data as &$key) {
            if ($key['prom_type'] == 0 || $key['prom_type'] == 1 || $key['prom_type'] == 4) {
                $autoSendGoods = false;
                // 保存微信发货推送表记录 不自动发货的情况下要写入订单记录
                if ('wechat' === trim($orderData['pay_name'])) {
                    model('ShopPublicHandle')->saveWxShippingInfo($orderData['users_id'], $orderData['order_code'], 2, $config);
                }
                break;    //只要存在一个需要手动发货的就结束循环，并且不进入自动发货
            }
        }
        //直接发货
        if ($autoSendGoods) {
            //自动确认收货
            $times = getTime();
            $confirmOrder = \think\Db::name('shop_order')->where([
                'order_id'  => $orderData['order_id'],
                'users_id'  => $orderData['users_id'],
            ])->update([
                'order_status' => 3,
                'express_time' => $times,
                'confirm_time' => $times,
                'update_time'  => $times,
                // 'virtual_delivery' => $virtual_delivery,  //注释,每次取出的时候再拼接
            ]);
            if (false !== $confirmOrder) {
                AddOrderAction($orderData['order_id'], 0, session('admin_id'), 2, 1, 1, '虚拟商品自动发货成功！', '发货成功');
                AddOrderAction($orderData['order_id'], 0, session('admin_id'), 3, 1, 1, '虚拟商品自动收货成功！', '确认订单已收货');
                if (function_exists('diy_push_buy_cloudminipro')) diy_push_buy_cloudminipro($orderData);
            }
            // 推送微信发货推送表记录
            if ('wechat' === trim($orderData['pay_name'])) {
                model('ShopPublicHandle')->pushWxShippingInfo($orderData['users_id'], $orderData['order_code'], 2, '', $config);
            }
        }

        return $autoSendGoods;
    }

    // 处理充值订单，超过指定时间修改为已取消订单，针对未付款订单
    public function UpdateOrderData($users_id)
    {
        $time  = getTime() - Config::get('global.get_order_validity');
        $where = array(
            'users_id' => $users_id,
            'status'   => 1,
            'add_time' => array('<', $time),
        );
        $data  = [
            'status'      => 4, // 订单取消
            'update_time' => getTime(),
        ];
        Db::name('users_money')->where($where)->update($data);
    }

    /*
     *   微信端H5支付，手机微信直接调起微信支付
     *   @params string $openid : 用户的openid
     *   @params string $out_trade_no : 商户订单号
     *   @params number $total_fee : 订单金额，单位分
     *   return string  $ReturnData : 微信支付所需参数数组
     */
    public function getWechatPay($openid, $out_trade_no, $total_fee, $PayInfo, $is_applets = 0, $transaction_type = 2)
    {
        if (isMobile() && isWeixin()) {
            $thirdparty = Db::name('users')->where(['users_id'=>session('users_id')])->getField('thirdparty');
            if (0 === intval($thirdparty)) $openid = model('ShopPublicHandle')->weChatauthorizeCookie(session('users_id'));
        }
        
        // 获取微信配置信息
        $where = [
            'pay_id' => 1,
            'pay_mark' => 'wechat'
        ];
        $pay_wechat_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
        if (empty($pay_wechat_config)) {
            $pay_wechat_config = getUsersConfigData('pay.pay_wechat_config');
            if (empty($pay_wechat_config)) return false;
        }
        $wechat = unserialize($pay_wechat_config);

        // 支付密钥
        $this->key = $wechat['key'];

        // 小程序配置
        if (1 === $is_applets) {
            $MiniproValue = Db::name('weapp_minipro0002')->where('type', 'minipro')->getField('value');
            if (empty($MiniproValue)) return false;
            $MiniproValue = !empty($MiniproValue) ? json_decode($MiniproValue, true) : [];
            $wechat['appid'] = $MiniproValue['appId'];

            // 支付备注
            $body = "小程序支付";
        } else {
            // 支付备注
            $body = "微信支付";
        }

        // 支付备注
        if (1 == config('global.opencodetype')) {
            $web_name = tpCache('web.web_name');
            $web_name = !empty($web_name) ? "[{$web_name}]" : "";
            $body = $web_name . $body;
        }

        //支付数据
        $data['body']             = $body . "订单号:{$out_trade_no}";
        $data['attach']           = "wechat|,|is_notify|,|" . $transaction_type . '|,|' . session('users_id');
        $data['out_trade_no']     = $out_trade_no;
        $data['total_fee']        = $total_fee * 100;
        $data['nonce_str']        = getTime();
        $data['spbill_create_ip'] = $this->get_client_ip();
        $data['appid']            = $wechat['appid'];
        $data['mch_id']           = $wechat['mchid'];
        $data['trade_type']       = "JSAPI";
        $data['notify_url']       = request()->domain() . ROOT_DIR . '/index.php'; // 异步地址
        $data['openid']           = $openid;
 
        $sign = $this->getParam($data);
        $dataXML = "<xml>
           <appid>".$data['appid']."</appid>
           <attach>".$data['attach']."</attach>
           <body>".$data['body']."</body>
           <mch_id>".$data['mch_id']."</mch_id>
           <nonce_str>".$data['nonce_str']."</nonce_str>
           <notify_url>".$data['notify_url']."</notify_url>
           <openid>".$data['openid']."</openid>
           <out_trade_no>".$data['out_trade_no']."</out_trade_no>
           <spbill_create_ip>".$data['spbill_create_ip']."</spbill_create_ip>
           <total_fee>".$data['total_fee']."</total_fee>
           <trade_type>".$data['trade_type']."</trade_type>
           <sign>".$sign."</sign>
        </xml>";

        $url    = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result =  $this->https_post($url,$dataXML);
        $ret    =  $this->xmlToArray($result);

        if (isset($ret['return_code']) && $ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            $timeStamp  = getTime();
            $ReturnData = [
                'appId'     => $wechat['appid'],
                'timeStamp' => "$timeStamp",
                'nonceStr'  => $this->GetRandomString(12),
                'package'   => 'prepay_id='.$ret['prepay_id'],
                'signType'  => 'MD5',
            ];
            $ReturnSign = $this->getParam($ReturnData);
            $ReturnData['paySign'] = $ReturnSign;
            return $ReturnData;
        } else if (isset($ret['return_code']) && $ret['return_code'] == 'FAIL') {
            return $ret;
        } else {
            return $ret;
        }
    }

    /*
     *   微信H5支付，手机浏览器调起微信支付
     *   @params string $openid : 用户的openid
     *   @params string $out_trade_no : 商户订单号
     *   @params number $total_fee : 订单金额，单位分
     *   return string $mweb_url : 二维码URL链接
     */
    public function getMobilePay($out_trade_no,$total_fee,$body="支付",$attach="手机浏览器微信H5支付")
    {
        // 获取微信配置信息
        $where = [
            'pay_id' => 1,
            'pay_mark' => 'wechat'
        ];
        $pay_wechat_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
        if (empty($pay_wechat_config)) {
            $pay_wechat_config = getUsersConfigData('pay.pay_wechat_config');
            if (empty($pay_wechat_config)) return false;
        }
        $wechat = unserialize($pay_wechat_config);
        $this->key = $wechat['key'];

        //支付数据
        $data['out_trade_no']     = $out_trade_no;
        $data['total_fee']        = $total_fee * 100;
        $data['spbill_create_ip'] = $this->get_client_ip();
        $data['attach']           = $attach;
        $data['body']             = $body;
        $data['appid']            = $wechat['appid'];
        $data['mch_id']           = $wechat['mchid'];
        $data['nonce_str']        = getTime();
        $data['trade_type']       = "MWEB";
        $data['scene_info']       = '{"h5_info":{"type":"Wap","wap_url":'.url("users/Pay/mobile_pay_notify").',"wap_name":"支付"}}';
        $data['notify_url']       = url('users/Pay/mobile_pay_notify');

        $sign = $this->getParam($data);
        $dataXML = "<xml>
           <appid>".$data['appid']."</appid>
           <attach>".$data['attach']."</attach>
           <body>".$data['body']."</body>
           <mch_id>".$data['mch_id']."</mch_id>
           <nonce_str>".$data['nonce_str']."</nonce_str>
           <notify_url>".$data['notify_url']."</notify_url>
           <out_trade_no>".$data['out_trade_no']."</out_trade_no>
           <scene_info>".$data['scene_info']."</scene_info>
           <spbill_create_ip>".$data['spbill_create_ip']."</spbill_create_ip>
           <total_fee>".$data['total_fee']."</total_fee>
           <trade_type>".$data['trade_type']."</trade_type>
           <sign>".$sign."</sign>
        </xml>";

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result =  $this->https_post($url,$dataXML);
        $ret = $this->xmlToArray($result);
        if(isset($ret['return_code']) && $ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            if (!empty($ret['err_code'])) {
                return $ret['err_code_des'];
            }
            return $ret['mweb_url'];
        } else {
            return $ret;
        }
    }

    /*
     *   微信二维码支付
     *   @params string $openid : 用户的openid
     *   @params string $out_trade_no : 商户订单号
     *   @params number $total_fee : 订单金额，单位分
     *   return string $code_url : 二维码URL链接
     */
    public function payForQrcode($out_trade_no,$total_fee,$body="支付",$attach="微信扫码支付")
    {
        // 获取微信配置信息
        $where = [
            'pay_id' => 1,
            'pay_mark' => 'wechat'
        ];
        $pay_wechat_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
        if (empty($pay_wechat_config)) {
            $pay_wechat_config = getUsersConfigData('pay.pay_wechat_config');
            if (empty($pay_wechat_config)) return false;
        }
        $wechat = unserialize($pay_wechat_config);
        $this->key = $wechat['key'];

        // 支付备注
        if (1 == config('global.opencodetype')) {
            $web_name = tpCache('web.web_name');
            $web_name = !empty($web_name) ? "[{$web_name}]" : "";
            $body = $web_name.$body;
        }

        //支付数据
        $data['out_trade_no']     = $out_trade_no;
        $data['total_fee']        = $total_fee * 100;
        $data['spbill_create_ip'] = $this->get_client_ip();
        $data['attach']           = $attach;
        $data['body']             = $body."订单号:{$out_trade_no}";
        $data['appid']            = $wechat['appid'];
        $data['mch_id']           = $wechat['mchid'];
        $data['nonce_str']        = getTime();
        $data['trade_type']       = "NATIVE";
        $data['notify_url']       = url('user/Pay/pay_deal_with');

        $sign = $this->getParam($data);

        $dataXML = "<xml>
           <appid>".$data['appid']."</appid>
           <attach>".$data['attach']."</attach>
           <body>".$data['body']."</body>
           <mch_id>".$data['mch_id']."</mch_id>
           <nonce_str>".$data['nonce_str']."</nonce_str>
           <notify_url>".$data['notify_url']."</notify_url>
           <out_trade_no>".$data['out_trade_no']."</out_trade_no>
           <spbill_create_ip>".$data['spbill_create_ip']."</spbill_create_ip>
           <total_fee>".$data['total_fee']."</total_fee>
           <trade_type>".$data['trade_type']."</trade_type>
           <sign>".$sign."</sign>
        </xml>";

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result =  $this->https_post($url,$dataXML);
        $ret = $this->xmlToArray($result);
        if(isset($ret['return_code']) && $ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret['code_url'];
        } else {
            return $ret;
        }
    }

    // 获取客户端IP
    private function get_client_ip()
    {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
    }

    //对参数排序，生成MD5加密签名
    private function getParam($paramArray, $isencode=false)
    {
        $paramStr = '';
        ksort($paramArray);
        $i = 0;

        foreach ($paramArray as $key => $value) {
            if ($key == 'Signature') {
                continue;
            }
            if ($i == 0) {
                $paramStr .= '';
            } else {
                $paramStr .= '&';
            }
            $paramStr .= $key . '=' . ($isencode ? urlencode($value) : $value);
            ++$i;
        }

        $stringSignTemp=$paramStr."&key=".$this->key;
        $sign=strtoupper(md5($stringSignTemp));
        return $sign;

    }

    //POST提交数据
    private function https_post($url,$data)
    {
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
        // curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Errno: '.curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    /*
    * XML转array
    * @params xml $xml : xml 数据
    * return array $data : 转义后的array数组
    */
    private function xmlToArray($xml)
    {
        @libxml_disable_entity_loader(true);
        $xmlstring = (array)simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring),true);
        return $val;
    }

    /*
     *   支付宝新版支付，生成支付链接方法。
     *   @params string $data   : 订单表数据，必须传入
     *   return string $alipay_url : 支付宝支付链接
     */
    public function getNewAliPayPayUrl($data)
    {
        // 引入SDK文件
        vendor('alipay.pagepay.service.AlipayTradeService');
        vendor('alipay.pagepay.buildermodel.AlipayTradePagePayContentBuilder');

        // 获取支付宝配置信息
        $where = [
            'pay_id' => 2,
            'pay_mark' => 'alipay'
        ];
        $PayInfo = Db::name('pay_api_config')->where($where)->getField('pay_info');
        if (empty($PayInfo)) return false;
        $alipay = unserialize($PayInfo);

        $type = $data['transaction_type'];
        // 参数拼装
        $config['app_id'] = $alipay['app_id'];
        $config['merchant_private_key'] = $alipay['merchant_private_key'];
        $config['transaction_type'] = $type;

        // 异步地址
        $notify_url = request()->domain().ROOT_DIR.'/index.php?transaction_type='.$type.'&is_notify=1';
        $config['notify_url'] = $notify_url;

        // 同步地址
        $return_url = url('user/Pay/alipay_return', ['transaction_type'=>$type,'is_notify'=>2], true, true);
        $config['return_url'] = $return_url;

        $config['charset']    = 'UTF-8';
        $config['sign_type']  = 'RSA2';
        $config['gatewayUrl'] = 'https://openapi.alipay.com/gateway.do';
        $config['alipay_public_key'] = $alipay['alipay_public_key'];
        // 实例化
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder;
        $aop               = new \AlipayTradeService($config);

        $out_trade_no = trim($data['unified_number']);//商户订单号，商户网站订单系统中唯一订单号，必填
        $subject      = '支付';//订单名称，必填
        $total_amount = trim($data['unified_amount']);//付款金额，必填
        $body         = '支付宝支付';//商品描述，可空
        if (1 == config('global.opencodetype')) {
            $web_name = tpCache('web.web_name');
            $web_name = !empty($web_name) ? "[{$web_name}]" : "";
            $subject = $web_name.$subject;
            $body = $web_name.$body;
        }
        //构造参数
        $payRequestBuilder->setBody($body."订单号:{$out_trade_no}");
        $payRequestBuilder->setSubject($subject."订单号:{$out_trade_no}");
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);

        // 调用SDK进行支付宝支付
        $response = $aop->pagePay($payRequestBuilder,$config['return_url'],$config['notify_url']);
    }

    /*
     *   支付宝旧版支付，生成支付链接方法。
     *   @params string $data   : 订单表数据，必须传入
     *   @params string $alipay : 支付宝配置信息，通过 getUsersConfigData 方法调用数据
     *   return string $alipay_url : 支付宝支付链接
     */
    public function getOldAliPayPayUrl($data, $alipay)
    {
        // 重要参数，支付宝配置信息
        if (empty($alipay)) {
            $where = [
                'pay_id' => 2,
                'pay_mark' => 'alipay'
            ];
            $alipay = Db::name('pay_api_config')->where($where)->getField('pay_info');
            if (empty($alipay)) {
                $alipay = getUsersConfigData('pay.pay_alipay_config');
                if (empty($alipay)) return false;
            }
            $alipay = unserialize($alipay);
        }

        // 参数设置
        $order['out_trade_no'] = $data['unified_number']; //订单号
        $order['price']        = $data['unified_amount']; //订单金额
        $charset               = 'utf-8';  //编码格式
        $real_method           = '2';      //调用方式
        $agent                 = 'C4335994340215837114'; //代理机构
        $seller_email          = $alipay['account'];//支付宝用户账号
        $security_check_code   = $alipay['code'];   //交易安全校验码
        $partner               = $alipay['id'];     //合作者身份ID

        switch ($real_method){
            case '0':
                $service = 'trade_create_by_buyer';
                break;
            case '1':
                $service = 'create_partner_trade_by_buyer';
                break;
            case '2':
                $service = 'create_direct_pay_by_user';
                break;
        }

        $type       = $data['transaction_type']; //自定义，用于验证
        // 异步地址
        $notify_url = request()->domain().ROOT_DIR.'/index.php?transaction_type='.$type.'&is_notify=1';
        // 同步地址
        $return_url = url('user/Pay/alipay_return', ['transaction_type'=>$type,'is_notify'=>2], true, true);
        // 参数拼装
        $parameter = array(
          'agent'             => $agent,
          'service'           => $service,
          //合作者ID
          'partner'           => $partner,
          '_input_charset'    => $charset,
          'notify_url'        => $notify_url,
          'return_url'        => $return_url,
          /* 业务参数 */
          'subject'           => "支付订单号:".$order['out_trade_no'],
          'out_trade_no'      => $order['out_trade_no'],
          'price'             => $order['price'],
          'quantity'          => 1,
          'payment_type'      => 1,
          /* 物流参数 */
          'logistics_type'    => 'EXPRESS',
          'logistics_fee'     => 0,
          'logistics_payment' => 'BUYER_PAY_AFTER_RECEIVE',
          /* 买卖双方信息 */
          'seller_email'      => $seller_email,
        );

        ksort($parameter);
        reset($parameter);
        $param = '';
        $sign  = '';

        foreach ($parameter AS $key => $val) {
            $param .= "$key=" . urlencode($val) . "&";
            $sign  .= "$key=$val&";
        }

        $param      = substr($param, 0, -1);
        $sign       = substr($sign, 0, -1) . $security_check_code;
        $alipay_url = 'https://www.alipay.com/cooperate/gateway.do?' . $param . '&sign=' . MD5($sign) . '&sign_type=MD5';
        return $alipay_url;
    }

    // 获取随机字符串
    // 长度 length
    // 结果 str
    public function GetRandomString($length)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}