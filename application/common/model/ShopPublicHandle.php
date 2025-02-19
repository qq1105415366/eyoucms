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
 * Date: 2019-1-7
 */

namespace app\common\model;

use think\Db;
use think\Cache;
use think\Config;
use think\Cookie;

/**
 * 商城公共处理模型
 */
load_trait('controller/Jump');
class ShopPublicHandle
{
    use \traits\controller\Jump;

    private $sendTerminal = '';

    // 构造函数
    public function __construct()
    {
        // 统一接收参数处理
        $this->times = getTime();
        $this->sendTerminal = input('param.sendTerminal/s', 'openSource');
        $this->sendTerminal = !empty($this->sendTerminal) ? trim($this->sendTerminal) : '';
    }

    // 微信授权 cookie 操作
    public function weChatauthorizeCookie($users_id = 0, $type = 'get', $save = [])
    {
        $cookieID = $users_id . '_weChatAuthorize';
        if ('get' == $type) {
            $open_id = Cookie::get($cookieID);
            return !empty($open_id) ? $open_id : '';
        } else if ('set' == $type) {
            Cookie::set($cookieID, $save['openid'], ['expire' => $save['expire']]);
        } else if ('del' == $type) {
            Cookie::delete($cookieID);
        }
    }

    // 获取订单商品规格列表(购买时的商品规格)
    public function getOrderGoodsSpecList($goods = [])
    {
        // 规格处理
        $goodsSpecList = [];
        $goodsSpec = !empty($goods['data']) ? unserialize($goods['data']) : [];
        $goodsSpec = empty($goodsSpec) && !empty($goods['detailsData']) ? unserialize($goods['detailsData']) : $goodsSpec;
        if (!empty($goodsSpec['spec_value'])) {
            $specValueArr = explode('<br/>', htmlspecialchars_decode($goodsSpec['spec_value']));
            foreach ($specValueArr as $value_10000) {
                $arr_10000 = !empty($value_10000) ? explode('：', $value_10000) : [];
                if (!empty($arr_10000[0]) && trim($arr_10000[0])) {
                    $goodsSpecList[] = [
                        'name'  => !empty($arr_10000[0]) ? trim($arr_10000[0]) : '',
                        'value' => !empty($arr_10000[1]) ? trim($arr_10000[1]) : '',
                    ];
                }
            }
        }
        // 返回规格列表
        return $goodsSpecList;
    }

    // 获取商品规格列表(查询数据库)
    public function getGoodsSpecList($goods = [])
    {
        $goodsSpecList = [];
        if (!empty($goods['spec_value_id'])) {
            $spec_value_id = explode('_', $goods['spec_value_id']);
            if (!empty($spec_value_id)) {
                $where = [
                    'aid' => $goods['aid'],
                    'lang' => get_home_lang(),
                    'spec_value_id' => ['IN', $spec_value_id]
                ];
                $productSpecData = Db::name("product_spec_data")->where($where)->field('spec_name, spec_value')->select();
                foreach ($productSpecData as $value_10001) {
                    $goodsSpecList[] = [
                        'name'  => !empty($value_10001['spec_name']) ? trim($value_10001['spec_name']) : '',
                        'value' => !empty($value_10001['spec_value']) ? trim(htmlspecialchars_decode($value_10001['spec_value'])) : '',
                    ];
                }
            }
        }
        // 返回规格列表
        return $goodsSpecList;
    }

    // 获取商品规格最低价格的划线价
    public function getGoodsSpecCrossedPrice($crossedPrice = 0, $goodsID = 0)
    {
        $where = [
            'aid' => intval($goodsID)
        ];
        $order = 'spec_price asc';
        $specCrossedPrice = Db::name('product_spec_value')->where($where)->order($order)->getField('spec_crossed_price');
        return !empty($specCrossedPrice) ? unifyPriceHandle($specCrossedPrice) : unifyPriceHandle($crossedPrice);
    }

    // 获取指定小程序的配置信息
    public function getSpecifyAppletsConfig($appletsID = 0, $appletsMark = '', $sendTerminal = '')
    {
        $this->sendTerminal = !empty($sendTerminal) ? trim($sendTerminal) : trim($this->sendTerminal);
        if (!empty($this->sendTerminal) && 'visualiz' == $this->sendTerminal) {
            // 可视化小程序配置
            $diyminiproMall = new \app\plugins\model\DiyminiproMall;
            $diyminiproInfo = $diyminiproMall->detail();
            $diyminiproMallSettingModel = new \weapp\DiyminiproMall\model\DiyminiproMallSettingModel;
            $setting = $diyminiproMallSettingModel->getSettingValue('setting');
            $result = [
                'appid' => !empty($setting['appId']) ? trim($setting['appId']) : '',
                'appsecret' => !empty($setting['appsecret']) ? trim($setting['appsecret']) : '',
                'mchid' => !empty($diyminiproInfo['mchid']) ? trim($diyminiproInfo['mchid']) : '',
                'apikey' => !empty($diyminiproInfo['apikey']) ? trim($diyminiproInfo['apikey']) : '',
                'plugins' => 'DiyminiproMall'
            ];
        } else {
            // 小程序ID及小程序标识
            $applets_id = !empty($appletsID) ? intval($appletsID) : input('param.applets_id/d', 0);
            $applets_mark = !empty($appletsMark) ? trim($appletsMark) : input('param.provider/s', 'weixin');

            $result = [];
            // 获取插件中创建的小程序配置
            if ($this->getWeappInfo('Suibian') && !empty($applets_id) && !empty($applets_mark)) {
                $result = model('WeappAppletsConfigList')->getAppletsConfigDetails($applets_id, $applets_mark);
                $result = !empty($result['config']) ? $result['config']['applets_config'] : [];
            }
            // 获取系统中创建的小程序配置
            else if (!empty($applets_mark)) {
                $result = tpSetting("OpenMinicode.conf_" . trim($applets_mark));
                $result = !empty($result) ? json_decode($result, true) : [];
            }
        }
        return $result;
    }

    // 保存微信发货推送表记录
    // $payConfig 里有个 plugins 字段目前只有可视化商城(DiyminiproMall)使用,传插件标识,因为可视化商城的订单要拿第三方服务商的token
    public function saveWxShippingInfo($usersID = 0, $orderCode = '', $orderSource = 0, $payConfig = [])
    {
        // 支付配置 可视化微信商城传参$payConfig,开源使用下面查询
        if (empty($payConfig)) $payConfig = $this->getSpecifyAppletsConfig();

        if ( (!empty($payConfig['appid']) && !empty($payConfig['mchid']) && !empty($payConfig['apikey'])) || (!empty($payConfig['appid']) && !empty($payConfig['mchid']) && !empty($payConfig['appsecret']) && in_array($payConfig['plugins'], ['DiyminiproMall'])) ) {
            // 查询相同来源未支付订单
            $where = [
                'users_id'     => intval($usersID),
                'order_source' => intval($orderSource),
                'order_code'   => trim($orderCode),
            ];
            $result = Db::name('wx_shipping_info')->where($where)->find();
            if (empty($result)) {
                $insert = [
                    'users_id'     => intval($usersID),
                    'order_code'   => trim($orderCode),
                    'order_source' => intval($orderSource),
                    'pay_success'  => 1,
                    'pay_config'   => serialize($payConfig),
                    'add_time'     => getTime(),
                    'update_time'  => getTime(),
                ];
                Db::name('wx_shipping_info')->insert($insert);
            }
        }
    }

    // 获取微信发货推送表记录
    public function getWxShippingInfo($usersID = 0, $orderCode = '', $orderSource = 0)
    {
        $where = [
            'users_id'     => intval($usersID),
            'order_code'   => trim($orderCode),
            'order_source' => intval($orderSource),
        ];
        $result = Db::name('wx_shipping_info')->where($where)->find();
        $result['pay_config'] = !empty($result['pay_config']) ? unserialize($result['pay_config']) : [];

        return $result;
    }

    // 支付成功更新微信发货推送表记录
    public function updateWxShippingInfo($usersID = 0, $orderCode = '', $orderSource = 0, $errcode = '', $errmsg = '')
    {
        $where = [
            'users_id'     => intval($usersID),
            'order_code'   => trim($orderCode),
            'order_source' => intval($orderSource),
        ];
        $update = [
            'pay_success' => 1,
            'errcode'     => $errcode,
            'errmsg'      => $errmsg,
            'update_time' => getTime(),
        ];
        return Db::name('wx_shipping_info')->where($where)->update($update);
    }

    // 推送微信发货推送表记录
    public function pushWxShippingInfo($usersID = 0, $orderCode = '', $orderSource = 0, $itemDesc = '', $config = [])
    {
        if (!empty($usersID) && !empty($orderCode) && !empty($orderSource)) {
            // 保存微信发货推送表记录
            $this->saveWxShippingInfo($usersID, $orderCode, $orderSource, $config);

            // 推送微信发货
            $data = [
                'users_id' => $usersID,
                'order_code' => strval($orderCode)
            ];
            $WxPayOrderLogic = new \app\common\logic\WxPayOrderLogic();
            $WxPayOrderLogic->minipro_send_goods($data, $orderSource, $itemDesc);
        }
    }

    // 获取微信小程序支付信息
    public function getWechatAppletsPay($users_id = 0, $code = '', $prices = 0, $type = 2, $config = [])
    {
        // 查询会员微信小程序 openid
        if (!empty($this->sendTerminal) && 'visualiz' == $this->sendTerminal) {
            $openid = Db::name('weapp_diyminipro_mall_users')->where('users_id', $users_id)->getField('openid');
        } else {
            $openid = Db::name('wx_users')->where('users_id', $users_id)->getField('openid');
        }
        // 调用user模块支付API模型
        $payApiModel = new \app\user\model\PayApi();
        // 返回微信小程序支付信息
        return $payApiModel->getWechatAppletsPay($openid, $code, $prices, $type, $config);
    }

    // 获取微信支付结果并执行支付成功业务逻辑
    public function getWeChatPayResult($users_id, $orderData = [], $type = 2, $config = [], $fromApplets = true, $fromAsync = false)
    {
        // 如果是异步则先查询一次订单信息
        $payApiLogic = new \app\user\logic\PayApiLogic($users_id, $fromApplets, $fromAsync);
        if (!empty($fromAsync)) {
            $post = [
                'order_pay_code' => trim($orderData['out_trade_no']),
                'unified_number' => trim($orderData['out_trade_no']),
                'transaction_type' => intval($type),
            ];
            $orderData = $payApiLogic->GetFindOrderData($post);
            // 查询会员的积分是否足够支付
            if (!empty($orderData['points_shop_order'])) {
                $where = [
                    'users_id' => intval($orderData['users_id']),
                    'order_id' => intval($orderData['order_id']),
                ];
                $detailsData = Db::name('shop_order_details')->where($where)->getField('data');
                $detailsData = !empty($detailsData) ? unserialize($detailsData) : [];
                $pointsGoodsBuyField = !empty($detailsData['pointsGoodsBuyField']) ? json_decode($detailsData['pointsGoodsBuyField'], true) : [];
                $usersScores = Db::name('users')->where('users_id', $users_id)->getField('scores');
                if (!empty($pointsGoodsBuyField['goodsTotalPoints']) && intval($pointsGoodsBuyField['goodsTotalPoints']) > intval($usersScores)) {
                    echo 'FAIL'; exit;
                }
            }
        }

        // 查询会员微信小程序 openid
        if (!empty($this->sendTerminal) && 'visualiz' == $this->sendTerminal) {
            $openid = Db::name('weapp_diyminipro_mall_users')->where('users_id', $users_id)->getField('openid');
        } else {
            $openid = Db::name('wx_users')->where('users_id', $users_id)->getField('openid');
        }

        // 调用user模块支付API模型
        $payApiModel = new \app\user\model\PayApi();
        // 返回微信小程序支付信息
        $result = $payApiModel->getWeChatPayResult($openid, $orderData['unified_number'], $config);
        if (!empty($result['return_code'])) {
            // 如果存在错误则直接提示
            if ('FAIL' == $result['return_code'] && !empty($result['return_msg'])) $this->error($result['return_msg']);
            // 查询成功后续处理
            if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
                $orderData['transaction_type'] = !empty($orderData['transaction_type']) ? intval($orderData['transaction_type']) : intval($type);
                if ($result['trade_state'] == 'SUCCESS' && !empty($result['transaction_id'])) {
                    $payApiLogic->OrderProcessing($orderData, $orderData, $result, $config);
                } else if ($result['trade_state'] == 'NOTPAY') {
                    if (empty($fromAsync)) $this->error('订单尚未完成支付');
                }
            }
        }
        // 未知异常则返回提示
        if (empty($fromAsync)) $this->error('微信接口异常，如已真实完成支付请联系客服查询微信支付');
    }

    // 商品购买确认页 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
    public function goodsBuyPagePublicHandle($resultData = [])
    {
        // 其他特殊处理和兼容
        // ...........

        // 返回数据
        return $resultData;
    }

    // 订单提交处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
    public function orderSubmitPublicHandle($orderData = [], $usersConfig = [], $usersID = 0, $post = [], $list = [])
    {
        // 如果后台【商城中心】-【商城配置】-【订单设置】-收货后可维权时间设置为0，则表示订单不允许申请维权，反之允许申请维权
        $orderData['allow_service'] = empty($usersConfig['order_right_protect_time']) ? 1 : 0;

        // 获取消费获得积分数据
        $orderData['obtain_scores'] = getConsumObtainScores($orderData, $usersConfig, true);
        $orderData['is_obtain_scores'] = !empty($orderData['obtain_scores']) ? 0 : 1;

        // 会员信息
        $userInfo = GetUsersLatestData($usersID);

        // 如果安装了分销插件则执行
        if (is_dir('./weapp/DealerPlugin/')) {
            // 开启分销插件则执行
            $weappInfo = model('Weapp')->getWeappList('DealerPlugin');
            if (!empty($weappInfo['status']) && 1 === intval($weappInfo['status'])) {
                // 调用分销逻辑层方法
                $dealerCommonLogic = new \weapp\DealerPlugin\logic\DealerCommonLogic;
                $orderData = $dealerCommonLogic->dealerOrderHandle($orderData, $userInfo);
            }
        }

        // 返回数据
        return $orderData;
    }

    // 订单创建后续处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
    public function orderCreatePublicHandle($orderData = [], $usersConfig = [], $usersID = 0, $post = [], $list = [])
    {
        // 会员信息
        $userInfo = GetUsersLatestData($usersID);

        // 获取核销插件数据
        $weappInfo = $this->getWeappVerifyInfo();
        // 开启核销插件则执行
        if (!empty($weappInfo['status']) && 1 === intval($weappInfo['status'])) {
            // 调用核销逻辑层方法
            $verifyLogic = new \weapp\Verify\logic\VerifyLogic;
            $orderData = $verifyLogic->verifyOrderCreateHandle($orderData, $userInfo, $post, $weappInfo);
        }

        // 返回数据
        return $orderData;
    }

    // 订单支付完成处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
    public function orderPayCompletePublicHandle($post = [], $userInfo = [], $notify = false, $shopOrder = [], $resultData = [], $goodsList = [])
    {
        // 其他特殊处理和兼容
        // ...........
    }

    // 获取售后订单是否是归属商城订单的最后一个售后订单，1是，0否
    public function isLastOneServiceOrder($users_id = 0, $order_id = 0, $value = 0)
    {
        // 查询所属订单下有多少个商品
        $where = [
            'users_id' => intval($users_id),
            'order_id' => intval($order_id),
        ];
        $goodsCount = Db::name('shop_order_details')->where($where)->count();
        // 查询所属订单下有多少个已完成维权的商品
        $where = [
            'status' => ['IN', [6, 7]],
            'users_id' => intval($users_id),
            'order_id' => intval($order_id),
        ];
        $applyCount = Db::name('shop_order_service')->where($where)->count();
        // 计算维权是否为本次商品订单中的最后一个维权商品
        return intval($value) === intval(intval($goodsCount) - intval($applyCount)) ? 1 : 0;
    }

    // H5终端检测处理
    public function detectH5Terminal($terminal = 'h5')
    {
        return !empty($terminal) && 'h5' === strval($terminal) ? true : false;
    }

    // 查询收货地址和运费计算
    public function getSystemAddress($users_id = 0, $systemShopConfig = [], $param = [])
    {
        // 查询用户第一个收货地址，若有默认地址则使用默认收货地址
        $where = [
            'users_id' => intval($users_id),
        ];
        if (!empty($param['addr_id'])) $where['addr_id'] = intval($param['addr_id']);
        $address = Db::name('shop_address')->where($where)->order('is_default desc')->find();
        if (!empty($address)) {
            // 收货地址地区名称转换
            $address['region']['province'] = get_province_name($address['province']);
            $address['region']['city']     = get_city_name($address['city']);
            $address['region']['district'] = get_area_name($address['district']);
            $address['region']['detail']   = $address['address'];
            $address['address_all'] = $address['region']['province'] . $address['region']['city'] . $address['region']['district'] . $address['region']['detail'];
            // 查询运费
            if (!empty($systemShopConfig['shop_open_shipping'])) {
                $where = [
                    'province_id' => intval($address['province']),
                ];
                $template_money = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                if (0 == unifyPriceHandle($template_money)) {
                    $where = [
                        'province_id' => 100000,
                    ];
                    $template_money = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                }
                $address['template_money'] = unifyPriceHandle($template_money);
            } else {
                $address['template_money'] = 0;
            }
        }

        return $address;
    }

    // 查询微信支付是否开启
    public function getWeChatPayOpen($isApplets = false)
    {
        $result = true;
        if (empty($isApplets)) {
            $where = [
                'pay_id' => 1,
                'pay_mark' => 'wechat',
            ];
            $weChatConfig = Db::name('pay_api_config')->where($where)->getField('pay_info');
            $weChatConfig = !empty($weChatConfig) ? unserialize($weChatConfig) : [];
            if (empty($weChatConfig) || 1 === intval($weChatConfig['is_open_wechat']) || empty($weChatConfig['appid']) || empty($weChatConfig['mchid']) || empty($weChatConfig['key'])) $result = false;
        } else {
            $miniproWeixin = $this->getSpecifyAppletsConfig();
            // $miniproWeixin = json_decode(tpSetting("OpenMinicode.conf_weixin"), true);
            if (empty($miniproWeixin) || empty($miniproWeixin['appid']) || empty($miniproWeixin['mchid']) || empty($miniproWeixin['apikey'])) $result = false;
        }

        return $result;
    }

    // 查询余额支付是否开启
    public function getBalancePayOpen()
    {
        $pay_balance_open = getUsersConfigData('pay.pay_balance_open');
        return !empty($pay_balance_open) ? true : false;
    }

    // 获取支付类型的隐藏域
    public function getPayApiHidden($param = [])
    {
        $where = [
            'status' => 1
        ];
        // 手机端微信、小程序不查询支付宝配置
        if ((isMobile() && isWeixin()) || isWeixinApplets()) $where['pay_mark'] = ['NEQ', 'alipay'];
        // 查询支付配置
        $payApiList = Db::name('pay_api_config')->where($where)->select();
        // 默认选中支付方式，3:货到付款，2:余额支付，1:在线支付，0:未开启支付方式
        $usePayType = 0;
        $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="">';
        // 封装订单支付方式隐藏域
        if (empty($param['shop_open_offline']) && empty($param['PromType'])) {
            $usePayType = 3;
            $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="1"><input type="hidden" name="payment_type" id="payment_type" value="hdfk_payOnDelivery">';
        }
        // 在线支付判断
        if (!empty($payApiList)) {
            foreach ($payApiList as $key => $value) {
                $PayInfo = unserialize($value['pay_info']);
                if ('wechat' == $value['pay_mark']) {
                    // 微信判断
                    if ((isset($PayInfo['is_open_wechat']) && 0 == $PayInfo['is_open_wechat']) || false === $this->getHupijiaoPay('wechat')) {
                        $usePayType = 1;
                        $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="zxzf_wechat">';
                        break;
                    }
                } else if ('alipay' == $value['pay_mark']) {
                    // 支付宝判断
                    if ((isset($PayInfo['is_open_alipay']) && 0 == $PayInfo['is_open_alipay']) || false === $this->getHupijiaoPay('alipay') || false === $this->getPersonPay()) {
                        $usePayType = 1;
                        $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="zxzf_alipay">';
                        break;
                    }  
                } else if (0 == $value['system_built']) {
                    // 第三方支付判断
                    if (isset($PayInfo['is_open_pay']) && 0 == $PayInfo['is_open_pay']) {
                        $usePayType = 1;
                        if (!empty($PayInfo['wechat_appid']) && !empty($PayInfo['wechat_appsecret'])) {
                            $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="zxzf_wechat">';
                            break;
                        } else if (!empty($PayInfo['alipay_appid']) && !empty($PayInfo['alipay_appsecret'])) {
                            $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="zxzf_alipay">';
                            break;
                        }
                    }
                }
            }
        }

        // 余额支付判断
        if (in_array($usePayType, [0, 3]) && 1 === intval($param['pay_balance_open'])) {
            $usePayType = 2;
            $payTypeHidden = '<input type="hidden" name="payment_method" id="payment_method" value="0"><input type="hidden" name="payment_type" id="payment_type" value="yezf_balance">';
        }

        return [
            'usePayType' => $usePayType,
            'payTypeHidden' => $payTypeHidden,
        ];
    }

    //查询虎皮椒支付有没有配置相应的(微信or支付宝)支付
    public function getHupijiaoPay($type = '')
    {
        $hupijiaoInfo = Db::name('weapp')->where(['code'=>'Hupijiaopay','status'=>1])->find();
        $hupijiaoPay = Db::name('pay_api_config')->where(['pay_mark'=>'Hupijiaopay'])->find();
        if (empty($hupijiaoPay) || empty($hupijiaoInfo)) return true;
        if (empty($hupijiaoPay['pay_info'])) return true;
        $payInfo = unserialize($hupijiaoPay['pay_info']);
        if (empty($payInfo)) return true;
        if (!isset($payInfo['is_open_pay']) || $payInfo['is_open_pay'] == 1) return true;
        $type .= '_appid';
        if (!isset($payInfo[$type]) || empty($payInfo[$type])) return true;

        return false;
    }

    // 查询是否已有支付宝当面付插件(支付宝当面付功能)
    public function getPersonPay()
    {
        $personPay = Db::name('weapp')->where(['code'=>'PersonPay','status'=>1])->find();
        $payApiConfig = Db::name('pay_api_config')->where(['pay_mark'=>'PersonPay'])->find();
        if (empty($payApiConfig) || empty($personPay)) return true;
        if (empty($payApiConfig['pay_info'])) return true;
        $payInfo = unserialize($payApiConfig['pay_info']);
        if (empty($payInfo)) return true;
        if (!isset($payInfo['is_open_pay']) || 1 === intval($payInfo['is_open_pay'])) return true;

        return false;
    }

    // 获取核销插件数据
    public function getWeappVerifyInfo()
    {
        $result = [];

        // 如果安装了核销插件则执行
        if (is_dir('./weapp/Verify/')) {
            // 核销插件数据信息
            $result = model('Weapp')->getWeappList('Verify');
        }

        // 返回数据
        return $result;
    }

    // 获取积分商城插件数据
    public function getWeappPointsShop()
    {
        $result = [];

        // 如果安装了积分商城插件则执行
        if (is_dir('./weapp/PointsShop/')) {
            // 积分商城插件数据信息
            $result = model('Weapp')->getWeappList('PointsShop');
        }

        // 返回数据
        return $result;
    }

    //判断是否安装某插件 传插件标识即可用
    public function getWeappInfo($code = '')
    {
        $result = [];

        // 如果安装了某个插件则执行
        if (is_dir("./weapp/{$code}/")) {
            // 某插件数据信息
            $result = model('Weapp')->getWeappList($code);
        }

        // 返回数据
        return $result;
    }

    // 系统商品操作时，积分商品的被动处理
    public function pointsGoodsPassiveHandle($aid = [])
    {
        $weappInfo = $this->getWeappPointsShop();
        if (!empty($weappInfo)) {
            $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
            $pointsShopLogic->pointsGoodsPassiveHandle($aid);
        }
    }

    // 执行每日签到赠送积分
    public function executeUsersDailyCheckIns($users_id = 0)
    {
        // 获取系统积分规则
        $systemPointsRules = getUsersConfigData('score');
        if (empty($systemPointsRules) || !isset($systemPointsRules['score_signin_status']) || $systemPointsRules['score_signin_status'] != 1) {
            return [
                'code' => 0,
                'msg' => '签到送积分功能已关闭',
            ];
        }

        // 查询今日签到信息
        $times = time();
        $today_0 = mktime(0, 0, 0, date("m", $times), date("d", $times), date("Y", $times));
        $today_1 = mktime(23, 59, 59, date("m", $times), date("d", $times), date("Y", $times));
        $where = [
            'users_id' => intval($users_id),
            'add_time' => ['BETWEEN', [$today_0, $today_1]]
        ];
        $result = Db::name('users_signin')->where($where)->count();

        // 未签到则执行签到赠送积分
        if (empty($result)) {
            // 添加签到记录
            $insert = [
                'users_id' => intval($users_id),
                'add_time' => $times,
            ];
            $insertID = Db::name('users_signin')->insertGetId($insert);
            if (!empty($insertID)) {
                // 更新会员积分
                $where = [
                    'users_id' => intval($users_id),
                ];
                $signinScore = $systemPointsRules['score_signin_score'] ? intval($systemPointsRules['score_signin_score']) : 0;
                Db::name('users')->where($where)->setInc('scores', $signinScore);
                $usersScores = Db::name('users')->where($where)->value('scores');

                // 添加会员积分记录
                $insert = [
                    'type' => 5,
                    'users_id' => intval($users_id),
                    'score' => '+' . intval($signinScore),
                    'devote' => intval($signinScore),
                    'info' => '每日签到',
                    'add_time' => $times,
                    'update_time' => $times,
                    'current_score' => intval($usersScores),
                    'remark' => '签到赠送积分',
                ];
                Db::name('users_score')->insert($insert);

                // 返回成功提示
                return [
                    'code' => 1,
                    'msg' => '签到成功',
                    'scores' => $usersScores,
                    'checkInsPoints' => $signinScore,
                ];
            }

            // 签到异常，刷新重试
            return [
                'code' => 0,
                'msg' => '签到异常，刷新重试',
            ];
        }

        // 今日已签到过
        return [
            'code' => 0,
            'msg' => '今日已签到过',
        ];
    }

    // 获取会员积分明细数据
    public function getBaseUsersPointsDetails($users_id = 0, $param = [])
    {
        // 会员积分查询
        $where = [
            'users_id' => intval($users_id)
        ];
        if (!empty($param['navID']) && 1 === intval($param['navID'])) {
            $where[] = Db::raw('score > 0');
        } else if (!empty($param['navID']) && 2 === intval($param['navID'])) {
            $where[] = Db::raw('score < 0');
        }
        $pagesize = config('paginate.list_rows') ? config('paginate.list_rows') : 15;
        $result = Db::name('users_score')->where($where)->order('add_time desc')->paginate($pagesize, false, ['query' => request()->param()]);
        !empty($result) && $result = $result->toArray();

        // 处理积分数据
        foreach ($result['data'] as $key => $value) {
            $value['type_title'] = '';
            if (1 === intval($value['type'])) {
                $value['type_title'] = '提问';
            } else if (2 === intval($value['type'])) {
                $value['type_title'] = '回答';
            } else if (3 === intval($value['type'])) {
                $value['type_title'] = '最佳答案';
            } else if (4 === intval($value['type'])) {
                $value['type_title'] = '悬赏退回';
            } else if (5 === intval($value['type'])) {
                $value['type_title'] = '每日签到';
            } else if (6 === intval($value['type'])) {
                $value['type_title'] = '管理员编辑';
            } else if (7 === intval($value['type']) && intval($value['score']) < 0) {
                $value['type_title'] = '问题悬赏';
            } else if (7 === intval($value['type']) && intval($value['score']) > 0) {
                $value['type_title'] = '问题获得悬赏';
            } else if (8 === intval($value['type'])) {
                $value['type_title'] = '消费赠送积分';
            } else if (9 === intval($value['type']) && intval($value['score']) < 0) {
                $value['type_title'] = '积分商城';
            } else if (9 === intval($value['type']) && intval($value['score']) > 0) {
                $value['type_title'] = '积分商城取消';
            } else if (10 === intval($value['type']) && intval($value['score']) > 0) {
                $value['type_title'] = '登录赠送积分';
            } else if (11 === intval($value['type'])) {
                $value['type_title'] = '积分商城订单支付';
            }
            $value['add_time'] = date('Y/m/d H:i:s', $value['add_time']);
            $result['data'][$key] = $value;
        }

        $navList = [
            ['id' => 0, 'name' => '全部记录'],
            ['id' => 1, 'name' => '积分获得'],
            ['id' => 2, 'name' => '积分消费']
        ];
        return [
            'points' => $result,
            'navList' => $navList,
        ];
    }

    // 处理会员折扣价返回
    public function handleUsersDiscountPrice($aid = 0, $level_id = 0)
    {
        // 会员折扣价
        $usersDiscountPrice = 0;
        // 查询会员折扣价列表
        $discountList = Db::name('product_users_discount')->where('aid', $aid)->getAllWithIndex('level_id');

        // 检测是否存在会员折扣价，没有则直接返回原数据
        if (!empty($discountList[$level_id])) {
            $usersDiscountPrice = !empty($discountList[$level_id]['users_discount_price']) ? floatval($discountList[$level_id]['users_discount_price']) : 0;
        }
        // 返回数据
        return $usersDiscountPrice;
    }

    // 获取会员折扣价格模板
    public function getUsersDiscountPriceTpl($aid = 0, $usersPrice = 0)
    {
        // 查询会员级别列表
        $usersLevelList = model('UsersLevel')->getList();
        if (empty($usersLevelList)) return ['code' => 0, 'data' => '请先在[会员中心]-[会员级别]中添加会员级别！'];

        // 如果存在产品ID则查询是否已指定会员级别
        $discountList = !empty($aid) ? Db::name('product_users_discount')->where('aid', $aid)->getAllWithIndex('level_id') : [];

        // 生成模板返回
        $resultTpl = $this->createUsersDiscountPriceTpl($usersLevelList, $discountList, $usersPrice);

        return ['code' => 1, 'data' => $resultTpl];
    }

    // 获取会员折扣价格模板
    public function saveUsersDiscountPriceList($usersDiscount = [], $aid = 0)
    {
        // 处理折扣价列表
        $insertAll = [];
        $times = getTime();
        $productUsersDiscount = Db::name('product_users_discount');
        $discount_ids = !empty($usersDiscount['id']) ? $usersDiscount['id'] : [];
        $discount_prices = !empty($usersDiscount['price']) ? $usersDiscount['price'] : [];
        $discount_level_ids = !empty($usersDiscount['level_id']) ? $usersDiscount['level_id'] : [];
        if (!empty($discount_level_ids[0])) {
            foreach ($discount_level_ids as $key => $value) {
                if (!empty($value)) {
                    // 编辑
                    if (!empty($discount_ids[$key])) {
                        $update = [
                            'users_discount_id' => intval($discount_ids[$key]),
                            'aid' => intval($aid),
                            'level_id' => intval($value),
                            'users_discount_price' => !empty($discount_prices[$key]) ? floatval($discount_prices[$key]) : 0,
                            'update_time' => $times,
                        ];
                        $productUsersDiscount->update($update);
                    }
                    // 新增
                    else {
                        $insertAll[] = [
                            'aid' => intval($aid),
                            'level_id' => intval($value),
                            'users_discount_price' => !empty($discount_prices[$key]) ? floatval($discount_prices[$key]) : 0,
                            'add_time' => $times,
                            'update_time' => $times,
                        ];
                    }
                }
            }
        }
        
        // 存在新增的会员折扣价则执行添加
        !empty($insertAll) && $productUsersDiscount->insertAll($insertAll);
    }

    // 生成模板返回
    private function createUsersDiscountPriceTpl($usersLevelList = [], $discountList = [], $usersPrice = 0)
    {
        $trTpl = '';
        $usersDiscountPrice = !empty($usersPrice) ? floatval($usersPrice) : floatval(0);
        foreach ($usersLevelList as $key => $value) {
            // 会员折扣列表
            $discountFind = !empty($discountList[$value['level_id']]) ? $discountList[$value['level_id']] : [];
            $usersDiscountID = !empty($discountFind['users_discount_id']) ? intval($discountFind['users_discount_id']) : 0;
            $usersDiscountPrice = !empty($discountFind['users_discount_price']) ? floatval($discountFind['users_discount_price']) : floatval($usersPrice);
            // 模板拼装
            $trTpl .= <<<EOF
<tr>
    <input type="hidden" name="users_discount[id][]" value="{$usersDiscountID}">
    <input type="hidden" name="users_discount[level_id][]" value="{$value['level_id']}">
    <td style='padding: 10px !important; width: 200px;'>
        <b style='font-weight: normal; color: #333;'>{$value['level_name']}</b>
    </td>
    <td style='padding: 10px !important;'>
        <input type='text' class='users_discount_price' name="users_discount[price][]" value="{$usersDiscountPrice}" onpaste='this.value=this.value.replace(/[^\d.]/g, "");' onkeyup='this.value=this.value.replace(/[^\d.]/g, "");'>&nbsp;元
    </td>
</tr>
EOF;
        }
        // 模板拼装
        $resultTpl = <<<EOF
<table class='table table-bordered' border='1' cellpadding='10' cellspacing='10' style='border: 1px solid #ddd;'>
    <thead>
        <tr>
            <td style='padding: 10px !important; width: 200px;'><b style='font-weight: normal; color: #333;'>会员级别</b></td>
            <td style='padding: 10px !important; width: 200px;'><b style='font-weight: normal; color: #333;'>会员价格</b> &nbsp; <a href="javascript:void(0);" onclick="bulkSetUsersDiscountPrice(this);" >批量设置 </a></td>
        </tr>
    </thead>
    <tbody>
        {$trTpl}
    </tbody>
</table>
EOF;
        return $resultTpl;
    }

    /**
     * 订单是否改价
     * @param  integer $order_id [description]
     * @return boolean           [description]
     */
    public function is_change_price($order_id = 0)
    {
        // 订单是否改价过
        $is_change_price = 0;
        $orderLog = Db::name('shop_order_log')->field('action_desc')->where('order_id', $order_id)->select();
        if (!empty($orderLog)) {
            foreach ($orderLog as $key => $value) {
                if (!empty($value['action_desc']) && stristr($value['action_desc'], '改价')) $is_change_price = 1;
            }
        }
        return $is_change_price;
    }

    // 订单使用优惠券时的处理
    public function order_coupon_handle($where = [], $list = [], $users_discount = 100)
    {
        $coupon_table = 'shop_coupon';
        $coupon_use_table = 'shop_coupon_use';
        $weappInfo = $this->getWeappInfo("Coupons");
        if (!empty($weappInfo['status']) && 1 === intval($weappInfo['status'])) {
            $coupon_table = 'weapp_coupons';
            $coupon_use_table = 'weapp_coupons_use';
        }

        $where['a.start_time'] = ['<=',getTime()];
        $where['a.end_time'] = ['>=',getTime()];
        $coupon_info = Db::name($coupon_use_table)->alias('a')->join("{$coupon_table} b",'a.coupon_id = b.coupon_id','left')->where($where)->find();
        if (intval($users_discount) !== 1 && !empty($coupon_info['use_limit'])) return [];

        // 使用优惠券处理
        if (!empty($coupon_info) && 2 === intval($coupon_info['coupon_form'])) {
            $coupon_discount = 1 - (intval($coupon_info['coupon_discount']) / 10); //优惠的折扣
            if (1 === intval($coupon_info['coupon_type'])) {
                $coupon_discount_money = 0;
                foreach ($list as $key => $value) {
                    if (empty($coupon_info['use_limit']) || (!empty($coupon_info['use_limit']) && empty($value['use_discount_price']))) {
                        $coupon_discount_money += unifyPriceHandle($value['users_price'] * $value['product_num']);
                    }
                }
                $coupon_info['coupon_price'] = unifyPriceHandle($coupon_discount_money * $coupon_discount);
            }
            else if (2 === intval($coupon_info['coupon_type'])) {
                $coupon_product_ids = explode(',',$coupon_info['product_id']);
                $coupon_discount_money = 0;
                foreach ($list as $key => $value) {
                    if (empty($coupon_info['use_limit']) || (!empty($coupon_info['use_limit']) && empty($value['use_discount_price']))) {
                        if (in_array($value['aid'], $coupon_product_ids)) $coupon_discount_money += unifyPriceHandle($value['users_price'] * $value['product_num']);
                    }
                }
                $coupon_info['coupon_price'] = unifyPriceHandle($coupon_discount_money * $coupon_discount);
            }
            else if (3 === intval($coupon_info['coupon_type'])) {
                $coupon_arctype_ids = explode(',',$coupon_info['arctype_id']);
                $coupon_discount_money = 0;
                foreach ($list as $key => $value) {
                    if (empty($coupon_info['use_limit']) || (!empty($coupon_info['use_limit']) && empty($value['use_discount_price']))) {
                        if (in_array($value['typeid'], $coupon_arctype_ids)) $coupon_discount_money += unifyPriceHandle($value['users_price'] * $value['product_num']);
                    }
                }
                $coupon_info['coupon_price'] = unifyPriceHandle($coupon_discount_money * $coupon_discount);
            }
        }

        return $coupon_info;
    }
}