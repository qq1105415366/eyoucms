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
 * 商品规格值ID，价格，库存表
 */
class ProductSpecValue extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->admin_lang = get_admin_lang();
    }

    public function ProducSpecValueEditSave($post = [], $action = 'edit')
    {
        if (!empty($post['aid']) && !empty($post['spec_price']) && !empty($post['spec_stock'])) {
            // 商品规格价格及规格库存
            $time = getTime();
            $saveAll = [];
            $specStock = 0;
            foreach ($post['spec_price'] as $kkk => $vvv) {
                $saveAll[] = [
                    'aid'           => $post['aid'],
                    'spec_value_id' => $kkk,
                    'spec_price'    => !empty($vvv['users_price']) ? $vvv['users_price'] : 0,
                    'spec_stock'    => !empty($post['spec_stock'][$kkk]['stock_count']) ? $post['spec_stock'][$kkk]['stock_count'] : 0,
                    'spec_crossed_price' => !empty($post['spec_crossed_price'][$kkk]['crossed_price']) ? $post['spec_crossed_price'][$kkk]['crossed_price'] : 0,
                    'spec_sales_num'=> !empty($post['spec_sales'][$kkk]['spec_sales_num']) ? $post['spec_sales'][$kkk]['spec_sales_num'] : 0,
                    'stock_code'    => !empty($post['preset_sales'][$kkk]['stock_code']) ? $post['preset_sales'][$kkk]['stock_code'] : '',
                    'seckill_price' => !empty($post['seckill_price'][$kkk]['spec_seckill_price']) ? $post['seckill_price'][$kkk]['spec_seckill_price'] : 0,
                    'seckill_stock' => !empty($post['seckill_stock'][$kkk]['spec_seckill_stock']) ? $post['seckill_stock'][$kkk]['spec_seckill_stock'] : 0,
                    'is_seckill'    => !empty($post['seckill_stock'][$kkk]['spec_seckill_stock']) ? 1 : 0,
                    'discount_price' => !empty($post['discount_price'][$kkk]['spec_discount_price']) ? $post['discount_price'][$kkk]['spec_discount_price'] : 0,
                    'discount_stock' => !empty($post['discount_stock'][$kkk]['spec_discount_stock']) ? $post['discount_stock'][$kkk]['spec_discount_stock'] : 0,
                    'is_discount'    => !empty($post['discount_stock'][$kkk]['spec_discount_stock']) ? 1 : 0,
                    'lang'          => $this->admin_lang,
                    'add_time'      => $time,
                    'update_time'   => $time,
                ];
                // 总库存
                $specStock += !empty($post['spec_stock'][$kkk]['stock_count']) ? intval($post['spec_stock'][$kkk]['stock_count']) : 0;
            }
            if (!empty($saveAll)) {
                if ('edit' === strval($action)) {
                    // 删除当前商品下的所有规格价格库存数据
                    $where = [
                        'aid' => $post['aid'],
                        'lang' => $this->admin_lang,
                    ];
                    $this->where($where)->delete(true);
                    Db::name('product_spec_value_handle')->delete(true);
                }

                // 批量新增商品规格价格数据
                $this->saveAll($saveAll);

                // 将规格库存汇总存入主表
                if (isset($specStock)) Db::name('archives')->where(['aid' => $post['aid']])->update(['stock_count' => intval($specStock), 'update_time' => $time]);
            }
        }
    }
}