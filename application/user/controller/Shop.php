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

namespace app\user\controller;

use think\Db;
use think\Config;
use think\Page;
use think\Cookie;
use app\common\logic\ShopCommonLogic;

class Shop extends Base
{
    // 初始化
    public function _initialize() {
        parent::_initialize();
        $this->users_db              = Db::name('users');               // 会员数据表
        $this->users_money_db        = Db::name('users_money');         // 会员金额明细表

        $this->shop_cart_db          = Db::name('shop_cart');           // 购物车表
        $this->shop_order_db         = Db::name('shop_order');          // 订单主表
        $this->shop_order_details_db = Db::name('shop_order_details');  // 订单明细表
        $this->shop_order_service_db = Db::name('shop_order_service');  // 订单售后服务表
        $this->shop_address_db       = Db::name('shop_address');        // 收货地址表

        $this->archives_db           = Db::name('archives');            // 产品表
        $this->product_attr_db       = Db::name('product_attr');        // 产品属性表
        $this->product_attribute_db  = Db::name('product_attribute');   // 产品属性标题表

        $this->region_db             = Db::name('region');                 // 三级联动地址总表
        $this->shipping_template_db  = Db::name('shop_shipping_template'); // 运费模板表

        $this->shop_model = model('Shop');  // 商城模型
        $this->shop_common = new ShopCommonLogic(); // common商城业务层，前后台共用

        // 订单中心是否开启
        $redirect_url = '';
        $shop_open = getUsersConfigData('shop.shop_open');
        $web_users_switch = tpCache('web.web_users_switch');
        if (empty($shop_open)) { 
            // 订单功能关闭，立马跳到会员中心
            $redirect_url = url('user/Users/index');
            $msg = '商城中心尚未开启！';
        } else if (empty($web_users_switch)) { 
            // 前台会员中心已关闭，跳到首页
            $redirect_url = ROOT_DIR.'/';
            $msg = '会员中心尚未开启！';
        }
        if (!empty($redirect_url)) {
            Db::name('users_menu')->where([
                    'mca'   => 'user/Shop/shop_centre',
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

    // 购物车列表
    public function shop_cart_list()
    {
        // 数据由标签调取生成
        return $this->fetch('users/shop_cart_list');
    }

    // 订单管理列表，订单中心
    public function shop_centre()
    {
        $result = [];
        // 应用搜索条件
        $keywords      = input('param.keywords/s');
        // 订单状态搜索
        $select_status = input('param.select_status');
        // 查询订单是否为空
        $result['data'] = $this->shop_model->GetOrderIsEmpty($this->users_id,$keywords,$select_status);
        // 是否移动端，1表示手机端，0表示PC端
        $result['IsMobile'] = isMobile() ? 1 : 0;
        // 菜单名称
        $result['title'] = Db::name('users_menu')->where([
                'mca'   => 'user/Shop/shop_centre',
                'lang'  => $this->home_lang,
            ])->getField('title');
        // 加载数据
        $eyou = [
            'field' => $result,
        ];
        $this->assign('eyou',$eyou);
        return $this->fetch('users/shop_centre');
    }

    // 订单数据详情
    public function shop_order_details()
    {
        if (IS_GET) {
            // 数据由标签调取生成
            return $this->fetch('users/shop_order_details');
        }else{
            $this->error(show_foreign_lang('非法访问'));
        }
    }

    // 订单提交
    public function shop_under_order($error = true)
    {
        // 存在游客免登录查询则执行
        if (is_dir('./weapp/FreeLogin/') && !empty($this->users_id)) {
            session('eyou_referurl', $this->request->url(true));
            auto_bind_wechatlogin($this->users, $referurl);
            if (!empty($referurl)) {
                header('Location: '. $referurl);
                exit;
            }
        }

        if (empty($error)) {
            if ($this->usersTplVersion == 'v3') {
                $this->redirect(url('user/Shop/shop_cart_list'));
            } else {
                $this->error(foreign_lang('users1'));
            }
        }
        // 获取当前页面URL，存入session，若操作添加地址后返回当前页面
        session($this->users_id.'_EyouShopOrderUrl', $this->request->url(true));
        // 数据由标签调取生成
        return $this->fetch('users/shop_under_order');
    }

    // 购物车库存检测
    public function cart_stock_detection()
    {
        if (IS_AJAX_POST) {
            // 购物车查询条件
            $CartWhere = [
                'a.users_id' => $this->users_id,
                'a.lang'     => $this->home_lang,
                'a.selected' => 1,
            ];
            $list = $this->shop_cart_db->field('a.product_num, b.stock_count, c.spec_value_id, c.spec_stock,b.prom_type')
                ->alias('a')
                ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                ->where($CartWhere)
                ->order('a.add_time desc')
                ->select();
            if (empty($list)) $this->error(show_foreign_lang('请选择要购买的商品！'));
            
            $ExceedingStock = 0;
            // 处理商品库存检测
            foreach ($list as $key => $value) {
                // 购物车商品存在规格并且库存不为空，则覆盖商品原来的库存
                if (!empty($value['spec_value_id'])) {
                    $value['stock_count'] = $value['spec_stock'];
                }
                // 检测是否有超出库存的产品
                if (empty($value['product_num']) || $value['product_num'] > $value['stock_count']) {
                    $ExceedingStock = 1;
                    break;
                }
                // 若库存为空则清除这条数据
                if (empty($value['stock_count'])) {
                    unset($list[$key]);
                    continue;
                }
            }

            $this->success('检测完成', null, $ExceedingStock);
        }
    }

    // 收货地址管理列表
    public function shop_address_list()
    {
        // 将当前URL存入 session ，使用微信获取收货地址时需要验签加密
        session($this->users_id.'_EyouShopAddAddress', $this->request->url(true));
        // 获取当前页面URL，存入session，若操作添加地址后返回当前页面
        session($this->users_id.'_EyouShopOrderUrl', $this->request->url(true));
        // 指定返回上一级URL
        $gourl = input('param.gourl/s');
        $gourl = urldecode($gourl);
        $this->assign('gourl', $gourl);
        $this->assign('add_addr_url', url("user/Shop/shop_get_wechat_addr"));
        // 收货地址列入的来源入口
        $type = input('param.type/s');
        $this->assign('type', $type);
        // 数据由标签调取生成
        return $this->fetch('users/shop_address_list');
    }

    // 取消订单
    public function shop_order_cancel()
    {
        if (IS_AJAX_POST) {
            $order_id = input('param.order_id/d');
            if (!empty($order_id)) {
                // 更新条件
                $Where = [
                    'order_id' => $order_id,
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
                // 更新数据
                $Data  = [
                    'order_status' => '-1',
                    'update_time'  => getTime(),
                ];
                // 更新订单主表
                $return = $this->shop_order_db->where($Where)->update($Data);
                if (!empty($return)) {
                    // 订单取消，还原单内产品数量 
                    model('ProductSpecValue')->SaveProducSpecValueStock($order_id, $this->users_id);
                    // 添加订单操作记录
                    AddOrderAction($order_id,$this->users_id,'0','0','0','0','订单取消！','会员关闭订单！');
                    $this->success('订单已取消！');
                }else{
                    $this->error(foreign_lang('system11', $this->home_lang));
                }
            }
        }
    }

    // 立即购买
    public function shop_buy_now()
    {
        if (IS_AJAX_POST) {
            $param = input('param.');
            $param['aid'] = intval($param['aid']);
            $param['num'] = intval($param['num']);

            // 商品是否已售罄
            $this->IsSoldOut($param);

            // 数量不可为空
            if (empty($param['num']) || 0 > $param['num']) {
                $this->error(show_foreign_lang('请选择数量！'));
            }
            // 查询条件
            $archives_where = [
                'arcrank' => array('egt','0'), //带审核稿件不查询
                'aid'     => $param['aid'],
                'lang'    => $this->home_lang,
            ];
            $count = $this->archives_db->where($archives_where)->count();
            // 跳转下单页
            if (!empty($count)) {
                // 对ID和订单号加密，拼装url路径
                $querydata = [
                    'aid'         => $param['aid'],
                    'product_num' => $param['num'],
                ];

                /*若开启多规格则执行*/
                if (!empty($this->usersConfig['shop_open_spec']) && !empty($param['spec_value_id'])) {
                    $querydata['spec_value_id'] = $param['spec_value_id'];
                }
                /* END */

                /*特定场景专用*/
                $opencodetype = config('global.opencodetype');
                if (1 == $opencodetype) {
                    if (!empty($param['mini_id']) && intval($param['mini_id']) > 0) {
                        $querydata['mini_id'] = intval($param['mini_id']);
                    }
                }
                /*end*/

                // 先 json_encode 后 md5 加密信息
                $querystr = md5(json_encode($querydata));
                // 存入 cookie
                cookie($querystr, $querydata);
                // 跳转链接
                $url = urldecode(url('user/Shop/shop_under_order', ['querystr'=>$querystr]));
                $this->success(show_foreign_lang('立即购买！'),$url);
            }else{
                $this->error(show_foreign_lang('该商品不存在或已下架！'));
            }
        }else {
            $this->error(show_foreign_lang('非法访问'));
        }
    }

    // 添加购物车数据
    public function shop_add_cart()
    {
        if (IS_AJAX_POST) {
            $param = input('param.');
            $param['aid'] = intval($param['aid']);
            // 商品是否已售罄
            $this->IsSoldOut($param);
            // 数量不可为空
            if (empty($param['num']) || 0 > $param['num']) $this->error(show_foreign_lang('请选择数量！'));

            // 查询条件
            $archives_where = [
                'arcrank' => ['egt', 0],
                'aid'     => $param['aid'],
                'lang'    => $this->home_lang,
            ];
            $count = $this->archives_db->where($archives_where)->count();
            // 加入购物车处理
            if (!empty($count)) {
                // 查询条件
                $cart_where = [
                    'users_id'   => $this->users_id,
                    'product_id' => $param['aid'],
                    'lang'       => $this->home_lang,
                ];
                // 若开启多规格则执行
                if (!empty($this->usersConfig['shop_open_spec']) && !empty($param['spec_value_id'])) {
                    $cart_where['spec_value_id'] = $param['spec_value_id'];
                }
                $cartInfo = $this->shop_cart_db->field('product_num')->where($cart_where)->find();
                if (!empty($cartInfo)) {
                    // 购物车内已有相同产品，进行数量更新。
                    $data['product_num'] = $param['num'] + intval($cartInfo['product_num']); //与购物车数量进行叠加
                    $data['update_time'] = getTime();
                    $cart_id = $this->shop_cart_db->where($cart_where)->update($data);
                } else {
                    // 购物车内还未有相同产品，进行添加。
                    $data['users_id']    = $this->users_id;
                    $data['product_id']  = $param['aid'];
                    $data['product_num'] = $param['num'];
                    $data['spec_value_id'] = $param['spec_value_id'];
                    $data['add_time']    = getTime();
                    $cart_id = $this->shop_cart_db->add($data);
                }
                if (!empty($cart_id)) {
                    $this->success(show_foreign_lang('加入购物车成功！'), url('user/Shop/shop_cart_list'), ['code'=>1]);
                } else {
                    $this->error(show_foreign_lang('加入购物车失败！'), null, ['code'=>-1]);
                }
            } else {
                $this->error(show_foreign_lang('该商品不存在或已下架！'), null, ['code'=>-1]);
            }
        } else {
            $this->error(show_foreign_lang('非法访问'), null, ['code'=>-1]);
        }
    }

    // 统一修改购物车数量
    // symbol 加或减数量或直接修改数量
    public function cart_unified_algorithm(){
        if (IS_AJAX_POST) {
            $aid    = input('post.aid/d');
            $symbol = input('post.symbol');
            $num    = input('post.num/d');
            $spec_value_id = input('post.spec_value_id');

            // 查询条件
            $archives_where = [
                'arcrank' => array('egt','0'),
                'aid'     => $aid,
                'lang'    => $this->home_lang,
            ];
            $archives_count = $this->archives_db->where($archives_where)->count();
            if (!empty($archives_count)) {
                // 查询条件
                $cart_where = [
                    'users_id'    => $this->users_id,
                    'product_id'  => $aid,
                    'lang'        => $this->home_lang,
                    'spec_value_id' => $spec_value_id,
                ];
                // 判断追加查询条件，当减数量时，商品数量最少为1
                if ('-' == $symbol) $cart_where['product_num'] = array('gt','1');
                $product_num = $this->shop_cart_db->where($cart_where)->getField('product_num');
                // 处理购物车产品数量
                if (!empty($product_num)) {
                    // 更新数组
                    if ('+' == $symbol) {
                        $data['product_num'] = $product_num + 1;
                    }else if ('-' == $symbol) {
                        $data['product_num'] = $product_num - 1;
                    }else if ('change' == $symbol) {
                        $data['product_num'] = $num;
                    }
                    $data['update_time'] = getTime();
                    // 更新数据
                    $cart_id = $this->shop_cart_db->where($cart_where)->update($data);

                    // 计算金额数量
                    $CaerWhere = [
                        'a.users_id' => $this->users_id,
                        'a.lang'     => $this->home_lang,
                        'a.selected' => 1,
                    ];
                    $CartData = $this->shop_cart_db
                        ->field('a.product_num, b.users_price, c.spec_price')
                        ->alias('a') 
                        ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                        ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                        ->where($CaerWhere)
                        ->select();

                    $level_discount = $this->users['level_discount'];
                    $discount_price = $level_discount / 100;
                    $spec_price = $users_price = $num_new = 0;
                    foreach ($CartData as $key => $value) {
                        if (!empty($value['spec_price'])) {
                            if (!empty($level_discount)) $value['spec_price'] = $value['spec_price'] * $discount_price;
                            $spec_price += $value['product_num'] * $value['spec_price'];
                        } else {
                            if (!empty($level_discount)) $value['users_price'] = $value['users_price'] * $discount_price;
                            $users_price += $value['product_num'] * $value['users_price'];
                        }
                        $num_new += $value['product_num'];
                    }
                    $CartData['num']   = $num_new;
                    $CartData['price'] = unifyPriceHandle($spec_price + $users_price);
                    if (empty($CartData['num']) && empty($CartData['price'])) {
                        $CartData['num']   = '0';
                        $CartData['price'] = '0';
                    }
                    $CartAmountVal = $this->shop_cart_db->where([ 'users_id' => $this->users_id,'lang' => $this->home_lang])->sum('product_num');
                    if (!empty($cart_id)) {
                        $this->success(foreign_lang('system12', $this->home_lang), null, ['NumberVal'=>$CartData['num'], 'AmountVal'=>$CartData['price'], 'CartAmountVal'=>$CartAmountVal]);
                    }
                } else {
                    $this->error(show_foreign_lang('商品数量最少为1'), null, ['error'=>'0']);
                }
            } else {
                $this->error(show_foreign_lang('该商品不存在或已下架！'));
            }
        }
    }

    // 删除购物车内的产品
    public function cart_del()
    {
        if (IS_AJAX_POST) {
            $cart_id = input('post.cart_id/s');
            if (!empty($cart_id)) {
                // 删除条件
                $cart_where = [
                    'cart_id'  => ['IN', explode(',', $cart_id)],
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
                // 删除数据
                $return = $this->shop_cart_db->where($cart_where)->delete();
            }
            if (!empty($return)) {
                // 计算购物车选中商品的总数总额
                $CaerWhere = [
                    'a.users_id' => $this->users_id,
                    'a.lang'     => $this->home_lang,
                    'a.selected' => 1
                ];
                $CartData = $this->shop_cart_db
                    ->field('a.product_num, b.users_price, c.spec_price')
                    ->alias('a') 
                    ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                    ->where($CaerWhere)
                    ->select();

                $level_discount = $this->users['level_discount'];
                $discount_price = $level_discount / 100;
                $spec_price = $users_price = $num_new = 0;
                foreach ($CartData as $key => $value) {
                    if (!empty($value['spec_price'])) {
                        if (!empty($level_discount)) $value['spec_price'] = $value['spec_price'] * $discount_price;
                        $spec_price += $value['product_num'] * $value['spec_price'];
                    } else {
                        if (!empty($level_discount)) $value['users_price'] = $value['users_price'] * $discount_price;
                        $users_price += $value['product_num'] * $value['users_price'];
                    }
                    $num_new += $value['product_num'];
                }
                $CartData['num']   = empty($num_new) ? 0 : $num_new;
                $CartData['price'] = unifyPriceHandle($spec_price + $users_price);
                $CartData['price'] = empty($CartData['price']) ? '0.00' : $CartData['price'];

                // 购物车是否还存在商品
                $CartCount = $this->shop_cart_db->where(['users_id' => $this->users_id, 'lang' => $this->home_lang])->count();
                // 购物车所有商品数量
                $CartAmountVal = $this->shop_cart_db->where(['users_id' => $this->users_id,'lang' => $this->home_lang])->sum('product_num');

                // 返回数据
                $data = [
                    'NumberVal' => $CartData['num'],
                    'AmountVal' => $CartData['price'],
                    'CartCount' => $CartCount,
                    'CartAmountVal' => $CartAmountVal,
                ];
                $this->success(foreign_lang('system12', $this->home_lang), null, $data);
            } else {
                $this->error(show_foreign_lang('操作失败'));
            }
        }
    }

    // 删除全部选中的购物车商品
    public function select_cart_del()
    {
        if (IS_AJAX_POST) {
            // 删除条件
            $where = [
                'selected' => 1,
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            // 删除数据
            $result = $this->shop_cart_db->where($where)->delete(true);
            if (!empty($result)) {
                $this->success(show_foreign_lang('操作成功'));
            } else {
                $this->error(show_foreign_lang('操作失败'));
            }
        }
    }

    // 移入收藏
    public function move_to_collection()
    {
        if (IS_AJAX_POST) {
            $cart_id = input('post.cart_id');
            if (!empty($cart_id)) {
                // 删除条件
                $cart_where = [
                    'cart_id'  => $cart_id,
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];

                // 加入收藏
                $aid = $this->shop_cart_db->where($cart_where)->value('product_id');
                $row = Db::name('users_collection')->where(['users_id'  => $this->users_id, 'aid' => $aid])->find();
                if (empty($row)) {
                    $archivesInfo = Db::name('archives')->field('aid,title,litpic,channel,typeid')->find($aid);
                    if (!empty($archivesInfo)) {
                        Db::name('users_collection')->add([
                            'users_id'  => $this->users_id,
                            'title' => $archivesInfo['title'],
                            'aid' => $aid,
                            'litpic' => $archivesInfo['litpic'],
                            'channel' => $archivesInfo['channel'],
                            'typeid' => $archivesInfo['typeid'],
                            'lang'  => $this->home_lang,
                            'add_time'  => getTime(),
                            'update_time' => getTime(),
                        ]);
                    }
                }

                // 删除数据
                $return = $this->shop_cart_db->where($cart_where)->delete();
            }
            if (!empty($return)) {
                // 计算购物车选中商品的总数总额
                $CaerWhere = [
                    'a.users_id' => $this->users_id,
                    'a.lang'     => $this->home_lang,
                    'a.selected' => 1
                ];
                $CartData = $this->shop_cart_db
                    ->field('a.product_num, b.users_price, c.spec_price')
                    ->alias('a') 
                    ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                    ->where($CaerWhere)
                    ->select();

                $level_discount = $this->users['level_discount'];
                $discount_price = $level_discount / 100;
                $spec_price = $users_price = $num_new = 0;
                foreach ($CartData as $key => $value) {
                    if (!empty($value['spec_price'])) {
                        if (!empty($level_discount)) $value['spec_price'] = $value['spec_price'] * $discount_price;
                        $spec_price += $value['product_num'] * $value['spec_price'];
                    } else {
                        if (!empty($level_discount)) $value['users_price'] = $value['users_price'] * $discount_price;
                        $users_price += $value['product_num'] * $value['users_price'];
                    }
                    $num_new += $value['product_num'];
                }
                $CartData['num']   = empty($num_new) ? 0 : $num_new;
                $CartData['price'] = unifyPriceHandle($spec_price + $users_price);
                $CartData['price'] = empty($CartData['price']) ? '0.00' : $CartData['price'];

                // 购物车是否还存在商品
                $CartCount = $this->shop_cart_db->where(['users_id' => $this->users_id, 'lang' => $this->home_lang])->count();

                // 返回数据
                $data = [
                    'NumberVal' => $CartData['num'],
                    'AmountVal' => $CartData['price'],
                    'CartCount' => $CartCount,
                ];
                $this->success(foreign_lang('system12', $this->home_lang), null, $data);
            } else {
                $this->error(foreign_lang('system11', $this->home_lang));
            }
        }
    }

    // 选中产品
    public function cart_checked()
    {
        if (IS_AJAX_POST) {
            $cart_id = input('post.cart_id/s', '');
            $selected = input('post.selected/d', 0);
            // 更新数组
            if (!empty($selected)) {
                $selected = 0;
            } else {
                $selected = 1;
            }
            $update['selected'] = $selected;
            $update['update_time'] = getTime();
            // 更新条件
            if ('*' == $cart_id) {
                $where = [
                    'product_num' => ['>', 0],
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
            } else {
                $where = [
                    'cart_id'  => intval($cart_id),
                    'product_num' => ['>', 0],
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
            }
            // 更新数据
            $return = $this->shop_cart_db->where($where)->update($update);
            if (!empty($return)) {
                $this->success(foreign_lang('system12', $this->home_lang));
            } else {
                $this->error(foreign_lang('system11', $this->home_lang));
            }
        }
    }

    public function shop_wechat_pay_select()
    {
        $ReturnOrderData = session($this->users_id.'_ReturnOrderData');
        if (empty($ReturnOrderData)) {
            $url = session($this->users_id.'_EyouShopOrderUrl');
            $this->error(show_foreign_lang('订单支付异常，请刷新重新下单~'),$url);
        }
        $eyou = [
            'field' => $ReturnOrderData,
        ];
        $this->assign('eyou',$eyou);
        return $this->fetch('users/shop_wechat_pay_select');
    }

    /**
     * 快速下单支付流程 - 添加商品信息及计算价格等
     * @return [type] [description]
     */
    public function fastSubmitOrder()
    {
        $aid = input('post.aid_v507428/d');
        $pay_code = input('post.pay_code_v507428/s'); // 支付方式

        if (IS_POST && !empty($aid) && !empty($pay_code)) {

            $OrderData = [];

            $mini_id = input('post.mini_id_v507428/d');
            !empty($mini_id) && $OrderData['mini_id'] = $mini_id;

            // 规格值
            $spec_value_id = input('post.spec_value_id_v507428/s');
            if (!empty($spec_value_id)) {
                $spec_value_id = preg_replace('/[^\d\_]/i', '', $spec_value_id);
                $spec_value_id = trim($spec_value_id, '_');
                $OrderData['spec_value_id'] = "_{$spec_value_id}_";
            }

            $ArchivesWhere = [
                'a.aid'  => $aid,
            ];
            if (!empty($spec_value_id)) $ArchivesWhere['b.spec_value_id'] = $spec_value_id;
            $field = 'a.aid, a.aid as product_id, a.title, a.litpic, a.users_price, a.stock_count, a.prom_type, a.attrlist_id, b.spec_price, b.spec_stock, b.spec_value_id, b.value_id';
            $list = Db::name('archives')->field($field)
                ->alias('a')
                ->join('__PRODUCT_SPEC_VALUE__ b', 'a.aid = b.aid', 'LEFT')
                ->where($ArchivesWhere)
                ->limit('0, 1')
                ->select();

            // 没有相应的产品
            if (empty($list[0])) $this->error(show_foreign_lang('订单生成失败，没有相应的商品！'));
            $list[0]['product_num']      = 1;
            $list[0]['under_order_type'] = 2; // 快速下单支付

            // 生成订单之前的产品数据整理
            $retData = $this->shop_model->handlerOrderData('fast', $OrderData, $list, $this->users);
            if (empty($retData['code'])) {
                $this->error($retData['msg']);
            }
            $OrderData = array_merge($OrderData, $retData['data']['OrderData']);
            $list = $retData['data']['list'];

            $OrderId = Db::name('shop_order')->insertGetId($OrderData);
            $OrderData['order_id'] = $OrderId;
            if (!empty($OrderId)) {

                // 生成订单之后的订单明细整理
                $retData = $this->shop_model->handlerDetailsData('fast', $OrderData, $list, $this->users);
                if (empty($retData['code'])) {
                    $this->error($retData['msg']);
                }
                $cart_ids = $retData['data']['cart_ids'];
                $OrderDetailsData = $retData['data']['OrderDetailsData'];
                $UpSpecValue = $retData['data']['UpSpecValue'];

                $DetailsId = Db::name('shop_order_details')->insertAll($OrderDetailsData);

                if (!empty($DetailsId)) {
                    // 清理购物车中已下单的ID
                    if (!empty($cart_ids)) Db::name('shop_cart')->where('cart_id', 'IN', $cart_ids)->delete();

                    // 产品库存、销量处理
                    $this->shop_model->ProductStockProcessing($UpSpecValue);

                    // 添加订单操作记录
                    AddOrderAction($OrderId, $this->users_id);

                    if (0 == $OrderData['payment_method']) {
                        // 选择在线付款并且在手机微信端、小程序中则返回订单ID，订单号，订单交易类型
                        if (isMobile() && isWeixin()) {
                            // $where = [
                            //     'pay_id' => 1,
                            //     'pay_mark' => 'wechat'
                            // ];
                            // $PayInfo = Db::name('pay_api_config')->where($where)->getField('pay_info');
                            // if (!empty($PayInfo)) $PayInfo = unserialize($PayInfo);

                            // if (!empty($this->users['open_id']) && !empty($PayInfo) && 0 == $PayInfo['is_open_wechat']) {
                            //     $ReturnOrderData = [
                            //         'unified_id'       => $OrderId,
                            //         'unified_number'   => $OrderData['order_code'],
                            //         'transaction_type' => 2, // 订单支付购买
                            //         'order_total_amount' => $TotalAmount,
                            //         'order_source'     => 1, // 提交订单页
                            //         'is_gourl'         => 1,
                            //     ];
                            //     if ($this->users['users_money'] <= '0.00') {
                            //         // 余额为0
                            //         $ReturnOrderData['is_gourl'] = 0;
                            //         $this->success('订单已生成！', null, $ReturnOrderData);
                            //     } else {
                            //         // 余额不为0
                            //         $url = url('user/Shop/shop_wechat_pay_select');
                            //         session($this->users_id.'_ReturnOrderData', $ReturnOrderData);
                            //         $this->success('订单已生成！', $url, $ReturnOrderData);
                            //     }
                            // } else {
                            //     // 如果会员没有openid则跳转到支付页面进行支付
                            //     // 在线付款时，跳转至付款页
                            //     // 对ID和订单号加密，拼装url路径
                            //     $Paydata = [
                            //         'order_id'   => $OrderId,
                            //         'order_code' => $OrderData['order_code'],
                            //     ];

                            //     // 先 json_encode 后 md5 加密信息
                            //     $Paystr = md5(json_encode($Paydata));

                            //     // 存入 cookie
                            //     cookie($Paystr, $Paydata);

                            //     // 跳转链接
                            //     $PaymentUrl = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$Paystr]));

                            //     $this->success('订单已生成！', $PaymentUrl, ['is_gourl' => 1]);
                            // }
                        } else {
                            if ('alipay' == $pay_code) {
                                // 重要参数，支付宝配置信息
                                $PayInfo = Db::name('pay_api_config')->where([
                                        'pay_id' => 2,
                                        'pay_mark' => 'alipay'
                                    ])->getField('pay_info');
                                if (empty($PayInfo)) $this->error('请先配置支付宝！');
                                $alipay = unserialize($PayInfo);
                                $vars = [
                                    'unified_number'   => $OrderData['order_code'],
                                    'unified_amount'   => $OrderData['order_amount'],
                                    'transaction_type' => 2,
                                ];
                                $PayApiLogic = new \app\user\logic\PayApiLogic;
                                $retData = $PayApiLogic->UseAliPayPay(['transaction_type'=>2], $vars, $alipay, true);
                                if (!empty($retData['alipay_url'])) {
                                    $PaymentUrl = $retData['alipay_url'];
                                    $this->redirect($PaymentUrl);
                                } else {
                                    $this->error('调起支付宝页面失败！');
                                }
                            }
                            else if ('weipay' == $pay_code) {
                                $vars = [
                                    'unified_number' => $OrderData['order_code'],
                                    'transaction_type' => 2
                                ];
                                $data = [
                                    'url_qrcode'        => url('user/PayApi/pay_wechat_png', $vars),
                                    'pay_id'            => 1,
                                    'pay_mark'          => 'wechat',
                                    'unified_id'        => $OrderId,
                                    'unified_number'    => $OrderData['order_code'],
                                    'transaction_type'  => 2,
                                ];
                                $this->success('正在支付中', null, $data);
                            }
                        }
                    }
                }
            }
        }
        $this->error(foreign_lang('system11', $this->home_lang));
    }

    // 订单提交处理逻辑，添加商品信息及计算价格等
    public function shop_payment_page()
    {
        if (IS_POST) {
            // 提交的订单信息判断
            $post = input('post.');
            if (empty($post)) $this->error(show_foreign_lang('订单生成失败，商品数据有误'));

            // 物流类型
            $post['logistics_type'] = !empty($post['logistics_type']) ? intval($post['logistics_type']) : 0;
            if (2 === intval($post['logistics_type'])) {
                if (empty($post['store_id'])) $this->error(show_foreign_lang('请选择自提门店'));
                if (empty($post['buyer'])) $this->error(show_foreign_lang('请输入用户姓名'));
                if (empty($post['phone'])) $this->error(show_foreign_lang('请输入预留手机号'));
                if (!check_mobile($post['phone'])) $this->error(show_foreign_lang('预留手机号格式不正确'));
            }

            $OrderData = [];
            // 产品ID是否存在
            $Md5Value = !empty($post['Md5Value']) ? $post['Md5Value'] : null;
            if (!empty($Md5Value)) {
                $querystr = cookie($Md5Value);
                $aid = !empty($querystr['aid']) ? intval($querystr['aid']) : 0;
                $num = !empty($querystr['product_num']) ? intval($querystr['product_num']) : 0;
                $mini_id = !empty($querystr['mini_id']) ? intval($querystr['mini_id']) : 0;
                !empty($mini_id) && $OrderData['mini_id'] = $mini_id;
                $spec_value_id = !empty($querystr['spec_value_id']) ? $querystr['spec_value_id'] : '';
                !empty($spec_value_id) && $OrderData['spec_value_id'] = $spec_value_id;
                $type = !empty($post['type']) ? intval($post['type']) : 0;
                $OrderData['order_md5'] = md5($aid.$spec_value_id);

                // 商品数量判断
                if ($num <= 0) $this->error(show_foreign_lang('订单生成失败，商品数量有误！'));
                // 订单来源判断
                if ($type != 1) $this->error(show_foreign_lang('订单生成失败，提交来源有误！'));
                
                // 立即购买查询条件
                $where = [
                    'a.aid'  => $aid,
                ];
                if (!empty($spec_value_id)) $where['b.spec_value_id'] = $spec_value_id;
                $field = 'a.aid, a.aid as product_id, a.title, a.litpic, a.users_price, a.users_discount_type, a.stock_count, a.prom_type, a.attrlist_id, b.spec_price, b.spec_stock, b.spec_value_id, b.value_id, c.spec_is_select, a.merchant_id, a.free_shipping';
                $list = $this->archives_db->field($field)
                    ->alias('a')
                    ->join('__PRODUCT_SPEC_VALUE__ b', 'a.aid = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_DATA__ c', 'a.aid = c.aid and b.spec_value_id = c.spec_value_id', 'LEFT')
                    ->where($where)
                    ->limit('0, 1')
                    ->select();
                $list[0]['product_num'] = $num;
                $list[0]['under_order_type'] = $type;
                if (empty($list[0]['spec_is_select'])) {
                    $list[0]['spec_price']    = '';
                    $list[0]['spec_stock']    = '';
                    $list[0]['spec_value_id'] = '';
                }
            } else {
                // 购物车查询条件
                $where = [
                    'a.users_id' => $this->users_id,
                    'a.selected' => 1,
                ];
                $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.users_discount_type, b.stock_count, b.prom_type, b.attrlist_id, c.spec_price, c.spec_stock, c.value_id, b.merchant_id, b.free_shipping';
                $list = $this->shop_cart_db->field($field)
                    ->alias('a')
                    ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
                    ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
                    ->where($where)
                    ->select();
            }
            // 没有相应的产品
            if (empty($list)) $this->error(show_foreign_lang('订单生成失败，没有相应的商品！'));

            // 生成订单之前的产品数据整理
            $retData = $this->shop_model->handlerOrderData('normal', $OrderData, $list, $this->users, $post);
            if (empty($retData['code'])) $this->error($retData['msg']);

            // 判断下单商品是否有多个商家的商品，若多个商家一起下单则执行
            $merchantID = count(array_filter(array_unique(get_arr_column($list, 'merchant_id'))));
            if (intval($merchantID) >= 1) {
                $multiMerchantLogic = new \app\user\logic\MultiMerchantLogic;
                $multiMerchantLogic->multiMerchantOrderHandle($retData['data']['list'], $post);
            }

            // 合并订单数据
            $OrderData = array_merge($OrderData, $retData['data']['OrderData']);
            $list = $retData['data']['list'];

            // 订单物流类型(0:虚拟订单; 1:快递发货; 2:到店核销;)
            $OrderData['logistics_type'] = !empty($post['logistics_type']) ? intval($post['logistics_type']) : 1;
            $post['verifyShow'] = $post['deliveryShow'] = false;
            if (1 === intval($OrderData['logistics_type'])) {
                $post['deliveryShow'] = true;
            } else if (2 === intval($OrderData['logistics_type'])) {
                $post['verifyShow'] = true;
            }

            $AddrData = [];
            // 非虚拟订单则查询运费信息
            if (empty($OrderData['prom_type']) && 1 === intval($OrderData['logistics_type'])) {
                // 没有选择收货地址
                if (empty($post['addr_id'])) {
                    // 在微信端并且不在小程序中
                    if (isWeixin() && !isWeixinApplets()) {
                        // 跳转至收货地址添加选择页
                        if ('v3' == $this->usersTplVersion) {
                            $get_addr_url = url('user/Shop/shop_add_address', ['type'=>'order']);
                        } else {
                            $get_addr_url = url('user/Shop/shop_get_wechat_addr');
                        }
                        $is_gourl['is_gourl'] = 1;
                        $this->success('101:选择添加地址方式', $get_addr_url, $is_gourl);
                    } else {
                        $paramNew['add_addr'] = 1;
                        $paramNew['is_mobile'] = $this->is_mobile;
                        if ('v3' == $this->usersTplVersion) {
                            $paramNew['url'] = url('user/Shop/shop_add_address', ['type'=>'order']);
                        }
                        $this->error('101:订单生成失败，请添加收货地址', null, $paramNew);
                    }
                }

                // 查询收货地址
                $AddrWhere = [
                    'addr_id'  => $post['addr_id'],
                    'users_id' => $this->users_id,
                ];
                $AddressData = $this->shop_address_db->where($AddrWhere)->find();
                if (empty($AddressData)) {
                    if (isWeixin() && !isWeixinApplets()) {
                        // 跳转至收货地址添加选择页
                        if ('v3' == $this->usersTplVersion) {
                            $get_addr_url = url('user/Shop/shop_add_address', ['type'=>'order']);
                        } else {
                            $get_addr_url = url('user/Shop/shop_get_wechat_addr');
                        }
                        $is_gourl['is_gourl'] = 1;
                        $this->success('102:选择添加地址方式', $get_addr_url, $is_gourl);
                    } else {
                        $paramNew['add_addr'] = 1;
                        $paramNew['is_mobile'] = $this->is_mobile;
                        if ('v3' == $this->usersTplVersion) {
                            $paramNew['url'] = url('user/Shop/shop_add_address', ['type'=>'order']);
                        }
                        $this->error('102:订单生成失败，请添加收货地址', null, $paramNew);
                    }
                }

                $shop_open_shipping = getUsersConfigData('shop.shop_open_shipping');
                $template_money = 0;
                if (!empty($shop_open_shipping)) {
                    // 通过省份获取运费模板中的运费价格
                    $template_money = $this->shipping_template_db->where('province_id', $AddressData['province'])->getField('template_money');
                    if (0 >= $template_money) {
                        // 省份运费价格为0时，使用统一的运费价格，固定ID为100000
                        $template_money = $this->shipping_template_db->where('province_id', '100000')->getField('template_money');
                    }
                    // 合计金额加上运费价格
                    $OrderData['order_total_amount'] += $template_money;
                    $OrderData['order_amount'] += $template_money;
                }

                // 拼装数组
                $AddrData = [
                    'consignee'    => $AddressData['consignee'],
                    'country'      => $AddressData['country'],
                    'province'     => $AddressData['province'],
                    'city'         => $AddressData['city'],
                    'district'     => $AddressData['district'],
                    'address'      => $AddressData['address'],
                    'mobile'       => $AddressData['mobile'],
                    'shipping_fee' => $template_money,
                ];
            }
            // 存在收货地址则追加合并到主表数组
            if (!empty($AddrData)) $OrderData = array_merge($OrderData, $AddrData);

            if (0 < $OrderData['order_amount']) {
                if (empty($post['payment_type'])) $this->error('网站支付配置未完善，购买服务暂停使用');
                // 数据验证
                $rule = ['payment_type' => 'require|token'];
                $message = ['payment_type.require' => show_foreign_lang('不可为空！')];
                $validate = new \think\Validate($rule, $message);
                if (!$validate->check($post)) $this->error(show_foreign_lang('不可连续提交订单！'));
            }

            // 订单提交处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
            $OrderData = model('ShopPublicHandle')->orderSubmitPublicHandle($OrderData, $this->usersConfig, $this->users_id, $post, $list);

            // // 如果后台【商城中心】-【商城配置】-【订单设置】-收货后可维权时间设置为0，则表示订单不允许申请维权
            // if (empty($this->usersConfig['order_right_protect_time'])) $OrderData['allow_service'] = 1;

            // // 获取消费获得积分数据
            // $OrderData['obtain_scores'] = getConsumObtainScores($OrderData, $this->usersConfig, true);
            // $OrderData['is_obtain_scores'] = !empty($OrderData['obtain_scores']) ? 0 : 1;

            // 添加订单及后续处理
            $OrderId = $this->shop_order_db->insertGetId($OrderData);
            if (!empty($OrderId)) {
                $OrderId = intval($OrderId);
                $OrderData['order_id'] = $OrderId;

                // 订单创建后续处理 -- 其他逻辑公共调用方法，部分逻辑改动不适合直接修改原文件时请在此方法做处理和兼容
                $OrderData = model('ShopPublicHandle')->orderCreatePublicHandle($OrderData, $this->usersConfig, $this->users_id, $post, $list);

                // 生成订单之后的订单明细整理
                $retData = $this->shop_model->handlerDetailsData('normal', $OrderData, $list, $this->users);
                if (empty($retData['code'])) $this->error($retData['msg']);
                
                // 清理购物车中已下单的ID
                $cart_ids = $retData['data']['cart_ids'];

                // 产品库存、销量处理
                $UpSpecValue = $retData['data']['UpSpecValue'];

                // 添加订单明细表
                $OrderDetailsData = $retData['data']['OrderDetailsData'];
                $DetailsId = $this->shop_order_details_db->insertAll($OrderDetailsData);

                if (!empty($DetailsId)) {
                    // 清理购物车中已下单的ID
                    if (!empty($cart_ids)) $this->shop_cart_db->where('cart_id', 'IN', $cart_ids)->delete();

                    // 产品库存、销量处理
                    $this->shop_model->ProductStockProcessing($UpSpecValue);

                    // 添加订单操作记录
                    AddOrderAction($OrderId, $this->users_id);
                    
                    // 统计销售额
                    eyou_statistics_data(2);
                    eyou_statistics_data(3, $OrderData['order_amount']);

                    // 第一版会员中心，清除下单的Cookie数据
                    if ($this->usersTplVersion == 'v1' && !empty($Md5Value)) Cookie::delete($Md5Value);

                    // 0元下单逻辑
                    if (0 == floatval($OrderData['order_amount'])) {
                        $pay_details = [
                            'unified_id'        => $OrderData['order_id'],
                            'unified_number'    => $OrderData['order_code'],
                            'transaction_type'  => 2,
                            'payment_amount'    => $OrderData['order_amount'],
                        ];
                        $returnData = pay_success_logic($this->users_id, $OrderData['order_code'], $pay_details, '');
                        if (is_array($returnData)) {
                            if (1 == $returnData['code']) {
                                $this->success($returnData['msg'], $returnData['url'], $returnData['data']);
                            } else {
                                $this->error($returnData['msg'], null, ['url'=>url('user/Shop/shop_centre')]);
                            }
                        }
                    }

                    // 在线支付 -- 根据版本调用支付
                    if (0 == $post['payment_method']) {
                        // 手机微信端支付
                        if (isMobile() && isWeixin()) {
                            // 选择在线付款并且在手机微信端、小程序中则返回订单ID，订单号，订单交易类型
                            $where = [
                                'pay_id' => 1,
                                'pay_mark' => 'wechat'
                            ];
                            $PayInfo = Db::name('pay_api_config')->where($where)->getField('pay_info');
                            if (!empty($PayInfo)) $PayInfo = unserialize($PayInfo);

                            if ((!empty($this->users['open_id']) || 0 === intval($this->users['thirdparty'])) && !empty($PayInfo) && 0 === intval($PayInfo['is_open_wechat'])) {
                                $payment_type = $post['payment_type'];
                                if ('yezf_balance' == $payment_type) {
                                    // 余额支付
                                    if ($this->users['users_money'] < $OrderData['order_amount']) {
                                        // 余额不足，支付失败
                                        $this->error(show_foreign_lang('余额不足，支付失败！'), null, ['url' => url('user/Shop/shop_centre')]);
                                    } else {
                                        // 余额充足，进行支付
                                        $ret = Db::name('users')->where(['users_id' => $this->users_id])->update([
                                            'users_money' => Db::raw('users_money-'.$OrderData['order_amount']),
                                            'update_time' => getTime(),
                                        ]);
                                        if (false !== $ret) {
                                            $pay_details = [
                                                'unified_id'        => $OrderData['order_id'],
                                                'unified_number'    => $OrderData['order_code'],
                                                'transaction_type'  => 2,
                                                'payment_amount'    => $OrderData['order_amount'],
                                                'payment_type'      => "余额支付",
                                            ];
                                            $returnData = pay_success_logic($this->users_id, $OrderData['order_code'], $pay_details, 'balance', true, $this->users);
                                            if (is_array($returnData)) {
                                                if (1 == $returnData['code']) {
                                                    $this->success($returnData['msg'], $returnData['url'], $returnData['data']);
                                                } else {
                                                    $this->error($returnData['msg'], null, ['url'=>url('user/Shop/shop_centre')]);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // 微信内支付、小程序内支付 -- 微信端不存在支付宝支付
                                    $ReturnOrderData = [
                                        'unified_id'       => $OrderId,
                                        'unified_number'   => $OrderData['order_code'],
                                        'transaction_type' => 2, // 订单支付购买
                                        'order_source'     => 1, // 提交订单页
                                        'is_gourl'         => 0, // 1,
                                        'order_total_amount' => 0 // 好像没有用处了
                                    ];
                                    $this->success('订单已生成！', null, $ReturnOrderData);
                                    // if ($this->users['users_money'] <= '0.00' || $this->usersTplVersion == 'v3') {
                                    //     // 余额为0
                                    //     $ReturnOrderData['is_gourl'] = 0;
                                    //     $this->success('订单已生成！', null, $ReturnOrderData);
                                    // } else {
                                    //     // 余额不为0
                                    //     $url = url('user/Shop/shop_wechat_pay_select');
                                    //     session($this->users_id.'_ReturnOrderData', $ReturnOrderData);
                                    //     $this->success('订单已生成！', $url, $ReturnOrderData);
                                    // }
                                }
                            }
                            else {
                                // 执行如果会员没有openid则跳转到支付页面进行支付
                                $paydata = [
                                    'order_id'   => $OrderId,
                                    'order_code' => $OrderData['order_code'],
                                ];
                                // 先 json_encode 后 md5 加密信息
                                $paystr = md5(json_encode($paydata));
                                // 存入 cookie
                                cookie($paystr, $paydata);
                                // 跳转链接
                                $this->success('订单已生成！', urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$paystr])), ['is_gourl' => 1]);
                            }
                        }
                        // PC端支付、手机浏览器端支付
                        else {
                            if (in_array($this->usersTplVersion, ['v2', 'v3', 'v4', 'v5']) && isset($post['payment_type'])) {
                                // 余额支付、微信支付、支付宝支付、其他第三方支付
                                $payment_type = $post['payment_type'];
                                if ('yezf_balance' == $payment_type) {
                                    // 余额支付
                                    if ($this->users['users_money'] < $OrderData['order_amount']) {
                                        $url = url('user/Shop/shop_centre');
                                        $this->error(show_foreign_lang('余额不足，支付失败！'), null, ['url'=>$url]);
                                    } else {
                                        $ret = Db::name('users')->where(['users_id'=>$this->users_id])->update([
                                            'users_money' => Db::raw('users_money-'.$OrderData['order_amount']),
                                            'update_time' => getTime(),
                                        ]);
                                        if (false !== $ret) {
                                            $pay_details = [
                                                'unified_id'        => $OrderData['order_id'],
                                                'unified_number'    => $OrderData['order_code'],
                                                'transaction_type'  => 2,
                                                'payment_amount'    => $OrderData['order_amount'],
                                                'payment_type'      => "余额支付",
                                            ];
                                            $returnData = pay_success_logic($this->users_id, $OrderData['order_code'], $pay_details, 'balance', true, $this->users);
                                            if (is_array($returnData)) {
                                                if (1 == $returnData['code']) {
                                                    $this->success($returnData['msg'], $returnData['url'], $returnData['data']);
                                                } else {
                                                    $this->error($returnData['msg'], null, ['url'=>url('user/Shop/shop_centre')]);
                                                }
                                            }
                                        }
                                    }
                                } else if (in_array($payment_type, ['zxzf_wechat', 'zxzf_alipay', 'zxzf_Paypal', 'zxzf_UnionPay', 'zxzf_PersonPay'])) {
                                    // 内置第三方在线支付
                                    $payment_type_arr = explode('_', $payment_type);
                                    $pay_mark = !empty($payment_type_arr[1]) ? $payment_type_arr[1] : '';
                                    $payApiRow = Db::name('pay_api_config')->where(['pay_mark' => $pay_mark, 'lang' => $this->home_lang])->find();
                                    if (empty($payApiRow)) $this->error(show_foreign_lang('请选择正确的支付方式！'));

                                    // 返回支付所需参数
                                    $data = [
                                        'code'              => 'order_status_0',
                                        'pay_id'            => $payApiRow['pay_id'],
                                        'pay_mark'          => $pay_mark,
                                        'unified_id'        => $OrderData['order_id'],
                                        'unified_number'    => $OrderData['order_code'],
                                        'transaction_type'  => 2,
                                    ];
                                    $this->success('正在支付中', url('user/Shop/shop_centre'), $data);
                                }
                            }
                            else {
                                // 第一套模板 -- 执行跳转至订单支付页
                                $paydata = [
                                    'order_id'   => $OrderId,
                                    'order_code' => $OrderData['order_code'],
                                ];
                                // 先 json_encode 后 md5 加密信息
                                $paystr = md5(json_encode($paydata));
                                // 存入 cookie
                                cookie($paystr, $paydata);
                                // 跳转链接
                                $this->success('订单已生成！', urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$paystr])));
                            }
                        }
                    }
                    // 货到付款 -- 跳转订单列表页
                    else {
                        // 再次添加一条订单操作记录
                        AddOrderAction($OrderId, $this->users_id, 0, 1, 0, 1, '货到付款', '会员选择货到付款，款项由快递代收');
                        // 邮箱发送
                        $SmtpConfig = tpCache('smtp');
                        $ReturnData['email'] = GetEamilSendData($SmtpConfig, $this->users, $OrderData, 1, 'delivery_pay');
                        // 手机发送
                        $SmsConfig = tpCache('sms');
                        $ReturnData['mobile'] = GetMobileSendData($SmsConfig, $this->users, $OrderData, 1, 'delivery_pay');
                        // 发送站内信给后台
                        $OrderData['pay_method'] = show_foreign_lang('货到付款');
                        SendNotifyMessage($OrderData, 5, 1, 0);
                        // 返回结束
                        $ReturnData['is_gourl'] = 1;
                        // 订单支付通知
                        $params = [
                            'users_id' => $this->users_id,
                            'result_id' => $OrderId,
                        ];
                        eyou_send_notice(9, $params);
                        // 返回提示
                        $this->success('订单已生成！', urldecode(url('user/Shop/shop_centre')), $ReturnData);
                    }
                } else {
                    $this->error(show_foreign_lang('订单生成失败，商品数据有误'));
                }
            } else {
                $this->error(show_foreign_lang('订单生成失败，商品数据有误'));
            }
        }
    }

    public function to_split_goods()
    {
        if (IS_AJAX_POST) {
            $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
            if (empty($weappInfo)) {
                $this->success('没有安装核销插件，直接进入购买');
            } else if (1 !== intval($weappInfo['status'])) {
                $this->success('没有启用核销插件，直接进入购买');
            } else if (0 === intval($weappInfo['data']['openVerify'])) {
                $this->success('没有开启核销功能，直接进入购买');
            }
        }

        // 查询条件
        $where = [
            'a.selected' => 1,
            'a.users_id' => $this->users_id,
            'a.lang'     => $this->home_lang,
            'b.arcrank'  => array('egt','0'),
        ];
        $field = 'a.*, b.aid, b.title, b.litpic, b.users_price, b.logistics_type, b.users_discount_type, b.stock_count, b.attrlist_id, b.is_del, c.spec_price, c.spec_stock';
        $list = Db::name("shop_cart")
            ->field($field)
            ->alias('a')
            ->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')
            ->join('__PRODUCT_SPEC_VALUE__ c', 'a.spec_value_id = c.spec_value_id and a.product_id = c.aid', 'LEFT')
            ->where($where)
            ->order('a.selected desc, a.add_time desc')
            ->select();
        $verify = $delivery = [];
        // 商品处理
        foreach ($list as $key => $value) {
            // 图片处理
            $value['litpic'] = !empty($value['litpic']) ? handle_subdir_pic($value['litpic']) : '';
            // 购物车商品存在规格并且价格不为空，则覆盖商品原来的价格
            if (!empty($value['spec_value_id']) && !empty($value['spec_price'])) $value['users_price'] = unifyPriceHandle($value['spec_price']);
            // 商品价格处理
            $value['subtotal'] = 0;
            if (!empty($value['users_price'])) {
                // 计算小计
                $value['subtotal'] = $value['users_price'] * $value['product_num'];
                $value['subtotal'] = unifyPriceHandle($value['subtotal']);
            }
            // 虚拟商品
            if ('0' === $value['logistics_type']) {
                array_push($verify, $value);
                array_push($delivery, $value);
            }
            // 仅物流配送
            else if ('1' === $value['logistics_type']) {
                array_push($delivery, $value);
            }
            // 仅到店核销
            else if ('2' === $value['logistics_type']) {
                array_push($verify, $value);
            }
            // 物流配送 和 到店核销
            else {
                $logisticsType = isset($value['logistics_type']) ? explode(',', $value['logistics_type']) : [];
                if (in_array(1, $logisticsType) && in_array(2, $logisticsType)) {
                    array_push($verify, $value);
                    array_push($delivery, $value);
                }
            }
        }

        if (IS_AJAX_POST) {
            if (empty($verify)) {
                $this->success('没有核销商品，直接进入购买');
            } else if (empty($delivery)) {
                $this->success('没有实体商品，直接进入购买');
            } else if ($verify === $delivery) {
                $this->success('数据相等');
            } else {
                $this->error('数据不相等');
            }
        } else {
            $result = [
                0 => [
                    'amount' => 0,
                    'number' => 0,
                    'cart_ids' => '',
                    'list' => $delivery,
                    'name' => '快递发货',
                ],
                1 => [
                    'amount' => 0,
                    'number' => 0,
                    'cart_ids' => '',
                    'list' => $verify,
                    'name' => '到店核销',
                ],
            ];
            foreach ($delivery as $key => $value) {
                // 合计数量
                $result[0]['number'] += $value['product_num'];
                // 合计金额
                $result[0]['amount'] += $value['subtotal'];
                $result[0]['amount'] = unifyPriceHandle($result[0]['amount']);
                // 购物车ID
                $result[0]['cart_ids'] .= !empty($key) ? ',' . $value['cart_id'] : $value['cart_id'];
            }
            $result[0]['submitBuyGoods'] = ' data-ids="'.$result[0]['cart_ids'].'" onclick="submitBuyGoods(this);" ';

            foreach ($verify as $key => $value) {
                // 合计数量
                $result[1]['number'] += $value['product_num'];
                // 合计金额
                $result[1]['amount'] += $value['subtotal'];
                $result[1]['amount'] = unifyPriceHandle($result[1]['amount']);
                // 购物车ID
                $result[1]['cart_ids'] .= !empty($key) ? ',' . $value['cart_id']  : $value['cart_id'];
            }
            $result[1]['submitBuyGoods'] = ' data-ids="'.$result[1]['cart_ids'].'" onclick="submitBuyGoods(this);" ';

            $this->assign('result', $result);
            return $this->fetch('users/shop_to_split_goods');
        }
    }

    public function submitBuyGoods()
    {
        if (IS_AJAX_POST) {
            $ids = input('post.ids', '');
            if (!empty($ids)) {
                $where = [
                    'cart_id' => ['NOT IN', $ids],
                    'users_id' => $this->users_id,
                ];
                $update = [
                    'selected' => 0,
                    'update_time' => getTime()
                ];
                Db::name("shop_cart")->where($where)->update($update);
                $this->success('数据正确', url('user/Shop/shop_under_order'));
            }

            $this->error(show_foreign_lang('数据有误'));
        }
    }

    public function select_verify_store()
    {
        // 查询条件
        $where = [
            'store_status' => 1,
        ];
        $list = Db::name("weapp_verify_store")->where($where)->order('store_id desc')->select();
        $regionList = get_region_list();
        foreach ($list as $key => $value) {
            // 省市县区地址处理
            $city = !empty($regionList[$value['store_city']]['name']) ? $regionList[$value['store_city']]['name'] : '';
            $area = !empty($regionList[$value['store_area']]['name']) ? $regionList[$value['store_area']]['name'] : '';
            $prov = !empty($regionList[$value['store_province']]['name']) ? $regionList[$value['store_province']]['name'] : '';
            $value['prov_city_area'] = $prov . ' ' . $city . ' ' . $area;
            $list[$key] = $value;
        }
        $this->assign('list', $list);
        return $this->fetch('users/shop_select_verify_store');
    }

    // 添加收货地址
    public function shop_add_address()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['consignee'])) $this->error(show_foreign_lang('请填写收货人姓名'));
            if (empty($post['mobile'])) $this->error(show_foreign_lang('请填写收货人手机'));
            if ('v5' == $this->usersTplVersion) {
                if (empty($post['country'])) $this->error(show_foreign_lang('请完善收货地址'));
            } else {
                if (empty($post['province'])) $this->error('请完善收货地址');
            }
            if (empty($post['address'])) $this->error(show_foreign_lang('请填写详细地址'));
            // 添加数据
            $post['users_id'] = $this->users_id;
            $post['add_time'] = getTime();
            $post['lang']     = $this->home_lang;
            if (isMobile() && isWeixin()) {
                // 在手机微信端、小程序中则把新增的收货地址设置为默认地址
                if ('v3' == $this->usersTplVersion) {
                    $post['is_default'] = !empty($post['is_default']) ? 1 : 0;
                } else {
                    $post['is_default'] = 1;// 设置为默认地址
                }
            } else {
                $post['is_default'] = !empty($post['is_default']) ? 1 : 0;
            }
            if (isset($post['is_default'])) $post['is_default'] = !empty($post['is_default']) ? 1 : 0;
            $addr_id = $this->shop_address_db->insertGetId($post);
            if (!empty($addr_id)) {
                // 把对应会员下的所有地址改为非默认
                if (!empty($post['is_default'])) {
                    $where = [
                        'addr_id'  => array('NEQ', $addr_id),
                        'users_id' => $this->users_id,
                        'lang'     => $this->home_lang,
                    ];
                    $update = [
                        'is_default' => 1,
                        'update_time' => getTime()
                    ];
                    $this->shop_address_db->where($where)->update($update);
                }

                // 如果是移动端(手机端、微信端)并且有v2x参数则执行
                if ((isMobile() || isWeixin()) && (!empty($post['v2x']) || !empty($post['v5']))) {
                    $url = session($this->users_id.'_EyouShopOrderUrl');
                    $this->success(show_foreign_lang('操作成功'), $url, ['url' => $url]);
                }

                if (!empty($post['type']) && 'list' == $post['type']) {
                    $url = url('user/Shop/shop_address_list');
                    $this->success(show_foreign_lang('操作成功'), $url, ['url' => $url]);
                }

                // 根据地址ID查询相应的中文名字
                $post['country']  = ('v5' == $this->usersTplVersion) ? get_country_name($post['country']) : '';
                $post['province'] = get_province_name($post['province']);
                $post['city']     = get_city_name($post['city']);
                $post['district'] = get_area_name($post['district']);
                $post['addr_id']  = $addr_id;
                $post['is_mobile'] = $this->is_mobile;
                $post['Info']     = $post['country'] . ' ' . $post['province'].' '.$post['city'].' '.$post['district'];
                $this->success(show_foreign_lang('操作成功'), null, $post);
            } else {
                $this->error(show_foreign_lang('操作失败'));
            }
        }

        $types = input('param.type');
        if ('list' == $types || 'order' == $types || 'order_new' == $types) {
            $Where = [
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $addr_num = $this->shop_address_db->where($Where)->count();

            $eyou = [
                'field'    => [
                    'Country'  => in_array($this->usersTplVersion, ['v5']) ? get_country_list() : [],
                    'Province' => !in_array($this->usersTplVersion, ['v5']) ? get_province_list() : [],
                    'types'    => $types,
                    'addr_num' => $addr_num,
                ],
            ];
            $this->assign('eyou',$eyou);
        }else{
            $this->error(show_foreign_lang('数据有误'));
        }

        $this->assign('is_mobile', $this->is_mobile);
        $this->assign('add_addr_url', url("user/Shop/shop_get_wechat_addr"));
        // 将当前URL存入 session ，使用微信获取收货地址时需要验签加密
        session($this->users_id.'_EyouShopAddAddress', $this->request->url(true));
        return $this->fetch('users/shop_add_address');
    }

    // 更新收货地址
    public function shop_edit_address()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['consignee'])) $this->error(show_foreign_lang('请填写收货人姓名'));
            if (empty($post['mobile'])) $this->error(show_foreign_lang('请填写收货人手机'));
            if ('v5' == $this->usersTplVersion) {
                if (empty($post['country'])) $this->error(show_foreign_lang('请完善收货地址'));
            } else {
                if (empty($post['province'])) $this->error('请完善收货地址');
            }
            if (empty($post['address'])) $this->error(show_foreign_lang('请填写详细地址'));
            // 更新条件及数据
            $post['addr_id'] = intval($post['addr_id']);
            $post['users_id'] = $this->users_id;
            $post['update_time'] = getTime();
            $post['lang']     = $this->home_lang;
            if ('v5' != $this->usersTplVersion) {
                $post['country'] = (empty($post['country']) || $post['country'] == '中国') ? 0 : 1;
            }
            if (isset($post['is_default'])) $post['is_default'] = !empty($post['is_default']) ? 1 : 0;
            
            $where = [
                'addr_id'  => $post['addr_id'],
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $addr_id = $this->shop_address_db->where($where)->update($post);
            if (!empty($addr_id)) {
                // 把对应会员下的所有地址改为非默认
                if (isset($post['is_default']) && 1 == $post['is_default']) {
                    $AddressWhere = [
                        'addr_id'  => array('NEQ',$post['addr_id']),
                        'users_id' => $this->users_id,
                        'lang'     => $this->home_lang,
                    ];
                    $data_new['is_default']  = 0;// 设置为非默认地址
                    $data_new['update_time'] = getTime();
                    $this->shop_address_db->where($AddressWhere)->update($data_new);
                }

                // 如果是移动端(手机端、微信端)并且有v2x参数则执行
                if ((isMobile() || isWeixin()) && (!empty($post['v2x']) || !empty($post['v5']))) {
                    $url = session($this->users_id.'_EyouShopOrderUrl');
                    $this->success(show_foreign_lang('操作成功'), $url, ['url' => $url]);
                }

                // 根据地址ID查询相应的中文名字
                $post['country']  = ('v5' == $this->usersTplVersion) ? get_country_name($post['country']) : '中国';
                $post['province'] = get_province_name($post['province']);
                $post['city']     = get_city_name($post['city']);
                $post['district'] = get_area_name($post['district']);
                $this->success(show_foreign_lang('操作成功'), null, $post);
            } else {
                $this->error(show_foreign_lang('操作失败'));
            }
        }

        // 查询收货地址
        $addr_id = input('param.addr_id/d', 0);
        $where = [
            'addr_id'  => $addr_id,
            'users_id' => $this->users_id,
            'lang'     => $this->home_lang,
        ];
        $address = $this->shop_address_db->where($where)->find();
        if (empty($address)) $this->error(show_foreign_lang('数据有误'));

        // 处理收货地址
        if (in_array($this->usersTplVersion, ['v3', 'v5']) || in_array($this->usersTpl2xVersion, ['v2.x'])) {
            $address['country_name'] = get_country_name($address['country']);
            $address['province_name'] = get_province_name($address['province']);
            $address['city_name'] = get_city_name($address['city']);
            $address['district_name'] = get_area_name($address['district']);
        }

        $address['country']  = empty($address['country_name']) ? '中国' : $address['country_name']; //国家
        $address['Country']  = in_array($this->usersTplVersion, ['v5']) ? get_country_list() : []; // 国家
        $address['Province'] = !in_array($this->usersTplVersion, ['v5']) ? get_province_list() : []; // 省份
        $address['City']     = $this->region_db->where('parent_id',$address['province'])->select(); // 城市
        $address['District'] = $this->region_db->where('parent_id',$address['city'])->select(); // 县/区/镇
        $address['onDelAddress'] = " onclick=\"DelAddress('{$addr_id}', this);\" ";

        $type = input('param.type/s', 'list');
        $gourl = input('param.gourl/s', '', 'urldecode');
        $delAddressUrl = url('Shop/shop_del_address', ['type'=>$type, 'gourl'=>$gourl]);
        $address['delAddressUrl'] = $delAddressUrl;

        $eyou = [
            'field' => $address,
        ];
        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_edit_address');
    }

    // 删除收货地址
    public function shop_del_address()
    {
        if (IS_POST) {
            $addr_id = input('post.addr_id/d');
            $Where = [
                'addr_id'  => $addr_id,
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $return = $this->shop_address_db->where($Where)->delete();
            if ($return) {
                $gourl = '';
                $type = input('param.type/s', 'list');
                if ('order' == $type) {
                    $gourl = input('param.gourl/s', '', 'urldecode');
                }
                $url = url('Shop/shop_address_list', ['type'=>$type, 'gourl'=>$gourl]);
                $this->success(show_foreign_lang('操作成功'), null, ['url'=>$url]);
            }else{
                $this->error(show_foreign_lang('操作失败'));
            }
        }
    }

    // 更新收货地址，设置为默认地址
    public function shop_set_default_address()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 更新条件及数据
            $post['addr_id'] = intval($post['addr_id']);
            $post['users_id']   = $this->users_id;
            $post['is_default'] = '1'; //设置为默认
            $post['add_time']   = getTime();
            $post['lang']       = $this->home_lang;

            $AddrWhere = [
                'addr_id'  => $post['addr_id'],
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $addr_id = $this->shop_address_db->where($AddrWhere)->update($post);
            if (!empty($addr_id)) {
                // 把对应会员下的所有地址改为非默认
                $AddressWhere = [
                    'addr_id'  => array('NEQ',$post['addr_id']),
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
                $data['is_default']  = '0';// 设置为非默认
                $data['update_time'] = getTime();
                $this->shop_address_db->where($AddressWhere)->update($data);
                $this->success(show_foreign_lang('操作成功'));
            }else{
                $this->error(show_foreign_lang('操作失败'));
            }
        }
    }

    // 查询运费
    public function shop_inquiry_shipping()
    {
        if (IS_AJAX_POST) {
            $shop_open_shipping = getUsersConfigData('shop.shop_open_shipping');
            if (empty($shop_open_shipping)) $this->success('未开启运费', '', 0);
            
            // 查询会员收货地址，获取省份
            $addr_id = input('post.addr_id');
            if ($this->usersTplVersion == 'v3') { // 第三套小米的会员中心
                if (empty($addr_id)) {
                    $addr_id = Db::name('shop_address')->where(['users_id'=>$this->users_id])->order('addr_id asc')->value('addr_id');
                }
            }
            $where = [
                'addr_id'  => $addr_id,
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            $province = $this->shop_address_db->where($where)->getField('province');

            // 通过省份获取运费模板中的运费价格
            $template_money = $this->shipping_template_db->where('province_id', $province)->getField('template_money');
            if (0 == $template_money) {
                // 省份运费价格为0时，使用统一的运费价格，固定ID为100000
                $template_money = $this->shipping_template_db->where('province_id', '100000')->getField('template_money');
            }

            // 查询下单商品信息
            $querystr = input('post.querystr/s', '');
            $merchantID = 0;
            if (!empty($querystr)) {
                // 解析参数查询商品信息
                $querystr = cookie($querystr);
                $aid = !empty($querystr['aid']) ? $querystr['aid'] : 0;
                $where = [
                    'aid' => $aid
                ];
                $archives = $this->archives_db->field('merchant_id, free_shipping')->where($where)->select();
                $merchantID = count(array_filter(array_unique(get_arr_column($archives, 'merchant_id'))));
            } else {
                $where = [
                    'a.selected' => 1,
                    'a.users_id' => $this->users_id,
                ];
                $field = 'b.merchant_id, b.free_shipping';
                $archives = $this->shop_cart_db->alias('a')->field($field)->join('__ARCHIVES__ b', 'a.product_id = b.aid', 'LEFT')->where($where)->select();
                $merchantID = count(array_filter(array_unique(get_arr_column($archives, 'merchant_id'))));
            }
            if (intval($merchantID) >= 1) {
                // 处理查询出需要运费的商品数量
                $merchantIDArr = [];
                $shippingFeeNum = 0;
                foreach ($archives as $key => $value) {
                    if (!in_array($value['merchant_id'], $merchantIDArr)) {
                        if (isset($value['free_shipping']) && 0 === intval($value['free_shipping'])) {
                            $shippingFeeNum++;
                            array_push($merchantIDArr, $value['merchant_id']);
                        }
                    }
                }
                $template_money = strval($template_money) * strval($shippingFeeNum);
            }

            $template_money = floatval($template_money);
            $this->success('查询成功！', '', $template_money);
        } else {
            $this->error(show_foreign_lang('订单号错误'));
        }
    }

    // 联动地址获取
    public function get_region_data()
    {
        $parent_id  = input('param.parent_id/d', 0);
        $regionData = $this->region_db->where("parent_id", $parent_id)->select();
        if (in_array($this->usersTpl2xVersion, ['v2.x']) || in_array($this->usersTplVersion, ['v3', 'v5'])) {
            $this->success('查询成功！', null, $regionData);
        } else {
            $html = '';
            if (!empty($regionData)) {
                // 拼装下拉选项
                foreach($regionData as $value) {
                    $html .= "<option value='{$value['id']}'>{$value['name']}</option>";
                }
            }
            echo json_encode($html);
        }
    }

    // 会员提醒收货
    public function shop_order_remind()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 添加订单操作记录
            AddOrderAction($post['order_id'],$this->users_id,'0','1','0','1','提醒成功！','会员提醒管理员及时发货！');
            $this->success(show_foreign_lang('提醒成功'));
        }else{
            $this->error(show_foreign_lang('订单号错误'));
        }
    }

    // 会员确认收货
    public function shop_member_confirm()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $post['order_id'] = intval($post['order_id']);
            // 更新条件
            $where = [
                'order_id' => $post['order_id'],
                'users_id' => $this->users_id,
                'lang'     => $this->home_lang,
            ];
            // 更新数据
            $update = [
                'order_status' => 3,
                'confirm_time' => getTime(),
                'update_time'  => getTime(),
                // 如果后台【商城中心】-【商城配置】-【订单设置】-收货后可维权时间设置为0，则表示订单不允许申请维权
                'allow_service' => !empty($this->usersConfig['order_right_protect_time']) ? 0 : 1
            ];
            // 更新订单主表
            $return = $this->shop_order_db->where($where)->update($update);
            if (!empty($return)) {
                // 查询订单是否存在多商家，存在多商家则执行
                $shopOrder = $this->shop_order_db->where($where)->find();
                if (!empty($shopOrder['merchant_id'])) {
                    // 处理多商家订单金额结算
                    $multiMerchantLogic = new \app\user\logic\MultiMerchantLogic;
                    $multiMerchantLogic->handleMultiMerchantOrderSettle($shopOrder);
                }
                // 更新数据
                $update = [
                    'update_time'  => getTime(),
                ];
                // 更新订单明细表
                $this->shop_order_details_db->where($where)->update($update);
                // 添加订单操作记录
                AddOrderAction($post['order_id'], $this->users_id, 0, 3, 1, 1, '确认收货', '会员已确认收到货物，订单完成');
                // 如果安装了分销插件则执行
                if (is_dir('./weapp/DealerPlugin/')) {
                    // 开启分销插件则执行
                    $weappInfo = model('Weapp')->getWeappList('DealerPlugin');
                    if (!empty($weappInfo['status']) && 1 == $weappInfo['status']) {
                        // 调用分销逻辑层方法
                        $dealerCommonLogic = new \weapp\DealerPlugin\logic\DealerCommonLogic;
                        $dealerCommonLogic->dealerOrderSettlementExecute($post['order_id'], $this->users_id);
                    }
                }
                $this->success(show_foreign_lang('会员确认收货'));
            }else{
                $this->error(show_foreign_lang('订单号错误'));
            }
        }
    }
    
    // 获取微信收货地址
    public function shop_get_wechat_addr()
    {
        if (IS_AJAX_POST) {
            // 微信配置信息
            $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
            $appid     = !empty($WeChatLoginConfig['appid']) ? $WeChatLoginConfig['appid'] : '';
            $appsecret = !empty($WeChatLoginConfig['appsecret']) ? $WeChatLoginConfig['appsecret'] : '';
            
            if (empty($appid)) {
                $this->error('后台微信配置尚未配置AppId，不可以获取微信地址！');
            }else if (empty($appsecret)) {
                $this->error('后台微信配置尚未配置AppSecret，不可以获取微信地址！');
            }

            // 当前时间戳
            $time = getTime();
            // 微信access_token和jsapi_ticket信息
            $WechatData  = getUsersConfigData('wechat');
            // access_token信息判断
            $accesstoken = !empty($WechatData['wechat_token_value']) ? $WechatData['wechat_token_value'] : [];
            if (empty($accesstoken)) {
                // 如果配置表中的accesstoken为空则执行
                // 获取公众号access_token，接口限制10万次/天
                $return = $this->shop_model->GetWeChatAccessToken($appid,$appsecret);
                if (empty($return['status'])) {
                    $this->error($return['prompt']);
                }else{
                    $accesstoken = $return['token'];
                }
            }else if ($time > ($WechatData['wechat_token_time']+7000)) {
                // 如果配置表中的时间超过过期时间则执行
                // 获取公众号access_token，接口限制10万次/天
                $return = $this->shop_model->GetWeChatAccessToken($appid,$appsecret);
                if (empty($return['status'])) {
                    $this->error($return['prompt']);
                }else{
                    $accesstoken = $return['token'];
                }
            }

            // jsapi_ticket信息判断
            $jsapi_ticket = !empty($WechatData['wechat_ticket_value']) ? $WechatData['wechat_ticket_value'] : [];
            if (empty($jsapi_ticket)) {
                // 获取公众号jsapi_ticket，接口限制500万次/天
                $return = $this->shop_model->GetWeChatJsapiTicket($accesstoken);
                if (empty($return['status'])) {
                    $this->error($return['prompt']);
                }else{
                    $jsapi_ticket = $return['ticket'];
                }
            }else if ($time > ($WechatData['wechat_ticket_time']+7000)) {
                // 获取公众号jsapi_ticket，接口限制500万次/天
                $return = $this->shop_model->GetWeChatJsapiTicket($accesstoken);
                if (empty($return['status'])) {
                    $this->error($return['prompt']);
                }else{
                    $jsapi_ticket = $return['ticket'];
                }
            }

            // <---- 加密参数开始 
            // 微信公众号jsapi_ticket
            // $jsapi_ticket = $jsapi_ticket;
            // 随机字符串
            $noncestr  = $this->shop_model->GetRandomString('16');
            $noncestr  = "$noncestr";
            // 当前时间戳
            $timestamp = time();
            $timestamp = "$timestamp";
            // 当前访问接口URL
            if ($this->usersTplVersion == 'v3') {
                $url = session($this->users_id . '_EyouShopAddAddress');
            } else {
                $url = $this->request->url(true);
            }
            // 加密参数结束 ----->
            
            // 参数加密，顺序固定不可改变
            $string    = 'jsapi_ticket='.$jsapi_ticket.'&noncestr='.$noncestr.'&timestamp='.$timestamp.'&url='.$url;
            $signature = SHA1($string);

            // 返回结果
            $result = [
                // 用于调试，不影响正常业务(如不需要，可直接清理)
                'token'     => $accesstoken,
                'ticket'    => $jsapi_ticket,
                'url'       => $url, // 传入接口调用参数(必须返回)
                'appid'     => $appid,
                'timestamp' => $timestamp,
                'noncestr'  => $noncestr,
                'signature' => $signature,
            ];
            $this->success('数据获取！',null,$result);
        }

        $result = [
            'wechat_url'   => url("user/Shop/shop_get_wechat_addr"),
            'add_addr_url' => url("user/Shop/shop_add_address"),
        ];
        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_get_wechat_addr');
    }

    // 添加微信的收货地址到数据库
    public function add_wechat_addr()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 省
            $province = $this->region_db->where('name',$post['provinceName'])->getField('id');
            // 市
            $city     = $this->region_db->where('name',$post['cityName'])->getField('id');
            // 县
            $district = $this->region_db->where('name',$post['countryName'])->getField('id');

            // 查询这个收货地址是否存在
            $where  = [
                'users_id'   => $this->users_id,
                'consignee'  => $post['userName'],
                'mobile'     => $post['telNumber'],
                'province'   => $province,
                'city'       => $city,
                'district'   => $district,
                'address'    => $post['detailInfo'],
                'lang'       => $this->home_lang,
            ];
            $return = $this->shop_address_db->where($where)->find();
            if (!empty($return)) {
                $this->success('获取成功！',session($this->users_id.'_EyouShopOrderUrl'));
            }else{
                $data = [
                    'users_id'   => $this->users_id,
                    'consignee'  => $post['userName'],
                    'mobile'     => $post['telNumber'],
                    'province'   => $province,
                    'city'       => $city,
                    'district'   => $district,
                    'address'    => $post['detailInfo'],
                    'is_default' => 1, // 设置为默认地址
                    'lang'       => $this->home_lang,
                    'add_time'   => getTime(),
                ];
                if ($this->usersTplVersion == 'v3') $data['is_default'] = 0;
                $addr_id = $this->shop_address_db->add($data);
                if (!empty($addr_id)) {
                    if ($this->usersTplVersion != 'v3') {
                        // 把对应会员下的所有地址改为非默认
                        $AddressWhere = [
                            'addr_id'  => array('NEQ',$addr_id),
                            'users_id' => $this->users_id,
                            'lang'     => $this->home_lang,
                        ];
                        $data_new['is_default']  = '0';// 设置为非默认地址
                        $data_new['update_time'] = getTime();
                        $this->shop_address_db->where($AddressWhere)->update($data_new);
                    }
                    $this->success('获取成功！',session($this->users_id.'_EyouShopOrderUrl'));
                }else{
                    $this->success('获取失败，请刷新后重试！');
                }
            }
        }
    }

    // 判断商品是否库存为0
    private function IsSoldOut($param = array())
    {
       if (!empty($param['aid'])) {
            if (!empty($param['spec_value_id'])) {
                $SpecWhere = [
                    'aid'  => $param['aid'],
                    'lang' => $this->home_lang,
                    'spec_stock' => ['>',0],
                    'spec_value_id' => $param['spec_value_id'],
                ];
                $spec_stock = Db::name('product_spec_value')->where($SpecWhere)->getField('spec_stock');
                if (empty($spec_stock)) {
                    $data['code'] = -1; // 已售罄
                    $this->error(show_foreign_lang('商品已售罄！'), null, $data);
                }
                if ($spec_stock < $param['num']) {
                    $data['code'] = -1; // 库存不足
                    $this->error(show_foreign_lang('商品最大库存：').$spec_stock, null, $data);
                }
            } else {
                $archives_where = [
                    'arcrank' => array('egt','0'), //带审核稿件不查询
                    'aid'     => $param['aid'],
                    'lang'    => $this->home_lang,
                ];
                $stock_count = $this->archives_db->where($archives_where)->getField('stock_count');
                if (empty($stock_count)) {
                    $data['code'] = -1; // 已售罄
                    $this->error(show_foreign_lang('商品已售罄！'), null, $data);
                }
                if ($stock_count < $param['num']) {
                    $data['code'] = -1; // 库存不足
                    $this->error(show_foreign_lang('商品最大库存：').$stock_count, null, $data);
                }
            }
        }
    }

    // 申请退换货服务中转页
    public function service_list()
    {
        $order_id = input('param.order_id/d', 0);
        if (empty($order_id)) $this->error('订单不存在！');
        // 查询订单商品
        $where = [
            'order_id' => $order_id,
            'users_id' => $this->users_id,
        ];
        $order_details = $this->shop_order_details_db->where($where)->select();
        if (empty($order_details)) $this->error('订单不存在！');
        // 查询订单商品售后
        $order_service = $this->shop_order_service_db->where($where)->getAllWithIndex('details_id');
        foreach ($order_details as $k => $v) {
            // 图片链接
            $v['litpic'] = !empty($v['litpic']) ? handle_subdir_pic($v['litpic']) : '';
            if (!empty($v['apply_service']) && !empty($order_service[$v['details_id']]['service_id'])) {
                // 售后链接
                $v['name'] = '查看售后';
                $v['url'] = url('user/Shop/after_service_details', ['service_id'=>$order_service[$v['details_id']]['service_id']]);
            } else {
                // 申请链接
                $v['name'] = '申请售后';
                $v['url'] = url('user/Shop/after_service_apply', ['details_id'=>$v['details_id']]);
            }
            // 获取订单商品规格列表(购买时的商品规格)
            $v['product_spec_list'] = model('ShopPublicHandle')->getOrderGoodsSpecList($v);
            // 覆盖原数据
            $order_details[$k] = $v;
        }

        $eyou = [
            'field' => $order_details
        ];
        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_after_service_list');
    }

    /*------陈风任---2021-1-12---售后服务(退换货)------开始------*/
    // 申请退换货服务
    public function after_service_apply()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post)) $this->error('提交数据有误！');
            if (empty($post['service_type'])) $this->error('请选择服务类型！');
            if (empty($post['content'])) $this->error('请填写问题描述！');
            if (empty($post['address'])) $this->error('请填写您的收货地址！');
            if (empty($post['consignee'])) $this->error('请填写您的姓名！');
            if (empty($post['mobile'])) $this->error('请填写您的手机号码！');
            $time = getTime();
            $post['order_id'] = intval($post['order_id']);
            $post['details_id'] = intval($post['details_id']);
            $post['product_id'] = intval($post['product_id']);
            $post['product_num'] = intval($post['product_num']);
            $post['service_type'] = intval($post['service_type']);
            $post['refund_price'] = unifyPriceHandle($post['refund_price']);
            // 查询订单详情信息
            $details = $this->shop_model->GetOrderDetailsInfo($post['details_id'], $this->users_id);
            if (isset($details['code']) && 0 === intval($details['code'])) $this->error($details['msg']);
            // 组合添加数据
            $data = [
                'users_id'    => $this->users_id,
                'merchant_id' => $details['merchant_id'],
                'address'     => $post['addrinfo'].' '.$post['address'],
                'upload_img'  => !empty($post['upload_img']) ? implode(',', $post['upload_img']) : '',
                'refund_balance' => '',
                'refund_point' => '',
                'refund_code' => 'HH' . $time . rand(10,99),
                'add_time'    => $time,
                'update_time' => $time,
            ];
            if (2 === intval($post['service_type']) && !empty($post['refund_price'])) $data['refund_code'] = 'TK' . $time . rand(10,99);
            $insert = array_merge($post, $data);
            $resultID = $this->shop_order_service_db->insertGetId($insert);
            if (!empty($resultID)) {
                // 更新订单明细表中对应商品为申请服务
                $update = [
                    'apply_service' => 1,
                    'update_time' => getTime()
                ];
                $this->shop_order_details_db->where('details_id', $post['details_id'])->update($update);

                // 添加订单服务记录
                $LogNote = 1 == $post['service_type'] ? '用户提交换货申请，待审核！' : '用户提交退货申请，待审核！';
                OrderServiceLog($resultID, $post['order_id'], $this->users_id, 0, $LogNote);

                // 返回结束
                $this->success('已申请，待审核', url('user/Shop/after_service_details', ['service_id' => $resultID]));
            } else {
                $this->error('申请失败！');
            }
        }

        $details_id = input('param.details_id/d');
        if (empty($details_id)) $this->error('售后服务单不存在！');

        // 查询订单中单个商品信息
        $data = $this->shop_model->GetOrderDetailsInfo($details_id, $this->users_id);

        // 返回错误提示
        if (!empty($data['msg']) && isset($data['code']) && 0 == $data['code']) $this->error($data['msg']);
        
        // 商户收货信息
        $maddr = getUsersConfigData('addr');
        // 如果存在商家ID则执行
        if (!empty($data['merchant_id'])) {
            // 查询商家地址信息
            $multiMerchantLogic = new \app\user\logic\MultiMerchantLogic;
            $maddr = $multiMerchantLogic->getMultiMerchantContact($data['merchant_id']);
        }

        $eyou = [
            'url'   => url('user/Shop/after_service_apply', ['_ajax'=>1]),
            'maddr' => $maddr,
            'field' => $data
        ];
        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_after_service_apply');
    }

    // 退货换服务列表
    public function after_service()
    {
        $status = input('param.status/s');
        $keywords = input('param.keywords/s');
        $orderCode = input('param.order_code');
        $statusArrEd = [3, 6, 7, 8];
        $statusArrIng = [1, 2, 4, 5];
        $ServiceInfo = $this->shop_model->GetAllServiceInfo($this->users_id, $orderCode, $status, $keywords, $statusArrEd, $statusArrIng);

        $where = [
            'lang' => $this->home_lang,
            'users_id' => $this->users_id
        ];
        //处理中
        $where['status'] = ['IN', $statusArrIng];
        $ServicingTotal = $this->shop_order_service_db->where($where)->count();
        //已完成
        $where['status'] = ['IN', $statusArrEd];
        $ServicedTotal = $this->shop_order_service_db->where($where)->count();
        $eyou = [
            'field' => [
               'service' => $ServiceInfo['Service'],
               'pageStr' => $ServiceInfo['pageStr'],
               'ServicingTotal' => $ServicingTotal,
               'ServicedTotal' => $ServicedTotal,
            ],
            'statusArrEd' => $statusArrEd,
            'statusArrIng' => $statusArrIng,
        ];
        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_after_service');
    }

    // 申请服务详情
    public function after_service_details()
    {
        if (IS_AJAX_POST) {
            $param = input('param.');
            if (empty($param['service_id']) || empty($param['details_id'])) $this->error('请选择需要取消的服务单');
            // 取消服务单
            $where = [
                'users_id'   => $this->users_id,
                'service_id' => intval($param['service_id']),
            ];
            $update = [
                'status' => 8,
                'update_time' => getTime(),
            ];
            $resultID = $this->shop_order_service_db->where($where)->update($update);
            if (!empty($resultID)) {
                // 更新订单明细表中对应商品为未申请服务
                $where = [
                    'users_id'   => $this->users_id,
                    'details_id' => intval($param['details_id'])
                ];
                $update = [
                    'apply_service' => 0,
                    'update_time' => getTime()
                ];
                $this->shop_order_details_db->where($where)->update($update);

                // 添加记录单
                $param['users_id'] = $this->users_id;
                $param['status'] = 8;
                $this->shop_common->AddOrderServiceLog($param, 1);

                $this->success('取消成功');
            } else {
                $this->error('取消失败');
            }
        }

        $service_id = input('param.service_id/d');
        if (empty($service_id)) $this->error('售后服务单不存在', url('user/Shop/shop_centre'));
        // 查询售后单详情
	    $details = $this->shop_model->GetServiceDetailsInfo($service_id, $this->users_id);
        // 商户收货信息
        $maddr = getUsersConfigData('addr');
        // 如果存在商家ID则执行
        if (!empty($details['merchant_id'])) {
            // 查询商家地址信息
            $multiMerchantLogic = new \app\user\logic\MultiMerchantLogic;
            $maddr = $multiMerchantLogic->getMultiMerchantContact($details['merchant_id']);
        }
        $expressList = Db::name('shop_express')->select();
        // 加载模板
        $eyou = [
            'maddr'      => $maddr,
            'field'      => $details,
            'ServiceUrl' => url('user/Shop/after_service_update', ['_ajax' => 1]),
            'StatusArr'  => [3, 6, 7, 8],
            'StatusLog'  => $this->shop_model->GetOrderServiceLog($service_id, $this->users, $details['users_id']),
            'ExpressList'  => $expressList,
        ];

        $this->assign('eyou', $eyou);
        return $this->fetch('users/shop_after_service_details');
    }

    // 更新服务单状态
    public function after_service_update()
    {
        if (IS_AJAX_POST) {
            if (empty($this->users_id)) $this->redirect('user/Login/login');
            if ($this->usersTplVersion == 'v3') return $this->after_service_update_v3();

            $post = input('post.');
            if (empty($post['delivery']['cost'])) $post['delivery']['cost'] = 0;
            if (empty($post['delivery']['time'])) $post['delivery']['time'] = date('Y-m-d H:i:s');
            // 查询条件
            $where = [
                'users_id' => $this->users_id,
                'service_id' => intval($post['service_id']),
            ];
            // 更新数据
            $update = [
                'status' => 4,
                'users_delivery' => serialize($post['delivery']),
                'update_time' => getTime(),
            ];
            $resultID = $this->shop_order_service_db->where($where)->update($update);
            if (!empty($resultID)) {
                // 添加记录单
                $post['users_id'] = $this->users_id;
                $this->shop_common->AddOrderServiceLog($post, 1);
                $this->success(foreign_lang('system12', $this->home_lang));
            } else {
                $this->error(foreign_lang('system11', $this->home_lang));
            }
        }
    }

    // 第三套模板 更新服务单状态
    public function after_service_update_v3()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 查询条件
            $where = [
                'users_id' => $this->users_id,
                'service_id' => intval($post['service_id']),
                'details_id' => intval($post['details_id']),
            ];
            // 更新数据
            $update = [
                'status' => 4,
                'users_delivery' => serialize($post['delivery']),
                'update_time' => getTime(),
            ];
            $resultID = $this->shop_order_service_db->where($where)->update($update);
            if (!empty($resultID)) {
                // 添加记录单
                $post['users_id'] = $this->users_id;
                $this->shop_common->AddOrderServiceLog($post, 1);
                $this->success(foreign_lang('system12', $this->home_lang));
            } else {
                $this->error(foreign_lang('system11', $this->home_lang));
            }
        }
    }
    /*------陈风任---2021-1-12---售后服务(退换货)------结束------*/

    // 移动端订单列表瀑布流分页专业ajax加载接口 20210709大黄
    public function ajax_shop_centre_page()
    {
        // 基础查询条件
        $OrderWhere = [
            'users_id' => $this->users_id,
            'lang'     => $this->home_lang,
        ];

        // 应用搜索条件
        $keywords = input('param.keywords/s');
        if (!empty($keywords)) $OrderWhere['order_code'] = ['LIKE', "%{$keywords}%"];

        // 订单状态搜索
        $select_status = input('param.select_status');
        $pagesize = input('param.pagesize',10);
        if (!empty($select_status)) {
            if ('daifukuan' === $select_status) $select_status = 0;
            if (3 == $select_status) $OrderWhere['is_comment'] = 0;
            $OrderWhere['order_status'] = $select_status;
        }

        // 分页查询逻辑
        $paginate_type = isMobile() ? 'usersmobile' : 'userseyou';
        $query_get = input('get.');
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
        $result['list']  = $pages->items();
        $result['pages'] = $pages;

        // 搜索名称时，查询订单明细表商品名称
        if (empty($result['list']) && !empty($keywords)) {
            $Data = model('Shop')->QueryOrderList($pagesize, $this->users_id, $keywords, $query_get);
            $result['list']  = $Data['list'];
            $result['pages'] = $Data['pages'];
        }

        /*规格值ID预处理*/
        $SpecValueArray = Db::name('product_spec_value')->field('aid,spec_value_id')->select();
        $SpecValueArray = group_same_key($SpecValueArray, 'aid');
        $ReturnData  = [];
        foreach ($SpecValueArray as $key => $value) {
            $ReturnData[$key] = [];
            foreach ($value as $kk => $vv) {
                array_push($ReturnData[$key], $vv['spec_value_id']);
            }
        }
        /* END */

        if (!empty($result['list'])) {
            // 订单数据处理
            $controller_name = 'Product';
            // 获取当前链接及参数，用于手机端查询快递时返回页面
            $OrderIds = [];
            $ReturnUrl = request()->url(true);
            foreach ($result['list'] as $key => $value) {
                $DetailsWhere['a.users_id'] = $value['users_id'];
                $DetailsWhere['a.order_id'] = $value['order_id'];
                // 查询订单明细表数据
                $result['list'][$key]['details'] = Db::name('shop_order_details')->alias('a')
                    ->field('a.*, b.service_id, c.is_del')
                    ->join('__SHOP_ORDER_SERVICE__ b', 'a.details_id = b.details_id', 'LEFT')
                    ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
                    ->order('a.product_price desc, a.product_name desc')
                    ->where($DetailsWhere)
                    ->select();
                $array_new = get_archives_data($result['list'][$key]['details'], 'product_id');

                foreach ($result['list'][$key]['details'] as $kk => $vv) {
                    // 产品规格处理
                    $spec_data = unserialize($vv['data']);
                    if (!in_array($vv['order_id'], $OrderIds) && 0 == $value['order_status']) {
                        if (!empty($spec_data['spec_value_id'])) {
                            $spec_value_id = $spec_data['spec_value_id'];
                            if (!in_array($spec_value_id, $ReturnData[$vv['product_id']])) {
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
                    }

                    $product_spec_list = [];
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
                    $result['list'][$key]['details'][$kk]['product_spec_list'] = $product_spec_list;

                    // 产品内页地址
                    if (!empty($array_new[$vv['product_id']]) && 0 == $vv['is_del']) {
                        // 商品存在
                        $arcurl = urldecode(arcurl('home/'.$controller_name.'/view', $array_new[$vv['product_id']]));
                        $has_deleted = 0;
                        $msg_deleted = '';
                    } else {
                        // 商品不存在
                        $arcurl = urldecode(url('home/View/index', ['aid'=>$vv['product_id']]));
                        $has_deleted = 1;
                        $msg_deleted = show_foreign_lang('[商品已停售]');
                    }
                    $result['list'][$key]['details'][$kk]['arcurl'] = $arcurl;
                    $result['list'][$key]['details'][$kk]['has_deleted'] = $has_deleted;
                    $result['list'][$key]['details'][$kk]['msg_deleted'] = $msg_deleted;
                    $result['list'][$key]['details'][$kk]['product_price'] = floatval($vv['product_price']);

                    // 图片处理
                    $result['list'][$key]['details'][$kk]['litpic'] = handle_subdir_pic(get_default_pic($vv['litpic']));

                    // 申请退换货
                    $result['list'][$key]['details'][$kk]['ApplyService'] = urldecode(url('user/Shop/after_service_apply', ['details_id' => $vv['details_id']]));

                    // 查看售后详情单
                    $result['list'][$key]['details'][$kk]['ViewAfterSale'] = urldecode(url('user/Shop/after_service_details', ['service_id' => $vv['service_id']]));

                    // 商品评价
                    $result['list'][$key]['details'][$kk]['CommentProduct'] = urldecode(url('user/ShopComment/product', ['details_id' => $vv['details_id']]));


                }

                if (empty($value['order_status'])) {
                    // 付款地址处理，对ID和订单号加密，拼装url路径
                    $Paydata = [
                        'order_id'   => $value['order_id'],
                        'order_code' => $value['order_code']
                    ];

                    // 先 json_encode 后 md5 加密信息
                    $Paystr = md5(json_encode($Paydata));

                    // 清除之前的 cookie
                    Cookie::delete($Paystr);

                    // 存入 cookie
                    cookie($Paystr, $Paydata);

                    // 跳转链接
                    $result['list'][$key]['PaymentUrl'] = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$Paystr]));
                }

                // 封装取消订单JS
                $result['list'][$key]['CancelOrder']   = " onclick=\"CancelOrder('{$value['order_id']}');\" ";

                // 获取订单状态
                $order_status_arr = Config::get('global.order_status_arr');
                $result['list'][$key]['order_status_name'] = $order_status_arr[$value['order_status']];

                // 获取订单支付方式名称
                $pay_method_arr = Config::get('global.pay_method_arr');
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
                //售后
                $result['list'][$key]['ServiceList'] = urldecode(url('user/Shop/service_list', ['order_id' => $value['order_id']]));

                //评价
                $result['list'][$key]['AddProduct'] = urldecode(url('user/ShopComment/comment_list', ['order_id' => $value['order_id']]));


                // 封装查询物流链接
                $result['list'][$key]['LogisticsInquiry'] = $MobileExpressUrl = '';
                if (('2' == $value['order_status'] || '3' == $value['order_status']) && empty($value['prom_type'])) {
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

                $result['list'][$key]['order_amount'] = floatval($value['order_amount']);
                $result['list'][$key]['order_total_amount'] = floatval($value['order_total_amount']);
                $result['list'][$key]['shipping_fee'] = is_numeric($value['shipping_fee']) ? floatval($value['shipping_fee']) : $value['shipping_fee'];

                // 默认为空
                $result['list'][$key]['hidden'] = '';
            }

            // 更新产品规格异常的订单，更新为订单过期
            if (!empty($OrderIds)) {
                // 更新订单
                $UpData = [
                    'order_status' => 4,
                    'update_time'  => getTime()
                ];
                Db::name('shop_order')->where('order_id', 'IN', $OrderIds)->update($UpData);
                // 追加订单操作记录
                AddOrderAction($OrderIds_, $this->users_id, 0, 4, 0, 0, '订单过期！', '规格更新后部分产品规格不存在，订单过期！');
            }

            // 传入JS参数
            $data['shop_order_cancel'] = url('user/Shop/shop_order_cancel', ['_ajax'=>1], true, false, 1, 1, 0);
            $data['shop_member_confirm'] = url('user/Shop/shop_member_confirm');
            $data['shop_order_remind']   = url('user/Shop/shop_order_remind');
            $data_json = json_encode($data);
            $version   = getCmsVersion();
            // 循环中第一个数据带上JS代码加载
            $result['list'][0]['hidden'] = <<<EOF
<script type="text/javascript">
    var d62a4a8743a94dc0250be0c53f833b = {$data_json};
</script>
<script type="text/javascript" src="{$this->root_dir}/public/static/common/js/tag_sporderlist.js?v={$version}"></script>
EOF;
            $this->success('请求成功!','',$result);
        }else{
            $this->error('暂无数据!');
        }
    }
}