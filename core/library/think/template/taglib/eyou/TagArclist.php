<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
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
 * 文章列表
 */
class TagArclist extends Base
{
    public $fieldLogic;
    public $archives_db;
    public $url_screen_var;

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->fieldLogic = new FieldLogic();
        $this->archives_db = Db::name('archives');
        /*应用于文档列表*/
        if ($this->aid > 0) {
            $this->tid = $this->get_aid_typeid($this->aid);
        }
        /*--end*/
        
        // 定义筛选标识
        $this->url_screen_var = config('global.url_screen_var');
    }

    /**
     *  arclist解析函数
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
    public function getArclist($param = array(),  $row = 15, $orderby = '', $addfields = '', $ordermode = '', $tagid = '', $tag = '', $pagesize = 0, $thumb = '', $arcrank = '', $type = '',$siteall = null)
    {
        $condition = array();

        // 提取特定文档（文档ID）
        $idlist = [];
        if (!empty($param['aid'])) {
            $idlist = [intval($param['aid'])];
        } else if (!empty($param['idlist'])) {
            $idlist = str_replace('，', ',', $param['idlist']);
            $idlist = trim($idlist, ',');
            $idlist = explode(',', $idlist);
        }else if(!empty($param['idrange'])){
            $idrange = explode('-', $param['idrange']);
        }

        /*自定义字段筛选*/
        $param_new = [];
        if (!empty($tagid)) {
            $url_screen_var = 0;
            if (!empty($tag['url_params'])) {
                $param_new = $tag['url_params'] = json_decode(base64_decode($tag['url_params']), true);
                $url_screen_var = !empty($param_new[$this->url_screen_var]) ? $param_new[$this->url_screen_var] : 0;
            } else {
                $UrlParams = $param_new = input('param.');
                $url_screen_var = !empty($param_new[$this->url_screen_var]) ? $param_new[$this->url_screen_var] : 0;
                foreach ($UrlParams as $key => $value) {
                    if (in_array($key, ['m', 'c', 'a'])) {
                        unset($UrlParams[$key]);
                    }
                }
                $tag['url_params'] = base64_encode(json_encode($UrlParams));
            }
            if (1 == $url_screen_var) {
                // 查询当前栏目所属模型
                if (empty($param_new['channelid'])) {
                    $channel_id = Db::name('arctype')->where('id',$param_new['tid'])->getField('current_channel');
                } else {
                    $channel_id = intval($param_new['channelid']);
                }
                /*自定义字段筛选*/
                $field_where = [
                    'is_screening' => 1,
                    'channel_id' => $channel_id,
                    // 根据需求新增条件
                ];
                // 所有应用于搜索的自定义字段
                $channelfield = Db::name('channelfield')->where($field_where)->field('channel_id,id,name,dtype,dfvalue')->select();
                // 所有模型类别
                $channeltype_list = config('global.channeltype_list');
                $channel_table = array_search($channel_id, $channeltype_list);

                // 查询获取aid初始sql语句
                $wheres = [];
                $where_multiple = [];
                foreach ($channelfield as $key => $value) {
                    // 值不为空则执行
                    $fieldname = $value['name'];
                    if (!empty($fieldname) && !empty($param_new[$fieldname])) {
                        // 分割参数，判断多选或单选，拼装sql语句
                        $val_arr  = explode('|', trim($param_new[$fieldname], '|'));
                        if (!empty($val_arr)) {
                            if ('' == $val_arr[0]) {
                                // 选择全部时拼装sql语句
                                // $wheres[$fieldname] = ['NEQ', null];
                            } else {
                                if (1 == count($val_arr)) {
                                    if ('checkbox' == $value['dtype']) { // 多选字段类型
                                        $val_arr[0] = addslashes($val_arr[0]);
                                        $dfvalue_tmp = explode(',', $value['dfvalue']);
                                        if (in_array($val_arr[0], $dfvalue_tmp)) {
                                            array_push($where_multiple, "FIND_IN_SET('".$val_arr[0]."',{$fieldname})");
                                        } else {
                                            $val_arr[0] = md5($val_arr[0]);
                                            array_push($where_multiple, "FIND_IN_SET('".$val_arr[0]."',{$fieldname})");
                                        }
                                    } else if ('region' == $value['dtype']) { // 区域字段类型
                                        $val_arr[0] = addslashes($val_arr[0]);
                                        $val_arr_arr = explode('_', $val_arr[0]);
                                        $dfvalue_tmp = explode(',', unserialize($value['dfvalue'])['region_ids']);
                                        if (in_array($val_arr_arr[0], $dfvalue_tmp)) {
                                            rsort($val_arr_arr);
                                            array_push($where_multiple, "FIND_IN_SET('".$val_arr_arr[0]."',{$fieldname})");
                                        } else {
                                            $val_arr_arr[0] = md5($val_arr_arr[0]);
                                            array_push($where_multiple, "FIND_IN_SET('".$val_arr_arr[0]."',{$fieldname})");
                                        }
                                    } else {
                                        if (!empty($value['dfvalue'])) {
                                            $dfvalue_tmp = explode(',', $value['dfvalue']);
                                            if (!in_array($val_arr[0], $dfvalue_tmp)) $val_arr[0] = md5($val_arr[0]);
                                        } else {
                                            $val_arr[0] = str_replace(['<','>','"',';',',','@','&','#','\\','*'], '', $val_arr[0]);
                                        }
                                        $wheres[$fieldname] = $val_arr[0];
                                    }
                                }
                            }
                        }
                    }
                }

                $where_multiple_str = "";
                !empty($where_multiple) && $where_multiple_str = implode(' AND ', $where_multiple);
                $aid_result = Db::name($channel_table.'_content')->field('aid')
                    ->where($wheres)
                    ->where($where_multiple_str)
                    ->select();
                if (!empty($aid_result)) {
                    array_push($condition, "a.aid IN (".implode(',', get_arr_column($aid_result, "aid")).")");
                } else {
                    $pages = Db::name('archives')->field("aid")->where("aid=0")->paginate($pagesize);
                    $result['pages'] = $pages; // 分页显示输出
                    $result['list'] = []; // 赋值数据集
                    return $result;
                }
                /*结束*/
            }
        }
        /*--end*/

        $result = false;

        $web_stypeid_open = tpCache('web.web_stypeid_open'); // 是否开启副栏目
        $site_showall = tpCache('site.site_showall'); // 多城市站点 - 全国是否显示分站的文档
        $channeltype = ("" != $param['channel'] && is_numeric($param['channel'])) ? intval($param['channel']) : '';
        $param['typeid'] = !empty($param['typeid']) ? $param['typeid'] : $this->tid;
        empty($ordermode) && $ordermode = 'desc';
        $ordermode = preg_replace('/([^\w\s\,])/i', '', $ordermode);
        $pagesize = empty($pagesize) ? intval($row) : intval($pagesize);
        $limit = $row;

        if (!empty($param['typeid'])) {
            if (!preg_match('/^\d+([\d\,]*)$/i', $param['typeid'])) {
                echo '标签arclist报错：typeid属性值语法错误，请正确填写栏目ID。';
                return [];
            }

            // 过滤typeid中含有空值的栏目ID
            $typeidArr_tmp = explode(',', $param['typeid']);
            $typeidArr_tmp = array_unique($typeidArr_tmp);
            foreach($typeidArr_tmp as $k => $v){   
                if (empty($v)) unset($typeidArr_tmp[$k]);  
            }
            $param['typeid'] = implode(',', $typeidArr_tmp);
            
            // 多语言
            $param['typeid'] = model('LanguageAttr')->getBindValue($param['typeid'], 'arctype');
            if (empty($param['typeid'])) {
                echo '标签arclist报错：找不到与第一套【'.self::$main_lang.'】语言关联绑定的属性 typeid 值。';
                return [];
            } else {
                if (self::$language_split) {
                    $this->lang = Db::name('arctype')->where(['id'=>$param['typeid']])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->value('lang');
                    if ($this->lang != self::$home_lang) {
                        $lang_title = Db::name('language_mark')->where(['mark'=>self::$home_lang])->value('cn_title');
                        echo "标签arclist报错：【{$lang_title}】语言 typeid 值不存在。";
                        return false;
                    }
                }
            }
        }

        $typeid = $param['typeid'];

        $allow_release_channel = config('global.allow_release_channel');
        /*不指定模型ID、栏目ID，默认显示所有可以发布文档的模型ID下的文档*/
        if (("" === $channeltype && empty($typeid)) || 0 === $channeltype) {
            $channeltype = $param['channel'] = implode(',', $allow_release_channel);
        }
        /*--end*/

        if (!empty($param['joinaid'])) {
            $joinaid = intval($param['joinaid']);
            if (!isset($tag['channel'])) unset($param['channel']);
            if (!isset($tag['typeid'])) {
                unset($param['typeid']);
            } else {
                $channeltype = $param['channel'] = M('arctype')->where('id', intval($param['typeid']))->getField('current_channel');
            }
        } else {
            if (!empty($channeltype)) { // 如果指定了频道ID，则频道下的所有文档都展示
                unset($param['typeid']);
            } else {
                // unset($param['channel']);
                if (!empty($typeid)) {
                    $typeidArr = explode(',', $typeid);
                    if (count($typeidArr) == 1) {
                        $typeid = intval($typeid);
                        $channel_info = M('Arctype')->field('id,current_channel')->where(array('id'=>array('eq', $typeid)))->find();
                        if (empty($channel_info)) {
                            echo '标签arclist报错：指定属性 typeid 的栏目ID不存在。';
                            return [];
                        }
                        $channeltype = !empty($channel_info) ? $channel_info["current_channel"] : ''; // 当前栏目ID所属模型ID
                        /*当前模型ID不属于含有列表模型，直接返回无数据*/
                        if (false === array_search($channeltype, $allow_release_channel)) {
                            return [];
                        }
                        /*end*/
                        /*获取当前栏目下的所有同模型的子孙栏目*/
                        $typeids = [$typeid];
                        $arctype_list = model("Arctype")->getHasChildren($channel_info['id']);
                        foreach ($arctype_list as $key => $val) {
                            if ($channeltype == $val['current_channel']) {
                                $typeids[] = $val['id'];
                            }
                        }
                        $typeid = implode(",", $typeids);
                        /*--end*/
                    } elseif (count($typeidArr) > 1) {
                        $firstTypeid = intval($typeidArr[0]);
                        $channeltype = M('Arctype')->where('id', $firstTypeid)->getField('current_channel');

                        if ('son' == $type) {
                            $typeids = [];
                            foreach ($typeidArr as $_k => $_v) {
                                $typeids[] = $_v;
                                $arctype_list = model("Arctype")->getHasChildren($_v);
                                foreach ($arctype_list as $_k2 => $_v2) {
                                    if ($channeltype == $_v2['current_channel']) {
                                        $typeids[] = $_v2['id'];
                                    }
                                }
                            }
                            $typeid = implode(",", $typeids);
                        }
                    }
                    $param['channel'] = $channeltype;
                }
            }
        }

        // 查询条件
        foreach (array('keyword','typeid','notypeid','flag','noflag','channel','joinaid','release') as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'channel') {
                    array_push($condition, "a.channel IN ({$channeltype})");
                }
                elseif ($key == 'typeid') {
                    if (empty($web_stypeid_open)) { // 关闭副栏目
                        $typeid_arr = explode(',', $typeid);
                        $typeid_arr = array_unique($typeid_arr);
                        $typeid = implode(",", $typeid_arr);
                        array_push($condition, "a.typeid IN ({$typeid})");
                    } else { // 开启副栏目
                        $stypeid_where = "";
                        $typeid_arr = explode(',', $typeid);
                        $typeid_arr = array_unique($typeid_arr);
                        foreach ($typeid_arr as $_k => $_v) {
                            $stypeid_where .= " OR CONCAT(',', a.stypeid, ',') LIKE '%,{$_v},%' ";
                        }
                        $condition[] = Db::raw(" (a.typeid IN ({$typeid}) {$stypeid_where}) ");
                    }
                }
                elseif ($key == 'notypeid') {
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    array_push($condition, "a.typeid NOT IN (".$param[$key].")");
                }
                elseif ($key == 'flag') {
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
                }
                elseif ($key == 'keyword' && trim($param[$key])) {
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
                }
                elseif ($key == 'release' && !empty($param[$key])){
                    if('on' == $param[$key]){
                        array_push($condition, "a.users_id > 0");
                    }
                }
                else {
                    $param[$key] = trim($param[$key]);
                    $param[$key] = addslashes($param[$key]);
                    array_push($condition, "a.{$key} = '".$param[$key]."'");
                }
            }
        }

        // 多城市分站与全国的显示逻辑
        $this->site_show_archives($condition, 'archives', $site_showall, $siteall);

        // 默认查询条件
        array_push($condition, "a.arcrank > -1");
        array_push($condition, "a.status = 1");
        array_push($condition, "a.is_del = 0");

        // 定时文档显示插件
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                array_push($condition, "a.add_time <= ".getTime()); // 只显当天或之前的文档
            }
        }

        // 处理拼接查询条件
        $where_str = 0 < count($condition) ? implode(" AND ", $condition) : "";

        // 给排序字段加上表别名
        $orderby = getOrderBy($orderby, $ordermode, true);
        
        // 获取排序信息 --- 陈风任
        $orderby = GetSortData($orderby, $param_new);
        
        // 用于arclist标签的分页
        if (0 < $pagesize) {
            $tag['typeid'] = $typeid;
            isset($tag['modelid']) && $tag['modelid'] = $channeltype;
            $tagidmd5 = $this->attDef($tag); // 进行tagid的默认处理
        }

        // 获取查询的控制器名
        $channeltype_info = model('Channeltype')->getInfo($channeltype);
        $controller_name = $channeltype_info['ctl_name'];
        $channeltype_table = $channeltype_info['table'];
        
        // 是否显示会员权限
        $users_level_list = $users_level_list2 = [];
        if ('on' == $arcrank || stristr(','.$addfields.',', ',arc_level_name,')) {
            $users_level_list = Db::name('users_level')->field('level_id,level_name,level_value')->order('is_system desc, level_value asc, level_id asc')->getAllWithIndex('level_value');
            if (stristr(','.$addfields.',', ',arc_level_name,')) {
                $users_level_list2 = convert_arr_key($users_level_list, 'level_id');
            }
        }
        $group_user_where = $this->diy_get_users_group_archives_query_builder();
        // 查询数据处理
        $aidArr = $adminArr = $usersArr = array();
        $addtableName = ''; // 附加字段的数据表名
        switch ($channeltype) {
            case '-1':
            {
                break;
            }
            
            default:
            {
                $where2 = [];
                // if (empty($typeid)) {
                    $where2['a.lang'] = self::$home_lang;
                // }
                // 提取特定文档（文档ID）
                if (!empty($idlist)) {
                    $where_str = [
                        'a.aid' => ['IN', $idlist],
                    ];
                }else if(!empty($idrange)){
                    $where2['a.aid'] = ['between', $idrange];
                }
                $web_cmsmode = tpCache('global.web_cmsmode');  //系统模式
                $cache_key_suffix = md5(json_encode($where_str).json_encode($where2).json_encode($group_user_where).$orderby.$limit.$arcrank.$addfields.$web_cmsmode);
                $buildhtml_arc_result = cache("buildhtml_arc_result_".$cache_key_suffix);
                $result = unserialize($buildhtml_arc_result);

                if (($web_cmsmode == 1 || 'Buildhtml' == CONTROLLER_NAME ) && !is_dir('./weapp/TimingTask/') && 'rand()' != $orderby && !empty($result)){
                    $querysql = cache("buildhtml_arc_querysql_".$cache_key_suffix);
                } else {
                    if ('rand()' == $orderby) { // 如果是随便获取数据，将先查询1000条，然后再从其中随机，提高大数据情况下的性能
                        if (empty($group_user_where)){
                            $result = $this->archives_db
                                ->field("a.*")
                                ->alias('a')
                                ->where($where_str)
                                ->where($where2)
                                ->order('a.aid asc')
                                ->limit(1000)
                                ->select();
                        }else{
                            $result = $this->archives_db
                                ->field("a.*")
                                ->alias('a')
                                ->join("weapp_users_group_archives c",'a.aid = c.archives_id','left')
                                ->where($where_str)
                                ->where($where2)
                                ->where($group_user_where)
                                ->order('a.aid asc')
                                ->limit(1000)
                                ->select();
                        }

                        if (!empty($result)) {
                            shuffle($result);
                            $result_tmp = [];
                            $countNum = count($result); // 实际总数量
                            $inventedNum = 0; // arclist 标签里传过来的参数数量 limit
                            if (stristr($limit, ',')) {
                                $limit_arr = explode(',', $limit);
                                if (1 < count($limit_arr)) {
                                    $inventedNum = intval($limit_arr[1]) - intval($limit_arr[0]);
                                } else {
                                    $inventedNum = intval($limit_arr[0]);
                                }
                            } else {
                                $inventedNum = intval($limit);
                            }
                            if ($countNum > $inventedNum) {
                                $countNum = $inventedNum;
                            }

                            $rand_keys = array_rand($result, $countNum);
                            if (is_array($rand_keys)) {
                                foreach ($rand_keys as $_k => $_v) {
                                    $result_tmp[] = $result[$_v];
                                }
                                $result = $result_tmp;
                            } else {
                                $result = [$result[$rand_keys]];
                            }
                        }
                    } else {
                        if (empty($group_user_where)){
                            $result = $this->archives_db
                                ->field("a.*")
                                ->alias('a')
                                ->where($where_str)
                                ->where($where2)
                                ->orderRaw($orderby)
                                ->limit($limit)
                                ->select();
                        }else{
                            $result = $this->archives_db
                                ->field("a.*")
                                ->alias('a')
                                ->join("weapp_users_group_archives c",'a.aid = c.archives_id','left')
                                ->where($where_str)
                                ->where($where2)
                                ->where($group_user_where)
                                ->orderRaw($orderby)
                                ->limit($limit)
                                ->select();
                        }
                    }
                    $querysql = $this->archives_db->getLastSql(); // 用于arclist标签的分页
                    // 每页文档所对应的栏目信息
                    $typeid_arr = [];
                    foreach ($result as $key => $val) {
                        array_push($typeid_arr, $val['typeid']);
                    }
                    $arctypeRow = Db::name('arctype')->where(['id'=>['IN', $typeid_arr]])->getAllWithIndex('id');
                    // 文档列表
                    foreach ($result as $key => $val) {
                        if (!empty($arctypeRow[$val['typeid']])) {
                            $val = array_merge($arctypeRow[$val['typeid']], $val);
                        }
                        array_push($aidArr, $val['aid']); // 收集文档ID
                        if (!empty($val['admin_id']) && !in_array($val['admin_id'], $adminArr)) {
                            array_push($adminArr, $val['admin_id']); // 收集admin_id
                        }
                        if (!empty($val['users_id']) && !in_array($val['users_id'], $usersArr)) {
                            array_push($usersArr, $val['users_id']); // 收集users_id
                        }

                        $val['users_price'] = floatval($val['users_price']);
                        $val['old_price'] = floatval($val['old_price']);
                        $val['real_sales'] = $val['sales_num']; // 真实总销量
                        $val['sales_num'] = $val['sales_all']; // 总虚拟销量

                        // 栏目链接
                        if (!empty($val['is_part']) && $val['is_part'] == 1) {
                            $val['typeurl'] = $val['typelink'];
                        } else {
                            $val['typeurl'] = typeurl('home/'.$controller_name."/lists", $val);
                        }

                        // 文档链接
                        if (!empty($val['is_jump']) && $val['is_jump'] == 1) {
                            $val['arcurl'] = $val['jumplinks'];
                        } else {
                            $val['arcurl'] = arcurl('home/'.$controller_name.'/view', $val);
                        }

                        // 封面图
                        $val['litpic'] = get_default_pic($val['litpic']);
                        if ('on' == $thumb) {
                            $val['litpic'] = thumb_img($val['litpic']);
                        }

                        // 是否显示会员权限
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
                        
                        // 显示下载权限
                        if (!empty($users_level_list2)) {
                            $val['arc_level_name'] = !empty($users_level_list2[$val['arc_level_id']]) ? $users_level_list2[$val['arc_level_id']]['level_name'] : '不限会员';
                        }

                        $result[$key] = $val;
                    }

                    if ('on' == $arcrank) {
                        $field = 'username,nickname,head_pic,users_id,admin_id,sex';
                        $userslist = $adminLitpicArr = $usersLitpicArr = $whereOr = [];
                        !empty($adminArr) && $whereOr['admin_id'] = ['IN', $adminArr];
                        !empty($usersArr) && $whereOr['users_id'] = ['IN', $usersArr];
                        if (!empty($whereOr)) {
                            $userslist = Db::name('users')->field($field)
                                ->whereOr($whereOr)
                                ->select();
                            foreach ($userslist as $key => $val) {
                                $val['head_pic'] = get_head_pic($val['head_pic'], false, $val['sex']);
                                empty($val['nickname']) && $val['nickname'] = $val['username'];
                                if (!empty($val['admin_id'])) {
                                    $adminLitpicArr[$val['admin_id']] = $val;
                                }
                                if (!empty($val['users_id'])) {
                                    $usersLitpicArr[$val['users_id']] = $val;
                                }
                            }
                        }
                        $adminLitpic = Db::name('users')->field($field)->where('admin_id','>',0)->order('users_id asc')->find();
                        $adminLitpic['head_pic'] = get_head_pic($adminLitpic['head_pic'], false, $adminLitpic['sex']);
                        empty($adminLitpic['nickname']) && $adminLitpic['nickname'] = $adminLitpic['username'];

                        foreach ($result as $key => $val) {
                            if (!empty($val['users_id'])) {
                                $users = !empty($usersLitpicArr[$val['users_id']]) ? $usersLitpicArr[$val['users_id']] : [];
                            } elseif (!empty($val['admin_id']) && !empty($adminLitpicArr[$val['admin_id']])) {
                                $users = !empty($adminLitpicArr[$val['admin_id']]) ? $adminLitpicArr[$val['admin_id']] : [];
                            } else {
                                $users = $adminLitpic;
                            }
                            !empty($users) && $val['users'] = $users;
                            $result[$key] = $val;
                        }
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
                        if (isMobile() && strstr(",{$addfields},", ',content,')){
                            $addfields .= ',content_ey_m';
                        }
                        $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$aidArr)->getAllWithIndex('aid');
                        /*自定义字段的数据格式处理*/
                        $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $channeltype, true);
                        /*--end*/
                        $media_file_data = Db::name('media_file')->where('aid','in',$aidArr)->getAllWithIndex('aid');
                        foreach ($result as $key => $val) {
                            $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                            if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                                $valExt['content'] = $valExt['content_ey_m'];
                            }
                            if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                            $val = array_merge($valExt, $val);
                            isset($val['total_duration']) && $val['total_duration'] = gmSecondFormat($val['total_duration'], ':');

                            $media_file_val = [];
                            if (!empty($media_file_data[$val['aid']])) {
                                $media_file_val = $media_file_data[$val['aid']];
                                unset($media_file_val['title']);
                            }
                            $val = array_merge($val, $media_file_val);
                            isset($val['file_time']) && $val['file_time'] = gmSecondFormat($val['file_time'], ':');

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
                            if (isMobile() && strstr(",{$addfields},", ',content,')){
                                if (in_array($channeltype, [1,2,3,4,5,6,7])) {
                                    $addfields .= ',content_ey_m';
                                } else {
                                    if (in_array($extFields, ['content_ey_m'])) {
                                        $addfields .= ',content_ey_m';
                                    }
                                }
                            }
                            $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$aidArr)->getAllWithIndex('aid');
                            /*自定义字段的数据格式处理*/
                            $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $channeltype, true);
                            /*--end*/
                            foreach ($result as $key => $val) {
                                $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                                if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                                    $valExt['content'] = $valExt['content_ey_m'];
                                }
                                if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                                $val = array_merge($valExt, $val);
                                $result[$key] = $val;
                            }
                        }
                    }
                    /*--end*/
                
                    if (!is_dir('./weapp/TimingTask/') && 'rand()' != $orderby){
                        cache("buildhtml_arc_result_".$cache_key_suffix, serialize($result), null, 'archives');
                        cache("buildhtml_arc_querysql_".$cache_key_suffix, $querysql, null, 'archives');
                    }
                }
                break;
            }
        }

        // 分页特殊处理
        if (false !== $tagidmd5 && 0 < $pagesize) {
            $arcmulti_db = \think\Db::name('arcmulti');
            $arcmultiRow = $arcmulti_db->field('tagid')->where(['tagid'=>$tagidmd5])->find();
            $attstr = addslashes(serialize($tag)); //记录属性,以便分页样式统一调用
            if (empty($arcmultiRow)) {
                $arcmulti_db->insert([
                    'tagid' => $tagidmd5,
                    'tagname' => 'arclist',
                    'innertext' => '',
                    'pagesize' => $pagesize,
                    'querysql' => $querysql,
                    'ordersql' => $orderby,
                    'addfieldsSql' => $addfields,
                    'addtableName' => $addtableName,
                    'attstr' => $attstr,
                    'add_time' => getTime(),
                    'update_time' => getTime(),
                ]);
            } else {
                $arcmulti_db->where([
                    'tagid' => $tagidmd5,
                    'tagname' => 'arclist',
                ])->update([
                    'innertext' => '',
                    'pagesize' => $pagesize,
                    'querysql' => $querysql,
                    'ordersql' => $orderby,
                    'addfieldsSql' => $addfields,
                    'addtableName' => $addtableName,
                    'attstr' => $attstr,
                    'update_time' => getTime(),
                ]);
            }
        }

        if (!empty($result) && 2 == $channeltype) {
            $ShopConfig = getUsersConfigData('shop');
            if (!empty($ShopConfig['shop_open']) && !empty($ShopConfig['shop_open_spec'])) {
                // 查询商品规格并分类处理
                $where = [
                    'aid' => ['IN', $aidArr],
                    'spec_stock' => ['gt', 0],
                ];
                $order = 'spec_price asc';
                $product_spec = Db::name('product_spec_value')->where($where)->order($order)->select();
                $product_spec = group_same_key($product_spec, 'aid');
                
                // 查询用户信息
                $usersInfo = GetUsersLatestData();
                $discountRate = !empty($usersInfo['level_discount']) ? floatval($usersInfo['level_discount']) / floatval(100) : 1;

                // 处理价格及库存
                $Spec = [];
                foreach ($result as $key => $value) {
                    if (!empty($product_spec[$value['aid']][0])) {
                        // 产品规格
                        $Spec = $product_spec[$value['aid']][0];
                        // 规格价格
                        if (!empty($Spec) && $Spec['spec_price'] >= 0) {
                            $result[$key]['old_price'] = unifyPriceHandle($Spec['spec_price']);
                            $result[$key]['users_price'] = unifyPriceHandle($Spec['spec_price'] * $discountRate);
                        }
                        // 规格库存
                        if (!empty($Spec) && $Spec['spec_stock'] >= 0) $result[$key]['stock_count'] = $Spec['spec_stock'];
                        // 加入购物车 onclick
                        $result[$key]['ShopAddCart'] = " onclick=\"ShopAddCart1625194556('{$value['aid']}', '{$Spec['spec_value_id']}', 1, '{$this->root_dir}');\" ";
                    } else {
                        // 无规格
                        $result[$key]['old_price'] = unifyPriceHandle($value['users_price']);
                        $result[$key]['users_price'] = unifyPriceHandle($value['users_price'] * $discountRate);
                        // 加入购物车 onclick
                        $result[$key]['ShopAddCart'] = " onclick=\"ShopAddCart1625194556('{$value['aid']}', null, 1, '{$this->root_dir}');\" ";
                    }
                }

                // 如果存在分销插件则处理分销商商品URL(携带分销商参数，用于绑定分销商上下级)
                $result = $this->handleDealerGoodsURL($result, $usersInfo);
            }
        }

        $data = [
            'tag' => $tag,
            'list' => !empty($result) ? $result : [],
        ];
        return $data;
    }

    // 生成hash唯一串
    private function attDef($tag)
    {
        $tagmd5 = md5(serialize($tag));
        if (!empty($tag['tagid'])) {
            $tagidmd5 = $tag['tagid'].'_'.$tagmd5;
        } else {
            $tagidmd5 = false;
            // $tagidmd5 = 'arclist_'.$tagmd5;
        }

        return $tagidmd5;
    }
}