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
 * Date: 2019-4-13
 */

namespace think\template\taglib\eyou;

use think\Config;
use think\Db;
use think\Cookie;

/**
 * 订单列表
 */
class TagSporderlist extends Base
{
    public $users_id = 0;
    public $usersTplVersion    = '';
    public $usersTpl2xVersion    = '';

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->users_id = session('users_id');
        // 是否安装核销插件
        $this->weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
        $this->usersTplVersion = getUsersTplVersion();
        $this->usersTpl2xVersion = getUsersTpl2xVersion();
    }

    /**
     * 获取订单列表数据
     */
    public function getSporderlist($pagesize = 10)
    {
        // 查询是否存在商品数量为0的订单，若存在则直接删除
        $where = [
            'a.order_total_num|b.num' => 0,
            'a.users_id' => $this->users_id
        ];
        $field = 'a.order_id, a.order_total_num, b.num';
        $orderList = Db::name('shop_order')->where($where)->field($field)->alias('a')->join('__SHOP_ORDER_DETAILS__ b', 'a.order_id = b.order_id', 'LEFT')->select();
        $orderListID = !empty($orderList) ? array_unique(get_arr_column($orderList, 'order_id')) : [];
        if (!empty($orderList) && !empty($orderListID)) {
            // 删除订单主表数据
            $where = [
                'users_id' => $this->users_id,
                'order_id' => ['IN', $orderListID]
            ];
            $deleteID = Db::name('shop_order')->where($where)->delete();
            if (!empty($deleteID)) {
                // 删除订单副表数据
                Db::name('shop_order_details')->where($where)->delete();
                // 删除订单记录数据
                Db::name('shop_order_log')->where($where)->delete();
            }
        }

        // 基础查询条件
        $where = [
            'users_id' => $this->users_id,
            'lang'     => self::$home_lang,
        ];
        // 应用搜索条件
        $keywords = input('param.keywords/s', '');
        if (!empty($keywords)) $where['order_code'] = ['LIKE', "%{$keywords}%"];
        // 订单状态搜索
        $select_status = input('param.select_status');
        if (!empty($select_status)) {
            if ('daifukuan' === $select_status) $select_status = 0;
            // if (3 === intval($select_status)) $where['is_comment'] = 0;
            $where['order_status'] = intval($select_status);
        }

        // 分页查询逻辑
        $paginate_type = isMobile() ? 'usersmobile' : 'userseyou';
        $query_get = input('get.');
        $paginate = array(
            'type'     => $paginate_type,
            'var_page' => config('paginate.var_page'),
            'query'    => $query_get,
        );
        $pages = Db::name('shop_order')->where($where)->order('add_time desc')->paginate($pagesize, false, $paginate);
        $result['list']  = $pages->items();
        $result['pages'] = $pages;
        
        // 搜索名称时，查询订单明细表商品名称
        if (empty($result['list']) && !empty($keywords)) {
            $Data = model('Shop')->QueryOrderList($pagesize, $this->users_id, $keywords, $query_get);
            $result['list']  = $Data['list'];
            $result['pages'] = $Data['pages'];
        }

        // 查询系统商品现所有规格的规格价格值ID
        $productSpecValue = Db::name('product_spec_value')->field('aid, spec_value_id')->select();
        $productSpecValue = !empty($productSpecValue) ? group_same_key($productSpecValue, 'aid') : [];
        $specValueArray = [];
        foreach ($productSpecValue as $key => $value) {
            $specValueArray[$key] = [];
            foreach ($value as $kk => $vv) {
                if (!empty($vv['spec_value_id'])) array_push($specValueArray[$key], $vv['spec_value_id']);
            }
        }

        if (!empty($result['list'])) {
            // 当前页面URL
            $ReturnUrl = request()->url(true);
            // 查询订单商品表数据
            $where = [
                'a.users_id' => intval($this->users_id),
                'a.order_id' => ['IN', get_arr_column($result['list'], 'order_id')],
            ];
            $orderDetails = Db::name('shop_order_details')->alias('a')
                ->field('a.*, b.service_id, c.is_del, d.comment_id, d.is_show')
                ->join('__SHOP_ORDER_SERVICE__ b', 'a.details_id = b.details_id && b.status NOT IN (3, 8, 9)', 'LEFT')
                ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
                ->join('__SHOP_ORDER_COMMENT__ d', 'a.details_id = d.details_id', 'LEFT')
                ->order('a.product_price desc, a.product_name desc')
                ->where($where)
                ->select();
            $orderDetails = !empty($orderDetails) ? group_same_key($orderDetails, 'order_id') : [];

            // 订单支付方式名称
            $pay_method_arr = Config::get('global.pay_method_arr');
            // 获取订单状态
            $order_status_arr = Config::get('global.order_status_arr');
            if ('v5' == $this->usersTplVersion) {
                foreach ($pay_method_arr as $key => $val) {
                    if (isset($this->pay_method_arr[$key])) {
                        $val = $this->pay_method_arr[$key];
                    }
                    $pay_method_arr[$key] = $val;
                }
                foreach ($order_status_arr as $key => $val) {
                    if (isset($this->order_status_arr[$key])) {
                        $val = $this->order_status_arr[$key];
                    }
                    $order_status_arr[$key] = $val;
                }
            }

            // 订单数据处理
            $OrderIds = $OrderIds_ = [];
            foreach ($result['list'] as $key => $value) {
                // 处理订单商品表数据
                $details = !empty($orderDetails[$value['order_id']]) ? $orderDetails[$value['order_id']] : [];
                $archives = !empty($details) ? get_archives_data($details, 'product_id') : [];
                foreach ($details as $kk => $vv) {
                    // 图片处理
                    $details[$kk]['litpic'] = handle_subdir_pic(get_default_pic($vv['litpic']));
                    // 商品评价是否已审核显示
                    $details[$kk]['is_show'] = !empty($vv['is_show']) ? intval($vv['is_show']) : 0;
                    // 商品价格格式处理
                    $details[$kk]['product_price'] = !empty($vv['product_price']) ? unifyPriceHandle($vv['product_price']) : 0;
                    // 商品售后ID
                    $details[$kk]['service_id'] = !empty($vv['service_id']) ? intval($vv['service_id']) : 0;
                    // 商品申请售后(单个商品)
                    $details[$kk]['ApplyService'] = urldecode(url('user/Shop/after_service_apply', ['details_id' => $vv['details_id']]));
                    // 商品售后详情单
                    $details[$kk]['ViewAfterSale'] = urldecode(url('user/Shop/after_service_details', ['service_id' => $vv['service_id']]));
                    // 商品评价ID
                    $details[$kk]['comment_id'] = !empty($vv['comment_id']) ? intval($vv['comment_id']) : 0;
                    // 商品发布评价(单个商品)
                    $details[$kk]['CommentProduct'] = urldecode(url('user/ShopComment/product', ['details_id' => $vv['details_id']]));
                    // 无效订单商品处理(商品规格无效且订单未付款)
                    $spec_data = !empty($vv['data']) ? unserialize($vv['data']) : [];
                    $details[$kk]['pointsGoodsBuyField'] = !empty($spec_data['pointsGoodsBuyField']) ? json_decode($spec_data['pointsGoodsBuyField'], true) : [];
                    if (!in_array($vv['order_id'], $OrderIds) && 0 === intval($value['order_status']) && !empty($spec_data['spec_value_id'])) {
                        if (empty($specValueArray[$vv['product_id']]) || !in_array($spec_data['spec_value_id'], $specValueArray[$vv['product_id']])) {
                            // 用于更新订单数据
                            array_push($OrderIds, $vv['order_id']);
                            // 修改循环内的订单状态进行逻辑计算
                            $value['order_status'] = 4;
                            // 修改主表数据，确保输出数据正确
                            $result['list'][$key]['order_status'] = 4;
                            // 用于追加订单操作记录
                            $OrderIds_[]['order_id'] = $vv['order_id'];
                        }
                    }
                    // 订单商品规格处理
                    $details[$kk]['product_spec_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($vv);
                    // 商品详情页面地址
                    if (!empty($archives[$vv['product_id']])) {
                        // 商品存在
                        $details[$kk]['arcurl'] = urldecode(arcurl('home/Product/view', $archives[$vv['product_id']]));
                    } else {
                        // 商品不存在
                        $details[$kk]['arcurl'] = urldecode(url('home/View/index', ['aid'=>$vv['product_id']]));
                    }
                    $has_deleted = 0;
                    $msg_deleted = '';
                    // 如果是被伪删除商品且为实体商品则执行
                    if (1 === intval($vv['is_del']) && 0 === intval($vv['prom_type'])) {
                        $has_deleted = 1;
                        $msg_deleted = '[商品已停售]';
                    }
                    // 如果是被伪删除商品且为虚拟商品则执行
                    else if (1 === intval($vv['is_del']) && in_array(intval($vv['prom_type']), [1, 2, 3])) {
                        $has_deleted = 1;
                        $msg_deleted = '[商品已停售]';
                    }
                    // 如果是正常售卖商品且为虚拟商品则执行
                    else if (0 === intval($vv['is_del']) && in_array(intval($vv['prom_type']), [1, 2, 3])) {
                        $has_deleted = 1;
                        $msg_deleted = '[虚拟商品]';
                    }
                    $details[$kk]['has_deleted'] = $has_deleted;
                    $details[$kk]['msg_deleted'] = $msg_deleted;
                }
                $result['list'][$key]['details'] = $details;

                // 待付款订单处理
                if (empty($value['order_status'])) {
                    // 付款地址处理，对ID和订单号加密，拼装url路径
                    $payParam = [
                        'order_id'   => $value['order_id'],
                        'order_code' => $value['order_code']
                    ];
                    // 先 json_encode 后 md5 加密信息
                    $payStr = md5(json_encode($payParam));
                    // 清除之前的 cookie
                    Cookie::delete($payStr);
                    // 存入 cookie
                    cookie($payStr, $payParam);
                    // 跳转链接
                    $result['list'][$key]['PaymentUrl'] = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$payStr]));
                    // 手机端微信支付(JSAPI支付)
                    $result['list'][$key]['wechatJsApiPay'] = " onclick=\"wechatJsApiPay('{$value['order_id']}', '{$value['order_code']}', 2);\" ";
                }

                // 封装取消订单JS
                $result['list'][$key]['CancelOrder'] = " onclick=\"CancelOrder('{$value['order_id']}');\" ";
                
                $result['list'][$key]['order_status_name'] = !empty($order_status_arr[$value['order_status']]) ? $order_status_arr[$value['order_status']] : '';
                if (!empty($this->weappInfo) && 2 === intval($value['logistics_type']) && 1 === intval($value['order_status'])) {
                    $result['list'][$key]['order_status_name'] = '待核销';
                }

                // 获取订单支付方式名称
                if (!empty($value['payment_method']) && !empty($value['pay_name'])) {
                    $result['list'][$key]['pay_name'] = !empty($pay_method_arr[$value['pay_name']]) ? $pay_method_arr[$value['pay_name']] : '第三方支付';
                } else {
                    if (!empty($value['pay_name'])) {
                        $result['list'][$key]['pay_name'] = !empty($pay_method_arr[$value['pay_name']]) ? $pay_method_arr[$value['pay_name']] : '第三方支付';
                    } else {
                        $result['list'][$key]['pay_name'] = '在线支付';
                    }
                }

                // 封装订单查询详情链接
                $result['list'][$key]['OrderDetailsUrl'] = urldecode(url('user/Shop/shop_order_details',['order_id'=>$value['order_id']]));

                // 封装订单催发货JS
                $result['list'][$key]['OrderRemind'] = " onclick=\"OrderRemind('{$value['order_id']}','{$value['order_code']}');\" ";
                 
                // 封装确认收货JS
                $result['list'][$key]['Confirm'] = " onclick=\"Confirm('{$value['order_id']}','{$value['order_code']}');\" ";

                // 订单商品售后选择列表
                $result['list'][$key]['ServiceList'] = urldecode(url('user/Shop/service_list', ['order_id' => $value['order_id']]));

                // 订单商品批量评价列表
                $result['list'][$key]['AddProduct'] = urldecode(url('user/ShopComment/comment_list', ['order_id' => $value['order_id']]));

                // 封装查询物流链接
                $result['list'][$key]['LogisticsInquiry'] = $result['list'][$key]['MobileExpressUrl'] = '';
                if (in_array($value['order_status'], [2, 3]) && empty($value['prom_type'])) {
                    // 物流查询接口
                    if (isMobile()) {
                        $ExpressUrl = "https://m.kuaidi100.com/index_all.html?type=".$value['express_code']."&postid=".$value['express_order']."&callbackurl=".$ReturnUrl;
                    } else {
                        $ExpressUrl = "https://www.kuaidi100.com/chaxun?com=".$value['express_code']."&nu=".$value['express_order'];
                    }
                    // 微信端、小程序使用跳转方式进行物流查询
                    $result['list'][$key]['MobileExpressUrl'] = $ExpressUrl;
                    // PC端，手机浏览器使用弹框方式进行物流查询
                    $result['list'][$key]['LogisticsInquiry'] = " onclick=\"LogisticsInquiry('{$ExpressUrl}');\" ";
                }

                $result['list'][$key]['order_amount'] = !empty($value['order_amount']) ? unifyPriceHandle($value['order_amount']) : 0;
                $result['list'][$key]['order_total_amount'] = !empty($value['order_total_amount']) ? unifyPriceHandle($value['order_total_amount']) : 0;
                $result['list'][$key]['shipping_fee'] = is_numeric($value['shipping_fee']) ? unifyPriceHandle($value['shipping_fee']) : $value['shipping_fee'];

                // 默认为空
                $result['list'][$key]['hidden'] = '';
            }

            // 存在积分商城订单则执行
            $pointsShopOrder = !empty($result['list']) ? get_arr_column($result['list'], 'points_shop_order') : [];
            if (!empty($pointsShopOrder) && in_array(1, $pointsShopOrder)) {
                $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
                if (!empty($weappInfo)) {
                    $pointsShopLogic = new \weapp\PointsShop\logic\PointsShopLogic();
                    foreach ($result['list'] as $key => $value) {
                        $result['list'][$key]['Details'] = $result['list'][$key]['details'];
                        unset($result['list'][$key]['details']);
                    }
                    $result['list'] = $pointsShopLogic->pointsShopOrderDataHandle($result['list']);
                    foreach ($result['list'] as $key => $value) {
                        $result['list'][$key]['details'] = $result['list'][$key]['Details'];
                        unset($result['list'][$key]['Details']);
                        if (1 === intval($value['points_shop_order']) && !empty($pay_method_arr[$value['pay_name']])) {
                            $result['list'][$key]['pay_name'] = $pay_method_arr[$value['pay_name']];
                        }
                    }
                }
            }

            // 更新产品规格异常的订单，更新为订单过期
            if (!empty($OrderIds)) {
                // 更新订单
                $update = [
                    'order_status' => 4,
                    'update_time'  => getTime()
                ];
                Db::name('shop_order')->where('order_id', 'IN', $OrderIds)->update($update);
                // 追加订单操作记录
                AddOrderAction($OrderIds_, $this->users_id, 0, 4, 0, 0, '订单过期', '规格更新后部分产品规格不存在，订单过期');
            }

            // 传入JS参数
            $data['is_wap'] = isWeixin() || isMobile() ? 1 : 0;
            $data['shop_order_cancel'] = url('user/Shop/shop_order_cancel', ['_ajax'=>1], true, false, 1, 1, 0);
            $data['shop_order_remind'] = url('user/Shop/shop_order_remind', ['_ajax'=>1], true, false, 1, 1, 0);
            $data['shop_member_confirm'] = url('user/Shop/shop_member_confirm', ['_ajax'=>1], true, false, 1, 1, 0);
            $data_json = json_encode($data);
            $version   = getCmsVersion();
            // 循环中第一个数据带上JS代码加载
            if ('v5' == $this->usersTplVersion) {
                $jsfile = "tag_sporderlist_{$this->usersTplVersion}.js";
            } else {
                $jsfile = "tag_sporderlist.js";
            }
            $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
            $result['list'][0]['hidden'] = <<<EOF
<script type="text/javascript">
    var d62a4a8743a94dc0250be0c53f833b = {$data_json};
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
            // dump($result);exit;
            return $result;
        } else {
            return false;
        }
    }

    public function getSpstatus()
    {
        // 公用条件
        $where = [
            'users_id' => $this->users_id,
            'lang'     => self::$home_lang,
        ];
        $ShopOrder = Db::name('shop_order')->where($where)->field('order_status, is_comment')->select();
        $result['All'] = $result['PendingPayment'] = $result['PendingReceipt'] = $result['Completed'] = $result['waitComment'] = 0;
        foreach ($ShopOrder as $key => $value) {
            // 待支付个数总计(同等未付款，已下单)
            if (0 === intval($value['order_status'])) {
                $result['PendingPayment']++;
            }
            // 待收货个数总计(同等已发货)
            else if (2 === intval($value['order_status'])) {
                $result['PendingReceipt']++;
            }
            // 已完成个数总计(已完成评价的订单不计算在内)
            else if (3 === intval($value['order_status'])) {
                $result['Completed']++;
                // 已完成待评价
                if (0 === intval($value['is_comment'])) $result['waitComment']++;
            }
        }
        $result['All'] = !empty($ShopOrder) ? count($ShopOrder) : 0;
        // 评价个数总计
        $result['CommentList'] = Db::name('shop_order_comment')->where($where)->count();
        $result['CommentList'] = !empty($result['CommentList']) ? intval($result['CommentList']) : 0;
        // 退换货个数总计
        $where['status'] = ['IN', [1, 2, 4, 5]];
        $result['AfterService'] = Db::name('shop_order_service')->where($where)->count();
        $result['AfterService'] = !empty($result['AfterService']) ? intval($result['AfterService']) : 0;
        // 当前页面url参数
        $result['access_action'] = request()->action();
        $result['access_controller'] = request()->controller();
        $result['select_status'] = input('param.select_status', '');

        return $result;
    }
}