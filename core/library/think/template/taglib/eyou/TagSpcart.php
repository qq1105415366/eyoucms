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
use think\Db;

/**
 * 购物车列表
 */
class TagSpcart extends Base
{
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
    }

    /**
     * 获取购物车数据
     */
    public function getSpcart($limit = '')
    {
        // 查询条件
        $condition = [
            'a.users_id' => $this->users_id,
            'a.lang'     => self::$home_lang,
            'b.arcrank'  => array('egt','0'),  // 带审核稿件不查询(同等伪删除)
        ];
        //判断是否安装并且启用了多商家插件
        $is_multiMerchant = false;
        if (is_dir('./weapp/MultiMerchant')){
            $TimingTaskRow = model('Weapp')->getWeappList('MultiMerchant');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $is_multiMerchant = true;
            }
        }
        if (!empty($is_multiMerchant)) {
            $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.logistics_type, b.users_discount_type, b.stock_count, b.attrlist_id, b.merchant_id, d.merchant_name, b.is_del, c.spec_price, c.spec_stock';
            $list = Db::name("shop_cart")
                ->field($field)
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->join('__WEAPP_MULTI_MERCHANT__ d', 'b.merchant_id = d.merchant_id', 'LEFT')
                ->where($condition)
                ->limit($limit)
                ->order('a.selected desc, a.add_time desc')
                ->select();
        } else {
            $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.logistics_type, b.users_discount_type, b.stock_count, b.attrlist_id, b.is_del, c.spec_price, c.spec_stock';
            $list = Db::name("shop_cart")
                ->field($field)
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->where($condition)
                ->limit($limit)
                ->order('a.selected desc, a.add_time desc')
                ->select();
        }
        if (empty($list)) return ['list'=>[]];

        // ClearID 需要清零的ID， RestoreID 需要恢复数据的ID， UpMaxNumID 需要更新最大数量的ID
        $ClearID = $RestoreID = $UpMaxNumID = [];
        // 规格商品价格及库存处理
        foreach ($list as $key => $value) {
            $list[$key]['StatusMark']  = '';
            if (!empty($value['spec_value_id'])) {
                // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
                if (!empty($value['spec_price'])) $list[$key]['users_price'] = floatval($value['spec_price']);

                if (!empty($value['spec_stock']) && 0 < $value['spec_stock']) {
                    // 购物车商品存在规格并且库存不为空，则覆盖商品原来的库存
                    $list[$key]['stock_count'] = $value['spec_stock'];
                    $value['stock_count'] = $value['spec_stock'];

                    // 若商品有库存并且购物车数量小于等于0则执行
                    if ($value['product_num'] <= 0 && 0 == $value['is_del']) {
                        $list[$key]['selected']    = 1;
                        $list[$key]['product_num'] = 1;
                        array_push($RestoreID, $value['cart_id']);
                    }
                } else {
                    // 商品已售罄则执行
                    $list[$key]['stock_count'] = 0;
                    $list[$key]['selected']    = 0;
                    $list[$key]['IsSoldOut']   = 1;
                    $list[$key]['StatusMark']  = '[商品已售罄]';
                    array_push($ClearID, $value['cart_id']);
                }
            } else {
                // 商品已售罄则执行
                if (empty($value['stock_count']) || 0 > $value['stock_count']) {
                    $list[$key]['stock_count'] = 0;
                    $list[$key]['selected']    = 0;
                    $list[$key]['IsSoldOut']   = 1;
                    $list[$key]['StatusMark']  = '[商品已售罄]';
                    array_push($ClearID, $value['cart_id']);
                } else {
                    // 若商品有库存并且购物车数量小于等于0则执行
                    if ($value['product_num'] <= 0 && 0 == $value['is_del']) {
                        $list[$key]['selected']    = 1;
                        $list[$key]['product_num'] = 1;
                        array_push($RestoreID, $value['cart_id']);
                    }
                }
            }
                    
            // 商品 被伪删除 或 规格被删除 则执行
            if ((1 == $value['is_del']) || (empty($value['spec_price']) && !empty($value['spec_value_id']))) {
                $list[$key]['stock_count'] = 0;
                $list[$key]['selected']    = 0;
                $list[$key]['StatusMark']  = '[商品已停售]';
                array_push($ClearID, $value['cart_id']);
            }

            // 购买数量超过库存则执行
            if ($value['product_num'] > $value['stock_count']) {
                $UpMaxNumID[] = [
                    'cart_id'     => $value['cart_id'],
                    'product_num' => $value['stock_count'],
                    'update_time' => getTime(),
                    'key'         => $key
                ];
            }
            //多商家字段显示
            if (isset($value['merchant_id']) && 0 === intval($value['merchant_id'])) {
                $list[$key]['merchant_name'] = '自营商户';
                $list[$key]['merchant_type'] = '平台';
            } else {
                $list[$key]['merchant_type'] = '商家';
            }
        }

        // 更新购物车库存为0并清除选中效果的商品
        if (!empty($ClearID)) {
            $ClearWhere = [
                'cart_id' => ['IN', $ClearID]
            ];
            $ClearUpdata = [
                'selected' => 0,
                'product_num' => 0,
                'update_time' => getTime()
            ];
            Db::name("shop_cart")->where($ClearWhere)->update($ClearUpdata);
        }

        // 更新购物车库存为1并恢复选中效果的商品
        if (!empty($RestoreID)) {
            $RestoreWhere = [
                'cart_id' => ['IN', $RestoreID]
            ];
            $RestoreUpdata = [
                'selected' => 1,
                'product_num' => 1,
                'update_time' => getTime()
            ];
            Db::name("shop_cart")->where($RestoreWhere)->update($RestoreUpdata);
        }

        // 当购物车库存超过商品库存则执行购物车库存为商品最大库存
        if (!empty($UpMaxNumID)) {
            foreach ($UpMaxNumID as $value) {
                Db::name("shop_cart")->where('cart_id', $value['cart_id'])->update($value);
                $list[$value['key']]['product_num'] = $value['product_num'];
            }
        }

        // 订单数据处理
        $result = [
            'TotalAmount' => 0,
            'TotalNumber' => 0,
            'AllSelected' => 0,
            'TotalCartNumber' => 0,
        ];
        $selected = 0;

        $logisticsTypeArr = [];
        $controller_name = 'Product';
        $array_new = get_archives_data($list, 'product_id');
        $level_discount = $this->users['level_discount'];
        foreach ($list as $key => $value) {
            // 仅物流配送 或 仅到店核销 则存入数组
            if (('1' === $value['logistics_type'] || '2' === $value['logistics_type']) && !empty($value['selected'])) {
                array_push($logisticsTypeArr, $value['logistics_type']);
            }
            // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
            if (!empty($level_discount)) {
                // 折扣率百分比
                $discount_price = $level_discount / 100;
                $value['users_price'] = 2 === intval($value['users_discount_type']) ? $value['users_price'] : $value['users_price'] * $discount_price;
                $value['users_price'] = floatval(sprintf("%.2f", $value['users_price']));
            }
            // 查询会员折扣价
            if (empty($value['spec_value_id']) && !empty($this->users['level_id']) && 1 === intval($value['users_discount_type'])) {
                $value['users_price'] = model('ShopPublicHandle')->handleUsersDiscountPrice($value['aid'], $this->users['level_id']);
            }
            // 覆盖原数据
            $list[$key]['users_price'] = $value['users_price'];
            // 产品价格处理
            $list[$key]['subtotal'] = 0;
            if (!empty($value['users_price'])) {
                // 计算小计
                $list[$key]['subtotal'] = $value['users_price'] * $value['product_num'];
                $list[$key]['subtotal'] = floatval(sprintf("%.2f", $list[$key]['subtotal']));
                //计算购车商品总件数
                $result['TotalCartNumber'] += $value['product_num'];
                // 计算购物车中已勾选的产品总数和总额
                if (!empty($value['selected'])) {
                    // 合计金额
                    $result['TotalAmount'] += $list[$key]['subtotal'];
                    $result['TotalAmount'] = floatval(sprintf("%.2f", $result['TotalAmount']));
                    // 合计数量
                    $result['TotalNumber'] += $value['product_num'];
                    // 选中的产品个数
                    $selected++;
                }
            }

            // 产品内页地址
            $list[$key]['arcurl'] = urldecode(arcurl('home/'.$controller_name.'/view', $array_new[$value['product_id']]));
            
            // 图片处理
            $list[$key]['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));

            // 产品旧参数属性处理
            $list[$key]['attr_value'] = '';
            if (!empty($value['product_id'])) {
                $attrData = Db::name("product_attr")->where('aid', $value['product_id'])->field('attr_value, attr_id')->select();
                foreach ($attrData as $val) {
                    $attr_name = Db::name("product_attribute")->where('attr_id', $val['attr_id'])->field('attr_name')->find();
                    $list[$key]['attr_value'] .= $attr_name['attr_name'].'：'.$val['attr_value'].'<br/>';
                }
            }

            // 商品规格处理
            $list[$key]['product_spec'] = '';
            $product_spec_list = [];
            if (!empty($value['spec_value_id'])) {
                $spec_value_id = explode('_', $value['spec_value_id']);
                if (!empty($spec_value_id)) {
                    $SpecWhere = [
                        'aid'           => $value['product_id'],
                        'lang'          => self::$home_lang,
                        'spec_value_id' => ['IN',$spec_value_id]
                    ];
                    $ProductSpecData = M("product_spec_data")->where($SpecWhere)->field('spec_name,spec_value')->select();
                    foreach ($ProductSpecData as $spec_value) {
                        $list[$key]['product_spec'] .= $spec_value['spec_name'].'：'.$spec_value['spec_value'].'<br/>';
                        $product_spec_list[] = [
                            'name' => $spec_value['spec_name'],
                            'value' => $spec_value['spec_value'],
                        ];
                    }
                }
            }
            $list[$key]['product_spec_list'] = $product_spec_list;

            if (isset($value['IsSoldOut']) && !empty($value['IsSoldOut'])) {
                $list[$key]['CartChecked'] = " disabled='true' title='商品已售罄' ";
                $list[$key]['ReduceQuantity'] = " onclick=\"CartUnifiedAlgorithm('IsSoldOut');\" ";
                $list[$key]['UpdateQuantity'] = " onchange=\"CartUnifiedAlgorithm('IsSoldOut');\" value=\"0\" ";
                $list[$key]['IncreaseQuantity'] = " onclick=\"CartUnifiedAlgorithm('IsSoldOut');\" ";
            } else if (isset($value['is_del']) && !empty($value['is_del'])) {
                $list[$key]['CartChecked'] = " disabled='true' title='商品已停售' ";
                $list[$key]['ReduceQuantity'] = " onclick=\"CartUnifiedAlgorithm('IsDel');\" ";
                $list[$key]['UpdateQuantity'] = " onchange=\"CartUnifiedAlgorithm('IsDel');\" value=\"0\" ";
                $list[$key]['IncreaseQuantity'] = " onclick=\"CartUnifiedAlgorithm('IsDel');\" ";
            } else {
                $list[$key]['CartChecked'] = " name=\"ey_buynum\" id=\"{$value['cart_id']}_checked\" cart-id=\"{$value['cart_id']}\" product-id=\"{$value['product_id']}\" onclick=\"Checked('{$value['cart_id']}','{$value['selected']}');\" ";
                $list[$key]['ReduceQuantity'] = " onclick=\"CartUnifiedAlgorithm('{$value['stock_count']}','{$value['product_id']}','-','{$value['selected']}','{$value['spec_value_id']}','{$value['cart_id']}');\" ";
                $list[$key]['UpdateQuantity'] = " onkeyup=\"this.value=this.value.replace(/[^0-9\.]/g,'')\" onafterpaste=\"this.value=this.value.replace(/[^0-9\.]/g,'')\"  onchange=\"CartUnifiedAlgorithm('{$value['stock_count']}','{$value['product_id']}','change','{$value['selected']}','{$value['spec_value_id']}','{$value['cart_id']}');\" value=\"{$value['product_num']}\" data-pre_value=\"{$value['product_num']}\" id=\"{$value['cart_id']}_num\" ";
                $list[$key]['IncreaseQuantity'] = " onclick=\"CartUnifiedAlgorithm('{$value['stock_count']}','{$value['product_id']}','+','{$value['selected']}','{$value['spec_value_id']}','{$value['cart_id']}');\" ";
            }

            $list[$key]['ProductId']     = " id=\"{$value['cart_id']}_product\" ";
            $list[$key]['ProductSpecId'] = " id=\"{$value['cart_id']}_product_spec\" ";
            $list[$key]['SubTotalId']    = " id=\"{$value['cart_id']}_subtotal\" ";
            $list[$key]['UsersPriceId']  = " id=\"{$value['cart_id']}_price\" ";
            $list[$key]['CartDel']       = " href=\"javascript:void(0);\" onclick=\"CartDel('{$value['cart_id']}','{$value['title']}');\" ";
            $list[$key]['MoveToCollection'] = " href=\"javascript:void(0);\" onclick=\"MoveToCollection('{$value['cart_id']}','{$value['title']}');\" ";
            $list[$key]['hidden']   = <<<EOF
<input type="hidden" id="{$value['cart_id']}_Selected" value="{$value['selected']}">
<input type="hidden" id="SpecStockCount" value="{$value['spec_stock']}">
<script type="text/javascript">
$(function(){
    if ('1' == $('#'+{$value['cart_id']}+'_Selected').val()) {
        $('#'+{$value['cart_id']}+'_checked').prop('checked','true');
    }
}); 
</script>
EOF;
        }

        if (!empty($is_multiMerchant)) {
            $result['list'] = group_same_key($list, 'merchant_id');    // 以商家为分组条件进行分组
        } else {
            $result['list'] = $list;
        }

        // dump($list);exit;
        // 是否购物车的产品全部选中
        if (count($list) == $selected) $result['AllSelected'] = 1;

        // 下单地址
        $result['ShopOrderUrl']  = urldecode(url('user/Shop/shop_under_order', [], true, false, 1, 1));
        $result['SubmitOrder'] = " id='SubmitOrder_v455820' onclick=\"SubmitOrder('{$result['ShopOrderUrl']}');\" ";
        $result['SubmitOrder_0'] = " id='SubmitOrder_0_v455820'  onclick=\"SubmitOrder_0();\" ";
        $result['InputChecked'] = " id=\"AllChecked\" onclick=\"Checked('*','{$result['AllSelected']}');\" ";
        $result['InputHidden'] = " <input type=\"hidden\" id=\"AllSelected\" value='{$result['AllSelected']}'> ";
        $result['TotalCartNumberId'] = " id=\"TotalCartNumber\" ";
        $result['TotalNumberId'] = " id=\"TotalNumber\" ";
        $result['TotalAmountId'] = " id=\"TotalAmount\" ";
        $result['BatchCartDel'] = " href=\"javascript:void(0);\" onclick=\"BatchCartDel();\" ";
        $result['selectCartDel'] = " href=\"javascript:void(0);\" onclick=\"selectCartDel();\" ";
        if (in_array($this->usersTplVersion, ['v2', 'v5'])) {
            $result['SubmitOrder'] = " onclick=\"toSplitGoods('{$result['ShopOrderUrl']}');\" ";
        }
        // 购物车同时存在 仅物流配送 和 仅到店核销 则执行
        // if (in_array(1, $logisticsTypeArr) && in_array(2, $logisticsTypeArr)) {
        //     $result['SubmitOrder'] = " onclick='toSplitGoods();' ";
        // }

        // 传入JS文件的参数
        $data['is_wap']                     = isWeixin() || isMobile() ? 1 : 0;
        $data['cart_del_url']               = url('user/Shop/cart_del', [], true, false, 1, 1);
        $data['cart_checked_url']           = url('user/Shop/cart_checked', [], true, false, 1, 1);
        $data['toSplitGoods']               = url('user/Shop/to_split_goods', [], true, false, 1, 1);
        $data['select_cart_del_url']        = url('user/Shop/select_cart_del', [], true, false, 1, 1);
        $data['move_to_collection_url']     = url('user/Shop/move_to_collection', [], true, false, 1, 1);
        $data['cart_stock_detection']       = url('user/Shop/cart_stock_detection', [], true, false, 1, 1);
        $data['cart_unified_algorithm_url'] = url('user/Shop/cart_unified_algorithm', [], true, false, 1, 1);
        $data_json = json_encode($data);
        $version = getCmsVersion();
        // 会员模板版本号
        if (empty($this->usersTplVersion) || 'v1' == $this->usersTplVersion) {
            $jsfile = "tag_spcart.js";
        } else {
            $jsfile = "tag_spcart_{$this->usersTplVersion}.js";
        }
        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
        $result['hidden'] = <<<EOF
<script type="text/javascript">
    var b82ac06cf24687eba9bc5a7ba92be4c8 = {$data_json};
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
        return $result;
    }
}