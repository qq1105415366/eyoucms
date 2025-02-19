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
use think\Cache;

/**
 * 栏目列表
 */
class TagChannel extends Base
{
    public $currentstyle = '';
    public $channelid = '';

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取指定级别的栏目列表
     * @param string type son表示下一级栏目,self表示同级栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getChannel($typeid = '', $type = 'top', $currentstyle = '', $showalltext = 'off', $channelid = '', $notypeid = '')
    {
        $this->currentstyle = $currentstyle;
        $this->channelid = $channelid;
        $typeid = intval($typeid);
        if (empty($typeid)) {
            if ($this->aid > 0) { // 应用于文档列表
                $this->tid = Db::name('archives')->where('aid', $this->aid)->cache(true,EYOUCMS_CACHE_TIME,"archives")->getField('typeid');
            }
            $typeid = $this->tid;
//            if (!empty($channelid) && empty($typeid)) {
//                $type = 'channel';
//            }
        }
        $result = $this->getSwitchType($typeid, $type, $showalltext, $channelid, $notypeid);

        return $result;
    }

    /**
     * 获取指定级别的栏目列表
     * @param string type son表示下一级栏目,self表示同级栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getSwitchType($typeid = '', $type = 'top', $showalltext = 'off', $channelid = '', $notypeid = '')
    {
        $result = array();
        switch ($type) {
            case 'son': // 下级栏目
                $result = $this->getSon($typeid, false, $showalltext);
                break;

            case 'self': // 同级栏目
                $result = $this->getSelf($typeid, $showalltext);
                break;

            case 'top': // 顶级栏目
                $result = $this->getTop($notypeid);
                break;

            case 'sonself': // 下级、同级栏目
                $result = $this->getSon($typeid, true, $showalltext);
                break;

            case 'first': // 第一级栏目
                $result = $this->getFirst($typeid);
                break;

            case 'tree': // 树形结构
            case 'tree10': // 树形结构
                if ('tree10' == $type) {
                    $showAllTxt = 'notSub'; // 无子栏目时显示“全部”字样
                    $parent_list = model('Arctype')->getAllPid($typeid);
                    $parent_list = current($parent_list); // 第一级栏目
                    $typeid_new = $parent_list['id'];
                } else {
                    $showAllTxt = 'all'; // 所有子层级栏目都显示“全部”字样
                    $typeid_new = $typeid;
                }
                $result = $this->getTree($typeid_new, $showAllTxt);
                if (empty($result)) {
                    $parent_id = Db::name('arctype')->where('id', $typeid_new)->cache(true,EYOUCMS_CACHE_TIME,"arctype")->getField('parent_id');
                    $result = $this->getTree($parent_id, $showAllTxt);
                }
                $result = reform_keys($result); // 重置数组索引，兼容多维数组
                return [
                    'data'=> !empty($result) ? $result : false,
                ];
                break;
            case 'channel': // 该模型的全部顶级栏目以及子栏目
                $result = $this->getChannelTop($channelid, $showalltext, $notypeid);
                break;
        }

        /*处理自定义表字段的值*/
        // if (!empty($result)) {
        //     /*获取自定义表字段信息*/
        //     $map = array(
        //         'channel_id'    => config('global.arctype_channel_id'),
        //     );
        //     $fieldInfo = model('Channelfield')->getListByWhere($map, '*', 'name');
        //     /*--end*/
        //     $fieldLogic = new \app\home\logic\FieldLogic;
        //     foreach ($result as $key => $val) {
        //         if (!empty($val)) {
        //             $val = $fieldLogic->handleAddonFieldList($val, $fieldInfo);
        //             $result[$key] = $val;
        //         }
        //     }
        // }
        /*--end*/

        return [
            'data'=> !empty($result) ? $result : false,
        ];
    }

    /**
     * 获取树状结构分类
     * @param  string  $typeid  栏目ID
     * @param  string $showAllTxt 显示“全部”字样
     * @return [type]           [description]
     */
    public function getTree($typeid = '', $showAllTxt = 'off')
    {
        $treeData = $this->getALL('tree', $showAllTxt);
        if (empty($typeid)) {
            $result = $treeData;
        } else {
            $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
            $result = Cache::get($cacheKey);
            if (empty($result)) {
                if (isset($treeData[$typeid])) {
                    /*栏目显示“最新”*/
                    if ('all' == $showAllTxt) {
                        $allchildren = $treeData[$typeid];
                        $allchildren['typename'] = '最新';
                        $allchildren['children'] = [];
                        $children = $treeData[$typeid]['children'];
                        $children = array_merge([$allchildren], $children);
                        $treeData[$typeid]['children'] = $children;
                    }
                    /*end*/
                    $result = [$treeData[$typeid]];
                } else {
                    $result = [];
                    $arctypeInfo = Db::name('arctype')->field('id,parent_id,grade')->find($typeid);
                    if (1 == $arctypeInfo['grade'] && !empty($treeData[$arctypeInfo['parent_id']]['children'])) {
                        foreach ($treeData[$arctypeInfo['parent_id']]['children'] as $key => $val) {
                            if ($typeid == $val['id']) {
                                /*栏目显示“最新”*/
                                if ('all' == $showAllTxt) {
                                    $allchildren = $val;
                                    $allchildren['typename'] = '最新';
                                    $allchildren['children'] = [];
                                    $children = !empty($val['children']) ? $val['children'] : [];
                                    $children = array_merge([$allchildren], $children);
                                    $val['children'] = $children;
                                }
                                /*end*/
                                $result[] = $val;
                            }
                        }
                    }
                }
                Cache::tag('arctype')->set($cacheKey, $result);
            }
        }

        return $result;
    }

    /**
     * 所有分类
     * @param  string $showAllTxt 显示“全部”字样
     * @return mixed
     */
    public function getALL($type = '', $showAllTxt = 'off')
    {
        $cacheKey = 'api-'.md5(__CLASS__.__FUNCTION__.json_encode(func_get_args()));
        $returnData = Cache::get($cacheKey);
        if (empty($returnData)) {
            $data = Db::name('arctype')->field('id,current_channel,parent_id,typename,grade,litpic,seo_title,seo_keywords,seo_description,sort_order, 0 as sub_level')->where([
                'is_part' => 0,
                'is_hidden'   => 0,
                'status'  => 1,
                'is_del'  => 0, // 回收站功能
                'lang'  => self::$home_lang,
                'current_channel' => ['not in',[4,5,51]],
            ])->order(['sort_order' => 'asc', 'id' => 'asc'])->select();
            $all = !empty($data) ? $data : [];
            $tree = [];
            foreach ($all as $_k => $first) {

                // 过滤插件产生的栏目
                if (!empty($first['weapp_code'])) continue;
                
                $all[$_k]['typename'] = $first['typename'] = htmlspecialchars_decode($first['typename']);
                $all[$_k]['litpic'] = $first['litpic'] = $this->get_default_pic($first['litpic']);
                if ($first['parent_id'] != 0) continue;
                $twoTree = [];
                foreach ($all as $_k2 => $two) {
                    $all[$_k2]['typename'] = $two['typename'] = htmlspecialchars_decode($two['typename']);
                    $all[$_k2]['litpic'] = $two['litpic'] = $this->get_default_pic($two['litpic']);
                    if ($two['parent_id'] != $first['id']) continue;
                    $threeTree = [];
                    foreach ($all as $_k3 => $three) {
                        $all[$_k3]['typename'] = $three['typename'] = htmlspecialchars_decode($three['typename']);
                        $all[$_k3]['litpic'] = $three['litpic'] = $this->get_default_pic($three['litpic']);
                        $three['parent_id'] == $two['id'] && $threeTree[$three['id']] = $three;
                    }
                    if (!empty($threeTree)) {
                        /*没有子栏目时，同级栏目是否有“全部”字样*/
                        $currentData = current($threeTree);
                        $two['sub_level'] = intval($currentData['sub_level']) + 1;
                        if ('notSub' == $showAllTxt) {
                            $tmp1 = $two;
                            $tmp1['typename'] = '全部';
                            $tmp1['sub_level'] = 0;
                            $threeTree = array_merge([$tmp1], $threeTree);
                        }
                        /*end*/
                        $two['children'] = $threeTree;
                    }
                    $twoTree[$two['id']] = $two;
                }
                if (!empty($twoTree)) {
                    array_multisort(get_arr_column($twoTree, 'sort_order'), SORT_ASC, $twoTree);
                    /*没有子栏目时，同级栏目是否有“全部”字样*/
                    $currentData = current($twoTree);
                    $first['sub_level'] = intval($currentData['sub_level']) + 1;
                    if ('notSub' == $showAllTxt) {
                        $tmp2 = $first;
                        $tmp2['typename'] = '全部';
                        $tmp2['sub_level'] = 0;
                        $twoTree = array_merge([$tmp2], $twoTree);
                    }
                    /*end*/
                    $first['children'] = $twoTree;
                }
                $tree[$first['id']] = $first;
            }
            $returnData = [
                'all'   => $all,
                'tree'   => $tree,
            ];
            Cache::tag('arctype')->set($cacheKey, $returnData);
        }

        if (!empty($type)) {
            isset($returnData[$type]) && $returnData = $returnData[$type];
        }

        return $returnData;
    }

    /**
     * 获取下一级栏目
     * @param string $self true表示没有子栏目时，获取同级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getSon($typeid, $self = false, $showalltext = 'off')
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        $arctype_max_level = intval(config('global.arctype_max_level')); // 栏目最多级别
        /*获取所有显示且有效的栏目列表*/
        $map = array(
            'c.is_part' => 0,
            'c.is_hidden'   => 0,
            'c.status'  => 1,
            'c.is_del'    => 0, // 回收站功能
            'c.lang'    => self::$home_lang,
            'c.weapp_code'    => '',
        );
        $map['c.current_channel'] = ['not in',[4,5,51]];
        $fields = "c.id,c.id as typeid,c.parent_id,c.typename,c.litpic,c.current_channel,count(s.id) as has_children, '' as children";
        $res = Db::name('arctype')
            ->field($fields)
            ->alias('c')
            ->join('__ARCTYPE__ s','s.parent_id = c.id','LEFT')
            ->where($map)
            ->group('c.id')
            ->order('c.parent_id asc, c.sort_order asc, c.id')
            ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
            ->select();
        /*--end*/
        if ($res) {
            $arctypelist = [];
            foreach ($res as $key => $val) {
                $val['typename'] = htmlspecialchars_decode($val['typename']);
                /*标记栏目被选中效果*/
                if ($val['id'] == $typeid || $val['id'] == $this->tid) {
                    $val['currentstyle'] = $this->currentstyle;
                } else {
                    $val['currentstyle'] = '';
                }
                /*--end*/

                $val['litpic'] = $this->get_default_pic($val['litpic']); // 封面图

                $res[$key] = $val;
                $arctypelist[$val['id']] = $val;
            }
        }

        /*栏目层级归类成阶梯式*/
        $arr = group_same_key($res, 'parent_id');
        for ($i=0; $i < $arctype_max_level; $i++) {
            foreach ($arr as $key => $val) {
                foreach ($arr[$key] as $key2 => $val2) {
                    if (!isset($arr[$val2['id']])) continue;
                    $val2['children'] = $arr[$val2['id']];
                    $arr[$key][$key2] = $val2;
                }
            }
        }
        /*--end*/

        /*取得指定栏目ID对应的阶梯式所有子孙等栏目*/
        $result = array();
        $typeidArr = explode(',', $typeid);
        foreach ($typeidArr as $key => $val) {
            if (!isset($arr[$val])) continue;
            if (is_array($arr[$val])) {
                foreach ($arr[$val] as $key2 => $val2) {
                    array_push($result, $val2);
                }
            } else {
                array_push($result, $arr[$val]);
            }
        }
        /*--end*/

        /*没有子栏目时，获取同级栏目*/
        if (empty($result) && $self == true) {
            $result = $this->getSelf($typeid, $showalltext);
        } else {
            if ($showalltext == 'on') { // 是否显示“全部”文本
                if (!empty($result)) { // type=sonself
                    $arr_1 = current($result);
                    $arr_2 = $arctypelist[$arr_1['parent_id']];
                    if ($arr_1['parent_id'] != $arr_2['parent_id']) {
                        $arr_2['typename'] = '全部';
                        if ($typeid != $this->tid) {
                            $arr_2['currentstyle'] = '';
                        }
                        $result = array_merge([$arr_2], $result);
                    }
                } else { // type=son
                    $arr_2 = $arctypelist[$typeid];
                    $arr_2['typename'] = '全部';
                    $arr_2['currentstyle'] = $this->currentstyle;
                    $result = array_merge([$arr_2], $result);
                }
            }
        }
        /*--end*/

        return $result;
    }

    /**
     * 获取当前栏目的第一级栏目下的子栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getFirst($typeid)
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        $row = model('Arctype')->getAllPid($typeid); // 当前栏目往上一级级父栏目
        if (!empty($row)) {
            reset($row); // 重置排序
            $firstResult = current($row); // 顶级栏目下的第一级父栏目
            $typeid = isset($firstResult['id']) ? $firstResult['id'] : '';
            $sonRow = $this->getSon($typeid, false); // 获取第一级栏目下的子孙栏目，为空时不获得同级栏目
            $result = $sonRow;
        }

        return $result;
    }

    /**
     * 获取同级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getSelf($typeid, $showalltext = 'off')
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        /*获取指定栏目ID的上一级栏目ID列表*/
        $map = array(
            'is_part' => 0,
            'id'   => array('in', $typeid),
            'is_hidden'   => 0,
            'status'  => 1,
            'is_del'    => 0, // 回收站功能
            'lang'  => self::$home_lang,
        );
        $map['current_channel'] = ['not in',[4,5,51]];

        $res = Db::name('arctype')->field('parent_id')
            ->where($map)
            ->group('parent_id')
            ->select();
        /*--end*/

        /*获取上一级栏目ID对应下的子孙栏目*/
        if ($res) {
            $typeidArr = get_arr_column($res, 'parent_id');
            $typeid = implode(',', $typeidArr);
            if ($typeid == 0) {
                $result = $this->getTop();
            } else {
                $result = $this->getSon($typeid, false, $showalltext);
            }
        }
        /*--end*/

        return $result;
    }

    /**
     * 获取顶级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getTop($notypeid = '')
    {
        $result = array();

        /*获取所有栏目*/
        $arctypeLogic = new \app\common\logic\ArctypeLogic();
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $map = array(
            'is_hidden' => 0,
            'is_del'    => 0, // 回收站功能
            'status'    => 1,
            'is_part' => 0,
        );
        if (!empty($this->channelid)){
            $map['current_channel'] = $this->channelid;
        }else{
            $map['current_channel'] = ['not in',[4,5,51]];
        }
        !empty($notypeid) && $map['id'] = ['NOTIN', $notypeid]; // 排除指定栏目ID
        $res = $arctypeLogic->arctype_list(0, 0, false, $arctype_max_level, $map);
        /*--end*/
        if (count($res) > 0) {
            $topTypeid = $this->getTopTypeid($this->tid);
            $currentstyleArr = [
                'tid'   => 0,
                'currentstyle'  => '',
                'grade' => 100,
                'is_part'   => 0,
            ]; // 标记选择栏目的数组

            foreach ($res as $key => $val) {
                $val['typename'] = htmlspecialchars_decode($val['typename']);
                $val['litpic'] = $this->get_default_pic($val['litpic']); // 封面图
                /*标记栏目被选中效果*/
                $val['currentstyle'] = '';
                if ($val['id'] == $topTypeid) {
                    $is_currentstyle = false;
                    if ($topTypeid != $this->tid && 0 == $currentstyleArr['is_part'] && $val['grade'] <= $currentstyleArr['grade']) { // 当前栏目不是顶级栏目，按外部链接优先
                        $is_currentstyle = true;
                    }
                    else if ($topTypeid == $this->tid && $val['grade'] < $currentstyleArr['grade']) 
                    { // 当前栏目是顶级栏目，按顺序优先
                        $is_currentstyle = true;
                    }

                    if ($is_currentstyle) {
                        $currentstyleArr = [
                            'tid'   => $val['id'],
                            'currentstyle'  => $this->currentstyle,
                            'grade' => $val['grade'],
                            'is_part'   => $val['is_part'],
                        ];
                    }
                }
                /*--end*/

                $res[$key] = [
                    'id'    => $val['id'],
                    'typeid'    => $val['id'],
                    'parent_id'    => $val['parent_id'],
                    'typename'    => $val['typename'],
                    'litpic'    => $val['litpic'],
                    'current_channel'   => $val['current_channel'],
                    'currentstyle'    => $val['currentstyle'],
                    'has_children'    => $val['has_children'],
                    'children'    => $val['children'],
                ];
            }

            // 循环处理选中栏目的标识
            foreach ($res as $key => $val) {
                if (!empty($currentstyleArr) && $val['id'] == $currentstyleArr['tid']) {
                    $val['currentstyle'] = $currentstyleArr['currentstyle'];
                }
                $res[$key] = $val;
            }

            /*栏目层级归类成阶梯式*/
            $arr = group_same_key($res, 'parent_id');
            for ($i=0; $i < $arctype_max_level; $i++) { 
                foreach ($arr as $key => $val) {
                    foreach ($arr[$key] as $key2 => $val2) {
                        if (!isset($arr[$val2['id']])) continue;
                        $val2['children'] = $arr[$val2['id']];
                        $arr[$key][$key2] = $val2;
                    }
                }
            }
            /*--end*/

            reset($arr);  // 重置数组
            /*获取第一个数组*/
            $firstResult = current($arr);
            $result = $firstResult;
            /*--end*/
        }

        return $result;
    }

    /**
     * 获取指定模型ID的顶级栏目以及子栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getChannelTop($channelid, $showalltext = 'off', $notypeid = '')
    {
        $result = [];
        $row = $this->getTop($notypeid);
        foreach ($row as $key => $val) {
            if ('on' == $showalltext && $key == 0) {
                $arr_1 = $val;
                $arr_1['id'] = $arr_1['typeid'] = 0;
                $arr_1['typename'] = '全部';
                $arr_1['currentstyle'] = $this->currentstyle;
                $result[] = $arr_1;
            }
            if ($channelid == $val['current_channel']) {
                $result[] = $val;
            }
        }
/*
        $row = Db::name('arctype')->field("id, id as typeid, parent_id, typename, litpic, 0 as has_children, '' as children")
            ->where([
                'current_channel'=>$channelid,
                'is_hidden' => 0,
                'is_del'    => 0, // 回收站功能
                'status'    => 1,
                'lang'=>self::$home_lang,
            ])->select();
        foreach ($row as $key => $val) {
            if ('on' == $showalltext && $key == 0) {
                $arr_1 = $val;
                $arr_1['id'] = $arr_1['typeid'] = 0;
                $arr_1['typename'] = '全部';
                $arr_1['currentstyle'] = $this->currentstyle;
                $result[] = $arr_1;
            }
            $val['litpic'] = $this->get_default_pic($val['litpic']); // 封面图
            $result[] = $val;
        }
*/
        return $result;
    }

    /**
     * 获取最顶级父栏目ID
     */
    public function getTopTypeid($typeid)
    {
        $topTypeId = 0;
        if ($typeid > 0) {
            $result = model('Arctype')->getAllPid($typeid); // 当前栏目往上一级级父栏目
            reset($result); // 重置数组
            /*获取最顶级父栏目ID*/
            $firstVal = current($result);
            $topTypeId = $firstVal['id'];
            /*--end*/
        }

        return intval($topTypeId);
    }
}