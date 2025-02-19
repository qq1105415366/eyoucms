<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 易而优团队 by 陈风任 <491085389@qq.com>
 * Date: 2021-01-14
 */

namespace app\admin\model;
use think\Model;
use think\Page;
use think\Config;
use think\Db;
use app\common\logic\ShopCommonLogic;

/**
 * 商品退换货服务数据模型
 */

load_trait('controller/Jump');
class ShopOrderService extends Model
{
    use \traits\controller\Jump;

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        // 会员表
        $this->users_db = Db::name('users');
        // 订单主表
        $this->shop_order_db = Db::name('shop_order');
        // 订单明细表
        $this->shop_order_details_db = Db::name('shop_order_details');
        // 订单退换明细表
        $this->shop_order_service_db = Db::name('shop_order_service');
        // 订单退换服务记录表
        $this->shop_order_service_log_db = Db::name('shop_order_service_log');
        // common商城业务层，前后台共用
        $this->shop_common = new ShopCommonLogic();
    }

    // 读取所有退换货服务信息处理返回
    public function GetAllServiceInfo($param = [], $isMerchant = false)
    {   
        // 初始化数组和条件
        $Return = [];
        $where = [
            'a.status' => ['NOT IN', [8]],
            'a.merchant_id' => !empty($isMerchant) ? ['>', 0] : 0
        ];

        // 订单号查询
        $order_code = !empty($param['order_code']) ? trim($param['order_code']) : '';
        if (!empty($order_code)) $where['a.order_code|a.product_name|a.refund_code'] = array('LIKE', "%{$order_code}%");

        // 支付方式查询
        $pay_name = input('pay_name/s', '');
        $Return['pay_name'] = $pay_name;
        if (!empty($pay_name)) $where['c.pay_name'] = trim($pay_name);

        // 订单下单终端查询
        $order_terminal = input('order_terminal/d', 0);
        $Return['order_terminal'] = $order_terminal;
        if (!empty($order_terminal)) $where['c.order_terminal'] = intval($order_terminal);

        // 查询类型
        $queryStatus = input('queryStatus/d', 0);
        if (!empty($queryStatus) && 1 === intval($queryStatus)) {
            $where['a.status'] = ['IN', [1]];
        } else if (!empty($queryStatus) && 2 === intval($queryStatus)) {
            $where['a.status'] = ['IN', [2, 4, 5, 6]];
        } else if (!empty($queryStatus) && 3 === intval($queryStatus)) {
            $where['a.status'] = ['IN', [7]];
        }

        if (!empty($isMerchant)) {
            // 时间检索条件
            $add_time = input('add_time/s', '');
            if (!empty($add_time)) {
                $add_time = explode('~', $add_time);
                $begin = strtotime(rtrim($add_time[0]));
                $finish = strtotime(rtrim($add_time[1]));
                $where['a.add_time'] = ['between', "$begin, $finish"];
            }
        } else {
            // 时间检索条件
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
        }

        $count = $this->shop_order_service_db->alias('a')->join('__SHOP_ORDER_DETAILS__ b', 'a.details_id = b.details_id', 'LEFT')->join('__SHOP_ORDER__ c', 'a.order_id = c.order_id', 'LEFT')->where($where)->count('a.service_id');
        $pageObj = new Page($count, config('paginate.list_rows'));

        /*查询退换货订单信息*/
        if (!empty($isMerchant)) {
            $Service = $this->shop_order_service_db->alias('a')
                ->field('a.*, b.product_price, b.num, b.data as detailsData, c.order_status, c.order_amount, d.merchant_name')
                ->join('__SHOP_ORDER_DETAILS__ b', 'a.details_id = b.details_id', 'LEFT')
                ->join('__SHOP_ORDER__ c', 'a.order_id = c.order_id', 'LEFT')
                ->join('__WEAPP_MULTI_MERCHANT__ d', 'a.merchant_id = d.merchant_id', 'LEFT')
                ->limit($pageObj->firstRow.','.$pageObj->listRows)
                ->where($where)
                ->order('a.service_id desc')
                ->select();
        } else {
            $field = 'a.*, b.product_price, b.num, b.data as detailsData, c.order_status, c.order_amount';
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) $field .= ', c.points_shop_order';
            $Service = $this->shop_order_service_db->alias('a')
                ->field($field)
                ->join('__SHOP_ORDER_DETAILS__ b', 'a.details_id = b.details_id', 'LEFT')
                ->join('__SHOP_ORDER__ c', 'a.order_id = c.order_id', 'LEFT')
                ->limit($pageObj->firstRow.','.$pageObj->listRows)
                ->where($where)
                ->order('a.service_id desc')
                ->select();
        }
        $DetailsID = get_arr_column($Service, 'details_id');
        /* END */
        // /*查询订单数据*/
        // $field_new = 'b.details_id, b.product_price, b.num, a.shipping_fee, a.order_total_num';
        // $where_new = [
        //     'b.apply_service' => 1,
        //     'b.details_id' => ['IN', $DetailsID]
        // ];
        // $OrderData = $this->shop_order_db->alias('a')
        //     ->field($field_new)
        //     ->join('__SHOP_ORDER_DETAILS__ b', 'a.order_id = b.order_id', 'LEFT')
        //     ->where($where_new)
        //     ->getAllWithIndex('details_id');
        // /* END */
        
        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);
        // 获取商品前台URL
        $Archives = get_archives_data($Service, 'product_id');
        foreach ($Service as $key => $value) {
            $detailsData = !empty($value['detailsData']) ? unserialize($value['detailsData']) : [];
            $Service[$key]['Details'][]['pointsGoodsBuyField'] = !empty($detailsData['pointsGoodsBuyField']) ? json_decode($detailsData['pointsGoodsBuyField'], true) : [];

            $Service[$key]['handle'] = in_array($value['status'], [3, 6, 7, 8]) ? '已完成' : '处理中';
            // 添加时间
            $Service[$key]['add_time'] = date('Y-m-d H:i:s', $value['add_time']);

            // 商品封面图
            $Service[$key]['product_img'] = handle_subdir_pic(get_default_pic($value['product_img']));

            // 商品前台URL
            $Service[$key]['arcurl'] = get_arcurl($Archives[$value['product_id']]);

            // 商品规格，组合数据
            // $value['product_spec'] = !empty($value['product_spec']) ? explode('&lt;br/&gt;', $value['product_spec']) : '';
            // $value['product_spec'] = empty($value['product_spec']) && !empty($detailsData['spec_value']) ? explode('；', $detailsData['spec_value']) : '';
            // $valueData = '';
            // foreach ($value['product_spec'] as $key_1 => $value_1) {
            //     $delimiter = '';//!empty($isMobile) ? '；' : '';
            //     if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>' . $delimiter;
            // }
            // $Service[$key]['product_spec'] = $valueData;
            // $Service[$key]['product_spec'] = rtrim(trim(str_replace("&lt;br/&gt;", " || ", $value['product_spec'])), '||');
            $Service[$key]['spec_value_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($value);

            /* 计算退还金额 */
            // $DetailsData = $OrderData[$value['details_id']];
            // $product_total_price = sprintf("%.2f", $DetailsData['product_price'] * (string)$DetailsData['num']);
            // $Service[$key]['product_total_price'] = $product_total_price > 0 ? $product_total_price : $Service[$key]['refund_price'];
            
            if (1 == $value['service_type']) {
                $Service[$key]['ShippingFee'] = $Service[$key]['refund_price'] = '0.00';
            } else if (2 == $value['service_type']) {
                // 运费计算
                $ShippingFee = 0;
                $Service[$key]['ShippingFee'] = $ShippingFee;
                // if (!empty($DetailsData['shipping_fee'])) {
                //     $ShippingFee = sprintf("%.2f", ($DetailsData['shipping_fee'] / (string)$DetailsData['order_total_num']) * (string)$value['product_num']);
                //     $Service[$key]['ShippingFee'] = $ShippingFee;
                // }
                // 计算退还金额
                $ProductPrice = 0;
                if (!empty($value['product_price'])) {
                    $ProductPrice = sprintf("%.2f", ($value['product_price'] * (string)$value['num']) - $ShippingFee);
                    $Service[$key]['refund_price'] = $value['refund_price'] = $ProductPrice;
                }
            }

            $Service[$key]['actual_price'] = floatval($value['actual_price']) > 0 ? floatval($value['actual_price']) : floatval($value['refund_price']);
        }

        // 存在积分商城订单则执行
        $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
        if (!empty($weappInfo)) {
            $pointsShopOrder = !empty($Service) ? get_arr_column($Service, 'points_shop_order') : [];
            if (!empty($pointsShopOrder) && in_array(1, $pointsShopOrder)) {
                $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                $Service = $pointsShopLogic->pointsShopOrderDataHandle($Service);
                foreach ($Service as $key => $value) {
                    $Details = !empty($value['Details'][0]) ? $value['Details'][0] : [];
                    $value['product_price'] = !empty($Details['product_price']) ? $Details['product_price'] : $value['product_price'];
                    $value['refund_price'] = !empty($value['order_total_amount']) ? $value['order_total_amount'] : $value['refund_price'];
                    if (1 === intval($value['service_type']) && 1 === intval($value['points_shop_order'])) {
                        $value['refund_price'] = preg_replace('/\d+/', 0, $value['refund_price']);
                    }
                    if (isset($Details['pointsGoodsBuyField']['goodsSinglePrice']) > 0) $value['actual_price'] = floatval($Details['pointsGoodsBuyField']['goodsSinglePrice']);
                    if (isset($Details['pointsGoodsBuyField']['goodsSinglePoints']) > 0) $value['actual_points'] = intval($Details['pointsGoodsBuyField']['goodsSinglePoints']);
                    $Service[$key] = $value;
                }
            }
        }

        $Return['Service'] = $Service;
        $Return['pageObj'] = $pageObj;
        $Return['pageStr'] = $pageObj->show();

        // 售后服务 json 数据处理
        $serviceJsonArr = [];
        foreach ($Service as $key => $value) {
            $serviceJsonArr[$value['service_id']] = $value;
        }
        $Return['serviceJsonArr'] = !empty($serviceJsonArr) ? json_encode($serviceJsonArr) : [];

        return $Return;
    }

    public function GetFieldServiceInfo($service_id = null)
    {
        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);

        $Return = [];
        if (empty($service_id)) return $Return;
        
        // 退换货信息
        $Service = $this->shop_order_service_db->where('service_id', $service_id)->find();
        // 前台商品详情页URL
        $array_new = get_archives_data([$Service], 'product_id');
        $Service['arcurl'] = !empty($array_new[$Service['product_id']]) ? get_arcurl($array_new[$Service['product_id']]) : '';
        $Service['StatusName'] = Config::get('global.order_service_status')[$Service['status']];
        $Service['status_text'] = Config::get('global.order_service_status')[$Service['status']];
        $Service['TypeName'] = Config::get('global.order_service_type')[$Service['service_type']];
        $Service['service_type_text'] = Config::get('global.order_service_type')[$Service['service_type']];
        $Service['product_img'] = !empty($Service['product_img']) ? handle_subdir_pic(get_default_pic($Service['product_img'])) : '';
        $Service['product_num'] = strval($Service['product_num']);
        $Service['add_time'] = date('Y-m-d H:i:s', $Service['add_time']);
        $Service['upload_img'] = !empty($Service['upload_img']) ? explode(',', $Service['upload_img']) : [];
        $Service['audit_time'] = !empty($Service['audit_time']) ? date('Y-m-d H:i:s', $Service['audit_time']) : 0;
        $Service['update_time'] = !empty($Service['update_time']) ? date('Y-m-d H:i:s', $Service['update_time']) : 0;
        $Service['manual_time'] = !empty($Service['manual_time']) ? date('Y-m-d H:i:s', $Service['manual_time']) : 0;
        if (empty($Service['manual_time']) && 1 === intval($Service['manual_refund'])) $Service['manual_time'] = $Service['update_time'];
        $Service['users_delivery'] = !empty($Service['users_delivery']) ? unserialize($Service['users_delivery']) : [];
        $Service['admin_delivery'] = !empty($Service['admin_delivery']) ? unserialize($Service['admin_delivery']) : [];
        if (!empty($Service['admin_delivery']['time'])) $Service['admin_delivery']['time'] = date('Y-m-d H:i:s', $Service['admin_delivery']['time']);
        $Service['refund_way'] = empty($Service['refund_way']) || 0 === intval($Service['refund_way']) ? 1 : intval($Service['refund_way']);
        $Service['refund_price'] = floatval($Service['refund_price']) * floatval($Service['product_num']);
        $Service['actual_price'] = floatval($Service['actual_price']) > 0 ? floatval($Service['actual_price']) : unifyPriceHandle($Service['refund_price']);
        $Service['actual_points'] = floatval($Service['actual_points']) > 0 ? floatval($Service['actual_points']) : '';

        // 商品规格，组合数据
        // $Service['product_spec'] = explode('&lt;br/&gt;', $Service['product_spec']);
        // $valueData = '';
        // foreach ($Service['product_spec'] as $key_1 => $value_1) {
        //     $delimiter = '';//!empty($isMobile) ? '；' : '';
        //     if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>' . $delimiter;
        // }
        // $Service['product_spec'] = $valueData;

        // 查询订单数据
        $where = [
            'order_id' => $Service['order_id'],
        ];
        $Order = $this->shop_order_db->where($where)->find();
        if (empty($Order['prom_type'])) {
            $Order['prom_type_name'] = '普通订单';
        } else {
            $Order['prom_type_name'] = '虚拟订单';
        }
        if (!empty($Order['is_seckill_order'])) $Order['prom_type_name'] = '秒杀订单';
        if (!empty($Order['points_shop_order'])) $Order['prom_type_name'] = '积分订单';

        // 订单来源
        $Order['order_terminal_show'] = 'PC';
        if (!empty($Order['order_terminal']) && 2 === intval($Order['order_terminal'])) {
            $Order['order_terminal_show'] = 'H5';
        } else if (!empty($Order['order_terminal']) && 3 === intval($Order['order_terminal'])) {
            $Order['order_terminal_show'] = '微信小程序';
        } else if (!empty($Order['order_terminal']) && 9 === intval($Order['order_terminal'])) {
            $Order['order_terminal_show'] = '抖音小程序';
        } else if (!empty($Order['order_terminal']) && 12 === intval($Order['order_terminal'])) {
            $Order['order_terminal_show'] = '支付宝小程序';
        } else if (!empty($Order['order_terminal']) && 10 === intval($Order['order_terminal'])) {
            $Order['order_terminal_show'] = '百度小程序';
        }
        // 收获地址省市区县
        $Order['city'] = get_city_name($Order['city']);
        $Order['district'] = get_area_name($Order['district']);
        $Order['province'] = get_province_name($Order['province']);
        $Order['shipping_fee'] = unifyPriceHandle($Order['shipping_fee']);
        $Order['order_amount'] = unifyPriceHandle($Order['order_amount']);
        // 备注条数
        $Order['admin_note_count'] = !empty($Order['admin_note']) ? count(unserialize($Order['admin_note'])) : 0;
        // 获取订单方式名称
        $Order['pay_name_show'] = !empty($Order['pay_name']) ? Config::get('global.pay_method_arr')[$Order['pay_name']] : '微信支付';

        // 查询订单数据
        $where = [
            'order_id' => $Service['order_id'],
            'details_id' => $Service['details_id'],
            // 'apply_service' => 1
        ];
        $Details = $this->shop_order_details_db->where($where)->find();
        // 商品类型
        if (empty($Details['prom_type'])) {
            $Details['prom_type_goods'] = '实物商品';
        } else if (4 == $Details['prom_type']) {
            $Details['prom_type_goods'] = '核销商品';
        } else {
            $Details['prom_type_goods'] = '虚拟商品';
        }
        $Details['product_price'] = unifyPriceHandle($Details['product_price']);
        $Details['product_img'] = !empty($Details['litpic']) ? get_default_pic($Details['litpic']) : '';
        $Details['product_subtotal'] = unifyPriceHandle(floatval($Details['product_price']) * floatval($Details['num']));
        // 规格处理
        $detailsData = !empty($Details['data']) ? unserialize($Details['data']) : [];
        $Details['pointsGoodsBuyField'] = !empty($detailsData['pointsGoodsBuyField']) ? json_decode($detailsData['pointsGoodsBuyField'], true) : [];
        // $productSpec = !empty($detailsData['spec_value']) ? explode('；', $detailsData['spec_value']) : '';
        // $specData = '';
        // foreach ($productSpec as $value) {
        //     if (!empty($value)) $specData .= '<span>' . $value . '</span>';
        // }
        // $Details['product_spec'] = $specData;
        $Details['spec_value_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($Details);

        // 售后订单是否是归属商城订单的最后一个售后订单，1是，0否
        $Service['last_one'] = model('ShopPublicHandle')->isLastOneServiceOrder($Service['users_id'], $Service['order_id'], 1);

        // 用户发货后计算退还金额、余额
        if (5 === intval($Service['status']) || 7 === intval($Service['status']) || in_array($Service['service_type'], [2])) {
            // 订单总数
            // $Order['order_total_num'] = (string)$Order['order_total_num'];

            // 运费计算
            $ShippingFee = 0;
            $Service['ShippingFee'] = $ShippingFee;
            // if (!empty($Order['shipping_fee'])) {
            //     $ShippingFee = sprintf("%.2f", ($Order['shipping_fee'] / $Order['order_total_num']) * $Service['product_num']);
            //     $Service['ShippingFee'] = $ShippingFee;
            // }

            // 退回应付款
            $ProductPrice = 0;
            if (!empty($Details['product_price'])) {
                $ProductPrice = sprintf("%.2f", ($Details['product_price'] * $Service['product_num']) - $ShippingFee);
                $Service['refund_price'] = $ProductPrice;
            }
        } else {
            $Service['refund_price'] = '0.00';
        }
        $Service['shipping_fee']  = $ShippingFee;

        // 会员信息
        $Users = $this->users_db->find($Service['users_id']);
        $Users['nickname'] = empty($Users['nickname']) ? $Users['username'] : $Users['nickname'];
        $Users['head_pic'] = get_head_pic($Users['head_pic'], false, $Users['sex']);

        // 服务记录表信息
        $Log = $this->shop_order_service_log_db->order('log_id desc')->where('service_id', $Service['service_id'])->select();
        foreach ($Log as $key => $value) {
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            if (!empty($value['users_id'])) {
                if (intval($value['users_id']) === intval($Service['users_id'])) {
                    $value['name'] = '会员：'.$Users['nickname'];
                } else {
                    // 商家操作
                    $value['name'] = '商家：'.Db::name('users')->where('users_id', $value['users_id'])->getField('username');
                }
            } else if (!empty($value['admin_id'])) {
                $value['name'] = '商家：'.getAdminInfo(session('admin_id'))['user_name'];
            }
            $Log[$key] = $value;
        }

        // 存在积分商城订单则执行
        if (!empty($Order['points_shop_order'])) {
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                $list = !empty($Order) ? $Order : [];
                $list['Details'] = !empty($Details) ? [$Details] : [];
                $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                $pointsShopLogic->pointsShopOrderDataHandle([$list], $Order, $Details);
                $Details = !empty($Order['Details'][0]) ? $Order['Details'][0] : $Details;
                $Details['product_subtotal'] = !empty($Details['subtotal']) ? $Details['subtotal'] : $Details['product_subtotal'];
                $Service['refund_price'] = !empty($Details['subtotal']) ? $Details['subtotal'] : $Service['refund_price'];
                if (1 === intval($Service['service_type']) && 1 === intval($Order['points_shop_order'])) {
                    $Service['refund_price'] = preg_replace('/\d+/', 0, $Service['refund_price']);
                }
                if (isset($Details['pointsGoodsBuyField']['goodsSinglePrice']) > 0) $Service['actual_price'] = floatval($Details['pointsGoodsBuyField']['goodsSinglePrice']);
                if (isset($Details['pointsGoodsBuyField']['goodsSinglePoints']) > 0) $Service['actual_points'] = intval($Details['pointsGoodsBuyField']['goodsSinglePoints']);
            }
        }

        // 订单是否改价过
        $Order['is_change_price'] = model('ShopPublicHandle')->is_change_price($Service['order_id']);
        if (!empty($Order['is_change_price'])) {
            $Service['actual_price'] = $Service['refund_price'] = unifyPriceHandle($Order['order_amount']);
            if (floatval($Order['order_amount']) > floatval($Details['product_subtotal'])) {
                $Service['actual_price'] = $Service['refund_price'] = unifyPriceHandle($Details['product_subtotal']);
            }
        }

        // 返回
        $Return['Log']     = $Log;
        $Return['Users']   = $Users;
        $Return['Order']   = $Order;
        $Return['Details'] = $Details;
        $Return['Service'] = $Service;

        // 核销订单记录信息
        $weappVerifyLog = [];
        $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
        if (!empty($weappInfo)) {
            $where = [
                'users_id' => intval($Order['users_id']),
                'order_id' => intval($Order['order_id']),
            ];
            $weappVerifyLog = Db::name('weapp_verify')->where($where)->find();
            $weappVerifyLog['verify_time'] = !empty($weappVerifyLog['verify_time']) ? date('Y-m-d H:i:s', $weappVerifyLog['verify_time']) : 0;
        }
        $Return['weappVerifyLog'] = $weappVerifyLog;

        return $Return;
    }

    // 读取所有退换货服务信息处理返回
    public function GetUserAllServiceInfo($param = [])
    {
        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);

        // 初始化数组和条件
        $Return  = $where =[];
        $where['a.service_type'] = 2;
        $where['a.status'] = 7;

        // 订单号查询
        $order_code = !empty($param['order_code']) ? trim($param['order_code']) : '';
        if (!empty($order_code)) $where['a.order_code|a.product_name'] = array('LIKE', "%{$order_code}%");
        if (!empty($param['users_id'])) $where['a.users_id'] = $param['users_id'];

        $count = $this->shop_order_service_db->alias('a')->join('__SHOP_ORDER_DETAILS__ b', 'a.details_id = b.details_id', 'LEFT')->join('__SHOP_ORDER__ c', 'a.order_id = c.order_id', 'LEFT')->where($where)->count('a.service_id');
        $pageObj = new Page($count, config('paginate.list_rows'));

        // 查询退换货订单信息
        $Service = $this->shop_order_service_db->alias('a')
            ->field('a.*, b.product_price, b.num')
            ->join('__SHOP_ORDER_DETAILS__ b', 'a.details_id = b.details_id', 'LEFT')
            ->join('__SHOP_ORDER__ c', 'a.order_id = c.order_id', 'LEFT')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->where($where)
            ->order('a.service_id desc')
            ->select();
        $Archives = get_archives_data($Service, 'product_id');
        foreach ($Service as $key => $value) {
            // 添加时间
            $Service[$key]['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            // 商品封面图
            $Service[$key]['product_img'] = handle_subdir_pic(get_default_pic($value['product_img']));
            // 商品前台URL
            $Service[$key]['arcurl'] = get_arcurl($Archives[$value['product_id']]);
            // 商品规格，组合数据
            $value['product_spec'] = explode('&lt;br/&gt;', $value['product_spec']);
            $valueData = '';
            foreach ($value['product_spec'] as $key_1 => $value_1) {
                $delimiter = '';//!empty($isMobile) ? '；' : '';
                if (!empty($value_1)) $valueData .= '<span>' . trim(strrchr($value_1, '：'),'：') . '</span>' . $delimiter;
            }
            $Service[$key]['product_spec'] = $valueData;

            if (1 == $value['service_type']) {
                $Service[$key]['ShippingFee'] = $Service[$key]['refund_price'] = '0.00';
            } else if (2 == $value['service_type']) {
                // 运费计算
                $ShippingFee = 0;
                $Service[$key]['ShippingFee'] = $ShippingFee;
                // 计算退还金额
                $ProductPrice = 0;
                if (!empty($value['product_price'])) {
                    $ProductPrice = sprintf("%.2f", ($value['product_price'] * (string)$value['num']) - $ShippingFee);
                    $Service[$key]['refund_price'] = $ProductPrice;
                }
            }
        }

        $Return['Service'] = $Service;
        $Return['pageObj'] = $pageObj;
        $Return['pageStr'] = $pageObj->show();

        return $Return;
    }

    // 售后服务处理(维权订单处理)
    public function afterServiceHandle($post = [])
    {
        // 更新指定的维权订单
        $times = getTime();
        $where = [
            'users_id' => intval($post['users_id']),
            'order_id' => intval($post['order_id']),
            'details_id' => intval($post['details_id']),
            'service_id' => intval($post['service_id']),
            'product_id' => intval($post['product_id'])
        ];
        $update = [
            'update_time' => $times,
            'status' => intval($post['status'])
        ];
        // 同意申请写入审核时间
        if (in_array($post['status'], [2])) $update['audit_time'] = $times;
        // 拒接申请、关闭维权清除审核时间
        if (in_array($post['status'], [3, 8])) $update['audit_time'] = 0;
        // 存在商家发货信息则执行
        if (!empty($post['delivery'])) {
            $post['delivery']['time'] = $times;
            $update['admin_delivery'] = serialize($post['delivery']);
        }
        // 售后服务手动完成服务单并自行退款
        if (in_array($post['status'], [6, 7]) && 1 === intval($post['manual_refund'])) {
            $update['refund_note'] = trim($post['refund_note']);
            $update['manual_refund'] = intval($post['manual_refund']);
        }
        // 存在退款备注则执行
        if (!empty($post['refund_remark'])) $update['refund_remark'] = htmlspecialchars(trim($post['refund_remark']));

        // 退款方式(1:退款到余额; 2:线下退款; 3:原路退回(微信))
        if (!empty($post['refund_way'])) $update['refund_way'] = intval($post['refund_way']);

        // 微信申请退款
        $applyResult = [];
        if (!empty($post['actual_price']) && 7 === intval($post['status']) && 3 === intval($post['refund_way']) && in_array($post['service_type'], [2, 3])) {
            // 是否允许微信申请退款
            $applyResult = model('PayApi')->applyWeChatPayOrderRefund($post, 'verify');
            // 查询售后单当前状态
            $currentStatus = $this->shop_order_service_db->where($where)->getField('status');
        }

        // 执行更新
        $resultID = $this->shop_order_service_db->where($where)->update($update);
        // 更新后续操作
        if (!empty($resultID)) {
            if (!empty($applyResult['data'])) {
                // 微信申请退款
                $submitResult = model('PayApi')->applyWeChatPayOrderRefund($applyResult['data'], 'submit');
                // 后续操作
                if (!empty($submitResult['return_code']) && 'FAIL' == $submitResult['return_code']) {
                    // 更新售后单回原状态
                    $this->shop_order_service_db->where($where)->update(['status' => intval($currentStatus), 'update_time' => $times]);
                    // 申请失败提示
                    $this->error($submitResult['return_msg']);
                }
            }

            // 退款到余额
            if (7 === intval($post['status']) && 1 === intval($post['refund_way']) && in_array($post['service_type'], [2, 3])) {
                // 执行退回余额
                if (!empty($post['actual_price'])) {
                    $where = [
                        'users_id' => $post['users_id']
                    ];
                    $resultID = $this->users_db->where($where)->setInc('users_money', $post['actual_price']);
                    if (!empty($resultID)) {
                        if (empty($post['order_code'])) {
                            $post['order_code'] = $this->shop_order_db->where('order_id', $post['order_id'])->getField('order_code');
                        }
                        // 添加余额记录
                        UsersMoneyRecording($post['order_code'], $post, $post['actual_price'], '商品退款维权结束');
                    }
                }
                // 执行退回积分
                if (!empty($post['actual_points'])) {
                    $insert = [
                        'type' => 12, // 积分商城订单退回
                        'users_id' => intval($post['users_id']),
                        'score' => intval($post['actual_points']),
                        'info' => '积分商城订单退回',
                        'remark' => '积分商城订单退回',
                    ];
                    addConsumObtainScores($insert);
                }
            }

            // 拒接申请、关闭维权时更新订单商品为可申请售后
            if (in_array($post['status'], [3, 8, 9])) {
                $where = [
                    'users_id' => intval($post['users_id']),
                    'order_id' => intval($post['order_id']),
                    'details_id' => intval($post['details_id']),
                    'product_id' => intval($post['product_id'])
                ];
                $update = [
                    'apply_service' => 0,
                    'update_time' => $times
                ];
                $this->shop_order_details_db->where($where)->update($update);
            }

            // 如果维权结束且最后一个维权订单则将订单更新为已关闭
            if (in_array($post['status'], [6, 7])) {
                // 售后订单是否是归属商城订单的最后一个售后订单，1是，0否
                $lastOne = !empty($post['last_one']) ? $post['last_one'] : model('ShopPublicHandle')->isLastOneServiceOrder($post['users_id'], $post['order_id'], 0);
                if (!empty($lastOne)) {
                    $where = [
                        'users_id' => $post['users_id'],
                        'order_id' => $post['order_id'],
                    ];
                    // 待发货和待收货的订单
                    if (in_array($post['order_status'], [1, 2])) {
                        $update = [
                            'allow_service' => 1,
                            'order_status' => -1,
                            'update_time' => $times,
                        ];
                        $resultID = $this->shop_order_db->where($where)->update($update);
                        // 添加订单操作记录
                        if (!empty($resultID)) AddOrderAction($post['order_id'], 0, session('admin_id'), -1, 1, 1, '订单关闭', '维权完成，订单自动关闭');
                    }
                    // 已完成订单
                    else if (in_array($post['order_status'], [3]) || !isset($post['order_status'])) {
                        $update = [
                            'allow_service' => 1,
                            'update_time' => $times,
                        ];
                        $this->shop_order_db->where($where)->update($update);
                    }
                }
            }

            // 维权记录
            $this->shop_common->AddOrderServiceLog($post, 0);
            return true;
        } else {
            return false;
        }
    }

    public function orderActiveRefundHandle($post = [])
    {
        if (!empty($post['order_id'])) {
            $times = getTime();
            $where = [
                'users_id' => intval($post['users_id']),
                'order_id' => intval($post['order_id']),
                'order_status' => ['IN', [1, 2]]
            ];
            // 查询订单信息
            $orderData = $this->shop_order_db->where($where)->find();
            // 更新订单为已关闭
            $update = [
                'manual_refund' => 1,
                'order_status' => -1,
                'update_time' => $times,
                'refund_note' => $post['refund_remark']
            ];
            $resultID = $this->shop_order_db->where($where)->update($update);
            if (!empty($resultID)) {
                // 添加订单操作记录
                $refundWayName = !empty($orderData['points_shop_order']) && 1 === intval($orderData['points_shop_order']) ? '退款到余额(积分)' : '退款到余额';
                if (!empty($post['refund_way']) && 2 === intval($post['refund_way'])) {
                    $refundWayName = '线下退款';
                }
                else if (!empty($post['refund_way']) && 3 === intval($post['refund_way'])) {
                    $refundWayName = '原路退回(微信)';
                }
                $refundWayName = '管理员主动退款，退款方式: ' . $refundWayName;

                // 加添订单售后信息
                $result = $this->adminAddOrderService($post, $orderData, $refundWayName);
                if (empty($result)) {
                    // 更新订单回原状态
                    $update = [
                        'manual_refund' => 0,
                        'order_status' => intval($post['order_status']),
                        'update_time' => $times,
                        'refund_note' => '',
                    ];
                    $this->shop_order_db->where($where)->update($update);
                    $this->error('操作失败，刷新重试~');
                }

                // 退款到余额
                if (1 === intval($post['refund_way']) && !empty($post['actual_price'])) {
                    // 执行退回余额
                    $where = [
                        'users_id' => $post['users_id']
                    ];
                    $resultID = $this->users_db->where($where)->setInc('users_money', $post['actual_price']);
                    // 添加余额记录
                    if (!empty($resultID)) UsersMoneyRecording($post['order_code'], $post, $post['actual_price'], '管理员主动退款');
                }

                // 执行退回积分
                if (!empty($post['actual_points'])) {
                    $insert = [
                        'type' => 12, // 积分商城订单退回
                        'users_id' => intval($post['users_id']),
                        'score' => intval($post['actual_points']),
                        'info' => '管理员主动退款，积分商城订单退回',
                        'remark' => '管理员主动退款，积分商城订单退回',
                    ];
                    addConsumObtainScores($insert);
                }

                // 如果安装了秒杀插件则执行，调用秒杀逻辑层方法
                if (is_dir('./weapp/Seckill/') && !empty($orderData['is_seckill_order']) && 1 === intval($orderData['is_seckill_order'])) {
                    $seckillRow = model('Weapp')->getWeappList('Seckill');
                    if (!empty($seckillRow) && 1 === intval($seckillRow['status'])) {
                        $weappSeckillLogic = new \weapp\Seckill\logic\SeckillLogic;
                        $weappSeckillLogic->cancelOrderHandle($orderData['order_id'], $orderData['users_id']);
                    }
                }

                // 商品库存恢复
                model('OrderPreHandle')->restoreGoodsStock([$orderData]);

                // 订单操作日志
                AddOrderAction($post['order_id'], 0, session('admin_id'), -1, 0, 1, '店铺主动退款', $refundWayName);

                // 返回结束
                $this->success('操作完成');
            }
        }

        $this->error('提交的数据缺少订单ID (order_id)');
    }

    // 加添订单售后信息
    public function adminAddOrderService($post = [], $orderData = [], $refundWayName = '')
    {
        // 添加成功ID组
        $insertArr = [];
        // 当前时间戳
        $times = getTime();

        // 查询订单信息
        $city = get_city_name($orderData['city']);
        $district = get_area_name($orderData['district']);
        $province = get_province_name($orderData['province']);
        $orderData['address'] = $province . ' ' . $city . ' ' . $district . ' ' . $orderData['address'];

        // 查询订单商品信息
        $where = [
            'a.order_id' => intval($orderID),
            'a.order_id' => intval($post['order_id']),
        ];
        $field = 'a.*, b.service_id, b.status as service_status';
        $details = $this->shop_order_details_db->alias('a')->field($field)->join('__SHOP_ORDER_SERVICE__ b', 'a.details_id = b.details_id', 'LEFT')->where($where)->select();
        if (!empty($details)) {
            // 删除已申请但未完成的服务单
            $where = [
                'status' => ['NOT IN', [6, 7]],
                'order_id' => intval($post['order_id']),
                'details_id' => ['IN', get_arr_column($details, 'details_id')]
            ];
            $deleteArr = $this->shop_order_service_db->where($where)->column('service_id');
            if (!empty($deleteArr)) {
                $where = [
                    'order_id' => intval($post['order_id']),
                    'service_id' => ['IN', $deleteArr]
                ];
                $this->shop_order_service_db->where($where)->delete(true);
                $this->shop_order_service_log_db->where($where)->delete(true);
            }

            $payApiModel = model('PayApi');
            // 新增售后服务单
            foreach ($details as $key => $value) {
                if (empty($value['service_id']) || !in_array($value['service_status'], [6, 7]))  {
                    // 商品规格信息
                    $value['data'] = !empty($value['data']) ? unserialize($value['data']) : [];
                    $product_spec = '';//!empty($value['data']['spec_value']) ? htmlspecialchars_decode(htmlspecialchars_decode($value['data']['spec_value'])) : '';
                    // 订单售后信息
                    $insert = [
                        'service_type' => 2,
                        'users_id'     => intval($orderData['users_id']),
                        'merchant_id'  => intval($orderData['merchant_id']),
                        'order_id'     => intval($orderData['order_id']),
                        'order_code'   => strval($orderData['order_code']),
                        'details_id'   => intval($value['details_id']),
                        'product_id'   => intval($value['product_id']),
                        'product_name' => strval($value['product_name']),
                        'product_spec' => strval($product_spec),
                        'product_num'  => intval($value['num']),
                        'product_img'  => strval($value['litpic']),
                        'content'      => strval($refundWayName),
                        'address'      => strval($orderData['address']),
                        'consignee'    => $orderData['consignee'],
                        'mobile'       => $orderData['mobile'],
                        'manual_refund'=> 1,
                        'manual_time'  => $times,
                        'refund_remark'=> !empty($post['refund_remark']) ? $post['refund_remark'] : $refundWayName,
                        'refund_note'  => !empty($post['refund_remark']) ? $post['refund_remark'] : $refundWayName,
                        'refund_price' => unifyPriceHandle($value['product_price']),
                        'refund_way'   => !empty($post['refund_way']) ? $post['refund_way'] : 0,
                        'refund_code'  => 'TK' . $times . rand(10, 99),
                        'status'       => 7,
                        'add_time'     => $times,
                        'update_time'  => $times,
                    ];
                    // 微信申请退款
                    $applyResult = [];
                    if (!empty($insert['refund_price']) && 3 === intval($insert['refund_way'])) {
                        // 是否允许微信申请退款
                        $post['refund_code'] = $insert['refund_code'];
                        $post['actual_price'] = $insert['refund_price'];
                        $applyResult = $payApiModel->applyWeChatPayOrderRefund($post, 'verify');
                    }
                    // 新增售后单
                    $resultID = $this->shop_order_service_db->insertGetId($insert);
                    if (!empty($resultID)) {
                        // 记录添加成功的售后服务ID
                        array_push($insertArr, $resultID);
                        // 添加订单售后服务记录
                        OrderServiceLog($resultID, $orderData['order_id'], 0, session('admin_id'), $refundWayName);
                        // 微信申请退款
                        if (!empty($applyResult['data'])) {
                            $submitResult = $payApiModel->applyWeChatPayOrderRefund($applyResult['data'], 'submit');
                            // 后续操作
                            if (!empty($submitResult['return_code']) && 'FAIL' == $submitResult['return_code']) {
                                // 更新售后单回原状态
                                $where = [
                                    'service_id' => intval($resultID)
                                ];
                                $this->shop_order_service_db->where($where)->update(['status' => 2, 'update_time' => $times]);
                                // 添加订单售后服务记录
                                OrderServiceLog($resultID, $orderData['order_id'], 0, session('admin_id'), '自动申请微信退款失败，请再次执行转账操作');
                            }
                        }
                    }
                } else {
                    unset($details[$key]);
                }
            }
        }

        // 默认操作成功
        $result = true;

        // 如果添加的售后维权记录和订单商品数量不相等则执行
        if (intval(count($details)) !== intval(count($insertArr))) {
            // 设置操作失败
            $result = false;
            // 删除已添加的售后维权相关记录
            $where = [
                'order_id' => intval($post['order_id']),
                'service_id' => ['IN', $insertArr]
            ];
            $this->shop_order_service_db->where($where)->delete(true);
            $this->shop_order_service_log_db->where($where)->delete(true);
        } else {
            // 更新订单明细表中对应商品已申请售后服务
            $where = [
                'order_id' => intval($post['order_id']),
                'details_id' => ['IN', get_arr_column($details, 'details_id')]
            ];
            $update = [
                'apply_service' => 1,
                'update_time' => $times
            ];
            $this->shop_order_details_db->where($where)->update($update);
        }

        // 返回操作结果
        return $result;
    }


}
