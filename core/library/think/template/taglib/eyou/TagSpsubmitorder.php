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
use think\Request;
use think\Db;

/**
 * 提交订单
 */
load_trait('controller/Jump');
class TagSpsubmitorder extends Base
{ 
    use \traits\controller\Jump;

    /**
     * 会员ID
     */
    public $users_id = 0;
    public $users    = [];
    public $usersTplVersion = '';
    
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
    }

    /**
     * 获取提交订单数据
     */
    public function getSpsubmitorder()
    {
        // 获取解析数据
        $aid = 0;
        $GetMd5 = input('param.querystr/s', '');
        if (!empty($GetMd5)) {
            $querystr = cookie($GetMd5);
            if(empty($querystr)) $this->error('下单链接已过期！');
            // 赋值数据
            $aid = !empty($querystr['aid']) ? $querystr['aid'] : 0;
            $num = !empty($querystr['product_num']) ? $querystr['product_num'] : 0;
            $spec_value_id = !empty($querystr['spec_value_id']) ? $querystr['spec_value_id'] : '';
        }

        if (!empty($aid)) {
            if ($num >= 1) {
                // 立即购买查询条件
                $where = [
                    'a.aid'  => $aid,
                    'a.lang' => self::$home_lang,
                ];
                if (!empty($spec_value_id)) $where['b.spec_value_id'] = $spec_value_id;
                $field = 'a.aid, a.title, a.litpic, a.users_price, a.users_discount_type, a.stock_count, a.prom_type, a.attrlist_id, b.spec_price, b.spec_stock, b.spec_value_id, c.spec_is_select, a.free_shipping, a.merchant_id, a.logistics_type';
                $result['list'] = Db::name('archives')->field($field)
                    ->alias('a')
                    ->join('__PRODUCT_SPEC_VALUE__ b', 'a.aid = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_DATA__ c', 'a.aid = c.aid and b.spec_value_id = c.spec_value_id', 'LEFT')
                    ->where($where)
                    ->limit('0, 1')
                    ->select();

                if (empty($result['list'][0]['spec_is_select'])) {
                    $result['list'][0]['spec_price']    = '';
                    $result['list'][0]['spec_stock']    = '';
                    $result['list'][0]['spec_value_id'] = '';
                }
                $submit_order_type = 1;
                $result['list'][0]['product_num'] = $num;
                $result['list'][0]['ProductHidden'] = '<input type="hidden" name="Md5Value" value="' . $GetMd5 . '"> <input type="hidden" name="type" value="' . $submit_order_type . '">';
            } else {
                action('user/Shop/shop_under_order', false);
                exit;
            }
        } else {
            // 购物车查询条件
            $where = [
                'a.users_id' => $this->users_id,
                'a.lang'     => self::$home_lang,
                'a.selected' => 1,
            ];
            $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.users_discount_type, b.stock_count, b.prom_type, b.attrlist_id, c.spec_price, c.spec_stock, d.spec_is_select, b.free_shipping, b.merchant_id, b.logistics_type';
            $result['list'] = Db::name('shop_cart')->field($field)
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_DATA__ d', 'a.product_id = d.aid and a.spec_value_id = d.spec_value_id', 'LEFT')
                ->where($where)
                ->order('a.add_time desc')
                ->select();
            $submit_order_type = 0;
        }

        // 如果产品数据为空则调用商城控制器的方式返回提示,中止运行
        if (empty($result['list'])) {
            action('user/Shop/shop_under_order', false);
            exit;
        }
        //判断是否安装并且启用了多商家插件
        $is_multiMerchant = false;
        if (is_dir('./weapp/MultiMerchant')){
            $TimingTaskRow = model('Weapp')->getWeappList('MultiMerchant');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $is_multiMerchant = true;
            }
        }
        if (!empty($is_multiMerchant)) {
            // 平台自营默认信息
            $merchantData = [['merchant_id' => 0,'merchant_name' => '自营商户']];

            // 查询是否有多商家插件
            $where = [
                'status' => 1,
                'code' => 'MultiMerchant'
            ];
            $isMultiMerchant = Db::name('weapp')->where($where)->getField('id');
            $isMultiMerchant = !empty($isMultiMerchant) ? $isMultiMerchant : 0;
            // 已安装多商家插件并开启则执行
            if (!empty($isMultiMerchant)) {
                // 获取多商家ID
                $merchantID = array_filter(array_unique(get_arr_column($result['list'], 'merchant_id')));
                $where = [
                    'merchant_id' => ['IN', $merchantID]
                ];
                // 查询多商家信息
                $multiMerchant = Db::name('weapp_multi_merchant')->field('merchant_id, merchant_name')->where($where)->getAllWithIndex('merchant_id');
                if (!empty($multiMerchant)) $merchantData = $merchantData + $multiMerchant;
            }

            // 商品和商家对应
            foreach ($result['list'] as $key => $value) {
                // 默认加载系统商家
                $value['merchant_name'] = $merchantData[0]['merchant_name'];
                // 如果有多商家则进行覆盖
                $merchant = !empty($merchantData[$value['merchant_id']]) ? $merchantData[$value['merchant_id']] : [];
                $value['merchant_name'] = !empty($merchant['merchant_name']) ? $merchant['merchant_name'] : $value['merchant_name'];
                // 加上商家、平台标志
                $value['merchant_type'] = !empty($merchant['merchant_id']) ? '商家' : '平台';
                // 覆盖商品信息
                $result['list'][$key] = $value;
            }
        }

        // 获取商城配置信息
        $ConfigData = getUsersConfigData('shop');
        // 余额开关
        $pay_balance_open = getUsersConfigData('pay.pay_balance_open');
        if (!is_numeric($pay_balance_open) && empty($pay_balance_open)) $pay_balance_open = 1;

        // 返回data拼装
        $result['data'] = [
            // 余额支付是否开启
            'pay_balance_open'   => $pay_balance_open,
            // 温馨提示内容,为空则不展示
            'shop_prompt'        => !empty($ConfigData['shop_prompt']) ? $ConfigData['shop_prompt'] : '',
            // 是否开启线下支付(货到付款)
            'shop_open_offline'  => !empty($ConfigData['shop_open_offline']) ? $ConfigData['shop_open_offline'] : 0,
            // 是否开启运费设置
            'shop_open_shipping' => !empty($ConfigData['shop_open_shipping']) ? $ConfigData['shop_open_shipping'] : 0,
            // 初始化总额
            'TotalAmount'        => 0,
            // 初始化总数
            'TotalNumber'        => 0,
            // 提交来源:0购物车;1直接下单
            'submit_order_type'  => $submit_order_type,
            // 1表示为虚拟订单
            'PromType'           => 1,
            // 存在多种订单类型
            'arc_prom_type'      => [],
            // 商家统计数
            'merchant_count'     => 1,
            // 仅到店核销
            'onlyVerify'         => false,
            // 仅物流配送
            'onlyDelivery'       => false,
            // 物流支持
            'allLogisticsType'   => false,
        ];
        $level_discount = $this->users['level_discount'];
        foreach ($result['list'] as $key => $value) {
            // 未开启多规格则执行 
            if (!isset($ConfigData['shop_open_spec']) || empty($ConfigData['shop_open_spec'])) {
                $value['spec_value_id'] = $value['spec_price'] = $value['spec_stock'] = 0;
                $result['list'][$key]['spec_value_id'] = $result['list'][$key]['spec_price'] = $result['list'][$key]['spec_stock'] = 0;
            }

            // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
            if (!empty($value['spec_value_id']) && $value['spec_price'] >= 0) {
                // 规格价格覆盖商品原价
                $value['users_price'] = $value['spec_price'];
            }

            // 计算折扣后的价格
            if (!empty($level_discount)) {
                // 折扣率百分比
                $discount_price = $level_discount / 100;
                // 会员折扣价
                $value['users_price'] = 2 === intval($value['users_discount_type']) ? $value['users_price'] : $value['users_price'] * $discount_price;
            }

            // 查询会员折扣价
            if (empty($value['spec_value_id']) && !empty($this->users['level_id']) && 1 === intval($value['users_discount_type'])) {
                $value['users_price'] = model('ShopPublicHandle')->handleUsersDiscountPrice($value['aid'], $this->users['level_id']);
            }
            $result['list'][$key]['users_price'] = floatval(sprintf("%.2f", $value['users_price']));

            // 购物车商品存在规格并且库存不为空，则覆盖商品原来的库存
            if (!empty($value['spec_stock'])) {
                // 规格库存覆盖商品库存
                $value['stock_count'] = $value['spec_stock'];
                $result['list'][$key]['stock_count'] = $value['spec_stock'];
            }

            if ($value['product_num'] > $value['stock_count']) {
                $result['list'][$key]['product_num'] = $value['stock_count'];
                $result['list'][$key]['stock_count'] = $value['stock_count'];
            }

            // 若库存为空则清除这条数据
            if (empty($value['stock_count'])) {
                unset($result['list'][$key]);
                continue;
            }
        }
        if (empty($result['list'])) $this->error('商品库存不足或已过期！');

        $controller_name = 'Product';
        $array_new = get_archives_data($result['list'],'aid');
        // 产品数据处理
        foreach ($result['list'] as $key => $value) {
            // 非法提交，请正规合法提交订单-订单确认时检测判断-购物车商品判断
            if (empty($value['product_num'])) $this->error('非法提交，请正规合法提交订单，非法代码：401');
            
            $result['list'][$key]['subtotal'] = 0;
            // 订单商品处理
            if ($value['users_price'] >= 0 && !empty($value['product_num'])) {
                // 计算小计
                $result['list'][$key]['subtotal'] = floatval(sprintf("%.2f", $value['users_price'] * $value['product_num']));
                // 计算合计金额
                $result['data']['TotalAmount'] += $result['list'][$key]['subtotal'];
                $result['data']['TotalAmount'] = floatval(sprintf("%.2f", $result['data']['TotalAmount']));
                // 计算合计数量
                $result['data']['TotalNumber'] += $value['product_num'];
                // 判断订单类型，目前逻辑：一个订单中，只要存在一个普通产品(实物产品，需要发货物流)，则为普通订单
                // 0表示为普通订单，1表示为虚拟订单，虚拟订单无需发货物流，无需选择收货地址，无需计算运费
                if (empty($value['prom_type'])) $result['data']['PromType'] = 0;
            }

            // 产品页面链接
            $result['list'][$key]['arcurl'] = urldecode(arcurl('home/'.$controller_name.'/view', $array_new[$value['aid']]));

            // 图片处理
            $result['list'][$key]['litpic'] = handle_subdir_pic(get_default_pic($value['litpic']));
             
            // 若不存在则重新定义,避免报错
            if (empty($result['list'][$key]['ProductHidden'])) {
                $result['list'][$key]['ProductHidden'] = '<input type="hidden" name="spec_value_id[]" value="'.$value['spec_value_id'].'">';
            }

            // 产品旧参数属性处理
            $result['list'][$key]['attr_value'] = '';
            if (!empty($value['aid'])) { 
                $attrData   = Db::name('product_attr')->where('aid', $value['aid'])->field('attr_value, attr_id')->select();
                foreach ($attrData as $val) {
                    $attr_name  = Db::name('product_attribute')->where('attr_id',$val['attr_id'])->field('attr_name')->find();
                    $result['list'][$key]['attr_value'] .= $attr_name['attr_name'].'：'.$val['attr_value'].'<br/>';
                }
            }

            // 规格处理
            $result['list'][$key]['product_spec'] = '';
            $product_spec_list = [];
            if (!empty($value['spec_value_id'])) {
                $spec_value_id = explode('_', $value['spec_value_id']);
                if (!empty($spec_value_id)) {
                    $SpecWhere = [
                        'aid'           => $value['aid'],
                        'lang'          => self::$home_lang,
                        'spec_value_id' => ['IN',$spec_value_id]
                    ];
                    $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name, spec_value')->select();
                    foreach ($ProductSpecData as $spec_value) {
                        if ('v2.x' == $this->usersTplVersion || in_array($this->usersTplVersion, ['v5'])) {
                            $result['list'][$key]['product_spec'] .= $spec_value['spec_value'].'; ';
                        } else {
                            $result['list'][$key]['product_spec'] .= $spec_value['spec_value'].'&nbsp; ';
                        }
                        $product_spec_list[] = [
                            'name' => $spec_value['spec_name'],
                            'value' => $spec_value['spec_value'],
                        ];
                    }
                }
            }
            $result['list'][$key]['product_spec_list'] = $product_spec_list;

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
                    $result['data']['onlyVerify'] = true;
                }
            }
            // 仅物流配送
            if ('1' === $value['logistics_type']) {
                $result['data']['onlyDelivery'] = true;
            }
            // 物流配送 和 到店核销 都支持
            if ('1,2' === $value['logistics_type']) {
                if (!empty($verify_open)) {
                    $result['data']['allLogisticsType'] = true;
                } else {
                    $result['data']['onlyDelivery'] = true;
                }
            }
        }

        // 切换配送方式 - 点击快递配送
        $result['data']['selectDelivery'] = " id='selectDelivery' onclick='selectLogisticsType(1);' ";
        // 快递配送地址 div框
        $result['data']['selectDeliveryID'] = " id='selectDeliveryID' ";
        $result['data']['selectDeliveryAddress'] = " id='selectDeliveryAddress' ";
        // 切换配送方式 - 点击到店自提
        $result['data']['selectVerify'] = " id='selectVerify' onclick='selectLogisticsType(2);' ";
        // 到店自提地址 div框
        $result['data']['selectVerifyID'] = " id='selectVerifyID' ";
        $result['data']['selectVerifyInfo'] = " id='selectVerifyInfo' ";
        // 订单物流类型(1:快递发货; 2:到店核销;)
        $logistics_type = 1;
        if (empty($result['data']['onlyVerify'])) {
            $result['data']['selectVerifyID'] .= " style='display: none;' ";
            $result['data']['selectVerifyInfo'] .= " style='display: none;' ";
        } else {
            $result['data']['selectDeliveryID'] .= " style='display: none;' ";
            $result['data']['selectDeliveryAddress'] .= " style='display: none;' ";
            // 订单物流类型(1:快递发货; 2:到店核销;)
            $logistics_type = 2;
        }

        // 以商家为分组条件进行分组
        if (!empty($is_multiMerchant)) {
            $result['list'] = group_same_key($result['list'], 'merchant_id');
            $result['data']['merchant_count'] = count($result['list']);
        }

        // 封装初始金额隐藏域
        $result['data']['TotalAmountOld'] = '<input type="hidden" id="TotalAmount_old" value="'.$result['data']['TotalAmount'].'">';

        // 封装添加收货地址JS
        if (isWeixin() && !isWeixinApplets()) {
            $result['data']['goAddressList'] = " data-url=\"".url('user/Shop/shop_address_list', ['type'=>'order'])."\" onclick=\"goAddressList(this);\" ";
            $result['data']['ShopAddAddr'] = " onclick=\"GetWeChatAddr();\" ";
            $data['shop_add_address'] = url('user/Shop/shop_get_wechat_addr');
        } else {
            $result['data']['goAddressList'] = " data-url=\"".url('user/Shop/shop_address_list', ['type'=>'order'])."\" onclick=\"goAddressList(this);\" ";
            $result['data']['ShopAddAddr'] = " onclick=\"ShopAddAddress();\" ";
            $data['shop_add_address'] = url('user/Shop/shop_add_address');
        }

        // 移动端下单时选中的收货地址
        $ShopAddressInfo = [];
        $ShopAddressList = Db::name('shop_address')->where([
                'users_id'  => $this->users_id,
                'lang'  => self::$home_lang,
            ])->order('is_default desc')->getAllWithIndex('addr_id');
        if (!empty($ShopAddressList)) {
            $PlaceOrderAddrid = cookie('PlaceOrderAddrid');
            if (!empty($ShopAddressList[$PlaceOrderAddrid])) {
                $ShopAddressInfo = $ShopAddressList[$PlaceOrderAddrid];
            } else {
                $ShopAddressInfo = current($ShopAddressList);
            }
            $ShopAddressInfo['ul_il_id'] = "{$ShopAddressInfo['addr_id']}_ul_li";
            // 封装收货地址信息
            $ShopAddressInfo['country']  = '';
            $ShopAddressInfo['province'] = get_province_name($ShopAddressInfo['province']);
            $ShopAddressInfo['city']     = get_city_name($ShopAddressInfo['city']);
            $ShopAddressInfo['district'] = get_area_name($ShopAddressInfo['district']);
            $ShopAddressInfo['Info']     = $ShopAddressInfo['province'].' '.$ShopAddressInfo['city'].' '.$ShopAddressInfo['district'];
        }
        if (empty($ShopAddressInfo)) {
            $result['data']['ShopAddressInfo'] = $ShopAddressInfo;
        } else {
            $result['data']['ShopAddressInfo'][] = $ShopAddressInfo;
        }

        // 第三套模板使用，若存在收货地址则进入收货地址列表，没有则进入添加收货和获取微信收货地址处理页
        if (in_array($this->usersTplVersion, ['v3'])) {
            if (!empty($ShopAddressInfo)) {
                $result['data']['goAddressList'] = " data-url=\"".url('user/Shop/shop_address_list', ['type'=>'order'])."\" onclick=\"goAddressList(this);\" ";
            } else {
                $result['data']['goAddressList'] = " data-url=\"".url('user/Shop/shop_add_address', ['type'=>'order'])."\" onclick=\"goAddressList(this);\" ";
            }
        }

        // 封装UL的ID,用于添加收货地址
        $result['data']['UlHtmlId']       = " id=\"UlHtml\" ";
        // 封装选择支付方式JS
        $result['data']['OnlinePay']      = " onclick=\"ColorS('zxzf')\" id=\"zxzf\"  ";
        $result['data']['DeliveryPay']    = " onclick=\"ColorS('hdfk')\" id=\"hdfk\"  ";
        // 封装运费信息
        if (empty($result['data']['shop_open_shipping'])) {
            $result['data']['Shipping'] = " 包邮 ";
            $result['data']['ShippingMoney'] = 0;
        } else {
            $result['data']['Shipping'] = " <span id=\"template_money\">0</span> ";
            $result['data']['ShippingMoney'] = " <span id=\"shipping_money\">0</span> ";
        }
        // 封装全部产品总额ID，用于计算总额
        $result['data']['TotalAmountId'] = " id=\"TotalAmount\" ";
        // 封装返回购物车链接
        $result['data']['ReturnCartUrl'] = url('user/Shop/shop_cart_list');
        // 封装提交订单JS
        $result['data']['wechatJsApiPay'] = " onclick=\"wechatJsApiPay();\" ";
        $result['data']['ShopPaymentPage'] = " onclick=\"ShopPaymentPage();\" ";
        
        // 封装表单验证隐藏域
        static $request = null;
        if (null == $request) { $request = Request::instance(); }  
        $token = $request->token('__token__');
        $result['data']['TokenValue'] = " <input type='hidden' name='__token__' id='__token__dfbfa92d4c447bf2c942c7d99a223b49' value='{$token}'/> ";

        /*封装用于余额支付计算*/
        // 会员信息
        $usersInfo = Db::name('users')->field('users_money')->where(['users_id'=>$this->users_id])->find();
        if (!empty($usersInfo)) {
            $result['data']['UsersMoney'] = floatval($usersInfo['users_money']);
            $UsersSurplusMoney = floatval($usersInfo['users_money']) - floatval($result['data']['TotalAmount']);
            $result['data']['UsersSurplusMoney'] = floatval($UsersSurplusMoney);
        }
        // 用于计算总额
        $result['data']['PayTotalAmountID'] = " id=\"PayTotalAmountID\" ";
        // 用于获取会员可用余额
        $result['data']['UsersSurplusMoneyID'] = " id=\"UsersSurplusMoneyID\" ";
        /* END */

        /*默认选中支付方式判断逻辑*/
        $payApiHidden = model('ShopPublicHandle')->getPayApiHidden($result['data']);
        $result['data']['use_pay_type'] = $payApiHidden['usePayType'];
        $result['data']['PayTypeHidden'] = $payApiHidden['payTypeHidden'];
        /* END */

        // 传入JS参数
        $data['onlyVerify'] = $result['data']['onlyVerify'];
        $data['onlyDelivery'] = $result['data']['onlyDelivery'];
        $data['allLogisticsType'] = $result['data']['allLogisticsType'];
        $data['UsersMoney'] = !empty($usersInfo['users_money']) ? $usersInfo['users_money'] : 0;
        $data['shop_edit_address'] = url('user/Shop/shop_edit_address');
        $data['shop_del_address']  = url('user/Shop/shop_del_address', ['_ajax' => 1], true, false, 1, 1, 0);
        $data['shop_inquiry_shipping']  = url('user/Shop/shop_inquiry_shipping', ['_ajax' => 1], true, false, 1, 1, 0);
        $data['shop_payment_page'] = url('user/Shop/shop_payment_page', ['_ajax' => 1], true, false, 1, 1, 0);
        $data['shop_centre_url'] = url('user/Shop/shop_centre');
        $data['pich_up_list_url'] = url('user/Shop/get_pick_up_list');
        $data['verifyStore'] = url('user/Shop/select_verify_store');
        $data['usersTplVersion'] = $this->usersTplVersion;
        $data['usersTpl2xVersion'] = !empty($this->usersTpl2xVersion) ? $this->usersTpl2xVersion : '';
        $data['totalAmountOld'] = !empty($result['data']['TotalAmount']) ? unifyPriceHandle($result['data']['TotalAmount']) : 0;
        // 会员模板版本号
        if (empty($this->usersTplVersion) || 'v1' == $this->usersTplVersion) {
            $jsfile = "tag_spsubmitorder.js";
        } else {
            $jsfile = "tag_spsubmitorder_{$this->usersTplVersion}.js";
        }

        $data['is_wap'] = 0;
        if (isWeixin() || isMobile()) {
            $data['is_wap'] = 1;
            $data['addr_width']  = '100%';
            $data['addr_height'] = '100%';
        } else {
            if (in_array($this->usersTplVersion, ['v3', 'v5']) || in_array($this->usersTpl2xVersion, ['v2.x'])) {
                $data['addr_width']  = '660px';
                $data['addr_height'] = '392px';
            } else {
                $data['addr_width']  = '350px';
                $data['addr_height'] = '480px';
            }
        }
        $data_json = json_encode($data);
        $version   = getCmsVersion();
        // 循环中第一个数据带上JS代码加载
        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
        $result['data']['hidden'] = <<<EOF
<input type="hidden" name="prom_type" value="{$result['data']['PromType']}">
<input type="hidden" name="store_id" id="store_id" value="0">
<input type="hidden" name="logistics_type" id="logistics_type" value="{$logistics_type}">
<input type="hidden" id="querystr" value="{$GetMd5}">
<input type="hidden" id="submit_order_type" value="{$submit_order_type}">
<script type="text/javascript">
    var b1decefec6b39feb3be1064e27be2a9 = {$data_json};
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;

        if (empty($result['list'])) {
            action('user/Shop/shop_under_order', false);
            exit;
        }

        return $result;
    }
}