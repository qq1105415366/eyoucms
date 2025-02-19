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

namespace app\admin\model;

use think\Db;
use think\Model;

/**
 * 留言列表
 */
class Guestbook extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /**
     * 删除的后置操作方法
     * 自定义的一个函数 用于数据删除后做的相应处理操作, 使用时手动调用
     * @param int $aid
     */
    public function afterDel($aidArr = array(), $form_type = 0)
    {
        if (is_string($aidArr)) {
            $aidArr = explode(',', $aidArr);
        }

        // 同时删除属性内容
        Db::name('guestbook_attr')->where([
                'aid'   => ['IN', $aidArr],
                'form_type' => $form_type,
            ])->delete();
    }
}