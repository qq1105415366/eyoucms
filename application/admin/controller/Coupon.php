<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-2-12
 */

namespace app\admin\controller;

use think\Page;
use think\Db;
use think\Config;
use app\admin\logic\ShopLogic;

class Coupon extends Base
{
    public $ShopLogic;

    /**
     * 构造方法
     */
    public function _initialize()
    {
        parent::_initialize();

        $functionLogic = new \app\common\logic\FunctionLogic;
        $functionLogic->validate_authorfile(2);
        
        $this->ShopLogic = new ShopLogic;
        // 优惠券数据表
        $this->shop_coupon_db     = Db::name('shop_coupon');
        $this->shop_coupon_use_db = Db::name('shop_coupon_use');
        // 商品数据表
        $this->archives_db = Db::name('archives');
        // 商品分类数据表
        $this->arctype_db = Db::name('arctype');
        // 默认商品模型
        $this->channeltype = 2;
        $this->assign('channeltype', $this->channeltype);
        
        // 列出营销功能里已使用的模块
        $marketFunc = $this->ShopLogic->marketLogic();
        if (!in_array('coupon', $marketFunc)) {
            // $this->error('内置功能已废弃！');
        }
        $this->assign('marketFunc', $marketFunc);
    }

    // 优惠券列表
    public function index()
    {
        $condition = [
            'lang'   => $this->admin_lang,
            'is_del' => 0
        ];

        $keywords = input('keywords/s');
        // 应用搜索条件
        if (!empty($keywords)) $condition['coupon_name'] = ['LIKE', "%{$keywords}%"];

        // 分页
        $count = $this->shop_coupon_db->where($condition)->count();
        $Page  = new Page($count, config('paginate.list_rows'));
        $show  = $Page->show();
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        // 数据查询
        $list = $this->shop_coupon_db
            ->where($condition)
            ->order('sort_order asc, coupon_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        if (!empty($list)) {
            $ids = [];
            foreach ($list as $k => $v) {
                $ids[] = $v['coupon_id'];
            }
            //已领数量
            $getedArr = $this->shop_coupon_use_db->field('count(*) as count,coupon_id')->where('coupon_id', 'in', $ids)->group('coupon_id')->getAllWithIndex('coupon_id');
            foreach ($list as $k => $v) {
                $list[$k]['geted'] = $getedArr[$v['coupon_id']]['count'];
            }
        }

        $this->assign('list', $list);

        return $this->fetch();
    }

    // 新增优惠券
    public function add()
    {
        if (IS_POST) {
            $post = input('post.');
            // 数据判断处理
            if (empty($post['coupon_name'])) $this->error('请填写优惠券名称');
            $post['coupon_name'] = htmlspecialchars_decode($post['coupon_name']);
            if ('' == $post['conditions_use']) $this->error('请填写优惠规则-使用条件');
            if (empty($post['coupon_price'])) $this->error('请填写优惠规则-减免金额');
            if (empty($post['coupon_stock'])) $this->error('请填写库存');
            if (1 == $post['use_type']) {
                if (empty($post['use_start_time'])) $this->error('请填写优惠券有效开始时间');
                if (empty($post['use_end_time'])) $this->error('请填写优惠券有效结束时间');
            } else if (2 == $post['use_type'] || 3 == $post['use_type']) {
                if (empty($post['valid_days'])) $this->error('请填写优惠券有效天数');
            }
            if (empty($post['start_date'])) $this->error('请选择优惠券发放日期');
            if (empty($post['end_date'])) $this->error('请选择优惠券结束日期');
            if (2 == $post['coupon_type']) {
                if (empty($post['product_id'])) $this->error('请填写指定的商品ID');
            }
            $post['redeem_authority'] = !empty($post['level_id']) ? implode(',', $post['level_id']) : '';
            $post['start_date']       = strtotime($post['start_date']);
            $post['end_date']         = strtotime($post['end_date']);

            $post['use_start_time'] = strtotime($post['use_start_time']);
            $post['use_end_time']   = strtotime($post['use_end_time']);

            $post['add_time']    = getTime();
            $post['update_time'] = getTime();
            $post['lang']        = $this->admin_lang;

            if (1 == $post['coupon_type']) {
                $post['product_id'] = '';
                $post['arctype_id'] = '';
            } else if (2 == $post['coupon_type']) {
                $post['arctype_id'] = '';
                // 去除重复项
                $post['product_id'] = implode(',', array_unique(explode(',', $post['product_id'])));
            } else if (3 == $post['coupon_type']) {
                $post['product_id'] = '';
            }
            // 添加新优惠券
            $ResultID = $this->shop_coupon_db->add($post);
            if (!empty($ResultID)) {
                $ResultID     = 2 > strlen($ResultID) ? '0' . $ResultID : $ResultID;
                $coupon_code  = date('Ymd') . $ResultID;
                $update_where = [
                    'coupon_id' => $ResultID,
                ];
                $this->shop_coupon_db->where($update_where)->update(['coupon_code' => $coupon_code]);
                $this->success('操作成功', url('Coupon/index'));
            } else {
                $this->error('操作失败');
            }
        }

        // 会员级别
        $users_level = model('UsersLevel')->getList();
        $this->assign('users_level', $users_level);

        // 开始时间
        $start_date = date("Y-m-d H:i:s", strtotime("-0 day"));
        $this->assign('start_date', $start_date);

        // 结束时间
        $end_date = date("Y-m-d H:i:s", strtotime("+15 day"));
        $this->assign('end_date', $end_date);
        return $this->fetch();
    }

    // 优惠券编辑
    public function edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['coupon_id'] = intval($post['coupon_id']);
            // 数据判断处理
            if (empty($post['coupon_name'])) $this->error('请填写优惠券名称');
            $post['coupon_name'] = htmlspecialchars_decode($post['coupon_name']);
            if ('' == $post['conditions_use']) $this->error('请填写优惠规则-使用条件');
            if (empty($post['coupon_price'])) $this->error('请填写优惠规则-减免金额');
            if (empty($post['coupon_stock'])) $this->error('请填写库存');
            if (1 == $post['use_type']) {
                if (empty($post['use_start_time'])) $this->error('请填写优惠券有效开始时间');
                if (empty($post['use_end_time'])) $this->error('请填写优惠券有效结束时间');
            } else if (2 == $post['use_type'] || 3 == $post['use_type']) {
                if (empty($post['valid_days'])) $this->error('请填写优惠券有效天数');
            }
            if (empty($post['start_date'])) $this->error('请选择优惠券发放日期');
            if (empty($post['end_date'])) $this->error('请选择优惠券结束日期');
            if (2 == $post['coupon_type']) {
                if (empty($post['product_id'])) $this->error('请填写指定的商品ID');
            }
            $post['redeem_authority'] = !empty($post['level_id']) ? implode(',', $post['level_id']) : '';
            $post['start_date']       = strtotime($post['start_date']);
            $post['end_date']         = strtotime($post['end_date']);
            $post['use_start_time']   = strtotime($post['use_start_time']);
            $post['use_end_time']     = strtotime($post['use_end_time']);
            $post['update_time']      = getTime();
            if (1 == $post['coupon_type']) {
                $post['product_id'] = '';
                $post['arctype_id'] = '';
            } else if (2 == $post['coupon_type']) {
                $post['arctype_id'] = '';
            } else if (3 == $post['coupon_type']) {
                $post['product_id'] = '';
            }
            $update_where = [
                'coupon_id' => $post['coupon_id'],
            ];
            // 更新优惠券
            $ResultID = $this->shop_coupon_db->where($update_where)->update($post);
            if (!empty($ResultID)) {
                $this->success('更新成功', url('Coupon/index'));
            } else {
                $this->error('更新失败');
            }
        }
        $shop_coupon_where = [
            'coupon_id' => input('id/d'),
        ];
        // 优惠券数据
        $shop_coupon                     = $this->shop_coupon_db->where($shop_coupon_where)->find();
        $shop_coupon['redeem_authority'] = explode(',', $shop_coupon['redeem_authority']);
        $shop_coupon['product_id_num']   = !empty($shop_coupon['product_id']) ? count(explode(',', $shop_coupon['product_id'])) : 0;
        $shop_coupon['arctype_id_num']   = !empty($shop_coupon['arctype_id']) ? count(explode(',', $shop_coupon['arctype_id'])) : 0;
        $this->assign('info', $shop_coupon);

        // 会员级别
        $users_level = model('UsersLevel')->getList();
        $this->assign('users_level', $users_level);
        return $this->fetch();
    }

    // 优惠券删除
    public function del()
    {
        $coupon_id = input('del_id/a');
        $coupon_id = eyIntval($coupon_id);
        if (IS_AJAX_POST && !empty($coupon_id)) {
            // 删除统一条件
            $Where            = [
                'coupon_id' => ['IN', $coupon_id],
            ];
            $result           = $this->shop_coupon_db->field('coupon_name')->where($Where)->select();
            $coupon_name_list = get_arr_column($result, 'coupon_name');

            $return = $this->shop_coupon_db->where($Where)->update(['is_del'=>1,'update_time'=>getTime()]);
            if ($return) {
                adminLog('删除优惠券：' . implode(',', $coupon_name_list));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    // 选择商品
    public function select_product()
    {
        // 查询条件
        $where['a.channel'] = $this->channeltype;
        $where['a.status']  = 1;
        $where['a.is_del']  = 0;
        $where['a.lang']    = $this->admin_lang;

        $keywords = input('keywords/s');
        if (!empty($keywords)) $where['a.title'] = ['LIKE', "%{$keywords}%"];

        // 指定商品ID查询
        $product_ids = input('product_ids/s');
        if (!empty($product_ids)) $where['a.aid'] = ['IN', explode(',', $product_ids)];

        // 指定分类查询
        $typeid = input('typeid/d') ? input('typeid/d') : 0;
        if (!empty($typeid)) {
            $hasRow            = model('Arctype')->getHasChildren($typeid);
            $typeids           = get_arr_column($hasRow, 'id');
            $where['a.typeid'] = ['IN', $typeids];
        }

        $count   = $this->archives_db->alias('a')->where($where)->count();
        $pageObj = new Page($count, config('paginate.list_rows'));
        $this->assign('page', $pageObj->show());
        $this->assign('pager', $pageObj);

        $field      = 'a.aid, a.litpic, a.aid as product_id, a.title, a.users_price, b.typename';
        $ResultData = $this->archives_db
            ->alias('a')
            ->field($field)
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($where)
            ->order('a.sort_order asc, a.users_price desc, a.aid desc')
            ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
            ->select();
        $array_new  = get_archives_data($ResultData, 'aid');
        foreach ($ResultData as $key => $value) {
            $ResultData[$key]['arcurl'] = get_arcurl($array_new[$value['aid']]);
            $ResultData[$key]['litpic'] = handle_subdir_pic($value['litpic']);
        }

        $this->assign('list', $ResultData);

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($typeid, [$this->channeltype]);
        $this->assign('arctype_html', $arctype_html);
        $this->assign('typeid', $typeid);
        /*--end*/
        return $this->fetch();
    }

    // 获取指定分类或查询后的所有商品ID
    public function ajax_get_product_id()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 查询条件
            $where['a.channel'] = $this->channeltype;
            $where['a.status']  = 1;
            $where['a.is_del']  = 0;
            $where['a.lang']    = $this->admin_lang;

            $keywords = input('keywords/s');
            if (!empty($keywords)) $where['a.title'] = ['LIKE', "%{$keywords}%"];

            // 指定商品ID查询
            $product_ids = input('product_ids/s');
            if (!empty($product_ids)) $where['a.aid'] = ['IN', explode(',', $product_ids)];

            // 指定分类查询
            $typeid = input('typeid/d') ? input('typeid/d') : 0;
            if (!empty($typeid)) {
                $hasRow            = model('Arctype')->getHasChildren($typeid);
                $typeids           = get_arr_column($hasRow, 'id');
                $where['a.typeid'] = ['IN', $typeids];
            }

            $field      = 'a.aid, a.aid as product_id';
            $ResultData = $this->archives_db
                ->alias('a')
                ->field($field)
                ->where($where)
                ->order('a.sort_order asc, a.users_price desc, a.aid desc')
                ->select();

            $product_ids              = get_arr_column($ResultData, 'product_id');
            $ReturnData['ProductNum'] = count($ResultData);
            $ReturnData['ProductIDS'] = implode(',', $product_ids);
            $this->success('', null, $ReturnData);
        }
    }

    /**
     * 选择分类
     * @return [type] [description]
     */
    public function select_arctype()
    {
        $assign_data = [];
        // 目录列表
        $where = [];
        $where['current_channel'] = $this->channeltype;
        $where['is_del'] = 0; // 回收站功能
        $arctypeLogic = new \app\common\logic\ArctypeLogic;
        $arctype_list = $arctypeLogic->arctype_list(0, 0, false, 0, $where, false);
        $assign_data['arctype_list'] = $arctype_list;

        /*获取所有有子栏目的栏目id*/
        $parent_ids = Db::name('arctype')->where([
                'parent_id' => ['gt', 0],
                'is_del'    => 0,
            ])->group('parent_id')->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->column('parent_id');
        $cookied_treeclicked =  json_decode(cookie('coupontypeids-treeClicked-Arr'));
        empty($cookied_treeclicked) && $cookied_treeclicked = [];
        $all_treeclicked = cookie('coupontypeids-treeClicked_All');
        empty($all_treeclicked) && $all_treeclicked = [];
        $tree = [
            'has_children'=>!empty($parent_ids) ? 1 : 0,
            'parent_ids'=>json_encode($parent_ids),
            'all_treeclicked'=>$all_treeclicked,
            'cookied_treeclicked'=>$cookied_treeclicked,
            'cookied_treeclicked_arr'=>json_encode($cookied_treeclicked),
        ];
        $assign_data['tree'] = $tree;
        /* end */

        // 分类
        $typeids = input('param.typeids/s');
        $typeids = trim($typeids, ',');
        $typeids_arr = explode(',', $typeids);
        $assign_data['typeids'] = $typeids;
        $assign_data['typeids_arr'] = $typeids_arr;

        $this->assign($assign_data);

        return $this->fetch();
    }
}