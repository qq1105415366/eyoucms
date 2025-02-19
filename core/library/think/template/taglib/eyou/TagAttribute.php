<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace think\template\taglib\eyou;

use think\Db;

/**
 * 产品参数
 */
class TagAttribute extends Base
{
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取每篇文章的属性
     * @author wengxianhu by 2018-4-20
     */
    public function getAttribute($aid = '', $type = '', $attrid = '')
    {
        $aid = !empty($aid) ? $aid : $this->aid;
        if (empty($aid)) {
            echo '标签attribute报错：缺少属性 aid 值。';
            return false;
        }
        $result = false;
        if ('newattr' == $type) {
	
            // 查询商品信息
            $archives = Db::name('archives')->field('merchant_id, attrlist_id')->where(['aid'=>$aid])->find();
            if (!empty($archives['merchant_id'])) {
                // 多商家商品则查询自定义参数
                $where = [
                    'aid' => $aid,
                ];
                $order = 'sort_order asc, param_id asc';
                $field = 'param_name as name, param_value as value';
                $result = M('product_custom_param')->field($field)->where($where)->order($order)->select();
            } else {
                // 新版参数
                $attrid = !empty($attrid) ? $attrid : $archives['attrlist_id'];
                if (!empty($attrid)) {
                    $where = [
                        'b.aid' => $aid,
                        'a.list_id' => ['IN', [0, $attrid]],
                        'a.status' => 1,
                    ];
                }else{
                    $where = [
                        'b.aid' => $aid,
                        'a.list_id' => 0,
                        'a.status' => 1,
                    ];
                }
                $result = M('shop_product_attribute')
                    ->alias('a')
                    ->field('a.attr_name as name, b.attr_value as value')
                    ->join('__SHOP_PRODUCT_ATTR__ b', 'a.attr_id = b.attr_id', 'LEFT')
                    ->where($where)
                    ->order('b.sort_order asc, a.attr_id asc')
                    ->select();
            }
        } else {
            // 旧版参数
            /*当前栏目下的属性*/
            $row = M('product_attr')->alias('a')
                ->field('a.attr_value,b.attr_id,b.attr_name')
                ->join('__PRODUCT_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
                ->where([
                    'a.aid'     => $aid,
                    'b.lang'    => self::$home_lang,
                    'b.is_del'  => 0,
                ])
                ->order('b.sort_order asc, a.attr_id asc')
                ->select();
            /*--end*/
            if (empty($row)) {
                return $result;
            } else {
                /*获取多语言关联绑定的值*/
                $row = model('LanguageAttr')->getBindValue($row, 'product_attribute', self::$main_lang); // 多语言
                /*--end*/

                if ('default' == $type) {
                    $newAttribute = array();
                    foreach ($row as $key => $val) {
                        $attr_id = $val['attr_id'];
                        /*字段名称*/
                        $name = 'value_'.$attr_id;
                        $newAttribute[$name] = $val['attr_value'];
                        /*--end*/
                        /*表单提示文字*/
                        $itemname = 'name_'.$attr_id;
                        $newAttribute[$itemname] = $val['attr_name'];
                        /*--end*/
                    }
                    $result[0] = $newAttribute;
                } else if ('auto' == $type) {
                    foreach ($row as $key => $val) {
                        $row[$key] = [
                            'name'  => $val['attr_name'],
                            'value'  => $val['attr_value'],
                        ];
                    }
                    $result = $row;
                }
            }
        }
        return $result;
    }
}