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

namespace app\admin\controller;

use think\Db;
use think\Cache;
use app\common\logic\ArctypeLogic;
use think\paginator\driver; // 生成静态页面代码
use app\admin\logic\FilemanagerLogic;
use app\common\logic\BuildhtmlLogic;

class Seo extends Base
{
    private $buildhtmlLogic;

    public function _initialize() {
        parent::_initialize();
        $this->language_access(); // 多语言功能操作权限
        $this->buildhtmlLogic = new BuildhtmlLogic;
    }
    /*
     * 生成总站
     */
    public function site(){
        $param = input('param.');
        $uphtmltype = !empty($param['uphtmltype']) ? intval($param['uphtmltype']) : 0;
        $this->assign('uphtmltype', $uphtmltype);

        if (!empty($param['is_buildhtml'])) {
            /*多语言*/
            $seoConfig = [];
            $seoConfig['seo_maxpagesize'] = !empty($param['seo_maxpagesize']) ? intval($param['seo_maxpagesize']) : 50; // 栏目每页生成文件
            $seoConfig['seo_pagesize'] = !empty($param['seo_pagesize']) ? intval($param['seo_pagesize']) : 20; // 文档每页生成文件
            $seoConfig['seo_start_time'] = !empty($param['seo_start_time']) ? $param['seo_start_time'] : ""; // 起始时间
            $seoConfig['seo_startid2'] = !empty($param['seo_startid2']) ? intval($param['seo_startid2']) : ""; // 起始id
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('seo', $seoConfig, $val['mark']);
                }
            } else {
                tpCache('seo', $seoConfig);
            }
            /*--end*/
        }

        return $this->fetch();
    }
    /*
     * 生成栏目页
     */
    public function channel(){
        $param = input('param.');
        $typeid = !empty($param['typeid']) ? intval($param['typeid']) : 0; // 栏目ID
        $this->assign('typeid', $typeid);

        if (!empty($param['is_buildhtml'])) {
            /*多语言*/
            $seoConfig = [];
            $seoConfig['seo_maxpagesize'] = !empty($param['seo_maxpagesize']) ? intval($param['seo_maxpagesize']) : 50; // 每页生成文件
            $seoConfig['seo_upnext'] = isset($param['seo_upnext']) ? intval($param['seo_upnext']) : 1; // 是否更新子栏目
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('seo', $seoConfig, $val['mark']);
                }
            } else {
                tpCache('seo', $seoConfig);
            }
            /*--end*/
        }

        return $this->fetch();
    }
    /*
     * 生成文档页
     */
    public function article()
    {
        $param = input('param.');
        $typeid = !empty($param['typeid']) ? intval($param['typeid']) : 0; // 栏目ID
        $this->assign('typeid', $typeid);

        $uphtmltype = !empty($param['uphtmltype']) ? intval($param['uphtmltype']) : 0;
        $this->assign('uphtmltype', $uphtmltype);

        if (!empty($param['is_buildhtml'])) {
            /*多语言*/
            $seoConfig = [];
            $seoConfig['seo_startid'] = !empty($param['seo_startid']) ? intval($param['seo_startid']) : 0; // 文档ID开始
            $seoConfig['seo_endid'] = !empty($param['seo_endid']) ? intval($param['seo_endid']) : 0; // 文档ID结束
            $seoConfig['seo_pagesize'] = !empty($param['seo_pagesize']) ? intval($param['seo_pagesize']) : 20; // 每页生成文件
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('seo', $seoConfig, $val['mark']);
                }
            } else {
                tpCache('seo', $seoConfig);
            }
            /*--end*/
        }

        return $this->fetch();
    }

    /**
     * 初始化数据缓存
     * @return [type] [description]
     */
    public function init_data_cache()
    {
        if (IS_POST) {
            // 获取全部栏目的数据
            $this->buildhtmlLogic->get_arctype_all();
            // 获取全部文档微表的数据
            $this->buildhtmlLogic->get_archives_all();

            $this->success('缓存成功');
        }
    }

    private function resetHtmlConf()
    {
        /*多语言*/
        // 恢复设置默认值
        $seoConfig = [];
        $seoConfig['seo_maxpagesize'] = 50;
        $seoConfig['seo_upnext'] = 1;
        $seoConfig['seo_pagesize'] = 20;
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')
                ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                ->select();
            foreach ($langRow as $key => $val) {
                tpCache('seo', $seoConfig, $val['mark']);
            }
        } else {
            tpCache('seo', $seoConfig);
        }
        /*--end*/

        // 生成静态页面代码 - 更新分页php文件支持生成静态功能
        $this->update_paginatorfile();

        return $seoConfig;
    }

    /*
     * 静态生成页面
     */
    public function build(){
        $globalConfig = tpCache('global');

        if (2 != $globalConfig['seo_pseudo']) {
            $this->error('当前不是静态页面模式！');
        }

        // 恢复生成静态的设置默认值
        $seoConfig = $this->resetHtmlConf();
        $globalConfig = array_merge($globalConfig, $seoConfig);

        $this->assign('config', $globalConfig);//当前配置项
        // 栏目列表
        $map = array(
            'status'  => 1,
            'is_del'  => 0, // 回收站功能
            'current_channel'    => ['neq', 51], // 问答模型
            'weapp_code'    => '',
        );
        $arctypeLogic = new ArctypeLogic();
        $select_html = $arctypeLogic->arctype_list(0, 0, true, config('global.arctype_max_level'), $map);
        $this->assign('select_html',$select_html);
        // 允许发布文档列表的栏目
        $arc_select_html = allow_release_arctype();
        $this->assign('arc_select_html', $arc_select_html);

        // 网站根目录缺少 index.php 文件，请拷贝该文件上传到空间里！
        $is_index_file = 1;
        if (!file_exists('./index.php')) {
            $is_index_file = 0;
        }
        $this->assign('is_index_file', $is_index_file);

        return $this->fetch();
    }
    /*
     * URL配置
     */
    public function seo()
    {
        /* 纠正栏目的HTML目录路径字段值 */
        $this->correctArctypeDirpath();
        /* end */

        $inc_type =  'seo';
        $config = tpCache($inc_type);
        $config['seo_pseudo'] = tpCache('global.seo_pseudo');
        //前台默认语言
        $default_lang = \think\Config::get('ey_config.system_home_default_lang');
        $this->assign('default_lang',$default_lang);
        $seo_pseudo_list = get_seo_pseudo_list();
        $this->assign('seo_pseudo_list', $seo_pseudo_list);

        /* 生成静态页面代码 - 多语言统一设置URL模式 */
        $seo_pseudo_lang = '';
        $web_language_switch = tpCache('global.web_language_switch');
        if (is_language() && !empty($web_language_switch)) {
            $markArr = Db::name('language')->field('mark')->order('id asc')->limit('1,1')->select();
            if (!empty($markArr[0]['mark'])) {
                $seo_pseudo_lang = tpCache('global.seo_pseudo', [], $markArr[0]['mark']);
            }
            $seo_pseudo_lang = !empty($seo_pseudo_lang) ? $seo_pseudo_lang : 1;
        }
        $this->assign('seo_pseudo_lang', $seo_pseudo_lang);
        /* end */

        /* 限制文档HTML保存路径的名称 */
        $wwwroot_dir = config('global.wwwroot_dir'); // 网站根目录的目录列表
        $disable_dirname = config('global.disable_dirname'); // 栏目伪静态时的路由路径
        $wwwroot_dir = array_merge($wwwroot_dir, $disable_dirname);
        // 不能与栏目的一级目录名称重复
        $arctypeDirnames = Db::name('arctype')->where(['parent_id'=>0])->column('dirname');
        is_array($arctypeDirnames) && $wwwroot_dir = array_merge($wwwroot_dir, $arctypeDirnames);
        // 不能与多语言的标识重复
        $markArr = Db::name('language_mark')->column('mark');
        is_array($markArr) && $wwwroot_dir = array_merge($wwwroot_dir, $markArr);
        $wwwroot_dir = array_unique($wwwroot_dir);
        $this->assign('seo_html_arcdir_limit', implode(',', $wwwroot_dir));
        /* end */

        $seo_html_arcdir_1 = '';
        if (!empty($config['seo_html_arcdir'])) {
            $config['seo_html_arcdir'] = trim($config['seo_html_arcdir'], '/');
            $seo_html_arcdir_1 = '/'.$config['seo_html_arcdir'];
        }
        $this->assign('seo_html_arcdir_1', $seo_html_arcdir_1);

        // 栏目列表
        $map = array(
            'status'  => 1,
            'is_del'  => 0, // 回收站功能
            'current_channel'    => ['neq', 51], // 问答模型
            'weapp_code'    => '',
        );
        $arctypeLogic = new ArctypeLogic();
        $select_html = $arctypeLogic->arctype_list(0, 0, true, config('global.arctype_max_level'), $map);
        $this->assign('select_html',$select_html);

        // 允许发布文档列表的栏目
        $arc_select_html = allow_release_arctype();
        $this->assign('arc_select_html', $arc_select_html);

        /*标记是否第一次切换静态页面模式*/
        if (!isset($config['seo_html_arcdir'])) {
            $init_html = 1; // 第一次切换
        } else {
            $init_html = 2; // 多次切换
        }
        $this->assign('init_html', $init_html);
        /*--end*/

        // 恢复生成静态的设置默认值
        $this->resetHtmlConf();

        // 网站根目录缺少 index.php 文件，请拷贝该文件上传到空间里！
        $is_index_file = 1;
        if (!file_exists('./index.php')) {
            $is_index_file = 0;
        }
        $this->assign('is_index_file', $is_index_file);

        $this->assign('config',$config);//当前配置项
        return $this->fetch();
    }

    /*
     * 保存URL配置
     */
    public function handle()
    {
        if (IS_POST) {
            $param = input('post.');
            $this->buildhtmlLogic->seo_handle($param);

            // 优化数据
            $this->optimizeTableData();
            // 更新分页php文件支持生成静态功能
            $this->update_paginatorfile();

            delFile(HTML_ROOT);
            \think\Cache::clear();
            $this->success('操作成功', url('Seo/seo'));
        }
        $this->error('操作失败');
    }

    /**
     *  优化数据
     *
     * @access    public
     * @return    void
     */
    private function optimizeTableData()
    {
        $optimizeTableData_time = tpSetting('system.system_optimizeTableData_time', '', 'cn');
        $optimizeTableData_time = intval($optimizeTableData_time) + (15 * 86400);
        if (getTime() > $optimizeTableData_time) {
            $tptables = ['archives'];
            $row = Db::name('channeltype')->field('nid,table')->select();
            foreach ($row as $key => $val) {
                if (in_array($val['nid'], ['ask','guestbook'])) {
                    continue;
                }
                $tptables[] = $val['table'].'_content';
            }

            $tptable = '';
            foreach ($tptables as $key => $t) {
                $t = PREFIX.$t;
                $tptable .= ($tptable == '' ? "`{$t}`" : ",`{$t}`" );
            }

            try {
                @Db::execute(" OPTIMIZE TABLE $tptable; ");
            } catch (\Exception $e) {

            }

            tpSetting('system', ['system_optimizeTableData_time'=>getTime()], 'cn');
        }
    }

    /**
     * 生成静态页面代码 - 更新分页php文件支持生成静态功能
     */
    private function update_paginatorfile()
    {
        $dirpath = CORE_PATH . 'paginator/driver/*.php';
        $files = glob($dirpath);
        foreach ($files as $key => $file) {
            if (is_writable($file)) {
                $strContent = @file_get_contents($file);
                if (false != $strContent && !stristr($strContent, 'data-ey_fc35fdc="html"')) {
                    $replace = 'htmlentities($url) . \'" data-ey_fc35fdc="html" data-tmp="1\'';
                    $strContent = str_replace('htmlentities($url)', $replace, $strContent);
                    if (function_exists('chmod')) {
                        @chmod($file,0777);
                    }
                    @file_put_contents($file, $strContent);
                }
            }
        }
    }

    /*
     * 生成整站静态文件
     */
    public function buildSite(){
        $type =  input("param.type/s");
        if($type != 'site'){
            $this->error('操作失败');
        }
        $this->success('操作成功');
    }

    /*
     * 获取生成栏目或文章的栏目id
     */
    public function getAllType(){
        $id =  input("param.id/d");//栏目id
        $type =  input("param.type/d");//1栏目2文章
        if(empty($id)) {
            if($id == 0){
                $mark = Db::name('language')->order('id asc')->value('mark');
                if($type == 1){
                    $arctype = Db::name('arctype')->where(['is_del'=>0,'status'=>1,'lang'=>$mark])->getfield('id',true);
                }else{
                    $where['is_del'] = 0;
                    $where['status'] = 1;
                    $where['lang'] = $mark;
                    $where['current_channel'] = array(array('neq',6),array('neq',8));
                    $arctype = Db::name('arctype')->where($where)->getfield('id',true);
                }
                if(empty($arctype)){
                    $this->error('没有要更新的栏目！');
                }else{
                    $arctype = implode(',',$arctype);
                    $this->success($arctype);
                }
            }else{
                $this->error('栏目ID不能为空！');
            }
        }else{
            //递归查询所有的子类
            $arctype_child_all = array($id);
            getAllChild($arctype_child_all,$id,$type);

            $arctype_child_all = implode(',',$arctype_child_all);
            if(empty($arctype_child_all)) {
                $this->error('没有要更新的栏目！');
            }else{
                $this->success($arctype_child_all);
            }
        }
    }

    /**
     * 纠正栏目的HTML目录路径字段值
     */
    private function correctArctypeDirpath()
    {
        $system_correctArctypeDirpath = tpCache('global.system_correctArctypeDirpath');
        if (!empty($system_correctArctypeDirpath)) {
            return false;
        }

        $saveData = [];
        $arctypeList = Db::name('arctype')->field('id,parent_id,dirname,dirpath,grade')
            ->order('grade asc')
            ->getAllWithIndex('id');
        foreach ($arctypeList as $key => $val) {
            if (empty($val['parent_id'])) { // 一级栏目
                $saveData[] = [
                    'id'            => $val['id'],
                    'dirpath'       => '/'.$val['dirname'],
                    'grade'         => 0,
                    'update_time'   => getTime(),
                ];
            } else {
                $parentRow = $arctypeList[$val['parent_id']];
                if (empty($parentRow['parent_id'])) { // 二级栏目
                    $saveData[] = [
                        'id'            => $val['id'],
                        'dirpath'       => '/'.$parentRow['dirname'].'/'.$val['dirname'],
                        'grade'         => 1,
                        'update_time'   => getTime(),
                    ];
                } else { // 三级栏目
                    $topRow = $arctypeList[$parentRow['parent_id']];
                    $saveData[] = [
                        'id'            => $val['id'],
                        'dirpath'       => '/'.$topRow['dirname'].'/'.$parentRow['dirname'].'/'.$val['dirname'],
                        'grade'         => 2,
                        'update_time'   => getTime(),
                    ];
                }
            }
        }
        $r = model('Arctype')->saveAll($saveData);
        if (false !== $r) {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('system', ['system_correctArctypeDirpath'=>1],$val['mark']);
                }
            } else {
                tpCache('system',['system_correctArctypeDirpath'=>1]);
            }
            /*--end*/
        }
    }

    /**
     * 静态页面模式切换为其他模式时，检测之前生成的静态目录是否存在，并提示手工删除还是自动删除
     */
    public function ajax_checkHtmlDirpath()
    {
        $seo_pseudo_new = input('param.seo_pseudo_new/d');
        if (3 == $seo_pseudo_new) {
            $dirArr = [];
            $seo_html_listname = tpCache('global.seo_html_listname');
            $row = Db::name('arctype')->field('dirpath,diy_dirpath')->select();
            foreach ($row as $key => $val) {
                $dirpathArr = explode('/', $val['dirpath']);
                if (3 == $seo_html_listname) {
                    $dir = end($dirpathArr);
                } else if (4 == $seo_html_listname) {
                    $dirpathArr = explode('/', $val['diy_dirpath']);
                    $dir = end($dirpathArr);
                } else {
                    $dir = !empty($dirpathArr[1]) ? $dirpathArr[1] : '';
                }
                if (!empty($dir) && !in_array($dir, $dirArr)) {
                    array_push($dirArr, $dir);
                }
            }

            $data = [];
            $data['msg'] = '';
            $num = 0;
            $wwwroot = glob('*', GLOB_ONLYDIR);
            foreach ($wwwroot as $key => $val) {
                if (in_array($val, $dirArr)) {
                    if (0 == $num) {
                        $data['msg'] .= "<font color='red'>根目录下有HTML静态目录，请先删除：</font><br/>";
                    }
                    $data['msg'] .= ($num+1)."、{$val}<br/>";
                    $num++;
                }
            }
            $data['height'] = $num * 24;

            $this->success('检测成功！', null, $data);
        }
    }

    /**
     * 自动删除静态HTML存放目录
     */
    public function ajax_delHtmlDirpath()
    {
        if (IS_AJAX_POST) {
            $data = del_html_dirpath();
            if (!empty($data['msg'])){
                $this->error('删除失败！', null, $data);
            }

            $this->success('删除成功！', null, $data);
        }
    }

    /*
     * 选择首页模板
     */
    public function filemanager(){
        $this->filemanagerLogic = new FilemanagerLogic();
        $this->globalTpCache = $this->filemanagerLogic->globalTpCache;
        $this->baseDir = $this->filemanagerLogic->baseDir; // 服务器站点根目录绝对路径
        $this->maxDir = $this->filemanagerLogic->maxDir."/pc"; // 默认文件管理的最大级别目录
        // 获取到所有GET参数
        $param = input('param.', '', null);
        $activepath = input('param.activepath', '', null);
        $activepath = $this->filemanagerLogic->replace_path($activepath, ':', true);

        /*当前目录路径*/
        $activepath = !empty($activepath) ? $activepath : $this->maxDir;
        $tmp_max_dir = preg_replace("#\/#i", "\/", $this->maxDir);
        if (!preg_match("#^".$tmp_max_dir."#i", $activepath)) {
            $activepath = $this->maxDir;
        }
        /*--end*/

        $inpath = "";
        $activepath = str_replace("..", "", $activepath);
        $activepath = preg_replace("#^\/{1,}#", "/", $activepath); // 多个斜杆替换为单个斜杆
        if($activepath == "/") $activepath = "";

        if(empty($activepath)) {
            $inpath = $this->baseDir.$this->maxDir;
        } else {
            $inpath = $this->baseDir.$activepath;
        }

        $list = $this->filemanagerLogic->getDirFile($inpath, $activepath);
        $assign_data['list'] = $list;

        /*文件操作*/
        $assign_data['replaceImgOpArr'] = $this->filemanagerLogic->replaceImgOpArr;
        $assign_data['editOpArr'] = $this->filemanagerLogic->editOpArr;
        $assign_data['renameOpArr'] = $this->filemanagerLogic->renameOpArr;
        $assign_data['delOpArr'] = $this->filemanagerLogic->delOpArr;
        $assign_data['moveOpArr'] = $this->filemanagerLogic->moveOpArr;
        /*--end*/

        $assign_data['activepath'] = $activepath;

        $this->assign($assign_data);
        return $this->fetch();
    }
}