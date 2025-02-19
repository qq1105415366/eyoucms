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
use think\Db;
use think\Cache;
use think\Request;
use think\Page;

class System extends Base
{
    // 选项卡是否显示
    public $tabase = '';
    
    public function _initialize() {
        parent::_initialize();
        $this->tabase = input('param.tabase/d');
    }

    public function index()
    {
        $this->redirect(url('System/web'));
    }

    /**
     * 网站设置
     */
    public function web()
    {
        $inc_type =  'web';
        $config = tpCache($inc_type);

        if (IS_POST) {
            $param = input('post.');
            $param['web_basehost'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_basehost']);
            $param['web_title'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_title']);
            $param['web_keywords'] = str_replace('，', ',', $param['web_keywords']);
            $param['web_keywords'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_keywords']);
            $param['web_description'] = filter_line_return($param['web_description']);
            $param['web_description'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_description']);
            $param['web_status_url'] = trim($param['web_status_url']);
            $web_status_tpl = trim($param['web_status_tpl']);
            $web_status_tpl = trim($web_status_tpl, '/');
            if (!empty($this->root_dir)) {
                $web_status_tpl = preg_replace('#^'.$this->root_dir.'/#i', '', '/'.$web_status_tpl);
                $web_status_tpl = trim($web_status_tpl, '/');
            }
            $param['web_status_tpl'] = $web_status_tpl;

            if (1 == $param['web_status']) {
                /*多语言 - 不知为何v1.4.0新增该逻辑【在静态模式下，关闭网站会自动切换为动态URL模式】*/
                // $seo_pseudo = tpCache('global.seo_pseudo');
                // if (2 == $seo_pseudo) {
                //     if (is_language()) {
                //         $langRow = \think\Db::name('language')->order('id asc')
                //             ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                //             ->select();
                //         foreach ($langRow as $key => $val) {
                //             tpCache('seo',['seo_pseudo'=>1],$val['mark']);
                //         }
                //     } else {
                //         tpCache('seo',['seo_pseudo'=>1]);
                //     }
                // }
                /*--end*/
                @unlink('./index.html');
            }

            // 站点状态关闭时，所关闭的终端口(pc、mobile)
            // $param['web_close_port'] = !empty($param['web_close_port']) ? serialize($param['web_close_port']) : '';

            // 网站根网址
            $web_basehost = rtrim($param['web_basehost'], '/');
            if (!is_http_url($web_basehost) && !empty($web_basehost)) {
                $web_basehost = $this->request->scheme().'://'.$web_basehost;
            }
            $param['web_basehost'] = $web_basehost;

            // 网站logo
            // $web_logo = '';
            // $web_logo_is_remote = !empty($param['web_logo_is_remote']) ? $param['web_logo_is_remote'] : 0;
            // if ($web_logo_is_remote == 1) {
            //     $web_logo = $param['web_logo_remote'];
            // } else {
            //     $web_logo = $param['web_logo_local'];
            // }
            $param['web_logo'] = $param['web_logo_local'];
            // unset($param['web_logo_is_remote']);
            // unset($param['web_logo_remote']);
            unset($param['web_logo_local']);

            // 浏览器地址图标
            if (!empty($param['web_ico']) && !is_http_url($param['web_ico'])) {
                $source = realpath(preg_replace('#^'.$this->root_dir.'/#i', '', $param['web_ico']));
                $destination = realpath('favicon.ico');
                if (empty($destination) || $source == $destination) {
                    unset($param['web_ico']);
                } else {
                    /*修复copy一句话图片木马漏洞*/
                    $source_ext = pathinfo($source, PATHINFO_EXTENSION);
                    if (!empty($source_ext) && !in_array($source_ext, ['ico'])) {
                        $this->error('地址栏图标必须是扩展名为ico的图片');
                    }
                    /*end*/
                    if (file_exists($source) && @copy($source, $destination)) {
                        $param['web_ico'] = $this->root_dir.'/favicon.ico';
                    }
                }
            }
            $param['web_recordnum'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_recordnum']);
            $param['web_garecordnum'] = preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', $param['web_garecordnum']);

            if (function_exists('is_template_opt') && is_template_opt()) {
                $param['web_thirdcode_pc'] = str_replace('ｓｃｒｉｐｔ', 'script', $param['web_thirdcode_pc']);
                $param['web_thirdcode_wap'] = str_replace('ｓｃｒｉｐｔ', 'script', $param['web_thirdcode_wap']);
            }

            tpCache($inc_type, $param);
            write_global_params($this->admin_lang); // 写入全局内置参数

            if ($config['web_basehost'] != $param['web_basehost']) {
                verify_authortoken();
            }

            $this->success('操作成功', url('System/web'));
            exit;
        }

        // 网站logo
        // if (is_http_url($config['web_logo'])) {
        //     $config['web_logo_is_remote'] = 1;
        //     $config['web_logo_remote'] = handle_subdir_pic($config['web_logo']);
        // } else {
        //     $config['web_logo_is_remote'] = 0;
        //     $config['web_logo_local'] = handle_subdir_pic($config['web_logo']);
        // }
        $config['web_logo_local'] = handle_subdir_pic($config['web_logo']);
        $config['web_ico'] = handle_subdir_pic($config['web_ico']);
        
        /*系统模式*/
        $web_cmsmode = isset($config['web_cmsmode']) ? $config['web_cmsmode'] : 2;
        $this->assign('web_cmsmode', $web_cmsmode);
        /*--end*/

        /*自定义变量*/
        /*$eyou_row = Db::name('config_attribute')->field('a.attr_id, a.attr_name, a.attr_var_name, a.attr_input_type, b.value, b.id, b.name')
            ->alias('a')
            ->join('__CONFIG__ b', 'b.name = a.attr_var_name AND b.lang = a.lang', 'LEFT')
            ->where([
                'b.lang'    => $this->admin_lang,
                'a.inc_type'    => $inc_type,
                'b.is_del'  => 0,
            ])
            ->order('a.attr_id asc')
            ->select();
        foreach ($eyou_row as $key => $val) {
            $val['value'] = handle_subdir_pic($val['value'], 'html'); // 支持子目录
            $val['value'] = handle_subdir_pic($val['value']); // 支持子目录
            $eyou_row[$key] = $val;
        }
        $this->assign('eyou_row',$eyou_row);*/
        /*--end*/

        // 站点状态关闭时，所关闭的终端口(pc、mobile)
        // $config['web_close_port'] = !empty($config['web_close_port']) ? unserialize($config['web_close_port']) : ['pc', 'mobile'];

        $this->assign('config',$config);//当前配置项
        $this->assign('seo_pseudo', tpCache('global.seo_pseudo')); // URL模式
        return $this->fetch();
    }

    /**
     * 自定义变量
     */
    public function customvar()
    {
        $inc_type =  'web';

        if (IS_POST) {
            $param = input('post.');

            tpCache($inc_type, $param);
            write_global_params($this->admin_lang); // 写入全局内置参数
            $this->success('操作成功', url('System/customvar'));
            exit;
        }

        $config = tpCache($inc_type);

        /*系统模式*/
        $web_cmsmode = isset($config['web_cmsmode']) ? $config['web_cmsmode'] : 2;
        $this->assign('web_cmsmode', $web_cmsmode);
        /*--end*/

        /*自定义变量*/
        $eyou_row = Db::name('config_attribute')->field('a.attr_id, a.attr_name, a.attr_var_name, a.attr_input_type, b.value, b.id, b.name')
            ->alias('a')
            ->join('__CONFIG__ b', 'b.name = a.attr_var_name AND b.lang = a.lang', 'LEFT')
            ->where([
                'b.lang'    => $this->admin_lang,
                'a.inc_type'    => $inc_type,
                'b.is_del'  => 0,
            ])
            ->order('a.attr_id asc')
            ->select();
        foreach ($eyou_row as $key => $val) {
            $val['value'] = handle_subdir_pic($val['value'], 'html'); // 支持子目录
            $val['value'] = handle_subdir_pic($val['value']); // 支持子目录
            $eyou_row[$key] = $val;
        }
        $this->assign('eyou_row',$eyou_row);
        /*--end*/

        $this->assign('config',$config);//当前配置项
        $this->assign('seo_pseudo', tpCache('global.seo_pseudo')); // URL模式
        return $this->fetch();
    }

    /**
     * 核心设置
     */
    public function web2()
    {
        $this->language_access(); // 多语言功能操作权限

        $inc_type = 'web';
        $config = tpCache($inc_type);

        if (IS_POST) {
            $param = input('post.');
            $param['web_show_popup_upgrade'] = intval($param['web_show_popup_upgrade']);
            if (empty($param['web_show_popup_upgrade']) && $this->php_servicemeal <= 0) {
                $param['web_show_popup_upgrade'] = -1;
            }
            if (1 == $param['web_mobile_domain_open']) {
                $web_mobile_domain = trim($param['web_mobile_domain']);
                if (!empty($web_mobile_domain) && ($web_mobile_domain == 'www' || $web_mobile_domain == $this->request->subDomain())) {
                    $this->error("手机站域名配置不能与主站域名一致！");
                }
            } else {
                unset($param['web_mobile_domain']);
            }
            /*EyouCMS安装目录*/
            empty($param['web_cmspath']) && $param['web_cmspath'] = $this->root_dir; // 支持子目录
            $web_cmspath = trim($param['web_cmspath'], '/');
            $web_cmspath = !empty($web_cmspath) ? '/'.$web_cmspath : '';
            $param['web_cmspath'] = $web_cmspath;

            /*前台模板风格*/
            $web_tpl_theme = $param['web_tpl_theme'];
            $web_tpl_theme_old = tpCache('global.web_tpl_theme');
            /*--end*/

            $recycle_is_clear = $param['recycle_is_clear']; // 是否要清空回收站
            unset($param['recycle_is_clear']);


            // $web_language_switch = tpCache('global.web_language_switch');
            // if (!empty($web_language_switch)) {
            //     $param['web_citysite_open'] = 0;
            // }

            $other_pcwapjs = $param['other_pcwapjs'];
            unset($param['other_pcwapjs']);

            $seo_pseudo = tpCache('global.seo_pseudo');
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                    write_global_params($val['mark']); // 写入全局内置参数
                    // if (!empty($param['web_citysite_open']) && 2 == $seo_pseudo) {
                    //     tpCache('seo', ['seo_pseudo'=>1, 'seo_dynamic_format'=>1], $val['mark']);
                    // }
                    // pc/wap跳转js
                    tpCache('other', ['other_pcwapjs'=>$other_pcwapjs], $val['mark']);
                }
            } else {
                tpCache($inc_type,$param);
                write_global_params($this->admin_lang); // 写入全局内置参数
                // if (!empty($param['web_citysite_open']) && 2 == $seo_pseudo) {
                //     tpCache('seo', ['seo_pseudo'=>1, 'seo_dynamic_format'=>1]);
                // }
                // pc/wap跳转js
                tpCache('other', ['other_pcwapjs'=>$other_pcwapjs]);
            }
            /*--end*/

            // URL启用/关闭https
            $this->setWebHttpsFilter($param['web_is_https']);

            // 开发模式，清掉缓存
            if (2 == $param['web_cmsmode']) {
                Cache::clear();
                delFile(RUNTIME_PATH);
            }

            // 清空回收站
            if (!empty($param['web_recycle_switch']) && !empty($recycle_is_clear)) {
                model('RecycleBin')->clear('all');
            }

            // 多城市开关
            // model('Citysite')->setCitysiteOpen();

            if ($web_tpl_theme != $web_tpl_theme_old) {
                foreach ([HTML_ROOT,TEMP_PATH] as $k2 => $v2) {
                    delFile($v2);
                }
            }
            if ((isset($config['absolute_path_open']) && $param['absolute_path_open'] != $config['absolute_path_open']) || (!isset($config['absolute_path_open']) && $param['absolute_path_open'] == 1)){
                foreach ([HTML_ROOT,TEMP_PATH,CACHE_PATH] as $k3 => $v3) {
                    delFile($v3);
                }
            }

            $this->success('操作成功', url('System/web2'));
        }

        // 当前主域名
        $this->assign('subDomain', $this->request->subDomain());

        // 当前域名是否IP或者localhost本地
        $is_localhost = 0;
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $this->request->host(true)) || 'localhost' == $this->request->host(true)) {
            $is_localhost = 1;
        }
        $this->assign('is_localhost',$is_localhost);

        /*模板风格列表*/
        $tpl_theme_list = glob('./template/*', GLOB_ONLYDIR);
        foreach ($tpl_theme_list as $key => &$val) {
            $val = str_replace('\\', '/', $val);
            $val = preg_replace('/^(.*)\/([^\/]*)$/i', '${2}', $val);
        }
        $this->assign('tpl_theme_list', $tpl_theme_list);

        $show_uiset = '';
        $web_tpl_theme = !empty($config['web_tpl_theme']) ? $config['web_tpl_theme'].DS : '';
        if (file_exists(ROOT_PATH.'template'.DS.$web_tpl_theme.'pc'.DS.'uiset.txt') && file_exists(ROOT_PATH.'template'.DS.$web_tpl_theme.'mobile'.DS.'uiset.txt')) {
            $show_uiset = 'pc+mobile';
        } else if (file_exists(ROOT_PATH.'template'.DS.$web_tpl_theme.'pc'.DS.'uiset.txt')) {
            $show_uiset = 'pc';
        } else if (file_exists(ROOT_PATH.'template'.DS.$web_tpl_theme.'mobile'.DS.'uiset.txt')) {
            $show_uiset = 'mobile';
        }
        $this->assign('show_uiset', $show_uiset);
        /*end*/

        /*代理贴牌功能限制-s*/
        $upgrade = true;
        if (function_exists('checkAuthRule')) {
            //系统升级
            $upgrade = checkAuthRule('upgrade');
        }
        $this->assign('upgrade', $upgrade);
        /*代理贴牌功能限制-e*/
        
        $this->assign('config',$config);//当前配置项

        // pc/wap跳转js
        $other_pcwapjs = tpCache('global.other_pcwapjs');
        $this->assign('other_pcwapjs', $other_pcwapjs);

        return $this->fetch();
    }

    /**
     * URL启用/关闭https
     */
    private function setWebHttpsFilter($web_is_https = 0)
    {
        $tfile = DATA_PATH.'conf'.DS.'https_service.txt';
        if (!empty($web_is_https)) {
            $fp = @fopen($tfile,'w');
            if(!$fp) {
                @file_put_contents($tfile, $web_is_https);
            }
            else {
                fwrite($fp, $web_is_https);
                fclose($fp);
            }
        } else {
            @unlink($tfile);
        }
    }

    /**
     * 附件设置
     */
    public function basic()
    {
        $inc_type =  'basic';
        $other_inc_type = 'other';

        // 文件上传最大限制
        $maxFileupload = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : 0;
        if (0 !== $maxFileupload) {
            $max_filesize = unformat_bytes($maxFileupload);
            $max_filesize = $max_filesize / 1024 / 1024; // 单位是MB的大小
        } else {
            $max_filesize = 500;
        }
        $max_sizeunit = 'MB';
        $maxFileupload = $max_filesize.$max_sizeunit;

        if (IS_POST) {
            $param = input('post.');

            // 文档默认浏览量
            if (isset($param['other_arcclick']) && 0 <= $param['other_arcclick']) {
                $arcclick_arr = explode("|", $param['other_arcclick']);
                if (count($arcclick_arr) > 1) {
                    foreach ($arcclick_arr as $k => $v) {
                        $arcclick_arr[$k] = intval($v);
                        if ($k > 1) {
                            unset($arcclick_arr[$k]);
                        }
                    }
                    asort($arcclick_arr);
                    $other_param['other_arcclick'] = implode('|', $arcclick_arr);
                } else {
                    $other_param['other_arcclick'] = intval($arcclick_arr[0]);
                }
            }else{
                $other_param['other_arcclick'] = '500|1000';
            }
            unset($param['other_arcclick']);

            // 软件默认下载量
            if (isset($param['other_arcdownload']) && 0 <= $param['other_arcdownload']) {
                $arcdownload_arr = explode("|", $param['other_arcdownload']);
                if (count($arcdownload_arr) > 1) {
                    foreach ($arcdownload_arr as $k => $v) {
                        $arcdownload_arr[$k] = intval($v);
                        if ($k > 1) {
                            unset($arcdownload_arr[$k]);
                        }
                    }
                    asort($arcdownload_arr);
                    $other_param['other_arcdownload'] = implode('|', $arcdownload_arr);
                } else {
                    $other_param['other_arcdownload'] = intval($arcdownload_arr[0]);
                }
            }else{
                $other_param['other_arcdownload'] = '500|1000';
            }
            unset($param['other_arcdownload']);

            $old_basic_img_setlist = $param['old_basic_img_setlist'];
            unset($param['old_basic_img_setlist']);

            $param['file_size'] = intval($param['file_size']);
            if (0 < $max_filesize && $max_filesize < $param['file_size']) {
                $this->error("附件上传大小超过空间的最大限制".$maxFileupload);
            }
            // 允许上传图片类型
            $image_ext = config('global.image_ext');
            $image_ext_arr = explode(',', $image_ext);
            $image_type = explode('|', $param['image_type']);
            foreach ($image_type as $key => $val) {
                $val = trim($val);
                if (!in_array($val, $image_ext_arr) || empty($val)) {
                    unset($image_type[$key]);
                }
            }
            $param['image_type'] = implode('|', $image_type);

            // 允许上传软件类型，类型太多无法进行白名单处理
            $file_type = explode('|', $param['file_type']);
            foreach ($file_type as $key => $val) {
                $val = trim($val);
                if (preg_match('/^(php|asp|jsp|perl|cgi|asa|pht|phtml|htm)/i', $val) || empty($val)) {
                    unset($file_type[$key]);
                }
            }
            $param['file_type'] = implode('|', $file_type);

            // 允许多媒体类型
            $media_ext = config('global.media_ext');
            $media_ext_arr = explode(',', $media_ext);
            $media_type = explode('|', $param['media_type']);
            foreach ($media_type as $key => $val) {
                $val = trim($val);
                if (empty($val)) {
                    unset($media_type[$key]);
                } else if (!in_array($val, $media_ext_arr)) {
                    $this->error("不支持{$val}格式");
                }
            }
            $param['media_type'] = implode('|', $media_type);
            /*end*/

            // 内容图片附加功能
            $param['basic_img_auto_wh'] = !empty($param['basic_img_auto_wh']) ? $param['basic_img_auto_wh'] : 0;
            $param['basic_img_alt'] = !empty($param['basic_img_alt']) ? $param['basic_img_alt'] : 0;
            $param['basic_img_title'] = !empty($param['basic_img_title']) ? $param['basic_img_title'] : 0;
            $param['basic_img_alt_force'] = !empty($param['basic_img_alt_force']) ? $param['basic_img_alt_force'] : 0;
            $param['basic_body_allowurls'] = !empty($param['basic_body_allowurls']) ? $param['basic_body_allowurls'] : '';
            /*过滤重复值和域名处理*/
            $allowurls_arr = explode(PHP_EOL, $param['basic_body_allowurls']);
            foreach ($allowurls_arr as $key => $val) {
                $tmp_val = trim($val);
                $tmp_val = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $tmp_val);
                if (empty($tmp_val)) {
                    unset($allowurls_arr[$key]);
                    continue;
                }
                $allowurls_arr[$key] = $tmp_val;
            }
            $allowurls_arr = array_unique($allowurls_arr);
            $param['basic_body_allowurls'] = implode(PHP_EOL, $allowurls_arr);
            /*end*/

            /*多语言*/
            if (is_language()) {
                $newParam['basic_indexname'] = $param['basic_indexname'];
                tpCache($inc_type,$newParam);

                $synLangParam = $param; // 同步更新多语言的数据
                unset($synLangParam['basic_indexname']);
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $synLangParam, $val['mark']);
                    if (!empty($other_param)) {
                        tpCache($other_inc_type, $other_param, $val['mark']);
                    }
                }
            } else {
                tpCache($inc_type,$param);
                if (!empty($other_param)) {
                    tpCache($other_inc_type, $other_param);
                }
            }
            /*--end*/

            $new_basic_img_setlist = $param['basic_img_auto_wh'].$param['basic_img_alt'].$param['basic_img_title'];
            if ($old_basic_img_setlist != $new_basic_img_setlist) {
                // 清空详情页缓存
                foreach (['http','https'] as $key => $val) {
                    delFile(HTML_ROOT.$val.'/view');
                }
            }

            // 编辑器选项配置
            if (!empty($param['editor_select'])) {
                $editor_arr = [
                    'editor_select' => $param['editor_select'],
                    'editor_bdmap_ak' => $param['editor_bdmap_ak'],
                    'editor_remote_img_local' => $param['editor_remote_img_local'],
                    'editor_img_clear_link' => $param['editor_img_clear_link'],
                ];
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpSetting('editor', $editor_arr, $val['mark']);
                    }
                } else {
                    tpSetting('editor',$editor_arr);
                }
                $bdmap_ak_js =  ROOT_PATH.'public/plugins/Ueditor/dialogs/map/bdmap_ak.js';
                file_put_contents($bdmap_ak_js,"var bdmap_ak = '{$editor_arr['editor_bdmap_ak']}';");
            }

            $this->success('操作成功', url('System/basic'));
        }

        $config = tpCache($inc_type);
        $other_config = tpCache($other_inc_type);

        $this->assign('config', $config);
        $this->assign('other_config', $other_config);
        $this->assign('max_filesize', $max_filesize);
        $this->assign('max_sizeunit', $max_sizeunit);

        $editor = tpSetting('editor');
        $this->assign('editor', $editor);
        return $this->fetch();
    }
    /*
     *
     */
    public function basic_open(){
        $inc_type =  'basic';
        $other_inc_type = 'other';

        $config = tpCache($inc_type);
        $other_config = tpCache($other_inc_type);
        $editor = tpSetting('editor');

        if (IS_POST) {
            $param = input('post.');
            // 文档默认浏览量
            if (isset($param['other_arcclick']) && 0 <= $param['other_arcclick']) {
                $arcclick_arr = explode("|", $param['other_arcclick']);
                if (count($arcclick_arr) > 1) {
                    foreach ($arcclick_arr as $k => $v) {
                        $arcclick_arr[$k] = intval($v);
                        if ($k > 1) {
                            unset($arcclick_arr[$k]);
                        }
                    }
                    asort($arcclick_arr);
                    $other_param['other_arcclick'] = implode('|', $arcclick_arr);
                } else {
                    $other_param['other_arcclick'] = intval($arcclick_arr[0]);
                }
            }else{
                $other_param['other_arcclick'] = '500|1000';
            }
            unset($param['other_arcclick']);

            // 软件默认下载量
            if (isset($param['other_arcdownload']) && 0 <= $param['other_arcdownload']) {
                $arcdownload_arr = explode("|", $param['other_arcdownload']);
                if (count($arcdownload_arr) > 1) {
                    foreach ($arcdownload_arr as $k => $v) {
                        $arcdownload_arr[$k] = intval($v);
                        if ($k > 1) {
                            unset($arcdownload_arr[$k]);
                        }
                    }
                    asort($arcdownload_arr);
                    $other_param['other_arcdownload'] = implode('|', $arcdownload_arr);
                } else {
                    $other_param['other_arcdownload'] = intval($arcdownload_arr[0]);
                }
            }else{
                $other_param['other_arcdownload'] = '500|1000';
            }
            unset($param['other_arcdownload']);

            $old_basic_img_setlist = $param['old_basic_img_setlist'];
            unset($param['old_basic_img_setlist']);

            // 内容图片附加功能
            $param['basic_img_auto_wh'] = !empty($param['basic_img_auto_wh']) ? $param['basic_img_auto_wh'] : 0;
            $param['basic_img_alt'] = !empty($param['basic_img_alt']) ? $param['basic_img_alt'] : 0;
            $param['basic_img_title'] = !empty($param['basic_img_title']) ? $param['basic_img_title'] : 0;

            /*多语言*/
            if (is_language()) {
                $newParam['basic_indexname'] = $param['basic_indexname'];
                $newParam = array_merge($config,$newParam);
                tpCache($inc_type,$newParam);

                $synLangParam = $param; // 同步更新多语言的数据
                unset($synLangParam['basic_indexname']);
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                $synLangParam = array_merge($config,$synLangParam);
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $synLangParam, $val['mark']);
                    if (!empty($other_param)) {
                        $other_param = array_merge($other_config,$other_param);
                        tpCache($other_inc_type, $other_param, $val['mark']);
                    }
                }
            } else {
                $param = array_merge($config,$param);
                tpCache($inc_type,$param);
                if (!empty($other_param)) {
                    $other_param = array_merge($other_config,$other_param);
                    tpCache($other_inc_type, $other_param);
                }
            }
            /*--end*/

            $new_basic_img_setlist = $param['basic_img_auto_wh'].$param['basic_img_alt'].$param['basic_img_title'];
            if ($old_basic_img_setlist != $new_basic_img_setlist) {
                // 清空详情页缓存
                foreach (['http','https'] as $key => $val) {
                    delFile(HTML_ROOT.$val.'/view');
                }
            }

            // 编辑器选项配置
            if (!empty($param['editor_select'])) {
                $editor_arr = [
                    'editor_select' => $param['editor_select'],
                    'editor_remote_img_local' => $param['editor_remote_img_local'],
                    'editor_img_clear_link' => $param['editor_img_clear_link'],
                ];
                tpSetting('editor', $editor_arr);
            }

            $this->success('操作成功', url('System/basic_open'));
        }


        $this->assign('config', $config);
        $this->assign('other_config', $other_config);
        $this->assign('editor', $editor);

        return $this->fetch();
    }
    /**
     * 图片水印
     */
    public function water()
    {
        $this->language_access(); // 多语言功能操作权限

        $inc_type =  'water';

        if (IS_POST) {
            $param = input('post.');
            $tabase = input('post.tabase/d');
            unset($param['tabase']);
            if (is_http_url($param['mark_img'])){
                $this->error('仅支持本地图片');
//                if (preg_match('/^\/\//i',$param['mark_img'])){
//                    $param['mark_img'] = request()->scheme().':'.$param['mark_img'];
//                }
//                $r =  saveRemote($param['mark_img'], 'temp/',0);
//                $r = json_decode($r, true);
//                if ('SUCCESS' != $r['state']) {
//                    $this->error('远程水印图片本地化失败,请检查图片链接域名协议与网站域名协议是否一致');
//                }
//                $param['mark_img'] = handle_subdir_pic($r['url']);
            }

            $waterPath = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $param['mark_img']);
            $waterImgInfo = @getimagesize('.'.$waterPath);
            $waterImgW = !empty($waterImgInfo[0]) ? $waterImgInfo[0] : 0;
            $waterImgH = !empty($waterImgInfo[1]) ? $waterImgInfo[1] : 0;
            if ($waterImgW > 2000 || $waterImgH > 2000) {
                $this->error('水印图片像素不能过大，否则无法对小图片进行水印！');
            }

            /*多语言*/
            empty($param['mark_img_is_remote']) && $param['mark_img_is_remote'] = 0;
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type,$param);
            }
            /*--end*/
            $this->success('操作成功', url('System/'.$inc_type, ['tabase'=>$tabase]));
        }

        $config = tpCache($inc_type);

        $this->assign('config',$config);//当前配置项
        return $this->fetch();
    }

    /**
     * 缩略图配置
     */
    public function thumb()
    {
        $this->language_access(); // 多语言功能操作权限

        $inc_type =  'thumb';

        if (IS_POST) {
            $param = input('post.');
            $tabase = input('post.tabase/d');
            unset($param['tabase']);
            isset($param['thumb_width']) && $param['thumb_width'] = preg_replace('/[^0-9]/', '', $param['thumb_width']);
            isset($param['thumb_height']) && $param['thumb_height'] = preg_replace('/[^0-9]/', '', $param['thumb_height']);

            $thumbConfig = tpCache('thumb'); // 旧数据

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type,$param);
            }
            /*--end*/

            /*校验配置是否改动，若改动将会清空缩略图目录*/
            unset($param['__token__']);
            if (md5(serialize($param)) != md5(serialize($thumbConfig))) {
                delFile(HTML_ROOT); // 清空缓存页面
                delFile(UPLOAD_PATH.'thumb'); // 清空缩略图
            }
            /*--end*/

            $this->success('操作成功', url('System/'.$inc_type, ['tabase'=>$tabase]));
        }

        $config = tpCache($inc_type);

        // 设置缩略图默认配置
        if (!isset($config['thumb_open'])) {
            /*多语言*/
            $thumbextra = config('global.thumb');
            $param = [
                'thumb_open'    => $thumbextra['open'],
                'thumb_mode'    => $thumbextra['mode'],
                'thumb_color'   => $thumbextra['color'],
                'thumb_width'   => $thumbextra['width'],
                'thumb_height'  => $thumbextra['height'],
            ];
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type,$param);
            }
            $config = tpCache($inc_type);
            /*--end*/
        }

        $this->assign('config',$config);//当前配置项
        return $this->fetch();
    }

    // 所有API接口的配置
    public function api_conf()
    {
        /*是否开启支付功能*/
        $this->assign('pay_open', $this->usersConfig['pay_open']);
        /* END */

        /*余额支付开关*/
        $pay_balance_open = 1;
        if (!isset($this->usersConfig['pay_balance_open'])) {
            getUsersConfigData('pay', ['pay_balance_open' => 1]);
        } else {
            $pay_balance_open = intval($this->usersConfig['pay_balance_open']);
        }
        $this->assign('pay_balance_open', $pay_balance_open);
        /* END */

        /*支付接口*/
        $pay_api_list = Db::name('pay_api_config')->where('status', 1)->order('pay_id asc')->select();
        foreach ($pay_api_list as $key => $val) {
            $val['pay_info'] = !empty($val['pay_info']) ? unserialize($val['pay_info']) : [];
            if (1 === intval($val['system_built'])) {
                $val['litpic'] = $this->root_dir . "/public/static/admin/images/{$val['pay_mark']}.png";
            } else {
                $val['litpic'] = $this->root_dir . "/weapp/{$val['pay_mark']}/logo.png";
            }
            $pay_api_list[$key] = $val;
        }
        $pay_api_list = convert_arr_key($pay_api_list, 'pay_mark');
        $this->assign('pay_api_list', $pay_api_list);
        /* END */

        /*开放API开关*/
        $confApi = tpSetting("OpenMinicode.conf", [], $this->main_lang);
        $confApi = json_decode($confApi, true);
        $this->assign('confApi', $confApi);
        /* END */

        return $this->fetch();
    }

    /**
     * 邮件配置
     */
    public function smtp()
    {
        $inc_type =  'smtp';
        if (IS_POST) {
            $goback = input('param.goback/s');
            $param = input('post.');

            if (!empty($param['tpl_id'])){
                $open_send_scene = Db::name('smtp_tpl')->where('tpl_id','in',$param['tpl_id'])->column('send_scene');
                Db::name('smtp_tpl')->where('send_scene','in',$open_send_scene)->update(['is_open'=>1,'update_time'=>getTime()]);
                Db::name('smtp_tpl')->where('send_scene','not in',$open_send_scene)->update(['is_open'=>0,'update_time'=>getTime()]);
            }else{
                Db::name('smtp_tpl')->where('is_open',1)->update(['is_open'=>0,'update_time'=>getTime()]);
            }
            if (isset($param['tpl_id'])) unset($param['tpl_id']);
            
            $smtp_from_eamil = str_replace('，', ',', trim($param['smtp_from_eamil']));
            if (!empty($smtp_from_eamil)) {
                $test_email_arr = explode(',', $smtp_from_eamil);
                foreach ($test_email_arr as $key => $val) {
                    $val = trim($val);
                    if (!check_email($val)) {
                        unset($test_email_arr[$key]);
                    }
                }
                $smtp_from_eamil = implode(',', $test_email_arr);
            }
            $param['smtp_from_eamil'] = $smtp_from_eamil;
            
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type, $param);
            }
            /*--end*/
            
            $this->success('操作成功', url('System/smtp', ['goback'=>$goback]));
        }

        $smtp = tpCache('smtp');
        $this->assign('smtp', $smtp);

        $goback = input('param.goback/s');
        $this->assign('goback', $goback);

        $tpl_list = Db::name('smtp_tpl')->where(['send_scene'=>['neq',30], 'lang'=>$this->admin_lang])->order('tpl_id asc')->select();
        $this->assign('tpl_list', $tpl_list);

        return $this->fetch();
    }

    /**
     * 邮件模板列表
     */
    public function smtp_tpl()
    {
        $list = array();
        $keywords = input('keywords/s');

        $map = array();
        if (!empty($keywords)) {
            $map['tpl_name'] = array('LIKE', "%{$keywords}%");
        }

        // 多语言
        $map['lang'] = array('eq', $this->admin_lang);

        $count = Db::name('smtp_tpl')->where($map)->count('tpl_id');// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('smtp_tpl')->where($map)
            ->order('tpl_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        $pageStr = $pageObj->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $pageStr); // 赋值分页输出
        $this->assign('pager', $pageObj); // 赋值分页对象
        
        // 是否填写邮件配置
        $is_conf = 1;
        $smtp_config = tpCache('smtp');
        if (empty($smtp_config['smtp_user']) || empty($smtp_config['smtp_pwd'])) {
            $is_conf = 0;
        }
        $this->assign('is_conf', $is_conf);

        $shop_open = getUsersConfigData('shop.shop_open');
        $this->assign('shop_open', $shop_open);

        return $this->fetch();
    }

    /**
     * 短信配置
     */
    public function sms()
    {
        $inc_type =  'sms';
        if (IS_POST) {
            $param = input('post.');
            empty($param['sms_shop_order_pay']) && $param['sms_shop_order_pay'] = 0;
            empty($param['sms_guestbook_send']) && $param['sms_guestbook_send'] = 0;

            if (!isset($param['sms_type'])) $param['sms_type'] = 1;
            if ($param['sms_type'] == 1) {
                unset($param['sms_appkey_tx']);
                unset($param['sms_appid_tx']);
            }else {
                unset($param['sms_appkey']);
                unset($param['sms_secretkey']);
            }
            if (!empty($param['sms_type'])){
                if (!empty($param['tpl_id'][$param['sms_type']])){
                    Db::name('sms_template')->where('sms_type',$param['sms_type'])->where('tpl_id','in',$param['tpl_id'][$param['sms_type']])->update(['is_open'=>1,'update_time'=>getTime()]);
                    Db::name('sms_template')->where('sms_type',$param['sms_type'])->where('tpl_id','not in',$param['tpl_id'][$param['sms_type']])->update(['is_open'=>0,'update_time'=>getTime()]);
                }else{
                    Db::name('sms_template')->where('sms_type',$param['sms_type'])->where('tpl_id','>',0)->update(['is_open'=>0,'update_time'=>getTime()]);
                }
            }
            if (isset($param['tpl_id'])) unset($param['tpl_id']);

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type, $param);
            }
            /*--end*/

            $this->success('操作成功', url('System/sms'));
        }

        $assign_data = [];
        $sms = tpCache('sms');
        if (!isset($sms['sms_type'])) {
            $sms['sms_type'] = 1;
            tpCache('sms', array('sms_type'=>1));
        }
        $assign_data['sms'] = $sms;

        $map = [
            'send_scene'=>['neq',30],
            'lang' => $this->admin_lang,
        ];
        $map['sms_type'] = 1;
        $assign_data['sms_list1'] = Db::name('sms_template')->where($map)->order('tpl_id asc')->select();
        $map['sms_type'] = 2;
        $assign_data['sms_list2'] = Db::name('sms_template')->where($map)->order('tpl_id asc')->select();

        // ToSms短信通知插件内置代码 start
        if (file_exists('./weapp/ToSms/model/ToSmsModel.php')) {
            $toSmsModel = new \weapp\ToSms\model\ToSmsModel;
            $toSmsModel->admin_System_sms($assign_data);
        }
        // ToSms短信通知插件内置代码 end

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 短信模板列表
     */
    public function sms_tpl()
    {
        $param = input('param.');
        $list = array();
        $keywords = input('keywords/s');
        $sms_type = input('sms_type/d');
        if (empty($sms_type)) {
            $sms_type = tpCache('sms.sms_type');
            $sms_type = !empty($sms_type) ? $sms_type : 1;
        }

        $map = [
            'lang' => $this->admin_lang,
            'sms_type' => $sms_type,
        ];
        if (!empty($keywords)) $map['tpl_title'] = array('LIKE', "%{$keywords}%");

        $count = Db::name('sms_template')->where($map)->count('tpl_id');// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('sms_template')->where($map)
            ->order('tpl_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        $pageStr = $pageObj->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $pageStr); // 赋值分页输出
        $this->assign('pager', $pageObj); // 赋值分页对象
        $this->assign('sms_type', $sms_type); // 短信服务商

        // 是否填写手机短信配置
        $is_conf = 1;
        $sms_arr = [];
        $sms_config = tpCache('sms');
        foreach ($sms_config as $key => $val) {
            $sms_arr[$key] = $val;
        }
        if (2 == $sms_type) {
            foreach (['sms_appkey_tx','sms_appid_tx'] as $key => $val) {
                if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                    $is_conf = 0;
                }
            }
        } else {
            foreach (['sms_appkey','sms_secretkey'] as $key => $val) {
                if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                    $is_conf = 0;
                }
            }
        }
        $this->assign('is_conf', $is_conf);

        $shop_open = getUsersConfigData('shop.shop_open');
        $this->assign('shop_open', $shop_open);

        // ToSms短信通知插件内置代码 start
        $show_sms_tpl_code = true;
        if (file_exists('./weapp/ToSms/model/ToSmsModel.php')) {
            $toSmsModel = new \weapp\ToSms\model\ToSmsModel;
            $show_sms_tpl_code = $toSmsModel->admin_system_sms_tpl($sms_type, 'show_sms_tpl_code');
        }
        $this->assign('show_sms_tpl_code', $show_sms_tpl_code);
        // ToSms短信通知插件内置代码 end

        return $this->fetch();
    }

    /**
     * 微站点配置
     */
    public function microsite()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post)) {
                // 过滤左右多余空格
                foreach ($post as $key => $val) {
                    if (is_array($val)) {
                        foreach ($val as $_k => $_v) {
                            if (is_string($_v)) {
                                $post[$key][$_k] = trim($_v);
                            }
                        }
                    } else if (is_string($_v)) {
                        $post[$key] = trim($val);
                    }
                }

                $conf_wechat = tpSetting("OpenMinicode.conf_wechat", [], $this->main_lang);
                $conf_wechat = json_decode($conf_wechat, true);
                empty($conf_wechat) && $conf_wechat = [];
                $appid = !empty($conf_wechat['appid']) ? $conf_wechat['appid'] : '';
                $appsecret = !empty($conf_wechat['appsecret']) ? $conf_wechat['appsecret'] : '';

                // 同步到微信公众号配置
                $conf_wechat['wechat_name'] = $post['login']['wechat_name'];
                $conf_wechat['wechat_pic'] = $post['login']['wechat_pic'];
                tpSetting('OpenMinicode', ['conf_wechat' => json_encode($conf_wechat)], $this->main_lang);

                if (1 == $post['shop']['shop_micro']) {
                    if (empty($appid) || empty($appsecret)) {
                        $post['shop']['shop_micro'] = 0;
                    }
                }
                if (1 == $post['shop']['shop_force_use_wechat']) {
                    if (empty($appid) || empty($appsecret)) {
                        $post['shop']['shop_force_use_wechat'] = 0;
                    } else {
                        $post['shop']['shop_micro'] = 1;
                    }
                }

                // 微信登录配置处理
                $login_config = [
                    'appid' => $appid,
                    'appsecret' => $appsecret,
                ];
                $login_config = array_merge($login_config, $post['login']);
                $post['wechat']['wechat_login_config'] = serialize($login_config);
                unset($post['login']);

                foreach ($post as $key => $val) {
                    is_array($val) && getUsersConfigData($key, $val);
                }

                $this->success('操作成功', url('System/microsite'));
            }
        }

        /*微站点配置*/
        $login = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
        $this->assign('login', $login);
        /* END */

        return $this->fetch();
    }
    
    
    /**
     * 邮件模板列表 - 编辑
     */
    public function smtp_tpl_edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['tpl_id'] = eyIntval($post['tpl_id']);
            if(!empty($post['tpl_id'])){
                $post['tpl_title'] = trim($post['tpl_title']);

                /*组装存储数据*/
                $nowData = array(
                    'update_time'   => getTime(),
                );
                $saveData = array_merge($post, $nowData);
                /*--end*/
                
                $r = Db::name('smtp_tpl')->where([
                        'tpl_id'    => $post['tpl_id'],
                        'lang'      => $this->admin_lang,
                    ])->update($saveData);
                if ($r) {
                    $tpl_name = Db::name('smtp_tpl')->where([
                            'tpl_id'    => $post['tpl_id'],
                            'lang'      => $this->admin_lang,
                        ])->getField('tpl_name');
                    adminLog('编辑邮件模板：'.$tpl_name); // 写入操作日志
                    $this->success("操作成功", url('System/smtp_tpl'));
                }
            }
            $this->error("操作失败");
        }

        $id = input('id/d', 0);
        $row = Db::name('smtp_tpl')->where([
                'tpl_id'    => $id,
                'lang'      => $this->admin_lang,
            ])->find();
        if (empty($row)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }

        $this->assign('row',$row);
        return $this->fetch();
    }

    /**
     * 阿里云OSS配置
     */
    public function oss()
    {
        $inc_type =  'oss';
        if (IS_POST) {
            $param = input('post.');
            
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache($inc_type, $param, $val['mark']);
                }
            } else {
                tpCache($inc_type, $param);
            }
            /*--end*/
            $this->success('操作成功');
        }
    }

    /**
     * 清空缓存
     */
    public function clear_cache()
    {
        if (IS_POST) {
            if (!function_exists('unlink')) {
                $this->error('php.ini未开启unlink函数，请联系空间商处理！');
            }

            $post = input('post.');
            if (!empty($post['clearAll'])) {
                $this->clearSystemCache(1);
            } else {
                if (!empty($post['clearTable'])) {
                    if (in_array('table', $post['clearTable'])) {
                        $this->clearSystemCache(3);
                    }
                }
                if (!empty($post['clearHtml'])) {
                    foreach ($post['clearHtml'] as $key => $val) {
                        delFile(HTML_ROOT.$val);
                    }
                }
                if (!empty($post['clearCache'])) {
                    foreach ($post['clearCache'] as $key => $val) {
                        if ($val == 'cache') {
                            $this->clearSystemCache(0);
                        } else {
                            delFile(RUNTIME_PATH.$val);
                        }
                    }
                }
            }

            /*清除旧升级备份包*/
            $backupArr = glob(DATA_PATH.'backup/*');
            foreach ($backupArr as $key => $filepath) {
                if (file_exists($filepath) && !stristr($filepath, '.htaccess')) {
                    if (is_dir($filepath)) {
                        delFile($filepath, true);
                    } else if (is_file($filepath)) {
                        @unlink($filepath);
                    }
                }
            }
            /*--end*/
            //重新统计栏目文档
            //如果 ey_archives 主表的数据大于20万，就不执行统计
            $arc_count = Db::name('archives')->count('aid');
            if ($arc_count < 200000){
                model('Arctype')->hand_type_count();
            }

            $request = Request::instance();
            $gourl = $request->baseFile();
            $lang = $request->param('lang/s');
            if (!empty($lang) && $lang != get_main_lang()) {
                $gourl .= "?lang={$lang}";
            }
            
            $this->success('操作成功', $gourl, '', 1, [], '_parent');
        }
        
        // 页面目录
        $pageDirList = [
            [
                'dir_title' => '首页',
                'dir_value' => 'index',
                'dir_type' => 'system',
                'sort_order' => $this->get_page_sort_order('index'),
            ],
            [
                'dir_title' => '列表',
                'dir_value' => 'lists',
                'dir_type' => 'system',
                'sort_order' => $this->get_page_sort_order('lists'),
            ],
            [
                'dir_title' => '内容',
                'dir_value' => 'view',
                'dir_type' => 'system',
                'sort_order' => $this->get_page_sort_order('view'),
            ],
            [
                'dir_title' => '标签',
                'dir_value' => 'tags',
                'dir_type' => 'system',
                'sort_order' => $this->get_page_sort_order('tags'),
            ],
        ];
        $directory = HTML_ROOT;
        if (is_dir($directory)) {
            $mydir = dir($directory);
            while ($file = $mydir->read()) {
                if ((is_dir("$directory/$file")) && !in_array($file, ['plugins','.','..','index','lists','view','tags'])) {
                    switch ($file) {
                        default:
                            $dir_title = '其他';
                            break;
                    }
                    $pageDirList[] = [
                        'dir_title' => $dir_title,
                        'dir_value' => $file,
                        'dir_type' => 'system',
                        'sort_order'=> $this->get_page_sort_order($file),
                    ];
                }
            }
            $mydir->close();
        }

        // 插件页面目录
        $directory = HTML_ROOT.'plugins';
        if (is_dir($directory)) {
            $mydir = dir($directory);
            while ($file = $mydir->read()) {
                if ((is_dir("$directory/$file")) && !in_array($file, ['.','..'])) {
                    switch ($file) {
                        case 'ask':
                            $dir_title = '问答';
                            break;
                        default:
                            $dir_title = '其他';
                            break;
                    }
                    $pageDirList[] = [
                        'dir_title' => $dir_title,
                        'dir_value' => $file,
                        'dir_type' => 'plugins',
                        'sort_order'=> $this->get_page_sort_order($file),
                    ];
                }
            }
            $mydir->close();
        }
        $sort_orders = get_arr_column($pageDirList, 'sort_order');
        array_multisort($sort_orders, SORT_ASC, $pageDirList);
        $this->assign('pageDirList', $pageDirList);
        
        // 数据目录
        $cacheDirList = [];
        $cache_dir = false;
        $directory = RUNTIME_PATH;
        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if ((is_dir("$directory/$file")) AND ($file != ".") AND ($file != "..") AND ($file != 'html')) {
                if ($file == 'cache') {
                    $cache_dir = true;
                    $dir_title = '数据缓存';
                } else if ($file == 'temp') {
                    $dir_title = 'php编译';
                } else if ($file == 'data') {
                    $dir_title = '项目数据';
                } else if ($file == 'log') {
                    $dir_title = '系统日志';
                } else if (stristr($file, 'lotus')) {
                    $dir_title = '支付日志';
                } else {
                    $dir_title = '其他';
                }
                $cacheDirList[] = [
                    'dir_title' => $dir_title,
                    'dir_value' => $file,
                    'dir_type' => 'system',
                    'sort_order'=> $this->get_file_sort_order($file),
                ];
            }
        }
        $mydir->close();
        if (!$cache_dir) {
            $cacheDirList[] = [
                'dir_title' => '数据缓存',
                'dir_value' => 'cache',
                'dir_type' => 'system',
                'sort_order'=> $this->get_file_sort_order('cache'),
            ];
        }
        $sort_orders = get_arr_column($cacheDirList, 'sort_order');
        array_multisort($sort_orders, SORT_ASC, $cacheDirList);
        $this->assign('cacheDirList', $cacheDirList);

        return $this->fetch();
    }

    private function get_page_sort_order($name)
    {
        if ($name == 'index') {
            $sort_order = 1;
        } else if ($name == 'lists') {
            $sort_order = 2;
        } else if ($name == 'view') {
            $sort_order = 3;
        } else if ($name == 'tags') {
            $sort_order = 4;
        } else if ($name == 'ask') {
            $sort_order = 5;
        } else {
            $sort_order = 100;
        }

        return $sort_order;
    }

    private function get_file_sort_order($name)
    {
        if ($name == 'cache') {
            $sort_order = 1;
        } else if ($name == 'temp') {
            $sort_order = 2;
        } else if (stristr($name, 'lotus')) {
            $sort_order = 3;
        } else {
            $sort_order = 100;
        }

        return $sort_order;
    }

    /**
     * 清空数据缓存
     */
    private function clearSystemCache($clearall = 0)
    {
        $clearall = input('param.clearall/d', $clearall);
        if ($clearall == 3) { // 数据表文件
            schemaAllTable();
        } else if (1 == $clearall) { // 全部缓存
            Cache::clear();
            delFile(RUNTIME_PATH);
            schemaAllTable();
        } else if (2 == $clearall) { // 页面缓存
            delFile(HTML_ROOT);
        } else { // 数据缓存
            Cache::clear();
        }

        try {
            /*清除大数据缓存表 -- 陈风任*/
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
            /* END */
        } catch (\Exception $e) {}

        if (empty($clearall) || 1 == $clearall) {
            /*多语言*/
            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('global', '', $val['mark']);
                }
            } else { // 单语言
                tpCache('global');
            }
            /*--end*/ 
        }

        return true;
    }

    /**
     * 清空页面缓存
     */
    public function clearHtmlCache($arr = array())
    {
        if (empty($arr)) {
            delFile(HTML_ROOT);
        } else {
            $seo_pseudo = tpCache('seo.seo_pseudo');
            foreach ($arr as $key => $val) {
                $fileList = glob(HTML_ROOT.'http*/'.$val.'*');
                if (!empty($fileList)) {
                    foreach ($fileList as $k2 => $v2) {
                        if (file_exists($v2) && is_dir($v2)) {
                            delFile($v2, true);
                        } else if (file_exists($v2) && is_file($v2)) {
                            @unlink($v2);
                        }
                    }
                }
                if ($val == 'index' && 2 != $seo_pseudo) {
                    foreach (['index.html','indexs.html'] as $sk1 => $sv1) {
                        $filename = ROOT_PATH.$sv1;
                        if (file_exists($filename)) {
                            @unlink($filename);
                        }
                    }
                }
            }
        }
    }
      
    /**
     * 发送测试邮件
     */
    public function send_email()
    {
        $param = $smtp_config = input('post.');
        // 收件人邮箱
        $smtp_from_eamil = str_replace('，', ',', trim($param['smtp_from_eamil']));
        if (!empty($smtp_from_eamil)) {
            $test_email_arr = explode(',', $smtp_from_eamil);
            foreach ($test_email_arr as $key => $val) {
                $val = trim($val);
                if (!check_email($val)) {
                    unset($test_email_arr[$key]);
                }
            }
            $smtp_from_eamil = implode(',', $test_email_arr);
        }
        if (empty($smtp_from_eamil)) {
            $this->error('管理员邮箱格式不正确');
        }

        $title = '演示标题';
        $content = '演示一串随机数字：' . mt_rand(1000,9999);
        $res = send_email($smtp_from_eamil, $title, $content, 0, $smtp_config);
        if (intval($res['code']) == 1) {
            unset($smtp_config['tpl_id']);
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('smtp', $smtp_config, $val['mark']);
                }
            } else {
                tpCache('smtp',$smtp_config);
            }
            /*--end*/
            $this->success($res['msg']);
        } else {
            $this->error($res['msg']);
        }
    }
      
    /**
     * 发送测试短信
     */
    public function send_mobile()
    {
        $param = $sms_config = input('post.');
        if (!isset($param['sms_type']) || empty($param['sms_type'])) $param['sms_type'] = 1;
        if ($param['sms_type'] == 1) {
            unset($sms_config['sms_appkey_tx']);
            unset($sms_config['sms_appid_tx']);
            unset($sms_config['sms_test_mobile']);
        }else{
            unset($sms_config['sms_appkey']);
            unset($sms_config['sms_secretkey']);
            unset($sms_config['sms_test_mobile']);
        }

        $send_scene = Db::name('sms_template')->where(['sms_tpl_code'=>['NEQ', ''],'sms_type'=>$param['sms_type'],'is_open'=>1])->order('tpl_id asc')->value("send_scene");
        $send_scene = intval($send_scene);
        $res = sendSms($send_scene, $param['sms_test_mobile'], array('content'=>mt_rand(1000,9999)),0,$sms_config);
        if (intval($res['status']) == 1) {
            unset($sms_config['tpl_id']);
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('sms', $sms_config, $val['mark']);
                }
            } else {
                unset($sms_config['sms_type']);
                tpCache('sms', $sms_config);
            }
            /*--end*/
            $this->success($res['msg']);
        } else {
            $this->error($res['msg']);
        }
    }
    //自定义分组列表
    public function config_type(){
        $list = array();
        $condition = array();
//        $keywords = input('keywords/s');
//        if (!empty($keywords)) {
//            $condition['type_name'] = array('LIKE', "%{$keywords}%");
//        }
        $condition['lang'] = array('eq', $this->admin_lang);

        $count = Db::name('config_type')->where($condition)->count('id');// 查询满足要求的总记录数
        $Page = $pager = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('config_type')->where($condition)->order('sort_order asc, id asc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $show = $Page->show();// 分页显示输出
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页对象
        return $this->fetch();
    }
    //修改(新增)分组
    public function config_type_save(){
        if (IS_AJAX_POST) {
            $post = input('post.');

            if (empty($post['type_name'])) {
                $this->error('至少新增一个链接分组！');
            } else {
                $is_empty = true;
                foreach ($post['type_name'] as $key => $val) {
                    $val = trim($val);
                    if (!empty($val)) {
                        $is_empty = false;
                        break;
                    }
                }
                if (true === $is_empty) {
                    $this->error('分组名称不能为空！');
                }
            }

            // 数据拼装
            $now_time = getTime();
            $addData = $editData = [];
            foreach ($post['type_name'] as $key => $val) {
                $group_name  = trim($val);
                if (!empty($group_name)) {
                    if (empty($post['id'][$key])) {
                        if ($this->admin_lang == $this->main_lang) {
                            $addData[] = [
                                'type_name' => $group_name,
                                'sort_order' => $post['sort_order'][$key] ? :100,
                                'lang' => $this->admin_lang,
                                'add_time' => $now_time,
                                'update_time' => $now_time,
                            ];
                        }
                    } else {
                        $id = intval($post['id'][$key]);
                        $editData[] = [
                            'id' => $id,
                            'type_name' => $group_name,
                            'sort_order' => $post['sort_order'][$key] ? :100,
                            'lang' => $this->admin_lang,
                            'update_time' => $now_time,
                        ];
                    }
                }
            }
            if (!empty($addData)) {
                $rdata = model('ConfigType')->saveAll($addData);
            }
            $r = true;
            if (!empty($editData)) {
                $r = model('ConfigType')->saveAll($editData);
            }

            if ($r !== false) {
                adminLog('保存自定义变量分组：'.implode(',', $post['type_name']));
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }
    //删除分组
    public function config_type_del(){
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            $group_name_list = Db::name('config_type')->where([
                'id'    => ['IN', $id_arr],
            ])->column('type_name');

            $r = Db::name('config_type')->where([
                'id'    => ['IN', $id_arr],
            ])->delete();
            if($r !== false){
                $attr_var_name = Db::name('config_attribute')->where([
                    'type_id'    => ['IN', $id_arr],
                ])->getField('attr_var_name',true);
                if (!empty($attr_var_name)){
                     Db::name('config')->where(['name'=>['in',$attr_var_name]])->delete();
                    Db::name('config_attribute')->where(['attr_var_name'=>['in',$attr_var_name]])->delete();
                }
                adminLog('删除自定义分组：'.implode(',', $group_name_list));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }
    //自定义变量列表
    public function customvar_index()
    {
        $list = array();
        $keywords = input('keywords/s');
        $type_id = input('type_id/d',0);
        $condition = array();
        if (!empty($keywords)) {
            $condition['a.attr_name'] = array('LIKE', "%{$keywords}%");
        }
        if (!empty($type_id)){
            $condition['a.type_id'] = $type_id;
        }
        $condition['a.lang'] = array('eq', $this->admin_lang);
        $attr_var_names = Db::name('config')->field('name')
            ->where([
                'name'  => ['LIKE', "web_attr_%"],
                'lang'  => $this->admin_lang,
                'is_del'    => 0,
            ])->getAllWithIndex('name');
        $condition['a.attr_var_name'] = array('IN', array_keys($attr_var_names));
        $count = Db::name('config_attribute')->alias('a')->where($condition)->count();
        $pageObj = new Page($count, config('paginate.list_rows'));
        $list = Db::name('config_attribute')->alias('a')
            ->field('a.*, b.id, b.value')
            ->join('__CONFIG__ b', 'b.name = a.attr_var_name AND b.lang = a.lang', 'LEFT')
            ->where($condition)
            ->where([
                'b.is_del'  => 0,
            ])
            ->order('a.attr_id asc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        $config_type = Db::name('config_type')->field('id, type_name')->order('sort_order asc')->getAllWithIndex('id');
        $this->assign('config_type',$config_type);  //分组列表
        foreach ($list as $key => $val) {
            if (3 == $val['attr_input_type']) {
                $val['value'] = handle_subdir_pic($val['value']);
            }
            $val['type_name'] = !empty($config_type[$val['type_id']]['type_name']) ? $config_type[$val['type_id']]['type_name'] : '';
            $val['attr_var_title'] = str_replace('web_attr_', 'web_attrname_', $val['attr_var_name']);
            $list[$key] = $val;
        }
        $attr_input_type = [
            '0' => '单行文本',
            '2' => '多行文本',
            '3' => '上传图片',
            '4' => '开关类型',
            '5' => '多媒体类型',
        ];
        $this->assign('attr_input_type',$attr_input_type);  //类型列表
        $pageStr = $pageObj->show();
        $this->assign('page',$pageStr);
        $this->assign('list',$list);
        $this->assign('pager',$pageObj);

        $upload_conf = [];
        $upload_conf['upload_flag'] = 'local';
        $WeappConfig = Db::name('weapp')->field('code, status')->where('code', 'IN', ['Qiniuyun', 'AliyunOss', 'Cos', 'AwsOss'])->where('status',1)->select();
        foreach ($WeappConfig as $value) {
            if ('Qiniuyun' == $value['code']) {
                $upload_conf['upload_flag'] = 'qny';
            } else if ('AliyunOss' == $value['code']) {
                $upload_conf['upload_flag'] = 'oss';
            } else if ('Cos' == $value['code']) {
                $upload_conf['upload_flag'] = 'cos';
            } else if ('AwsOss' == $value['code']) {
                $upload_conf['upload_flag'] = 'aws';
            }
        }
        $ext = tpCache('basic.media_type');
        $upload_conf['ext'] = !empty($ext) ? $ext : config('global.media_ext');
        $upload_conf['filesize'] = upload_max_filesize();
        $this->assign('upload_conf',$upload_conf);

        return $this->fetch();
    }
    /*
     * 保存单个自定义变量
     */
    public function customvar_save_one(){
        if (IS_AJAX_POST){
            $post = input('post.');
            if(isset($post['attr_id']) && isset($post['attr_name']) && isset($post['attr_input_type']) && isset($post['attr_var_name']) && isset($post['attr_value']) && isset($post['type_id'])){
                $a = Db::name("config_attribute")->where([
                        'attr_id'=>$post['attr_id']
                    ])->update([
                        'type_id'=>$post['type_id'],
                        'attr_name'=>$post['attr_name'],
                        'attr_input_type'=>$post['attr_input_type'],
                        'update_time'=>getTime(),
                    ]);
                $c = tpCache('web', [$post['attr_var_name']=>$post['attr_value']]);
                if($a !== false && $c !== false){
                    if($post['attr_input_type'] == 4){
                        $post['attr_value'] = $post['attr_value'] == 1 ? '开启' : '关闭';
                    }else if($post['attr_input_type'] == 3){
                        $img_url = get_default_pic($post['attr_value']);
                        $post['attr_value'] = '<img src="'.$img_url.'" height="50">';
                    }else if($post['attr_input_type'] == 5){
                        $media_url = handle_subdir_pic($post['attr_value'], 'media');
                        $post['attr_value'] = $media_url;
                    }
                    $this->success("修改成功",null,['attr_value'=> $post['attr_value'] ]);
                }
            }
        }
        $this->error('操作错误！');
    }
    /**
     * 保存自定义变量
     */
    public function customvar_save()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['attr_name'])) {
                $this->error('至少新增一个自定义变量！');
            }

            // 数据拼装
            $addData = $editData = [];
            foreach ($post['attr_name'] as $key => $val) {
                $attr_name  = trim($val);
                if (!empty($attr_name)) {
                    $attr_input_type = intval($post['attr_input_type'][$key]);
                    $attr_value = isset($post['attr_value'][$key]) ? $post['attr_value'][$key] : '';
                    $attr_var_name = isset($post['attr_var_name'][$key]) ? $post['attr_var_name'][$key] : '';
                    $type_id = isset($post['attr_type_id'][$key]) ? $post['attr_type_id'][$key] : '';
                    if (empty($post['attr_id'][$key])) {
                        if ($this->admin_lang == $this->main_lang) {
                            $addData[] = [
                                'inc_type' => 'web',
                                'attr_name' => $attr_name,
                                'attr_input_type' => $attr_input_type,
                                'attr_value' => $attr_value,
                                'type_id' => $type_id,
                                'lang' => $this->admin_lang,
                                'add_time' => getTime(),
                                'update_time' => getTime(),
                            ];
                        }
                    } else {
                        $attr_id = intval($post['attr_id'][$key]);
                        $editData[] = [
                            'attr_id' => $attr_id,
                            'inc_type' => 'web',
                            'attr_name' => $attr_name,
                            'attr_var_name' => $attr_var_name,
                            'attr_input_type' => $attr_input_type,
                            'attr_value' => $attr_value,
                            'type_id' => $type_id,
                            'lang' => $this->admin_lang,
                            'update_time' => getTime(),
                        ];
                    }
                }
            }

            /*多语言*/
            $langRow = [];
            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
            }
            /*end*/
            
            if (!empty($addData)) {
                $rdata = model('ConfigAttribute')->saveAll($addData);
                if ($rdata !== false) {
                    foreach ($rdata as $k1 => $v1) {
                        $attr_id = $v1->getData('attr_id');
                        $addData[$k1]['attr_id'] = $attr_id;
                        $addData[$k1]['attr_var_name'] = 'web_attr_'.$attr_id;
                        $addData[$k1]['update_time'] = getTime();
                        unset($addData[$k1]['add_time']);
                    }
                    $editData = array_merge($editData, $addData);
                }

                /*多语言*/
                if (is_language()) {
                    $langAddData = [];
                    foreach ($langRow as $key => $val) {
                        if ($this->admin_lang == $val['mark']) {
                            continue;
                        }

                        foreach ($rdata as $k1 => $v1) {
                            $attr_data = $v1->getData();
                            $attr_data['lang'] = $val['mark'];
                            $attr_data['attr_var_name'] = 'web_attr_'.$attr_data['attr_id'];
                            unset($attr_data['attr_id']);
                            $langAddData[] = $attr_data;
                        }
                    }
                    !empty($langAddData) && model('ConfigAttribute')->saveAll($langAddData);
                }
                /*end*/
            }

            if (!empty($editData)) {
                $r = model('ConfigAttribute')->saveAll($editData);
                if ($r !== false) {
                    // 新增到config表，更新缓存
                    if (!empty($addData) && $this->admin_lang == $this->main_lang) {
                        $configData = [];
                        foreach ($addData as $key => $val) {
                            $configData['web_attr_'.$val['attr_id']] = isset($val['attr_value']) ? $val['attr_value'] : '';
                        }
                        // 多语言
                        if (is_language()) {
                            foreach ($langRow as $key => $val) {
                                tpCache('web', $configData, $val['mark']);
                            }
                        } else { // 单语言
                            tpCache('web', $configData);
                        }
                    }

                    // 更新到config表，更新缓存
                    $configData = [];
                    foreach ($editData as $key => $val) {
                        $configData[$val['attr_var_name']] = isset($val['attr_value']) ? $val['attr_value'] : '';
                    }
                    tpCache('web', $configData);
                    // end

                    adminLog('保存自定义变量：'.implode(',', $post['attr_name']));
                    $this->success('操作成功', url('System/customvar_index'));
                } else {
                    $this->error('操作失败');
                }
            } 
        }
        $this->error('非法访问！');
    }

    /**
     * 删除自定义变量
     */
    public function customvar_del()
    {
        $this->language_access(); // 多语言功能操作权限

        $id = input('del_id/d');
        $deltype = input('deltype/s');
        if(!empty($id)){
            $attr_var_name = Db::name('config')->where([
                    'id'    => $id,
                    'lang'  => $this->admin_lang,
                ])->getField('name');
            if ('del' == $deltype){//彻底删除
                $r = Db::name('config')->where('name', $attr_var_name)->delete();
            }else{
                $r = Db::name('config')->where('name', $attr_var_name)->update(array('is_del'=>1, 'update_time'=>getTime()));
            }
            if($r !== false){
                if ('del' == $deltype){
                    Db::name('config_attribute')->where('attr_var_name', $attr_var_name)->delete();
                }else{
                    Db::name('config_attribute')->where('attr_var_name', $attr_var_name)->update(array('update_time'=>getTime()));
                }
                adminLog('删除自定义变量：'.$attr_var_name);
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    /**
     * 标签调用的弹窗说明
     */
    public function ajax_tag_call()
    {
        $space = "&nbsp;&nbsp;&nbsp;&nbsp;";
        if (IS_AJAX_POST) {
            $name = input('post.name/s');
            $msg = '';
            switch ($name) {
                case 'web_users_switch': // 会员功能入口标签
                    {
$msg_code = <<<EOF
{eyou:user type='open'}  <br>
{$space}{eyou:user type='cart'} <br>
{$space}{$space}&lt;a href="{\$field.url}" id="{\$field.id}" &gt;购物车&lt;/a&gt; <br>
{$space}{$space}{\$field.hidden} <br>
{$space}{/eyou:user} <br>
     <br>
{$space}{eyou:user type='login'} <br>
{$space}{$space}&lt;a href="{\$field.url}" id="{\$field.id}" &gt;登录&lt;/a&gt; <br>
{$space}{$space}{\$field.hidden} <br>
{$space}{/eyou:user} <br>
     <br>
{$space}{eyou:user type='reg'} <br>
{$space}{$space}&lt;a href="{\$field.url}" id="{\$field.id}" &gt;注册&lt;/a&gt; <br>
{$space}{$space}{\$field.hidden} <br>
{$space}{/eyou:user} <br>
     <br>
{$space}{eyou:user type='logout'} <br>
{$space}{$space}&lt;a href="{\$field.url}" id="{\$field.id}" &gt;退出&lt;/a&gt; <br>
{$space}{$space}{\$field.hidden} <br>
{$space}{/eyou:user}   <br>
{/eyou:user}
EOF;

$tpl_theme = TPL_THEME;
$msg = <<<EOF
<strong>前台会员登录注册标签调用</strong><br>
比如需要在PC通用头部加入会员入口，复制下方代码在/template/{$tpl_theme}pc/header.htm模板文件里找到合适位置粘贴
<br/><br/>
<div style="color:red">
{$msg_code}
</div>
EOF;
                    }
                    break;

                case 'web_language_switch': // 多语言入口标签
                    {
$tpl_theme = TPL_THEME;
$msg = <<<EOF
<strong>前台多语言切换入口标签调用</strong><br>
比如需要在PC通用头部加入多语言切换，复制下方代码在/template/{$tpl_theme}pc/header.htm模板文件里找到合适位置粘贴
<br/><br/>
<div style="color:red">
{eyou:language type='default'}<br/>
{$space}&lt;a href="{\$field.url}"&gt;&lt;img src="{\$field.logo}" alt="{\$field.title}"&gt;{\$field.title}&lt;/a&gt;<br/>
{/eyou:language}
</div>
EOF;
                    }
                    break;

                case 'thumb_open':
                    {
$msg = <<<EOF
<span style="color:red">温馨提示：高级调用不会受缩略图功能的开关影响！</span><br/>
【标签方法的格式】<br/>
{$space}thumb_img=###,宽度,高度,生成方式<br/>
<br/>
【指定宽高度的调用】<br/>
{$space}列表页/内容页：{\$eyou.field.litpic|thumb_img=###,500,500<br/>
{$space}标签arclist/list里：{\$field.litpic|thumb_img=###,500,500<br/>
<br/>
【指定生成方式的调用】<br/>
{$space}生成方式：1 = 拉伸；2 = 留白；3 = 截减；<br/>
{$space}以标签arclist为例：<br/>
{$space}缩略图拉伸：{\$field.litpic|thumb_img=###,500,500,1}<br/>
{$space}缩略图留白：{\$field.litpic|thumb_img=###,500,500,2}<br/>
{$space}缩略图截减：{\$field.litpic|thumb_img=###,500,500,3}<br/>
{$space}默&nbsp;认&nbsp;生&nbsp;成：{\$field.litpic|thumb_img=###,500,500}{$space}(以默认全局配置的生成方式)<br/>
EOF;
                    }
                    break;
                
                case 'shop_open':
                    {
$msg_code = <<<EOF
&lt;!--购物车组件start--&gt; <br/>
{eyou:sppurchase id='field' currentstyle='btn-danger'} <br/>
{$space}&lt;!-- 价格 标签开始 --&gt;  <br/>
{$space}&lt;div class="ey-price"&gt;&lt;span&gt;￥{\$field.users_price}&lt;/span&gt; &lt;/div&gt;  <br/>
{$space}&lt;!-- 价格 标签结束 --&gt;  <br/>
     <br/>
{$space}&lt;!-- 规格 标签开始 --&gt; <br/>
{$space}&lt;div class="ey-spec"&gt; <br/>
{$space}{eyou:volist name="\$field.ReturnData" id='field2'} <br/>
{$space}{$space}&lt;div class="row m-t-15"&gt; <br/>
{$space}{$space}{$space}&lt;label class="form-control-label col-sm-7"&gt;{\$field2.spec_name}&lt;/label&gt; <br/>
{$space}{$space}{$space}&lt;div class="col-sm-10"&gt; <br/>
{$space}{$space}{$space}{eyou:volist name="\$field2.spec_value" id='field3'} <br/>
{$space}{$space}{$space}{$space}&lt;a href="JavaScript:void(0);" {\$field3.SpecData} class="btn btn-default btn-selected {\$field3.SpecClass}"&gt;{\$field3.spec_value}&lt;/a&gt; <br/>
{$space}{$space}{$space}{/eyou:volist} <br/>
{$space}{$space}{$space}&lt;/div&gt; <br/>
{$space}{$space}&lt;/div&gt; <br/>
{$space}{/eyou:volist} <br/>
{$space}&lt;/div&gt; <br/>
{$space}&lt;!-- 规格 标签结束 --&gt; <br/>
     <br/>
{$space}&lt;!-- 数量操作 标签开始 --&gt;  <br/>
{$space}&lt;div class="ey-number"&gt;  <br/>
{$space}{$space}&lt;label&gt;数量&lt;/label&gt;  <br/>
{$space}{$space}&lt;div class="btn-input"&gt;  <br/>
{$space}{$space}{$space}&lt;button class="layui-btn" {\$field.ReduceQuantity}&gt;-&lt;/button&gt;  <br/>
{$space}{$space}{$space}&lt;input type="text" class="layui-input" {\$field.UpdateQuantity}&gt;  <br/>
{$space}{$space}{$space}&lt;button class="layui-btn" {\$field.IncreaseQuantity}&gt;+&lt;/button&gt;  <br/>
{$space}{$space}&lt;/div&gt;  <br/>
{$space}&lt;/div&gt;  <br/>
{$space}&lt;!-- 数量操作 标签结束 --&gt;  <br/>
     <br/>
{$space}&lt;!-- 库存量 标签开始 --&gt;  <br/>
{$space}&lt;span {\$field.stock_show}&gt;库存量：{\$field.stock_count} 件&lt;/span&gt;  <br/>
{$space}&lt;!-- 库存量 标签结束 --&gt;  <br/>
     <br/>
{$space}&lt;!-- 购买按钮 标签开始 --&gt;  <br/>
{$space}&lt;div class="ey-buyaction"&gt;  <br/>
{$space}{$space}&lt;a class="ey-joinin" href="JavaScript:void(0);" {\$field.ShopAddCart}&gt;加入购物车&lt;/a&gt;  <br/>
{$space}{$space}&lt;a class="ey-joinbuy" href="JavaScript:void(0);" {\$field.BuyNow}&gt;立即购买&lt;/a&gt; <br/>
{$space}&lt;/div&gt;  <br/>
{$space}&lt;!-- 购买按钮 标签结束 --&gt;  <br/>
     <br/>
{$space}{\$field.hidden}  <br/>
{/eyou:sppurchase}  <br/>
&lt;!--购物车组件end--&gt;
EOF;

$tpl_theme = TPL_THEME;
$msg = <<<EOF
<div style="color:red"> 
请手工调用最新版的购买行为入口标签，代码验证通过便可启用
<br/>
复制下方代码在/template/{$tpl_theme}pc/view_product.htm模板文件里找到合适位置粘贴
</div>
<br/>
<div id='ShopOpenCode'>
{$msg_code}
</div>
EOF;
                    }
                    break;

                default:
                    # code...
                    break;
            }
            $this->success('请求成功', null, ['msg'=>$msg]);
        }
        $this->error('非法访问！');
    }

    /**
     * 手机面板
     * @return [type] [description]
     */
    public function web_m()
    {
        return $this->fetch();
    }

    public function ajax_check_language_open()
    {
        if (IS_AJAX) {
            $web_language_switch = tpCache('web.web_language_switch');
            if (!empty($web_language_switch)) {
                $this->error('已开启多语言');
            } else {
                $this->success('未开启多语言');
            }
        }
    }

    public function neverAgainPrompt()
    {
        if (IS_AJAX_POST) {
            $neverAgainPrompt = input('param.neverAgainPrompt/d', 0);
            tpSetting('system', ['system_never_again_prompt' => $neverAgainPrompt]);
        }
    }
}