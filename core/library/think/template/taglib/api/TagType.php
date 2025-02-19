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
use app\home\logic\FieldLogic;

/**
 * 栏目基本信息
 */
class TagType extends Base
{
    public $fieldLogic;
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->fieldLogic = new FieldLogic();
        if ($this->aid > 0) { // 应用于文档详情页
            $this->tid = Db::name('archives')->where('aid', $this->aid)->cache(true,EYOUCMS_CACHE_TIME,"archives")->getField('typeid');
        }
    }

    /**
     * 获取栏目基本信息
     * @author wengxianhu by 2018-4-20
     */
    public function getType($typeid = '', $type = 'self', $addfields = '', $infolen = '',$suffix = true)
    {
        $typeid = !empty($typeid) ? $typeid : $this->tid;
        if (empty($typeid)) {
            $redata = [
                'data'  => false,
            ];
            return $redata;
        }

        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        $redata = cache($cacheKey);
        if (!empty($redata['data'])) { // 启用缓存
            return $redata;
        }

        switch ($type) {
            case 'top':
                $result = $this->getTop($typeid);
                break;
            
            default:
                $result = $this->getSelf($typeid, $addfields);
                break;
        }
        isset($result['typename']) && $result['typename'] = htmlspecialchars_decode($result['typename']);
        isset($result['litpic']) && $result['litpic'] = $this->get_default_pic($result['litpic']);
        isset($result['seo_title']) && $result['seo_title'] = $this->set_arcseotitle($result['typename'], $result['seo_title']);
        if (!empty($infolen) && !empty($result['seo_description'])) {
            $result['seo_description'] = text_msubstr($result['seo_description'], 0, $infolen, $suffix);
        }
        $result = $this->fieldLogic->getTableFieldList($result, config('global.arctype_channel_id'));
        /*当前单页栏目的内容信息*/
        $result['current_typeid'] =  $result['typeid'];
        if (!empty($addfields) && $result['current_channel'] == 6) {
            $addfields = str_replace('，', ',', $addfields); // 替换中文逗号
            $addfields_arr = explode(",",$addfields);
            if(in_array('content',$addfields_arr) && !in_array('content_ey_m',$addfields_arr)){
                $addfields .= ",content_ey_m";
            }
            $addfields = trim($addfields, ',');
            $rowExt = Db::name('single_content')->field($addfields)->where('typeid', $result['id'])->find();
            $rowExt = $this->fieldLogic->getChannelFieldList($rowExt, $result['current_channel'], false, true);

            if (isset($rowExt['content']) && isset($rowExt['content_ey_m']) && !empty($rowExt['content_ey_m'])){
                $rowExt['content'] = $rowExt['content_ey_m'];
            }else if(isset($rowExt['content']) && isset($rowExt['content_ey_m']) && empty($rowExt['content']) && empty($rowExt['content_ey_m'])){   //当前栏目内容为空，从下级栏目读取
                $type_arr = Db::name("arctype")->where(['parent_id' => $result['typeid'],'is_del'=>0])->getField('id',true);
                $rowNext = Db::name('single_content')->field('typeid,content,content_ey_m')->where(['typeid' => ['in',$type_arr],'content|content_ey_m'=>['neq','']])->find();
                if(!empty($rowNext)){
                    $rowNext = $this->fieldLogic->getChannelFieldList($rowNext, $result['current_channel'], false, true);
                    if (!empty($rowNext['content_ey_m'])){
                        $rowExt['content'] = $rowNext['content_ey_m'];
                    }else{
                        $rowExt['content'] = $rowNext['content'];
                    }
                    $rowExt['current_typeid'] =  $rowNext['typeid'];
                }
            }
            //限制content的长度  html_msubstr
            if(!empty($rowExt['content']) && !empty($infolen)){
                $rowExt['content'] = html_msubstr($rowExt['content'],0,$infolen,$suffix);
            }
            is_array($rowExt) && $result = array_merge($result, $rowExt);
        }
        /*--end*/

        $redata = [
            'data'  => !empty($result) ? $result : false,
        ];
        cache($cacheKey, $redata, null, 'arctype');

        return $redata;
    }

    /**
     * 获取当前栏目基本信息
     * @author wengxianhu by 2018-4-20
     */
    public function getSelf($typeid, $addfields = '')
    {
        $result = Db::name('arctype')->field('*')
            ->where(['id'=>$typeid])
            ->find();
        if (!empty($result)) {
            $result['typename'] = htmlspecialchars_decode($result['typename']);
        }

        return $result;
    }

    /**
     * 获取当前栏目的第一级栏目基本信息
     * @author wengxianhu by 2018-4-20
     */
    public function getTop($typeid)
    {
        $parent_list = model('Arctype')->getAllPid($typeid); // 获取当前栏目的所有父级栏目
        $result = current($parent_list); // 第一级栏目
        if (!empty($result)) {
            $result['typename'] = htmlspecialchars_decode($result['typename']);
        }

        return $result;
    }
}