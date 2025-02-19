<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\api\controller\v1;

use think\Db;

class Api extends Base
{
    /**
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
    }

    public function get_all_region()
    {
        $not_arr = [46047,41103,47494,47495,47493];//新疆  西藏   香港  澳门   台湾 海外 除外
        $list = Db::name('region')->order('level asc,parent_id asc')->getAllWithIndex('id');
        $province = [];
        $city = [];
        $area = [];
        foreach ($list as $k => $v) {
            if('海外' == $v['name']){
                $not_arr[] = $v['id'];
            }
            if ( in_array($v['id'],$not_arr) || in_array($v['parent_id'],$not_arr) ) {
                continue;
            }
            if (1 == $v['level']) {
                $province[$k] = $v;
            } else if (2 == $v['level']) {
                $city[$k] = $v;
            } else if (3 == $v['level']) {
                $area[] = $v;
            }
        }
        foreach ($area as $k => $v) {
            if(!empty($city[$v['parent_id']])){
              $city[$v['parent_id']]['child'][] = $v;
            }
        }
        $city = array_merge($city);
        foreach ($city as $k => $v) {
            if(!empty($province[$v['parent_id']])){
                $province[$v['parent_id']]['child'][] = $v;
            }
        }
        $province = array_merge($province);
        // dump($province);exit;
        $this->success('success','',$province);
    }

    //处理静态页面分享路径
    public function handle_share_url()
    {
        $url = input('post.share_url/s', '');
        $url = urldecode(htmlspecialchars_decode($url));
        if (!is_http_url($url)) {
            $this->error('失败！');
        }

        $this->success('成功！', null, $this->getSignPackage($url));
    }

    private function get_jsapi_ticket($row = [], $access_token = '')
    {
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type=jsapi";
        $data = httpRequest($url);
        $WeChatData = json_decode($data, true);
        if (empty($WeChatData) || (!empty($WeChatData['errcode']) && !empty($WeChatData['errmsg']))) {
            $errmsg = !empty($WeChatData['errmsg']) ? $WeChatData['errmsg'] : '获取jsapi_ticket失败';
            $this->error($errmsg);
        }
        $update = [
            'ticket' => $WeChatData['ticket'],
            'ticket_expires_time' => getTime() + $WeChatData['expires_in'] + 200,
        ];
        $this->wechatLogic->update_ticket($row['appid'], $update);

        return $update['ticket'];
    }

    private function getSignPackage($url = '')
    {
        $apiModel = model('v1.Api');
        $tokenData = $apiModel->get_access_token();
        if (empty($tokenData['code'])) {
            $this->error($tokenData['msg']);
        }
        $jsapiTicket = $this->get_jsapi_ticket($tokenData, $tokenData['access_token']);

        // 注意 URL 一定要动态获取，不能 hardcode.
        $url = $url ? $url : request()->domain() . ROOT_DIR;

        $timestamp = getTime();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $web_logo = tpCache('web.web_logo');
        $signPackage = array(
            "appId" => $tokenData['appid'],
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string,
            "jsapiTicket" => $jsapiTicket,
            "web_logo" => $web_logo,
        );
        return $signPackage;
    }

    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 首页
     */
    public function index()
    {
        $data = $this->apiLogic->taglibData();
        $this->renderSuccess($data);
    }

    /**
     * 分类页面
     * @return [type]          [description]
     */
    public function category()
    {
        $data = [];
        $show_type = input('param.show_type/d'); // 模板风格类型
        if (35 == $show_type) {
            // 商品分类列表
            // $result = model('v1.Category')->getProductCategory();
            // $data['list'] = !empty($result['list']) ? array_values($result['list']) : [];
            // $data['arclist'] = !empty($result['arclist']) ? array_values($result['arclist']): [];
        } else {
            $data = $this->apiLogic->taglibData();
            $data['channel'][0]['data'] = array_values($data['channel'][0]['data']);
        }

        $this->renderSuccess($data);
    }

    // 查询商品信息
    public function get_product_data()
    {
        if (IS_AJAX_POST) {
            $typeid = input('post.typeid/d');
            if (empty($typeid)) $this->error('数据异常');
            $ArchivesData = model('v1.Category')->GetProductData($typeid);
            $this->success('查询成功', null, $ArchivesData);
        }
    }

    /**
     * 文档列表
     * @param  string  $typeid 栏目ID
     * @return array          返回值
     */
    public function archivesList($typeid = '')
    {
        $data = $this->apiLogic->taglibData();
        $this->renderSuccess($data);
    }

    /**
     * 文档详情页
     * @param  string  $aid 文档ID
     * @param  string  $typeid 分类ID
     * @return array          返回值
     */
    public function archivesView($aid = '', $typeid = '')
    {
        $aid = intval($aid);
        $typeid = intval($typeid);

        if (empty($aid) && !empty($typeid)) { // 单页栏目详情页
            $data = $this->apiLogic->taglibData();
            $this->renderSuccess($data);
        }
        else { // 普通文档详情
            $users = $this->getUser(false);
            $view = model('v1.Api')->getArchivesView($aid, $users);
            $data = $this->apiLogic->taglibData($users);
            $data = array_merge($view, $data);
            $this->renderSuccess($data);
        }
    }

    /**
     * 联系我们
     * @param  string  $aid 文档ID
     * @return array          返回值
     */
    public function contact()
    {
        $data = model('v1.Api')->getContact();

        $this->renderSuccess($data);
    }

    /**
     * 留言栏目
     */
    public function guestbook_form()
    {
        $data = $this->apiLogic->taglibData();
        $this->renderSuccess($data);
    }

    /**
     * 发送邮箱
     * @return array          返回值
     */
    public function sendemail()
    {
        // 超时后，断掉邮件发送
        function_exists('set_time_limit') && set_time_limit(10);

        $type = input('param.type/s');
        
        // 留言发送邮件
        if (IS_POST && 'gbook_submit' == $type) {
            $aid = input('param.aid/d');
            $typeid = input('param.typeid/d');
            $form_type = input('param.form_type/d', 0);

            $send_email_scene = config('send_email_scene');
            $scene = $send_email_scene[1]['scene'];

            $web_name = tpCache('web.web_name');
            // 判断标题拼接
            if (!empty($form_type) && 1 === intval($form_type)) {
                $form_name = M('form')->where('form_id', $typeid)->getField('form_name');
                $web_name = $form_name.'-'.$web_name;
            } else {
                $arctype  = M('arctype')->field('typename')->find($typeid);
                $web_name = $arctype['typename'].'-'.$web_name;
            }

            // 拼装发送的字符串内容
            $row = M('guestbook_attribute')->field('a.attr_name, b.attr_value')
                ->alias('a')
                ->join('__GUESTBOOK_ATTR__ b', 'a.attr_id = b.attr_id AND a.typeid = '.$typeid, 'LEFT')
                ->where([
                    'b.aid' => $aid,
                ])
                ->order('a.attr_id sac')
                ->select();
            $content = '';
            foreach ($row as $key => $val) {
                if(10 == $val['attr_input_type']){
                    $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
                }if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = get_absolute_url(handle_subdir_pic($val['attr_value']));
                        $val['attr_value'] = "<a href='".$val['attr_value']."' target='_blank'><img src='".$val['attr_value']."' width='150' height='150' /></a>";
                    }
                } else {
                    $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
                }
                $content .= $val['attr_name'] . '：' . $val['attr_value'].'<br/>';
            }
            $html = "<p style='text-align: left;'>{$web_name}</p><p style='text-align: left;'>{$content}</p>";
            if (isWeixinApplets()) {
                $html .= "<p style='text-align: left;'>——来源：小程序端</p>";
            } else if (isMobile()) {
                $html .= "<p style='text-align: left;'>——来源：移动端</p>";
            } else {
                $html .= "<p style='text-align: left;'>——来源：电脑端</p>";
            }
            
            // 发送邮件
            $res = send_email(null,null,$html, $scene);
            if (intval($res['code']) == 1) {
                $this->renderSuccess($res);
            } else {
                $this->error($res['msg']);
            }
        }
    }

    // 发送留言短信
    private function sendGbookSms($type = 'gbook_submit', $send_scene = 11)
    {
        // 超时后，断掉邮件发送
        function_exists('set_time_limit') && set_time_limit(10);
        
        // 留言发送短信
        if ('gbook_submit' == $type) {
            $sms_config = tpCache('sms');
            // 配置不接收留言短信提醒
            if (!empty($sms_config['sms_guestbook_send'])) {
                // 短信模板无内容
                $sms_type = $sms_config['sms_type'] ? intval($sms_config['sms_type']) : 1;
                $tpl_content = Db::name('sms_template')->where(["send_scene" => $send_scene, "sms_type" => $sms_type])->value('tpl_content');
                // 发送短信
                if (!empty($tpl_content)) sendSms($send_scene, $sms_config['sms_test_mobile'], []);
            }
        }
    }

    /**
     * 用户自动登录
     * @return array
     * @throws \app\common\exception\BaseException
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function users_login()
    {
        if (empty($this->globalConfig['web_users_switch'])) {
            $this->error('后台会员中心尚未开启！');
        }
        $userModel = model('v1.User');
        return $this->renderSuccess([
            'users_id' => $userModel->login(input('post.', null, 'htmlspecialchars_decode')),
            'token' => $userModel->getToken()
        ]);
    }

    /**
     * 获取当前用户信息
     * @return array
     * @throws \app\common\exception\BaseException
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function users_detail()
    {
        if (empty($this->globalConfig['web_users_switch'])) {
            $this->error('后台会员中心尚未开启！');
        }

        // 当前用户信息
        $users = model('v1.User')->getUser(false);
        $data = [
            'userInfo' => $users,
        ];

        // 开启商城中心
        if (!empty($this->usersConfig['shop_open'])) {
            $shopModel = model('v1.Shop');
            $data['orderCount'] = [
                'payment' => $shopModel->getOrderCount($users, 'payment'),
                'delivery' => $shopModel->getOrderCount($users, 'delivery'),
                'received' => $shopModel->getOrderCount($users, 'received'),
            ];
            $data['coupon'] = model('v1.api')->getCouponCount($users); // 优惠券数量
            $data['product'] = model('v1.api')->getRecomProduct(); // 可能你还想要
        }

        // 是否安装积分商城插件
        $data['showPointsShop'] = false;
        $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
        if (!empty($weappInfo)) {
            // 调用积分商城逻辑层方法
            $pointsShopLogic = new \app\plugins\logic\PointsShopLogic($users);
            $data['showPointsShop'] = $pointsShopLogic->showPointsShop($weappInfo);
        }

        // 是否安装订单核销插件
        $data['showVerifyOrder'] = false;
        $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
        if (!empty($weappInfo)) {
            // 调用订单核销逻辑层方法
            $verifyLogic = new \app\plugins\logic\VerifyLogic($users);
            $data['showVerifyOrder'] = $verifyLogic->showVerifyOrder($weappInfo);
        }
        // 是否安装订单核销插件
        $data['showVerifyOrder'] = false;
        $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
        if (!empty($weappInfo)) {
            // 调用订单核销逻辑层方法
            $verifyLogic = new \app\plugins\logic\VerifyLogic($users);
            $data['showVerifyOrder'] = $verifyLogic->showVerifyOrder($weappInfo);
        }
        // 是否安装抽奖插件
        $data['showLotterydraw'] = false;
        $weappInfo = model('ShopPublicHandle')->getWeappInfo("Lotterydraw");
        if (!empty($weappInfo)) {
            // 调用订单核销逻辑层方法
            $lotterydrawLogic = new \weapp\Lotterydraw\logic\LotterydrawLogic();
            $data['showLotterydraw'] = $lotterydrawLogic->showLotterydraw($weappInfo);
        }

        $tagData = $this->apiLogic->taglibData($users);
        $data = array_merge($data, $tagData);
        return $this->renderSuccess($data);
    }
        /**
     * 发送 POST 请求
     */
    private function makePostRequest($url, array $data)
    {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // 设置返回内容
        curl_setopt($ch, CURLOPT_POST, true);  // 设置 POST 请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);  // 设置 POST 请求的数据
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'  // 设置请求的内容类型为 JSON
        ]);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // 开启 cURL 调试模式
        
        $response = curl_exec($ch);
    
        // 如果 curl_exec 失败
        if ($response === false) {
            $error = curl_error($ch);  // 获取详细的错误信息
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  // 获取 HTTP 状态码
            // 输出调试信息
            throw new \Exception("CURL 请求失败: $error, HTTP 状态码: $httpCode");
        }
    
        curl_close($ch);
        return $response;
    }
      /**
     * 发起 GET 请求
     */
    private function httpGet($url)
    {
        // 使用 cURL 发起 HTTP GET 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    
        // 将返回的 JSON 数据解析成数组
        return json_decode($response, true);
    }
    public function Logisticswx()
    {
        if (IS_AJAX) {
            // 判断是否安装了物流小程序插件（假设插件名为 Logisticswx）
            $weappInfo = model('ShopPublicHandle')->getWeappInfo('Logisticswx');
            
            if (!empty($weappInfo)) {
                // 插件已安装，执行相关操作
                $trackingNumber = input('post.trackingNumber', '', 'trim'); // 获取 POST 请求中的 trackingNumber
                $userId = input('post.userId', '', 'trim'); // 获取 POST 请求中的 userId
                $mobile = input('post.mobile', '', 'trim'); // 获取 POST 请求中的 userId
                $delivery = input('post.delivery', '', 'trim'); // 获取 POST 请求中的 userId
    
                if (empty($trackingNumber)) {
                    return json(['code' => 0, 'msg' => 'Tracking number cannot be empty']);
                }
    
                if (empty($userId)) {
                    return json(['code' => 0, 'msg' => 'User ID cannot be empty']);
                }
    
                try {
                    // 获取微信 access_token
                    $accessToken =  get_weixin_access_token(true, 'other');
                    if (!$accessToken) {
                        return json(['code' => 0, 'msg' => 'Failed to get access token']);
                    }
    
                    // 查询用户的 openid
                    $openid = Db::name('wx_users')->where(['users_id' => $userId])->value('openid');
                    if (empty($openid)) {
                        return json(['code' => 0, 'msg' => 'openid not found for user']);
                    }
    
                    // 调用微信物流 API 获取运单信息
                    $url = "https://api.weixin.qq.com/cgi-bin/express/delivery/open_msg/trace_waybill?access_token={$accessToken['access_token']}";
                    $postData = [
                        'waybill_id' => $trackingNumber,
                        'openid' => $openid, // 添加 openid 参数
                        'receiver_phone' => $mobile,
                        'delivery_id' => $delivery,
                    ];
    
                    // 使用 makePostRequest 方法发送 POST 请求
                    $response = $this->makePostRequest($url, $postData);
                    $response = json_decode($response, true); // 解析返回的 JSON 响应
                   
                    if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
                        return json(['code' => 1, 'msg' => 'Success', 'data' => $response]);
                    } else {
                        return json(['code' => 0, 'msg' => 'Failed to retrieve waybill info']);
                    }
                } catch (\Exception $e) {
                    return json(['code' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
                }
            }
        }
    
        // 如果不是 AJAX 请求，或者插件没有安装，返回错误
        $this->error('请求错误！');
    }


    /**
     * 微信支付成功异步通知 (shop_order)
     * @throws BaseException
     * @throws \Exception
     * @throws \think\exception\DbException
     */
    public function wxpay_notify()
    {
//        $xml = <<<EOF
// <xml><a><![CDATA[wxpay_notify]]></a>
// <appid><![CDATA[wx8f143c88b8946bd7]]></appid>
// <attach><![CDATA[微信小程序支付]]></attach>
// <bank_type><![CDATA[OTHERS]]></bank_type>
// <c><![CDATA[v1.Api]]></c>
// <cash_fee><![CDATA[1]]></cash_fee>
// <fee_type><![CDATA[CNY]]></fee_type>
// <is_subscribe><![CDATA[N]]></is_subscribe>
// <m><![CDATA[api]]></m>
// <mch_id><![CDATA[1604998382]]></mch_id>
// <nonce_str><![CDATA[9252a7a2244dd45858fb8d18b914f663]]></nonce_str>
// <openid><![CDATA[oRObw5V57ISeTXkW32qXTYc7V-oE]]></openid>
// <out_trade_no><![CDATA[20230402168042847493]]></out_trade_no>
// <result_code><![CDATA[SUCCESS]]></result_code>
// <return_code><![CDATA[SUCCESS]]></return_code>
// <sign><![CDATA[F472710FA0BE4FF89AB8E38EFDD58061]]></sign>
// <time_end><![CDATA[20230402174121]]></time_end>
// <total_fee>1</total_fee>
// <trade_type><![CDATA[JSAPI]]></trade_type>
// <transaction_id><![CDATA[4200066278202304023173075693]]></transaction_id>
// </xml>
// EOF;
        $userModel = model('v1.User');
        $xml = file_get_contents('php://input');
        if (!$xml) {
            $userModel->returnCode(false, 'Not found DATA');
        }
        // 将服务器返回的XML数据转化为数组
        $data = $userModel->fromXml($xml);
        // 订单信息
        $order = Db::name("shop_order")->where(['order_code' => $data['out_trade_no']])->find();
        empty($order) && $userModel->returnCode(false, '订单不存在');
        // 保存微信服务器返回的签名sign
        $dataSign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        // 生成签名
        $sign = $userModel->makeSign($data);
        // 判断签名是否正确 判断支付状态
        if (
            ($sign !== $dataSign)
            || ($data['return_code'] !== 'SUCCESS')
            || ($data['result_code'] !== 'SUCCESS')
        ) {
            $userModel->returnCode(false, '签名失败');
        }

        // 订单支付成功业务处理
        $openid = Db::name('wx_users')->where(['users_id'=>$order['users_id']])->getField('openid');
        $PostData = [
            'openid' => $openid,
            'users_id' => $order['users_id'],
            'order_id' => $order['order_id'],
            'order_code' => $order['order_code'],
        ];
        $redata = model('v1.Shop')->WechatAppletsPayDealWith($PostData, true);
        if (isset($redata['code']) && empty($redata['code'])) {
            $userModel->returnCode(false, $redata['msg']);
        }
        // 返回状态
        $userModel->returnCode(true, 'OK');
    }

    /**
     * 微信支付成功异步通知 (users_money表)
     * @throws BaseException
     * @throws \Exception
     * @throws \think\exception\DbException
     */
    public function wxpay_notify_users()
    {
        $userModel = model('v1.User');

        if (!$xml = file_get_contents('php://input')) {
            $userModel->returnCode(false, 'Not found DATA');
        }
        // 将服务器返回的XML数据转化为数组
        $data = $userModel->fromXml($xml);
        // 订单信息
        $order = Db::name("users_money")->where(['order_number' => $data['out_trade_no']])->find();
        empty($order) && $userModel->returnCode(false, '订单不存在');
        // 保存微信服务器返回的签名sign
        $dataSign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        // 生成签名
        $sign = $userModel->makeSign($data);
        // 判断签名是否正确 判断支付状态
        if (
            ($sign !== $dataSign)
            || ($data['return_code'] !== 'SUCCESS')
            || ($data['result_code'] !== 'SUCCESS')
        ) {
            $userModel->returnCode(false, '签名失败');
        }

        // 订单支付成功业务处理
        $openid = Db::name('wx_users')->where(['users_id'=>$order['users_id']])->getField('openid');
        $PostData = [
            'openid' => $openid,
            'users_id' => $order['users_id'],
            'moneyid' => $order['moneyid'],
            'order_number' => $order['order_number'],
        ];
        $redata = model('v1.Shop')->WechatAppletsPayDealWithUsersMoney($PostData, true);
        if (isset($redata['code']) && empty($redata['code'])) {
            $userModel->returnCode(false, $redata['msg']);
        }
        // 返回状态
        $userModel->returnCode(true, 'OK');
    }

    /**
     * 微信支付成功异步通知 (meida_order表)
     * @throws BaseException
     * @throws \Exception
     * @throws \think\exception\DbException
     */
    public function wxpay_notify_media()
    {
        $userModel = model('v1.User');

        if (!$xml = file_get_contents('php://input')) {
            $userModel->returnCode(false, 'Not found DATA');
        }
        // 将服务器返回的XML数据转化为数组
        $data = $userModel->fromXml($xml);
        // 订单信息
        $order = Db::name("meida_order")->where(['order_code' => $data['out_trade_no']])->find();
        empty($order) && $userModel->returnCode(false, '订单不存在');
        // 保存微信服务器返回的签名sign
        $dataSign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        // 生成签名
        $sign = $userModel->makeSign($data);
        // 判断签名是否正确 判断支付状态
        if (
            ($sign !== $dataSign)
            || ($data['return_code'] !== 'SUCCESS')
            || ($data['result_code'] !== 'SUCCESS')
        ) {
            $userModel->returnCode(false, '签名失败');
        }

        // 订单支付成功业务处理
        $openid = Db::name('wx_users')->where(['users_id'=>$order['users_id']])->getField('openid');
        $PostData = [
            'openid' => $openid,
            'users_id' => $order['users_id'],
            'order_id' => $order['order_id'],
            'order_code' => $order['order_code'],
        ];
        $redata = model('v1.Shop')->WechatAppletsPayDealWithMedia($PostData, true);
        if (isset($redata['code']) && empty($redata['code'])) {
            $userModel->returnCode(false, $redata['msg']);
        }
        // 返回状态
        $userModel->returnCode(true, 'OK');
    }

    // 生成商品二维码海报
    public function createGoodsShareQrcodePoster()
    {
        if (IS_AJAX_POST) {
            // 海报模型
            $posterModel = model('v1.Poster');

            // 调用接口生成海报
            $post = input('post.');
            $post['aid'] = intval($post['aid']);
            $post['typeid'] = intval($post['typeid']);
            $users = $this->getUser(false);
            $post['mid'] = !empty($users['users_id']) ? $users['users_id'] : 0;
            if (!empty($users['dealer']) && !empty($users['dealer']['users_id']) && !empty($users['dealer']['dealer_id'])) {
                $post['users_id'] = intval($users['dealer']['users_id']);
                $post['dealer_id'] = intval($users['dealer']['dealer_id']);
            }
            $qrcodePoster = $posterModel->getCreateGoodsShareQrcodePoster($post, 2);
            if (!empty($qrcodePoster) && !empty($qrcodePoster['poster'])) {
                $this->success('海报生成成功', null, $qrcodePoster);
            } else {
                $this->error('生成失败'.$qrcodePoster['errmsg']);
            }
        }
    }

    // 生成文章二维码海报
    public function createArticleShareQrcodePoster()
    {
        if (IS_AJAX_POST) {
            // 海报模型
            $posterModel = model('v1.Poster');

            // 调用接口生成海报
            $post = input('post.');
            $post['aid'] = intval($post['aid']);
            $post['typeid'] = intval($post['typeid']);
            $QrcodePoster = $posterModel->GetCreateGoodsShareQrcodePoster($post, 1);
            if (!empty($QrcodePoster) && !empty($QrcodePoster['poster'])) {
                $this->success('海报生成成功', null, $QrcodePoster);
            } else {
                $this->error('生成失败'.$QrcodePoster['errmsg']);
            }
        }
    }

  

      // 提交文章评论
      public function submitArticleComment()
      {
          if (IS_AJAX) {
              if (!is_dir('./weapp/Comment/')){
                  $this->error('请先安装评论插件');
              }
              $param = input('param.');
              if (empty($param['aid'])) $this->error('数据错误，刷新重试');
              if (empty($param['content'])) $this->error('请输入您的评论内容');
  
              $users = $this->getUser(false);
  
              // 添加文章评论模型
              $res = model('v1.Api')->addArticleComment($param, $users);
              if (0 < $res['code']) {
                  $this->success($res['msg'], null, ['is_show'=>$res['is_show']]);
              } else {
                  $this->error($res['msg']);
              }
          }
      }
      // 提交文章评论回复
    public function addArticleReply()
        {
            if (IS_AJAX) {
                // 检查是否安装评论插件
                if (!is_dir('./weapp/Comment/')) {
                    $this->error('请先安装评论插件');
                }

                // 获取前端传递的参数
                $param = input('param.');
               
                // 校验必填参数
                if (empty($param['aid'])) {
                    $this->error('数据错误，刷新重试');
                }
                if (empty($param['content'])) {
                    $this->error('请输入您的评论内容');
                }
                
                // 获取当前用户信息
                $users = $this->getUser(false);
                
                // 获取评论或回复的父评论 ID
                $param['comment_id'] = !empty($param['comment_id']) ? intval($param['comment_id']) : 0;
           
                // 调用模型方法添加评论回复
                $res = model('v1.Api')->addArticleReply($param, $users);
             
                // 返回结果
                if (0 < $res['code']) {
                    $this->success($res['msg'], null, ['is_show' => $res['is_show']]);
                } else {
                    $this->error($res['msg']);
                }
            }
        }

    /**
     * 购物车列表
     */
    public function shop_cart_list()
    {
        if (IS_AJAX) {
            $users = $this->getUser(false);
            if (!empty($users)) {
                // 商城模型
                $ShopModel = model('v1.Shop');

                // 获取商品信息生成订单并支付
                $ShopCart = $ShopModel->ShopCartList($users['users_id'], $users['level_discount'], $users['level_id']);
            } else {
                $ShopCart = [];
            }

            $this->renderSuccess($ShopCart);
        }
    }

    /**
     * 上传评论图片
     * @return array
     */
    public function uploads()
    {
        if (IS_AJAX_POST) {
            $file_type = input('param.file_type/s',"");
            $data = func_common('file', 'minicode',$file_type);
            $is_absolute = input('param.is_absolute/d',0);
            if (!empty($data['img_url'])) {
                if ($is_absolute){
                    $data['img_url'] = get_absolute_url($data['img_url'],'default',true);
                }
                $this->success('上传成功！','',$data);
            }
        }
        $msg = empty($data['errmsg']) ? '上传失败' : $data['errmsg'];
        $this->error($msg);
    }

    /**
     * 获取评论列表
     */
    public function get_goods_comment_list()
    {
        if (IS_AJAX) {
            $param = input('param.');
            // 获取商品信息生成订单并支付
            $commentList = model('v1.Api')->getGoodsCommentList($param);
            $this->success('success','',$commentList);

//            $this->renderSuccess($commentList);
        }
    }

    /**
     * 获取秒杀列表
     */
    public function get_sharp_index()
    {
        // 商城模型
        $ShopModel = model('v1.Shop');

        // 获取秒杀tabbar
        $tabbar = $ShopModel->GetSharpTabbar();
        $SharpList = [];
        if (!empty($tabbar)){
            // 获取秒杀列表
            $SharpList = $ShopModel->GetSharpIndex($tabbar[0]['active_time_id']);
        }
        $this->renderSuccess(['goodsList'=>$SharpList,'tab'=>$tabbar]);
    }

    /**
     * 获取秒杀商品列表
     */
    public function get_sharp_goods_index($active_time_id = '', $page = 1)
    {
        // 商城模型
        $DiyminiproModel = model('v1.Shop');
        // 获取秒杀商品分页列表
        $SharpList = $DiyminiproModel->GetSharpIndex($active_time_id,$page);

        $this->renderSuccess(['goodsList'=>$SharpList]);
    }
    /**
     * 获取秒杀商品详情
     */
    public function get_sharp_goods($aid=0,$active_time_id=0)
    {
        // 文档详情
        $data = model('v1.Api')->GetSharpGoods($aid);
        $data['detail']['active_time_id'] = $active_time_id;
        // 商城模型
        $ShopModel = model('v1.User');
        // 获取秒杀商品活动场次信息
        $data['active'] = $ShopModel->GetSharp($active_time_id,$aid);

        $this->renderSuccess($data);
    }

    //上传头像
     public function upload_head_pic()
     {
        if (IS_AJAX_POST) {
            $data = func_common('file', 'minicode');
            if (!empty($data['img_url'])){
                $data['url'] = $data['img_url'];
                if (!is_http_url($data['img_url'])) {
                    $data['img_url'] = request()->domain().ROOT_DIR.$data['img_url'];
                }
                $this->success('上传成功！','',$data);
            }
        }
        $msg = empty($data['errmsg']) ? '上传失败' : $data['errmsg'];
        $this->error($msg);
     }

     //获取购物车数量
    public function get_cart_total_num()
    {
        $data['cart_total_num'] = model('v1.Shop')->getCartTotalNum();
        $this->renderSuccess($data);
    }

    /**
     * 获取限时折扣列表
     */
    public function get_discount_index()
    {
        $param = input('param.');
        if (empty($param['active_id'])){
            $this->error('缺少必要参数！');
        }
        // 商城模型
        $ShopModel = model('v1.Shop');

        $DiscountGoodsList = $ShopModel->GetDiscountIndex($param);

        $this->renderSuccess(['goodsList'=>$DiscountGoodsList]);
    }
    /**
     * 获取限时折扣商品详情
     */
    public function get_discount_goods($aid=0,$active_id=0)
    {
        // 文档详情
        $data = model('v1.Api')->GetDiscountGoods($aid);
        $data['detail']['active_id'] = $active_id;
        // 商城模型
        $ShopModel = model('v1.Shop');
        // 获取秒杀商品活动场次信息
        $data['active'] = $ShopModel->GetDiscount($active_id);

        $this->renderSuccess($data);
    }

    /**
     * 添加我的浏览足迹
     */
    public function set_footprint()
    {
        $aid = input('param.aid/d');
        $users = $this->getUser(false);
        if (empty($users['users_id']) || empty($aid)) {
            $this->success('不达到记录的条件');
        }

        $users_id = intval($users['users_id']);
        //查询标题模型缩略图信息
        $arc = Db::name('archives')
            ->field('aid,channel,typeid,title,litpic')
            ->find($aid);
        if (!empty($arc)) {
            $count = Db::name('users_footprint')->where([
                'users_id' => $users_id,
                'aid'      => $aid,
            ])->count();

            if (empty($count)) {
                // 足迹记录条数限制
                $user_footprint_limit = config('global.user_footprint_limit');
                if (!$user_footprint_limit) {
                    $user_footprint_limit = 100;
                    config('global.user_footprint_limit',$user_footprint_limit);
                }
                $user_footprint_record = Db::name('users_footprint')->where(['users_id'=>$users_id])->count("id");
                if ($user_footprint_record == $user_footprint_limit) {
                    Db::name('users_footprint')->where(['users_id' => $users_id])->order("update_time ASC")->limit(1)->delete();
                }elseif ($user_footprint_record > $user_footprint_limit) {
                    $del_count = $user_footprint_record-$user_footprint_limit+1;
                    $del_ids = Db::name('users_footprint')->field("id")->where(['users_id' => $this->users_id])->order("update_time ASC")->limit($del_count)->select();
                    $del_ids = get_arr_column($del_ids,'id');
                    Db::name('users_footprint')->where(['id' => ['IN',$del_ids]])->delete();
                }

                $arc['users_id']    = $users_id;
                $arc['lang']        = $this->home_lang;
                $arc['add_time']    = getTime();
                $arc['update_time'] = getTime();
                Db::name('users_footprint')->add($arc);
            } else {
                Db::name('users_footprint')->where([
                    'users_id' => $users_id,
                    'aid'      => $aid
                ])->update([
                    'update_time' => getTime(),
                ]);
            }
            $this->success('保存成功');
        }
    }
    /**
     * 留言栏目数据提交
     */
    public function guestbook($typeid = '')
    {
        $param = input('param.');
        if (IS_POST && !isset($param['apiGuestbookform'])) {
            $post = input('post.');
            $typeid = !empty($post['typeid']) ? intval($post['typeid']) : $typeid;
            $form_type = !empty($post['form_type']) ? intval($post['form_type']) : 0;
            if (empty($typeid)) $this->error('post接口缺少typeid的参数与值！');
            // 是否开源小程序提交
            if (!empty($param['applets_id'])) $form_type = 1;

            /*留言间隔限制*/
            $channel_guestbook_interval = tpSetting('channel_guestbook.channel_guestbook_interval');
            $channel_guestbook_interval = is_numeric($channel_guestbook_interval) ? intval($channel_guestbook_interval) : 60;
            if (0 < $channel_guestbook_interval) {
                $map = array(
                    'ip'    => clientIP(),
                    'typeid'    => $typeid,
                    'form_type' => $form_type,
                    'add_time'  => array('gt', getTime() - $channel_guestbook_interval),
                );
                $count = Db::name('guestbook')->where($map)->count('aid');
                if (!empty($count)) {
                    $this->error("同一个IP在{$channel_guestbook_interval}秒之内不能重复提交！");
                }
            }
            /*end*/

            // 提取表单令牌的token变量名
            $token = '__token__';
            foreach ($post as $key => $val) {
                if (preg_match('/^__token__/i', $key)) {
                    $token = $key;
                    continue;
                }
            }

            //判断必填项
            $ContentArr = []; // 添加站内信所需参数
            foreach ($post as $key => $value) {
                if (stripos($key, "attr_") !== false) {
                    //处理得到自定义属性id
                    $attr_id = substr($key, 5);
                    $attr_id = intval($attr_id);
                    $ga_data = Db::name('guestbook_attribute')->where([
                        'attr_id'   => $attr_id,
                    ])->find();
                    if ($ga_data['required'] == 1 && empty($value)) {
                        $this->error($ga_data['attr_name'] . '不能为空！');
                    }

                    if ($ga_data['validate_type'] == 6 && !empty($value)) {
                        $pattern  = "/^1\d{10}$/";
                        if (!preg_match($pattern, $value)) {
                            $this->error($ga_data['attr_name'] . '格式不正确！');
                        }
                    } elseif ($ga_data['validate_type'] == 7 && !empty($value)) {
                        $pattern  = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";
                        if (preg_match($pattern, $value) == false) {
                            $this->error($ga_data['attr_name'] . '格式不正确！');
                        }
                    }
                    // 添加站内信所需参数
                    array_push($ContentArr, $value);
                }
            }
            $users = $this->getUser(false);

            $newData = array(
                'typeid'    => $typeid,
                'form_type' => $form_type,
                'users_id'  => !empty($users['users_id']) ? $users['users_id'] : 0,
                'channel'   => 8,
                'ip'        => clientIP(),
                'lang'      => get_main_lang(),
                'add_time'  => getTime(),
                'update_time' => getTime(),
            );
            $data    = array_merge($post, $newData);
            // 是否开源小程序提交
            if (!empty($param['applets_id'])) {
                $data['source'] = 3;
                $data['channel'] = 0;
            }

            /*表单令牌*/
            $token_value = !empty($data[$token]) ? $data[$token] : '';
            $session_path = \think\Config::get('session.path');
            $session_file = ROOT_PATH . $session_path . "/sess_".str_replace('__token__', '', $token);
            $filesize = @filesize($session_file);
            if(file_exists($session_file) && !empty($filesize)) {
                $fp = fopen($session_file, 'r');
                $token_v = fread($fp, $filesize);
                fclose($fp);
                if ($token_v != $token_value) {
                    $this->error('表单令牌无效！');
                }
            } else {
                $this->error('表单令牌无效！');
            }
            /*end*/

            $guestbookRow = [];
            /*处理是否重复表单数据的提交*/
            $formdata = $data;
            foreach ($formdata as $key => $val) {
                if (in_array($key, ['typeid', 'lang']) || preg_match('/^attr_(\d+)$/i', $key)) {
                    continue;
                }
                unset($formdata[$key]);
            }
            $md5data         = md5(serialize($formdata));
            $data['md5data'] = $md5data;
            $guestbookRow    = Db::name('guestbook')->field('aid')->where(['md5data' => $md5data])->find();
            /*--end*/

            $aid = !empty($guestbookRow['aid']) ? $guestbookRow['aid'] : 0;
            if (empty($guestbookRow)) { // 非重复表单的才能写入数据库
                $examine = Db::name('form')->where('form_id',$typeid)->value('open_examine');
                $data['examine'] = empty($examine) ? 1 : 0;
                $aid = Db::name('guestbook')->insertGetId($data);
                if ($aid > 0) {
                    $res = model('v1.Api')->saveGuestbookAttr($post, $aid, $typeid, $form_type);
                    if ($res){
                        $this->error($res);
                    }
                }
            } else {
                // 存在重复数据的表单，将在后台显示在最前面
                Db::name('guestbook')->where('aid', $aid)->update([
                    'add_time' => getTime(),
                    'update_time' => getTime(),
                ]);
            }
            @unlink($session_file);
            // 发送站内信给后台
            SendNotifyMessage($ContentArr, 1, 1, 0,'',['aid'=>$aid]);
            // 发送留言短信
            $this->sendGbookSms();
            $this->renderSuccess(['aid'=>$aid], '提交成功');
        }
        $this->error('请求错误！');
    }

    /**
     * 获取下级地区
     */
    public function get_region()
    {
        if (IS_AJAX) {
            $pid  = input('pid/d', 0);
            $res = Db::name('region')->where('parent_id',$pid)->select();
            if (!empty($res)){
                array_unshift($res,['id'=>'','name'=>'请选择']);
            }
            $this->success('请求成功', null, $res);
        }
    }
    //问题列表
    public function get_ask_list()
    {
        $data = model('v1.Ask')->getAskList();
        $this->renderSuccess($data);
    }
     //问题对应答案列表
     public function get_ask_reply_list()
     {
         $data = model('v1.Ask')->getAskreplyList();
         $this->renderSuccess($data);
     }
    //问题类型列表
    public function get_ask_type_list(){
        $typeList = model('v1.Ask')->getTypeList();
        $data['typeList'] = $typeList;

        $this->renderSuccess($data);
    }
    //问题详情
    public function get_ask_details()
    {
        $users = $this->getUser(false);
        $data = model('v1.Ask')->GetAskDetails($users);
        $this->success('success','',$data);
    }

    /**
     * 索引页
     */
    public function repertory()
    {
        $page = input('param.page/d', 1);
        $data = model('v1.Api')->getRepertory($page);

        $this->success('请求成功', null, $data);
    }

    //获取表单令牌
    public function get_token()
    {
        $type = input('param.type/s');
        $type = !empty($type) ? $type : 'mobile';
        /*表单令牌*/
        $token_name = md5($type.'_token_'.md5(getTime().uniqid(mt_rand(), TRUE)));
        $token_value = md5($_SERVER['REQUEST_TIME_FLOAT']);
        $session_path = \think\Config::get('session.path');
        $session_file = ROOT_PATH . $session_path . "/sess_".$token_name;
        $fp = fopen($session_file, "w+");
        if (!empty($fp)) {
            if (fwrite($fp, $token_value)) {
                fclose($fp);
            }
        } else {
            file_put_contents ( $session_file,  $token_value);
        }
        /*end*/

        $result = array(
            'token' => [
                'name'  => '__token__'.$token_name,
                'value' => $token_value,
            ],
        );
        $this->success('success','',$result);
    }

    /**
     * 手机短信发送
     */
    public function send_mobile_code()
    {
        // 超时后，断掉发送
        function_exists('set_time_limit') && set_time_limit(5);
        // \think\Session::pause(); // 暂停session，防止session阻塞机制

        // 发送手机验证码
        if (IS_AJAX_POST) {
            $post = input('post.');
            $mobile = $post['mobile'];
            if (empty($mobile)) $this->error('手机号不能为空!');
            if (!check_mobile($mobile)) $this->error('手机号格式不正确!');
            $scene = !empty($post['scene']) ? $post['scene'] : 0;

            // 提取表单令牌的token变量名
            $token = '__token__';
            foreach ($post as $key => $val) {
                if (preg_match('/^__token__/i', $key)) {
                    $token = $key;
                    continue;
                }
            }

            /*表单令牌*/
            $token_value = !empty($post[$token]) ? $post[$token] : '';
            $session_path = \think\Config::get('session.path');
            $session_file = ROOT_PATH . $session_path . "/sess_".str_replace('__token__', '', $token);
            $filesize = @filesize($session_file);
            if(file_exists($session_file) && !empty($filesize)) {
                $fp = fopen($session_file, 'r');
                $token_v = fread($fp, $filesize);
                fclose($fp);
                if ($token_v != $token_value) {
                    $this->error('表单令牌无效！');
                }
            } else {
                $this->error('表单令牌无效！');
            }
            /*end*/

            /*是否存在手机号码*/
            $where = ['mobile' => $mobile];

            $Result = Db::name('users')->where($where)->count();
            /* END */
            if (0 == $scene) {
                if (!empty($Result)) $this->error('手机号码已注册');
            } else if (2 == $scene) {
                if (empty($Result)) $this->error('手机号码未注册');
            } else if (4 == $scene) {
                if (empty($Result)) $this->error('手机号码不存在');
            } else {
                if (!empty($Result)) $this->error('手机号码已存在');
            }

            /*是否允许再次发送*/
            $where = [
                'mobile'   => $mobile,
                'source'   => $scene,
                'status'   => 1,
                'is_use'   => 0,
                'add_time' => ['>', getTime() - 120]
            ];
            $Result = Db::name('sms_log')->where($where)->order('id desc')->count();

            if (!empty($Result) && false == config('sms_debug')) $this->error('120秒内只能发送一次！');
            /* END */


            /*发送并返回结果*/
            $Result = sendSms($scene, $mobile, array('content' => mt_rand(1000, 9999)));
            if (intval($Result['status']) == 1) {
                @unlink($session_file);
                $this->success('发送成功！');
            } else {
                $this->error($Result['msg']);
            }
            /* END */
        }
    }

    /**
     * 用户手机号注册
     */
    public function users_mobile_reg()
    {
        if (empty($this->globalConfig['web_users_switch'])) {
            $this->error('后台会员中心尚未开启！');
        }

        $userModel = model('v1.User');
        return $this->renderSuccess([
            'users_id' => $userModel->mobile_reg(input('post.', null, 'htmlspecialchars_decode')),
            'token' => $userModel->getToken()
        ]);
    }

    /**
     * 用户账号密码注册
     */
    public function users_account_reg()
    {
        if (empty($this->globalConfig["web_users_switch"])) {
            $this->error("后台会员中心尚未开启！");
        }

        $userModel = model("v1.User");
        return $this->renderSuccess([
            "users_id" => $userModel->account_reg(input("post.", null, "htmlspecialchars_decode")),
            "token" => $userModel->getToken()
        ]);
    }

    /**
     * 用户手机号验证码/手机号密码登录
     */
    public function users_mobile_login()
    {
        if (empty($this->globalConfig['web_users_switch'])) {
            $this->error('后台会员中心尚未开启！');
        }

        $userModel = model('v1.User');
        return $this->renderSuccess([
            'users_id' => $userModel->mobile_login(input('post.', null, 'htmlspecialchars_decode')),
            'token' => $userModel->getToken()
        ]);
    }

    /**
     * 用户账号密码登录
     */
    public function users_account_login()
    {
        if (empty($this->globalConfig['web_users_switch'])) {
            $this->error('后台会员中心尚未开启！');
        }

        $userModel = model('v1.User');
        return $this->renderSuccess([
            'users_id' => $userModel->account_login(input('post.', null, 'htmlspecialchars_decode')),
            'token' => $userModel->getToken()
        ]);
    }

    // 获取自由表单
    public function get_form()
    {
        $count = Db::name('guestbook_attribute')->count();
        if (empty($count)) {
            $this->error('404报错，接口不生效');
        }
        $data = $this->apiLogic->taglibData();
        $this->renderSuccess($data);
    }

    /**
     * 记录视频播放进程
     */
    public function record_media_process()
    {
        $aid = input('post.aid/d', 0);
        $file_id = input('post.file_id/d', 0);
        $timeDisplay = input('post.timeDisplay/d', 0);
        $users = $this->getUser(false);
        if (empty($users) || 0 == $timeDisplay) {
            $this->success('success');
        }
        $users_id = intval($users['users_id']);
        $where = ['users_id' => $users_id,
            'aid' => $aid,
            'file_id' => $file_id];
        $count = Db::name('media_play_record')->where($where)->find();
        $data = [
            'users_id' => $users_id,
            'aid' => intval($aid),
            'file_id' => intval($file_id),
            'play_time' => $timeDisplay,
            'update_time' => getTime(),
        ];
        if (!empty($count)) {
            $timeDisplay = $timeDisplay + $count['play_time'];
            $file_time = Db::name('media_file')->where('file_id', $file_id)->value('file_time');
            $data['play_time'] = $timeDisplay > $file_time ? $file_time : $timeDisplay;
            $data['play_time'] = intval($data['play_time']);
            //更新
            Db::name('media_play_record')->where($where)->update($data);
        } else {
            $data['add_time'] = getTime();
            Db::name('media_play_record')->insert($data);
        }
        $this->success('success');
    }

    // 积分商城插件操作(集合方法)
    public function points_shop_action()
    {
        if (IS_AJAX) {
            // 是否安装积分商城插件
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                // 调用积分商城逻辑层方法
                $users = $this->getUser(false);
                $pointsShopLogic = new \app\plugins\logic\PointsShopLogic($users);
                $pointsShopLogic->pointsShopAction($weappInfo);
            }
        }
        $this->error('请求错误！');
    }

    // 开源小程序插件操作(集合方法)
    public function applets_weapp_action()
    {
        if (IS_AJAX) {
            // 是否安装开源小程序插件
            $weappInfo = model('ShopPublicHandle')->getWeappInfo('Suibian');
            if (!empty($weappInfo)) {
                // 调用开源小程序逻辑层方法
                $users = $this->getUser(false);
                $suibianLogic = new \app\plugins\logic\SuibianLogic($users);
                $suibianLogic->suibianAction($weappInfo);
            }
        }
        $this->error('请求错误！');
    }
}
