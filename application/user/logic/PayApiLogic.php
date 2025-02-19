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
 * Date: 2020-05-22
 */

namespace app\user\logic;

use think\Model;
use think\Db;
use think\Request;
use think\Config;
use app\user\logic\PayLogic;
/**
 * 支付API逻辑处理
 * @package user\Logic
 */
load_trait('controller/Jump');
class PayApiLogic extends Model
{
    use \traits\controller\Jump;

    private $home_lang = 'cn';
    private $param_users_id = 0;
    private $fromApplets = false;
    private $fromAsync = false;

    public function __construct($param_users_id = 0, $fromApplets = false, $fromAsync = false) {
        if (!empty($fromAsync)) $this->fromAsync = $fromAsync;
        if (!empty($fromApplets)) $this->fromApplets = $fromApplets;
        if (!empty($param_users_id)) $this->param_users_id = $param_users_id;
        parent::__construct();
    }

    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();
        // 时间戳
        $this->times             = getTime();
        // 多语言
        $this->home_lang         = get_home_lang();
        // 会员信息表
        $this->users_db          = Db::name('users');
        // 订单主表
        $this->shop_order_db     = Db::name('shop_order');
        // 会员金额明细表
        $this->users_money_db    = Db::name('users_money');
        // 支付API配置
        $this->pay_api_config_db = Db::name('pay_api_config');
        // 会员信息
        if (!empty($this->param_users_id)) {
            $this->users = GetUsersLatestData($this->param_users_id);
        } else {
            $this->users = GetUsersLatestData();
        }
        $this->users_id = $this->users['users_id'];
    }

    // 支付API配置信息查询
    public function GetPayApiConfig($post = [])
    {
        if (empty($post['pay_mark'])) $this->error('支付API异常，请刷新重试');

        // 如果是支付宝支付则先查询是否已有支付宝当面付插件(支付宝当面付功能)
        if ('alipay' == $post['pay_mark']) {
            $Config = $this->pay_api_config_db->where(['pay_mark'=>'PersonPay'])->find();
            $personPay = Db::name('weapp')->where(['code'=>'PersonPay','status'=>1])->find();
            $Config['pay_info'] = !empty($Config['pay_info']) ? unserialize($Config['pay_info']) : [];
            if (empty($personPay) || !isset($Config['pay_info']['is_open_pay']) || 1 === intval($Config['pay_info']['is_open_pay'])) $Config = [];
        }

        if (empty($Config)) {
            //先查虎皮椒支付有没有配置
            $hupijiao_pay_config = $this->pay_api_config_db->where(['pay_mark'=>'Hupijiaopay'])->find();
            if (!empty($hupijiao_pay_config)) {
                $hupijiao_pay_config['pay_info'] = unserialize($hupijiao_pay_config['pay_info']);
                $hupijiaoInfo = Db::name('weapp')->where(['code'=>'Hupijiaopay','status'=>1])->find();
                if (empty($hupijiaoInfo) || !isset($hupijiao_pay_config['pay_info']['is_open_pay']) || 1 == $hupijiao_pay_config['pay_info']['is_open_pay']) {
                    $Config = $this->GetOtherPayApiConfig($post);
                } else {
                    //兼容订单轮询查支付配置
                    if ($post['pay_mark'] != 'Hupijiaopay') {
                        if (empty($hupijiao_pay_config['pay_info'][$post['pay_mark'] . '_appid'])) {
                            $Config = $this->GetOtherPayApiConfig($post);
                        } else {
                            $new_pay_info = [];
                            $new_pay_info['is_open_pay'] = 0;
                            $new_pay_info['appid'] = $hupijiao_pay_config['pay_info'][$post['pay_mark'] . '_appid'];
                            $new_pay_info['appsecret'] = $hupijiao_pay_config['pay_info'][$post['pay_mark'] . '_appsecret'];
                            $new_pay_info['pay_type'] = $post['pay_mark'];
                            if (!empty($hupijiao_pay_config['pay_info']['gateway_domain'])) {
                                $new_pay_info['gateway_domain'] = $hupijiao_pay_config['pay_info']['gateway_domain'];
                            } else {
                                $new_pay_info['gateway_domain'] = 'https://api.xunhupay.com';
                            }
                            $hupijiao_pay_config['pay_info'] = $new_pay_info;
                            $Config = $hupijiao_pay_config;
                        }
                    } else {
                        $new_pay_info = [];
                        $new_pay_info['is_open_pay'] = 0;
                        $new_pay_info['appid'] = $hupijiao_pay_config['pay_info'][$post['pay_type'] . '_appid'];
                        $new_pay_info['appsecret'] = $hupijiao_pay_config['pay_info'][$post['pay_type'] . '_appsecret'];
                        $new_pay_info['pay_type'] = $post['pay_type'];
                        if (!empty($hupijiao_pay_config['pay_info']['gateway_domain'])) {
                            $new_pay_info['gateway_domain'] = $hupijiao_pay_config['pay_info']['gateway_domain'];
                        } else {
                            $new_pay_info['gateway_domain'] = 'https://api.xunhupay.com';
                        }
                        $hupijiao_pay_config['pay_info'] = $new_pay_info;
                        $Config = $hupijiao_pay_config;
                    }
                }
            } else {
                $Config = $this->GetOtherPayApiConfig($post);
            }
        }

        return $Config;
    }

    // 支付API配置信息查询 -- 先查询虎皮椒支付
    public function GetOtherPayApiConfig($post = [])
    {
        if (empty($post['pay_id']) || empty($post['pay_mark'])) $this->error('支付API异常，请刷新重试');
        $where = [
            'pay_id'   => $post['pay_id'],
            'pay_mark' => $post['pay_mark']
        ];
        $Config = $this->pay_api_config_db->where($where)->find();
        if (empty($Config) || empty($Config['pay_info'])) $this->error('请在后台【接口配置】完善【'.$Config['pay_name'].'】配置信息');
        $Config['pay_info'] = unserialize($Config['pay_info']);

        if (1 == $post['pay_id']) {
            if (!isset($Config['pay_info']['is_open_wechat']) || 1 == $Config['pay_info']['is_open_wechat']) {
                $this->error($Config['pay_name'] . '未开启');
            }
        } else if (2 == $post['pay_id']) {
            if (!isset($Config['pay_info']['is_open_alipay']) || 1 == $Config['pay_info']['is_open_alipay']) {
                $this->error($Config['pay_name'] . '未开启');
            }
        } else {
            if (!isset($Config['pay_info']['is_open_pay']) || 1 == $Config['pay_info']['is_open_pay']) {
                $this->error($Config['pay_name'] . '未开启');
            }
        }

        return $Config;
    }

    // 订单查询
    public function GetFindOrderData($post = [], $is_up_order = false, $config = [])
    {
        if (empty($this->fromAsync)) {
            $submit_order_type = isset($post['submit_order_type']) ? intval($post['submit_order_type']) : -1;
            if (empty($post['unified_id']) || empty($post['unified_number']) || empty($post['transaction_type'])) $this->error('订单异常，请刷新重试');
        }
        // 获取充值订单
        if (1 === intval($post['transaction_type'])) {
            $where = [
                'users_id'     => $this->users_id,
                'lang'         => $this->home_lang,
                'order_number' => $post['unified_number']
            ];
            if (empty($this->fromAsync) && !empty($post['unified_id'])) $where['moneyid'] = $post['unified_id'];
            $OrderData = $this->users_money_db->where($where)->find();
            // 同步处理
            if (empty($this->fromAsync)) {
                if (empty($OrderData)) $this->error('订单不存在或已变更');
                // 判断订单状态，1未付款，2已付款，3已完成，4订单取消
                $url = urldecode(url('user/Pay/pay_consumer_details'));
                if (in_array($OrderData['status'], [2, 3])) {
                    // 订单已完成
                    if ('wechat' === trim($OrderData['pay_method'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_number'], 1, '充值');
                    }
                    $this->success('订单已支付，即将跳转', $url, true);
                } else if ($OrderData['status'] == 4) {
                    $this->success('订单已取消，即将跳转', $url, true);
                }
                // 更新订单支付方式
                if (!empty($is_up_order)) {
                    $update = [
                        'pay_method' => $post['pay_mark'],
                        'wechat_pay_type' => '',
                        'update_time' => getTime()
                    ];
                    if ('wechat' == $post['pay_mark']) {
                        if (!isMobile()) {
                            // PC端
                            $wechat_pay_type = 'WeChatScanCode';
                        } else if (isMobile() && !isWeixin()) {
                            // 手机端浏览器
                            $wechat_pay_type = 'WeChatH5';
                        } else if (isMobile() && isWeixin()) {
                            // 手机端微信
                            $wechat_pay_type = 'WeChatInternal';
                        }
                        if (!empty($OrderData['wechat_pay_type'])) {
                            $ReturnData = false;//$this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                            if (!empty($ReturnData)) $this->error($ReturnData);
                        }
                        $update['wechat_pay_type'] = $wechat_pay_type;
                    }
                    $this->users_money_db->where($where)->update($update);
                }
            }
            // 异步处理
            else {
                if (empty($OrderData)) {
                    echo 'FAIL'; exit;
                }
                // 订单无需处理，直接返回结束
                else if (!empty($OrderData['status']) && 1 !== intval($OrderData['status'])) {
                    // 订单已完成
                    if (in_array($OrderData['status'], [2, 3]) && 'wechat' === trim($OrderData['pay_method'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_number'], 1, '充值', $config);
                    }
                    echo 'SUCCESS'; exit;
                }
            }
            $OrderData['unified_id'] = intval($OrderData['moneyid']);
            $OrderData['unified_amount'] = unifyPriceHandle($OrderData['money']);
            $OrderData['unified_number'] = trim($post['unified_number']);
        }
        // 获取商品订单
        else if (2 === intval($post['transaction_type'])) {
            $where = [
                'users_id'   => $this->users_id,
                'lang'       => $this->home_lang,
                'order_code' => $post['unified_number']
            ];
            if (empty($this->fromAsync) && !empty($post['unified_id'])) $where['order_id'] = $post['unified_id'];
            $OrderData = $this->shop_order_db->where($where)->find();
            // 同步处理
            if (empty($this->fromAsync)) {
                if (empty($OrderData)) $this->error('订单不存在或已变更');
                // 判断订单状态，1已付款(待发货)，2已发货(待收货)，3已完成(确认收货)，-1订单取消(已关闭)，4订单过期
                $url = urldecode(url('user/Shop/shop_order_details', ['order_id' => $OrderData['order_id']]));
                if (in_array($OrderData['order_status'], [1, 2, 3])) {
                    if ('v3' == getUsersTplVersion() && 0 <= $submit_order_type && 1 == $OrderData['order_status']) $url = urldecode(url('user/Shop/shop_centre'));
                    // 订单已完成
                    if ((0 < intval($OrderData['prom_type']) || (0 === intval($OrderData['prom_type']) && 2 === intval($OrderData['logistics_type']))) && in_array($OrderData['order_status'], [2, 3]) && 'wechat' === trim($OrderData['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_code'], 2);
                    }
                    $this->success('订单已支付，即将跳转', $url, true);
                } else if ($OrderData['order_status'] == 4) {
                    $this->success('订单已过期，即将跳转', $url, true);
                } else if ($OrderData['order_status'] == -1) {
                    $this->success('订单已关闭，即将跳转', $url, true);
                }
                // 更新订单支付方式
                if (!empty($is_up_order)) {
                    $update = [
                        'pay_name' => $post['pay_mark'],
                        'update_time' => getTime()
                    ];
                    if ('wechat' == $post['pay_mark']) {
                        if (!isMobile()) {
                            // PC端
                            $wechat_pay_type = 'WeChatScanCode';
                        } else if (isMobile() && !isWeixin()) {
                            // 手机端浏览器
                            $wechat_pay_type = 'WeChatH5';
                        } else if (isMobile() && isWeixin()) {
                            // 手机端微信
                            $wechat_pay_type = 'WeChatInternal';
                        }
                        if (!empty($OrderData['wechat_pay_type'])) {
                            $ReturnData = $this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                            if (!empty($ReturnData)) $this->error($ReturnData);
                        }
                        $update['wechat_pay_type'] = $wechat_pay_type;
                    }
                    $this->shop_order_db->where($where)->update($update);
                    $OrderData['pay_name'] = $post['pay_mark'];
                }
            }
            // 异步处理
            else {
                if (empty($OrderData)) {
                    echo 'FAIL'; exit;
                }
                // 订单无需处理，直接返回结束
                else if (isset($OrderData['order_status']) && 0 !== intval($OrderData['order_status'])) {
                    // 订单支付通知
                    $params = [
                        'users_id' => $OrderData['users_id'],
                        'result_id' => $OrderData['order_id'],
                    ];
                    eyou_send_notice(9, $params);
                    // 订单已完成
                    if ((0 < intval($OrderData['prom_type']) || (0 === intval($OrderData['prom_type']) && 2 === intval($OrderData['logistics_type']))) && in_array($OrderData['order_status'], [2, 3]) && 'wechat' === trim($OrderData['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_code'], 2, $config);
                    }
                    echo 'SUCCESS'; exit;
                }
            }

            $OrderData['unified_id'] = intval($OrderData['order_id']);
            $OrderData['unified_amount'] = unifyPriceHandle($OrderData['order_amount']);
            $OrderData['unified_number'] = trim($post['unified_number']);
        }
        // 获取会员升级订单
        else if (3 === intval($post['transaction_type'])) {
            $where = [
                'users_id'     => $this->users_id,
                'lang'         => $this->home_lang,
                'order_number' => $post['unified_number']
            ];
            if (empty($this->fromAsync) && !empty($post['unified_id'])) $where['moneyid'] = $post['unified_id'];
            $OrderData = $this->users_money_db->where($where)->find();
            // 同步处理
            if (empty($this->fromAsync)) {
                if (empty($OrderData)) $this->error('订单不存在或已变更');

                // 判断订单状态，1未付款，2已付款，3已完成，4订单取消
                $url = urldecode(url('user/Level/level_centre'));
                if (!empty($OrderData['status']) && in_array($OrderData['status'], [2, 3])) {
                    // 订单已完成
                    if ('wechat' === trim($OrderData['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_number'], 3, '会员升级');
                    }
                    $this->success('订单已支付，即将跳转', $url, true);
                } else if ($OrderData['status'] == 4) {
                    $this->success('订单已取消，即将跳转', $url, true);
                }
            }
            // 异步处理
            else {
                if (empty($OrderData)) {
                    echo 'FAIL'; exit;
                }
                // 订单无需处理，直接返回结束
                else if (!empty($OrderData['status']) && 1 !== intval($OrderData['status'])) {
                    // 订单已完成
                    if (in_array($OrderData['status'], [2, 3]) && 'wechat' === trim($OrderData['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_number'], 3, '会员升级', $config);
                    }
                    echo 'SUCCESS'; exit;
                }
            }
            $OrderData['unified_id'] = intval($OrderData['moneyid']);
            $OrderData['unified_amount'] = unifyPriceHandle($OrderData['money']);
            $OrderData['unified_number'] = trim($post['unified_number']);
        }
        // 获取视频订单
        else if (8 == $post['transaction_type']) {
            $where = [
                'users_id'   => $this->users_id,
                'lang'       => $this->home_lang,
                'order_id'   => $post['unified_id'],
                'order_code' => $post['unified_number']
            ];
            $OrderData = Db::name('media_order')->where($where)->find();
            if (empty($OrderData)) $this->error('订单不存在或已变更', url('user/Media/index'));
            
            $url = url('user/Media/index');
            if (in_array($OrderData['order_status'], [1])) $this->success('订单已支付，即将跳转！', $url, true);
            $OrderData['unified_id'] = $post['unified_id'];
            $OrderData['unified_amount'] = $OrderData['order_amount'];
            $OrderData['unified_number'] = $post['unified_number'];

            // 更新订单支付方式
            if (!empty($is_up_order)) {
                $update = [
                    'pay_name' => $post['pay_mark'],
                    'update_time' => getTime()
                ];
                if ('wechat' == $post['pay_mark']) {
                    if (!isMobile()) {
                        // PC端
                        $wechat_pay_type = 'WeChatScanCode';
                    } else if (isMobile() && !isWeixin()) {
                        // 手机端浏览器
                        $wechat_pay_type = 'WeChatH5';
                    } else if (isMobile() && isWeixin()) {
                        // 手机端微信
                        $wechat_pay_type = 'WeChatInternal';
                    }
                    if (!empty($OrderData['wechat_pay_type'])) {
                        $ReturnData = false;//$this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                        if (!empty($ReturnData)) $this->error($ReturnData);
                    }
                    $update['wechat_pay_type'] = $wechat_pay_type;
                }
                Db::name('media_order')->where($where)->update($update);
            }
        }
        // 文章购买
        else if (9 == $post['transaction_type']) {
            $where = [
                'users_id'   => $this->users_id,
                'lang'       => $this->home_lang,
                'order_id'   => $post['unified_id'],
                'order_code' => $post['unified_number']
            ];
            $OrderData = Db::name('article_order')->where($where)->find();
            if (empty($OrderData)) $this->error('订单不存在或已变更', url('user/Users/article_index'));

            $url = url('user/Users/article_index');
            if (in_array($OrderData['order_status'], [1])) $this->success('订单已支付，即将跳转！', $url, true);
            $OrderData['unified_amount'] = $OrderData['order_amount'];
            $OrderData['unified_number'] = $post['unified_number'];
            $OrderData['unified_id'] = $post['unified_id']; 

            // 更新订单支付方式
            if (!empty($is_up_order)) {
                $update = [
                    'pay_name' => $post['pay_mark'],
                    'update_time' => getTime()
                ];
                if ('wechat' == $post['pay_mark']) {
                    if (!isMobile()) {
                        // PC端
                        $wechat_pay_type = 'WeChatScanCode';
                    } else if (isMobile() && !isWeixin()) {
                        // 手机端浏览器
                        $wechat_pay_type = 'WeChatH5';
                    } else if (isMobile() && isWeixin()) {
                        // 手机端微信
                        $wechat_pay_type = 'WeChatInternal';
                    }
                    if (!empty($OrderData['wechat_pay_type'])) {
                        $ReturnData = false;//$this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                        if (!empty($ReturnData)) $this->error($ReturnData);
                    }
                    $update['wechat_pay_type'] = $wechat_pay_type;
                }
                Db::name('article_order')->where($where)->update($update);
            }
        }
        // 下载模型购买
        else if (10 == $post['transaction_type']) {
            $where = [
                'users_id'   => $this->users_id,
                'lang'       => $this->home_lang,
                'order_id'   => $post['unified_id'],
                'order_code' => $post['unified_number']
            ];
            $OrderData = Db::name('download_order')->where($where)->find();
            if (empty($OrderData)) $this->error('订单不存在或已变更', url('user/Users/download_index'));

            $url = url('user/Users/download_index');
            if (in_array($OrderData['order_status'], [1])) $this->success('订单已支付，即将跳转！', $url, true);
            $OrderData['unified_amount'] = $OrderData['order_amount'];
            $OrderData['unified_number'] = $post['unified_number'];
            $OrderData['unified_id'] = $post['unified_id'];

            // 更新订单支付方式
            if (!empty($is_up_order)) {
                $update = [
                    'pay_name' => $post['pay_mark'],
                    'update_time' => getTime()
                ];
                if ('wechat' == $post['pay_mark']) {
                    if (!isMobile()) {
                        // PC端
                        $wechat_pay_type = 'WeChatScanCode';
                    } else if (isMobile() && !isWeixin()) {
                        // 手机端浏览器
                        $wechat_pay_type = 'WeChatH5';
                    } else if (isMobile() && isWeixin()) {
                        // 手机端微信
                        $wechat_pay_type = 'WeChatInternal';
                    }
                    if (!empty($OrderData['wechat_pay_type'])) {
                        $ReturnData = false;//$this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                        if (!empty($ReturnData)) $this->error($ReturnData);
                    }
                    $update['wechat_pay_type'] = $wechat_pay_type;
                }
                Db::name('download_order')->where($where)->update($update);
            }
        }
        // 会员充值套餐订单
        else if (20 == $post['transaction_type']) {
            $where = [
                'users_id'   => $this->users_id,
                'lang'       => $this->home_lang,
                'order_pay_code' => $post['order_pay_code'],
            ];
            if (empty($this->fromAsync) && !empty($post['order_id'])) $where['order_id'] = $post['order_id'];
            if (empty($this->fromAsync) && !empty($post['unified_id'])) $where['order_id'] = $post['unified_id'];
            if (empty($this->fromAsync) && !empty($post['order_code'])) $where['order_code'] = $post['order_code'];
            $OrderData = Db::name('users_recharge_pack_order')->where($where)->find();
            // 同步处理
            if (empty($this->fromAsync)) {
                if (empty($OrderData)) {
                    $this->error('无效订单');
                } else if (!empty($OrderData['order_status']) && 1 < intval($OrderData['order_status'])) {
                    // 订单已完成
                    if (in_array($OrderData['order_status'], [2, 3]) && 'wechat' === trim($OrderData['order_pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_pay_code'], 20, '充值');
                    }
                    $this->success('支付完成');
                }
            }
            // 异步处理
            else {
                if (empty($OrderData)) {
                    echo 'FAIL'; exit;
                }
                // 订单无需处理，直接返回结束
                else if (!empty($OrderData['order_status']) && 1 < intval($OrderData['order_status'])) {
                    // 订单已完成
                    if (in_array($OrderData['order_status'], [2, 3]) && 'wechat' === trim($OrderData['order_pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $OrderData['order_pay_code'], 20, '充值', $config);
                    }
                    echo 'SUCCESS'; exit;
                }
            }
            $OrderData['unified_id'] = intval($OrderData['order_id']);
            $OrderData['unified_amount'] = unifyPriceHandle($OrderData['order_pay_prices']);
            $OrderData['unified_number'] = trim($post['order_pay_code']);
        }
        // 多商家订单
        else if (99 === intval($post['transaction_type'])) {
            // 获取商品订单
            $where = [
                'users_id'   => $this->users_id,
                'unified_id' => $post['unified_id'],
                'unified_number' => $post['unified_number']
            ];
            $OrderData = Db::name('shop_order_unified_pay')->where($where)->find();
            if (empty($OrderData)) $this->error('订单不存在或已变更..');

            // 判断订单状态，0未付款，1已付款
            $url = urldecode(url('user/Shop/shop_centre'));
            if (in_array($OrderData['pay_status'], [1])) $this->success('订单已支付，即将跳转...', $url, true);

            // 更新订单支付方式
            if (!empty($is_up_order)) {
                $update = [
                    'pay_name' => $post['pay_mark'],
                    'update_time' => getTime()
                ];
                if ('wechat' == $post['pay_mark']) {
                    if (!isMobile()) {
                        // PC端
                        $wechat_pay_type = 'WeChatScanCode';
                    } else if (isMobile() && !isWeixin()) {
                        // 手机端浏览器
                        $wechat_pay_type = 'WeChatH5';
                    } else if (isMobile() && isWeixin()) {
                        // 手机端微信
                        $wechat_pay_type = 'WeChatInternal';
                    }
                    if (!empty($OrderData['wechat_pay_type'])) {
                        $ReturnData = $this->determine_pay_type($OrderData['wechat_pay_type'], $wechat_pay_type);
                        if (!empty($ReturnData)) $this->error($ReturnData);
                    }
                    $update['wechat_pay_type'] = $wechat_pay_type;
                }
                Db::name('shop_order_unified_pay')->where($where)->update($update);
                $OrderData['pay_name'] = $post['pay_mark'];
            }
        }
        
        $OrderData['transaction_type'] = $post['transaction_type'];
        return $OrderData;
    }

    // 使用微信支付，判断终端调起支付功能
    public function UseWeChatPay($Post = [], $Order = [], $PayInfo = [])
    {
        if (isset($PayInfo['is_open_wechat']) && 0 == $PayInfo['is_open_wechat']) {
            $total_fee = $Order['unified_amount'];
            $out_trade_no = $Order['unified_number'];

            // PC端电脑微信扫码支付
            if (!isMobile()) {
                $params = [
                    'unified_number' => $Post['unified_number'],
                    'transaction_type' => $Post['transaction_type']
                ];
                $result['url_qrcode'] = url('user/PayApi/pay_wechat_png', $params);
                $this->success('订单支付中', null, $result);
            }
            // 移动端浏览器微信H5支付
            else if (isMobile() && !isWeixin()) {
                $result = model('PayApi')->getMobilePay($out_trade_no, $total_fee, $PayInfo, $Post['transaction_type']);
                if (!empty($result['return_code']) && 'FAIL' == $result['return_code']) $this->error($result['return_msg']);
                $this->success('订单支付中', $result);
            }
            // 移动端微信内支付
            else if (isMobile() && isWeixin()) {
                if (isWeixinApplets()) {
                    $params = [
                        'is_applets' => 1,
                        'unified_id' => $Order['unified_id'],
                        'unified_number' => $Order['unified_number'],
                        'transaction_type' => $Order['transaction_type']
                    ];
                    $this->success('订单支付中', null, $params);
                }

                // 小程序支付
                if (!empty($Post['openid'])) {
                    $result = model('PayApi')->getWechatPay($Post['openid'], $out_trade_no, $total_fee, $PayInfo, 1, $Post['transaction_type']);
                    if (!empty($result)) echo json_encode($result);
                }
                // 移动端微信内支付
                else if (!empty($this->users_id)) {
                    $where = [
                        'users_id' => $this->users_id,
                        'lang'     => $this->home_lang
                    ];
                    $open_id = Db::name('users')->where($where)->getField('open_id');
                    // if (empty($open_id)) $this->error('手机端微信使用本站账号登录仅可余额支付！');

                    // 手机端微信支付
                    $result = model('PayApi')->getWechatPay($open_id, $out_trade_no, $total_fee, $PayInfo, 0, $Post['transaction_type']);
                    if (!empty($result['postCode']) && 'error' === $result['postCode']) {
                        $this->error($result['return_msg']);
                    } else if (!empty($result)) {
                        $this->success('订单支付中', null, $result);
                    }
                } else {
                    $this->error('使用本站账号登录仅可余额支付！');
                }
            }
        } else {
            $this->error('微信支付已关闭');
        }
    }

    // 使用支付宝支付，读取数据调起支付功能
    public function UseAliPayPay($Post = [], $Order = [], $PayInfo = [], $isReturn = false)
    {
        $alipay_url = null;
        if (!empty($Order) && !empty($PayInfo)) {
            $Order['transaction_type'] = $Post['transaction_type'];
            if (version_compare(PHP_VERSION,'5.5.0','<')) {
                // 低于5.5版本，仅可使用旧版支付宝支付
                $PayApi_model = new \app\user\model\PayApi;
                $alipay_url = $PayApi_model->getOldAliPayPayUrl($Order, $PayInfo);
            } else {
                // 高于或等于5.5版本，可使用新版支付宝支付
                if (empty($PayInfo['version'])) {
                    // 新版
                    $AliPayResult = [
                        'unified_number'   => $Order['unified_number'],
                        'unified_amount'   => $Order['unified_amount'],
                        'transaction_type' => $Order['transaction_type']
                    ];
                    $alipay_url = url('user/Pay/newAlipayPayUrl', $AliPayResult);
                } else if ($PayInfo['version'] == 1){
                    // 旧版
                    $PayApi_model = new \app\user\model\PayApi;
                    $alipay_url = $PayApi_model->getOldAliPayPayUrl($Order, $PayInfo);
                }
            }
        }

        if (true === $isReturn) {
            return ['code'=>1, 'msg'=>'订单支付中', 'alipay_url'=>$alipay_url];
        } else {
            $this->success('订单支付中', $alipay_url);
        }
    }

    // 微信支付订单处理
    public function WeChatPayProcessing($Post = [], $Order = [], $PayInfo = [], $Config = [])
    {
        vendor('wechatpay.lib.WxPayApi');
        vendor('wechatpay.lib.WxPayConfig');
        // 实例化加载订单号
        $WxPayOrderQuery  = new \WxPayOrderQuery;
        $WxPayOrderQuery->SetOut_trade_no($Order['unified_number']);

        // 处理微信配置数据
        $ApiConfig['app_id'] = $PayInfo['appid'];
        $ApiConfig['mch_id'] = $PayInfo['mchid'];
        $ApiConfig['key']    = $PayInfo['key'];

        // 实例化微信配置
        $WxPayConfig = new \WxPayConfig($ApiConfig);
        $WxPayApi = new \WxPayApi;

        if (empty($WxPayConfig->app_id)) $this->error('微信支付配置信息不全');

        // 返回结果
        $WeChatOrder = $WxPayApi->orderQuery($WxPayConfig, $WxPayOrderQuery);
        if (isset($WeChatOrder['return_code']) && $WeChatOrder['return_code'] == 'SUCCESS' && $WeChatOrder['result_code'] == 'SUCCESS') {
            if ($WeChatOrder['trade_state'] == 'SUCCESS' && !empty($WeChatOrder['transaction_id'])) {
                $this->OrderProcessing($Post, $Order, $WeChatOrder, $Config);
            } else if ($WeChatOrder['trade_state'] == 'NOTPAY') {
                $this->success('正在支付中...');
            }
        }
    }

    // 支付宝支付订单处理
    public function AliPayPayProcessing($Post = [], $Order = [], $PayInfo = [], $Config = [])
    {
        if (!empty($PayInfo) && 0 == $PayInfo['version']) {
            vendor('alipay.pagepay.service.AlipayTradeService');
            vendor('alipay.pagepay.buildermodel.AlipayTradeQueryContentBuilder');

            // 实例化加载订单号
            $RequestBuilder = new \AlipayTradeQueryContentBuilder;
            $OutTradeNo     = trim($Order['unified_number']);
            $RequestBuilder->setOutTradeNo($OutTradeNo);

            // 拼装配置
            $ApiConfig['app_id']     = $PayInfo['app_id'];
            $ApiConfig['merchant_private_key'] = $PayInfo['merchant_private_key'];
            $ApiConfig['charset']    = 'UTF-8';
            $ApiConfig['sign_type']  = 'RSA2';
            $ApiConfig['gatewayUrl'] = 'https://openapi.alipay.com/gateway.do';
            $ApiConfig['alipay_public_key'] = $PayInfo['alipay_public_key'];

            // 实例化支付宝配置
            $AlipayTradeService = new \AlipayTradeService($ApiConfig);
            $AliPayOrder = $AlipayTradeService->Query($RequestBuilder);
            
            // 解析数据
            $AliPayOrder = json_decode(json_encode($AliPayOrder), true);

            if ('40004' == $AliPayOrder['code'] && 'Business Failed' === $AliPayOrder['msg']) {
                $this->success('订单已提交，尚未支付');
            } else if ('10000' == $AliPayOrder['code'] && 'WAIT_BUYER_PAY' === $AliPayOrder['trade_status']) {
                $this->success('订单已建立，尚未支付');
            } else if ('10000' == $AliPayOrder['code'] && 'TRADE_SUCCESS' === $AliPayOrder['trade_status']) {
                // 已经支付，处理订单
                $this->OrderProcessing($Post, $Order, $AliPayOrder, $Config);
            }
        } else {
            $this->success('订单支付中');
        }
    }

    // 订单统一处理
    public function OrderProcessing($Post = [], $Order = [], $PayDetails = [], $config = [], $queryOrder = true)
    {
        if (!empty($PayDetails) && !empty($queryOrder)) {
            $total_amount = 0;
            $out_trade_no = '';
            if (!empty($PayDetails['total_fee'])) {
                $total_amount = $PayDetails['total_fee'] / 100;
                $out_trade_no = $PayDetails['out_trade_no'];
            } else if (!empty($PayDetails['total_amount'])) {
                $total_amount = $PayDetails['total_amount'];
                $out_trade_no = $PayDetails['out_trade_no'];
            } else if (!empty($PayDetails['txnAmt'])) {
                $total_amount = $PayDetails['txnAmt'] / 100;
                $out_trade_no = $PayDetails['out_trade_no'];
            } else if (!empty($PayDetails['payment_info']['total_fee'])) {
                $total_amount = $PayDetails['payment_info']['total_fee'] / 100;
                $out_trade_no = $PayDetails['out_order_no'];
            }
            if (!empty($out_trade_no) && !empty($total_amount)) {
                $payLogicObj = new PayLogic();
                $OrderData = $payLogicObj->checkAmount($out_trade_no, $total_amount, $Post['transaction_type']);
                if (empty($OrderData)) $this->error("支付失败，支付金额与订单金额不相符");
            }
        }

        // 查询实时订单信息 
        $Order = $this->GetFindOrderData($Post, false, $config);

        // 充值订单处理
        if (1 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (1 === intval($Order['status'])) {
                // 返回参数
                $result_0['email'] = false;
                $result_0['mobile'] = false;
                $url = url('user/Pay/pay_consumer_details');
                // 订单更新条件
                $where = [
                    'moneyid' => $Order['moneyid'],
                    'users_id' => $this->users_id,
                    'order_number' => strval($Order['order_number']),
                ];
                // 订单更新数据，更新为已付款
                $update = [
                    'status' => 2,
                    'pay_details' => serialize($PayDetails),
                    'update_time' => $this->times
                ];
                // 订单更新
                $result_1 = $this->users_money_db->where($where)->update($update);
                // 订单更新后续操作
                if (!empty($result_1)) {
                    // 更新增加会员余额
                    $result_2 = Db::name('users')->where(['users_id' => intval($this->users_id)])->setInc('users_money', $Order['money']);
                    // 用户充值金额后续操作
                    if (!empty($result_2)) {
                        // 业务处理完成，订单已完成
                        $update = [
                            'status'      => 3,
                            'update_time' => getTime()
                        ];
                        $this->users_money_db->where($where)->update($update);

                        // 推送微信发货推送表记录
                        if ('wechat' === trim($Order['pay_method'])) {
                            model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_number'], 1, '充值', $config);
                        }

                        // 同步处理
                        if (empty($this->fromAsync)) {
                            $this->success('充值成功', $url, $result_0);
                        }
                        // 异步处理
                        else {
                            echo 'SUCCESS'; exit;
                        }
                    } else {
                        // 同步处理
                        if (empty($this->fromAsync)) {
                            $this->success('支付成功，余额充值失败，请联系客服', $url, $result_0);
                        }
                        // 异步处理
                        else {
                            echo 'FAIL'; exit;
                        }
                    }
                } else {
                    // 同步处理
                    if (empty($this->fromAsync)) {
                        $this->success('支付成功，订单更新失败，请联系客服', $url, $result_0);
                    }
                    // 异步处理
                    else {
                        echo 'FAIL'; exit;
                    }
                }
            }
        }
        // 商品订单处理
        else if (2 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (empty($Order['order_status'])) {
                // async为true时表示同步，否则异步
                $async = $this->fromAsync ? false : true;
                $returnData = pay_success_logic($this->users_id, $Order['order_code'], $PayDetails, $Order['pay_name'], $async, [], $config);
                if (is_array($returnData)) {
                    // 同步处理
                    if (empty($this->fromAsync)) {
                        if (1 === intval($returnData['code'])) {
                            if (!empty($this->fromApplets)) {
                                $returnData['data']['url'] = 1 == input('param.fenbao/d') ? '' : '/pages/order/index';
                                $this->success('支付完成', $returnData['data']['url'], $returnData['data']);
                            } else {
                                $this->success($returnData['msg'], $returnData['url'], $returnData['data']);
                            }
                        } else {
                            $this->error($returnData['msg']);
                        }
                    }
                    // 异步处理
                    else {
                        if (1 === intval($returnData['code'])) {
                            echo 'SUCCESS'; exit;
                        } else {
                            echo 'FAIL'; exit;
                        }
                    }
                }
            } else {
                // 同步处理
                if (empty($this->fromAsync)) {
                    $returnData = [];
                    $users = \think\Db::name('users')->field('*')->find($Order['users_id']);
                    // 邮箱发送
                    $returnData['email'] = GetEamilSendData(tpCache('smtp'), $users, $Order, 1, $Order['pay_name']);
                    // 手机发送
                    $returnData['mobile'] = GetMobileSendData(tpCache('sms'), $users, $Order, 1, $Order['pay_name']);
                    $this->success('已支付', url('user/Shop/shop_centre'), $returnData);
                }
            }
        }
        // 会员升级处理
        else if (3 === intval($Post['transaction_type'])) {
            $result_0['email']  = false;
            $result_0['mobile'] = false;
            $url = url('user/Level/level_centre');
            // 更新升级订单
            if (1 === intval($Order['status'])) {
                // 更新条件
                $where = [
                    'moneyid' => $Order['moneyid'],
                    'users_id' => $this->users_id,
                    'order_number' => strval($Order['order_number']),
                ];
                // 更新数据
                $usersTypeData = !empty($Order['cause']) ? unserialize($Order['cause']) : session('UsersTypeData');
                $update = $this->GetUpMoneyData($usersTypeData, $Order['pay_method']);
                // 执行更新
                $resultID = $this->users_money_db->where($where)->update($update);
                // 订单更新后续操作
                if (!empty($resultID)) {
                    $where = [
                        'users_id' => $Order['users_id'],
                    ];
                    // 获取更新会员数据数组
                    $update = $this->GetUpUsersData($usersTypeData);
                    $resultID = $this->users_db->where($where)->update($update);
                    if (!empty($resultID)) {
                        // 推送微信发货推送表记录
                        if ('wechat' === trim($Order['pay_method'])) {
                            model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_number'], 3, '会员升级', $config);
                        }
                        if (!empty($this->fromApplets)) $url = 1 == input('param.fenbao/d') ? '' : '/pages/user/index';
                        $result_0['url'] = $url;
                        // 同步处理
                        if (empty($this->fromAsync)) {
                            $this->success('升级成功', $url, $result_0);
                        }
                        // 异步处理
                        else {
                            echo 'SUCCESS'; exit;
                        }
                    }
                }
                // 同步处理
                if (empty($this->fromAsync)) {
                    $this->success('支付成功，升级失败，请联系客服', $url, $result_0);
                }
                // 异步处理
                else {
                    echo 'FAIL'; exit;
                }
            }
        }
        // 视频订单处理
        else if (8 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (empty($Order['order_status'])) {
                // 订单更新条件
                $where = [
                    'order_id'  => $Order['order_id'],
                    'users_id'  => $this->users_id,
                    'lang'      => $this->home_lang
                ];
                // 订单更新数据，更新为已付款
                $update = [
                    'order_status' => 1,
                    'pay_details'  => serialize($PayDetails),
                    'pay_time'     => getTime(),
                    'update_time'  => getTime()
                ];
                // 订单更新
                $resultID = Db::name('media_order')->where($where)->update($update);
                // 订单更新后续操作
                if (!empty($resultID)) {
                    // 推送微信发货推送表记录
                    if ('wechat' === trim($Order['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_code'], 8, $Order['product_name'], $config);
                    }
                    // 处理会员订单累计总额，用于会员自动升级
                    model('UsersLevel')->handleUsersOrderTotalAmount($this->users, $Order);
                    // 订单操作完成，返回跳转
                    $ViewUrl = cookie($this->users_id . '_' . $Order['product_id'] . '_EyouMediaViewUrl');
                    $this->success('支付成功，处理订单完成', $ViewUrl, true);
                }
            }
        }
        // 文章订单处理
        else if (9 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (empty($Order['order_status'])) {
                // 订单更新条件
                $where = [
                    'order_id'  => $Order['order_id'],
                    'users_id'  => $this->users_id,
                    'lang'      => $this->home_lang
                ];
                // 订单更新数据，更新为已付款
                $update = [
                    'order_status' => 1,
                    'pay_details'  => serialize($PayDetails),
                    'pay_time'     => getTime(),
                    'update_time'  => getTime()
                ];
                // 订单更新
                $resultID = Db::name('article_order')->where($where)->update($update);
                // 订单更新后续操作
                if (!empty($resultID)) {
                    // 推送微信发货推送表记录
                    if ('wechat' === trim($Order['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_code'], 9, $Order['product_name'], $config);
                    }
                    // 处理会员订单累计总额，用于会员自动升级
                    model('UsersLevel')->handleUsersOrderTotalAmount($this->users, $Order);
                    // 订单操作完成，返回跳转
                    $ViewUrl = cookie($this->users_id . '_' . $Order['product_id'] . '_EyouArticleViewUrl');
                    $this->success('支付成功，处理订单完成', $ViewUrl, true);
                }
            }
        }
        // 下载订单处理
        else if (10 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (empty($Order['order_status'])) {
                // 订单更新条件
                $where = [
                    'order_id'  => $Order['order_id'],
                    'users_id'  => $this->users_id,
                    'lang'      => $this->home_lang
                ];
                // 订单更新数据，更新为已付款
                $update = [
                    'order_status' => 1,
                    'pay_details'  => serialize($PayDetails),
                    'pay_time'     => getTime(),
                    'update_time'  => getTime()
                ];
                // 订单更新
                $resultID = Db::name('download_order')->where($where)->update($update);
                // 订单更新后续操作
                if (!empty($resultID)) {
                    // 推送微信发货推送表记录
                    if ('wechat' === trim($Order['pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_code'], 10, $Order['product_name'], $config);
                    }
                    // 处理会员订单累计总额，用于会员自动升级
                    model('UsersLevel')->handleUsersOrderTotalAmount($this->users, $Order);
                    // 订单操作完成，返回跳转
                    $ViewUrl = cookie($this->users_id . '_' . $Order['product_id'] . '_EyouDownloadViewUrl');
                    $this->success('支付成功，处理订单完成', $ViewUrl, true);
                }
            }
        }
        // 会员充值套餐订单处理
        else if (20 === intval($Post['transaction_type'])) {
            // 更新订单为已支付
            $where = [
                'users_id' => $this->users_id,
                'order_id' => $Order['order_id'],
                'order_code' => $Order['order_code'],
                'order_pay_code' => $Order['order_pay_code'],
            ];
            $update = [
                'order_status' => 2,
                'order_pay_time' => $this->times,
                'order_pay_details' => serialize($PayDetails),
                'update_time' => $this->times,
            ];
            $result_1 = Db::name('users_recharge_pack_order')->where($where)->update($update);
            if (!empty($result_1)) {
                // 根据会员充值套餐给会员充储值余额
                $result_2 = Db::name('users')->where(['users_id' => $this->users_id])->setInc('users_money', $Order['order_face_value']);
                if (!empty($result_2)) {
                    // 更新订单为已充值
                    $update = [
                        'order_status' => 3,
                        'update_time' => $this->times,
                    ];
                    Db::name('users_recharge_pack_order')->where($where)->update($update);
                    // 增加充值套餐的销售量
                    $where = [
                        'pack_id' => $Order['pack_id'],
                    ];
                    Db::name('users_recharge_pack')->where($where)->setInc('pack_sales_num');
                    // 会员当前余额
                    $usersMoney = Db::name('users')->where('users_id', $this->users_id)->getField('users_money');
                    $insert = [
                        'users_id' => $this->users_id,
                        'money' => unifyPriceHandle($Order['order_face_value']),
                        'users_money' => unifyPriceHandle($usersMoney),
                        'cause' => $Order['order_pack_names'] . '(充值套餐)',
                        'cause_type' => 1,
                        'status' => 3,
                        'pay_method' => !empty($Order['order_pay_name']) ? trim($Order['order_pay_name']) : '',
                        'pay_details' => serialize($PayDetails),
                        'order_number' => $Order['order_pay_code'],
                        'lang' => $this->home_lang,
                        'add_time' => $this->times,
                        'update_time' => $this->times,
                    ];
                    Db::name('users_money')->insertGetId($insert);

                    // 推送微信发货推送表记录
                    if ('wechat' === trim($Order['order_pay_name'])) {
                        model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $Order['order_pay_code'], 20, '充值', $config);
                    }

                    // 处理会员订单累计总额，用于会员自动升级
                    $Order['unified_amount'] = !empty($Order['unified_amount']) ? $Order['unified_amount'] : $Order['order_pay_prices'];
                    model('UsersLevel')->handleUsersOrderTotalAmount($this->users, $Order);

                    // 同步处理
                    if (empty($this->fromAsync)) {
                        // 充值成功
                        $this->success('充值成功');
                    }
                    // 异步处理
                    else {
                        echo 'SUCCESS'; exit;
                    }
                }
            }
        }
        // 多商家订单处理
        else if (99 === intval($Post['transaction_type'])) {
            // 付款成功后，订单并未修改状态时，修改订单状态并返回
            if (!empty($Order) && empty($Order['pay_status'])) {
                $multiMerchantLogic = new \app\user\logic\MultiMerchantLogic;
                $multiMerchantLogic->unifiedPaySuccessHandle($Order, $Post['pay_mark'], $PayDetails);
            }
        }
    }

    // 确定支付类型
    // $OrderPayMethodType 数据中的数据
    // $PayMethodType 当前提交的类型，用于判断
    private function determine_pay_type($OrderPayMethodType = null, $PayMethodType = null)
    {
        // 若为空，则表现未标记过支付类型
        if (empty($OrderPayMethodType)) return false;

        // 是否数据库中的支付类型和传入的一致
        if ($OrderPayMethodType != $PayMethodType) {
            // 判断返回提示信息
            switch ($OrderPayMethodType) {
                case 'WeChatScanCode':
                    // PC端微信扫码支付
                    return '该订单已使用微信扫码创建订单，根据微信支付规则，请在PC端浏览器扫码支付';
                    break;
                case 'WeChatInternal':
                    // 手机微信端H5支付
                    return '该订单已使用微信JSAPI创建订单，根据微信支付规则，请使用手机微信进行支付';
                    break;
                case 'WeChatH5':
                    // 手机端浏览器H5支付
                    return '该订单已使用手机浏览器创建订单，根据微信支付规则，请使用手机浏览器进行支付';
                    break;
                default:
                    return '微信支付方法选择错误，请刷新后重试~~';
                    break;
            }
        } else {
            return false;
        }
    }

    /*--------------------以下为会员升级代码---------------------*/

    // 判断是否可以升级
    public function IsAllowUpgrade($post = [])
    {
        // 查询会员升级选择的数据
        $UsersTypeData = Db::name('users_type_manage')->where('type_id', $post['type_id'])->find();

        // 查询提交过来级别等级值
        $LevelValue = Db::name('users_level')->where('level_id', $UsersTypeData['level_id'])->getField('level_value');

        // 查询当前会员等级值
        $UsersValue = Db::name('users_level')->where('level_id', $this->users['level'])->getField('level_value');

        // 提交的等级是否比现有等级高
        if ($UsersValue > $LevelValue) $this->error('选择升级的等级不可以比目前持有的等级低');

        // 将查询数据存入 session，微信和支付宝回调时需要查询数据
        if (!empty($UsersTypeData)) session('UsersTypeData', $UsersTypeData);
    }

    // 余额支付
    public function BalancePayment($order_number = null, $UsersTypeData = [])
    {
        // 没有传入则从 session 中读取
        $UsersTypeData = !empty($UsersTypeData) ? $UsersTypeData : session('UsersTypeData');

        if (!empty($UsersTypeData)) {
            $UsersMoney = $this->users_db->where('users_id', $this->users_id)->getField('users_money');
            if ($UsersMoney < $UsersTypeData['price']) {
                // 若会员余额不足支付则返回
                $ReturnData = $this->GetReturnData();
                $this->success($ReturnData);
            } else {
                if (!empty($order_number)) {
                    // 获取更新金额明细表数据数组
                    $UpMoneyData = $this->GetUpMoneyData($UsersTypeData);
                    // 更新数据
                    $ReturnID = $this->users_money_db->where('order_number', $order_number)->update($UpMoneyData);
                } else {
                    // 获取生成的订单信息
                    $AddMoneyData = $this->GetAddMoneyData($UsersTypeData);
                    // 存入会员金额明细表
                    $ReturnID = $this->users_money_db->add($AddMoneyData);
                }

                if (!empty($ReturnID)) {
                    $Where = [
                        'users_id' => $this->users_id,
                        'lang'     => $this->home_lang
                    ];
                    // 获取更新会员数据数组
                    $UpUsersData = $this->GetUpUsersData($UsersTypeData, true);
                    $ReturnID = $this->users_db->where($Where)->update($UpUsersData);
                    if (!empty($ReturnID)) {
                        // 跳转链接
                        $referurl = input('param.referurl/s', null, 'htmlspecialchars_decode,urldecode');
                        if (empty($referurl)) {
                            $referurl = cookie('referurl');
                            if (empty($referurl)) {
                                $referurl = url('user/Level/level_centre');
                            }
                        }
                        cookie('referurl', null);
                        // 支付完成返回
                        $ReturnData = $this->GetReturnData(0, 1, '余额支付完成！', $referurl);
                        $this->success($ReturnData);
                    }
                }
            }
        } else {
            $this->error('升级失败，刷新重试');
        }
    }

    // 微信支付
    public function WeChatPayment($Post = [], $PayInfo = [])
    {
        $MoneyData = $this->GetMoneyData('*', $Post['order_number']);
        $UsersTypeData = session('UsersTypeData');

        if (empty($MoneyData)) {
            // 获取生成的订单信息
            $AddMoneyData = $this->GetAddMoneyData($UsersTypeData, 'wechat', 1);

            // 存入会员金额明细表
            $ReturnID = $this->users_money_db->add($AddMoneyData);
            if (!empty($ReturnID)) {
                // 返回订单数据
                $AddMoneyData['moneyid'] = $ReturnID;
                $this->ReturnMoneyPayData($Post, $AddMoneyData, $PayInfo);
            }
        } else {
            $MoneyDataCause = unserialize($MoneyData['cause']);
            if (isMobile() && !isWeixin()) {
                // 手机浏览器端支付
                $PayType = 'WeChatH5';
            } else if (isMobile() && isWeixin()) {
                // 手机微信端支付
                $PayType = 'WeChatInternal';
            } else {
                // PC端扫码支付
                $PayType = 'WeChatScanCode';
            }

            if ($MoneyDataCause['level_id'] == $UsersTypeData['level_id'] && $MoneyData['money'] == $UsersTypeData['price'] && $MoneyData['wechat_pay_type'] == $PayType) {
                // 提交的订单与上一次是同一类型产品，直接返回数据
                $this->ReturnMoneyPayData($Post, $MoneyData, $PayInfo);
            } else {
                // 生成新订单覆盖原来的订单返回
                $UpMoneyData = $this->GetUpMoneyData($UsersTypeData, 'wechat');
                $UpMoneyData['status'] = 1;
                $UpMoneyData['order_number'] = date('Ymd').getTime().rand(10,100);
                $this->users_money_db->where('moneyid', $MoneyData['moneyid'])->update($UpMoneyData);

                // 返回订单数据
                $UpMoneyData['moneyid'] = $MoneyData['moneyid'];
                $this->ReturnMoneyPayData($Post, $UpMoneyData, $PayInfo);
            }
        }
    }

    // 支付宝支付
    public function AliPayPayment($Post = [], $PayInfo = [])
    {
        $UsersTypeData = session('UsersTypeData');
        if (!empty($UsersTypeData)) {
            $MoneyData = $this->GetMoneyData('*', $Post['order_number']);
            if (empty($MoneyData)) {
                // 获取生成的订单信息
                $AddMoneyData = $this->GetAddMoneyData($UsersTypeData, 'alipay', 1);
                // 存入会员金额明细表
                $ReturnID = $this->users_money_db->add($AddMoneyData);
                // 支付宝处理返回信息
                $AddMoneyData['moneyid'] = $ReturnID;
                if (!empty($ReturnID)) $this->AliPayProcessing($AddMoneyData, $PayInfo, $Post);
            } else {
                // 获取生成的订单信息
                $UpMoneyData = $this->GetUpMoneyData($UsersTypeData, 'alipay');
                $UpMoneyData['status'] = 1;
                // 更新会员金额明细表
                $ReturnID = $this->users_money_db->where('moneyid', $MoneyData['moneyid'])->update($UpMoneyData);
                if (!empty($ReturnID)) {
                    // 支付宝处理返回信息
                    $MoneyData = $this->GetMoneyData('*', $Post['order_number']);
                    $this->AliPayProcessing($MoneyData, $PayInfo, $Post);
                }
            }
        } else {
            $this->error('升级失败，刷新重试');
        }
    }

    // 获取第三方订单
    public function GetPayOrderData($Post = [], $PayInfo = [], $pay_mark = null)
    {
        $UsersTypeData = session('UsersTypeData');
        $MoneyData = $this->GetMoneyData('*', $Post['order_number']);
        if (empty($MoneyData)) {
            // 获取生成的订单信息
            $AddMoneyData = $this->GetAddMoneyData($UsersTypeData, $pay_mark, 1);
            // 存入会员金额明细表
            $ReturnID = $this->users_money_db->add($AddMoneyData);
            // 支付宝处理返回信息
            $AddMoneyData['moneyid'] = $ReturnID;
            return $AddMoneyData;
        } else {
            // 获取生成的订单信息
            $UpMoneyData = $this->GetUpMoneyData($UsersTypeData, $pay_mark);
            $UpMoneyData['status'] = 1;
            // 更新会员金额明细表
            $ReturnID = $this->users_money_db->where('moneyid', $MoneyData['moneyid'])->update($UpMoneyData);
            if (!empty($ReturnID)) {
                // 支付宝处理返回信息
                $MoneyData = $this->GetMoneyData('*', $Post['order_number']);
                return $MoneyData;
            }
        }
    }

    // 支付宝订单处理逻辑
    private function AliPayProcessing($MoneyData = [], $PayInfo = [], $Post = [])
    {
        // 返回订单数据
        $AliPayUrl = '';
        // 支付宝支付所需参数信息拼装
        $Data = [
            'unified_number' => $MoneyData['order_number'],
            'unified_amount' => $MoneyData['money'],
            'transaction_type' => 3,
        ];
        if (version_compare(PHP_VERSION,'5.5.0','<')) {
            // 低于5.5版本，仅可使用旧版支付宝支付
            $AliPayUrl = model('PayApi')->getOldAliPayPayUrl($Data, $PayInfo);
        } else {
            // 高于或等于5.5版本，可使用新版支付宝支付
            if (empty($PayInfo['version'])) {
                // 新版
                $AliPayUrl = url('user/Pay/newAlipayPayUrl', $Data);
            } else if ($PayInfo['version'] == 1) {
                // 旧版
                $AliPayUrl = model('PayApi')->getOldAliPayPayUrl($Data, $PayInfo);
            }
        }
        if (!empty($AliPayUrl)) {
            $ReturnData = $this->GetReturnData(2, 0, '订单生成！', $AliPayUrl, $Data['unified_number']);
            $ReturnData['ReturnOrderID'] = $MoneyData['moneyid'];
            $ReturnData['pay_id']   = $Post['pay_id'];
            $ReturnData['pay_mark'] = $Post['pay_mark'];
        } else {
            $this->error('升级失败，刷新重试');
        }
        $this->success($ReturnData);
    }

    // 处理微信订单支付信息并加载回页面
    private function ReturnMoneyPayData($Post = [], $MoneyData = [], $PayInfo = [])
    {
        if (empty($MoneyData)) $this->error('订单生成错误，请刷新后重试');
        // 订单信息
        $ReturnOrderData = [
            'pay_id'             => $Post['pay_id'],
            'pay_mark'           => $Post['pay_mark'],
            'unified_id'         => $MoneyData['moneyid'],
            'unified_number'     => $MoneyData['order_number'],
            'transaction_type'   => 3, // 订单支付购买
            'order_total_amount' => $MoneyData['money'],
            'PayData'            => [
                'appId' => ''
            ]
        ];
        if (isMobile() && !isWeixin()) {
            // 手机浏览器端支付
            $out_trade_no = $MoneyData['order_number'];
            if (empty($out_trade_no)) $this->error('支付异常，请刷新后重试~');
            
            $total_fee = $MoneyData['money'];
            if (empty($total_fee)) $this->error('支付异常，请刷新后重试~');
            
            $url = model('PayApi')->getMobilePay($out_trade_no, $total_fee, $PayInfo, 3);
            if (isset($url['return_code']) && 'FAIL' == $url['return_code']) {
                $this->error('商户公众号尚未成功开通H5支付，请开通成功后重试~');
            }
            $ReturnDataNew['url_qrcode'] = null;
        } else if (isMobile() && isWeixin()) {
            // 手机微信端支付
            // if (empty($this->users['open_id'])) {
            //     // 如果会员没有openid则使用扫码支付方式
            //     $Param = [
            //         'unified_number' => $MoneyData['order_number'],
            //         'transaction_type' => 3
            //     ];
            //     $url = url('user/PayApi/pay_wechat_png', $Param);
            // } else {
            //     $url = null;
            //     if (isWeixinApplets()) { 
            //         $ReturnOrderData['is_applets'] = 1;
            //     } else {
            //         $Paydata = model('PayApi')->getWechatPay($this->users['open_id'], $MoneyData['order_number'], $MoneyData['money'], $PayInfo, 0, 3);
            //         $ReturnOrderData['PayData'] = $Paydata;
            //     }
            // }
            $url = null;
            if (isWeixinApplets()) { 
                $ReturnOrderData['is_applets'] = 1;
            } else {
                $Paydata = model('PayApi')->getWechatPay($this->users['open_id'], $MoneyData['order_number'], $MoneyData['money'], $PayInfo, 0, 3);
                $ReturnOrderData['PayData'] = $Paydata;
            }
            $ReturnDataNew['url_qrcode'] = $url;
        } else {
            $Param = [
                'unified_number' => $MoneyData['order_number'],
                'transaction_type' => 3
            ];
            $url = url('user/PayApi/pay_wechat_png', $Param);
            $ReturnDataNew['url_qrcode'] = $url;
        }

        $ReturnData = $this->GetReturnData(1, 0, '订单生成！', $url, $MoneyData['order_number']);
        $ReturnData = array_merge($ReturnData, $ReturnDataNew);
        $this->success($ReturnData, $url, $ReturnOrderData);
    }

    // 查询
    // field  字段信息，若不传入则默认值为*
    // 值为*：find方式查询，查询所有字段，返回一维数组
    // 值为多个：find方式查询，查询指定字段，返回一维数组
    // 值为单个：getField方式查询，返回单个字段值
    // return 返回查询结果
    public function GetMoneyData($field = '*', $order_number = null)
    {
        $data = [];
        // 查询条件
        $where = [
            'users_id'   => $this->users_id,
            'cause_type' => 0, // 消费类型
            'status'     => 1, // 未付款状态
            'lang'       => $this->home_lang,
        ];

        if (!empty($order_number)) $where['order_number'] = $order_number;

        if ('*' == $field) {
            // 查询所有字段
            $data = $this->users_money_db->where($where)->find();
        } else {
            $info = explode(',', $field);
            if (1 < count($info)) {
                // 查询指定的多个字段
                $data = $this->users_money_db->where($where)->field($field)->find();
            } else {
                // 查询指定的单个字段
                $data = $this->users_money_db->where($where)->getField($field);
            }
        }
        return $data;
    }

    // 拼装更新会员数据数组
    private function GetUpUsersData($data = array(), $balance = false)
    {
        // 会员期限定义数组
        $limit_arr = Config::get('global.admin_member_limit_arr');
        // 到期天数
        $maturity_days = $limit_arr[$data['limit_id']]['maturity_days'];
        // 更新会员属性表的数组
        $result = [
            'level' => $data['level_id'],
            'update_time' => $this->times,
            'level_maturity_days' => Db::raw('level_maturity_days+'.($maturity_days)),
        ];

        // 如果是余额支付则追加数组
        if (!empty($balance)) $result['users_money'] = Db::raw('users_money-'.($data['price']));

        // 判断是否需要追加天数，maturity_code在Base层已计算，1表示终身会员天数
        if (1 != $this->users['maturity_code']) {
            // 判断是否到期，到期则执行，3表示会员在期限内，不需要进行下一步操作
            if (3 != $this->users['maturity_code']) {
                // 追加天数数组
                $result['open_level_time']     = $this->times;
                $result['level_maturity_days'] = $maturity_days;
            }
        }

        return $result;
    }

    // 拼装返回数组
    private function GetReturnData($ReturnCode = 0, $ReturnPay = 0, $ReturnMsg = null, $ReturnUrl = null, $ReturnOrder = null)
    {
        // 返回跳转的链接
        $ReturnUrl = !empty($ReturnUrl) ? $ReturnUrl : url('user/Pay/pay_account_recharge');

        // 返回提示的信息
        $ReturnMsg = 0 == $ReturnCode && empty($ReturnMsg) ? '余额不足，若要使用余额支付，请先充值！' : $ReturnMsg;

        // 拼装数据
        $ReturnData = [
            // 返回判断支付类型，0为余额支付，1为微信，2为支付宝，2以上为第三方支付
            'ReturnCode' => $ReturnCode,
            // 返回判断是否已支付，0为未支付，1为完成支付
            'ReturnPay'  => $ReturnPay,
            // 返回提示的信息
            'ReturnMsg'  => $ReturnMsg,
            // 返回跳转的链接
            'ReturnUrl'  => $ReturnUrl,
            // 支付订单号
            'ReturnOrder'=> $ReturnOrder,
        ];

        // 微信支付才需要的返回字段
        if (2 == $ReturnCode) {
            if (isMobile() && !isWeixin()) {
                // 手机浏览器端支付
                $ReturnData['WeChatType'] = 'WeChatH5';
            } else if (isMobile() && isWeixin()) {
                // 手机微信端支付
                $ReturnData['WeChatType'] = 'WeChatInternal';
            } else {
                // PC端扫码支付
                $ReturnData['WeChatType'] = 'WeChatScanCode';
            }
        }

        return $ReturnData;
    }

    // 拼装订单数组
    private function GetAddMoneyData($UsersTypeData = array(), $pay_method = 'balance', $status = 2, $details = '')
    {
        $wechat_pay_type = '';
        if ('balance' == $pay_method) {
            $pay_method_new = '余额';
        } else if ('alipay' == $pay_method) {
            $pay_method_new = '支付宝';
        } else if ('wechat' == $pay_method) {
            if (isMobile() && !isWeixin()) {
                // 手机浏览器端支付
                $wechat_pay_type = 'WeChatH5';
            } else if (isMobile() && isWeixin()) {
                // 手机微信端支付
                $wechat_pay_type = 'WeChatInternal';
            } else {
                // PC端扫码支付
                $wechat_pay_type = 'WeChatScanCode';
            }
            $pay_method_new = '微信';
        } else {
            $pay_method_new = $pay_method;
        }

        $details = '会员当前级别为【' . $this->users['level_name'] . '】，使用' . $pay_method_new . '支付【 ' . $UsersTypeData['type_name'] . '】，支付金额为' . $UsersTypeData['price'];

        $time = getTime();
        // 拼装数组存入会员购买等级表
        $UsersMoney =  strval($this->users['users_money']) - strval($UsersTypeData['price']);
        $AddMoneyData = [
            'users_id'     => $this->users_id,
            // 订单生成规则
            'order_number' => date('Ymd') . $time . rand(10,100),
            // 金额
            'money'        => $UsersTypeData['price'],
            'users_money'  => unifyPriceHandle($UsersMoney),
            // 购买的产品等级ID(level_id)
            'cause'        => serialize($UsersTypeData),
            // 购买消费标记
            'cause_type'   => 0,
            // 支付状态，默认2为支付完成
            'status'       => $status,
            // 支付方式，默认余额支付
            'pay_method'   => $pay_method,
            'wechat_pay_type' => $wechat_pay_type,
            // 支付详情
            'pay_details'  => serialize($details),
            // 如果时升级订单则存在升级会员级别ID
            'level_id'     => $UsersTypeData['level_id'],
            'lang'         => $this->home_lang,
            'add_time'     => $time,
            'update_time'  => $time
        ];

        return $AddMoneyData;
    }

    // 拼装更新金额明细表数据数组
    private function GetUpMoneyData($data = array(), $pay_method = 'balance')
    {
        // 支付方式
        $pay_method_arr = config('global.pay_method_arr');
        $pay_method_new = !empty($pay_method_arr[$pay_method]) ? $pay_method_arr[$pay_method] : '其他支付';
        // 微信支付则执行
        $wechat_pay_type = '';
        if ('wechat' == $pay_method) {
            if (isMobile() && !isWeixin()) {
                // 手机浏览器端支付
                $wechat_pay_type = 'WeChatH5';
            } else if (isMobile() && isWeixinApplets()) {
                // 手机微信小程序支付
                $wechat_pay_type = 'WeChatApplets';
            } else if (isMobile() && isWeixin()) {
                // 手机微信端支付
                $wechat_pay_type = 'WeChatInternal';
            } else {
                // PC端扫码支付
                $wechat_pay_type = 'WeChatScanCode';
            }
        }
        // 订单详情
        $details = '会员当前级别为【' . $this->users['level_name'] . '】，使用' . $pay_method_new . '【 ' . $data['type_name'] . '】，支付金额为' . $data['price'];
        // 订单数据
        $result = [
            'cause'           => serialize($data),
            'money'           => $data['price'],
            'status'          => 2,
            'pay_method'      => $pay_method,
            'wechat_pay_type' => $wechat_pay_type,
            'pay_details'     => serialize($details),
            'level_id'        => $data['level_id'],
            'update_time'     => getTime()
        ];
        if ('balance' == $pay_method && empty($this->fromApplets)) $result['users_money'] = $this->users['users_money'] - $result['money'];
        return $result;
    }
}