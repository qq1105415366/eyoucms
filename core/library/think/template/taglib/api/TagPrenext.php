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

namespace think\template\taglib\api;

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
    public function getPrenext($aid = '', $typeid = '', $get = 'pre', $titlelen = 100)
    {
        !empty($aid) && $this->aid = $aid;
        if (empty($this->aid)) return false;
        
        !empty($typeid) && $this->tid = $typeid;
        if (empty($this->tid)) {
            $this->tid = Db::name('archives')->where('aid', $this->aid)->value('typeid');
        }

        $result = [];
        if ($get == 'pre' || $get == 'all') { // 上一篇
            $detail = Db::name('archives')->field('a.aid, a.typeid, a.title, a.litpic, a.add_time, a.channel')
                ->alias('a')
                ->where([
                    'a.typeid'  => $this->tid,
                    'a.aid'     => ['LT', $this->aid],
                    'a.arcrank' => ['EGT', 0],
                    'a.status'  => 1,
                    'a.is_del'  => 0,
                    'a.lang'    => self::$home_lang,
                ])
                ->order('a.aid desc')
                ->find();
               
            if (!empty($detail)) {
                $detail['title'] = text_msubstr($detail['title'], 0, $titlelen, false);
                $detail['litpic'] = $this->get_default_pic($detail['litpic']);
                $detail['add_time_format'] =  $this->time_format($detail['add_time']);
                $result['preDetail'] = $detail;
            }
        }
        if ($get == 'next' || $get == 'all') { // 下一篇
            $detail = Db::name('archives')->field('a.aid, a.typeid, a.title, a.litpic,a.add_time, a.click, a.channel')
                ->alias('a')
                ->where([
                    'a.typeid'  => $this->tid,
                    'a.aid'     => ['GT', $this->aid],
                    'a.arcrank' => ['EGT', 0],
                    'a.status'  => 1,
                    'a.is_del'  => 0,
                    'a.lang'    => self::$home_lang,
                ])
                ->order('a.aid asc')
                ->find();
            if (!empty($detail)) {
                $detail['title'] = text_msubstr($detail['title'], 0, $titlelen, false);
                $detail['litpic'] = $this->get_default_pic($detail['litpic']);
                $detail['add_time_format'] =  $this->time_format($detail['add_time']);;
                // $detail['typeurl'] = '/pages/article/list?typeid='.$detail['typeid'];
                $result['nextDetail'] = $detail;
            }
        }

        return !empty($result) ? $result : false;
    }
}