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

namespace app\admin\logic;

use think\Config;
use think\Model;
use think\Db;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class AjaxLogic extends Model
{
    private $request = null;
    private $admin_lang = 'cn';
    private $main_lang = 'cn';

    /**
     * 析构函数
     */
    function  __construct() {
        $this->request = request();
        $this->admin_lang = get_admin_lang();
        $this->main_lang = get_main_lang();
    }

    /**
     * 进入登录页面需要异步处理的业务
     */
    public function login_handle()
    {
        // $this->repairAdmin(); // 修复管理员ID为0的问题
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        clear_session_file(); // 清理过期的data/session文件
    }

    /**
     * 修复管理员
     * @return [type] [description]
     */
    private function repairAdmin()
    {
        $row = [];
        $result = Db::name('admin')->field('admin_id,user_name')->order('add_time asc')->select();
        $total = count($result);
        foreach ($result as $key => $val) {
            $pre_admin_id = $next_admin_id = 0;
            if (empty($val['admin_id'])) {
                if (1 == $total) {
                    Db::name('admin')->where(['user_name'=>$val['user_name']])->update(['admin_id'=>1, 'update_time'=>getTime()]);
                } else {
                    $pre_admin_id = empty($key) ? 0 : $result[$key - 1]['admin_id'];
                    if ($key < ($total - 1)) {
                        $next_admin_id = $result[$key + 1]['admin_id'];
                    } else {
                        $next_admin_id = $pre_admin_id + 2;
                    }

                    if (($next_admin_id - $pre_admin_id) >= 2) {
                        $admin_id = $pre_admin_id + 1;
                        Db::name('admin')->where(['user_name'=>$val['user_name']])->update(['admin_id'=>$admin_id, 'update_time'=>getTime()]);
                    }
                }
            }
        }
    }

    /**
     * 清理未存在的左侧菜单
     * @return [type] [description]
     */
    public function admin_menu_clear()
    {
        $del_ids = [];
        $codeArr = Db::name('weapp')->column('code');
        $list = Db::name('admin_menu')->where(['controller_name'=>'Weapp','action_name'=>'execute'])->select();
        foreach ($list as $key => $val) {
            $code = preg_replace('/^(.*)\|sm\|([^\|]+)\|sc\|(.*)$/i', '${2}', $val['param']);
            if (!in_array($code, $codeArr)) {
                $del_ids[] = $val['id'];
            }
        }
        if (!empty($del_ids)) {
            Db::name('admin_menu')->where(['id'=>['IN', $del_ids]])->delete();
        }
    }

    /**
     * 进入欢迎页面需要异步处理的业务
     */
    public function welcome_handle()
    {
        getVersion('version_themeusers', 'v1.0.1', true);
        getVersion('version_themeshop', 'v1.0.1', true);
        $this->addChannelFile(); // 自动补充自定义模型的文件
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        $this->renameInstall(); // 重命名安装目录，提高网站安全性
        $this->renameSqldatapath(); // 重命名数据库备份目录，提高网站安全性
        $this->del_adminlog(); // 只保留最近一个月的操作日志
        model('Member')->batch_update_userslevel(); // 批量更新会员过期等级
        // tpversion(); // 统计装载量，请勿删除，谢谢支持！
    }
    
    /**
     * 自动补充自定义模型的文件
     */
    public function addChannelFile()
    {
        try {
            $list = Db::name('channeltype')->where([
                'ifsystem'  => 0,
                ])->select();
            if (!empty($list)) {
                $cmodSrc = "data/model/application/common/model/CustomModel.php";
                $cmodContent = @file_get_contents($cmodSrc);
                $hctlSrc = "data/model/application/home/controller/CustomModel.php";
                $hctlContent = @file_get_contents($hctlSrc);
                $hmodSrc = "data/model/application/home/model/CustomModel.php";
                $hmodContent = @file_get_contents($hmodSrc);
                foreach ($list as $key => $val) {
                    $file = "application/common/model/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $cmodContent = str_replace('CustomModel', $val['ctl_name'], $cmodContent);
                        $cmodContent = str_replace('custommodel', strtolower($val['nid']), $cmodContent);
                        $cmodContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $cmodContent);
                        @file_put_contents($file, $cmodContent);
                    }
                    $file = "application/home/controller/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $hctlContent = str_replace('CustomModel', $val['ctl_name'], $hctlContent);
                        $hctlContent = str_replace('custommodel', strtolower($val['nid']), $hctlContent);
                        $hctlContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $hctlContent);
                        @file_put_contents($file, $hctlContent);
                    }
                    $file = "application/home/model/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $hmodContent = str_replace('CustomModel', $val['ctl_name'], $hmodContent);
                        $hmodContent = str_replace('custommodel', strtolower($val['nid']), $hmodContent);
                        $hmodContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $hmodContent);
                        @file_put_contents($file, $hmodContent);
                    }
                }
            }
        } catch (\Exception $e) {}
    }
    
    /**
     * 只保留最近一个月的操作日志
     */
    public function del_adminlog()
    {
        try {
            $is_system = true;
            if (file_exists(ROOT_PATH.'weapp/Equal/logic/EqualLogic.php')) {
                $equalLogic = new \weapp\Equal\logic\EqualLogic;
                if (method_exists($equalLogic, 'del_adminlog')) {
                    $is_system = false;
                    $equalLogic->del_adminlog();
                }
            }
            else if (file_exists(ROOT_PATH.'weapp/Systemdoctor/logic/SystemdoctorLogic.php')) {
                $systemdoctorLogic = new \weapp\Systemdoctor\logic\SystemdoctorLogic;
                if (method_exists($systemdoctorLogic, 'del_adminlog')) {
                    $is_system = false;
                    $systemdoctorLogic->del_adminlog();
                }
            }
            if ($is_system) {
                $mtime = strtotime("-1 month");
                Db::name('admin_log')->where([
                    'log_time'  => ['lt', $mtime],
                    ])->delete();
            }
        } catch (\Exception $e) {}
    }

    /*
     * 修改备份数据库目录
     */
    private function renameSqldatapath() {
        $default_sqldatapath = config('DATA_BACKUP_PATH');
        if (is_dir('.'.$default_sqldatapath)) { // 还是符合初始默认的规则的链接方式
            $dirname = get_rand_str(20, 0, 1);
            $new_path = '/data/sqldata_'.$dirname;
            if (@rename(ROOT_PATH.ltrim($default_sqldatapath, '/'), ROOT_PATH.ltrim($new_path, '/'))) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('web', ['web_sqldatapath'=>$new_path], $val['mark']);
                    }
                } else { // 单语言
                    tpCache('web', ['web_sqldatapath'=>$new_path]);
                }
                /*--end*/
            }
        }
    }

    /**
     * 重命名安装目录，提高网站安全性
     * 在 Admin@login 和 Index@index 操作下
     */
    private function renameInstall()
    {
        if (stristr($this->request->host(), 'eycms.hk')) {
            return true;
        }
        $install_path = ROOT_PATH.'install';
        if (is_dir($install_path) && file_exists($install_path)) {
            $install_time = get_rand_str(20, 0, 1);
            $new_path = ROOT_PATH.'install_'.$install_time;
            @rename($install_path, $new_path);
        }
        else {
            $dirlist = glob('install_*');
            $install_dirname = current($dirlist);
            if (!empty($install_dirname)) {
                /*---修补v1.1.6版本删除的安装文件 install.lock start----*/
                if (!empty($_SESSION['isset_install_lock'])) {
                    return true;
                }
                $_SESSION['isset_install_lock'] = 1;
                /*---修补v1.1.6版本删除的安装文件 install.lock end----*/

                $install_path = ROOT_PATH.$install_dirname;
                if (preg_match('/^install_[0-9]{10}$/i', $install_dirname)) {
                    $install_time = get_rand_str(20, 0, 1);
                    $install_dirname = 'install_'.$install_time;
                    $new_path = ROOT_PATH.$install_dirname;
                    if (@rename($install_path, $new_path)) {
                        $install_path = $new_path;
                        /*多语言*/
                        if (is_language()) {
                            $langRow = \think\Db::name('language')->order('id asc')->select();
                            foreach ($langRow as $key => $val) {
                                tpSetting('install', ['install_dirname'=>$install_time], $val['mark']);
                            }
                        } else { // 单语言
                            tpSetting('install', ['install_dirname'=>$install_time]);
                        }
                        /*--end*/
                    }
                }

                $filename = $install_path.DS.'install.lock';
                if (!file_exists($filename)) {
                    @file_put_contents($filename, '');
                }
            }
        }
    }

    /**
     * 存储后台入口文件路径，比如：/login.php
     * 在 Admin@login 和 Index@index 操作下
     */
    private function saveBaseFile()
    {
        $data = [];
        $data['web_adminbasefile'] = $this->request->baseFile();
        $data['web_cmspath'] = ROOT_DIR; // EyouCMS安装目录
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('web', $data, $val['mark']);
            }
        } else { // 单语言
            tpCache('web', $data);
        }
        /*--end*/
    }

    /**
     * 升级前台会员中心的模板文件
     */
    public function update_template($type = '')
    {
        if (!empty($type)) {
            if ('users' == $type) {
                if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/users') || file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/users')) {
                    $upgrade = getDirFile(DATA_PATH.'backup'.DS.'tpl');
                    if (!empty($upgrade) && is_array($upgrade)) {
                        delFile(DATA_PATH.'backup'.DS.'template_www');
                        // 升级之前，备份涉及的源文件
                        foreach ($upgrade as $key => $val) {
                            $val_tmp = str_replace("template/", "template/".TPL_THEME, $val);
                            $source_file = ROOT_PATH.$val_tmp;
                            if (file_exists($source_file)) {
                                $destination_file = DATA_PATH.'backup'.DS.'template_www'.DS.$val_tmp;
                                tp_mkdir(dirname($destination_file));
                                @copy($source_file, $destination_file);
                            }
                        }

                        // 递归复制文件夹
                        $this->recurse_copy(DATA_PATH.'backup'.DS.'tpl', rtrim(ROOT_PATH, DS));
                    }
                    /*--end*/
                }
            }
        }
    }

    /**
     * 自定义函数递归的复制带有多级子目录的目录
     * 递归复制文件夹
     *
     * @param string $src 原目录
     * @param string $dst 复制到的目录
     * @return string
     */                        
    //参数说明：            
    //自定义函数递归的复制带有多级子目录的目录
    private function recurse_copy($src, $dst)
    {
        $planPath_pc = "template/".TPL_THEME."pc/";
        $planPath_m = "template/".TPL_THEME."mobile/";
        $dir = opendir($src);

        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
        $dst_tmp = str_replace('\\', '/', $dst);
        $dst_tmp = rtrim($dst_tmp, '/').'/';
        if (stristr($dst_tmp, $planPath_pc) && file_exists($planPath_pc)) {
            tp_mkdir($dst);
        } else if (stristr($dst_tmp, $planPath_m) && file_exists($planPath_m)) {
            tp_mkdir($dst);
        }
        /*--end*/

        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $needle = '/template/'.TPL_THEME;
                    $needle = rtrim($needle, '/');
                    $dstfile = $dst . '/' . $file;
                    if (!stristr($dstfile, $needle)) {
                        $dstfile = str_replace('/template', $needle, $dstfile);
                    }
                    $this->recurse_copy($src . '/' . $file, $dstfile);
                }
                else {
                    if (file_exists($src . DIRECTORY_SEPARATOR . $file)) {
                        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
                        $rs = true;
                        $src_tmp = str_replace('\\', '/', $src . DIRECTORY_SEPARATOR . $file);
                        if (stristr($src_tmp, $planPath_pc) && !file_exists($planPath_pc)) {
                            continue;
                        } else if (stristr($src_tmp, $planPath_m) && !file_exists($planPath_m)) {
                            continue;
                        }
                        /*--end*/
                        $rs = @copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                        if($rs) {
                            @unlink($src . DIRECTORY_SEPARATOR . $file);
                        }
                    }
                }
            }
        }
        closedir($dir);
    }
    
    // 记录当前是多语言还是单语言到文件里
    public function system_langnum_file()
    {
        model('Language')->setLangNum();
    }
    
    // 记录当前是否多站点到文件里
    public function system_citysite_file()
    {
        $key = base64_decode('cGhwLnBocF9zZXJ2aWNlbWVhbA==');
        $value = tpCache($key);
        if (2 > $value) {
            /*多语言*/
            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', ['web_citysite_open'=>0], $val['mark']);
                }
            } else { // 单语言
                tpCache('web', ['web_citysite_open'=>0]);
            }
            /*--end*/
            model('Citysite')->setCitysiteOpen();
        }
    }

    public function admin_logic_1609900642()
    {
        // 更新自定义的样式表文件
        $version = getVersion();
        $syn_admin_logic_1697156935 = tpSetting('syn.admin_logic_1697156935', [], 'cn');
        if ($version != $syn_admin_logic_1697156935) {
            $r = $this->admin_update_theme_css();
            if ($r !== false) {
                tpSetting('syn', ['admin_logic_1697156935'=>$version], 'cn');
            }
        }

        $vars1 = 'cGhwLnBo'.'cF9zZXJ2aW'.'NlaW5mbw==';
        $vars1 = base64_decode($vars1);
        $data = tpCache($vars1);
        $data = mchStrCode($data, 'DECODE');
        $data = json_decode($data, true);
        if (empty($data['pid']) || 2 > $data['pid']) return true;
        $file = "./data/conf/{$data['code']}.txt";
        $vars2 = 'cGhwX3Nl'.'cnZpY2V'.'tZWFs';
        $vars2 = base64_decode($vars2);
        if (!file_exists($file)) {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$vars2=>1], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$vars2=>1]);
            }
            /*--end*/
        } else {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$vars2=>$data['pid']], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$vars2=>$data['pid']]);
            }
            /*--end*/
        }
    }

    /**
     * 更新后台自定义的样式表文件
     * @return [type] [description]
     */
    public function admin_update_theme_css()
    {
        $r = false;
        $file = APP_PATH.'admin/template/public/theme_css.htm';
        if (file_exists($file)) {
            $view = \think\View::instance(\think\Config::get('template'), \think\Config::get('view_replace_str'));
            $view->assign('global', tpCache('global'));
            $css = $view->fetch($file);
            $css = str_replace(['<style type="text/css">','</style>'], '', $css);
            if (function_exists('chmod')) {
                @chmod($file, 0755);
            }
            $r = @file_put_contents(ROOT_PATH.'public/static/admin/css/theme_style.css', $css);
        }

        return $r;
    }

    /**
     * 更新会员中心自定义的样式表文件
     * @return [type] [description]
     */
    public function users_update_theme_css()
    {
        
    }

    public function admin_logic_1623036205()
    {
        $arr = [
            ROOT_PATH."application/weapp",
            ROOT_PATH."data/model/application/admin",
            ROOT_PATH."data/model/custom_model_path/recruit.filelist.txt",
            ROOT_PATH."data/weapp/Sample/weapp/Sample/behavior/weapp",
            ROOT_PATH."data/weapp/Sample/template/plugins/sample/index.htm",
            ROOT_PATH."public/static/admin/js/qtip",
            ROOT_PATH."application/admin/controller/OrderVerify.php",
            ROOT_PATH."application/admin/controller/Recruit.php",
            ROOT_PATH."application/admin/logic/AuthModularLogic.php",
            ROOT_PATH."application/admin/logic/AuthRoleLogic.php",
            ROOT_PATH."application/admin/logic/NavigationLogic.php",
            ROOT_PATH."application/admin/model/FormField.php",
            ROOT_PATH."application/admin/model/Recruit.php",
            ROOT_PATH."application/admin/template/admin/admin_pwd.htm",
            ROOT_PATH."application/admin/template/admin/admin_pwd_ajax.htm",
            ROOT_PATH."application/admin/template/admin/login_double.htm",
            ROOT_PATH."application/admin/template/ajax/new_appoint_tplfile.htm",
            ROOT_PATH."application/admin/template/archives/left.htm",
            ROOT_PATH."application/admin/template/channeltype/field_management.htm",
            ROOT_PATH."application/admin/template/encodes/theme_conf.htm",
            ROOT_PATH."application/admin/template/field/bar.htm",
            ROOT_PATH."application/admin/template/field/modelfield.htm",
            ROOT_PATH."application/admin/template/foreign/htmlfilename_handel.htm",
            ROOT_PATH."application/admin/template/foreign/htmlfilename_index.htm",
            ROOT_PATH."application/admin/template/foreign/seo_desc_handel.htm",
            ROOT_PATH."application/admin/template/form/view_form_data.htm",
            ROOT_PATH."application/admin/template/guestbook/btn.htm",
            ROOT_PATH."application/admin/template/index/switch_map_new.htm",
            ROOT_PATH."application/admin/template/index/theme_add_welcome_tplfile.htm",
            ROOT_PATH."application/admin/template/level/level_bar.htm",
            ROOT_PATH."application/admin/template/member/ajax_set_oauth_config.htm",
            ROOT_PATH."application/admin/template/member/pay_set.htm",
            ROOT_PATH."application/admin/template/order_verify",
            ROOT_PATH."application/admin/template/product/btn.htm",
            ROOT_PATH."application/admin/template/recruit",
            ROOT_PATH."application/admin/template/security/ajax_force_verify.htm",
            ROOT_PATH."application/admin/template/system/clearCache.htm",
            ROOT_PATH."application/admin/template/system/customvar_add.htm",
            ROOT_PATH."application/admin/template/system/customvar_edit.htm",
            ROOT_PATH."application/admin/template/system/customvar_recycle.htm",
            ROOT_PATH."application/admin/template/system/oss.htm",
            ROOT_PATH."application/admin/template/system/pay_set.htm",
            ROOT_PATH."application/admin/template/system/region.htm",
            ROOT_PATH."application/admin/template/uploadify/site.htm",
            ROOT_PATH."application/api/controller/Count.php",
            ROOT_PATH."application/common/common.php",
            ROOT_PATH."core/library/think/behavior/admin/WeappBehavior.php",
            ROOT_PATH."core/library/think/template/taglib/api/TagPickUpPointsList.php",
            ROOT_PATH."core/library/think/template/taglib/eyou/TagForm.php",
            ROOT_PATH."core/library/think/template/taglib/eyou/TagLogin.php",
            ROOT_PATH."public/plugins/bootstrap/img/Thumbs.db",
            ROOT_PATH."public/plugins/Ueditor/third-party/snapscreen/UEditorSnapscreen.exe",
            ROOT_PATH."public/static/admin/css/login_double.css",
            ROOT_PATH."public/static/admin/font/css/font-awesome.css",
            ROOT_PATH."public/static/admin/images/admin.png",
            ROOT_PATH."public/static/admin/images/ajaxLoader.gif",
            ROOT_PATH."public/static/admin/images/channel_bg.gif",
            ROOT_PATH."public/static/admin/images/circle_level_bg.png",
            ROOT_PATH."public/static/admin/images/cms_edit_bg.png",
            ROOT_PATH."public/static/admin/images/cms_edit_bg_line.png",
            ROOT_PATH."public/static/admin/images/combine_img2.png",
            ROOT_PATH."public/static/admin/images/cut_bg.png",
            ROOT_PATH."public/static/admin/images/ddn.png",
            ROOT_PATH."public/static/admin/images/down.gif",
            ROOT_PATH."public/static/admin/images/iframe_bg.png",
            ROOT_PATH."public/static/admin/images/login_formBg.png",
            ROOT_PATH."public/static/admin/images/login_icon.png",
            ROOT_PATH."public/static/admin/images/logo-login.png",
            ROOT_PATH."public/static/admin/images/size.gif",
            ROOT_PATH."public/static/admin/images/size_channel.gif",
            ROOT_PATH."public/static/admin/images/socp.png",
            ROOT_PATH."public/static/admin/images/subbag.png",
            ROOT_PATH."public/static/admin/images/theme/default.png",
            ROOT_PATH."public/static/admin/images/up.gif",
            ROOT_PATH."public/static/admin/images/user1-128x128.jpg",
            ROOT_PATH."public/static/admin/images/uup.png",
            ROOT_PATH."public/static/admin/images/vertline.gif",
            ROOT_PATH."public/static/admin/images/xianbg.png",
            ROOT_PATH."public/static/admin/js/jquery.purebox.js",
            ROOT_PATH."public/static/common/js/jquery.editable.min.js",
            ROOT_PATH."public/static/common/js/tag_login.js",
            ROOT_PATH."vendor/PHPExcel.zip",
            ROOT_PATH."vendor/phpmailer/.gitattributes",
            ROOT_PATH."vendor/phpmailer/.gitignore",
            ROOT_PATH."vendor/phpmailer/.scrutinizer.yml",
            ROOT_PATH."vendor/phpmailer/.travis.yml",
            ROOT_PATH."vendor/phpmailer/changelog.md",
            ROOT_PATH."vendor/phpmailer/README.md",
            ROOT_PATH."vendor/phpmailer/SECURITY.md",
            ROOT_PATH."vendor/phpmailer/travis.phpunit.xml.dist",
        ];
        foreach ($arr as $key => $val) {
            if (is_dir($val)) {
                try {
                    delFile($val, true);
                } catch (\Exception $e) {}
            } else if (file_exists($val)) {
                @unlink($val);
            }
        }
        
        // 同步模板的付费选择支付文件到前台模板指定位置
        $this->copy_tplpayfile();
        // 自动更新插件里的jquery文件为最新版本，修复jquery漏洞
        $this->copy_jquery();
        // 升级v1.6.7版本要处理的数据
        $this->eyou_v167_handle_data();
        // 升级后，清理缓存文件
        // $this->upgrade_clear_cache();
        // 升级v1.7.0版本要处理的数据
        $this->eyou_v170_handle_data();
        // 升级v1.7.1版本要处理的数据
        $this->eyou_v171_handle_data();
    }

    // 升级v1.7.1版本要处理的数据
    private function eyou_v171_handle_data()
    {
        // 表前缀
        $Prefix = config('database.prefix');
        
        $syn_admin_logic_1732008365 = tpSetting('syn.syn_admin_logic_1732008365', [], 'cn');
        if (empty($syn_admin_logic_1732008365)) {
            $r = true;
            $isTable = Db::query('SHOW TABLES LIKE \''.$Prefix.'language_archives_copy_log\'');
            if (empty($isTable)) {
                $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}language_archives_copy_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel` int(10) DEFAULT '0',
  `typeid` int(10) DEFAULT '0' COMMENT '分类ID',
  `new_typeid` int(10) DEFAULT '0',
  `oldid` int(10) DEFAULT '0',
  `newid` int(10) DEFAULT '0',
  `lang` varchar(20) DEFAULT '' COMMENT '生成语言',
  `add_time` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `index_oldid` (`oldid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
EOF;
                try{
                    $r = @Db::execute($tableSql);
                }catch(\Exception $e){
                    if (stristr($e->getMessage(), 'Storage engine MyISAM is disabled')) {
                        $tableSql = str_replace('ENGINE=MyISAM', 'ENGINE=InnoDB', $tableSql);
                        $r = @Db::execute($tableSql);
                    }
                }
            }
            if ($r !== false) {
                schemaTable('language_archives_copy_log');
                tpSetting('syn', ['syn_admin_logic_1732008365'=>1], 'cn');
            }
        }

        $syn_admin_logic_1732517850 = tpSetting('syn.syn_admin_logic_1732517850', [], 'cn');
        if (empty($syn_admin_logic_1732517850)) {
            try{
                $param = [];
                // 编辑器防注入
                $param['web_xss_filter'] = tpCache('web.web_xss_filter');
                $web_xss_words = ['union','delete','outfile','char','concat','truncate','insert','revoke','grant','replace','rename','declare','exec','delimiter','phar','eval','onerror','script'];
                $param['web_xss_words'] = implode(PHP_EOL, $web_xss_words);
                // 网站防止被刷
                $param['web_anti_brushing'] = tpCache('web.web_anti_brushing');
                $param['web_anti_words'] = implode(PHP_EOL, ['wd']);
                /*-------------------后台安全配置 end-------------------*/
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
                // 存储文件
                $content = json_encode($param);
                $tfile = webXssKeyFile();
                $fp = @fopen($tfile,'w');
                if(!$fp) {
                    @file_put_contents($tfile, $content);
                }
                else {
                    fwrite($fp, $content);
                    fclose($fp);
                }
                tpSetting('syn', ['syn_admin_logic_1732517850'=>1], 'cn');
            }catch(\Exception $e){}
        }
        
        // 处理上个版本升级后，导航数据因为标签底层改动，缓存问题导致不显示
        $syn_admin_logic_1732586784 = tpSetting('syn.syn_admin_logic_1732586784', [], 'cn');
        if (empty($syn_admin_logic_1732586784)) {
            try {
                $upgradeTime = Db::name('config')->where(['name'=>'system_version', 'value'=>'v1.7.0'])->order('update_time asc')->value('update_time');
                if ($upgradeTime < 1732587428) {
                    delFile(rtrim(RUNTIME_PATH, '/'));
                }
            } catch (\Exception $e) {
                
            }
            tpSetting('syn', ['syn_admin_logic_1732586784'=>1], 'cn');
        }

        $syn_admin_logic_1735088029 = tpSetting('syn.syn_admin_logic_1735088029', [], 'cn');
        if (empty($syn_admin_logic_1735088029)) {
            try{
                $r = true;
                Db::name('users_menu')->where(['version'=>'v5'])->delete();
                $saveData = Db::name('users_menu')->field('id', true)->where(['version'=>'v2'])->select();
                if (!empty($saveData)) {
                    $addData = [];
                    foreach ($saveData as $key => $val) {
                        $val['version'] = 'v5';
                        $addData[] = $val;
                    }
                    $r = Db::name('users_menu')->insertAll($addData);
                }
                if ($r !== false) {
                    tpSetting('syn', ['syn_admin_logic_1735088029'=>1], 'cn');
                }
            }catch(\Exception $e){}
        }

        // 处理国家表数据
        $this->syn_handle_country();
    }

    /**
     * 处理国家表数据
     * @return [type] [description]
     */
    private function syn_handle_country()
    {
        $admin_logic_1735200508 = tpSetting('syn.admin_logic_1735200508', [], 'cn');
        if (empty($admin_logic_1735200508)) {
            $r = true;
            $Prefix = config('database.prefix');
            $isTable = Db::query('SHOW TABLES LIKE \''.$Prefix.'country\'');
            if (empty($isTable)) {
                $tableSql = <<<EOF
CREATE TABLE `{$Prefix}country` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `code` varchar(50) NOT NULL DEFAULT '' COMMENT '编码',
  `continent` varchar(50) NOT NULL DEFAULT '' COMMENT '所属大洲',
  `sort_order` int(11) unsigned DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态(0:禁用; 1:启用;)',
  `add_time` int(11) unsigned DEFAULT '0' COMMENT '添加时间',
  `update_time` int(11) unsigned DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='国家列表';
EOF;
                try{
                    $r = @Db::execute($tableSql);
                }catch(\Exception $e){
                    if (stristr($e->getMessage(), 'Storage engine MyISAM is disabled')) {
                        $tableSql = str_replace('ENGINE=MyISAM', 'ENGINE=InnoDB', $tableSql);
                        $r = @Db::execute($tableSql);
                    }
                }
            }
            if ($r !== false) {
                schemaTable('country');
                Db::name('country')->where(['id'=>['gt', 0]])->delete(true);
                $saveData = [
                    [
                        "id" => 1,
                        "name" => "中国",
                        "code" => "CN",
                        "continent" => "AS",
                        "sort_order" => 1,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1732173305,
                    ],
                    [
                        "id" => 2,
                        "name" => "Afghanistan",
                        "code" => "AF",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 3,
                        "name" => "Albania",
                        "code" => "AL",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 4,
                        "name" => "Algeria",
                        "code" => "DZ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 5,
                        "name" => "American Samoa",
                        "code" => "AS",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 6,
                        "name" => "Andorra",
                        "code" => "AD",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 7,
                        "name" => "Angola",
                        "code" => "AO",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 8,
                        "name" => "Anguilla",
                        "code" => "AI",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 9,
                        "name" => "Antarctica",
                        "code" => "AQ",
                        "continent" => "AN",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 10,
                        "name" => "Antigua and Barbuda",
                        "code" => "AG",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 11,
                        "name" => "Argentina",
                        "code" => "AR",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 12,
                        "name" => "Armenia",
                        "code" => "AM",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 13,
                        "name" => "Aruba",
                        "code" => "AW",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 14,
                        "name" => "Australia",
                        "code" => "AU",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 15,
                        "name" => "Austria",
                        "code" => "AT",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 16,
                        "name" => "Azerbaijan",
                        "code" => "AZ",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 17,
                        "name" => "Bahamas",
                        "code" => "BS",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 18,
                        "name" => "Bahrain",
                        "code" => "BH",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 19,
                        "name" => "Bangladesh",
                        "code" => "BD",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 20,
                        "name" => "Barbados",
                        "code" => "BB",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 21,
                        "name" => "Belarus",
                        "code" => "BY",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 22,
                        "name" => "Belgium",
                        "code" => "BE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 23,
                        "name" => "Belize",
                        "code" => "BZ",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 24,
                        "name" => "Benin",
                        "code" => "BJ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 25,
                        "name" => "Bermuda",
                        "code" => "BM",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 26,
                        "name" => "Bhutan",
                        "code" => "BT",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 27,
                        "name" => "Bolivia",
                        "code" => "BO",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 28,
                        "name" => "Bosnia and Herzegovina",
                        "code" => "BA",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 29,
                        "name" => "Botswana",
                        "code" => "BW",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 30,
                        "name" => "Bouvet Island",
                        "code" => "BV",
                        "continent" => "AN",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 31,
                        "name" => "Brazil",
                        "code" => "BR",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 32,
                        "name" => "British Indian Ocean Territory",
                        "code" => "IO",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 33,
                        "name" => "Brunei Darussalam",
                        "code" => "BN",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 34,
                        "name" => "Bulgaria",
                        "code" => "BG",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 35,
                        "name" => "Burkina Faso",
                        "code" => "BF",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 36,
                        "name" => "Burundi",
                        "code" => "BI",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 37,
                        "name" => "Cambodia",
                        "code" => "KH",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 38,
                        "name" => "Cameroon",
                        "code" => "CM",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 39,
                        "name" => "Canada",
                        "code" => "CA",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 40,
                        "name" => "Cape Verde",
                        "code" => "CV",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 41,
                        "name" => "Cayman Islands",
                        "code" => "KY",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 42,
                        "name" => "Central African Republic",
                        "code" => "CF",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 43,
                        "name" => "Chad",
                        "code" => "TD",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 44,
                        "name" => "Chile",
                        "code" => "CL",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 45,
                        "name" => "Christmas Island",
                        "code" => "CX",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 46,
                        "name" => "Cocos (Keeling) Islands",
                        "code" => "CC",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 47,
                        "name" => "Colombia",
                        "code" => "CO",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 48,
                        "name" => "Comoros",
                        "code" => "KM",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 49,
                        "name" => "Congo",
                        "code" => "CG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 50,
                        "name" => "Cook Islands",
                        "code" => "CK",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 51,
                        "name" => "Costa Rica",
                        "code" => "CR",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 52,
                        "name" => "Cote D'Ivoire",
                        "code" => "CI",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 53,
                        "name" => "Croatia",
                        "code" => "HR",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 54,
                        "name" => "Cuba",
                        "code" => "CU",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 55,
                        "name" => "Cyprus",
                        "code" => "CY",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 56,
                        "name" => "Czech Republic",
                        "code" => "CZ",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 57,
                        "name" => "Denmark",
                        "code" => "DK",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 58,
                        "name" => "Djibouti",
                        "code" => "DJ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 59,
                        "name" => "Dominica",
                        "code" => "DM",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 60,
                        "name" => "Dominican Republic",
                        "code" => "DO",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 61,
                        "name" => "East Timor",
                        "code" => "TL",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 62,
                        "name" => "Ecuador",
                        "code" => "EC",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 63,
                        "name" => "Egypt",
                        "code" => "EG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 64,
                        "name" => "El Salvador",
                        "code" => "SV",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 65,
                        "name" => "Equatorial Guinea",
                        "code" => "GQ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 66,
                        "name" => "Eritrea",
                        "code" => "ER",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 67,
                        "name" => "Estonia",
                        "code" => "EE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 68,
                        "name" => "Ethiopia",
                        "code" => "ET",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 69,
                        "name" => "Falkland Islands (Malvinas)",
                        "code" => "FK",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 70,
                        "name" => "Faroe Islands",
                        "code" => "FO",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 71,
                        "name" => "Fiji",
                        "code" => "FJ",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 72,
                        "name" => "Finland",
                        "code" => "FI",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 73,
                        "name" => "France, Metropolitan",
                        "code" => "FR",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 74,
                        "name" => "French Guiana",
                        "code" => "GF",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 75,
                        "name" => "French Polynesia",
                        "code" => "PF",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 76,
                        "name" => "French Southern Territories",
                        "code" => "TF",
                        "continent" => "AN",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 77,
                        "name" => "Gabon",
                        "code" => "GA",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 78,
                        "name" => "Gambia",
                        "code" => "GM",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 79,
                        "name" => "Georgia",
                        "code" => "GE",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 80,
                        "name" => "Germany",
                        "code" => "DE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 81,
                        "name" => "Ghana",
                        "code" => "GH",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 82,
                        "name" => "Gibraltar",
                        "code" => "GI",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 83,
                        "name" => "Greece",
                        "code" => "GR",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 84,
                        "name" => "Greenland",
                        "code" => "GL",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 85,
                        "name" => "Grenada",
                        "code" => "GD",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 86,
                        "name" => "Guadeloupe",
                        "code" => "GP",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 87,
                        "name" => "Guam",
                        "code" => "GU",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 88,
                        "name" => "Guatemala",
                        "code" => "GT",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 89,
                        "name" => "Guinea",
                        "code" => "GN",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 90,
                        "name" => "Guinea-Bissau",
                        "code" => "GW",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 91,
                        "name" => "Guyana",
                        "code" => "GY",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 92,
                        "name" => "Haiti",
                        "code" => "HT",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 93,
                        "name" => "Heard and Mc Donald Islands",
                        "code" => "HM",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 94,
                        "name" => "Honduras",
                        "code" => "HN",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 95,
                        "name" => "Hungary",
                        "code" => "HU",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 96,
                        "name" => "Iceland",
                        "code" => "IS",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 97,
                        "name" => "India",
                        "code" => "IN",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 98,
                        "name" => "Indonesia",
                        "code" => "ID",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 99,
                        "name" => "Iran (Islamic Republic of)",
                        "code" => "IR",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 100,
                        "name" => "Iraq",
                        "code" => "IQ",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 101,
                        "name" => "Ireland",
                        "code" => "IE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 102,
                        "name" => "Israel",
                        "code" => "IL",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 103,
                        "name" => "Italy",
                        "code" => "IT",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 104,
                        "name" => "Jamaica",
                        "code" => "JM",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 105,
                        "name" => "Japan",
                        "code" => "JP",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 106,
                        "name" => "Jordan",
                        "code" => "JO",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 107,
                        "name" => "Kazakhstan",
                        "code" => "KZ",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 108,
                        "name" => "Kenya",
                        "code" => "KE",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 109,
                        "name" => "Kiribati",
                        "code" => "KI",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 110,
                        "name" => "North Korea",
                        "code" => "KP",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 111,
                        "name" => "South Korea",
                        "code" => "KR",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 112,
                        "name" => "Kuwait",
                        "code" => "KW",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 113,
                        "name" => "Kyrgyzstan",
                        "code" => "KG",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 114,
                        "name" => "Lao People's Democratic Republic",
                        "code" => "LA",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 115,
                        "name" => "Latvia",
                        "code" => "LV",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 116,
                        "name" => "Lebanon",
                        "code" => "LB",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 117,
                        "name" => "Lesotho",
                        "code" => "LS",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 118,
                        "name" => "Liberia",
                        "code" => "LR",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 119,
                        "name" => "Libyan Arab Jamahiriya",
                        "code" => "LY",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 120,
                        "name" => "Liechtenstein",
                        "code" => "LI",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 121,
                        "name" => "Lithuania",
                        "code" => "LT",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 122,
                        "name" => "Luxembourg",
                        "code" => "LU",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 123,
                        "name" => "FYROM",
                        "code" => "MK",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 124,
                        "name" => "Madagascar",
                        "code" => "MG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 125,
                        "name" => "Malawi",
                        "code" => "MW",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 126,
                        "name" => "Malaysia",
                        "code" => "MY",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 127,
                        "name" => "Maldives",
                        "code" => "MV",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 128,
                        "name" => "Mali",
                        "code" => "ML",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 129,
                        "name" => "Malta",
                        "code" => "MT",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 130,
                        "name" => "Marshall Islands",
                        "code" => "MH",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 131,
                        "name" => "Martinique",
                        "code" => "MQ",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 132,
                        "name" => "Mauritania",
                        "code" => "MR",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 133,
                        "name" => "Mauritius",
                        "code" => "MU",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 134,
                        "name" => "Mayotte",
                        "code" => "YT",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 135,
                        "name" => "Mexico",
                        "code" => "MX",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 136,
                        "name" => "Micronesia, Federated States of",
                        "code" => "FM",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 137,
                        "name" => "Moldova, Republic of",
                        "code" => "MD",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 138,
                        "name" => "Monaco",
                        "code" => "MC",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 139,
                        "name" => "Mongolia",
                        "code" => "MN",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 140,
                        "name" => "Montserrat",
                        "code" => "MS",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 141,
                        "name" => "Morocco",
                        "code" => "MA",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 142,
                        "name" => "Mozambique",
                        "code" => "MZ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 143,
                        "name" => "Myanmar",
                        "code" => "MM",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 144,
                        "name" => "Namibia",
                        "code" => "NA",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 145,
                        "name" => "Nauru",
                        "code" => "NR",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 146,
                        "name" => "Nepal",
                        "code" => "NP",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 147,
                        "name" => "Netherlands",
                        "code" => "NL",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 148,
                        "name" => "Netherlands Antilles",
                        "code" => "AN",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 149,
                        "name" => "New Caledonia",
                        "code" => "NC",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 150,
                        "name" => "New Zealand",
                        "code" => "NZ",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 151,
                        "name" => "Nicaragua",
                        "code" => "NI",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 152,
                        "name" => "Niger",
                        "code" => "NE",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 153,
                        "name" => "Nigeria",
                        "code" => "NG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 154,
                        "name" => "Niue",
                        "code" => "NU",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 155,
                        "name" => "Norfolk Island",
                        "code" => "NF",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 156,
                        "name" => "Northern Mariana Islands",
                        "code" => "MP",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 157,
                        "name" => "Norway",
                        "code" => "NO",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 158,
                        "name" => "Oman",
                        "code" => "OM",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 159,
                        "name" => "Pakistan",
                        "code" => "PK",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 160,
                        "name" => "Palau",
                        "code" => "PW",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 161,
                        "name" => "Panama",
                        "code" => "PA",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 162,
                        "name" => "Papua New Guinea",
                        "code" => "PG",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 163,
                        "name" => "Paraguay",
                        "code" => "PY",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 164,
                        "name" => "Peru",
                        "code" => "PE",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 165,
                        "name" => "Philippines",
                        "code" => "PH",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 166,
                        "name" => "Pitcairn",
                        "code" => "PN",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 167,
                        "name" => "Poland",
                        "code" => "PL",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 168,
                        "name" => "Portugal",
                        "code" => "PT",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 169,
                        "name" => "Puerto Rico",
                        "code" => "PR",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 170,
                        "name" => "Qatar",
                        "code" => "QA",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 171,
                        "name" => "Reunion",
                        "code" => "RE",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 172,
                        "name" => "Romania",
                        "code" => "RO",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 173,
                        "name" => "Russian Federation",
                        "code" => "RU",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 174,
                        "name" => "Rwanda",
                        "code" => "RW",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 175,
                        "name" => "Saint Kitts and Nevis",
                        "code" => "KN",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 176,
                        "name" => "Saint Lucia",
                        "code" => "LC",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 177,
                        "name" => "Saint Vincent and the Grenadines",
                        "code" => "VC",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 178,
                        "name" => "Samoa",
                        "code" => "WS",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 179,
                        "name" => "San Marino",
                        "code" => "SM",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 180,
                        "name" => "Sao Tome and Principe",
                        "code" => "ST",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 181,
                        "name" => "Saudi Arabia",
                        "code" => "SA",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 182,
                        "name" => "Senegal",
                        "code" => "SN",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 183,
                        "name" => "Seychelles",
                        "code" => "SC",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 184,
                        "name" => "Sierra Leone",
                        "code" => "SL",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 185,
                        "name" => "Singapore",
                        "code" => "SG",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 186,
                        "name" => "Slovak Republic",
                        "code" => "SK",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 187,
                        "name" => "Slovenia",
                        "code" => "SI",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 188,
                        "name" => "Solomon Islands",
                        "code" => "SB",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 189,
                        "name" => "Somalia",
                        "code" => "SO",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 190,
                        "name" => "South Africa",
                        "code" => "ZA",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 191,
                        "name" => "South Georgia &amp; South Sandwich Islands",
                        "code" => "GS",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 192,
                        "name" => "Spain",
                        "code" => "ES",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 193,
                        "name" => "Sri Lanka",
                        "code" => "LK",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 194,
                        "name" => "St. Helena",
                        "code" => "SH",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 195,
                        "name" => "St. Pierre and Miquelon",
                        "code" => "PM",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 196,
                        "name" => "Sudan",
                        "code" => "SD",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 197,
                        "name" => "Suriname",
                        "code" => "SR",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 198,
                        "name" => "Svalbard and Jan Mayen Islands",
                        "code" => "SJ",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 199,
                        "name" => "Swaziland",
                        "code" => "SZ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 200,
                        "name" => "Sweden",
                        "code" => "SE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 201,
                        "name" => "Switzerland",
                        "code" => "CH",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 202,
                        "name" => "Syrian Arab Republic",
                        "code" => "SY",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 203,
                        "name" => "Tajikistan",
                        "code" => "TJ",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 204,
                        "name" => "Tanzania, United Republic of",
                        "code" => "TZ",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 205,
                        "name" => "Thailand",
                        "code" => "TH",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 206,
                        "name" => "Togo",
                        "code" => "TG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 207,
                        "name" => "Tokelau",
                        "code" => "TK",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 208,
                        "name" => "Tonga",
                        "code" => "TO",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 209,
                        "name" => "Trinidad and Tobago",
                        "code" => "TT",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 210,
                        "name" => "Tunisia",
                        "code" => "TN",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 211,
                        "name" => "Turkey",
                        "code" => "TR",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 212,
                        "name" => "Turkmenistan",
                        "code" => "TM",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 213,
                        "name" => "Turks and Caicos Islands",
                        "code" => "TC",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 214,
                        "name" => "Tuvalu",
                        "code" => "TV",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 215,
                        "name" => "Uganda",
                        "code" => "UG",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 216,
                        "name" => "Ukraine",
                        "code" => "UA",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 217,
                        "name" => "United Arab Emirates",
                        "code" => "AE",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 218,
                        "name" => "United Kingdom",
                        "code" => "GB",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 219,
                        "name" => "United States",
                        "code" => "US",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 220,
                        "name" => "United States Minor Outlying Islands",
                        "code" => "UM",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 221,
                        "name" => "Uruguay",
                        "code" => "UY",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 222,
                        "name" => "Uzbekistan",
                        "code" => "UZ",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 223,
                        "name" => "Vanuatu",
                        "code" => "VU",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 224,
                        "name" => "Vatican City State (Holy See)",
                        "code" => "VA",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 225,
                        "name" => "Venezuela",
                        "code" => "VE",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 226,
                        "name" => "Viet Nam",
                        "code" => "VN",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 227,
                        "name" => "Virgin Islands (British)",
                        "code" => "VG",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 228,
                        "name" => "Virgin Islands (U.S.)",
                        "code" => "VI",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 229,
                        "name" => "Wallis and Futuna Islands",
                        "code" => "WF",
                        "continent" => "OA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 230,
                        "name" => "Western Sahara",
                        "code" => "EH",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 231,
                        "name" => "Yemen",
                        "code" => "YE",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1732173279,
                    ],
                    [
                        "id" => 232,
                        "name" => "Democratic Republic of Congo",
                        "code" => "CD",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 233,
                        "name" => "Zambia",
                        "code" => "ZM",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 234,
                        "name" => "Zimbabwe",
                        "code" => "ZW",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 235,
                        "name" => "Montenegro",
                        "code" => "ME",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 236,
                        "name" => "Serbia",
                        "code" => "RS",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 237,
                        "name" => "Aaland Islands",
                        "code" => "AX",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 238,
                        "name" => "Bonaire, Sint Eustatius and Saba",
                        "code" => "BQ",
                        "continent" => "SA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 239,
                        "name" => "Curacao",
                        "code" => "CW",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 240,
                        "name" => "Palestinian Territory, Occupied",
                        "code" => "PS",
                        "continent" => "AS",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 241,
                        "name" => "South Sudan",
                        "code" => "SS",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 242,
                        "name" => "St. Barthelemy",
                        "code" => "BL",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 243,
                        "name" => "St. Martin (French part)",
                        "code" => "MF",
                        "continent" => "NA",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 244,
                        "name" => "Canary Islands",
                        "code" => "IC",
                        "continent" => "AF",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 245,
                        "name" => "Ascension Island (British)",
                        "code" => "AC",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 246,
                        "name" => "Kosovo, Republic of",
                        "code" => "XK",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 247,
                        "name" => "Isle of Man",
                        "code" => "IM",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 248,
                        "name" => "Tristan da Cunha",
                        "code" => "TA",
                        "continent" => "NULL",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 249,
                        "name" => "Guernsey",
                        "code" => "GG",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                    [
                        "id" => 250,
                        "name" => "Jersey",
                        "code" => "JE",
                        "continent" => "EU",
                        "sort_order" => 100,
                        "status" => 1,
                        "add_time" => 1704038400,
                        "update_time" => 1704038400,
                    ],
                ];
                $r = Db::name('country')->insertAll($saveData);
                if ($r !== false) {
                    tpSetting('syn', ['admin_logic_1735200508'=>1], 'cn');
                }
            }
        }
    }

    // 升级v1.7.0版本要处理的数据
    private function eyou_v170_handle_data()
    {
        $this->syn_handle_twofactor_tpl();
    }

    /**
     * 同步双因子登录的模板
     * @return [type] [description]
     */
    private function syn_handle_twofactor_tpl()
    {
        $syn_admin_logic_1727423349 = tpSetting('syn.syn_admin_logic_1727423349', [], 'cn');
        if (empty($syn_admin_logic_1727423349)) {
            try{
                $r = true;
                Db::name('sms_template')->where(['send_scene'=>30])->delete();
                $saveData = Db::name('sms_template')->field('tpl_id', true)->where(['send_scene'=>2])->select();
                if (!empty($saveData)) {
                    $addData = [];
                    foreach ($saveData as $key => $val) {
                        $val['tpl_title'] = '后台登录';
                        $val['send_scene'] = 30;
                        // $val['sms_sign'] = '';
                        // $val['sms_tpl_code'] = '';
                        // if (2 == $val['sms_type']) {
                        //     $val['tpl_content'] = '验证码为 {1} ，请在30分钟内输入验证。';
                        // } else {
                        //     $val['tpl_content'] = '验证码为 ${content} ，请在30分钟内输入验证。';
                        // }
                        $val['is_open'] = 1;
                        $addData[] = $val;
                    }
                    $r = Db::name('sms_template')->insertAll($addData);
                }
                if ($r !== false) {
                    tpSetting('syn', ['syn_admin_logic_1727423349'=>1], 'cn');
                }
            }catch(\Exception $e){}
        }

        $syn_admin_logic_1727423350 = tpSetting('syn.syn_admin_logic_1727423350', [], 'cn');
        if (empty($syn_admin_logic_1727423350)) {
            try{
                $r = true;
                Db::name('smtp_tpl')->where(['send_scene'=>30])->delete();
                $saveData = Db::name('smtp_tpl')->field('tpl_id', true)->where(['send_scene'=>2])->select();
                if (!empty($saveData)) {
                    $addData = [];
                    foreach ($saveData as $key => $val) {
                        $val['tpl_name'] = '后台登录';
                        $val['tpl_title'] = '后台登录验证码，请查收！';
                        $val['send_scene'] = 30;
                        $val['is_open'] = 1;
                        $addData[] = $val;
                    }
                    $r = Db::name('smtp_tpl')->insertAll($addData);
                }
                if ($r !== false) {
                    tpSetting('syn', ['syn_admin_logic_1727423350'=>1], 'cn');
                }
            }catch(\Exception $e){}
        }
    }

    /**
     * 升级后，清理缓存文件
     * @return [type] [description]
     */
    private function upgrade_clear_cache()
    {
        $version = getVersion();
        $syn_admin_logic_1726881989 = tpSetting('syn.syn_admin_logic_1726881989', [], 'cn');
        if ($syn_admin_logic_1726881989 != $version) {
            try {
                delFile(rtrim(RUNTIME_PATH, '/'));
                tpSetting('syn', ['syn_admin_logic_1726881989' => $version], 'cn');
            } catch (\Exception $e) {}
        }
    }

    /**
     * 自动更新插件里的jquery文件为最新版本，修复jquery漏洞
     * @return [type] [description]
     */
    private function copy_jquery()
    {
        $list = glob('weapp/*/template/skin/js/jquery.js');
        if (!empty($list)) {
            $list[] = 'public/static/common/diyminipro/js/jquery.min.js';
            $minilist = glob('weapp/*/template/*/js/jquery.min.js');
            if (!empty($minilist)) {
                $list = array_merge($list, $minilist);
            }
            foreach ($list as $key => $val) {
                if (file_exists('./'.$val)) {
                    @copy(realpath('public/static/admin/js/jquery.js'), realpath($val));
                }
            }
        }
    }
    
    /*
    * 初始化原来的菜单栏目
    */
    public function initialize_admin_menu(){
        $total = Db::name("admin_menu")->count();
        if (empty($total)){
            $menuArr = getAllMenu();
            $insert_data = [];
            foreach ($menuArr as $key => $val){
                foreach ($val['child'] as $nk=>$nrr) {
                    $sort_order = 100;
                    $is_switch = 1;
                    if ($nrr['id'] == 2004){
                        $sort_order = 10000;
                        $is_switch = 0;
                    }
                    $insert_data[] = [
                        'menu_id' => $nrr['id'],
                        'title' => $nrr['name'],
                        'controller_name' => $nrr['controller'],
                        'action_name' => $nrr['action'],
                        'param' => !empty($nrr['param']) ? $nrr['param'] : '',
                        'is_menu' => $nrr['is_menu'],
                        'is_switch' => $is_switch,
                        'icon' =>  $nrr['icon'],
                        'sort_order' => $sort_order,
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                }
            }
            Db::name("admin_menu")->insertAll($insert_data);
        }
    }

    // 升级v1.6.7版本要处理的数据
    private function eyou_v167_handle_data()
    {
        $Prefix = config('database.prefix');

        // 售后数据表加入原路退回功能需要的字段
        if (config('database.type') == 'dm') { // 达梦优化
            $serviceTableInfo = Db::query("SELECT COLUMN_NAME,DATA_TYPE,DATA_DEFAULT,NULLABLE FROM ALL_TAB_COLS WHERE TABLE_NAME = '{$Prefix}shop_order_service'");
            $serviceTableInfo = get_arr_column($serviceTableInfo, 'COLUMN_NAME');   
            if (!empty($serviceTableInfo) && !in_array('refund_way', $serviceTableInfo)) {
                $sql = "ALTER TABLE `{$Prefix}shop_order_service` ADD COLUMN `refund_way` TINYINT NOT NULL DEFAULT 0;";                
                if (@Db::execute($sql)) {
                    $sql = "comment ON COLUMN `{$Prefix}shop_order_service`.`refund_way` IS '退款方式(1:退款到余额; 2:线下退款; 3:原路退回(微信))';";
                    @Db::execute($sql);
                }
            }
        }else{
            $serviceTableInfo = Db::query("SHOW COLUMNS FROM {$Prefix}shop_order_service");
            $serviceTableInfo = get_arr_column($serviceTableInfo, 'Field');
            if (!empty($serviceTableInfo) && !in_array('refund_way', $serviceTableInfo)) {
                $sql = "ALTER TABLE `{$Prefix}shop_order_service` ADD COLUMN `refund_way`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '退款方式(1:退款到余额; 2:线下退款; 3:原路退回(微信))' AFTER `refund_note`;";
                @Db::execute($sql);
            }
        }
        schemaTable('shop_order_service');

        // 修复外贸助手的文案错误
        $syn_admin_logic_1719815413 = tpSetting('syn.syn_admin_logic_1719815413', [], 'cn');
        if (empty($syn_admin_logic_1719815413)) {
            try {
                Db::name('foreign_pack')->where(['name'=>'users7','value'=>'%已存在！'])->update(['value'=>'%s已存在！']);
                Db::name('foreign_pack')->where(['name'=>'users7','value'=>'% already exists!'])->update(['value'=>'%s already exists!']);
                tpSetting('syn', ['syn_admin_logic_1719815413' => 1], 'cn');
            } catch (\Exception $e) {
            }
        }

        // 修复消息推送的多余数据
        $syn_admin_logic_1721179406 = tpSetting('syn.syn_admin_logic_1721179406', [], 'cn');
        if (empty($syn_admin_logic_1721179406)) {
            try {
                $tpl_ids = [];
                $markList = Db::name('language')->field('mark')->getAllWithIndex('mark');
                $result = Db::name('wechat_template')->order('send_scene asc, lang asc, tpl_id asc')->select();
                $result = group_same_key($result, 'send_scene');
                foreach ($result as $key => $val) {
                    $mark_arr = $markList;
                    foreach ($val as $_k => $_v) {
                        if (isset($mark_arr[$_v['lang']])) {
                            $tpl_ids[] = $_v['tpl_id'];
                            unset($mark_arr[$_v['lang']]);
                        }
                        if (empty($mark_arr)) {
                            break;
                        }
                    }
                }
                if (!empty($tpl_ids)) {
                    Db::name('wechat_template')->where(['tpl_id'=>['NOTIN', $tpl_ids]])->delete();
                }
                Db::name('wechat_template')->where(['send_scene'=>1])->update(['tpl_title'=>'新表单']);
                Db::name('wechat_template')->where(['send_scene'=>9])->update(['tpl_title'=>'新订单']);
                
                tpSetting('syn', ['syn_admin_logic_1721179406' => 1], 'cn');
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * 同步模板的付费选择支付文件到前台模板指定位置
     * @return [type] [description]
     */
    public function copy_tplpayfile($channel = 0)
    {
        $shop_open_comment = getUsersConfigData('shop.shop_open_comment');
        $channelRow = Db::name('channeltype')->where(['status'=>1])->getAllWithIndex('id');
        foreach ($channelRow as $key => $val) {
            $data = json_decode($val['data'], true);
            $val['data'] = empty($data) ? [] : $data;
            $channelRow[$key] = $val;
        }
        $source_path = ROOT_PATH.'public/html/template/';
        $dest_path = ROOT_PATH.'template/'.THEME_STYLE_PATH.'/system/';
        if (stristr($dest_path, '/pc/system/')) {
            tp_mkdir($dest_path);
            if (!empty($channelRow[1]['data']['is_article_pay'])) {
                if (in_array($channel, [0,1]) && !file_exists($dest_path.'article_pay.htm') && file_exists($source_path.'pc/system/article_pay.htm')) {
                    @copy($source_path.'pc/system/article_pay.htm', $dest_path.'article_pay.htm');
                }
            }
            if (!empty($channelRow[4]['data']['is_download_pay'])) {
                if (in_array($channel, [0,4]) && !file_exists($dest_path.'download_pay.htm') && file_exists($source_path.'pc/system/download_pay.htm')) {
                    @copy($source_path.'pc/system/download_pay.htm', $dest_path.'download_pay.htm');
                }
            }
            if (!empty($shop_open_comment)) {
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'product_comment.htm') && file_exists($source_path.'pc/system/product_comment.htm')) {
                    @copy($source_path.'pc/system/product_comment.htm', $dest_path.'product_comment.htm');
                }
            }
        }

        $dest_path = ROOT_PATH.'template/'.THEME_STYLE_PATH;
        $dest_path = preg_replace('/\/pc$/i', '/mobile', $dest_path);
        if (file_exists($dest_path)) {
            $dest_path .= '/system/';
            tp_mkdir($dest_path);
            if (!empty($channelRow[1]['data']['is_article_pay'])) {
                if (in_array($channel, [0,1]) && !file_exists($dest_path.'article_pay.htm') && file_exists($source_path.'mobile/system/article_pay.htm')) {
                    @copy($source_path.'mobile/system/article_pay.htm', $dest_path.'article_pay.htm');
                }
            }
            if (!empty($channelRow[4]['data']['is_download_pay'])) {
                if (in_array($channel, [0,4]) && !file_exists($dest_path.'download_pay.htm') && file_exists($source_path.'mobile/system/download_pay.htm')) {
                    @copy($source_path.'mobile/system/download_pay.htm', $dest_path.'download_pay.htm');
                }
            }
            if (!empty($shop_open_comment)) {
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'product_comment.htm') && file_exists($source_path.'mobile/system/product_comment.htm')) {
                    @copy($source_path.'mobile/system/product_comment.htm', $dest_path.'product_comment.htm');
                }
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'comment_list.htm') && file_exists($source_path.'mobile/system/comment_list.htm')) {
                    @copy($source_path.'mobile/system/comment_list.htm', $dest_path.'comment_list.htm');
                }
            }
        }
    }
}
