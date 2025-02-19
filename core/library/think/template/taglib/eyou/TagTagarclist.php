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
use app\home\logic\FieldLogic;

/**
 * 标签文章列表
 */
class TagTagarclist extends Base
{
    public $fieldLogic;
    public $archives_db;

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->fieldLogic = new FieldLogic();
        $this->archives_db = Db::name('archives');
    }

    /**
     *  tagarclist解析函数
     *
     * @author wengxianhu by 2018-4-20
     * @access    public
     * @param     array  $param  查询数据条件集合
     * @param     int  $row  调用行数
     * @param     string  $orderby  排列顺序
     * @param     string  $addfields  附加表字段，以逗号隔开
     * @param     string  $ordermode  排序方式
     * @param     string  $tagid  标签id
     * @param     string  $tag  标签属性集合
     * @param     string  $pagesize  分页显示条数
     * @param     string  $thumb  是否开启缩略图
     * @param     string  $arcrank  是否显示会员权限
     * @return    array
     */
    public function getTagarclist($param = array(),  $limit = 20, $orderby = '', $addfields = '', $ordermode = '', $tagid = '', $tag = '', $pagesize = 0, $thumb = '', $arcrank = '')
    {
        $result = false;

        empty($ordermode) && $ordermode = 'desc';
        $limit_arr = explode(',', $limit);
        $pagesize = empty($pagesize) ? end($limit_arr) : intval($pagesize);
        $allow_release_channel = config('global.allow_release_channel');

        // 查询条件
        $condition = array();
        foreach (array('keyword','flag','noflag') as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'flag') {
                    $flag_arr = explode(",", $param[$key]);
                    $where_or_flag = array();
                    foreach ($flag_arr as $k2 => $v2) {
                        if ($v2 == "c") {
                            array_push($where_or_flag, "a.is_recom = 1");
                        } elseif ($v2 == "h") {
                            array_push($where_or_flag, "a.is_head = 1");
                        } elseif ($v2 == "a") {
                            array_push($where_or_flag, "a.is_special = 1");
                        } elseif ($v2 == "j") {
                            array_push($where_or_flag, "a.is_jump = 1");
                        } elseif ($v2 == "p") {
                            array_push($where_or_flag, "a.is_litpic = 1");
                        } elseif ($v2 == "b") {
                            array_push($where_or_flag, "a.is_b = 1");
                        } elseif ($v2 == "s") {
                            array_push($where_or_flag, "a.is_slide = 1");
                        } elseif ($v2 == "r") {
                            array_push($where_or_flag, "a.is_roll = 1");
                        } elseif ($v2 == "d") {
                            array_push($where_or_flag, "a.is_diyattr = 1");
                        }
                    }
                    if (!empty($where_or_flag)) {
                        $where_flag_str = " (".implode(" OR ", $where_or_flag).") ";
                        array_push($condition, $where_flag_str);
                    }
                } 
                elseif ($key == 'noflag') {
                    $flag_arr = explode(",", $param[$key]);
                    $where_or_flag = array();
                    foreach ($flag_arr as $nk2 => $nv2) {
                        if ($nv2 == "c") {
                            array_push($where_or_flag, "a.is_recom <> 1");
                        } elseif ($nv2 == "h") {
                            array_push($where_or_flag, "a.is_head <> 1");
                        } elseif ($nv2 == "a") {
                            array_push($where_or_flag, "a.is_special <> 1");
                        } elseif ($nv2 == "j") {
                            array_push($where_or_flag, "a.is_jump <> 1");
                        } elseif ($nv2 == "p") {
                            array_push($where_or_flag, "a.is_litpic <> 1");
                        } elseif ($nv2 == "b") {
                            array_push($where_or_flag, "a.is_b <> 1");
                        } elseif ($nv2 == "s") {
                            array_push($where_or_flag, "a.is_slide <> 1");
                        } elseif ($nv2 == "r") {
                            array_push($where_or_flag, "a.is_roll <> 1");
                        } elseif ($nv2 == "d") {
                            array_push($where_or_flag, "a.is_diyattr <> 1");
                        }
                    }
                    if (!empty($where_or_flag)) {
                        $where_flag_str = " (".implode(" OR ", $where_or_flag).") ";
                        array_push($condition, $where_flag_str);
                    }
                } elseif ($key == 'keyword' && !empty($param[$key])) {
                    $keyword = str_replace('，', ',', $param[$key]);
                    $keywordArr = explode(',', $keyword);
                    $keywordArr = array_unique($keywordArr); // 去重
                    foreach ($keywordArr as $_k => $_v) {
                        $_v = trim($_v);
                        if (empty($_v)) {
                            unset($keywordArr[$_k]);
                            break;
                        } else {
                            $keywordArr[$_k] = addslashes($_v);
                        }
                    }
                    $keyword = implode('|', $keywordArr);
                    $condition[] = Db::raw(" CONCAT(a.title,a.seo_keywords) REGEXP '$keyword' ");
                } else {
                    array_push($condition, "a.{$key} = '".$param[$key]."'");
                }
            }
        }
        array_push($condition, "a.arcrank > -1");
        array_push($condition, "a.status = 1");
        array_push($condition, "a.is_del = 0"); // 回收站功能
        /*定时文档显示插件*/
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                array_push($condition, "a.add_time <= ".getTime()); // 只显当天或之前的文档
            }
        }
        /*end*/
        $where_str = "";
        if (0 < count($condition)) {
            $where_str = implode(" AND ", $condition);
        }

        // 给排序字段加上表别名
        $orderby = getOrderBy($orderby,$ordermode,true);

        /*用于arclist标签的分页*/
        if(0 < $pagesize) {
            $tagidmd5 = $this->attDef($tag); // 进行tagid的默认处理
        }
        /*--end*/

        // 是否显示会员权限
        $users_level_list = $users_level_list2 = [];
        if ('on' == $arcrank || stristr(','.$addfields.',', ',arc_level_name,')) {
            $users_level_list = Db::name('users_level')->field('level_id,level_name,level_value')->where('lang',self::$home_lang)->order('is_system desc, level_value asc, level_id asc')->getAllWithIndex('level_value');
            if (stristr(','.$addfields.',', ',arc_level_name,')) {
                $users_level_list2 = convert_arr_key($users_level_list, 'level_id');
            }
        }

        // 查询数据处理
        $aidArr = array();
        $addtableName = ''; // 附加字段的数据表名
        
        $field = "b.*, a.*";
        $result = $this->archives_db
            ->field($field)
            ->alias('a')
            ->join('__ARCTYPE__ b', 'b.id = a.typeid', 'LEFT')
            ->where($where_str)
            ->where('a.lang', self::$home_lang)
            ->orderRaw($orderby)
            ->limit($limit)
            ->select();
        $querysql = $this->archives_db->getLastSql(); // 用于arclist标签的分页

        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        foreach ($result as $key => $val) {
            array_push($aidArr, $val['aid']); // 收集文档ID

            $controller_name = $channeltype_row[$val['channel']]['ctl_name'];
            $channeltype_table = $channeltype_row[$val['channel']]['table'];

            /*栏目链接*/
            if (!empty($val['is_part']) && $val['is_part'] == 1) {
                $val['typeurl'] = $val['typelink'];
            } else {
                $val['typeurl'] = typeurl('home/'.$controller_name."/lists", $val);
            }
            /*--end*/

            /*文档链接*/
            if (!empty($val['is_jump']) && $val['is_jump'] == 1) {
                $val['arcurl'] = $val['jumplinks'];
            } else {
                $val['arcurl'] = arcurl('home/'.$controller_name.'/view', $val);
            }
            /*--end*/

            /*封面图*/
            $val['litpic'] = get_default_pic($val['litpic']); // 默认封面图
            if ('on' == $thumb) { // 属性控制是否使用缩略图
                $val['litpic'] = thumb_img($val['litpic']);
            }
            /*--end*/

            /*是否显示会员权限*/
            !isset($val['level_name']) && $val['level_name'] = $val['arcrank'];
            !isset($val['level_value']) && $val['level_value'] = 0;
            if ('on' == $arcrank) {
                if (!empty($users_level_list[$val['arcrank']])) {
                    $val['level_name'] = $users_level_list[$val['arcrank']]['level_name'];
                    $val['level_value'] = $users_level_list[$val['arcrank']]['level_value'];
                } else if (empty($val['arcrank'])) {
                    $firstUserLevel = current($users_level_list);
                    $val['level_name'] = $firstUserLevel['level_name'];
                    $val['level_value'] = $firstUserLevel['level_value'];
                }
            }
            /*--end*/
            
            /*显示下载权限*/
            if (!empty($users_level_list2)) {
                $val['arc_level_name'] = !empty($users_level_list2[$val['arc_level_id']]) ? $users_level_list2[$val['arc_level_id']]['level_name'] : '不限会员';
            }
            /*end*/

            $result[$key] = $val;
        }

        /*附加表*/
        if (5 == $channeltype) {
            $addtableName = $channeltype_table.'_content';
            $addfields .= ',courseware,courseware_free,total_duration,total_video';
            $addfields = str_replace('，', ',', $addfields); // 替换中文逗号
            $addfields = trim($addfields, ',');
            /*过滤不相关的字段*/
            $addfields_arr = explode(',', $addfields);
            $addfields_arr = array_unique($addfields_arr);
            $extFields = Db::name($addtableName)->getTableFields();
            $addfields_arr = array_intersect($addfields_arr, $extFields);
            if (!empty($addfields_arr) && is_array($addfields_arr)) {
                $addfields = implode(',', $addfields_arr);
            } else {
                $addfields = '';
            }
            /*end*/
            !empty($addfields) && $addfields = ','.$addfields;
            $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$aidArr)->getAllWithIndex('aid');
            /*自定义字段的数据格式处理*/
            $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $channeltype, true);
            /*--end*/
            foreach ($result as $key => $val) {
                $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                $val = array_merge($valExt, $val);
                isset($val['total_duration']) && $val['total_duration'] = gmSecondFormat($val['total_duration'], ':');
                $result[$key] = $val;
            }
        } else if (!empty($addfields) && !empty($aidArr)) {
            $addtableName = $channeltype_table.'_content';
            $addfields = str_replace('，', ',', $addfields); // 替换中文逗号
            $addfields = trim($addfields, ',');
            /*过滤不相关的字段*/
            $addfields_arr = explode(',', $addfields);
            $extFields = Db::name($addtableName)->getTableFields();
            $addfields_arr = array_intersect($addfields_arr, $extFields);
            if (!empty($addfields_arr) && is_array($addfields_arr)) {
                $addfields = implode(',', $addfields_arr);
            } else {
                $addfields = '';
            }
            /*end*/
            if (!empty($addfields)) {
                $addfields = ','.$addfields;
                $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$aidArr)->getAllWithIndex('aid');
                /*自定义字段的数据格式处理*/
                $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $channeltype, true);
                /*--end*/
                foreach ($result as $key => $val) {
                    $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                    $val = array_merge($valExt, $val);
                    $result[$key] = $val;
                }
            }
        }
        /*--end*/

        //分页特殊处理
        if(false !== $tagidmd5 && 0 < $pagesize)
        {
            $arcmulti_db = \think\Db::name('arcmulti');
            $arcmultiRow = $arcmulti_db->field('tagid')->where(['tagid'=>$tagidmd5])->find();
            $attstr = addslashes(serialize($tag)); //记录属性,以便分页样式统一调用

            if(empty($arcmultiRow))
            {
                $arcmulti_db->insert([
                    'tagid' => $tagidmd5,
                    'tagname'   => 'tagarclist',
                    'innertext' => '',
                    'pagesize'  => $pagesize,
                    'querysql'  => $querysql,
                    'ordersql'  => $orderby,
                    'addfieldsSql'  => $addfields,
                    'addtableName'  => $addtableName,
                    'attstr'    => $attstr,
                    'add_time'   => getTime(),
                    'update_time'   => getTime(),
                ]);
            } else {
                $arcmulti_db->where([
                    'tagid' => $tagidmd5,
                    'tagname' => 'tagarclist',
                ])->update([
                    'innertext' => '',
                    'pagesize'  => $pagesize,
                    'querysql'  => $querysql,
                    'ordersql'  => $orderby,
                    'addfieldsSql'  => $addfields,
                    'addtableName'  => $addtableName,
                    'attstr'    => $attstr,
                    'update_time'   => getTime(),
                ]);
            }
        }

        $data = [
            'tag'    => $tag,
            'list'      => $result,
        ];

        return $data;
    }

    /**
     *  生成hash唯一串
     *
     * @param     array  $tag 标签属性
     * @return    string
     */
    private function attDef($tag)
    {
        $tagmd5 = md5(serialize($tag));
        if (!empty($tag['tagid'])) {
            $tagidmd5 = $tag['tagid'].'_'.$tagmd5;
        } else {
            $tagidmd5 = false;
        }

        return $tagidmd5;
    }
}