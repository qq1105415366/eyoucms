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

namespace app\plugins\controller;

use think\Db;

class Sample extends Base
{
    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
    }

    /**
     * index
     */
    public function index()
    {
        return $this->fetch('sample/'.THEME_STYLE.'/index');
    }
}