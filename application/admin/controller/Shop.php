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
 * Date: 2019-03-26
 */

namespace app\admin\controller;

use think\Page;
use think\Db;
use think\Config;
use app\admin\logic\ShopLogic;
use app\admin\logic\ProductSpecLogic; // 用于产品规格逻辑功能处理
use app\user\model\Pay as PayModel;  //用于虚拟网盘商品付款后自动走流程处理

class Shop extends Base {

    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
        $this->language_access(); // 多语言功能操作权限
        $this->users_db              = Db::name('users');                   // 会员信息表
        $this->shop_order_db         = Db::name('shop_order');              // 订单主表
        $this->shop_order_details_db = Db::name('shop_order_details');      // 订单明细表
        $this->shop_address_db       = Db::name('shop_address');            // 收货地址表
        $this->shop_express_db       = Db::name('shop_express');            // 物流名字表
        $this->shop_order_log_db     = Db::name('shop_order_log');          // 订单操作表
        $this->shipping_template_db  = Db::name('shop_shipping_template');  // 运费模板表
        $this->product_spec_preset_db = Db::name('product_spec_preset');    // 产品规格预设表

        // 用于产品规格逻辑功能处理
        $this->ProductSpecLogic = new ProductSpecLogic;

        // 模型是否开启
        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        $this->assign('channeltype_row', $channeltype_row);

        // 过期订单预处理
        $this->ShopLogic = new ShopLogic;
        $this->ShopLogic->OverdueOrderHandle();

        // 列出营销功能里已使用的模块
        $marketFunc = $this->ShopLogic->marketLogic();
        $this->assign('marketFunc', $marketFunc);

        // 获取核销插件数据
        $this->weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
        $this->assign('weappInfo', $this->weappInfo);
    }

    public function home()
    {
        $url = url('Statistics/index', [], true, true);
        $url = preg_replace('/^http(s?)/i', $this->request->scheme(), $url);
        $this->redirect($url);
        exit;
    }

    /**
     * 商城设置
     */
    public function conf()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post)) {
                $TestPass = $post['TestPass'];
                unset($post['TestPass']);
                if (0 == $TestPass) unset($post['shop']['shop_open_spec']);

                foreach ($post as $key => $val) {
                    is_array($val) && getUsersConfigData($key, $val);
                }
                if (!empty($post['shop_open_comment']) && 1 === intval($post['shop_open_comment'])) {
                    tpCache('web', ['web_shopcomment_switch' => 1]);
                } else if (isset($post['shop_open_comment']) && 0 === intval($post['shop_open_comment'])) {
                    tpCache('web', ['web_shopcomment_switch' => 0]);
                }
                
                $this->success('设置成功！', url('Shop/conf'));
            }
        }

        $Result = VerifyLatestTemplate();
        if (!empty($Result)) getUsersConfigData('shop', ['shop_open_spec' => 0]);
        $TestPass = empty($Result) ? 1 : 0;
        $this->assign('TestPass', $TestPass);

        // 商城配置信息
        $smtp = tpCache('smtp');
        $this->assign('smtp', $smtp);

        // 文件类型
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? $file_type : 'zip|gz|rar|iso|doc|xls|ppt|wps|docx|xlsx|pptx|pdf|pem';
        if (strpos($file_type, 'pem') === false) $file_type = $file_type . '|pem';
        $this->assign('file_type', $file_type);

        // 系统最大上传文件的大小
        $this->assign('upload_max_filesize', upload_max_filesize());
        return $this->fetch('conf');
    }

    /**
     *  订单列表
     */
    public function index()
    {
        // 是否安装 秒杀抢购插件
        $where = [
            'status' => 1,
            'code' => 'Seckill'
        ];
        $seckill = Db::name('weapp')->where($where)->count();
        $this->assign('seckill', $seckill);

        // 积分商城插件
        $pointsShop = model('ShopPublicHandle')->getWeappPointsShop();
        $this->assign('pointsShop', $pointsShop);

        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);

        // 初始化数组和条件
        $where = [
            'merchant_id' => 0,
            'a.lang' => $this->admin_lang,
        ];

        // 订单号查询
        $order_code = input('order_code/s');
        if (!empty($order_code)) $where['a.order_code'] = ['LIKE', "%{$order_code}%"];

        // 支付方式查询
        $pay_name = input('pay_name/s');
        if (!empty($pay_name)) $where['a.pay_name'] = $pay_name;
        $this->assign('pay_name', $pay_name);

        // 订单下单终端查询
        $order_terminal = input('order_terminal/d');
        if (!empty($order_terminal)) $where['a.order_terminal'] = $order_terminal;
        $this->assign('order_terminal', $order_terminal);
        
        // 商品类型查询
        $contains_virtual = input('contains_virtual/d');
        if (!empty($contains_virtual)) $where['a.contains_virtual'] = $contains_virtual;
        $this->assign('contains_virtual', $contains_virtual);

        //活动订单查询
        $act_type = input('act_type/d', 0);
        // 普通订单
        if (1 === intval($act_type)) {
            if (!empty($seckill)) $where['a.is_seckill_order'] = 0;
            if (!empty($pointsShop)) $where['a.points_shop_order'] = 0;
        }
        // 秒杀插件订单
        else if (2 === intval($act_type) && !empty($seckill)) {
            $where['a.is_seckill_order'] = ['gt', 0];
        }
        // 积分商城插件订单
        else if (3 === intval($act_type) && !empty($pointsShop)) {
            $where['a.points_shop_order'] = 1;
        }

        //时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $where['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $where['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $where['a.add_time'] = array('elt', $end);
        }

        // 订单状态查询
        $order_status = input('order_status/s');
        if (!empty($order_status)) $where['a.order_status'] = (10 == $order_status) ? 0 : $order_status;

        // 分页查询
        $count = $this->shop_order_db->alias('a')->where($where)->count('order_id');
        $pageObj = new Page($count, config('paginate.list_rows'));

        // 订单主表数据查询
        $list = $this->shop_order_db->alias('a')
            ->field('a.*, b.username as u_username, b.nickname as u_nickname, b.mobile as u_mobile')
            ->where($where)
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        if (empty($list) && !empty($order_code)) {
            // 通过商品名称查询订单号
            $where_1['product_name'] = ['LIKE', "%{$order_code}%"];
            $order_ids = $this->shop_order_details_db->where($where_1)->group('order_id')->column('order_id');
            // 重新查询订单主表
            unset($where['a.order_code']);
            $where['a.order_id'] = ['IN', $order_ids];
            // 分页查询
            $count = $this->shop_order_db->alias('a')->where($where)->count('order_id');
            $pageObj = new Page($count, config('paginate.list_rows'));
            // 订单主表数据查询
            $list = $this->shop_order_db->alias('a')
                ->field('a.*, b.username as u_username, b.nickname as u_nickname, b.mobile as u_mobile')
                ->where($where)
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->order('a.order_id desc')
                ->limit($pageObj->firstRow.','.$pageObj->listRows)
                ->select();
        }

        $order_ids = $order_code_arr = [];
        $OrderReminderID = [];
        foreach ($list as $key => $value) {
            array_push($order_ids, $value['order_id']);
            if (1 == $value['order_status']){
                array_push($OrderReminderID, $value['order_id']);
            } elseif (in_array($value['order_status'],[2,3])){
                array_push($order_code_arr, $value['order_code']);
            }
        }

        // 处理订单详情数据
        $where = [
            'a.order_id' => ['IN', $order_ids]
        ];
        $DetailsData = $this->shop_order_details_db->alias('a')
            ->field('a.*, b.service_id, b.status')
            ->where($where)
            ->join('__SHOP_ORDER_SERVICE__ b', 'a.details_id = b.details_id && b.status NOT IN (3, 8, 9)', 'LEFT')
            ->order('details_id asc')
            ->select();
        $ArchivesData = get_archives_data($DetailsData, 'product_id');
        $OrderServiceStatus = Config::get('global.order_service_status');
        foreach ($DetailsData as $key => $value) {
            // 售后信息处理
            $value['service_id'] = !empty($value['service_id']) ? $value['service_id'] : 0;
            $value['status'] = !empty($value['status']) ? $value['status'] : 0;
            $value['status_name'] = !empty($value['status']) ? $OrderServiceStatus[$value['status']] : '';
            // 产品属性处理
            $value['data'] = !empty($value['data']) ? unserialize($value['data']) : [];
            $value['pointsGoodsBuyField'] = !empty($value['data']['pointsGoodsBuyField']) ? json_decode($value['data']['pointsGoodsBuyField'], true) : [];
            $value['data'] = htmlspecialchars_decode(htmlspecialchars_decode($value['data']['spec_value']));
            // 组合数据
            $value['data'] = explode('<br/>', $value['data']);
            $valueData = '';
            foreach ($value['data'] as $key_1 => $value_1) {
                $delimiter = '';//!empty($isMobile) ? '；' : '';
                if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>' . $delimiter;
            }
            $value['data'] = $valueData;
            $value['arcurl'] = get_arcurl($ArchivesData[$value['product_id']]);
            $value['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));
            $DetailsData[$key] = $value;
        }
        if (!empty($order_code_arr)){
            //处理微信推送数据
            $wsi_where['order_code'] = ['in',$order_code_arr];
            $wsi_where['order_source'] = 2;
            $wx_push_arr = Db::name('wx_shipping_info')->where($wsi_where)->getAllWithIndex('order_code');
        }

        // 把订单详情数据植入订单数据
        $defaultDetails = [
            'details_id' => 0,
            'order_id' => 0,
            'users_id' => 0,
            'product_id' => 0,
            'product_name' => '',
            'num' => 0,
            'data' => '',
            'product_price' => 0,
            'prom_type' => 0,
            'litpic' => get_default_pic(),
            'apply_service' => 0,
            'is_comment' => 0,
            'lang' => 'cn',
            'add_time' => 0,
            'update_time' => 0,
            'service_id' => 0,
            'status' => 0,
            'status_name' => '',
            'arcurl' => '',
        ];
        // 把订单详情数据植入订单数据
        $DetailsDataGroup = group_same_key($DetailsData, 'order_id');
        foreach ($list as $key => $value) {
            // 处理会员昵称
            $value['u_nickname'] = !empty($value['u_nickname']) ? $value['u_nickname'] : $value['u_username'];
            // 处理订单详情数据
            $value['Details'] = $DetailsDataGroup[$value['order_id']];
            if (empty($value['Details'])) $value['Details'] = [$defaultDetails];
            // 商品条数
            $value['rowspan'] = count($value['Details']);
            // 添加时间
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            // 更新时间
            $value['update_time'] = date('Y-m-d H:i:s', $value['update_time']);
            if (!empty($wx_push_arr[$value['order_code']])) $value['wx_shipping_info'] = $wx_push_arr[$value['order_code']];
            // 重新赋值数据
            $list[$key] = $value;
        }

        // 存在积分商城订单则执行
        $pointsShopOrder = !empty($list) ? get_arr_column($list, 'points_shop_order') : [];
        if (!empty($pointsShopOrder) && in_array(1, $pointsShopOrder)) {
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                $list = $pointsShopLogic->pointsShopOrderDataHandle($list);
            }
        }

        // 查询订单被提醒发货次数
        if (!empty($OrderReminderID)) {
            $field = 'order_id, count(action_id) as action_count';
            $group = 'order_id';
            $LogWhere = [
                'action_desc' => '提醒成功！',
                'order_id' => ['IN', $OrderReminderID]
            ];
            $LogData = $this->shop_order_log_db->field($field)->group($group)->where($LogWhere)->getAllWithIndex('order_id');
        }

        // // 计算单页总额
        // $total_money = $this->shop_order_db->alias('a')->where($where)->where('order_id','IN',$order_ids)->value("SUM(`order_amount`) as `total_money`");
        // /*查询已退还的总额*/
        // $list_order_id = get_arr_column($list, 'order_id');
        // $where_new = [
        //     'service_type' => 2,
        //     'status' => ['IN', [2, 4, 5, 7]],
        //     'order_id' => ['IN', $list_order_id]
        // ];
        // $refund_balance = Db::name('shop_order_service')->where($where_new)->value("SUM(`refund_balance`) as `refund_balance`");
        // /* END */
        // // 计算单页总额 - 已退还的总额
        // if (!empty($refund_balance)) $total_money = $total_money - $refund_balance;
        // $this->assign('total_money', $total_money);
        

        // 分页显示输出
        $pageStr = $pageObj->show();
        // 获取订单方式名称
        $pay_method_arr = Config::get('global.pay_method_arr');
        // 获取订单状态
        $admin_order_status_arr = Config::get('global.admin_order_status_arr');

        // 数据加载
        $this->assign('list', $list);
        $this->assign('page', $pageStr);
        $this->assign('pager', $pageObj);
        $this->assign('LogData', $LogData);
        $this->assign('pay_method_arr', $pay_method_arr);
        $this->assign('admin_order_status_arr', $admin_order_status_arr);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        // 是否开启货到付款
        $shopOpenOffline = 1;
        if (0 === intval($this->usersConfig['shop_open_offline']) || !isset($this->usersConfig['shop_open_offline'])) {
            $shopOpenOffline = 0;
        }
        $this->assign('shopOpenOffline', $shopOpenOffline);

        // 开启的商城商品类型
        $shopType = $this->usersConfig['shop_type'];
        $this->assign('shopType', $shopType);

        // 是否开启微信、支付宝支付
        $where = [
            'status' => 1,
            'pay_mark' => ['IN', ['wechat', 'alipay']]
        ];
        $payApiConfig = Db::name('pay_api_config')->where($where)->select();
        $openWeChat = $openAliPay = 1;
        foreach ($payApiConfig as $key => $value) {
            $payInfo = unserialize($value['pay_info']);
            if (!empty($payInfo) && isset($payInfo['is_open_wechat']) && 0 === intval($payInfo['is_open_wechat'])) {
                $openWeChat = 0;
            }
            if (!empty($payInfo) && isset($payInfo['is_open_alipay']) && 0 === intval($payInfo['is_open_alipay'])) {
                $openAliPay = 0;
            }
        }
        $this->assign('openWeChat', $openWeChat);
        $this->assign('openAliPay', $openAliPay);

        // 是否安装 可视化微信小程序（商城版），未安装开启则不显示小程序支付
        $where = [
            'status' => 1,
            'code' => 'DiyminiproMall'
        ];
        $openMall = Db::name('weapp')->where($where)->count();
        $this->assign('openMall', $openMall);

        // 如果安装手机端后台管理插件并且在手机端访问时执行
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            $mbPage = input('param.p/d', 1);
            $nullShow = intval($pageObj->totalPages) === intval($mbPage) ? 1 : 0;
            $this->assign('nullShow', $nullShow);
            if ($mbPage >= 2) {
                return $this->display('shop/order_list');
            } else {
                return $this->display('shop/index');
            }
        } else {
            return $this->fetch();
        }
    }

    /**
     *  订单详情
     */
    public function order_details()
    {
        $order_id = input('param.order_id/d');
        if (!empty($order_id)) {
            // 查询订单信息
            $this->GetOrderData($order_id);
            // 查询订单操作记录
            $Action = $this->shop_order_log_db->where('order_id',$order_id)->order('action_id desc')->select();
            // 操作记录数据处理
            foreach ($Action as $key => $value) {
                $value['action_note'] = str_replace('!', '', $value['action_note']);
                $value['action_note'] = str_replace('！', '', $value['action_note']);
                // 会员操作
                if (!empty($value['users_id'])) {
                    $Action[$key]['action_note'] = '[买家] ' . $value['action_note'];
                    $user = $this->users_db->field('username, nickname')->where('users_id', $value['users_id'])->find();
                    $Action[$key]['action_users'] = !empty($user['nickname']) ? $user['nickname'] : $user['username'];
                }
                // 管理员操作
                else if (!empty($value['action_user'])) {
                    $Action[$key]['action_note'] = '[商家] ' . $value['action_note'];
                    $user_name = Db::name('admin')->where('admin_id', $value['action_user'])->getField('user_name');
                    $Action[$key]['action_users'] = !empty($user_name) ? $user_name : '';
                }
                // 系统操作
                else {
                    $Action[$key]['action_note'] = '[系统] ' . $value['action_note'];
                    $Action[$key]['action_users'] = '自动操作';
                }

                $Action[$key]['action_desc'] = str_replace("！", "", $value['action_desc']);
                // 操作时，订单发货状态
                $Action[$key]['express_status'] = 1 == $value['express_status'] ? '已发货' : '未发货';
                // 操作时，订单付款状态
                $Action[$key]['pay_status'] = 1 == $value['pay_status'] ? '已支付' : '未支付';
            }

            $this->assign('Action', $Action);

            // 如果安装手机端后台管理插件并且在手机端访问时执行
            $isMobile = input('param.isMobile/d', 0);
            if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
                return $this->display('shop/order_details');
            } else {
                return $this->fetch('order_details');
            }
        }else{
            $this->error('非法访问！');
        }
    }
    
    // 订单备注
    public function order_remarks()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['order_id']) && !empty($post['order_remarks'])) {
                $admin_id = session('admin_info.admin_id');
                $admin_info = Db::name('admin')->where(['admin_id'=>$admin_id])->find();
                // 执行条件
                $where = [
                    'order_id' => intval($post['order_id'])
                ];
                // 查询订单备注信息
                $adminNote = Db::name('shop_order')->where($where)->getField('admin_note');
                if (empty($adminNote)) {
                    $adminNote = [
                        'times' => date('Y-m-d H:i:s'),
                        'admin_id' => $admin_info['admin_id'],
                        'admin_name' => $admin_info['user_name'],
                        'remarks' => strval($post['order_remarks'])
                    ];
                    $update = [
                        'admin_note' => serialize([$adminNote]),
                        'update_time' => getTime()
                    ];
                } else {
                    // 解析原先的备注信息
                    $adminNote = unserialize($adminNote);
                    // 新的备注信息
                    $adminNoteNew = [
                        'times' => date('Y-m-d H:i:s'),
                        'admin_id' => $admin_info['admin_id'],
                        'admin_name' => $admin_info['user_name'],
                        'remarks' => strval($post['order_remarks'])
                    ];
                    // 合并两个备注信息
                    $adminNote = array_merge($adminNote, [$adminNoteNew]);
                    $update = [
                        'admin_note' => serialize($adminNote),
                        'update_time' => getTime()
                    ];
                }
                if (!empty($update)) $updateID = Db::name('shop_order')->where($where)->update($update);
                if (!empty($updateID)) $this->success('备注成功');
            }
            $this->error('操作失败，刷新重试！');
        }
    
        $order_id = input('param.order_id/d', 0);
        $this->assign('order_id', $order_id);
        $where = [
            'order_id' => intval($order_id)
        ];
        $remarksList = Db::name('shop_order')->where($where)->getField('admin_note');
        $remarksList = !empty($remarksList) ? unserialize($remarksList) : [];
        krsort($remarksList);
        $this->assign('remarksList', $remarksList);
    
        return $this->fetch();
    }

    /**
     *  订单发货
     */
    public function order_send()
    {
        $order_id = input('param.order_id');
        if ($order_id) {
            // 查询订单信息
            $this->GetOrderData($order_id);

            $where = [
                'is_choose' => 1,
            ];
            $express = $this->shop_express_db->where($where)->order('sort_order asc, express_id asc')->select();
            $this->assign('express', $express);

            // 如果安装手机端后台管理插件并且在手机端访问时执行
            $isMobile = input('param.isMobile/d', 0);
            if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
                return $this->display('shop/order_send');
            } else {
                return $this->fetch('order_send');
            }
        }
    }

    /**
     *  订单发货操作
     */
    public function order_send_operating()
    {
        if (IS_POST) {
            $post = input('post.');
            // 参数强制转类型
            $post['order_id'] = intval($post['order_id']);
            $post['users_id'] = intval($post['users_id']);
            $post['prom_type'] = intval($post['prom_type']);
            $post['express_id'] = intval($post['express_id']);

            // 需要物流时必须选择快递
            if (isset($post['prom_type']) && 0 === $post['prom_type']) {
                if (empty($post['express_id']) || empty($post['express_name']) || empty($post['express_code'])) {
                    $this->error('请选择快递公司');
                }
            }

            // 条件数组
            $Where = [
                'order_id' => $post['order_id'],
                'users_id' => $post['users_id'],
                'lang' => $this->admin_lang,
            ];

            // 更新数组
            $UpdateData = [
                'order_status'  => 2,
                'express_order' => $post['express_order'],
                'express_name'  => $post['express_name'],
                'express_code'  => $post['express_code'],
                'express_time'  => getTime(),
                'consignee'     => $post['consignee'],
                'update_time'   => getTime(),
                'virtual_delivery' => $post['virtual_delivery'],
            ];

            // 订单操作记录逻辑
            $LogWhere = [
                'order_id' => $post['order_id'],
                'express_status' => 1,
            ];
            $LogData = $this->shop_order_log_db->where($LogWhere)->count();
            if (!empty($LogData)) {
                // 数据存在则表示为修改发货内容
                $OrderData = $this->shop_order_db->where($Where)->field('prom_type')->find();
                $Desc = '修改发货内容！';
                if (1 == $post['prom_type']) {
                    // 提交的数据为虚拟订单
                    if ($OrderData['prom_type'] != $post['prom_type']) {
                        // 此处判断后，提交的订单类型和数据库中的订单类型不相同，表示普通订单修改为虚拟订单
                        $Note = '管理员将普通订单修改为虚拟订单！';
                        if (!empty($post['virtual_delivery'])) {
                            // 若存在数据则拼装
                            $Note .= '给买家回复：'.$post['virtual_delivery'];
                        }
                    } else {
                        // 继续保持为虚拟订单修改
                        $Note = '虚拟订单，无需物流。';
                        if (!empty($post['virtual_delivery'])) {
                            // 若存在数据则拼装
                            $Note .= '给买家回复：'.$post['virtual_delivery'];
                        }
                    }
                } else {
                    // 提交的数据为普通订单
                    if ($OrderData['prom_type'] != $post['prom_type']) {
                        // 这一段暂时无用，因为发货时，暂时无法选择将虚拟订单修改为普通订单
                        $Note = '管理员将虚拟订单修改为普通订单！';
                        if (!empty($post['virtual_delivery'])) {
                            // 若存在数据则拼装
                            $Note .= '给买家回复：'.$post['virtual_delivery'];
                        }
                    } else {
                        // 继续保持为普通订单修改
                        $Note = '使用'.$post['express_name'].'发货成功！';
                    }
                }
                $UpdateData['prom_type'] = $post['prom_type'];
            } else {
                // 数据不存在则表示为初次发货，拼装发货内容
                $Desc = '发货成功！';
                $Note = '使用'.$post['express_name'].'发货成功！';
                if ('1' == $post['prom_type']) {
                    // 若为虚拟订单，无需发货物流。
                    $UpdateData['prom_type'] = $post['prom_type'];
                    $Note = '虚拟订单，无需物流。';
                    if (!empty($post['virtual_delivery'])) {
                        // 若存在数据则拼装
                        $Note .= '给买家回复：'.$post['virtual_delivery'];
                    }
                }
            }

            // 配送单号
            if (empty($post['prom_type']) && empty($post['express_order'])) $this->error('配送单号不能为空');

            // 更新订单主表信息
            $IsOrder = $this->shop_order_db->where($Where)->update($UpdateData);
            if (!empty($IsOrder)) {
                // 更新订单明细表信息
                $Data['update_time'] = getTime();
                $this->shop_order_details_db->where('order_id', $post['order_id'])->update($Data);

                // 添加订单操作记录
                AddOrderAction($post['order_id'], 0, session('admin_id'), 2, 1, 1, $Desc, $Note);

                // 查询会员信息
                $Field = 'username, nickname, email, mobile';
                $Users = $this->users_db->field($Field)->where('users_id', $post['users_id'])->find();

                // 邮箱发送
                $SmtpConfig = tpCache('smtp');
                $Result['email'] = GetEamilSendData($SmtpConfig, $Users, $post, 2);

                // 手机发送
                $SmsConfig = tpCache('sms');
                $Result['mobile'] = GetMobileSendData($SmsConfig, $Users, $post, 2);

                // 发送站内信给会员
                $NickName = !empty($Users['nickname']) ? $Users['nickname'] : $Users['username'];
                SendNotifyMessage($UpdateData, 6, 0, $post['users_id'], $NickName);

                //小程序下单推送微信
                $OrderData = $this->shop_order_db->where($Where)->find();
                $WxPayOrderLogic = new \app\common\logic\WxPayOrderLogic();
                $WxPayOrderLogic->minipro_send_goods($OrderData);

                $this->success('发货成功', null, $Result);
            } else {
                $this->error('发货失败');
            }
        }
    }
    // 订单更新收货地址
    public function order_address()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 判断数据
            if (empty($post['consignee'])) $this->error('请填写收货人姓名！');
            if (empty($post['mobile'])) $this->error('请填写收货人手机！');
            if (empty($post['province'])) $this->error('请选择省份！');
            if (empty($post['city'])) $this->error('请选择城市！');
            if (empty($post['district'])) $this->error('请选择县区！');
            if (empty($post['address'])) $this->error('请填写详细地址！');
    
            // 更新地址
            $where = [
                'order_id' => intval($post['order_id']),
            ];
            $update = [
                'consignee' => strval($post['consignee']),
                'mobile' => strval($post['mobile']),
                'province' => intval($post['province']),
                'city' => intval($post['city']),
                'district' => intval($post['district']),
                'address' => strval($post['address']),
                'update_time' => getTime()
            ];
            $updateID = Db::name('shop_order')->where($where)->update($update);
            if (!empty($updateID)) {
                // 添加订单操作记录
                AddOrderAction($post['order_id'], 0, session('admin_id'), 1, 0, 1, '修改地址！', '修改买家订单收货信息！');
                $this->success('更新成功');
            } else {
                $this->error('操作失败，刷新重试！');
            }
        }
    
        $order_id = input('param.order_id/d', 0);
        $this->assign('order_id', $order_id);
        $address = Db::name('shop_order')->field('consignee, mobile, province, city, district, address')->where($where)->find($order_id);
        $this->assign('address', $address);
        $provinceList = get_province_list();
        $this->assign('provinceList', $provinceList);
        $cityList = Db::name('region')->where('parent_id', $address['province'])->select();
        $this->assign('cityList', $cityList);
        $areaList = Db::name('region')->where('parent_id', $address['city'])->select();
        $this->assign('areaList', $areaList);
    
        return $this->fetch();
    }
    
    // 联动地址获取
    public function ajaxGetSpecifyRegion()
    {
        $parent_id  = input('param.parent_id/d');
        $region = Db::name('region')->where("parent_id", $parent_id)->select();
        $html = '';
        if (!empty($region)) {
            // 拼装下拉选项
            foreach ($region as $value) {
                $html .= "<option value='{$value['id']}'>{$value['name']}</option>";
            }
        }
        $this->success('查询成功！', null, $html);
    }

    /**
     * 查询快递名字及Code
     */
    public function order_express()
    {
        // 查询条件
        $where = [];
        $keywords = input('keywords/s');
        if (!empty($keywords)) $where['express_name'] = ['LIKE', "%{$keywords}%"];
            
        // 分页查询
        $count = $this->shop_express_db->where($where)->count('express_id');
        $pageObj = new Page($count, 10);

        // 查询数据
        $ExpressData = $this->shop_express_db->where($where)
            ->order('sort_order asc,express_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        // 计算选中个数
        $where['is_choose'] = 1;
        $selectNum = $this->shop_express_db->where($where)->count();
        $this->assign('selectNum', $selectNum);

        // 加载模板
        $pageStr = $pageObj->show();
        $select = input('param.select/d', 0);
        $this->assign('select', $select);
        $this->assign('pageStr', $pageStr);
        $this->assign('pageObj', $pageObj);
        $this->assign('ExpressData', $ExpressData);
        return $this->fetch('order_express');
    }

    // 全部选中/全部取消
    public function express_is_choose()
    {
        if (IS_AJAX_POST) {
            $is_choose = input('post.is_choose/d', 0);
            if (isset($is_choose) && $is_choose >= 0) {
                $where = [
                    'is_choose' => !empty($is_choose) ? 0 : 1
                ];
                $update = [
                    'is_choose' => $is_choose,
                    'update_time' => getTime(),
                ];
                $ResultID = $this->shop_express_db->where($where)->update($update);
                if (!empty($ResultID)) $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    /**
     *  管理员后台标记订单状态
     */
    public function order_mark_status()
    {
        if (IS_POST) {
            $post = input('post.');
            // 条件数组
            $Where = [
                'order_id' => $post['order_id'],
                'users_id' => $post['users_id'],
                'lang'     => $this->admin_lang,
            ];

            if ('ddsc' == $post['status_name']) {
                // 订单删除
                $IsDelete = $this->shop_order_db->where($Where)->delete();
                if (!empty($IsDelete)) {
                    $Where = [
                        'order_id' => $post['order_id'],
                    ];
                    // 同步删除订单下的操作记录
                    $this->shop_order_log_db->where($Where)->delete();
                    // 同步删除订单下的产品
                    $this->shop_order_details_db->where($Where)->delete();
                    $this->success('删除成功！', url('Shop/index'));
                }else{
                    $this->error('数据错误！');
                }
            }else{
                $OrderData = $this->shop_order_db->where($Where)->find();

                // 更新数组
                $UpdateData = [
                    'update_time'  => getTime(),
                ];

                // 根据不同操作标记不同操作内容
                if ('yfk' == $post['status_name']) {
                    // 订单标记为付款，追加更新数组
                    $UpdateData['order_status'] = '1';
                    $UpdateData['pay_time']     = getTime();
                    // 管理员付款
                    $UpdateData['pay_name']     = 'admin_pay';
                    $UpdateData['wechat_pay_type'] = '';

                    /*用于添加订单操作记录*/
                    $order_status   = '1'; // 订单状态
                    $express_status = '0'; // 发货状态
                    $pay_status     = '1'; // 支付状态
                    $action_desc    = '付款成功！'; // 操作明细
                    $action_note    = '管理员确认订单付款！'; // 操作备注
                    /*结束*/

                }else if ('ysh' == $post['status_name']) {
                    // 如果后台【商城中心】-【商城配置】-【订单设置】-收货后可维权时间设置为0，则表示订单不允许申请维权
                    $OrderData['allow_service'] = !empty($this->usersConfig['order_right_protect_time']) ? 0 : 1;

                    // 订单确认收货，追加更新数组
                    $UpdateData['order_status'] = '3';
                    $UpdateData['confirm_time'] = getTime();

                    /*用于添加订单操作记录*/
                    $order_status   = '3'; // 订单状态
                    $express_status = '1'; // 发货状态
                    $pay_status     = '1'; // 支付状态
                    $action_desc    = '确认收货！'; // 操作明细
                    $action_note    = '管理员确认订单已收货！'; // 操作备注
                    /*结束*/

                }else if ('gbdd' == $post['status_name']) {
                    // 订单关闭，追加更新数组
                    $UpdateData['order_status'] = '-1';

                    /*用于添加订单操作记录*/
                    $order_status = '-1'; // 订单状态
                    if ('0' == $OrderData['order_status'] || '1' == $OrderData['order_status']) {
                        $express_status = '0'; // 发货状态
                        $pay_status     = '0'; // 支付状态
                    }else{
                        $express_status = '1'; // 发货状态
                        $pay_status     = '1'; // 支付状态
                    }
                    $action_desc  = '订单关闭！'; // 操作明细
                    $action_note  = '管理员关闭订单！'; // 操作备注
                    /*结束*/
                }

                // 更新订单主表
                $IsOrder = $this->shop_order_db->where($Where)->update($UpdateData);
                if (!empty($IsOrder)) {
                    // 更新订单明细表
                    $Data['update_time'] = getTime();
                    $this->shop_order_details_db->where('order_id',$post['order_id'])->update($Data);

                    // 如果是关闭订单操作则执行还原产品库存
                    if ('gbdd' == $post['status_name']) {
                        $UpWhere = $this->shop_order_details_db->where('order_id',$post['order_id'])->field('product_id as aid,num,data')->find();
                        // 读取规格值ID，拼装作为更新条件
                        $UpWhere['spec_value_id'] = unserialize($UpWhere['data'])['spec_value_id'];
                        // 更新数据
                        $UpData['spec_stock']     = Db::raw('spec_stock+'.($UpWhere['num']));
                        $UpData['spec_sales_num'] = Db::raw('spec_sales_num-'.($UpWhere['num']));
                        // 清除多余num数据
                        unset($UpWhere['num']); 
                        // 清除多余data数据
                        unset($UpWhere['data']);
                        // 更新库存及销量
                        Db::name('product_spec_value')->where($UpWhere)->update($UpData);
                    }

                    // 添加订单操作记录
                    AddOrderAction($post['order_id'], 0, session('admin_id'), $order_status, $express_status, $pay_status, $action_desc, $action_note);

                    // 判断是否为虚拟商品
                    if ('yfk' == $post['status_name'] && $OrderData['prom_type'] == 1) {
                        PayModel::afterVirtualProductPay($Where);
                    }

                    // 确认收货则执行
                    if ('ysh' == $post['status_name']) {
                        // 如果安装了分销插件则执行
                        if (is_dir('./weapp/DealerPlugin/')) {
                            // 开启分销插件则执行
                            $weappInfo = model('Weapp')->getWeappList('DealerPlugin');
                            if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
                                // 调用分销逻辑层方法
                                $dealerCommonLogic = new \weapp\DealerPlugin\logic\DealerCommonLogic;
                                $dealerCommonLogic->dealerOrderSettlementExecute($post['order_id'], $post['users_id']);
                            }
                        }
                        // 如果安装了秒杀插件则执行
                        if (is_dir('./weapp/Seckill/')) {
                            $SeckillRow = model('Weapp')->getWeappList('Seckill');
                            //is_seckill_order 只有安装了秒杀插件 shop_order表才会有这个字段
                            if (!empty($SeckillRow) && 1 == intval($SeckillRow['status']) && !empty($order['is_seckill_order'])) {
                                // 调用秒杀逻辑层方法
                                $weappSeckillLogic = new \weapp\Seckill\logic\SeckillLogic;
                                $weappSeckillLogic->confirmOrderHandle($post['order_id'], $post['users_id']);
                            }
                        }
                    }
                    $this->success('操作成功！');
                }
            }
        }else{
            $this->error('非法访问！');
        }
    }

    // 手动关闭订单并退款
    public function order_manual_refund()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $OrderID = intval($post['order_id']);
            $RefundNote = trim($post['refund_note']);
            if (!empty($OrderID)) {
                $update = [
                    'order_id' => $OrderID,
                    'order_status' => '-1',
                    'manual_refund' => 1,
                    'refund_note' => $RefundNote,
                    'update_time' => getTime(),
                ];
                $ResultID = $this->shop_order_db->update($update);
                if (!empty($ResultID)) {
                    // 如果安装了秒杀插件则执行
                    if (is_dir('./weapp/Seckill/')) {
                        $SeckillRow = model('Weapp')->getWeappList('Seckill');
                        //is_seckill_order 只有安装了秒杀插件 shop_order表才会有这个字段
                        if (!empty($SeckillRow) && 1 == intval($SeckillRow['status']) && !empty($order['is_seckill_order'])) {
                            // 调用秒杀逻辑层方法
                            $users_id = $this->shop_order_db->where('order_id',$OrderID)->value('users_id');
                            $weappSeckillLogic = new \weapp\Seckill\logic\SeckillLogic;
                            $weappSeckillLogic->cancelOrderHandle($OrderID, $users_id);
                        }
                    }

                    // 添加订单操作记录
                    AddOrderAction($OrderID, 0, session('admin_id'), '-1', 0, 1, '关闭并退款', '管理员手动关闭订单并自行退款');

                    // 商品库存恢复
                    $where = [
                        'order_id' => $OrderID,
                    ];
                    $shopOrder = $this->shop_order_db->where($where)->select();
                    model('OrderPreHandle')->restoreGoodsStock($shopOrder);

                    // 加添订单售后信息
                    $this->adminAddOrderService($OrderID, $RefundNote);

                    // 返回结束
                    $this->success('操作完成');
                }
            }
        }
        $this->error('非法访问！');
    }

    // 加添订单售后信息
    public function adminAddOrderService($orderID = 0, $refundNote = '')
    {
        $where = [
            'order_id' => $orderID,
        ];
        // 查询订单信息
        $order = Db::name('shop_order')->where($where)->find();
        $city = get_city_name($order['city']);
        $district = get_area_name($order['district']);
        $province = get_province_name($order['province']);
        $order['address'] = $province . ' ' . $city . ' ' . $district . ' ' . $order['address'];
        // 查询订单商品信息
        $details = $this->shop_order_details_db->where($where)->select();
        if (!empty($details)) {
            $times = getTime();
            foreach ($details as $key => $value) {
                // 商品规格信息
                $value['data'] = !empty($value['data']) ? unserialize($value['data']) : [];
                $product_spec = !empty($value['data']['spec_value']) ? htmlspecialchars_decode(htmlspecialchars_decode($value['data']['spec_value'])) : '';
                // 订单售后信息
                $insert = [
                    'service_type' => 2,
                    'users_id'     => intval($order['users_id']),
                    'merchant_id'  => intval($order['merchant_id']),
                    'order_id'     => intval($order['order_id']),
                    'order_code'   => strval($order['order_code']),
                    'details_id'   => intval($value['details_id']),
                    'product_id'   => intval($value['product_id']),
                    'product_name' => strval($value['product_name']),
                    'product_spec' => strval($product_spec),
                    'product_num'  => intval($value['num']),
                    'product_img'  => strval($value['litpic']),
                    'content'      => '商家主动退款',
                    'address'      => strval($order['address']),
                    'consignee'    => $order['consignee'],
                    'mobile'       => $order['mobile'],
                    'manual_refund'=> 1,
                    'manual_time'  => $times,
                    'refund_note'  => !empty($refundNote) ? $refundNote : '商家主动退款',
                    'refund_price' => unifyPriceHandle($value['product_price']),
                    'refund_code'  => 'TK' . $times . rand(10, 99),
                    'status'       => 7,
                    'add_time'     => $times,
                    'update_time'  => $times,
                ];
                $resultID = Db::name('shop_order_service')->insertGetId($insert);
                if (!empty($resultID)) {
                    // 更新订单明细表中对应商品为申请服务
                    $update = [
                        'apply_service' => 1,
                        'update_time' => getTime()
                    ];
                    $this->shop_order_details_db->where('details_id', $value['details_id'])->update($update);

                    // 添加订单服务记录
                    OrderServiceLog($resultID, $order['order_id'], 0, session('admin_id'), '商家主动退款');
                }
            }
        }
    }

    /*
     *  更新管理员备注
     */
    public function update_note()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['order_id'])) {
                $UpdateData = [
                    'admin_note'  => $post['admin_note'],
                    'update_time' => getTime(),
                ];
                $return = $this->shop_order_db->where('order_id',$post['order_id'])->update($UpdateData);
                if (!empty($return)) {
                    $this->success('保存成功！');
                }
            }else{
                $this->error('非法访问！');
            }
        }else{
            $this->error('非法访问！');
        }
    }

    /*
     *  运费模板列表
     */
    public function shipping_template()
    {
        $Where = [
            'a.level' => 1,
        ];

        $region_name = input('param.region_name');
        if (!empty($region_name)) {
            $Where['a.name'] = array('LIKE', "%{$region_name}%");
        }

        // 省份
        $Template = Db::name('region')->field('a.id, a.name, b.template_money, b.template_id')
            ->alias('a')
            ->join('__SHOP_SHIPPING_TEMPLATE__ b', 'a.id = b.province_id', 'LEFT')
            ->where($Where)
            ->getAllWithIndex('id');
        $this->assign('Template', $Template);
        // 统一配送
        $info = $this->shipping_template_db->where('province_id','100000')->find();
        $this->assign('info', $info);

        return $this->fetch('shipping_template');
    }

    // 订单批量删除
    public function order_del()
    {
        $order_id = input('del_id/a');
        $order_id = eyIntval($order_id);
        if (IS_AJAX_POST && !empty($order_id)) {
            // 条件数组
            $Where = [
                'order_id'  => ['IN', $order_id],
                'lang'      => $this->admin_lang,
            ];
            // 查询数据，存在adminlog日志
            $result = $this->shop_order_db->field('order_code')->where($Where)->select();
            $order_code_list = get_arr_column($result, 'order_code');
            // 删除订单列表数据
            $return = $this->shop_order_db->where($Where)->delete();
            if ($return) {
                // 同步删除订单下的产品
                $this->shop_order_details_db->where($Where)->delete();
                // 同步删除订单下的操作记录
                $this->shop_order_log_db->where($Where)->delete();

                adminLog('删除订单：'.implode(',', $order_code_list));
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    /*
     *  查询会员订单数据并加载，无返回
     */
    function GetOrderData($order_id)
    {
        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);

        // 获取订单数据
        $OrderData = $this->shop_order_db->find($order_id);
        $OrderData['add_time'] = date('Y-m-d H:i:s', $OrderData['add_time']);
        $OrderData['pay_time'] = !empty($OrderData['pay_time']) ? date('Y-m-d H:i:s', $OrderData['pay_time']) : 0;
        $OrderData['update_time'] = !empty($OrderData['update_time']) ? date('Y-m-d H:i:s', $OrderData['update_time']) : 0;
        $OrderData['express_time'] = !empty($OrderData['express_time']) ? date('Y-m-d H:i:s', $OrderData['express_time']) : 0;
        $OrderData['confirm_time'] = !empty($OrderData['confirm_time']) ? date('Y-m-d H:i:s', $OrderData['confirm_time']) : 0;
        $OrderData['order_terminal_name'] = '电脑端';
        if (!empty($OrderData['order_terminal']) && 2 === intval($OrderData['order_terminal'])) {
            $OrderData['order_terminal_name'] = '手机端';
        } else if (!empty($OrderData['order_terminal']) && 3 === intval($OrderData['order_terminal'])) {
            $OrderData['order_terminal_name'] = '微信小程序';
        }

        // 获取会员数据
        $UsersData = $this->users_db->find($OrderData['users_id']);
        !empty($UsersData) && $UsersData['head_pic'] = get_head_pic($UsersData['head_pic']);
        // 当前单条订单信息的会员ID，存入session，用于添加订单操作表
        session('OrderUsersId', $OrderData['users_id']);

        // 获取订单详细表数据
        $DetailsData = $this->shop_order_details_db->where('order_id',$OrderData['order_id'])->select();

        // 获取订单状态，后台专用
        $admin_order_status_arr = Config::get('global.admin_order_status_arr');

        // 获取订单方式名称
        $pay_method_arr = Config::get('global.pay_method_arr');

        // 处理订单主表的地址数据处理，显示中文名字
        $OrderData['country']  = '中国';
        $OrderData['province'] = get_province_name($OrderData['province']);
        $OrderData['city']     = get_city_name($OrderData['city']);
        $OrderData['district'] = get_area_name($OrderData['district']);

        $array_new = get_archives_data($DetailsData,'product_id');
        $OrderData['prom_type_virtual'] = false;
        // 处理订单详细表数据处理
        $total_num = 0;
        $OrderData['totalAmount'] = 0;
        foreach ($DetailsData as $key => $value) {
            $total_num += intval($value['num']);
            if ($value['prom_type'] == 1) {
                $OrderData['prom_type_virtual'] = true;
            }
            // 产品属性处理
            $value['data'] = !empty($value['data']) ? unserialize($value['data']) : [];
            $DetailsData[$key]['pointsGoodsBuyField'] = !empty($value['data']['pointsGoodsBuyField']) ? json_decode($value['data']['pointsGoodsBuyField'], true) : [];
            $value['data'] = htmlspecialchars_decode(htmlspecialchars_decode($value['data']['spec_value']));
            // 组合数据
            $value['data'] = explode('<br/>', $value['data']);
            $valueData = '';
            foreach ($value['data'] as $key_1 => $value_1) {
                $delimiter = '';//!empty($isMobile) ? '；' : '';
                if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>' . $delimiter;
            }
            $DetailsData[$key]['product_spec'] = $value['data'] = $valueData;

            // // 规制值
            // $spec_value = !empty($ValueData['spec_value']) ? htmlspecialchars_decode($ValueData['spec_value']) : '';
            // $spec_value = htmlspecialchars_decode($spec_value);
            // // 旧参数
            // $attr_value = !empty($ValueData['attr_value']) ? htmlspecialchars_decode($ValueData['attr_value']) : '';
            // $attr_value = htmlspecialchars_decode($attr_value);
            // // 新参数
            // $attr_value_new = !empty($ValueData['attr_value_new']) ? htmlspecialchars_decode($ValueData['attr_value_new']) : '';
            // $attr_value_new = htmlspecialchars_decode($attr_value_new);
            // // 优先显示新参数
            // $attr_value = !empty($attr_value_new) ? $attr_value_new : $attr_value;
            // $DetailsData[$key]['data'] = $spec_value . $attr_value;

            // 产品内页地址
            $DetailsData[$key]['arcurl'] = get_arcurl($array_new[$value['product_id']]);
            // 小计
            $DetailsData[$key]['subtotal'] = unifyPriceHandle($value['product_price'] * $value['num']);
            // 合计金额
            $OrderData['totalAmount'] += $DetailsData[$key]['subtotal'];
            $OrderData['totalAmount'] = unifyPriceHandle($OrderData['totalAmount']);
            // 支持子目录
            $DetailsData[$key]['litpic'] = handle_subdir_pic($DetailsData[$key]['litpic']);
        }

        // 订单类型
        if (empty($OrderData['prom_type'])) {
            $OrderData['prom_type_name'] = '普通订单';
        }else{
            $OrderData['prom_type_name'] = '虚拟订单';
        }
        if (!empty($OrderData['is_seckill_order'])) $OrderData['prom_type_name'] = '秒杀订单';
        if (!empty($OrderData['points_shop_order'])) $OrderData['prom_type_name'] = '积分订单';

        // 移动端查询物流链接
        $MobileExpressUrl = "//m.kuaidi100.com/index_all.html?type=".$OrderData['express_code']."&postid=".$OrderData['express_order'];
        $this->assign('MobileExpressUrl', $MobileExpressUrl);
        // PC端查询物流链接
        $pcExpressUrl = "https://www.kuaidi100.com/chaxun?com=".$OrderData['express_code']."&nu=".$OrderData['express_order'];
        $this->assign('pcExpressUrl', $pcExpressUrl);
        // 管理员备注
        $adminNoteCount = !empty($OrderData['admin_note']) ? count(unserialize($OrderData['admin_note'])) : 0;
        $this->assign('adminNoteCount', $adminNoteCount);

        // 存在积分商城订单则执行
        if (!empty($OrderData['points_shop_order'])) {
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                $list = !empty($OrderData) ? $OrderData : [];
                $list['Details'] = !empty($DetailsData) ? $DetailsData : [];
                $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                $pointsShopLogic->pointsShopOrderDataHandle([$list], $OrderData, $DetailsData);
            }
        }

        // 加载数据
        $this->assign('OrderData', $OrderData);
        $this->assign('DetailsData', $DetailsData);
        $this->assign('UsersData', $UsersData);
        $this->assign('admin_order_status_arr', $admin_order_status_arr);
        $this->assign('pay_method_arr', $pay_method_arr);
        $this->assign('total_num', $total_num);
        
        // 核销订单记录信息
        $weappVerifyLog = [];
        if (!empty($this->weappInfo)) {
            $where = [
                'users_id' => intval($OrderData['users_id']),
                'order_id' => intval($OrderData['order_id']),
            ];
            $weappVerifyLog = Db::name('weapp_verify')->where($where)->find();
            $weappVerifyLog['verify_time'] = !empty($weappVerifyLog['verify_time']) ? date('Y-m-d H:i:s', $weappVerifyLog['verify_time']) : 0;
        }
        $this->assign('weappVerifyLog', $weappVerifyLog);
    }

    // ------------------------------------------------------------------------------------------------------
    // 以下所有代码都是产品规格处理逻辑 2019-07-08 陈风任
    // ------------------------------------------------------------------------------------------------------
    // 规格列表管理，包含新增、更新
    public function spec_index()
    {
        if (IS_AJAX_POST) {
            // 新增、更新
            $post = input('post.');
            // 当前时间戳
            $time = getTime();
            /*新增数据处理*/
            $post_new = [];
            foreach ($post['preset_new'] as $key => $value) {
                // 规格名称不允许为空
                $preset_name = $post['preset_name_'.$value][0];
                if (empty($preset_name)) continue;
                // 是否同步到已发布的商品规格
                $spec_sync = !empty($post['spec_sync_'.$value]) ? 1 : 0;
                // 排序号
                $sort_order  = $post['sort_order_'.$value];

                // 拼装三维数组
                foreach ($post['preset_value_'.$value] as $kk => $vv) {
                    if (empty($vv)) continue;
                    $post_new[$key][$kk]['preset_mark_id'] = $value; // 标记ID，一整条规格信息中的唯一标识
                    $post_new[$key][$kk]['preset_name']    = $preset_name;
                    $post_new[$key][$kk]['preset_value']   = $vv;
                    $post_new[$key][$kk]['spec_sync']      = $spec_sync;
                    $post_new[$key][$kk]['sort_order']     = $sort_order;
                    $post_new[$key][$kk]['lang']           = $this->admin_lang;
                    $post_new[$key][$kk]['add_time']       = $time;
                    $post_new[$key][$kk]['update_time']    = $time;
                }
            }

            // 三维数组降为二维数组
            $data_new = $this->ProductSpecLogic->ArrayDowngrade($post_new);
            /* END */

            /*原有数据处理*/
            $post_old = [];
            $spec_sync_where = $spec_sync_mark_data = $spec_sync_value_data = [];
            foreach ($post['preset_old'] as $key => $value) {
                // 规格名称不允许为空
                $preset_name = $post['preset_name_old_'.$value][0];
                if (empty($preset_name)) continue;

                // 是否同步到已发布的商品规格
                $spec_sync = !empty($post['spec_sync_'.$value]) ? 1 : 0;

                // 排序号
                $sort_order  = $post['sort_order_'.$value];

                // 需要同步的规格名称
                if (!empty($spec_sync)) {
                    array_push($spec_sync_where, $value);
                    $spec_sync_mark_data[$value]['spec_name'] = $preset_name;
                }

                // 拼装三维数组
                foreach ($post['preset_value_old_'.$value] as $kk => $vv) {
                    if (empty($vv)) continue;
                    $preset_id = $post['preset_id_old_'.$value][$kk];
                    // 如果ID是否为空
                    if (!empty($preset_id)) {
                        // 有ID则为更新
                        $post_old[$key][$kk]['preset_id'] = $preset_id;

                        // 需要同步的规格值
                        if (!empty($spec_sync)) $spec_sync_value_data[$preset_id]['spec_value'] = $vv;
                    } else {
                        // 无ID则为新增
                        $post_old[$key][$kk]['lang']     = $this->admin_lang;
                        $post_old[$key][$kk]['add_time'] = $time;
                        $post_old[$key][$kk]['preset_mark_id'] = $value; // 标记ID，一整条规格信息中的唯一标识
                    }
                    $post_old[$key][$kk]['preset_name']  = $preset_name;
                    $post_old[$key][$kk]['preset_value'] = $vv;
                    $post_old[$key][$kk]['spec_sync']    = $spec_sync;
                    $post_old[$key][$kk]['sort_order']   = $sort_order;
                    $post_old[$key][$kk]['update_time']  = $time;
                }
            }
            // 三维数组降为二维数组
            $data_old = $this->ProductSpecLogic->ArrayDowngrade($post_old);
            /* END */

            // 合并数组并且更新数据
            $UpData = array_merge($data_old, $data_new);
            model('ProductSpecPreset')->saveAll($UpData);

            /*执行同步数据*/
            if (!empty($spec_sync_where)) {
                $w_1['spec_mark_id'] = ['IN', $spec_sync_where];
                $f_1 = 'spec_id, spec_mark_id, spec_name, spec_value_id, spec_value';
                $SpecMarkData = Db::name('product_spec_data')->where($w_1)->field($f_1)->order('spec_mark_id asc')->select();
                foreach ($SpecMarkData as $key => $value) {
                    $SpecMarkData[$key]['spec_name'] = $spec_sync_mark_data[$value['spec_mark_id']]['spec_name'];
                    $SpecMarkData[$key]['spec_value'] = $spec_sync_value_data[$value['spec_value_id']]['spec_value'];
                }
                model('ProductSpecData')->saveAll($SpecMarkData);
            }
            /* END */

            $this->success('更新成功！');
        }

        // 查询规格数据
        $PresetData = $this->product_spec_preset_db->where('lang',$this->admin_lang)->order('sort_order asc, preset_id asc')->select();
        // 数组转化
        $ResultData = $this->ProductSpecLogic->GetPresetData($PresetData);
        // 获取预设规格中最大的标记MarkId
        $PresetMarkId = model('ProductSpecPreset')->GetMaxPresetMarkId();
        // 加载参数
        $this->assign('info', $ResultData);
        $this->assign('PresetMarkId', $PresetMarkId);
        return $this->fetch('spec_index');
    }


    // 规格列表管理，包含新增、更新
    public function spec_template()
    {
        if (IS_AJAX_POST) {
            // 新增、更新
            $post = input('post.');
            // 当前时间戳
            $time = getTime();
            /*新增数据处理*/
            $post_new = [];
            foreach ($post['preset_new'] as $key => $value) {
                // 规格名称不允许为空
                $preset_name = $post['preset_name_'.$value][0];
                if (empty($preset_name)) continue;
                // 是否同步到已发布的商品规格
                $spec_sync = !empty($post['spec_sync_'.$value]) ? 1 : 0;
                // 排序号
                $sort_order  = $post['sort_order_'.$value];

                // 拼装三维数组
                foreach ($post['preset_value_'.$value] as $kk => $vv) {
                    if (empty($vv)) continue;
                    $post_new[$key][$kk]['preset_mark_id'] = $value; // 标记ID，一整条规格信息中的唯一标识
                    $post_new[$key][$kk]['preset_name']    = $preset_name;
                    $post_new[$key][$kk]['preset_value']   = $vv;
                    $post_new[$key][$kk]['spec_sync']      = $spec_sync;
                    $post_new[$key][$kk]['sort_order']     = $sort_order;
                    $post_new[$key][$kk]['lang']           = $this->admin_lang;
                    $post_new[$key][$kk]['add_time']       = $time;
                    $post_new[$key][$kk]['update_time']    = $time;
                }
            }

            // 三维数组降为二维数组
            $data_new = $this->ProductSpecLogic->ArrayDowngrade($post_new);
            /* END */

            /*原有数据处理*/
            $post_old = [];
            $spec_sync_where = $spec_sync_mark_data = $spec_sync_value_data = [];
            foreach ($post['preset_old'] as $key => $value) {
                // 规格名称不允许为空
                $preset_name = $post['preset_name_old_'.$value][0];
                if (empty($preset_name)) continue;

                // 是否同步到已发布的商品规格
                $spec_sync = !empty($post['spec_sync_'.$value]) ? 1 : 0;

                // 排序号
                $sort_order  = $post['sort_order_'.$value];

                // 需要同步的规格名称
                if (!empty($spec_sync)) {
                    array_push($spec_sync_where, $value);
                    $spec_sync_mark_data[$value]['spec_name'] = $preset_name;
                }

                // 拼装三维数组
                foreach ($post['preset_value_old_'.$value] as $kk => $vv) {
                    if (empty($vv)) continue;
                    $preset_id = $post['preset_id_old_'.$value][$kk];
                    // 如果ID是否为空
                    if (!empty($preset_id)) {
                        // 有ID则为更新
                        $post_old[$key][$kk]['preset_id'] = $preset_id;

                        // 需要同步的规格值
                        if (!empty($spec_sync)) $spec_sync_value_data[$preset_id]['spec_value'] = $vv;
                    } else {
                        // 无ID则为新增
                        $post_old[$key][$kk]['lang']     = $this->admin_lang;
                        $post_old[$key][$kk]['add_time'] = $time;
                        $post_old[$key][$kk]['preset_mark_id'] = $value; // 标记ID，一整条规格信息中的唯一标识
                    }
                    $post_old[$key][$kk]['preset_name']  = $preset_name;
                    $post_old[$key][$kk]['preset_value'] = $vv;
                    $post_old[$key][$kk]['spec_sync']    = $spec_sync;
                    $post_old[$key][$kk]['sort_order']   = $sort_order;
                    $post_old[$key][$kk]['update_time']  = $time;
                }
            }
            // 三维数组降为二维数组
            $data_old = $this->ProductSpecLogic->ArrayDowngrade($post_old);
            /* END */

            // 合并数组并且更新数据
            $UpData = array_merge($data_old, $data_new);
            model('ProductSpecPreset')->saveAll($UpData);

            /*执行同步数据*/
            if (!empty($spec_sync_where)) {
                $w_1['spec_mark_id'] = ['IN', $spec_sync_where];
                $f_1 = 'spec_id, spec_mark_id, spec_name, spec_value_id, spec_value';
                $SpecMarkData = Db::name('product_spec_data')->where($w_1)->field($f_1)->order('spec_mark_id asc')->select();
                foreach ($SpecMarkData as $key => $value) {
                    $SpecMarkData[$key]['spec_name'] = $spec_sync_mark_data[$value['spec_mark_id']]['spec_name'];
                    $SpecMarkData[$key]['spec_value'] = $spec_sync_value_data[$value['spec_value_id']]['spec_value'];
                }
                model('ProductSpecData')->saveAll($SpecMarkData);
            }
            /* END */

            $this->success('更新成功！');
        }

        // 查询规格数据
        $PresetData = $this->product_spec_preset_db->where('lang',$this->admin_lang)->order('sort_order asc, preset_id asc')->select();
        // 数组转化
        $ResultData = $this->ProductSpecLogic->GetPresetData($PresetData);
        // 获取预设规格中最大的标记MarkId
        $PresetMarkId = model('ProductSpecPreset')->GetMaxPresetMarkId();
        // 加载参数
        $this->assign('info', $ResultData);
        $this->assign('PresetMarkId', $PresetMarkId);
        return $this->fetch('spec_template');
    }

    // 删除规格名称\规格值
    public function spec_delete()
    {
        if (IS_AJAX_POST) {
            $post = input('post.'); 
            $where = $this->ProductSpecLogic->GetDeleteSpecWhere($post);
            if (!empty($where)) {
                $result = $this->product_spec_preset_db->where($where)->delete();
                if (!empty($result)) {
                    $this->success('删除成功！');
                }
            }
            $this->error('删除失败！');
        }
    }

    // 选中规格名称，追加html到页面展示
    public function spec_select()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 当选中的规格大类大于等于三个则不允许再添加
            if (3 == count(session('spec_arr'))) $this->error('最多只能添加三种规格大类');

            // 获取预设规格标记ID数组
            $PresetMarkIdArray = $this->ProductSpecLogic->GetPresetMarkIdArray($post);

            // 拼装预设名称下拉选项
            if (!empty($PresetMarkIdArray)) {
                // 添加选中的规格数据
                model('ProductSpecData')->PresetSpecAddData($post);
                // 拼装更新预设名称下拉选项
                $Result = $this->ProductSpecLogic->GetPresetNameOption($PresetMarkIdArray, $post);
            } else {
                $this->error('最多只能添加三种规格大类！');
            }
            
            if (isset($post['aid']) && !empty($post['aid'])) {
                $ResultData = $this->ProductSpecLogic->GetPresetValueOption('', $post['spec_mark_id'], $post['aid'], 2);
                $PresetName = $ResultData['PresetName'];
                $PresetValueOption = $ResultData['PresetValueOption'];
            } else {
                // 拼装预设值下拉选项
                $PresetValue = $this->product_spec_preset_db->where('preset_mark_id','IN',$post['preset_mark_id'])->field('preset_id,preset_name,preset_value')->select();
                $PresetName = $PresetValue[0]['preset_name'];
                $PresetValueOption = $this->ProductSpecLogic->GetPresetValueOption($PresetValue);
            }

            if (isset($post['aid']) && !empty($post['aid'])) {
                // 结果返回
                $ReturnHtml = [
                    'preset_name'         => $PresetName,
                    'preset_name_option'  => $Result['Option'],
                    'spec_mark_id_arr'    => $Result['MarkId'],
                    'preset_value_option' => $PresetValueOption,
                ];
            } else {
                // 结果返回
                $ReturnHtml = [
                    'preset_name'         => $PresetName,
                    'preset_name_option'  => $Result['Option'],
                    'preset_mark_id_arr'  => $Result['MarkId'],
                    'preset_value_option' => $PresetValueOption,
                ];
            }
            $this->success('加载成功！', null, $ReturnHtml);
        }
    }

    // 当规格库更新后，调用此方式及时更新选择预设规格的下拉框信息及规格框信息
    public function update_spec_info()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 拼装更新预设名称下拉选项
            $ResultData = $this->ProductSpecLogic->GetPresetNameOption($post['preset_mark_id_arr']);

            // 获取规格拼装后的html表格
            $ResultArray = $this->ProductSpecLogic->GetPresetSpecAssembly($post);

            // 结果返回
            if (isset($post['aid']) && !empty($post['aid'])) {
                $ReturnHtml = [
                    'HtmlTable' => $ResultArray['HtmlTable'],
                    'spec_name_option' => $ResultData['Option'],
                    'spec_mark_id_arr' => $ResultArray['PresetMarkIdArray'],
                ];
            }else{
                // 拼装更新预设名称下拉选项
                $where = [
                    'preset_mark_id' => ['IN', $post['preset_mark_id_arr']],
                ];
                $PresetData = $this->product_spec_preset_db->where($where)->order('preset_id asc')->select();
                $sessionData = session('spec_arr');
                foreach ($PresetData as $key => $value) {
                    if (!empty($sessionData[$value['preset_mark_id']])) {
                        if (in_array($value['preset_id'], $sessionData[$value['preset_mark_id']])) {
                            unset($PresetData[$key]);
                        }
                    }
                }

                $PresetData = group_same_key($PresetData, 'preset_mark_id');
                $result = [];
                foreach ($PresetData as $key => $value) {
                    $result[$key] .= "<option value='0'>选择规格值</option>";
                    if(!empty($value)){
                        foreach($value as $sub_value){
                            $result[$key] .= "<option value='{$sub_value['preset_id']}'>{$sub_value['preset_value']}</option>";
                        }
                    }
                }
                
                $ReturnHtml = [
                    'HtmlTable' => $ResultArray['HtmlTable'],
                    'preset_name_option' => $ResultData['Option'],
                    'preset_mark_id_arr' => $ResultArray['PresetMarkIdArray'],
                    'preset_value_id'     => explode(',', $post['preset_mark_id_arr']),
                    'preset_value_option' => $result,
                ];
            }
            $this->success('更新成功！', null, $ReturnHtml);
        }
    }

    // 获取或更新规格组合的数据
    // preset_id：预设值ID
    // preset_mark_id：预设参数标记ID，同一预设规格名称下的所有规格值统一使用，可理解为规格名称唯一ID。
    public function assemble_spec_data()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 刷新或重新进入产品添加页则清除关于产品session
            if (isset($post['initialization']) && !empty($post['initialization'])) {
                session('spec_arr', null);
                $this->success('初始化完成');
            }

            // 若清除一整条规格信息则清除session中相应的数据并且重置规格名称下拉框选项
            $ResultArray = $this->ProductSpecLogic->GetResetPresetNameOption($post);

            // 删除单个规格值则清除session对应的值
            $ValueArray  = $this->ProductSpecLogic->ClearSpecValueID($post);

            // 把session中的数据和提交的数据组合
            $SpecArray = $this->ProductSpecLogic->GetSessionPostArrayMerge($post);
            if (isset($SpecArray['error']) && !empty($SpecArray['error'])) $this->error($SpecArray['error']);

            // 获取规格拼装后的html表格
            if (isset($post['aid']) && !empty($post['aid'])) {
                // 编辑
                $HtmlTable = $this->ProductSpecLogic->SpecAssemblyEdit($SpecArray, $post['aid']);
            } else {
                // 新增
                $HtmlTable = $this->ProductSpecLogic->SpecAssembly($SpecArray);
            }

            if (!empty($ValueArray['Option'])) {
                // 删除规格值后的规格值下拉框
                $PresetValueOption = $ValueArray['Option'];
                $ResultValue['Value'] = null;
            } else {
                $ResultValue = model('ProductSpecPreset')->GetPresetNewData(session('spec_arr'), $post);
                // 获取新增规格值后的下拉框
                if (empty($post['aid'])) {
                    $PresetValueOption = $this->ProductSpecLogic->GetPresetValueOption($ResultValue['Option']);
                } else {
                    $PresetValueOption = $ResultValue['Option'];
                }
            }

            // 返回数据
            $ReturnData = [
                'HtmlTable' => $HtmlTable,
                'PresetNameOption' => $ResultArray['Option'],
                'PresetMarkIdArray' => $ResultArray['MarkId'],
                'SelectPresetValue' => $ResultValue['Value'],
                'PresetValueOption' => $PresetValueOption,
            ];
            $this->success('加载成功！', null, $ReturnData);
        }
    }

    // 同步规格值到产品规格中，刷新规格值下拉框
    public function refresh_spec_value()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 是否提交完整数据
            if (empty($post['spec_mark_id']) || empty($post['aid'])) $this->error('数据有误，同步失败，请刷新重试！');

            /*查询产品已选规格数据*/
            $where = [
                'aid' => $post['aid'],
                'spec_mark_id' => $post['spec_mark_id'],
            ];
            $SpecData = Db::name('product_spec_data')->where($where)->order('spec_value_id asc')->select();
            // 以规格值ID为键名
            $SpecData = group_same_key($SpecData, 'spec_value_id');
            /* END */

            /*查询规格库数据*/
            $where = [
                'preset_mark_id' => $post['spec_mark_id'],
            ];
            $PresetData = $this->product_spec_preset_db->where($where)->order('preset_id asc')->select();
            /* END */

            // 初始化数组
            $AddData = $UpData = $SpecIds = $UpSpecWhere = $UpSpecName = [];
            // 数据处理
            foreach ($PresetData as $pd_k => $pd_v) {
                if (!empty($SpecData[$pd_v['preset_id']]) && $pd_v['preset_name'] != $SpecData[$pd_v['preset_id']][0]['spec_name']) {
                    // 更新规格名称数组
                    if (empty($UpSpecWhere) && empty($UpSpecName)) {
                        $UpSpecWhere = [
                            'aid'          => $post['aid'],
                            'spec_mark_id' => $pd_v['preset_mark_id'],
                        ];
                        $UpSpecName = [
                            'spec_name' => $pd_v['preset_name'],
                        ];
                    }
                }

                if (empty($SpecData[$pd_v['preset_id']])) {
                    // 添加规格值数据
                    $AddData[] = [
                        'aid'            => $post['aid'],
                        'spec_mark_id'   => $pd_v['preset_mark_id'],
                        'spec_name'      => $pd_v['preset_name'],
                        'spec_value_id'  => $pd_v['preset_id'],
                        'spec_value'     => $pd_v['preset_value'],
                        'spec_is_select' => 0,
                        'lang'           => $this->admin_lang,
                        'add_time'       => getTime(),
                        'update_time'    => getTime(),
                    ];
                } else if (!empty($SpecData[$pd_v['preset_id']]) && $pd_v['preset_value'] != $SpecData[$pd_v['preset_id']][0]['spec_value']) {
                    // 更新规格值数据
                    $UpData[] = [
                        'spec_id'        => $SpecData[$pd_v['preset_id']][0]['spec_id'],
                        'spec_mark_id'   => $pd_v['preset_mark_id'],
                        'spec_name'      => $pd_v['preset_name'],
                        'spec_value_id'  => $pd_v['preset_id'],
                        'spec_value'     => $pd_v['preset_value'],
                        'update_time'    => getTime(),
                    ];
                    // 删除产品已选规格表中对应需要更新的规格值数据
                    unset($SpecData[$pd_v['preset_id']]);
                } else {
                    // 删除规格库中不存在的规格值数据
                    unset($SpecData[$pd_v['preset_id']]);
                }
            }

            // 合并添加、编辑数据，统一处理
            $SaveData = array_merge($AddData, $UpData);
            
            /*处理需要删除的规格值数据*/
            if (!empty($SpecData)) {
                $DelIsSelect = 0;
                foreach ($SpecData as $key => $value) {
                    $SpecIds[] = $value[0]['spec_id'];
                    if (1 == $value[0]['spec_is_select']) {
                        $DelIsSelect = 1;
                    }
                    $spec_mark_id = $value[0]['spec_mark_id'];
                }
            }
            /* END */

            $HtmlTable = $SpecMarks = '';
            if (!empty($SpecIds)) {
                // 删除废弃的规格值数据
                Db::name('product_spec_data')->where('spec_id', 'IN', $SpecIds)->delete();
                if (1 == $DelIsSelect) {
                    session('spec_arr', null);
                    $SpecWhere = [
                        'aid' => $post['aid'],
                        'lang' => $this->admin_lang,
                        'spec_is_select' => 1,// 已选中的
                    ];
                    $order = 'spec_value_id asc, spec_id asc';
                    $product_spec_data = Db::name('product_spec_data')->where($SpecWhere)->order($order)->select();
                    if (!empty($product_spec_data)) {
                        $spec_arr_new = group_same_key($product_spec_data, 'spec_mark_id');
                        $DelAllSpec = $spec_mark_id;
                        foreach ($spec_arr_new as $key => $value) {
                            $spec_mark_id_arr[] = $key;
                            for ($i=0; $i<count($value); $i++) {
                                $spec_arr_new[$key][$i] = $value[$i]['spec_value_id'];
                            }
                            if ($spec_mark_id == $key) {
                                $DelAllSpec = 0;
                            }
                        }

                        session('spec_arr', $spec_arr_new);
                        $HtmlTable = $this->ProductSpecLogic->SpecAssemblyEdit($spec_arr_new, $post['aid']);
                        $SpecMarks = implode(',', $spec_mark_id_arr);
                    }
                }
            }

            if (!empty($SaveData)) {
                // 批量保存更新新规格
                model('ProductSpecData')->saveAll($SaveData);
            }

            if (!empty($UpSpecWhere) && !empty($UpSpecName)) {
                // 更新当前产品下对应的规格名称
                Db::name('product_spec_data')->where($UpSpecWhere)->update($UpSpecName);
                if (empty($UpData)) {
                    $UpData[0] = [
                        'spec_name' => $UpSpecName['spec_name'],
                        'spec_mark_id' => $UpSpecWhere['spec_mark_id']
                    ];
                }
            }

            $ValueOption = $this->ProductSpecLogic->GetPresetValueOption('', $post['spec_mark_id'], $post['aid']);
            $ResultData = [
                'UpData'      => $UpData,
                'SpecIds'     => $SpecIds,
                'HtmlTable'   => $HtmlTable,
                'SpecMarks'   => $SpecMarks,
                'DelAllSpec'  => $DelAllSpec,
                'ValueOption' => $ValueOption,
            ];
            $this->success('同步成功，规格值已刷新！', null, $ResultData);
        }
    }

    // 新增产品时更新同步规格数据
    public function refresh_preset_value()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post)) {
                $HtmlTable = $DelAllPreset = $PresetData = $MarkData = '';
                if ((isset($post['mark_mark_ids']) && !empty($post['mark_mark_ids'])) || (isset($post['mark_preset_ids']) && !empty($post['mark_preset_ids']))) {
                    if (!empty($post['mark_mark_ids'])) {
                        $MarkData = $this->product_spec_preset_db->where('preset_mark_id', 'IN', $post['mark_mark_ids'])->field('preset_mark_id, preset_name')->select();
                    }
                    if (!empty($post['mark_preset_ids'])) {
                        $PresetData = $this->product_spec_preset_db->where('preset_id', 'IN', $post['mark_preset_ids'])->field('preset_id, preset_value')->select();
                    }
                } else {
                    $DelAllPreset = 0;
                    $spec_arr_ses = session('spec_arr');
                    foreach ($spec_arr_ses[$post['preset_mark_id']] as $key => $value) {
                        if ($value == $post['preset_id']) {
                            unset($spec_arr_ses[$post['preset_mark_id']][$key]);
                        }
                    }
                    if (empty($spec_arr_ses[$post['preset_mark_id']])) {
                        unset($spec_arr_ses[$post['preset_mark_id']]);
                        $count = $this->product_spec_preset_db->where('preset_mark_id', $post['preset_mark_id'])->count();
                        if (empty($count)) {
                            $DelAllPreset = 1;
                        }
                    }
                    session('spec_arr',$spec_arr_ses);
                    $HtmlTable = $this->ProductSpecLogic->SpecAssembly($spec_arr_ses);
                }

                $ResultData = [
                    'MarkData'     => $MarkData,
                    'HtmlTable'    => $HtmlTable,
                    'PresetData'   => $PresetData,
                    'DelAllPreset' => $DelAllPreset,
                ];
                $this->success('同步成功！', null, $ResultData);
            }
        }
    }

    // 检查是否最新的购物车标签
    public function VerifyLatestTemplate()
    {
        // 验证最新模板
        $ResultData = VerifyLatestTemplate();
        if (empty($ResultData)) {
            // 更新开启多规格
            getUsersConfigData('shop', ['shop_open_spec' => 1]);
            // 返回提示
            $this->success('模板检测通过，规格已开启！');
        }else{
            if (5 == count($ResultData)) {
                $msg = '未检测到规格标签，请根据提示手工调用后再重新验证！';
            }else{
                $msg = '规格标签缺少变量：<br/><span style="color: red;">'.implode('， ', $ResultData).'</span><br/>请检查模板核实后再次验证！';
            }
            // 更新关闭多规格
            getUsersConfigData('shop', ['shop_open_spec' => 0]);
            // 返回提示
            $this->error($msg);
        }
    }

    // 暂时已废弃，暂时不清理删除代码
    public function FindSmptConfig() {
        $Smtp = tpCache('smtp');
        if (empty($Smtp['smtp_server']) || empty($Smtp['smtp_port']) || empty($Smtp['smtp_user']) || empty($Smtp['smtp_pwd']) || empty($Smtp['smtp_from_eamil'])) {
            $this->error('邮箱配置尚未配置完成，前往配置？', url('System/api_conf'));
        } else {
            // tpCache('smtp', [input('post.field') => 1]);
            $this->success('配置完成');
        }
    }

    // 未付款订单改价
    public function order_change_price()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['order_id']) || empty($post['order_amount'])) $this->error('操作错误，刷新重试');
            // 更新条件
            $where = [
                'order_id' => $post['order_id'],
                'order_status' => 0
            ];
            // 更新数据
            $Code = date('Ymd') . getTime() . rand(10, 100);
            $update = [
                'order_code' => $Code,
                'update_time' => getTime(),
                'order_amount' => $post['order_amount']
            ];
            // 更新操作
            $ResultID = $this->shop_order_db->where($where)->update($update);
            // 更新后续操作
            if (!empty($ResultID)) {
                // 添加订单的操作记录
                $action_note = '应付金额改为：' . $post['order_amount'] . '，原价：' . $post['order_amount_old'];
                AddOrderAction($post['order_id'], 0, session('admin_id'), 0, 0, 0, '商家改价！', $action_note);
                $this->success('改价完成');
            } else {
                $this->error('改价失败，刷新重试');
            }
        }
    }

    /**
     * 营销功能
     * @return [type] [description]
     */
    public function market_index()
    {
        $assign_data = array();
        $this->myShopWeapp($assign_data);
        // 应用市场显示与否
        $assign_data['weapp_plugin_open'] = $this->get_weapp_plugin_open();
        // 渲染变量
        $this->assign($assign_data);
        // 渲染模板
        return $this->fetch();
    }

    /**
     * 我的应用
     * @param  array  &$assign_data [description]
     * @return [type]               [description]
     */
    private function myShopWeapp(&$assign_data = [])
    {
        $condition   = array();
        $condition['a.is_buy'] = ['=',0];
        /*权限控制 by 小虎哥*/
        $pluginsList = [];
        if (0 < intval(session('admin_info.role_id'))) {
            $auth_role_info = session('admin_info.auth_role_info');
            if (!empty($auth_role_info)) {
                if (!empty($auth_role_info['permission']['plugins'])) {
                    foreach ($auth_role_info['permission']['plugins'] as $plugins) {
                        if (isset($plugins['code'])) {
                            $pluginsList[] = $plugins['code'];
                        }
                    }
                }
            }
            if (!empty($pluginsList)) {
                $condition['a.code'] = array('in', $pluginsList);
            }
        }
        /*--end*/

        $weappArr = array(); // 插件标识数组

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = DB::name('weapp')->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $Page  = new Page($count, 100);// 实例化分页类 传入总记录数和每页显示的记录数
        $list  = DB::name('weapp')
            ->field('a.*')
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('id');
        foreach ($list as $key => $val) {
            if (!isset($val['is_buy'])) $val['is_buy'] = 0;
            $config = [];
            if ($val['is_buy'] == 0) {
                if (file_exists(WEAPP_PATH . $val['code'] . DS . 'config.php')) {
                    $config                = include WEAPP_PATH . $val['code'] . DS . 'config.php';
                    $config['description'] = filter_line_return($config['description'], '<br/>');
                    $val['version']        = getWeappVersion($val['code']);
                }
            }else if ($val['is_buy'] == 1){
                $config = json_decode($val['config'],true);
            }
            $config['litpic']      = !empty($config['litpic']) ? get_default_pic($config['litpic']) : get_default_pic();
            $val['config']         = $config;

            if (!empty($config['management']['href'])) {
                $val['href'] = $config['management']['href'];
                $val['target'] = empty($config['management']['target']) ? '_self' : $config['management']['target'];
            } else {
                $val['href'] = url('Weapp/execute',array('sm'=>$val['code'],'sc'=>$val['code'],'sa'=>'index'));
                $val['target'] = '_self';
            }

            switch ($val['status']) {
                case '-1':
                    $status_text = '禁用';
                    break;

                case '1':
                    $status_text = '启用';
                    break;

                default:
                    $status_text = '未安装';
                    break;
            }
            $val['status_text'] = $status_text;

            $list[$key] = $val;

            /*插件标识数组*/
            $weappArr[$val['code']] = array(
                'code'    => $val['code'],
                'version' => $val['version'],
            );
            /*--end*/
        }

        // 应用渠道
        try {
            $post_data = [
                'codeList' => get_arr_column($weappArr, 'code'),
            ];
            $upgradeLogic = new \app\admin\logic\UpgradeLogic;
            $upgradeLogic->GetKeyData($post_data);
            $url       = 'http://plugins.eyoucms.com/user/ajax_memberplugin.php?action=myshop_weapp';
            $response  = httpRequest2($url, 'POST', $post_data);
            $params    = json_decode($response, true);
            if (!empty($params['code']) && !empty($params['list'])) {
                foreach ($list as $key => $val) {
                    if (empty($params['list'][$val['code']])) {
                        unset($list[$key]);
                        continue;
                    }
                    $val['apply_channels'] = empty($params['list'][$val['code']]['apply_channels']) ? '' : "应用于".str_replace(',', '、', $params['list'][$val['code']]['apply_channels']);
                    $list[$key] = $val;
                }
            } else {
                $list = [];
            }
        } catch (\Exception $e) {
            
        }

        $show                 = $Page->show(); // 分页显示输出
        $assign_data['myShopPage']  = $show; // 赋值分页输出
        $assign_data['myShopList']  = $list; // 赋值数据集
        $assign_data['myShopPager'] = $Page; // 赋值分页对象
    }

    /**
     * 应用市场
     * @return [type]               [description]
     */
    public function pluginShopWeapp()
    {
        $is_pay    = input('param.is_pay/d', 0);
        $keywords  = input('param.keywords/s', 0);
        $url       = 'http://plugins.eyoucms.com/user/ajax_memberplugin.php?action=plugin';
        $post_data = [
            'page'      => input('param.p/d', 1),
            'is_pay'    => $is_pay,
            'keywords'  => $keywords,
            'query_str' => input('param.'),
            'pid'   => $this->php_servicemeal,
            'diy_cjfl' => '商城应用',
        ];
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        $upgradeLogic->GetKeyData($post_data);
        $response  = httpRequest2($url, 'POST', $post_data);
        $params    = json_decode($response, true);
        if (empty($params['code'])) {
            $msg = !empty($params['msg']) ? $params['msg'] : '连接远程插件接口失败！';
            $this->error($msg);
        }
        $html = "";
        $weappArr = array(); // 插件标识数组
        $local = Db::name('weapp')->getAllWithIndex('code');
        $list = $params['list'];
        foreach ($list as $key =>$val){
            $config = [];
            if (file_exists(WEAPP_PATH . $val['weapp_code'] . DS . 'config.php')) {
                $config                = include WEAPP_PATH . $val['weapp_code'] . DS . 'config.php';
                $config['description'] = filter_line_return($config['description'], '<br/>');
                $val['version']        = getWeappVersion($val['weapp_code']);
                $config['litpic']      = !empty($config['litpic']) ? get_default_pic($config['litpic']) : get_default_pic();
            }
            $val['config']         = $config;

            if ($val['meal']){
                $val['meal'] = unserialize($val['meal']);
            }
            $val['install'] = 0;
            if (!empty($local[$val['weapp_code']])) {
                $val['status'] = $local[$val['weapp_code']]['status'];
                if (1 == $local[$val['weapp_code']]['status']) {
                    $val['install']=1;
                }
            }
            $val['litpic'] = get_default_pic($val['litpic']);
            $val['apply_channels'] = empty($val['apply_channels']) ? '' : "应用于".str_replace(',', '、', $val['apply_channels']);

            if (!empty($val['config']['management']['href'])) {
                $val['href'] = $val['config']['management']['href'];
                $val['target'] = empty($val['config']['management']['target']) ? '_self' : $val['config']['management']['target'];
            } else {
                $val['href'] = url('Weapp/execute',array('sm'=>$val['weapp_code'],'sc'=>$val['weapp_code'],'sa'=>'index'));
                $val['target'] = '_self';
            }

            $list[$key] = $val;

            /*插件标识数组*/
            $weappArr[$val['weapp_code']] = array(
                'code'    => $val['weapp_code'],
                'version' => $val['version'],
            );
            /*--end*/
        }

        /*检测更新*/
        $weapp_upgrade = array();
        if (!empty($weappArr)) {
            // 标识
            $codeArr = get_arr_column($weappArr, 'code');
            $codeStr = implode(',', $codeArr);
            // 版本号
            $versionArr = get_arr_column($weappArr, 'version');
            $versionStr = implode(',', $versionArr);
            // URL参数
            $values        = array(
                'domain' => request()->host(true),
                'ip'    => serverIP(),
                'code'   => $codeStr,
                'v'      => $versionStr,
                'pid'   => $this->php_servicemeal,
                // 'dev'   => 1,
            );
            tpCache('system', ['system_usecodelist'=>'']);
            $upgradeLogic->GetKeyData($values);
            $url = $upgradeLogic->getServiceUrl(true, 'weapp').'/index.php?m=api&c=Weapp&a=checkBatchVersion';
            $response = @httpRequest($url, 'POST', $values, [], 5);
            $batch_upgrade = json_decode($response, true);
            if (is_array($batch_upgrade)) {
                if (!empty($batch_upgrade)) {
                    tpSetting('system', ['system_codelogic_1638857408'=>$batch_upgrade['Sample']]);
                    $weappLogic = new \app\admin\logic\WeappLogic;
                    $weapp_upgrade = $weappLogic->checkBatchVersion($batch_upgrade); //升级包消息 
                }
            }
        }
        /*--end*/

        $cms_version = getCmsVersion();
        foreach ($list as $key =>$val){
            $apply_channels = $val['apply_channels'];
            if (0 == $val['install']) {
                if ('v'.$val['min_version'] > $cms_version) {
                    $apply_channels = "<a class='red'>当前CMS版本太低，请升级系统！</a>";
                }
            }
            else if (1 == $val['install'] && !empty($weapp_upgrade[$val['weapp_code']])) {
                $weapp_upgrade_info = $weapp_upgrade[$val['weapp_code']];
                if ($weapp_upgrade_info['code'] == 2) {
                    $msg_upgrade = empty($weapp_upgrade_info['msg']['upgrade']) ? '' : $weapp_upgrade_info['msg']['upgrade'];
                    $msg_intro = empty($weapp_upgrade_info['msg']['intro']) ? '' : $weapp_upgrade_info['msg']['intro'];
                    $msg_notice = empty($weapp_upgrade_info['msg']['notice']) ? '' : $weapp_upgrade_info['msg']['notice'];
                    $msg_tips = '当前插件有新版本，请升级！'; //empty($weapp_upgrade_info['msg']['tips']) ? '[新版本更新]' : $weapp_upgrade_info['msg']['tips'];
                    $apply_channels =<<<EOF
<textarea id="{$val['weapp_code']}_upgrade" class="none">{$msg_upgrade}</textarea> 
<textarea id="{$val['weapp_code']}_intro" class="none">{$msg_intro}</textarea>
<textarea id="{$val['weapp_code']}_notice" class="none">{$msg_notice}</textarea>
<a href="javascript:void(0);" class="a_upgrade red" data-version="{$val['version']}" data-code="{$val['weapp_code']}" data-status="{$val['status']}" data-name="{$val['name']}" onclick="weapp_upgrade(this);">{$msg_tips}</a>
EOF;
                }
            }

            $btn = "";
            if (empty($val['buy'])) {
                if (empty($val['install'])) {
                    if (empty($val['needmoney'])) {
                        $btn_txt = "安装";
                    } else {
                        $btn_txt = "购买";
                    }
                    $btn = "<a href=\"javascript:void(0);\" onclick=\"goPage(this);\" data-id=\"{$val['id']}\" data-weapp_code=\"{$val['weapp_code']}\"  data-min_version=\"{$val['min_version']}\" data-buy=\"{$val['buy']}\" data-needmoney=\"{$val['needmoney']}\">{$btn_txt}</a>";
                } else {
                    if (!empty($val['target']) && '_blank' == $val['target']) {
                        $btn = "<a href=\"{$val['href']}\" target=\"{$val['target']}\" class=\"yaz\">已安装</a>";
                    } else {
                        $btn = "<a href=\"javascript:void(0);\" data-href=\"{$val['href']}\" onclick=\"goto_url(this);\" class=\"yaz\">已安装</a>";
                    }
                }
            } else {
                if (empty($val['install'])) {
                    $btn = "<a href=\"javascript:void(0);\" onclick=\"goPage(this);\" data-id=\"{$val['id']}\"  data-weapp_code=\"{$val['weapp_code']}\"  data-min_version=\"{$val['min_version']}\" data-buy=\"{$val['buy']}\" data-needmoney=\"{$val['needmoney']}\">安装</a>";
                } else {
                    if (!empty($val['target']) && '_blank' == $val['target']) {
                        $btn = "<a href=\"{$val['href']}\" target=\"{$target}\">使用</a>";
                    } else {
                        $btn = "<a href=\"javascript:void(0);\" data-href=\"{$val['href']}\" onclick=\"goto_url(this);\" class=\"yaz\">已安装</a>";
                    }
                }
            }

            $html .=<<<EOF
<li class="">
    <div class="my">
        <div class="pic"><a href="https://www.eyoucms.com/plus/view.php?aid={$val['id']}" target="_blank"><img src="{$val['litpic']}"></a></div>
        <span>
            <h2><a href="https://www.eyoucms.com/plus/view.php?aid={$val['id']}" target="_blank">{$val['name']}</a></h2>
            <p>{$apply_channels}</p>
        </span>
        <div class="button">
            {$btn}
        </div>
    </div>
</li>
EOF;
        }
        $this->success('请求成功', null, ['html'=>$html]);
    }

    /**
     * 应用市场显示与否
     * @return [type] [description]
     */
    private function get_weapp_plugin_open()
    {
        $weapp_plugin_open = (int)tpCache('php.php_weapp_plugin_open');
        if (is_file('./data/conf/weapp_plugin_open.txt')) {
            $is_exist = @file_get_contents('./data/conf/weapp_plugin_open.txt');
            if ($is_exist !== false && empty($is_exist)) {
                $weapp_plugin_open = 0;
            }
        }

        return $weapp_plugin_open;
    }

    //会员编辑 订单数列表
    public function users_edit_order_index()
    {
        // 初始化数组和条件
        $where = [
            'a.lang' => $this->admin_lang,
        ];

        // 会员编辑专用 - 筛选
        $users_id = input('users_id/d');
        if (!empty($users_id)) {
            $where['a.users_id'] = $users_id;
            $where['a.order_status'] = 3;
        }
        // 订单号查询
        $order_code = input('order_code/s');
        if (!empty($order_code)) $where['a.order_code'] = ['LIKE', "%{$order_code}%"];

        // 分页查询
        $count = $this->shop_order_db->alias('a')->where($where)->count('order_id');
        $pageObj = new Page($count, config('paginate.list_rows'));

        // 订单主表数据查询
        $list = $this->shop_order_db->alias('a')
            ->field('a.*, b.username as u_username, b.nickname as u_nickname, b.mobile as u_mobile')
            ->where($where)
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        if (empty($list) && !empty($order_code)) {
            // 通过商品名称查询订单号
            $where_1['product_name'] = ['LIKE', "%{$order_code}%"];
            $order_ids = $this->shop_order_details_db->where($where_1)->group('order_id')->column('order_id');
            // 重新查询订单主表
            unset($where['a.order_code']);
            $where['a.order_id'] = ['IN', $order_ids];
            // 分页查询
            $count = $this->shop_order_db->alias('a')->where($where)->count('order_id');
            $pageObj = new Page($count, config('paginate.list_rows'));
            // 订单主表数据查询
            $list = $this->shop_order_db->alias('a')
                ->field('a.*, b.username as u_username, b.nickname as u_nickname, b.mobile as u_mobile')
                ->where($where)
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->order('a.order_id desc')
                ->limit($pageObj->firstRow.','.$pageObj->listRows)
                ->select();
        }

        $order_ids = [];
        $OrderReminderID = [];
        foreach ($list as $key => $value) {
            array_push($order_ids, $value['order_id']);
            if (1 == $value['order_status']) array_push($OrderReminderID, $value['order_id']);
        }

        // 处理订单详情数据
        $where = [
            'a.order_id' => ['IN', $order_ids]
        ];
        $DetailsData = $this->shop_order_details_db->alias('a')
            ->field('a.*, b.service_id, b.status')
            ->where($where)
            ->join('__SHOP_ORDER_SERVICE__ b', 'a.details_id = b.details_id', 'LEFT')
            ->order('details_id asc')
            ->select();
        $ArchivesData = get_archives_data($DetailsData, 'product_id');
        $OrderServiceStatus = Config::get('global.order_service_status');
        foreach ($DetailsData as $key => $value) {
            // 售后信息处理
            $value['service_id'] = !empty($value['service_id']) ? $value['service_id'] : 0;
            $value['status'] = !empty($value['status']) ? $value['status'] : 0;
            $value['status_name'] = !empty($value['status']) ? $OrderServiceStatus[$value['status']] : '';
            // 产品属性处理
            $value['data'] = unserialize($value['data']);
            $value['data'] = htmlspecialchars_decode(htmlspecialchars_decode($value['data']['spec_value']));
            // 组合数据
            $value['data'] = explode('<br/>', $value['data']);
            $valueData = '';
            foreach ($value['data'] as $key_1 => $value_1) {
                if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>';
            }
            $value['data'] = $valueData;
            $value['arcurl'] = get_arcurl($ArchivesData[$value['product_id']]);
            $value['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));
            $DetailsData[$key] = $value;
        }

        // 把订单详情数据植入订单数据
        $DetailsDataGroup = group_same_key($DetailsData, 'order_id');
        foreach ($list as $key => $value) {
            // 处理会员昵称
            $value['u_nickname'] = !empty($value['u_nickname']) ? $value['u_nickname'] : $value['u_username'];
            // 处理订单详情数据
            $value['Details'] = $DetailsDataGroup[$value['order_id']];
            // 商品条数
            $value['rowspan'] = count($value['Details']);
            // 添加时间
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            // 更新时间
            $value['update_time'] = date('Y-m-d H:i:s', $value['update_time']);
            // 重新赋值数据
            $list[$key] = $value;
        }

        // 分页显示输出
        $pageStr = $pageObj->show();

        // 数据加载
        $this->assign('list', $list);
        $this->assign('page', $pageStr);
        $this->assign('pager', $pageObj);

        return $this->fetch('member/edit/order_index');
    }
    //前台改变产品类型,被取消的类型的商品将会统一下架处理,这里返回受影响的商品数量
    public function get_shop_type_arc()
    {
        $cancel_type = input('param.cancel_type/s');
        $cancel_type = explode(',',$cancel_type);
        $where_prom_type = [];
        if (in_array(0,$cancel_type)) $where_prom_type[] = 0;
        if (in_array(1,$cancel_type)) {
            $where_prom_type[] = 1;
            $where_prom_type[] = 2;
            $where_prom_type[] = 3;
        }
        if (in_array(2,$cancel_type)) $where_prom_type[] = 4;
        $where['channel'] = 2;
        $where['is_del'] = 0;
        $where['arcrank'] = ['>',-1];
        $where['prom_type'] = ['in',$where_prom_type];
        $count = Db::name('archives')->where($where)->count();
        $this->success('success','',$count);
    }

    // 订单确认核销
    public function verify()
    {
        if (IS_AJAX_POST && !empty($this->weappInfo)) {
            // 开启核销插件则执行
            if (!empty($this->weappInfo['status']) && 1 === intval($this->weappInfo['status'])) {
                // 调用核销逻辑层方法
                $post = input('post.');
                $verifyLogic = new \weapp\Verify\logic\VerifyLogic;
                $verifyLogic->verifyOrderConfirmHandle($post, $this->weappInfo);
            }
            $this->error('请先开启核销插件');
        }
        $this->error('请先安装核销插件');
    }

    public function shopOrderPreHandle()
    {
        if (IS_AJAX_POST) {
            model('OrderPreHandle')->eyou_shopOrderPreHandle(0, getUsersConfigData('all'), 'admin');
            $this->success('订单处理完成');
        }
    }

    public function push_shipping()
    {
        if (IS_AJAX_POST) {
            $order_code = input('post.order_code/s');
            if (empty($order_code)) $this->error('缺少必要参数!');
            $users_id = input('post.users_id/d');
            $source = input('post.source/d');
            $action = input('post.action/s');
            if ('mark' == $action){
                $r = model('ShopPublicHandle')->updateWxShippingInfo($users_id, $order_code,$source,0,'手动发货');
                if (false !== $r){
                    $this->success('标记手动发货成功!');
                }else{
                    $this->error('标记手动发货失败');
                }
            }elseif ('retry' == $action){
                $item_desc = '';
                $where['order_code'] = $order_code;
                $where['users_id'] = $users_id;
                if (2 == $source){
                    $order = Db::name('shop_order')->where($where)->find();
                }elseif (1 == $source){
                    $item_desc = '充值';
                    $order['order_code'] = $order_code;
                    $order['users_id'] = $users_id;
                }elseif (3 == $source){
                    $item_desc = '会员升级';
                    $order['order_code'] = $order_code;
                    $order['users_id'] = $users_id;
                }elseif (8 == $source){
                    $order = Db::name('media_order')->where($where)->find();
                    $item_desc = $order['product_name'];
                }elseif (9 == $source){
                    $order = Db::name('article_order')->where($where)->find();
                    $item_desc = $order['product_name'];
                }elseif (10 == $source){
                    $order = Db::name('download_order')->where($where)->find();
                    $item_desc = $order['product_name'];
                }
                $WxPayOrderLogic = new \app\common\logic\WxPayOrderLogic();
                $WxPayOrderLogic->minipro_send_goods($order,$source,$item_desc);
                $where['order_source'] = $source;
                $return = Db::name('wx_shipping_info')->where($where)->find();
                if (!empty($return)){
                    if (empty($return['errcode'])){
                        $this->success($return['errmsg']);
                    } else{
                        $this->error($return['errmsg']);
                    }
                }else{
                    $this->error('该订单不在推送范围内');
                }
            }
        }
        $this->error('请求错误!');
    }
}