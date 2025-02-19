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
namespace app\admin\model;

use think\Model;
use think\Config;
use think\Db;

/**
 * 会员
 */
class Pay extends Model
{
    private $key = ''; // key密钥

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /*
     *   微信二维码支付
     *   @params string $openid : 用户的openid
     *   @params string $out_trade_no : 商户订单号
     *   @params number $total_fee : 订单金额，单位分
     *   return string $code_url : 二维码URL链接
     */
    public function payForQrcode($wechat,$out_trade_no='',$total_fee='',$body="充值",$attach="微信扫码支付")
    {
        $this->key = $wechat['key'];

        // 支付备注
        $body = "支付";
        if (1 == config('global.opencodetype')) {
            $web_name = tpCache('web.web_name');
            $web_name = !empty($web_name) ? "[{$web_name}]" : "";
            $body = $web_name.$body;
        }

        //支付数据
        $out_trade_no             = getTime();
        $data['out_trade_no']     = $out_trade_no;
        $data['total_fee']        = '1';
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
        if($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret['code_url'];
        } else {
            return $ret;
        }
    }

    // 获取客户端IP
    private function get_client_ip() {
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

    //对参数排序，生成MD5加密签名
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

    

}