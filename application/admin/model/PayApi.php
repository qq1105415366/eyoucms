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
 * Date: 2020-05-22
 */
namespace app\admin\model;

use think\Model;
use think\Config;
use think\Db;

/**
 * 支付接口模型
 */

load_trait('controller/Jump');
class PayApi extends Model
{
    use \traits\controller\Jump;

    private $key = ''; // key密钥

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /*
     * 验证微信支付配置信息是否正确
     */
    public function VerifyWeChatConfig($wechat = [])
    {
        if (empty($wechat)) return false;
        $this->key = $wechat['key'];

        // 支付备注
        $body = "验证支付";
        if (1 == config('global.opencodetype')) {
            $web_name = tpCache('web.web_name');
            $web_name = !empty($web_name) ? "[{$web_name}]" : "";
            $body = $web_name.$body;
        }

        // 支付数据
        $out_trade_no             = getTime();
        $data['out_trade_no']     = $out_trade_no;
        $data['total_fee']        = '1';
        $data['spbill_create_ip'] = $this->get_client_ip();
        $data['attach']           = '微信扫码支付';
        $data['body']             = $body."订单号:{$out_trade_no}";
        $data['appid']            = $wechat['appid'];
        $data['mch_id']           = $wechat['mchid'];
        $data['nonce_str']        = getTime();
        $data['trade_type']       = "JSAPI";
        $data['notify_url']       = url('user/Pay/pay_deal_with');
        $data['openid']           = getTime();

        // 签名加密
        $sign = $this->getParam($data);

        // 转化XML格式
        $dataXML = "<xml>
           <appid>".$data['appid']."</appid>
           <attach>".$data['attach']."</attach>
           <body>".$data['body']."</body>
           <mch_id>".$data['mch_id']."</mch_id>
           <nonce_str>".$data['nonce_str']."</nonce_str>
           <notify_url>".$data['notify_url']."</notify_url>
           <out_trade_no>".$data['out_trade_no']."</out_trade_no>
           <openid>".$data['openid']."</openid>
           <spbill_create_ip>".$data['spbill_create_ip']."</spbill_create_ip>
           <total_fee>".$data['total_fee']."</total_fee>
           <trade_type>".$data['trade_type']."</trade_type>
           <sign>".$sign."</sign>
        </xml>";

        // 调用接口，转换回数组格式
        $result = $this->xmlToArray($this->httpsPost('https://api.mch.weixin.qq.com/pay/unifiedorder', $dataXML));

        // 请求接口成功
        if (!empty($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
            if ('FAIL' == $result['result_code'] && !empty($result['err_code']) && !empty($result['err_code_des'])) {
                if (stristr($result['err_code_des'], 'openid')) {
                    return true;
                } else {
                    $result['return_code'] = 'FAIL';
                    $result['return_msg'] = $result['err_code_des'];
                    return $result;
                }
            } else {
                return true;
            }
        }
        // 请求接口失败
        else if (!empty($result['return_code']) && $result['return_code'] == 'FAIL') {
            if (stristr($result['return_msg'], '签名错误')) {
                $result['return_msg'] = '微信支付KEY密钥不正确';
            } else if (stristr($result['return_msg'], 'mch_id')) {
                $result['return_msg'] = '微信支付商户号配置不正确';
            } else if (stristr($result['return_msg'], 'appid')) {
                $result['return_msg'] = '微信支付AppID配置不正确';
            }
            return $result;
        }
    }

    // 验证支付宝支付配置是否正确
    public function VerifyAliPayConfig($alipay = [])
    {
        if (!empty($alipay)) {
            // 时间戳当订单号
            $order_number = getTime();

            // 引入文件
            vendor('alipay.pagepay.service.AlipayTradeService');
            vendor('alipay.pagepay.buildermodel.AlipayTradeQueryContentBuilder');

            // 实例化加载订单号
            $RequestBuilder = new \AlipayTradeQueryContentBuilder;
            $out_trade_no   = trim($order_number);
            $RequestBuilder->setOutTradeNo($out_trade_no);

            // 处理支付宝配置数据
            $config['app_id']     = $alipay['app_id'];
            $config['merchant_private_key'] = $alipay['merchant_private_key'];
            $config['charset']    = 'UTF-8';
            $config['sign_type']  = 'RSA2';
            $config['gatewayUrl'] = 'https://openapi.alipay.com/gateway.do';
            $config['alipay_public_key'] = $alipay['alipay_public_key'];

            // 实例化支付宝配置
            $aop = new \AlipayTradeService($config);

            // 返回结果
            $result = $aop->IsQuery($RequestBuilder, 'admin_pay');
            $result = json_decode(json_encode($result), true);

            // 判断结果
            if ('40004' == $result['code'] && 'Business Failed' == $result['msg']) {
                // 用于支付宝支付配置验证
                return 'ok';
            } else if ('40001' == $result['code'] && 'Missing Required Arguments' == $result['msg']) {
                return '商户私钥错误！';
            } else if (is_array($result)) {
                $msg = !empty($result['sub_msg']) ? $result['sub_msg'] : '请确保配置正确，且检查支付宝平台的权限';
                return $msg;
            } else {
                return $result;
            }
        }
    }

    // 获取客户端IP
    private function get_client_ip()
    {
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    }

    // 对参数排序，生成MD5加密签名
    private function getParam($paramArray, $isencode=false)
    {
        $paramStr = '';
        ksort($paramArray);
        $i = 0;

        foreach ($paramArray as $key => $value)
        {
            if ($key == 'Signature'){
                continue;
            }
            if ($i == 0){
                $paramStr .= '';
            }else{
                $paramStr .= '&';
            }
            $paramStr .= $key . '=' . ($isencode ? urlencode($value) : $value);
            ++$i;
        }

        $stringSignTemp=$paramStr."&key=".$this->key;
        $sign=strtoupper(md5($stringSignTemp));
        return $sign;
    }

    // POST提交数据
    private function httpsPost($url = '', $paramsXml = [], $ssl = false, $payConfig = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        if (!empty($ssl)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, ROOT_PATH . $payConfig['apiclient_key']);
            curl_setopt($ch, CURLOPT_SSLCERT, ROOT_PATH . $payConfig['apiclient_cert']);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsXml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        // 返回错误提示
        if (curl_errno($ch)) return 'Errno: ' . curl_error($ch);
        // 返回成功数据
        curl_close($ch);
        return $result;
    }

    // XML转array
    private function xmlToArray($xml = '')
    {
        @libxml_disable_entity_loader(true);
        $xmlstring = (array)simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring),true);
        return $val;
    }


    // 申请微信支付订单退款
    public function applyWeChatPayOrderRefund($data = [], $action = '')
    {
        // 是否允许微信申请退款
        if ('verify' == $action) {
            // 查询系统订单信息
            $where = [
                'users_id' => intval($data['users_id']),
                'order_id' => intval($data['order_id']),
            ];
            $shopOrder = Db::name('shop_order')->where($where)->find();
            if (empty($shopOrder)) $this->error('订单不存在');
            if (!empty($shopOrder['pay_name']) && 'wechat' != $shopOrder['pay_name']) $this->error('订单非微信支付，不可申请');

            // 查询支付配置
            $payConfig = 3 === intval($shopOrder['order_terminal']) ? getOpenMiniCodeConfig('weixin') : getPayApiConfig(1, 'wechat');
            if (empty($payConfig)) {
                // 小程序配置
                $diyminiproMallModel = new \app\plugins\model\DiyminiproMall;
                $payConfig = $diyminiproMallModel->detail();
                $diyminiproMallSettingModel = new \weapp\DiyminiproMall\model\DiyminiproMallSettingModel;
                $setting = $diyminiproMallSettingModel->getSettingValue('setting');
                if (!empty($setting['appId'])) $payConfig['appid'] = $setting['appId'];
            }

            $shopPayConfig = getUsersConfigData('pay');
            if (empty($shopPayConfig['pay_original_refund'])) $this->error('请开启【商城中心】-【商城配置】-【基本设置】-【原路退回】功能');
            if (empty($shopPayConfig['pay_wechat_apiclient_key'])) $this->error('请上传【商城中心】-【商城配置】-【基本设置】-【apiclient_key】文件');
            if (empty($shopPayConfig['pay_wechat_apiclient_cert'])) $this->error('请上传【商城中心】-【商城配置】-【基本设置】-【apiclient_cert】文件');
            $payConfig['apiclient_key'] = trim($shopPayConfig['pay_wechat_apiclient_key']);
            $payConfig['apiclient_cert'] = trim($shopPayConfig['pay_wechat_apiclient_cert']);

            // 查询微信订单信息
            $userPayApi = new \app\user\model\PayApi();
            $payResult = $userPayApi->getWeChatPayResult($shopOrder['users_id'], $shopOrder['order_code'], $payConfig);
            if (empty($payResult)) $this->error('未查询到相关的微信订单，请核实再提交');
            if ('NOTPAY' == $payResult['trade_state'] || empty($payResult['transaction_id'])) $this->error('微信订单尚未支付，不可申请');
            if (floatval($data['actual_price']) > (floatval($payResult['total_fee']) / 100)) $this->error('退款金额超过订单金额，不可申请');

            // 返回信息
            $result = [
                'shopOrder' => $shopOrder,
                'payConfig' => $payConfig,
                'payResult' => $payResult,
                'refundCode' => trim($data['refund_code']),
                'actualPrice' => floatval($data['actual_price']),
            ];
            return ['code' => 1, 'data' => $result];
        }
        // 提交允许微信申请退款
        else if ('submit' == $action) {
            // 订单及支付信息
            $shopOrder = !empty($data['shopOrder']) ? $data['shopOrder'] : [];
            $payConfig = !empty($data['payConfig']) ? $data['payConfig'] : [];
            $payResult = !empty($data['payResult']) ? $data['payResult'] : [];
            $refundCode = !empty($data['refundCode']) ? trim($data['refundCode']) : '';
            $actualPrice = !empty($data['actualPrice']) ? floatval($data['actualPrice']) : 0;
            // 使用配置key
            if (!empty($payConfig['key'])) {
                $this->key = $payConfig['key'];
            }
            else if (!empty($payConfig['apikey'])) {
                $this->key = $payConfig['apikey'];
            }
            // 调用微信申请退款接口
            $post = [
                'appid'          => $payConfig['appid'],
                'mch_id'         => $payConfig['mchid'],
                'nonce_str'      => $this->createNonceStr(),
                'total_fee'      => floatval($payResult['total_fee']),
                'refund_fee'     => floatval($actualPrice) * 100,
                'sign_type'      => 'MD5',
                'transaction_id' => $payResult['transaction_id'],
                'out_trade_no'   => $shopOrder['order_code'],
                'out_refund_no'  => $refundCode,
                'refund_desc'    => '商品已售完',
            ];
            $post['sign'] = $this->getParam($post);
            // 请求接口
            $result = $this->xmlToArray($this->httpsPost('https://api.mch.weixin.qq.com/secapi/pay/refund', $this->arrayToXml($post), true, $payConfig));
            // 请求接口成功
            if (!empty($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
                // 申请成功
                if (!empty($result['result_code']) && $result['result_code'] == 'SUCCESS') {
                    return $result;
                }
                // 申请失败
                else if (!empty($result['result_code']) && $result['result_code'] == 'FAIL') {
                    $result['return_msg'] = $result['err_code_des'];
                    $result['return_code'] = $result['result_code'];
                }
            }
            // 请求接口失败
            else if (!empty($result['return_code']) && $result['return_code'] == 'FAIL') {
                if (stristr($result['return_msg'], '签名错误')) {
                    $result['return_msg'] = '微信支付KEY密钥不正确';
                } else if (stristr($result['return_msg'], 'mch_id')) {
                    $result['return_msg'] = '微信支付商户号配置不正确';
                } else if (stristr($result['return_msg'], 'appid')) {
                    $result['return_msg'] = '微信支付AppID配置不正确';
                }
            }
            if (isset($result[0]) && false === $result[0]) {
                $result['return_msg'] = '请检查微信支付证书是否过期或未上传';
                $result['return_code'] = 'FAIL';
            }
            return ['return_code' => $result['return_code'], 'return_msg' => $result['return_msg']];
        }
        // 报错提示
        else {
            $this->error('请执行验证或提交操作');
        }
    }

    // 查询微信退款信息
    public function inquireWeChatPayOrderRefund($service = [], $orderTerminal = 0)
    {
        // 查询支付配置
        $payConfig = 3 === intval($orderTerminal) ? getOpenMiniCodeConfig('weixin') : getPayApiConfig(1, 'wechat');
        if (empty($payConfig)) {
            // 小程序配置
            $diyminiproMallModel = new \app\plugins\model\DiyminiproMall;
            $payConfig = $diyminiproMallModel->detail();
            $diyminiproMallSettingModel = new \weapp\DiyminiproMall\model\DiyminiproMallSettingModel;
            $setting = $diyminiproMallSettingModel->getSettingValue('setting');
            if (!empty($setting['appId'])) $payConfig['appid'] = $setting['appId'];
        }

        $shopPayConfig = getUsersConfigData('pay');
        if (empty($shopPayConfig['pay_original_refund'])) $this->error('请开启【商城中心】-【商城配置】-【基本设置】-【原路退回】功能');
        if (empty($shopPayConfig['pay_wechat_apiclient_key'])) $this->error('请上传【商城中心】-【商城配置】-【基本设置】-【apiclient_key】文件');
        if (empty($shopPayConfig['pay_wechat_apiclient_cert'])) $this->error('请上传【商城中心】-【商城配置】-【基本设置】-【apiclient_cert】文件');
        $payConfig['apiclient_key'] = trim($shopPayConfig['pay_wechat_apiclient_key']);
        $payConfig['apiclient_cert'] = trim($shopPayConfig['pay_wechat_apiclient_cert']);
        
        // 使用配置key
        if (!empty($payConfig['key'])) {
            $this->key = $payConfig['key'];
        }
        else if (!empty($payConfig['apikey'])) {
            $this->key = $payConfig['apikey'];
        }
        // 调用微信申请退款接口
        $post = [
            'appid'         => $payConfig['appid'],
            'mch_id'        => $payConfig['mchid'],
            'nonce_str'     => $this->createNonceStr(),
            'sign_type'     => 'MD5',
            'out_refund_no' => $service['refund_code'],
        ];
        $post['sign'] = $this->getParam($post);
        // 请求接口
        $result = $this->xmlToArray($this->httpsPost('https://api.mch.weixin.qq.com/pay/refundquery', $this->arrayToXml($post)));
        if (empty($result)) $this->error('未查询到相关的微信退款订单，请核实再查询');

        // 处理显示数据
        $result['total_fee'] = floatval(floatval($result['total_fee']) / floatval(100));
        $result['refund_fee'] = floatval(floatval($result['refund_fee']) / floatval(100));
        $result['refund_fee_0'] = floatval(floatval($result['refund_fee_0']) / floatval(100));
        $result['refund_remark'] = !empty($service['refund_remark']) ? $service['refund_remark'] : '-';
        $result['update_time'] = !empty($service['update_time']) ? date('Y-m-d H:i:s', $service['update_time']) : '-';
        // 退款状态
        if (!empty($result['refund_status_0']) && 'SUCCESS' == $result['refund_status_0']) {
            $result['refund_status_0'] = '退款成功';
        }
        else if (!empty($result['refund_status_0']) && 'REFUNDCLOSE' == $result['refund_status_0']) {
            $result['refund_status_0'] = '退款关闭，商户发起退款失败';
        }
        else if (!empty($result['refund_status_0']) && 'PROCESSING' == $result['refund_status_0']) {
            $result['refund_status_0'] = '退款处理中';
        }
        else if (!empty($result['refund_status_0']) && 'CHANGE' == $result['refund_status_0']) {
            $result['refund_status_0'] = '退款异常，退款到银行发现用户的卡作废或者冻结了，导致原路退款银行卡失败，可前往商户平台(pay.weixin.qq.com)-交易中心，手动处理此笔退款';
        }
        // 退款方式
        if (!empty($result['refund_channel_0']) && 'ORIGINAL' == $result['refund_channel_0']) {
            $result['refund_channel_0'] = '原路退款';
        }
        else if (!empty($result['refund_channel_0']) && 'BALANCE' == $result['refund_channel_0']) {
            $result['refund_channel_0'] = '退回到余额';
        }
        else if (!empty($result['refund_channel_0']) && 'OTHER_BALANCE' == $result['refund_channel_0']) {
            $result['refund_channel_0'] = '原账户异常退到其他余额账户';
        }
        else if (!empty($result['refund_channel_0']) && 'OTHER_BANKCARD' == $result['refund_channel_0']) {
            $result['refund_channel_0'] = '原银行卡异常退到其他银行卡';
        }
        // 退款资金来源
        if (!empty($result['refund_account_0']) && 'REFUND_SOURCE_RECHARGE_FUNDS' == $result['refund_account_0']) {
            $result['refund_account_0'] = '可用余额退款/基本账户';
        }
        else if (!empty($result['refund_account_0']) && 'REFUND_SOURCE_UNSETTLED_FUNDS' == $result['refund_account_0']) {
            $result['refund_account_0'] = '未结算资金退款';
        }

        return $result;
    }

    // 获取系统唯一系列号
    public function getEyouCmsSerialNumber()
    {
        // 序列号提取
        $serial_number = DEFAULT_SERIALNUMBER;
        $constsant_path = APP_PATH . MODULE_NAME . '/conf/constant.php';
        if (file_exists($constsant_path)) {
            require_once($constsant_path);
            defined('SERIALNUMBER') && $serial_number = SERIALNUMBER;
        }
        return $serial_number;
    }

    // 获取指定数量随机数
    private function createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function arrayToXml($arr = [])
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }
}
