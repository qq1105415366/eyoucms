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
 * Date: 2019-7-9
 */
namespace app\admin\model;

use think\Model;
use think\Config;
use think\Db;

/**
 * 商品规格价格预处理模型
 */
class ProductSpecValueHandle extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    public function editProductSpecPrice($post = [])
    {
        $aid = !empty($post['aid']) ? $post['aid'] : session('handleAID');
        if (!empty($aid) && !empty($post['spec_price']) && !empty($post['spec_stock'])) {
            // 产品规格价格及规格库存
            $time = getTime();
            $saveAll = $insertAll = [];
            $keys = rand(1, 9);
            foreach ($post['spec_price'] as $kkk => $vvv) {
                if (!empty($post['handle_ids'][$kkk]['handle_id'])) {
                    $saveAll[] = [
                        'handle_id'      => intval($post['handle_ids'][$kkk]['handle_id']),
                        'aid'           => $aid,
                        'spec_value_id' => $kkk,
                        'spec_price'    => !empty($vvv['users_price']) ? $vvv['users_price'] : 0,
                        'spec_crossed_price' => !empty($post['spec_crossed_price'][$kkk]['crossed_price']) ? $post['spec_crossed_price'][$kkk]['crossed_price'] : 0,
                        'spec_stock'    => !empty($post['spec_stock'][$kkk]['stock_count']) ? $post['spec_stock'][$kkk]['stock_count'] : 0,
                        'spec_sales_num'=> !empty($post['spec_sales'][$kkk]['spec_sales_num']) ? $post['spec_sales'][$kkk]['spec_sales_num'] : 0,
                        'update_time'   => $time,
                    ];
                } else if (!empty($vvv['users_price'])) {
                    $keys++;
                    $insertAll[] = [
                        'value_id'      => date('His') . rand(10, 99) . $keys,
                        'aid'           => $aid,
                        'spec_value_id' => $kkk,
                        'spec_price'    => !empty($vvv['users_price']) ? $vvv['users_price'] : 0,
                        'spec_crossed_price' => !empty($post['spec_crossed_price'][$kkk]['crossed_price']) ? $post['spec_crossed_price'][$kkk]['crossed_price'] : 0,
                        'spec_stock'    => !empty($post['spec_stock'][$kkk]['stock_count']) ? $post['spec_stock'][$kkk]['stock_count'] : 0,
                        'spec_sales_num'=> !empty($post['spec_sales'][$kkk]['spec_sales_num']) ? $post['spec_sales'][$kkk]['spec_sales_num'] : 0,
                        'add_time'      => $time,
                        'update_time'   => $time,
                    ];
                }
            }
            if (!empty($saveAll)) $this->saveAll($saveAll);
            if (!empty($insertAll)) Db::name('product_spec_value_handle')->insertAll($insertAll);

            // 查询商品的规格信息
            $where = [
                'aid' => $aid,
                'spec_is_select' => 1,
            ];
            $field = 'spec_mark_id, spec_value_id';
            $order = 'spec_value_id asc, spec_id asc';
            $data = Db::name('product_spec_data_handle')->field($field)->where($where)->order($order)->select();
            // 处理规格数组
            $resultArray = [];
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $resultArray[$value['spec_mark_id']][] = $value['spec_value_id'];
                }
            }
            return [
                'aid' => $aid,
                'resultArray' => $resultArray,
            ];
        }

        return false;
    }
}