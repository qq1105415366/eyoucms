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
 * Date: 2020-05-25
 */

namespace think\template\taglib\eyou;

use think\Config;
use think\Request;
use think\Db;

/**
 * 支付API列表
 */
load_trait('controller/Jump');
class TagSppayapilist extends Base
{ 
    use \traits\controller\Jump;

    /**
     * 会员ID
     */
    public $users_id = 0;
    public $users    = [];
    public $usersTplVersion    = '';
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        // 会员信息
        $this->users    = session('users');
        $this->users_id = session('users_id');
        $this->users_id = !empty($this->users_id) ? $this->users_id : 0;
        $this->usersTplVersion = getUsersTplVersion();
        $this->usersTpl2xVersion = getUsersTpl2xVersion();
        $this->shopPublicHandleModel = model('ShopPublicHandle');
    }

    /**
     * 获取提交订单数据
     */
    public function getSppayapilist()
    {
        // 接收数据读取解析
        $Paystr = input('param.paystr/s');
        $PayData = cookie($Paystr);

        if (!empty($PayData['moneyid']) && !empty($PayData['order_number'])) {
            // 充值信息
            $money_id = !empty($PayData['moneyid']) ? intval($PayData['moneyid']) : 0;
            $money_code = !empty($PayData['order_number']) ? $PayData['order_number'] : '';
        } else if (!empty($PayData['order_id']) && !empty($PayData['order_code'])) {
            // 订单信息
            $order_id   = !empty($PayData['order_id']) ? intval($PayData['order_id']) : 0;
            $order_code = !empty($PayData['order_code']) ? $PayData['order_code'] : '';
        }
        
        $JsonData['unified_id']       = '';
        $JsonData['unified_amount']   = '';
        $JsonData['unified_number']   = '';
        $JsonData['transaction_type'] = 3; // 交易类型，3为会员升级

        $Result = [];
        if (is_array($PayData) && (!empty($order_id) || !empty($money_id)) && (!empty($money_code) || !empty($order_code))) {
            if (!empty($money_id)) {
                // 获取会员充值信息
                $where = [
                    'moneyid'      => $money_id,
                    'order_number' => $money_code,
                    'users_id'     => $this->users_id,
                    'lang'         => self::$home_lang
                ];
                $Result = Db::name('users_money')->where($where)->find();
                if (empty($Result)) $this->error('订单不存在或已变更', url('user/Pay/pay_consumer_details'));

                // 组装数据返回
                $JsonData['transaction_type'] = 1; // 交易类型，1为充值
                $JsonData['unified_id']       = $Result['moneyid'];
                $JsonData['unified_amount']   = $Result['money'];
                $JsonData['unified_number']   = $Result['order_number'];

            } else if (!empty($order_id)) {
                if (!empty($PayData['type']) && 8 == $PayData['type']) {
                    // 获取支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => self::$home_lang
                    ];
                    $Result = Db::name('media_order')->where($where)->find();
                    if (empty($Result)) $this->error('订单不存在或已变更', url('user/Media/index'));
                    
                    $url = url('user/Media/index');
                    if (in_array($Result['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $JsonData['transaction_type'] = 8; // 交易类型，8为购买视频
                    $JsonData['unified_id']       = $Result['order_id'];
                    $JsonData['unified_amount']   = $Result['order_amount'];
                    $JsonData['unified_number']   = $Result['order_code'];

                }else if (!empty($PayData['type']) && 9 == $PayData['type']) {
                    // 获取文章支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => self::$home_lang
                    ];
                    $Result = Db::name('article_order')->where($where)->find();
                    if (empty($Result)) $this->error('订单不存在或已变更', url('user/Article/index'));

                    $url = url('user/Article/index');
                    if (in_array($Result['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $JsonData['transaction_type'] = 9; // 交易类型，9为购买文章
                    $JsonData['unified_id']       = $Result['order_id'];
                    $JsonData['unified_amount']   = $Result['order_amount'];
                    $JsonData['unified_number']   = $Result['order_code'];

                }else if (!empty($PayData['type']) && 10 == $PayData['type']) {
                    // 获取下载支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => self::$home_lang
                    ];
                    $Result = Db::name('download_order')->where($where)->find();
                    if (empty($Result)) $this->error('订单不存在或已变更', url('user/Download/index'));

                    $url = url('user/Download/index');
                    if (in_array($Result['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $JsonData['transaction_type'] = 10; // 交易类型，10为购买下载模型
                    $JsonData['unified_id']       = $Result['order_id'];
                    $JsonData['unified_amount']   = $Result['order_amount'];
                    $JsonData['unified_number']   = $Result['order_code'];

                } else {
                    // 获取支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => self::$home_lang
                    ];
                    $Result = Db::name('shop_order')->where($where)->find();
                    if (empty($Result)) $this->error('订单不存在或已变更', url('user/Shop/shop_centre'));
                    
                    // 判断订单状态，1已付款(待发货)，2已发货(待收货)，3已完成(确认收货)，-1订单取消(已关闭)，4订单过期
                    $url = urldecode(url('user/Shop/shop_order_details', ['order_id' => $Result['order_id']]));
                    if (in_array($Result['order_status'], [1, 2, 3])) {
                        $this->error('订单已支付，即将跳转', $url);
                    } elseif ($Result['order_status'] == 4) {
                        $this->error('订单已过期，即将跳转', $url);
                    } elseif ($Result['order_status'] == -1) {
                        $this->error('订单已关闭，即将跳转', $url);
                    }

                    // 组装数据返回
                    $JsonData['transaction_type'] = 2; // 交易类型，2为购买
                    $JsonData['unified_id']       = $Result['order_id'];
                    $JsonData['unified_amount']   = $Result['order_amount'];
                    $JsonData['unified_number']   = $Result['order_code'];
                }
            }
        }

        $where = [
            'status' => 1,
        ];
        if (isWeixinApplets()) $where['pay_mark'] = ['NEQ', 'alipay'];
        $PayApiList = Db::name('pay_api_config')->where($where)->select();

        $isPaypal = 0;
        if (!empty($PayApiList)) {
            $tagStatic = new \think\template\taglib\eyou\TagStatic;
            foreach ($PayApiList as $key => $value) {
                $PayApiList[$key]['pay_img'] = '';
                $PayApiList[$key]['hidden'] = '';
                $PayInfo = unserialize($value['pay_info']);
                // 微信支付
                if ('wechat' == $value['pay_mark']) {
                    $PayApiList[$key]['bgColor'] = '#22d465';
                    $PayApiList[$key]['pay_img'] = $tagStatic->getStatic('users/skin/images/pay_'.$value['pay_mark'].'.png');
                    if (!empty($PayInfo['is_open_wechat'])) {
                        $r1 = $this->shopPublicHandleModel->getHupijiaoPay('wechat');
                        if ($r1 == true) unset($PayApiList[$key]);
                    }
                }
                // 支付宝支付
                else if ('alipay' == $value['pay_mark']) {
                    $PayApiList[$key]['bgColor'] = '#0090ce';
                    $PayApiList[$key]['pay_img'] = $tagStatic->getStatic('users/skin/images/pay_'.$value['pay_mark'].'.png');
                    if (!empty($PayInfo['is_open_alipay'])) {
                        $r1 = $this->shopPublicHandleModel->getHupijiaoPay('alipay');
                        $r2 = $this->shopPublicHandleModel->getPersonPay();
                        if ($r1 == true && $r2 == true) unset($PayApiList[$key]);
                    }
                }
                // 第三方支付
                else if (0 == $value['system_built']) {
                    // Paypal支付
                    if ('Paypal' == $value['pay_mark']) {
                        $PayApiList[$key]['bgColor'] = '#013088';
                        if (!empty($PayInfo) && 0 == $PayInfo['is_open_pay']) {
                            foreach ($PayInfo as $kk => $vv) {
                                if ('business' == $kk && empty($vv)) {
                                    unset($PayApiList[$key]);
                                    break;
                                }
                            }
                        } else {
                            unset($PayApiList[$key]);
                        }
                    } else {
                        if (!empty($PayInfo) && 0 == $PayInfo['is_open_pay']) {
                            foreach ($PayInfo as $kk => $vv) {
                                if ('is_open_pay' != $kk && empty($vv)) {
                                    unset($PayApiList[$key]);
                                    break;
                                }
                            }
                        } else {
                            unset($PayApiList[$key]);
                        }
                    }
                    if (!empty($PayApiList[$key])) {
                        // Paypal支付
                        if ('Paypal' == $value['pay_mark']) {
                            $isPaypal = 1;
                            $paypalBusiness = $PayInfo['business'];
                        }
                        $PayApiList[$key]['pay_img'] = get_default_pic('/weapp/'.$value['pay_mark'].'/pay.png');
                    }

                    // 如果是支付宝当面付插件则删除(支付宝当面付插件已融入系统的支付宝支付，不需要单独展示)
                    if ('PersonPay' == $value['pay_mark']) unset($PayApiList[$key]);
                    // 如果是虎皮椒支付插件则删除(虎皮椒支付插件已融入系统的微信和支付宝支付，不需要单独展示)
                    if ('Hupijiaopay' == $value['pay_mark']) unset($PayApiList[$key]);
                }

                if (empty($value['pay_id'])) unset($PayApiList[$key]);
            }
        }
        $PayApiList = array_merge($PayApiList);

        // 传入JS参数
        $JsonData['IsMobile']        = isMobile() ? 1 : 0;
        $JsonData['is_wap']          = isWeixin() || isMobile() ? 1 : 0;
        $JsonData['PayDealWith']     = url('user/Pay/pay_deal_with', ['_ajax' => 1], true, false, 1, 1, 0);
        $JsonData['SelectPayMethod'] = url('user/PayApi/select_pay_method', ['_ajax' => 1], true, false, 1, 1, 0);
        $JsonData['OrderPayPolling'] = url('user/PayApi/order_pay_polling', ['_ajax' => 1], true, false, 1, 1, 0);
        $JsonData['UsersUpgradePay'] = url('user/PayApi/users_upgrade_pay', ['_ajax' => 1], true, false, 1, 1, 0);
        $JsonData['get_token_url']   = url('api/Ajax/get_token', ['name'=>'__token__'], true, false, 1, 1, 0);
        $JsonData['submitForm']      = '';
        if (!empty($JsonData) && !empty($paypalBusiness)) {
            $url = "https://www.paypal.com/cgi-bin/webscr";
            $return = url('plugins/Paypal/paypalNotifyHandlePay', ['transaction_type' => 2, 'is_notify' => 2], true, true, 1, 1, 0);
            $notify_url = url('plugins/Paypal/paypalNotifyHandlePay', ['transaction_type' => 2, 'is_notify' => 1], true, true, 1, 1, 0);
            $cancel_return = request()->domain() . ROOT_DIR;
            $JsonData['submitForm'] = <<<EOF
<br/>
<form id="eyou_paypalForm" style="text-align:center;" action="{$url}" method="post" target="_blank">
    <input type='hidden' name='cmd' value='_xclick'>
    <input type='hidden' name='business' value='{$paypalBusiness}'>
    <input type='hidden' name='item_name' value='' id='eyou_itemName'>
    <input type='hidden' name='amount' value='' id='eyou_amount'>
    <input type='hidden' name='currency_code' value='USD'>
    <input type='hidden' name='return' value='{$return}'>
    <input type='hidden' name='invoice' value='' id='eyou_invoice'>
    <input type='hidden' name='charset' value='utf-8'>
    <input type='hidden' name='no_shipping' value='1'>
    <input type='hidden' name='no_note' value=''>
    <input type='hidden' name='notify_url' value='{$notify_url}'>
    <input type='hidden' name='rm' value='2'>
    <input type='hidden' name='cancel_return' value='{$cancel_return}' id='eyou_cancel_return'>
    <input style="display: none;" type='submit' id='eyou_submitForm'>
</form>
<br/>
EOF;
        }
        $JsonData['usersTplVersion'] = $this->usersTplVersion;
        $JsonData['usersTpl2xVersion'] = $this->usersTpl2xVersion;
        $JsonData = json_encode($JsonData);
        $version = getCmsVersion();
        if (empty($this->usersTplVersion) || 'v1' == $this->usersTplVersion) {
            $jsfile = "tag_sppayapilist.js";
        } else {
            $jsfile = "tag_sppayapilist_{$this->usersTplVersion}.js";
        }
        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
        // 循环中第一个数据带上JS代码加载
        $indexKey = !empty($PayApiList) ? intval(count($PayApiList)) - 1 : 0;
        $PayApiList[0]["pay_mark"] = !empty($PayApiList[0]["pay_mark"]) ? $PayApiList[0]["pay_mark"] : '';
        $PayApiList[$indexKey]['hidden'] = <<<EOF
<script type="text/javascript">
    var eyou_data_json_v627847 = {$JsonData};
    $(function() {
        if ($('input[name=payment_type]')) {
            $('input[name=payment_type]').val('zxzf_{$PayApiList[0]["pay_mark"]}');
        }
    })
</script>
<script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.3.2.js"></script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
        return $PayApiList;
    }
}