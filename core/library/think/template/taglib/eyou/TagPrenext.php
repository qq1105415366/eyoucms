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
use think\Request;

/**
 * 内容页上下篇
 */
class TagPrenext extends Base
{
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取内容页上下篇
     * @author wengxianhu by 2018-4-20
     */
    public function getPrenext($get = 'pre')
    {
        $aid = $this->aid;
        if (empty($aid)) {
            echo '标签prenext报错：只能用在内容页。';
            return false;
        }

        $channelRes = model('Channeltype')->getInfoByAid($aid);
        $channel = $channelRes['channel'];
        $typeid = $channelRes['typeid'];
        $controller_name = $channelRes['ctl_name'];

        $condition = [];
        // 多城市分站与全国的显示逻辑
        $this->site_show_archives($condition, 'archives');

        if ($get == 'next') {
            /* 下一篇 */
            $condition['a.typeid'] = $typeid;
            $condition['a.aid'] = ['GT', $aid];
            $condition['a.channel'] = $channel;
            $condition['a.status'] = 1;
            $condition['a.lang'] = self::$home_lang;
            $condition['a.is_del'] = 0;
            $condition['a.arcrank'] = ['EGT', 0];
            $result = M('archives')->field('b.*, a.*')
                ->alias('a')
                ->join('__ARCTYPE__ b', 'b.id = a.typeid', 'LEFT')
                ->where($condition)
                ->order('a.aid asc')
                ->find();
            if (!empty($result)) {
                if (!empty($result['is_jump']) && 1 == $result['is_jump']) {
                    $result['arcurl'] = $result['jumplinks'];
                } else {
                    $result['arcurl'] = arcurl('home/'.$controller_name.'/view', $result);
                }
                /*封面图*/
                if (empty($result['litpic'])) {
                    $result['is_litpic'] = 0; // 无封面图
                } else {
                    $result['is_litpic'] = 1; // 有封面图
                }
                $result['litpic'] = get_default_pic($result['litpic']); // 默认封面图
                /*--end*/
            }
        } else {
            /* 上一篇 */ 
            $condition['a.typeid'] = $typeid;
            $condition['a.aid'] = ['LT', $aid];
            $condition['a.channel'] = $channel;
            $condition['a.status'] = 1;
            $condition['a.lang'] = self::$home_lang;
            $condition['a.is_del'] = 0;
            $condition['a.arcrank'] = ['EGT', 0];
            $result = M('archives')->field('b.*, a.*')
                ->alias('a')
                ->join('__ARCTYPE__ b', 'b.id = a.typeid', 'LEFT')
                ->where($condition)
                ->order('a.aid desc')
                ->find();
            if (!empty($result)) {
                if (!empty($result['is_jump']) && 1 == $result['is_jump']) {
                    $result['arcurl'] = $result['jumplinks'];
                } else {
                    $result['arcurl'] = arcurl('home/'.$controller_name.'/view', $result);
                }
                /*封面图*/
                if (empty($result['litpic'])) {
                    $result['is_litpic'] = 0; // 无封面图
                } else {
                    $result['is_litpic'] = 1; // 有封面图
                }
                $result['litpic'] = get_default_pic($result['litpic']); // 默认封面图
                /*--end*/
            }
        }

        return $result;
    }
}