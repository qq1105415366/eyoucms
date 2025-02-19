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
 * Date: 2019-3-20
 */

namespace app\user\model;

use think\Model;
use think\Db;
use think\Config;
use think\Page;

/**
 * 商城
 */
class Shop extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->home_lang = get_home_lang();
    }

    // 处理购买订单，超过指定时间修改为已订单过期，针对未付款订单
    public function UpdateShopOrderData($users_id){
        $time  = getTime() - Config::get('global.get_shop_order_validity');
        $where = array(
            'users_id'     => $users_id,
            'order_status' => 0,
            'add_time'     => array('<',$time),
        );
        $data = [
            'order_status'    => 4,  // 状态修改为订单过期
            'pay_name'        => '', // 订单过期则清空支付方式标记
            'wechat_pay_type' => '', // 订单过期则清空微信支付类型标记
            'update_time'     => getTime(),
        ];

        // 查询订单id数组用于添加订单操作记录
        $OrderIds = Db::name('shop_order')->field('order_id')->where($where)->select();

        // 订单过期，更新规格数量
        $productSpecValueModel = new \app\user\model\ProductSpecValue;
        $productSpecValueModel->SaveProducSpecValueStock($OrderIds, $users_id);

        //批量修改订单状态 
        Db::name('shop_order')->where($where)->update($data);
        
        // 添加订单操作记录
        if (!empty($OrderIds)) {
	        AddOrderAction($OrderIds,$users_id,'0','4','0','0','订单过期！','会员未在订单有效期内支付，订单过期！');
        }
    }

    // 通过商品名称模糊查询订单信息
    public function QueryOrderList($pagesize,$users_id,$keywords,$query_get){
        // 商品名称模糊查询订单明细表，获取订单主表ID
        $DetailsWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
        ];
        $DetailsWhere['product_name'] =  ['LIKE', "%{$keywords}%"];
        $DetailsData = Db::name('shop_order_details')->field('order_id')->where($DetailsWhere)->select();
        // 若查无数据，则返回false
        if (empty($DetailsData)) {
            return false;
        }

        $order_ids = '';
        // 处理订单ID，查询订单主表信息
        foreach ($DetailsData as $key => $value) {
            if ('0' < $key) {
                $order_ids .= ',';
            }
            $order_ids .= $value['order_id'];
        }
        // 查询条件
        $OrderWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
            'order_id' => ['IN', $order_ids],
        ];

        $paginate_type = 'userseyou';
        if (isMobile()) {
            $paginate_type = 'usersmobile';
        }

        $paginate = array(
            'type'     => $paginate_type,
            'var_page' => config('paginate.var_page'),
            'query'    => $query_get,
        );

        $pages = Db::name('shop_order')
            ->field("*")
            ->where($OrderWhere)
            ->order('add_time desc')
            ->paginate($pagesize, false, $paginate);

        $data['list']  = $pages->items();
        $data['pages'] = $pages;

        return $data;
    }

    public function GetOrderIsEmpty($users_id,$keywords,$select_status){
        // 基础查询条件
        $OrderWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
        ];

        // 应用搜索条件
        if (!empty($keywords)) {
            $OrderWhere['order_code'] =  ['LIKE', "%{$keywords}%"];
        }

        // 订单状态搜索
        if (!empty($select_status)) {
            if ('dzf' === $select_status) {
                $select_status = 0;
            }
            $OrderWhere['order_status'] = $select_status;
            if (3 == $select_status){
                if (in_array(getUsersTpl2xVersion(), ['v2.x']) || in_array(getUsersTplVersion(), ['v5'])) {

                } else {
                    $OrderWhere['is_comment'] = 0;
                }
            }
        }

        $order = Db::name('shop_order')->where($OrderWhere)->count();
        // 查询存在数据，则返回1
        if (!empty($order)) {
            return 1; exit;
        }
        
        // 查询订单明细表
        if (empty($order) && !empty($keywords)) {
            $DetailsWhere = [
                'users_id' => $users_id,
                'lang'     => $this->home_lang,
            ];
            $DetailsWhere['product_name'] =  ['LIKE', "%{$keywords}%"];
            $DetailsData = Db::name('shop_order_details')->field('order_id')->where($DetailsWhere)->select();
            // 查询无数据，则返回0
            if (empty($DetailsData)) {
                return 0; exit;
            }

            $order_ids = '';
            // 处理订单ID，查询订单主表信息
            foreach ($DetailsData as $key => $value) {
                if (0 < $key) {
                    $order_ids .= ',';
                }
                $order_ids .= $value['order_id'];
            }
            // 查询条件
            $OrderWhere = [
                'users_id' => $users_id,
                'lang'     => $this->home_lang,
                'order_id' => ['IN', $order_ids],
            ];

            $order2 = Db::name('shop_order')->where($OrderWhere)->count();
            if (!empty($order2)) {
                return 1; exit;
            }else{
                return 0; exit;
            }
        }
    }

    // 获取微信公众号access_token
    // 传入微信公众号appid
    // 传入微信公众号secret
    // 返回data
    public function GetWeChatAccessToken($appid,$secret){
        // 获取公众号access_token，接口限制10万次/天
        $time = getTime();
        $get_token_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
        $TokenData = httpRequest($get_token_url);
        $TokenData = json_decode($TokenData, true);
        if (!empty($TokenData['access_token'])) {
            // 存入缓存配置
            $WechatData  = [
                'wechat_token_value' => $TokenData['access_token'],
                'wechat_token_time'  => $time,
            ];
            getUsersConfigData('wechat',$WechatData);

            $setting_info = [
                'appid' => $appid,
                'secret' => $secret,
                'access_token' => $TokenData['access_token'],
                'expires_time' => getTime() + $TokenData['expires_in'] - 1000, //提前200s过期
            ];
            tpSetting(md5($appid), $setting_info);
            
            $data = [
                'status' => true,
                'token'  => $WechatData['wechat_token_value'],
            ];
        }else{
            $data = [
                'status' => false,
                'prompt' => '错误提示：101，后台配置配置AppId或AppSecret不正确，请检查！',
            ];
        }
        return $data;
    }

    // 获取微信公众号jsapi_ticket
    // 传入微信公众号accesstoken
    // 返回data
    public function GetWeChatJsapiTicket($accesstoken){
        // 获取公众号jsapi_ticket
        $time = getTime();
        $get_ticket_url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accesstoken.'&type=jsapi';
        $TicketData = httpRequest($get_ticket_url);
        $TicketData = json_decode($TicketData, true);
        if (!empty($TicketData['ticket'])) {
            // 存入缓存配置
            $WechatData  = [
                'wechat_ticket_value' => $TicketData['ticket'],
                'wechat_ticket_time'  => $time,
            ];
            getUsersConfigData('wechat',$WechatData);
            $data = [
                'status' => true,
                'ticket' => $WechatData['wechat_ticket_value'],
            ];
        }else{
            $data = [
                'status' => false,
                'prompt' => '错误提示：102，后台配置配置AppId或AppSecret不正确，请检查！',
            ];
        }
        return $data;
    }

    // 获取随机字符串
    // 长度 length
    // 结果 str
    public function GetRandomString($length){
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    // 旧产品属性处理
    public function ProductAttrProcessing($value = array())
    {
        $attr_value = '';
        $AttrWhere = [
            'a.aid'     => $value['aid'],
            'b.lang'    => $this->home_lang
        ];
        $attrData = Db::name('product_attr')
            ->alias('a')
            ->field('a.attr_value as value, b.attr_name as name')
            ->join('__PRODUCT_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($AttrWhere)
            ->order('b.sort_order asc, a.attr_id asc')
            ->select();
        foreach ($attrData as $val) {
            $attr_value .= $val['name'].'：'.$val['value'].'<br/>';
        }
        return $attr_value;
    }

    // 新产品属性处理
    public function ProductNewAttrProcessing($value = array())
    {
        $attr_value = '';
        if (!empty($value['merchant_id'])) {
            // 多商家商品则查询自定义参数
            $where = [
                'aid' => $value['aid'],
            ];
            $order = 'sort_order asc, param_id asc';
            $field = 'param_name as name, param_value as value';
            $attrData = Db::name('product_custom_param')->field($field)->where($where)->order($order)->select();
        } else {
            // 新版参数
            if (!empty($value['attrlist_id'])) {
                $where = [
                    'b.aid' => $value['aid'],
                    'a.list_id' => ['IN', [0, $value['attrlist_id']]],
                    'a.status' => 1,
                ];
            }else{
                $where = [
                    'b.aid' => $value['aid'],
                    'a.list_id' => 0,
                    'a.status' => 1,
                ];
            }
            $attrData = Db::name('shop_product_attribute')
                ->alias('a')
                ->field('a.attr_name as name, b.attr_value as value')
                ->join('__SHOP_PRODUCT_ATTR__ b', 'a.attr_id = b.attr_id', 'LEFT')
                ->where($where)
                ->order('a.sort_order asc, a.attr_id asc')
                ->select();
        }
        
        foreach ($attrData as $val) {
            $attr_value .= $val['name'].'：'.$val['value'].'<br/>';
        }
        return $attr_value;
    }

    // 产品规格处理
    public function ProductSpecProcessing($value = array())
    {
        $spec_value_s = '';
        if (!empty($value['spec_value_id'])) {
            $spec_value_id = explode('_', $value['spec_value_id']);
            if (!empty($spec_value_id)) {
                $SpecWhere = [
                    'aid'           => $value['aid'],
                    'lang'          => $this->home_lang,
                    'spec_value_id' => ['IN',$spec_value_id],
                ];
                $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name,spec_value')->select();
                foreach ($ProductSpecData as $spec_value) {
                    $spec_value_s .= $spec_value['spec_name'].'：'.$spec_value['spec_value'].'<br/>';
                }
            }
        }
        return $spec_value_s;
    }

    // 产品库存处理
    public function ProductStockProcessing($SpecValue = array())
    {   
        $SpecUpData = []; // 有规格
        $ArcUpData  = []; // 无规格
        foreach ($SpecValue as $key => $value) {
            if (!empty($value['value_id'])) {
                $SpecUpData[] = [
                    'value_id'   => $value['value_id'],
                    'spec_stock' => Db::raw('spec_stock-'.($value['quantity'])),
                    'spec_sales_num' => Db::raw('spec_sales_num+'.($value['quantity'])),
                ];
                
                $ArcUpData[] = [
                    'aid'         => $value['aid'],
                    'stock_count' => Db::raw('stock_count-' . ($value['quantity'])),
                    'sales_num'   => Db::raw('sales_num+' . ($value['quantity'])),
                    'sales_all'   => Db::raw('sales_all+' . ($value['quantity'])),
                ];
            }else{
                $ArcUpData[] = [
                    'aid'         => $value['aid'],
                    'stock_count' => Db::raw('stock_count-'.($value['quantity'])),
                    'sales_num'   => Db::raw('sales_num+' . ($value['quantity'])),
                    'sales_all'   => Db::raw('sales_all+' . ($value['quantity'])),
                ];
            }
        }

        // 更新规格库存销量
        if (!empty($SpecUpData)) model('ProductSpecValue')->saveAll($SpecUpData);

        // 更新商品库存销量
        if (!empty($ArcUpData)) model('Archives')->saveAll($ArcUpData);
    }

    /**
     * 生成订单之前的产品数据整理 - 【第一步】
     * @return [type] [description]
     */
    public function handlerOrderData($opt = 'normal', $OrderData = [], $list = [], $users = [], $post = [])
    {
        $aid = 0;
        if ('fast' == $opt) {
            $aid = !empty($list[0]['aid']) ? $list[0]['aid'] : 0;
        }
        // 会员配置
        $usersConfig = getUsersConfigData('all');

        // 产品数据处理
        $PromType = $ContainsVirtual = 1; // 1表示为虚拟订单
        $TotalAmount = $TotalNumber = 0;
        $level_discount = $users['level_discount'];
        foreach ($list as $key => $value) {
            // 未开启多规格则执行 
            if (!isset($usersConfig['shop_open_spec']) || empty($usersConfig['shop_open_spec'])) {
                $value['spec_value_id'] = $value['spec_price'] = $value['spec_stock'] = $value['value_id'] = 0;
            }

            // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
            if (!empty($value['spec_value_id']) && $value['spec_price'] >= 0) $value['users_price'] = $value['spec_price'];

            // 计算折扣后的价格
            if (!empty($level_discount)) {
                // 折扣率百分比
                $discount_price = $level_discount / 100;
                // 会员折扣价
                $value['users_price'] = 2 === intval($value['users_discount_type']) ? $value['users_price'] : $value['users_price'] * $discount_price;
            }

            // 查询会员折扣价
            if (empty($value['spec_value_id']) && !empty($users['level_id']) && 1 === intval($value['users_discount_type'])) {
                $value['users_price'] = model('ShopPublicHandle')->handleUsersDiscountPrice($value['aid'], $users['level_id']);
            }
            $value['users_price'] = sprintf("%.2f", $value['users_price']);

            // 购物车商品存在规格并且库存不为空，则覆盖商品原来的库存
            if (!empty($value['spec_stock'])) $value['stock_count'] = $value['spec_stock'];

            // 若库存为空则返回提示
            if (empty($value['stock_count'])) return ['code'=>0, 'msg'=>"产品 《{$value['title']}》 库存不足！"];

            // 非法提交，请正规合法提交订单-提交生成订单时检测判断
            if (empty($value['product_num'])) return ['code'=>0, 'msg'=>'非法提交，请正规合法提交订单，非法代码：402'];

            // 金额、数量计算
            if ($value['users_price'] >= 0 && !empty($value['product_num'])) {
                // 合计金额
                $TotalAmount += sprintf("%.2f", $value['users_price'] * $value['product_num']);
                // 合计数量
                $TotalNumber += $value['product_num'];
                // 判断订单类型，目前逻辑：一个订单中，只要存在一个普通产品(实物产品，需要发货物流)，则为普通订单
                if (empty($value['prom_type'])) $PromType = 0;// 0表示为普通订单
                // 判断是否包含虚拟商品，只要存在一个虚拟商品则表示包含虚拟商品
                if (!empty($value['prom_type']) && $value['prom_type'] >= 1) $ContainsVirtual = 2;
            }
            $list[$key] = $value;
        }

        // 添加到订单主表
        $time = getTime();
        $OrderData['order_code']        = date('Ymd').$time.rand(10,100); //订单生成规则
        $OrderData['users_id']          = $users['users_id'];
        $OrderData['order_status']      = 0; // 订单未付款
        $OrderData['add_time']          = $time;
        $OrderData['payment_method']    = !empty($post['payment_method']) ? intval($post['payment_method']) : 0; // 订单支付方式
        $OrderData['order_total_amount']= $TotalAmount;
        $OrderData['order_amount']      = $TotalAmount;
        $OrderData['order_total_num']   = $TotalNumber;
        $OrderData['contains_virtual']  = $ContainsVirtual;
        $OrderData['prom_type']         = $PromType;
        $OrderData['user_note']         = !empty($post['message']) ? $post['message'] : ''; // 会员备注
        $OrderData['lang']              = $this->home_lang;

        /*特定场景专用*/
        $opencodetype = config('global.opencodetype');
        if (1 == $opencodetype) {
            $aid = !empty($list[0]['aid']) ? $list[0]['aid'] : 0;
            $OrderData['spec_value_id'] = !empty($OrderData['spec_value_id']) ? "_".trim($OrderData['spec_value_id'], '_')."_" : '';
            // 订单分组
            $group = 'archives';
            if (74 == $aid) {
                $group = 'cloudminipro';
            } else if (75 == $aid) {
                $group = 'cloudminiproMall';
            }
            $OrderData['group'] = $group;
            // 规格值
            $spec_value_ids =  $OrderData['spec_value_id'];
            // 购买套餐
            $buy_type = 1;
            if (stristr($spec_value_ids, '_1_')) {
                $buy_type = 2;
            } else if (stristr($spec_value_ids, '_2_')) {
                $buy_type = 3;
            }
            $OrderData['buy_type'] = $buy_type;
            // 购买天数
            $buy_day = 0;
            if (stristr($spec_value_ids, '_4_')) {
                $buy_day = 365;
            } else if (stristr($spec_value_ids, '_5_')) {
                $buy_day = 365 * 2;
            }
            $OrderData['buy_day'] = $buy_day;
        }
        /*end*/

        if (isMobile() && isWeixin()) {
            $OrderData['pay_name'] = 'wechat';// 如果在微信端中则默认为微信支付
            $OrderData['wechat_pay_type'] = 'WeChatInternal';// 如果在微信端中则默认为微信端调起支付
        }

        if (1 == $OrderData['payment_method']) {
            // 追加添加到订单主表的数组
            $OrderData['order_status'] = 1; // 标记已付款
            $OrderData['pay_time']     = $time;
            $OrderData['pay_name']     = 'delivery_pay';// 货到付款
            $OrderData['wechat_pay_type'] = ''; // 选择货到付款，则去掉微信端调起支付标记
            $OrderData['update_time']  = $time;
        }

        // 判断订单来源
        $isMobile = isMobile();
        $isWeixin = isWeixin();
        $isWeixinApplets = isWeixinApplets();
        if (empty($isMobile) && empty($isWeixin)) {
            $OrderData['order_terminal'] = 1;
        } else if (!empty($isMobile) && empty($isWeixinApplets)) {
            $OrderData['order_terminal'] = 2;
        } else if (!empty($isMobile) && !empty($isWeixinApplets)) {
            $OrderData['order_terminal'] = 3;
        }

        return ['code'=>1, 'msg'=>'ok', 'data'=>['OrderData'=>$OrderData, 'list'=>$list]];
    }

    /**
     * 生成订单之后的订单明细整理 - 【第二步】
     * @return [type] [description]
     */
    public function handlerDetailsData($opt = 'normal', $OrderData = [], $list = [], $users = [])
    {
        $OrderId = $OrderData['order_id'];
        $cart_ids   = $UpSpecValue = [];
        $attr_value = $spec_value = '';

        if (1 == count($list) && !empty($list[0]['under_order_type'])) {
            /*----------删除用户旧的未付款的同类记录 start ------------*/
            $order_md5 = md5($list[0]['aid'].$list[0]['spec_value_id']);
            $where = [
                'order_md5'     => $order_md5,
                'users_id'      => intval($users['users_id']),
                'order_id'      => ['NEQ', $OrderId],
                'order_status'  => 0,
            ];
            $opencodetype = config('global.opencodetype');
            if (1 == $opencodetype) {
                $where['group'] = $OrderData['group'];
            }
            $order_id_arr = Db::name('shop_order')->where($where)->column('order_id');
            if (!empty($order_id_arr)) {
                $r = Db::name('shop_order')->where([
                        'order_id'  => ['IN', $order_id_arr],
                    ])->delete();
                if (false !== $r) {
                    Db::name('shop_order_details')->where([
                        'order_id'  => ['IN', $order_id_arr],
                    ])->delete();
                }
            }
            /*----------删除用户旧的未付款的同类记录 end ------------*/
        }

        /*----------------订单副表添加数组--------------*/
        // 添加到订单明细表
        foreach ($list as $key => $value) {
            // 旧产品属性处理
            $attr_value = model('Shop')->ProductAttrProcessing($value);
            // 新产品属性处理
            $attr_value_new = model('Shop')->ProductNewAttrProcessing($value);
            // 产品规格处理
            $spec_value = model('Shop')->ProductSpecProcessing($value);
            $Data = [
                // 产品属性
                'attr_value' => htmlspecialchars($attr_value),
                // 产品属性
                'attr_value_new' => htmlspecialchars($attr_value_new),
                // 产品规格
                'spec_value' => htmlspecialchars($spec_value),
                // 产品规格值ID
                'spec_value_id' => $value['spec_value_id'],
                // 对应规格值ID的唯一标识ID，数据表主键ID
                'value_id'   => $value['value_id'],
                // 后续添加
            ];

            // 订单副表添加数组
            $OrderDetailsData[] = [
                'order_id'      => $OrderId,
                'users_id'      => intval($users['users_id']),
                'product_id'    => $value['aid'],
                'product_name'  => $value['title'],
                'num'           => $value['product_num'],
                'data'          => serialize($Data),
                'product_price' => $value['users_price'],
                'prom_type'     => $value['prom_type'],
                'litpic'        => $value['litpic'],
                'add_time'      => getTime(),
                'lang'          => $this->home_lang,
            ];

            // 处理购物车ID
            if (empty($value['under_order_type'])) array_push($cart_ids, $value['cart_id']);

            // 产品库存处理
            $UpSpecValue[] = [
                'aid'           => $value['aid'],
                'value_id'      => $value['value_id'],
                'quantity'      => $value['product_num'],
                'spec_value_id' => $value['spec_value_id'],
            ];
        }

        return [
            'code'  => 1,
            'msg'   => 'ok',
            'data'  => [
                'cart_ids'          => $cart_ids,
                'OrderDetailsData'  => $OrderDetailsData,
                'UpSpecValue'       => $UpSpecValue,
            ]
        ];
    }


    /*------陈风任---2021-1-12---售后服务(退换货)------开始------*/

    // 读取会员自身所有服务单信息
    public function GetAllServiceInfo($users_id = null, $order_code = null, $status = null, $keywords = null, $statusArrEd = [], $statusArrIng = [])
    {
        $where = [
            'users_id' => $users_id
        ];
        if ('ing' == $status) {
            $where['status'] = ['IN', $statusArrIng];
        } else if ('ed' == $status) {
            $where['status'] = ['IN', $statusArrEd];
        }
        if (!empty($order_code)) $where['order_code'] = ['LIKE', "%{$order_code}%"];
        if (!empty($keywords)) $where['order_code|product_name'] = ['LIKE', "%{$keywords}%"];

        $count   = Db::name('shop_order_service')->where($where)->count('service_id');
        $pageObj = new Page($count, config('paginate.list_rows'));
        // 订单主表数据查询
        $Service = Db::name('shop_order_service')->where($where)
            ->order('status asc, add_time desc, service_id desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $handleArr = [];
        $New = get_archives_data($Service, 'product_id');
        foreach ($Service as $key => $value) {
            $Service[$key]['status_old']   = intval($value['status']);
            $Service[$key]['handle']       = in_array($value['status'], $statusArrEd) ? '已完成' : '处理中';
            $Service[$key]['status']       = Config::get('global.order_service_status')[$value['status']];
            $Service[$key]['service_type'] = Config::get('global.order_service_type')[$value['service_type']];
            $Service[$key]['ArchivesUrl']  = urldecode(arcurl('home/Product/view', $New[$value['product_id']]));
            $Service[$key]['OrDetailsUrl'] = url('user/Shop/shop_order_details', ['order_id'=>$value['order_id']]);
            $Service[$key]['SeDetailsUrl'] = url('user/Shop/after_service_details', ['service_id'=>$value['service_id']]);
            $Service[$key]['product_spec'] = str_replace("&lt;br/&gt;", " &nbsp; ", $value['product_spec']);

            // 规格
            $product_spec = !empty($Service[$key]['product_spec']) ? htmlspecialchars_decode($Service[$key]['product_spec']) : '';
            $product_spec_list = [];
            $product_spec_arr = explode('<br/>', $product_spec);
            foreach ($product_spec_arr as $sp_key => $sp_val) {
                $sp_arr = explode('：', $sp_val);
                if (trim($sp_arr[0]) && !empty($sp_arr[0])) {
                    $product_spec_list[] = [
                        'name'  => !empty($sp_arr[0]) ? trim($sp_arr[0]) : '',
                        'value' => !empty($sp_arr[1]) ? trim($sp_arr[1]) : '',
                    ];
                }
            }
            $Service[$key]['product_spec_list'] = $product_spec_list;

            // 清除拒接、删除售后订单
            if (in_array($value['status'], [3, 8])) {
                array_push($handleArr, $Service[$key]);
                unset($Service[$key]);
            }
        }
        // 将拒接、删除售后订单拼到最后
        if (!empty($handleArr)) $Service = array_merge($Service, $handleArr);
        
        $Return['Service'] = $Service;
        $Return['pageStr'] = $pageObj->show();

        return $Return;
    }

    // 服务详情信息
    public function GetServiceDetailsInfo($service_id = null, $users_id = null)
    {
        $Return = [];
        if (empty($service_id) || empty($users_id)) return $Return;
        $where = [
            'users_id'   => $users_id,
            'service_id' => $service_id
        ];
        $Service = Db::name('shop_order_service')->where($where)->select();
        if (empty($Service)) return $Return;
        $New = get_archives_data($Service, 'product_id');
        $Service = $Service[0];
        $Service['arcurl']       = urldecode(arcurl('home/Product/view', $New[$Service['product_id']]));
        $Service['CancelUrl']    = url('user/Shop/after_service_details', ['_ajax' => 1, 'details_id' => $Service['details_id'], 'order_id' => $Service['order_id']]);
        $Service['StatusName']   = Config::get('global.order_service_status')[$Service['status']];
        $Service['upload_img']   = !empty($Service['upload_img']) ? explode(',', $Service['upload_img']) : [];
        $Service['product_img']  = handle_subdir_pic(get_default_pic($Service['product_img']));
        if (isMobile()) {
            $Service['product_spec'] = htmlspecialchars_decode($Service['product_spec']);
        } else {
            $Service['product_spec'] = str_replace("&lt;br/&gt;", " <br/> ", $Service['product_spec']);
        }
        $Service['service_type_old'] = $Service['service_type'];
        $Service['service_type'] = Config::get('global.order_service_type')[$Service['service_type']];
        $Service['admin_delivery'] = !empty($Service['admin_delivery']) ? unserialize($Service['admin_delivery']) : ['name'=>'', 'code'=>''];
        $Service['product_total'] = floatval(sprintf("%.2f", $Service['refund_price'] * (string)$Service['product_num']));
        /*计算退还余额*/
        $field_new = 'b.details_id, b.product_price, b.data, b.num, a.shipping_fee, a.order_total_num';
        $where_new = [
            'b.order_id' => $Service['order_id'],
            'b.details_id' => $Service['details_id'],
            'b.apply_service' => 1
        ];
        $Order = Db::name('shop_order')->alias('a')
            ->field($field_new)
            ->join('__SHOP_ORDER_DETAILS__ b', 'a.order_id = b.order_id', 'LEFT')
            ->where($where_new)
            ->find();
        // 运费计算
        $ShippingFee = 0;
        $Service['ShippingFee'] = floatval($ShippingFee);
        // if (!empty($Order['shipping_fee'])) {
        //     $ShippingFee = sprintf("%.2f", ($Order['shipping_fee'] / (string)$Order['order_total_num']) * (string)$Service['product_num']);
        //     $Service['ShippingFee'] = floatval($ShippingFee);
        // }
        $Service['refund_total_price'] = floatval((string)$Service['product_total'] - (string)$ShippingFee);
        // 规格
        $product_spec_list = [];
        $spec_data = unserialize($Order['data']);
        if (!empty($spec_data['spec_value'])) {
            $spec_value_arr = explode('<br/>', htmlspecialchars_decode($spec_data['spec_value']));
            foreach ($spec_value_arr as $sp_key => $sp_val) {
                $sp_arr = explode('：', $sp_val);
                if (trim($sp_arr[0]) && !empty($sp_arr[0])) {
                    $product_spec_list[] = [
                        'name'  => !empty($sp_arr[0]) ? trim($sp_arr[0]) : '',
                        'value' => !empty($sp_arr[1]) ? trim($sp_arr[1]) : '',
                    ];
                }
            }
        }
        $Service['product_spec_list'] = $product_spec_list;
        /* END */
        return $Service;
    }

    // 服务详情记录
    public function GetOrderServiceLog($service_id = null, $Users = [], $users_id = 0) {
        if (empty($service_id) || empty($Users)) return [];
        $Log = Db::name('shop_order_service_log')->order('log_id desc')->where('service_id', $service_id)->select();
        foreach ($Log as $key => $value) {
            if (!empty($value['users_id'])) {
                if (intval($users_id) === intval($value['users_id'])) {
                    $Log[$key]['name'] = '会员';
                } else {
                    $Log[$key]['name'] = '商家';
                }
            } else if (!empty($value['admin_id'])) {
                $Log[$key]['name'] = '商家';
            }
        }
        return $Log;
    }

    // 查询订单数据
    public function GetOrderDetailsInfo($details_id = null, $users_id = null)
    {
        $Return = [];
        if (empty($details_id) || empty($users_id)) return $Return;

        // 查询订单明细数据并处理
        $where = [
            'details_id' => $details_id,
            'users_id' => $users_id
        ];
        $Details = Db::name('shop_order_details')->where($where)->find();
        if (empty($Details)) {
            $Return = [
                'code' => 0,
                'msg'  => '售后服务单不存在'
            ];
            return $Return;
        }

        if (!empty($Details) && 1 === intval($Details['apply_service'])) {
            $Return = [
                'code' => 0,
                'msg'  => '已提交过申请，请查阅退换货信息'
            ];
            return $Return;
        }

        $array_new = get_archives_data([$Details], 'product_id');
        // 商品封面图处理
        $Details['litpic'] = handle_subdir_pic(get_default_pic($Details['litpic']));
        // 商品详情页URL
        $Details['arcurl'] = urldecode(arcurl('home/Product/view', $array_new[$Details['product_id']]));
        // 获取订单商品规格列表(购买时的商品规格)
        $Details['product_spec_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($Details);
        // 商品其他处理
        $Details['data'] = unserialize($Details['data']);
        $Details['spec_value'] = htmlspecialchars_decode(htmlspecialchars_decode($Details['data']['spec_value']));
        $Details['spec_value_id'] = $Details['data']['spec_value_id'];
        $Details['value_id'] = $Details['data']['value_id'];
        unset($Details['data']);

        // 查询订单主表数据并处理
        $field = 'order_code, merchant_id, consignee, province, city, district, address, mobile, allow_service';
        $Order = Db::name('shop_order')->field($field)->where('order_id', $Details['order_id'])->find();
        // 判断订单是否允许申请售后
        if (isset($Order['allow_service']) && 1 === intval($Order['allow_service'])) {
            $Return = [
                'code' => 0,
                'msg'  => '该订单不允许申请售后维权！'
            ];
            return $Return;
        }
        $Order['province'] = get_province_name($Order['province']);
        $Order['city']     = get_city_name($Order['city']);
        $Order['district'] = get_area_name($Order['district']);

        // 合并数据返回
        $Return = array_merge($Details, $Order);
        return $Return;
    }
    /*------陈风任---2021-1-12---售后服务(退换货)------结束------*/
}

