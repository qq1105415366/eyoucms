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
use app\admin\controller\Base;
use think\Controller;
use think\Db;
use think\Page;
use app\admin\logic\UpgradeLogic;
use app\admin\logic\EyouCmsLogic;

class Index extends Base
{
    public $eyouCmsLogic;

    public function _initialize()
    {
        parent::_initialize();
        $this->eyouCmsLogic = new EyouCmsLogic;
        //初始化admin_menu表（将原来左边栏目设置为跟原来一样）
        $ajaxLogic = new \app\admin\logic\AjaxLogic;
        $ajaxLogic->initialize_admin_menu();
    }
    
    public function index()
    {
        // diy_database_column();
        // diy_source_filelist();

        // $dbtables = Db::query('SHOW TABLE STATUS');
        // $list = array();
        // foreach ($dbtables as $k => $v) {
        //     if (preg_match('/^'.PREFIX.'/i', $v['Name'])) {
        //         $list[$k] = $v;
        //     }
        // }
        // $str = '';
        // foreach ($list as $_k => $_v) {
        //     $table = $_v['Name'];
        //     $str .= $table.'|';
        //     $str2 = "|";
        //     $fields = Db::table($table)->getTableFields();
        //     foreach ($fields as $key => $value) {
        //         if ($key == 0) {
        //             $str .= "{$value}";
        //         } else {
        //             $str .= ",{$value}";
        //             $str2 .= ";";
        //         }
        //     }
        //     $str = $str . $str2 . "|". PHP_EOL . PHP_EOL;
        // }
        // echo($str);
        // exit;

        $language_db = Db::name('language');
        /*多语言列表*/
        $web_language_switch = tpCache('global.web_language_switch');
        $languages = [];
        $languages = $language_db->field('a.mark, a.title')
            ->alias('a')
            ->where('a.status',1)
            ->order('sort_order asc,id asc')
            ->getAllWithIndex('mark');
        $this->assign('languages', $languages);
        $this->assign('web_language_switch', $web_language_switch);
        /*--end*/

        $web_adminlogo = tpCache('web.web_adminlogo', [], $this->main_lang);
        $this->assign('web_adminlogo', handle_subdir_pic($web_adminlogo));

        /*代理贴牌功能限制-s*/
        $function_switch = $upgrade = true;
        if (function_exists('checkAuthRule')) {
            // 功能地图
            $function_switch = checkAuthRule(2004008);
            // 系统更新
            $upgrade = checkAuthRule('upgrade');
        }
        $this->assign('function_switch', $function_switch);
        $this->assign('upgrade', $upgrade);
        /*代理贴牌功能限制-e*/

        /*小程序开关*/
        $diyminipro_list = [];
        if ($this->admin_lang == $this->main_lang) {
            $diyminipro_list = Db::name('weapp')->field('id,code,name,config')->where(['code'=>['IN',['Diyminipro','DiyminiproMall','BdDiyminipro','TtDiyminipro','ZfbDiyminipro']],'status'=>1])->order('code desc')->select();
            foreach ($diyminipro_list as $key => $val) {
                $val['config'] = (array)json_decode($val['config']);
                $val['litpic'] = empty($val['config']['litpic']) ? '' : handle_subdir_pic($val['config']['litpic']);
                if ('Diyminipro' == $val['code']) {
                    $val['name'] = '微信企业小程序';
                } else if ('DiyminiproMall' == $val['code']) {
                    $val['name'] = '微信商城小程序';
                } else if ('BdDiyminipro' == $val['code']) {
                    $val['name'] = '百度企业小程序';
                } else if ('TtDiyminipro' == $val['code']) {
                    $val['name'] = '抖音企业小程序';
                } else if ('ZfbDiyminipro' == $val['code']) {
                    $val['name'] = '支付宝企业小程序';
                } 
                $diyminipro_list[$key] = $val;
            }
        }
        $this->assign('diyminipro_list', $diyminipro_list);
        /*end*/

        //获取前台入口链接
        $this->assign('home_url', $this->eyouCmsLogic->shouye($this->globalConfig));
        /*--end*/
        $this->assign('admin_info', getAdminInfo(session('admin_id')));
        //左侧菜单列表（old）
//        $this->assign('menu',getMenuList());
        //获取所有权限
        $all_menu_tree = getAllMenu();
        $all_menu_list = tree_to_list($all_menu_tree,'child','id');
        $this->assign('all_menu_list',$all_menu_list);
        //获取选中的权限
        $ajaxLogic = new \app\admin\logic\AjaxLogic;
        $ajaxLogic->admin_menu_clear();
        // $ajaxLogic->eyou_v165_del_func();
        $menu_list = Db::name("admin_menu")->where(['status'=>1,'is_menu'=>1])->order("sort_order asc,update_time asc,id asc")->select();
        foreach ($menu_list as $key => $val) {
            if (stristr($val['param'], '|sm|Diyminipro|')) {
                $val['title'] = '微信企业小程序';
            } else if (stristr($val['param'], '|sm|DiyminiproMall|')) {
                $val['title'] = '微信商城小程序';
            } else if (stristr($val['param'], '|sm|BdDiyminipro|')) {
                $val['title'] = '百度企业小程序';
            } else if (stristr($val['param'], '|sm|TtDiyminipro|')) {
                $val['title'] = '抖音企业小程序';
            } else if (stristr($val['param'], '|sm|ZfbDiyminipro|')) {
                $val['title'] = '支付宝企业小程序';
            }
            $menu_list[$key] = $val;

            // 其他语言不显示留言管理
            /*if ($this->admin_lang != $this->main_lang) {
                foreach ([2004018] as $_k => $_v) {
                    if ($_v == $val['menu_id']) {
                        unset($menu_list[$key]);
                    }
                }
            }*/
        }
        $menu_list = getAdminMenuList($menu_list);
        $this->assign('menu_list',$menu_list);
        //获取因为没有开启相关模块没有权限的节点
        $not_role_menu_id_arr = get_not_role_menu_id();
        $this->assign('not_role_menu_id_arr',$not_role_menu_id_arr);

        // 是否开启安全补丁
        $security_patch = tpSetting('upgrade.upgrade_security_patch');
        if (empty($security_patch)) $security_patch = 0;
        $this->assign('security_patch', $security_patch);

        // 统计未读的站内信数量
        action('admin/Notify/count_unread_notify');

        return $this->fetch();
    }
   
    public function welcome()
    {
        $assign_data = [];
        // 更新数据缓存表信息
        $this->update_sql_cache_table();
        
        /*小程序组件更新*/
        $assign_data['is_update_component_access'] = 1;
        if (!is_dir('./weapp/Diyminipro/') || $this->admin_lang != $this->main_lang) {
            $assign_data['is_update_component_access'] = 0;
        }
        /*end*/

        // 纠正上传附件的大小，始终以空间大小为准
        $file_size = $this->globalConfig['file_size'];
        $maxFileupload = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : 0;
        $maxFileupload = intval($maxFileupload);
        if (empty($file_size) || $file_size > $maxFileupload) {
            /*多语言*/
            if (is_language()) {
                $langRow = Db::name('language')->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->order('id asc')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('basic', ['file_size'=>$maxFileupload], $val['mark']);
                }
            } else { // 单语言
                tpCache('basic', ['file_size'=>$maxFileupload]);
            }
            /*--end*/
        }

        /*检查密码复杂度*/
        $admin_login_pwdlevel = -1;
        $system_explanation_welcome_2 = !empty($this->globalConfig['system_explanation_welcome_2']) ? $this->globalConfig['system_explanation_welcome_2'] : 0;
        if (empty($system_explanation_welcome_2)) {
            $admin_login_pwdlevel = session('admin_login_pwdlevel');
            if (!session('?admin_login_pwdlevel') || 3 < intval($admin_login_pwdlevel)) {
                $system_explanation_welcome_2 = 1;
            }
        }
        $assign_data['admin_login_pwdlevel'] = $admin_login_pwdlevel;
        $assign_data['system_explanation_welcome_2'] = $system_explanation_welcome_2;
        /*end*/

        // 定期检查多久没有扫描
        $system_explanation_welcome_3 = 0;
        $ddos_scan_expire_day = 0;
        $ddos_scan_last_time = (int)tpSetting('ddos.ddos_scan_last_time', [], 'cn');
        if (!empty($ddos_scan_last_time)) {
            $ddos_scan_day = 30;
            $ddos_scan_expire_time = getTime() - ($ddos_scan_last_time + ($ddos_scan_day * 86400));
            if ($ddos_scan_expire_time < 0) {
                $system_explanation_welcome_3 = 1;
            } else {
                $ddos_scan_expire_day = ceil($ddos_scan_expire_time / 86400);
            }
        }
        $assign_data['ddos_scan_last_time'] = $ddos_scan_last_time;
        $assign_data['ddos_scan_expire_day'] = $ddos_scan_expire_day;
        $assign_data['system_explanation_welcome_3'] = $system_explanation_welcome_3;

        /*代理贴牌功能限制-s*/
        $assign_data['upgrade'] = true;
        if (function_exists('checkAuthRule')) {
            //系统更新
            $assign_data['upgrade'] = checkAuthRule('upgrade');
        }
        /*代理贴牌功能限制-e*/

        // 是否开启安全补丁
        $assign_data['security_patch'] = (int)tpSetting('upgrade.upgrade_security_patch');
        // 升级弹窗
        if (2 == $this->globalConfig['web_show_popup_upgrade'] && $this->php_servicemeal <= 0) {
            $this->globalConfig['web_show_popup_upgrade'] = -1;
        }
        $assign_data['web_show_popup_upgrade'] = $this->globalConfig['web_show_popup_upgrade'];
        // 升级系统时，同时处理sql语句
        $this->synExecuteSql();

        $ajaxLogic = new \app\admin\logic\AjaxLogic;
        $ajaxLogic->system_langnum_file(); // 记录当前是多语言还是单语言到文件里
        $ajaxLogic->system_citysite_file(); // 记录当前是否多站点到文件里

        $ajaxLogic->admin_logic_1609900642(); // 内置方法
        // 纠正SQL缓存表结果字段类型(v1.6.1节点去掉--陈风任)
        $ajaxLogic->admin_logic_1623036205();

        $viewfile = 'welcome';
        $web_theme_welcome_tplname = empty($this->globalConfig['web_theme_welcome_tplname']) ? '' : $this->globalConfig['web_theme_welcome_tplname'];
        if (!empty($web_theme_welcome_tplname) && file_exists("application/admin/template/theme/{$web_theme_welcome_tplname}")) {
            $welcome_tplname = str_ireplace('.htm', '', $web_theme_welcome_tplname);
            $viewfile = "theme/{$welcome_tplname}";
        }

        if (preg_match('/^(.*)\/welcome_shop$/i', $viewfile)) {
            // 商城版欢迎页主题
            $this->eyouCmsLogic->welcome_shop($assign_data, $this->globalConfig, $this->usersConfig);
        } else if (preg_match('/^(.*)\/welcome_taskflow$/i', $viewfile)) {
            // 任务流版欢迎页主题
            $this->eyouCmsLogic->welcome_taskflow($assign_data, $this->globalConfig, $this->usersConfig);
        } else {
            // 默认欢迎页主题
            $this->eyouCmsLogic->welcome_default($assign_data, $this->globalConfig, $this->usersConfig);
        }

        $this->assign($assign_data);
        $html = $this->fetch($viewfile);

        // 后台密码修改提醒 - 大黄
        if (file_exists('./weapp/PasswordRemind/model/PasswordRemindModel.php')) {
            $PasswordRemindModel = new \weapp\PasswordRemind\model\PasswordRemindModel;
            $html = $PasswordRemindModel->getInfo($html);
        }

        return $html;
    }

    /**
     * 实时概况快捷导航管理
     */
    public function ajax_surveyquickmenu()
    {
        if (IS_AJAX_POST) {
            $checkedids = input('post.checkedids/a', []);
            if (count($checkedids) != 4){
                $this->error('保存数量必须为4个');
            }
            $ids = input('post.ids/a', []);
            $saveData = [];
            foreach ($ids as $key => $val) {
                if (in_array($val, $checkedids)) {
                    $checked = 1;
                } else {
                    $checked = 0;
                }
                $saveData[$key] = [
                    'id'            => $val,
                    'checked'       => $checked,
                    'sort_order'    => intval($key) + 1,
                    'update_time'   => getTime(),
                ];
            }
            if (!empty($saveData)) {
                $r = model('Quickentry')->saveAll($saveData);
                if ($r !== false) {
                    $this->success('操作成功', url('Index/welcome'));
                }
            }
            $this->error('操作失败');
        }
        $menuList = Db::name('quickentry')->where([
            'type'      => 21,
            'groups'    => 1,
            'status'    => 1,
        ])->order('sort_order asc, id asc')->select();

        $this->assign('menuList',$menuList);

        return $this->fetch();
    }

    /**
     * 实时概况快捷导航管理 - 任务流版
     */
    public function ajax_surveyquickmenu_taskflow()
    {
        if (IS_AJAX_POST) {
            $checkedids = input('post.checkedids/a', []);
            $ids = input('post.ids/a', []);
            $saveData = [];
            foreach ($ids as $key => $val) {
                if (in_array($val, $checkedids)) {
                    $checked = 1;
                } else {
                    $checked = 0;
                }
                $saveData[$key] = [
                    'id'            => $val,
                    'checked'       => $checked,
                    'sort_order'    => intval($key) + 1,
                    'update_time'   => getTime(),
                ];
            }
            if (!empty($saveData)) {
                $r = model('Quickentry')->saveAll($saveData);
                if ($r !== false) {
                    $this->success('操作成功', url('Index/welcome'));
                }
            }
            $this->error('操作失败');
        }
        $menuList = Db::name('quickentry')->where([
            'type'      => 31,
            'groups'    => 1,
            'status'    => 1,
        ])->order('sort_order asc, id asc')->select();

        $this->assign('menuList',$menuList);

        return $this->fetch();
    }

    /**
     * 升级系统时，同时处理sql语句
     * @return [type] [description]
     */
    private function synExecuteSql()
    {
        // 新增订单提醒的邮箱模板
        if (!tpCache('global.system_smtp_tpl_5')){
            /*多语言*/
            if (is_language()) {
                $langRow = Db::name('language')->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->order('id asc')
                    ->select();
                foreach ($langRow as $key => $val) {
                    $r = Db::name('smtp_tpl')->insert([
                        'tpl_name'      => '订单提醒',
                        'tpl_title'     => '您有新的订单消息，请查收！',
                        'tpl_content'   => '${content}',
                        'send_scene'    => 5,
                        'is_open'       => 1,
                        'lang'          => $val['mark'],
                        'add_time'      => getTime(),
                    ]);
                    false !== $r && tpCache('system', ['system_smtp_tpl_5' => 1], $val['mark']);
                }
            } else { // 单语言
                $r = Db::name('smtp_tpl')->insert([
                    'tpl_name'      => '订单提醒',
                    'tpl_title'     => '您有新的订单消息，请查收！',
                    'tpl_content'   => '${content}',
                    'send_scene'    => 5,
                    'is_open'       => 1,
                    'lang'          => $this->admin_lang,
                    'add_time'      => getTime(),
                ]);
                false !== $r && tpCache('system', ['system_smtp_tpl_5' => 1]);
            }
            /*--end*/
        }
    }

    /**
     * 内容统计管理
     */
    public function ajax_content_total()
    {
        if (IS_AJAX_POST) {
            $checkedids = input('post.checkedids/a', []);
            $ids = input('post.ids/a', []);
            $saveData = [];
            foreach ($ids as $key => $val) {
                if (in_array($val, $checkedids)) {
                    $checked = 1;
                } else {
                    $checked = 0;
                }
                $saveData[$key] = [
                    'id'            => $val,
                    'checked'       => $checked,
                    'sort_order'    => intval($key) + 1,
                    'update_time'   => getTime(),
                ];
            }
            if (!empty($saveData)) {
                $r = model('Quickentry')->saveAll($saveData);
                if ($r) {
                    $this->success('操作成功', url('Index/welcome'));
                }
            }
            $this->error('操作失败');
        }

        /*同步v1.3.9以及早期版本的自定义模型*/
        $this->syn_custom_quickmenu(2);
        /*end*/

        $totalList = Db::name('quickentry')->where([
                'type'      => ['IN', [2]],
                'status'    => 1,
            ])->order('sort_order asc, id asc')->select();
        $this->assign('totalList',$totalList);

        return $this->fetch();
    }

    /**
     * 快捷导航管理
     */
    public function ajax_quickmenu()
    {
        if (IS_AJAX_POST) {
            $checkedids = input('post.checkedids/a', []);
            $ids = input('post.ids/a', []);
            $saveData = [];
            foreach ($ids as $key => $val) {
                if (in_array($val, $checkedids)) {
                    $checked = 1;
                } else {
                    $checked = 0;
                }
                $saveData[$key] = [
                    'id'            => $val,
                    'checked'       => $checked,
                    'sort_order'    => intval($key) + 1,
                    'update_time'   => getTime(),
                ];
            }
            if (!empty($saveData)) {
                $r = model('Quickentry')->saveAll($saveData);
                if ($r) {
                    $this->success('操作成功', url('Index/welcome'));
                }
            }
            $this->error('操作失败');
        }

        $welcome_type = input('param.welcome_type/s');
        if ($welcome_type == 'shop') {
            $type = [11];
        } else {
            /*同步v1.3.9以及早期版本的自定义模型*/
            $this->syn_custom_quickmenu(1);
            /*end*/
            $type = [1];
        }

        $menuList = Db::name('quickentry')->where([
                'type'      => ['IN', $type],
                'groups'    => 0,
                'status'    => 1,
            ])->order('sort_order asc, id asc')->select();
        foreach ($menuList as $key => $val) {
            if ($this->php_servicemeal <= 2 && $val['controller'] == 'Shop' && $val['action'] == 'index') {
                unset($menuList[$key]);
                continue;
            }
            if (!empty($this->globalConfig['web_recycle_switch']) && $val['controller'] == 'RecycleBin' && $val['action'] == 'archives_index'){
                unset($menuList[$key]);
                continue;
            }
            if (is_language() && $this->main_lang != $this->admin_lang) {
                $controllerArr = ['Weapp','Filemanager','Sitemap','Admin','Member','Seo','Channeltype','Tools'];
                if (empty($globalConfig['language_split'])) {
                    $controllerArr[] = 'RecycleBin';
                }
                $ctlActArr = ['System@water','System@thumb','System@api_conf'];
                if (in_array($val['controller'], $controllerArr) || in_array($val['controller'].'@'.$val['action'], $ctlActArr)) {
                    unset($menuList[$key]);
                    continue;
                }
            }
        }
        $this->assign('menuList',$menuList);

        return $this->fetch();
    }

    /**
     *
     * 插件快捷导航管理
     */
    public function ajax_weapp_quickmenu()
    {
        if (IS_AJAX_POST) {
            $checkedids = input('post.checkedids/a', []);
            $ids = input('post.ids/a', []);
            $saveData = [];
            foreach ($ids as $key => $val) {
                if (in_array($val, $checkedids)) {
                    $checked = 1;
                } else {
                    $checked = 0;
                }
                $saveData[$key] = [
                    'id'            => $val,
                    'checked'       => $checked,
                    'quick_sort'    => intval($key) + 1,
                    'update_time'   => getTime(),
                ];
            }
            if (!empty($saveData)) {
                $r = model('Weapp')->saveAll($saveData);
                if ($r !== false) {
                    $this->success('操作成功', url('Index/welcome'));
                }
            }
            $this->error('操作失败');
        }

        $where = ['status'=>1];
        $menuList = Db::name('weapp')->where($where)->order('quick_sort asc, id asc')->select();
        $this->assign('menuList',$menuList);
        return $this->fetch();
    }

    /**
     * 同步自定义模型的快捷导航
     */
    private function syn_custom_quickmenu($type = 1)
    {
        $row = Db::name('quickentry')->where([
                'controller'    => 'Custom',
                'type'  => $type,
            ])->count();
        if (empty($row)) {
            $customRow = Db::name('channeltype')->field('id,ntitle')
                ->where(['ifsystem'=>0])->select();
            $saveData = [];
            foreach ($customRow as $key => $val) {
                $saveData[] = [
                    'title' => $val['ntitle'],
                    'laytext'   => $val['ntitle'].'列表',
                    'type' => $type,
                    'controller' => 'Custom',
                    'action' => 'index',
                    'vars' => 'channel='.$val['id'],
                    'groups'    => 1,
                    'sort_order' => 100,
                    'add_time' => getTime(),
                    'update_time' => getTime(),
                ];
            }
            model('Quickentry')->saveAll($saveData);
        }
    }

    /**
     * 录入商业授权
     */
    public function authortoken()
    {
        $is_force = input('param.is_force/d', 0);
        $redata = verify_authortoken($is_force);
        if (!empty($redata['code'])) {
            $source = realpath('public/static/admin/images/logo_ey.png');
            $destination = realpath('public/static/admin/images/logo.png');
            @copy($source, $destination);

            adminLog('验证授权');
            $this->success('授权校验成功', request()->baseFile(), '', 1, [], '_parent');
        }
        $msg = empty($redata['msg']) ? '域名（'.request()->host(true).'）未授权' : $redata['msg'];
        $this->error($msg, request()->baseFile(), '', 5, [], '_parent');
    }

    /**
     * 更换后台logo
     */
    public function edit_adminlogo()
    {
        $filename = input('param.filename/s', '');
        if (!empty($filename)) {
            $source = realpath(preg_replace('#^'.ROOT_DIR.'/#i', '', $filename)); // 支持子目录
            $web_is_authortoken = tpCache('global.web_is_authortoken');
            if (empty($web_is_authortoken)) {
                $destination = realpath('public/static/admin/images/logo.png');
            } else {
                $destination = realpath('public/static/admin/images/logo_ey.png');
            }
            if (@copy($source, $destination)) {
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    /**
     * 待处理事项
     */
    public function pending_matters()
    {
        $html = '<div style="text-align: center; margin: 20px 0px; color:red;">惹妹子生气了，没啥好处理！</div>';
        echo $html;
    }
    
    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal()
    {
        if (IS_AJAX_POST) {
            $url = null;
            $data = [
                'refresh'   => 0,
            ];

            $param    = input('param.');
            $table    = input('param.table/s'); // 表名
            $id_name  = input('param.id_name/s'); // 表主键id名
            $id_value = input('param.id_value/d'); // 表主键id值
            $field    = input('param.field/s'); // 修改哪个字段
            $value    = input('param.value/s', '', null); // 修改字段值
            $value    = eyPreventShell($value) ? $value : strip_sql($value);
            $_POST[$id_name] = $id_value;
            if ('archives' == $table && 'arcrank' == $field) {
                $ScreeningTable = $table;
                $ScreeningField = $field;
                $ScreeningValue = $value;
                $ScreeningAid   = $id_value;
            }

            /*插件专用*/
            if ('weapp' == $table) {
                if (1 == intval($value)) { // 启用
                    action('Weapp/enable', ['id' => $id_value]);
                } else if (-1 == intval($value)) { // 禁用
                    action('Weapp/disable', ['id' => $id_value]);
                }
            }
            /*end*/

            /*处理数据的安全性*/
            if (empty($id_value)) {
                $this->error('查询条件id不合法！');
            }
            foreach ($param as $key => $val) {
                if ('value' == $key) {
                    if (stristr($val, '&lt;') && stristr($val, '&gt;')) {
                        $val = htmlspecialchars_decode($val);
                    }
                    if (preg_match('/<script([^\>]*)>/i', $val)) {
                        $this->error('数据含有非法入侵字符！');
                    }
                } else {
                    if (!preg_match('/^([A-Za-z0-9_-]*)$/i', $val)) {
                        $this->error('数据含有非法入侵字符！');
                    }
                }
            }
            /*end*/

            switch ($table) {
                // 会员等级表
                case 'users_level':
                    {
                        $return = model('UsersLevel')->isRequired($id_name,$id_value,$field,$value);
                        if (is_array($return)) {
                            $this->error($return['msg']);
                        }
                    }
                    break;
                
                // 会员属性表
                case 'users_parameter':
                    {
                        $return = model('UsersParameter')->isRequired($id_name,$id_value,$field,$value);
                        if (is_array($return)) {
                            $time = !empty($return['time']) ? $return['time'] : 3;
                            $this->error($return['msg'], null, [], $time);
                        }
                    }
                    break;
                
                // 会员中心菜单表
                case 'users_menu':
                    {
                        if ('is_userpage' == $field) {
                            Db::name('users_menu')->where('id','gt',0)->update([
                                    'is_userpage'   => 0,
                                    'update_time'   => getTime(),
                                ]);
                        }
                        $data['refresh'] = 1;
                    }
                    break;
                
                // 会员投稿功能
                case 'archives':
                    {
                        if ('arcrank' == $field) {
                            if (0 == $value) {
                                $value = -1;
                            }else{
                                $value = 0;
                            }
                        }
                    }
                    break;

                // 会员产品类型表
                case 'users_type_manage':
                    {
                        if (in_array($field, ['type_name','price'])) {
                            if (empty($value)) {
                                $this->error('不可为空');
                            }
                        }
                    }
                    break;

                // 留言属性表
                case 'guestbook_attribute':
                    {
                        $return = model('GuestbookAttribute')->isValidate($id_name,$id_value,$field,$value);
                        if (is_array($return)) {
                            $time = !empty($return['time']) ? $return['time'] : 3;
                            $this->error($return['msg'], null, [], $time);
                        }
                    }
                    break;

                // 小程序页面表
                case 'diyminipro_page':
                    {
                        $re = Db::name('diyminipro_page')->where([
                            'is_home'    => 1,
                            $id_name    => ['EQ', $id_value],
                        ])->count();
                        if (!empty($re)) {
                            $this->error('禁止取消默认项', null, [], 3);
                        }
                    }
                    break;

                // 文档属性表
                case 'archives_flag':
                    {
                        if (in_array($field, ['flag_name'])) {
                            if(empty($value)){
                                $this->error('属性名称不能为空', null, [],2);
                            }
                            $value = htmlspecialchars($value);
                        }
                        if ('sort_order' == $field) {
                            $data['refresh'] = 1;
                            $data['time'] = 500;
                        }
                    }
                    break;
                // 会员中心移动端底部菜单表
                case 'users_bottom_menu':
                    {
                        if ('sort_order' == $field) {
                            $data['refresh'] = 1;
                            $data['time'] = 500;
                        }
                    }
                    break;

                // 友情链接分组表
                case 'links_group':
                    {
                        if ('sort_order' == $field) {
                            $data['refresh'] = 1;
                            $data['time'] = 500;
                        }
                    }
                    break;

                // 栏目表
                case 'arctype':
                    {
                        if ('is_hidden' == $field) {
                            $value = (1 == $value) ? 0 : 1;
                        }
                    }
                    break;

                // 多语言表
                case 'language':
                    {
                        $return = model('Language')->isValidateStatus($field,$value);
                        if (is_array($return)) {
                            $time = !empty($return['time']) ? $return['time'] : 3;
                            $this->error($return['msg'], null, [], $time);
                        }
                    }
                    break;
                // 积分商品列表
                case 'memgift':
                    {
                        if ('sort_order' == $field) {
                            $data['refresh'] = 1;
                            $data['time'] = 500;
                        }
                    }
                    break;

                // 邮箱自定义模板表
                case 'smtp_tpl':
                    {
                        if (in_array($field, ['tpl_title'])) {
                            $value = htmlspecialchars($value);
                        }
                    }
                    break;

                // 站内信自定义模板表
                case 'users_notice_tpl':
                    {
                        if (in_array($field, ['tpl_title'])) {
                            $value = htmlspecialchars($value);
                        }
                    }
                    break;

                // 手机端会员中心底部菜单
                case 'users_bottom_menu':
                    {
                        if (in_array($field, ['title'])) {
                            $value = htmlspecialchars($value);
                        }
                    }
                    break;
                default:
                    # code...
                    break;
            }

            $savedata = [
                $field => $value,
                'update_time'   => getTime(),
            ];
            switch ($table) {
                case 'diyminipro_page':
                {
                    if ('is_home' == $field) {
                        if ($value == 1) {
                            $savedata['page_type'] = 1;
                        } else {
                            $savedata['page_type'] = -1;
                        }
                    }
                    break;
                }
            }
            // 根据条件保存修改的数据
            $r = Db::name($table)->where([$id_name => $id_value])->cache(true,null,$table)->save($savedata);
            if ($r !== false) {
                if (!empty($ScreeningTable) && !empty($ScreeningField) && 'archives' == $ScreeningTable && 'arcrank' == $ScreeningField) {
                    $Result = model('SqlCacheTable')->ScreeningArchives($ScreeningAid, $ScreeningValue);
                    if (!empty($Result)) {
                        $data['refresh'] = 1;
                        $data['time'] = 500;
                    }
                }elseif ('users' == $table && 'is_activation' == $field){
                    $data['refresh'] = 1;
                    $data['time'] = 500;
                }
                // 以下代码可以考虑去掉，与行为里的清除缓存重复 AppEndBehavior.php / clearHtmlCache
                switch ($table) {
                    case 'auth_modular':
                        extra_cache('admin_auth_modular_list_logic', null);
                        extra_cache('admin_all_menu', null);
                        break;

                    case 'diyminipro_page':
                    {
                        if ('is_home' == $field) {
                            $data['refresh'] = 1;
                            Db::name('diyminipro_page')->where([
                                $id_name    => ['NEQ', $id_value],
                                'lang'      => $this->admin_lang,
                            ])->update([
                                'is_home'    => 0, 
                                'page_type'    => -1, 
                                'update_time'   => getTime()
                            ]);
                        }
                        break;
                    }
                
                    // 会员投稿功能
                    case 'archives':
                    {
                        if ('arcrank' == $field) {
                            model('Arctype')->hand_type_count(['aid'=>[$id_value]]);//统计栏目文档数量
                            Db::name('taglist')->where('aid', $id_value)->update([
                                'arcrank'=>$value,
                                'update_time'   => getTime(),
                            ]);
                            \think\Cache::clear('taglist');
                            adminLog('文档'.($value >=0 ? '通过审核' : '取消审核').'：'.$id_value);
                            if (isset($value) && -1 === intval($value)) {
                                // 系统快捷下架时，积分商品的被动处理
                                model('ShopPublicHandle')->pointsGoodsPassiveHandle([$id_value]);
                            }
                            // 清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表
                            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
                            model('SqlCacheTable')->InsertSqlCacheTable(true);
                        }
                        break;
                    }
                
                    // 问答插件
                    case 'weapp_ask_users_level':
                    {
                        if ('ask_is_release' == $field) {
                            Db::name('users_level')->where('level_id', $id_value)->update([
                                'ask_is_release'=>$value,
                                'update_time'   => getTime(),
                            ]);
                        } else if ('ask_is_review' == $field) {
                            Db::name('users_level')->where('level_id', $id_value)->update([
                                'ask_is_review'=>$value,
                                'update_time'   => getTime(),
                            ]);
                        }
                        \think\Cache::clear('users_level');
                        break;
                    }
                
                    // 会员字段
                    case 'users_list':
                    case 'users_parameter':
                    {
                        \think\Cache::clear('users_parameter');
                        \think\Cache::clear('users_list');
                        break;
                    }
                
                    // 广告
                    case 'ad':
                    case 'ad_position':
                    {
                        \think\Cache::clear('ad_position');
                        \think\Cache::clear('ad');
                        break;
                    }
                
                    // 多语言
                    case 'language':
                    {
                        if ('status' == $field) {
                            /*统计多语言数量*/
                            model('Language')->setLangNum();
                            break;
                        }
                    }

                    default:
                        // 清除logic逻辑定义的缓存
                        extra_cache('admin_'.$table.'_list_logic', null);
                        break;
                }
                \think\Cache::clear($table);
                delFile(HTML_ROOT.'index');
                $this->success('更新成功', $url, $data);
            }
            $this->error('更新失败', null, []);
        }
    }

    /**
     * 功能地图
     */
    public function switch_map()
    {
        /*权限控制 by 小虎哥*/
        $auth_role_info = session('admin_info.auth_role_info');
        if(0 < intval(session('admin_info.role_id')) && ! empty($auth_role_info) && intval($auth_role_info['switch_map']) <= 0){
            $this->error('您没有操作权限，请联系超级管理员分配权限');
        }
        /*--end*/
        
        $msg = '操作成功';
        $seo_pseudo = $this->globalConfig['seo_pseudo'];
        $web_users_tpl_theme = $this->globalConfig['web_users_tpl_theme'];
        empty($web_users_tpl_theme) && $web_users_tpl_theme = 'users';

        if (IS_POST) {
            $inc_type = input('post.inc_type/s');
            $name = input('post.name/s');
            $value = input('post.value/s');
            $is_force = input('post.is_force/d'); // 是否强制开启，跳过检测提示，目前用于（多语言、多站点）
            $langRow = \think\Db::name('language')->order('id asc')->select();

            $data = [];
            switch ($inc_type) {
                case 'pay':
                case 'shop':
                {
                    foreach ($langRow as $key => $val) {
                        getUsersConfigData($inc_type, [$name => $value], $val['mark']);
                    }

                    // 开启商城
                    if (1 == $value) {
                        /*多语言 - 同时开启会员中心*/
                        foreach ($langRow as $key => $val) {
                            tpCache('web', ['web_users_switch' => 1], $val['mark']);
                        }
                        /*--end*/

                        // 同时显示发布文档时的价格文本框
                        Db::name('channelfield')->where([
                                'name'   => 'users_price',
                                'channel_id'  => 2,
                            ])->update([
                                'ifeditable'    => 1,
                                'update_time'   => getTime(),
                            ]);
                    }
                    if (in_array($name, ['shop_open'])) {
                        // $data['reload'] = 1;
                        /*检测是否存在订单中心模板*/
                        $shop_tpl_list = glob("./template/".TPL_THEME."pc/{$web_users_tpl_theme}/shop_*");
                        if (!empty($value) && empty($shop_tpl_list)) {
                            $is_syn = 1;
                        } else {
                            $is_syn = 0;
                        }
                        $data['is_syn'] = $is_syn;
                        /*--end*/
                        // 同步会员中心的左侧菜单
                        if ('shop_open' == $name) {
                            Db::name('users_menu')->where([
                                    'mca'   => 'user/Shop/shop_centre',
                                ])->update([
                                    'status'    => (1 == $value) ? 1 : 0,
                                    'update_time'   => getTime(),
                                ]);
                        }
                    } else if ('pay_open' == $name) {
                        // 同步会员中心的左侧菜单
                        Db::name('users_menu')->where([
                                'mca'   => 'user/Pay/pay_consumer_details',
                            ])->update([
                                'status'    => (1 == $value) ? 1 : 0,
                                'update_time'   => getTime(),
                            ]);
                        //同步会员中心手机端底部菜单开关
                        Db::name('users_bottom_menu')->where([
                                'mca'   => ['IN',['user/Pay/pay_account_recharge']]
                            ])->update([
                                'status'    => (1 == $value) ? 1 : 0,
                                'update_time'   => getTime(),
                            ]);
                    }

                    //同步会员中心手机端底部菜单开关  ---start
                    Db::name('users_bottom_menu')->where([
                            'mca'   => ['IN',['user/Shop/shop_centre','user/Shop/shop_cart_list']]
                        ])->update([
                            'status'    => (1 == $value) ? 1 : 0,
                            'update_time'   => getTime(),
                        ]);
                    //同步会员中心手机端底部菜单开关  ---end
                    break;
                }

                case 'users':
                {
                    // 会员投稿
                    if ('users_open_release' == $name) {
                        if (empty($this->php_servicemeal) && !empty($value)) {
                            $str = '6K+l5Yqf6IO95Y+q6ZmQ5LqO5o6I5p2D5Z+f5ZCN77yB';
                            $this->error(base64_decode($str));
                        }
                    }
                    
                    //同步会员中心手机端底部菜单开关  ---start
                    Db::name('users_bottom_menu')->where([
                            'mca'   => ['IN',['user/UsersRelease/article_add','user/UsersRelease/release_centre']]
                        ])->update([
                            'status'    => (1 == $value) ? 1 : 0,
                            'update_time'   => getTime(),
                        ]);
                    //同步会员中心手机端底部菜单开关  ---end
                    // 会员投稿
                    $r = Db::name('users_menu')->where([
                            'mca'  => 'user/UsersRelease/release_centre',
                        ])->update([
                            'status'      => (1 == $value) ? 1 : 0,
                            'update_time' => getTime(),
                        ]);
                    if ($r !== false) {
                        foreach ($langRow as $key => $val) {
                            getUsersConfigData($inc_type, [$name => $value], $val['mark']);
                        }

                        if (1 == $value) {
                            // 多语言 - 同时开启会员中心
                            foreach ($langRow as $key => $val) {
                                tpCache('web', ['web_users_switch' => 1], $val['mark']);
                            }
                            // end
                        }
                    }
                    break;
                }

                case 'level':
                {
                    // 会员升级
                    //同步会员中心手机端底部菜单开关  ---start
                    Db::name('users_bottom_menu')->where([
                            'mca'   => ['IN',['user/Level/level_centre','user/Pay/pay_account_recharge']]
                        ])->update([
                            'status'    => (1 == $value) ? 1 : 0,
                            'update_time'   => getTime(),
                        ]);
                    //同步会员中心手机端底部菜单开关  ---end

                    // 会员升级
                    $r = Db::name('users_menu')->where([
                            'mca'  => 'user/Level/level_centre',
                        ])->update([
                            'status'      => (1 == $value) ? 1 : 0,
                            'update_time' => getTime(),
                        ]);
                    if ($r) {
                        foreach ($langRow as $key => $val) {
                            getUsersConfigData($inc_type, [$name => $value], $val['mark']);
                        }

                        if (1 == $value) {
                            // 多语言 - 同时开启会员中心
                            $langRow = \think\Db::name('language')->order('id asc')->select();
                            foreach ($langRow as $key => $val) {
                                tpCache('web', ['web_users_switch' => 1], $val['mark']);
                            }
                            // end
                        }
                    }
                    break;
                }

                case 'web':
                {
                    if (empty($is_force)) {
                        if ($name == 'web_language_switch' && $value == 1) { // 多语言开关
                            if (!empty($this->globalConfig['web_citysite_open'])) {
                                $this->error('强制开启多语言，会自动关闭城市分站。');
                            }
                        } else if ($name == 'web_citysite_open' && $value == 1) { // 多站点开关
                            if (!empty($this->globalConfig['web_language_switch'])) {
                                $this->error('强制开启城市分站，会自动关闭多语言。');
                            }
                        }
                    }

                    /*多语言*/
                    foreach ($langRow as $key => $val) {
                        tpCache($inc_type, [$name => $value], $val['mark']);
                    }
                    /*--end*/

                    if (in_array($name, ['web_users_switch'])) {
                        // $data['reload'] = 1;
                        /*检测是否存在会员中心模板*/
                        if (!empty($value) && !file_exists('template/'.TPL_THEME.'pc/'.$web_users_tpl_theme)) {
                            $is_syn = 1;
                        } else {
                            $is_syn = 0;
                        }
                        $data['is_syn'] = $is_syn;
                        /*--end*/
                        // 同时关闭会员相关的开关
                        if (empty($value)) {
                            getUsersConfigData('users', ['users_open_release' => 0]); // 会员投稿
                            getUsersConfigData('level', ['level_member_upgrade' => 0]); // 会员升级
                            getUsersConfigData('shop', ['shop_open' => 0]); // 商城中心
                            getUsersConfigData('pay', ['pay_open' => 0]); // 支付功能
                        }
                    } else if ($name == 'web_language_switch') { // 多语言开关
                        // 统计多语言数量
                        model('Language')->setLangNum();
                        // 重新生成sitemap.xml
                        sitemap_all();
                        // 强制关闭多站点
                        if (!empty($is_force)) {
                            $data['reload'] = 1;
                            foreach ($langRow as $key => $val) {
                                tpCache('web', ['web_citysite_open' => 0], $val['mark']);
                            }
                            model('Citysite')->setCitysiteOpen();
                        }
                        // 清除页面缓存
                        delFile(HTML_ROOT);
                    } else if ($name == 'web_citysite_open') { // 多城市站点开关
                        model('Citysite')->setCitysiteOpen();
                        // 强制关闭多语言
                        if (!empty($is_force)) {
                            $data['reload'] = 1;
                            $msg = "已开启城市分站<br/>1、仅支持动态URL、伪静态这两种模式；<br/>2、可在下方的【高级扩展】进入城市分站；";
                        }
                        foreach ($langRow as $key => $val) {
                            tpCache('web', ['web_language_switch' => 0], $val['mark']);
                            if (!empty($value) && 2 == $seo_pseudo) {
                                tpCache('seo', ['seo_pseudo'=>1, 'seo_dynamic_format'=>1], $val['mark']);
                                if (file_exists('./index.html')) {
                                    @unlink('./index.html');
                                }
                            }
                        }
                        // 统计多语言数量
                        model('Language')->setLangNum();
                        // 重新生成sitemap.xml
                        sitemap_all();
                        // 清除页面缓存
                        delFile(HTML_ROOT);
                    }
                    break;
                }
            }

            $this->success($msg, null, $data);
        }

        /*代理贴牌功能限制-s*/
        $weapp_switch = true;
        if (function_exists('checkAuthRule')) {
            //插件应用
            $weapp_switch = checkAuthRule(2005);
        }
        $this->assign('weapp_switch', $weapp_switch);
        /*代理贴牌功能限制-e*/

        if (file_exists('./data/conf/memgift_open.txt')) {
            $this->usersConfig['memgift_open'] = 1;
            $this->assign('usersConfig',$this->usersConfig);
        }

        $is_online = 0;
        if (is_realdomain()) {
            $is_online = 1;
        }
        $this->assign('is_online',$is_online);

        /*检测是否存在会员中心模板*/
        if (!file_exists('template/'.TPL_THEME.'pc/'.$web_users_tpl_theme)) {
            $is_themeusers_exist = 1;
        } else {
            $is_themeusers_exist = 0;
        }
        $this->assign('is_themeusers_exist',$is_themeusers_exist);
        /*--end*/

        /*检测是否存在商城中心模板*/
        $shop_tpl_list = glob("./template/".TPL_THEME."pc/{$web_users_tpl_theme}/shop_*");
        if (empty($shop_tpl_list)) {
            $is_themeshop_exist = 1;
        } else {
            $is_themeshop_exist = 0;
        }
        $this->assign('is_themeshop_exist',$is_themeshop_exist);
        /*--end*/

        /*支付接口*/
        $pay = Db::name('pay_api_config')->where('status', 1)->order('pay_id asc')->select();
        foreach ($pay as $key => $val) {
            if (1 == $val['system_built']) {
                $val['litpic'] = $this->root_dir . "/public/static/admin/images/{$val['pay_mark']}.png";
            } else {
                $val['litpic'] = $this->root_dir . "/weapp/{$val['pay_mark']}/logo.png";
            }
            $pay[$key] = $val;
        }
        $this->assign('pay_list', $pay);
        /*--end*/

        //获取所有权限列表（id为键值的list格式）
        $all_menu_tree = getAllMenu();
        $all_menu_list = tree_to_list($all_menu_tree,'child','id');
        $this->assign('all_menu_list',$all_menu_list);

        //选中的且需要展示在“当前导航”菜单栏目
        $admin_menu_list = Db::name("admin_menu")->field("menu_id,controller_name,action_name,title,icon,is_menu,is_switch")->where(['is_menu'=>1,'status'=>1])->order("sort_order asc,update_time asc")->select();
        $admin_menu_id_arr = [];  //在“当前导航”栏目显示菜单集合
        foreach ($admin_menu_list as $key=>$val){
            $admin_menu_id_arr[] = $val['menu_id'];
            
            // 其他语言不显示留言管理
            /*if ($this->admin_lang != $this->main_lang) {
                foreach ([2004018] as $_k => $_v) {
                    if ($_v == $val['menu_id']) {
                        unset($admin_menu_list[$key]);
                    }
                }
            }*/
        }
        //用户手动关闭的权限集合
        $this->assign('admin_menu_id_arr', $admin_menu_id_arr);
        $menu_list = getAdminMenuList($admin_menu_list);
        $this->assign('menu_list',$menu_list);
        //获取因为没有开启相关模块没有权限的节点（用于初始化）
        $not_role_menu_id_arr = get_not_role_menu_id();
        $this->assign('not_role_menu_id_arr',$not_role_menu_id_arr);

        //模块开关与入口关联(用于动态js)
        $global = include APP_PATH.MODULE_NAME.'/conf/global.php';
        $this->assign('module_rele_menu',$global['module_rele_menu']);
        $this->assign('module_default_menu',$global['module_default_menu']);
        $this->assign('module_reverse_menu',$global['module_reverse_menu']);

        // 可视编辑入口
        $is_show_uiset = 0;
        if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/uiset.txt') || file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/uiset.txt')) {
            $is_show_uiset = 1;
        }
        $this->assign('is_show_uiset', $is_show_uiset);
        //创始人才有权限控制
        $admin_info = session('admin_info');
        $is_founder = !empty($admin_info['is_founder']) ? $admin_info['is_founder'] : 0;
        $this->assign('is_founder', $is_founder);

        $security_ask_open = (int)tpSetting('security.security_ask_open');
        $this->assign('security_ask_open', $security_ask_open);

        $shopLogic = new \app\admin\logic\ShopLogic;
        // 列出营销功能里已使用的模块
        $marketFunc = $shopLogic->marketLogic();
        $this->assign('marketFunc', $marketFunc);
        // 列出功能地图里已使用的模块
        $useFunc = $shopLogic->useFuncLogic();
        $this->assign('useFunc', $useFunc);

        $foreign_authorize = tpSetting('foreign.foreign_authorize', [], 'cn');
        $this->assign('foreign_authorize', $foreign_authorize);

        return $this->fetch();
    }

    // 更新数据缓存表信息
    public function update_sql_cache_table()
    {
        $CacheMaxID = Db::name('sql_cache_table')->where('sql_name', 'ArchivesMaxID')->getField('sql_result');
        if (empty($CacheMaxID)) {
            // 添加查询执行语句到mysql缓存表
            model('SqlCacheTable')->InsertSqlCacheTable(true);
        } else {
            $ArchivesMaxID = Db::name('archives')->max('aid');
            if ($ArchivesMaxID != $CacheMaxID) {
                /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
                Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
                model('SqlCacheTable')->InsertSqlCacheTable(true);
                /* END */
            }
        }
    }

    /**
     * 主题风格
     * @return [type] [description]
     */
    public function theme_index()
    {
        // 主题风格
        // $list = Db::name('admin_theme')->where(['theme_type'=>1])->order('is_system desc, sort_order asc, theme_id asc')->select();
        // $this->assign('list', $list);

        // 登录页自定义模板
        $login_tplist = glob('application/admin/template/theme/login_*.htm');
        foreach ($login_tplist as $key => $val) {
            $val = preg_replace('/^(.*)login_([\w\-]+)\.htm$/i', 'login_${2}.htm', $val);
            $login_tplist[$key] = $val;
        }
        $this->assign('login_tplist', $login_tplist);

        // 欢迎页主题风格
        $welcome_list = Db::name('admin_theme')->where(['theme_type'=>2])->order('is_system desc, sort_order asc, theme_id asc')->select();
        foreach ($welcome_list as $key => $val) {
            $val['disabled'] = $val['disabled_tips'] = '';
            if ($val['welcome_tplname'] == 'welcome_shop.htm') {
                if (empty($this->usersConfig['shop_open'])) {
                    $val['disabled'] = ' disabled="disabled" readonly="true" ';
                    $val['disabled_tips'] = ' title="需开启商城中心才能使用" ';
                }
            } else if ($val['welcome_tplname'] == 'welcome_taskflow.htm') {
                $weappRow = model('weapp')->getWeappList('TaskFlow');
                if (!is_dir('./weapp/TaskFlow/') || empty($weappRow['status'])) {
                    $val['disabled'] = ' disabled="disabled" readonly="true" ';
                    $val['disabled_tips'] = ' title="需安装【工作任务流】插件才能使用" ';
                }
            }
            $welcome_list[$key] = $val;
        }
        $this->assign('welcome_list', $welcome_list);

        return $this->fetch();
    }

    /**
     * 主题设置 - 保存
     * @return [type] [description]
     */
    public function theme_conf()
    {
        if (IS_POST) {
            $post = input('post.');
            $webData = [];
            $image_ext = config('global.image_ext');
            $image_ext_arr = explode(',', $image_ext);
            foreach ($post as $key => $val) {
                $val = trim($val);
                if (in_array($key, ['admin_logo','login_logo','login_bgimg','web_ico'])) { // 后台LOGO/登录LOGO
                    $source = preg_replace('#^'.$this->root_dir.'#i', '', $val); // 支持子目录
                    $source_ext = pathinfo('.'.$source, PATHINFO_EXTENSION);
                    if (!empty($source_ext) && !in_array($source_ext, $image_ext_arr)) {
                        $this->error('上传图片扩展名错误！');
                    }
                }
                if ('theme_id' == $key) {
                    $key = 'web_theme_styleid';
                } else if ('login_logo' == $key) {
                    $key = 'web_loginlogo';
                } else if ('login_bgimg_model' == $key) {
                    $key = 'web_loginbgimg_model';
                } else if ('login_bgimg' == $key) {
                    $key = 'web_loginbgimg';
                } else if ('theme_color_model' == $key) {
                    $key = 'web_theme_color_model';
                } else if ('theme_main_color' == $key) {
                    $key = 'web_theme_color';
                } else if ('theme_assist_color' == $key) {
                    $key = 'web_assist_color';
                } else if ('admin_logo' == $key) {
                    $key = 'web_adminlogo';
                } else if ('login_tplname' == $key) {
                    $key = 'web_theme_login_tplname';
                }
                $webData[$key] = $val;
            }
            $webData['web_theme_style_uptime'] = getTime();
            if (!empty($webData)) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('web', $webData, $val['mark']);
                    }
                } else {
                    tpCache('web', $webData);
                }
                /*--end*/
                $ajaxLogic = new \app\admin\logic\AjaxLogic;
                $ajaxLogic->admin_update_theme_css();
            }

            $is_change = 0;
            // $theme_info = Db::name('admin_theme')->field('theme_title,theme_pic,add_time,update_time', true)->where(['theme_id'=>$post['theme_id']])->find();
            // foreach ($post as $key => $val) {
            //     if (in_array($key, ['login_logo','login_bgimg','admin_logo'])) {
            //         $val = handle_subdir_pic($val);
            //         $theme_info[$key] = handle_subdir_pic($theme_info[$key]);
            //     }
            //     if (isset($theme_info[$key]) && $theme_info[$key] != $val) {
            //         $is_change = 1;
            //         break;
            //     }
            // }
            // if (empty($post['is_select_theme'])) {
            //     $is_change = 0;
            // }

            $this->success('操作成功，需刷新后台看效果！', null, ['is_change'=>$is_change]);
        }
    }

    /**
     * 欢迎页设置 - 保存
     * @return [type] [description]
     */
    public function theme_welcome_conf()
    {
        if (IS_POST) {
            $post = input('post.');
            $webData = ['web_theme_welcome_tplname'=>$post['welcome_tplname']];
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $webData, $val['mark']);
                }
            } else {
                tpCache('web', $webData);
            }
            /*--end*/
            $this->success('操作成功');
        }
    }

    /**
     * 新增主题风格
     * @return [type] [description]
     */
    public function theme_add_login()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['theme_title'] = trim($post['theme_title']);
            if (empty($post['theme_title'])) {
                $this->error('主题名称不能为空！');
            }
            if (isset($post['theme_id'])) {
                unset($post['theme_id']);
            }
            $newData = [
                'theme_type'=>1,
                'is_system' => 0,
                'sort_order' => 100,
                'add_time' => getTime(),
                'update_time' => getTime(),
            ];
            $newData = array_merge($post, $newData);
            $theme_id = Db::name('admin_theme')->insertGetId($newData);
            if ($theme_id !== false) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('web', ['web_theme_styleid'=>$theme_id], $val['mark']);
                    }
                } else {
                    tpCache('web', ['web_theme_styleid'=>$theme_id]);
                }
                /*--end*/
                $this->success('操作成功，需刷新后台看效果！');
            }
            $this->error('操作失败');
        }
    }

    /**
     * 获取主题风格信息
     * @return [type] [description]
     */
    public function ajax_get_theme_info()
    {
        $theme_id = input('param.theme_id/d');
        $info = Db::name('admin_theme')->where(['theme_id'=>$theme_id])->find();
        $this->success('读取成功', null, ['info'=>$info]);
    }

    /**
     * 生成随机欢迎页文件名，确保唯一性
     */
    private function theme_rand_filename($filename = '', $prefix = 'style', $filename_list = [])
    {
        if (empty($filename)) {
            $filename = $prefix . mt_rand(100,999);
        }
        if (in_array($filename, $filename_list)) {
            $filename = $prefix . mt_rand(100,999);
            return $this->theme_rand_filename($filename, $prefix, $filename_list);
        }

        return $filename;
    }

    //ajax获取任务流数据
    public function get_task_list()
    {
        $this->eyouCmsLogic->get_task_list();
    }

    /**
     * 创建指定模板文件
     * @return [type] [description]
     */
    public function ajax_theme_tplfile_add()
    {
        $this->error('出于安全考虑已禁用，请使用ftp或易优助手插件修改模板');
        $type = input('param.type/s', '');
        $view_suffix = config('template.view_suffix');
        $tpldirpath = '';
        if ('welcome' == $type) {
            $select_input_id = 'welcome_tplname';
            $tpldirpath = '/application/admin/template/theme';
        } else if ('login' == $type) {
            $select_input_id = 'login_tplname';
            $tpldirpath = '/application/admin/template/theme';
        }

        if (IS_POST) {
            $post = input('post.', '', null);
            $content = input('post.content', '', null);
            $post['filename'] = trim($post['filename']);
            $post['theme_title'] = empty($post['theme_title']) ? '' : trim($post['theme_title']);
            if ('welcome' == $post['type']) {
                if (empty($post['theme_title'])) {
                    $this->error('模板名称不能为空！');
                }
            }
            if (!empty($post['filename'])) {
                if (!preg_match("/^[\w\-\_]{1,}$/u", $post['filename'])) {
                    $this->error('文件名称只允许字母、数字、下划线、连接符的任意组合！');
                }
                $filename = "{$type}_{$post['filename']}.{$view_suffix}";
            } else {
                $this->error('文件名称不能为空！');
            }

            if (file_exists(ROOT_PATH.ltrim($tpldirpath, '/').'/'.$filename)) {
                $this->error('文件名称已经存在，请重新命名！', null, ['focus'=>'filename']);
            }

            $nosubmit = input('param.nosubmit/d');
            if (1 == $nosubmit) {
                $this->success('检测通过');
            }

            if (empty($content)) {
                $this->error('HTML代码不能为空！');
            }

            $filemanagerLogic = new \app\admin\logic\FilemanagerLogic;
            $file = ROOT_PATH.trim($tpldirpath, '/').'/'.$filename;
            if (!is_writable(dirname($file))) {
                $this->error("请把以下目录设置为可写入权限<br/>{$tpldirpath}");
            }
            $ext = preg_replace('/^(.*)\.([^\.]+)$/i', '${2}', $filename);
            if ('htm' == $ext) {
                $content = htmlspecialchars_decode($content, ENT_QUOTES);
                if (preg_match('#<([^?]*)\?php#i', $content) || preg_match('#<\?(\s*)=#i', $content) || (preg_match('#<\?#i', $content) && preg_match('#\?>#i', $content)) || preg_match('#\{eyou\:php([^\}]*)\}#i', $content) || preg_match('#\{php([^\}]*)\}#i', $content) || preg_match('#(\s+)language(\s*)=(\s*)("|\')?php("|\')?#i', $content)) {
                    $this->error('模板里不允许有php语法，为了安全考虑，请通过FTP工具进行编辑上传。');
                }
                foreach ($filemanagerLogic->disableFuns as $key => $val) {
                    $val_new = msubstr($val, 0, 1).'-'.msubstr($val, 1);
                    $content = preg_replace("/(@)?".$val."(\s*)\(/i", "{$val_new}(", $content);
                }
            }
            $fp = fopen($file, "w");
            fputs($fp, $content);
            fclose($fp);

            $theme_id = 0;
            if ('welcome' == $post['type']) {
                $newData = [
                    'theme_type'=>2,
                    'theme_title'=>$post['theme_title'],
                    'theme_pic'=>ROOT_DIR."/public/static/admin/images/theme/theme_pic_default.png",
                    'welcome_tplname'=>$filename,
                    'is_system' => 0,
                    'sort_order' => 100,
                    'add_time' => getTime(),
                    'update_time' => getTime(),
                ];
                $theme_id = Db::name('admin_theme')->insertGetId($newData);
            }
            $data = [
                'filename'=>$filename,
                'type'=>$type,
                'select_input_id'=>$select_input_id,
                'theme_id'=>$theme_id,
                'theme_title'=>$post['theme_title'],
            ];
            $this->success('操作成功', null, $data);
        }

        $content = '';
        if ('welcome' == $type) {
            $content = file_get_contents(APP_PATH.'admin/template/index/welcome.htm');
        } else if ('login' == $type) {
            $content = file_get_contents(APP_PATH.'admin/template/admin/login.htm');
        }
        $this->assign('content', $content);
        $this->assign('type', $type);
        $this->assign('tpldirpath', $tpldirpath);
        return $this->fetch('theme_tplfile_add');
    }

    /**
     * 编辑指定模板文件
     * @return [type] [description]
     */
    public function ajax_theme_tplfile_edit()
    {
        $this->error('出于安全考虑已禁用，请使用ftp或易优助手插件修改模板');
        $type = input('param.type/s', '');
        if ('welcome' == $type) {
            $select_input_id = 'welcome_tplname';
        } else if ('login' == $type) {
            $select_input_id = 'login_tplname';
        }
        $tpldirpath = '/application/admin/template/theme';
        $view_suffix = config('template.view_suffix');

        if (IS_POST) {
            $post = input('post.', '', null);
            if (!empty($post['theme_id'])) {
                $content = input('post.content', '', null);
                $post['filename'] = trim($post['filename']);
                $post['theme_title'] = empty($post['theme_title']) ? '' : trim($post['theme_title']);
                if ('welcome' == $post['type']) {
                    if (empty($post['theme_title'])) {
                        $this->error('模板名称不能为空！');
                    }
                }
                if (!empty($post['filename'])) {
                    if (!preg_match("/^[\w\-\_]{1,}$/u", $post['filename'])) {
                        $this->error('文件名称只允许字母、数字、下划线、连接符的任意组合！');
                    }
                    $filename = "{$type}_{$post['filename']}.{$view_suffix}";
                } else {
                    $this->error('文件名称不能为空！');
                }

                if ($filename != $post['welcome_tplname'] && file_exists(ROOT_PATH.ltrim($tpldirpath, '/').'/'.$filename)) {
                    $this->error('文件名称已经存在，请重新命名！', null, ['focus'=>'filename']);
                }

                $nosubmit = input('param.nosubmit/d');
                if (1 == $nosubmit) {
                    $this->success('检测通过');
                }

                if (empty($content)) {
                    $this->error('HTML代码不能为空！');
                }

                $filemanagerLogic = new \app\admin\logic\FilemanagerLogic;
                $file = ROOT_PATH.trim($tpldirpath, '/').'/'.$filename;
                if (!is_writable(dirname($file))) {
                    $this->error("请把以下目录设置为可写入权限<br/>{$tpldirpath}");
                }
                $ext = preg_replace('/^(.*)\.([^\.]+)$/i', '${2}', $filename);
                if ('htm' == $ext) {
                    $content = htmlspecialchars_decode($content, ENT_QUOTES);
                    if (preg_match('#<([^?]*)\?php#i', $content) || preg_match('#<\?(\s*)=#i', $content) || (preg_match('#<\?#i', $content) && preg_match('#\?>#i', $content)) || preg_match('#\{eyou\:php([^\}]*)\}#i', $content) || preg_match('#\{php([^\}]*)\}#i', $content) || preg_match('#(\s+)language(\s*)=(\s*)("|\')?php("|\')?#i', $content)) {
                        $this->error('模板里不允许有php语法，为了安全考虑，请通过FTP工具进行编辑上传。');
                    }
                    foreach ($filemanagerLogic->disableFuns as $key => $val) {
                        $val_new = msubstr($val, 0, 1).'-'.msubstr($val, 1);
                        $content = preg_replace("/(@)?".$val."(\s*)\(/i", "{$val_new}(", $content);
                    }
                }
                $fp = fopen($file, "w");
                if ($fp != false && fwrite($fp, $content)) {
                    fclose($fp);
                    if ($filename != $post['welcome_tplname']) {
                        rename(ROOT_PATH.ltrim($tpldirpath, '/').'/'.$post['welcome_tplname'], ROOT_PATH.ltrim($tpldirpath, '/').'/'.$filename);
                    }
                }

                if ('welcome' == $post['type']) {
                    $newData = [
                        'theme_type'=>2,
                        'theme_title'=>$post['theme_title'],
                        'theme_pic'=>ROOT_DIR."/public/static/admin/images/theme/theme_pic_default.png",
                        'welcome_tplname'=>$filename,
                        'is_system' => 0,
                        'update_time' => getTime(),
                    ];
                    Db::name('admin_theme')->where(['theme_id'=>$post['theme_id']])->update($newData);
                }
                $data = [
                    'filename'=>$filename,
                    'type'=>$type,
                    'select_input_id'=>$select_input_id,
                    'theme_id'=>$post['theme_id'],
                    'theme_title'=>$post['theme_title'],
                ];
                $this->success('操作成功', null, $data);
            }
            $this->error('操作失败');
        }

        $theme_id = input('param.theme_id/d', 0);
        $info = Db::name('admin_theme')->where(['theme_id'=>$theme_id])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        if (!empty($info['is_system'])) {
            $this->error('内置模板禁止编辑，系统更新会覆盖');
        }

        $is_default_theme = 0;
        if (!empty($info['is_system']) && empty($info['welcome_tplname'])) {
            $is_default_theme = 1;
            if ('welcome' == $type) {
                $content = file_get_contents(APP_PATH."admin/template/index/{$type}.{$view_suffix}");
                $info['welcome_tplname'] = "welcome.{$view_suffix}";
            } else if ('login' == $type) {
                $viewfile = 'login';
                if (2 <= $this->php_servicemeal) {
                    $viewfile = 'login_zy';
                }
                $content = file_get_contents(APP_PATH."admin/template/admin/{$viewfile}.{$view_suffix}");
                $info['welcome_tplname'] = "{$viewfile}.{$view_suffix}";
            }
        } else {
            $content = file_get_contents(APP_PATH."admin/template/theme/{$info['welcome_tplname']}");
        }
        $info['filename'] = preg_replace('/^'.$type.'(_([^\.]+))?\.'.$view_suffix.'$/i', '${2}', $info['welcome_tplname']);
        $this->assign('content', $content);
        $this->assign('type', $type);
        $this->assign('tpldirpath', $tpldirpath);
        $this->assign('is_default_theme', $is_default_theme);
        $this->assign('info', $info);
        return $this->fetch('theme_tplfile_edit');
    }

    /**
     * 删除指定模板文件
     */
    public function ajax_theme_tplfile_del()
    {
        $theme_id = input('param.theme_id/d');
        if (IS_POST && !empty($theme_id)) {
            $type = input('param.type/s', '');
            $select_input_id = '';
            if ('welcome' == $type) {
                $select_input_id = 'welcome_tplname';
            } else if ('login' == $type) {
                $select_input_id = 'login_tplname';
            }
            $tpldirpath = '/application/admin/template/theme';
            $info = Db::name('admin_theme')->where(['theme_id'=>$theme_id])->find();
            $r = Db::name('admin_theme')->where(['theme_id'=>$theme_id])->delete();
            if ($r !== false) {
                @unlink('.'.$tpldirpath.'/'.$info['welcome_tplname']);
                adminLog('删除欢迎页模板：'.$info['theme_title']);
                $this->success('删除成功', null, ['select_input_id'=>$select_input_id]);
            }
        }
        $this->error('删除失败');
    }
}
