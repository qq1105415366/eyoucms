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

namespace app\admin\controller;

use think\Page;
use think\Db;

class Download extends Base
{
    // 模型标识
    public $nid = 'download';
    // 模型ID
    public $channeltype = '';

    public function _initialize()
    {
        parent::_initialize();
        $channeltype_list  = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
        empty($this->channeltype) && $this->channeltype = 4;
        $this->assign('nid', $this->nid);
        $this->assign('channeltype', $this->channeltype);

        // 返回页面
        $paramTypeid = input('param.typeid/d', 0);
        $this->callback_url = url('Download/index', ['lang' => $this->admin_lang, 'typeid' => $paramTypeid]);
        $this->assign('callback_url', $this->callback_url);
    }

    //列表
    public function index()
    {
        $assign_data = $condition = [];

        // 获取到所有GET参数
        $param = input('param.');
        $typeid = input('typeid/d', 0);

        // 搜索、筛选查询条件处理
        foreach (['keywords', 'typeid', 'flag', 'is_release','province_id','city_id','area_id'] as $key) {
            if ($key == 'typeid' && empty($param['typeid'])) {
                $typeids = Db::name('arctype')->where('current_channel', $this->channeltype)->column('id');
                $condition['a.typeid'] = array('IN', $typeids);
            }
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $keywords = $param[$key];
                    $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
                } else if ($key == 'typeid') {
                    $typeid = $param[$key];
                    $hasRow = model('Arctype')->getHasChildren($typeid);
                    $typeids = get_arr_column($hasRow, 'id');
                    // 权限控制 by 小虎哥
                    $admin_info = session('admin_info');
                    if (0 < intval($admin_info['role_id'])) {
                        $auth_role_info = $admin_info['auth_role_info'];
                        if (!empty($typeid) && !empty($auth_role_info) && !empty($auth_role_info['permission']['arctype'])) {
                            $typeids = array_intersect($typeids, $auth_role_info['permission']['arctype']);
                        }
                    }
                    $condition['a.typeid'] = array('IN', $typeids);
                } else if ($key == 'flag') {
                    if ('is_release' == $param[$key]) {
                        $condition['a.users_id'] = array('gt', 0);
                    } else {
                        $FlagNew = $param[$key];
                        $condition['a.'.$param[$key]] = array('eq', 1);
                    }
                } else if (in_array($key, ['province_id','city_id','area_id'])) {
                    if (!empty($param['area_id'])) {
                        $condition['a.area_id'] = $param['area_id'];
                    } else if (!empty($param['city_id'])) {
                        $condition['a.city_id'] = $param['city_id'];
                    } else if (!empty($param['province_id'])) {
                        $condition['a.province_id'] = $param['province_id'];
                    }
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        // 权限控制 by 小虎哥
        $admin_info = session('admin_info');
        if (0 < intval($admin_info['role_id'])) {
            $auth_role_info = $admin_info['auth_role_info'];
            if (!empty($auth_role_info) && isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']) {
                $condition['a.admin_id'] = $admin_info['admin_id'];
            }
        }

        // 时间检索条件
        $begin = strtotime(input('add_time_begin'));
        $end = strtotime(input('add_time_end'));
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 必要条件
        $condition['a.channel'] = array('eq', $this->channeltype);
        $condition['a.lang'] = array('eq', $this->admin_lang);
        $condition['a.is_del'] = array('eq', 0);
        $conditionNew = "(a.users_id = 0 OR (a.users_id > 0 AND a.arcrank >= 0))";

        // 自定义排序
        $orderby = input('param.orderby/s');
        $orderway = input('param.orderway/s');
        if (!empty($orderby) && !empty($orderway)) {
            $orderby = "a.{$orderby} {$orderway}, a.aid desc";
        } else {
            $orderby = "a.aid desc";
        }

        // 数据查询，搜索出主键ID的值
        $SqlQuery = Db::name('archives')->alias('a')->where($condition)->where($conditionNew)->fetchSql()->count('aid');
        $count = Db::name('sql_cache_table')->where(['sql_md5'=>md5($SqlQuery)])->getField('sql_result');
        $count = ($count < 0) ? 0 : $count;
        if (empty($count)) {
            $count = Db::name('archives')->alias('a')->where($condition)->where($conditionNew)->count('aid');
            /*添加查询执行语句到mysql缓存表*/
            $SqlCacheTable = [
                'sql_name' => '|download|' . $this->channeltype . '|',
                'sql_result' => $count,
                'sql_md5' => md5($SqlQuery),
                'sql_query' => $SqlQuery,
                'add_time' => getTime(),
                'update_time' => getTime(),
            ];
            if (!empty($FlagNew)) $SqlCacheTable['sql_name'] = $SqlCacheTable['sql_name'] . $FlagNew . '|';
            if (!empty($typeid)) $SqlCacheTable['sql_name'] = $SqlCacheTable['sql_name'] . $typeid . '|';
            if (!empty($keywords)) $SqlCacheTable['sql_name'] = '|download|keywords|';
            Db::name('sql_cache_table')->insertGetId($SqlCacheTable);
            /*END*/
        }

        $Page = new Page($count, config('paginate.list_rows'));
        $list = [];
        if (!empty($count)) {
            $limit = $count > config('paginate.list_rows') ? $Page->firstRow.','.$Page->listRows : $count;
            $list = Db::name('archives')
                ->field("a.aid")
                ->alias('a')
                ->where($condition)
                ->where($conditionNew)
                ->order($orderby)
                ->limit($limit)
                ->getAllWithIndex('aid');
            if (!empty($list)) {
                $aids = array_keys($list);
                $fields = "b.*, a.*, a.aid as aid";
                $row = Db::name('archives')
                    ->field($fields)
                    ->alias('a')
                    ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                    ->where('a.aid', 'in', $aids)
                    ->getAllWithIndex('aid');
                $cityRow = get_citysite_list();
                foreach ($list as $key => $val) {
                    $row[$val['aid']]['areas']  = '';
                    if($row[$val['aid']]['area_id']>0){
                        if (isset($cityRow[$row[$val['aid']]['area_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['area_id']]['name'];     
                        }
                    }elseif($row[$val['aid']]['city_id']>0){
                        if (isset($cityRow[$row[$val['aid']]['city_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['city_id']]['name'];     
                        }
                    }elseif($row[$val['aid']]['province_id']>0){                        
                        if (isset($cityRow[$row[$val['aid']]['province_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['province_id']]['name'];     
                        }
                    } else{
                        $row[$val['aid']]['areas']  = '全国';
                    }
                    $row[$val['aid']]['arcurl'] = get_arcurl($row[$val['aid']]);
                    $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']);
                    $list[$key] = $row[$val['aid']];
                }
            }
        }

        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['typeid'] = $typeid;
        $assign_data['tab'] = input('param.tab/d', 3);// 选项卡
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();// 文档属性
        $assign_data['arctype_info'] = $typeid > 0 ? Db::name('arctype')->field('typename')->find($typeid) : [];// 当前栏目信息
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $admin_info = session('admin_info');
        $auth_role_info = $admin_info['auth_role_info'];
        $this->assign('auth_role_info', $auth_role_info);
        $this->assign('admin_info', $admin_info);

        if (IS_POST) {
            $post = input('post.');
            model('Archives')->editor_auto_210607($post);
            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $content = empty($post['addonFieldExt']['content']) ? '' : htmlspecialchars_decode($post['addonFieldExt']['content']);
            if (!empty($post['fileupload'])){
                foreach ($post['fileupload']['file_url'] as $k => $v){
                    if (is_http_url($v)){
                        $post['fileupload']['uhash'][$k] = md5($v);
                    }
                }
            }
            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $litpic = $post['litpic'];
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = 1; // 有封面图
            }

            // SEO描述
            $seo_description = '';
            if (empty($post['seo_description']) && !empty($content)) {
                $seo_description = @msubstr(checkStrHtml($content), 0, get_seo_description_length(), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // 外部链接跳转
            $jumplinks = '';
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            $htmlfilename = trim($post['htmlfilename']);
            if (!empty($htmlfilename)) {
                $htmlfilename = preg_replace("/[^\x{4e00}-\x{9fa5}\w\-]+/u", "-", $htmlfilename);
                // $htmlfilename = strtolower($htmlfilename);
                //判断是否存在相同的自定义文件名
                $map = [
                    'htmlfilename'  => $htmlfilename,
                    'lang'  => $this->admin_lang,
                ];
                if (!empty($post['typeid'])) {
                    $map['typeid'] = array('eq', $post['typeid']);
                }
                $filenameCount = Db::name('archives')->where($map)->count();
                if (!empty($filenameCount)) {
                    $this->error("同栏目下，自定义文件名已存在！");
                } else if (preg_match('/^(\d+)$/i', $htmlfilename)) {
                    $this->error("自定义文件名不能纯数字，会与文档ID冲突！");
                }
            } else {
                // 处理外贸链接
                if (is_dir('./weapp/Waimao/')) {
                    $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
                    $waimaoLogic->get_new_htmlfilename($htmlfilename, $post, 'add', $this->globalConfig);
                } else {
                    $foreignLogic = new \app\admin\logic\ForeignLogic;
                    $foreignLogic->get_new_htmlfilename($htmlfilename, $post, 'add', $this->globalConfig);
                }
            }
            $post['htmlfilename'] = $htmlfilename;

            //做自动通过审核判断
            if ($admin_info['role_id'] > 0 && $auth_role_info['check_oneself'] < 1) {
                $post['arcrank'] = -1;
            }

            // 副栏目
            if (isset($post['stypeid'])) {
                $post['stypeid'] = preg_replace('/([^\d\,\，]+)/i', ',', $post['stypeid']);
                $post['stypeid'] = str_replace('，', ',', $post['stypeid']);
                $post['stypeid'] = trim($post['stypeid'], ',');
                $post['stypeid'] = str_replace(",{$post['typeid']},", ',', ",{$post['stypeid']},");
                $post['stypeid'] = trim($post['stypeid'], ',');
            }

            // 付费限制模式与之前三个字段 arc_level_id、 users_price、 no_vip_pay 组合逻辑兼容
            $restricData = restric_type_logic($post, $this->channeltype);
            if (isset($restricData['code']) && empty($restricData['code'])) {
                $this->error($restricData['msg']);
            }

            // --存储数据
            $newData = array(
                'typeid'=> empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'   => $this->channeltype,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'      => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'      => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'     => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'origin'      => empty($post['origin']) ? '网络' : $post['origin'],
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'admin_id'  => session('admin_info.admin_id'),
                'lang'  => $this->admin_lang,
                'sort_order'    => 100,
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                'add_time'     => strtotime($post['add_time']),
                'update_time'  => strtotime($post['add_time']),
            );
            $data = array_merge($post, $newData);

            $aid          = Db::name('archives')->insertGetId($data);
            $_POST['aid'] = $aid;
            if ($aid) {
                // ---------后置操作
                model('Download')->afterSave($aid, $data, 'add');
                // 添加查询执行语句到mysql缓存表
                model('SqlCacheTable')->InsertSqlCacheTable();
                // ---------end
                adminLog('新增下载：'.$data['title']);

                // 生成静态页面代码
                $successData = [
                    'aid'   => $aid,
                    'tid'   => $post['typeid'],
                ];
                $this->success("操作成功!", null, $successData);
                exit;
            }

            $this->error("操作失败!");
            exit;
        }

        $typeid = input('param.typeid/d', 0);
        $assign_data['typeid'] = $typeid; // 栏目ID

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        //第三方存储空间 七牛云/oss开关信息
        $assign_data['qiniu_open'] = 0;
        $assign_data['oss_open'] = 0;
        $assign_data['cos_open'] = 0;
        $assign_data['aws_open'] = 0;
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        if(!empty($channelRow)){
            $channelRow['data'] = json_decode($channelRow['data'], true);
            $assign_data['qiniu_open'] = !empty($channelRow['data']['qiniuyun_open']) ? $channelRow['data']['qiniuyun_open'] : 0;
            $assign_data['oss_open'] = !empty($channelRow['data']['oss_open']) ? $channelRow['data']['oss_open'] : 0;
            $assign_data['cos_open'] = !empty($channelRow['data']['cos_open']) ? $channelRow['data']['cos_open'] : 0;
            $assign_data['aws_open'] = !empty($channelRow['data']['aws_open']) ? $channelRow['data']['aws_open'] : 0;
        }
        $assign_data['channelRow'] = $channelRow;

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($typeid, array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = 'view_'.$this->nid.'.'.config('template.view_suffix');
        !empty($arctypeInfo['tempview']) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        /*会员等级信息*/
        $assign_data['users_level'] = model('UsersLevel')->getList('level_id,level_name');
        /*--end*/

        /*下载模型自定义属性字段*/
        $attr_field = Db::name('download_attr_field')->select();
        $servername_use = 0;
        if ($attr_field) {
            $servername_info = [];
            for ($i = 0; $i < count($attr_field); $i++) {
                if ($attr_field[$i]['field_name'] == 'server_name') {
                    if ($attr_field[$i]['field_use'] == 1) {
                        $servername_use = 1;
                    }
                    $servername_info = $attr_field[$i];
                    break;
                }
            }
            $assign_data['servername_info'] = $servername_info;
        }
        $assign_data['attr_field'] = $attr_field;
        $assign_data['servername_use'] = $servername_use;

        $servername_arr = unserialize(tpCache('global.download_select_servername'));
        $assign_data['default_servername'] = $servername_arr?$servername_arr[0]:'立即下载';
        /*--end*/

        // 文档默认浏览量 / 软件默认下载量
        $globalConfig = tpCache('global');
        if (isset($globalConfig['other_arcclick']) && 0 <= $globalConfig['other_arcclick']) {
            $arcclick_arr = explode("|", $globalConfig['other_arcclick']);
            if (count($arcclick_arr) > 1) {
                $assign_data['rand_arcclick'] = mt_rand($arcclick_arr[0], $arcclick_arr[1]);
            } else {
                $assign_data['rand_arcclick'] = intval($arcclick_arr[0]);
            }
        }else{
            $arcclick_config['other_arcclick'] = '500|1000';
            tpCache('other', $arcclick_config);
            $assign_data['rand_arcclick'] = mt_rand(500, 1000);
        }

        if (isset($globalConfig['other_arcdownload']) && 0 <= $globalConfig['other_arcdownload']) {
            $arcdownload_arr = explode("|", $globalConfig['other_arcdownload']);
            if (count($arcdownload_arr) > 1) {
                $assign_data['rand_arcdownload'] = mt_rand($arcdownload_arr[0], $arcdownload_arr[1]);
            } else {
                $assign_data['rand_arcdownload'] = intval($arcdownload_arr[0]);
            }
        }else{
            $arcdownload_config['other_arcdownload'] = '100|500';
            tpCache('other', $arcdownload_config);
            $assign_data['rand_arcdownload'] = mt_rand(100, 500);
        }

        /*文档属性*/
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;
        // 系统最大上传视频的大小  限制类型
        $file_size = tpCache('global.file_size');
        $postsize       = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size = strval($file_size) < strval($postsize) ? $file_size : $postsize;
        $min_size = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
        $basic['file_size'] = intval($min_size) * 1024 * 1024;
        $file_type = tpCache('global.file_type');
        $basic['file_type'] = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['basic'] = $basic;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_0'] = !empty($system_originlist) ? $system_originlist[0] : "";
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);

        // 多站点，当用站点域名访问后台，发布文档自动选择当前所属区域
        model('Citysite')->auto_location_select($assign_data);

        $this->assign($assign_data);

        return $this->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit()
    {
        $admin_info = session('admin_info');
        $auth_role_info = $admin_info['auth_role_info'];
        $this->assign('auth_role_info', $auth_role_info);
        $this->assign('admin_info', $admin_info);

        if (IS_POST) {
            $post = input('post.');
            model('Archives')->editor_auto_210607($post);
            $post['aid'] = intval($post['aid']);

            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $typeid = input('post.typeid/d', 0);
            $content = empty($post['addonFieldExt']['content']) ? '' : htmlspecialchars_decode($post['addonFieldExt']['content']);
            if (!empty($post['fileupload'])){
                foreach ($post['fileupload']['file_url'] as $k => $v){
                    if (is_http_url($v)){
                        $post['fileupload']['uhash'][$k] = md5($v);
                    }
                }
            }
            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $litpic = $post['litpic'];
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = !empty($post['is_litpic']) ? $post['is_litpic'] : 0; // 有封面图
            }

            // 勾选后SEO描述将随正文内容更新
            $basic_update_seo_description = empty($post['basic_update_seo_description']) ? 0 : 1;
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('basic', ['basic_update_seo_description'=>$basic_update_seo_description], $val['mark']);
                }
            } else {
                tpCache('basic', ['basic_update_seo_description'=>$basic_update_seo_description]);
            }
            /*--end*/

            // SEO描述
            $seo_description = '';
            if (!empty($basic_update_seo_description) || empty($post['seo_description'])) {
                $seo_description = @msubstr(checkStrHtml($content), 0, get_seo_description_length(), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // --外部链接
            $jumplinks = '';
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            $htmlfilename = trim($post['htmlfilename']);
            if (!empty($htmlfilename)) {
                $htmlfilename = preg_replace("/[^\x{4e00}-\x{9fa5}\w\-]+/u", "-", $htmlfilename);
                // $htmlfilename = strtolower($htmlfilename);
                //判断是否存在相同的自定义文件名
                $map = [
                    'aid'   => ['NEQ', $post['aid']],
                    'htmlfilename'  => $htmlfilename,
                    'lang'  => $this->admin_lang,
                ];
                if (!empty($post['typeid'])) {
                    $map['typeid'] = array('eq', $post['typeid']);
                }
                $filenameCount = Db::name('archives')->where($map)->count();
                if (!empty($filenameCount)) {
                    $this->error("同栏目下，自定义文件名已存在！");
                } else if (preg_match('/^(\d+)$/i', $htmlfilename)) {
                    $this->error("自定义文件名不能纯数字，会与文档ID冲突！");
                }
            } else {
                // 处理外贸链接
                if (is_dir('./weapp/Waimao/')) {
                    $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
                    $waimaoLogic->get_new_htmlfilename($htmlfilename, $post, 'edit', $this->globalConfig);
                } else {
                    $foreignLogic = new \app\admin\logic\ForeignLogic;
                    $foreignLogic->get_new_htmlfilename($htmlfilename, $post, 'edit', $this->globalConfig);
                }
            }
            $post['htmlfilename'] = $htmlfilename;

            // 同步栏目切换模型之后的文档模型
            $channel = Db::name('arctype')->where(['id'=>$typeid])->getField('current_channel');

            //做未通过审核文档不允许修改文档状态操作
            if ($admin_info['role_id'] > 0 && $auth_role_info['check_oneself'] < 1) {
                $old_archives_arcrank = Db::name('archives')->where(['aid' => $post['aid']])->getField("arcrank");
                if ($old_archives_arcrank < 0) {
                    unset($post['arcrank']);
                }
            }

            // 副栏目
            if (isset($post['stypeid'])) {
                $post['stypeid'] = preg_replace('/([^\d\,\，]+)/i', ',', $post['stypeid']);
                $post['stypeid'] = str_replace('，', ',', $post['stypeid']);
                $post['stypeid'] = trim($post['stypeid'], ',');
                $post['stypeid'] = str_replace(",{$typeid},", ',', ",{$post['stypeid']},");
                $post['stypeid'] = trim($post['stypeid'], ',');
            }

            // 付费限制模式与之前三个字段 arc_level_id、 users_price、 no_vip_pay 组合逻辑兼容
            $restricData = restric_type_logic($post, $this->channeltype);
            if (isset($restricData['code']) && empty($restricData['code'])) {
                $this->error($restricData['msg']);
            }

            // --存储数据
            $newData = array(
                'typeid'=> $typeid,
                'channel'   => $channel,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'      => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'      => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'   => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                'add_time'     => strtotime($post['add_time']),
                'update_time'     => getTime(),
            );
            $data = array_merge($post, $newData);
            $r = Db::name('archives')->where([
                    'aid'   => $data['aid'],
                    'lang'  => $this->admin_lang,
                ])->update($data);
            
            if ($r !== false) {
                // ---------后置操作
                model('Download')->afterSave($data['aid'], $data, 'edit');
                // ---------end
                adminLog('编辑下载：'.$data['title']);

                $_POST['aid'] = $data['aid'];
                // 生成静态页面代码
                $successData = [
                    'aid'       => $data['aid'],
                    'tid'       => $typeid,
                ];
                $this->success("操作成功!", null, $successData);
                exit;
            }

            $this->error("操作失败!");
            exit;
        }

        $assign_data = array();

        $id = input('id/d');
        $info = model('Download')->getInfo($id);
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        /*兼容采集没有归属栏目的文档*/
        if (empty($info['channel'])) {
            $channelRow = Db::name('channeltype')->field('id as channel')
                ->where('id',$this->channeltype)
                ->find();
            $info = array_merge($info, $channelRow);
        }
        /*--end*/
        $typeid = $info['typeid'];
        $assign_data['typeid'] = $typeid;
        
        // 副栏目
        $stypeid_arr = [];
        if (!empty($info['stypeid'])) {
            $info['stypeid'] = trim($info['stypeid'], ',');
            $stypeid_arr = Db::name('arctype')->field('id,typename')->where(['id'=>['IN', $info['stypeid']],'is_del'=>0])->select();
        }
        $assign_data['stypeid_arr'] = $stypeid_arr;

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        $info['channel'] = $arctypeInfo['current_channel'];
        // 封面图
        $info['litpic'] = handle_subdir_pic($info['litpic']);

        $assign_data['field'] = $info;

        //第三方存储空间 七牛云/oss开关信息
        $assign_data['qiniu_open'] = 0;
        $assign_data['oss_open'] = 0;
        $assign_data['oss_open'] = 0;
        $assign_data['aws_open'] = 0;
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        if(!empty($channelRow)){
            $channelRow['data'] = json_decode($channelRow['data'], true);
            $assign_data['qiniu_open'] = !empty($channelRow['data']['qiniuyun_open']) ? $channelRow['data']['qiniuyun_open'] : 0;
            $assign_data['oss_open'] = !empty($channelRow['data']['oss_open']) ? $channelRow['data']['oss_open'] : 0;
            $assign_data['cos_open'] = !empty($channelRow['data']['cos_open']) ? $channelRow['data']['cos_open'] : 0;
            $assign_data['aws_open'] = !empty($channelRow['data']['aws_open']) ? $channelRow['data']['aws_open'] : 0;
        }
        $assign_data['channelRow'] = $channelRow;

        // 下载文件
        $downfile_list = model('DownloadFile')->getDownFile($id);
        $assign_data['downfile_list'] = $downfile_list;

        // 下载文件中是否存在远程链接
        $is_remote_file = 0;
        foreach ($downfile_list as $key => $value) {
            if (1 == $value['is_remote']) {
                $is_remote_file = 1;
                break;
            }
        }
        $assign_data['is_remote_file'] = $is_remote_file;

        /*允许发布文档列表的栏目，文档所在模型以栏目所在模型为主，兼容切换模型之后的数据编辑*/
        $arctype_html = allow_release_arctype($typeid, array($info['channel']));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        $assign_data['arcrank_list'] = $arcrank_list;

        /*模板列表*/
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList = $archivesLogic->getTemplateList($this->nid);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*默认模板文件*/
        $tempview = $info['tempview'];
        empty($tempview) && $tempview = $arctypeInfo['tempview'];
        $this->assign('tempview', $tempview);
        /*--end*/

        /*会员等级信息*/
        $assign_data['users_level'] = model('UsersLevel')->getList('level_id,level_value,level_name');
        /*--end*/

        /*下载模型自定义属性字段*/
        $attr_field = Db::name('download_attr_field')->select();
        $servername_use = 0;
        if ($attr_field) {
            $servername_info = [];
            for ($i = 0; $i < count($attr_field); $i++) {
                if ($attr_field[$i]['field_name'] == 'server_name') {
                    if ($attr_field[$i]['field_use'] == 1) {
                        $servername_use = 1;
                    }
                    $servername_info = $attr_field[$i];
                    break;
                }
            }
            $assign_data['servername_info'] = $servername_info;
        }
        $assign_data['attr_field'] = $attr_field;
        $assign_data['servername_use'] = $servername_use;

        $servername_arr = unserialize(tpCache('global.download_select_servername'));
        $assign_data['default_servername'] = $servername_arr?$servername_arr[0]:'立即下载';
        /*--end*/

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        /*文档属性*/
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();

        // 系统最大上传视频的大小  限制类型
        $file_size = tpCache('global.file_size');
        $postsize       = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size = strval($file_size) < strval($postsize) ? $file_size : $postsize;
        $min_size = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
        $basic['file_size'] = intval($min_size) * 1024 * 1024;
        $file_type = tpCache('global.file_type');
        $basic['file_type'] = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['basic'] = $basic;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);

        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 删除
     */
    public function del()
    {
        if (IS_POST) {
            $archivesLogic = new \app\admin\logic\ArchivesLogic;
            $archivesLogic->del([], 0, 'download');
        }
    }

    public function template_set()
    {

        if (IS_AJAX_POST) {
            $post = input('post.');

            // 修改是否使用
            if (!empty($post) && isset($post['field_use'])) {
                $data['field_use']   = $post['field_use'];
                $data['update_time'] = getTime();
                Db::name('download_attr_field')->where('field_id',$post['field_id'])->update($data);
                $this->success("更新成功！");
            }
            // 修改标题
            if (!empty($post) && isset($post['field_title'])) {
                $data['field_title'] = $post['field_title'];
                $data['update_time'] = getTime();
                Db::name('download_attr_field')->where('field_id',$post['field_id'])->update($data);
                $this->success("更新成功！");
            }
        }

        $list = Db::name('download_attr_field')->select();
        $assign_data['list'] = $list;
        $this->assign($assign_data);
        return $this->fetch();
    }

    public function select_servername()
    {
        $servername_arr = unserialize(tpCache('global.download_select_servername'));
        $param = input('param.');
        $assign_data['select_servername'] = $servername_arr;
        $assign_data['file_key'] = $param['file_key'];
        $assign_data['sn_type'] = $param['sn_type'];
        $assign_data['sn_name_sub'] = $param['sn_name_sub'];
        $this->assign($assign_data);
        return $this->fetch();
    }

    public function set_servername()
    {
        if (IS_AJAX_POST) {
            $post = input('param.');
            $servernames = htmlspecialchars($post['servername']);
            $servernames = str_replace("\r\n", "\n", trim($servernames));
            $servernames = explode("\n", $servernames);

            $servernames_new = array_unique($servernames);
            $servernames_new = serialize($servernames_new);
            $param = ['download_select_servername'=>$servernames_new];

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('download', $param, $val['mark']);
                }
            } else {
                tpCache('download', $param);
            }
            /*--end*/

            $this->success("更新成功！");
        }

        $servername_arr = unserialize(tpCache('global.download_select_servername'));
        $servername_arr = implode("\n",$servername_arr);

        $assign_data['servernames'] = $servername_arr;
        $this->assign($assign_data);
        return $this->fetch();
    }

    public function search_servername()
    {
        if (IS_AJAX_POST) {
            $post = input('param.');
            $keyword = $post['keyword'];

            $servernames = unserialize(tpCache('global.download_select_servername'));

            $search_data = $servernames;
            if (!empty($keyword)) {
                $search_data = [];
                if ($servernames) {
                    foreach ($servernames as $k => $v) {
                        if (preg_match("/$keyword/s", $v)) $search_data[] = $v;
                    }
                }
            }

            $this->success("获取成功",null,$search_data);
        }
    }

    public function get_template()
    {
        if (IS_AJAX_POST) {
            //$list = Db::name('download_attr_field')->where('field_use',1)->select();
            $list = Db::name('download_attr_field')->select();
            $this->success("查询成功！", null, $list);
        }
    }

    /**
     * 获取七牛云token
     */
/*    public function qiniu_upload()
    {
        if (IS_AJAX_POST) {
            $weappInfo     = Db::name('weapp')->where('code','Qiniuyun')->field('id,status,data')->find();
            if (empty($weappInfo)) {
                $this->error('请先安装配置【七牛云图片加速】插件!', null, ['code'=>-1]);
            } else if (1 != $weappInfo['status']) {
                $this->error('请先启用【七牛云图片加速】插件!', null, ['code'=>-2,'id'=>$weappInfo['id']]);
            } else {
                $Qiniuyun = json_decode($weappInfo['data'], true);
                if (empty($Qiniuyun)) {
                    $this->error('请先配置【七牛云图片加速】插件!', null, ['code'=>-3]);
                } else if (empty($Qiniuyun['domain'])) {
                    $this->error('请先配置【七牛云图片加速】插件中的域名!', null, ['code'=>-3]);
                }
            }

            //引入七牛云的相关文件
            weapp_vendor('Qiniu.src.Qiniu.Auth', 'Qiniuyun');
            weapp_vendor('Qiniu.src.Qiniu.Storage.UploadManager', 'Qiniuyun');
            require_once ROOT_PATH.'weapp/Qiniuyun/vendor/Qiniu/autoload.php';

            // 配置信息
            $accessKey = $Qiniuyun['access_key'];
            $secretKey = $Qiniuyun['secret_key'];
            $bucket    = $Qiniuyun['bucket'];
            $domain    = '//'.$Qiniuyun['domain'];

            // 区域对应的上传URl
            $config = new \Qiniu\Config(null);
            $uphost  = $config->getUpHost($accessKey, $bucket);
            $uphost = str_replace('http://', '//', $uphost);

            // 生成上传Token
            $auth = new \Qiniu\Auth($accessKey, $secretKey);
            $token = $auth->uploadToken($bucket);
            if ($token) {
                $filePath = UPLOAD_PATH.'soft/';
//                $filePath = UPLOAD_PATH.'soft/' . date('Ymd/') . session('admin_id') . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
                $data = [
                    'token'  => $token,
                    'domain'  => $domain,
                    'uphost'  => $uphost,
                    'filePath'  => $filePath,
                ];
                $this->success('获取token成功!', null, $data);
            } else {
                $this->error('获取token失败!');
            }
        }

    }*/
    //帮助
    public function help()
    {
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);
        $this->assign($assign_data);
    
        return $this->fetch();
    }
}