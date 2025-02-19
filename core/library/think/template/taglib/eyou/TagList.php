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

use think\Page;
use think\Request;
use app\home\logic\FieldLogic;
use think\Db;
use think\Config;

/**
 * 文章分页列表
 */
class TagList extends Base
{
    public $fieldLogic;
    public $url_screen_var;
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->fieldLogic = new FieldLogic();
        /*应用于文档列表*/
        if ($this->aid > 0) {
            $this->tid = $this->get_aid_typeid($this->aid);
        }
        /*--end*/

        // 定义筛选标识
        $this->url_screen_var = config('global.url_screen_var');
    }

    /**
     * 获取分页列表
     * @author wengxianhu by 2018-4-20
     */
    public function getList($param = array(), $pagesize = 10, $orderby = '', $addfields = '', $ordermode = '', $thumb = '', $arcrank = '',$siteall = null)
    {
        empty($ordermode) && $ordermode = 'desc';
        $ordermode = preg_replace('/([^\w\s\,])/i', '', $ordermode);

        /*自定义字段筛选*/
        $url_screen_var = input('param.'.$this->url_screen_var.'/d');
        if (1 == $url_screen_var) {
            return $this->GetFieldScreeningList($param,$pagesize, $orderby, $addfields, $ordermode, $thumb, $arcrank,$siteall);
        }
        /*--end*/

        /*搜索、标签搜索*/
        if (in_array(CONTROLLER_NAME, array('Search','Tags'))) {
            return $this->getSearchList($pagesize, $orderby, $addfields, $ordermode, $thumb, $arcrank,$siteall);
        }
        /*--end*/

        $result = false;

        $web_stypeid_open = tpCache('web.web_stypeid_open'); // 是否开启副栏目
        $language_split = tpCache('language.language_split'); // 多语言分离
        $site_showall = tpCache('site.site_showall'); // 多城市站点 - 全国是否显示分站的文档
        $site_showsuball = tpCache('site.site_showsuball'); // 多城市站点 - 是否显示子站文档
        $cacheKey = 'taglib-'.md5(__CLASS__.__FUNCTION__.json_encode([$param,$pagesize,$addfields,$thumb,$arcrank,$this->tid,self::$main_lang,self::$home_lang,self::$city_switch_on,self::$site_info,$web_stypeid_open,$site_showall,$site_showsuball,$language_split,$siteall]));
        $cacheData = cache($cacheKey);

        if (empty($cacheData)) {
            $channeltype = ("" != $param['channel'] && is_numeric($param['channel'])) ? intval($param['channel']) : '';
            $param['typeid'] = !empty($param['typeid']) ? $param['typeid'] : $this->tid;
            $channeltype_row = cache('extra_global_channeltype');

            if (!empty($param['typeid'])) {
                if (!preg_match('/^\d+([\d\,]*)$/i', $param['typeid'])) {
                    echo '标签list报错：typeid属性值语法错误，请正确填写栏目ID。';
                    return false;
                }

                // 过滤typeid中含有空值的栏目ID
                $typeidArr_tmp = explode(',', $param['typeid']);
                $typeidArr_tmp = array_unique($typeidArr_tmp);
                foreach($typeidArr_tmp as $k => $v){   
                    if (empty($v)) unset($typeidArr_tmp[$k]);  
                }
                $param['typeid'] = implode(',', $typeidArr_tmp);
                // end
                
                /*多语言*/
                $param['typeid'] = model('LanguageAttr')->getBindValue($param['typeid'], 'arctype');
                if (empty($param['typeid'])) {
                    echo '标签list报错：找不到与第一套【'.self::$main_lang.'】语言关联绑定的属性 typeid 值。';
                    return false;
                } else {
                    if (self::$language_split) {
                        $this->lang = Db::name('arctype')->where(['id'=>$param['typeid']])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->value('lang');
                        if ($this->lang != self::$home_lang) {
                            $lang_title = Db::name('language_mark')->where(['mark'=>self::$home_lang])->value('cn_title');
                            echo "标签list报错：【{$lang_title}】语言 typeid 值不存在。";
                            return false;
                        }
                    }
                }
                /*--end*/
            }

            $typeid = $param['typeid'];
            
            /*不指定模型ID、栏目ID，默认显示所有可以发布文档的模型ID下的文档*/
            if (("" === $channeltype && empty($typeid)) || 0 === $channeltype) {
                $allow_release_channel = config('global.allow_release_channel');
                $channeltype = $param['channel'] = implode(',', $allow_release_channel);
            }
            /*--end*/

            // 如果指定了频道ID，则频道下的所有文档都展示
            if (!empty($channeltype)) { // 优先展示模型下的文章
                unset($param['typeid']);
            } elseif (!empty($typeid)) { // 其次展示栏目下的文章
                // unset($param['channel']);
                $typeidArr = explode(',', $typeid);
                if (count($typeidArr) == 1) {
                    $typeid = intval($typeid);
                    $channel_info = M('Arctype')->field('id,current_channel')->where(array('id'=>$typeid))->find();
                    if (empty($channel_info)) {
                        echo '标签list报错：指定属性 typeid 的栏目ID不存在。';
                        return false;
                    }
                    $channeltype = !empty($channel_info) ? $channel_info["current_channel"] : '';

                    /*获取当前栏目下的同模型所有子孙栏目*/
                    $arctype_list = model("Arctype")->getHasChildren($channel_info['id']);
                    foreach ($arctype_list as $key => $val) {
                        if ($channeltype != $val['current_channel']) {
                            unset($arctype_list[$key]);
                        }
                    }
                    $typeids = get_arr_column($arctype_list, "id");
                    !in_array($typeid, $typeids) && $typeids[] = $typeid;
                    $typeid = implode(",", $typeids);
                    /*--end*/
                } elseif (count($typeidArr) > 1) {
                    // $firstTypeid = $typeidArr[0];
                    // $firstTypeid = M('Arctype')->where(array('id|dirname'=>array('eq', $firstTypeid)))->getField('id');
                    // $channeltype = M('Arctype')->where(array('id'=>array('eq', $firstTypeid)))->getField('current_channel');
                }
                $param['channel'] = $channeltype;
            } else { // 再次展示控制器对应的模型文章
                if (!empty($channeltype_row)) {
                    foreach ($channeltype_row as $_k1 => $_v1) {
                        if ($_v1['ctl_name'] == CONTROLLER_NAME) {
                            $channeltype_info = $channeltype_row[$_v1['id']];
                            continue;
                        }
                    }
                } else {
                    $channeltype_info = model('Channeltype')->getInfoByWhere(array('ctl_name'=>CONTROLLER_NAME), 'id');
                }
                if (!empty($channeltype_info)) {
                    $channeltype = $channeltype_info['id'];
                    $param['channel'] = $channeltype;
                }
            }
            
            // if (empty($typeid) && empty($channeltype)) {
            //     echo '标签list报错：至少指定属性 typeid | modelid 任何一个。';
            //     return $result;
            // }

            // 查询条件
            $condition = array();
            foreach (array('keywords','keyword','typeid','notypeid','flag','noflag','channel') as $key) {
                if (isset($param[$key]) && $param[$key] !== '') {
                    if ($key == 'keywords' && trim($param[$key])) {
                        $keywords = trim($param[$key]);
                        $keywords = str_replace(['<','>','"',';',',','@','&'], '', $keywords);
                        $keywords = addslashes($keywords);
                        $condition[] = Db::raw('a.title LIKE "%'.$keywords.'%"');
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
                    elseif ($key == 'channel') {
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
                    else {
                        $param[$key] = trim($param[$key]);
                        $param[$key] = addslashes($param[$key]);
                        array_push($condition, "a.{$key} = '".$param[$key]."'");
                    }
                }
            }

            // 多城市分站与全国的显示逻辑
            $this->site_show_archives($condition, 'archives', $site_showall, $siteall);

            array_push($condition, "a.arcrank > -1");
            array_push($condition, "a.status = 1");
            array_push($condition, "a.is_del = 0"); // 回收站功能

            // 是否显示会员权限
            $users_level_list = $users_level_list2 = [];
            if ('on' == $arcrank || stristr(','.$addfields.',', ',arc_level_name,')) {
                $users_level_list = Db::name('users_level')->field('level_id,level_name,level_value')->order('is_system desc, level_value asc, level_id asc')->getAllWithIndex('level_value');
                if (stristr(','.$addfields.',', ',arc_level_name,')) {
                    $users_level_list2 = convert_arr_key($users_level_list, 'level_id');
                }
            }

            // 获取查询的表名
            if (!empty($channeltype_row[$channeltype])) {
                $channeltype_info = $channeltype_row[$channeltype];
            } else {
                $channeltype_info = model('Channeltype')->getInfo($channeltype);
            }
            if (!empty($param['idlist'])) {
                $idlist = str_replace('，', ',', $param['idlist']);
                $idlist = trim($idlist, ',');
                array_push($condition, "a.aid IN ($idlist)");
            }else if(!empty($param['idrange'])){
                $idrange = explode('-', $param['idrange']);
                if (!empty($idrange[1])){
                    array_push($condition, "a.aid between $idrange[0] AND $idrange[1]");
                }
            }
            $cacheData = [
                'channeltype'   => $channeltype,
                'param' => $param,
                'condition' => $condition,
                'users_level_list' => $users_level_list,
                'users_level_list2' => $users_level_list2,
                'channeltype_info'  => $channeltype_info,
            ];
            cache($cacheKey, $cacheData, null, 'archives');
        }

        $group_user_where = $this->diy_get_users_group_archives_query_builder();   //会员分组查询条件
        extract($cacheData, EXTR_OVERWRITE);

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
        $orderby = getOrderBy($orderby, $ordermode);
        // 获取排序信息 --- 陈风任
        $orderby = GetSortData($orderby);

        $controller_name = $channeltype_info['ctl_name'];
        $channeltype_table = $channeltype_info['table'];

        switch ($channeltype) {
            case '-1':
            {
                break;
            }
            
            default:
            {
                $list = array();
                $adminArr = array();
                $usersArr = array();
                $query_get = array();
                $where2 = [];
                // if (empty($typeid)) {
                    $where2['a.lang'] = self::$home_lang;
                // }

                /*列表分页URL问号的查询部分*/
                $get_arr = input('get.');
                foreach ($get_arr as $key => $val) {
                    if (empty($val) || stristr($key, '/')) {
                        unset($get_arr[$key]);
                    }
                }
                $param_arr = input('param.');
                foreach ($param_arr as $key => $val) {
                    if (empty($val) || stristr($key, '/')) {
                        unset($param_arr[$key]);
                    }
                }
                $seo_pseudo = tpCache('seo.seo_pseudo');
                if ($seo_pseudo == 1) { // 动态URL模式
                    $query_get = $get_arr;
                } elseif ($seo_pseudo == 3) { // 伪静态URL模式
                    $uiset = input('param.uiset/s', 'off');
                    $uiset = trim($uiset, '/');
                    if ($uiset == 'on') {// 装修模式下的分类URL
                        $query_get = $get_arr;
                    } else {
                        /*正常模式下的分页链接*/
/*                        $diff_arr = array_diff_key($param_arr, $get_arr);
                        if (empty($diff_arr)) {
                            $query_get = array();
                        } else {
                            $query_get = $get_arr;
                        }*/
                        /*--end*/
                        $query_get = array(
                            'typeid_tmp'   => $this->tid,
                        );
                    }
                } elseif ($seo_pseudo == 2) {
                    $query_get = $get_arr;
                }
                /*--end*/

                $paginate_type = config('paginate.type');
                if (isMobile()) {
                    $paginate_type = 'mobile';
                }
                $paginate = array(
                    'type'  => $paginate_type,
                    'var_page' => config('paginate.var_page'),
                    'query' => $query_get,
                );
                $page = input('param.page/d');

                $cache_key_suffix = md5("{$this->tid}_{$this->php_sessid}");
                $buildhtml_channel_aidarr = cache("buildhtml_channel_aidarr_{$cache_key_suffix}");
                $buildhtml_channel_aidarr = unserialize($buildhtml_channel_aidarr);
                if ('Buildhtml' == CONTROLLER_NAME && !empty($buildhtml_channel_aidarr)){
                    $start = !empty($page) ? ($page-1)*$pagesize : 0;
                    $aid_arr = array_slice($buildhtml_channel_aidarr, $start, $pagesize);
                    if (empty($aid_arr)){
                        $where_str = "a.aid = 0";
                    }else{
                        $aid_str = implode(',',$aid_arr);
                        $where_str = "a.aid in ($aid_str)";
                    }
                    $paginate['page_tmp'] = 1;
                    $pages = Db::name('archives')
                        ->field("a.*")
                        ->alias('a')
                        ->where($where_str)
                        ->orderRaw($orderby)
                        ->paginate($pagesize, false, $paginate);
                    $pages->currentPage = $page;
                    $pages->total = count($buildhtml_channel_aidarr);
                    $pages->lastPage = (int)ceil($pages->total / $pagesize);
                    $pages->hasMore = false;
                    if ($start + $pagesize < $pages->total) {
                        $pages->hasMore = true;
                    } else {
                        cache("buildhtml_channel_aidarr_{$cache_key_suffix}", null);
                    }
                } else {
                    if (empty($group_user_where)){
                        $pages = Db::name('archives')
                            ->field("a.*")
                            ->alias('a')
                            ->where($where_str)
                            ->where($where2)
                            ->orderRaw($orderby)
                            ->paginate($pagesize, false, $paginate);
                    }else{
                        $pages = Db::name('archives')
                            ->field("a.*")
                            ->alias('a')
                            ->join("weapp_users_group_archives c",'a.aid = c.archives_id','left')
                            ->where($where_str)
                            ->where($where2)
                            ->where($group_user_where)
                            ->orderRaw($orderby)
                            ->paginate($pagesize, false, $paginate);
                    }
                    if ('Buildhtml' == CONTROLLER_NAME && empty($buildhtml_channel_aidarr) && 1 < $pages->lastPage()){
                        $aids_tmp = Db::name('archives')
                            ->alias('a')
                            ->where($where_str)
                            ->where($where2)
                            ->orderRaw($orderby)
                            ->column('aid');
                        cache("buildhtml_channel_aidarr_{$cache_key_suffix}", serialize($aids_tmp), null, 'buildhtml');
                    }
                }
                cache("eyou-TagList-lastPage_{$cache_key_suffix}", $pages->lastPage(), null, 'buildhtml');
                if ('Buildhtml' != CONTROLLER_NAME && $page > 1 && $page > $pages->lastPage()) {
                    abort(404);
                }
                // 每页文档所对应的栏目信息
                $typeid_arr = [];
                foreach ($pages->items() as $key => $val) {
                    array_push($typeid_arr, $val['typeid']);
                }
                $arctypeRow = Db::name('arctype')->where(['id'=>['IN', $typeid_arr]])->getAllWithIndex('id');
                // 文档列表
                $aidArr = array();
                foreach ($pages->items() as $key => $val) {
                    if (!empty($arctypeRow[$val['typeid']])) {
                        $val = array_merge($arctypeRow[$val['typeid']], $val);
                    }
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

                    /*获取指定路由模式下的URL*/
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
                    // 封面图
/*                    if (empty($val['litpic'])) {
                        $val['is_litpic'] = 0; // 无封面图
                    } else {
                        $val['is_litpic'] = 1; // 有封面图
                    }*/
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
                    
                    $list[$key] = $val;

                    array_push($aidArr, $val['aid']); // 文档ID数组
                }

                if ('on' == $arcrank) {
                    $field = 'username,nickname,head_pic,sex,users_id,admin_id';
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

                    foreach ($list as $key => $val) {
                        if (!empty($val['users_id'])) {
                            $users = !empty($usersLitpicArr[$val['users_id']]) ? $usersLitpicArr[$val['users_id']] : [];
                        } elseif (!empty($val['admin_id']) && !empty($adminLitpicArr[$val['admin_id']])) {
                            $users = !empty($adminLitpicArr[$val['admin_id']]) ? $adminLitpicArr[$val['admin_id']] : [];
                        } else {
                            $users = $adminLitpic;
                        }
                        !empty($users) && $val['users'] = $users;
                        $list[$key] = $val;
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
                    foreach ($list as $key => $val) {
                        $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                        if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                            $valExt['content'] = $valExt['content_ey_m'];
                        }
                        if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                        $val = array_merge($valExt, $val);
                        $val['total_duration'] = gmSecondFormat($val['total_duration'], ':');
                        $list[$key] = $val;
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
                        foreach ($list as $key => $val) {
                            $valExt = !empty($resultExt[$val['aid']]) ? $resultExt[$val['aid']] : array();
                            if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                                $valExt['content'] = $valExt['content_ey_m'];
                            }
                            if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                            $val = array_merge($valExt, $val);
                            $list[$key] = $val;
                        }
                    }
                }
                /*--end*/

                /*针对下载列表*/
                if (!empty($aidArr) && $controller_name == 'Download') {
                    $downloadRow = M('download_file')->where(array('aid'=>array('IN', $aidArr)))
                        ->order('aid asc, sort_order asc')
                        ->select();
                    $downloadFileArr = array();
                    if (!empty($downloadRow)) {
                        /*获取指定文档ID的下载文件列表*/
                        foreach ($downloadRow as $key => $val) {
                            if (!isset($downloadFileArr[$val['aid']]) || empty($downloadFileArr[$val['aid']])) {
                                $downloadFileArr[$val['aid']] = array();
                            }
                            $val['downurl'] = ROOT_DIR."/index.php?m=home&c=View&a=downfile&id={$val['file_id']}&uhash={$val['uhash']}&lang=".self::$home_lang;
                            $downloadFileArr[$val['aid']][] = $val;
                        }
                        /*--end*/
                    }
                    /*将组装好的文件列表与文档相关联*/
                    foreach ($list as $key => $val) {
                        $list[$key]['file_list'] = !empty($downloadFileArr[$val['aid']]) ? $downloadFileArr[$val['aid']] : array();
                    }
                    /*--end*/
                }
                /*--end*/

                if ('product' == $channeltype_info['nid'] && !empty($list)) {
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
                        foreach ($list as $key => $value) {
                            if (!empty($product_spec[$value['aid']][0])) {
                                // 产品规格
                                $Spec = $product_spec[$value['aid']][0];
                                // 规格价格
                                if (!empty($Spec) && $Spec['spec_price'] >= 0) {
                                    $list[$key]['old_price'] = unifyPriceHandle($Spec['spec_price']);
                                    $list[$key]['users_price'] = unifyPriceHandle($Spec['spec_price'] * $discountRate);
                                }
                                // 规格库存
                                if (!empty($Spec) && $Spec['spec_stock'] >= 0) $list[$key]['stock_count'] = $Spec['spec_stock'];
                                // 加入购物车 onclick
                                $list[$key]['ShopAddCart'] = " onclick=\"ShopAddCart1625194556('{$value['aid']}', '{$Spec['spec_value_id']}', 1, '{$this->root_dir}');\" ";
                            } else {
                                // 无规格
                                $list[$key]['old_price'] = unifyPriceHandle($value['users_price']);
                                $list[$key]['users_price'] = unifyPriceHandle($value['users_price'] * $discountRate);
                                // 加入购物车 onclick
                                $list[$key]['ShopAddCart'] = " onclick=\"ShopAddCart1625194556('{$value['aid']}', null, 1, '{$this->root_dir}');\" ";
                            }
                        }
                    }

                    // 如果存在分销插件则处理分销商商品URL(携带分销商参数，用于绑定分销商上下级)
                    $list = $this->handleDealerGoodsURL($list, $usersInfo);
                }

                $result['pages'] = $pages; // 分页显示输出
                $result['list'] = $list; // 赋值数据集

                break;
            }
        }

        return $result;
    }

    /**
     * 获取搜索分页列表
     * @author wengxianhu by 2018-4-20
     */
    public function getSearchList($pagesize = 10, $orderby = '', $addfields = '', $ordermode = '', $thumb = '', $arcrank = '',$siteall = null)
    {
        $result = false;
        empty($ordermode) && $ordermode = 'desc';
        $ordermode = preg_replace('/([^\w\s\,])/i', '', $ordermode);

        $condition = array();
        // 获取到所有URL参数
        $param = input('param.');
        $tagid = '';
        if (CONTROLLER_NAME == 'Tags') {
            $tag = input('param.tag/s', '');
            $tagid = input('param.tagid/d', 0);
            if (!empty($tag)) {
                $tagidArr = M('tagindex')->where(array('tag'=>array('LIKE', "%{$tag}%")))->column('id', 'id');
                $aidArr = M('taglist')->field('aid')->where(array('tid'=>array('in', $tagidArr)))->column('aid', 'aid');
                $condition['a.aid'] = array('in', $aidArr);
            } elseif ($tagid > 0) {
                $aidArr = M('taglist')->field('aid')->where(array('tid'=>array('eq', $tagid)))->column('aid', 'aid');
                $condition['a.aid'] = array('in', $aidArr);
            }
        }

        $web_stypeid_open = tpCache('web.web_stypeid_open');
        $site_showall = tpCache('site.site_showall'); // 多城市站点 - 全国是否显示分站的文档
        $search_conf = tpCache('search'); // 搜索配置
        $search_model = !empty($search_conf['search_model']) ? $search_conf['search_model'] : 'default'; // 搜索模式
        $title_word_model = !empty($search_conf['title_word_model']) ? $search_conf['title_word_model'] : 0; // 分词匹配
        if (!isset($search_conf['search_tabu_words'])) {
            $search_tabu_words = ['<','>','"',';',',','@','&'];
        } else {
            $search_tabu_words = explode(PHP_EOL, $search_conf['search_tabu_words']);
        }
        $where_keywords = '';
        // 应用搜索条件
        foreach (['keywords','keyword','typeid','notypeid','channelid','flag','noflag'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ('keywords' == $key && trim($param[$key])) {
                    $keywords = trim($param[$key]);
                    $keywords = str_replace($search_tabu_words, '', $keywords);
                    if(!empty($keywords)){
                        /*关键词高级搜索功能 start*/
                        $keywords = addslashes($keywords);
                        $where_keywords = $keywords;
                        if ('intellect' == $search_model) {
                            $condition[] = Db::raw(' a.title LIKE "%'.$keywords.'%" OR a.seo_keywords LIKE "%'.$keywords.'%" ');
                        } else if ('accurate' == $search_model) {
                            $condition['a.title'] = trim($param[$key]);
                        } else {
                            if (stristr($keywords, ' ')) {
                                // 标题分词搜索功能 start
                                $title_where = [];
                                $keywords_arr = explode(' ', $keywords);
                                foreach ($keywords_arr as $_k2 => $_v2) {
                                    $_v2 = trim($_v2);
                                    if (!empty($_v2)) {
                                        $title_where[] = ' a.title LIKE "%'.$_v2.'%" ';
                                    }
                                }
                                if (!empty($title_where)) {
                                    if (empty($title_word_model)) {
                                        $title_where_str = implode(' OR ', $title_where);
                                    } else {
                                        $title_where_str = implode(' AND ', $title_where);
                                    }
                                    $condition[] = Db::raw($title_where_str);
                                }
                                // 标题分词搜索功能 end
                            } else {
                                $condition['a.title'] = array('LIKE', "%{$keywords}%"); // 标题模糊搜索功能
                            }
                        }
                        /*关键词高级搜索功能 end*/
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
                else if ('typeid' == $key) {
                    $search_type = input('param.type/s', 'default');
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    $param[$key] = preg_replace('/([^0-9,])/i', '', $param[$key]);
                    if (stristr($param[$key], ',')) { // 指定多个栏目ID
                        $typeids = explode(',', $param[$key]);
                        $typeids = array_unique($typeids);
                        if ('sonself' == $search_type) { // 当前栏目以及下级栏目（相同模型）
                            $current_channel_arr = Db::name('arctype')->where(['id'=>['IN', $typeids], 'is_del'=>0])->column('current_channel');
                            foreach ($typeids as $_k => $_v) {
                                $childrenRow = model('Arctype')->getHasChildren($_v);
                                foreach ($childrenRow as $k2 => $v2) {
                                    if (in_array($v2['current_channel'], $current_channel_arr)) {
                                        array_push($typeids, $v2['id']);
                                    }
                                }
                            }
                        }else if('all' == $search_type){// 当前栏目以及下级栏目（忽略模型）
                            foreach ($typeids as $_k => $_v) {
                                $childrenRow = model('Arctype')->getHasChildren($_v);
                                foreach ($childrenRow as $k2 => $v2) {
                                    array_push($typeids, $v2['id']);
                                }
                            }
                        }
                    } else {
                        if ('default' == $search_type) { // 默认只检索指定的栏目ID，不涉及下级栏目
                            $typeids = [$param[$key]];
                        } else if ('sonself' == $search_type) { // 当前栏目以及下级栏目（相同模型）
                            $arctype_info = Db::name('arctype')->field('id,current_channel')->where(['id'=>['eq', $param[$key]], 'is_del'=>0])->find();
                            $childrenRow = model('Arctype')->getHasChildren($param[$key]);
                            foreach ($childrenRow as $k2 => $v2) {
                                if ($arctype_info['current_channel'] != $v2['current_channel']) {
                                    unset($childrenRow[$k2]); // 排除不是同一模型的栏目
                                }
                            }
                            $typeids = get_arr_column($childrenRow, 'id');
                        }else if('all' == $search_type){        // 当前栏目以及下级栏目（忽略模型）
                            $childrenRow = model('Arctype')->getHasChildren($param[$key]);
                            $typeids = get_arr_column($childrenRow, 'id');
                        }
                    }

                    if (empty($web_stypeid_open)) { // 关闭副栏目
                        $typeids = array_unique($typeids);
                        $condition['a.typeid'] = ['IN', $typeids];
                    } else { // 开启副栏目
                        $typeid_str = implode(',', $typeids);
                        $stypeid_where = "";
                        foreach ($typeids as $_k => $_v) {
                            $stypeid_where .= " OR CONCAT(',', a.stypeid, ',') LIKE '%,{$_v},%' ";
                        }
                        $condition[] = Db::raw(" (a.typeid IN ({$typeid_str}) {$stypeid_where}) ");
                    }
                }
                elseif ($key == 'channelid') {
                    $condition['a.channel'] = array('eq', $param[$key]);
                }
                elseif ($key == 'notypeid') {
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    $param[$key] = preg_replace('/([^0-9,])/i', '', $param[$key]);
                    $notypeids = explode(',', $param[$key]);
                    $condition['a.typeid'] = ['NOT IN', $notypeids];
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
                        $condition[] = Db::raw($where_flag_str);
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
                        $condition[] = Db::raw($where_flag_str);
                    }
                }
                else {
                    $param[$key] = trim($param[$key]);
                    $param[$key] = addslashes($param[$key]);
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        // 多城市分站与全国的显示逻辑
        $this->site_show_archives($condition, 'archives', $site_showall, $siteall);

        $condition['a.arcrank'] = array('gt', -1);
        $condition['a.status'] = array('eq', 1);
        $condition['a.is_del'] = array('eq', 0); // 回收站功能
        $condition['a.lang'] = array('eq', self::$home_lang);
        /*定时文档显示插件*/
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $condition['a.add_time'] = array('elt', getTime()); // 只显当天或之前的文档
            }
        }
        /*end*/
        // 给排序字段加上表别名
        $orderby = getOrderBy($orderby,$ordermode);

        // 获取排序信息 --- 陈风任
        $orderby = GetSortData($orderby);

        // 是否显示会员权限
        $users_level_list = $users_level_list2 = [];
        if ('on' == $arcrank || stristr(','.$addfields.',', ',arc_level_name,')) {
            $users_level_list = Db::name('users_level')->field('level_id,level_name,level_value')->order('is_system desc, level_value asc, level_id asc')->getAllWithIndex('level_value');
            if (stristr(','.$addfields.',', ',arc_level_name,')) {
                $users_level_list2 = convert_arr_key($users_level_list, 'level_id');
            }
        }

        /**
         * 数据查询，搜索出主键ID的值
         */
        $list = array();
        $query_get = input('get.');
        unset($query_get['s']);
        if (CONTROLLER_NAME == 'Tags') {
            $query_get['tagid'] = $tagid;
        }

        $paginate_type = config('paginate.type');
        if (isMobile()) {
            $paginate_type = 'mobile';
        }
        $paginate = array(
            'type'  => $paginate_type,
            'var_page' => config('paginate.var_page'),
            'query' => $query_get,
        );
        $group_user_where = $this->diy_get_users_group_archives_query_builder();

        if (empty($group_user_where)){
            $pages = Db::name('archives')
                ->field("a.aid")
                ->alias('a')
                ->where($condition)
                ->where('a.channel','NOT IN',[6]) // 排除单页
                ->order($orderby)
                ->paginate($pagesize, false, $paginate);
        }else{
            $pages = Db::name('archives')
                ->field("a.aid")
                ->alias('a')
                ->join("weapp_users_group_archives c",'a.aid = c.archives_id','left')
                ->where($condition)
                ->where($group_user_where)
                ->where('a.channel','NOT IN',[6]) // 排除单页
                ->order($orderby)
                ->paginate($pagesize, false, $paginate);
        }

        $page = input('param.page/d');
        cache("eyou-TagList-Tag-lastPage_".md5("{$tagid}_{$this->php_sessid}"), $pages->lastPage(), null, 'buildTags');
        if ('Buildhtml' != CONTROLLER_NAME && $page > 1 && $page > $pages->lastPage()) {
            abort(404);
        }

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($pages->total() > 0) {
            //写入搜索结果
            if (!empty($where_keywords)){
                Db::name('search_word')->where('word',$where_keywords)->update(['resultNum'=>$pages->total()]);
            }
            $list = $pages->items();
            $aids = get_arr_column($list, 'aid');
            $row = Db::name('archives')
                ->field("a.*")
                ->alias('a')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');
            // 每页文档所对应的栏目信息
            $typeid_arr = [];
            foreach ($row as $key => $val) {
                array_push($typeid_arr, $val['typeid']);
            }
            $arctypeRow = Db::name('arctype')->where(['id'=>['IN', $typeid_arr]])->getAllWithIndex('id');
            // 获取模型对应的控制器名称
            $channel_list = model('Channeltype')->getAll('id, ctl_name', array(), 'id');
            $adminArr = $usersArr = array();
            foreach ($list as $key => $val) {
                $arcval = $row[$val['aid']];
                if (!empty($arctypeRow[$arcval['typeid']])) {
                    $arcval = array_merge($arctypeRow[$arcval['typeid']], $arcval);
                }
                $controller_name = $channel_list[$arcval['channel']]['ctl_name'];
                if (!empty($arcval['admin_id']) && !in_array($arcval['admin_id'], $adminArr)) {
                    array_push($adminArr, $arcval['admin_id']); // 收集admin_id
                }
                if (!empty($arcval['users_id']) && !in_array($arcval['users_id'], $usersArr)) {
                    array_push($usersArr, $arcval['users_id']); // 收集users_id
                }

                $arcval['users_price'] = floatval($arcval['users_price']);
                $arcval['old_price'] = floatval($arcval['old_price']);
                $arcval['real_sales'] = $arcval['sales_num']; // 真实总销量
                $arcval['sales_num'] = $arcval['sales_all']; // 总虚拟销量

                /*获取指定路由模式下的URL*/
                if (!empty($arcval['is_part']) && $arcval['is_part'] == 1) {
                    $arcval['typeurl'] = $arcval['typelink'];
                } else {
                    $arcval['typeurl'] = typeurl('home/'.$controller_name."/lists", $arcval);
                }
                /*--end*/
                /*文档链接*/
                if (!empty($arcval['is_jump']) && $arcval['is_jump'] == 1) {
                    $arcval['arcurl'] = $arcval['jumplinks'];
                } else {
                    $arcval['arcurl'] = arcurl('home/'.$controller_name."/view", $arcval);
                }
                /*--end*/
                // 封面图
                /*if (empty($arcval['litpic'])) {
                    $arcval['is_litpic'] = 0; // 无封面图
                } else {
                    $arcval['is_litpic'] = 1; // 有封面图
                }*/
                $arcval['litpic'] = get_default_pic($arcval['litpic']); // 默认封面图
                if ('on' == $thumb) { // 属性控制是否使用缩略图
                    $arcval['litpic'] = thumb_img($arcval['litpic']);
                }
                /*--end*/

                /*是否显示会员权限*/
                !isset($arcval['level_name']) && $arcval['level_name'] = $arcval['arcrank'];
                !isset($arcval['level_value']) && $arcval['level_value'] = 0;
                if ('on' == $arcrank) {
                    if (!empty($users_level_list[$arcval['arcrank']])) {
                        $arcval['level_name'] = $users_level_list[$arcval['arcrank']]['level_name'];
                        $arcval['level_value'] = $users_level_list[$arcval['arcrank']]['level_value'];
                    } else if (empty($arcval['arcrank'])) {
                        $firstUserLevel = current($users_level_list);
                        $arcval['level_name'] = $firstUserLevel['level_name'];
                        $arcval['level_value'] = $firstUserLevel['level_value'];
                    }
                }
                /*--end*/
                    
                /*显示下载权限*/
                if (!empty($users_level_list2)) {
                    $arcval['arc_level_name'] = !empty($users_level_list2[$arcval['arc_level_id']]) ? $users_level_list2[$arcval['arc_level_id']]['level_name'] : '不限会员';
                }
                /*end*/

                $list[$key] = $arcval;
            }

            if ('on' == $arcrank) {
                $field = 'username,nickname,head_pic,sex,users_id,admin_id';
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

                foreach ($list as $key => $val) {
                    if (!empty($val['users_id'])) {
                        $users = !empty($usersLitpicArr[$val['users_id']]) ? $usersLitpicArr[$val['users_id']] : [];
                    } elseif (!empty($val['admin_id'])) {
                        $users = !empty($adminLitpicArr[$val['admin_id']]) ? $adminLitpicArr[$val['admin_id']] : [];
                    } else {
                        $users = $adminLitpic;
                    }
                    !empty($users) && $val['users'] = $users;
                    $list[$key] = $val;
                }
            }

            /*附加表*/
            if (!empty($addfields) && !empty($list)) {
                $channeltypeRow = model('Channeltype')->getAll('id,table', [], 'id'); // 模型对应数据表
                $channelGroupRow = group_same_key($list, 'current_channel'); // 模型下的文档集合
                foreach ($channelGroupRow as $channelid => $tmp_list) {
                    $addtableName = ''; // 附加字段的数据表名
                    $tmp_aid_arr = get_arr_column($tmp_list, 'aid');
                    $channeltype_table = $channeltypeRow[$channelid]['table']; // 每个模型对应的数据表
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
                            if (in_array($channelid, [1,2,3,4,5,6,7])) {
                                $addfields .= ',content_ey_m';
                            } else {
                                if (in_array($extFields, ['content_ey_m'])) {
                                    $addfields .= ',content_ey_m';
                                } 
                            }
                        }
                        $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$tmp_aid_arr)->getAllWithIndex('aid');
                        /*自定义字段的数据格式处理*/
                        $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $channelid, true);
                        /*--end*/
                        foreach ($list as $key2 => $val2) {
                            $valExt = !empty($resultExt[$val2['aid']]) ? $resultExt[$val2['aid']] : array();
                            if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                                $valExt['content'] = $valExt['content_ey_m'];
                            }
                            if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                            $val2 = array_merge($valExt, $val2);
                            $list[$key2] = $val2;
                        }
                    }
                }
            }
            /*--end*/
        }

        // 如果存在分销插件则处理分销商商品URL(携带分销商参数，用于绑定分销商上下级)
        $list = $this->handleDealerGoodsURL($list);

        $result['pages'] = $pages; // 分页显示输出
        $result['list'] = $list; // 赋值数据集

        return $result;
    }


    /**
     * 获取筛选分页列表
     * @author 陈风任 by 2019-6-11
     */
    public function GetFieldScreeningList($param = array(),$pagesize = 10, $orderby = '', $addfields = '', $ordermode = '', $thumb = '', $arcrank = '',$siteall = null)
    {
        $result = false;
        empty($ordermode) && $ordermode = 'desc';
        $ordermode = preg_replace('/([^\w\s\,])/i', '', $ordermode);

        $condition = array();
        // 获取到所有URL参数
        $param_new = input('param.');

        if (CONTROLLER_NAME == 'Tags') {
            $tag = input('param.tag/s', '');
            $tagid = input('param.tagid/d', 0);
            if (!empty($tag)) {
                $tagidArr = M('tagindex')->where(array('tag'=>array('LIKE', "%{$tag}%")))->column('id', 'id');
                $aidArr = M('taglist')->field('aid')->where(array('tid'=>array('in', $tagidArr)))->column('aid', 'aid');
                $condition['a.aid'] = array('in', $aidArr);
            } elseif ($tagid > 0) {
                $aidArr = M('taglist')->field('aid')->where(array('tid'=>array('eq', $tagid)))->column('aid', 'aid');
                $condition['a.aid'] = array('in', $aidArr);
            }
        } else{
            // 查询当前栏目所属模型
            if (empty($param_new['channelid'])) {
                $channel_id = Db::name('arctype')->where('id',$param_new['tid'])->getField('current_channel');
                $channel_id = empty($channel_id) ? 1 : $channel_id;
            } else {
                $channel_id = intval($param_new['channelid']);
            }
            /*自定义字段筛选*/
            $where = [
                'is_screening' => 1,
                'channel_id' => $channel_id,
                // 根据需求新增条件
            ];
            // 所有应用于搜索的自定义字段
            $channelfield = Db::name('channelfield')->where($where)->field('channel_id,id,name,dtype,dfvalue')->select();
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
                        }else{
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
                $condition['a.aid'] = ['IN', get_arr_column($aid_result, "aid")]; // array_push($condition, "a.aid IN (".implode(',', get_arr_column($aid_result, "aid")).")");
            } else {
                $pages = Db::name('archives')->field("aid")->where("aid=0")->paginate($pagesize);
                $result['pages'] = $pages; // 分页显示输出
                $result['list'] = []; // 赋值数据集
                return $result;
            }
            /*结束*/
        }

        $web_stypeid_open = tpCache('web.web_stypeid_open');
        $site_showall = tpCache('site.site_showall'); // 多城市站点 - 全国是否显示分站的文档
        // 应用搜索条件
        foreach (['keywords','keyword','typeid','notypeid','channel','flag','noflag'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ('keywords' == $key && trim($param[$key])) {
                    $keywords = trim($param[$key]);
                    $keywords = str_replace(['<','>','"',';',',','@','&'], '', $keywords);
                    if (!empty($keywords)){
                        $keywords = addslashes($keywords);
                        $condition['a.title'] = array('LIKE', "%{$keywords}%");
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
                elseif ($key == 'typeid') {
                    $search_type = input('param.type/s', 'default');
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    $param[$key] = preg_replace('/([^0-9,])/i', '', $param[$key]);
                    if (stristr($param[$key], ',')) { // 指定多个栏目ID
                        $typeids = explode(',', $param[$key]);
                        $typeids = array_unique($typeids);
                        if ('sonself' == $search_type) { // 当前栏目以及下级栏目（相同模型）
                            $current_channel_arr = Db::name('arctype')->where(['id'=>['IN', $typeids], 'is_del'=>0])->column('current_channel');
                            foreach ($typeids as $_k => $_v) {
                                $childrenRow = model('Arctype')->getHasChildren($_v);
                                foreach ($childrenRow as $k2 => $v2) {
                                    if (in_array($v2['current_channel'], $current_channel_arr)) {
                                        array_push($typeids, $v2['id']);
                                    }
                                }
                            }
                        }else if('all' == $search_type){// 当前栏目以及下级栏目（忽略模型）
                            foreach ($typeids as $_k => $_v) {
                                $childrenRow = model('Arctype')->getHasChildren($_v);
                                foreach ($childrenRow as $k2 => $v2) {
                                    array_push($typeids, $v2['id']);
                                }
                            }
                        }
                    } else {
                        if ('default' == $search_type) { // 默认只检索指定的栏目ID，不涉及下级栏目
                            $typeids = [$param[$key]];
                        } else if ('sonself' == $search_type) { // 当前栏目以及下级栏目（相同模型）
                            $arctype_info = Db::name('arctype')->field('id,current_channel')->where(['id'=>['eq', $param[$key]], 'is_del'=>0])->find();
                            $childrenRow = model('Arctype')->getHasChildren($param[$key]);
                            foreach ($childrenRow as $k2 => $v2) {
                                if ($arctype_info['current_channel'] != $v2['current_channel']) {
                                    unset($childrenRow[$k2]); // 排除不是同一模型的栏目
                                }
                            }
                            $typeids = get_arr_column($childrenRow, 'id');
                        }else if('all' == $search_type){        // 当前栏目以及下级栏目（忽略模型）
                            $childrenRow = model('Arctype')->getHasChildren($param[$key]);
                            $typeids = get_arr_column($childrenRow, 'id');
                        }
                    }

                    if (empty($web_stypeid_open)) { // 关闭副栏目
                        $typeids = array_unique($typeids);
                        $condition['a.typeid'] = ['IN', $typeids];
                    } else { // 开启副栏目
                        $typeid_str = implode(',', $typeids);
                        $stypeid_where = "";
                        foreach ($typeids as $_k => $_v) {
                            $stypeid_where .= " OR CONCAT(',', a.stypeid, ',') LIKE '%,{$_v},%' ";
                        }
                        $condition[] = Db::raw(" (a.typeid IN ({$typeid_str}) {$stypeid_where}) ");
                    }
                }
                elseif ($key == 'channel') {
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    $condition['a.channel'] = array('in', $param[$key]);
                }
                elseif ($key == 'notypeid') {
                    $param[$key] = str_replace('，', ',', $param[$key]);
                    $condition['a.typeid'] = array('not in', $param[$key]);
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
                        $condition[] = Db::raw($where_flag_str);
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
                        $condition[] = Db::raw($where_flag_str);
                    }
                }
                else {
                    $param[$key] = trim($param[$key]);
                    $param[$key] = addslashes($param[$key]);
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        // 多城市分站与全国的显示逻辑
        $this->site_show_archives($condition, 'archives', $site_showall, $siteall);
        
        $condition['a.arcrank'] = ['gt', -1]; // 阅读权限
        $condition['a.status'] = 1;
        $condition['a.is_del'] = 0; // 回收站
        $condition['a.lang'] = self::$home_lang;
        /*定时文档显示插件*/
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $condition['a.add_time'] = ['elt', getTime()]; // 只显当天或之前的文档
            }
        }
        /*end*/

        $seo_pseudo = config('ey_config.seo_pseudo');
        // 是否伪静态下
        if (!isset($param_new[$this->url_screen_var]) && 3 == $seo_pseudo) {
             $arctype_where = [
                'dirname' => $param_new['tid'],
                'lang'    => self::$home_lang,
            ];
            $param_new['tid'] = Db::name('arctype')->where($arctype_where)->getField('id');
        }

        $arctype_ids = [];
        if (isset($param_new['tid'])) {
            // 最后一级栏目则查询当前栏目数据
            $condition['a.typeid'] = $param_new['tid']; // $TypeIdWhere = "a.typeid = ".$param_new['tid'];
            // 查询栏目是否存在下一级栏目
            // $arctype_ids = Db::name('arctype')->where('parent_id',$param_new['tid'])->field('id')->select();
            $arctype_ids = model('Arctype')->getHasChildren($param_new['tid']);
        }

        if (!empty($arctype_ids)) {
            $arctype_ids = get_arr_column($arctype_ids, "id");
            $field_id = [];
            // 处理得到绑定栏目的ID
            foreach ($param_new as $key => $value) {
                foreach ($channelfield as $kk => $vv) {
                    if ($vv['name'] == $key) {
                        $field_id[] = $vv['id'];
                    }
                }
            }
            // 处理栏目ID
            if (!empty($field_id)) {
                $typeid_arr = Db::name('channelfield_bind')->where('field_id','IN',$field_id)->field('typeid')->select();
                $typeid_arr = array_unique(get_arr_column($typeid_arr, "typeid"));
                $array_new  = array_intersect($typeid_arr, $arctype_ids);
                if (!empty($array_new)) {
                    $typeid_ids = $array_new; // $typeid_ids = '('.implode(',', $array_new).')';
                }else{
                    $typeid_ids = explode(',', $param_new['tid']); // $typeid_ids = '('.$param_new['tid'].')';
                }
            }else{
                $typeid_ids = $arctype_ids; // $typeid_ids = '('.implode(',', $arctype_ids).')';
            }
            $condition['a.typeid'] = ['IN', $typeid_ids]; // $TypeIdWhere = "a.typeid IN ".$typeid_ids;
        }
        // array_push($condition, $TypeIdWhere);

        // 拼装查询所有条件成sql
/*        $condition_str = "";
        if (0 < count($condition)) {
            $condition_str = implode(" AND ", $condition);
        }*/

        if (!empty($param['idlist'])) {
            $idlist = str_replace('，', ',', $param['idlist']);
            $idlist = trim($idlist, ',');
            $idlist = explode(',', $idlist);
            $condition['a.aid'] = ['IN', $idlist];
        }else if(!empty($param['idrange'])){
            $idrange = explode('-', $param['idrange']);
            if (!empty($idrange[1])){
                $condition['a.aid'] = ['between', $idrange];
            }
        }
        // 给排序字段加上表别名
        $orderby = getOrderBy($orderby,$ordermode);

        // 获取排序信息 --- 陈风任
        $orderby = GetSortData($orderby);

        // 是否显示会员权限
        $users_level_list = $users_level_list2 = [];
        if ('on' == $arcrank || stristr(','.$addfields.',', ',arc_level_name,')) {
            $users_level_list = Db::name('users_level')->field('level_id,level_name,level_value')->order('is_system desc, level_value asc, level_id asc')->getAllWithIndex('level_value');
            if (stristr(','.$addfields.',', ',arc_level_name,')) {
                $users_level_list2 = convert_arr_key($users_level_list, 'level_id');
            }
        }

        /**
         * 数据查询，搜索出主键ID的值
         */
        $list = array();
        $query_get = input('get.');
        $paginate_type = config('paginate.type');
        if (isMobile()) {
            $paginate_type = 'mobile';
        }
        $paginate = array(
            'type'  => $paginate_type,
            'var_page' => config('paginate.var_page'),
            'query' => $query_get,
        );
        $pages = Db::name('archives')
            ->field("a.aid")
            ->alias('a')
            ->where($condition)
            ->where('a.channel','NOT IN',[6]) // 排除单页
            ->order($orderby)
            ->paginate($pagesize, false, $paginate);

        $page = input('param.page/d');
        if ('Buildhtml' != CONTROLLER_NAME && $page > 1 && $page > $pages->lastPage()) {
            abort(404);
        }
        
        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($pages->total() > 0) {
            $list = $pages->items();
            $aids = get_arr_column($list, 'aid');
            $row = Db::name('archives')
                ->field("a.*")
                ->alias('a')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');
            // 每页文档所对应的栏目信息
            $typeid_arr = [];
            foreach ($row as $key => $val) {
                array_push($typeid_arr, $val['typeid']);
            }
            $arctypeRow = Db::name('arctype')->where(['id'=>['IN', $typeid_arr]])->getAllWithIndex('id');
            // 获取模型对应的控制器名称
            $channel_list = model('Channeltype')->getAll('id, ctl_name', array(), 'id');
            $adminArr = $usersArr = array();
            foreach ($list as $key => $val) {
                $arcval = $row[$val['aid']];
                if (!empty($arctypeRow[$arcval['typeid']])) {
                    $arcval = array_merge($arctypeRow[$arcval['typeid']], $arcval);
                }
                $controller_name = $channel_list[$arcval['channel']]['ctl_name'];
                if (!empty($arcval['admin_id']) && !in_array($arcval['admin_id'], $adminArr)) {
                    array_push($adminArr, $arcval['admin_id']); // 收集admin_id
                }
                if (!empty($arcval['users_id']) && !in_array($arcval['users_id'], $usersArr)) {
                    array_push($usersArr, $arcval['users_id']); // 收集users_id
                }
                
                $arcval['users_price'] = floatval($arcval['users_price']);
                $arcval['old_price'] = floatval($arcval['old_price']);
                $arcval['real_sales'] = $arcval['sales_num']; // 真实总销量
                $arcval['sales_num'] = $arcval['sales_all']; // 总虚拟销量
                
                /*获取指定路由模式下的URL*/
                if (!empty($arcval['is_part']) && $arcval['is_part'] == 1) {
                    $arcval['typeurl'] = $arcval['typelink'];
                } else {
                    $arcval['typeurl'] = typeurl('home/'.$controller_name."/lists", $arcval);
                }
                /*--end*/
                /*文档链接*/
                if (!empty($arcval['is_jump']) && $arcval['is_jump'] == 1) {
                    $arcval['arcurl'] = $arcval['jumplinks'];
                } else {
                    $arcval['arcurl'] = arcurl('home/'.$controller_name."/view", $arcval);
                }
                /*--end*/
                // 封面图
                if (empty($arcval['litpic'])) {
                    $arcval['is_litpic'] = 0; // 无封面图
                } else {
                    $arcval['is_litpic'] = 1; // 有封面图
                }
                $arcval['litpic'] = thumb_img(get_default_pic($arcval['litpic'])); // 默认封面图
                /*--end*/

                /*是否显示会员权限*/
                !isset($arcval['level_name']) && $arcval['level_name'] = $arcval['arcrank'];
                !isset($arcval['level_value']) && $arcval['level_value'] = 0;
                if ('on' == $arcrank) {
                    if (!empty($users_level_list[$arcval['arcrank']])) {
                        $arcval['level_name'] = $users_level_list[$arcval['arcrank']]['level_name'];
                        $arcval['level_value'] = $users_level_list[$arcval['arcrank']]['level_value'];
                    } else if (empty($arcval['arcrank'])) {
                        $firstUserLevel = current($users_level_list);
                        $arcval['level_name'] = $firstUserLevel['level_name'];
                        $arcval['level_value'] = $firstUserLevel['level_value'];
                    }
                }
                /*--end*/
                    
                /*显示下载权限*/
                if (!empty($users_level_list2)) {
                    $arcval['arc_level_name'] = !empty($users_level_list2[$arcval['arc_level_id']]) ? $users_level_list2[$arcval['arc_level_id']]['level_name'] : '不限会员';
                }
                /*end*/

                $list[$key] = $arcval;
            }

            if ('on' == $arcrank) {
                $field = 'username,nickname,head_pic,sex,users_id,admin_id';
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

                foreach ($list as $key => $val) {
                    if (!empty($val['users_id'])) {
                        $users = !empty($usersLitpicArr[$val['users_id']]) ? $usersLitpicArr[$val['users_id']] : [];
                    } elseif (!empty($val['admin_id'])) {
                        $users = !empty($adminLitpicArr[$val['admin_id']]) ? $adminLitpicArr[$val['admin_id']] : [];
                    } else {
                        $users = $adminLitpic;
                    }
                    !empty($users) && $val['users'] = $users;
                    $list[$key] = $val;
                }
            }

            /*附加表*/
            if (!empty($addfields) && !empty($list)) {
                $channeltypeRow = model('Channeltype')->getAll('id,table', [], 'id'); // 模型对应数据表
                $channelGroupRow = group_same_key($list, 'current_channel'); // 模型下的文档集合
                foreach ($channelGroupRow as $modelid => $tmp_list) {
                    $addtableName = ''; // 附加字段的数据表名
                    $tmp_aid_arr = get_arr_column($tmp_list, 'aid');
                    $channeltype_table = $channeltypeRow[$modelid]['table']; // 每个模型对应的数据表
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
                            if (in_array($modelid, [1,2,3,4,5,6,7])) {
                                $addfields .= ',content_ey_m';
                            } else {
                                if (in_array($extFields, ['content_ey_m'])) {
                                    $addfields .= ',content_ey_m';
                                } 
                            }
                        }
                        $resultExt = M($addtableName)->field("aid {$addfields}")->where('aid','in',$tmp_aid_arr)->getAllWithIndex('aid');
                        /*自定义字段的数据格式处理*/
                        $resultExt = $this->fieldLogic->getChannelFieldList($resultExt, $modelid, true);
                        if (isMobile() && !empty($arcval['content_ey_m'])){
                            $arcval['content'] = $arcval['content_ey_m'];
                        }
                        /*--end*/
                        foreach ($list as $key2 => $val2) {
                            $valExt = !empty($resultExt[$val2['aid']]) ? $resultExt[$val2['aid']] : array();
                            if (isMobile() && strstr(",{$addfields},", ',content,') && !empty($valExt['content_ey_m'])){
                                $valExt['content'] = $valExt['content_ey_m'];
                            }
                            if (isset($valExt['content_ey_m'])) {unset($valExt['content_ey_m']);}
                            $val2 = array_merge($valExt, $val2);
                            $list[$key2] = $val2;
                        }
                    }
                }
            }
            /*--end*/
        }

        // 如果存在分销插件则处理分销商商品URL(携带分销商参数，用于绑定分销商上下级)
        $list = $this->handleDealerGoodsURL($list);

        $result['pages'] = $pages; // 分页显示输出
        $result['list'] = $list; // 赋值数据集

        return $result;
    }
}