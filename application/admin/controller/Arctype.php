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
use app\common\logic\ArctypeLogic;
use app\admin\logic\FieldLogic;

class Arctype extends Base
{
    public $fieldLogic;
    public $arctypeLogic;
    // 栏目对应模型ID
    public $arctype_channel_id = '';
    // 允许发布文档的模型ID
    public $allowReleaseChannel = array();
    // 禁用的目录名称
    public $disableDirname = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->fieldLogic          = new FieldLogic();
        $this->arctypeLogic        = new ArctypeLogic();
        $this->allowReleaseChannel = config('global.allow_release_channel');
        $this->arctype_channel_id  = config('global.arctype_channel_id');
        $this->disableDirname      = config('global.disable_dirname');
        $this->arctype_db  = Db::name('arctype');
        $this->archives = Db::name('archives');

        /*兼容每个用户的自定义字段，重新生成数据表字段缓存文件*/
        $arctypeFieldInfo = include DATA_PATH . 'schema/ey_arctype.php';
        foreach (['weapp_code'] as $key => $val) {
            if (!isset($arctypeFieldInfo[$val])) {
                try {
                    schemaTable('arctype');
                } catch (\Exception $e) {}
                break;
            }
        }
        /*--end*/
    }

    public function index()
    {
        $arctype_list = array();
        // 目录列表
        $where['is_del'] = '0'; // 回收站功能
        $arctype_list = $this->arctypeLogic->arctype_list(0, 0, false, 0, $where, false);
        /*foreach ($arctype_list as $key => $val) {
            if ($val['current_channel'] == 51 && 2 > $this->php_servicemeal && empty($val['has_children'])) {
                unset($arctype_list[$key]);
                continue;
            } else if ($val['current_channel'] == 5 && 1.5 > $this->php_servicemeal) {
                unset($arctype_list[$key]);
                continue;
            }
        }*/
        $this->assign('arctype_list', $arctype_list);

        /*多语言模式下，栏目ID显示主体语言的ID和属性title名称*/
        $main_arctype_list = [];
        if ($this->admin_lang != $this->main_lang && empty($this->globalConfig['language_split'])) {
            $attr_values = get_arr_column($arctype_list, 'id');
            $languageAttrRow = Db::name('language_attr')->field('attr_name,attr_value')->where([
                    'attr_value'    => ['IN', $attr_values],
                    'attr_group'    => 'arctype',
                    'lang'          => $this->admin_lang,
                ])->getAllWithIndex('attr_value');
            $typeids = [];
            foreach ($languageAttrRow as $key => $val) {
                $tid_tmp = str_replace('tid', '', $val['attr_name']);
                array_push($typeids, intval($tid_tmp));
            }
            $main_ArctypeRow = Db::name('arctype')->field("id,typename,CONCAT('tid', id) AS attr_name")
                ->where([
                    'id'    => ['IN', $typeids],
                    'lang'  => $this->main_lang,
                ])->getAllWithIndex('attr_name');
            foreach ($arctype_list as $key => $val) {
                $key_tmp = !empty($languageAttrRow[$val['id']]['attr_name']) ? $languageAttrRow[$val['id']]['attr_name'] : '';
                $main_arctype_list[$val['id']] = [
                    'id'        => !empty($main_ArctypeRow[$key_tmp]['id']) ? $main_ArctypeRow[$key_tmp]['id'] : '',
                    'typename'  => !empty($main_ArctypeRow[$key_tmp]['typename']) ? $main_ArctypeRow[$key_tmp]['typename'] : '',
                ];
            }
        }
        $this->assign('main_arctype_list', $main_arctype_list);
        /*end*/

        // 模型列表
        $channeltype_list = getChanneltypeList();
        $this->assign('channeltype_list', $channeltype_list);

        // 栏目最多级别
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $this->assign('arctype_max_level', $arctype_max_level);
        
        /* 生成静态页面代码 */
        $typeid = input('param.typeid/d',0);
        $is_del = input('param.is_del/d',0);
        $handle = input('param.handle/s','');
        $this->assign('typeid',$typeid);
        $this->assign('is_del',$is_del);
        $seo_pseudo = tpCache('global.seo_pseudo');//URL模式
        $sitemap_zzbaidutoken = config('tpcache.sitemap_zzbaidutoken');//实时推送Url的token
        $bdminipro = Db::name('weapp')->where(['code'=>'BdDiyminipro','status'=>1])->find();
        $typeurl = '';
        if (!empty($typeid)){
            $typeData = Db::name('arctype')->where(['id'=>$typeid,'is_hidden'=>0,'status'=>1,'is_del'=>0])->find();
            if (!empty($typeData)){
                $typeurl = get_typeurl($typeData);
            }
        }
        $eyou = [
            'handle'=>$handle,
            'typeurl'=>$typeurl,
            'zzbaidutoken'=>$sitemap_zzbaidutoken,
            'bdminipro'=>$bdminipro,
            'seo_pseudo'=>$seo_pseudo
        ];
        $this->assign('eyou',$eyou);
        /* end */

        /*获取所有有子栏目的栏目id*/
        $parent_ids = Db::name('arctype')->where([
                'parent_id' => ['gt', 0],
                'is_del'    => 0,
            ])->group('parent_id')->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->column('parent_id');
        $cookied_treeclicked =  json_decode(cookie('admin-treeClicked-Arr'));
        empty($cookied_treeclicked) && $cookied_treeclicked = [];
        $all_treeclicked = cookie('admin-treeClicked_All');
        empty($all_treeclicked) && $all_treeclicked = [];
        $tree = [
            'has_children'=>!empty($parent_ids) ? 1 : 0,
            'parent_ids'=>json_encode($parent_ids),
            'all_treeclicked'=>$all_treeclicked,
            'cookied_treeclicked'=>$cookied_treeclicked,
            'cookied_treeclicked_arr'=>json_encode($cookied_treeclicked),
        ];
        $this->assign('tree', $tree);
        /* end */

        return $this->fetch();
    }
    
    /**
     * 新增
     */
    public function add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        $globalConfig = tpCache('global');
        $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
        $seo_html_arcdir = !empty($globalConfig['seo_html_arcdir']) ? $globalConfig['seo_html_arcdir'] : '';
        $seo_html_listname = !empty($globalConfig['seo_html_listname']) ? $globalConfig['seo_html_listname'] : 2;
        $seo_html_pagename = !empty($globalConfig['seo_html_pagename']) ? $globalConfig['seo_html_pagename'] : 2;
        $is_diyseo_htmlpath = 0; // 是否自定义生成静态页面的规则
        if (2 == $seo_pseudo && (4 == $seo_html_listname || 4 == $seo_html_pagename)) {
            $is_diyseo_htmlpath = 1;
        }

        if (IS_POST) {
            $post = input('post.');
            if ($post) {
                // 问答模型只能存在一个
                if (51 == $post['current_channel']){
                    $ask_info = Db::name('arctype')->where(['current_channel' => $post['current_channel'], 'is_del' => 0])->order('id desc')->find();
                    if (!empty($ask_info)){
                        $this->error('问答模型只能创建一个栏目!');
                    }
                }
                /*目录名称*/
                $post['typename'] = str_replace('\\', '/', $post['typename']);
                $post['typename'] = addslashes(htmlspecialchars(strip_tags(strip_sql($post['typename']))));
                $post['typename'] = str_replace(["\'","&amp;"], ["'","&"], $post['typename']);
                $post['dirname'] = preg_replace('/([^\w\-])/i', '', $post['dirname']);
                $dirname = $this->arctypeLogic->get_dirname($post['typename'], $post['dirname']);
                // 检测
                if (!empty($post['dirname']) && !$this->arctypeLogic->dirname_unique($post['dirname'])) {
                    $arctype_is_del = Db::name('arctype')->where(['dirname'=>$post['dirname'], 'lang'=>$this->admin_lang])->value('is_del');
                    if (empty($arctype_is_del)) {
                        $this->error('目录名称与系统内置冲突，请更改！');
                    } else {
                        $this->error('目录名称与回收站里的栏目冲突，请更改！');
                    }
                }
                /*--end*/
                $dirpath = rtrim($post['dirpath'],'/');
                $dirpath = $dirpath . '/' . $dirname;
                $diy_dirpath = '';
                if (1 == $is_diyseo_htmlpath) { // 自定义静态命名规则
                    if (!empty($post['diy_dirpath'])) {
                        $diy_dirpath = '/'.trim($post['diy_dirpath'],'/');
                    }
                } else {
                    $diy_dirpath = $dirpath;
                }
                $typelink = !empty($post['is_part']) ? $post['typelink'] : '';
                $post['target'] = !empty($post['target']) ? 1 : 0;
                $post['nofollow'] = !empty($post['nofollow']) ? 1 : 0;
                $post['page_limit'] = empty($post['page_limit']) ? '' : implode(',',$post['page_limit']);
                /*封面图的本地/远程图片处理*/
                $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
                $litpic = '';
                if ($is_remote == 1) {
                    $litpic = $post['litpic_remote'];
                } else {
                    $litpic = $post['litpic_local'];
                }
                /*--end*/
                // 获取顶级模型ID
                if (empty($post['parent_id'])) {
                    $channeltype = $post['current_channel'];
                    $topid = 0;
                } else {
                    $parentInfo = Db::name('arctype')->field('id,channeltype,topid')->where('id', $post['parent_id'])->find();
                    $channeltype = $parentInfo['channeltype'];
                    $topid = !empty($parentInfo['topid']) ? $parentInfo['topid'] : $parentInfo['id'];
                }
                /*SEO描述*/
                $seo_description = $post['seo_description'];
                /*--end*/
                /*处理自定义字段值*/
                $addonField = array();
                if (!empty($post['addonField'])) {
                    $addonField = $this->fieldLogic->handleAddonField($this->arctype_channel_id, $post['addonField']);
                }
                /*--end*/
                // 列表/文档命名规则
                $post['rulelist'] = trim($post['rulelist']);
                $post['ruleview'] = trim($post['ruleview']);

                $newData = array(
                    'topid' => $topid,
                    'dirname' => $dirname,
                    'dirpath'   => $dirpath,
                    'diy_dirpath'   => $diy_dirpath,
                    'typelink' => $typelink,
                    'litpic'    => $litpic,
                    'channeltype'   => $channeltype,
                    'current_channel' => $post['current_channel'],
                    'seo_keywords' => str_replace('，', ',', $post['seo_keywords']),
                    'seo_description' => $seo_description,
                    'admin_id'  => session('admin_info.admin_id'),
                    'empty_logic' => ($post['current_channel'] == 6) ? 0 : 1,
                    'lang'  => $this->admin_lang,
                    'sort_order'    => 100,
                    'add_time'  => getTime(),
                    'update_time'  => getTime(),
                );
                $data = array_merge($post, $newData, $addonField);
                $insertId = model('Arctype')->addData($data);
                if(false !== $insertId){
                    $_POST['id'] = $_POST['typeid'] = $insertId;
                    
                    // 删除多余的问答栏目
                    if (51 == $post['current_channel']){
                        Db::name('arctype')->where(['current_channel' => $post['current_channel'], 'id' => ['NEQ', $insertId]])->delete();
                    }
                    
                    /*同步栏目ID到多语言的模板栏目变量里*/
                    $this->arctypeLogic->syn_add_language_attribute($insertId);
                    /*--end*/

                    adminLog('新增栏目：'.$data['typename']);

                    // 生成静态页面代码
                    $this->success("操作成功!", url('Arctype/index', ['typeid'=>$insertId,'handle'=>'add']));
                }
            }
            $this->error("操作失败!");
        }

        $assign_data = array();

        /* 模型 */
        $map = array(
            'status'    => 1,
        );
        $channeltype_list = model('Channeltype')->getAll('id,title,nid', $map, 'id');
        $this->assign('channeltype_list', $channeltype_list);

        // 新增栏目在指定的上一级栏目下
        $parent_id = input('param.parent_id/d');
        $grade = 0;
        $current_channel = '';
        $predirpath = ''; // 生成静态页面代码
        $ptypename = '';
        if (0 < $parent_id) {
            $info = Db::name('arctype')->where(array('id'=>$parent_id))->find();
            if ($info) {
                // 级别
                $grade = $info['grade'] + 1;
                // 菜单对应下的栏目
                // $selected = $info['id'];
                // 模型
                $current_channel = $info['current_channel'];
                // 上级目录
                $predirpath = $info['dirpath'];
                if (2 == $this->globalConfig['seo_pseudo'] && 4 == $this->globalConfig['seo_html_listname']) {
                    $predirpath = $info['diy_dirpath'];
                } else {
                    $predirpath = $info['dirpath'];
                }
                // 上级栏目名称
                $ptypename = $info['typename'];
            }
        }
        $this->assign('predirpath', $predirpath);
        $this->assign('parent_id', $parent_id);
        $this->assign('ptypename', $ptypename);
        $this->assign('grade',$grade);
        $this->assign('current_channel',$current_channel);
        $this->assign('rulelist', "{栏目目录}/list_{tid}_{page}.html");
        $this->assign('ruleview', "{栏目目录}/{aid}.html");
        
        /*发布文档的模型ID，用于是否显示文档模板列表*/
        $js_allow_channel_arr = '[';
        foreach ($this->allowReleaseChannel as $key => $val) {
            if (51 == $val) continue; // 问答模型
            if ($key > 0) {
                $js_allow_channel_arr .= ',';
            }
            $js_allow_channel_arr .= $val;
        }
        $js_allow_channel_arr = $js_allow_channel_arr.']';
        $this->assign('js_allow_channel_arr', $js_allow_channel_arr);
        /*--end*/

        // 所属栏目
        $select_html = '<option value="0" data-grade="-1" data-dirpath="">顶级栏目</option>';
        $selected = $parent_id;
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $arctypeWhere = ['is_del'=>0];
        $options = $this->arctypeLogic->arctype_list(0, $selected, false, $arctype_max_level - 1, $arctypeWhere);
        foreach ($options AS $var)
        {
            $select_html .= '<option value="' . $var['id'] . '" data-grade="' . $var['grade'] . '"';
            $select_html .= ' data-dirpath="'.$var['dirpath'].'" ';
            if (2 == $this->globalConfig['seo_pseudo'] && 4 == $this->globalConfig['seo_html_listname']) {
                $select_html .= ' data-dirpath="'.$var['diy_dirpath'].'" ';
            } else {
                $select_html .= ' data-dirpath="'.$var['dirpath'].'" ';
            }
            $select_html .= ($selected == $var['id']) ? " selected='true' " : '';
            $select_html .= '>';
            if ($var['level'] > 0)
            {
                $select_html .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select_html .= htmlspecialchars_decode(addslashes($var['typename'])) . '</option>';
        }
        $this->assign('select_html',$select_html);

        /*模板列表*/
        $templateList = $this->ajax_getTemplateList('add');
        $this->assign('templateList', $templateList);
        /*--end*/

        /*自定义字段*/
//        $assign_data['addonFieldExtList'] = model('Field')->getTabelFieldList(config('global.arctype_channel_id'));
        $assign_data['aid'] = 0;
        $assign_data['channeltype'] = 6;
        $assign_data['nid'] = 'arctype';
        /*--end*/
        //用于处理编辑器自动远程本地化 开始
        $editor_addonFieldExt = [];
//        foreach ($assign_data['addonFieldExtList'] as $key => $val) {
//            if ($val['dtype'] == 'htmltext') {
//                if (empty($first_html)){
//                    $editor = tpSetting('editor');
//                    $assign_data['addonFieldExtList'][$key]['editor'] = $editor;
//                    $assign_data['addonFieldExtList'][$key]['first'] = 1;
//                    $first_html = 1;
//                }
//                $editor_addonFieldExt[] = $val['name'];
//            }
//        }
        $editor_addonFieldExt = implode(",",$editor_addonFieldExt);
        $assign_data['editor_addonFieldExt'] = $editor_addonFieldExt;
        //用于处理编辑器自动远程本地化 结束
        //url模式
        $assign_data['seo_pseudo'] = $seo_pseudo;
        $assign_data['seo_html_arcdir'] = $seo_html_arcdir;
        $assign_data['seo_html_listname'] = $seo_html_listname;
        $assign_data['seo_html_pagename'] = $seo_html_pagename;
        $assign_data['is_diyseo_htmlpath'] = $is_diyseo_htmlpath;

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        foreach ($arcrank_list as $key=>$val){
            if ($val['rank'] < 0){
                unset($arcrank_list[$key]);
            }
        }
        $assign_data['arcrank_list'] = $arcrank_list;

        $this->assign($assign_data);
        return $this->fetch();
    }
    /**
     * 递归查新是否是子集
     * Author: 非洲和尚
     * @param  [type]  $fid [父id] $id [查询id]
     */
    private function _sonArctype($fid, $id)
    {
        // 查询当前id的直接子节点
        $children = Db::name('arctype')->where('parent_id', $fid)->column('id');
        if (in_array($id, $children)) {
            return true;
        }
        foreach ($children as $child) {
            if ($this->_sonArctype($child, $id)) {
                return true;
            }
        }
        return false;
    }
    /**
     * 编辑
     */
    public function edit()
    {
        $globalConfig = tpCache('global');
        $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
        $seo_html_arcdir = !empty($globalConfig['seo_html_arcdir']) ? $globalConfig['seo_html_arcdir'] : '';
        $seo_html_listname = !empty($globalConfig['seo_html_listname']) ? $globalConfig['seo_html_listname'] : 2;
        $seo_html_pagename = !empty($globalConfig['seo_html_pagename']) ? $globalConfig['seo_html_pagename'] : 2;
        $is_diyseo_htmlpath = 0; // 是否自定义生成静态页面的规则
        if (2 == $seo_pseudo && (4 == $seo_html_listname || 4 == $seo_html_pagename)) {
            $is_diyseo_htmlpath = 1;
        }
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post['id'])) {
                $post['id'] = intval($post['id']);
                $post['typename'] = str_replace('\\', '/', $post['typename']);
                $post['typename'] = addslashes(htmlspecialchars(strip_tags(strip_sql($post['typename']))));
                $post['typename'] = str_replace(["\'","&amp;"], ["'","&"], $post['typename']);
                $post['dirname'] = preg_replace('/([^\w\-])/i', '', $post['dirname']);
                //问答模型只能存在一个
                if (51 == $post['current_channel']){
                    $ask_info = Db::name('arctype')->where(['current_channel'=>$post['current_channel'], 'id'=>['NEQ', $post['id']], 'is_del'=>0])->find();
                    if (!empty($ask_info)){
                        $this->error('问答模型只能存在一个栏目，请删除!');
                    }
                }

                /*自己的上级不能是自己*/
                if (intval($post['id']) == intval($post['parent_id'])) {
                    $this->error('自己不能成为自己的上级栏目');
                }
                /*--end*/
                $arctypeData = Db::name('arctype')->field('parent_id,topid')->where('id',$post['id'])->find();
                if($arctypeData['parent_id']>0){
                    if($this->_sonArctype($post['id'], $post['parent_id'])!==false){
                       $this->error('上级不能成为自己下级的栏目');  
                    }
                }else{// 顶级                    
                    if(Db::name('arctype')->where(['topid'=>$post['id'],'id'=>$post['parent_id']])->find()){
                        $this->error('上级不能成为自己下级的栏目');   
                    }  
                }                      
                /*目录名称*/
                $post['dirname'] = func_preg_replace([' ', '　'], '', $post['dirname']);
                $dirname         = $this->arctypeLogic->get_dirname($post['typename'], $post['dirname'], $post['id']);
                // 检测
                if (!empty($post['dirname']) && !$this->arctypeLogic->dirname_unique($post['dirname'], $post['id'])) {
                    $arctype_is_del = Db::name('arctype')->where(['dirname'=>$post['dirname'], 'lang'=>$this->admin_lang])->value('is_del');
                    if (empty($arctype_is_del)) {
                        $this->error('目录名称与系统内置冲突，请更改！');
                    } else {
                        $this->error('目录名称与回收站里的栏目冲突，请更改！');
                    }
                }
                /*--end*/
                $dirpath  = rtrim($post['dirpath'], '/');
                $dirpath = $dirpath . '/' . $dirname;
                $diy_dirpath = '';
                if (!empty($post['diy_dirpath'])) {
                    $diy_dirpath = '/'.trim($post['diy_dirpath'],'/');
                    if (1 == $is_diyseo_htmlpath) { // 自定义静态命名规则
                        $dirpath = $diy_dirpath;
                    }
                }
                $typelink = !empty($post['is_part']) ? $post['typelink'] : '';
                $post['target'] = !empty($post['target']) ? 1 : 0;
                $post['nofollow'] = !empty($post['nofollow']) ? 1 : 0;
                $post['page_limit'] = empty($post['page_limit']) ? '' : implode(',',$post['page_limit']);
                /*封面图的本地/远程图片处理*/
                $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
                $litpic    = '';
                if ($is_remote == 1) {
                    $litpic = $post['litpic_remote'];
                } else {
                    $litpic = $post['litpic_local'];
                }
                /*--end*/
                // 顶级栏目ID
                $topid = 0;
                // 最顶级模型ID
                $channeltype = $post['channeltype'];
                // 当前更改的等级
                $grade = $post['grade'];
                // 根据栏目ID获取最新的最顶级模型ID
                if (intval($post['parent_id']) > 0) {
                    $parentInfo = Db::name('arctype')->field('id,topid,grade,channeltype')->where('id', $post['parent_id'])->find();
                    $channeltype = $parentInfo['channeltype'];
                    $grade       = $parentInfo['grade'] + 1;
                    $topid = !empty($parentInfo['topid']) ? $parentInfo['topid'] : $parentInfo['id'];
                }
                /*SEO描述*/
                $seo_description = $post['seo_description'];
                /*--end*/

                /*处理自定义字段值*/
                $addonField = array();
                if (!empty($post['addonField'])) {
                    $addonField = $this->fieldLogic->handleAddonField($this->arctype_channel_id, $post['addonField']);
                }
                /*--end*/
                // 列表/文档命名规则
                $post['rulelist'] = trim($post['rulelist']);
                $post['ruleview'] = trim($post['ruleview']);

                $newData = array(
                    'topid' => $topid,
                    'dirname' => $dirname,
                    'dirpath'   => $dirpath,
                    'diy_dirpath'   => $diy_dirpath,
                    'typelink' => $typelink,
                    'litpic'    => $litpic,
                    'channeltype'   => $channeltype,
                    'grade' => $grade,
                    'seo_keywords' => str_replace('，', ',', $post['seo_keywords']),
                    'seo_description' => $seo_description,
                    'lang'  => $this->admin_lang,
                    'update_time'  => getTime(),
                );
                if ($post['current_channel'] == 6 && $post['current_channel'] != $post['old_current_channel']) {
                    $newData['empty_logic'] = 0;
                }
                unset($post['old_current_channel']);
                
                $data = array_merge($post, $newData, $addonField);
                $r = model('Arctype')->updateData($data);
                if(false !== $r){
                    $_POST['id'] = $_POST['typeid'] = $post['id'];
                    // 删除多余的问答栏目
                    if (51 == $post['current_channel']){
                        Db::name('arctype')->where(['current_channel' => $post['current_channel'], 'id' => ['NEQ', $post['id']]])->delete();
                    }

                    adminLog('编辑栏目：'.$data['typename']);

                    // 生成静态页面代码
                    $this->success("操作成功!", url('Arctype/index', ['typeid'=>$post['id'],'handle'=>'edit']));
                    exit;
                }
            }
            $this->error("操作失败!");
            exit;
        }

        $assign_data = array();

        $id = input('id/d');
        $info = Db::name('arctype')->where([
                'id'    => $id,
                'lang'  => $this->admin_lang,
            ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        $info['typename'] = htmlspecialchars_decode($info['typename']);
        $info['page_limit'] = empty($info['page_limit']) ? [] : explode(',', $info['page_limit']);

        // 栏目图片处理
        if (is_http_url($info['litpic'])) {
            $info['is_remote'] = 1;
            $info['litpic_remote'] = handle_subdir_pic($info['litpic']);
        } else {
            $info['is_remote'] = 0;
            $info['litpic_local'] = handle_subdir_pic($info['litpic']);
        }
        $this->assign('field',$info);

        // 获得上级目录路径
        if (!empty($info['parent_id'])) {
            $pinfo = Db::name('arctype')->field('dirpath,diy_dirpath')->where(['id'=>$info['parent_id']])->find();
            if (2 == $this->globalConfig['seo_pseudo'] && 4 == $this->globalConfig['seo_html_listname']) {
                $predirpath = $pinfo['diy_dirpath'];
            } else {
                $predirpath = $pinfo['dirpath'];
            }
        } else {
            $predirpath = ''; // 生成静态页面代码
        }
        $this->assign('predirpath',$predirpath);

        // 是否有子栏目
        $is_edit_parent_id = 1; // 是否可编辑所属栏目
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $hierarchy = model('Arctype')->getHierarchy($id);
        if ($hierarchy >= $arctype_max_level) {
            $is_edit_parent_id = 0; // 不可编辑，因为可能会导致超过所限制的最大层级
            $select_html = Db::name('arctype')->where('id', $info['parent_id'])->getField('typename');
            $select_html = !empty($select_html) ? $select_html : '顶级栏目';
        } else {
            // 所属栏目
            $select_html       = '<option value="0" data-grade="-1" data-dirpath="">顶级栏目</option>';
            $selected          = $info['parent_id'];
            $arctypeWhere      = ['is_del' => 0];
            $options           = $this->arctypeLogic->arctype_list(0, $selected, false, $arctype_max_level - $hierarchy, $arctypeWhere);
            foreach ($options AS $var)
            {
                $select_html .= '<option value="' . $var['id'] . '" data-grade="' . $var['grade'] . '"';
                if (2 == $this->globalConfig['seo_pseudo'] && 4 == $this->globalConfig['seo_html_listname']) {
                    $select_html .= ' data-dirpath="'.$var['diy_dirpath'].'" ';
                } else {
                    $select_html .= ' data-dirpath="'.$var['dirpath'].'" ';
                }
                $select_html .= ($selected == $var['id']) ? "selected='true'" : '';
                $select_html .= ($id == $var['id'] || ($hierarchy + $var['grade'] > $arctype_max_level - 1)) ? "disabled='true' style='background-color:#f5f5f5;' " : '';
                if($this->_sonArctype($id, $var['id'])!==false){
                    $select_html .= "disabled='true' style='background-color:#f5f5f5;' ";
                }                
                $select_html .= '>';
                if ($var['level'] > 0)
                {
                    $select_html .= str_repeat('&nbsp;', $var['level'] * 4);
                }
                $select_html .= htmlspecialchars_decode(addslashes($var['typename'])) . '</option>';
            }
        }
        $this->assign('select_html',$select_html);
        $this->assign('is_edit_parent_id',$is_edit_parent_id);

        /* 模型 */
        $map = "status = 1 OR id = '".$info['current_channel']."'";
        $channeltype_list = model('Channeltype')->getAll('id,title,nid,ctl_name', $map, 'id');
        // 模型对应模板文件不存在报错
        if (!isset($channeltype_list[$info['current_channel']])) {
            $row = model('Channeltype')->getInfo($info['current_channel']);
            $file = 'lists_'.$row['nid'].'.htm';
            $this->error($row['title'].'缺少模板文件'.$file);
        }
        // 选项卡内容的链接
        $ctl_name = $channeltype_list[$info['current_channel']]['ctl_name'];
        $list_url = url("{$ctl_name}/index")."?typeid={$id}";
        $this->assign('list_url', $list_url);
        $this->assign('channeltype_list', $channeltype_list);
        
        /*发布文档的模型ID，用于是否显示文档模板列表*/
        $js_allow_channel_arr = '[';
        foreach ($this->allowReleaseChannel as $key => $val) {
            if (51 == $val) continue; // 问答模型
            if ($key > 0) {
                $js_allow_channel_arr .= ',';
            }
            $js_allow_channel_arr .= $val;
        }
        $js_allow_channel_arr = $js_allow_channel_arr.']';
        $this->assign('js_allow_channel_arr', $js_allow_channel_arr);
        /*--end*/

        /*选项卡*/
        $tab = input('param.tab/d', 1);
        $this->assign('tab', $tab);
        /*--end*/

        /*模板列表*/
        $templateList = $this->ajax_getTemplateList('edit', $info['templist'], $info['tempview']);
        $this->assign('templateList', $templateList);
        /*--end*/

        /*自定义字段*/
        $assign_data['addonFieldExtList'] = model('Field')->getTabelFieldList(config('global.arctype_channel_id'), $id);
        $assign_data['aid'] = $id;
        $assign_data['channeltype'] = 6;
        $assign_data['nid'] = 'arctype';
        /*--end*/
        //用于处理编辑器自动远程本地化 开始
        $editor_addonFieldExt = [];
        //查询绑定栏目的自定义字段
        $channelfieldBindRow = Db::name('channelfield_bind')->where([
            'typeid'    => ['IN', [0, $id]],
        ])->column('field_id');
        foreach ($assign_data['addonFieldExtList'] as $key => $val) {
            if (!in_array($val['id'],$channelfieldBindRow)){
                unset($assign_data['addonFieldExtList'][$key]);
                continue;
            }
            if ($val['dtype'] == 'htmltext') {
                if (empty($first_html)){
                    $editor = tpSetting('editor');
                    $assign_data['addonFieldExtList'][$key]['editor'] = $editor;
                    $assign_data['addonFieldExtList'][$key]['first'] = 1;
                    $first_html = 1;
                }
                $editor_addonFieldExt[] = $val['name'];
            }
        }
        $editor_addonFieldExt = implode(",",$editor_addonFieldExt);
        $assign_data['editor_addonFieldExt'] = $editor_addonFieldExt;
        //用于处理编辑器自动远程本地化 结束
        /*之前标记旧参数可用，就开放入口*/
        $assign_data['is_old_product_attr'] = tpSetting('system.system_old_product_attr', [], 'cn');
        /*end*/
        //url模式
        $assign_data['seo_pseudo'] = $seo_pseudo;
        $assign_data['seo_html_arcdir'] = $seo_html_arcdir;
        $assign_data['seo_html_listname'] = $seo_html_listname;
        $assign_data['seo_html_pagename'] = $seo_html_pagename;
        $assign_data['is_diyseo_htmlpath'] = $is_diyseo_htmlpath;

        $assign_data['rulelist'] = "{栏目目录}/list_{tid}_{page}.html";
        $assign_data['ruleview'] = "{栏目目录}/{aid}.html";

        // 阅读权限
        $arcrank_list = get_arcrank_list();
        foreach ($arcrank_list as $key=>$val){
            if ($val['rank'] < 0){
                unset($arcrank_list[$key]);
            }
        }
        $assign_data['arcrank_list'] = $arcrank_list;

        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 内容管理
     */
    public function single_edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $typeid = input('post.typeid/d', 0);
            if(!empty($typeid)){
                model('Archives')->editor_auto_210607($post);
                $info = Db::name('arctype')->field('id,typename,current_channel')
                    ->where([
                        'id'    => $typeid,
                        'lang'  => $this->admin_lang,
                    ])->find();
                $aid = Db::name('archives')->where([
                        'typeid'    => $typeid,
                        'channel'   => 6,
                        'lang'  => $this->admin_lang,
                    ])->getField('aid');
                
                /*修复新增单页栏目的关联数据不完善，进行修复*/
                if (empty($aid)) {
                    $archivesData = array(
                        'title' => $info['typename'],
                        'typeid'=> $info['id'],
                        'channel'   => $info['current_channel'],
                        'sort_order'    => 100,
                        'add_time'  => getTime(),
                        'update_time'     => getTime(),
                        'lang'  => $this->admin_lang,
                    );
                    $aid = Db::name('archives')->insertGetId($archivesData);
                }
                /*--end*/

                Db::name('archives')->where(['aid'=>$aid])->update([
                        'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                        'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                    ]);

                Db::name('arctype')->where(['id'=>$typeid])->update([
                        'empty_logic'=>!empty($post['empty_logic']) ? 1 : 0,
                        'update_time'     => getTime(),
                    ]);

                if (!isset($post['addonFieldExt'])) {
                    $post['addonFieldExt'] = array();
                }
                $updateData = array(
                    'aid'   => $aid,
                    'typename' => $info['typename'],
                    'addonFieldExt' => $post['addonFieldExt'],
                );
                model('Single')->afterSave($aid, $updateData, 'edit');

                \think\Cache::clear("arctype");
                adminLog('编辑栏目：'.$info['typename']);
                // 生成静态页面代码
                $gourl = '';
                if (!empty($post['gourl'])) {
                    $gourl = htmlspecialchars_decode($post['gourl']);
                    if (!stristr($gourl, '&typeid='.$typeid)) {
                        $gourl .= '&typeid='.$typeid;
                    }
                }
                if ($post['archives'] == 1) {
                    $gourl = url('Arctype/single_edit', ['typeid'=>$typeid,'archives'=>$post['archives'],'gourl'=>$gourl]);
                }
                $this->success("操作成功!", $gourl);
            }
            $this->error("操作失败!");
        }

        $assign_data = array();

        $typeid = input('typeid/d');
        $info = Db::name('arctype')->field('a.*, b.editor_remote_img_local, b.editor_img_clear_link')
            ->alias('a')
            ->join('archives b', 'a.id = b.typeid', 'left')
            ->where([
                'a.id'    => $typeid,
                'a.lang'  => $this->admin_lang,
            ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
        }
        $info['typename'] = htmlspecialchars_decode(addslashes($info['typename']));
        $assign_data['info'] = $info;

        $editor = tpSetting('editor');
        $editor['editor_remote_img_local'] = $info['editor_remote_img_local'];
        $editor['editor_img_clear_link'] = $info['editor_img_clear_link'];

        /*自定义字段*/
        $addonFieldExtList = model('Field')->getChannelFieldList($info['current_channel'], 0, $typeid, $info);
        $field_id_row = Db::name('channelfield_bind')->where([
                'field_id'    => ['IN', get_arr_column($addonFieldExtList, 'id')],
            ])->column('field_id');
        // 匹配显示的自定义字段
        $editor_addonFieldExt = []; // 富文本的字段名
        $content_ey_m_dfvalue = ""; //手机端详情内容
        $have_content_ey_m = 0;         //是否显示手机端
        $name_arr = get_arr_column($addonFieldExtList,'name');

        if (!empty($field_id_row)) {
            // 查询绑定的自定义字段
            $channelfieldBindRow = Db::name('channelfield_bind')->where([
                    'typeid'    => ['IN', [0, $typeid]],
                ])->column('field_id');
            $first_html = '';
            foreach ($addonFieldExtList as $key => $val) {
                if (in_array($val['id'], $field_id_row) && !in_array($val['id'], $channelfieldBindRow)) {
                    unset($addonFieldExtList[$key]);
                    continue;
                }
                if ($val['dtype'] == 'htmltext') {
                    if ($val['name'] == 'content_ey_m'){
                        $content_ey_m_dfvalue = $val['dfvalue'];
                        if ($val['ifeditable']){
                            $have_content_ey_m = 1;
                        }
                    }
                    if (empty($first_html) && $val['name'] != 'content_ey_m'){
                        $addonFieldExtList[$key]['editor'] = $editor;
                        if ('content' == $val['name']) {
                            $addonFieldExtList[$key]['first'] = 1;
                            $first_html = 1;
                        }
                    }
                    $editor_addonFieldExt[] = $val['name'];
                }
                if($val['name'] == 'content_ey_m' && in_array('content',$name_arr)){  //百度编辑器，去掉content_ey_m
                    unset($addonFieldExtList[$key]);
                    continue;
                }
            }
        } else {
            $first_html = '';
            foreach ($addonFieldExtList as $key => $val) {
                if ($val['dtype'] == 'htmltext') {
                    if ($val['name'] == 'content_ey_m'){
                        $content_ey_m_dfvalue = $val['dfvalue'];
                        if ($val['ifeditable']){
                            $have_content_ey_m = 1;
                        }
                    }
                    if (empty($first_html) && $val['name'] != 'content_ey_m'){
                        $addonFieldExtList[$key]['editor'] = $editor;
                        if ('content' == $val['name']) {
                            $addonFieldExtList[$key]['first'] = 1;
                            $first_html = 1;
                        }
                    }
                    $editor_addonFieldExt[] = $val['name'];
                }
                if($val['name'] == 'content_ey_m' && in_array('content',$name_arr)){  //百度编辑器，去掉content_ey_m
                    unset($addonFieldExtList[$key]);
                    continue;
                }
            }
        }

        if (!empty($editor_addonFieldExt)){
            $editor_addonFieldExt = implode(',',$editor_addonFieldExt);
        }else{
            $editor_addonFieldExt = '';
        }
        $assign_data['editor_addonFieldExt'] = $editor_addonFieldExt;
        
        $assign_data['content_ey_m_dfvalue'] = $content_ey_m_dfvalue;
        $assign_data['have_content_ey_m'] = $have_content_ey_m;
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['aid'] = $typeid;
        $assign_data['channeltype'] = 6;
        $assign_data['nid'] = 'single';
        $assign_data['controller_name'] = CONTROLLER_NAME;
        $assign_data['action_name'] = ACTION_NAME;
        /*--end*/

        /*返回上一层*/
        $gourl = input('param.gourl/s', '');
        if (empty($gourl)) {
            $gourl = url('Arctype/index');
        }
        $assign_data['gourl'] = $gourl;
        /*--end*/
        $assign_data['archives'] = input('param.archives/d', 0);
        $this->assign($assign_data);
        
        /* 生成静态页面代码 */
        $this->assign('typeid',$typeid);
        /* end */
        
        return $this->fetch();
    }
    
    /**
     * 伪删除 del->彻底删除 pseudo->伪删除
     */
    public function pseudo_del()
    {
        if (IS_POST) {
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $this->language_access(); // 多语言功能操作权限
            }
            
            $post = input('post.');
            $post['del_id'] = eyIntval($post['del_id']);

            /*当前栏目信息*/
            $row = Db::name('arctype')->field('id, current_channel, typename')
                ->where([
                    'id'    => $post['del_id'],
                    'lang'  => $this->admin_lang,
                ])
                ->find();
            
            if ('del' == $post['deltype']) {
                $r = model('Arctype')->del($post['del_id']);
                $logtxt = '删除栏目：'.$row['typename'];
            } else {
                $r = model('Arctype')->pseudo_del($post['del_id']);
                $logtxt = '伪删除栏目：'.$row['typename'];
            }
            if (false !== $r) {
                adminLog($logtxt);
                /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
                Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
                model('SqlCacheTable')->InsertSqlCacheTable(true);
                /* END */
                $this->success('删除成功');
            }
        }

        $this->error('删除失败');
    }

    /**
     * 批量伪删除/真删除
     */
    public function batch_pseudo_del()
    {
        if (IS_POST) {
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $this->language_access(); // 多语言功能操作权限
            }

            $post = input('post.');
            $post['del_id'] = eyIntval($post['del_id']);
            if (!$post['del_id']) $this->error('未选择栏目');

            $typename = '';
            foreach ($post['del_id'] as $item) {
                /*当前栏目信息*/
                $row = Db::name('arctype')->field('id, current_channel, typename')
                    ->where([
                        'id'    => $item,
                        'lang'  => $this->admin_lang,
                    ])
                    ->find();

                $typename .= $row['typename'].',';

                if ('del' == $post['deltype']) {
                    model('Arctype')->del($item);
                } else {
                    model('Arctype')->pseudo_del($item);
                }
            }

            if ('del' == $post['deltype']) {
                $logtxt = '删除栏目：'.trim($typename,',');
            } else {
                $logtxt = '伪删除栏目：'.trim($typename,',');
            }
            adminLog($logtxt);
            /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
            /* END */
            $this->success('删除成功');
        }

        $this->error('删除失败');
    }

    /**
     * 通过模型获取栏目
     */
    public function ajax_get_arctype($channeltype = 0)
    {
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $options           = $this->arctypeLogic->arctype_list(0, 0, false, $arctype_max_level, array('channeltype' => $channeltype));
        $select_html       = '<option value="0" data-grade="-1">顶级栏目</option>';
        foreach ($options AS $var)
        {
            $select_html .= '<option value="' . $var['id'] . '" data-grade="' . $var['grade'] . '" data-dirpath="'.$var['dirpath'].'"';
            $select_html .= '>';
            if ($var['level'] > 0)
            {
                $select_html .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select_html .= htmlspecialchars_decode(addslashes($var['typename'])) . '</option>';
        }

        $returndata = array(
            'status' => 1,
            'select_html' => $select_html,
        );
        
        respose($returndata);
    }

    /**
     * 获取栏目的拼音
     */
    public function ajax_get_dirpinyin($typename = '')
    {
        $pinyin = get_pinyin($typename);
        $this->success('提取成功', null, ['pinyin'=>$pinyin]);
    }

    /**
     * 检测文件保存目录是否存在
     */
    public function ajax_check_dirpath()
    {
        $dirpath = input('post.dirpath/s');
        $id = input('post.id/d');
        $map = array(
            'dirpath' => $dirpath,
            'lang'  => $this->admin_lang,
        );
        if (intval($id) > 0) {
            $map['id'] = array('neq', $id);
        }
        $result = Db::name('arctype')->where($map)->find();
        if (!empty($result)) {
            respose(array(
                'status'    => 0,
                'msg'   => '文件保存目录已存在，请更改',
            ));
        } else {
            respose(array(
                'status'    => 1,
                'msg'   => '文件保存目录可用',
            ));
        }
    }

    public function ajax_getTemplateList($opt = 'add', $templist = '', $tempview = '')
    {   
        $planPath = 'template/'.TPL_THEME.'pc';
        $dirRes   = opendir($planPath);
        $view_suffix = config('template.view_suffix');

        /*模板PC目录文件列表*/
        $templateArr = array();
        while($filename = readdir($dirRes))
        {
            if (in_array($filename, array('.','..'))) {
                continue;
            }
            array_push($templateArr, $filename);
        }
        !empty($templateArr) && asort($templateArr);
        /*--end*/

        /*多语言全部标识*/
        $markArr = Db::name('language_mark')->column('mark');
        /*--end*/
        
        $is_language = false;
        $web_language_switch = tpCache('global.web_language_switch');
        if (!empty($web_language_switch)) {
            $languageCount = Db::name('language')->where(['status'=>1])->count('id');
            if (1 < $languageCount) {
                $is_language = true;
            }
        }

        $templateList = array();
        $channelList = model('Channeltype')->getAll();
        foreach ($channelList as $k1 => $v1) {
            $l = 1;
            $v = 1;
            $lists = ''; // 销毁列表模板
            $view = ''; // 销毁文档模板
            $templateList[$v1['id']] = array();
            foreach ($templateArr as $k2 => $v2) {
                $v2 = iconv('GB2312', 'UTF-8', $v2);

                if ('add' == $opt) {
                    $selected = 0; // 默认选中状态
                } else {
                    $selected = 1; // 默认选中状态
                }

                preg_match('/^(lists|view)_'.$v1['nid'].'(_(.*))?(_'.$this->admin_lang.')?\.'.$view_suffix.'/i', $v2, $matches1);
                $langtpl = preg_replace('/\.'.$view_suffix.'$/i', "_{$this->admin_lang}.{$view_suffix}", $v2);
                if (file_exists(realpath($planPath.DS.$langtpl))) {
                    continue;
                } else if (true == $is_language && preg_match('/^(.*)_([a-zA-z]{2,2})\.'.$view_suffix.'$/i',$v2,$matches2)) {
                    if (in_array($matches2[2], $markArr) && $matches2[2] != $this->admin_lang) {
                        continue;
                    }
                }

                if (!empty($matches1)) {
                    $selectefile = '';
                    if ('lists' == $matches1[1]) {
                        $lists .= '<option value="'.$v2.'" ';
                        $lists .= ($templist == $v2 || $selected == $l) ? " selected='true' " : '';
                        $lists .= '>'.$v2.'</option>';
                        $l++;
                    } else if ('view' == $matches1[1]) {
                        $view .= '<option value="'.$v2.'" ';
                        $view .= ($tempview == $v2 || $selected == $v) ? " selected='true' " : '';
                        $view .= '>'.$v2.'</option>';
                        $v++;
                    }
                }
            }
            $nofileArr = [];
            if (in_array($v1['nid'], ['ask'])) { // 问答模型

            } else { // 其他模型
                if ('add' == $opt) {
                    if (empty($lists)) {
                        $lists = '<option value="">无</option>';
                        $nofileArr[] = "lists_{$v1['nid']}.{$view_suffix}";
                    }
                    
                    if (empty($view)) {
                        $view = '<option value="">无</option>';
                        if (!in_array($v1['nid'], ['single','guestbook'])) {
                            $nofileArr[] = "view_{$v1['nid']}.{$view_suffix}";
                        }
                    }
                } else {
                    if (empty($lists)) {
                        $nofileArr[] = "lists_{$v1['nid']}.{$view_suffix}";
                    }
                    $lists = '<option value="">请选择模板…</option>'.$lists;

                    if (empty($view)) {
                        if (!in_array($v1['nid'], ['single','guestbook'])) {
                            $nofileArr[] = "view_{$v1['nid']}.{$view_suffix}";
                        }
                    }
                    $view = '<option value="">请选择模板…</option>'.$view;
                }
            }

            $msg = '';
            if (!empty($nofileArr)) {
                $msg = '<font color="red">该模型缺少模板文件：'.implode(' 和 ', $nofileArr).'</font>';
            }

            $templateList[$v1['id']] = array(
                'lists' => $lists,
                'view' => $view,
                'msg'    => $msg,
                'nid'    => $v1['nid'],
            );
        }

        if (IS_AJAX) {
            $this->success('请求成功', null, ['templateList'=>$templateList]);
        } else {
            return $templateList;
        }
    }

    /**
     * 新建模板文件
     */
    public function ajax_newtpl()
    {
        $this->error('出于安全考虑已禁用，请使用ftp或易优助手插件修改模板');
        if (IS_POST) {
            $post = input('post.', '', null);
            $content = input('post.content', '', null);
            $view_suffix = config('template.view_suffix');
            if (!empty($post['filename'])) {
                if (!preg_match("/^[\w\-\_]{1,}$/u", $post['filename'])) {
                    $this->error('文件名称只允许字母、数字、下划线、连接符的任意组合！');
                }
                $filename = "{$post['type']}_{$post['nid']}_{$post['filename']}.{$view_suffix}";
            } else {
                $filename = "{$post['type']}_{$post['nid']}.{$view_suffix}";
            }

            $tpldirpath = !empty($post['tpldir']) ? '/template/'.TPL_THEME.trim($post['tpldir']) : '/template/'.TPL_THEME.'pc';
            if (file_exists(ROOT_PATH.ltrim($tpldirpath, '/').'/'.$filename)) {
                $this->error('文件名称已经存在，请重新命名！', null, ['focus'=>'filename']);
            }

            $nosubmit = input('param.nosubmit/d');
            if (1 == $nosubmit) {
                $this->success('检测通过');
            }

            $filemanagerLogic = new \app\admin\logic\FilemanagerLogic;
            $r = $filemanagerLogic->editFile($filename, $tpldirpath, $content);
            if ($r === true) {
                $this->success('操作成功', null, ['filename'=>$filename,'type'=>$post['type']]);
            } else {
                $this->error($r);
            }
        }
        $type = input('param.type/s');
        $nid = input('param.nid/s');
        $tpldirList = glob('template/'.TPL_THEME.'*');
        $tpl_theme = str_replace('/', '\\/', TPL_THEME);
        foreach ($tpldirList as $key => $val) {
            if (!preg_match('/template\/'.$tpl_theme.'(pc|mobile)$/i', $val)) {
                unset($tpldirList[$key]);
            } else {
                $tpldirList[$key] = preg_replace('/^(.*)template\/'.$tpl_theme.'(pc|mobile)$/i', '$2', $val);
            }
        }
        !empty($tpldirList) && arsort($tpldirList);
        $this->assign('tpldirList', $tpldirList);
        $this->assign('type', $type);
        $this->assign('nid', $nid);
        $this->assign('tpl_theme', TPL_THEME);
        return $this->fetch();
    }

    /**
     * 批量增加栏目
     */
    public function batch_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        if (IS_POST) {
            $post = input('post.');
            if ($post) {

                if (empty($post['parent_id'])) { // 增加顶级栏目
                    foreach ($post['toptype'] as $key => $val) {
                        $val = trim($val);
                        if (empty($val)) {
                            unset($post['toptype'][$key]);
                        }
                    }
                    if (empty($post['toptype'])) {
                        $this->error('顶级栏目名称不能为空！');
                    }
                    $this->batch_add_toptype($post);
                } 
                else { // 增加下级栏目
                    foreach ($post['reltype'] as $key => $val) {
                        $val = trim($val);
                        if (empty($val)) {
                            unset($post['reltype'][$key]);
                        }
                    }
                    if (empty($post['reltype'])) {
                        $this->error('栏目名称不能为空！');
                    }
                    $this->batch_add_subtype($post);
                }
            }
            $this->error("操作失败！");
            exit;
        }

        /* 模型 */
        $map = array(
            'status'    => 1,
            'id'        => ['neq', 51], // 排除问答模型
        );
        $channeltype_list = model('Channeltype')->getAll('id,title,nid', $map, 'id');
        $this->assign('channeltype_list', $channeltype_list);
        
        /*发布文档的模型ID，用于是否显示文档模板列表*/
        $js_allow_channel_arr = '[';
        foreach ($this->allowReleaseChannel as $key => $val) {
            if ($key > 0) {
                $js_allow_channel_arr .= ',';
            }
            $js_allow_channel_arr .= $val;
        }
        $js_allow_channel_arr = $js_allow_channel_arr.']';
        $this->assign('js_allow_channel_arr', $js_allow_channel_arr);
        /*--end*/

        // 所属栏目
        $select_html = '<option value="0" data-grade="-1" data-dirpath="">顶级栏目</option>';
        $selected = 0;
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $arctypeWhere = ['is_del'=>0];
        $options = $this->arctypeLogic->arctype_list(0, $selected, false, $arctype_max_level - 1, $arctypeWhere);
        foreach ($options AS $var)
        {
            $select_html .= '<option value="' . $var['id'] . '" data-grade="' . $var['grade'] . '"';
            if (2 == $this->globalConfig['seo_pseudo'] && 4 == $this->globalConfig['seo_html_listname']) {
                $select_html .= ' data-dirpath="'.$var['diy_dirpath'].'" ';
            } else {
                $select_html .= ' data-dirpath="'.$var['dirpath'].'" ';
            }
            $select_html .= '>';
            if ($var['level'] > 0)
            {
                $select_html .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select_html .= htmlspecialchars_decode(addslashes($var['typename'])) . '</option>';
        }
        $this->assign('select_html',$select_html);

        /*模板列表*/
        $templateList = $this->ajax_getTemplateList('add');
        $this->assign('templateList', $templateList);
        /*--end*/

        return $this->fetch();
    }

    /**
     * 批量增加顶级栏目
     */
    private function batch_add_toptype($post = [])
    {
        $saveData = [];
        $dirnameArr = [];
        foreach ($post['toptype'] as $key => $val) {
            $typename = func_preg_replace([',','，'], '', trim($val));
            if (empty($typename)) continue;

            // 子栏目
            if (!empty($post['sontype'][$key])) {
                $sontype = str_replace('，', ',', $post['sontype'][$key]);
                $post['sontype'][$key] = explode(',', $sontype);
            }

            // 目录名称
            $dirname = $this->arctypeLogic->get_dirname($typename, '', 0, $dirnameArr);
            array_push($dirnameArr, $dirname);

            $dirpath = '/'.$dirname;

            $data = [
                'typename'  => $typename,
                'channeltype'   => $post['current_channel'],
                'current_channel'   => $post['current_channel'],
                'parent_id' => 0,
                'dirname'   => $dirname,
                'dirpath'   => $dirpath,
                'diy_dirpath'   => $dirpath,
                'grade' => 0,
                'litpic' => !empty($post['litpic_local'][$key]) ? $post['litpic_local'][$key] : '',
                'templist'  => !empty($post['templist']) ? $post['templist'] : '',
                'tempview'  => !empty($post['tempview']) ? $post['tempview'] : '',
                'is_hidden'  => $post['is_hidden'],
                'seo_description'   => '',
                'admin_id'  => session('admin_info.admin_id'),
                'lang'  => $this->admin_lang,
                'sort_order'    => !empty($post['sort_order'][$key]) ? intval($post['sort_order'][$key]) : 100,
                'add_time'  => getTime(),
                'update_time'  => getTime(),
            ];

            $saveData[] = $data;
        }

        if (!empty($saveData)) {
            $result = model('Arctype')->batchAddTopData($saveData, $post);
            if (!empty($result)) {
                \think\Cache::clear('arctype');
                $typenameArr = get_arr_column($result, 'typename');
                $typenameStr = implode(',', $typenameArr);
                adminLog('批量增加栏目：'.$typenameStr);

                // 生成静态页面代码
                $msg = '操作成功！';
                $seo_pseudo = config('tpcache.seo_pseudo');
                if (2 == $seo_pseudo) {
                    $msg = '操作成功，请手工生成静态页面！';
                }
                $this->success($msg, url('Arctype/index'));
                exit;
            }
        }
        $this->error("操作失败！");
    }

    /**
     * 批量增加下级栏目
     */
    private function batch_add_subtype($post = [])
    {
        // 获取所属栏目信息
        $arctypeInfo = Db::name('arctype')->field('id,channeltype,topid,parent_id,grade')->where('id', $post['parent_id'])->find();

        $topid = 0;
        if (!empty($arctypeInfo['topid'])) {
            $topid = $arctypeInfo['topid'];
        } else {
            if ($arctypeInfo['grade'] == 0) {
                $topid = $arctypeInfo['id'];
            } else if ($arctypeInfo['grade'] == 1) {
                $topid = $arctypeInfo['parent_id'];
            }
        }

        $saveData = [];
        $dirnameArr = [];
        foreach ($post['reltype'] as $key => $val) {
            $typename = func_preg_replace([',','，'], '', trim($val));
            if (empty($typename)) continue;

            // 目录名称
            $dirname = $this->arctypeLogic->get_dirname($typename, '', 0, $dirnameArr);
            array_push($dirnameArr, $dirname);

            $dirpath = $post['dirpath'].'/'.$dirname;

            $data = [
                'typename'  => $typename,
                'channeltype'   => $arctypeInfo['channeltype'],
                'current_channel'   => $post['current_channel'],
                'parent_id' => intval($post['parent_id']),
                'topid'     => $topid,
                'dirname'   => $dirname,
                'dirpath'   => $dirpath,
                'diy_dirpath'   => $dirpath,
                'grade' => intval($post['grade']),
                'litpic' => !empty($post['litpic_local_1'][$key]) ? $post['litpic_local_1'][$key] : '',
                'templist'  => !empty($post['templist']) ? $post['templist'] : '',
                'tempview'  => !empty($post['tempview']) ? $post['tempview'] : '',
                'is_hidden'  => $post['is_hidden'],
                'seo_description'   => '',
                'admin_id'  => session('admin_info.admin_id'),
                'lang'  => $this->admin_lang,
                'sort_order'    => !empty($post['sort_order_1'][$key]) ? intval($post['sort_order_1'][$key]) : 100,
                'add_time'  => getTime(),
                'update_time'  => getTime(),
            ];

            $saveData[] = $data;
        }

        if (!empty($saveData)) {
            $result = model('Arctype')->batchAddSubData($saveData);
            if (!empty($result)) {
                \think\Cache::clear('arctype');
                $typenameArr = get_arr_column($result, 'typename');
                $typenameStr = implode(',', $typenameArr);
                adminLog('批量增加栏目：'.$typenameStr);

                // 生成静态页面代码
                $msg = '操作成功！';
                $seo_pseudo = config('tpcache.seo_pseudo');
                if (2 == $seo_pseudo) {
                    $msg = '操作成功，请手工生成静态页面！';
                }
                $this->success($msg, url('Arctype/index'));
                exit;
            }
        }
        $this->error("操作失败！");
    }

    /**
     * 单页可视化入口
     */
    public function single_uiset()
    {
        $tid = input('param.tid/d');
        $v = input('param.v/s', 'pc');
        if (!empty($tid)) {
            if ('mobile' == $v) {
                $gourl = ROOT_DIR."/index.php?m=home&c=Lists&a=index&tid={$tid}&uiset=on&v={$v}&lang=".$this->admin_lang;
                $url = ROOT_DIR."/index.php?m=api&c=Uiset&a=mobileTpl&tid={$tid}&gourl=".base64_encode($gourl);
            } else {
                $url = ROOT_DIR."/index.php?m=home&c=Lists&a=index&tid={$tid}&uiset=on&v={$v}&lang=".$this->admin_lang;
            }
            $this->redirect($url);
        }
    }
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