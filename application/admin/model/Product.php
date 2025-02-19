<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\model;

use think\Db;
use think\Model;
use app\admin\logic\ProductLogic;

/**
 * 产品
 */
class Product extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /**
     * 后置操作方法
     * 自定义的一个函数 用于数据保存后做的相应处理操作, 使用时手动调用
     * @param int $aid 产品id
     * @param array $post post数据
     * @param string $opt 操作
     */
    public function afterSave($aid, $post, $opt, $new = '')
    {
        // -----------内容
        $post['aid'] = $aid;
        $addonFieldExt = !empty($post['addonFieldExt']) ? $post['addonFieldExt'] : array();
        $FieldModel = new \app\admin\model\Field;
        $FieldModel->dealChannelPostData($post['channel'], $post, $addonFieldExt);
        
        // 处理外贸链接
        if (is_dir('./weapp/Waimao/')) {
            $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
            $waimaoLogic->update_htmlfilename($aid, $post, $opt);
        } else {
            $foreignLogic = new \app\admin\logic\ForeignLogic;
            $foreignLogic->update_htmlfilename($aid, $post, $opt);
        }

        // ---------产品多图
        model('ProductImg')->saveimg($aid, $post);
        // ---------end

        // 处理产品 属性
        $productLogic = new ProductLogic();
        if (empty($new)) {   // && !empty($post['attrlist_id'])
            // 旧参数处理
            $productLogic->saveProductAttr($aid, $post['typeid'], $post);
        } else {
            // 新参数处理
            $productLogic->saveShopProductAttr($aid, $post['typeid'], $post);
        }
        
        // --处理TAG标签
        model('Taglist')->savetags($aid, $post['typeid'], $post['tags'],$post['arcrank'], $opt);

        if ('edit' == $opt) {
            // 清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
        } else {
            // 处理mysql缓存表数据
            if (isset($post['arcrank']) && -1 == $post['arcrank'] /*&& -1 == $post['old_arcrank']*/ && !empty($post['users_id'])) {
                // 待审核
                model('SqlCacheTable')->UpdateDraftSqlCacheTable($post, $opt);
            } else if (isset($post['arcrank'])) {
                // 已审核
                $post['old_typeid'] = intval($post['attr']['typeid']);
                model('SqlCacheTable')->UpdateSqlCacheTable($post, $opt, 'product');
            }
        }
        model('Arctype')->hand_type_count(['aid'=>[$aid]]);//统计栏目文档数量
    }

    /**
     * 获取单条记录
     * @author wengxianhu by 2017-7-26
     */
    public function getInfo($aid, $field = '', $isshowbody = true)
    {
        $result = array();
        $field = !empty($field) ? $field : '*';
        $result = Db::name('archives')->field($field)
            ->where([
                'aid'   => $aid,
                'lang'  => get_admin_lang(),
            ])
            ->find();
        if ($isshowbody) {
            $tableName = Db::name('channeltype')->where('id','eq',$result['channel'])->getField('table');
            $result['addonFieldExt'] = Db::name($tableName.'_content')->where('aid',$aid)->find();
        }

        // 产品TAG标签
        if (!empty($result)) {
            $typeid = isset($result['typeid']) ? $result['typeid'] : 0;
            $tags = model('Taglist')->getListByAid($aid, $typeid);
            $result['tags'] = $tags['tag_arr'];
            $result['tag_id'] = $tags['tid_arr'];
        }

        return $result;
    }

    /**
     * 删除的后置操作方法
     * 自定义的一个函数 用于数据删除后做的相应处理操作, 使用时手动调用
     * @param int $aid
     */
    public function afterDel($aidArr = array())
    {
        if (is_string($aidArr)) {
            $aidArr = explode(',', $aidArr);
        }

        // 删除条件
        $where = [
            'aid' => ['IN', $aidArr]
        ];
        // 同时删除内容
        Db::name('product_content')->where($where)->delete();
        // 同时删除属性
        Db::name('product_attr')->where($where)->delete();
        // 同时删除产品属性
        Db::name('shop_product_attr')->where($where)->delete();
        // 同时删除图片
        $result = Db::name('product_img')->field('image_url')->where($where)->select();
        if (!empty($result)) {
           //  20210603大黄注释 删掉文档不删掉服务器的图片,否则复制的文档图片无法显示
           // foreach ($result as $key => $val) {
           //     $image_url = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $val['image_url']);
           //     if (!is_http_url($image_url) && file_exists('.'.$image_url) && preg_match('#^(/uploads/|/public/upload/)(.*)/([^/]+)\.([a-z]+)$#i', $image_url)) {
           //         @unlink(realpath('.'.$image_url));
           //     }
           // }
            Db::name('product_img')->where($where)->delete();
        }
        // 同时删除虚拟商品
        Db::name("product_netdisk")->where($where)->delete();
        // 产品规格数据表
        Db::name("product_spec_data")->where($where)->delete();
        // 产品多规格组装表
        Db::name("product_spec_value")->where($where)->delete();
        // 产品自定义参数表
        // Db::name("product_custom_param")->where($where)->delete();
        // 产品规格数据处理表
        Db::name("product_spec_data_handle")->where($where)->delete();
        // 同时删除商品秒杀数据
        Db::name("sharp_goods")->where($where)->delete();
        
        // 删除条件
        $where = [
            'product_id' => ['IN', $aidArr]
        ];
        // 同时删除商品评论数据
        Db::name("shop_order_comment")->where($where)->delete();
        // 同时删除购物车数据
        Db::name("shop_cart")->where($where)->delete();
        // 同时删除商品优惠券数据
        // Db::name("shop_coupon")->where($where)->delete();
        // Db::name("shop_coupon_use")->where($where)->delete();
        
        // 同时删除TAG标签
        model('Taglist')->delByAids($aidArr);
        // 减少统计数
        del_statistics_data(6, $aidArr);
    }
}