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

namespace app\common\logic;

use think\Model;
use think\Db;
/**
 * 栏目逻辑定义
 * @package common\Logic
 */
class ArctypeLogic extends Model
{

    /**
     * 获得指定栏目下的子栏目的数组
     *
     * @access  public
     * @param   int     $id     栏目的ID
     * @param   int     $selected   当前选中栏目的ID
     * @param   boolean $re_type    返回的类型: 值为真时返回下拉列表,否则返回数组
     * @param   int     $level      限定返回的级数。为0时返回所有级数
     * @param   array   $map      查询条件
     * @return  mix
     */
    public function arctype_list($id = 0, $selected = 0, $re_type = true, $level = 0, $map = array(), $is_cache = true,$group_user_where = '')
    {
        static $res = NULL;
        // $res = NULL;
        
        if ($res === NULL)
        {
            $where = array(
                'status' => 1,
            );

            /*权限控制 by 小虎哥*/
            $admin_info = session('admin_info');
            if (in_array(MODULE_NAME, ['admin']) && 0 < intval($admin_info['role_id'])) {
                $auth_role_info = $admin_info['auth_role_info'];
                if(! empty($auth_role_info)){
                    if(! empty($auth_role_info['permission']['arctype'])){
                        $where['id'] = array('IN', $auth_role_info['permission']['arctype']);
                    }
                }
            }
            /*--end*/

            /*多语言 by 小虎哥*/
            if (empty($map['lang'])) {
                $where['lang'] = get_current_lang();
            }
            /*--end*/

            if (!empty($map)) {
                $where = array_merge($where, $map);
            }
            foreach ($where as $key => $val) {
                $key_tmp = 'c.'.$key;
                $where[$key_tmp] = $val;
                unset($where[$key]);
            }
            $fields = "c.*, c.id as typeid, count(s.id) as has_children, '' as children";
            $args = [$fields, $group_user_where, $where, $is_cache];
            $cacheKey = md5('arctype-'.md5(__CLASS__.__FUNCTION__.json_encode($args)));
            $res = cache($cacheKey);
            if (empty($res) || empty($is_cache)) {
                if (empty($group_user_where)){
                    $res = Db::name('arctype')
                        ->field($fields)
                        ->alias('c')
                        ->join('__ARCTYPE__ s','s.parent_id = c.id','LEFT')
                        ->where($where)
                        ->group('c.id')
                        ->order('c.parent_id asc, c.sort_order asc, c.id')
                        ->cache($is_cache,EYOUCMS_CACHE_TIME,"arctype")
                        ->select();
                }else{
                    $res = Db::name('arctype')
                        ->field($fields)
                        ->alias('c')
                        ->join('__ARCTYPE__ s','s.parent_id = c.id','LEFT')
                        ->join("weapp_users_group_arctype b",'c.id = b.type_id','left')
                        ->where($where)
                        ->where($group_user_where)
                        ->group('c.id')
                        ->order('c.parent_id asc, c.sort_order asc, c.id')
                        ->cache($is_cache,EYOUCMS_CACHE_TIME,"arctype")
                        ->select();
                }
                cache($cacheKey, $res, null, 'arctype');
            }
        }

        if (empty($res) == true)
        {
            return $re_type ? '' : array();
        }
    
        $options = $this->arctype_options($id, $res); // 获得指定栏目下的子栏目的数组

        /* 截取到指定的缩减级别 */
        $arctype_max_level = intval(config('global.arctype_max_level'));
        if ($level > 0 && $level < $arctype_max_level)
        {
            if ($id == 0)
            {
                $end_level = $level;
            }
            else
            {
                $first_item = reset($options); // 获取第一个元素
                $end_level  = $first_item['level'] + $level;
            }
    
            /* 保留level小于end_level的部分20241219和尚 */
            // foreach ($options AS $key => $val)
            // {
            //     if ($val['level'] >= $end_level)
            //     {
            //         unset($options[$key]);
            //     }
            // }
        }
    
        $pre_key = 0;
        $select = '';
        foreach ($options AS $key => $value)
        {
            $options[$key]['has_children'] = 0;
            if ($pre_key > 0)
            {
                if ($options[$pre_key]['id'] == $options[$key]['parent_id'])
                {
                    $options[$pre_key]['has_children'] = 1;
                }
            }
            $pre_key = $key;

            if ($re_type == true) {
                $select .= '<option value="' . $value['id'] . '" ';
                $select .= ($selected == $value['id']) ? "selected='true'" : '';
                $select .= '>';
                if ($value['level'] > 0)
                {
                    $select .= str_repeat('&nbsp;', $value['level'] * 4);
                }
                $select .= htmlspecialchars_decode(addslashes($value['typename'])) . '</option>';
            }
        }
    
        if ($re_type == true) {
            return $select;
        } else {
            return $options;
        }
    }
    
    /**
     * 过滤和排序所有文章栏目，返回一个带有缩进级别的数组
     *
     * @access  private
     * @param   int     $id     上级栏目ID
     * @param   array   $arr        含有所有栏目的数组
     * @param   int     $level      级别
     * @return  void
     */
    public function arctype_options($spec_id, $arr)
    {
        static $cat_options = array();

        // $cat_options = array();
    
        if (isset($cat_options[$spec_id]))
        {
            return $cat_options[$spec_id];
        }
    
        if (!isset($cat_options[0]))
        {
            $level = $last_id = 0;
            $options = $id_array = $level_array = array();
            while (!empty($arr))
            {
                foreach ($arr AS $key => $value)
                {
                    $id = $value['id'];
                    if ($level == 0 && $last_id == 0)
                    {
                        if ($value['parent_id'] > 0)
                        {
                            break;
                        }
    
                        $options[$id]          = $value;
                        $options[$id]['level'] = $level;
                        $options[$id]['id']    = $id;
                        $options[$id]['typename']  = htmlspecialchars_decode($value['typename']);
                        unset($arr[$key]);
    
                        if ($value['has_children'] == 0)
                        {
                            continue;
                        }
                        $last_id  = $id;
                        $id_array = array($id);
                        $level_array[$last_id] = ++$level;
                        continue;
                    }
    
                    if ($value['parent_id'] == $last_id)
                    {
                        $options[$id]          = $value;
                        $options[$id]['level'] = $level;
                        $options[$id]['id']    = $id;
                        $options[$id]['typename']  = htmlspecialchars_decode($value['typename']);
                        unset($arr[$key]);
    
                        if ($value['has_children'] > 0)
                        {
                            if (end($id_array) != $last_id)
                            {
                                $id_array[] = $last_id;
                            }
                            $last_id    = $id;
                            $id_array[] = $id;
                            $level_array[$last_id] = ++$level;
                        }
                    }
                    elseif ($value['parent_id'] > $last_id)
                    {
                        break;
                    }
                }
    
                $count = count($id_array);
                if ($count > 1)
                {
                    $last_id = array_pop($id_array);
                }
                elseif ($count == 1)
                {
                    if ($last_id != end($id_array))
                    {
                        $last_id = end($id_array);
                    }
                    else
                    {
                        $level = 0;
                        $last_id = 0;
                        $id_array = array();
                        continue;
                    }
                }
    
                if ($last_id && isset($level_array[$last_id]))
                {
                    $level = $level_array[$last_id];
                }
                else
                {
                    $level = 0;
                    break;
                }
            }
            $cat_options[0] = $options;
        }
        else
        {
            $options = $cat_options[0];
        }
    
        if (!$spec_id)
        {
            return $options;
        }
        else
        {
            if (empty($options[$spec_id]))
            {
                return array();
            }
    
            $spec_id_level = $options[$spec_id]['level'];
    
            foreach ($options AS $key => $value)
            {
                if ($key != $spec_id)
                {
                    unset($options[$key]);
                }
                else
                {
                    break;
                }
            }
    
            $spec_id_array = array();
            foreach ($options AS $key => $value)
            {
                if (($spec_id_level == $value['level'] && $value['id'] != $spec_id) ||
                    ($spec_id_level > $value['level']))
                {
                    break;
                }
                else
                {
                    $spec_id_array[$key] = $value;
                }
            }
            $cat_options[$spec_id] = $spec_id_array;
    
            return $spec_id_array;
        }
    }

    /**
     * 同步新增栏目ID到多语言的模板栏目变量里
     */
    public function syn_add_language_attribute($typeid)
    {
        /*单语言 | 语言分离 情况下不执行多语言代码*/
        if (!is_language() || tpCache('language.language_split')) {
            return true;
        }
        /*--end*/

        $attr_group = 'arctype';
        $admin_lang = get_admin_lang();
        $main_lang = get_main_lang();
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $arctypeRow = Db::name('arctype')->find($typeid);
            $attr_name = 'tid'.$typeid;
            $r = Db::name('language_attribute')->save([
                'attr_title'    => $arctypeRow['typename'],
                'attr_name'     => $attr_name,
                'attr_group'    => $attr_group,
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
            if (false !== $r) {
                $data = [];
                foreach ($languageRow as $key => $val) {
                    /*同步新栏目到其他语言栏目列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $arctypeRow;
                        $addsaveData['lang'] = $val['mark'];
                        $addsaveData['typename'] = $val['mark'].$addsaveData['typename']; // 临时测试
                        $parent_id = Db::name('language_attr')->where([
                                'attr_name' => 'tid'.$arctypeRow['parent_id'],
                                'lang'  => $val['mark'],
                            ])->getField('attr_value');
                        $addsaveData['parent_id'] = intval($parent_id);
                        /*获取顶级栏目ID*/
                        if (empty($parent_id)) {
                            $topid = 0;
                        } else {
                            $parentInfo = Db::name('arctype')->field('id,topid')->where('id', $parent_id)->find();
                            $topid = !empty($parentInfo['topid']) ? $parentInfo['topid'] : $parentInfo['id'];
                        }
                        $addsaveData['topid'] = $topid;
                        /*end*/
                        unset($addsaveData['id']);
                        $typeid = model('Arctype')->addData($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name' => $attr_name,
                        'attr_value'    => $typeid,
                        'lang'  => $val['mark'],
                        'attr_group'    => $attr_group,
                        'add_time'      => getTime(),
                        'update_time'   => getTime(),
                    ];
                    /*--end*/
                }
                if (!empty($data)) {
                    model('LanguageAttr')->saveAll($data);
                }
            }
        }
    }

    /**
     * 获取栏目的目录名称，确保唯一性
     */
    public function get_dirname($typename = '', $dirname = '', $id = 0, $newDirnameArr = [])
    {
        $id = intval($id);
        if (!trim($dirname) || empty($dirname)) {
            $dirname = get_pinyin($typename);
        }
        if (strval(intval($dirname)) == strval($dirname)) {
            if (preg_match('/^([0-9]+)$/i', $dirname)) {
                $dirname .= get_rand_str(3,0,0);
            } else {
                $dirname .= get_rand_str(3,0,2);
            }
        }
        $dirname = preg_replace('/(\s)+/', '_', $dirname);
        if (!$this->dirname_unique($dirname, $id, $newDirnameArr)) {
            $nowDirname = $dirname.get_rand_str(3,0,2);
            return $this->get_dirname($typename, $nowDirname, $id, $newDirnameArr);
        }

        return $dirname;
    }

    /**
     * 判断目录名称的唯一性
     */
    public function dirname_unique($dirname = '', $typeid = 0, $newDirnameArr = [])
    {
        $result = Db::name('arctype')->field('id,dirname')
            ->where(['lang'=>get_admin_lang()])
            ->getAllWithIndex('id');
        if (!empty($result)) {
            if (0 < $typeid) unset($result[$typeid]);
            !empty($result) && $result = get_arr_column($result, 'dirname');
        }
        empty($result) && $result = [];
        $langMarks = Db::name('language_mark')->column('mark'); // 多语言标识
        $disableDirname = config('global.disable_dirname');
        $disableDirname = array_merge($disableDirname, $langMarks, $result);
        !empty($newDirnameArr) && $disableDirname = array_merge($disableDirname, $newDirnameArr);
        foreach ($disableDirname as $key => $val) {
            if (strtolower($dirname) == strtolower($val)) {
                return false;
            }
        }
        return true;
    }
}