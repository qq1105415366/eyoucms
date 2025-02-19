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
 * 栏目列表
 */
class TagChannel extends Base
{
    public $currentclass = '';
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        /*应用于文档列表*/
        if ($this->aid > 0) {
            $this->tid = $this->get_aid_typeid($this->aid);
        }
        /*--end*/
    }

    /**
     * 获取指定级别的栏目列表
     * @param string type son表示下一级栏目,self表示同级栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getChannel($typeid = '', $type = 'top', $currentclass = '', $notypeid = '', $modelid = '')
    {
        $this->currentclass = $currentclass;
        if (!empty($modelid)) {
            $typeid = '';
        } else {
            $typeid  = !empty($typeid) ? $typeid : $this->tid;

            /*多语言*/
            if (!empty($typeid)) {
                $typeid = model('LanguageAttr')->getBindValue($typeid, 'arctype');
                if (empty($typeid)) {
                    echo '标签channel报错：找不到与第一套【'.self::$main_lang.'】语言关联绑定的属性 typeid 值 。';
                    return false;
                }
            }
            /*--end*/

            if (empty($typeid)) {
                /*应用于没有指定tid的列表，默认获取该控制器下的第一级栏目ID*/
                // http://demo.eyoucms.com/index.php/home/Article/lists.html
                $map = array(
                    'lang' => self::$home_lang,
                    'parent_id' => 0,
                    'is_hidden' => 0,
                    'status'    => 1,
                );
                $controller_name = request()->controller();
                $channeltype_info = model('Channeltype')->getInfoByWhere(array('ctl_name'=>$controller_name), 'id');
                if (!empty($channeltype_info)) {
                    $map['channeltype'] = $channeltype_info['id'];
                }
                $group_user_where = $this->diy_get_users_group_arctype_query_builder();   //会员分组查询条件
                if (empty($group_user_where)){
                    $typeid = Db::name('arctype')->where($map)->order('sort_order asc')->limit(1)->getField('id');
                }else{
                    $typeid = Db::name('arctype')->alias("a")->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                        ->where($map)->where($group_user_where)->order('sort_order asc')->limit(1)->getField('id');
                }
                /*--end*/
            }
        }

        if (self::$language_split) {
            $this->lang = Db::name('arctype')->where(['id'=>$typeid])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->value('lang');
            if ($this->lang != self::$home_lang) {
                $lang_title = Db::name('language_mark')->where(['mark'=>self::$home_lang])->value('cn_title');
                echo "标签channel报错：【{$lang_title}】语言 typeid 值不存在。";
                return false;
            }
        }

        $result = $this->getSwitchType($modelid, $typeid, $type, $notypeid);

        return $result;
    }

    /**
     * 获取指定级别的栏目列表
     * @param string type son表示下一级栏目,self表示同级栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getSwitchType($modelid = '', $typeid = '', $type = 'top', $notypeid = '')
    {
        $result = array();
        switch ($type) {
            case 'son': // 下级栏目
                $result = $this->getSon($typeid, false);
                break;

            case 'self': // 同级栏目
                $result = $this->getSelf($typeid);
                break;

            case 'top': // 顶级栏目
                $result = $this->getTop($modelid, $notypeid);
                break;

            case 'sonself': // 下级、同级栏目
                $result = $this->getSon($typeid, true);
                break;

            case 'first': // 第一级栏目
                $result = $this->getFirst($typeid);
                break;
        }

        /*处理自定义表字段的值*/
        if (!empty($result)) {
            /*获取自定义表字段信息*/
            $map = array(
                'channel_id'    => config('global.arctype_channel_id'),
            );
            $fieldInfo = model('Channelfield')->getListByWhere($map, '*', 'name');
            /*--end*/
            $fieldLogic = new \app\home\logic\FieldLogic;
            foreach ($result as $key => $val) {
                if (!empty($val)) {
                    $val = $fieldLogic->handleAddonFieldList($val, $fieldInfo);
                    $result[$key] = $val;
                }
            }
        }
        /*--end*/

        return $result;
    }

    /**
     * 获取下一级栏目
     * @param string $self true表示没有子栏目时，获取同级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getSon($typeid = '', $self = false)
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        $arctype_max_level = intval(config('global.arctype_max_level')); // 栏目最多级别
        /*获取所有显示且有效的栏目列表*/
        $map = array(
            'c.is_hidden'   => 0,
            'c.status'  => 1,
            'c.is_del'    => 0, // 回收站功能
        );
        $fields = "c.*, c.id as typeid, count(s.id) as has_children, '' as children";
        $group_user_where = $this->diy_get_users_group_arctype_query_builder('d.');   //会员分组查询条件
        if(empty($group_user_where)){
            $res = Db::name('arctype')
                ->field($fields)
                ->alias('c')
                ->join('__ARCTYPE__ s','s.parent_id = c.id','LEFT')
                ->where($map)
                ->where('c.lang', $this->lang)
                ->group('c.id')
                ->order('c.parent_id asc, c.sort_order asc, c.id')
                ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
                ->select();
        }else{
            $res = Db::name('arctype')
                ->field($fields)
                ->alias('c')
                ->join('__ARCTYPE__ s','s.parent_id = c.id','LEFT')
                ->join("weapp_users_group_arctype d",'c.id = d.type_id','left')
                ->where($map)
                ->where('c.lang', $this->lang)
                ->where($group_user_where)
                ->group('c.id')
                ->order('c.parent_id asc, c.sort_order asc, c.id')
                ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
                ->select();
        }

        /*--end*/
        if ($res) {
            $allArctypePids = model('Arctype')->getAllPidByids([$typeid, $this->tid]);
            $ctl_name_list = model('Channeltype')->getAll('id,ctl_name', array(), 'id');
            foreach ($res as $key => $val) {
                $val['extends'] = '';
                /*获取指定路由模式下的URL*/
                if (!empty($val['is_part']) && $val['is_part'] == 1) {
                    $val['typeurl'] = $val['typelink'];
                    if (!is_http_url($val['typeurl'])) {
                        $typeurl = '//'.request()->host();
                        if (!preg_match('#^'.ROOT_DIR.'(.*)$#i', $val['typeurl'])) {
                            $typeurl .= ROOT_DIR;
                        }
                        $typeurl .= '/'.trim($val['typeurl'], '/');
                        if (preg_match('/\/([\w\-]+)$/i', $typeurl)) {
                            $typeurl .= '/';
                        }
                        $val['typeurl'] = $typeurl;
                    }

                    if (!empty($val['target'])) {
                        $val['extends'] .= " target='_blank' ";
                    }
                    if (!empty($val['nofollow'])) {
                        $val['extends'] .= " rel='nofollow' ";
                    }
                } else {
                    $ctl_name = !empty($ctl_name_list[$val['current_channel']]['ctl_name']) ? $ctl_name_list[$val['current_channel']]['ctl_name'] : '';
                    $val['typeurl'] = typeurl('home/'.$ctl_name."/lists", $val);
                }
                /*--end*/

                /*标记栏目被选中效果*/
                if (!empty($allArctypePids[$val['id']])) {
                    $val['currentclass'] = $val['currentstyle'] = $this->currentclass;
                } else {
                    $val['currentclass'] = $val['currentstyle'] = '';
                }
                /*--end*/

                // 封面图
                $val['litpic'] = handle_subdir_pic($val['litpic']);

                $res[$key] = $val;
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
            $result = $this->getSelf($typeid);
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
    public function getSelf($typeid)
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        /*获取指定栏目ID的上一级栏目ID列表*/
        $map = array(
            'id'   => array('in', $typeid),
            'is_hidden'   => 0,
            'status'  => 1,
            'is_del'    => 0, // 回收站功能
        );
        $group_user_where = $this->diy_get_users_group_arctype_query_builder('b.');   //会员分组查询条件
        if (empty($group_user_where)){
            $res = Db::name('arctype')->field('parent_id')
                ->where($map)
                ->group('parent_id')
                ->select();
        }else{
            $res = Db::name('arctype')->alias("a")->field('parent_id')
                ->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                ->where($map)
                ->where($group_user_where)
                ->group('parent_id')
                ->select();
        }

        /*--end*/

        /*获取上一级栏目ID对应下的子孙栏目*/
        if ($res) {
            $typeidArr = get_arr_column($res, 'parent_id');
            $typeid = implode(',', $typeidArr);
            if ($typeid == 0) {
                $result = $this->getTop();
            } else {
                $result = $this->getSon($typeid, false);
            }
        }
        /*--end*/

        return $result;
    }

    /**
     * 获取顶级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getTop($modelid = '', $notypeid = '')
    {
        $result = array();

        /*获取所有栏目*/
        $arctypeLogic = new \app\common\logic\ArctypeLogic();
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $map = [];
        !empty($modelid) && $map['current_channel'] = intval($modelid);
        $map['is_hidden'] = 0;
        $map['is_del'] = 0; // 回收站功能
        $map['status'] = 1;
        !empty($notypeid) && $map['id'] = ['NOTIN', $notypeid]; // 排除指定栏目ID
        $group_user_where = $this->diy_get_users_group_arctype_query_builder('b.');   //会员分组查询条件
        $res = $arctypeLogic->arctype_list(0, 0, false, $arctype_max_level, $map,true,$group_user_where);
        /*--end*/

        if (count($res) > 0) {
            $topTypeid = $this->getTopTypeid($this->tid);
            $ctl_name_list = model('Channeltype')->getAll('id,ctl_name', array(), 'id');
            $currentclassArr = [
                'tid'   => 0,
                'currentclass'  => '',
                'currentstyle'  => '', // 加强兼容性
                'grade' => 100,
                'is_part'   => 0,
            ]; // 标记选择栏目的数组

            // 问答栏目ID
            $group_user_where = $this->diy_get_users_group_arctype_query_builder('b.');   //会员分组查询条件
            if (empty($group_user_where)){
                $ask_topTypeid = Db::name('arctype')->where(['channeltype'=>51,'is_del'=>0,'status'=>1])->getField('id');
            }else{
                $ask_topTypeid = Db::name('arctype')->alias("a")->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                    ->where(['channeltype'=>51,'is_del'=>0,'status'=>1])->where($group_user_where)->getField('id');
            }

            foreach ($res as $key => $val) {
                $val['extends'] = '';
                /*获取指定路由模式下的URL*/
                if (!empty($val['is_part']) && $val['is_part'] == 1) {
                    $val['typeurl'] = $val['typelink'];
                    if (!is_http_url($val['typeurl'])) {
                        $typeurl = '//'.request()->host();
                        if (!preg_match('#^'.ROOT_DIR.'(.*)$#i', $val['typeurl'])) {
                            $typeurl .= ROOT_DIR;
                        }
                        $typeurl .= '/'.trim($val['typeurl'], '/');
                        if (preg_match('/\/([\w\-]+)$/i', $typeurl)) {
                            $typeurl .= '/';
                        }
                        $val['typeurl'] = $typeurl;
                    }

                    if (!empty($val['target'])) {
                        $val['extends'] .= " target='_blank' ";
                    }
                    if (!empty($val['nofollow'])) {
                        $val['extends'] .= " rel='nofollow' ";
                    }
                } else {
                    $ctl_name = $ctl_name_list[$val['current_channel']]['ctl_name'];
                    if ($val['current_channel'] == 51){ // 问答模型
                        $val['typeurl'] = askurl('home/'.$ctl_name."/index");
                    }else{
                        $val['typeurl'] = typeurl('home/'.$ctl_name."/lists", $val);
                    }
                }
                /*--end*/

                /*标记栏目被选中效果*/
                $val['currentclass'] = $val['currentstyle'] = '';
                $pageurl = request()->url(true);
                $typelink = htmlspecialchars_decode($val['typelink']);
                if (CONTROLLER_NAME == 'Ask') { // 问答模型
                    $topTypeid = $ask_topTypeid;
                }
                if ($val['id'] == $topTypeid || (!empty($typelink) && stristr($pageurl, $typelink))) {
                    $is_currentclass = false;
                    if ($topTypeid != $this->tid && 0 == $currentclassArr['is_part'] && $val['grade'] <= $currentclassArr['grade']) { // 当前栏目不是顶级栏目，按外部链接优先
                        $is_currentclass = true;
                    }
                    else if ($topTypeid == $this->tid && $val['grade'] < $currentclassArr['grade']) 
                    { // 当前栏目是顶级栏目，按顺序优先
                        $is_currentclass = true;
                    }

                    if ($is_currentclass) {
                        $currentclassArr = [
                            'tid'   => $val['id'],
                            'currentclass'  => $this->currentclass,
                            'currentstyle'  => $this->currentclass, // 加强兼容性
                            'grade' => $val['grade'],
                            'is_part'   => $val['is_part'],
                        ];
                    }
                }
                /*--end*/

                // 封面图
                $val['litpic'] = handle_subdir_pic($val['litpic']);
                
                $res[$key] = $val;
            }

            // 循环处理选中栏目的标识
            foreach ($res as $key => $val) {
                if (!empty($currentclassArr) && $val['id'] == $currentclassArr['tid']) {
                    $val['currentclass'] = $val['currentstyle'] = $currentclassArr['currentclass'];
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
     * 获取最顶级父栏目ID
     */
    public function getTopTypeid($typeid = 0)
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