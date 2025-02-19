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

use think\Request;
use think\Db;
use app\common\model\Arctype;

/**
 * 获取当前频道的下级栏目的内容列表标签
 */
class TagChannelartlist extends Base
{
    public $currentclass = '';
    public $parent_arr = [];

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        /*应用于文档列表*/
        if ($this->aid > 0) {
            $this->tid = $this->get_aid_typeid($this->aid);
        }
        /*--end*/
        $arctype_obj = new Arctype();
        $arctype_arr = $arctype_obj->getAllPid($this->tid);
        $this->parent_arr = get_arr_column($arctype_arr,'id');
    }

    /**
     * 获取当前频道的下级栏目的内容列表标签
     * @param string type son表示下一级栏目,self表示当前栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getChannelartlist($typeid = '', $type = 'self', $currentclass = '', $modelid = '')
    {
        $this->currentclass = $currentclass;
        if (!empty($modelid)) {
            $typeid = '';
            $type = 'top';
        } else {
            $typeid = !empty($typeid) ? $typeid : $this->tid;

            // 栏目ID为空则默认顶级栏目
            if (empty($typeid)) {
                $type = 'top';
            } else {
                // 多语言
                $typeid = model('LanguageAttr')->getBindValue($typeid, 'arctype');
                if (empty($typeid)) {
                    echo '标签channelartlist报错：找不到与第一套【'.self::$main_lang.'】语言关联绑定的属性 typeid 值 。';
                    return false;
                } else {
                    if (self::$language_split) {
                        $this->lang = Db::name('arctype')->where(['id'=>$typeid])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->value('lang');
                        if ($this->lang != self::$home_lang) {
                            $lang_title = Db::name('language_mark')->where(['mark'=>self::$home_lang])->value('cn_title');
                            echo "标签channel报错：【{$lang_title}】语言 typeid 值不存在。";
                            return false;
                        }
                    }
                }
            }
        }

        $result = $this->getSwitchType($modelid, $typeid, $type);
        return $result;
    }

    /**
     * 获取指定级别的栏目列表
     * @param string type son表示下一级栏目,self表示同级栏目,top顶级栏目
     * @param boolean $self 包括自己本身
     * @author wengxianhu by 2018-4-26
     */
    public function getSwitchType($modelid = '', $typeid = '', $type = 'son')
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
                $result = $this->getTop($modelid);
                break;

            case 'sonself': // 下级、同级栏目
                $result = $this->getSon($typeid, true);
                break;
        }

        /*处理自定义表字段的值*/
        if (!empty($result)) {
            /*获取自定义表字段信息*/
            $map = array(
                'channel_id' => config('global.arctype_channel_id')
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
    public function getSon($typeid, $self = false)
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        if ($self) {
            $map['id|parent_id'] = array('IN', $typeid);
        } else {
            $map['parent_id'] = array('IN', $typeid);
        }
        $map['is_hidden'] = 0;
        $map['status'] = 1;
        $map['is_del'] = 0;

        $group_user_where = $this->diy_get_users_group_arctype_query_builder();   //会员分组查询条件
        if (empty($group_user_where)){
            $result = Db::name('arctype')->field('*, id as typeid')
                ->where($map)
                ->order('sort_order asc')
                ->select();
        }else{
            $result = Db::name('arctype')->alias("a")
                ->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                ->field('a.*, id as typeid')
                ->where($map)
                ->where($group_user_where)
                ->order('sort_order asc')
                ->select();
        }
        if ($result) {
            $ctl_name_list = model('Channeltype')->getAll('id,ctl_name', array(), 'id');
            foreach ($result as $key => $val) {
                // 获取指定路由模式下的URL
                if (!empty($val['is_part']) && $val['is_part'] == 1) {
                    $typeurl = $val['typelink'];
                } else {
                    $ctl_name = $ctl_name_list[$val['current_channel']]['ctl_name'];
                    $typeurl = typeurl('home/'.$ctl_name."/lists", $val);
                }
                $val['typeurl'] = $typeurl;
                // 封面图
                $val['litpic'] = handle_subdir_pic($val['litpic']);
                /*标记栏目被选中效果*/
                if (!empty($this->parent_arr) && in_array($val['id'],$this->parent_arr)) {
                    $val['currentclass'] = $val['currentstyle'] = $this->currentclass;
                } else {
                    $val['currentclass'] = $val['currentstyle'] = '';
                }
                /*--end*/
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * 获取当前栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getSelf($typeid)
    {
        $result = array();
        if (empty($typeid)) {
            return $result;
        }

        $map = array(
            'id'    => array('IN', $typeid),
            'is_hidden' => 0,
            'status'    => 1,
            'is_del'    => 0,
        );
        $group_user_where = $this->diy_get_users_group_arctype_query_builder();   //会员分组查询条件
        if (empty($group_user_where)){
            $result = Db::name('arctype')->field('*, id as typeid')
                ->where($map)
                ->order('sort_order asc')
                ->select();
        }else{
            $result = Db::name('arctype')->alias("a")
                ->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                ->field('a.*, id as typeid')
                ->where($map)
                ->where($group_user_where)
                ->order('sort_order asc')
                ->select();
        }

        if ($result) {
            $ctl_name_list = model('Channeltype')->getAll('id,ctl_name', array(), 'id');
            foreach ($result as $key => $val) {
                // 获取指定路由模式下的URL
                if (!empty($val['is_part']) && $val['is_part'] == 1) {
                    $typeurl = $val['typelink'];
                } else {
                    $ctl_name = $ctl_name_list[$val['current_channel']]['ctl_name'];
                    $typeurl = typeurl('home/'.$ctl_name."/lists", $val);
                }
                $val['typeurl'] = $typeurl;

                // 封面图
                $val['litpic'] = handle_subdir_pic($val['litpic']);
                /*标记栏目被选中效果*/
                if (!empty($this->parent_arr) && in_array($val['id'],$this->parent_arr)) {
                    $val['currentclass'] = $val['currentstyle'] = $this->currentclass;
                } else {
                    $val['currentclass'] = $val['currentstyle'] = '';
                }
                /*--end*/
                
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * 获取顶级栏目
     * @author wengxianhu by 2017-7-26
     */
    public function getTop($modelid = '')
    {
        $map = [];
        !empty($modelid) && $map['current_channel'] = intval($modelid);
        $map['lang'] = self::$home_lang;
        $map['parent_id'] = 0;
        $map['is_hidden'] = 0;
        $map['is_del'] = 0; // 回收站功能
        $map['status'] = 1;
        $group_user_where = $this->diy_get_users_group_arctype_query_builder();   //会员分组查询条件
        if (empty($group_user_where)){
            $result = Db::name('arctype')->field('*, id as typeid')
                ->where($map)
                ->order('sort_order asc')
                ->select();
        }else{
            $result = Db::name('arctype')->alias("a")
                ->join("weapp_users_group_arctype b",'a.id = b.type_id','left')
                ->field('a.*, id as typeid')
                ->where($map)
                ->where($group_user_where)
                ->order('sort_order asc')
                ->select();
        }
        if ($result) {
            $ctl_name_list = model('Channeltype')->getAll('id,ctl_name', array(), 'id');
            foreach ($result as $key => $val) {
                // 获取指定路由模式下的URL
                if (!empty($val['is_part']) && $val['is_part'] == 1) {
                    $typeurl = $val['typelink'];
                } else {
                    $ctl_name = $ctl_name_list[$val['current_channel']]['ctl_name'];
                    $typeurl = typeurl('home/'.$ctl_name."/lists", $val);
                }
                $val['typeurl'] = $typeurl;

                // 封面图
                $val['litpic'] = handle_subdir_pic($val['litpic']);
                /*标记栏目被选中效果*/
                if (!empty($this->parent_arr) && in_array($val['id'],$this->parent_arr)) {
                    $val['currentclass'] = $val['currentstyle'] = $this->currentclass;
                } else {
                    $val['currentclass'] = $val['currentstyle'] = '';
                }
                /*--end*/
                $result[$key] = $val;
            }
        }
        
        return $result;
    }
}