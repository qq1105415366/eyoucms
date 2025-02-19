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
 * Date: 2020-05-07
 */

namespace app\api\model\v1;

use think\Db;
use think\Cache;
use think\Config;

/**
 * 微信小程序商城订单模型
 */
load_trait('controller/Jump');

class Shop extends UserBase
{
    use \traits\controller\Jump;

    private $miniproInfo = [];

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->times = getTime();
        $dataConf = tpSetting("OpenMinicode.conf_".self::$provider, [], self::$lang);
        $this->miniproInfo = json_decode($dataConf, true);
    }

    public function getCartTotalNum()
    {
        $cart_total_num = 0;
        if (!empty($this->users_id)){
            $cart_total_num = Db::name('shop_cart')->where(['users_id' => $this->users_id])->sum('product_num');
        }
        return $cart_total_num;
    }

    /*购物车操作--Start*/
    // 页面点击直接添加购物车
    public function ShopPageAddCart($post = [])
    {
        $post['product_id']  = intval($post['product_id']);
        $post['users_id']    = intval($post['users_id']);
        $post['product_num'] = intval($post['product_num']);

        // 查询条件
        $ArchivesWhere = [
            'aid'     => $post['product_id'],
            'lang'    => get_home_lang(),
            'arcrank' => ['>=', 0]
        ];
        $count         = Db::name('archives')->where($ArchivesWhere)->count();
        // 加入购物车处理
        if (!empty($count)) {
            // 查询条件
            $CartWhere = [
                'users_id'   => $post['users_id'],
                'product_id' => $post['product_id'],
                'lang'       => get_home_lang()
            ];

            /*若开启多规格则执行*/
            $usersConfig = getUsersConfigData('shop');
            if (!empty($usersConfig['shop_open_spec'])) {
                $combiWhere = [
                    'aid'        => $post['product_id'],
                    'spec_stock' => ['>', 0],
                ];
                //获取第一个有库存的规格
                $Field = 'spec_value_id, spec_stock';
                $SpecValue = Db::name('product_spec_value')->field($Field)->where($combiWhere)->order('value_id asc')->find();
                if (!empty($SpecValue)) {
                    if ($SpecValue['spec_stock'] > 0) {
                        $post['spec_value_id'] = $CartWhere['spec_value_id'] = $SpecValue['spec_value_id'];
                    } else {
                        $this->error('商品已售罄');
                    }
                }
            }
            /* END */

            $ProductNum = Db::name('shop_cart')->field('product_num')->where($CartWhere)->getField('product_num');
            if (!empty($ProductNum)) {
                // 购物车内已有相同商品，进行数量更新。
                $ResultID = Db::name('shop_cart')->where($CartWhere)->setInc('product_num', $post['product_num']);
            } else {
                // 购物车内还未有相同商品，进行添加。
                $data     = [
                    'users_id'      => $post['users_id'],
                    'product_id'    => $post['product_id'],
                    'product_num'   => $post['product_num'],
                    'spec_value_id' => empty($post['spec_value_id']) || 'undefined' == $post['spec_value_id'] ? '' : $post['spec_value_id'],
                    'selected'      => 1,
                    'lang'          => get_home_lang(),
                    'add_time'      => getTime(),
                    'update_time'   => getTime()
                ];
                $ResultID = Db::name('shop_cart')->add($data);
            }
            if (!empty($ResultID)) {
                $cart_total_num = Db::name('shop_cart')->where(['users_id' => $post['users_id']])->sum('product_num');
                $this->success('加入成功','',['cart_total_num'=>$cart_total_num]);
            } else {
                $this->error('加入失败');
            }
        } else {
            $this->error('商品已下架');
        }
    }

    /*购物车操作--Start*/
    // 添加购物车
    public function ShopAddCart($post = [])
    {
        $post['product_id']  = intval($post['product_id']);
        $post['users_id']    = intval($post['users_id']);
        $post['product_num'] = intval($post['product_num']);

        // 查询条件
        $ArchivesWhere = [
            'aid'     => $post['product_id'],
            'lang'    => get_home_lang(),
            'arcrank' => ['>=', 0]
        ];
        $count         = Db::name('archives')->where($ArchivesWhere)->count();
        // 加入购物车处理
        if (!empty($count)) {
            // 查询条件
            $CartWhere = [
                'users_id'   => $post['users_id'],
                'product_id' => $post['product_id'],
                'lang'       => get_home_lang()
            ];
            if (!empty($post['discount_active_id'])){
                $CartWhere['discount_active_id'] = $post['discount_active_id'];
            }else{
                $CartWhere['discount_active_id'] = 0;
            }

            /*若开启多规格则执行*/
            $usersConfig = getUsersConfigData('shop');
            if (!empty($post['spec_value_id']) && 'undefined' != $post['spec_value_id'] && !empty($usersConfig['shop_open_spec'])) {
                $CartWhere['spec_value_id'] = $post['spec_value_id'];
            }
            /* END */

            $ProductNum = Db::name('shop_cart')->field('product_num')->where($CartWhere)->getField('product_num');
            if (!empty($ProductNum)) {
                // 购物车内已有相同商品，进行数量更新。
                $ResultID = Db::name('shop_cart')->where($CartWhere)->setInc('product_num', $post['product_num']);
            } else {
                // 购物车内还未有相同商品，进行添加。
                $data     = [
                    'users_id'      => $post['users_id'],
                    'product_id'    => $post['product_id'],
                    'product_num'   => $post['product_num'],
                    'spec_value_id' => empty($post['spec_value_id']) || 'undefined' == $post['spec_value_id'] ? '' : $post['spec_value_id'],
                    'selected'      => 1,
                    'lang'          => get_home_lang(),
                    'add_time'      => getTime(),
                    'update_time'   => getTime(),
                    'discount_active_id'   => !empty($CartWhere['discount_active_id']) ? $CartWhere['discount_active_id'] : 0
                ];
                $ResultID = Db::name('shop_cart')->add($data);
            }
            if (!empty($ResultID)) {
                $totalCart = Db::name('shop_cart')->where(['users_id' => $post['users_id']])->sum('product_num');
                $this->success('加入成功','',['cart_total_num'=>$totalCart]);
            } else {
                $this->error('加入失败');
            }
        } else {
            $this->error('商品已下架');
        }
    }

    // 删除购物车商品
    public function ShopCartDelete($param = [])
    {
        if (empty($param['users_id'])) $this->error('请先登录');
        if (empty($param['action'])) $this->error('数据错误，请重试');
        if ('[]' == $param['cart_id']) $this->error('选择要删除的商品');
        $where    = [
            'users_id' => intval($param['users_id']),
            'cart_id'  => ['IN', $param['cart_id']],
        ];
        $ResultID = Db::name('shop_cart')->where($where)->delete();
        if (!empty($ResultID)) {
            $totalCart = Db::name('shop_cart')->where(['users_id' => $param['users_id']])->sum('product_num');
            $this->success('操作成功','',['cart_total_num'=>$totalCart]);
        } else {
            $this->error('操作失败');
        }
    }

    // 购物车数据列表
    public function ShopCartList($users_id = null, $level_discount = 100, $level_id = 0)
    {
        $result              = [];
        $result['cart_list'] = [];

        if (!empty($users_id)) {
            // 查询购物车数据
            $condition = [
                'a.users_id' => $users_id,
                'a.lang'     => get_home_lang(),
                'b.arcrank'  => array('egt', 0)
            ];
            $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.users_discount_type, b.stock_count, b.attrlist_id, c.spec_price, c.spec_stock';
            if ('v1.5.1' < getVersion()) {
                $field .= ',c.seckill_price, c.seckill_stock';
            }
            if ('v1.5.7' < getVersion()) {
                $field .= ',c.discount_price, c.discount_stock';
            }

            $ShopCart  = Db::name("shop_cart")
                ->field($field)
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->where($condition)
                ->order('a.selected desc, a.add_time desc')
                ->select();

            if (!empty($ShopCart)) {
                // 获取商城配置信息
                $ConfigData = getUsersConfigData('shop');

                // 会员折扣
                $users_discount = strval($level_discount) / strval(100);

                // 处理购物车数据
                $ShopCart = $this->OrderProductProcess($ShopCart, $ConfigData, $users_discount, $level_id);
            }
            $result['cart_list'] = $ShopCart;
        }

        $result['product'] = model('v1.Api')->getRecomProduct();

        return $result;
    }

    // 购物车数量增加
    public function ShopCartNumAdd($param = [])
    {
        if (empty($param['users_id'])) $this->error('请先登录');
        if (empty($param['action']) || empty($param['product_id'])) $this->error('数据错误，请重试');
        $where = [
            'users_id'   => intval($param['users_id']),
            'product_id' => intval($param['product_id']),
        ];
        if (!empty($param['spec_value_id']) && 'undefined' != $param['spec_value_id']) {
            $where['spec_value_id'] = $param['spec_value_id'];
        }
        $ResultID = Db::name('shop_cart')->where($where)->setInc('product_num');
        if (!empty($ResultID)) {
            $cart_total_num = Db::name('shop_cart')->where('users_id',$param['users_id'])->sum('product_num');
            $this->success('操作成功','',['cart_total_num'=>$cart_total_num]);
        } else {
            $this->error('操作失败');
        }
    }

    // 购物车数量减少
    public function ShopCartNumLess($param = [])
    {
        if (empty($param['users_id'])) $this->error('请先登录');
        if (empty($param['action']) || empty($param['product_id'])) $this->error('数据错误，请重试');
        $where = [
            'users_id'   => intval($param['users_id']),
            'product_id' => intval($param['product_id']),
        ];
        if (!empty($param['spec_value_id']) && 'undefined' != $param['spec_value_id']) {
            $where['spec_value_id'] = $param['spec_value_id'];
        }
        $product_num_tmp = Db::name('shop_cart')->where($where)->getField('product_num');
        if (!empty($product_num_tmp)) {
            if ($product_num_tmp > 1) {
                $ResultID = Db::name('shop_cart')->where($where)->setDec('product_num');
                if (!empty($ResultID)) {
                    $cart_total_num = Db::name('shop_cart')->where('users_id',$param['users_id'])->sum('product_num');
                    $this->success('操作成功','',['cart_total_num'=>$cart_total_num]);
                }
            } else {
                $this->error('数量至少一件！');
            }
        }
        $this->error('操作失败');
    }

    // 购物车商品更新是否选中
    public function ShopCartSelected($param = [])
    {
        if (empty($param['users_id'])) $this->error('请先登录');
        if (empty($param['action']) || empty($param['product_id'])) $this->error('数据错误，请重试');
        $where = [
            'users_id'   => intval($param['users_id']),
            'product_id' => intval($param['product_id']),
        ];
        if (!empty($param['spec_value_id']) && 'undefined' != $param['spec_value_id']) {
            $where['spec_value_id'] = $param['spec_value_id'];
        }
        $update   = [
            'selected'    => $param['selected'] == 1 ? 0 : 1,
            'update_time' => getTime()
        ];
        $ResultID = Db::name('shop_cart')->where($where)->update($update);
        if (!empty($ResultID)) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }

    // 全选/反选
    public function ShopCartAllSelected($param = [])
    {
        if (empty($param['users_id'])) $this->error('请先登录');
        if (empty($param['action'])) $this->error('数据错误，请重试');
        $where    = [
            'users_id' => intval($param['users_id']),
        ];
        $update   = [
            'selected'    => $param['all_selected'],
            'update_time' => getTime()
        ];
        $ResultID = Db::name('shop_cart')->where($where)->update($update);
        if (!empty($ResultID)) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
    /*购物车操作--END*/

    /*商城购物流程--Start*/
    // 立即购买
    public function ShopBuyNow($post = [])
    {
        // 查询条件
        $where = [
            'aid'     => $post['product_id'],
            'lang'    => get_home_lang(),
            'arcrank' => ['>=', 0]
        ];
        $count = Db::name('archives')->where($where)->count();
        // 加入购物车处理
        if (!empty($count)) {
            // 对ID和订单号加密，拼装url路径
            $querydata = [
                'product_id'  => $post['product_id'],
                'product_num' => $post['product_num'],
                'active_time_id' => isset($post['active_time_id']) ? $post['active_time_id'] : 0,
                'discount_active_id' => isset($post['discount_active_id']) ? $post['discount_active_id'] : 0,
            ];

            /*存在规格则执行*/
            if (!empty($post['spec_value_id']) && 'undefined' != $post['spec_value_id']) {
                $querydata['spec_value_id'] = $post['spec_value_id'];
            }
            /* END */

            // 先 json_encode 后 md5 加密信息
            $querystr = md5(json_encode($querydata));

            // 存入缓存，一天有效期
            Cache::set($querystr, $querydata, 86400);

            // 跳转链接
            if (1 == input('param.fenbao/d')) {
                $url = '?querystr=' . $querystr;
            } else {
                $url = '/pages/product_buy/index?querystr=' . $querystr;
            }
            $this->success('数据正确', $url, ['querystr'=>$querystr, 'url'=>$url]);
        } else {
            $this->error('该商品不存在或已下架！');
        }
    }

    // 获取商品信息进行展示
    public function GetProductData($querystr = null, $users_id = null, $level_discount = 100, $users = [], $post = [])
    {
        if (empty($users_id)) $this->error('请先登录');

        /* 商品信息 */
        $CacheData = Cache::get($querystr);

        // 是否存在购买商品
        $list = $this->GetBuyOrderList($CacheData, $users_id, $post);
        if (empty($list)) $this->error('链接失效，请重新下单');

        //购物车下单
        if (empty($CacheData)) {
            $logistics_type_arr = get_arr_column($list, 'logistics_type');
            if (empty($post['deliveryShow']) && empty($post['verifyShow']) && in_array(1, $logistics_type_arr)) {
                $post['deliveryShow'] = 1;
            }
        }

        // 获取商城配置信息
        $ConfigData = getUsersConfigData('shop');

        // 会员折扣
        $users_discount = strval($level_discount) / strval(100);
        if (!empty($CacheData['active_time_id'])){
            //秒杀商品没有会员折扣
            $users_discount = 0;
        }

        /* 商品规格/折扣数据处理 */
        $list = $this->OrderProductProcess($list, $ConfigData, $users_discount, $users['level_id']);
        if (empty($list)) $this->error('商品库存不足或已过期！');
        /* END */

        /* 全局信息组装 */
        $GlobalInfo = $this->GetGlobalInfo($ConfigData, $CacheData);
        /* END */

        /* 商品数据整合处理 */
        foreach ($list as $key => $value) {
            // 合计金额、合计数量计算 
            if ($value['users_price'] >= 0 && !empty($value['product_num'])) {
                // 计算单品小计
                $list[$key]['subtotal'] = unifyPriceHandle($value['users_price'] * $value['product_num']);
                // 计算合计金额
                $GlobalInfo['TotalAmount'] += $list[$key]['subtotal'];
                $GlobalInfo['TotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount']);
                // 计算合计数量
                $GlobalInfo['TotalNumber'] += $value['product_num'];
                // 判断订单类型，目前逻辑：一个订单中，只要存在一个普通商品(实物商品，需要发货物流)，则为普通订单
                // 0表示为普通订单，1表示为虚拟订单，虚拟订单无需发货物流，无需选择收货地址，无需计算运费
                if (empty($value['prom_type'])) $GlobalInfo['PromType'] = 0;
            }

            // 如果安装了核销插件则执行
            $verify_open = 0;
            if (is_dir('./weapp/Verify/')) {
                // 核销插件数据信息
                $verifyInfo = model('Weapp')->getWeappList('Verify');
                if (!empty($verifyInfo['status']) && !empty($verifyInfo['data']['openVerify'])) {
                    $verify_open = 1;
                }
            }

            // 仅到店核销
            if ('2' === $value['logistics_type']) {
                if (!empty($verify_open)) {
                    $GlobalInfo['onlyVerify'] = true;
                }
            }
            // 仅物流配送
            if ('1' === $value['logistics_type']) {
                $GlobalInfo['onlyDelivery'] = true;
            }
            // 物流配送 和 到店核销 都支持
            if ('1,2' === $value['logistics_type']) {
                if (!empty($verify_open)) {
                    $GlobalInfo['allLogisticsType'] = true;
                } else {
                    $GlobalInfo['onlyDelivery'] = true;
                }
            }
        }
        /* END */

        /* 默认地址 */
        $address = Db::name('shop_address')->where('users_id', $users_id)->order('is_default desc')->find();
        if (!empty($address)) {
            $address['region']['province'] = $this->GetRegionName($address['province']);
            $address['region']['city']     = $this->GetRegionName($address['city']);
            $address['region']['district'] = $this->GetRegionName($address['district']);
            $address['region']['detail']   = $address['address'];
            $address['address_all']        = $address['region']['province'] . $address['region']['city'] . $address['region']['district'] . $address['region']['detail'];

            if (!empty($GlobalInfo['shop_open_shipping'])) {
                /* 查询运费 */
                $where['province_id'] = $address['province'];
                $template_money       = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                if (0 == $template_money) {
                    $where['province_id'] = 100000;
                    $template_money       = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                }
                $address['template_money'] = $template_money;
                /* END */
            } else {
                $address['template_money'] = 0;
            }
        } else {
            $address['template_money'] = 0;
        }
        /* END */

        // 最终支付总金额
        if (!empty($post['deliveryShow']) && 1 === intval($post['deliveryShow'])) {
            // 订单总金额 + 运费金额
            $GlobalInfo['PayTotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount'] + $address['template_money']);
        } else {
            $GlobalInfo['PayTotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount']);
        }
        
        // 数据返回
        $ResultData = [
            'users' => $users,
            'address' => $address,
            'global_info' => $GlobalInfo,
            'product_data' => $list
        ];
        $ResultData['coupon_data'] = $this->getOrderCoupon($list,$users_id,$users_discount);
        $coupon_ids = get_arr_column($ResultData['coupon_data'],'coupon_id');
        $ResultData['unuse_coupon_data'] = $this->getUnuseOrderCoupon($coupon_ids,$users_id);
        $this->success('数据正确', null, $ResultData);
    }

    /**
     * 获取该订单可用优惠券
     */
    public function getOrderCoupon($product_data,$user_id,$users_discount)
    {
        $use_data = [];
        $coupon_table = 'shop_coupon';
        $coupon_use_table = 'shop_coupon_use';
        $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
        if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
            $coupon_table = 'weapp_coupons';
            $coupon_use_table = 'weapp_coupons_use';
        }

        $all_total_price = 0;//所有商品总价
        $no_discount_total_price = 0;//没用会员价 的商品总价
        foreach ($product_data as $k => $v){
            if (!empty($v['stop_buy']) && 1 == $v['stop_buy']){
                unset($product_data[$k]); //去除本单不可使用商品
                continue;
            }
            if (empty($v['use_discount_price'])) $no_discount_total_price += $v['subtotal'];
            $all_total_price += $v['subtotal'];
        }
        $where['a.users_id'] = $user_id;
        $where['a.start_time'] = ['<=',getTime()];
        $where['a.end_time'] = ['>=',getTime()];
        $where['a.use_status'] =  0;
        $where['b.coupon_id'] =  ['>',0];
        //查出全部的没过期优惠券
        $data = Db::name($coupon_use_table)
            ->alias('a')
            ->join("{$coupon_table} b",'a.coupon_id = b.coupon_id','left')
            ->where($where)
            ->getAllWithIndex('use_id');
        if (!empty($data)){
            foreach ($data as $k => $v){
                if (1 != $users_discount && !empty($v['use_limit'])) continue;

                $v['conditions_use'] = floatval($v['conditions_use']);
                if (1 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满减券';
                }elseif (2 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满折券';
                    $v['coupon_discount'] = floatval( $v['coupon_discount']);
                }
                $v['start_time'] = date('Y/m/d', $v['start_time']);
                $v['end_time'] = date('Y/m/d', $v['end_time']);
                switch ($v['coupon_type'])
                {
                    case 2:
                        $v['coupon_type_name'] = '指定商品';
                        //指定商品
                        if (!empty($v['product_id'])){
                            $product_arr = explode(",",$v['product_id']);
                            $product_total_price = 0; //本张优惠券可用的商品总价
                            foreach ($product_data as $m => $n){
                                if (!empty($v['use_limit']) && !empty($n['use_discount_price'])){
                                    continue;
                                }
                                if (in_array($n['aid'],$product_arr)){
                                    $product_total_price += $n['subtotal'];
                                }
                            }
                            if ($product_total_price >= $v['conditions_use']) {
                                if (2 == $v['coupon_form']){ //满折
                                    $coupon_discount = 1 - ($v['coupon_discount'] / 10); //优惠的折扣
                                    $v['coupon_price'] = unifyPriceHandle($product_total_price * $coupon_discount);
                                }

                                $use_data[] = $v;//此优惠券可用
                            }
                        }
                        break;
                    case 3:
                        $v['coupon_type_name'] = '指定分类';
                        //指定分类
                        if (!empty($v['arctype_id'])){
                            $arctype_arr = explode(",",$v['arctype_id']);
                            $arctype_total_price = 0;//本张优惠券可用的商品总价
                            foreach ($product_data as $m => $n){
                                if (!empty($v['use_limit']) && !empty($n['use_discount_price'])){
                                    continue;
                                }
                                if (in_array($n['typeid'],$arctype_arr)){
                                    $arctype_total_price += $n['subtotal'];
                                }
                            }
                            if ($arctype_total_price >= $v['conditions_use']) {
                                if (2 == $v['coupon_form']){ //满折
                                    $coupon_discount = 1 - ($v['coupon_discount'] / 10); //优惠的折扣
                                    $v['coupon_price'] = unifyPriceHandle($arctype_total_price * $coupon_discount);
                                }
                                $use_data[] = $v;//此优惠券可用
                            }
                        }
                        break;
                    default:
                        $v['coupon_type_name'] = '全部商品';
                        //不可与会员折扣同时使用 包括会员价和会员等级折扣
                        if (!empty($v['use_limit'])){
                            $total_price = $no_discount_total_price;
                        }else{
                            $total_price = $all_total_price;
                        }

                        if ($total_price >= $v['conditions_use']) {
                            if (2 == $v['coupon_form']){ //满折
                                $coupon_discount = 1 - ($v['coupon_discount'] / 10); //优惠的折扣
                                $v['coupon_price'] = unifyPriceHandle($total_price * $coupon_discount);
                            }
                            $use_data[] = $v;
                        }


                        break;
                }
            }
        }
        return $use_data;
    }
    /**
     * 获取该订单不可用优惠券
     */
    public function getUnuseOrderCoupon($coupon_ids = [],$users_id)
    {
        $coupon_table = 'shop_coupon';
        $coupon_use_table = 'shop_coupon_use';
        $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
        if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
            $coupon_table = 'weapp_coupons';
            $coupon_use_table = 'weapp_coupons_use';
        }

        $where['a.users_id'] = $users_id;
        $where['a.use_status'] =  0;
        $where['a.end_time'] = ['>=',getTime()];
        $where['b.coupon_id'] =  ['not in',$coupon_ids];
        $unuse_data = [];
        //查出全部的优惠券
        $data = Db::name($coupon_use_table)
            ->alias('a')
            ->join("{$coupon_table} b",'a.coupon_id = b.coupon_id','left')
            ->where($where)
            ->getAllWithIndex('use_id');
        if (!empty($data)){
            foreach ($data as $k => $v){
                $v['conditions_use'] = floatval($v['conditions_use']);
                if (1 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满减券';
                }elseif (2 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满折券';
                    $v['coupon_discount'] = floatval( $v['coupon_discount']);
                }
                $v['start_time'] = date('Y/m/d', $v['start_time']);
                $v['end_time'] = date('Y/m/d', $v['end_time']);
                switch ($v['coupon_type']) {
                    case '1':
                        $v['coupon_type_name'] = '全部商品';
                        break;
                    case '2';
                        $v['coupon_type_name'] = '指定商品';
                        break;
                    case '3';
                        $v['coupon_type_name'] = '指定分类';
                        break;
                }
                $unuse_data[] = $v;
            }
        }
        return $unuse_data;
    }

    // 订单结算
    public function ShopOrderPay($post = [], $level_discount = 100, $level_id = 0, $usersData = [])
    {
        // 基础判断
        if (empty($post['users_id'])) $this->error('请先登录');
        if (!empty($post['deliveryShow']) && 1 === intval($post['deliveryShow'])) {
            if (empty($post['addr_id']) || $post['addr_id'] == 'undefined') $this->error('请添加收货地址');
        }
        if (empty($post['pay_type'])) $this->error(foreign_lang('users15', get_home_lang()));

        // 获取商城配置信息
        $ConfigData = getUsersConfigData('shop');

        // 会员折扣
        $users_discount = strval($level_discount) / strval(100);
        if (isset($post['order_source']) && 20 == $post['order_source']) $users_discount = 0;

        // 获取下单的商品数据
        $CacheData = Cache::get($post['querystr']);
        $list = $this->GetBuyOrderList($CacheData, $post['users_id'], $post);
        if (empty($list)) $this->error('链接失效，请重新下单');

        // 订单商品数据处理
        $list = $this->OrderProductProcess($list, $ConfigData, $users_discount, $level_id);
        if (empty($list)) $this->error('商品库存不足或已过期');

        // 组装订单流程所需全局信息
        $GlobalInfo = $this->GetGlobalInfo($ConfigData, $CacheData);

        /* 商品数据整合处理 */
        foreach ($list as $key => $value) {
            /* 合计金额、合计数量计算 */
            if ($value['users_price'] >= 0 && !empty($value['product_num'])) {
                // 计算合计金额
                $GlobalInfo['TotalAmount'] += unifyPriceHandle($value['users_price'] * $value['product_num']);
                $GlobalInfo['TotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount']);
                // 计算合计数量
                $GlobalInfo['TotalNumber'] += $value['product_num'];
                // 判断订单类型，目前逻辑：一个订单中，只要存在一个普通商品(实物商品，需要发货物流)，则为普通订单
                // 0表示为普通订单，1表示为虚拟订单，虚拟订单无需发货物流，无需选择收货地址，无需计算运费
                if (empty($value['prom_type'])) $GlobalInfo['PromType'] = 0;
            }
            /* END */
        }
        /* END */

        // 使用优惠券
        if (!empty($post['coupon_id'])){
            $coupon_where = [
                'a.coupon_id' => $post['coupon_id'],
                'a.use_status' => 0,
                'a.users_id' => $post['users_id'],
            ];
            $coupon_info = model('ShopPublicHandle')->order_coupon_handle($coupon_where, $list,$users_discount);
            if (!empty($coupon_info)) $GlobalInfo['TotalAmount'] = $GlobalInfo['TotalAmount'] - $coupon_info['coupon_price'] < 0 ? 0 : $GlobalInfo['TotalAmount'] - $coupon_info['coupon_price'];
        }

        $AddrData = [];
        if (!empty($post['deliveryShow']) && 1 === intval($post['deliveryShow'])) {
            // 获取用户下单时提交的收货地址及运费
            $AddrData = $this->GetUsersAddrData($post, $GlobalInfo['PromType']);
            // 订单总金额 + 运费金额
            $GlobalInfo['PayTotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount'] + $AddrData['shipping_fee']);
        } else {
            $GlobalInfo['PayTotalAmount'] = unifyPriceHandle($GlobalInfo['TotalAmount']);
        }

        // 应付总金额
        $PayAmount = $GlobalInfo['PayTotalAmount'];
        if(self::$provider == 'alipay') {
            $providerStatus = 12;
        } else{
             $providerStatus =3;
        }
        // 添加到订单主表
        $time      = getTime();
        $OrderData = [
            'order_code'         => date('Ymd') . $time . rand(10, 100),
            'users_id'           => $post['users_id'],
            'order_status'       => 0, // 订单未付款
            'add_time'           => $time,
            'payment_method'     => 0,
            'pay_time'           => 0,
            'pay_name'           => 'wechat',
            'wechat_pay_type'    => 'WeChatAppletsPay',
            'order_terminal'     => $providerStatus,
            'pay_details'        => '',
            'order_total_amount' => $GlobalInfo['PayTotalAmount'],
            'order_amount'       => $PayAmount,
            'order_total_num'    => $GlobalInfo['TotalNumber'],
            'prom_type'          => $GlobalInfo['PromType'],
            'order_source'       => isset($post['order_source']) ? $post['order_source'] : 10,
            'order_source_id'    => isset($post['order_source_id']) ? $post['order_source_id'] : 0,
            'virtual_delivery'   => '',
            'admin_note'         => '',
            'user_note'          => $post['remark'],
            'lang'               => get_home_lang()
        ];
        if (!empty($coupon_info)) {
            $OrderData['coupon_id'] = $coupon_info['coupon_id'];
            $OrderData['use_id'] = $coupon_info['use_id'];
            $OrderData['coupon_price'] = $coupon_info['coupon_price'];
        }
        // 存在收货地址则追加合并到主表数组
        if (!empty($AddrData)) $OrderData = array_merge($OrderData, $AddrData);

        // 余额支付
        if (2 === intval($post['pay_type'])) {
            $usersMoney = Db::name('users')->where('users_id', $post['users_id'])->getField('users_money');
            if ($GlobalInfo['PayTotalAmount'] > $usersMoney) $this->error('您的余额不足，支付失败');
        }
        // 抖音支付时更新订单支付类型
        else if (9 === intval($post['pay_type'])) {
            $OrderData['pay_name'] = 'tikTokPay';
            $OrderData['wechat_pay_type'] = '';
        }
         // 支付宝支付时更新订单支付类型
        else if (12 === intval($post['pay_type'])) {
            $OrderData['pay_name'] = 'aliPay';
            $OrderData['wechat_pay_type'] = '';
        }
        // 百度支付时更新订单支付类型
        else if (10 === intval($post['pay_type'])) {
            $OrderData['pay_name'] = 'baiduPay';
            $OrderData['wechat_pay_type'] = '';
        }

        // 订单提交处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
        $OrderData = model('ShopPublicHandle')->orderSubmitPublicHandle($OrderData, $this->usersConfig, $this->users_id, $post, $list);

        // 添加订单及后续处理
        $OrderId = Db::name('shop_order')->add($OrderData);
        if (!empty($OrderId)) {
            $OrderData['order_id'] = $OrderId;

            // 订单创建后续处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
            $OrderData = model('ShopPublicHandle')->orderCreatePublicHandle($OrderData, $this->usersConfig, $this->users_id, $post, $list);

            //锁定优惠券
            if (!empty($coupon_info)){
                $coupon_update = [
                    'coupon_id'=>$post['coupon_id'],
                    'use_status'=> 0,
                    'users_id'  => $post['users_id'],
                ];
                $coupon_use_table = 'shop_coupon_use';
                $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
                if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
                    $coupon_use_table = 'weapp_coupons_use';
                }

                Db::name($coupon_use_table)->where($coupon_update)->update(['use_status'=> 1,'use_time'=>getTime(),'update_time'=>getTime()]);
            }

            $cart_ids   = $UpSpecValue = [];
            $contains_virtual = 1;//是否包含虚拟产品
            foreach ($list as $key => $value) {
                $attr_value = $attr_value_new = $spec_value = '';

                // 旧商品属性处理
                $attr_value = $this->ProductAttrProcessing($value);
                // 新商品属性处理
                $attr_value_new = $this->ProductNewAttrProcessing($value);
                // 商品规格处理
                $spec_value = $this->ProductSpecProcessing($value);

                // 规格及属性
                $ParamData = [
                    // 商品属性
                    'attr_value'     => htmlspecialchars($attr_value),
                    // 商品属性
                    'attr_value_new' => htmlspecialchars($attr_value_new),
                    // 商品规格
                    'spec_value'     => htmlspecialchars($spec_value),
                    // 商品规格值ID
                    'spec_value_id'  => $value['spec_value_id'],
                    // 对应规格值ID的唯一标识ID，数据表主键ID
                    'value_id'       => $value['value_id'],
                    // 后续添加
                ];

                // 订单副表添加数组
                $OrderDetailsData[] = [
                    'order_id'      => $OrderId,
                    'users_id'      => $post['users_id'],
                    'product_id'    => $value['aid'],
                    'product_name'  => $value['title'],
                    'num'           => $value['product_num'],
                    'data'          => serialize($ParamData),
                    'product_price' => $value['users_price'],
                    'prom_type'     => $value['prom_type'],
                    'litpic'        => $value['litpic'],
                    'add_time'      => $time,
                    'lang'          => get_home_lang()
                ];

                // 处理购物车ID
                if (empty($GlobalInfo['submit_order_type'])) {
                    array_push($cart_ids, $value['cart_id']);
                }

                // 商品库存处理
                $UpSpecValue[] = [
                    'aid'           => $value['aid'],
                    'value_id'      => $value['value_id'],
                    'quantity'      => $value['product_num'],
                    'spec_value_id' => $value['spec_value_id'],
                    'order_source'       => isset($post['order_source']) ? $post['order_source'] : 10,
                    'order_source_id'    => isset($post['order_source_id']) ? $post['order_source_id'] : 0,
                ];

                if (in_array($value['prom_type'],[1,2,3])){
                    $contains_virtual = 2;
                }
            }
            if (2 == $contains_virtual) {
                Db::name('shop_order')->where('order_id',$OrderId)->update(['contains_virtual'=>$contains_virtual]);
            }

            $DetailsId = Db::name('shop_order_details')->insertAll($OrderDetailsData);
            if (!empty($OrderId) && !empty($DetailsId)) {
                // 清理购物车中已下单的ID
                if (!empty($cart_ids)) Db::name('shop_cart')->where('cart_id', 'IN', $cart_ids)->delete();

                // 商品库存、销量处理
                $this->ProductStockProcessing($UpSpecValue);

                // 添加订单操作记录
                AddOrderAction($OrderId, $post['users_id']);

                // 微信支付
                if (1 === intval($post['pay_type'])) {
                    if ('h5' == self::$provider) {
                        $openid = Db::name('users')->where('users_id', $post['users_id'])->value('open_id');
                        $row = $this->wechatLogic->get_wechat_pay_config();
                        $order_id = $OrderId;
                        $order_code = $OrderData['order_code'];
                        $order_amount = $OrderData['order_amount'];

                        $payApiModel = new \app\user\model\PayApi;
                        $weChatPay = $payApiModel->getWechatPay($openid, $order_code, $order_amount, $row);
                        if (!empty($weChatPay['postCode']) && 'error' === $weChatPay['postCode']) {
                            $this->error($weChatPay['return_msg']);
                        } else if (!empty($weChatPay)) {
                            $result = [
                                'weChatPay' => $weChatPay,
                                'orderData' => [
                                    'order_id' => $order_id,
                                    'order_code' => $order_code,
                                ]
                            ];
                            $this->success('正在支付', null, $result);
                        }
                    } else {
                        // 调用微信支付接口
                        $weChatPay = model('ShopPublicHandle')->getWechatAppletsPay($post['users_id'], $OrderData['order_code'], $OrderData['order_amount']);
                        $result = [
                            'WeChatPay' => $weChatPay,
                            'OrderData' => [
                                'order_id'   => $OrderId,
                                'order_code' => $OrderData['order_code']
                            ]
                        ];
                        // 已开启的微信订阅消息模板
                        // $where = [
                        //    'is_open' => 1,
                        //    'send_scene' => ['IN', [7]],
                        //    'template_code' => ['GT', 0],
                        // ];
                        // $tmplData = Db::name('applets_template')->where($where)->order('send_scene asc')->getAllWithIndex('template_id');
                        // $result['tmplData'] = $tmplData;
                        $result['tmplData'] = [];
                        // 返回提示
                        $this->success('正在支付', null, $result); 
                    }
                }
                // 余额支付
                else if (2 === intval($post['pay_type'])) {
                    // 更新订单为已余额支付完成
                    $pay_details = [
                        'unified_id'     => $OrderId,
                        'unified_number' => $OrderData['order_code'],
                        'payment_amount' => $OrderData['order_amount'],
                        'payment_type'   => '余额支付'
                    ];
                    $update = [
                        'order_status'    => 1,
                        'pay_name'        => 'balance',
                        'wechat_pay_type' => '',
                        'pay_details'     => serialize($pay_details),
                        'pay_time'        => getTime(),
                        'update_time'     => getTime()
                    ];
                    $resultID = Db::name('shop_order')->where('order_id', $OrderId)->update($update);
                    if (!empty($resultID)) {
                        // 扣除会员余额
                        $resultID = Db::name('users')->where('users_id', $post['users_id'])->setDec('users_money', $OrderData['order_amount']);
                        if (!empty($resultID)) {
                            // 添加会员余额记录
                            $getParam = [
                                'users_money' => $usersMoney,
                                'users_id' => $post['users_id']
                            ];
                            UsersMoneyAction($OrderData['order_code'], $getParam, $OrderData['order_amount'], '订单支付');

                            // 添加订单操作记录
                            AddOrderAction($OrderId, $post['users_id'], 0, 1, 0, 1, '支付成功！', '会员使用余额完成支付！');

                            // 虚拟自动发货
                            $ShopModel = new \app\user\model\Pay();
                            $Where     = [
                                'lang'     => get_home_lang(),
                                'users_id' => $post['users_id'],
                                'order_id' => $OrderId
                            ];
                            $ShopModel->afterVirtualProductPay($Where);
                            
                            // 统计销售额
                            eyou_statistics_data(2);
                            eyou_statistics_data(3, $OrderData['order_amount']);

                            // 订单支付通知
                            $params = [
                               'users_id' => $post['users_id'],
                               'result_id' => $OrderId,
                            ];
                            eyou_send_notice(9, $params);

                            // // 已开启的微信订阅消息模板
                            // $where = [
                            //    'is_open' => 1,
                            //    'send_scene' => ['IN', [7]],
                            //    'template_code' => ['GT', 0],
                            // ];
                            // $tmplData = Db::name('applets_template')->where($where)->order('send_scene asc')->getAllWithIndex('template_id');
                            // $result['tmplData'] = $tmplData;
                            $result['tmplData'] = [];

                            // 邮箱发送
                            $result['email'] = GetEamilSendData(tpCache('smtp'), $usersData, $OrderData, 1, 'balance');

                            // 短信发送
                            $result['mobile'] = GetMobileSendData(tpCache('sms'), $usersData, $OrderData, 1, 'balance');

                            // 发送站内信给后台
                            $OrderData['pay_method'] = '余额支付';
                            SendNotifyMessage($OrderData, 5, 1, 0);

                            // 返回数据
                            $result['OrderData'] = $OrderData;
                            $this->success('支付完成', '/pages/order/index', $result);
                        }
                    }
                }
                // 抖音支付
                else if (9 === intval($post['pay_type'])) {
                    $tikTokPay = model('TikTok')->getTikTokAppletsPay($OrderId, $OrderData['order_code'], $OrderData['order_amount'], $this->usersConfig['order_unpay_close_time']);
                    $result = [
                        'tikTokPay' => $tikTokPay,
                        'OrderData' => [
                            'order_id'   => $OrderId,
                            'order_code' => $OrderData['order_code']
                        ]
                    ];
                    // 已开启的微信订阅消息模板
                    // $where = [
                    //    'is_open' => 1,
                    //    'send_scene' => ['IN', [7]],
                    //    'template_code' => ['GT', 0],
                    // ];
                    // $tmplData = Db::name('applets_template')->where($where)->order('send_scene asc')->getAllWithIndex('template_id');
                    // $result['tmplData'] = $tmplData;
                    $result['tmplData'] = [];
                    // 返回提示
                    $this->success('正在支付', null, $result);
                }
                // 支付宝支付
                 else if (12 === intval($post['pay_type'])) {
                     $openid = Db::name('wx_users')->where('wxuser_id', $post['users_id'])->value('openid');
                   
                    $aliPay = model('AliPay')->getAlipayAppletsPay($OrderId, $OrderData['order_code'], $OrderData['order_amount'],2,$openid);
                     
                    $result = [
                        'aliPay' => $aliPay,
                        'OrderData' => [
                            'order_id'   => $OrderId,
                            'order_code' => $OrderData['order_code']
                        ]
                    ];
                    // 已开启的微信订阅消息模板
                    // $where = [
                    //    'is_open' => 1,
                    //    'send_scene' => ['IN', [7]],
                    //    'template_code' => ['GT', 0],
                    // ];
                    // $tmplData = Db::name('applets_template')->where($where)->order('send_scene asc')->getAllWithIndex('template_id');
                    // $result['tmplData'] = $tmplData;
                    $result['tmplData'] = [];
                    // 返回提示
                    $this->success('正在支付', null, $result);
                }
                // 百度支付
                else if (10 === intval($post['pay_type'])) {
                    $baiduPay = model('BaiduPay')->getBaiDuAppletsPay($OrderId, $OrderData['order_code'], $OrderData['order_amount']);
                    $result = [
                        'baiduPay' => $baiduPay,
                        'OrderData' => [
                            'order_id'   => $OrderId,
                            'order_code' => $OrderData['order_code']
                        ]
                    ];
                    // 已开启的微信订阅消息模板
                    // $where = [
                    //    'is_open' => 1,
                    //    'send_scene' => ['IN', [7]],
                    //    'template_code' => ['GT', 0],
                    // ];
                    // $tmplData = Db::name('applets_template')->where($where)->order('send_scene asc')->getAllWithIndex('template_id');
                    // $result['tmplData'] = $tmplData;
                    $result['tmplData'] = [];
                    // 返回提示
                    $this->success('正在支付', null, $result);
                }
            } else if (!empty($OrderId) && empty($DetailsId)) {
                // 订单详情添加失败则删除已添加的订单主表数据
                Db::name('shop_order')->delete($OrderId);
                $this->error('订单生成失败，商品数据有误！');
            }
        }
        $this->error('订单生成失败，商品数据有误！');
    }

    //生成核销码
    public function create_verify_code($length = 12)
    {
        $chars = "0123456789";
        $char_len = strlen($chars);
        $verify_code = '';
        for ($i = 0; $i < $length; $i++) {
            $loop = mt_rand(0, ($char_len - 1));
            $verify_code .= $chars[$loop];
        }
        $is_have = Db::name('shop_order_verify')->where('verify_code',$verify_code)->count();
        if (!empty($is_have)){
            $verify_code = $this->create_verify_code(12);
        }
        return $verify_code;
    }

    // 微信小程序支付后续处理
    public function WechatAppletsPayDealWith($PostData = [], $notify = false)
    {
        if (!empty($PostData['order_id']) && !empty($PostData['order_code'])) {
            // 查询本站订单状态 
            $where = [
                'users_id'   => $this->users_id,
                'order_id'   => $PostData['order_id'],
                'order_code' => $PostData['order_code'],
            ];
            $order = Db::name('shop_order')->where($where)->find();
            if (empty($order)) $this->error('无效订单');
            if ((0 < intval($order['prom_type']) || (0 === intval($order['prom_type']) && 2 === intval($order['logistics_type']))) && in_array($order['order_status'], [2, 3]) && 'wechat' === trim($order['pay_name'])) {
                // model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $order['order_code'], 2);
                $this->success('订单已完成');
            }
            if (0 !== intval($order['order_status'])) $this->success('订单支付成功');
            $order['unified_id'] = $order['order_id'];
            $order['unified_number'] = $order['order_code'];
            $order['transaction_type'] = 2;
            if (9 === intval($PostData['pay_type'])) {
                model('TikTok')->tikTokAppletsPayDealWith($order);
            }
             // 支付宝支付
            else if (12 === intval($PostData['pay_type'])) {
                model('AliPay')->aliPayAppletsPayDealWith($order);
            }
            // 百度支付
            else if (10 === intval($PostData['pay_type'])) {
                model('BaiduPay')->baiDuAppletsPayDealWith($order);
            }
            // 微信支付
            else {
                model('ShopPublicHandle')->getWeChatPayResult($this->users_id, $order);
            }
        }
    }

    // 用于订单列表和订单详情页直接支付
    public function OrderDirectPay($post = [])
    {
        $where  = [
            'users_id'   => $post['users_id'],
            'order_id'   => $post['order_id'],
            'order_code' => $post['order_code']
        ];
        $field  = 'order_status, order_amount';
        $Result = Db::name('shop_order')->where($where)->field($field)->find();
        if (!empty($Result)) {
            if (0 == $Result['order_status']) {
                // 抖音支付
                if (9 === intval($post['pay_type'])) {
                    $tikTokPay = model('TikTok')->getTikTokAppletsPay($post['order_id'], $post['order_code'], $Result['order_amount'], $this->usersConfig['order_unpay_close_time']);
                    $resultData = [
                        'tikTokPay' => $tikTokPay,
                        'OrderData' => [
                            'order_id'   => $post['order_id'],
                            'order_code' => $post['order_code']
                        ]
                    ];
                }
                // 支付宝支付
                else if (12 === intval($post['pay_type'])) {
                    $openid = Db::name('wx_users')->where('wxuser_id', $post['users_id'])->value('openid');
                    $aliPay = model('AliPay')->getAlipayAppletsPay($post['order_id'], $post['order_code'], $Result['order_amount'],'',$openid);;
                    $resultData = [
                        'aliPay' => $aliPay,
                        'OrderData' => [
                            'order_id'   => $post['order_id'],
                            'order_code' => $post['order_code']
                        ]
                    ];
                }
                // 百度支付
                else if (10 === intval($post['pay_type'])) {
                    $baiduPay = model('BaiduPay')->getBaiDuAppletsPay($post['order_id'], $post['order_code'], $Result['order_amount']);
                    $resultData = [
                        'baiduPay' => $baiduPay,
                        'OrderData' => [
                            'order_id'   => $post['order_id'],
                            'order_code' => $post['order_code']
                        ]
                    ];
                }
                // 微信支付
                else {
                    if ('h5' == self::$provider) {
                        $openid = Db::name('users')->where('users_id', $post['users_id'])->value('open_id');
                        $row = $this->wechatLogic->get_wechat_pay_config();
                        $order_id = $post['order_id'];
                        $order_code = $post['order_code'];
                        $order_amount = $Result['order_amount'];

                        $payApiModel = new \app\user\model\PayApi;
                        $weChatPay = $payApiModel->getWechatPay($openid, $order_code, $order_amount, $row);
                        if (!empty($weChatPay['postCode']) && 'error' === $weChatPay['postCode']) {
                            $this->error($weChatPay['return_msg']);
                        } else if (!empty($weChatPay)) {
                            $result = [
                                'weChatPay' => $weChatPay,
                                'orderData' => [
                                    'order_id' => $order_id,
                                    'order_code' => $order_code,
                                ]
                            ];
                            $this->success('正在支付', null, $result);
                        }
                    } else {
                        // 调用微信支付接口
                        $weChatPay = model('ShopPublicHandle')->getWechatAppletsPay($post['users_id'], $post['order_code'], $Result['order_amount'], 2);
                        $resultData = [
                            'WeChatPay' => $weChatPay,
                            'OrderData' => [
                                'order_id'   => $post['order_id'],
                                'order_code' => $post['order_code']
                            ]
                        ];
                    }
                }
                $url = 1 == input('param.fenbao/d') ? '' : '/pages/user/index';
                $this->success('正在支付', $url, $resultData);
            } else if (in_array($Result['order_status'], [1, 2, 3])) {
                $this->success('订单已支付');
            } else if ($Result['order_status'] == 4) {
                $this->success('订单已过期');
            } else if ($Result['order_status'] == -1) {
                $this->success('订单已关闭');
            }
        }
        $this->error('订单不存在');
    }

    // 微信小程序支付
    public function GetWechatAppletsPay($OpenID = null, $out_trade_no = null, $total_fee = null, $notify_url = '')
    {
        // 调用微信支付接口
        return model('ShopPublicHandle')->getWechatAppletsPay($this->users_id, $out_trade_no, $total_fee, 2);
    }

    // 微信支付签名
    private function getPaySign($appid = '', $nonceStr = '', $prepay_id = '',$time = '',$apikey = '')
    {
        $result = [
            'appId'     => $appid,
            'nonceStr'  => $nonceStr,
            'package'   => 'prepay_id=' . $prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => strval($time),
        ];
        return $this->ParamsSign($result,$apikey);
    }

    private function ParamsSign($values, $apikey)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = $this->ParamsUrl($values);
        //签名步骤二：在string后加入KEY
        $string = $string . '&key=' . $apikey;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    private function ParamsUrl($values)
    {
        $Url = '';
        foreach ($values as $k => $v) {
            if ($k != 'sign' && $v != '' && !is_array($v)) {
                $Url .= $k . '=' . $v . '&';
            }
        }
        return trim($Url, '&');
    }

    private function ParamsXml($values)
    {
        if (!is_array($values)
            || count($values) <= 0
        ) {
            return false;
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    private function ResultXml($xml)
    {
        // 禁止引用外部xml实体
        @libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    private function GetClientIP()
    {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
    }

    private function HttpsPost($url, $data)
    {
        $result = httpRequest($url, 'POST', $data);
        return $result;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Errno: ' . curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    private function PaySign($appid, $nonceStr, $prepay_id, $timeStamp)
    {
        $data = [
            'appId'     => $appid,
            'nonceStr'  => $nonceStr,
            'package'   => 'prepay_id=' . $prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => $timeStamp,
        ];
        // 签名步骤一：按字典序排序参数
        ksort($data);
        $string = $this->ParamsUrl($data);
        // 签名步骤二：在string后加入KEY
        $string = $string . '&key=' . $this->miniproInfo['apikey'];
        // 签名步骤三：MD5加密
        $string = md5($string);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    // 扣除商品库存
    private function ProductStockProcessing($SpecValue = [])
    {
        if (!empty($SpecValue)) {
            $SpecUpData = []; // 有规格
            $ArcUpData  = []; // 无规格
            foreach ($SpecValue as $key => $value) {
                if (!empty($value['value_id'])) {
                    if (!empty($value['order_source']) && $value['order_source'] == 20){
                        //秒杀订单
                        $SpecUpData[] = [
                            'value_id'       => $value['value_id'],
                            'seckill_stock'     => Db::raw('seckill_stock-' . ($value['quantity'])),
                            'seckill_sales_num' => Db::raw('seckill_sales_num+' . ($value['quantity'])),
                        ];
                    }else{
                        //普通订单
                        $SpecUpData[] = [
                            'value_id'       => $value['value_id'],
                            'spec_stock'     => Db::raw('spec_stock-' . ($value['quantity'])),
                            'spec_sales_num' => Db::raw('spec_sales_num+' . ($value['quantity'])),
                        ];
                        $ArcUpData[] = [
                            'aid'         => $value['aid'],
                            'stock_count' => Db::raw('stock_count-' . ($value['quantity'])),
                            'sales_num'   => Db::raw('sales_num+' . ($value['quantity'])),
                            'sales_all'   => Db::raw('sales_all+' . ($value['quantity'])),
                        ];
                    }
                } else {
                    if ($value['order_source'] != 20){
                        //普通订单
                        $ArcUpData[] = [
                            'aid'         => $value['aid'],
                            'stock_count' => Db::raw('stock_count-' . ($value['quantity'])),
                            'sales_num'   => Db::raw('sales_num+' . ($value['quantity'])),
                            'sales_all'   => Db::raw('sales_all+' . ($value['quantity'])),
                        ];
                    }
                }
                //秒杀订单无论单规格还是多规格都要更改sharp_goods的库存和销量
                if (!empty($value['order_source']) && $value['order_source'] == 20){
                    $SharpUpData = [
                        'aid'         => $value['aid'],
                        'seckill_stock' => Db::raw('seckill_stock-' . ($value['quantity'])),
                        'sales'   => Db::raw('sales+' . ($value['quantity']))
                    ];
                    Db::name('sharp_goods')->where('aid',$value['aid'])->update($SharpUpData);
                    Db::name('sharp_active_goods')
                        ->where(['active_time_id'=>$value['order_source_id'],'aid'=> $value['aid']])
                        ->update(['sales_actual'=>Db::raw('sales_actual+' . ($value['quantity']))]);
                }
            }

            // 更新规格库存销量
            if (!empty($SpecUpData)) {
                $ProductSpecValueModel = new \app\user\model\ProductSpecValue();
                $ProductSpecValueModel->saveAll($SpecUpData);
            }

            // 更新商品库存销量
            if (!empty($ArcUpData)) {
                $ArchivesModel = new \app\user\model\Archives();
                $ArchivesModel->saveAll($ArcUpData);
            }
        }
    }

    // 获取下单的商品数据
    private function GetBuyOrderList($CacheData = [], $users_id = null, $post = [])
    {
        $list = [];
        if (!empty($CacheData)) {
            // 立即购买下单
            $where['a.aid'] = $CacheData['product_id'];
            if (!empty($CacheData['spec_value_id'])) $where['b.spec_value_id'] = $CacheData['spec_value_id'];
            $field = 'a.aid, a.typeid, a.title, a.litpic, a.users_price, a.users_discount_type, a.stock_count, a.prom_type, a.attrlist_id, a.logistics_type, b.value_id, b.aid as product_id, b.spec_value_id, b.spec_price, b.spec_stock';
            if ('v1.5.1' < getVersion()) {
                $field .= ',b.seckill_price, b.seckill_stock';
            }
            if ('v1.5.7' < getVersion()) {
                $field .= ',b.discount_price, b.discount_stock';
            }
            $list = Db::name('archives')->alias('a')->field($field)
                ->join('__PRODUCT_SPEC_VALUE__ b', 'a.aid = b.aid', 'LEFT')
                ->where($where)
                ->limit('0, 1')
                ->select();
            $list[0]['product_num'] = $CacheData['product_num'];
            if (isset($CacheData['active_time_id']) && !empty($CacheData['active_time_id'])){
                $list[0]['active_time_id'] = $CacheData['active_time_id'];
                //判断是否还在秒杀时间内
                $hour = date('H');
                $date = strtotime(date('Y-m-d'));
                $active_time = Db::name('sharp_active_time')
                    ->alias('a')
                    ->join('sharp_active b','a.active_id = b.active_id')
                    ->where('b.active_date',$date)
                    ->where('a.active_time',$hour)
                    ->getField('active_time_id');
                if (empty($active_time) || $CacheData['active_time_id'] != $active_time){
                    if (empty($list)) $this->error('秒杀活动已结束!');
                }
                //判断该秒杀商品是多规格还是单规格
                $is_sku = Db::name('sharp_active_goods')
                    ->alias('a')
                    ->join('sharp_goods b','a.aid = b.aid')
                    ->where('a.aid',$CacheData['product_id'])
                    ->where('a.active_time_id',$CacheData['active_time_id'])
                    ->getField('b.is_sku');
                $list[0]['is_sku'] = $is_sku;
                if (empty($is_sku)){
                    //单规格
                    $active_goods = Db::name('sharp_goods')->where('aid',$CacheData['product_id'])->field('seckill_price,seckill_stock')->find();
                    if (!empty($active_goods)){
                        $list[0]['users_price'] = $list[0]['seckill_price'] = $active_goods['seckill_price'];
                        $list[0]['stock_count'] = $list[0]['seckill_stock'] = $active_goods['seckill_stock'];
                    }
                }
            }elseif (isset($CacheData['discount_active_id']) && !empty($CacheData['discount_active_id'])){
                $list[0]['discount_active_id'] = $CacheData['discount_active_id'];
                $end_date = Db::name('discount_active')->where('active_id',$CacheData['discount_active_id'])->value('end_date');
                if ($end_date < getTime()){
                    if (empty($list)) $this->error('活动已结束!');
                }
                //判断该秒杀商品是多规格还是单规格
                $discount_goods = Db::name('discount_active_goods')
                    ->alias('a')
                    ->field('b.is_sku,b.discount_price,b.discount_stock')
                    ->join('discount_goods b','a.aid = b.aid')
                    ->where('a.aid',$CacheData['product_id'])
                    ->where('a.active_id',$CacheData['discount_active_id'])
                    ->find();
                $list[0]['is_sku'] = $discount_goods['is_sku'];
                if (empty($discount_goods['is_sku'])){
                    //单规格
                    $list[0]['users_price'] = $list[0]['discount_price'] = $discount_goods['discount_price'];
                    $list[0]['stock_count'] = $list[0]['discount_stock'] = $discount_goods['discount_stock'];
                }
            }
        } else {
            // 购物车下单
            $where = [
                'a.users_id' => $users_id,
                'a.lang'     => get_home_lang(),
                'a.selected' => 1,
            ];
            $list = Db::name('shop_cart')->field('a.*,b.typeid, b.aid, b.title, b.litpic, b.users_price, b.users_discount_type, b.stock_count, b.prom_type, b.attrlist_id, b.logistics_type, c.spec_price, c.spec_stock, c.value_id')
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->where($where)
                ->select();
            $logistics_type_arr = get_arr_column($list,'logistics_type');
            if (empty($post['deliveryShow']) && empty($post['verifyShow']) && in_array(1,$logistics_type_arr)){
                $post['deliveryShow'] = 1;
            }
            foreach ($list as $key => $value) {
                // 仅购买支持到店核销商品
                if (!empty($post['verifyShow']) && 1 === intval($post['verifyShow'])) {
                    if ('1' === $value['logistics_type']) unset($list[$key]);
                }
                // 仅购买支持快递配送商品
                else if (!empty($post['deliveryShow']) && 1 === intval($post['deliveryShow'])) {
                    if ('2' === $value['logistics_type']) unset($list[$key]);
                }
            }
        }

        return $list;
    }

    // 商品规格/折扣数据处理
    private function OrderProductProcess($list = [], $ConfigData = [], $users_discount = 1, $level_id = 0)
    {
        foreach ($list as $key => $value) {
            // 规格处理
            $list[$key]['product_spec_list'] = !empty($value['spec_value_id']) ? model('ShopPublicHandle')->getGoodsSpecList($value) : [];

            /* 未开启多规格则执行 */
            if (!isset($ConfigData['shop_open_spec']) || empty($ConfigData['shop_open_spec'])) {
                $value['spec_value_id']      = $value['spec_price'] = $value['spec_stock'] = 0;
                $list[$key]['spec_value_id'] = $list[$key]['spec_price'] = $list[$key]['spec_stock'] = 0;
            }
            /* END */

            /* 商品存在规格且价格不为空，则覆盖商品原来的价格 */
            if (!empty($value['spec_value_id']) && 'undefined' != $value['spec_value_id'] && $value['spec_price'] >= 0) {
                // 规格价格覆盖商品原价
                $list[$key]['users_price'] = $value['users_price'] = $value['spec_price'];
            }
            /* END */

            /* 商品原价 */
            $list[$key]['old_price'] = unifyPriceHandle($list[$key]['users_price']);
            /* END */

            /* 计算折扣后的价格 */
            if (!empty($users_discount) && 0 === intval($value['users_discount_type'])) {
                // 会员折扣价
                $list[$key]['users_price'] = unifyPriceHandle($value['users_price'] * $users_discount);
            }
            /* END */
            $list[$key]['discount_end_date'] = 0;
            $list[$key]['use_discount_price'] = 0;
            /* 限时折扣商品多规格商品，则覆盖商品原来的价格 */
            if (!empty($value['discount_active_id'])) {
                if (empty($value['discount_price'])){
                    $value['discount_price'] = Db::name('discount_goods')->where('aid',$value['aid'])->value('discount_price');
                }
                $list[$key]['users_price'] = $value['spec_price'] = $value['discount_price'];
                $list[$key]['use_discount_price'] = 1;
                $list[$key]['discount_end_date'] = Db::name('discount_active')->where('active_id',$value['discount_active_id'])->value('end_date');
            }
            /* END */

            /* 商品存在规格且库存不为空，则覆盖商品原来的库存 */
            if (!empty($value['spec_stock'])) {
                // 规格库存覆盖商品库存
                $list[$key]['stock_count'] = $value['stock_count'] = $value['spec_stock'];
            }
            if ($value['product_num'] > $value['stock_count']) {
                // 购买数量超过最大库存量则默认购买最大库存量
                $list[$key]['stock_count'] = $list[$key]['product_num'] = $value['stock_count'];
            }
            /* END */

            // 图片处理
            $list[$key]['litpic'] = $this->get_default_pic($value['litpic'], true);

            // 商品是 单规格 且 设置会员折扣价 则处理
            if (empty($list[$key]['product_spec']) && 1 === intval($value['users_discount_type']) && !empty($level_id)) {
                $list[$key]['users_price'] = model('ShopPublicHandle')->handleUsersDiscountPrice($value['aid'], $level_id);
            }

            /* 若库存为空则清除这条数据 */
            if (empty($value['stock_count'])) {
                unset($list[$key]);
                continue;
            }
            /* END */
        }

        return $list;
    }

    // 组装订单流程所需全局信息
    private function GetGlobalInfo($ConfigData = [], $CacheData = [])
    {
        $GlobalInfo = [
            // 温馨提示内容,为空则不展示
            'shop_prompt'        => !empty($ConfigData['shop_prompt']) ? $ConfigData['shop_prompt'] : '',
            // 是否开启线下支付(货到付款)
            'shop_open_offline'  => !empty($ConfigData['shop_open_offline']) ? $ConfigData['shop_open_offline'] : 0,
            // 是否开启运费设置
            'shop_open_shipping' => !empty($ConfigData['shop_open_shipping']) ? $ConfigData['shop_open_shipping'] : 0,
            // 初始化支付总额
            'TotalAmount'        => 0,
            // 初始化支付总额，包含运费
            'PayTotalAmount'     => 0,
            // 初始化总数
            'TotalNumber'        => 0,
            // 提交来源:0购物车;1直接下单
            'submit_order_type'  => !empty($CacheData) ? 1 : 0,
            // 1表示为虚拟订单
            'PromType'           => 1,
            // 虚拟订单文案
            'virtual_text1'      => '1、该产品为虚拟产品，仅支持在线支付且无需选择收货地址及运费计算。',
            'virtual_text2'      => '2、若产品是充值类产品，请将您的手机号或需充值的卡号填入留言中。',
            // 仅到店核销
            'onlyVerify'         => false,
            // 仅物流配送
            'onlyDelivery'       => false,
            // 物流支持
            'allLogisticsType'   => false,

        ];

        return $GlobalInfo;
    }

    // 获取用户下单时提交的收货地址及运费
    private function GetUsersAddrData($post = [], $PromType = 0)
    {
        $AddrData = [];
        if (0 == $PromType) {
            // 查询收货地址
            $AddrWhere   = [
                'addr_id'  => $post['addr_id'],
                'users_id' => $post['users_id'],
                'lang'     => get_home_lang()
            ];
            $AddressData = Db::name('shop_address')->where($AddrWhere)->find();
            if (empty($AddressData)) $this->error('收货地址不存在！');

            $shop_open_shipping = getUsersConfigData('shop.shop_open_shipping');
            $template_money     = '0.00';
            if (!empty($shop_open_shipping)) {
                // 通过省份获取运费模板中的运费价格
                $where['province_id'] = $AddressData['province'];
                $template_money       = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                if (0 == $template_money) {
                    // 省份运费价格为0时，使用统一的运费价格，固定ID为100000
                    $where['province_id'] = 100000;
                    $template_money       = Db::name('shop_shipping_template')->where($where)->getField('template_money');
                }
            }
        }

        // 拼装数组
        $AddrData = [
            'consignee'    => !empty($AddressData['consignee']) ? $AddressData['consignee'] : '',
            'country'      => !empty($AddressData['country']) ? $AddressData['country'] : '',
            'province'     => !empty($AddressData['province']) ? $AddressData['province'] : '',
            'city'         => !empty($AddressData['city']) ? $AddressData['city'] : '',
            'district'     => !empty($AddressData['district']) ? $AddressData['district'] : '',
            'address'      => !empty($AddressData['address']) ? $AddressData['address'] : '',
            'mobile'       => !empty($AddressData['mobile']) ? $AddressData['mobile'] : '',
            'shipping_fee' => !empty($template_money) ? $template_money : 0,
        ];

        return $AddrData;
    }

    // 旧产品属性处理
    private function ProductAttrProcessing($value = array())
    {
        $attr_value = '';
        $AttrWhere  = [
            'a.aid'  => $value['aid'],
            'b.lang' => get_home_lang()
        ];
        $attrData   = Db::name('product_attr')
            ->alias('a')
            ->field('a.attr_value as value, b.attr_name as name')
            ->join('__PRODUCT_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($AttrWhere)
            ->order('b.sort_order asc, a.attr_id asc')
            ->select();
        foreach ($attrData as $val) {
            $attr_value .= $val['name'] . '：' . $val['value'] . '<br/>';
        }
        return $attr_value;
    }

    // 新商品属性处理
    private function ProductNewAttrProcessing($value = array())
    {
        $attr_value = '';
        $where      = [
            'a.list_id' => $value['attrlist_id'],
            'a.status'  => 1,
            'b.aid'     => $value['aid']
        ];
        $attrData   = Db::name('shop_product_attribute')
            ->alias('a')
            ->field('a.attr_name as name, b.attr_value as value')
            ->join('__SHOP_PRODUCT_ATTR__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($where)
            ->order('a.sort_order asc, a.attr_id asc')
            ->select();
        foreach ($attrData as $val) {
            $attr_value .= $val['name'] . '：' . $val['value'] . '<br/>';
        }
        return $attr_value;
    }

    // 商品规格处理
    private function ProductSpecProcessing($value = array())
    {
        $spec_value_s = '';
        if (!empty($value['spec_value_id'])) {
            $spec_value_id = explode('_', $value['spec_value_id']);
            if (!empty($spec_value_id)) {
                $SpecWhere       = [
                    'aid'           => $value['aid'],
                    'lang'          => get_home_lang(),
                    'spec_value_id' => ['IN', $spec_value_id],
                ];
                $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name,spec_value')->select();
                foreach ($ProductSpecData as $spec_value) {
                    $spec_value_s .= $spec_value['spec_name'] . '：' . $spec_value['spec_value'] . '<br/>';
                }
            }
        }
        return $spec_value_s;
    }
    /*商城购物流程--END*/

    /* 判断商品库存 */
    public function IsSoldOut($post = [])
    {
        if (!empty($post['product_id'])) {
            if (!empty($post['spec_value_id'])) {
                $SpecWhere  = [
                    'aid'           => $post['product_id'],
                    'lang'          => get_home_lang(),
                    'spec_stock'    => ['>', 0],
                    'spec_value_id' => $post['spec_value_id']
                ];
                $find_field = 'spec_stock';
                if (!empty($post['active_time_id'])){
                    $find_field = 'seckill_stock';
                }
                if (!empty($post['discount_active_id'])){
                    $find_field = 'discount_stock';
                }
                $spec_stock = Db::name('product_spec_value')->where($SpecWhere)->getField($find_field);
                if (empty($spec_stock)) {
                    $this->error('商品已售罄！');
                }
                if ($spec_stock < $post['product_num']) {
                    $this->error('商品最大库存：' . $spec_stock);
                }

            } else {
                $ArchivesWhere = [
                    'aid'     => $post['product_id'],
                    'lang'    => get_home_lang(),
                    'arcrank' => ['>=', 0]
                ];
                if (!empty($post['active_time_id'])){
                    $stock_count = Db::name('sharp_goods')->where('aid',$post['product_id'])->getField('seckill_stock');
                }elseif (!empty($post['discount_active_id'])){
                    $stock_count = Db::name('discount_goods')->where('aid',$post['product_id'])->getField('discount_stock');
                }else{
                    $stock_count   = Db::name('archives')->where($ArchivesWhere)->getField('stock_count');
                }
                if (empty($stock_count)) {
                    $this->error('商品已售罄！');
                }
                if ($stock_count < $post['product_num']) {
                    $this->error('商品最大库存：' . $stock_count);
                }
            }
        }
    }
    /* END */

    /*收货地址--Start*/
    // 收货地址列表
    public function GetAllAddressList($users = [])
    {
        $ReturnData = [];
        $list       = !empty($users['address_1588820149']) ? $users['address_1588820149'] : [];
        $default_id = !empty($users['address_default_1588820149']) ? $users['address_default_1588820149']['addr_id'] : 0;
        if (!empty($list)) {
            // 将地址ID转成地址名称
            foreach ($list as $key => $value) {
                $list[$key]['provinceName'] = $this->GetRegionName($value['province']);
                $list[$key]['cityName']     = $this->GetRegionName($value['city']);
                $list[$key]['districtName'] = $this->GetRegionName($value['district']);
            }

            // 若没有默认收货地址则默认第一个地址为默认地址
            if (empty($default_id)) {
                $default_id = $list[0]['addr_id'];
                $where      = [
                    'addr_id'  => $default_id,
                    'users_id' => $this->users_id
                ];
                $update     = [
                    'is_default'  => 1,
                    'update_time' => getTime()
                ];
                Db::name('shop_address')->where($where)->update($update);
            }
        }

        // 返回数据
        $ReturnData = [
            'list'       => $list,
            'default_id' => $default_id
        ];

        $this->success('查询成功', null, $ReturnData);
    }

    // 获取单条收货地址
    public function GetFindAddrDetail($post = [], $users = [])
    {
        if (!empty($users['address_1588820149'])) {
            $ResultData['detail'] = [];
            foreach ($users['address_1588820149'] as $key => $value) {
                if ($post['addr_id'] == $value['addr_id']) {
                    $ResultData['detail'] = $value;
                    break;
                }
            }
            if (!empty($ResultData)) {
                // 查询地址名称
                $ProvinceName = $this->ProcessingProvince($this->GetRegionName($ResultData['detail']['province']));
                $CityName     = $this->GetRegionName($ResultData['detail']['city']);
                $DistrictName = $this->GetRegionName($ResultData['detail']['district']);
                // 拼装地址
                $region                         = $ProvinceName . ',' . $CityName . ',' . $DistrictName;
                $ResultData['detail']['region'] = [
                    'province' => $ProvinceName,
                    'city'     => $CityName,
                    'region'   => $DistrictName
                ];
                $ResultData['region']           = explode(',', $region);


                // 查询地址名称
                $ProvinceName = $this->GetRegionName($ResultData['detail']['province']);
                // 拼装地址
                $regionNew                         = $ProvinceName . ',' . $CityName . ',' . $DistrictName;
                $ResultData['detail']['regionNew'] = [
                    'province' => $ProvinceName,
                    'city'     => $CityName,
                    'region'   => $DistrictName
                ];
                $ResultData['regionNew']           = explode(',', $regionNew);

                $this->success('查询成功', null, $ResultData);
            }
        }
        $this->error('数据错误');
    }

    // 添加单条收货地址
    public function FindAddAddr($post = [], $users_id = null)
    {
        if (!empty($post['name']) && !empty($post['phone']) && !empty($post['region']) && !empty($users_id)) {
            /* 微信地址名称换取本站地址ID */
            $AddrData = $this->GetAddrData($post['region']);
            /* END */
            $AddData  = [
                'users_id'    => $users_id,
                'consignee'   => $post['name'],
                'country'     => 1,
                'province'    => $AddrData['province'],
                'city'        => $AddrData['city'],
                'district'    => $AddrData['district'],
                'address'     => $post['detail'],
                'mobile'      => $post['phone'],
                'lang'        => get_home_lang(),
                'add_time'    => getTime(),
                'update_time' => getTime()
            ];
            $ResultID = Db::name('shop_address')->add($AddData);
            if (!empty($ResultID)) $this->success('添加成功');
        }
        $this->error('数据错误');
    }

    // 编辑单条收货地址
    public function FindEditAddr($post = [], $users_id = null)
    {
        if (!empty($post['name']) && !empty($post['phone']) && !empty($post['region']) && !empty($users_id)) {
            /* 微信地址名称换取本站地址ID */
            $AddrData = $this->GetAddrData($post['region']);
            /* END */
            $UpData   = [
                'users_id'    => $users_id,
                'consignee'   => $post['name'],
                'country'     => 1,
                'province'    => $AddrData['province'],
                'city'        => $AddrData['city'],
                'district'    => $AddrData['district'],
                'address'     => $post['detail'],
                'mobile'      => $post['phone'],
                'update_time' => getTime()
            ];
            $ResultID = Db::name('shop_address')->where('addr_id', $post['addr_id'])->update($UpData);
            if (!empty($ResultID)) $this->success('编辑成功');
        }
        $this->error('数据错误');
    }

    // 设置默认地址
    public function SetDefaultAddr($post = [], $users_id = null)
    {
        if (empty($post['addr_id']) || empty($users_id)) $this->error('请刷新重试');
        /*设置默认地址*/
        $where    = [
            'addr_id'  => $post['addr_id'],
            'users_id' => $users_id
        ];
        $update   = [
            'is_default'  => 1,
            'update_time' => getTime()
        ];
        $ResultID = Db::name('shop_address')->where($where)->update($update);
        /* END */
        if (!empty($ResultID)) {
            /*将其他地址设置为非默认地址*/
            $where  = [
                'addr_id'  => ['NEQ', $post['addr_id']],
                'users_id' => $users_id
            ];
            $update = [
                'is_default'  => 0,
                'update_time' => getTime()
            ];
            Db::name('shop_address')->where($where)->update($update);
            /* END */
            $this->success('更新成功');
        }
        $this->error('更新失败，请刷新重试');
    }

    // 删除单条收货地址
    public function FindDelAddr($post = [], $users_id = null)
    {
        if (!empty($post['addr_id']) && !empty($users_id)) {
            $where    = [
                'users_id' => $users_id,
                'addr_id'  => $post['addr_id']
            ];
            $ResultID = Db::name('shop_address')->where($where)->delete();
            if (!empty($ResultID)) $this->success('操作成功');
        }
        $this->error('数据错误');
    }

    // 微信地址名称换取本站地址ID
    private function GetAddrData($region = null)
    {
        $region                 = explode(',', $region);
        $province               = substr($region[0], 0, 6); // 截取前两个汉字
        $ResultData['province'] = $this->GetRegionID(1, $province);
        $ResultData['city']     = $this->GetRegionID(2, $region[1], $ResultData['province']);
        $ResultData['district'] = $this->GetRegionID(3, $region[2], $ResultData['city']);

        return $ResultData;
    }

    // 微信地址名称换取本站地址ID
    private function GetRegionID($level = 1, $name = 1, $parent_id = 0)
    {
        $old_name = $name;

        // 当区域名称大于2两个字是，就去除末尾指定的字
        if ($level > 1 && strlen($name) >= 9) {
            $name = preg_replace('/(省|市|县|区|乡|镇)$/i', '', $name);
        }

        $where = [
            'level' => $level,
            'parent_id' => $parent_id,
            'name'  => ['LIKE', "{$name}%"],
        ];
        $id = Db::name('region')->where($where)->getField('id');
        if (empty($id) && 2 <= $level) {
            $addData = [
                'name'  => $old_name,
                'level' => $level,
                'parent_id' => $parent_id,
                'initial'   => getFirstCharter($old_name),
            ];
            $id = Db::name('region')->insertGetId($addData);
        }

        return $id;
    }

    // 地址ID换取地址名称
    private function GetRegionName($id = 1)
    {
        $name = Db::name('region')->where('id', $id)->getField('name');
        return $name;
    }

    // 特殊地区处理
    private function ProcessingProvince($province = null)
    {
        if (empty($province)) return $province;
        if ('山西' == $province) {
            $province = '山西省';
        } else if ('内蒙古' == $province) {
            $province = '内蒙古自治区';
        } else if ('广西' == $province) {
            $province = '广西壮族自治区';
        } else if ('西藏' == $province) {
            $province = '西藏自治区';
        } else if ('宁夏' == $province) {
            $province = '宁夏回族自治区';
        } else if ('新疆' == $province) {
            $province = '新疆维吾尔族自治区';
        } else if ('香港' == $province) {
            $province = '香港特别行政区';
        } else if ('澳门' == $province) {
            $province = '澳门特别行政区';
        } else if ('台湾' == $province) {
            $province = '台湾省';
        }
        return $province;
    }

    /* 收货地址--END */

    /**
     * 获取订单总数
     * @param $user
     * @param string $type
     * @return int|string
     * @throws \think\Exception
     */
    public function getOrderCount($users, $type = 'all')
    {
        if ($users === false) {
            return false;
        }
        // 筛选条件
        $filter = [];
        // 订单数据类型
        switch ($type) {
            case 'all': // 全部订单
                break;
            case 'payment'; // 待付款订单
                $filter['order_status'] = 0;
                break;
            case 'delivery'; // 待发货
                $filter['order_status'] = 1;
                break;
            case 'received'; // 待收货订单
                $filter['order_status'] = 2;
                break;
            case 'complete'; // 已完成
                $filter['order_status'] = 3;
                break;
        }

        $filter['users_id'] = $users['users_id'];
        $filter['lang']     = get_home_lang();
        $count              = Db::name('shop_order')
            ->where($filter)
            ->count();

        return $count;
    }

    /**
     * 用户中心订单列表
     * @param $users_id
     * @param string $type
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function getOrderList($users_id = 0, $type = 'all')
    {
        // 筛选条件
        $where = [
            'lang' => get_home_lang(),
            'users_id' => intval($users_id),
        ];
        // 订单数据类型
        switch ($type) {
            case 'all': // 全部
                break;
            case 'payment'; // 待付款
                $where['order_status'] = 0;
                break;
            case 'delivery'; // 待发货
                $where['order_status'] = 1;
                break;
            case 'received'; // 待收货
                $where['order_status'] = 2;
                break;
            case 'complete'; // 待评价
                $where['order_status'] = 3;
                // $where['is_comment'] = 0;
                break;
            case 'close'; // 订单关闭
                $where['order_status'] = -1;
                break; 
        }
        $result = Db::name('shop_order')->where($where)->order('order_id desc')->paginate(15, false, ['query' => request()->param()]);
        !empty($result) && $result = $result->toArray();
        $order_ids = get_arr_column($result['data'], 'order_id');

        // 订单商品详情
        $orderGoods = [];
        $where = [
            'users_id' => intval($users_id),
            'order_id' => ['IN', $order_ids],
        ];
        $goodsList = Db::name('shop_order_details')->where($where)->order('order_id desc')->select();

        // 安装积分商城插件则执行
        if (!empty($goodsList)) {
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                // 积分商城插件积分订单商品详情处理
                $pointsOrderModel = new \app\plugins\model\PointsOrder();
                $pointsGoodsBuyField = $pointsOrderModel->pointsGoodsOrderDetails($goodsList);
            }
        }

        foreach ($goodsList as $key => $_goods) {
            $_goods['product_price'] = unifyPriceHandle($_goods['product_price']);
            // 获取订单商品规格列表(购买时的商品规格)
            $_goods['product_spec_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($_goods);
            $_goods['litpic'] = $this->get_default_pic($_goods['litpic'], true);
            $orderGoods[$_goods['order_id']][] = $_goods;
        }

        // 模型名称
        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        $channeltypeInfo = !empty($channeltype_row[2]) ? $channeltype_row[2] : [];
        $channel_ntitle  = !empty($channeltypeInfo['ntitle']) ? $channeltypeInfo['ntitle'] : '商品';

        $pay_method_arr = config('global.pay_method_arr');
        $order_status_arr = config('global.order_status_arr');
        foreach ($result['data'] as $key => &$_order) {
            $_order['shipping_fee'] = unifyPriceHandle($_order['shipping_fee']);
            $_order['order_amount'] = unifyPriceHandle($_order['order_amount']);
            $_order['order_total_amount'] = unifyPriceHandle($_order['order_total_amount']);
            $_order['pay_name_text']     = !empty($pay_method_arr[$_order['pay_name']]) ? $pay_method_arr[$_order['pay_name']] : '未支付';
            $_order['order_status_text'] = $order_status_arr[$_order['order_status']];
            $_order['add_time']          = MyDate('Y-m-d H:i:s', $_order['add_time']);
            $_order['goods']             = !empty($orderGoods[$_order['order_id']]) ? $orderGoods[$_order['order_id']] : [];
            $_order['channel_ntitle']    = '商品'; // $channel_ntitle;
            $_order['pointsGoodsBuyField'] = !empty($pointsGoodsBuyField[$_order['order_id']]) ? $pointsGoodsBuyField[$_order['order_id']] : [];
        }
        return $result;
    }

    // 取消订单
    public function orderCancel($order_id, $users_id)
    {
        // 更新条件
        $Where = [
            'order_id' => $order_id,
            'users_id' => $users_id,
        ];
        // 更新数据
        $Data = [
            'order_status' => -1,
            'update_time'  => getTime(),
        ];
        // 更新订单主表
        $return = Db::name('shop_order')->where($Where)->update($Data);
        if (!empty($return)) {
            //还原优惠券
            $use_id = Db::name('shop_order')->where($Where)->value('use_id');
            if (!empty($use_id)){
                $coupon_use_table = 'shop_coupon_use';
                $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
                if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
                    $coupon_use_table = 'weapp_coupons_use';
                }

                $coupon_use = Db::name($coupon_use_table)->where(['use_id'=>$use_id])->find();
                if (!empty($coupon_use)){
                    if ($coupon_use['start_time'] <= getTime() && $coupon_use['end_time'] >= getTime()){
                        $use_update = ['use_status'=>0];
                    }else{
                        $use_update = ['use_status'=>2];
                    }
                    $use_update['use_time'] = 0;
                    $use_update['update_time'] = getTime();
                    Db::name($coupon_use_table)->where(['use_id'=>$use_id,'users_id' => $users_id])->update($use_update);
                }
            }

            //查询是否是秒杀订单
            $order_info = Db::name('shop_order')->where($Where)->find();
            $productSpecValue = new \app\user\model\ProductSpecValue;
            if (20 == $order_info['order_source']){
                $productSpecValue->SaveSharpStock($order_id, $users_id,$order_info);
            }else{
                // 订单取消，还原单内产品数量
                $productSpecValue->SaveProducSpecValueStock($order_id, $users_id);
            }

            // 如果安装了秒杀插件则执行
            if (is_dir('./weapp/Seckill/')) {
                $SeckillRow = model('Weapp')->getWeappList('Seckill');
                //is_seckill_order 只有安装了秒杀插件 shop_order表才会有这个字段
                if (!empty($SeckillRow) && 1 == intval($SeckillRow['status']) && !empty($order['is_seckill_order'])) {
                    // 调用秒杀逻辑层方法
                    $weappSeckillLogic = new \weapp\Seckill\logic\SeckillLogic;
                    $weappSeckillLogic->cancelOrderHandle($order_id, $users_id);
                }
            }
            // 添加订单操作记录
            AddOrderAction($order_id, $users_id, 0, 0, 0, 0, '订单取消！', '会员关闭订单！');
            $this->success('订单取消成功！');
        }
    }

    // 订单提醒发货
    public function orderRemind($order_id = 0, $users_id = 0)
    {
         $post = input('post.');
        // 添加订单操作记录
        AddOrderAction($order_id, $users_id, 0, 1, 0, 1, '提醒成功！', '会员提醒管理员及时发货！');
        $this->success('提醒成功');
    }

    // 确认收货
    public function orderReceipt($order_id, $users_id)
    {
        // 更新条件
        $where = [
            'order_id' => $order_id,
            'users_id' => $users_id,
        ];
        $order = Db::name('shop_order')->where($where)->find();
        $orderStatus = empty($order['order_status']) ? 0 : intval($order['order_status']);
        if (2 === intval($orderStatus)) {
            // 更新数据
            $update = [
                'order_status' => 3,
                'confirm_time' => getTime(),
                'update_time'  => getTime(),
            ];
            // 更新订单主表
            $return = Db::name('shop_order')->where($where)->update($update);
            if (!empty($return)) {
                // 更新数据
                $update = [
                    'update_time' => getTime(),
                ];
                // 更新订单明细表
                Db::name('shop_order_details')->where($where)->update($update);

                // 添加订单操作记录
                AddOrderAction($order_id, $users_id, 0, 3, 1, 1, '确认收货！', '会员已确认收到货物，订单完成！');

                // 如果安装了分销插件则执行
                if (is_dir('./weapp/DealerPlugin/')) {
                    // 开启分销插件则执行
                    $data = model('Weapp')->getWeappList('DealerPlugin');
                    if (!empty($data['status']) && 1 == $data['status']) {
                        // 调用分销逻辑层方法
                        $dealerCommonLogic = new \weapp\DealerPlugin\logic\DealerCommonLogic;
                        $dealerCommonLogic->dealerOrderSettlementExecute($order_id, $users_id);
                    }
                }

                // 如果安装了秒杀插件则执行
                if (is_dir('./weapp/Seckill/')) {
                    $SeckillRow = model('Weapp')->getWeappList('Seckill');
                    //is_seckill_order 只有安装了秒杀插件 shop_order表才会有这个字段
                    if (!empty($SeckillRow) && 1 == intval($SeckillRow['status']) && !empty($order['is_seckill_order'])) {
                        // 调用秒杀逻辑层方法
                        $weappSeckillLogic = new \weapp\Seckill\logic\SeckillLogic;
                        $weappSeckillLogic->confirmOrderHandle($order_id, $users_id);
                    }
                }
                $this->success('确认收货成功');
            } else {
                $this->error('101:订单异常，请刷新重试');
            }
        } else {
            $this->error('102:订单异常，请刷新重试');
        }
    }

    /**
     * 订单详情
     * @param $order_id
     * @param null $user_id
     * @return null|static
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    public function getOrderDetail($order_id, $users_id)
    {
        // 公共条件
        $Where = [
            'order_id' => $order_id,
            'users_id' => $users_id,
        ];
        // 订单主表
        $result = Db::name("shop_order")->where($Where)->find();
        if (empty($result)) {
            $this->error('订单不存在！');
        }

        // 优化显示金额
        $result['coupon_price'] = unifyPriceHandle($result['coupon_price']);
        $result['shipping_fee'] = unifyPriceHandle($result['shipping_fee']);
        $result['order_amount'] = unifyPriceHandle($result['order_amount']);
        $result['order_total_amount'] = unifyPriceHandle($result['order_total_amount']);
        // 模型名称
        $channeltype_row          = \think\Cache::get('extra_global_channeltype');
        $channeltypeInfo          = !empty($channeltype_row[2]) ? $channeltype_row[2] : [];
        $result['channel_ntitle'] = '商品'; // !empty($channeltypeInfo['ntitle']) ? $channeltypeInfo['ntitle'] : '商品';
        // 获取订单状态
        $order_status_arr            = config('global.order_status_arr');
        $result['order_status_text'] = $order_status_arr[$result['order_status']];
        // 获取订单支付方式
        $pay_method_arr          = config('global.pay_method_arr');
        $result['pay_name_text'] = !empty($pay_method_arr[$result['pay_name']]) ? $pay_method_arr[$result['pay_name']] : '未支付';
        // 收货地址
        $result['province_text'] = $this->GetRegionName($result['province'], 1);
        $result['city_text']     = $this->GetRegionName($result['city'], 2);
        $result['district_text'] = $this->GetRegionName($result['district'], 3);
        // 下单时间
        $result['add_time'] = MyDate('Y-m-d H:i:s', $result['add_time']);
        // 订单总金额
        $result['TotalAmount'] = 0.00;
        // 订单明细表
        $result['goods'] = Db::name("shop_order_details")->order('product_price desc, product_name desc')->where($Where)->select();
        // 订单操作记录
        $result['orderLog'] = Db::name('shop_order_log')->where('order_id', $order_id)->order('action_id desc')->select();
        foreach ($result['orderLog'] as $key => $value) {
            $result['orderLog'][$key]['add_time'] = MyDate('Y-m-d H:i:s', $value['add_time']);
        }

        // 安装积分商城插件则执行
        $result['pointsGoodsBuyField'] = [];
        if (!empty($result['goods'])) {
            $weappInfo = model('ShopPublicHandle')->getWeappPointsShop();
            if (!empty($weappInfo)) {
                // 积分商城插件积分订单商品详情处理
                $pointsOrderModel = new \app\plugins\model\PointsOrder();
                $pointsGoodsBuyField = $pointsOrderModel->pointsGoodsOrderDetails($result['goods']);
                $result['pointsGoodsBuyField'] = !empty($pointsGoodsBuyField[$result['order_id']]) ? $pointsGoodsBuyField[$result['order_id']] : [];
            }
        }

        // 安装订单核销插件则执行
        $result['verify'] = [];
        if (!empty($result['verify_id'])) {
            $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
            if (!empty($weappInfo)) {
                // 订单核销插件核销订单商品详情处理
                $verifyModel = new \app\plugins\model\Verify();
                $result['verify'] = $verifyModel->verifyOrderDetails($result);
            }
        }

        //虚拟商品拼接回复
        $virtual_delivery = '';
        // 虚拟发货
        $virtual_delivery_status = false;
        // 商品处理
        foreach ($result['goods'] as $key => $value) {
            $result['prom_type']  = $value['prom_type'];
            $result['goods'][$key]['product_price'] = unifyPriceHandle($value['product_price']);
            $result['goods'][$key]['product_spec_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($value);

            //虚拟需要自动发货的商品卖家回复拼接
            if ($value['prom_type'] == 2 && 2 <= intval($result['order_status'])) {
                $virtual_delivery_status = true;
                //查询商品名称
                // $product_title = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                //查网盘信息
                $netdisk = Db::name("product_netdisk")->where('aid', $value['product_id'])->find();
                if ($netdisk) {
                    // $virtual_delivery .= "商品标题：" . $product_title . "";
                    $virtual_delivery .= "网盘地址：" . $netdisk['netdisk_url'] . "";
                    if (!empty($netdisk['netdisk_pwd'])) {
                        $virtual_delivery .= "提取码：" . $netdisk['netdisk_pwd'] . "";
                    }
                    if (!empty($netdisk['unzip_pwd'])) {
                        $virtual_delivery .= "解压密码：" . $netdisk['unzip_pwd'] . "";
                    }
                    $virtual_delivery .= "--------------------";
                }
                $result['netdisk'] =  $netdisk;
            } else if ($value['prom_type'] == 3 && 2 <= intval($result['order_status'])) {
                $virtual_delivery_status = true;
                //查询商品名称
                // $product_title = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                //查网盘信息
                $netdisk = Db::name("product_netdisk")->where('aid', $value['product_id'])->find();
                if ($netdisk) {
                    // $virtual_delivery .= "商品标题：" . $product_title . "";
                    $virtual_delivery .= "文本内容：" . $netdisk['text_content'] . "";
                    $virtual_delivery .= "--------------------";
                }
                $result['netdisk'] =  $netdisk;
            } else if ($value['prom_type'] == 1) {
                $virtual_delivery_status = true;
                //查询商品名称
                if (!empty($result['virtual_delivery'])) {
                    // $product_title    = Db::name('archives')->where('aid', $value['product_id'])->getField('title');
                    // $virtual_delivery .= "商品标题：" . $product_title . "";
                    $virtual_delivery .= filter_line_return($result['virtual_delivery'], '') . "";
                    $virtual_delivery .= "--------------------";
                }
            }
            $result['virtual_delivery_status'] = $virtual_delivery_status;
            if ($virtual_delivery_status && !empty($virtual_delivery)) $result['virtual_delivery'] = $virtual_delivery;

            // 商品属性处理
            $ValueData  = unserialize($value['data']);
            $spec_value = !empty($ValueData['spec_value']) ? htmlspecialchars_decode($ValueData['spec_value']) : '';
            $spec_value = !empty($spec_value) ? htmlspecialchars_decode($spec_value) : '';
            // 旧参数+规格值
            // $attr_value                    = !empty($ValueData['attr_value']) ? htmlspecialchars_decode($ValueData['attr_value']) : '';
            // $attr_value                    = htmlspecialchars_decode($attr_value);
            // $spec_data                     = $spec_value . $attr_value;
            // $spec_data                     = str_replace('<br/>', '；', $spec_data);
            // $result['goods'][$key]['data'] = trim($spec_data, '；');
            $result['goods'][$key]['data'] = trim(str_replace('<br/>', '；', $spec_value), '；');
            // 新参数+规格值
            // $attr_value_new                    = !empty($ValueData['attr_value_new']) ? htmlspecialchars_decode($ValueData['attr_value_new']) : '';
            // $attr_value_new                    = htmlspecialchars_decode($attr_value_new);
            // $new_spec_data                     = $spec_value . $attr_value_new;
            // $new_spec_data                     = str_replace('<br/>', '；', $new_spec_data);
            // $result['goods'][$key]['new_data'] = trim($new_spec_data, '；');
            $result['goods'][$key]['new_data'] = trim(str_replace('<br/>', '；', $spec_value), '；');

            // 图片处理
            $result['goods'][$key]['litpic'] = $this->get_default_pic($value['litpic'], true);
            // 小计
            $result['goods'][$key]['subtotal'] = $value['product_price'] * $value['num'];
            $result['goods'][$key]['subtotal'] = unifyPriceHandle($result['goods'][$key]['subtotal']);
            // 合计金额
            $result['TotalAmount'] += $result['goods'][$key]['subtotal'];
            $result['TotalAmount'] = unifyPriceHandle($result['TotalAmount']);
        }
        // 自动关闭未付款订单时间
        $orderUnpayCloseTime = getUsersConfigData('order.order_unpay_close_time');
        $result['orderUnpayCloseTime'] = !empty($orderUnpayCloseTime) ? intval($orderUnpayCloseTime) / 60 : 0;

        return $result;
    }

    /**
     * 获取评价订单商品列表
     * @param $order_id
     * @param $users_id
     */
    public function getOrderComment($order_id, $users_id)
    {
        //判断该订单是否已经评价
        $comment_count = Db::name('shop_order_comment')->where(['order_id' => $order_id, 'users_id' => $users_id, 'lang' => get_home_lang()])->count();
        if (!empty($comment_count)) {
            $this->error('该订单已经评价！');
        }
        $order_data = $this->getOrderDetail($order_id, $users_id);

        return $order_data['goods'];
    }

    public function getSaveComment($post, $users_id)
    {
        $order_id = intval($post['order_id']);
        //判断该订单是否已经评价
        $comment_count = Db::name('shop_order_comment')->where(['order_id' => $order_id, 'users_id' => $users_id])->count();
        if (!empty($comment_count)) {
            $this->error('该订单已经评价！');
        }

        if (!empty($post['formData'])) {
            $post['formData'] = json_decode(htmlspecialchars_decode($post['formData']), true);
            $insert = [];
            $detail_ids = [];
            $order_code = Db::name('shop_order')->where(['order_id'=>$order_id])->value('order_code');
            foreach ($post['formData'] as $k => $v) {
                $detail_ids[] = $v['order_details_id'];
                $uploadImg = '';
                if (!empty($v['uploaded'])) {
                    $uploadImg = implode(',', $v['uploaded']);
                } else if (!empty($v['image_list'])) {
                    $uploadImg = implode(',', $v['image_list']);
                }
                $insert[] = [
                    'upload_img'  => serialize($uploadImg),
                    'total_score' => $v['score'],
                    'content'     => serialize($v['content']),
                    'users_id'    => $users_id,
                    'order_id'    => $order_id,
                    'order_code'  => $order_code,
                    'product_id'  => $v['goods_id'],
                    'details_id'  => $v['order_details_id'],
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                ];
            }
            $insert_data = Db::name('shop_order_comment')->insertAll($insert);
            if ($insert_data) {
                Db::name('shop_order')
                    ->where([
                        'order_id'     => $order_id,
                        'users_id'     => $users_id,
                        'order_status' => 3,
                    ])
                    ->update(['is_comment' => 1, 'update_time' => getTime()]);

                if (!empty($detail_ids)) {
                    Db::name('shop_order_details')
                        ->where('details_id', 'in', $detail_ids)
                        ->where('users_id', $users_id)
                        ->update(['is_comment' => 1, 'update_time' => getTime()]);
                }
                \think\Cache::clear('archives');

                $this->success('评价成功！');
            }
        }
        $this->error('评价失败！');
    }

    /**
     * 获取评论列表
     */
    public function getGoodsCommentList($goods_id = 0 ,$score = 0 ,$page = 1 )
    {
        $field='a.*,u.nickname,u.head_pic';
        // 筛选条件
        $condition = [
            'a.product_id' => $goods_id,
            'a.is_show' => 1,
            'a.lang' => get_home_lang(),
        ];
        $score > 0 && $condition['total_score'] = $score;

        $pagesize = empty($pagesize) ? config('paginate.list_rows') : $pagesize;
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        $result = cache($cacheKey);
        if (true || empty($result)) {
            $paginate = array(
                'page'  => $page,
            );
            $pages = Db::name('shop_order_comment')
                ->field($field)
                ->alias('a')
                ->join('users u','a.users_id = u.users_id')
                ->where($condition)
                ->order('a.add_time desc')
                ->paginate($pagesize, false, $paginate);

            $result = $pages->toArray();

            foreach ($result['data'] as $key => $val) {
                if ($val['is_anonymous'] == 1){
                    //匿名用户
                    $val['head_pic'] = get_head_pic();
                    $val['nickname'] = '匿名用户';
                }else{
                    if (empty($val['head_pic'])){
                        $val['head_pic'] = get_head_pic();
                    }
                }
                $val['head_pic'] = get_default_pic($val['head_pic'],true);

                if (isset($val['add_time'])) {
                    $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                }
                if (!empty($val['upload_img'])){
                    $val['upload_img'] = unserialize($val['upload_img']);
                    $val['upload_img'] = explode(',',$val['upload_img']);
                    foreach ($val['upload_img'] as $k => $v){
                        $val['upload_img'][$k] = get_default_pic($v,true);
                    }
                }
                if (!empty($val['content'])){
                    $val['content'] = unserialize($val['content']);
                }
                $result['data'][$key] = $val;
            }
            $score_type = Db::name('shop_order_comment')
                ->where([
                    'product_id' => $goods_id,
                    'is_show' => 1,
                    'lang' => get_home_lang(),
                ])->field('count(*) as count,total_score')
                ->group('total_score')
                ->getAllWithIndex('total_score');
            $result['count']['goods'] = isset($score_type[1]) ? $score_type[1]['count'] : 0;
            $result['count']['middle'] = isset($score_type[2]) ? $score_type[2]['count'] : 0;
            $result['count']['bad'] = isset($score_type[3]) ? $score_type[3]['count'] : 0;
            $result['count']['all'] = $result['count']['goods']+$result['count']['middle']+$result['count']['bad'] ;

            cache($cacheKey, $result, null, 'getGoodsCommentList');
        }
        return $result;
    }

    /**
     * 获取秒杀tabbar
     */
    public function GetSharpTabbar()
    {
        $time = strtotime(date('Y-m-d'));
        $hour = date('H');
        $where['b.status'] = 1;
        $where['a.status'] = 1;
        $where['b.is_del'] = 0;
        //非当日预告
        $active = Db::name('sharp_active_time')
            ->alias('a')
            ->field('a.*,b.active_date')
            ->join('sharp_active b','a.active_id = b.active_id')
            ->where($where)
            ->where('b.active_date', '>',$time)
            ->order('b.active_date asc,a.active_time asc')
            ->select();
        //当天
        $data = Db::name('sharp_active_time')
            ->alias('a')
            ->field('a.*,b.active_date')
            ->join('sharp_active b','a.active_id = b.active_id')
            ->where($where)
            ->where('b.active_date', $time)
            ->where('a.active_time', '>=',$hour)
            ->order('active_time asc')
            ->select();
        if (!empty($data) && !empty($active)){
            $data[] = $active[0];
        }else if (empty($data) && !empty($active)){
            $data[] = $active;
        }
        $date = date('Y-m-d');
        foreach ($data as $k => $v){
            if ($v['active_time'] < 10){
                $v['active_time'] = '0'.$v['active_time'];
            }
            $active_time = $v['active_time'];
            $v['active_time'] = $v['active_time'].':00';
            $date_time = $date.' '.$v['active_time']; //2020-15-2-15 15:00
            $time_plus = strtotime($date_time . "+1 hours"); //2020-15-2-15 15:00
            $time_plus = date('Y-m-d H:i',$time_plus); //2020-15-2-15 16:00
            if ($time == $v['active_date']){
                if ($active_time  ==  $hour){
                    $v['status'] = 10;
                    $arr = ['正在疯抢','已开抢','正在疯抢',$time_plus,$time_plus,$date_time];
                }else if ($active_time > $hour){
                    $v['status'] = 20;
                    $arr = [$v['active_time'].' 场预告','即将开抢','即将开抢',$date_time,$time_plus,$date_time];
                }
            }else{
                $v['status'] = 30;
                $arr = [$date_time.' 开始','预告',$date_time.' 开始',$date_time,$time_plus,$date_time];
            }
            $v['sharp_modular_text'] = $arr[0];
            $v['status_text'] = $arr[1];
            $v['status_text2'] = $arr[2];
            $v['count_down_time'] = $arr[3];
            $v['end_time'] = $arr[4];
            $v['start_time'] = $arr[5];
            $data[$k] = $v;
        }
        return $data;
    }

    /**
     * 获取秒杀列表
     */
    public function GetSharpIndex( $tid = 0 , $page = 1 )
    {
        $pagesize = 10;
        $pagesize = empty($pagesize) ? config('paginate.list_rows') : $pagesize;
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        $result = cache($cacheKey);
        if (true || empty($result)) {
            $paginate = array(
                'page'  => $page,
            );
            $pages = Db::name('sharp_active_goods')
                ->alias('a')
                ->field('c.*,b.litpic,b.title,a.*,b.users_price')
                ->join('archives b','a.aid = b.aid')
                ->join('sharp_goods c','a.sharp_goods_id = c.sharp_goods_id')
                ->where('a.active_time_id',$tid)
                ->paginate($pagesize, false, $paginate);

            $result = $pages->toArray();

            $aids = [];
            foreach ($result['data'] as $k => $v){
                $result['data'][$k]['litpic'] = get_default_pic($v['litpic'],true);
                if (1 == $v['is_sku']){
                    $aids[] = $v['aid'];
                }
            }

            //多规格
            if (!empty($aids)) {
                $sku = Db::name('product_spec_value')
                    ->field('*,min(seckill_price) as seckill_price')
                    ->where('aid', 'in', $aids)
                    ->group('aid')
                    ->getAllWithIndex('aid');
            }
            //sales_actual //
            foreach ($result['data'] as $k => $v){
                if (1 == $v['is_sku'] && !empty($sku[$v['aid']])){
                    $v['seckill_price'] = $sku[$v['aid']]['seckill_price'];
                }
                $v['progress'] = 0;
                if (0 < $v['sales_actual']){
                    $count_stock = $v['sales_actual']+$v['seckill_stock']+$v['virtual_sales'];
                    $v['progress'] = intval(($v['sales_actual']+$v['virtual_sales'])/$count_stock*100);
                }else{
                    if (0 < $v['virtual_sales']){
                        $v['progress'] = intval($v['virtual_sales']/($v['virtual_sales']+$v['seckill_stock'])*100);;
                    }
                }
                if (0 < $v['virtual_sales']){
                    $v['sales_actual'] = $v['sales_actual'] +$v['virtual_sales'];

                }
                $result['data'][$k] = $v;
            }

            cache($cacheKey, $result, null, 'GetSharpIndex');
        }
        return $result;
    }

    /**
     * 获取物流动态信息
     * @param $express_name
     * @param $express_code
     * @param $express_order
     * @return array|bool
     */
    public function orderExpress($express_name, $express_code, $express_order, $timestamp = '')
    {
        $data = [
            'express_name'  => $express_name,
            'express_order' => $express_order
        ];
        // 快递100 请求查询接口
        $data['list'] = $this->orderExpressQuery($express_code, $express_order);
        if ($data['list'] === false) {
            $this->error('没有找到物流信息！');
            return false;
        }
        return $data;
    }

    /**
     * 执行查询
     * @param $express_code
     * @param $express_order
     * @return bool
     */
    private function orderExpressQuery($express_code, $express_order)
    {
        // 缓存索引
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        if ($data = Cache::get($cacheKey)) {
            return $data;
        }

        // 查询物流链接
        empty($timestamp) && $timestamp = getTime();

        $postData   = [
            'postid'   => $express_order,
            'id'       => 1,
            'valicode' => '',
            'temp'     => $timestamp,
            'type'     => $express_code,
            'phone'    => '',
            'token'    => '',
            'platform' => 'MWWW',
            'coname'   => 'indexall',
        ];
        $expressUrl = "https://m.kuaidi100.com/query";
        $response   = httpRequest($expressUrl, 'POST', $postData);
        $express    = json_decode($response, true);
        // 记录错误信息
        if (!isset($express['message']) || $express['message'] != 'ok') {
            $msg = '查询失败';
            $this->error($msg);
            return false;
        }

        // $expressUrl = "https://m.kuaidi100.com/app/query/?com=".$express_code."&nu=".$express_order."&callbackurl=";

        // $mobileExpressUrl = "https://m.kuaidi100.com/index_all.html?type=".$result['express_code']."&postid=".$result['express_order']."&callbackurl=".$ReturnUrl;


        // 记录缓存, 时效5分钟
        Cache::set($cacheKey, $express['data'], 300);
        return $express['data'];
    }

    /**
     * 执行查询
     * @param $express_code
     * @param $express_order
     * @return bool
     */
    private function orderExpressQuery2($express_code, $express_order)
    {
        // 缓存索引
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        if ($data = Cache::get($cacheKey)) {
            return $data;
        }

        $key      = 'anFrVFyu8015'; // '快递100 授权KEY';
        $customer = 'B972EB01B5B32404957BE09C8FE85A51'; // '快递100 Customer';
        // 参数设置
        $postData         = [
            'customer' => $customer,
            'param'    => json_encode([
                'resultv2' => '1',
                'com'      => $express_code,
                'num'      => $express_order
            ])
        ];
        $postData['sign'] = strtoupper(md5($postData['param'] . $key . $postData['customer']));
        // 请求快递100 api
        $url     = 'http://poll.kuaidi100.com/poll/query.do';
        $result  = httpRequest($url, 'POST', $postData);
        $express = json_decode($result, true);
        // 记录错误信息
        if (isset($express['returnCode']) || !isset($express['data'])) {
            $msg = isset($express['message']) ? $express['message'] : '查询失败';
            $this->error($msg);
            return false;
        }
        // 记录缓存, 时效5分钟
        Cache::set($cacheKey, $express['data'], 300);
        return $express['data'];
    }

    /**
     * 构建支付请求的参数
     * @param $users
     * @param $order
     * @param $payType
     * @return array
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    public function onOrderPayment($users, $order, $payType = 20)
    {
        if ($payType == 20) { // 微信支付
            return $this->onPaymentByWechat($users, $order);
        }
        return [];
    }

    /**
     * 构建微信支付请求
     * @param $users
     * @param $order
     * @return array
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    protected function onPaymentByWechat($users, $order)
    {
        // 统一下单API
        if (empty($this->miniproInfo['mchid']) || empty($this->miniproInfo['apikey'])) {
            return [
                'code' => 0,
                'msg'  => '很抱歉，请在网站小程序后台，完善微信支付配置！',
            ];
        }
        $openid  = Db::name('weapp_diyminipro_mall_users')->where(['users_id' => $users['users_id']])->getField('openid');
        $payment = $this->unifiedorder($order['order_code'], $openid, $order['order_amount']);

        return $payment;
    }

    /**
     * 统一下单API
     * @param $order_code
     * @param $openid
     * @param $totalFee
     * @return array
     * @throws BaseException
     */
    public function unifiedorder($order_code, $openid, $totalFee)
    {
        // 当前时间
        $time = getTime();
        // 生成随机字符串
        $nonceStr = md5($time . $openid);

        // API参数
        $params = [
            'appid'            => $this->miniproInfo['appid'],
            'attach'           => '微信小程序支付',
            'body'             => $order_code,
            'mch_id'           => $this->miniproInfo['mchid'],
            'nonce_str'        => $nonceStr,
            'notify_url'       => url('api/v1/Api/wxpay_notify', [], true, true, 1, 1),  // 异步通知地址
            'openid'           => $openid,
            'out_trade_no'     => $order_code,
            'spbill_create_ip' => clientIP(),
            'total_fee'        => $totalFee * 100, // 价格:单位分
            'trade_type'       => 'JSAPI',
        ];
        // 生成签名
        $params['sign'] = model('v1.User')->makeSign($params, $this->miniproInfo['apikey']);
        // 请求API
        $url    = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result = httpRequest($url, 'POST', model('v1.User')->toXml($params));
        $prepay = model('v1.User')->fromXml($result);
        // 请求失败
        if ($prepay['return_code'] === 'FAIL') {
            return [
                'code' => 0,
                'msg'  => "微信支付api：{$prepay['return_msg']}",
            ];
        }
        if ($prepay['result_code'] === 'FAIL') {
            return [
                'code' => 0,
                'msg'  => "微信支付api：{$prepay['err_code_des']}",
            ];
        }
        // 生成 nonce_str 供前端使用
        $paySign = $this->makePaySign($this->miniproInfo['appid'], $params['nonce_str'], $prepay['prepay_id'], $time);
        return [
            'prepay_id' => $prepay['prepay_id'],
            'nonceStr'  => $nonceStr,
            'timeStamp' => (string)$time,
            'paySign'   => $paySign
        ];
    }

    /**
     * 生成paySign
     * @param $nonceStr
     * @param $prepay_id
     * @param $timeStamp
     * @return string
     */
    private function makePaySign($appid, $nonceStr, $prepay_id, $timeStamp)
    {
        $data = [
            'appId'     => $appid,
            'nonceStr'  => $nonceStr,
            'package'   => 'prepay_id=' . $prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => $timeStamp,
        ];
        // 签名步骤一：按字典序排序参数
        ksort($data);
        $string = model('v1.User')->toUrlParams($data);
        // 签名步骤二：在string后加入KEY
        $string = $string . '&key=' . $this->miniproInfo['apikey'];
        // 签名步骤三：MD5加密
        $string = md5($string);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 获取我的优惠券
     */
    public function GetMyCouponList($users_id, $type = 0)
    {
        $coupon_table = 'shop_coupon';
        $coupon_use_table = 'shop_coupon_use';
        $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
        if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
            $coupon_table = 'weapp_coupons';
            $coupon_use_table = 'weapp_coupons_use';
        }

        // 筛选条件
        $filter = [];
        //0-未使用 1-已使用 2-已过期
        $filter['a.use_status'] = $type;
        // 订单数据类型
        switch ($type) {
            case '0': // 未使用
                $filter['a.end_time'] = ['>=',getTime()];
                break;
            case '1'; // 已使用
                break;
            case '2'; // 已过期
                unset($filter['a.use_status']);
                $filter['a.end_time'] = ['<',getTime()];
                break;
        }
        $filter['a.users_id'] = $users_id;
        $filter['b.coupon_id'] = ['>',0];

        $result = Db::name($coupon_use_table)
            ->alias('a')
            ->join("{$coupon_table} b",'a.coupon_id = b.coupon_id','left')
            ->where($filter)
            ->order('a.add_time desc')
            ->paginate(10, false, [
                'query' => request()->param()
            ]);
        !empty($result) && $result = $result->toArray();

        foreach ($result['data'] as $k => $v ) {
            $v['conditions_use'] = floatval($v['conditions_use']);
            if (1 == $v['coupon_form']){
                $v['coupon_form_name'] = '满减券';
            }elseif (2 == $v['coupon_form']){
                $v['coupon_form_name'] = '满折券';
                $v['coupon_discount'] = floatval( $v['coupon_discount']);
            }
            $v['start_time'] = date('Y/m/d',$v['start_time']);
            $v['end_time'] = date('Y/m/d',$v['end_time']);
            switch ($v['coupon_type']) {
                case '1': // 未使用
                    $v['coupon_type_name'] = '全部商品';
                    break;
                case '2'; // 已使用
                    $v['coupon_type_name'] = '指定商品';
                    break;
                case '3'; // 已过期
                    $v['coupon_type_name'] = '指定分类';
                    break;
            }
            $result['data'][$k] = $v;
        }

        return $result;
    }
    /**
     * 领券中心
     */
    public function GetCouponCenter($users_id)
    {
        $coupon_table = 'shop_coupon';
        $coupon_use_table = 'shop_coupon_use';
        $weappInfo = model('ShopPublicHandle')->getWeappInfo("Coupons");
        if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
            $coupon_table = 'weapp_coupons';
            $coupon_use_table = 'weapp_coupons_use';
        }

        // 筛选条件
        $filter = [
            'status' => 1
        ];
        $filter['start_date'] = ['<=',getTime()];
        $filter['end_date'] = ['>=',getTime()];
        $coupon_type = input('param.coupon_type/d');
        if (!empty($coupon_type)) $filter['coupon_type'] = $coupon_type;

        $result = Db::name($coupon_table)
            ->where($filter)
            ->order('sort_order asc,coupon_id desc')
            ->paginate(6, false, [
                'query' => request()->param()
            ]);
        if (!empty($result)) {
            $coupon_ids = [];
            $result = $result->toArray();
            foreach ($result['data'] as $k => $v ) {
                $v['conditions_use'] = floatval($v['conditions_use']);
                $coupon_ids[] = $v['coupon_id'];
                if (1 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满减券';
                }elseif (2 == $v['coupon_form']){
                    $v['coupon_form_name'] = '满折券';
                    $v['coupon_discount'] = floatval( $v['coupon_discount']);
                }
                $v['start_date'] = date('Y/m/d',$v['start_date']);
                $v['end_date'] = date('Y/m/d',$v['end_date']);
                switch ($v['use_type']) {
                    case '1': // 固定期限有效
                        $v['use_start_time'] = date('Y/m/d',$v['use_start_time']);
                        $v['use_end_time'] = date('Y/m/d',$v['use_end_time']);
                        $v['use_type_name'] = $v['use_start_time'].'-'.$v['use_end_time'];
                        break;
                    case '2'; // 领取当天开始有效
                        $v['use_type_name'] = '领取'.$v['valid_days'].'天内有效';
                        break;
                    case '3'; // 领取次日开始有效
                        $v['use_type_name'] =  '领取次日开始'.$v['valid_days'].'天内有效';
                        break;
                }
                $result['data'][$k] = $v;
            }
            $have_where['users_id'] = $users_id;
            $have_where['coupon_id'] = ['in',$coupon_ids];
            $have_where['use_status'] = 0;
            $have = Db::name($coupon_use_table)->where($have_where)->column('coupon_id');
            if (!empty($have)){
                foreach ($result['data'] as $k => $v ) {
                    if (in_array($v['coupon_id'],$have)){
                        $result['data'][$k]['geted'] = 1;//当前还有已领取未使用的
                    }
                }
            }
        }

        return $result;
    }

    // 会员余额记录
    public function getUsersMoneyDetails($users = [], $param = [])
    {
        $where = [
            'status' => ['IN', [2, 3]],
            'users_id' => $param['users_id'],
        ];
        $UsersMoney = Db::name('users_money')->where($where)->order('moneyid desc')->select();
        // 获取金额明细类型
        $pay_cause_type_arr = Config::get('global.pay_cause_type_arr');
        // 获取金额明细状态
        $pay_status_arr = Config::get('global.pay_status_arr');
        foreach ($UsersMoney as $key => $value) {
            $value['status_name'] = $pay_status_arr[$value['status']];
            $value['add_time'] = MyDate('Y-m-d H:i:s', $value['add_time']);
            $value['cause_type_name'] = $pay_cause_type_arr[$value['cause_type']];
            $UsersMoney[$key] = $value;
        }
        $users['MoneyList'] = $UsersMoney;

        // 会员余额首页
        $this->success('查询成功', null, $users);
    }

    // 会员余额充值
    public function getUsersMoneyRecharge($param = [])
    {
        if (empty($param['usersMoney'])) $this->error('请输入充值金额');

        // 记录类型，充值
        $cause_type = 1;
        // 清理当前会员的未支付的充值记录
        $where = [
            'status' => 1,
            'cause_type' => $cause_type,
            'users_id' => $param['users_id'],
        ];
        Db::name('users_money')->where($where)->delete();

        // 查询当前会员的余额
        $where = [
            'users_id' => $param['users_id']
        ];
        $UsersMoney = Db::name('users')->where($where)->getField('users_money');

        // 充值记录数据处理
        $time = getTime();
        // 订单号规则
        $OrderNumber = date('Ymd') . $time . rand(10, 100);
        // 支付类型中文名称
        $pay_cause_type_arr = Config::get('global.pay_cause_type_arr');
        // 记录数据
        $insert = [
            'users_id'      => $param['users_id'],
            'cause_type'    => $cause_type,
            'cause'         => $pay_cause_type_arr[$cause_type],
            'money'         => $param['usersMoney'],
            'users_money'   => $UsersMoney + $param['usersMoney'],
            'pay_method'    => 'wechat',
            'pay_details'   => '',
            'order_number'  => $OrderNumber,
            'status'        => 1,
            'add_time'      => $time
        ];

        $insertID = Db::name('users_money')->insertGetId($insert);
        if (!empty($insertID)) {
            $openid = Db::name('wx_users')->where('users_id', $param['users_id'])->getField('openid');
            $Data = $this->GetWechatAppletsPay($openid, $OrderNumber, $param['usersMoney']);
            $ResultData = [
                'WeChatPay' => $Data,
                'OrderData' => [
                    'moneyid' => $insertID,
                    'order_number' => $OrderNumber
                ]
            ];
            $this->success('正在支付', null, $ResultData);
        } else {
            $this->error('充值失败');
        }
    }

    // 会员余额充值后续处理
    public function getUsersMoneyRechargePay($param = [])
    {
        // 查询订单信息
        $where = [
            'cause_type' => 1,
            'users_id' => $param['users_id'],
            'moneyid' => $param['moneyid'],
            'order_number' => $param['order_number'],
        ];
        $usersMoney = Db::name('users_money')->where($where)->find();
        if (empty($usersMoney)) $this->error('订单错误，刷新重试');
        if (1 !== intval($usersMoney['status'])) $this->error('订单已被处理，刷新重试');

        // 查询会员openid
        $openid = Db::name('wx_users')->where('users_id', $param['users_id'])->getField('openid');

        // 下单确认页数据处理
        $WeChatPay = $this->GetWeChatPayDetails($openid, $param['order_number']);

        // 判断处理订单是否真实支付并处理订单
        if (!empty($WeChatPay) && 'SUCCESS' == $WeChatPay['return_code'] && 'OK' == $WeChatPay['return_msg'] && 'SUCCESS' == $WeChatPay['trade_state']) {
            // 充值订单已支付，将订单更新为已支付
            $update = [
                'status' => 2,
                'pay_details' => serialize($WeChatPay),
                'update_time' => getTime(),
            ];
            $ResultID = Db::name('users_money')->where($where)->update($update);
            if (!empty($ResultID)) {
                // 增加会员余额
                $update = [
                    'users_money' => Db::raw('users_money+'.($usersMoney['money'])),
                ];
                $ResultID = Db::name('users')->where('users_id', $param['users_id'])->update($update);
                if (!empty($ResultID)) {
                    // 将充值订单更新为已完成
                    $update = [
                        'status' => 3,
                        'update_time' => getTime(),
                    ];
                    Db::name('users_money')->where($where)->update($update);

                    //统计余额充值
                    eyou_statistics_data(5, floatval($usersMoney['money']));

                    // 返回结束
                    $this->success('支付完成');
                }
            }
            $this->success('订单处理失败，请联系管理员');
        }
    }

    // 查询会员支付信息详情
    public function GetWeChatPayDetails($openid = 0, $orderCode = 0)
    {
        // 当前时间戳
        $time = getTime();

        // 当前时间戳 + openid 经 MD5加密
        $nonceStr = md5($time . $openid);

        // 调用支付接口参数
        $params = [
            'appid' => $this->miniproInfo['appid'],
            'mch_id' => $this->miniproInfo['mchid'],
            'nonce_str' => $nonceStr,
            'out_trade_no' => $orderCode
        ];

        // 生成参数签名
        $params['sign'] = $this->ParamsSign($params, $this->miniproInfo['apikey']);

        // 生成参数XML格式
        $ParamsXml = $this->ParamsXml($params);

        // 调用接口返回数据
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $result = httpRequest($url, 'POST', $ParamsXml);

        // 解析XML格式
        $ResultData = $this->ResultXml($result);

        return $ResultData;
    }

    // 查询订单内的指定的某个商品，判断是否已申请售后或已评价商品
    public function getOrderGoodsDetect($param = [])
    {
        // 查询商品
        $where = [
            'users_id' => $param['users_id'],
            'order_id' => $param['order_id'],
            'details_id' => $param['details_id'],
            'product_id' => $param['product_id']
        ];
        $field = 'details_id, order_id, users_id, product_id, apply_service, is_comment';
        $orderGoods = Db::name("shop_order_details")->where($where)->field($field)->find();
        if (empty($orderGoods)) $this->error('订单商品不存在');
        $orderGoods['service_id'] = 0;

        // 检测商品是否可以进行下一步操作
        if ('service' == $param['use']) {
            if (!empty($orderGoods['apply_service'])) {
                $where['status'] = ['NEQ', 8];
                $orderGoods['service_id'] = Db::name('shop_order_service')->where($where)->getField('service_id');
            }
        } else if ('comment' == $param['use']) {
            if (!empty($orderGoods['is_comment'])) {
                $url = strval('/pages/article/view?aid=' . $param['product_id']);
                $this->success('订单商品已完成评价', $url, false);
            }
        }

        // 返回信息
        $this->success('检测完成', null, $orderGoods);
    }

    // 查询售后服务单列表
    public function getOrderGoodsServiceList($param = [])
    {
        $where = [
            'users_id' => $param['users_id'],
        ];
        if (!empty($param['serviceStatus']) && 1 == $param['serviceStatus']) {
            $where['status'] = ['IN', [1, 2, 4, 5]];
        } else if (!empty($param['serviceStatus']) && 2 == $param['serviceStatus']) {
            $where['status'] = ['IN', [6, 7]];
        }
        $field = 'service_id, service_type, users_id, product_name, product_img, status, add_time';

        // 分页参数
        $Query = request()->param();
        // 查询订单数据
        $result = Db::name('shop_order_service')->field($field)->where($where)->paginate(15, false, ['query' => $Query]);
        !empty($result) && $serviceData = $result->toArray();

        if (!empty($serviceData['data'])) {
            $orderServiceType = Config::get('global.order_service_type');
            $orderServiceStatus = Config::get('global.order_service_status');
            foreach ($serviceData['data'] as $key => $value) {
                // 处理数据
                $value['add_time'] = MyDate('Y-m-d H:i:s', $value['add_time']);
                $value['product_img'] = $this->get_default_pic($value['product_img'], true);
                $value['status_name'] = $orderServiceStatus[$value['status']];
                $value['service_type_name'] = $orderServiceType[$value['service_type']];
                // 重载数据
                $serviceData['data'][$key] = $value;
            }
        }

        $this->success('查询成功', null, $serviceData);
    }

    // 查询申请售后的订单商品
    public function getOrderGoodsService($param = [])
    {
        $serviceData = $serviceDataLog = $expressData = [];
        if (!empty($param['service_id'])) {
            // 如果存在则查询售后服务订单
            $where = [
                'users_id' => $param['users_id'],
                'service_id' => $param['service_id'],
            ];
            $serviceData = Db::name('shop_order_service')->where($where)->find();
            if (empty($serviceData)) $this->error('售后订单不存在');
            $uploadImg = explode(',', $serviceData['upload_img']);
            foreach ($uploadImg as $key => $value) {
                if (!empty($value)) {
                    $uploadImg[$key] = $this->get_default_pic($value, true);
                } else {
                    unset($serviceImg[$key]);
                }
            }
            $serviceData['upload_img'] = $uploadImg;
            $serviceData['add_time'] = MyDate('Y-m-d H:i:s', $serviceData['add_time']);
            $serviceData['product_img'] = $this->get_default_pic($serviceData['product_img'], true);
            $serviceData['status_name'] = Config::get('global.order_service_status')[$serviceData['status']];
            $serviceData['service_type_name'] = Config::get('global.order_service_type')[$serviceData['service_type']];
            $serviceData['admin_delivery'] = !empty($serviceData['admin_delivery']) ? unserialize($serviceData['admin_delivery']) : '';
            $serviceData['users_delivery'] = !empty($serviceData['users_delivery']) ? unserialize($serviceData['users_delivery']) : '';

            // 查询售后订单流程记录
            $where = [
                'service_id' => $param['service_id'],
            ];
            $serviceDataLog = Db::name('shop_order_service_log')->order('log_id desc')->where($where)->select();
            foreach ($serviceDataLog as $key => $value) {
                if (!empty($value['users_id'])) {
                    $serviceDataLog[$key]['name'] = '会员';
                } else if (!empty($value['admin_id'])) {
                    $serviceDataLog[$key]['name'] = '商家';
                }
                $serviceDataLog[$key]['add_time'] = MyDate('Y-m-d H:i:s', $value['add_time']);
            }

            // 物流数据表
            $expressData = Db::name('shop_express')->select();

            // 追加参数用于查询
            $param['order_id'] = $serviceData['order_id'];
            $param['product_id'] = $serviceData['product_id'];
            $param['details_id'] = $serviceData['details_id'];
        }

        // 查询商品
        $where = [
            'a.users_id' => $param['users_id'],
            'a.order_id' => $param['order_id'],
            'a.details_id' => $param['details_id'],
            'a.product_id' => $param['product_id']
        ];
        $field = 'a.users_id, a.order_id, b.order_code, a.details_id, a.product_id, a.product_name, a.product_price, a.num as product_num, a.litpic, a.apply_service, b.pay_time, b.consignee, b.mobile, b.province, b.city, b.district, b.address';
        $orderGoods = Db::name("shop_order_details")->alias('a')->join('__SHOP_ORDER__ b', 'a.order_id = b.order_id', 'LEFT')->where($where)->field($field)->find();
        if (empty($orderGoods)) $this->error('订单商品不存在');

        // 处理商品信息
        $orderGoods['litpic'] = $this->get_default_pic($orderGoods['litpic'], true);
        $orderGoods['pay_time'] = MyDate('Y-m-d H:i:s', $orderGoods['pay_time']);
        $Province = $this->GetRegionName($orderGoods['province']);
        $City     = $this->GetRegionName($orderGoods['city']);
        $District = $this->GetRegionName($orderGoods['district']);
        $orderGoods['address'] = $Province . ' ' . $City . ' ' . $District . ' ' . $orderGoods['address'];

        // 后台设置的商家收货地址
        $orderGoods['admin_addr'] = getUsersConfigData('addr');

        // 物流数据
        $orderGoods['express'] = $expressData;

        // 售后服务订单
        $orderGoods['service'] = $serviceData;
        $orderGoods['service_log'] = $serviceDataLog;

        $this->success('查询成功', null, $orderGoods);
    }

    // 添加申请售后的订单商品入库
    public function addOrderGoodsService($param = [])
    {
        // 解析参数
        $formData = htmlspecialchars_decode($param['formData']);
        $formData = json_decode(htmlspecialchars_decode($formData), true);

        // 查询是否已申请售后
        $where = [
            'status' => ['NEQ', 8],
            'users_id' => $param['users_id'],
            'order_id' => $formData['order_id'],
            'product_id' => $formData['product_id'],
            'details_id' => $formData['details_id']
        ];
        $IsCount = Db::name('shop_order_service')->where($where)->count();
        if (!empty($IsCount)) $this->error('订单商品已申请售后');

        $time = getTime();
        $addService = [
            'service_type' => !empty($formData['service_type']) ? intval($formData['service_type']) : 0,
            'users_id' => intval($param['users_id']),
            'order_id' => !empty($formData['order_id']) ? intval($formData['order_id']) : 0,
            'order_code' => !empty($formData['order_code']) ? strval($formData['order_code']) : 0,
            'details_id' => !empty($formData['details_id']) ? intval($formData['details_id']) : 0,
            'product_id' => !empty($formData['product_id']) ? intval($formData['product_id']) : 0,
            'product_name' => !empty($formData['product_name']) ? strval($formData['product_name']) : '',
            'product_num' => !empty($formData['product_num']) ? intval($formData['product_num']) : 0,
            'product_img' => !empty($formData['litpic']) ? strval($formData['litpic']) : '',
            'content' => !empty($formData['content']) ? htmlspecialchars($formData['content']) : '',
            'upload_img' => !empty($formData['uploaded'][0]) ? implode(',', $formData['uploaded']) : '',
            'address' => strval($formData['address']),
            'consignee' => !empty($formData['consignee']) ? $formData['consignee'] : '',
            'mobile' => !empty($formData['mobile']) ? $formData['mobile'] : 0,
            'refund_price' => !empty($formData['product_price']) ? $formData['product_price'] : 0,
            'refund_code' => 'HH' . $time . rand(10,100),
            'add_time'    => $time,
            'update_time' => $time,
        ];
        if (2 == $addService['service_type'] && !empty($addService['refund_price'])) $addService['refund_code'] = 'TK' . $time . rand(10,100);
        $ResultID = Db::name('shop_order_service')->insertGetId($addService);

        // 申请售后后续操作
        if (!empty($ResultID)) {
            // 更新订单明细表中对应商品为申请服务
            $update = [
                'details_id' => $addService['details_id'],
                'apply_service' => 1,
                'update_time' => getTime()
            ];
            Db::name('shop_order_details')->update($update);

            // 添加订单服务记录
            $LogNote = 1 == $addService['service_type'] ? '会员提交换货申请，待管理员审核！' : '会员提交退货申请，待管理员审核！';
            OrderServiceLog($ResultID, $addService['order_id'], $addService['users_id'], 0, $LogNote);

            // 申请成功返回
            $this->success('已申请，待审核');
        } else {
            $this->error('申请售后失败');
        }
    }

    // 取消售后订单会员邮寄物流信息
    public function cancelOrderGoodsService($param = [])
    {
        // 取消服务单
        $where = [
            'users_id' => $param['users_id'],
            'service_id' => $param['service_id']
        ];
        $update = [
            'status' => 8,
            'update_time' => getTime(),
        ];
        $ResultID = Db::name('shop_order_service')->where($where)->update($update);
        if (!empty($ResultID)) {
            // 更新订单明细表中对应商品为未申请服务
            $where = [
                'users_id' => $param['users_id'],
                'details_id' => $param['details_id']
            ];
            $update = [
                'apply_service' => 0,
                'update_time' => getTime()
            ];
            Db::name('shop_order_details')->where($where)->update($update);

            // 添加记录单
            $param['status'] = 8;
            $this->addOrderServiceLog($param);
            $this->success('取消成功');
        } else {
            $this->error('取消失败');
        }
    }

    // 添加售后订单会员邮寄物流信息
    public function addServiceUsersDelivery($param = [])
    {
        // 解析物流参数
        $usersDelivery = htmlspecialchars_decode($param['usersDelivery']);
        $usersDelivery = json_decode(htmlspecialchars_decode($usersDelivery), true);

        // 更新处理
        $where = [
            'users_id' => $param['users_id'],
            'service_id' => $param['service_id'],
        ];
        $update = [
            'status' => 4,
            'users_delivery' => !empty($usersDelivery) ? serialize($usersDelivery) : '',
            'update_time' => getTime(),
        ];
        $ResultID = Db::name('shop_order_service')->where($where)->update($update);
        if (!empty($ResultID)) {
            // 添加记录单
            $param['status'] = 4;
            $this->addOrderServiceLog($param);
            $this->success('提交物流成功');
        } else {
            $this->error('提交物流失败');
        }
    }

    // 记录商品退换货服务单信息
    private function addOrderServiceLog($param = [])
    {
        if (empty($param)) return false;

        if (2 == $param['status']) {
            $LogNote = '商家通过申请，等待会员将货物寄回商家！';
        } else if (3 == $param['status']) {
            $LogNote = '商家拒绝申请，请联系商家处理！';
        } else if (4 == $param['status']) {
            $LogNote = '会员已将货物发出，等待商家收货！';
        } else if (5 == $param['status']) {
            $LogNote = '商家已收到货物，待管理员进行退换货处理！';
        } else if (6 == $param['status']) {
            $LogNote = '商家已将新货物重新发出，换货完成，服务结束！';
        } else if (7 == $param['status']) {
            $LogNote = '商家已将金额、余额、积分退回，退款完成，服务结束！';
        } else if (8 == $param['status']) {
            $LogNote = '服务单被取消，服务结束！';
        }
        OrderServiceLog($param['service_id'], $param['order_id'], $param['users_id'], 0, $LogNote);
    }

    //获取支付信息
    public function get_payment_info($data = [])
    {
        $openid = Db::name('users')->where('users_id', $data['users_id'])->value('open_id');
        $row = $this->wechatLogic->get_wechat_pay_config();
        $order_code = $data['order_code'];
        $order_amount = $data['order_amount'];
        $order_id = $data['order_id'];
        $time = getTime();
        // 调用支付接口参数
        $params = [
            'appid' => $row['appid'],
            'attach' => $order_code . '|,|' . session('users_id'),
            'body' => "订单号: {$order_code}",//不能太长
            'mch_id' => $row['mchid'],
            'nonce_str' => md5($time . $openid),
            'notify_url' => request()->domain() . ROOT_DIR . '/index.php?m=plugins&c=Supde&a=wechat_callback', // 异步地址
            'openid' => $openid,
            'out_trade_no' => $order_code,
            'spbill_create_ip' => getClientIP(),
            'total_fee' => strval($order_amount * 100),
            'trade_type' => 'JSAPI'
        ];
        // 微信小程序参数签名
        $params['sign'] = $this->ParamsSign($params, $row['key']);
        // 调用接口返回数据
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result = $this->ResultXml($this->HttpsPost($url, $this->ParamsXml($params)));

        // 请求接口成功
        if (!empty($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
            // 返回支付所需参数
            $return_data = [
                'order_id' => $order_id,
                'appId' => $row['appid'],
                'nonceStr' => $params['nonce_str'],
                'timeStamp' => strval($time),
                'package' => 'prepay_id=' . $result['prepay_id'],
                'signType' => 'MD5',
                // 微信小程序支付签名
                'paySign' => $this->getPaySign($row['appid'], $params['nonce_str'], $result['prepay_id'],$time,$row['key']),
            ];

            return $return_data;
        } else{// 请求接口失败
            return $result['return_msg'];
        }
    }

    /**************************     限时折扣 开始    ****************************/
    //获取单次显示折扣
    public function getOneDiscount($param = [])
    {
        $avtive_id = !empty($param['activeid']) ? intval($param['activeid']) : 0;

        $where['status'] = 1;
        $where['is_del'] = 0;

        //如果没传活动id就取当前正在进行的
        if (!empty($avtive_id)){
            $where['active_id'] = $avtive_id;
            $active = Db::name('discount_active')->where($where)->find();
        }else{
            $where['start_date'] = ['<=',getTime()];
            $where['end_date'] = ['>=',getTime()];
            //获取已开始未结束的限时折扣
            $active = Db::name('discount_active')->where($where)->find();
            //如果没有就取即将开始的
            if (empty($active)){
                $active = Db::name('discount_active')
                    ->where('status',1)
                    ->where('is_del',0)
                    ->where('start_date','>',getTime())
                    ->order('start_date asc')
                    ->find();
            }
        }
        $return = [];
        if (!empty($active)){
            $return['active'] = $active;

            if ($active['start_date'] <= getTime()){
                $return['active']['active_status'] = 10;//进行中
            }else{
                $return['active']['active_status'] = 20;//即将开始
            }
            $param['active_id'] = $return['active']['active_id'];
            $return['goodsList'] = $this->getDiscountGoodsList($param);
        }
        return $return;
    }

    /**
     * 限时折扣：获取限时折扣商品列表
     */
    public function getDiscountGoodsList($param = [])
    {
        $limit = !empty($param['limit']) ? intval($param['limit']) : 6;
        $active_id = $param['active_id'];
        $data = Db::name('discount_active_goods')
            ->alias('a')
            ->field('c.*,b.litpic,b.title,a.*,b.users_price')
            ->join('archives b','a.aid = b.aid')
            ->join('discount_goods c','a.discount_goods_id = c.discount_gid')
            ->where('a.active_id',$active_id)
            ->limit($limit)
            ->getAllWithIndex('aid');
        if (!empty($data)){
            $aids = [];
            foreach ($data as $k => $v){
                $data[$k]['litpic'] = get_default_pic($v['litpic'],true);
                if (1 == $v['is_sku']){
                    $aids[] = $v['aid'];
                }
            }
            //多规格
            if (!empty($aids)){
                $sku = Db::name('product_spec_value')
                    ->field('*,min(discount_price) as discount_price')
                    ->where('aid','in',$aids)
                    ->group('aid')
                    ->getAllWithIndex('aid');
            }

            foreach ($data as $k => $v){
                if (1 == $v['is_sku'] && !empty($sku[$v['aid']])){
                    $v['discount_price'] = $sku[$v['aid']]['discount_price'];
                }
                $v['progress'] = 0;
                if (0 < $v['sales_actual']){
                    $count_stock = $v['sales_actual']+$v['discount_stock']+$v['virtual_sales'];
                    $v['progress'] = intval(($v['sales_actual']+$v['virtual_sales'])/$count_stock*100);
                }else{
                    if (0 < $v['virtual_sales']){
                        $v['progress'] = intval($v['virtual_sales']/($v['virtual_sales']+$v['discount_stock'])*100);;
                    }
                }
                if (0 < $v['virtual_sales']){
                    $v['sales_actual'] = $v['sales_actual'] +$v['virtual_sales'];

                }
                $data[$k] = $v;
            }
        }
        return $data;
    }

    /**
     * 获取限时折扣列表
     */
    public function GetDiscountIndex($param = [])
    {
        $active_id = $param['active_id'];
        $page = !empty($param['page']) ? $param['page'] : 1;
        $pagesize = !empty($param['limit']) ? $param['limit'] : 20;
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        $result = cache($cacheKey);
        if (true || empty($result)) {
            $paginate = array(
                'page'  => $page,
            );
            $pages = Db::name('discount_active_goods')
                ->alias('a')
                ->field('c.*,b.litpic,b.title,a.*,b.users_price')
                ->join('archives b','a.aid = b.aid')
                ->join('discount_goods c','a.discount_goods_id = c.discount_gid')
                ->where('a.active_id',$active_id)
                ->paginate($pagesize, false, $paginate);

            $result = $pages->toArray();

            $aids = [];
            foreach ($result['data'] as $k => $v){
                $result['data'][$k]['litpic'] = get_default_pic($v['litpic'],true);
                if (1 == $v['is_sku']){
                    $aids[] = $v['aid'];
                }
            }

            //多规格
            if (!empty($aids)) {
                $sku = Db::name('product_spec_value')
                    ->field('*,min(discount_price) as discount_price')
                    ->where('aid', 'in', $aids)
                    ->group('aid')
                    ->getAllWithIndex('aid');
            }
            //sales_actual //
            foreach ($result['data'] as $k => $v){
                if (1 == $v['is_sku'] && !empty($sku[$v['aid']])){
                    $v['discount_price'] = $sku[$v['aid']]['discount_price'];
                }
                $v['progress'] = 0;
                if (0 < $v['sales_actual']){
                    $count_stock = $v['sales_actual']+$v['discount_stock']+$v['virtual_sales'];
                    $v['progress'] = intval(($v['sales_actual']+$v['virtual_sales'])/$count_stock*100);
                }else{
                    if (0 < $v['virtual_sales']){
                        $v['progress'] = intval($v['virtual_sales']/($v['virtual_sales']+$v['discount_stock'])*100);;
                    }
                }
                if (0 < $v['virtual_sales']){
                    $v['sales_actual'] = $v['sales_actual'] + $v['virtual_sales'];

                }
                $result['data'][$k] = $v;
            }
            if(1 == $page){
                $result['active'] = $this->GetDiscount($active_id);
            }
            cache($cacheKey, $result, null, 'GetDiscountIndex');
        }

        return $result;
    }
    //获取限时折扣活动信息(不包括折扣商品信息)
    public function GetDiscount($active_id = 0){
        $data = Db::name('discount_active')->where('active_id',$active_id)->find();
        if ($data['start_date'] <= getTime()){
            $data['active_status'] = 10;//进行中
        }else{
            $data['active_status'] = 20;//即将开始
        }
        return $data;
    }
    /**************************     限时折扣 结束    ****************************/

    /**************************     会员升级 开始    ****************************/
    // 会员升级
    public function upgradeUserLevel($param = [], $users = [])
    {
        if (empty($param['type_id'])) $this->error('缺少升级类型');
        // 记录类型，消费
        $causeType = 0;
        // 清理当前会员的未支付完成的升级订单记录
        $where = [
            'status' => 1,
            'users_id' => $this->users_id,
            'cause_type' => intval($causeType),
        ];
        Db::name('users_money')->where($where)->delete(true);
        // 会员信息
        $users = empty($users) ? GetUsersLatestData($this->users_id) : $users;
        // 订单号规则
        $orderNumber = date('Ymd') . $this->times . rand(10, 100);
        // 查询会员升级级别信息
        $usersTypeManage = Db::name('users_type_manage')->where('type_id', $param['type_id'])->find();
        // 支付方式
        $payName = '余额';
        $payName = 'wechat' == $param['pay_type'] ? '微信' : $payName;
        $payName = 'baiduPay' == $param['pay_type'] ? '百度' : $payName;
        $payName = 'tikTokPay' == $param['pay_type'] ? '抖音' : $payName;
        $payName = 'aliPay' == $param['pay_type'] ? '支付宝' : $payName;
        // 支付详情
        $payDetails = "会员当前级别为【{$users['level_name']}】，使用{$payName}支付【{$usersTypeManage['type_name']}】，支付金额为{$usersTypeManage['price']}";
        // 记录数据
        $insert = [
            'users_id'      => intval($this->users_id),
            'cause_type'    => intval($causeType),
            'cause'         => serialize($usersTypeManage),
            'money'         => $usersTypeManage['price'],
            'users_money'   => floatval($users['users_money']),
            'pay_method'    => $param['pay_type'],
            'pay_details'   => serialize($payDetails),
            'order_number'  => $orderNumber,
            'level_id'      => $usersTypeManage['level_id'],
            'status'        => 1,
            'add_time'      => $this->times,
            'update_time'   => $this->times
        ];
        if ('balance' == $param['pay_type']) {
            // 会员余额是否足够支付
            if (floatval($users['users_money']) < floatval($usersTypeManage['price'])) $this->error('余额不足');
            // 余额支付则记录支付后会员剩余余额
            $insert['users_money'] = floatval($users['users_money']) - floatval($usersTypeManage['price']);
        }
        $insertID = Db::name('users_money')->insertGetId($insert);
        if (!empty($insertID)) {
            // 微信支付
            if ('wechat' == $param['pay_type']) {
                // 调用微信支付接口
                $weChatPay = model('ShopPublicHandle')->getWechatAppletsPay($this->users_id, $orderNumber, $usersTypeManage['price'], 3);
                $resultData = [
                    'WeChatPay' => $weChatPay,
                    'OrderData' => [
                        'moneyid' => $insertID,
                        'order_number' => $orderNumber
                    ]
                ];
                $this->success('正在支付', null, $resultData);
            }
            // 抖音支付
            else if ('tikTokPay' == $param['pay_type']) {
                $tikTokPay = model('TikTok')->getTikTokAppletsPay($insertID, $orderNumber, $usersTypeManage['price'], 30, 'users_money', 3);
                $resultData = [
                    'tikTokPay' => $tikTokPay,
                    'OrderData' => [
                        'moneyid' => $insertID,
                        'order_number' => $orderNumber
                    ]
                ];
                // 返回提示
                $this->success('正在支付', null, $resultData);
            }
             // 支付宝支付
            else if ('aliPay' == $param['pay_type']) {
                 $openid = Db::name('wx_users')->where('wxuser_id', intval($this->users_id))->value('openid');
                $aliPay = model('AliPay')->getAlipayAppletsPay($insertID, $orderNumber, $usersTypeManage['price'], 30, 'users_money', 3,$openid);
                $resultData = [
                    'aliPay' => $aliPay,
                    'OrderData' => [
                        'moneyid' => $insertID,
                        'order_number' => $orderNumber
                    ]
                ];
                // 返回提示
                $this->success('正在支付', null, $resultData);
            }
            // 百度支付
            else if ('baiduPay' == $param['pay_type']) {
                $baiduPay = model('BaiduPay')->getBaiDuAppletsPay($insertID, $orderNumber, $usersTypeManage['price'], 'users_money', 3);
                $result = [
                    'baiduPay' => $baiduPay,
                    'OrderData' => [
                        'moneyid' => $insertID,
                        'order_number' => $orderNumber
                    ]
                ];
                // 返回提示
                $this->success('正在支付', null, $result);
            }
            // 余额支付
            else if ('balance' == $param['pay_type']) {
                // 扣除会员余额
                $resultID = Db::name('users')->where(['users_id' => $this->users_id])->setDec('users_money', $usersTypeManage['price']);
                // 后续处理
                if (!empty($resultID)) {
                    $insert['unified_id'] = $insertID;
                    $insert['unified_number'] = $insert['order_number'];
                    $insert['transaction_type'] = 3;
                    $payApiLogic = new \app\user\logic\PayApiLogic($this->users_id, true);
                    $payApiLogic->OrderProcessing($insert, $insert);
                }
            }
        }
        $this->error('会员升级失败');
    }

    // 会员升级
    public function handleUpgradeUserLevel($param = [], $users = [])
    {
        // 查询订单信息
        $where = [
            'cause_type' => 0,
            'users_id' => $this->users_id,
            'order_number' => trim($param['order_number']),
        ];
        $usersMoney = Db::name('users_money')->where($where)->find();
        if (empty($usersMoney)) $this->error('订单错误，刷新重试');
        if (1 !== intval($usersMoney['status'])) {
            if (in_array($usersMoney['status'], [2, 3]) && 'wechat' === trim($usersMoney['pay_method'])) {
                // model('ShopPublicHandle')->pushWxShippingInfo($this->users_id, $usersMoney['order_number'], 3, '会员升级');
            }
            $this->success('订单已处理');
        }

        // 下单确认页数据处理
        $usersMoney['unified_id'] = $usersMoney['moneyid'];
        $usersMoney['unified_number'] = $usersMoney['order_number'];
        $usersMoney['transaction_type'] = 3;
        // 微信支付后续处理
        if ('wechat' == $usersMoney['pay_method']) {
            model('ShopPublicHandle')->getWeChatPayResult($this->users_id, $usersMoney, 3);
        }
        // 抖音支付后续处理
        else if ('tikTokPay' == $usersMoney['pay_method']) {
            model('TikTok')->tikTokAppletsPayDealWith($usersMoney, false, 'users_money');
        }
         // 支付宝支付后续处理
        else if ('aliPay' == $usersMoney['pay_method']) {
            model('AliPay') -> aliPayAppletsPayDealWith($usersMoney, false, 'users_money');
        }
        // 百度支付后续处理
        else if ('baiduPay' == $usersMoney['pay_method']) {
            model('BaiduPay')->baiDuAppletsPayDealWith($usersMoney, false, 'users_money');
        }
    }

    // 微信小程序支付后续处理
    public function WechatAppletsPayDealWithUsersMoney($PostData = [], $notify = false)
    {
        $OpenID    = !empty($PostData['openid']) ? $PostData['openid'] : '';
        $UsersID   = !empty($PostData['users_id']) ? $PostData['users_id'] : '';
        $order_number = $PostData['order_number'];

        if (!empty($moneyid) && !empty($order_number)) {
            $where = [
                'users_id'   => $UsersID,
                'order_number' => $order_number,
            ];
            //查询订单状态
            $order = Db::name('users_money')->where($where)->find();

            if (empty($order)) {
                $this->error('无效订单！');
            } else if (1 < $order['status']) {
                // 订单已支付
                if ($notify === true) { // 异步
                    return [
                        'code' => 1,
                        'msg'  => 'ok',
                    ];
                } else { // 同步
                    $this->success('支付完成', '/pages/user/index');
                }
            }

            // 当前时间戳
            $time = getTime();

            // 当前时间戳 + OpenID 经 MD5加密
            $nonceStr = md5($time . $OpenID);

            // 调用支付接口参数
            $params = [
                'appid'        => $this->miniproInfo['appid'],
                'mch_id'       => $this->miniproInfo['mchid'],
                'nonce_str'    => $nonceStr,
                'out_trade_no' => $order_number
            ];

            // 生成参数签名
            $params['sign'] = $this->ParamsSign($params, $this->miniproInfo['apikey']);

            // 生成参数XML格式
            $ParamsXml = $this->ParamsXml($params);

            // 调用接口返回数据
            $url    = 'https://api.mch.weixin.qq.com/pay/orderquery';
            $result = $this->HttpsPost($url, $ParamsXml);

            // 解析XML格式
            $ResultData = $this->ResultXml($result);

            // 订单是否存在
            if (!empty($ResultData) && 'SUCCESS' == $ResultData['return_code'] && 'OK' == $ResultData['return_msg']) {
                // if ('NOTPAY' == $ResultData['trade_state'] && !empty($ResultData['trade_state_desc'])) {
                //     // 订单未支付
                //     $this->error($ResultData['trade_state_desc']);
                // }
                if ('SUCCESS' == $ResultData['trade_state']) {
                    // 订单已支付，处理订单流程
                    $OrderWhere = [
                        'order_number' => $order_number,
                        'users_id' => $UsersID
                    ];
                    $OrderData  = [
                        'status' => 2,
                        'update_time'  => getTime()
                    ];
                    $ResultID   = Db::name('users_money')->where($OrderWhere)->update($OrderData);
                    if (!empty($ResultID)) {
                        $order['cause'] = unserialize($order['cause']);
                        $level_maturity_days = Db::name('users')->where('users_id', $UsersID)->value('level_maturity_days');
                        $limit_arr = Config::get('global.admin_member_limit_arr');

                        $day = $limit_arr[$order['cause']['limit_id']]['maturity_days'];
                        $users_update['level_maturity_days'] = Db::Raw('level_maturity_days +'.$day);
                        if (empty($level_maturity_days)){
                            $users_update['open_level_time'] = getTime();
                        }
                        $users_update['level'] = $order['cause']['level_id'];
                        $users_update['update_time'] = getTime();
                        Db::name('users')->where('users_id', $UsersID)->update($users_update);

                        // 订单支付完成
                        if ($notify === true) { // 异步
                            return [
                                'code' => 1,
                                'msg'  => 'ok',
                            ];
                        } else { // 同步
                            $url = '/pages/user/index';
                            $this->success('支付完成', $url);
                        }
                    }
                }
            }
        }
    }

    /**************************     会员升级 结束    ****************************/

    /**************************     视频购买 开始    ****************************/
    // 视频购买
    public function buyMedia($param = [])
    {
        if (empty($param['aid'])) $this->error('缺少必要参数');
        // 清理当前会员的未支付的充值记录
        $where = [
            'order_status' => 0,
            'users_id' => $this->users_id,
        ];
        Db::name('media_order')->where($where)->delete();

        $users = Db::name('users')
            ->field('a.*,b.discount')
            ->alias('a')
            ->join('users_level b','a.level = b.level_id','left')
            ->where('a.users_id',$this->users_id)
            ->find();
        $list = Db::name('archives')->where('aid',$param['aid'])->find();
        $price = unifyPriceHandle($list['users_price'] * ($users['discount'] / 100));

        // 生成订单并保存到数据库
        $time = getTime();
        $OrderData = [
            'order_code'      => date('Y') . $time . rand(10,100),
            'users_id'        => $this->users_id,
            'mobile'          => !empty($users['mobile']) ?$users['mobile'] : '',
            'order_status'    => 0,
            'order_amount'    => $price,
            'pay_time'        => '',
            'pay_name'        => '',
            'wechat_pay_type' => '',
            'pay_details'     => '',
            'product_id'      => $list['aid'],
            'product_name'    => $list['title'],
            'product_litpic'  => get_default_pic($list['litpic']),
            'lang'            => get_home_lang(),
            'add_time'        => $time,
            'update_time'     => $time
        ];
        $insertID = Db::name('media_order')->insertGetId($OrderData);
        if (!empty($insertID)) {
            if ('wechat' == $param['pay_type']) {
                // 调用微信支付接口
                $weChatPay = model('ShopPublicHandle')->getWechatAppletsPay($this->users_id, $OrderData['order_code'], $price, 8);
                $resultData = [
                    'WeChatPay' => $weChatPay,
                    'OrderData' => [
                        'order_id' => $insertID,
                        'order_code' => $OrderData['order_code']
                    ]
                ];
                $this->success('正在支付', null, $resultData);

                // 微信支付
                // $openid = Db::name('wx_users')->where('users_id', $param['users_id'])->getField('openid');
                // $notify_url =  url('api/v1/Api/wxpay_notify_media', [], true, true, 1, 2);
                // $Data = $this->GetWechatAppletsPay($openid, $OrderData['order_code'],  $price,$notify_url);
                // $ResultData = [
                //     'WeChatPay' => $Data,
                //     'OrderData' => [
                //         'order_id' => $insertID,
                //         'order_code' => $OrderData['order_code']
                //     ]
                // ];
                // $this->success('正在支付', null, $ResultData);
            } else if ('balance' == $param['pay_type']) {
                //先判断余额够不够
                if ($users['users_money'] < $price){
                    $this->error('余额不足!');
                }

                // 订单更新数据，更新为已付款
                $pay_details['unified_id'] = $insertID;
                $pay_details['unified_number'] = $OrderData['order_code'];
                $pay_details['payment_type'] = '余额支付';
                $pay_details['payment_amount'] = $price;
                $pay_details['transaction_type'] = 8;
                // 余额支付
                $UpOrderData = [
                    'order_status'    => 1,
                    'pay_time'    => getTime(),
                    'pay_name'    => 'balance',
                    'pay_details' => serialize($pay_details),
                    'update_time' => getTime()
                ];
                $ReturnID    = Db::name('media_order')->where('order_code',  $OrderData['order_code'])->update($UpOrderData);
                if (!empty($ReturnID)) {
                    $Where = [
                        'users_id' => $this->users_id
                    ];
                    $UsersData = [
                        'users_money' => Db::raw('users_money-'.$OrderData['order_amount']),
                        'update_time' => getTime()
                    ];
                    Db::name('users')->where($Where)->update($UsersData);
                    UsersMoneyRecording($OrderData['order_code'], $users, $OrderData['order_amount'], '视频购买', 3);
                    $url = '/pages/user/index';
                    $this->success('支付完成', $url);
                }
            }
        } else {
            $this->error('会员升级失败');
        }
    }

    // 会员升级
    public function handleBuyMedia($param = [])
    {
        // 查询订单信息
        $where = [
            'users_id' => $this->users_id,
            'order_code' => $param['order_code'],
        ];
        $order = Db::name('media_order')->where($where)->find();
        if (empty($order)) $this->error('订单错误，刷新重试');
        if (0 !== intval($order['status'])) $this->error('订单已被处理，刷新重试');

        // 查询会员openid
        $openid = Db::name('wx_users')->where('users_id', $param['users_id'])->getField('openid');

        // 下单确认页数据处理
        $WeChatPay = $this->GetWeChatPayDetails($openid, $param['order_code']);

        // 判断处理订单是否真实支付并处理订单
        if (!empty($WeChatPay) && 'SUCCESS' == $WeChatPay['return_code'] && 'OK' == $WeChatPay['return_msg'] && 'SUCCESS' == $WeChatPay['trade_state']) {
            // 视频订单已支付，将订单更新为已支付
            // 订单更新数据，更新为已付款
            $pay_details['unified_id'] = $order['order_id'];
            $pay_details['unified_number'] = $order['order_code'];
            $pay_details['payment_type'] = '微信支付';
            $pay_details['payment_amount'] = $order['order_amount'];
            $pay_details['transaction_type'] = 8;
            // 余额支付
            $UpOrderData = [
                'order_status'    => 1,
                'pay_time'    => getTime(),
                'pay_name'    => 'wechat',
                'wechat_pay_type'    => 'WeChatScanCode',
                'pay_details' => serialize($pay_details),
                'update_time' => getTime()
            ];
            $return  = Db::name('media_order')->where('order_code',  $param['order_code'])->update($UpOrderData);
            if (!empty($return)) {
                // 返回结束
                $this->success('支付完成');
            }
            $this->success('订单处理失败，请联系管理员');
        }
    }

    // 微信小程序支付后续处理
    public function WechatAppletsPayDealWithMedia($PostData = [], $notify = false)
    {
        $OpenID    = !empty($PostData['openid']) ? $PostData['openid'] : '';
        $UsersID   = !empty($PostData['users_id']) ? $PostData['users_id'] : '';
        $order_code = $PostData['order_code'];

        if (!empty($order_code)) {
            $where = [
                'users_id'   => $UsersID,
                'order_code' => $order_code,
            ];
            //查询订单状态
            $order = Db::name('media_order')->where($where)->find();

            if (empty($order)) {
                $this->error('无效订单！');
            } else if (0 < $order['status']) {
                // 订单已支付
                if ($notify === true) { // 异步
                    return [
                        'code' => 1,
                        'msg'  => 'ok',
                    ];
                } else { // 同步
                    $this->success('支付完成', '/pages/user/index');
                }
            }

            // 当前时间戳
            $time = getTime();

            // 当前时间戳 + OpenID 经 MD5加密
            $nonceStr = md5($time . $OpenID);

            // 调用支付接口参数
            $params = [
                'appid'        => $this->miniproInfo['appid'],
                'mch_id'       => $this->miniproInfo['mchid'],
                'nonce_str'    => $nonceStr,
                'out_trade_no' => $order_code
            ];

            // 生成参数签名
            $params['sign'] = $this->ParamsSign($params, $this->miniproInfo['apikey']);

            // 生成参数XML格式
            $ParamsXml = $this->ParamsXml($params);

            // 调用接口返回数据
            $url    = 'https://api.mch.weixin.qq.com/pay/orderquery';
            $result = $this->HttpsPost($url, $ParamsXml);

            // 解析XML格式
            $ResultData = $this->ResultXml($result);

            // 订单是否存在
            if (!empty($ResultData) && 'SUCCESS' == $ResultData['return_code'] && 'OK' == $ResultData['return_msg']) {
                // if ('NOTPAY' == $ResultData['trade_state'] && !empty($ResultData['trade_state_desc'])) {
                //     // 订单未支付
                //     $this->error($ResultData['trade_state_desc']);
                // }
                if ('SUCCESS' == $ResultData['trade_state']) {
                    // 订单已支付，处理订单流程
                    $OrderWhere = [
                        'order_code' => $order_code,
                        'users_id' => $UsersID
                    ];
                    // 订单更新数据，更新为已付款
                    $pay_details['unified_id'] = $order['order_id'];
                    $pay_details['unified_number'] = $order['order_code'];
                    $pay_details['payment_type'] = '微信支付';
                    $pay_details['payment_amount'] = $order['order_amount'];
                    $pay_details['transaction_type'] = 8;
                    // 余额支付
                    $UpOrderData = [
                        'order_status'    => 1,
                        'pay_time'    => getTime(),
                        'pay_name'    => 'wechat',
                        'wechat_pay_type'    => 'WeChatScanCode',
                        'pay_details' => serialize($pay_details),
                        'update_time' => getTime()
                    ];
                    $ResultID   = Db::name('media_order')->where($OrderWhere)->update($UpOrderData);
                    if (!empty($ResultID)) {

                        // 订单支付完成
                        if ($notify === true) { // 异步
                            return [
                                'code' => 1,
                                'msg'  => 'ok',
                            ];
                        } else { // 同步
                            $url = '/pages/user/index';
                            $this->success('支付完成', $url);
                        }
                    }
                }
            }
        }
    }

    /**************************     视频购买 结束    ****************************/
}