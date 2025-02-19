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
 * Date: 2019-2-25
 */

namespace app\user\controller;

use think\Db;
// use think\Session;
use think\Config;
use think\Page;
use app\user\logic\PayLogic;
use think\Cookie;

class Pay extends Base
{
    public $php_version = '';

    public function _initialize() {
        parent::_initialize();
        $this->users_db       = Db::name('users');      // 会员数据表
        $this->users_money_db = Db::name('users_money');// 会员金额明细表
        $this->shop_order_db = Db::name('shop_order'); // 订单主表
        $this->shop_order_details_db = Db::name('shop_order_details'); // 订单明细表

        // 判断PHP版本信息
        if (version_compare(PHP_VERSION,'5.5.0','<')) {
            $this->php_version = 1; // PHP5.5.0以下版本，可使用旧版支付方式
        }else{
            $this->php_version = 0;// PHP5.5.0以上版本，可使用新版支付方式，兼容旧版支付方式
        }

        // 支付功能是否开启
        $redirect_url = '';
        $pay_open = $this->usersConfig['pay_open'];
        $web_users_switch = tpCache('web.web_users_switch');
        if (empty($pay_open)) { 
            // 支付功能关闭，立马跳到会员中心
            $redirect_url = url('user/Users/index');
            $msg = '支付功能尚未开启！';
        } else if (empty($web_users_switch)) { 
            // 前台会员中心已关闭，跳到首页
            $redirect_url = ROOT_DIR.'/';
            $msg = '会员中心尚未开启！';
        }
        if (!empty($redirect_url)) {
            Db::name('users_menu')->where([
                    'mca'   => 'user/Pay/pay_consumer_details',
                    'lang'  => $this->home_lang,
                ])->update([
                    'status'    => 0,
                    'update_time' => getTime(),
                ]);
            $this->error($msg, $redirect_url);
            exit;
        }
        // --end
    }

    // 消费明细
    public function pay_consumer_details()
    {
        // 订单超过 get_order_validity 设定的时间，则修改订单为已取消状态，无需返回数据
        // model('Pay')->UpdateOrderData($this->users_id);
        // 获取金额明细状态
        $pay_status_arr = Config::get('global.pay_status_arr');
        // 获取金额明细类型
        $pay_cause_type_arr = Config::get('global.pay_cause_type_arr');
        // 只读取已付款或已完成订单信息
        $condition = [];
        array_push($condition, "a.lang = '".$this->home_lang.'\'');
        array_push($condition, "a.users_id = ".$this->users_id);
        array_push($condition, "a.status IN (2, 3)");
        // $increase_type = [0, 3, 5, 6];
        $increase_type = [3, 5, 6];
        $decrease_type = [1, 2, 4, 7];
        $queryID = input('param.queryID/d', 0);
        if (!empty($queryID)) {
            if (in_array($queryID, [10, 20])) {
                if (10 === intval($queryID)) {
                    $cause_type = implode(',', $increase_type);
                    array_push($condition, "(a.cause_type IN ({$cause_type}) OR (a.cause_type = 0 AND a.pay_method ='balance'))");
                } else {
                    $cause_type = implode(',', $decrease_type);
                    array_push($condition, "a.cause_type IN ({$cause_type})");
                }
            }
            else if (in_array($queryID, [30, 40, 50, 60])) {
                array_push($condition, "a.admin_id = 0");
                switch (intval($queryID)) {
                    case 30:
                        $queryName = 'shop_order';
                        break;
                    case 40:
                        $queryName = 'article_order';
                        break;
                    case 50:
                        $queryName = 'media_order';
                        break;
                    case 60:
                        $queryName = 'download_order';
                        break;
                }
                $where = [
                    'pay_name' => 'balance',
                    'users_id' => $this->users_id
                ];
                $orderCodes = Db::name($queryName)->where($where)->column('order_code');
                $orderCodes = !empty($orderCodes) ? implode(',', $orderCodes) : '\'\'';
                array_push($condition, "a.order_number IN ({$orderCodes})");
            }
            else if (in_array($queryID, [70])) {
                array_push($condition, "a.admin_id > 0");
            }
        } else {
            array_push($condition, "(a.cause_type != 0 OR (a.cause_type = 0 AND a.pay_method ='balance'))");
        }
        $this->assign('queryID', $queryID);
        $this->assign('increase_type', $increase_type);
        $this->assign('decrease_type', $decrease_type);
        $where_str = "";
        if (0 < count($condition)) $where_str = implode(" AND ", $condition);
        $count = $this->users_money_db->alias('a')->where($where_str)->count();// 查询满足要求的总记录数
        $Page = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数 
        $list = $this->users_money_db->field('a.*')
            ->alias('a')
            ->where($where_str)
            ->order('a.add_time desc, a.moneyid desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        foreach ($list as $key => $val) {
            $val['money'] = floatval($val['money']);
            $val['users_money'] = floatval($val['users_money']);
            $val['status_name'] = !empty($pay_status_arr[$val['status']]) ? $pay_status_arr[$val['status']] : '';
            if (3 === intval($val['cause_type'])) {
                $causeValue = !empty($val['cause']) ? explode('，', $val['cause']) : [];
                $val['cause_type_name'] = !empty($causeValue[0]) ? str_replace('购买', '订单', $causeValue[0]) : '';
            } else {
                $val['cause_type_name'] = !empty($pay_cause_type_arr[$val['cause_type']]) ? $pay_cause_type_arr[$val['cause_type']] : '';
            }
            if (in_array($val['cause_type'], [4, 5]) && empty($val['order_number'])) {
                $val['order_number'] = date('Ymd') . $val['add_time'];
            }
            $list[$key] = $val;
        }

        $show = $Page->show();// 分页显示输出
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('list', $list);// 赋值数据集
        $this->assign('pager', $Page);// 赋值分页集
        $this->assign('pay_status_arr', $pay_status_arr);
        $this->assign('pay_cause_type_arr', $pay_cause_type_arr);

        $result = [];

        // 菜单名称
        $result['title'] = Db::name('users_menu')->where([
                'mca'   => 'user/Pay/pay_consumer_details',
                'lang'  => $this->home_lang,
            ])->getField('title');

        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);

        /*第三套模板，将充值功能加入明细页面*/
        $money = input('param.money/f');
        $this->assign('money', $money);
        $unified_number = input('param.unified_number/s');
        $this->assign('unified_number', $unified_number);
        /*end*/

        return $this->fetch('users/pay_consumer_details');
    }

    // 账户充值
    public function pay_account_recharge()
    {
        if (IS_AJAX_POST) {
            $where = [
                'status' => 1,
                'pay_info' => ['NEQ', '']
            ];
            $payApiList = Db::name('pay_api_config')->where($where)->select();
            $is_open_wechat = 1;
            if (!empty($payApiList)) {
                foreach ($payApiList as $key => $value) {
                    if (!empty($value['pay_info']) && 'wechat' == $value['pay_mark']) {
                        $is_open_wechat = 0;
                        $payInfo = unserialize($value['pay_info']);
                        if ('wechat' == $value['pay_mark']) {
                            if (0 === intval($payInfo['is_open_wechat'])) {
                                if (empty($payInfo['appid']) || empty($payInfo['mchid']) || empty($payInfo['key'])) $is_open_wechat = 1;
                            } else {
                                $is_open_wechat = 1;
                            }
                        }
                    }
                }
            } else {
                $this->error('网站支付配置未完善，充值服务暂停使用');
            }

            // 判断传入的数据
            $money = input('post.money/f');
            $unified_number = input('post.unified_number/s', '');
            if (!empty($unified_number) && !preg_match('/^\d+$/',$unified_number)) $this->error('订单号不存在！');

            // 判断不为空和数字字符串
            if (!empty($money) && is_numeric($money)) {
                $moneyRow = [];
                if (!empty($unified_number)) {
                    $where = [
                        'lang' => $this->home_lang,
                        'status' => 1,
                        'order_number' => $unified_number
                    ];
                    $moneyRow = $this->users_money_db->where($where)->find();
                }

                $time = getTime();
                if (!empty($moneyRow['moneyid'])) {
                    // 更改充值金额
                    $moneyid = !empty($moneyRow['moneyid']) ? intval($moneyRow['moneyid']) : 0;
                    $order_number = date('Ymd') . $time . rand(10, 100);
                    $order_number_old = !empty($moneyRow['order_number']) ? $moneyRow['order_number'] : '';
                    $where = [
                        'moneyid'  => $moneyid,
                        'users_id' => $this->users_id,
                        'order_number' => $order_number_old,
                    ];
                    $data = [
                        'money'       => $money,
                        'users_money' => $this->users['users_money'] + $money,
                        'order_number' => $order_number,
                        'status'      => 1,
                        'add_time'    => $time,
                        'update_time' => $time
                    ];
                    $this->users_money_db->where($where)->update($data);
                } else {
                    // 数据添加到订单表
                    $cause_type = 1;
                    $order_number = date('Ymd') . $time . rand(10,100); //订单生成规则
                    $pay_cause_type_arr = Config::get('global.pay_cause_type_arr');
                    $data = [
                        'users_id'      => $this->users_id,
                        'cause_type'    => $cause_type,
                        'cause'         => $pay_cause_type_arr[$cause_type],
                        'money'         => $money,
                        'users_money'   => $this->users['users_money'] + $money,
                        'pay_details'   => '',
                        'order_number'  => $order_number,
                        'status'        => 1,
                        'lang'          => $this->home_lang,
                        'add_time'      => $time
                    ];
                    if (isMobile() && isWeixin()) {
                        $data['pay_method'] = 'wechat';// 如果在微信端中则默认为微信支付
                        $data['wechat_pay_type'] = 'WeChatInternal';// 如果在微信端中则默认为微信端调起支付
                    }
                    $moneyid = $this->users_money_db->add($data);
                }

                // 添加状态
                if (!empty($moneyid)) {
                    if (isMobile() && isWeixin() && 0 === intval($is_open_wechat)) {
                        $ReturnOrderData = [
                            'unified_id'       => $moneyid,
                            'unified_number'   => $order_number,
                            'transaction_type' => 1, // 订单支付金额充值
                            'is_gourl'         => 0
                        ];
                        $this->success('等待支付', null, $ReturnOrderData);
                    } else {
                        $payment_type = input('post.payment_type/s');
                        // 第二、第三、第四、第五版会员中心充值处理
                        if (in_array($this->usersTplVersion, ['v2', 'v3', 'v4', 'v5']) && !empty($payment_type)) {
                            $payment_type_arr = explode('_', $payment_type);
                            $pay_mark = !empty($payment_type_arr[1]) ? $payment_type_arr[1] : '';
                            $payApiRow = Db::name('pay_api_config')->where(['pay_mark'=>$pay_mark,'lang'=>$this->home_lang])->find();
                            if (empty($payApiRow)) {
                                $this->error('请选择正确的支付方式！');
                            }
                            $data = [
                                'pay_id'            => $payApiRow['pay_id'],
                                'pay_mark'          => $pay_mark,
                                'unified_id'        => $moneyid,
                                'unified_number'    => $order_number,
                                'transaction_type'  => 1,
                            ];
                            $this->success('正在支付中', url('user/Pay/pay_consumer_details'), $data);
                        }
                        // 第一版会员中心充值处理
                        else {
                            $paydata = [
                                'moneyid'      => $moneyid,
                                'order_number' => $order_number,
                            ];
                            // 先 json_encode 后 md5 加密信息
                            $paystr = md5(json_encode($paydata));
                            // 清除之前的 cookie
                            Cookie::delete($paystr);
                            // 存入 cookie
                            cookie($paystr, $paydata);
                            // 跳转链接
                            $url = urldecode(url('user/Pay/pay_recharge_detail', ['paystr'=>$paystr]));
                            $this->success('等待支付', $url, ['is_gourl'=>1]);
                        }
                    }
                }
                $this->error('充值表单提交失败');
            }
            $this->error(foreign_lang('users14', $this->home_lang));
        }

        $money = input('param.money/f');
        $this->assign('money', $money);

        $unified_number = input('param.unified_number/s', '');
        if (empty($unified_number)) {
            $where = [
                'status' => 1,
                'cause_type' => 1,
                'users_id' => $this->users_id
            ];
            $unified_number = $this->users_money_db->where($where)->order('moneyid desc')->getField('order_number');
        }
        $this->assign('unified_number', $unified_number);

        return $this->fetch('users/pay_account_recharge');
    }

    // 充值详情
    public function pay_recharge_detail()
    {
        // 接收数据读取解析
        $Paystr = input('param.paystr/s');
        $PayData = cookie($Paystr);

        if (!empty($PayData['moneyid']) && !empty($PayData['order_number'])) {
            // 充值信息
            $moneyid = !empty($PayData['moneyid']) ? intval($PayData['moneyid']) : 0;
            $order_number = !empty($PayData['order_number']) ? $PayData['order_number'] : '';
        } else if (!empty($PayData['order_id']) && !empty($PayData['order_code'])) {
            // 订单信息
            $order_id   = !empty($PayData['order_id']) ? intval($PayData['order_id']) : 0;
            $order_code = !empty($PayData['order_code']) ? $PayData['order_code'] : '';
        } else {
            $this->error('订单不存在或已变更', url('user/Shop/shop_centre'));
        }

        // 处理数据
        if (is_array($PayData) && (!empty($order_id) || !empty($moneyid)) && (!empty($order_number) || !empty($order_code))) {
            $data = [];
            if (!empty($moneyid)) {
                // 获取会员充值信息
                $where = [
                    'moneyid'      => $moneyid,
                    'order_number' => $order_number,
                    'users_id'     => $this->users_id,
                    'lang'         => $this->home_lang
                ];

                $data = $this->users_money_db->where($where)->find();
                if (empty($data)) $this->error('订单不存在或已变更', url('user/Pay/pay_consumer_details'));

                // 组装数据返回
                $data['transaction_type'] = 1; // 交易类型，1为充值
                $data['unified_id']       = $data['moneyid'];
                $data['unified_amount']   = $data['money'];
                $data['unified_number']   = $data['order_number'];
                $data['cause']            = '余额充值';
                $data['shop_open_offline'] = !empty($this->usersConfig['shop_open_offline']) ? intval($this->usersConfig['shop_open_offline']) : 0;
                $this->assign('data', $data);

            } else if (!empty($order_id)) {
                /*余额开关*/
                $pay_balance_open = $this->usersConfig['pay_balance_open'];
                if (!is_numeric($pay_balance_open) && empty($pay_balance_open)) {
                    $pay_balance_open = 1;
                }
                /*end*/

                if (!empty($PayData['type']) && 8 == $PayData['type']) {
                    // 获取支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang
                    ];
                    $data = Db::name('media_order')->where($where)->find();
                    if (empty($data)) $this->error('订单不存在或已变更', url('user/Media/index'));
                    
                    $url = url('user/Media/index');
                    if (in_array($data['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $data['transaction_type'] = 8; // 交易类型，8为购买视频
                    $data['unified_id']       = $data['order_id'];
                    $data['unified_amount']   = floatval($data['order_amount']);
                    $data['unified_number']   = $data['order_code'];
                    $data['cause']            = '购买视频';
                    $data['pay_balance_open'] = $pay_balance_open;
                    $data['shop_open_offline'] = !empty($this->usersConfig['shop_open_offline']) ? intval($this->usersConfig['shop_open_offline']) : 0;
                    $this->assign('data', $data);
                } else if(!empty($PayData['type']) && 9 == $PayData['type']) {
                    //文章支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang
                    ];
                    $data = Db::name('article_order')->where($where)->find();
                    if (empty($data)) $this->error('订单不存在或已变更', url('user/Article/index'));

                    $url = url('user/Article/index');
                    if (in_array($data['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $data['transaction_type'] = 9; // 交易类型，9为购买文章
                    $data['unified_id']       = $data['order_id'];
                    $data['unified_amount']   = floatval($data['order_amount']);
                    $data['unified_number']   = $data['order_code'];
                    $data['cause']            = $data['product_name'];
                    $data['pay_balance_open'] = $pay_balance_open;
                    $data['shop_open_offline'] = !empty($this->usersConfig['shop_open_offline']) ? intval($this->usersConfig['shop_open_offline']) : 0;
                    $this->assign('data', $data);
                }else if(!empty($PayData['type']) && 10 == $PayData['type']) {
                    //下载模型支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang
                    ];
                    $data = Db::name('download_order')->where($where)->find();
                    if (empty($data)) $this->error('订单不存在或已变更', url('user/Download/index'));

                    $url = url('user/Download/index');
                    if (in_array($data['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $data['transaction_type'] = 10; // 交易类型，10为购买下载模型
                    $data['unified_id']       = $data['order_id'];
                    $data['unified_amount']   = floatval($data['order_amount']);
                    $data['unified_number']   = $data['order_code'];
                    $data['cause']            = $data['product_name'];
                    $data['pay_balance_open'] = $pay_balance_open;
                    $data['shop_open_offline'] = !empty($this->usersConfig['shop_open_offline']) ? intval($this->usersConfig['shop_open_offline']) : 0;
                    $this->assign('data', $data);
                } else {
                    // 获取支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang
                    ];
                    $data = $this->shop_order_db->where($where)->find();
                    if (empty($data)) $this->error('订单不存在或已变更', url('user/Shop/shop_centre'));
                    // 非法提交，请正规合法提交订单-订单列表点击立即支付时检测判断-订单主表判断
                    if (empty($data['order_total_num'])) $this->error('非法提交，请正规合法提交订单，非法代码：403');

                    // 判断订单状态，1已付款(待发货)，2已发货(待收货)，3已完成(确认收货)，-1订单取消(已关闭)，4订单过期
                    $url = urldecode(url('user/Shop/shop_order_details', ['order_id' => $data['order_id']]));
                    if (in_array($data['order_status'], [1, 2, 3])) {
                        $this->error('订单已支付，即将跳转！', $url);
                    } elseif ($data['order_status'] == 4) {
                        $this->error('订单已过期，即将跳转！', $url);
                    } elseif ($data['order_status'] == -1) {
                        $this->error('订单已关闭，即将跳转！', $url);
                    }

                    // 组装数据返回
                    $data['transaction_type'] = 2; // 交易类型，2为购买
                    $data['unified_id']       = $data['order_id'];
                    $data['unified_amount']   = floatval($data['order_amount']);
                    $data['unified_number']   = $data['order_code'];
                    $data['cause']            = '购买商品';
                    $data['pay_balance_open'] = $pay_balance_open;
                    $data['province']         = get_province_name($data['province']);
                    $data['city']             = get_city_name($data['city']);
                    $data['district']         = get_area_name($data['district']);
                    $data['addressInfo']      = $data['province'].' '.$data['city'].' '.$data['district'].' '.$data['address'];

                    // 订单关闭倒计时处理
                    $data['paymentExpire'] = 0;
                    $data['eyCountdownTimes'] = 'eyCountdownTimes';
                    if (isset($data['order_status']) && 0 === intval($data['order_status'])) {
                        // 查询 自动关闭未付款订单 时长
                        $orderUnpayCloseTime = $this->usersConfig['order_unpay_close_time'];
                        if (!empty($orderUnpayCloseTime)) {
                            // 未付款过期时间
                            $data['paymentExpire'] = $data['add_time'] + (intval($orderUnpayCloseTime) * 60) - getTime();
                        }
                    }

                    // 规格处理
                    $order_details = Db::name('shop_order_details')->where('order_id',$data['order_id'])->order('details_id asc')->select();
                    foreach ($order_details as $key => $val) {
                        // 非法提交，请正规合法提交订单-订单列表点击立即支付时检测判断-订单副表判断
                        if (empty($val['num'])) $this->error('非法提交，请正规合法提交订单，非法代码：404');

                        $product_spec = unserialize($val['data']);
                        $order_details[$key]['pointsGoodsBuyField'] = !empty($product_spec['pointsGoodsBuyField']) ? json_decode($product_spec['pointsGoodsBuyField'], true) : [];
                        if (!empty($product_spec['spec_value_id'])) {
                            $spec_value_id = explode('_', $product_spec['spec_value_id']);
                            if (!empty($spec_value_id)) {
                                $product_spec_list = [];
                                $SpecWhere = [
                                    'aid'           => $val['product_id'],
                                    'spec_value_id' => ['IN',$spec_value_id]
                                ];
                                $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name, spec_value')->select();
                                foreach ($ProductSpecData as $spec_value) {
                                    $product_spec_list[] = [
                                        'name' => $spec_value['spec_name'],
                                        'value' => $spec_value['spec_value'],
                                    ];
                                }
                                $order_details[$key]['product_spec_list'] = $product_spec_list;
                            }
                        }
                    }
                    $data['order_details'] = $order_details;

                    // 支付参数
                    $data['PromType'] = $data['prom_type'];
                    $data['shop_open_offline'] = !empty($this->usersConfig['shop_open_offline']) ? intval($this->usersConfig['shop_open_offline']) : 0;
                    $payApiHidden = model('ShopPublicHandle')->getPayApiHidden($data);
                    $data['use_pay_type'] = $payApiHidden['usePayType'];
                    $data['payTypeHidden'] = $payApiHidden['payTypeHidden'];

                    // 存在积分商城订单则执行
                    if (!empty($data['points_shop_order'])) {
                        $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
                        if (!empty($weappInfo)) {
                            $list = !empty($data) ? $data : [];
                            $list['Details'] = !empty($data['order_details']) ? $data['order_details'] : [];
                            $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                            $pointsShopLogic->pointsShopOrderDataHandle([$list], $data, $data['order_details']);
                            $data['order_details'] = !empty($data['Details']) ? $data['Details'] : $data['order_details'];
                        }
                    }

                    // 仅到店核销
                    $data['onlyVerify'] = !empty($data['logistics_type']) && 2 === intval($data['logistics_type']) ? true : false;
                    // 仅物流配送
                    $data['onlyDelivery'] = !empty($data['logistics_type']) && 1 === intval($data['logistics_type']) ? true : false;

                    $this->assign('data', $data);
                }
            }
            if (!empty($PayData['type']) && 9 === intval($PayData['type']) && isMobile()) {
                return $this->fetch('system/article_pay');
            }else{
                return $this->fetch('users/pay_recharge_detail');
            }
        }
        $this->error('参数错误！');
    }

    // 支付宝订单查询
    public function get_alipay_order($Order = [], $Config = [])
    {
        if (IS_AJAX_POST || (!empty($Order) && !empty($Config))) {
            $unified_id = input('post.unified_id/d') ? input('post.unified_id/d') : $Order['unified_id'];
            $unified_number = input('post.unified_number/s') ? input('post.unified_number/s') : $Order['unified_number'];
            $transaction_type = input('post.transaction_type/d') ? input('post.transaction_type/d') : $Order['transaction_type'];

            //判断openssl是否开启
            $openssl_funcs = get_extension_funcs('openssl');
            if(empty($openssl_funcs)) $this->error('尚未开启php的openssl扩展');

            // 获取支付宝配置信息
            if (!empty($Config)) {
                $pay_alipay_config = $Config;
            } else {
                $where = [
                    'pay_id' => 2,
                    'pay_mark' => 'alipay'
                ];
                $pay_alipay_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
                if (empty($pay_alipay_config)) return false;
                $pay_alipay_config = unserialize($pay_alipay_config);
            }
            if (empty($pay_alipay_config)) $this->error('尚未配置支付宝支付配置');
            
            // 订单号是否存在
            $where = [
                'order_id'   => $unified_id,
                'order_code' => $unified_number
            ];
            $Result = $this->shop_order_db->where($where)->field('order_id, pay_name, order_status')->find();
            if (empty($Result)) $this->error('订单信息错误，请尝试刷新');
            // 获取支付宝配置信息
            if (1 == $pay_alipay_config['version']) {
                if (0 == $Result['order_status']) {
                    if (!empty($Config)) {
                        $this->success('订单正在支付');
                    } else {
                        $this->error('订单正在支付');
                    }
                } else {
                    // 已支付完成则执行
                    $ResultUrl = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type, 'order_code'=>$unified_number]));
                    $ResultData = [
                        'email' => false,
                        'mobile' => false
                    ];
                    if (2 == $transaction_type) {
                        /*订单提醒*/
                        // 邮箱发送
                        $SmtpConfig = tpCache('smtp');
                        $ResultData['email'] = GetEamilSendData($SmtpConfig, $this->users, $where, 1, 'alipay');
                        /* END */

                        /*手机发送*/
                        $SmsConfig = tpCache('sms');
                        $ResultData['mobile'] = GetMobileSendData($SmsConfig, $this->users, $where, 1, 'alipay');
                        /* END */
                    }
                    $this->success('支付完成', $ResultUrl, $ResultData);
                }
            }

            // 引入文件
            vendor('alipay.pagepay.service.AlipayTradeService');
            vendor('alipay.pagepay.buildermodel.AlipayTradeQueryContentBuilder');

            // 实例化加载订单号
            $RequestBuilder = new \AlipayTradeQueryContentBuilder;
            $out_trade_no   = trim($unified_number);
            $RequestBuilder->setOutTradeNo($out_trade_no);

            // 拼装配置
            $config['app_id']     = $pay_alipay_config['app_id'];
            $config['merchant_private_key'] = $pay_alipay_config['merchant_private_key'];
            $config['charset']    = 'UTF-8';
            $config['sign_type']  = 'RSA2';
            $config['gatewayUrl'] = 'https://openapi.alipay.com/gateway.do';
            $config['alipay_public_key'] = $pay_alipay_config['alipay_public_key'];

            // 实例化支付宝配置
            $aop = new \AlipayTradeService($config);
            $result = $aop->Query($RequestBuilder);
            
            // 解析数据
            $result = json_decode(json_encode($result), true);

            // 判断结果
            if ('40004' == $result['code'] && 'Business Failed' === $result['msg']) {
                if (!empty($Config)) {
                    $this->success('正在建立订单信息');
                } else {
                    $this->error('正在建立订单信息');
                }
            } else if ('10000' == $result['code'] && 'WAIT_BUYER_PAY' === $result['trade_status']) {
                if (!empty($Config)) {
                    $this->success('订单已建立，尚未支付');
                } else {
                    $this->error('订单已建立，尚未支付');
                }
            } else if ('10000' == $result['code'] && 'TRADE_SUCCESS' === $result['trade_status']) {
                $ResultUrl = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type, 'order_code'=>$unified_number]));
                $ResultData = [
                    'email' => false,
                    'mobile' => false
                ];
                if (2 == $transaction_type) {
                    /*订单提醒*/
                    // 邮箱发送
                    $SmtpConfig = tpCache('smtp');
                    $ResultData['email'] = GetEamilSendData($SmtpConfig, $this->users, $where, 1, 'alipay');
                    /* END */

                    /*手机发送*/
                    $SmsConfig = tpCache('sms');
                    $ResultData['mobile'] = GetMobileSendData($SmsConfig, $this->users, $where, 1, 'alipay');
                    /* END */
                }
                $this->success('支付完成', $ResultUrl, $ResultData);
            }
        }
    }

    public function get_order_detail()
    {
        if (IS_AJAX_POST) {
            // 订单号
            $unified_number = input('post.unified_number/s');
            $unified_id     = input('post.unified_id/d');
            $transaction_type = input('post.transaction_type/d');
            // 跳转链接
            $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type]));
            if ('2' == $transaction_type) {
                // 购买订单
                // 查询条件
                $OrderWhere = array(
                    'order_id'   => $unified_id,
                    'order_code' => $unified_number,
                    'users_id'   => $this->users_id,
                    'lang'       => $this->home_lang,
                );
                $OrderRow  = $this->shop_order_db->where($OrderWhere)->field('order_status,pay_name')->find();
                if (!empty($OrderRow)) {
                    // 判断返回
                    if ('alipay' == $OrderRow['pay_name'] && in_array($OrderRow['order_status'], [1])) {
                        $this->success('订单已在支付宝付款完成！即将跳转~~~', $url);
                    }else if ('wechat' == $OrderRow['pay_name'] && in_array($OrderRow['order_status'], [1])) {
                        $this->success('订单已在微信付款完成！即将跳转~~~', $url);
                    }else if ('balance' == $OrderRow['pay_name'] && in_array($OrderRow['order_status'], [1])) {
                        // $this->success('订单已使用余额支付完成！即将跳转~~~', $url);
                    }else{
                        $this->error('等待支付');
                    }
                }
            }else if ('1' == $transaction_type) {
                // 充值订单
                // 查询条件
                $where = array(
                    'moneyid'     => $unified_id,
                    'order_number' => $unified_number,
                    'users_id'     => $this->users_id,
                    'lang'         => $this->home_lang,
                );
                $moneyRow  = $this->users_money_db->where($where)->field('status,pay_method')->find();
                if (!empty($moneyRow)) {
                    // 判断返回
                    if ('alipay' == $moneyRow['pay_method'] && in_array($moneyRow['status'], [2,3])) {
                        $this->success('订单已在支付宝付款完成！即将跳转~~~', $url);
                    }else if ('wechat' == $moneyRow['pay_method'] && in_array($moneyRow['status'], [2,3])) {
                        $this->success('订单已在微信付款完成！即将跳转~~~', $url);
                    }else if ('artificial' == $moneyRow['pay_method'] && in_array($moneyRow['status'], [2,3])) {
                        $this->success('订单已人为处理完成！即将跳转~~~', $url);
                    }else{
                        $this->error('等待支付');
                    }
                }
            }
        }
        $this->error('访问错误');
    }

    // 选择付款方式，目前用于微信，支付宝方式已直接调用链接
    public function pay_method()
    {
        // 付款方式，跳转至微信支付还是支付宝支付。
        // $pay_method = input('param.pay_method/s');
        // 订单交易类型
        $transaction_type = input('param.transaction_type/s');
        // 订单号
        $unified_number   = input('param.unified_number/s');
        // 订单ID
        $unified_id       = input('param.unified_id/d');

        // 升级会员支付
        $level_pay = input('get.level_pay/d');
        $WeChatUrl = '';
        if (isset($level_pay) && !empty($level_pay)) {
            // 生成回调URL
            $WeChatUrl = url('user/Level/wechat_order_inquiry',['_ajax'=>1]);
        }
        $this->assign('WeChatUrl',$WeChatUrl);
        $this->assign('unified_number',$unified_number);
        $this->assign('transaction_type',$transaction_type);

        // 执行跳转
        return $this->fetch('users/pay_wechat');
    }

    // 微信支付，获取订单信息并调用微信接口，生成二维码用于扫码支付
    public function pay_wechat_png()
    {
        $users_id = session('users_id');
        if (!empty($users_id)) {
            $unified_number   = input('param.unified_number/s');
            $transaction_type = input('param.transaction_type/s');
            if (2 == $transaction_type) {
                // 购买订单
                $where  = array(
                    'users_id'   => $users_id,
                    'order_code' => $unified_number,
                );
                $data  = $this->shop_order_db->where($where)->find();
                $out_trade_no = $data['order_code'];
                $total_fee    = $data['order_amount'];
            } else if (1 == $transaction_type) {
                // 充值订单
                $where  = array(
                    'users_id'     => $users_id,
                    'order_number' => $unified_number,
                );
                $data  = $this->users_money_db->where($where)->find();
                $out_trade_no = $data['order_number'];
                $total_fee    = $data['money'];
            }

            // 调取微信支付链接
            $payUrl = model('PayApi')->payForQrcode($out_trade_no, $total_fee, $transaction_type);
            // 生成二维码加载在页面上
            vendor('wechatpay.phpqrcode.phpqrcode');
            $qrcode = new \QRcode;
            $pngurl = $payUrl;
            $qrcode->png($pngurl);
            exit();
        } else {
            $this->redirect('user/Users/login');
        }
    }

    // ajax异步查询订单状态，轮询方式（微信）
    public function pay_deal_with()
    {
        if (IS_AJAX_POST) {
            $unified_number   = input('post.unified_number/s');
            $transaction_type = input('post.transaction_type/d');
            if(!empty($unified_number)){
                // ajax异步查询订单是否完成并处理相应逻辑返回。
                vendor('wechatpay.lib.WxPayApi');
                vendor('wechatpay.lib.WxPayConfig');
                // 实例化加载订单号
                $input  = new \WxPayOrderQuery;
                $input->SetOut_trade_no($unified_number);

                // 处理微信配置数据
                $where = [
                    'pay_id' => 1,
                    'pay_mark' => 'wechat'
                ];
                $pay_wechat_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
                if (empty($pay_wechat_config)) return false;

                $pay_wechat_config = unserialize($pay_wechat_config);
                $config_data['app_id'] = $pay_wechat_config['appid'];
                $config_data['mch_id'] = $pay_wechat_config['mchid'];
                $config_data['key']    = $pay_wechat_config['key'];

                // 实例化微信配置
                $config = new \WxPayConfig($config_data);
                $wxpayapi = new \WxPayApi;

                if (empty($config->app_id)) $this->error('微信支付配置尚未配置完成。');

                // 返回结果
                $result = $wxpayapi->orderQuery($config, $input);
                // 业务处理
                if (isset($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS'){
                    if ($result['trade_state'] == 'SUCCESS' && !empty($result['transaction_id'])) {
                        if (2 == $transaction_type) { // 产品购买订单
                            if (empty($order_data['order_status'])) {
                                $returnData = pay_success_logic($this->users_id, $result['out_trade_no'], $result, 'wechat');
                                if (is_array($returnData)) {
                                    if (1 == $returnData['code']) {
                                        // 订单支付完成
                                        if (isMobile() && isWeixin()) {
                                            $url = url('user/Shop/shop_centre');
                                        }else{
                                            $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type]));
                                        }
                                        $returnData['data']['status'] = 1;
                                        $this->success('支付成功，即将跳转~~~', $url, $returnData['data']);
                                    } else {
                                        $this->error($returnData['msg']);
                                    }
                                }
                            }

                            if ($order_data['order_status'] == 1 && !empty($order_data['pay_details'])) {
                                // 订单已付款
                                if (isMobile() && isWeixin()) {
                                    $url = url('user/Shop/shop_centre');
                                }else{
                                    $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type]));
                                }
                                $this->success('支付成功，即将跳转~~~', $url, ['status'=>1]);
                            }

                            if ($order_data['order_status'] == 3) {
                                // 订单已完成，待处理逻辑
                                // 待处理逻辑..........
                            }

                            if ($order_data['order_status'] == 4) {
                                // 订单已取消，待处理逻辑
                                // 待处理逻辑..........
                            }

                        }else if (1 == $transaction_type) { // 充值订单
                            // 付款成功
                            $moneydata = $this->users_money_db->where([
                                'order_number' => $result['out_trade_no'],
                                'users_id'     => $this->users_id,
                                'lang'         => $this->home_lang,
                            ])->find();

                            if (empty($moneydata)) {
                                $this->error('支付异常，请刷新页面后重试');
                            }

                            // 微信付款成功后，订单并未修改状态时，修改订单状态并返回
                            if ($moneydata['status'] == 1) {
                                // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
                                $data = [
                                    'status'        => 2,
                                    // 'pay_method'    => 'wechat', //微信支付
                                    'pay_details'   => serialize($result),
                                    'update_time'   => getTime(),
                                ];
                                $ismoney = $this->users_money_db->where([
                                        'moneyid'  => $moneydata['moneyid'],
                                        'users_id'  => $this->users_id,
                                    ])->update($data);

                                if (!empty($ismoney)) {
                                    // 同步修改会员的金额
                                    $usersdata = [
                                        'users_money' => Db::raw('users_money+'.($moneydata['money'])),
                                    ];
                                    $isusers = $this->users_db->where([
                                            'users_id'  => $this->users_id,
                                        ])->update($usersdata);

                                    if (!empty($isusers)) {
                                        // 业务处理完成，订单已完成
                                        $data2 = [
                                            'status'      => 3,
                                            'update_time' => getTime(),
                                        ];
                                        $this->users_money_db->where([
                                                'moneyid'  => $moneydata['moneyid'],
                                                'users_id'  => $this->users_id,
                                            ])->update($data2);
                                        if (isMobile() && isWeixin()) {
                                            $url = url('user/Pay/pay_consumer_details');
                                        }else{
                                            $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type]));
                                        }
                                        $this->success('充值成功，即将跳转~~~', $url, ['status'=>1]);
                                    }else{
                                        $this->success('付款成功，但未充值成功，请联系管理员。', null, ['status'=>2]);
                                    }
                                }else{
                                    $this->success('付款成功，数据错误，未能充值成功，请联系管理员。', null, ['status'=>2]);
                                }
                            }

                            if ($moneydata['status'] == 2 && !empty($moneydata['pay_details'])) {
                                // 订单已付款
                                if (isMobile() && isWeixin()) {
                                    $url = url('user/Pay/pay_consumer_details');
                                }else{
                                    $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>$transaction_type]));
                                }
                                $this->success('充值成功，即将跳转~~~', $url, ['status'=>1]);
                            }

                            if ($moneydata['status'] == 3) {
                                // 订单已完成，待处理逻辑
                                // 待处理逻辑..........
                            }

                            if ($moneydata['status'] == 4) {
                                // 订单已取消，待处理逻辑
                                // 待处理逻辑..........
                            }
                        }
                    }else if ($result['trade_state'] == 'NOTPAY') {
                        // 付款中
                        $this->success('正在付款中~~~~', '', ['status'=>0]);
                    }
                }else{
                    $msg = '订单号：'.$unified_number.'，正在付款中~~~~~';
                    $this->error($msg, null, ['status'=>0]);
                }
            }
        }
        $this->error('访问错误');
    }

    // 微信支付成功后跳转到此页面
    public function pay_success()
    {
        $transaction_type = input('param.transaction_type/d');
        if (1 == $transaction_type) {
            $url = urldecode(url('user/Pay/pay_consumer_details'));
        }else if (2 == $transaction_type) {
            $url = urldecode(url('user/Shop/shop_centre'));
        }
        $this->assign('url',$url);
        return $this->fetch('users/pay_success');
    }

    // 新版支付宝支付
    public function newAlipayPayUrl()
    {
        $data['unified_number']   = input('param.unified_number/s');
        $data['unified_amount']   = input('param.unified_amount/f');
        $data['transaction_type'] = input('param.transaction_type/d');
        /*校验支付金额与订单金额是否相符合，避免被钻空子 start*/
        $payLogicObj = new PayLogic();
        $OrderData = $payLogicObj->checkAmount($data['unified_number'],$data['unified_amount'],$data['transaction_type']);
        if(empty($OrderData)){
            $this->error("支付失败，支付金额与订单金额不相符");
        }
        /*校验支付金额与订单金额是否相符合，避免被钻空子 end*/
        // 调用新版支付宝支付方法
        $Result = model('PayApi')->getNewAliPayPayUrl($data);
        if (!empty($Result)) $this->error($Result);
    }

    // 支付宝回调接口，处理订单数据
    public function alipay_return()
    {
        // 跳转处理回调信息
        $pay_logic = new PayLogic();
        $result    = $pay_logic->alipay_return();
        if (!empty($result['code']) && 1 == $result['code']) {
            $this->redirect($result['url']);
        }else{
            $msg = !empty($result['msg']) ? $result['msg'] : '数据出错';
            $this->error($msg);
        }
    }

    // 余额支付
    public function balance_payment()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $post['unified_id'] = intval($post['unified_id']);
            $Data = $this->shop_order_db->field('order_code,order_amount,order_id,order_status')->where([
                    'order_id'  => $post['unified_id'],
                    'order_code' => $post['unified_number'],
                ])->find();
            if (empty($Data)) $this->error('订单不存在或已变更', url('user/Shop/shop_centre'));
            
            //1已付款(待发货)，2已发货(待收货)，3已完成(确认收货)，-1订单取消(已关闭)，4订单过期
            $url = urldecode(url('user/Shop/shop_order_details', ['order_id' => $Data['order_id']]));
            if (in_array($Data['order_status'], [1,2,3])) {
                $this->success('订单已支付！即将跳转~~~', $url);
            } elseif ($Data['order_status'] == 4) {
                $this->success('订单已过期！即将跳转~~~', $url);
            } elseif ($Data['order_status'] == -1) {
                $this->success('订单已关闭！即将跳转~~~', $url);
            }
            if ($this->users['users_money'] >= $Data['order_amount']) {
                $Where = [
                    'users_id'   => $this->users_id,
                    'lang'       => $this->home_lang,
                ];

                $post['payment_amount'] = $Data['order_amount'];
                $post['payment_type']   = '余额支付';
                $OrderData = [
                    'order_status' => 1,
                    'pay_name'     => 'balance',// 余额支付
                    'wechat_pay_type' => '', // 余额支付则清空微信标志
                    'pay_details'  => serialize($post),
                    'pay_time'     => getTime(),
                    'update_time'  => getTime(),
                ];
                $OrderWhere = [
                    'order_id'   => $Data['order_id'],
                    'order_code' => $Data['order_code'],
                ];
                $OrderWhere = array_merge($Where, $OrderWhere);
                $return = $this->shop_order_db->where($OrderWhere)->update($OrderData);

                if (!empty($return)) {
                    $DetailsWhere = [
                        'order_id'   => $Data['order_id'],
                    ];
                    $DetailsWhere = array_merge($Where, $DetailsWhere);
                    $DetailsData['update_time'] = getTime();
                    $this->shop_order_details_db->where($DetailsWhere)->update($DetailsData);

                    $UsersData = [
                        'users_money' => $this->users['users_money'] - $Data['order_amount'],
                        'update_time' => getTime(),
                    ];
                    $users_id = $this->users_db->where($Where)->update($UsersData);
                    if (!empty($users_id)) {
                        // 添加订单操作记录
                        AddOrderAction($Data['order_id'],$this->users_id,'0','1','0','1','支付成功！','会员使用余额完成支付！');
                        // 虚拟自动发货
                        model('Pay')->afterVirtualProductPay($DetailsWhere);

                        // 邮箱发送
                        $SmtpConfig = tpCache('smtp');
                        $ResultData['email'] = GetEamilSendData($SmtpConfig, $this->users, $OrderWhere, 1, 'balance');

                        // 手机发送
                        $SmsConfig = tpCache('sms');
                        $ResultData['mobile'] = GetMobileSendData($SmsConfig, $this->users, $OrderWhere, 1, 'wechat');
                        if (isMobile() && isWeixin()) {
                            $url = url('user/Shop/shop_centre');
                        }else{
                            $url = urldecode(url('user/Pay/pay_success', ['transaction_type'=>2]));
                        }
                        $this->success('订单已在余额付款完成！即将跳转~~~', $url, $ResultData);
                    }
                }else{
                    $this->error('订单支付异常，请刷新后再进行支付！');
                }
            }else{
                $url = urldecode(url('user/Pay/pay_account_recharge'));
                $this->error('余额不足，若要使用余额支付，请去充值！',$url);
            }
        }
    }

    public function update_pay_method()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post)) {
                // 初始化默认为微信支付，用于存入数据
                $pay_method       = 'wechat';
                // 初始化默认为传入的值，这个参数仅用于微信支付存入数据
                $wechat_pay_type  = '';
                // 订单交易类型，用于判断
                $transaction_type = $post['transaction_type'];
                // 支付方式(支付宝或微信)，用于判断
                $pay_method_type  = $post['pay_method'];
                // 订单ID，用于查询
                $unified_id       = intval($post['unified_id']);
                // 订单号，用于查询
                $unified_number   = $post['unified_number'];

                /*判断微信支付是否开启*/
                if ('WeChatInternal' == $pay_method_type) {
                    $where = [
                        'pay_id' => 1,
                        'pay_mark' => 'wechat'
                    ];
                    $PayInfo = Db::name('pay_api_config')->where($where)->getField('pay_info');
                    if (!empty($PayInfo)) $PayInfo = unserialize($PayInfo);
                    if (empty($PayInfo) || 1 == $PayInfo['is_open_wechat']) {
                        // $querydata = [
                        //     'order_id'   => $unified_id,
                        //     'order_code' => $unified_number
                        // ];
                        // /*修复1.4.2漏洞 -- 加密防止利用序列化注入SQL*/
                        // $querystr = '';
                        // foreach($querydata as $_qk => $_qv)
                        // {
                        //     $querystr .= $querystr ? "&$_qk=$_qv" : "$_qk=$_qv";
                        // }
                        // $querystr = str_replace('=', '', mchStrCode($querystr));
                        // $auth_code = tpCache('system.system_auth_code');
                        // $hash = md5("payment".$querystr.$auth_code);
                        // /*end*/
                        // $PaymentUrl = urldecode(url('user/Pay/pay_recharge_detail', ['querystr'=>$querystr,'hash'=>$hash]));

                        // 付款地址处理，对ID和订单号加密，拼装url路径
                        $Paydata = [
                            'order_id'   => $unified_id,
                            'order_code' => $unified_number
                        ];

                        // 先 json_encode 后 md5 加密信息
                        $Paystr = md5(json_encode($Paydata));

                        // 清除之前的 cookie
                        Cookie::delete($Paystr);

                        // 存入 cookie
                        cookie($Paystr, $Paydata);

                        // 跳转链接
                        $PaymentUrl = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$Paystr]));

                        $this->success('105：信息正确', $PaymentUrl, ['is_gourl'=>1]);
                    }
                }
                /* END */

                // 判断订单交易类型，选择查询条件
                if (1 == $transaction_type) {
                    // 充值金额
                    $UpdateWhere = [
                        'moneyid'      => $unified_id,
                        'order_number' => $unified_number,
                        'users_id'     => $this->users_id,
                        'lang'         => $this->home_lang,
                    ];
                }else if (2 == $transaction_type) {
                    // 购买商品
                    $UpdateWhere = [
                        'order_id'   => $unified_id,
                        'order_code' => $unified_number,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang,
                    ];
                    // 查询订单价格
                    $order_total_amount = $this->shop_order_db->where($UpdateWhere)->getField('order_total_amount');
                }

                // 判断支付方式及类型
                if ('AliPay' == $pay_method_type) {
                    // 支付宝支付
                    $pay_method = 'alipay';
                }else {
                    // 微信支付，先判断这个订单是否标记过，标记和传入的参数是否一致，不一致则返回提示结束支付
                    if ('1' == $transaction_type) {
                        // 充值金额，判断是否属于当前支付类型
                        $return = $this->determine_pay_type($this->users_money_db,$UpdateWhere,$pay_method_type);
                        if (!empty($return)) {
                            $this->error($return);exit;
                        }
                    }else if ('2' == $transaction_type) {
                        // 购买商品，判断是否属于当前支付类型
                        $return = $this->determine_pay_type($this->shop_order_db,$UpdateWhere,$pay_method_type);
                        if (!empty($return)) {
                            $this->error($return);exit;
                        }
                    }

                    // 判断支付类型
                    switch ($pay_method_type) {
                        case 'WeChatScanCode':
                            // PC端微信扫码支付
                            $wechat_pay_type = 'WeChatScanCode';
                            break;
                        case 'WeChatInternal':
                            // 手机微信端H5支付
                            $wechat_pay_type = 'WeChatInternal';               
                            break;
                        case 'WeChatH5':
                            // 手机端浏览器H5支付
                            $wechat_pay_type = 'WeChatH5';               
                            break;
                        default:
                            $this->error('错误提示：101，选择支付方式错误，请刷新后重试~~');
                            break;
                    }

                }

                // 判断充值金额\购买商品
                if ('1' == $transaction_type) {
                    // 充值金额
                    $UpdateData = [
                        'pay_method'      => $pay_method,
                        'update_time'     => getTime(),
                    ];
                    if ('AliPay' != $pay_method_type) {
                        // 支付方式不等于支付宝时才修改的内容
                        $UpdateData['wechat_pay_type'] = $wechat_pay_type;
                    }
                    $result = $this->users_money_db->where($UpdateWhere)->update($UpdateData);

                }else if ('2' == $transaction_type) {
                    // 购买商品
                    $UpdateData = [
                        'pay_name'        => $pay_method,
                        'update_time'     => getTime(),
                    ];
                    if ('AliPay' != $pay_method_type) {
                        // 支付方式不等于支付宝时才修改的内容
                        $UpdateData['wechat_pay_type'] = $wechat_pay_type;
                    }
                    $result = $this->shop_order_db->where($UpdateWhere)->update($UpdateData);
                }
                if (!empty($result)) {
                    if (isMobile() && isWeixin()) {
                        $ReturnOrderData = [
                            'unified_id'       => $unified_id,
                            'unified_number'   => $unified_number,
                            'transaction_type' => $transaction_type, // 订单支付购买
                            'order_total_amount' => $order_total_amount,
                            'order_source'     => $post['order_source'], // 订单列表页、订单详情页
                            'is_gourl'         => 1,
                        ];
                        if ($this->users['users_money'] <= '0.00') {
                            if (!empty($this->users['open_id']) || 0 === intval($this->users['thirdparty'])) {
                                // 余额小于0
                                $ReturnOrderData['is_gourl'] = 0;
                                $this->success('101：信息正确', null, $ReturnOrderData);
                            } else if (2 == $post['order_source']) {
                                $this->error('余额为0！');
                            } else {
                                // $this->error('手机端微信使用本站账号登录仅可余额支付！');
                            }
                        } else {
                            if (!empty($this->users['open_id']) || 0 === intval($this->users['thirdparty'])) {
                                // 余额大于0
                                // $url = url('user/Shop/shop_wechat_pay_select');
                                // session($this->users_id.'_ReturnOrderData', $ReturnOrderData);
                                // $this->success('102：信息正确', $url, $ReturnOrderData);
                                $ReturnOrderData['is_gourl'] = 0;
                                $this->success('102：信息正确', null, $ReturnOrderData);
                            } else if ($this->users['users_money'] < $order_total_amount){
                                $this->error('余额不足！');
                            } else {
                                $url = url('user/Shop/shop_wechat_pay_select');
                                session($this->users_id.'_ReturnOrderData', $ReturnOrderData);
                                $this->success('102：信息正确', $url, $ReturnOrderData);
                            }
                        }
                    }else{
                        $this->success('103：信息正确');
                    }
                }else{
                    $this->error('数据错误，请刷新后重试！刷新后仍然无法支付请联系管理员！');
                }
            }else{
                $this->error('数据错误，请刷新后重试~');
            }
        }
    }

    // 确定支付类型
    // $table 查询的表，仅用于充值金额和购买订单表
    // $where 查询条件
    // $pay_method_type 当前提交的类型，用于判断
    private function determine_pay_type($table, $where, $pay_method_type)
    {
        $new_wechat_pay_type = $table->where($where)->getField('wechat_pay_type');

        // 若为空，则表现未标记过支付类型
        if (empty($new_wechat_pay_type)) {
            return false;
        }

        // 是否数据库中的支付类型和传入的一致
        if ($new_wechat_pay_type != $pay_method_type) {
            // 判断返回提示信息
            switch ($new_wechat_pay_type) {
                case 'WeChatScanCode':
                    // PC端微信扫码支付
                    return '已在PC端浏览器中微信扫码生成订单，请到PC端浏览器完成支付！';
                    break;
                case 'WeChatInternal':
                    // 手机微信端H5支付
                    return '已在手机端微信中生成订单，请到手机端微信完成支付！';                
                    break;
                case 'WeChatH5':
                    // 手机端浏览器H5支付
                    return '已在手机端浏览器中生成订单，请到手机端浏览器完成支付！';                
                    break;
                default:
                    return '错误提示：102，选择支付方式错误，请刷新后重试~~';
                    break;
            }
        } else {
            return false;
        }
    }

    // 手机微信端H5支付
    public function wechat_pay()
    {
        if (IS_POST) {
            $unified_id       = input('post.unified_id/d');
            $unified_number   = input('post.unified_number/s');
            $transaction_type = input('post.transaction_type/d');

            $where = [
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $open_id = input('post.openid') ? input('post.openid') : $this->users_db->where($where)->getField('open_id');
            if (empty($open_id)) {
                $open_id = Db::name('wx_users')->where('users_id',$this->users_id)->value('openid');
            }
            // if (empty($open_id)) $this->error('手机端微信使用本站账号登录仅可余额支付！');

            if (2 == $transaction_type) {
                // 购买商品
                $PayWhere = [
                    'order_id'   => $unified_id,
                    'order_code' => $unified_number
                ];
                $PayData = $this->shop_order_db->where($PayWhere)->field('order_code, order_amount')->find();
                $out_trade_no = $PayData['order_code'];
                $total_fee    = $PayData['order_amount'];
            } else if (1 == $transaction_type) {
                // 充值金额
                $PayWhere = [
                    'moneyid'      => $unified_id,
                    'order_number' => $unified_number
                ];
                $PayData = $this->users_money_db->where($PayWhere)->field('order_number, money')->find();
                $out_trade_no = $PayData['order_number'];
                $total_fee    = $PayData['money'];
            } else if (3 == $transaction_type) { 
                // 升级金额
                $PayWhere = [
                    'moneyid'      => $unified_id,
                    'order_number' => $unified_number
                ];
                $PayData = $this->users_money_db->where($PayWhere)->field('order_number, money')->find();
                $out_trade_no = $PayData['order_number'];
                $total_fee    = $PayData['money'];
            } else if (9 == $transaction_type) { 
                // 文章付费金额
                $PayWhere = [
                    'order_id'      => $unified_id,
                    'order_code' => $unified_number
                ];
                $PayData = M('article_order')->where($PayWhere)->field('order_code, order_amount')->find();
                $out_trade_no = $PayData['order_code'];
                $total_fee    = $PayData['order_amount'];
            } else if (10 == $transaction_type) {
                // 下载模型付费金额
                $PayWhere = [
                    'order_id'      => $unified_id,
                    'order_code' => $unified_number
                ];
                $PayData = Db::name('download_order')->where($PayWhere)->field('order_code, order_amount')->find();
                $out_trade_no = $PayData['order_code'];
                $total_fee    = $PayData['order_amount'];
            } else {
                $this->error('订单类型错误！');
            }

            // 判断是否小程序接入，存在openid则表示小程序接入
            if (input('post.openid')) {
                $data = model('PayApi')->getWechatPay($open_id, $out_trade_no, $total_fee, [], 1, $transaction_type); 
            } else {
                $data = model('PayApi')->getWechatPay($open_id, $out_trade_no, $total_fee, [], 0, $transaction_type);
            }
            
            // 这个data返回的是调用需要时，所需要给微信提供的公众号参数，并非提示信息
            if (!empty($data)) {
                if (input('post.openid')) {
                    echo json_encode($data);
                } else {
                    $this->success($data);
                }
            } else {
                $this->error('微信支付信息错误，请刷新后重试~');
            }
        }
    }

    public function get_openid()
    {
        // 小程序配置
        $MiniproValue = Db::name('weapp_minipro0002')->where('type', 'minipro')->getField('value');
        if (empty($MiniproValue)) return false;
        $MiniproValue = !empty($MiniproValue) ? json_decode($MiniproValue, true) : [];

        $code = input('param.code');
        $appid  = $MiniproValue['appId']; // 小程序APPID
        $secret = $MiniproValue['appSecret']; // 小程序secret
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $appid . '&secret=' . $secret . '&js_code=' . $code . '&grant_type=authorization_code';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。    
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        $res = curl_exec($curl);
        curl_close($curl);
        
        echo json_encode($res);
    }

    public function ajax_applets_pay()
    {
        // 小程序配置
        $MiniproValue = Db::name('weapp_minipro0002')->where('type', 'minipro')->getField('value');
        if (empty($MiniproValue)) return false;
        $MiniproValue = !empty($MiniproValue) ? json_decode($MiniproValue, true) : [];

        $app_id           = $MiniproValue['appId'];
        $unified_id       = input('post.unified_id/s');
        $unified_number   = input('post.unified_number/s');
        $transaction_type = input('post.transaction_type');
        if (!empty($app_id) && !empty($unified_id) && !empty($unified_number)) {
            // ajax异步查询订单是否完成并处理相应逻辑返回。
            vendor('wechatpay.lib.WxPayApi');
            vendor('wechatpay.lib.WxPayConfig');
            
            // 实例化加载订单号
            $input  = new \WxPayOrderQuery;
            $input->SetOut_trade_no($unified_number);
            
            // 处理微信配置数据
            $where = [
                'pay_id' => 1,
                'pay_mark' => 'wechat'
            ];
            $pay_wechat_config = Db::name('pay_api_config')->where($where)->getField('pay_info');
            if (empty($pay_wechat_config)) return false;

            $pay_wechat_config = unserialize($pay_wechat_config);
            $config_data['app_id'] = $app_id;
            $config_data['mch_id'] = $pay_wechat_config['mchid'];
            $config_data['key']    = $pay_wechat_config['key'];

            // 实例化微信配置 
            $config = new \WxPayConfig($config_data);
            $wxpayapi = new \WxPayApi;

            // 返回结果
            $result = $wxpayapi->orderQuery($config, $input);
            if (isset($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS'){
                if ($result['trade_state'] == 'SUCCESS' && !empty($result['transaction_id'])) {
                    if (1 == $transaction_type) {
                        // 充值处理
                        $moneydata = $this->users_money_db->where([
                            'order_number' => $result['out_trade_no'],
                        ])->find();

                        // 微信付款成功后，订单并未修改状态时，修改订单状态并返回
                        if ($moneydata['status'] == 1) {
                            // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
                            $data = [
                                'status'        => 2,
                                'pay_details'   => serialize($result),
                                'update_time'   => getTime(),
                            ];
                            $ismoney = $this->users_money_db->where([
                                    'moneyid'  => $moneydata['moneyid'],
                                ])->update($data);
                            if (!empty($ismoney)) {
                                // 同步修改会员的金额
                                $usersdata = [
                                    'users_money' => Db::raw('users_money+'.($moneydata['money'])),
                                ];
                                $isusers = $this->users_db->where([
                                        'users_id'  => $moneydata['users_id'],
                                    ])->update($usersdata);

                                if (!empty($isusers)) {
                                    // 业务处理完成，订单已完成
                                    $data2 = [
                                        'status'      => 3,
                                        'update_time' => getTime(),
                                    ];
                                    $this->users_money_db->where([
                                            'moneyid'  => $moneydata['moneyid'],
                                            'users_id' => $moneydata['users_id'],
                                        ])->update($data2);
                                    $url = url('user/Pay/pay_consumer_details');
                                    $this->success('充值成功', $url);
                                }else{
                                    $this->error('付款成功，但未充值成功，请联系管理员。');
                                }
                            }else{
                                $this->error('付款成功，数据错误，未能充值成功，请联系管理员。');
                            }
                        }
                    } else if (2 == $transaction_type) {
                        // 支付处理
                        $order_data = $this->shop_order_db->where([
                            'order_code' => $result['out_trade_no'],
                        ])->find();
                        if (0 == $order_data['order_status']) {
                            $OrderWhere = [
                                'order_id' => $order_data['order_id'],
                            ];
                            // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
                            $OrderData = [
                                'order_status' => 1,
                                'pay_details'  => serialize($result),
                                'pay_time'     => getTime(),
                                'update_time'  => getTime(),
                            ];
                            $order_id = $this->shop_order_db->where($OrderWhere)->update($OrderData);
                            if (!empty($order_id)) {
                                $DetailsData['update_time'] = getTime();
                                $this->shop_order_details_db->where($OrderWhere)->update($DetailsData);
            
                                // 添加订单操作记录
                                AddOrderAction($order_data['order_id'],$order_data['users_id'],'0','1','0','1','支付成功！','会员使用微信小程序完成支付！');
                                    
                                // 邮箱发送
                                $SmtpConfig = tpCache('smtp');
                                $Result['email'] = GetEamilSendData($SmtpConfig, $this->users, $order_data, 1, 'wechat');

                                // 手机发送
                                $SmsConfig = tpCache('sms');
                                $Result['mobile'] = GetMobileSendData($SmsConfig, $this->users, $order_data, 1, 'wechat');

                                $url = url('user/Shop/shop_centre');
                                $this->success('支付成功！', $url, $Result); 
                            }
                        }
                    } else if (3 == $transaction_type) { 
                        // 会员升级处理
                        $moneydata = $this->users_money_db->where([
                            'order_number' => $result['out_trade_no'],
                        ])->find();

                        // 微信付款成功后，订单并未修改状态时，修改订单状态并返回
                        if ($moneydata['status'] == 1) {
                            $cause = unserialize($moneydata['cause']);
                            $UsersTypeData = M('users_type_manage')->where('type_id',$cause['type_id'])->find();

                            // 订单更新条件
                            $where = [
                                'moneyid' => $moneydata['moneyid'],
                                'users_id' => $moneydata['users_id'],
                            ];

                            // 订单更新数据，更新为已付款
                            $details = '会员当前级别为【' . $this->users['level_name'] . '】，使用微信支付【 ' . $UsersTypeData['type_name'] . '】，支付金额为' . $UsersTypeData['price'];
                            $UpMoneyData = [
                                'cause'           => serialize($UsersTypeData),
                                'money'           => $UsersTypeData['price'],
                                'status'          => 2,
                                'pay_method'      => $moneydata['pay_method'],
                                'wechat_pay_type' => 'WeChatInternal',
                                'pay_details'     => serialize($details),
                                // 如果时升级订单则存在升级会员级别ID
                                'level_id'        => $UsersTypeData['level_id'],
                                'update_time'     => getTime()
                            ];

                            // 订单更新
                            $ResultID = $this->users_money_db->where($where)->update($UpMoneyData);

                            // 订单更新后续操作
                            if (!empty($ResultID)) {
                                $Where = [
                                    'users_id' => $moneydata['users_id'],
                                ];

                                // 获取更新会员数据数组
                                $UpUsersData = $this->GetUpUsersData($UsersTypeData);
                                $ReturnID = M('users')->where($Where)->update($UpUsersData);
                                
                                // 用户充值金额后续操作
                                if (!empty($ReturnID)) {
                                    $url = url('user/Level/level_centre');
                                    $this->success('升级成功', $url);
                                } else {
                                    $this->error('付款成功，但未升级成功，请联系管理员。');
                                }
                            } else {
                                $this->error('付款成功，数据错误，未能升级成功，请联系管理员。');
                            }
                        }
                    } else if (9 == $transaction_type) { 
                        // 支付处理
                        $order_data = M('article_order')->where([
                            'order_code' => $result['out_trade_no'],
                        ])->find();
                        if (0 == $order_data['order_status']) {
                            $OrderWhere = [
                                'order_id' => $order_data['order_id'],
                            ];
                            // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
                            $OrderData = [
                                'order_status' => 1,
                                'pay_details'  => serialize($result),
                                'pay_time'     => getTime(),
                                'pay_name'  => 'wechat',
                                'wechat_pay_type' => 'WeChatInternal',
                                'update_time'  => getTime(),
                            ];
                            $order_id = M('article_order')->where($OrderWhere)->update($OrderData);
                            if (!empty($order_id)) {
                                // 添加订单操作记录
                                AddOrderAction($order_data['order_id'],$order_data['users_id'],'0','1','0','1','支付成功！','会员使用微信小程序完成支付！');
                                    
                                $url = url('user/Users/article_index');
                                $this->success('支付成功！', $url);
                            } else {
                                $this->error('付款成功，但未执行业务，请联系管理员。');
                            }
                        }
                    } else if (10 == $transaction_type) {
                        // 支付处理
                        $order_data = Db::name('download_order')->where([
                            'order_code' => $result['out_trade_no'],
                        ])->find();
                        if (0 == $order_data['order_status']) {
                            $OrderWhere = [
                                'order_id' => $order_data['order_id'],
                            ];
                            // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
                            $OrderData = [
                                'order_status' => 1,
                                'pay_details'  => serialize($result),
                                'pay_time'     => getTime(),
                                'pay_name'  => 'wechat',
                                'wechat_pay_type' => 'WeChatInternal',
                                'update_time'  => getTime(),
                            ];
                            $order_id = Db::name('download_order')->where($OrderWhere)->update($OrderData);
                            if (!empty($order_id)) {
                                // 添加订单操作记录
                                AddOrderAction($order_data['order_id'],$order_data['users_id'],'0','1','0','1','支付成功！','会员使用微信小程序完成支付！');

                                $url = url('user/Users/download_index');
                                $this->success('支付成功！', $url);
                            } else {
                                $this->error('付款成功，但未执行业务，请联系管理员。');
                            }
                        }
                    }
                }
            }
        }
    }

    // 拼装更新会员数据数组
    private function GetUpUsersData($data = array(), $balance = false)
    {
        $time = getTime();
        // 会员期限定义数组
        $limit_arr = Config::get('global.admin_member_limit_arr');
        // 到期天数
        $maturity_days = $limit_arr[$data['limit_id']]['maturity_days'];
        // 更新会员属性表的数组
        $result = [
            'level' => $data['level_id'],
            'update_time' => $time,
            'level_maturity_days' => Db::raw('level_maturity_days+'.($maturity_days)),
        ];

        // 如果是余额支付则追加数组
        if (!empty($balance)) $result['users_money'] = Db::raw('users_money-'.($data['price']));
        
        // 判断是否需要追加天数，maturity_code在Base层已计算，1表示终身会员天数
        if (1 != $this->users['maturity_code']) {
            // 判断是否到期，到期则执行，3表示会员在期限内，不需要进行下一步操作
            if (3 != $this->users['maturity_code']) {
                // 追加天数数组
                $result['open_level_time']     = $time;
                $result['level_maturity_days'] = $maturity_days;
            }
        }

        return $result;
    }
}
