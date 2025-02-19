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

// 关闭所有PHP错误报告
use think\Db;

error_reporting(0);

include_once EXTEND_PATH."function.php";

// 应用公共文件

if (!function_exists('switch_exception')) 
{
    // 模板错误提示
    function switch_exception() {
        $web_exception = tpCache('web.web_exception');
        if (!empty($web_exception)) {
            config('ey_config.web_exception', $web_exception);
            error_reporting(-1);
        }
    }
}

if (!function_exists('is_adminlogin')) 
{
    /**
     * 检验后台登陆
     * @param
     * @return bool
     */
    function is_adminlogin(){
        $admin_id = session('admin_id');
        if(isset($admin_id) && $admin_id > 0){
            return $admin_id;
        }else{
            return false;
        }
    }
}

if (!function_exists('adminLog')) 
{
    /**
     * 管理员操作记录
     * @param $log_url 操作URL
     * @param $log_info 记录信息
     */
    function adminLog($log_info = ''){
        // 只保留最近一个月的操作日志
        try {
            $ajaxLogic = new \app\admin\logic\AjaxLogic;
            $ajaxLogic->del_adminlog();
            
            $admin_id = session('admin_id');
            $admin_id = !empty($admin_id) ? $admin_id : -1;
            if ($admin_id > 0) {
                $add['log_time'] = getTime();
                $add['admin_id'] = $admin_id;
                $add['log_info'] = htmlspecialchars($log_info);
                $add['log_ip'] = clientIP();
                $add['log_url'] = request()->baseUrl() ;
                M('admin_log')->add($add);
            }
        } catch (\Exception $e) {
            
        }
    }
}

if (!function_exists('adminlogin_lock_clear')) 
{
    /**
     * 清理管理员登录锁定的无效数据
     * @param  string $clear_type [description]
     * @return [type]             [description]
     */
    function adminlogin_lock_clear($clear_type = 'default')
    {
        try {
            $name_arr = [];
            $list = \think\Db::name('admin')->field('user_name,last_ip')->where(['status'=>1])->select();
            foreach ($list as $key => $val) {
                $name_arr[] = 'adminlogin_'.md5('login_lock_'.$val['user_name'].$val['last_ip']);
            }
            $web_login_errexpire = (int)tpCache('web.web_login_errexpire');
            $web_login_errexpire = $web_login_errexpire > 300 ? 300 : $web_login_errexpire;
            $where = [
                'inc_type'=>'adminlogin',
                'name'=>['NOTIN', $name_arr],
                'update_time'=>['lt', getTime() - $web_login_errexpire],
            ];
            if ('all' == $clear_type) {
                unset($where['update_time']);
            }
            $r = \think\Db::name('setting')->where($where)->delete();
            if ($r !== false) {
                if (config('database.type') != 'dm') { // 达梦优化
                    \think\Db::query("REPAIR TABLE " . config('database.prefix') . "setting");
                    \think\Db::query("ALTER TABLE " . config('database.prefix') . " AUTO_INCREMENT 1");
                }
            }
        } catch (\Exception $e) {
            
        }
    }
}

if (!function_exists('login_third_type')) 
{
    /**
     * 识别当前使用的扫码登录功能：1、扫微信应用登录，2、扫官方微信公众号登录
     * @param
     * @return array
     */
    function login_third_type()
    {
        $redata = [
            'type'  => '',
            'data'  => [],
        ];

        if (is_dir('./weapp/EyouGzhLogin/')) { // 是否安装【后台扫码登录】插件
            $EyouGzhLoginRow = model('Weapp')->getWeappList('EyouGzhLogin');
            $status = !empty($EyouGzhLoginRow['status']) ? intval($EyouGzhLoginRow['status']) : 0;
            $data = !empty($EyouGzhLoginRow['data']) ? $EyouGzhLoginRow['data'] : [];
            if (1 == $status && !empty($data['is_open'])) {
                $redata['type'] = $data['mode'];
                if ('EyouGzhLogin' == $data['mode']) {
                    $data['gzh']['switch'] = 1;
                    $redata['data'] = $data['gzh'];
                }
                else if ('userGzhLogin' == $data['mode']) {
                    $data['usergzh']['switch'] = 1;
                    $redata['data'] = $data['usergzh'];
                }
                else if ('WechatLogin' == $data['mode']) {
                    $security = tpSetting('security');
                    $redata['data'] = $security;
                }
            }
        }

        return $redata;
    }
}

if (!function_exists('tpCache')) 
{
    /**
     * 获取缓存或者更新缓存，只适用于config表
     * @param string $config_key 缓存文件名称
     * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
     * @param array $options 缓存配置
     * @param string $lang 语言标识
     * @return array or string or bool
     */
    function tpCache($config_key,$data = array(), $lang = '', $options = null){
        $tableName = 'config';
        $table_db = \think\Db::name($tableName);

        $lang = !empty($lang) ? $lang : get_current_lang();
        $param = explode('.', $config_key);
        $cache_inc_type = "{$tableName}-{$lang}-{$param[0]}";
        if (empty($options['path'])) {
            $options['path'] = DATA_PATH.'runtime'.DS.'cache'.DS.$lang.DS;
        }
        if(empty($data)){
            //如$config_key=shop_info则获取网站信息数组
            //如$config_key=shop_info.logo则获取网站logo字符串
            $config = cache($cache_inc_type,'',$options);//直接获取缓存文件
            if(empty($config)){
                //缓存文件不存在就读取数据库
                if ($param[0] == 'global') {
                    $param[0] = 'global';
                    $res = $table_db->where([
                        'lang'  => $lang,
                        'is_del'    => 0,
                    ])->select();
                } else {
                    $res = $table_db->where([
                        'inc_type'  => $param[0],
                        'lang'  => $lang,
                        'is_del'    => 0,
                    ])->select();
                }
                if($res){
                    foreach($res as $k=>$val){
                        $config[$val['name']] = $val['value'];
                    }
                    cache($cache_inc_type,$config,$options);
                }
                // write_global_params($lang, $options);
            }
            if(!empty($param) && count($param)>1){
                $newKey = strtolower($param[1]);
                return isset($config[$newKey]) ? $config[$newKey] : '';
            }else{
                return $config;
            }
        }else{
            //更新缓存
            $result =  $table_db->where([
                'inc_type'  => $param[0],
                'lang'  => $lang,
                'is_del'    => 0,
            ])->select();
            if($result){
                foreach($result as $val){
                    $temp[$val['name']] = $val['value'];
                }
                $add_data = array();
                foreach ($data as $k=>$v){
                    $newK = strtolower($k);
                    $newArr = array(
                        'name'=>$newK,
                        'value'=>trim($v),
                        'inc_type'=>$param[0],
                        'lang'  => $lang,
                        'update_time'   => getTime(),
                    );
                    if(!isset($temp[$newK])){
                        array_push($add_data, $newArr); //新key数据插入数据库
                    }else{
                        if (strval($v) !== strval($temp[$newK])) {
                            $table_db->where([
                                'name'  => $newK,
                                'lang'  => $lang,
                            ])->save($newArr);//缓存key存在且值有变更新此项
                        }
                    }
                }
                if (!empty($add_data)) {
                    $table_db->insertAll($add_data);
                }
                //更新后的数据库记录
                $newRes = $table_db->where([
                    'inc_type'  => $param[0],
                    'lang'  => $lang,
                    'is_del'    => 0,
                ])->select();
                foreach ($newRes as $rs){
                    $newData[$rs['name']] = $rs['value'];
                }
            }else{
                if ($param[0] != 'global') {
                    foreach($data as $k=>$v){
                        $newK = strtolower($k);
                        $newArr[] = array(
                            'name'=>$newK,
                            'value'=>trim($v),
                            'inc_type'=>$param[0],
                            'lang'  => $lang,
                            'update_time'   => getTime(),
                        );
                    }
                    !empty($newArr) && $table_db->insertAll($newArr);
                }
                $newData = $data;
            }

            $result = false;
            $res = $table_db->where([
                'lang'  => $lang,
                'is_del'    => 0,
            ])->select();
            if($res){
                $global = array();
                foreach($res as $k=>$val){
                    $global[$val['name']] = $val['value'];
                }
                $result = cache("{$tableName}-{$lang}-global",$global,$options);
            } 

            if ($param[0] != 'global') {
                $result = cache($cache_inc_type,$newData,$options);
            }
            
            return $result;
        }
    }
}

if (!function_exists('tpSetting')) 
{
    /**
     * 获取缓存或者更新缓存，只适用于setting表
     * @param string $config_key 缓存文件名称
     * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
     * @param array $options 缓存配置
     * @param string $lang 语言标识
     * @return array or string or bool
     */
    function tpSetting($config_key,$data = array(), $lang = '', $options = null){
        $tableName = 'setting';
        $table_db = \think\Db::name($tableName);

        $lang = !empty($lang) ? $lang : get_current_lang();
        $param = explode('.', $config_key);
        $cache_inc_type = "{$tableName}-{$lang}-{$param[0]}";
        if (empty($options['path'])) {
            $options['path'] = DATA_PATH.'runtime'.DS.'cache'.DS.$lang.DS;
        }
        if(empty($data)){
            //如$config_key=shop_info则获取网站信息数组
            //如$config_key=shop_info.logo则获取网站logo字符串
            $config = cache($cache_inc_type,'',$options);//直接获取缓存文件
            if(empty($config)){
                //缓存文件不存在就读取数据库
                if ($param[0] == 'global') {
                    $param[0] = 'global';
                    $res = $table_db->where([
                        'lang'  => $lang,
                    ])->select();
                } else {
                    $res = $table_db->where([
                        'inc_type'  => $param[0],
                        'lang'  => $lang,
                    ])->select();
                }
                if($res){
                    foreach($res as $k=>$val){
                        $config[$val['name']] = $val['value'];
                    }
                    cache($cache_inc_type,$config,$options);
                }
                // write_global_params($lang, $options);
            }
            if(!empty($param) && count($param)>1){
                $newKey = strtolower($param[1]);
                return isset($config[$newKey]) ? $config[$newKey] : '';
            }else{
                return $config;
            }
        }else{
            //更新缓存
            $result =  $table_db->where([
                'inc_type'  => $param[0],
                'lang'  => $lang,
            ])->select();
            if($result){
                foreach($result as $val){
                    $temp[$val['name']] = $val['value'];
                }
                $add_data = array();
                foreach ($data as $k=>$v){
                    $newK = strtolower($k);
                    $newArr = array(
                        'name'=>$newK,
                        'value'=>trim($v),
                        'inc_type'=>$param[0],
                        'lang'  => $lang,
                        'update_time'   => getTime(),
                    );
                    if(!isset($temp[$newK])){
                        array_push($add_data, $newArr); //新key数据插入数据库
                    }else{
                        if (strval($v) !== strval($temp[$newK])) {
                            $table_db->where([
                                'name'  => $newK,
                                'lang'  => $lang,
                            ])->save($newArr);//缓存key存在且值有变更新此项
                        }
                    }
                }
                if (!empty($add_data)) {
                    $table_db->insertAll($add_data);
                }
                //更新后的数据库记录
                $newRes = $table_db->where([
                    'inc_type'  => $param[0],
                    'lang'  => $lang,
                ])->select();
                foreach ($newRes as $rs){
                    $newData[$rs['name']] = $rs['value'];
                }
            }else{
                if ($param[0] != 'global') {
                    foreach($data as $k=>$v){
                        $newK = strtolower($k);
                        $newArr[] = array(
                            'name'=>$newK,
                            'value'=>trim($v),
                            'inc_type'=>$param[0],
                            'lang'  => $lang,
                            'update_time'   => time(),
                        );
                    }
                    $table_db->insertAll($newArr);
                }
                $newData = $data;
            }

            $result = false;
            $res = $table_db->where([
                'lang'  => $lang,
            ])->select();
            if($res){
                $global = array();
                foreach($res as $k=>$val){
                    $global[$val['name']] = $val['value'];
                }
                $result = cache("{$tableName}-{$lang}-global",$global,$options);
            } 

            if ($param[0] != 'global') {
                $result = cache($cache_inc_type,$newData,$options);
            }
            
            return $result;
        }
    }
}

if (!function_exists('write_global_params')) 
{
    /**
     * 写入全局内置参数
     * @return array
     */
    function write_global_params($lang = '', $options = null)
    {
        $inc_type = 'web';
        empty($lang) && $lang = get_admin_lang();
        $webConfigParams = \think\Db::name('config')->where([
            'inc_type'  => $inc_type,
            'lang'  => $lang,
            'is_del'    => 0,
        ])->getAllWithIndex('name');
        $web_basehost = !empty($webConfigParams['web_basehost']) ? $webConfigParams['web_basehost']['value'] : ''; // 网站根网址
        $web_cmspath = !empty($webConfigParams['web_cmspath']) ? $webConfigParams['web_cmspath']['value'] : ''; // EyouCMS安装目录
        /*启用绝对网址，开启此项后附件、栏目连接、arclist内容等都使用http路径*/
        $web_multi_site = !empty($webConfigParams['web_multi_site']) ? $webConfigParams['web_multi_site']['value'] : '';
        if($web_multi_site == 1)
        {
            $web_mainsite = $web_basehost.$web_cmspath;
        }
        else
        {
            $web_mainsite = '';
        }
        /*--end*/
        /*CMS安装目录的网址*/
        $param['web_cmsurl'] = $web_mainsite;
        /*--end*/
        
        $web_tpl_theme = !empty($webConfigParams['web_tpl_theme']) ? $webConfigParams['web_tpl_theme']['value'] : ''; // 网站根网址
        !empty($web_tpl_theme) && $web_tpl_theme = '/'.trim($web_tpl_theme, '/');
        $param['web_templets_dir'] = '/template'.$web_tpl_theme; // 前台模板根目录
        $param['web_templeturl'] = $web_mainsite.$param['web_templets_dir']; // 前台模板根目录的网址
        $param['web_templets_pc'] = $web_mainsite.$param['web_templets_dir'].'/pc'; // 前台PC模板主题
        $param['web_templets_m'] = $web_mainsite.$param['web_templets_dir'].'/mobile'; // 前台手机模板主题
        $param['web_eyoucms'] = str_replace('#', '', '#h#t#t#p#:#/#/#w#w#w#.#e#y#o#u#c#m#s#.#c#o#m#'); // eyou网址

        /*将内置的全局变量(页面上没有入口更改的全局变量)存储到web版块里*/
        foreach ($param as $key => $val) {
            if (preg_match("/^".$inc_type."_(.)+/i", $key) !== 1) {
                $nowKey = strtolower($inc_type.'_'.$key);
                $param[$nowKey] = $val;
            }
        }
        tpCache($inc_type, $param, $lang, $options);
        /*--end*/
    }
}

if (!function_exists('write_html_cache')) 
{
    /**
     * 写入静态页面缓存
     */
    function write_html_cache($html = '', $result = []){
        $html_cache_status = config('HTML_CACHE_STATUS');
        $html_cache_arr = config('HTML_CACHE_ARR');
        if ($html_cache_status && !empty($html_cache_arr) && !empty($html)) {
            // 多站点/多语言
            $home_lang = 'cn';
            $home_site = '';
            $city_switch_on = config('city_switch_on');
            if (!empty($city_switch_on)) {
                $home_site = get_home_site();
            } else {
                $home_lang = get_home_lang();
            }

            $request = \think\Request::instance();
            $param = input('param.');

            /*URL模式是否启动页面缓存（排除admin后台、前台可视化装修、前台筛选）*/
            $uiset = input('param.uiset/s', 'off');
            $uiset = trim($uiset, '/');
            $url_screen_var = config('global.url_screen_var');
            $arcrank = !empty($result['arcrank']) ? intval($result['arcrank']) : 0;
            $admin_id = input('param.admin_id/d');
            if (isset($param[$url_screen_var]) || 'on' == $uiset || 'admin' == $request->module() || -1 == $arcrank || !empty($admin_id)) {
                return false;
            }
            $seo_pseudo = config('ey_config.seo_pseudo');
            if (2 == $seo_pseudo && !isMobile()) { // 排除普通动态模式
                return false;
            }
            /*--end*/

            if (1 == $seo_pseudo) {
                isset($param['tid']) && $param['tid'] = input('param.tid/d');
            } else {
                isset($param['tid']) && $param['tid'] = input('param.tid/s');
            }
            isset($param['page']) && $param['page'] = input('param.page/d');

            // aid唯一性的处理
            if (isset($param['aid'])) {
                if (!preg_match('/^([\w\-]+)$/', $param['aid'])) {
                    abort(404,'页面不存在');
                }
            }

            $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
            $m_c_a_str = strtolower($m_c_a_str);
            //exit('write_html_cache写入缓存<br/>');
            foreach($html_cache_arr as $mca=>$val)
            {
                $mca = strtolower($mca);
                if($mca != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
                    continue;

                if (empty($val['filename'])) {
                    continue;
                }

                $cache_tag = ''; // 缓存标签
                $filename = '';
                // 组合参数  
                if(isset($val['p']))
                {
                    $tid = '';
                    if (in_array('tid', $val['p'])) {
                        $tid = !empty($param['tid']) ? $param['tid'] : '';
                        if (strval(intval($tid)) != strval($tid)) {
                            $where = [
                                'dirname'   => $tid,
                            ];
                            if (empty($city_switch_on)) {
                                $where['lang'] =$home_lang;
                            }
                            $tid = \think\Db::name('arctype')->where($where)->getField('id');
                            $param['tid']   = $tid;
                        }
                    }

                    foreach ($val['p'] as $k=>$v) {
                        if (isset($param[$v])) {
                            if (preg_match('/\/$/i', $filename)) {
                                $filename .= $param[$v];
                            } else {
                                if (!empty($filename) || is_numeric($filename)) {
                                    $filename .= '_';
                                }
                                $filename .= $param[$v];
                            }
                        }
                    }
                    !empty($tid) && $cache_tag = $tid; // 针对列表缓存的标签
                    !empty($param['aid']) && $cache_tag = $param['aid']; // 针对内容缓存的标签
                }
                empty($filename) && $filename = 'index';

                /*子域名（移动端域名）*/
                $is_mobile_domain = false;
                $web_mobile_domain = config('tpcache.web_mobile_domain');
                $goto = $request->param('goto');
                $goto = trim($goto, '/');
                $subDomain = $request->subDomain();
                if ('m' == $goto || (!empty($subDomain) && $subDomain == $web_mobile_domain)) {
                    $is_mobile_domain = true;
                } else {
                    if (3 == $seo_pseudo) {
                        $pathinfo = $request->pathinfo();
                        if (!empty($pathinfo)) {
                            $s_arr = explode('/', $pathinfo);
                            if ('m' == $s_arr[0]) {
                                $is_mobile_domain = true;
                            }
                        }
                    }
                }
                /*end*/

                // 多站点
                !empty($home_site) && $home_site = '_'.$home_site;

                // 缓存时间
                $web_cmsmode = 1;//tpCache('web.web_cmsmode');
                // $response_type = config('ey_config.response_type');  // 0是代码适配,1:pc、移动端分离（存在pc、移动端两套模板）
                if (1 == intval($web_cmsmode)) { // 永久
                    $path = HTML_ROOT.$val['filename'].DS;
                    $new_filename = TCP_SCHEME.'_'.$home_lang.$home_site;
                    if (isMobile() || $is_mobile_domain) {
                        $new_filename .= "_mobile";
                    } else {
                        $new_filename .= "_pc";
                    }
                    $new_filename .= '_'.$filename;
                    // $arr = explode('_', $filename);
                    // $id = end($arr);
                    // $new_filename = preg_replace('/^(.*)\_([^\_]+)$/i', '${1}', $new_filename);
                    // $filename = md5($new_filename).'_'.$id;
                    $filename = preg_replace('/([^\w\-]+)/i', '', $new_filename);
                    $filename = $path."{$filename}.html";
                    tp_mkdir(dirname($filename));
                    !empty($html) && file_put_contents($filename, $html);
                } else {
                    $path = HTML_PATH.$val['filename'].DS.$home_lang.DS.trim($home_site, '_');
                    if (isMobile()) {
                        $path .= "_mobile";
                    } else {
                        $path .= "_pc";
                    }
                    $path .= '_cache'.DS;
                    $web_htmlcache_expires_in = config('tpcache.web_htmlcache_expires_in');
                    $options = array(
                        'path'  => $path,
                        'expire'=> intval($web_htmlcache_expires_in),
                        'prefix'    => $cache_tag,
                    );
                    !empty($html) && html_cache($filename,$html,$options);
                }
            }
        }
    }
}

if (!function_exists('read_html_cache')) 
{
    /**
     * 读取静态页面缓存
     */
    function read_html_cache(){
        $html_cache_status = config('HTML_CACHE_STATUS');
        $html_cache_arr = config('HTML_CACHE_ARR');
        if ($html_cache_status && !empty($html_cache_arr)) {
            // 多站点/多语言
            $home_lang = 'cn';
            $home_site = '';
            $city_switch_on = config('city_switch_on');
            if (!empty($city_switch_on)) {
                $home_site = get_home_site();
            } else {
                $home_lang = get_home_lang();
            }

            $request = \think\Request::instance();
            $seo_pseudo = config('ey_config.seo_pseudo');
            $param = input('param.');

            /*前台筛选不进行页面缓存*/
            $url_screen_var = config('global.url_screen_var');
            if (isset($param[$url_screen_var])) {
                return false;
            }
            /*end*/

            if (1 == $seo_pseudo) {
                isset($param['tid']) && $param['tid'] = input('param.tid/d');
            } else {
                isset($param['tid']) && $param['tid'] = input('param.tid/s');
            }
            isset($param['page']) && $param['page'] = input('param.page/d');

            // aid唯一性的处理
            if (isset($param['aid'])) {
                if (!preg_match('/^([\w\-]+)$/', $param['aid'])) {
                    abort(404,'页面不存在');
                }
            }

            $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
            $m_c_a_str = strtolower($m_c_a_str);
            //exit('read_html_cache读取缓存<br/>');
            foreach($html_cache_arr as $mca=>$val)
            {
                $mca = strtolower($mca);
                if($mca != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
                    continue;

                if (empty($val['filename'])) {
                    continue;
                }

                $cache_tag = ''; // 缓存标签
                $filename = '';
                // 组合参数  
                if(isset($val['p']))
                {
                    $tid = '';
                    if (in_array('tid', $val['p'])) {
                        $tid = !empty($param['tid']) ? $param['tid'] : '';
                        if (strval(intval($tid)) != strval($tid)) {
                            $where = [
                                'dirname'   => $tid,
                            ];
                            if (empty($city_switch_on)) {
                                $where['lang'] =$home_lang;
                            }
                            $tid = \think\Db::name('arctype')->where($where)->getField('id');
                            $param['tid']   = $tid;
                        }
                    }

                    foreach ($val['p'] as $k=>$v) {
                        if (isset($param[$v])) {
                            if (preg_match('/\/$/i', $filename)) {
                                $filename .= $param[$v];
                            } else {
                                if (!empty($filename) || is_numeric($filename)) {
                                    $filename .= '_';
                                }
                                $filename .= $param[$v];
                            }
                        }
                    }
                    !empty($tid) && $cache_tag = $tid; // 针对列表缓存的标签
                    !empty($param['aid']) && $cache_tag = $param['aid']; // 针对内容缓存的标签
                }
                empty($filename) && $filename = 'index';
                // $filename = md5($filename);

                /*子域名（移动端域名）*/
                $is_mobile_domain = false;
                $web_mobile_domain = config('tpcache.web_mobile_domain');
                $goto = $request->param('goto');
                $goto = trim($goto, '/');
                $subDomain = $request->subDomain();
                if ('m' == $goto || (!empty($subDomain) && $subDomain == $web_mobile_domain)) {
                    $is_mobile_domain = true;
                } else {
                    if (3 == $seo_pseudo) {
                        $pathinfo = $request->pathinfo();
                        if (!empty($pathinfo)) {
                            $s_arr = explode('/', $pathinfo);
                            if ('m' == $s_arr[0]) {
                                $is_mobile_domain = true;
                            }
                        }
                    }
                }
                /*end*/

                // 多站点
                !empty($home_site) && $home_site = '_'.$home_site;

                // 缓存时间
                $web_cmsmode = 1;//tpCache('web.web_cmsmode');
                // $response_type = config('ey_config.response_type');  // 0是代码适配,1:pc、移动端分离（存在pc、移动端两套模板）
                if (1 == intval($web_cmsmode)) { // 永久
                    $path = HTML_ROOT.$val['filename'].DS;
                    $new_filename = TCP_SCHEME.'_'.$home_lang.$home_site;
                    if (isMobile() || $is_mobile_domain) {
                        $new_filename .= "_mobile";
                    } else {
                        $new_filename .= "_pc";
                    }
                    $new_filename .= '_'.$filename;
                    // $arr = explode('_', $filename);
                    // $id = end($arr);
                    // $new_filename = preg_replace('/^(.*)\_([^\_]+)$/i', '${1}', $new_filename);
                    // $filename = md5($new_filename).'_'.$id;
                    $filename = preg_replace('/([^\w\-]+)/i', '', $new_filename);
                    $filename = $path."{$filename}.html";

                    if(is_file($filename) && file_exists($filename))
                    {
                        echo file_get_contents($filename);
                        exit();
                    }
                } else {
                    $path = HTML_PATH.$val['filename'].DS.$home_lang.DS.trim($home_site, '_');
                    if (isMobile()) {
                        $path .= "_mobile";
                    } else {
                        $path .= "_pc";
                    }
                    $path .= '_cache'.DS;
                    $web_htmlcache_expires_in = config('tpcache.web_htmlcache_expires_in');
                    $options = array(
                        'path'  => $path,
                        'expire'=> intval($web_htmlcache_expires_in),
                        'prefix'    => $cache_tag,
                    );
                    $html = html_cache($filename, '', $options);
                    // $html = $html_cache->get($filename);
                    if($html)
                    {
                        echo $html;
                        exit();
                    }
                }
            }
        }
    }
}
 
if (!function_exists('is_local_images')) 
{
    /**
     * 判断远程链接是否属于本地图片，并返回本地图片路径
     *
     * @param string $pic_url 图片地址
     * @param boolean $returnbool 返回类型，false 返回图片路径，true 返回布尔值
     */
    function is_local_images($pic_url = '', $returnbool = false)
    {
        if (stristr($pic_url, '//'.request()->host().'/')) {
            $picPath  = parse_url($pic_url, PHP_URL_PATH);
            $picPath = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/public/static/|/uploads/|/weapp/)#i', '$2', $picPath);
            if (!empty($picPath) && file_exists('.'.$picPath)) {
                $pic_url = ROOT_DIR.$picPath;
                if (true == $returnbool) {
                    return $pic_url;
                }
            }
        }

        if (true == $returnbool) {
            return false;
        } else {
            return $pic_url;
        }
    }
}

if (!function_exists('get_head_pic')) 
{
    /**
     * 默认头像
     */
    function get_head_pic($pic_url = '', $is_admin = false, $sex = '保密')
    {
        if ($is_admin) {
            $default_pic = ROOT_DIR . '/public/static/admin/images/admint.png';
        } else {
            if ($sex == '女') {
                $default_pic = ROOT_DIR . '/public/static/common/images/dfgirl.png';
            } else {
                $default_pic = ROOT_DIR . '/public/static/common/images/dfboy.png';
            }
        }

        if (empty($pic_url)) {
            $pic_url = handle_subdir_pic($default_pic, 'img', true);
        } else if (!is_http_url($pic_url)) {
            $pic_url = handle_subdir_pic($pic_url, 'img', true);
        } else if (is_http_url($pic_url)) {
            $pic_url = str_ireplace(['http://thirdqq.qlogo.cn','http://qzapp.qlogo.cn'], ['https://thirdqq.qlogo.cn','https://qzapp.qlogo.cn'], $pic_url);
        }

        return $pic_url;
    }
}
if (!function_exists('get_absolute_url'))
{
    /*
     * 本站url转为绝对链接
     * $is_absolute     是否无论开启都转换为绝对路径
     */
    function get_absolute_url($str, $type = 'default',$is_absolute = false)
    {
        if (!is_http_url($str)) {
            static $absolute_path_open = null;
            if ($is_absolute){
                $absolute_path_open = true;
            }else{
                null === $absolute_path_open && $absolute_path_open = tpCache('web.absolute_path_open'); //是否开启绝对链接
            }
            if (!empty($absolute_path_open)) {
                static $domain = null;
                if (null == $domain) {
                    $domain = preg_replace('/^(([^\:]+):)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}${4}', request()->domain());
                }
                $root_dir = $domain.ROOT_DIR;
                switch ($type) {
                    case 'html':
                        $str = preg_replace('#(.*)(\#39;|&quot;|"|\')(/[/\w\-]+)?(/public/upload/|/public/plugins|/public/static/|/uploads/)(.*)#iU', '$1$2'.$root_dir.'$4$5', $str);
                        break;
                    case 'url':
                        $str = $domain.$str;
                        break;
                    default:
                        if (preg_match('#^(/[/\w\-]+)?(/public/(upload|plugins|static)/|/uploads/|/weapp/)#i', $str)) {
                            $str = preg_replace('#^(/[/\w\-]+)?(/public/(upload|plugins|static)/|/uploads/|/weapp/)#i', $root_dir.'$2', $str);
                        } else {
                            $str = $domain.$str;
                        }
                        break;
                }
            }
        }

        return $str;
    }
}
if (!function_exists('get_default_pic'))
{
    /**
     * 图片不存在，显示默认无图封面
     * @param string $pic_url 图片路径
     * @param string|boolean $domain 完整路径的域名
     */
    function get_default_pic($pic_url = '', $domain = false)
    {
        if (is_http_url($pic_url)) {
            $pic_url = handle_subdir_pic($pic_url, 'img', $domain);
        }

        if (!is_http_url($pic_url)) {
            if (!$domain){
                static $absolute_path_open = null;
                null === $absolute_path_open && $absolute_path_open = tpCache('web.absolute_path_open'); //是否开启绝对链接
                if ($absolute_path_open && request()->module() != 'admin'){
                    $domain = true;
                }
            }
            if (true === $domain) {
                $domain = request()->domain();
            } else if (false === $domain) {
                $domain = '';
            }
            
            $pic_url = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/public/static/|/uploads/|/weapp/)#i', '$2', $pic_url); // 支持子目录
            $realpath = realpath(ROOT_PATH.trim($pic_url, '/'));
            if ( is_file($realpath) && file_exists($realpath) ) {
                $pic_url = $domain . ROOT_DIR . $pic_url;
            } else {
                $pic_url = $domain . ROOT_DIR . '/public/static/common/images/not_adv.jpg';
            }
        }

        return $pic_url;
    }
}

if (!function_exists('handle_subdir_pic')) 
{
    /**
     * 处理子目录与根目录的图片平缓切换
     * @param string $str 图片路径或html代码
     */
    function handle_subdir_pic($str = '', $type = 'img', $domain = false, $clear_root_dir = false)
    {
        static $request = null;
        if (null === $request) {
            $request = \think\Request::instance();
        }

        $root_dir = $add_root_dir = ROOT_DIR;
        if ($clear_root_dir == true && $domain == false) {
            $add_root_dir = '';
        }else{
            static $absolute_path_open = null;
            null === $absolute_path_open && $absolute_path_open = tpCache('web.absolute_path_open'); //是否开启绝对链接
            if ($absolute_path_open && $request->module() != 'admin'){
                $add_root_dir = $request->domain().$add_root_dir;
                $domain = false;
            }
        }
        switch ($type) {
            case 'img':
                if (!is_http_url($str) && !empty($str)) {
                    $str = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/public/static/|/uploads/|/weapp/)#i', $add_root_dir.'$2', $str);
                }else if (is_http_url($str) && !empty($str)) {
                    $StrData = parse_url($str);
                    if (empty($StrData['scheme']) && $request->host(true) != $StrData['host']) {
                        $StrData['path'] = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/|/public/static/)#i', '$2', $StrData['path']);
                        if (preg_match('#^(/public/upload/|/public/static/|/uploads/|/weapp/)#i', $StrData['path']) && file_exists('.'.$StrData['path'])) {
                            // 插件列表
                            static $weappList = null;
                            if (null == $weappList) {
                                $weappList = \think\Db::name('weapp')->where([
                                    'status'    => 1,
                                ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                                ->getAllWithIndex('code');
                            }

                            if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
                                $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
                                $weappConfig = json_decode($weappList['Qiniuyun']['config'], true);
                                if (!empty($weappConfig['version']) && 'v1.0.6' <= $weappConfig['version']) {
                                    $qiniuyunModel = new \weapp\Qiniuyun\model\QiniuyunModel;
                                    $str = $qiniuyunModel->handle_subdir_pic($qnyData, $StrData, $str);
                                } else {
                                    if ($qnyData['domain'] == $StrData['host']) {
                                        $tcp = !empty($qnyData['tcp']) ? $qnyData['tcp'] : '';
                                        switch ($tcp) {
                                            case '2':
                                                $tcp = 'https://';
                                                break;

                                            case '3':
                                                $tcp = '//';
                                                break;
                                            
                                            case '1':
                                            default:
                                                $tcp = 'http://';
                                                break;
                                        }
                                        $str = $tcp.$qnyData['domain'].$StrData['path'];
                                    }else{
                                        // 若切换了存储空间或访问域名，与数据库中存储的图片路径域名不一致时，访问本地路径，保证图片正常
                                        $str = $add_root_dir.$StrData['path'];
                                    }
                                }
                            }
                            else if (!empty($weappList['AliyunOss']) && 1 == $weappList['AliyunOss']['status']) {
                                $ossData = json_decode($weappList['AliyunOss']['data'], true);
                                $aliyunOssModel = new \weapp\AliyunOss\model\AliyunOssModel;
                                $str = $aliyunOssModel->handle_subdir_pic($ossData, $StrData, $str);
                            }
                            else if (!empty($weappList['Cos']) && 1 == $weappList['Cos']['status']) {
                                $cosData = json_decode($weappList['Cos']['data'], true);
                                $cosModel = new \weapp\Cos\model\CosModel;
                                $str = $cosModel->handle_subdir_pic($cosData, $StrData, $str);
                            }
                            else if (!empty($weappList['AwsOss']) && 1 == $weappList['AwsOss']['status']) {
                                $awsData = json_decode($weappList['AwsOss']['data'], true);
                                $awsModel = new \weapp\AwsOss\model\AwsOssModel;
                                $str = $awsModel->handle_subdir_pic($awsData, $StrData, $str);
                            }
                            else {
                                // 关闭
                                $str = $add_root_dir.$StrData['path'];
                            }
                        } else {
                            $str = preg_replace('/^\/\//i', $request->scheme().'://', $str);
                        }
                    }
                }
                break;

            case 'html':
                preg_match_all('/(\&lt\;|\<)img.*(\/)?(\>|\&gt\;)/iUs', $str, $imginfo);//摘出图片
                $imgArr = empty($imginfo[0]) ? [] : $imginfo[0];
                if (!empty($imgArr)) {
                    // 插件列表
                    static $weappList = null;
                    if (null == $weappList) {
                        $weappList = \think\Db::name('weapp')->where([
                            'status'    => 1,
                        ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                        ->getAllWithIndex('code');
                    }
                    foreach ($imgArr as $key => $value) {
                        preg_match_all("#src=('|\")(.*)('|\")#isU", $value, $img_val);
                        if (isset($img_val[2][0]) && !is_http_url($img_val[2][0])) { // 是否本地图片
                            $handle_img = preg_replace('#(/[/\w\-]+)?(/public/upload/|/public/static/|/uploads/|/weapp/)#i', $add_root_dir.'$2', $value);
                            $str = str_ireplace($value, $handle_img, $str);
                        } else if (isset($img_val[2][0]) && is_http_url($img_val[2][0])) {
                            $StrData = parse_url($img_val[2][0]);
                            if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
                                $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
                                $weappConfig = json_decode($weappList['Qiniuyun']['config'], true);
                                if ($qnyData['domain'] != $StrData['host']) {
                                    // 若切换了存储空间或访问域名，与数据库中存储的图片路径域名不一致时，访问本地路径，保证图片正常
                                    $str = str_ireplace('//' . $StrData['host'], '', $str);
                                    $str = str_ireplace('http://' . $StrData['host'], '', $str);
                                    $str = str_ireplace('https://' . $StrData['host'], '', $str);
                                }
                            }
                        }
                    }
                }
                
                // $str = preg_replace('#(.*)(\#39;|&quot;|"|\')(/[/\w\-]+)?(/public/upload/|/public/plugins/|/uploads/)(.*)#iU', '$1$2'.$add_root_dir.'$4$5', $str);
                break;

            case 'soft':
                if (!is_http_url($str) && !empty($str)) {
                    $str = preg_replace('#^(/[/\w\-]+)?(/public/upload/soft/|/uploads/soft/|/weapp/)#i', $add_root_dir.'$2', $str);
                } else if (is_http_url($str) && !empty($str)) {
                    $StrData = parse_url($str);
                    $StrData['path'] = preg_replace('#^(/[/\w\-]+)?(/public/upload/soft/|/uploads/soft/)#i', '$2', $StrData['path']);
                    if ($request->host(true) != $StrData['host'] && preg_match('#^(/public/upload/soft/|/uploads/soft/)#i', $StrData['path'])) {
                        // 插件列表
                        static $weappList = null;
                        if (null == $weappList) {
                            $weappList = \think\Db::name('weapp')->where([
                                'status'    => 1,
                            ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                            ->getAllWithIndex('code');
                        }

                        if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
                            $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
                            $weappConfig = json_decode($weappList['Qiniuyun']['config'], true);
                            if (!empty($weappConfig['version']) && 'v1.0.6' <= $weappConfig['version']) {
                                $qiniuyunModel = new \weapp\Qiniuyun\model\QiniuyunModel;
                                $str = $qiniuyunModel->handle_subdir_pic($qnyData, $StrData, $str);
                            } else {
                                if ($qnyData['domain'] == $StrData['host']) {
                                    $tcp = !empty($qnyData['tcp']) ? $qnyData['tcp'] : '';
                                    switch ($tcp) {
                                        case '2':
                                            $tcp = 'https://';
                                            break;

                                        case '3':
                                            $tcp = '//';
                                            break;
                                        
                                        case '1':
                                        default:
                                            $tcp = 'http://';
                                            break;
                                    }
                                    $str = $tcp.$qnyData['domain'].$StrData['path'];
                                }else{
                                    // 若切换了存储空间或访问域名，与数据库中存储的图片路径域名不一致时，访问本地路径，保证图片正常
                                    if (file_exists('.'.$StrData['path'])) {
                                        $str = $add_root_dir.$StrData['path'];
                                    }
                                }
                            }
                        }
                        else if (!empty($weappList['AliyunOss']) && 1 == $weappList['AliyunOss']['status']) {
                            $ossData = json_decode($weappList['AliyunOss']['data'], true);
                            $aliyunOssModel = new \weapp\AliyunOss\model\AliyunOssModel;
                            $str = $aliyunOssModel->handle_subdir_pic($ossData, $StrData, $str);
                        }
                        else if (!empty($weappList['Cos']) && 1 == $weappList['Cos']['status']) {
                            $cosData = json_decode($weappList['Cos']['data'], true);
                            $cosModel = new \weapp\Cos\model\CosModel;
                            $str = $cosModel->handle_subdir_pic($cosData, $StrData, $str);
                        }
                        else if (!empty($weappList['AwsOss']) && 1 == $weappList['AwsOss']['status']) {
                            $awsData = json_decode($weappList['AwsOss']['data'], true);
                            $awsModel = new \weapp\AwsOss\model\AwsOssModel;
                            $str = $awsModel->handle_subdir_pic($awsData, $StrData, $str);
                        }
                    }
                }
                break;

            case 'media':  //多媒体文件
                if (!is_http_url($str) && !empty($str)) {
                    $str = preg_replace('#^(/[/\w\-]+)?(/uploads/media/|/weapp/)#i', $add_root_dir.'$2', $str);
                }
                break;

            default:
                # code...
                break;
        }

        if (!empty($str) && !is_http_url($str)) {
            if (false !== $domain) {
                if (true === $domain) {
                    static $domain_new = null;
                    if (null === $domain_new) {
                        $domain_new = $request->domain();
                    }
                    $domain = $domain_new;
                }
                $str = $domain.$str;
            }
        }

        return $str;
    }
}

/**
 * 获取阅读权限
 */
if ( ! function_exists('get_arcrank_list'))
{
    function get_arcrank_list()
    {
        $result = \think\Db::name('arcrank')->where([
                'lang'  => get_admin_lang(),
            ])
            ->order('id asc')
            ->cache(true, EYOUCMS_CACHE_TIME, "arcrank")
            ->getAllWithIndex('rank');

        // 等级分类
        $LevelData = \think\Db::name('users_level')->field('level_name as `name`, level_value as `rank`')->order('level_value asc, level_id asc')->cache(true, EYOUCMS_CACHE_TIME, "users_level")->select();
        if (!empty($LevelData)) {
            $result = array_merge($result, $LevelData);
        }
        return $result;
    }
}

if (!function_exists('thumb_img')) 
{
    /**
     * 缩略图 从原始图来处理出来
     * @param type $original_img  图片路径
     * @param type $width     生成缩略图的宽度
     * @param type $height    生成缩略图的高度
     * @param type $thumb_mode    生成方式
     */
    function thumb_img($original_img = '', $width = '', $height = '', $thumb_mode = '')
    {
        // 缩略图配置
        static $thumbConfig = null;
        if (null === $thumbConfig) {
            @ini_set('memory_limit', '-1'); // 内存不限制，防止图片大小过大，导致缩略图处理失败，网站打不开
            $thumbConfig = tpCache('thumb');
        }
        $thumbextra = config('global.thumb');

        if (!empty($width) || !empty($height) || !empty($thumb_mode)) { // 单独在模板里调用，不受缩略图全局开关影响

        } else { // 非单独模板调用，比如内置的arclist\list标签里
            if (empty($thumbConfig['thumb_open'])) {
                return $original_img;
            }
        }

        // 缩略图优先级别高于七牛云，自动把七牛云的图片路径转为本地图片路径，并且进行缩略图
        $original_img = is_local_images($original_img);
        // 未开启缩略图，或远程图片
        if (is_http_url($original_img)) {
            return $original_img;
        } else if (empty($original_img)) {
            return ROOT_DIR.'/public/static/common/images/not_adv.jpg';
        }

        // 图片文件名
        $filename = '';
        $imgArr = explode('/', $original_img);    
        $imgArr = end($imgArr);
        $filename = preg_replace("/\.([^\.]+)$/i", "", $imgArr);
        $file_ext = preg_replace("/^(.*)\.([^\.]+)$/i", "$2", $imgArr);

        // 如果图片参数是缩略图，则直接获取到原图，并进行缩略处理
        if (preg_match('/\/uploads\/thumb\/\d{1,}_\d{1,}\//i', $original_img)) {
            $pattern = UPLOAD_PATH.'allimg/*/'.$filename;
            if (in_array(strtolower($file_ext), ['jpg','jpeg'])) {
                $pattern .= '.jp*g';
            } else {
                $pattern .= '.'.$file_ext;
            }
            $original_img_tmp = glob($pattern);
            if (!empty($original_img_tmp)) {
                $original_img = '/'.current($original_img_tmp);
            }
        } else {
            if ('bmp' == $file_ext && version_compare(PHP_VERSION,'7.2.0','<')) {
                return $original_img;
            }
        }
        // --end

//        $original_img1 = preg_replace('#^'.ROOT_DIR.'#i', '', handle_subdir_pic($original_img));
        $original_img1 = preg_replace('#^'.ROOT_DIR.'#i', '', handle_subdir_pic($original_img, 'img',false,true));

        $original_img1 = '.' . $original_img1; // 相对路径
        //获取图像信息
        $info = @getimagesize($original_img1);

        //检测图像合法性
        if (false === $info || (IMAGETYPE_GIF === $info[2] && empty($info['bits']))) {
            return $original_img;
        } else {
            if (!empty($info['mime']) && stristr($info['mime'], 'bmp') && version_compare(PHP_VERSION,'7.2.0','<')) {
                return $original_img;
            }
        }

        // 缩略图宽高度
        $is_auto_mode = 0;
        if (empty($width)) {
            if (is_numeric($thumbConfig['thumb_width']) && 0 == $thumbConfig['thumb_width']) {
                $width = !empty($info[0]) ? $info[0] : 1000000;
                $is_auto_mode = 1;
            } else {
                $width = !empty($thumbConfig['thumb_width']) ? $thumbConfig['thumb_width'] : $thumbextra['width'];
            }
        }
        if (empty($height)) {
            if (is_numeric($thumbConfig['thumb_height']) && 0 == $thumbConfig['thumb_height']) {
                $height = !empty($info[0]) ? $info[0] : 1000000;
                $is_auto_mode = 1;
            } else {
                $height = !empty($thumbConfig['thumb_height']) ? $thumbConfig['thumb_height'] : $thumbextra['height'];
            }
        }
        $width = intval($width);
        $height = intval($height);

        //判断缩略图是否存在
        $path = UPLOAD_PATH."thumb/{$width}_{$height}/";
        $img_thumb_name = "{$filename}";

        // 已经生成过这个比例的图片就直接返回了
        if (is_file($path . $img_thumb_name . '.jpg')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.jpg');
        if (is_file($path . $img_thumb_name . '.jpeg')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.jpeg');
        if (is_file($path . $img_thumb_name . '.gif')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.gif');
        if (is_file($path . $img_thumb_name . '.png')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.png');
        if (is_file($path . $img_thumb_name . '.bmp')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.bmp');
        if (is_file($path . $img_thumb_name . '.webp')) return get_absolute_url(ROOT_DIR.'/' . $path . $img_thumb_name . '.webp');

        if (!is_file($original_img1)) {
            return get_absolute_url(ROOT_DIR.'/public/static/common/images/not_adv.jpg');
        }

        try {
            vendor('topthink.think-image.src.Image');
            vendor('topthink.think-image.src.image.Exception');
            if(stristr($original_img1,'.gif'))
            {
                vendor('topthink.think-image.src.image.gif.Encoder');
                vendor('topthink.think-image.src.image.gif.Decoder');
                vendor('topthink.think-image.src.image.gif.Gif');               
            }           
            $image = \think\Image::open($original_img1);

            $img_thumb_name = $img_thumb_name . '.' . $image->type();
            // 生成缩略图
            !is_dir($path) && mkdir($path, 0777, true);
            // 填充颜色
            $thumb_color = !empty($thumbConfig['thumb_color']) ? $thumbConfig['thumb_color'] : $thumbextra['color'];
            // 生成方式参考 vendor/topthink/think-image/src/Image.php
            if (!empty($thumb_mode)) {
                $thumb_mode = intval($thumb_mode);
            } else {
                $thumb_mode = !empty($thumbConfig['thumb_mode']) ? $thumbConfig['thumb_mode'] : $thumbextra['mode'];
            }

            if (1 == $is_auto_mode) {
                $thumb_mode = 1;
            } else {
                1 == $thumb_mode && $thumb_mode = 6; // 按照固定比例拉伸
                2 == $thumb_mode && $thumb_mode = 2; // 填充空白
                if (3 == $thumb_mode) {
                    $img_width = $image->width();
                    $img_height = $image->height();
                    if ($width < $img_width && $height < $img_height) {
                        // 先进行缩略图等比例缩放类型，取出宽高中最小的属性值
                        $min_width = ($img_width < $img_height) ? $img_width : 0;
                        $min_height = ($img_width > $img_height) ? $img_height : 0;
                        if ($min_width > $width || $min_height > $height) {
                            if (0 < intval($min_width)) {
                                $scale = $min_width / min($width, $height);
                            } else if (0 < intval($min_height)) {
                                $scale = $min_height / $height;
                            } else {
                                $scale = $min_width / $width;
                            }
                            $s_width  = $img_width / $scale;
                            $s_height = $img_height / $scale;
                            $image->thumb($s_width, $s_height, 1, $thumb_color)->save($path . $img_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
                        }
                    }
                    $thumb_mode = 3; // 截减
                }
            }
            // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
            $image->thumb($width, $height, $thumb_mode, $thumb_color)->save($path . $img_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
            //图片水印处理
            $water = tpCache('water');
            if($water['is_mark']==1 && $water['is_thumb_mark'] == 1 && $image->width()>$water['mark_width'] && $image->height()>$water['mark_height']){
                $imgresource = './' . $path . $img_thumb_name;
                if($water['mark_type'] == 'text'){
                    //$image->text($water['mark_txt'],ROOT_PATH.'public/static/common/font/hgzb.ttf',20,'#000000',9)->save($imgresource);
                    $ttf = ROOT_PATH.'public/static/common/font/hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ? $water['mark_txt_size'] : 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127/100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['mark_sel'])->save($imgresource);
                        $return_data['mark_txt'] = $water['mark_txt'];
                    }
                }else{
                    /*支持子目录*/
                    $water['mark_img'] = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $water['mark_img']); // 支持子目录
                    /*--end*/
                    //$image->water(".".$water['mark_img'],9,$water['mark_degree'])->save($imgresource);
                    $waterPath = "." . $water['mark_img'];
                    if (eyPreventShell($waterPath) && file_exists($waterPath)) {
                        $quality = $water['mark_quality'] ? $water['mark_quality'] : 80;
                        $waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['mark_sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                }
            }
            $img_url = ROOT_DIR.'/' . $path . $img_thumb_name;
            $img_url = get_absolute_url($img_url);

            return $img_url;

        } catch (think\Exception $e) {

            return $original_img;
        }
    }
}

if (!function_exists('get_controller_byct')) {
    /**
     * 根据模型ID获取控制器的名称
     * @return mixed
     */
    function get_controller_byct($current_channel)
    {
        $channeltype_info = model('Channeltype')->getInfo($current_channel);
        return $channeltype_info['ctl_name'];
    }
}

if (!function_exists('ui_read_bidden_inc')) {
    /**
     * 读取被禁止外部访问的配置文件
     * @param string $filename 文件路径
     * @return mixed
     */
    function ui_read_bidden_inc($filename)
    {
        $data = false;
        if (file_exists($filename)) {
            $data = @file($filename);
            $data = json_decode($data[1], true);
        }

        if (empty($data)) {
            // -------------优先读取配置文件，不存在才读取数据表
            $params = explode('/', $filename);
            $page = $params[count($params) - 1];
            $pagearr = explode('.', $page);
            reset($pagearr);
            $page = current($pagearr);
            $map = array(
                'page'   => $page,
                'theme_style'   => THEME_STYLE_PATH,
            );
            $result = M('ui_config')->where($map)->cache(true,EYOUCMS_CACHE_TIME,"ui_config")->select();
            if ($result) {
                $dataArr = array();
                foreach ($result as $key => $val) {
                    $k = "{$val['lang']}_{$val['type']}_{$val['name']}";
                    $dataArr[$k] = $val['value'];
                }
                $data = $dataArr;
            } else {
                $data = false;
            }
            //---------------end

            if (!empty($data)) {
                // ----------文件不存在，并写入文件缓存
                tp_mkdir(dirname($filename));
                $nowData = $data;
                $setting = "<?php die('forbidden'); ?>\n";
                $setting .= json_encode($nowData);
                $setting = str_replace("\/", "/",$setting);
                $incFile = fopen($filename, "w+");
                if ($incFile != false && fwrite($incFile, $setting)) {
                    fclose($incFile);
                }
                //---------------end
            }
        }
        
        return $data;
    }
}

if (!function_exists('ui_write_bidden_inc')) {
    /**
     * 写入被禁止外部访问的配置文件
     * @param array $arr 配置变量
     * @param string $filename 文件路径
     * @param bool $is_append false
     * @return mixed
     */
    function ui_write_bidden_inc($data, $filename, $is_append = false)
    {
        $data2 = $data;
        if (!empty($filename)) {

            // -------------写入数据表，同时写入配置文件
            reset($data2);
            $value = current($data2);
            $tmp_val = json_decode($value, true);
            $name = $tmp_val['id'];
            $type = $tmp_val['type'];
            $page = $tmp_val['page'];
            $lang = !empty($tmp_val['lang']) ? $tmp_val['lang'] : cookie(config('global.home_lang'));
            $idcode = $tmp_val['idcode'];
            if (empty($lang)) {
                $lang = model('language')->order('id asc')
                    ->limit(1)
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->getField('mark');
            }
            $theme_style = THEME_STYLE_PATH;
            $md5key = md5($name.$page.$theme_style.$lang);
            $savedata = array(
                'md5key'    => $md5key,
                'theme_style'  => $theme_style,
                'page'  => $page,
                'type'  => $type,
                'name'  => $name,
                'value' => $value,
                'lang'  => $lang,
                'idcode'=> $idcode,
            );
            $map = array(
                'name'   => $name,
                'page'   => $page,
                'theme_style'   => $theme_style,
                'lang'   => $lang,
            );
            $count = M('ui_config')->where($map)->count('id');
            if ($count > 0) {
                $savedata['update_time'] = getTime();
                $r = M('ui_config')->where($map)->cache(true,EYOUCMS_CACHE_TIME,'ui_config')->update($savedata);
            } else {
                $savedata['add_time'] = getTime();
                $savedata['update_time'] = getTime();
                $r = M('ui_config')->insert($savedata);
                \think\Cache::clear('ui_config');
            }

            if ($r) {

                // ----------同时写入文件缓存
                tp_mkdir(dirname($filename));

                // 追加
                if ($is_append) {
                    $inc = ui_read_bidden_inc($filename);
                    if ($inc) {
                        $oldarr = (array)$inc;
                        $data = array_merge($oldarr, $data);
                    }
                }

                $setting = "<?php die('forbidden'); ?>\n";
                $setting .= json_encode($data);
                $setting = str_replace("\/", "/",$setting);
                $incFile = fopen($filename, "w+");
                if ($incFile != false && fwrite($incFile, $setting)) {
                    fclose($incFile);
                }
                //---------------end

                return true;
            }
        }

        return false;
    }
}

if (!function_exists('get_ui_inc_params')) {
    /**
     * 获取模板主题的美化配置参数
     * @return mixed
     */
    function get_ui_inc_params($page)
    {
        $e_page = $page;
        $filename = RUNTIME_PATH.'ui/'.THEME_STYLE_PATH.'/'.$e_page.'.inc.php';
        $inc = ui_read_bidden_inc($filename);

        return $inc;
    }
}

if (!function_exists('allow_release_arctype')) 
{
    /**
     * 允许发布文档的栏目列表
     */
    function allow_release_arctype($selected = 0, $allow_release_channel = array(), $selectform = true, $release_typeids = [],$users_release = false)
    {
        $release_typeids_pre = [];
        if ($users_release){
            $release_typeids_pre = $release_typeids;
            $topids = Db::name('arctype')->where([
                    'id' => ['IN', $release_typeids],
                    'topid' => ['gt', 0],
                    'lang' => get_current_lang(),
                ])->column('topid');
            $topid_arr = Db::name('arctype')->where([
                    'topid|id' => ['IN', $topids],
                    'lang' => get_current_lang(),
                ])->column('id');
            $release_typeids = array_merge($release_typeids,$topid_arr);
        }
        $where = [];

        $where['c.weapp_code'] = ''; // 回收站功能
        $where['c.lang']   = get_current_lang(); // 多语言 by 小虎哥
        $where['c.is_del'] = 0; // 回收站功能
        $current_channel = [51];
        $php_servicemeal = tpCache('php.php_servicemeal');
        if (1.5 > $php_servicemeal) {
            array_push($current_channel, 5);
        }
        $current_channel_str = implode(',', $current_channel);
        $where['c.current_channel'] = ['notin', $current_channel];

        /*权限控制 by 小虎哥*/
        $admin_info = session('admin_info');
        if (0 < intval($admin_info['role_id'])) {
            $auth_role_info = $admin_info['auth_role_info'];
            if(! empty($auth_role_info)){
                if(! empty($auth_role_info['permission']['arctype'])){
                    $where['c.id'] = array('IN', $auth_role_info['permission']['arctype']);
                }
            }
        }
        /*--end*/

        // 查询会员投稿指定的栏目
        if (!empty($release_typeids)) $where['c.id'] = ['IN', $release_typeids];

        // 默认选中的栏目
        if (!is_array($selected)) $selected = [$selected];

        $cacheKey = md5(json_encode($selected).json_encode($allow_release_channel).$selectform.json_encode($where));
        $select_html = cache($cacheKey);
        if (empty($select_html) || false == $selectform) {
            /*允许发布文档的模型*/
            $allow_release_channel = !empty($allow_release_channel) ? $allow_release_channel : config('global.allow_release_channel');

            /*所有栏目分类*/
            $where['c.status'] = 1;
            $fields = "c.id, c.parent_id, c.current_channel, c.typename, c.grade, '' as children";
            $res = $res2 = \think\Db::name('arctype')
                ->field($fields)
                ->alias('c')
                ->where($where)
                ->order('c.parent_id asc, c.sort_order asc, c.id asc')
                ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
                ->select();
            /*--end*/
            if (empty($res)) {
                return '';
            }

            // 汇总每个栏目下的一级子栏目数量
            $arctypeSublist = [];
            foreach ($res as $key => $val) {
                if (!empty($val['parent_id'])) {
                    $arctypeSublist[$val['parent_id']][] = $val['id'];
                }
            }

            $grade_arr = [];
            $i = 0;
            foreach ($res as $key=>$val) { 
                $res[$key]['has_children'] = $res2[$key]['has_children'] = !empty($arctypeSublist[$val['id']]) ? count($arctypeSublist[$val['id']]) : 0;
                $grade_arr[] = $val['grade']; // 用一个空数组来承接字段
                $res2[$key]['new_sort_order'] = $i++; // 标记新的排序号
            }
            $max_grade = max($grade_arr); // 取最大的层级
            array_multisort($grade_arr, SORT_DESC, SORT_NUMERIC, $res2); // 按层级排序，从大到小

            $res3 = $new_sort_order_arr = [];
            foreach ($res2 as $key=>$val) { 
                $res3[$val['id']] = $val;
                $new_sort_order_arr[$val['id']] = $val['new_sort_order'];
            }

            /*过滤掉不允许发布的栏目（该栏目下包含不允许发布的栏目或没有下级）*/
            for ($i=0; $i <= $max_grade; $i++) {
                foreach ($res3 as $key => $val) {
                    if (!in_array($val['current_channel'], $allow_release_channel)) {
                        $tmp_val = $res3[$key];
                        if ( $tmp_val['has_children'] <= 0 ) {
                            unset($res3[$key]);
                            unset($new_sort_order_arr[$key]);
                            if (!empty($tmp_val['parent_id'])) {
                                if (!empty($res3[$tmp_val['parent_id']]['has_children'])) {
                                    $res3[$tmp_val['parent_id']]['has_children'] -= 1;
                                }
                            }
                        }
                    }
                }
            }
            /*--end*/
            //只有前台会员投稿走这个判断
            if ($users_release){
                duplicate_removal($res3,$new_sort_order_arr,$release_typeids_pre);
                if (empty($res3)){
                    return '';
                }
            }

            array_multisort($new_sort_order_arr, SORT_ASC, SORT_NUMERIC, $res3); // 按设置的最新排序号，从小到大
            /*所有栏目列表进行层次归类*/
            $arr = group_same_key($res3, 'parent_id');
            for ($i=0; $i <= $max_grade; $i++) {
                foreach ($arr as $key => $val) {
                    foreach ($arr[$key] as $key2 => $val2) {
                        if (!isset($arr[$val2['id']])) {
                            $arr[$key][$key2]['has_children'] = 0;
                            continue;
                        }
                        $val2['children'] = $arr[$val2['id']];
                        $arr[$key][$key2] = $val2;
                    }
                }
            }
            /*--end*/

            $nowArr = $arr[0];

            /*组装成层级下拉列表框*/
            $select_html = '';
            if (false == $selectform) {
                $select_html = $nowArr;
            } else if (true == $selectform) {

                handle_arctype_data($select_html, $nowArr, $selected, $allow_release_channel,$release_typeids_pre);
                cache($cacheKey, $select_html, null, 'arctype');
            }
        }

        return $select_html;
    }
}
if (!function_exists('duplicate_removal'))
{
    //用于会员投稿清除下级没有选中的多余栏目
    function duplicate_removal(&$data_arr = [],&$new_sort_order_arr = [],$ids = []){
        $circulate = false;
        foreach ($data_arr as $k => $v){
            if (0 == $v['has_children'] && !in_array($v['id'],$ids)){
                $circulate = true;
                if (!empty($data_arr[$v['parent_id']])){
                    $data_arr[$v['parent_id']]['has_children'] -= 1;
                }
                unset($data_arr[$k]);
                unset($new_sort_order_arr[$k]);
            }
        }
        if ($circulate){
            duplicate_removal($data_arr,$new_sort_order_arr,$ids);
        }

    }
}
if (!function_exists('handle_arctype_data'))
{
    // 处理栏目数据
    function handle_arctype_data(&$select_html = '', $nowArr = [], $selected = 0, $allow_release_channel = [],$release_typeids_pre = [])
    {
        foreach ($nowArr AS $key => $val)
        {
            //只有前台会员投稿走这个判断
            if (!empty($release_typeids_pre) && !in_array($val['id'], $release_typeids_pre) && $val['has_children'] == 0){
                continue;
            }
            $select_html .= '<option value="' . $val['id'] . '" data-grade="' . $val['grade'] . '" data-current_channel="' . $val['current_channel'] . '"';
            $select_html .= (in_array($val['id'], $selected)) ? ' selected="true"' : '';
            if ((!empty($allow_release_channel) && !in_array($val['current_channel'], $allow_release_channel)) || (!empty($release_typeids_pre) && !in_array($val['id'], $release_typeids_pre) ) ) {
                $select_html .= ' disabled="true" style="background-color:#f5f5f5;"';
            }
            $select_html .= '>';
            if ($val['grade'] > 0)
            {
                $select_html .= str_repeat('&nbsp;', $val['grade'] * 4);
            }
            $select_html .= htmlspecialchars_decode($val['typename']) . '</option>';

            if (empty($val['children'])) {
                continue;
            }
            handle_arctype_data($select_html, $val['children'], $selected, $allow_release_channel,$release_typeids_pre);
        }
    }
}

if (!function_exists('every_top_dirname_list')) 
{
    /**
     * 获取一级栏目的目录名称
     */
    function every_top_dirname_list() {
        $arctypeModel = new \app\common\model\Arctype();
        $result = $arctypeModel->getEveryTopDirnameList();
        
        return $result;
    }
}

if (!function_exists('getalltype')){
    /**
     * 获取当前栏目的所有上级栏目
     * $typeid  当前栏目id
     * $field   需要获取的某一列的值的集合
     */
    function getalltype($typeid, $field = '')
    {
        $parent_list = model('Arctype')->getAllPid($typeid); // 获取当前栏目的所有父级栏目

        if (!empty($field)){
            $parent_list = get_arr_column($parent_list,$field);
        }
        return $parent_list;
    }
}

if (!function_exists('gettoptype'))
{
    /**
     * 获取当前栏目的第一级栏目
     */
    function gettoptype($typeid, $field = 'typename')
    {
        $parent_list = model('Arctype')->getAllPid($typeid); // 获取当前栏目的所有父级栏目
        $result = current($parent_list); // 第一级栏目
        if (isset($result[$field]) && !empty($result[$field])) {
            return handle_subdir_pic($result[$field]); // 支持子目录
        } else {
            return '';
        }
    }
}

if (!function_exists('getparenttype')) 
{
    /**
     * 获取当前栏目的上级栏目
     */
    function getparenttype($typeid, $field = 'typename')
    {
        $parent_list = model('Arctype')->getAllPid($typeid); // 获取当前栏目的所有父级栏目
        if (!empty($parent_list)) {
            array_pop($parent_list);
        }
        $result = end($parent_list); // 上级栏目
        if (isset($result[$field]) && !empty($result[$field])) {
            return handle_subdir_pic($result[$field]); // 支持子目录
        } else {
            return '';
        }
    }
}

if (!function_exists('get_main_lang')) 
{
    /**
     * 获取主体语言（语言列表里最早的一条）
     */
    function get_main_lang($is_force = false)
    {
        static $main_lang = null;
        if (null === $main_lang || $is_force) {
            $keys = 'common_get_main_lang';
            $main_lang = \think\Cache::get($keys);
            if ($is_force || empty($main_lang) || (!empty($main_lang) && !preg_match('/^[a-z]{2}$/i', $main_lang))) {
                $main_lang = \think\Db::name('language')->order('id asc')
                    ->limit(1)
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->getField('mark');
                \think\Cache::set($keys, $main_lang);
            }
            $main_lang = preg_replace('/([^a-z])/i', '', $main_lang);
        }

        return $main_lang;
    }
}

if (!function_exists('get_default_lang')) 
{
    /**
     * 获取默认语言
     */
    function get_default_lang($is_force = false)
    {
        static $default_lang = null;
        if (null === $default_lang || $is_force) {
            $request = \think\Request::instance();
            if (!stristr($request->baseFile(), 'index.php')) {
                $default_lang = get_admin_lang($is_force);
            } else {
                $default_lang = \think\Config::get('ey_config.system_home_default_lang');
            }
        }

        return $default_lang;
    }
}

if (!function_exists('get_current_lang')) 
{
    /**
     * 获取当前默认语言
     */
    function get_current_lang($is_force = false)
    {
        static $current_lang = null;
        if (null === $current_lang || $is_force) {
            $request = \think\Request::instance();
            if (!stristr($request->baseFile(), 'index.php')) {
                $current_lang = get_admin_lang($is_force);
            } else {
                $current_lang = get_home_lang($is_force);
            }
        }

        return $current_lang;
    }
}

if (!function_exists('get_admin_lang')) 
{
    /**
     * 获取后台当前语言
     */
    function get_admin_lang($is_force = false)
    {
        static $admin_lang = null;
        if (null === $admin_lang || $is_force) {
            $keys = \think\Config::get('global.admin_lang');
            $admin_lang = \think\Cookie::get($keys);
            $admin_lang = addslashes($admin_lang);
            if ($is_force || empty($admin_lang) || (!empty($admin_lang) && !preg_match('/^[a-z]{2}$/i', $admin_lang))) {
                $admin_lang = input('param.lang/s');
                empty($admin_lang) && $admin_lang = get_main_lang($is_force);
                \think\Cookie::set($keys, $admin_lang);
            }
            $admin_lang = preg_replace('/([^a-z])/i', '', $admin_lang);
        }

        return $admin_lang;
    }
}

if (!function_exists('get_home_lang')) 
{
    /**
     * 获取前台当前语言
     */
    function get_home_lang($is_force = false)
    {
        static $home_lang = null;
        if (null === $home_lang || $is_force) {
            $keys = \think\Config::get('global.home_lang');
            $home_lang = input('param.lang/s');
            if ($is_force || empty($home_lang)){
                $home_lang = \think\Cookie::get($keys);
                $home_lang = addslashes($home_lang);
                if ($is_force || empty($home_lang) || (!empty($home_lang) && !preg_match('/^[a-z]{2}$/i', $home_lang))) {
                    if ($is_force || empty($home_lang)) {
                        $home_lang = \think\Db::name('language')->where([
                            'is_home_default'   => 1,
                        ])->getField('mark');
                    }
                }
            }
            $home_lang = preg_replace('/([^a-z])/i', '', $home_lang);
            \think\Cookie::set($keys, $home_lang);
        }

        return $home_lang;
    }
}

if (!function_exists('is_language')) 
{
    /**
     * 是否多语言
     */
    function is_language()
    {
        static $value = null;
        if (null === $value) {
            $module = \think\Request::instance()->module();
            if (empty($module)) {
                $system_langnum = tpCache('system.system_langnum');
            } else {
                $system_langnum = config('ey_config.system_langnum');
            }

            if (1 < intval($system_langnum)) {
                $value = $system_langnum;
            } else {
                $value = false;
            }
        }

        return $value;
    }
}

if (!function_exists('switch_language')) 
{
    /**
     * 多语言切换（默认中文）
     *
     * @return void
     */
    function switch_language() 
    {
        static $execute_end = false;
        if (true === $execute_end) {
            return true;
        }

        static $request = null;
        if (null == $request) {
            $request = \think\Request::instance();
        }

        $pathinfo = $request->pathinfo();
        /*验证语言标识是否合法---PS：$request->param('lang/s','')一定要放在$request->pathinfo()后面，非则会造成分页错误（链接带有"s"变量）*/
        $var_lang = $request->param('lang/s');
        $var_lang = trim($var_lang, '/');
        $var_lang = trim($var_lang);
        if (!empty($var_lang)) {
            if (!preg_match('/^([a-z]+)$/i', $var_lang)) {
                abort(404,'页面不存在');
            }
        }
        /*end*/

        $lang_switch_on = config('lang_switch_on');
        if (!$lang_switch_on) {
            return true;
        }

        static $language_db = null;
        if (null == $language_db) {
            $language_db = \think\Db::name('language');
        }

        $is_admin = false;
        if (!stristr($request->baseFile(), 'index.php')) {
            $is_admin = true;
            $langCookieVar = \think\Config::get('global.admin_lang');
        } else {
            $langCookieVar = \think\Config::get('global.home_lang');
        }
        \think\Lang::setLangCookieVar($langCookieVar);

        /*单语言执行代码 - 排序不要乱改，影响很大*/
        $langRow = $language_db->field('title,mark,is_home_default')
            ->order('id asc')
            ->cache(true, EYOUCMS_CACHE_TIME, 'language')
            ->select();
        if (1 >= count($langRow)) {
            $langRow = current($langRow);
            $lang = $langRow['mark'];
            \think\Config::set('cache.path', CACHE_PATH.$lang.DS);
            \think\Cookie::set($langCookieVar, $lang);
            cookie('site_info', null);
            return true;
        }
        /*--end*/

        $current_lang = '';
        /*兼容伪静态多语言切换*/
        if (!empty($pathinfo)) {
            $s_arr = explode('/', $pathinfo);
            if ('m' == $s_arr[0]) {
                $s_arr[0] = $s_arr[1];
            }
            $s_arr[0] = addslashes($s_arr[0]);
            $count = $language_db->where(['mark'=>$s_arr[0]])->cache(true, EYOUCMS_CACHE_TIME, 'language')->count();
            if (!empty($count)) {
                $current_lang = $s_arr[0];
            }
        }
        /*--end*/

        /*前后台默认语言*/
        if (empty($current_lang)) {
            if ($is_admin) {
                $current_lang = !empty($langRow[0]['mark']) ? $langRow[0]['mark'] : 'cn';
            } else {
                foreach ($langRow as $key => $val) {
                    if (1 == $val['is_home_default']) {
                        $current_lang = $val['mark'];
                        break;
                    }
                }
                empty($current_lang) && $current_lang = !empty($langRow[0]['mark']) ? $langRow[0]['mark'] : 'cn';
            }
        }
        /*end*/

        $lang = $request->param('lang/s', $current_lang);
        $lang = trim($lang, '/');
        if (!empty($lang)) {
            // 处理访问不存在的语言
            $lang = $language_db->where('mark',$lang)->cache(true, EYOUCMS_CACHE_TIME, 'language')->getField('mark');
        }
        if (empty($lang)) {
            if ($is_admin) {
                $lang = !empty($langRow[0]['mark']) ? $langRow[0]['mark'] : 'cn';
                // $lang = \think\Db::name('language')->order('id asc')->getField('mark');
            } else {
                abort(404,'页面不存在');
            }
        }
        $lang_info = [];
        foreach ($langRow as $key => $val) {
            if ($val['mark'] == $lang) {
                $lang_info['lang_title'] = $val['title'];
                /*单独域名*/
                $inletStr = (1 == config('ey_config.seo_inlet')) ? '' : '/index.php'; // 去掉入口文件
                $url = $val['url'];
                if (empty($url)) {
                    if (1 == $val['is_home_default']) {
                        $url = ROOT_DIR.'/'; // 支持子目录
                    } else {
                        $seoConfig = tpCache('seo', [], $val['mark']);
                        $seo_pseudo = !empty($seoConfig['seo_pseudo']) ? $seoConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
                        if (1 == $seo_pseudo) {
                            $url = $request->domain().ROOT_DIR.$inletStr; // 支持子目录
                            if (!empty($inletStr)) {
                                $url .= '?';
                            } else {
                                $url .= '/?';
                            }
                            $url .= http_build_query(['lang'=>$val['mark']]);
                        } else {
                            $url = ROOT_DIR.$inletStr.'/'.$val['mark']; // 支持子目录
                        }
                    }
                }
                /*--end*/
                $lang_info['lang_url'] = $url;
                $lang_info['lang_logo'] = ROOT_DIR . "/public/static/common/images/language/{$val['mark']}.gif";
                cookie('lang_info', $lang_info);
                break;
            }
        }
        \think\Config::set('cache.path', CACHE_PATH.$lang.DS);
        $pre_lang = \think\Cookie::get($langCookieVar);
        \think\Cookie::set($langCookieVar, $lang);
        if ($pre_lang != $lang) {
            if ($is_admin) {
                \think\Db::name('admin')->where('admin_id', \think\Session::get('admin_id'))->update([
                    'mark_lang' =>  $lang,
                    'update_time'   => getTime(),
                ]);
            }
        }

        $execute_end = true;
    }
}

if (!function_exists('get_default_site')) 
{
    /**
     * 获取默认城市站点
     */
    function get_default_site()
    {
        static $default_site = null;
        if (null === $default_site) {
            $default_site = \think\Config::get('ey_config.site_default_home');
            if (!empty($default_site)) {
                $default_site = \think\Db::name('citysite')->where(['id'=>$default_site])->getField('domain');
            }
        }

        return $default_site;
    }
}

if (!function_exists('get_home_site')) 
{
    /**
     * 获取前台当前城市站点
     */
    function get_home_site()
    {
        static $home_site = null;
        if (null === $home_site) {
            $home_site = input('param.site/s');
            if (empty($home_site)) {
                /*支持独立域名配置*/
                $subDomain = request()->subDomain();
                if (!empty($subDomain) && 'www' != $subDomain) {
                    $siteInfo = \think\Db::name('citysite')->where('domain',$subDomain)->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->find();
                    if (!empty($siteInfo['is_open'])) {
                        $home_site = $siteInfo['domain'];
                    }
                }
                /*--end*/
                empty($home_site) && $home_site = get_default_site();
            }
            $home_site = preg_replace('/([^\w\-\_])/i', '', $home_site);
        }

        return $home_site;
    }
}

if (!function_exists('switch_citysite')) 
{
    /**
     * 多城市切换
     *
     * @return void
     */
    function switch_citysite() 
    {
        static $execute_end = false;
        if (true === $execute_end) {
            return true;
        }

        $request = \think\Request::instance();
        // 忽略后台
        if (!stristr($request->baseFile(), 'index.php')) {
            return true;
        }
        
        $pathinfo = $request->pathinfo();
        /*验证二级域名、路径标识是否合法--- PS：$request->param('site/s','')一定要放在$request->pathinfo()后面，非则会造成分页错误（链接带有"s"变量）*/
        $var_site = $request->param('site/s');
        $var_site = trim($var_site, '/');
        if (!empty($var_site)) {
            if (!preg_match('/^([\w\-\_]+)$/i', $var_site)) {
                abort(404,'页面不存在');
            }
        }
        /*end*/

        $lang_switch_on = config('lang_switch_on');
        $city_switch_on = config('city_switch_on');
        if ($lang_switch_on || !$city_switch_on) {
            return true;
        }

        static $citysite_db = null;
        if (null == $citysite_db) {
            $citysite_db = \think\Db::name('citysite');
        }

        $current_site = '';
        /*兼容伪静态多城市切换*/
        if (!empty($pathinfo)) {
            $s_arr = explode('/', $pathinfo);
            if ('m' == $s_arr[0]) {
                $s_arr[0] = $s_arr[1];
            }
            $s_arr[0] = addslashes($s_arr[0]);
            $count = $citysite_db->where(['domain'=>$s_arr[0]])->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->count();
            if (!empty($count)) {
                $current_site = $s_arr[0];
            }
        }
        /*--end*/

        $site = $request->param('site/s', $current_site);
        $site = trim($site, '/');
        if (!empty($site)) {
            // 处理访问不存在的城市
            $siteInfo = $citysite_db->where('domain',$site)->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->find();
            if (empty($siteInfo['domain'])) {
                abort(404,'页面不存在');
            } else {
                $site_default_home = tpCache('site.site_default_home');
                if ($siteInfo['id'] == $site_default_home) { // 设为默认站点跳转
                    header('Location: '.ROOT_DIR.'/');
                    exit;
                }
            }
        } else {
            $subDomain = $request->subDomain();
            $web_basehost = tpCache('web.web_basehost');
            $web_subDomain = $request->subDomain('', true, $web_basehost);
            if (!empty($subDomain) && !in_array($subDomain, ['www', $web_subDomain])) {
                $siteInfo = $citysite_db->where('domain',$subDomain)->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->find();
                if (!empty($siteInfo['is_open'])) {
                    $site_default_home = tpCache('site.site_default_home');
                    if ($site_default_home == $siteInfo['id']) { // 设为默认站点跳转
                        $domain = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
                        $url = $request->scheme().'://'.$domain;
                        if (stristr($request->host(), ':')) {
                            $url .= ":".$request->port();
                        }
                        $url .= ROOT_DIR;
                        header('Location: '.$url);
                        exit;
                    } else {
                        $site = $siteInfo['domain'];
                    }
                } else {
                    abort(404,'页面不存在');
                }
            }
            empty($site) && $site = 'www';
        }

        \think\Config::set('cache.path', CACHE_PATH.$site.DS);
        $execute_end = true;
    }
}

if (!function_exists('getUsersConfigData')) 
{
    // 专用于获取users_config，会员配置表数据处理。
    // 参数1：必须传入，传入值不同，获取数据不同：
    // 例：获取配置所有数据，传入：all，
    // 获取分组所有数据，传入：分组标识，如：member，
    // 获取分组中的单个数据，传入：分组标识.名称标识，如：users.users_open_register
    // 参数2：data数据，为空则查询，否则为添加或修改。
    // 参数3：多语言标识，为空则获取当前默认语言。
    function getUsersConfigData($config_key,$data=array(),$lang='', $options = null){
        $tableName = 'users_config';
        $table_db = \think\Db::name($tableName);

        $lang = !empty($lang) ? $lang : get_current_lang();
        $param = explode('.', $config_key);
        $cache_inc_type = "{$tableName}-{$lang}-{$param[0]}";
        if (empty($options['path'])) {
            $options['path'] = DATA_PATH.'runtime'.DS.'cache'.DS.$lang.DS;
        }
        if(empty($data)){
            //如$config_key=shop_info则获取网站信息数组
            //如$config_key=shop_info.logo则获取网站logo字符串
            $config = cache($cache_inc_type,'',$options);//直接获取缓存文件
            if(empty($config)){
                //缓存文件不存在就读取数据库
                if ($param[0] == 'all') {
                    $param[0] = 'all';
                    $res = $table_db->where([
                        'lang'  => $lang,
                    ])->select();
                } else {
                    $res = $table_db->where([
                        'inc_type'  => $param[0],
                        'lang'  => $lang,
                    ])->select();
                }
                if($res){
                    foreach($res as $k=>$val){
                        $config[$val['name']] = $val['value'];
                    }
                    cache($cache_inc_type,$config,$options);
                }
            }
            if(!empty($param) && count($param)>1){
                $newKey = strtolower($param[1]);
                return isset($config[$newKey]) ? $config[$newKey] : '';
            }else{
                return $config;
            }
        }else{
            //更新缓存
            $result =  $table_db->where([
                'inc_type'  => $param[0],
                'lang'  => $lang,
            ])->select();
            if($result){
                foreach($result as $val){
                    $temp[$val['name']] = $val['value'];
                }
                $add_data = array();
                foreach ($data as $k=>$v){
                    $newK = strtolower($k);
                    $newArr = array(
                        'name'=>$newK,
                        'value'=>trim($v),
                        'inc_type'=>$param[0],
                        'lang'  => $lang,
                        'update_time'   => getTime(),
                    );
                    if(!isset($temp[$newK])){
                        array_push($add_data, $newArr); //新key数据插入数据库
                    }else{
                        if (strval($v) !== strval($temp[$newK])) {
                            $table_db->where([
                                'name'  => $newK,
                                'lang'  => $lang,
                            ])->save($newArr);//缓存key存在且值有变更新此项
                        }
                    }
                }
                if (!empty($add_data)) {
                    $table_db->insertAll($add_data);
                }
                //更新后的数据库记录
                $newRes = $table_db->where([
                    'inc_type'  => $param[0],
                    'lang'  => $lang,
                ])->select();
                foreach ($newRes as $rs){
                    $newData[$rs['name']] = $rs['value'];
                }
            }else{
                if ($param[0] != 'all') {
                    foreach($data as $k=>$v){
                        $newK = strtolower($k);
                        $newArr[] = array(
                            'name'=>$newK,
                            'value'=>trim($v),
                            'inc_type'=>$param[0],
                            'lang'  => $lang,
                            'update_time'   => getTime(),
                        );
                    }
                    !empty($newArr) && $table_db->insertAll($newArr);
                }
                $newData = $data;
            }

            $result = false;
            $res = $table_db->where([
                'lang'  => $lang,
            ])->select();
            if($res){
                $global = array();
                foreach($res as $k=>$val){
                    $global[$val['name']] = $val['value'];
                }
                $result = cache("{$tableName}-{$lang}-all",$global,$options);
            } 

            if ($param[0] != 'all') {
                $result = cache($cache_inc_type,$newData,$options);
            }
            
            return $result;
        }
    }
}

if (!function_exists('send_email')) 
{
    /**
     * 邮件发送
     * @param $to    接收人
     * @param string $subject   邮件标题
     * @param string $content   邮件内容(html模板渲染后的内容)
     * @param string $scene   使用场景
     * @throws Exception
     * @throws phpmailerException
     */
    function send_email($to='', $subject='', $data=array(), $scene=0, $smtp_config = []){
        // 实例化类库，调用发送邮件
        $emailLogic = new \app\common\logic\EmailLogic($smtp_config);
        $res = $emailLogic->send_email($to, $subject, $data, $scene);
        return $res;
    }
}

if (!function_exists('sendSms')) 
{
    /**
     * 发送短信逻辑
     * @param unknown $scene
     */
    function sendSms($scene, $sender, $params,$unique_id=0,$sms_config = [])
    {
        $smsLogic = new \app\common\logic\SmsLogic($sms_config);
        return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
    }
}

if (!function_exists('get_country_list')){
    /**
     * 获得全部国家列表
     */
    function get_country_list()
    {
        $result = extra_cache('global_get_country_list');
        if ($result == false) {
            $result = \think\Db::name('country')->field('id, name')
                ->where('status',1)
                ->order('sort_order asc')
                ->getAllWithIndex('id');
            extra_cache('global_get_country_list', $result);
        }

        return $result;
    }
}

if (!function_exists('get_country_name')){
    /**
     * 根据国家ID获得国家名称
     */
    function get_country_name($id = 0)
    {
        $result = get_country_list();
        return empty($result[$id]['name']) ? '' : $result[$id]['name'];
    }
}

if (!function_exists('get_region_list')){
    /**
     * 获得全部省份列表
     */
    function get_region_list()
    {
        $result = extra_cache('global_get_region_list');
        if ($result == false) {
            $result = \think\Db::name('region')->field('id, name')->getAllWithIndex('id');
            extra_cache('global_get_region_list', $result);
        }

        return $result;
    }
}

if (!function_exists('get_province_list')){
    /**
     * 获得全部省份列表
     */
    function get_province_list()
    {
        $result = extra_cache('global_get_province_list');
        if ($result == false) {
            $result = \think\Db::name('region')->field('id, name')
                ->where('level',1)
                ->getAllWithIndex('id');
            extra_cache('global_get_province_list', $result);
        }

        return $result;
    }
}

if (!function_exists('get_city_list')){
    /**
     * 获得全部城市列表
     */
    function get_city_list()
    {
        $result = extra_cache('global_get_city_list');
        if ($result == false) {
            $result = \think\Db::name('region')->field('id, name')
                ->where('level',2)
                ->getAllWithIndex('id');
            extra_cache('global_get_city_list', $result);
        }

        return $result;
    }
}

if (!function_exists('get_area_list')){
    /**
     * 获得全部地区列表
     */
    function get_area_list()
    {
        $result = extra_cache('global_get_area_list');
        if ($result == false) {
            $result = \think\Db::name('region')->field('id, name')
                ->where('level',3)
                ->getAllWithIndex('id');
            extra_cache('global_get_area_list', $result);
        }

        return $result;
    }
}

if (!function_exists('get_region_name')){
    /**
     * 根据地区ID获得区域名称
     */
    function get_region_name($id = 0)
    {
        $result = get_region_list();
        return empty($result[$id]['name']) ? '' : $result[$id]['name'];
    }
}

if (!function_exists('get_province_name')){
    /**
     * 根据地区ID获得省份名称
     */
    function get_province_name($id = 0)
    {
        $result = get_province_list();
        return empty($result[$id]['name']) ? '' : $result[$id]['name'];
    }
}

if (!function_exists('get_city_name')){
    /**
     * 根据地区ID获得城市名称
     */
    function get_city_name($id = 0)
    {
        $result = get_city_list();
        return empty($result[$id]['name']) ? '' : $result[$id]['name'];
    }
}

if (!function_exists('get_area_name')){
    /**
     * 根据地区ID获得县区名称
     */
    function get_area_name($id = 0)
    {
        $result = get_area_list();
        return empty($result[$id]['name']) ? '' : $result[$id]['name'];
    }
}

if (!function_exists('get_citysite_list')){
    /**
     * 获得城市站点的全部列表
     */
    function get_citysite_list()
    {
        $result = cache('global_get_citysite_list');
        if (empty($result)) {
            $result = \think\Db::name('citysite')->field('id, name, level, parent_id, topid, domain, initial, is_open')
                ->where(['status'=>1])
                ->order("sort_order asc, id asc")
                ->getAllWithIndex('id');
            cache('global_get_citysite_list', $result, null, 'citysite');
        }

        return $result;
    }
}

if (!function_exists('get_site_province_list')){
    /**
     * 获得城市站点的全部省份列表
     */
    function get_site_province_list()
    {
        $result = cache('global_get_site_province_list');
        if (empty($result)) {
            $result = \think\Db::name('citysite')->field('id, name, domain, parent_id')
                ->where(['level'=>1, 'status'=>1])
                ->order("sort_order asc, id asc")
                ->getAllWithIndex('id');

            cache('global_get_site_province_list', $result, null, 'citysite');
        }
        return $result;
    }
}

if (!function_exists('get_site_city_list')){
    /**
     * 获得城市站点的全部城市列表
     */
    function get_site_city_list()
    {
        $result = cache('global_get_site_city_list');
        if (empty($result)) {
            $result = \think\Db::name('citysite')->field('id, name, parent_id')
                ->where(['level'=>2, 'status'=>1])
                ->order("sort_order asc, id asc")
                ->getAllWithIndex('id');
            cache('global_get_site_city_list', $result, null, 'citysite');
        }

        return $result;
    }
}

if (!function_exists('get_site_area_list')){
    /**
     * 获得城市站点的全部地区列表
     */
    function get_site_area_list()
    {
        $result = cache('global_get_site_area_list');
        if (empty($result)) {
            $result = \think\Db::name('citysite')->field('id, name, parent_id')
                ->where(['level'=>3, 'status'=>1])
                ->order("sort_order asc, id asc")
                ->getAllWithIndex('id');
            cache('global_get_site_area_list', $result, null, 'citysite');
        }

        return $result;
    }
}

if (!function_exists('AddOrderAction')) 
{
    /**
     * 添加订单操作表数据
     * 参数说明：
     * $OrderId       订单ID或订单ID数组
     * $UsersId       会员ID，若不为0，则ActionUsers为0
     * $ActionUsers   操作员ID，为0，表示会员操作，反之则为管理员ID
     * $OrderStatus   操作时，订单当前状态
     * $ExpressStatus 操作时，订单当前物流状态
     * $PayStatus     操作时，订单当前付款状态
     * $ActionDesc    操作描述
     * $ActionNote    操作备注
     * 返回说明：
     * return 无需返回
     */
    function AddOrderAction($OrderId, $UsersId = 0, $ActionUsers = 0, $OrderStatus = 0, $ExpressStatus = 0, $PayStatus = 0, $ActionDesc = '提交订单', $ActionNote = '会员提交订单成功')
    {
        if (is_array($OrderId) && 4 == $OrderStatus) {
            // OrderId为数组并且订单状态为过期，则执行
            foreach ($OrderId as $key => $value) {
                $ActionData[] = [
                    'order_id'       => $value['order_id'],
                    'users_id'       => $UsersId,
                    'action_user'    => $ActionUsers,
                    'order_status'   => $OrderStatus,
                    'express_status' => $ExpressStatus,
                    'pay_status'     => $PayStatus,
                    'action_desc'    => $ActionDesc,
                    'action_note'    => $ActionNote,
                    'lang'           => get_home_lang(),
                    'add_time'       => getTime(),
                ];
            }
            // 批量添加
            M('shop_order_log')->insertAll($ActionData);
        } else if (is_array($OrderId)) {
            // OrderId为数组则执行
            foreach ($OrderId as $key => $value) {
                $ActionData[] = [
                    'order_id'       => is_array($value) && !empty($value['order_id']) ? $value['order_id'] : $value,
                    'users_id'       => $UsersId,
                    'action_user'    => $ActionUsers,
                    'order_status'   => $OrderStatus,
                    'express_status' => $ExpressStatus,
                    'pay_status'     => $PayStatus,
                    'action_desc'    => $ActionDesc,
                    'action_note'    => $ActionNote,
                    'lang'           => get_home_lang(),
                    'add_time'       => getTime(),
                ];
            }
            // 批量添加
            M('shop_order_log')->insertAll($ActionData);
        } else {
            // OrderId不为数组，则执行
            $ActionData = [
                'order_id'       => $OrderId,
                'users_id'       => $UsersId,
                'action_user'    => $ActionUsers,
                'order_status'   => $OrderStatus,
                'express_status' => $ExpressStatus,
                'pay_status'     => $PayStatus,
                'action_desc'    => $ActionDesc,
                'action_note'    => $ActionNote,
                'lang'           => get_home_lang(),
                'add_time'       => getTime(),
            ];
            // 单条添加
            M('shop_order_log')->add($ActionData);
        }
    }
}

if (!function_exists('UsersMoneyAction')) 
{
    /**
     * 添加会员余额明细表
     * 参数说明：
     * $OrderCode  订单编号
     * $UsersId    会员ID
     * $UsersMoney 记录余额
     * $Cause      订单说明
     * 返回说明：
     * return 无需返回
     */
    function UsersMoneyAction($OrderCode = null, $UsersId = null, $UsersMoney = null, $Cause = '订单支付')
    {
        if (empty($OrderCode) || empty($UsersId) || empty($UsersMoney)) return false;
        $Time = getTime();
        /*使用余额支付时，同时添加一条记录到金额明细表*/
        $UsersNewMoney = sprintf("%.2f", $UsersId['users_money'] -= $UsersMoney);
        $MoneyData = [
            'users_id'     => $UsersId['users_id'],
            'money'        => $UsersMoney,
            'users_money'  => $UsersNewMoney,
            'cause'        => $Cause,
            'cause_type'   => 3,
            'status'       => 3,
            'pay_details'  => '',
            'order_number' => $OrderCode,
            'add_time'     => $Time,
            'update_time'  => $Time,
        ];
        M('users_money')->add($MoneyData);
        /* END */
    }
}

if (!function_exists('GetEamilSendData')) 
{
    /**
     * 获取邮箱发送数据
     * 参数说明：
     * $SmtpConfig  后台设置的邮箱配置信息
     * $users       会员数据
     * $sendContent 发送内容
     * $type        发送场景
     * $pay_method  支付方式
     * 返回说明：
     * return 邮箱发送所需参数
     */
    function GetEamilSendData($SmtpConfig = [], $users = [], $sendContent = [], $type = 1, $pay_method = null)
    {
        // 是否传入配置、用户信息、发送内容，缺一则返回结束
        if (empty($SmtpConfig) || empty($users) || empty($sendContent)) return false;
        
        // 根据类型判断场景是否开启并选择发送场景及地址
        if (in_array($type, [1, 3])) {
            // 查询判断是否开启邮件订单提醒
            $send_scene = 1 === intval($type) ? 5 : 20;
            $where = [
                'lang' => get_admin_lang(),
                'send_scene' => $send_scene
            ];
            $SmtpOpen = \think\Db::name('smtp_tpl')->where($where)->getField('is_open');
            
            // 发送给后台，选择邮件配置中的邮箱地址
            $email = !empty($SmtpConfig['smtp_from_eamil']) ? $SmtpConfig['smtp_from_eamil'] : null;
        } else if (in_array($type, [2])) {
            $send_scene = 6;
            $where = [
                'lang' => get_admin_lang(),
                'send_scene' => $send_scene
            ];
            $SmtpOpen = \think\Db::name('smtp_tpl')->where($where)->getField('is_open');
            
            // 发送给用户，选择用户的邮箱地址
            $email = !empty($users['email']) ? $users['email'] : null;
        }

        // 若未开启或邮箱地址不存在则返回结束
        if (empty($SmtpOpen) || empty($email)) return false;

        // 发送接口及内容拼装
        if (!empty($SmtpConfig['smtp_server']) && !empty($SmtpConfig['smtp_user']) && !empty($SmtpConfig['smtp_pwd'])) {
            $Result = [];
            $url = ROOT_DIR . '/index.php?m=user&c=Smtpmail&a=send_email&_ajax=1';
            // 订单(支付、发货)发送信息
            if (in_array($type, [1, 2])) {
                switch ($type) {
                    case '1':
                        $title = '订单支付';
                        break;
                    case '2':
                        $title = '订单发货';
                        break;
                }
                $Result = [
                    'url' => $url,
                    'data' => [
                        'email' => $email,
                        'title' => $title,
                        'type'  => 'order_msg',
                        'scene' => $send_scene,
                        'data'  => [
                            'type' => $type,
                            'nickname' => !empty($users['nickname']) ? $users['nickname'] : $users['username'],
                            'pay_method' => $pay_method,
                            'order_id'   => !empty($sendContent['order_id']) ? $sendContent['order_id'] : '',
                            'order_code' => !empty($sendContent['order_code']) ? $sendContent['order_code'] : '',
                            'service_id' => !empty($sendContent['service_id']) ? $sendContent['service_id'] : ''
                        ],
                    ]
                ];
            }
            // 会员投稿提醒
            else if (in_array($type, [3])) {
                $Result = [
                    'url' => $url,
                    'data' => [
                        'email' => $email,
                        'title' => '投稿提醒',
                        'type'  => 'usersRelease',
                        'scene' => $send_scene,
                        'data'  => [
                            'type' => $type,
                            'nickname' => !empty($users['nickname']) ? $users['nickname'] : $users['username'],
                            'title'    => !empty($sendContent['title']) ? $sendContent['title'] : '',
                            'content'  => !empty($sendContent['seo_description']) ? $sendContent['seo_description'] : '',
                            'add_time' => date('Y-m-d H:i:s', $sendContent['add_time']),
                            'arcrank'  => isset($sendContent['arcrank']) && -1 === intval($sendContent['arcrank']) ? '未审核' : '自动审核',
                        ],
                    ]
                ];
            }
            return $Result;
        }
        return false;
    }
}

if (!function_exists('GetMobileSendData')) 
{
    /**
     * 获取手机发送数据
     * 参数说明：
     * $SmtpConfig  后台设置的短信配置信息
     * $users       会员数据
     * $sendContent 发送内容
     * $type        发送场景
     * $pay_method  支付方式
     * 返回说明：
     * return 手机短信发送所需参数
     */
    function GetMobileSendData($SmsConfig = [], $users = [], $sendContent = [], $type = 1, $pay_method = null)
    {
        // 是否传入配置、用户信息、订单信息，缺一则返回结束
        if (empty($SmsConfig) || empty($users) || empty($sendContent)) return false;
            
        // 查询短信配置中的使用平台
        $sms_type = tpCache('sms.sms_type') ? tpCache('sms.sms_type') : 0;

        // 根据类型判断场景是否开启并选择发送场景及手机号
        if (in_array($type, [1, 3])) {
            // 查询判断是否开启手机订单提醒
            $send_scene = 1 === intval($type) ? 5 : 20;
            $where = [
                'sms_type' => $sms_type,
                'lang' => get_admin_lang(),
                'send_scene' => $send_scene
            ];
            $SmsOpen = \think\Db::name('sms_template')->where($where)->getField('is_open');
            
            // 发送给后台，选择邮件配置中的手机号
            $mobile = !empty($SmsConfig['sms_test_mobile']) ? $SmsConfig['sms_test_mobile'] : null;
        } else if (in_array($type, [2])) {
            $send_scene = 6;
            $where = [
                'sms_type' => $sms_type,
                'lang' => get_admin_lang(),
                'send_scene' => $send_scene
            ];
            $SmsOpen = \think\Db::name('sms_template')->where($where)->getField('is_open');
            
            // 发送给用户，选择用户的手机号
            $mobile = !empty($users['mobile']) ? $users['mobile'] : null;
            if (empty($mobile)) {
                $mobile = \think\Db::name('shop_order')->where('order_code', $sendContent['order_code'])->getField('mobile');
            }
        }

        // 若未开启或手机号不存在则返回结束
        if (empty($SmsOpen) || empty($mobile)) return false;

        // ToSms短信通知插件内置代码 start
        $toSmsBool = false;
        if (file_exists('./weapp/ToSms/model/ToSmsModel.php')) {
            $toSmsModel = new \weapp\ToSms\model\ToSmsModel;
            $toSmsBool = $toSmsModel->GetMobileSendData($sms_type, $SmsConfig);
        }
        // ToSms短信通知插件内置代码 end

        // 发送接口及内容拼装
        if ($toSmsBool === true || ($sms_type == 1 && !empty($SmsConfig['sms_appkey']) && !empty($SmsConfig['sms_secretkey'])) || ($sms_type == 2 && !empty($SmsConfig['sms_appkey_tx']) && !empty($SmsConfig['sms_appid_tx']))) {
            $Result = [];
            $url = ROOT_DIR . '/index.php?m=api&c=Ajax&a=SendMobileCode&_ajax=1';
            if (in_array($type, [1, 2])) {
                switch ($type) {
                    case '1':
                        $title = '订单支付';
                        break;
                    case '2':
                        $title = '订单发货';
                        break;
                }
                $Result = [
                    'url' => $url,
                    'data' => [
                        'mobile' => $mobile,
                        'scene' => $send_scene,
                        'title' => $title,
                        'type'  => 'order_msg',
                        'data'  => [
                            'type' => $type,
                            'nickname' => !empty($users['nickname']) ? $users['nickname'] : $users['username'],
                            'pay_method' => $pay_method,
                            'order_code' => !empty($sendContent['order_code']) ? $sendContent['order_code'] : '',
                        ],
                    ]
                ];
            } else if (in_array($type, [3])) {
                $Result = [
                    'url' => $url,
                    'data' => [
                        'mobile' => $mobile,
                        'scene' => $send_scene,
                        'title' => '投稿提醒',
                        'type'  => 'usersRelease',
                        'data'  => [
                            'type' => $type,
                            'nickname' => !empty($users['nickname']) ? $users['nickname'] : $users['username'],
                            'title'    => !empty($sendContent['title']) ? $sendContent['title'] : '',
                            'content'  => !empty($sendContent['seo_description']) ? $sendContent['seo_description'] : '',
                            'add_time' => date('Y-m-d H:i:s', $sendContent['add_time']),
                            'arcrank'  => isset($sendContent['arcrank']) && -1 === intval($sendContent['arcrank']) ? '未审核' : '自动审核',
                        ],
                    ]
                ];
            }
            return $Result;
        }
        return false;
    }
}

if (!function_exists('download_file')) 
{
    /**
     * 下载文件
     * @param $down_path 文件路径
     * @param $file_mime 文件类型
     */
    function download_file($down_path = '', $file_mime = '', $file_name = '')
    {
        //设置脚本的最大执行时间，设置为0则无时间限制
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');
        @ini_set('max_execution_time', '0');

        $down_path = iconv("utf-8", "gb2312//IGNORE", $down_path);

        /*支持子目录*/
        $down_path = preg_replace('#^(/[/\w\-]+)?(/public/upload/soft/|/uploads/soft/)#i', '$2', $down_path);
        /*--end*/

        // 原文件名下载--部分文件名称下载异常
        $filename = basename($down_path);
        if (!empty($file_name)) {
            $arr = explode('.', $filename);
            $ext = end($arr);
            $arr1 = explode('.', $file_name);
            unset($arr1[count($arr1) - 1]);
            $arr1_str = implode('.', $arr1);
            $arr1_str = !empty($arr1_str) ? implode('.', $arr1) : getTime();
            $filename = $arr1_str.'.'.$ext;
        }

        // 文件上传后系统自定义的文件名下载--目前多种文件名称类型测试均无问题
        // $filename = basename($down_path);
        // if (!empty($file_name)) {
        //     $arr = explode('.', $filename);
        //     $ext = end($arr);
        //     unset($arr[count($arr) - 1]);
        //     $filename = implode('.', $arr).'.'.$ext;
        // }

        //文件大小
        preg_match("/^((\w)*:)?(\/\/).*$/", $down_path, $match);
        if (empty($match)) { // 本地文件
            $filesize = filesize('.'.$down_path);
        } else { // 远程文件
            $header_array = get_headers($down_path, true);
            $filesize = !empty($header_array['Content-Length']) ? $header_array['Content-Length'] : 0;
        }
        //告诉浏览器这是一个文件流格式的文件
        // header("Content-type: ".$file_mime);    
        //因为不知道文件是什么类型的，告诉浏览器输出的是字节流
        header('content-type:application/octet-stream');
        //请求范围的度量单位
        Header("Accept-Ranges: bytes");
        //Content-Length是指定包含于请求或响应中数据的字节长度
        Header("Accept-Length: " . $filesize);
        //用来告诉浏览器，文件是可以当做附件被下载，下载后的文件名称为$filename该变量的值。
        Header("Content-Disposition: attachment; filename=" . basename($filename)); 
        
        //针对大文件，规定每次读取文件的字节数为2MB，直接输出数据
        $read_buffer = 1024 * 1024 * 2; // 2MB
        if (is_http_url($down_path)) {
            $file = fopen($down_path, 'rb');
        } else {
            $file = fopen('.' . $down_path, 'rb');
        }
        //总的缓冲的字节数
        $sum_buffer = 0;
        //只要没到文件尾，就一直读取
        while(!feof($file) && $sum_buffer < $filesize) {
            echo fread($file,$read_buffer);
            $sum_buffer += $read_buffer;
        }
    
        //关闭句柄
        fclose($file);
        exit;
    }
}

if (!function_exists('is_realdomain')) 
{
    /**
     * 简单判断当前访问的域名是否真实
     * @param string $domain 不带协议的域名
     * @return boolean
     */
    function is_realdomain($domain = '')
    {
        $is_real = false;
        $domain = !empty($domain) ? $domain : request()->host();
        if (!preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i', $domain) && 'localhost' != $domain && '127.0.0.1' != serverIP()) {
            $is_real = true;
        }

        return $is_real;
    }
}

if (!function_exists('img_style_wh')) 
{
    /**
     * 追加指定内嵌样式到编辑器内容的img标签，兼容图片自动适应页面
     */
    function img_style_wh($content = '', $title = '')
    {
        if (!empty($content)) {
            
            static $basicConfig = null;
            null === $basicConfig && $basicConfig = tpCache('basic');
            if (empty($basicConfig['basic_img_auto_wh']) && empty($basicConfig['basic_img_alt']) && empty($basicConfig['basic_img_title'])) {
                return $content;
            }

            preg_match_all('/<img.*(\/)?>/iUs', $content, $imginfo);
            $imginfo = !empty($imginfo[0]) ? $imginfo[0] : [];
            if (!empty($imginfo)) {
                $num = 1;
                $appendStyle = "max-width:100%!important;height:auto!important;";
                $title = preg_replace('/("|\')/i', '', $title);
                foreach ($imginfo as $key => $imgstr) {
                    $imgstrNew = $imgstr;
                    if (!stristr($imgstrNew, ' src=')) {
                        continue;
                    }
                    $imgname  = preg_replace("/<img(.*?)src(\s*)=(\s*)[\'|\"](.*?)([^\/\'\"]*)[\'|\"](.*?)[\/]?(\s*)>/i", '${5}', $imgstrNew);
                    $imgname = str_replace('/', '\/', $imgname);

                    // 是否开启图片大小自适应
                    if (!empty($basicConfig['basic_img_auto_wh'])) {
                        if (!stristr($imgstrNew, $appendStyle)) {
                            // 追加style属性
                            $imgstrNew = preg_replace('/style(\s*)=(\s*)[\'|\"]([^\'\"]*)?[\'|\"]/i', 'style="'.$appendStyle.'${3}"', $imgstrNew);
                            if (!preg_match('/<img(.*?)style(\s*)=(\s*)[\'|\"](.*?)[\'|\"](.*?)[\/]?(\s*)>/i', $imgstrNew)) {
                                // 新增style属性
                                $imgstrNew = str_ireplace('<img', "<img style=\"".$appendStyle."\" ", $imgstrNew);
                            }
                        }
                    } else {
                        $imgstrNew = str_ireplace([$appendStyle, $appendStyle], ['', ''], $imgstrNew);
                    }

                    // 移除img中多余的title属性
                    // $imgstrNew = preg_replace('/title(\s*)=(\s*)[\'|\"]([^\'\"]*)[\'|\"]/i', '', $imgstrNew);

                    // 追加alt属性
                    if (!empty($basicConfig['basic_img_alt'])) {
                        $altNew = $title."(".foreign_lang('system1')."{$num})";
                        if (!empty($basicConfig['basic_img_alt_force'])) { // alt强制同步title
                            // 新增alt属性
                            $imgstrNew = preg_replace('/alt(\s*)=(\s*)[\'|\"]([^\'\"]*)[\'|\"]/i', '', $imgstrNew);
                            $imgstrNew = str_ireplace('<img', "<img alt=\"{$altNew}\" ", $imgstrNew);
                        } else {
                            $imgstrNew = preg_replace('/alt(\s*)=(\s*)[\'|\"]('.$imgname.')?[\'|\"]/i', 'alt="'.$altNew.'"', $imgstrNew);
                            if (!preg_match('/<img(.*?)alt(\s*)=(\s*)[\'|\"](.*?)[\'|\"](.*?)[\/]?(\s*)>/i', $imgstrNew)) {
                                // 新增alt属性
                                $imgstrNew = str_ireplace('<img', "<img alt=\"{$altNew}\" ", $imgstrNew);
                            }
                        }
                    }

                    // 追加title属性
                    if (!empty($basicConfig['basic_img_title'])) {
                        $titleNew = $title."(".foreign_lang('system1')."{$num})";
                        $imgstrNew = preg_replace('/title(\s*)=(\s*)[\'|\"]('.$imgname.')?[\'|\"]/i', 'title="'.$titleNew.'"', $imgstrNew);
                        if (!preg_match('/<img(.*?)title(\s*)=(\s*)[\'|\"](.*?)[\'|\"](.*?)[\/]?(\s*)>/i', $imgstrNew)) {
                            // 新增title属性
                            $imgstrNew = str_ireplace('<img', "<img title=\"{$titleNew}\" ", $imgstrNew);
                        }
                    }
                    
                    // 新的img替换旧的img
                    $content = str_ireplace($imgstr, $imgstrNew, $content);
                    $num++;
                }
            }
        }

        return $content;
    }
}

if (!function_exists('get_archives_data')) 
{
    /**
     * 查询文档主表信息和文档栏目表信息整合到一个数组中
     * @param string $array 产品数组信息
     * @param string $id 产品ID，购物车下单页传入aid，订单列表订单详情页传入product_id
     * @return return array_new
     */
    function get_archives_data($array = [], $id = '')
    {
        // 目前定义订单中心和评论中使用
        if (empty($array) || empty($id)) {
            return false;
        }

        static $array_new    = null;
        if (null === $array_new) {
            $aids         = get_arr_column($array, $id);
            $archivesList = \think\Db::name('archives')->field('*')->where('aid','IN',$aids)->select();
            $typeids      = get_arr_column($archivesList, 'typeid');
            $arctypeList  = \think\Db::name('arctype')->field('*')->where('id','IN',$typeids)->getAllWithIndex('id');
            
            foreach ($archivesList as $key2 => $val2) {
                $array_new[$val2['aid']] = array_merge($arctypeList[$val2['typeid']], $val2);
            }
        }

        return $array_new;
    }
}

if (!function_exists('SynchronizeQiniu')) 
{
    /**
     * 参数说明：
     * $images   本地图片地址
     * $Qiniuyun 七牛云插件配置信息
     * $is_tcp 是否携带协议
     * 返回说明：
     * return false 没有配置齐全
     * return true  同步成功
     */
    function SynchronizeQiniu($images,$Qiniuyun=null,$is_tcp=false)
    {
        static $Qiniuyun = null;
        // 若没有传入配信信息则读取数据库
        if (null == $Qiniuyun) {
            // 需要填写你的 Access Key 和 Secret Key
            $data     = M('weapp')->where('code','Qiniuyun')->field('data')->find();
            $Qiniuyun = json_decode($data['data'], true);
        }
        /*支持子目录*/
        $images = preg_replace('#^(/[/\w\-]+)?(/uploads/)#i', '$2', $images);
        // 配置为空则返回原图片路径
        if (empty($Qiniuyun) || empty($Qiniuyun['domain'])) {
            return ROOT_DIR.$images;
        }

        //引入七牛云的相关文件
        weapp_vendor('Qiniu.src.Qiniu.Auth', 'Qiniuyun');
        weapp_vendor('Qiniu.src.Qiniu.Storage.UploadManager', 'Qiniuyun');
        require_once ROOT_PATH.'weapp/Qiniuyun/vendor/Qiniu/autoload.php';

        // 配置信息
        $accessKey = $Qiniuyun['access_key'];
        $secretKey = $Qiniuyun['secret_key'];
        $bucket    = $Qiniuyun['bucket'];
        $domain    = $Qiniuyun['domain'];
        // 构建鉴权对象
        $auth      = new Qiniu\Auth($accessKey, $secretKey);
        // 生成上传 Token
        $token     = $auth->uploadToken($bucket);
        // 要上传文件的本地路径
        $filePath  = realpath('.'.$images);
        // 上传到七牛后保存的文件名
        $key       = ltrim($images, '/');
        // 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new Qiniu\Storage\UploadManager;
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        // list($ret, $err) = $uploadMgr->put($token, $key, $filePath);
        if (empty($err) || $err === null) {
            $tcp = '//';
            if ($is_tcp) {
                $tcp = !empty($Qiniuyun['tcp']) ? $Qiniuyun['tcp'] : '';
                switch ($tcp) {
                    case '2':
                        $tcp = 'https://';
                        break;

                    case '3':
                        $tcp = '//';
                        break;
                    
                    case '1':
                    default:
                        $tcp = 'http://';
                        break;
                }
            }
            $images = $tcp.$domain.'/'.ltrim($images, '/');
        }

        return [
            'state' => 'SUCCESS',
            'url'   => $images,
        ];
    }
}

if (!function_exists('SynImageObjectBucket')) 
{
    /**
     * 同步到第三方对象存储空间
     * 参数说明：
     * $images   本地图片地址
     * $weappList 插件列表
     */
    function SynImageObjectBucket($images = '', $weappList = [], $fileziyuan = [])
    {
        $result = [];
        if (empty($images)) {
            return $result;
        }

        /*支持子目录*/
        $images = preg_replace('#^(/[/\w\-]+)?(/uploads/|/public/static/)#i', '$2', $images);

        if (empty($weappList)) {
            $weappList = \think\Db::name('weapp')->where([
                'status'    => 1,
            ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
            ->getAllWithIndex('code');
        }

        if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
            // 同步图片到七牛云
            $weappConfig = json_decode($weappList['Qiniuyun']['config'], true);
            if (!empty($weappConfig['version']) && 'v1.0.6' <= $weappConfig['version']) {
                $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
                $qiniuyunOssModel = new \weapp\Qiniuyun\model\QiniuyunModel;
                $ResultQny = $qiniuyunOssModel->Synchronize($qnyData, $images);
            } else {
                $ResultQny = SynchronizeQiniu($images);
            }
            // 数据覆盖
            if (!empty($ResultQny) && is_array($ResultQny)) {
                $result['local_save'] = !empty($qnyData['local_save']) ? $qnyData['local_save'] : '';
                $result['state'] = !empty($ResultQny['state']) ? $ResultQny['state'] : '';
                $result['url'] = !empty($ResultQny['url']) ? $ResultQny['url'] : '';
            }
        } else if (!empty($weappList['AliyunOss']) && 1 == $weappList['AliyunOss']['status']) {
            // 同步图片到OSS
            $ossData = json_decode($weappList['AliyunOss']['data'], true);
            $aliyunOssModel = new \weapp\AliyunOss\model\AliyunOssModel;
            $ResultOss = $aliyunOssModel->Synchronize($ossData, $images);
            // 数据覆盖
            if (!empty($ResultOss) && is_array($ResultOss)) {
                $result['local_save'] = !empty($ossData['local_save']) ? $ossData['local_save'] : '';
                $result['state'] = !empty($ResultOss['state']) ? $ResultOss['state'] : '';
                $result['url'] = !empty($ResultOss['url']) ? $ResultOss['url'] : '';
            }
        } else if (!empty($weappList['Cos']) && 1 == $weappList['Cos']['status']) {
            // 同步图片到COS
            $CosData = json_decode($weappList['Cos']['data'], true);
            $cosModel = new \weapp\Cos\model\CosModel;
            $ResultCos = $cosModel->Synchronize($CosData, $images);
            // 数据覆盖
            if (!empty($ResultCos) && is_array($ResultCos)) {
                $result['local_save'] = !empty($CosData['local_save']) ? $CosData['local_save'] : '';
                $result['url'] = !empty($ResultCos['url']) ? $ResultCos['url'] : '';
                $result['state'] = !empty($ResultCos['state']) ? $ResultCos['state'] : '';
            }
        } else if (!empty($weappList['AwsOss']) && 1 == $weappList['AwsOss']['status']) {
            // 同步图片到aws s3
            $awsData = json_decode($weappList['AwsOss']['data'], true);
            $awsModel = new \weapp\AwsOss\model\AwsOssModel;
            $ResultAws = $awsModel->Synchronize($awsData, $images);
            // 数据覆盖
            if (!empty($ResultAws) && is_array($ResultAws)) {
                $result['local_save'] = !empty($awsData['local_save']) ? $awsData['local_save'] : '';
                $result['url'] = !empty($ResultAws['url']) ? $ResultAws['url'] : '';
                $result['state'] = !empty($ResultAws['state']) ? $ResultAws['state'] : '';
            }
        }
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('getWeappObjectBucket')) 
{
    /**
     * 获取第三方对象存储插件的配置信息
     * 参数说明：
     * $weappList 插件列表
     */
    function getWeappObjectBucket()
    {
        $data = [];

        $weappList = \think\Db::name('weapp')->where([
            'status'    => 1,
        ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
        ->getAllWithIndex('code');

        if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
            // 七牛云
            $data = json_decode($weappList['Qiniuyun']['data'], true);
        } else if (!empty($weappList['AliyunOss']) && 1 == $weappList['AliyunOss']['status']) {
            // OSS
            $data = json_decode($weappList['AliyunOss']['data'], true);
        } else if (!empty($weappList['Cos']) && 1 == $weappList['Cos']['status']) {
            // COS
            $data = json_decode($weappList['Cos']['data'], true);
        } else if (!empty($weappList['AwsOss']) && 1 == $weappList['AwsOss']['status']) {
            // 亚马逊S3
            $data = json_decode($weappList['AwsOss']['data'], true);
        }

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('getAllChild')) 
{   
    /**
     * 递归查询所有的子类
     * @param array $arctype_child_all 存放所有的子栏目
     * @param int $id 栏目ID 或 父栏目ID
     * @param int $type 1=栏目，2=文章
     */ 
    function getAllChild(&$arctype_child_all,$id,$type = 1){
        if($type == 1){
            $arctype_child = \think\Db::name('arctype')->where(['is_del'=>0,'status'=>1,'parent_id'=>$id])->getfield('id',true);
        }else{
            $where['is_del'] = 0;
            $where['status'] = 1;
            $where['parent_id'] = $id;
            $where['current_channel'] = array(array('neq',6),array('neq',8));
            $arctype_child = \think\Db::name('arctype')->where($where)->getfield('id',true); 
        }
        
        if(!empty($arctype_child)){
            $arctype_child_all = array_merge($arctype_child_all,$arctype_child);
            for($i=0;$i<count($arctype_child);$i++){
                getAllChild($arctype_child_all,$arctype_child[$i],$type);
            }
        }
    }
}
    
if (!function_exists('getAllChildByList')) 
{   
    /**
     * 生成栏目页面时获取同模型下级
     * @param array $arctype_child_all 存放所有的子栏目
     * @param int $id 栏目ID 或 父栏目ID
     * @param int $current_channel 当前栏目的模型ID
     */ 
    function getAllChildByList(&$arctype_child_all,$id,$current_channel){
        $arctype_child = \think\Db::name('arctype')->where(['is_del'=>0,'status'=>1,'parent_id'=>$id,'current_channel'=>$current_channel])->getfield('id',true);
        if(!empty($arctype_child)){
            $arctype_child_all = array_merge($arctype_child_all,$arctype_child);
            for($i=0;$i<count($arctype_child);$i++){
                getAllChild($arctype_child_all,$arctype_child[$i]);
            }
        }
    }
}

if (!function_exists('getAllChildArctype'))
{
    //递归查询所有的子类
    function getAllChildArctype(&$arctype_child_all,$id){
        $where['a.is_del'] = 0;
        $where['a.status'] = 1;
        $where['a.parent_id'] = $id;
        $arctype_child = \think\Db::name('arctype')->field('c.*, a.*, a.id as typeid')
            ->alias('a')
            ->join('__CHANNELTYPE__ c', 'c.id = a.current_channel', 'LEFT')
            ->where($where)
            ->select();
        if(!empty($arctype_child)){
            $arctype_child_all = array_merge($arctype_child,$arctype_child_all);
            for($i=0;$i<count($arctype_child);$i++){
                getAllChildArctype($arctype_child_all,$arctype_child[$i]['typeid']);
            }
        }
    }
}

if (!function_exists('getAllArctype'))
{
    /*
     * 递归查询所有栏目
     * $home_lang   语言
     * $id          栏目id    存在则获取指定的栏目，不存在获取全部
     * $parent      是否获取下级栏目    true：获取，false：不获取
     * $aid
     */
    function getAllArctype($home_lang,$id,$view_suffix,$parent = true,$aid = 0){
        $map = [];
        if (!empty($id)){
            if (is_array($id)) {
                $map['a.id'] = ['IN', $id];
            } else {
                $map['a.id'] = $id;
            }
        }

        $map['a.lang'] = $home_lang;
        $map['a.is_del'] = 0;
        $map['a.status'] = 1;
        $info = \think\Db::name('arctype')->field('c.*, a.*, a.id as typeid')
            ->alias('a')
            ->join('__CHANNELTYPE__ c', 'c.id = a.current_channel', 'LEFT')
            ->where($map)
            ->order("a.grade desc")
            ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
            ->select();
        if (!empty($id) && $parent && $aid == 0) { // $aid > 0 表示栏目生成不生成子栏目
            getAllChildArctype($info,$id);
        }
        $info = getAllArctypeCount($home_lang,$info,$id,$view_suffix,$aid);
        return $info;
    }
}

if (!function_exists('arctypeAllSub'))
{
    /**
     * 获取所有栏目，并每个栏目都包含所有子栏目，以及自己
     * @param  boolean $self [description]
     * @return [type]        [description]
     */
    function arctypeAllSub($typeid = 0, $self = true)
    {
        $lang = get_current_lang();
        $cacheKey = md5("common_arctypeAllSub_{$typeid}_{$self}_{$lang}");
        $data = cache($cacheKey);
        if (empty($data)) {
            $where = [];
            $where['c.lang']   = $lang; // 多语言 by 小虎哥
            $where['c.is_del'] = 0; // 回收站功能
            $where['c.status'] = 1;
            /*所有栏目分类*/
            $fields = "c.id, c.parent_id, c.current_channel, c.grade";
            $res = $res2 = \think\Db::name('arctype')
                ->field($fields)
                ->alias('c')
                ->where($where)
                ->order('c.grade desc, c.id asc')
                ->select();
            if (empty($res)) return [];

            $data = [];
            foreach ($res as $key => $val) {
                if (in_array($val['current_channel'], [51]) || !empty($val['weapp_code'])) {
                    continue;
                }
                
                // 当前栏目
                if (!isset($data[$val['id']])) {
                    $data[$val['id']] = [$val['id']];
                } else {
                    $data[$val['id']][] = $val['id'];
                    $data[$val['id']] = array_unique($data[$val['id']]);
                }

                // 父级栏目
                if (!isset($data[$val['parent_id']])) {
                    $data[$val['parent_id']] = [$val['parent_id']];
                } else {
                    $data[$val['parent_id']][] = $val['id'];
                }
                if (!empty($data[$val['id']])) {
                    $data[$val['parent_id']] = array_merge($data[$val['parent_id']], $data[$val['id']]);
                }
                $data[$val['parent_id']] = array_unique($data[$val['parent_id']]);
            }
            if (isset($data[0])) unset($data[0]);
            if (false === $self) {
                foreach ($data as $key => $val) {
                    $indx = array_search($key, $val);
                    if (false !== $indx) {
                        unset($val[$indx]);
                    }
                    if (!empty($val)) {
                        $val = array_merge($val);
                    }
                    $data[$key] = $val;
                }
            }

            cache($cacheKey, $data, null, "arctype");
        }

        return !empty($typeid) ? $data[$typeid] : $data;
    }
}

if (!function_exists('getAllArctypeCount'))
{
    /*
     * 获取所有栏目数据条数，所有aid集合
     * 获取需要生成的栏目页的静态文件的个数   缓存到channel_page_total
     */
    function getAllArctypeCount($home_lang,$info,$id = 0,$view_suffix = ".htm",$aid = 0)
    {
        /**
         * 这里统计每个栏目的文档数有两种方法
         * 1、当文档数量少于10W时，执行第一种方法，在循环外部查询一条sql统计出栏目的文档数
         * 2、当文档数量大于10W时，执行第二种方法，在循环里面每次执行一个统计当前栏目的文档数sql
         * @var integer
         */
        $method_mode = 1; // 默认是第一种方法
        $max_aid = \think\Db::name('archives')->max('aid'); // 取出文档的最大数量，已最大文档ID来大概计算
        if ($max_aid > 100000) {
            $method_mode = 2;
        }

        $map_arc = [];
        // 是否更新子栏目
        $seo_upnext = tpCache('seo.seo_upnext');
        $web_stypeid_open = tpCache('web.web_stypeid_open'); // 是否开启副栏目
        if (1 == $method_mode && $id) {
            if (empty($web_stypeid_open)) {
                $map_arc['typeid'] = array('in',get_arr_column($info,'typeid'));
            } else {
                $typeids_tmp = get_arr_column($info,'typeid');
                $typeids_tmp = implode(',', $typeids_tmp);
                $map_arc[] = \think\Db::raw(" ( typeid IN ({$typeids_tmp}) OR CONCAT(',', stypeid, ',') LIKE '%,{$id},%' ) ");
            }
        }
        // 可发布文档列表的频道模型
        static $new_channel = null;
        if (null === $new_channel) {
            $allow_release_channel = config('global.allow_release_channel');
            $arctypeRow = \think\Db::name('arctype')->field('channeltype,current_channel')->select();
            foreach ($arctypeRow as $key => $val) {
                if (in_array($val['channeltype'], $allow_release_channel)) {
                    $new_channel[] = $val['channeltype'];
                }
                if (in_array($val['current_channel'], $allow_release_channel)) {
                    $new_channel[] = $val['current_channel'];
                }
            }
            $new_channel = array_unique($new_channel);
        }
        !empty($new_channel) && $map_arc['a.channel'] = ['IN', $new_channel];
        $map_arc['a.arcrank'] = ['egt', 0];
        $map_arc['a.status'] = 1;
        $map_arc['a.is_del'] = 0;
        $map_arc['a.lang'] = $home_lang;
        /*定时文档显示插件*/
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $map_arc['a.add_time'] = array('elt', getTime()); // 只显当天或之前的文档
            }
        }
        /*end*/

        if (1 == $method_mode) { // 方法1
            $count_type = [];
            $archivesList = \think\Db::name('archives')->alias('a')->field("typeid,stypeid")->where($map_arc)->order('typeid asc')->select();
            foreach ($archivesList as $key => $val) {
                if (!isset($count_type[$val['typeid']])) {
                    $count_type[$val['typeid']] = [
                        'typeid'    => $val['typeid'],
                        'count' => 1,
                    ];
                } else {
                    $count_type[$val['typeid']]['count']++;
                }

                // 开启副栏目
                if (!empty($web_stypeid_open) && !empty($val['stypeid'])) {
                    $stypeids = explode(',', $val['stypeid']);
                    $arr_index = array_search($val['typeid'], $stypeids);
                    if (is_numeric($arr_index) && 0 <= $arr_index) {
                        unset($stypeids[$arr_index]);
                    }
                    foreach ($stypeids as $_k => $_v) {
                        if (!isset($count_type[$_v])) {
                            $count_type[$_v] = [
                                'typeid'    => $_v,
                                'count' => 1,
                            ];
                        } else {
                            $count_type[$_v]['count']++;
                        }
                    }
                }
            }
        }

        $db = new \think\Db;
        $pagetotal = 0;
        $arctypeAllSub = arctypeAllSub(); // 获取所有栏目，并每个栏目都包含所有子栏目，以及自己
        $info2 = $tplData = [];
        $info = convert_arr_key($info,'typeid');
        foreach ($info as $k => $v) {
            //外链
            if ($v['is_part'] == 1 || 'ask' == $v['nid']) {
                $dir = ROOT_PATH . trim($v['dirpath'], '/');
                if (!empty($v['dirpath']) && true == is_dir($dir)) {//判断是否生成过文件夹,文件夹存在则删除
                    deldir_html($dir);
                }
                continue;
            }

            if (1 == $method_mode) { // 方法1
                if (!isset($info[$v['typeid']]['count'])){    //判断当前栏目的count是否已经存在
                    $v['count'] = 0;
                }else{
                    $v['count'] = intval($info[$v['typeid']]['count']);
                }
                if (isset($count_type[$v['typeid']])){    //存在当前栏目个数
                    $v['count'] += $count_type[$v['typeid']]['count'];
                }

                //判断是否存在上级目录，且当前栏目和上级栏目都不是单页，且当前栏目和上级栏目是相同模型，则，把当前栏目的aid和count赋值给父栏目
                if ($v['parent_id'] && !in_array($v['current_channel'], [6,8]) && isset($info[$v['parent_id']]) && $v['current_channel'] == $info[$v['parent_id']]['current_channel']){
                    if (isset($info[$v['parent_id']]['count'])) {
                        $info[$v['parent_id']]['count'] += intval($v['count']);
                    }else{
                        $info[$v['parent_id']]['count'] = intval($v['count']);
                    }
                }
            }

            /**
             * 判断是否需要更新子栏目
             * 1、更新子栏目，正常处理
             * 2、不更新子栏目
             * （1）、选择指定栏目的情况下，判断当前栏目是否为选择栏目，如果是，走正常流程，否则，去掉当前栏目
             * （2）、不选择指定栏目的情况下，判断当前栏目是否为顶级栏目，如果是，走正常流程，否则，去掉当前栏目
             */
            if (empty($seo_upnext) && ( (!empty($id) && $v['typeid'] != $id) || (empty($id) && !empty($v['parent_id'])) ) ){
                continue;
            }

            $tag_attr_arr = [];
            if (!isset($tplData[$v['templist']])) {
                $tpl = !empty($v['templist']) ? str_replace('.'.$view_suffix, '',$v['templist']) : 'lists_'. $v['nid'];
                $template_html = "./template/".TPL_THEME."pc/".$tpl.".htm";
                $content = @file_get_contents($template_html);
                if ($content) {
                    preg_match_all('/\{eyou:list(\s+)?(.*)\}/i', $content, $matchs);
                    if (!empty($matchs[0][0])) {
                        $tag_attr = !empty($matchs[2][0]) ? $matchs[2][0] : '';
                        if (!empty($tag_attr)) {
                            $tag_attr = preg_replace('/([a-z]+)(\s*)=(\s*)([\'|\"]?)([^ \f\n\r\t\v\'\"]+)([\'|\"]?)/i', '${1}=\'${5}\'', $tag_attr); // 属性引导统一设置单引号
                            preg_match_all('/([0-9a-z_-]+)=\'([^\']+)\'/i', $tag_attr, $attr_matchs);
                            $attr_keys = !empty($attr_matchs[1]) ? $attr_matchs[1] : [];
                            $attr_vals = !empty($attr_matchs[2]) ? $attr_matchs[2] : [];
                            if (!empty($attr_keys)) {
                                foreach ($attr_keys as $_ak => $_av) {
                                    $tag_attr_arr[$_av] = $attr_vals[$_ak];
                                }
                                // 每页条数
                                if (!empty($tag_attr_arr['loop'])) $tag_attr_arr['pagesize'] = intval($tag_attr_arr['loop']);
                                $tag_attr_arr['pagesize'] = !empty($tag_attr_arr['pagesize']) ? intval($tag_attr_arr['pagesize']) : 10;
                                // 模型ID
                                if (!empty($tag_attr_arr['modelid'])) $tag_attr_arr['channelid'] = intval($tag_attr_arr['modelid']);
                                // 排序
                                if (empty($tag_attr_arr['ordermode'])) {
                                    if (!empty($tag['orderWay'])) {
                                        $tag_attr_arr['ordermode'] = $tag_attr_arr['orderWay'];
                                    } else {
                                        $tag_attr_arr['ordermode'] = !empty($tag_attr_arr['orderway']) ? $tag_attr_arr['orderway'] : 'desc';
                                    }
                                }
                            }
                        }
                        $tag_attr_arr['orderby'] = !empty($tag_attr_arr['orderby']) ? $tag_attr_arr['orderby'] : "";
                        $tag_attr_arr['ordermode'] = !empty($tag_attr_arr['ordermode']) ? $tag_attr_arr['ordermode'] : "desc";
                        $tplData[$v['templist']] = $tag_attr_arr;
                    } else {
                        $tplData[$v['templist']]['count'] = -1;
                    }
                }
            }
            $tplDataInfo = !empty($tplData[$v['templist']]) ? $tplData[$v['templist']] : [];

            if (2 == $method_mode) { // 方法2
                $map_arc2 = $map_arc;
                if (empty($web_stypeid_open)) { // 没开启副栏目
                    $map_arc2['a.typeid'] = array('in', $arctypeAllSub[$v['typeid']]);
                } else { // 开启副栏目
                    $stypeid_where = "";
                    $typeid_str = implode(',', $arctypeAllSub[$v['typeid']]);
                    foreach ($arctypeAllSub[$v['typeid']] as $_k => $_v) {
                        $stypeid_where .= " OR CONCAT(',', a.stypeid, ',') LIKE '%,{$_v},%' ";
                    }
                    $map_arc2[] = $db::raw(" (a.typeid IN ({$typeid_str}) {$stypeid_where}) ");
                }

                $v['count'] = 0;
                if (!in_array($v['current_channel'], [6,8])) {
                    $v['count'] = $db::name('archives')->alias('a')->where($map_arc2)->count('aid');
                }
            }

            if (in_array($v['current_channel'], [6,8])){
                $v['pagesize'] = 1;
                $v['pagetotal'] = 1;
                $pagetotal += $v['pagetotal'];
            }else{
                if (!empty($tplDataInfo)) {
                    $count = !empty($tplDataInfo['count']) ? $tplDataInfo['count'] : 0;
                    if (-1 == $count) {
                        $v['count'] = 1;
                    } else {
                        $pagesize = !empty($tplDataInfo['pagesize']) ? $tplDataInfo['pagesize'] : 0;
                        $channelid = !empty($tplDataInfo['channelid']) ? $tplDataInfo['channelid'] : 0;
                        if (!empty($channelid)) {
                            $map_arc['a.channel'] = $channelid;
                            if (isset($map_arc['a.typeid'])) {
                                unset($map_arc['a.typeid']);
                            }
                            if (isset($map_arc[0])) {
                                foreach ($map_arc as $_k => $_v) {
                                    if (is_numeric($_k) && stristr($_v, 'stypeid')) {
                                        unset($map_arc[$_k]);
                                    }
                                }
                            }
                            $v['count'] = $db::name('archives')->alias('a')->where($map_arc)->count();
                        }
                        if ($aid) {
                            $orderby = !empty($tplDataInfo['orderby']) ? $tplDataInfo['orderby'] : '';
                            $ordermode = !empty($tplDataInfo['ordermode']) ? $tplDataInfo['ordermode'] : 'desc';
                        }
                    }
                }
                $v['pagesize']  = !empty($pagesize) ? $pagesize : 10;
                $v['pagetotal'] = !empty($v['count']) ? (int)ceil($v['count'] / $v['pagesize']) : 1;
                $pagetotal += $v['pagetotal'];
            }
            $v['orderby'] = !empty($orderby) ? $orderby : "";
            $v['ordermode'] = !empty($ordermode) ? $ordermode : "desc";

            $info2[] = $v;
        }
        return ["info"=>$info2, "pagetotal"=>$pagetotal];
    }
}

/**
 * 删除文件夹
 * @param $dir
 * @return bool
 */
if (!function_exists('deldir_html'))
{
    function deldir_html($dir = '')
    {
        //先删除目录下的文件：
        $fileArr = glob($dir.'/*.html');
        if (!empty($fileArr)) {
            foreach ($fileArr as $key => $val) {
                !empty($val) && @unlink($val);
            }
        }

        $fileArr = glob($dir.'/*');
        if(empty($fileArr)){ //目录为空
            rmdir($dir); // 删除空目录
        }
        return true;
    }
}

/*
 * 以下几个方法为生成静态时使用
 * 获取所有需要生成的文档的aid集合
 * $typeid  栏目id
 * $startid 起始ID（空或0表示从头开始）
 * $endid   结束ID（空或0表示直到结束ID）
 */

if (!function_exists('getAllArchivesAid'))
{
    function getAllArchivesAid($typeid = 0, $home_lang = '', $startid = 0,$endid = 0){
        empty($home_lang) && $home_lang = get_current_lang();
        $map = [];
        if (!empty($typeid)){
            $id_arr = [$typeid];
            getAllChild($id_arr,$typeid,2);
            $map['typeid'] = ['in',$id_arr];
        }
        if (!empty($startid) && !empty($endid)){
            $map['aid'] = ['between',[$startid,$endid]];
        }else if(!empty($startid)){
            $map['aid'] = ['egt',$startid];
        }else if(!empty($endid)){
            $map['aid'] = ['elt',$endid];
        }
        // 可发布文档列表的频道模型
        static $new_channel = null;
        if (null === $new_channel) {
            $allow_release_channel = config('global.allow_release_channel');
            $arctypeRow = \think\Db::name('arctype')->field('channeltype,current_channel')->select();
            foreach ($arctypeRow as $key => $val) {
                if (in_array($val['channeltype'], $allow_release_channel)) {
                    $new_channel[] = $val['channeltype'];
                }
                if (in_array($val['current_channel'], $allow_release_channel)) {
                    $new_channel[] = $val['current_channel'];
                }
            }
            $new_channel = array_unique($new_channel);
        }
        !empty($new_channel) && $map['channel'] = ['IN', $new_channel];
        $map['arcrank'] = ['egt', 0];
        $map['status'] = 1;
        $map['is_del'] = 0;
        $map['lang'] = $home_lang;
        /*定时文档显示插件*/
        if (is_dir('./weapp/TimingTask/')) {
            $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
            if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                $map['add_time'] = array('elt', getTime()); // 只显当天或之前的文档
            }
        }
        /*end*/
        $row = \think\Db::name('archives')
            ->field('aid,typeid,channel')
            ->where($map)
            ->order('aid asc')
            ->select();
        $aid_arr = $typeid_arr = $channel_arr = [];
        foreach ($row as $key => $val) {
            array_push($aid_arr, $val['aid']);
            if (!in_array($val['typeid'], $typeid_arr)) {
                array_push($typeid_arr, $val['typeid']);
            }
            $channel_arr[$val['channel']][] = $val['aid'];
        }

        return [
            'aid_arr'   => $aid_arr,
            'typeid_arr'   => $typeid_arr, // 文档所涉及的栏目ID
            'channel_arr'   => $channel_arr, // 文档以模型ID分组
        ];
    }
}

if (!function_exists('getAllArchives'))
{
    //递归查询所有栏目
    function getAllArchives($home_lang,$id,$aid = ''){
        $map = [];
        if(!empty($aid)){
            if (is_array($aid)) {
                $map['a.aid'] = ['in',$aid];
            } else {
                $map['a.aid'] = $aid;
            }
        }else if (!empty($id)){
            $id_arr = [$id];
            getAllChild($id_arr,$id,2);
            $map['a.typeid'] = ['in',$id_arr];
        }

        // 可发布文档列表的频道模型
        $new_channel = cache("application_common_getAllArchives_new_channel");
        if(empty($new_channel)){
            $new_channel = [];
            $allow_release_channel = config('global.allow_release_channel');
            $arctypeList = \think\Db::name('arctype')->field('channeltype,current_channel')->select();
            foreach ($arctypeList as $key => $val) {
                if (in_array($val['channeltype'], $allow_release_channel)) {
                    $new_channel[] = $val['channeltype'];
                }
                if (in_array($val['current_channel'], $allow_release_channel)) {
                    $new_channel[] = $val['current_channel'];
                }
            }
            $new_channel = array_unique($new_channel);
            cache("application_common_getAllArchives_new_channel", $new_channel, null, 'arctype');
        }
        !empty($new_channel) && $map['a.channel']  = ['IN', $new_channel];

        $map['a.is_jump'] = 0;
        $map['a.status'] = 1;
        $map['a.is_del'] = 0;
        $map['a.lang'] = $home_lang;
        $info = \think\Db::name('archives')->field('a.*')
            ->alias('a')
            ->where($map)
            ->select();
        $info = getAllContent($info);

        /*栏目信息*/
        $arctypeRow = cache("application_common_getAllArchives_arctypeRow");
        if(empty($arctypeRow)){
            $arctypeRow = \think\Db::name('arctype')->field('c.*, a.*, a.id as typeid')
                ->alias('a')
                ->where(['a.lang'=>$home_lang])
                ->join('__CHANNELTYPE__ c', 'c.id = a.current_channel', 'LEFT')
                ->getAllWithIndex('typeid');
            cache("application_common_getAllArchives_arctypeRow", $arctypeRow, null, 'arctype');
        }

        return [
            'info'          => $info,
            'arctypeRow'   => $arctypeRow,
        ];
    }
}

if (!function_exists('getPreviousArchives'))
{
    //获取上一条文章数据
    function getPreviousArchives($home_lang,$id,$aid = 0){
        $map = [];
        if(!empty($aid)){
            $map['a.aid'] = ['lt',$aid];
        }
        if (!empty($id)){
            $id_arr = [$id];
            getAllChild($id_arr,$id,2);
            $map['a.typeid'] = ['in',$id_arr];
        }
        // 可发布文档列表的频道模型
        static $new_channel = null;
        if (null === $new_channel) {
            $allow_release_channel = config('global.allow_release_channel');
            $arctypeRow = \think\Db::name('arctype')->field('channeltype,current_channel')->select();
            foreach ($arctypeRow as $key => $val) {
                if (in_array($val['channeltype'], $allow_release_channel)) {
                    $new_channel[] = $val['channeltype'];
                }
                if (in_array($val['current_channel'], $allow_release_channel)) {
                    $new_channel[] = $val['current_channel'];
                }
            }
            $new_channel = array_unique($new_channel);
        }
        !empty($new_channel) && $map['a.channel']  = ['IN', $new_channel];
        $map['a.lang'] = $home_lang;
        $map['a.is_jump'] = 0;
        $map['a.is_del'] = 0;
        $map['a.status'] = 1;
        $info = \think\Db::name('archives')->field('a.*')
            ->alias('a')
            ->where($map)
            ->order("a.aid desc")
            ->limit(1)
            ->select();
        $info = getAllContent($info);

        /*栏目信息*/
        $arctypeRow = \think\Db::name('arctype')->field('c.*, a.*, a.id as typeid')
            ->alias('a')
            ->where(['a.lang'=>$home_lang])
            ->join('__CHANNELTYPE__ c', 'c.id = a.current_channel', 'LEFT')
            ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
            ->getAllWithIndex('typeid');

        return [
            'info'          => $info,
            'arctypeRow'   => $arctypeRow,
        ];
    }
}

if (!function_exists('getNextArchives'))
{
    //获取下一条文章数据
    function getNextArchives($home_lang,$id,$aid = 0){
        $map = [];
        if(!empty($aid)){
            $map['a.aid'] = ['gt',$aid];
        }
        if (!empty($id)){
            $id_arr = [$id];
            getAllChild($id_arr,$id,2);
            $map['a.typeid'] = ['in',$id_arr];
        }
        // 可发布文档列表的频道模型
        static $new_channel = null;
        if (null === $new_channel) {
            $allow_release_channel = config('global.allow_release_channel');
            $arctypeRow = \think\Db::name('arctype')->field('channeltype,current_channel')->select();
            foreach ($arctypeRow as $key => $val) {
                if (in_array($val['channeltype'], $allow_release_channel)) {
                    $new_channel[] = $val['channeltype'];
                }
                if (in_array($val['current_channel'], $allow_release_channel)) {
                    $new_channel[] = $val['current_channel'];
                }
            }
            $new_channel = array_unique($new_channel);
        }
        !empty($new_channel) && $map['a.channel']  = ['IN', $new_channel];
        $map['a.lang'] = $home_lang;
        $map['a.is_jump'] = 0;
        $map['a.is_del'] = 0;
        $map['a.status'] = 1;
        $info = \think\Db::name('archives')->field('a.*')
            ->alias('a')
            ->where($map)
            ->order("a.aid asc")
            ->limit(1)
            ->select();
        $info = getAllContent($info);

        /*栏目信息*/
        $arctypeRow = \think\Db::name('arctype')->field('c.*, a.*, a.id as typeid')
            ->alias('a')
            ->where(['a.lang'=>$home_lang])
            ->join('__CHANNELTYPE__ c', 'c.id = a.current_channel', 'LEFT')
            ->cache(true,EYOUCMS_CACHE_TIME,"arctype")
            ->getAllWithIndex('typeid');

        return [
            'info'          => $info,
            'arctypeRow'   => $arctypeRow,
        ];
    }
}

if (!function_exists('getAllContent'))
{
    //获取指定文档列表的内容附加表字段值
    function getAllContent($archivesList = [], $field = '*'){
        if (is_dir('./weapp/CompressContent/')) {                
            $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                
        } 
        $contentList = [];
        $db = new \think\Db;
        $channeltype_list = config('global.channeltype_list');
        $arr = group_same_key($archivesList, 'channel');
        foreach ($arr as $nid => $list) {
            $table = array_search($nid, $channeltype_list);
            if (!empty($table)) {
                $aids = get_arr_column($list, 'aid');
                $row = $db::name($table.'_content')->field($field)
                    ->where(['aid'=>['IN', $aids]])
                    ->select();
                $result = [];
                foreach ($row as $_k => $_v) {
                    if (!empty($compressLogic)) {                
                        // 调用逻辑层
                        try {
                            $_v = $compressLogic->decrypt_base64_encode(2,$_v);              
                        } catch (Exception $e) {
                        }                
                    }      
                    unset($_v['id']);
                    unset($_v['add_time']);
                    unset($_v['update_time']);
                    $result[$_v['aid']] = $_v;
                }

                $contentList += $result;
            }
        }

        $firstFieldData = current($contentList);
        foreach ($archivesList as $key => $val) {

            /*文档所属模型是不存在，或已被禁用*/
            $table = array_search($val['channel'], $channeltype_list);
            if (empty($table)) {
                unset($archivesList[$key]);
                continue;
            }
            /*end*/

            /*文档内容表没有记录的特殊情况*/
            if (!isset($contentList[$val['aid']])) {
                $contentList[$val['aid']] = [];
                if (!empty($firstFieldData)) {
                    foreach ($firstFieldData as $k2 => $v2) {
                        if (in_array($k2, ['aid'])) {
                            $contentList[$val['aid']][$k2] = $val[$k2];
                        } else {
                            $contentList[$val['aid']][$k2] = '';
                        }
                    }
                }
            }
            /*end*/
            $val = array_merge($val, $contentList[$val['aid']]);
            $archivesList[$key] = $val;
        }

        return $archivesList;
    }
}

if (!function_exists('getAllTags'))
{
    //递归查询所有栏目内容
    function getAllTags($aid_arr = []){
        $map = [];
        $info = [];
        if (!empty($aid_arr)){
            $map['aid'] = ['in',$aid_arr];
        }
        $result = \think\Db::name('taglist')->field("aid,tag")->where($map)->select();
        if ($result) {
            foreach ($result as $key => $val) {
                if (!isset($info[$val['aid']])) $info[$val['aid']] = array();
                array_push($info[$val['aid']], $val['tag']);
            }
        }

        return $info;
    }
}

if (!function_exists('getAllAttrInfo'))
{
    /**
     * 查询所有文档的其他页面内容
     * @param  array  $channel_aids_arr [以模型ID分组的文档ID]
     * @return [type]                   [description]
     */
    function getAllAttrInfo($channel_aids_arr = []){
        $info = [];
        foreach ($channel_aids_arr as $channel => $aids) {
            if (2 == $channel) {
                $ProductImg = new \app\home\model\ProductImg;
                $info['product_img'] = $ProductImg->getProImg($aids);
                $ProductAttr = new \app\home\model\ProductAttr;
                $info['product_attr'] = $ProductAttr->getProAttr($aids);
            } else if (3 == $channel) {
                $ImagesUpload = new \app\home\model\ImagesUpload;
                $info['images_upload'] = $ImagesUpload->getImgUpload($aids);
            } else if (4 == $channel) {
                $DownloadFile = new \app\home\model\DownloadFile;
                $info['download_file'] = $DownloadFile->getDownFile($aids);
            }
        }
        return $info;
    }
}

if (!function_exists('getOneAttrInfo'))
{
    /**
     * 与getAllAttrInfo方法结合使用
     * @param  array   $info [getAllAttrInfo方法返回的值]
     * @param  integer $aid  [文档ID]
     * @return [type]        [description]
     */
    function getOneAttrInfo($info = [], $aid = 0){
        $arr = [];

        if (isset($info['product_img'][$aid])) {
            $arr['product_img'][$aid] = $info['product_img'][$aid];
        }
        if (isset($info['product_attr'][$aid])) {
            $arr['product_attr'][$aid] = $info['product_attr'][$aid];
        }
        if (isset($info['images_upload'][$aid])) {
            $arr['images_upload'][$aid] = $info['images_upload'][$aid];
        }
        if (isset($info['download_file'][$aid])) {
            $arr['download_file'][$aid] = $info['download_file'][$aid];
        }

        return $arr;
    }
}

if (!function_exists('getOrderBy'))
{
    // 特别注意：如新增排序规则，请加上括号里的内容！！！ (, a.add_time desc)
    //根据tags-list规则，获取查询排序，用于标签文件 TagArclist / TagList
    function getOrderBy($orderby,$ordermode,$isrand=false){
        switch ($orderby) {
            case 'hot':
            case 'click':
                $orderby = "a.click {$ordermode}, a.add_time desc";
                break;
            case 'real_sales':
                $orderby = "a.sales_num {$ordermode}, a.add_time desc";
                break;
            case 'sales_num':
                $orderby = "a.sales_all {$ordermode}, a.add_time desc";
                break;
            case 'users_price':
                $orderby = "a.users_price {$ordermode}, a.add_time desc";
                break;
            case 'id': // 兼容写法
            case 'aid':
                $orderby = "a.aid {$ordermode}";
                break;

            case 'now':
            case 'new': // 兼容写法
            case 'pubdate': // 兼容写法
            case 'add_time':
                $orderby = "a.add_time {$ordermode}";
                break;

            case 'update_time':
                $orderby = "a.update_time {$ordermode}";
                break;

            case 'sortrank': // 兼容写法
            case 'weight': // 兼容写法
            case 'sort_order':
                $orderby = "a.sort_order {$ordermode}, a.add_time desc";
                break;

            case 'rand':
                if (true === $isrand) {
                    $orderby = "rand()";
                } else {
                    $orderby = "a.add_time {$ordermode}";
                }
                break;

            default:
            {
                if (empty($orderby)) {
                    $orderby = 'a.sort_order asc, a.add_time desc';
                } elseif (trim($orderby) != 'rand()') {
                    $orderbyArr = explode(',', $orderby);
                    foreach ($orderbyArr as $key => $val) {
                        $val = trim($val);
                        if (preg_match('/^([a-z]+)\./i', $val) == 0) {
                            $val = 'a.'.$val;
                            $orderbyArr[$key] = $val;
                        }
                    }
                    $orderby = implode(',', $orderbyArr);
                }
                break;
            }
        }

        return $orderby;
    }
}

if (!function_exists('getLocationPages'))
{
    /*
     * 获取当前文章属于栏目第几条
     */
    function getLocationPages($tid,$aid,$order){
        $map_arc = [];
        if (!empty($tid)){
            $id_arr = [$tid];
            getAllChild($id_arr,$tid,2);
            $map_arc['typeid'] = ['in',$id_arr];
        }
        $map_arc['is_del'] = 0;
        $map_arc['status'] = 1;
        $result = \think\Db::name('archives')->alias('a')->field("a.aid")->where($map_arc)->orderRaw($order)->select();

        foreach ($result as $key=>$val){
            if ($aid == $val['aid']){
                return $key + 1;
            }
        }
        return false;
    }
}

if (!function_exists('auto_hide_index')) 
{
    /**
     * URL中隐藏index.php入口文件（适用后台显示前台的URL）
     */
    function auto_hide_index($url, $seo_inlet = null) {
        static $web_adminbasefile = null;
        if (null === $web_adminbasefile) {
            $web_adminbasefile = tpCache('web.web_adminbasefile');
            $web_adminbasefile = !empty($web_adminbasefile) ? $web_adminbasefile : ROOT_DIR.'/login.php'; // 支持子目录
        }
        $url = str_replace($web_adminbasefile, ROOT_DIR.'/index.php', $url); // 支持子目录
        null === $seo_inlet && $seo_inlet = config('ey_config.seo_inlet');
        if (1 == $seo_inlet) {
            $url = str_replace('/index.php/', '/', $url);
        }
        return $url;
    }
}

if (!function_exists('getArchivesField')) 
{
    /**
     * 获取指定文档的字段值
     */
    function getArchivesField($aid = 0, $fieldName = 'aid') {
        $value = '';
        if (0 < intval($aid)) {
            if ('arcurl' == $fieldName) {
                $row = \think\Db::name('archives')->where(['aid'=>$aid])->find();
                $value = get_arcurl($row);
            } else {
                $value = \think\Db::name('archives')->where(['aid'=>$aid])->getField($fieldName);
                if ('litpic' == $fieldName) {
                    $value = handle_subdir_pic($value); // 支持子目录
                }
            }
        }

        return $value;
    }
}

if (!function_exists('GetUsersLatestData')) 
{
    /**
     * 获取登录的会员最新数据
     */
    function GetUsersLatestData($users_id = null)
    {
        $users_id = empty($users_id) ? session('users_id') : $users_id;
        if(!empty($users_id)) {
            // 查询会员数据
            $field = 'b.*, b.discount as level_discount, b.status as level_status, a.*';
            $users = \think\Db::name('users')->field($field)
                ->alias('a')
                ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
                ->where([
                    'a.users_id'        => $users_id,
                    'a.is_activation'   => 1,
                    'a.is_del'          => 0,
                ])->find();
            // 会员不存在则返回空
            if (empty($users)) return false;

            // 如果没有设置会员级别折扣或者已禁用相关会员级别则将会员折扣率设为100(无折扣)
            if (0 === intval($users['discount_type']) || 0 === intval($users['level_status'])) $users['level_discount'] = 100;

            // 删除登录密码及支付密码
            unset($users['paypwd']);
            // 去掉余额小数点多余的0
            $users['users_money'] = unifyPriceHandle($users['users_money']);
            // 头像处理
            $users['head_pic'] = get_head_pic(htmlspecialchars_decode($users['head_pic']), false, $users['sex']);
            // 昵称处理
            $users['nickname'] = empty($users['nickname']) ? $users['username'] : $users['nickname'];
            // 密码为空并且存在openid则表示微信注册登录，密码字段更新为0，可重置密码一次。
            $users['password'] = empty($users['password']) && !empty($users['thirdparty']) ? 1 : 1;
            // 会员级别处理
            $LevelData = [];
            if (intval($users['level_maturity_days']) >= 36600) {
                $users['maturity_code'] = 1;
                $users['maturity_date'] = '终身';
            } else if (0 === intval($users['open_level_time']) && 0 === intval($users['level_maturity_days'])) {
                $users['maturity_code'] = 0;
                $users['maturity_date'] = '未升级会员';
            } else {
                // 计算剩余天数后取整
                $days = $users['open_level_time'] + (intval($users['level_maturity_days']) * 86400);
                $days = ceil(($days - getTime()) / 86400);
                if (0 >= $days) {
                    // 更新会员的级别
                    $LevelData = model('EyouUsers')->UpUsersLevelData($users_id);
                    $users['maturity_code'] = 2;
                    $users['maturity_date'] = '未升级会员';
                } else {
                    $users['maturity_code'] = 3;
                    $users['maturity_date'] = $days . ' 天';
                }
            }

            // 如果安装了分销插件则执行
            if (is_dir('./weapp/DealerPlugin/')) {
                // 开启分销插件则执行
                $data = model('Weapp')->getWeappList('DealerPlugin');
                if (!empty($data['status']) && 1 == $data['status']) {
                    // 如果当前会员有顶级会员则不允许申请成为分销商
                    $users['allowApply'] = (empty($users['is_dealer']) && !empty($users['top_users_id']) && !empty($users['top_dealer_id'])) ? 0 : 1;
                    // 查询分销商信息
                    if (!empty($users['users_id'])) {
                        $where = [
                            'users_id' => $users['users_id'],
                        ];
                        $dealer = \think\Db::name('weapp_dealer')->where($where)->find();
                        $users['dealer'] = !empty($dealer) ? $dealer : false;
                    }
                    // 分销商绑定客户处理
                    if (isMobile()/* && isWeixin()*/) {
                        $dealerParam = input('param.dealerParam/s', '', 'trim');
                        if (!empty($dealerParam)) {
                            cookie('dealerParam', null);
                            session('dealerParam', null);
                            cookie('dealerParam', $dealerParam);
                            session('dealerParam', $dealerParam);
                        } else {
                            $dealerParam = cookie('dealerParam') ? cookie('dealerParam') : session('dealerParam');
                        }
                        if (!empty($dealerParam)) {
                            $dealerPluginLogic = new \app\plugins\logic\DealerPluginLogic($users);
                            $dealerPluginLogic->dealerAction('h5', 'bindUsers');
                        }
                        // 分销插件图标
                        $users['dealer']['dealer_pic'] = get_default_pic($data['config']['litpic']);
                    }
                }
            }

            // 订单核销插件
            $users['verify'] = [];
            $weappInfo = model('ShopPublicHandle')->getWeappVerifyInfo();
            if (!empty($weappInfo)) {
                // 调用订单核销逻辑层方法
                $verifyLogic = new \app\plugins\logic\VerifyLogic($users);
                $users['verify'] = $verifyLogic->getVerifyStaff($weappInfo);
            }

            // 登录赠送积分
            $scoreConfig = getUsersConfigData('score');
            $scoreHandleTimes = \think\Cache::get('scoreHandleTimes_' . $users_id);
            if (empty($scoreHandleTimes) && !empty($scoreConfig['score_login_points_open']) && 1 == $scoreConfig['score_login_points_open'] && !empty($users_id)) {
                // 设置1秒过期
                \think\Cache::set('scoreHandleTimes_' . $users_id, true, 1);

                // 当前时间戳
                $times = getTime();
                // 当前年月日
                $log_time = date('Ymd');
                // 查询会员登录日志
                $usersLoginLog = Db::name('users_login_log')->where(['users_id'=>$users_id])->find();
                // 如果没有日志或日志时间不是今天则执行添加赠送积分，并且更新日志时间
                if (empty($usersLoginLog) || intval($log_time) !== intval($usersLoginLog['log_time'])) {
                    // 登录赠送积分
                    $insert = [
                        'type' => 10,
                        'users_id' => $users_id,
                        'score' => $scoreConfig['score_login_points_value'],
                        'info' => '登录赠送' . $scoreConfig['score_name'],
                        'remark' => '登录赠送' . $scoreConfig['score_name'],
                    ];
                    addConsumObtainScores($insert, 2, true);
                    // 添加或更新登录日志
                    if (!empty($usersLoginLog['log_id'])) {
                        $update = [
                            'users_id' => $users_id,
                            'log_time' => $log_time,
                            'log_count' => Db::Raw('log_count+1'),
                            'update_time' => $times,
                        ];
                        Db::name('users_login_log')->where(['log_id' => $usersLoginLog['log_id']])->update($update);
                    } else {
                        $insert = [
                            'users_id' => $users_id,
                            'log_time' => $log_time,
                            'log_count' => 1,
                            'add_time' => $times,
                            'update_time' => $times,
                        ];
                        Db::name('users_login_log')->insert($insert);
                    }
                    // 增加会员积分
                    $users['scores'] = intval($users['scores']) + intval($scoreConfig['score_login_points_value']);
                }
            }

            // 合并数据
            $LatestData = array_merge($users, $LevelData);

            // 更新session
            session('users', $LatestData);
            session('users_id', $LatestData['users_id']);
            cookie('users_id', $LatestData['users_id']);

            // 返回数据
            return $LatestData;
        } else {
            // session中不存在会员ID则返回空
            session('users_id', null);
            session('users', null);
            cookie('users_id', null);
            return false;
        }
    }
}

if (!function_exists('GetTotalArc')) 
{
    /**
     * 统计栏目文章数
     */
    function GetTotalArc($typeid = 0)
    {
        if (empty($typeid)) {
            return 0;
        } else {
            $cache_key = md5("common-GetTotalArc-{$typeid}");
            $count = cache($cache_key);
            if (empty($count)) {
                if(version_compare(getCmsVersion(),'v1.6.6','>')) {
                    $count = \think\Db::name('arctype')->where(['id'=>$typeid])->value('total_arc');
                    cache($cache_key, $count, null, 'arctype');
                } else {
                    $row = model('Arctype')->getHasChildren($typeid);
                    if (empty($row)) return 0;

                    $typeids = array_keys($row);
                    $allow_release_channel = config('global.allow_release_channel');
                    $condition = [
                        'typeid' => ['IN', $typeids],
                        'channel' => ['IN', $allow_release_channel],
                        'arcrank' => ['gt', -1],
                        'status' => 1,
                        'is_del' => 0,
                    ];
                    /*定时文档显示插件*/
                    if (is_dir('./weapp/TimingTask/')) {
                        $TimingTaskRow = model('Weapp')->getWeappList('TimingTask');
                        if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                            $condition['add_time'] = ['elt', getTime()]; // 只显当天或之前的文档
                        }
                    }
                    /*end*/
                    $count = \think\Db::name('archives')->where($condition)->count('aid');
                    cache($cache_key, $count, null, 'archives');
                }
            }

            return intval($count);
        }
    }
}

if (!function_exists('GetTagIndexRanking')) 
{
    /**
     * 统计栏目文章数
     */
    function GetTagIndexRanking($limit = 5, $field = 'id, tag')
    {
        $order = 'weekcc desc, monthcc desc';
        $limit = '0, ' . $limit;
        $list = \think\Db::name('tagindex')->field($field)->order($order)->limit($limit)->select();

        return $list;
    }
}

if (!function_exists('weapptaglib')) 
{
    /**
     * 通用 - 插件模板标签 
     */
    function weapptaglib($weapp_code = '', $act = '', $vars = [])
    {
        $list = '';
        if (empty($weapp_code) || empty($act) || !is_dir("./weapp/{$weapp_code}/")) {
            return '';
        }
        
        $is_exist = false;
        if (file_exists("./weapp/{$weapp_code}/logic/{$weapp_code}Logic.php")) {
            $class = '\weapp\\'.$weapp_code.'\logic\\'.$weapp_code.'Logic';
            if (method_exists($class, $act)) {
                $is_exist = true;
                $ctl = new $class();
                $list = $ctl->$act($vars);
            }
        }
        if (!$is_exist) {
            $class = '\weapp\\'.$weapp_code.'\controller\\'.$weapp_code;
            $ctl = new $class();
            $list = $ctl->$act($vars);
        }

        return $list;
    }
}

if (!function_exists('rand_username')) 
{
    /**
     * 生成随机用户名，确保唯一性
     */
    function rand_username($username = '', $prefix = 'U', $includenumber = 2)
    {
        if (empty($username)) {
            $username = $prefix . get_rand_str(6, 0, $includenumber);
        }
        $count = \think\Db::name('users')->where('username', $username)->count();
        if (!empty($count)) {
            $username = $prefix . get_rand_str(6, 0, $includenumber);
            return rand_username($username, $prefix, $includenumber);
        }

        return $username;
    }
}

if (!function_exists('update_username')) {
    /**
     * 修改用户名为U+用户id，用户id不足6位补0
     * @param  [type] $users_id    [用户id]
     * @return [type]        [description]
     */
    function update_username($users_id, $is_update = true)
    {
        $username = '';
        if (!empty($users_id)) {
            if (6 > strlen($users_id)) {
                $users_id = sprintf("%06d", $users_id); // 不足6位补0
            }
            $username = 'U'.$users_id;
            $username = rand_username($username);
            if ($is_update) {
                \think\Db::name('users')->where('users_id', $users_id)->update(['username'=>$username,'update_time'=>getTime()]);
            }
        }

        return $username;
    }
}

if (!function_exists('pay_success_logic')) 
{
    /**
     * 支付成功的后置业务逻辑
     */
    function pay_success_logic($users_id = 0, $order_code = '', $pay_details = [], $paycode = 'alipay', $notify = true, $users = [], $config = [])
    {
        $pay_method_arr = config('global.pay_method_arr');

        $where = [
            'order_code' => $order_code,
        ];
        !empty($users_id) && $where['users_id'] = intval($users_id);
        $orderData = \think\Db::name('shop_order')->where($where)->find();

        if (empty($orderData)) {
            return [
                'code'  => 0,
                'msg'   => '该订单不存在！',
            ];
        }
        else if (isset($orderData['order_status']) && 0 === intval($orderData['order_status'])) {
            $saveData = [
                'order_status' => 1,
                'pay_details'  => serialize($pay_details),
                'pay_time'     => getTime(),
                'update_time'  => getTime(),
            ];
            if ('wechat' != $paycode) {
                $saveData['pay_name'] = $paycode;
                $saveData['wechat_pay_type'] = ''; // 清空微信标志
            }
            $where = [
                'order_id' => intval($orderData['order_id']),
                'users_id' => intval($orderData['users_id']),
            ];
            $ret = \think\Db::name('shop_order')->where($where)->update($saveData);
            if (false !== $ret) {
                // 更新订单变量，保存最新数据
                $orderData = array_merge($orderData, $saveData);

                if (!empty($paycode) && isset($pay_method_arr[$paycode])){
                    $orderData['pay_method'] = $pay_method_arr[$paycode];
                    $actionNote = "使用{$pay_method_arr[$paycode]}完成支付";
                } else {
                    $orderData['pay_method'] = '';
                    $actionNote = "完成支付";
                }
                // 添加订单操作记录
                AddOrderAction($orderData['order_id'], $orderData['users_id'], 0, 1, 0, 1, "支付成功", $actionNote);

                // 发送站内信给后台
                SendNotifyMessage($orderData, 5, 1, 0);

                // 余额支付则追加会员余额明细表
                if ('balance' == $paycode) {
                    $users = !empty($users) ? $users : \think\Db::name('users')->find($orderData['users_id']);
                    UsersMoneyRecording($order_code, $users, $orderData['order_amount'], '商品购买', 3);
                }

                // 添加会员积分记录
                if (!empty($orderData['points_shop_order'])) {
                    $where = [
                        'users_id' => $orderData['users_id'],
                        'order_id' => $orderData['order_id']
                    ];
                    $detailsData = \think\Db::name('shop_order_details')->where($where)->getField('data');
                    $detailsData = !empty($detailsData) ? unserialize($detailsData) : [];
                    $pointsGoodsBuyField = !empty($detailsData['pointsGoodsBuyField']) ? json_decode($detailsData['pointsGoodsBuyField'], true) : [];
                    if (!empty($pointsGoodsBuyField['goodsTotalPoints'])) {
                        $insert = [
                            'type' => 11, // 积分商城订单支付
                            'users_id' => $orderData['users_id'],
                            'score' => $pointsGoodsBuyField['goodsTotalPoints'],
                            'info' => '积分商城订单支付',
                            'remark' => '积分商城订单支付',
                        ];
                        addConsumObtainScores($insert, 1, !in_array($paycode, ['balance', 'noNeedPay']) ? true : false);
                    }
                }

                // 统计销售额
                eyou_statistics_data(2);
                eyou_statistics_data(3, $orderData['order_amount']);

                // 虚拟自动发货
                $PayModel = new \app\user\model\Pay;
                $autoSendGoods = $PayModel->afterVirtualProductPay($orderData, $config);

                $data = [];
                if (false === $autoSendGoods && true === $notify) {
                    $users = !empty($users) ? $users : \think\Db::name('users')->find($orderData['users_id']);
                    // 邮箱发送
                    $data['email'] = GetEamilSendData(tpCache('smtp'), $users, $orderData, 1, $paycode);
                    // 手机发送
                    $data['mobile'] = GetMobileSendData(tpCache('sms'), $users, $orderData, 1, $paycode);
                }
                
                if (true !== $notify || 'balance' == $paycode) { // 异步处理
                    // 订单支付通知
                    $params = [
                        'users_id' => $orderData['users_id'],
                        'result_id' => $orderData['order_id'],
                    ];
                    eyou_send_notice(9, $params);
                }

                // 保存微信发货推送表记录(需要物流发货订单)
                if (1 === intval($orderData['logistics_type']) && 1 !== intval($orderData['prom_type']) && 'wechat' === trim($paycode)) {
                    model('ShopPublicHandle')->saveWxShippingInfo($orderData['users_id'], $orderData['order_code'], 2, $config);
                }
                // 推送微信发货推送表记录(核销订单)
                else if (2 === intval($orderData['logistics_type']) && 1 !== intval($orderData['prom_type']) && 'wechat' === trim($paycode)) {
                    model('ShopPublicHandle')->pushWxShippingInfo($orderData['users_id'], $orderData['order_code'], 2, '', $config);
                }

                // 订单操作完成，返回跳转
                $url = url('user/Shop/shop_centre');
                if (true === $autoSendGoods) {
                    $msg = '支付订单完成！';
                } else {
                    $msg = '支付成功，处理订单完成！';
                }
                return [
                    'code'  => 1,
                    'msg'   => $msg,
                    'url'   => $url,
                    'data'  => $data,
                ];
            }
            else {
                return [
                    'code'  => 0,
                    'msg'   => '支付成功，处理订单失败！',
                ];
            }
        }
        else if (1 <= intval($orderData['order_status']) && intval($orderData['order_status']) <= 3) {
            return [
                'code'  => 1,
                'msg'   => '已支付',
            ];
        }
        else if (4 === intval($orderData['order_status'])) {
            return [
                'code'  => 0,
                'msg'   => '该订单已过期！',
            ];
        }
        else {
            return [
                'code'  => 0,
                'msg'   => '该订单不存在或已关闭！',
            ];
        }
    }
}

if (!function_exists('OrderServiceLog')) 
{
    /**
     * 订单服务记录表
     * 参数说明：
     * $ServiceId 订单服务信息ID
     * $OrderId   订单ID
     * $UsersId   会员ID
     * $AdminId   管理员ID
     * $LogNote   记录信息
     * 返回说明：
     * return 无需返回
     */
    function OrderServiceLog($ServiceId = null, $OrderId = null, $UsersId = 0, $AdminId = 0, $LogNote = '会员提交退换货申请')
    {
        if (empty($ServiceId) || empty($OrderId)) return false;
        /*使用余额支付时，同时添加一条记录到金额明细表*/
        $Time = getTime();
        $LogData = [
            'service_id'  => $ServiceId,
            'order_id'    => $OrderId,
            'users_id'    => $UsersId,
            'admin_id'    => $AdminId,
            'log_note'    => empty($LogNote) ? '' : $LogNote,
            'add_time'    => $Time,
            'update_time' => $Time,
        ];
        M('shop_order_service_log')->add($LogData);
        /* END */
    }
}

if (!function_exists('UsersMoneyRecording')) 
{
    /**
     * 添加会员余额明细表
     * 参数说明：
     * $OrderCode  订单编号
     * $Users      会员信息
     * $UsersMoney 记录余额
     * $Cause      订单状态，如过期，取消，退款，退货等
     * 返回说明：
     * return 无需返回
     * $CauseType global.pay_cause_type_arr
     */
    function UsersMoneyRecording($OrderCode = null, $Users = [], $UsersMoney = null, $Cause = '商品退换货', $CauseType = 2)
    {
        if (empty($OrderCode) || empty($Users) || empty($UsersMoney)) return false;
        $Time = getTime();
        $pay_method = '';
        // 使用余额支付时，同时添加一条记录到金额明细表
        if (2 == $CauseType) {
            $Status = 3;
            $Cause = $Cause . '，退还使用余额，订单号：' . $OrderCode;
            $UsersNewMoney = !empty($Users['users_money']) ? sprintf("%.2f", $Users['users_money'] += $UsersMoney) : 0;
            $pay_method = 'balance';
        } else if (3 == $CauseType) {
            $Status = 2;
            $Cause = $Cause . '，使用余额支付，订单号：' . $OrderCode;
            $UsersNewMoney = !empty($Users['users_money']) ? sprintf("%.2f", $Users['users_money'] -= $UsersMoney) : 0;
            $pay_method = 'balance';
        }
        $UsersNewMoney = !empty($Users['users_money']) ? $UsersNewMoney : Db::name('users')->where('users_id', $Users['users_id'])->value('users_money');
        $MoneyData = [
            'users_id'     => $Users['users_id'],
            'money'        => $UsersMoney,
            'users_money'  => $UsersNewMoney,
            'cause'        => $Cause,
            'cause_type'   => $CauseType,
            'status'       => $Status,
            'pay_method'   => $pay_method,
            'pay_details'  => '',
            'order_number' => $OrderCode,
            'add_time'     => $Time,
            'update_time'  => $Time,
        ];
        Db::name('users_money')->insert($MoneyData);
    }
}

if (!function_exists('GetScoreArray')) {
    /**
     * 评价转换星级评分
     */
    function GetScoreArray($total_score = 0)
    {
        $Result = 0;
        if (empty($total_score)) return $Result;
        if (in_array($total_score, [1])) {
            $Result = 5;
        } else if (in_array($total_score, [2])) {
            $Result = 3;
        } else if (in_array($total_score, [3])) {
            $Result = 1;
        }
        return $Result;
    }
}

if (!function_exists('getTrueTypeid')) {
    /**
     * 在typeid传值为目录名称的情况下，获取栏目ID
     */
    function getTrueTypeid($typeid = '')
    {
        /*tid为目录名称的情况下*/
        if (!empty($typeid) && strval($typeid) != strval(intval($typeid))) {
            $typeid = \think\Db::name('arctype')->where([
                    'dirname'   => $typeid,
                    'lang'  => get_current_lang(),
                ])->cache(true,EYOUCMS_CACHE_TIME,"arctype")
                ->getField('id');
        }
        /*--end*/

        return $typeid;
    }
}

if (!function_exists('getTrueAid')) {
    /**
     * 在aid传值为自定义文件名的情况下，获取真实aid
     */
    function getTrueAid($aid = '')
    {
        /*aid为自定义文件名的情况下*/
        if (!empty($aid) && strval($aid) != strval(intval($aid))) {
            $aid = \think\Db::name('archives')->where([
                    'htmlfilename'   => $aid,
                    'lang'  => get_current_lang(),
                ])->cache(true,EYOUCMS_CACHE_TIME,"archives")
                ->getField('aid');
        }
        /*--end*/

        return intval($aid);
    }
}

if (!function_exists('SendNotifyMessage')) 
{
    /**
     * 发送站内信通知
     * 参数说明：
     * $ContentArr  需要存入的通知内容
     * $SendScene   发送来源
     * $UsersID     会员ID
     * $Cause      订单状态，如过期，取消，退款，退货等
     * 返回说明：
     * return 无需返回
     */
    function SendNotifyMessage($GetContentArr = [], $SendScene = 0, $AdminID = 0, $UsersID = 0, $UsersName = null, $data = [])
    {
        // 存储数据为空则返回结束
        if (empty($GetContentArr) || empty($SendScene)) return false;

        // 查询通知模板信息
        $tpl_where = [
            // 'lang' => get_home_lang(),
            'send_scene' => $SendScene
        ];
        $Notice = M('users_notice_tpl')->where($tpl_where)->find();

        $times = getTime();
        $homeLang = get_home_lang();
        // 通知模板存在并且开启则执行
        if (!empty($Notice) && !empty($Notice['tpl_title']) && 1 === intval($Notice['is_open'])) {
            if (in_array($SendScene, [1, 5, 20])) {
                $ContentArr = [];
                // 留言表单
                if (1 === intval($SendScene)) {
                    $ContentArr = $GetContentArr;
                }
                // 订单付款
                else if (5 === intval($SendScene)) {
                    $ContentArr = [
                        '订单编号：' . $GetContentArr['order_code'],
                        '订单总额：' . $GetContentArr['order_amount'],
                        '支付方式：' . $GetContentArr['pay_method'],
                        '手机号：' . $GetContentArr['mobile']
                    ];
                }
                // 会员投稿
                else if (20 === intval($SendScene)) {
                    $arcrank = isset($GetContentArr['arcrank']) && -1 === intval($GetContentArr['arcrank']) ? '未审核' : '自动审核';
                    $ContentArr = [
                        '文档标题：' . $GetContentArr['title'],
                        '文档内容：' . $GetContentArr['seo_description'],
                        '投稿时间：' . date('Y-m-d H:i:s', $GetContentArr['add_time']),
                        '文档审核：' . $arcrank,
                    ];
                }
                $Content = !empty($ContentArr) ? implode('<br/>', $ContentArr) : '';
                $ContentData = [
                    'source'      => $SendScene,
                    'admin_id'    => $AdminID,
                    'users_id'    => $UsersID,
                    'content_title' => $Notice['tpl_title'],
                    'content'     => !empty($Content) ? $Content : '',
                    'is_read'     => 0,
                    'lang'        => $homeLang,
                    'add_time'    => $times,
                    'update_time' => $times
                ];
                if(!empty($data['aid'])) $ContentData['aid'] = $data['aid'];
                M('users_notice_tpl_content')->add($ContentData);
            }
            // 订单发货
            else if (6 === intval($SendScene)) {
                $ContentArr = [
                    '快递公司：' . $GetContentArr['express_name'],
                    '快递单号：' . $GetContentArr['express_order'],
                    '发货时间：' . date('Y-m-d H:i:s', $GetContentArr['express_time']),
                ];
                $Content = !empty($ContentArr) ? implode('<br/>', $ContentArr) : '';
                $ContentData = [
                    'title'       => $Notice['tpl_title'],
                    'users_id'    => $UsersID,
                    'usernames'   => $UsersName,
                    'remark'      => $Content,
                    'lang'        => $homeLang,
                    'add_time'    => $times,
                    'update_time' => $times
                ];
                M('users_notice')->add($ContentData);
            }
            // 会员升级
            else if (21 === intval($SendScene)) {
                $content = '您已消费'.$GetContentArr['total_amount'].'元，会员级别已升级为'.$GetContentArr['level_name'].'(终身)';
                $insert = [
                    'title'       => $Notice['tpl_title'],
                    'users_id'    => $UsersID,
                    'usernames'   => $UsersName,
                    'remark'      => $content,
                    'lang'        => $homeLang,
                    'add_time'    => $times,
                    'update_time' => $times
                ];
                M('users_notice')->insert($insert);
            }
        }
    }
}

if (!function_exists('usershomeurl')) 
{
    /**
     * 个人主页URL
     * @param  [type] $users_id [description]
     * @return [type]           [description]
     */
    function usershomeurl($users_id)
    {
        $usershomeurl = '';
        static $is_users_weapp = null;
        static $users_seo_pseudo = 1;
        if (is_dir('./weapp/Users/') && null === $is_users_weapp) {
            $weappInfo = \think\Db::name('weapp')->field('data,status')->where(['code' => 'Users'])->find();
            if (!empty($weappInfo['status'])) {
                $is_users_weapp = true;
                $weappInfo['data'] = unserialize($weappInfo['data']);
                $users_seo_pseudo = !empty($weappInfo['data']['seo_pseudo']) ? intval($weappInfo['data']['seo_pseudo']) : 1;
            }
        }

        if (true === $is_users_weapp) {
            $usershomeurl = url('plugins/Users/userask', ['id'=>$users_id], true, false, $users_seo_pseudo);
        }

        return $usershomeurl;
    }
}

if (!function_exists('restric_type_logic')) {
    /**
     * 文章模型 付费限制模式与之前三个字段 arc_level_id、 users_price、 users_free 组合逻辑兼容
     * 下载模型/视频模型 付费限制模式与之前三个字段 arc_level_id、 users_price、 no_vip_pay 组合逻辑兼容
     * @param array $post [description]
     */
    function restric_type_logic(&$post = [], $channel = 0)
    {
        if (isset($post['restric_type'])) {
            if (!isset($post['no_vip_pay'])) $post['no_vip_pay'] = 0;
            // 文章模型 、 视频模型
            if (in_array($channel, [1,5])) {
                if (empty($post['restric_type'])) { // 免费
                    $post['arc_level_id'] = 0;
                    $post['users_price'] = 0;
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0;
                } else if (1 == $post['restric_type']) { // 付费
                    $post['arc_level_id'] = 0;
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0;
                    if (empty($post['users_price']) || $post['users_price'] == 0) {
                        return ['code' => 0, 'msg' => '购买价格不能为空！'];
                    }
                } else if (2 == $post['restric_type']) { // 指定会员
                    if (!empty($post['no_vip_pay'])) {
                        if (empty($post['users_price']) || $post['users_price'] == 0) {
                            return ['code' => 0, 'msg' => '购买价格不能为空！'];
                        }
                        if ($post['arc_level_id'] > 0) {
                            $post['users_free'] = 1;
                        }
                    } else {
                        if ($post['arc_level_id'] > 0) {
                            $post['users_price'] = 0;
                            $post['users_free'] = 1;
                        }
                    }
                } else if (3 == $post['restric_type']) { // 会员付费
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0;
                    if (empty($post['users_price']) || $post['users_price'] == 0) {
                        return ['code' => 0, 'msg' => '购买价格不能为空！'];
                    }
                }
            }
            // 下载模型
            else if (4 == $channel) {
                if (empty($post['restric_type'])) { // 免费
                    $post['arc_level_id'] = 0;
                    $post['users_price'] = 0;
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0; // 用不上
                } else if (1 == $post['restric_type']) { // 付费
                    $post['arc_level_id'] = 0;
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0; // 用不上
                    if (empty($post['users_price']) || $post['users_price'] == 0) {
                        return ['code' => 0, 'msg' => '购买价格不能为空！'];
                    }
                } else if (2 == $post['restric_type']) { // 指定会员
                    if (!empty($post['no_vip_pay'])) {
                        if (empty($post['users_price']) || $post['users_price'] == 0) {
                            return ['code' => 0, 'msg' => '购买价格不能为空！'];
                        }
                        if ($post['arc_level_id'] > 0) {
                            $post['users_free'] = 1; // 用不上
                        }
                    } else {
                        if ($post['arc_level_id'] > 0) {
                            $post['users_price'] = 0;
                            $post['users_free'] = 1; // 用不上
                        }
                    }
                } else if (3 == $post['restric_type']) { // 会员付费
                    $post['no_vip_pay'] = 0;
                    $post['users_free'] = 0; // 用不上
                    if (empty($post['users_price']) || $post['users_price'] == 0) {
                        return ['code' => 0, 'msg' => '购买价格不能为空！'];
                    }
                }
            }
        }
        
        return true;
    }
}

if (!function_exists('clear_session_file')) 
{
    /**
     * 清理过期的data/session文件
     * @param array $post [description]
     */
    function clear_session_file()
    {
        $path = \think\Config::get('session.path');
        if (!empty($path) && file_exists($path)) {
            if ('data/session' != $path && is_dir('data/session')) {
                delFile('./data/session', true);
            }

            $time = getTime();
            $web_login_expiretime = tpCache('web.web_login_expiretime');
            empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
            $files = glob($path.'/sess_*');
            foreach ($files as $key => $file) {
                clearstatcache(); // 清除文件状态缓存
                $filemtime = filemtime($file);
                if (false === $filemtime) {
                    $filemtime = $time;
                }
                $filesize = filesize($file);
                if (false === $filesize) {
                    $filesize = 1;
                }
                if (empty($filesize) || (($time - $filemtime) > ($web_login_expiretime + 300))) {
                    $referurl = '';
                    if (isset($_SERVER['HTTP_REFERER'])) {
                        $referurl = $_SERVER['HTTP_REFERER'];
                    }
                    @unlink($file);
                }
            }
        }
    }
}

if (!function_exists('func_thumb_img')) 
{
    /**
     * 压缩图 - 从原始图来处理出来
     * @param type $original_img  图片路径
     * @param type $width     生成缩略图的宽度
     * @param type $height    生成缩略图的高度
     * @param type $quality   压缩系数
     */
    function func_thumb_img($original_img = '', $width = '', $height = '', $quality = 75)
    {
        // 缩略图配置
        static $thumbextra = null;
        static $thumbConfig = null;
        if (null === $thumbextra) {
            @ini_set('memory_limit', '-1'); // 内存不限制，防止图片大小过大，导致缩略图处理失败，网站打不开
            $thumbConfig = tpCache('thumb');
            $thumbextra = config('global.thumb');
            empty($thumbConfig['thumb_width']) && $thumbConfig['thumb_width'] = $thumbextra['width'];
            empty($thumbConfig['thumb_height']) && $thumbConfig['thumb_height'] = $thumbextra['height'];
        }

        $c_width = !empty($width) ? intval($width) : intval($thumbConfig['thumb_width']);
        $c_height = !empty($height) ? intval($height) : intval($thumbConfig['thumb_height']);
        if ((empty($c_width) && empty($c_height)) || stristr($original_img,'.gif')) {
            return $original_img;
        }

        $original_img_new = handle_subdir_pic($original_img, 'img', false, true);
        $original_img_new = trim($original_img_new, '/');

        //获取图像信息
        $info = @getimagesize('./'.$original_img_new);
        $img_width = !empty($info[0]) ? intval($info[0]) : 0;
        $img_height = !empty($info[1]) ? intval($info[1]) : 0;

        // 过滤实际图片大小比设置最大宽高小的，直接忽视
        if (!empty($img_width) && !empty($img_height) && $img_width <= $c_width && $img_height <= $c_height) {
            return $original_img;
        }

        //检测图像合法性
        if (false === $info || (IMAGETYPE_GIF === $info[2] && empty($info['bits']))) {
            return $original_img;
        } else {
            if (!empty($info['mime']) && stristr($info['mime'], 'bmp') && version_compare(PHP_VERSION,'7.2.0','<')) {
                return $original_img;
            }
        }

        try {
            vendor('topthink.think-image.src.Image');
            vendor('topthink.think-image.src.image.Exception');
            $image = \think\Image::open('./'.$original_img_new);
            $image->thumb($c_width, $c_height, 1)->save($original_img_new, NULL, $quality); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        } catch (think\Exception $e) {}

        return $original_img;
    }
}

if (!function_exists('pc_to_mobile_url')) 
{
    /**
     * 生成静态模式下且PC和移动端模板分离，自动获取移动端URL
     * @access public
     */
    function pc_to_mobile_url($pageurl = '', $tid = '', $aid = '')
    {
        $url = '';
        $webData = tpCache('web');
        $seo_pseudo = tpCache('seo.seo_pseudo');
        if (file_exists('./template/'.TPL_THEME.'mobile')) { // 分离式模板

            $domain = request()->host(true);

            /*是否开启手机站域名，并且配置*/
            if (!empty($webData['web_mobile_domain_open']) && !empty($webData['web_mobile_domain'])) {
                $domain = $webData['web_mobile_domain'] . '.' . request()->rootDomain();
            }
            /*end*/

            if (!empty($aid)) { // 内容页
                $url = url('home/View/index', ['aid' => $aid], true, $domain, 1, 1, 0);
            } else if (!empty($tid)) { // 列表页
                $url = url('home/Lists/index', ['tid' => $tid], true, $domain, 1, 1, 0);
            } else { // 首页
                $url = request()->scheme().'://'. $domain . ROOT_DIR . '/';
                if ($seo_pseudo == 2) {
                    $url .= 'index.php';
                }
            }
        } else { // 响应式模板
            // 开启手机站域名，且配置
            if (!empty($webData['web_mobile_domain_open']) && !empty($webData['web_mobile_domain'])) {
                if (empty($pageurl)) {
                    $url = request()->subDomain($webData['web_mobile_domain']) . ROOT_DIR . '/';
                    if ($seo_pseudo == 2) {
                        $url .= 'index.php';
                    }
                } else {
                    $url = !preg_match('/^(http(s?):)?\/\/(.*)$/i', $pageurl) ? request()->domain() . $pageurl : $pageurl;
                    $url = preg_replace('/^(.*)(\/\/)([^\/]*)(\.?)(' . request()->rootDomain() . ')(.*)$/i', '${1}${2}' . $webData['web_mobile_domain'] . '.${5}${6}', $url);
                }
            }
        }

        return $url;
    }
}

if (!function_exists('GetSortData')) 
{
    /**
     * list/arclist标签的排序处理
     * @param string $orderby [description]
     * @param array  $Param   [description]
     */
    function GetSortData($orderby = '', $Param = [])
    {
        if (empty($Param)) {
            $Param = request()->param();
        }

        $sort_asc = !empty($Param['sort_asc']) ? $Param['sort_asc'] : 'desc';
        
        if (!empty($Param['sort']) && 'sales' == $Param['sort']) {
            $orderby = 'a.sales_all ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'price' == $Param['sort']) {
            $orderby = 'a.users_price ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'appraise' == $Param['sort']) {
            $orderby = 'a.appraise ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'new' == $Param['sort']) {
            $orderby = 'a.add_time ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'collection' == $Param['sort']) {
            $orderby = 'a.collection ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'click' == $Param['sort']) {
            $orderby = 'a.click ' . $sort_asc . ', ' . $orderby;
        } else if (!empty($Param['sort']) && 'download' == $Param['sort']) {
            $orderby = 'a.downcount ' . $sort_asc . ', ' . $orderby;
        }
        return $orderby;
    }
}

if (!function_exists('set_tagseotitle')) 
{
    /**
     * 设置Tag标题
     */
    function set_tagseotitle($tag = '', $seo_title = '', $site_info = [])
    {
        $page = I('param.page/d', 1);
        static $lang = null;
        $lang === null && $lang = get_home_lang();
        static $seoConfig = null;
        null === $seoConfig && $seoConfig = tpCache('seo');
        $seo_title_symbol = isset($seoConfig['seo_title_symbol']) ? htmlspecialchars_decode($seoConfig['seo_title_symbol']) : '_';
        if (empty($seo_title)) { // 针对没有自定义SEO标题的Tag
            $web_name = tpCache('web.web_name');
            if ($page > 1) {
                if (in_array($lang, ['cn'])) {
                    $tag .= "{$seo_title_symbol}第{$page}页";
                } else {
                    $tag .= "{$seo_title_symbol}{$page}";
                }
            }
            $seo_title = $tag.'_'.$web_name;
        } else {
            if ($page > 1) {
                if (in_array($lang, ['cn'])) {
                    $seo_title .= "{$seo_title_symbol}第{$page}页";
                } else {
                    $seo_title .= "{$seo_title_symbol}{$page}";
                }
            }
        }

        // 城市分站的seo
        if (empty($site_info)) {
            $site_info = cookie('site_info');
            $site_info = json_decode($site_info, true);
        }
        $seo_title = site_seo_handle($seo_title, $site_info);

        return $seo_title;
    }
}
if (!function_exists('spellLabel'))
{
    /**
     * 会员价格拼标签
     */
    function spellLabel( $value = '' )
    {
        $value = '<span id="users_price_1640658971">'.$value.'</span>';
        return $value;
    }
}

if (!function_exists('get_discount_price'))
{
    /**
     * 获取会员折扣价格
     */
    function get_discount_price($users = [], $price = 0)
    {
        if (0 < $price) {
            // 计算折扣率
            $discountPrice = 1;
            if (isset($users['level_discount']) && !empty($users['level_status'])) $discountPrice = $users['level_discount'] / 100;
            // 计算折扣价
            $price = unifyPriceHandle($price * $discountPrice);
        }
        return $price;
    }
}

if (!function_exists('site_seo_handle'))
{
    /**
     * 转换多站点的区域seo标题、关键字、描述
     */
    function site_seo_handle($value = '', $site_info = [])
    {
        if (!empty($value)) {
            if (!config('city_switch_on')) {
                $value = str_ireplace(['{region}','{regionAll}','{parent}','{top}'], '', $value);
            } else {
                if (empty($site_info)) {
                    $site_info = [
                        'name'  => '',
                        'topid' => 0,
                        'parent_id' => 0,
                        'level' => 0,
                    ];
                }

                if (stristr($value, "{region}")) {
                    $name = !empty($site_info['name']) ? $site_info['name'] : '';
                    $value = str_ireplace('{region}', $name, $value);
                }
                if (stristr($value, "{regionAll}") || stristr($value, "{parent}") || stristr($value, "{top}")) {
                    static $citysiteList = null;
                    if (null === $citysiteList) {
                        $citysiteList = get_citysite_list();
                    }

                    $topName = !empty($citysiteList[$site_info['topid']]) ? $citysiteList[$site_info['topid']]['name'] : '';
                    $parentName = !empty($citysiteList[$site_info['parent_id']]) ? $citysiteList[$site_info['parent_id']]['name'] : '';
                    if (1 == $site_info['level']) {
                        $topName = $parentName = '';
                    } else if (2 == $site_info['level']) {
                        $topName = '';
                    } else {
                        $topName = !empty($citysiteList[$site_info['topid']]) ? $citysiteList[$site_info['topid']]['name'] : '';
                        $parentName = !empty($citysiteList[$site_info['parent_id']]) ? $citysiteList[$site_info['parent_id']]['name'] : '';
                    }
                    $regionAll = $topName.$parentName.$site_info['name'];
                    $value = str_ireplace(['{parent}','{top}','{regionAll}'], [$parentName,$topName,$regionAll], $value);
                }
            }
        }
        return $value;
    }
}

if (!function_exists('adminLoginAfter')) {
    /**
     * 管理员登录成功后的后置业务逻辑
     * @return [type] [description]
     */
    function adminLoginAfter($admin_id = 0, $session_id = '', $third_type = '')
    {
        if (!empty($admin_id)) {
            $admin_info = \think\Db::name('admin')->where(['admin_id'=>$admin_id])->find();
            $role_id = !empty($admin_info['role_id']) ? $admin_info['role_id'] : -1;
            $auth_role_info = array();
            $is_founder = 0;
            if (!empty($admin_info['parent_id'])) {
                $role_name = '超级管理员';
            } else {
                $is_founder = 1;
                $role_name = '创始人';
            }
            $admin_info['is_founder'] = $is_founder;

            if (0 < intval($role_id)) {
                $auth_role_info = \think\Db::name('auth_role')
                    ->field("a.*, a.name AS role_name")
                    ->alias('a')
                    ->where('a.id','eq', $role_id)
                    ->find();
                if (!empty($auth_role_info)) {
                    $auth_role_info['language'] = unserialize($auth_role_info['language']);
                    $auth_role_info['cud'] = unserialize($auth_role_info['cud']);
                    $auth_role_info['permission'] = unserialize($auth_role_info['permission']);
                    $role_name = $auth_role_info['name'];
                }
            }
            $admin_info['auth_role_info'] = $auth_role_info;
            $admin_info['role_name'] = $role_name;

            $last_login_time = getTime();
            $last_login_ip = clientIP();
            $saveData = [
                'last_login'    => $last_login_time, 
                'last_ip'       => $last_login_ip, 
                'login_cnt'     => $admin_info['login_cnt'] + 1, 
                'session_id'    => $session_id,
            ];
            \think\Db::name('admin')->where(['admin_id'=>$admin_info['admin_id']])->save($saveData);
            $admin_info['last_login'] = $last_login_time;
            $admin_info['last_ip'] = $last_login_ip;

            // 头像
            empty($admin_info['head_pic']) && $admin_info['head_pic'] = get_head_pic($admin_info['head_pic'], true);

            // 多语言
            $langRow = \think\Db::name('language')->order('id asc')
                ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                ->select();

            // 重置登录错误次数
            $login_errnum_key = 'adminlogin_'.md5('login_errnum_'.$admin_info['user_name'].$last_login_ip);
            $login_errtime_key = 'adminlogin_'.md5('login_errtime_'.$admin_info['user_name'].$last_login_ip);
            $login_lock_key = 'adminlogin_'.md5('login_lock_'.$admin_info['user_name'].$last_login_ip); // 是否被锁定
            foreach ($langRow as $key => $val) {
                tpSetting('adminlogin', [$login_errnum_key=>0, $login_errtime_key=>0, $login_lock_key=>0], $val['mark']);
            }

            // 二次安全验证 - 每次登录后，如果没设置同IP不验证，则清除答案验证成功的IP记录
            $security = tpSetting('security');
            if (isset($security['security_ask_ip_open']) && empty($security['security_ask_ip_open'])) {
                foreach ($langRow as $key => $val) {
                    tpSetting('security', ['security_answerverify_ip'=>''], $val['mark']);
                }
            }

            // 第三方扫码登录
            if (in_array($third_type, ['WechatLogin','EyouGzhLogin','userGzhLogin'])) {
                $map = ['admin_id'=>$admin_id];
                if ('EyouGzhLogin' == $third_type) {
                    $map['type'] = 1;
                } else if ('WechatLogin' == $third_type) {
                    $map['type'] = 2;
                } else if ('userGzhLogin' == $third_type) {
                    $map['type'] = 3;
                }
                $admin_info['openid'] = \think\Db::name('admin_wxlogin')->where($map)->value('openid');
            }

            $admin_info_new = $admin_info;
            /*过滤存储在session文件的敏感信息*/
            unset($admin_info['password']);
            foreach (['user_name','true_name','password'] as $key => $val) {
                unset($admin_info_new[$val]);
            }
            /*--end*/

            // 保存后台session
            session('admin_id', $admin_info['admin_id']);
            session('admin_info', $admin_info_new);
            session('admin_login_expire', getTime()); // 登录有效期
            cookie('from_url', null);

            // Im客服系统
            if (is_dir('./weapp/Im/') && file_exists('./application/plugins/logic/ImLogic.php')) {
                try {
                    $imLogic = new \app\plugins\logic\ImLogic;
                    $imLogic->opt_admin_token('save', $admin_id);
                } catch (\Exception $e) {
                    
                }
            }

            return $admin_info;
        }
        else {
            session('admin_id', null);
            session('admin_info', null);
            session('admin_login_expire', null);
            return false;
        }
    }
}

if (!function_exists('adminLogoutAfter')) {
    /**
     * 管理员退出成功的前置业务逻辑
     * @return [type] [description]
     */
    function adminLogoutAfter($admin_id = 0)
    {
        cookie('from_url', null);
        
        // Im客服系统
        if (is_dir('./weapp/Im/') && file_exists('./application/plugins/logic/ImLogic.php')) {
            try {
                $imLogic = new \app\plugins\logic\ImLogic;
                $imLogic->opt_admin_token('save', $admin_id, '');
            } catch (\Exception $e) {
                
            }
        }
    }
}

if (!function_exists('get_form_read_value')){
    /**
     * 表单数据类型转换
     * $field_value     字段值
     * $field_type      字段类型
     *  $domain          图片文件是否完整链接
     */
    function get_form_read_value($field_value,$field_type,$domain = false){
        static $region_arr = null;
        if (null === $region_arr) {
            $region_arr = get_region_list();
        }

        if ('checkbox' == $field_type && !empty($field_value)) {
            $field_value = str_replace(',', '] [', '['.$field_value.']');
        }else if('region' == $field_type && !empty($field_value)){
            if (is_string($field_value)) {
                $field_value_arr = explode(',', $field_value);
            } else {
                $field_value_arr = $field_value;
            }

            $attr_value = [];
            foreach ($field_value_arr as $key => $val) {
                $attr_value[] = !empty($region_arr[$val]['name']) ? $region_arr[$val]['name'] : '';
            }
            $field_value = implode('',$attr_value);
        }elseif (('file' == $field_type || 'img' == $field_type) && !empty($field_value)){
            static $file_type = null;
            null === $file_type && $file_type = tpCache('basic.file_type');
            if(preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $field_value)){
                if (!stristr($field_value, '|')) {
                    $field_value = handle_subdir_pic($field_value,'img',$domain);
                    $field_value  = "<a href='{$field_value}' target='_blank'><img src='{$field_value}' width='60' height='60' style='float: unset;cursor: pointer;' /></a>";
                }
            }else if(preg_match('/(\.('.$file_type.'))$/i', $field_value)){
                static $current_domain = null;
                null === $current_domain && $current_domain = request()->domain();
                if (!stristr($field_value, '|')) {
                    $field_value = handle_subdir_pic($field_value,'img',$domain);
                    $domain_new = "";
                    if ($domain){
                        $domain_new = $current_domain;
                    }
                    $field_value  = "<a href='{$field_value}' download='".time()."'><img src=\"{$domain_new}".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width: 16px;height:  16px;\">点击下载</a>";
                }
            }
        }

        return $field_value;
    }
}
if (!function_exists('del_all_dir')){
    //完整删除目录，以及目录下面所有文件(相比于rmdir不会产生错误报告)
    function del_all_dir($path){
        $path = rtrim($path, '/').'/';
        //如果是目录则继续
        if(is_dir($path)){
            //扫描一个文件夹内的所有文件夹和文件并返回数组
            $p = scandir($path);
            //如果 $p 中有两个以上的元素则说明当前 $path 不为空
            if(count($p)>2){
                foreach($p as $val){
                    //排除目录中的.和..
                    if($val !="." && $val !=".."){
                        //如果是目录则递归子目录，继续操作
                        if(is_dir($path.$val)){
                            //子目录中操作删除文件夹和文件
                            del_all_dir($path.$val.'/');
                        }else{
                            //如果是文件直接删除
                            unlink($path.$val);
                        }
                    }
                }
            }
        }
        //删除目录
        return @rmdir($path);
    }
}
if (!function_exists('get_image_type')){
    //获取图片的类型
    function get_image_type($image)
    {
        if (function_exists('exif_imagetype')) {
            return exif_imagetype($image);
        }
        try {
            $info = getimagesize($image);
            return $info ? $info[2] : false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('get_all_arctype')){
    /*
     *
     */
    function get_all_arctype(){
        $arctype_list = Db::name('arctype')->field("id,parent_id,dirname")->where([
            'is_del'    => 0,
            'lang' => get_current_lang()
        ])->order("grade asc")->getAllWithIndex("id");
        return $arctype_list;
    }
}
if (!function_exists('get_all_parent_dirpath')){
    /*
     *
     */
    function get_all_parent_dirpath($id,$arctype_list,&$parent_dir = ''){
        !empty($arctype_list[$id]['dirname']) && $parent_dir = $arctype_list[$id]['dirname']."/".$parent_dir;
        if (!empty($arctype_list[$id]['parent_id'])){
            $parent_dir = get_all_parent_dirpath($arctype_list[$id]['parent_id'],$arctype_list,$parent_dir);
        }

        return $parent_dir;
    }
}

if (!function_exists('clearHtmlCache')) {
    /**
     * 清理文档和相关页面缓存、数据
     */
    function clearHtmlCache($aids = [], $typeids = [])
    {
        $filelist = [];
        $all_typeids = [];
        $seoData = tpCache('seo');

        if (!empty($aids)) {
            $archivesList = \think\Db::name('archives')->field('aid,typeid,stypeid')->where(['aid'=>['IN', $aids]])->select();
            foreach ($archivesList as $_k => $_v) {
                $typeids[] = $_v['typeid'];
                $_v['stypeid'] = trim($_v['stypeid'], ',');
                $stypeid = explode(',', $_v['stypeid']);
                foreach ($stypeid as $_k2 => $_v2) {
                    $typeids[] = $_v2;
                }
            }
            if (!isset($seoData['seo_uphtml_after_pernext13']) || !empty($seoData['seo_uphtml_after_pernext13'])) {
                // 文档
                foreach ($aids as $_k => $_v) {
                    $arr = glob(HTML_ROOT.'view'.DS."*_{$_v}.html");
                    if (is_array($arr)) {
                        $filelist = array_merge($filelist, $arr);
                    }
                }
                // tag标签
                $tagids = \think\Db::name('taglist')->where(['aid'=>['IN', $aids]])->group('tid')->column('tid');
                foreach ($tagids as $_k => $_v) {
                    $arr = glob(HTML_ROOT.'tags'.DS."*_{$_v}.html");
                    if (is_array($arr)) {
                        $filelist = array_merge($filelist, $arr);
                    }
                }
            }
        }

        // 文档涉及的所有相关栏目ID
        if (!empty($typeids)) {
            if (!isset($seoData['seo_uphtml_after_channel13']) || !empty($seoData['seo_uphtml_after_channel13'])) {
                $arctypeM = new \app\common\model\Arctype;
                $typeid_list = $arctypeM->getAllPidByids($typeids);
                foreach ($typeid_list as $_k => $_v) {
                    $all_typeids[] = $_v['id'];
                    $arr = glob(HTML_ROOT.'lists'.DS."*_{$_v['id']}.html");
                    if (is_array($arr)) {
                        $filelist = array_merge($filelist, $arr);
                    }
                }
            }
            $all_typeids = array_merge($all_typeids, $typeids);
            $all_typeids = array_unique($all_typeids);
            \think\Db::name('arctype')->where(['id'=>['IN', $all_typeids]])->update(['update_time'=>getTime()]);
        }


        // 删除页面缓存文件
        if (!empty($filelist) || !empty($aids) || !empty($typeids)) {
            foreach ($filelist as $_k => $_v) {
                @unlink($_v);
            }
            \think\Cache::clear();
        }

        if (!empty($aids)) {
            if (!isset($seoData['seo_uphtml_after_home13']) || !empty($seoData['seo_uphtml_after_home13'])) {
                delFile(HTML_ROOT.'index');
            }
        }
    }
}

if (!function_exists('getToutiaoAccessToken'))
{
    /**
     * 返回字节小程序 access_token
     * @param  string $appid     [description]
     * @return [type]            [description]
     */
    function getToutiaoAccessToken($appid = '', $secret = '', $salt = '', $isSave = false)
    {
        $data = [
            'salt' => $salt,
            'appid' => $appid,
            'secret' => $secret,
        ];
        if (empty($data['appid'])) {
            return [
                'code' => 0,
                'msg' => '请先完成字节小程序配置',
            ];
        }

        $url = "https://developer.toutiao.com/api/apps/v2/token";
        $postData = [
            'appid' => $data['appid'],
            'secret' => $data['secret'],
            'grant_type' => "client_credential",
        ];
        $headers = ["content-type: application/json"];
        $response = httpRequest($url, 'POST', json_encode($postData), $headers);
        $params = json_decode($response, true);
        if (!empty($params['data']['access_token'])) {
            $data['access_token'] = $access_token = $params['data']['access_token'];
            $data['expire_time']  = getTime() + $params['data']['expires_in'] - 1000;
            if (!empty($isSave)) tpSetting('OpenMinicode', ['conf_toutiao' => json_encode($data)]);
            return [
                'code'  => 1,
                'access_token' => $access_token,
                'expire_time' => $data['expire_time'],
                'appid' => !empty($data['appid']) ? $data['appid'] : '',
            ];
        }

        return [
            'code'  => 0,
            'msg' => !empty($params['errmsg']) ? $params['errmsg'] : '请检查小程序appid和secret是否正确',
        ];
    }
}

if (!function_exists('sitemap_auto')) 
{
    /**
     * 自动生成引擎sitemap
     */
    function sitemap_auto()
    {
        $globalConfig = tpCache('global');
        if (isset($globalConfig['sitemap_auto']) && $globalConfig['sitemap_auto'] > 0) {
            sitemap_all();
        }
    }
}

if (!function_exists('sitemap_all')) 
{
    /**
     * 生成全部引擎sitemap
     */
    function sitemap_all($type = 'all')
    {
        sitemap_xml($type);
    }
}

if (!function_exists('sitemap_xml')) 
{
    /**
     * 生成xml形式的sitemap,分页（入口页面、首页、栏目页、内容页、tags、问答）
     * <mobile:mobile/> ：移动网页
     * <mobile:mobile type="mobile"/> ：移动网页
     * <mobile:mobile type="pc,mobile"/>：自适应网页  一个域名一个模板，
     * <mobile:mobile type="htmladapt"/>：代码适配    一个域名两个模板，手机端浏览和pc端浏览显示内容不一样（根据硬件判断）
     */
    function sitemap_xml($type = 'all'){
        $globalConfig = tpCache('global');
        if (empty($globalConfig['sitemap_xml']) && empty($globalConfig['sitemap_txt'])) {
            return '';
        }
        $response_type = config('ey_config.response_type');  // 0是代码适配,1:pc、移动端分离（存在pc、移动端两套模板）
        $web_mobile_domain_open = $globalConfig['web_mobile_domain_open']; //是否开启手机端域名
        $web_mobile_domain = $globalConfig['web_mobile_domain']; //手机端域名
        $lang = get_current_lang();
        $default_lang = get_default_lang();
        $langRow = \think\Db::name('language')->field('is_home_default')->where(['mark'=>$lang])->find();
        if (!empty($langRow['is_home_default'])) {
            $filename = ROOT_PATH . "sitemap.xml";
            $filename_txt = ROOT_PATH . "sitemap.txt";
        } else {
            $filename = ROOT_PATH . "sitemap_{$lang}.xml";
            $filename_txt = ROOT_PATH . "sitemap_{$lang}.txt";
        }

        /* 分类列表(用于生成列表链接的sitemap) */
        $map = array(
            'status'    => 1,
            'is_del'    => 0,
            'lang'      => $lang,
        );
        if (is_array($globalConfig)) {
            // 过滤隐藏栏目
            if (isset($globalConfig['sitemap_not1']) && $globalConfig['sitemap_not1'] > 0) {
                $map['is_hidden'] = 0;
            }
            // 过滤外部模块
            if (isset($globalConfig['sitemap_not2']) && $globalConfig['sitemap_not2'] > 0) {
                $map['is_part'] = 0;
            }
        }
        $result_arctype = M('arctype')->field("*, id AS loc, add_time AS lastmod, 'hourly' AS changefreq, '0.8' AS priority")
            ->where($map)
            ->order('sort_order asc, id asc')
            ->getAllWithIndex('id');

        /* 文章列表(用于生成文章详情链接的sitemap) */
        if (!isset($globalConfig['sitemap_archives_num']) || $globalConfig['sitemap_archives_num'] === '') {
            $sitemap_archives_num = 1000;
        } else {
            $sitemap_archives_num = intval($globalConfig['sitemap_archives_num']);
        }
        $result_archives = [];
        if (0 < $sitemap_archives_num) {
            $map = array(
                'channel'   => ['IN', config('global.allow_release_channel')],
                'arcrank'   => array('gt', -1),
                'status'    => 1,
                'is_del'    => 0,
                'lang'      => $lang,
            );
            if (is_array($globalConfig)) {
                // 过滤外部模块
                if (isset($globalConfig['sitemap_not2']) && $globalConfig['sitemap_not2'] > 0) {
                    $map['is_jump'] = 0;
                }
            }
            /*定时文档显示插件*/
            if (is_dir('./weapp/TimingTask/')) {
                $weappModel = new \app\admin\model\Weapp;
                $TimingTaskRow = $weappModel->getWeappList('TimingTask');
                if (!empty($TimingTaskRow['status']) && 1 == $TimingTaskRow['status']) {
                    $map['add_time'] = ['elt', getTime()]; // 只显当天或之前的文档
                }
            }
            /*end*/
            $field = "aid, channel, is_jump, jumplinks, htmlfilename,province_id,city_id,area_id, add_time, update_time, typeid, aid AS loc, add_time AS lastmod, 'daily' AS changefreq, '0.5' AS priority";
            $result_archives = M('archives')->field($field)
                ->where($map)
                ->order('aid desc')
                ->limit($sitemap_archives_num)
                ->select();
        }


        $urls_txt = []; // 记录sitemap里包含的URL，用于生成sitemap.txt文件

        // header('Content-Type: text/xml');//这行很重要，php默认输出text/html格式的文件，所以这里明确告诉浏览器输出的格式为xml,不然浏览器显示不出xml的格式
        $xml_wrapper = <<<XML
<?xml version='1.0' encoding='utf-8'?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0">
</urlset>
XML;
        if (function_exists('simplexml_load_string')) {
            $xml = @simplexml_load_string($xml_wrapper);
        } else if (class_exists('SimpleXMLElement')) {
            $xml = new SimpleXMLElement($xml_wrapper);
        }
        if (!$xml) {
            return true;
        }
        if($web_mobile_domain_open){
            if (function_exists('simplexml_load_string')) {
                $xml_mobile = @simplexml_load_string($xml_wrapper);
            } else if (class_exists('SimpleXMLElement')) {
                $xml_mobile = new SimpleXMLElement($xml_wrapper);
            }
            $urls_txt_mobile = [];
            $filename_mobile = str_replace('sitemap','sitemap_'.$web_mobile_domain,$filename);
            $filename_txt_mobile = str_replace('sitemap','sitemap_'.$web_mobile_domain,$filename_txt);
        }
        // 更新频率
        $sitemap_changefreq_index = !empty($globalConfig['sitemap_changefreq_index']) ? $globalConfig['sitemap_changefreq_index'] : 'always';
        $sitemap_changefreq_list = !empty($globalConfig['sitemap_changefreq_list']) ? $globalConfig['sitemap_changefreq_list'] : 'hourly';
        $sitemap_changefreq_view = !empty($globalConfig['sitemap_changefreq_view']) ? $globalConfig['sitemap_changefreq_view'] : 'daily';

        // 优先级别
        $sitemap_priority_index = !empty($globalConfig['sitemap_priority_index']) ? $globalConfig['sitemap_priority_index'] : '1.0';
        $sitemap_priority_list = !empty($globalConfig['sitemap_priority_list']) ? $globalConfig['sitemap_priority_list'] : '0.8';
        $sitemap_priority_view = !empty($globalConfig['sitemap_priority_view']) ? $globalConfig['sitemap_priority_view'] : '0.5';

        $langRow = \think\Db::name('language')
            ->where(['status'=>1])
            ->order('id asc')
            ->cache(true, EYOUCMS_CACHE_TIME, 'language')
            ->select();

        // 去掉入口文件
        $inletStr = '/index.php';
        $seo_inlet = config('ey_config.seo_inlet');
        1 == intval($seo_inlet) && $inletStr = '';
        $now = date('Y-m-d');
        // 首页
        if ($lang == $default_lang) {
            foreach ($langRow as $key => $val) {
                $mark = $val['mark'];
                if (empty($globalConfig['web_language_switch']) && $lang != $mark) { // 关闭多语言
                    continue;
                }
                /*单独域名*/
                $url = $val['url'];
                if (empty($url)) {
                    if (1 == $val['is_home_default']) {
                        $url = request()->domain().ROOT_DIR.'/';
                    } else {
                        if ($mark != $default_lang) {
                            $url = request()->domain().ROOT_DIR."/sitemap_{$mark}.xml";
                        } else {
                            $global_config_tmp = tpCache('global', [], $mark);
                            $seo_pseudo = !empty($global_config_tmp['seo_pseudo']) ? $global_config_tmp['seo_pseudo'] : config('ey_config.seo_pseudo');
                            if (1 == $seo_pseudo) {
                                $url = request()->domain().ROOT_DIR.$inletStr;
                                if (!empty($inletStr)) {
                                    $url .= '?';
                                } else {
                                    $url .= '/?';
                                }
                                $url .= http_build_query(['lang'=>$mark]);
                            } else {
                                $url = request()->domain().ROOT_DIR.$inletStr.'/'.$mark;
                            }
                        }
                    }
                } else {
                    if (0 == $val['is_home_default']) {
                        $url = $url.ROOT_DIR."/sitemap_{$mark}.xml";
                    }
                }
                /*--end*/
                if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                    $xml = join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'pc');
                    array_push($urls_txt, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                    if($xml_mobile){
                        $url = pc_to_mobile_url($url);
                        $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                        if (!in_array($url, $urls_txt_mobile)) {
                            array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                        }
                    }
                }else{   //一个域名
                    if ($response_type){  //pc、移动端分离  <mobile:mobile type="htmladapt"/>
                        $xml = join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'htmladapt');
                    }else{   //代码适配    <mobile:mobile type="pc,mobile"/>
                        $xml =join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'pc,mobile');
                    }
                    array_push($urls_txt, htmlspecialchars_decode($url));
                }
            }
        }
        else {
            foreach ($langRow as $key => $val) {
                $mark = $val['mark'];
                if (empty($globalConfig['web_language_switch']) || $lang != $mark) { // 关闭多语言
                    continue;
                }
                /*单独域名*/
                $url = $val['url'];
                if (empty($url)) {
                    $global_config_tmp = tpCache('global', [], $mark);
                    $seo_pseudo = !empty($global_config_tmp['seo_pseudo']) ? $global_config_tmp['seo_pseudo'] : config('ey_config.seo_pseudo');
                    if (1 == $seo_pseudo) {
                        $url = request()->domain().ROOT_DIR.$inletStr;
                        if (!empty($inletStr)) {
                            $url .= '?';
                        } else {
                            $url .= '/?';
                        }
                        $url .= http_build_query(['lang'=>$mark]);
                    } else {
                        $url = request()->domain().ROOT_DIR.$inletStr.'/'.$mark;
                    }
                }
                /*--end*/

                /*--end*/
                if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                    $xml = join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'pc');
                    array_push($urls_txt, htmlspecialchars_decode($url));
                    if($xml_mobile){
                        $url = pc_to_mobile_url($url);
                        $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                        array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                    }
                }else{   //一个域名
                    if ($response_type){  //pc、移动端分离  <mobile:mobile type="pc,mobile"/>
                        $xml = join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'htmladapt');
                    }else{   //代码适配    <mobile:mobile type="htmladapt"/>
                        $xml =join_xml($xml,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'pc,mobile');
                    }
                    array_push($urls_txt, htmlspecialchars_decode($url));
                }
            }
        }
        /*--end*/

        /*所有栏目*/
        foreach ($result_arctype as $sub) {
            if (is_array($sub)) {
                if ($sub['is_part'] == 1) {
                    $url = $sub['typelink'];
                } else {
                    $url = get_typeurl($sub, false);
                }
                $url = str_replace('&amp;', '&', $url);
                $url = str_replace('&', '&amp;', $url);
                if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                    $xml = join_xml($xml,$url,$now,$sitemap_changefreq_list,$sitemap_priority_list,'pc');
                    array_push($urls_txt, htmlspecialchars_decode($url));
                    if($xml_mobile){
                        $url = get_typeurl($sub, false,'mobile'); //pc_to_mobile_url($url,$sub['id']);
                        $url = str_replace('&amp;', '&', $url);
                        $url = str_replace('&', '&amp;', $url);
                        $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                        array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                    }
                }else{   //一个域名
                    if ($response_type){  //pc、移动端分离  <mobile:mobile type="pc,mobile"/>
                        $xml = join_xml($xml,$url,$now,$sitemap_changefreq_list,$sitemap_priority_list,'htmladapt');
                    }else{   //代码适配    <mobile:mobile type="htmladapt"/>
                        $xml =join_xml($xml,$url,$now,$sitemap_changefreq_list,$sitemap_priority_list,'pc,mobile');
                    }
                    array_push($urls_txt, htmlspecialchars_decode($url));
                }
            }
        }
        /*--end*/

        /*所有文档*/
        foreach ($result_archives as $val) {
            if (is_array($val) && isset($result_arctype[$val['typeid']])) {
                $val = array_merge($result_arctype[$val['typeid']], $val);
                if ($val['is_jump'] == 1) {
                    $url = $val['jumplinks'];
                } else {
                    $url = get_arcurl($val, false);
                }
                $url = str_replace('&amp;', '&', $url);
                $url = str_replace('&', '&amp;', $url);
                $lastmod_time = empty($val['update_time']) ? $val['add_time'] : $val['update_time'];
                $time_row = date('Y-m-d', $lastmod_time);
                if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                    $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc');
                    array_push($urls_txt, htmlspecialchars_decode($url));
                    if($xml_mobile){
                        $url = get_arcurl($val, false,'mobile'); //pc_to_mobile_url($url,$val['typeid'],$val['aid']);
                        $url = str_replace('&amp;', '&', $url);
                        $url = str_replace('&', '&amp;', $url);
                        $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                        array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                    }
                }else{   //一个域名
                    if ($response_type){  //pc、移动端分离  <mobile:mobile type="pc,mobile"/>
                        $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'htmladapt');
                    }else{   //代码适配    <mobile:mobile type="htmladapt"/>
                        $xml =join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc,mobile');
                    }
                    array_push($urls_txt, htmlspecialchars_decode($url));
                }
            }
        }
        /*--end*/
        /*所有Tag*/
        /* Tag列表(用于生成Tag标签链接的sitemap) */
        //判断模板文件是否存在
        if (!isset($globalConfig['sitemap_tags_num']) || $globalConfig['sitemap_tags_num'] === '') {
            $sitemap_tags_num = 1000;
        } else {
            $sitemap_tags_num = intval($globalConfig['sitemap_tags_num']);
        }
        $web_tpl_theme =  !empty($globalConfig['web_tpl_theme']) ? "/".$globalConfig['web_tpl_theme'] : '';
        if (is_file('./template'.$web_tpl_theme.'/pc/lists_tags.htm') && $sitemap_tags_num > 0){
            $result_tags = [];
            if (0 < $sitemap_tags_num) {
                $map = array(
                    'lang'      => $lang,
                );
                $field = "id, add_time, id AS loc, add_time AS lastmod, 'daily' AS changefreq, '0.5' AS priority";
                $result_tags = M('tagindex')->field($field)
                    ->where($map)
                    ->order('add_time desc')
                    ->limit($sitemap_tags_num)
                    ->select();
            }
            foreach ($result_tags as $val) {
                if (is_array($val)) {
                    $url = get_tagurl($val['id']);
                    $url = str_replace('&amp;', '&', $url);
                    $url = str_replace('&', '&amp;', $url);
                    $lastmod_time = empty($val['update_time']) ? $val['add_time'] : $val['update_time'];
                    $time_row = date('Y-m-d', $lastmod_time);
                    if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                        $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc');
                        array_push($urls_txt, htmlspecialchars_decode($url));
                        if($xml_mobile){
                            $url = get_tagurl($val['id'],'mobile');
                            $url = str_replace('&amp;', '&', $url);
                            $url = str_replace('&', '&amp;', $url);
                            $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                            array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                        }
                    }else{   //一个域名
                        if ($response_type){  //pc、移动端分离  <mobile:mobile type="pc,mobile"/>
                            $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'htmladapt');
                        }else{   //代码适配    <mobile:mobile type="htmladapt"/>
                            $xml =join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc,mobile');
                        }
                        array_push($urls_txt, htmlspecialchars_decode($url));
                    }
                }
            }
        }
        /*--end*/
        /*--问答插件begin*/
        if (is_dir('./weapp/Ask/')){
            try{
                $Askow = \think\Db::name("weapp")->where(['code'=>'Ask'])->field("status,data")->find();
                if (!empty($Askow['status']) && 1 == $Askow['status']) {
                    $ask_map = [
                        'is_review' =>1,
                    ];
                    $mobile_domain = tpCache('global.web_mobile_domain');
                    $ask_seo_pseudo = 1;
                    $Askow['data'] = unserialize($Askow['data']);
                    if (!empty($Askow['data']['seo_pseudo'])) {
                        $ask_seo_pseudo = intval($Askow['data']['seo_pseudo']);
                    }
                    //问答首页
                    $ask_list[] = [
                        'url' => auto_hide_index(url('plugins/Ask/index', [], true, true, $ask_seo_pseudo)),
                        'title' => "问答首页",
                        'add_time' =>time(),
                        'mobile_url' => auto_hide_index(url('plugins/Ask/index', [], true, $mobile_domain, $ask_seo_pseudo)),
                    ];
                    //问答栏目
                    $result_ask_type = \think\Db::name("weapp_ask_type")->field("type_id,type_name,update_time")->order('sort_order asc')->select();
                    foreach ($result_ask_type as $val){
                        $ask_list[] = [
                            'url' => auto_hide_index(url('plugins/Ask/index', ['type_id'=>$val['type_id']],true,true,$ask_seo_pseudo)),
                            'title' => $val['type_name'],
                            'update_time' =>$val['update_time'],
                            'mobile_url' => auto_hide_index(url('plugins/Ask/index', ['type_id'=>$val['type_id']],true,$mobile_domain,$ask_seo_pseudo)),

                        ];
                    }
                    //问答内容
                    $result_ask = \think\Db::name('weapp_ask')->field('ask_id,type_id,ask_title,update_time')
                        ->where($ask_map)
                        ->order('ask_id desc')
                        ->select();
                    foreach ($result_ask as $val){
                        $ask_list[] = [
                            'url' => auto_hide_index(url('plugins/Ask/details', ['ask_id'=>$val['ask_id']],true,true,$ask_seo_pseudo)),
                            'title' => $val['ask_title'],
                            'update_time' =>$val['update_time'],
                            'mobile_url' => auto_hide_index(url('plugins/Ask/details', ['ask_id'=>$val['ask_id']],true,$mobile_domain,$ask_seo_pseudo)),
                        ];
                    }
                    foreach ($ask_list as $val) {
                        if (is_array($val)) {
                            $url = $val['url'];
                            $url = str_replace('&amp;', '&', $url);
                            $url = str_replace('&', '&amp;', $url);
                            $lastmod_time = empty($val['update_time']) ? $val['add_time'] : $val['update_time'];
                            $time_row = date('Y-m-d', $lastmod_time);
                            if ($web_mobile_domain_open){  //两个域名，生成两次,移动端添加标签：<mobile:mobile type="mobile"/>
                                $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc');
                                array_push($urls_txt, htmlspecialchars_decode($url));
                                if($xml_mobile){
                                    $url = $val['mobile_url'];
                                    $url = str_replace('&amp;', '&', $url);
                                    $url = str_replace('&', '&amp;', $url);
                                    $xml_mobile = join_xml($xml_mobile,$url,$now,$sitemap_changefreq_index,$sitemap_priority_index,'mobile');
                                    array_push($urls_txt_mobile, str_replace('.xml', '.txt', htmlspecialchars_decode($url)));
                                }
                            }else{   //一个域名
                                if ($response_type){  //pc、移动端分离  <mobile:mobile type="pc,mobile"/>
                                    $xml = join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'htmladapt');
                                }else{   //代码适配    <mobile:mobile type="htmladapt"/>
                                    $xml =join_xml($xml,$url,$time_row,$sitemap_changefreq_view,$sitemap_priority_view,'pc,mobile');
                                }
                                array_push($urls_txt, htmlspecialchars_decode($url));
                            }
                        }
                    }
                }
            }catch (\Exception $e){}
        }
        /*--end*/

        if ($type == 'xml' || (!empty($globalConfig['sitemap_xml']) && in_array($type, ['all','xml']))) {
            if($xml && $filename){
                $content = $xml->asXML(); //用asXML方法输出xml，默认只构造不输出。
                @file_put_contents($filename, $content);
            }
            if(!empty($xml_mobile) && !empty($filename_mobile)){
                $content = $xml_mobile->asXML(); //用asXML方法输出xml，默认只构造不输出。
                @file_put_contents($filename_mobile, $content);
            }
        }
        if ($type == 'txt' || (!empty($globalConfig['sitemap_txt']) && in_array($type, ['all','txt']))) {
            if($urls_txt && $filename_txt){
                $content = implode(PHP_EOL, $urls_txt);
                @file_put_contents($filename_txt, $content);
            }
            if(!empty($urls_txt_mobile) && !empty($filename_txt_mobile)){
                $content = implode(PHP_EOL, $urls_txt_mobile);
                @file_put_contents($filename_txt_mobile, $content);
            }
        }
    }
}

if (!function_exists('join_xml'))
{
    /**
     *  拼接xml
     */
    function join_xml($xml,$loc,$lastmod,$changefreq,$priority,$model = 'pc')
    {
        $item = $xml->addChild('url'); //使用addChild添加节点
        foreach (['loc','lastmod','changefreq','priority'] as $key1) {
            if ('loc' == $key1) {
                $row = $loc;
            } else if ('lastmod' == $key1) {
                $row = $lastmod;
            } else if ('changefreq' == $key1) {
                $row = $changefreq;
            } else if ('priority' == $key1) {
                $row = $priority;
            }
            try {
                $node = $item->addChild($key1, $row);
            } catch (\Exception $e) {}
            if (isset($attribute_array[$key1]) && is_array($attribute_array[$key1])) {
                foreach ($attribute_array[$key1] as $akey => $aval) {//设置属性值，我这里为空
                    $node->addAttribute($akey, $aval);
                }
            }
        }
        if ($model == 'mobile'){   //移动端
            $item->addChild('mobile','','http://www.google.com/schemas/sitemap-mobile/1.0')->addAttribute("type","mobile");
        }else if($model == 'pc,mobile'){
            $item->addChild('mobile','','http://www.google.com/schemas/sitemap-mobile/1.0')->addAttribute("type","pc,mobile");
        }else if($model == 'htmladapt'){
            $item->addChild('mobile','','http://www.google.com/schemas/sitemap-mobile/1.0')->addAttribute("type","htmladapt");
        }

        return $xml;
    }
}

if (!function_exists('get_typeurl')) 
{
    /**
     * 获取栏目链接
     *
     * @param array $arctype_info 栏目信息
     * @param boolean $admin 后台访问链接，还是前台链接
     * $domain_type mobile：手机端
     */
    function get_typeurl($arctype_info = array(), $admin = true,$domain_type = '')
    {
        /*问答模型*/
        if ($arctype_info['current_channel'] == 51) { 
            $typeurl = get_askurl("home/Ask/index");
            // 自动隐藏index.php入口文件
            $typeurl = auto_hide_index($typeurl);

            return $typeurl;
        }
        /*end*/
        $domain = null; //static $domain = null;   //pc移动交替生成时候混乱
        null === $domain && $domain = request()->domain();
        if ($domain_type == 'mobile'){
            $web_mobile_domain = tpCache('global.web_mobile_domain');
            if (!empty($web_mobile_domain)){
                $subDomain = request()->subDomain();
                $domain = str_replace($subDomain,$web_mobile_domain,$domain);
            }
        }
        /*兼容采集没有归属栏目的文档*/
        if (empty($arctype_info['current_channel'])) {
            $channelRow = \think\Db::name('channeltype')->field('id as channel')
                ->where('id',1)
                ->find();
            $arctype_info = array_merge($arctype_info, $channelRow);
        }
        /*--end*/
        
        static $result = null;
        null === $result && $result = model('Channeltype')->getAll('id, ctl_name', array(), 'id');
        $ctl_name = '';
        if ($result) {
            $ctl_name = $result[$arctype_info['current_channel']]['ctl_name'];
        }

        static $seo_pseudo = null;
        static $seo_dynamic_format = null;
        if (null === $seo_pseudo || null === $seo_dynamic_format) {
            $globalConfig = tpCache('global');
            $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
            $seo_dynamic_format = !empty($globalConfig['seo_dynamic_format']) ? $globalConfig['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
        }

        if (2 == $seo_pseudo && $admin) {
            static $lang = null;
            null === $lang && $lang = input('param.lang/s', 'cn');
            $typeurl = ROOT_DIR."/index.php?m=home&c=Lists&a=index&tid={$arctype_info['id']}&lang={$lang}&t=".getTime();
        } else {
            $typeurl = typeurl("home/{$ctl_name}/lists", $arctype_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
            // 自动隐藏index.php入口文件
            $typeurl = auto_hide_index($typeurl);
        }

        return $typeurl;
    }
}

if (!function_exists('get_askurl')) 
{
    /**
     * 获取问答链接
     *
     * @param array $arctype_info 栏目信息
     * @param boolean $admin 后台访问链接，还是前台链接
     */
    function get_askurl($url = '', $ask_info = array(), $admin = true)
    {
        static $domain = null;
        null === $domain && $domain = request()->domain();

        static $seo_pseudo = null;
        static $seo_dynamic_format = null;
        if (null === $seo_pseudo || null === $seo_dynamic_format) {
            $globalConfig = tpCache('global');
            $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
            $seo_dynamic_format = !empty($globalConfig['seo_dynamic_format']) ? $globalConfig['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
        }

        $askurl = askurl($url, $ask_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
        // 自动隐藏index.php入口文件
        $askurl = auto_hide_index($askurl);

        return $askurl;
    }
}

if (!function_exists('get_arcurl')) 
{
    /**
     * 获取文档链接
     *
     * @param array $arctype_info 栏目信息
     * @param boolean $admin 后台访问链接，还是前台链接
     * @param string  $domain_type   mobile：手机端
     */
    function get_arcurl($arcview_info = array(), $admin = true, $domain_type = '')
    {
        $domain = null; //static $domain = null;   //pc移动交替生成时候混乱
        null === $domain && $domain = request()->domain();
        if ($domain_type == 'mobile'){
            $web_mobile_domain = tpCache('global.web_mobile_domain');
            if (!empty($web_mobile_domain)){
                $subDomain = request()->subDomain();
                $domain = str_replace($subDomain,$web_mobile_domain,$domain);
            }
        }
        /*兼容采集没有归属栏目的文档*/
        if (!empty($arcview_info) && empty($arcview_info['channel'])) {
            $channelRow = \think\Db::name('channeltype')->field('id as channel')
                ->where('id',1)
                ->find();
            $arcview_info = array_merge($arcview_info, $channelRow);
        }
        /*--end*/

        static $result = null;
        null === $result && $result = model('Channeltype')->getAll('id, ctl_name', array(), 'id');
        $ctl_name = '';
        if ($result) {
            $ctl_name = $result[$arcview_info['channel']]['ctl_name'];
        }

        static $seo_pseudo = null;
        static $seo_dynamic_format = null;
        if (null === $seo_pseudo || null === $seo_dynamic_format) {
            $globalConfig = tpCache('global');
            $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
            $seo_dynamic_format = !empty($globalConfig['seo_dynamic_format']) ? $globalConfig['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
        }
        
        if ($admin) {
            if (2 == $seo_pseudo) {
                static $lang = null;
                null === $lang && $lang = input('param.lang/s', 'cn');
                $arcurl = ROOT_DIR."/index.php?m=home&c=View&a=index&aid={$arcview_info['aid']}&lang={$lang}&admin_id=".session('admin_id');
            } else {
                if (config('city_switch_on')) {
                    $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info);
                    $url_path = parse_url($arcurl, PHP_URL_PATH);
                    $url_path = str_replace('.html', '', $url_path);
                    $url_path = '/'.trim($url_path, '/').'/';
                    preg_match_all('/\/site\/([^\/]+)\//', $url_path, $matches);
                    $site_domain = !empty($matches[1][0]) ? $matches[1][0] : '';
                    if (!empty($site_domain)) {
                        $url_path_new = str_replace("/site/{$site_domain}/", '', $url_path);
                        $root_dir_str = str_replace('/', '\/', ROOT_DIR);
                        $url_path_new = preg_replace("/^{$root_dir_str}\//", ROOT_DIR."/{$site_domain}/", $url_path_new);
                        $arcurl = str_replace(rtrim($url_path, '/'), $url_path_new, $arcurl);
                    }
                } else {
                    $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
                }
                // 自动隐藏index.php入口文件
                $arcurl = auto_hide_index($arcurl);
                if (stristr($arcurl, '?')) {
                    $arcurl .= '&admin_id='.session('admin_id');
                } else {
                    $arcurl .= '?admin_id='.session('admin_id');
                }
            }
        } else {
            if (config('city_switch_on')) {
                $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info);
                $url_path = parse_url($arcurl, PHP_URL_PATH);
                $url_path = str_replace('.html', '', $url_path);
                $url_path = '/'.trim($url_path, '/').'/';
                preg_match_all('/\/site\/([^\/]+)\//', $url_path, $matches);
                $site_domain = !empty($matches[1][0]) ? $matches[1][0] : '';
                if (!empty($site_domain)) {
                    $url_path_new = str_replace("/site/{$site_domain}/", '', $url_path);
                    $root_dir_str = str_replace('/', '\/', ROOT_DIR);
                    $url_path_new = preg_replace("/^{$root_dir_str}\//", ROOT_DIR."/{$site_domain}/", $url_path_new);
                    $arcurl = str_replace(rtrim($url_path, '/'), $url_path_new, $arcurl);
                }
            } else {
                $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
            }
            // 自动隐藏index.php入口文件
            $arcurl = auto_hide_index($arcurl);
        }

        return $arcurl;
    }
}

if (!function_exists('get_tagurl')) 
{
    /**
     * 获取标签链接
     *
     * @param array $tagid 标签ID
     */
    function get_tagurl($tagid = '',$domain_type = '')
    {
        if ($domain_type == 'mobile'){
            $domain = tpCache('global.web_mobile_domain');
        }else{
            static $domain = null;
            null === $domain && $domain = true;
        }
        $tagurl = tagurl("home/Tags/lists", ['tagid'=>$tagid], true, $domain);
//        static $seo_pseudo = null;
//        static $seo_dynamic_format = null;
//        if (null === $seo_pseudo || null === $seo_dynamic_format) {
//            $globalConfig = tpCache('global');
//            $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
//            $seo_dynamic_format = !empty($globalConfig['seo_dynamic_format']) ? $globalConfig['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
//        }
//        $tagurl = tagurl("home/Tags/lists", ['tagid'=>$tagid], true, $domain, $seo_pseudo, $seo_dynamic_format);

        // 自动隐藏index.php入口文件
        $tagurl = auto_hide_index($tagurl);

        return $tagurl;
    }
}

if (!function_exists('handleEyouDataValidate')) 
{
    /**
     * 处理数据验证
     * $required  必填项字段
     * $token     验证token名称
     * $post      提交验证的数组
     * $error     必填项不存在时的错误提示
     */
    function handleEyouDataValidate($required = '', $token = '', $post = [], $error = '数据不存在！')
    {
        if (empty($required)) return '请传入必填项字段';
        if (empty($token)) return '请传入验证token名称';
        if (empty($post)) return '请传入提交验证的数组';

        $rule = [
            $required => 'require|token:'.$token.'',
        ];
        $message = [
            $required . '.require' => $error,
        ];
        $validate = new \think\Validate($rule, $message);
        if (!$validate->batch()->check($post)) {
            $getError = $validate->getError();
            $errorMsg = array_values($getError);
            $resultMsg = (empty($errorMsg[0]) || $errorMsg[0] == '令牌数据无效') ? '表单校验失败，请检查站点权限问题' : $errorMsg[0];
        }
        return !empty($resultMsg) ? $resultMsg : false;
    }
}

if (!function_exists('handleEyouFilterStr')) 
{
    /**
     * 处理数据验证
     * $required  必填项字段
     * $token     验证token名称
     * $post      提交验证的数组
     * $error     必填项不存在时的错误提示
     */
    function handleEyouFilterStr($resultStr = '')
    {
        if (empty($resultStr)) return '请传入字符串';

        $resultStr = htmlspecialchars_decode($resultStr);
        $filterStr = ['javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base', 'svg'];
        foreach ($filterStr as $value) {
            $resultStr = preg_replace('/<(\s*)\b'.$value.'\b/i', '<', $resultStr);
        }
        $resultStr = htmlspecialchars($resultStr);

        return !empty($resultStr) ? $resultStr : false;
    }
}

if (!function_exists('auto_bind_wechatlogin'))
{
    /**
     * 在微信端登录，非微站点用户自动静默补充openid和union_id
     * @param  integer $users_id [description]
     * @return [type]            [description]
     */
    function auto_bind_wechatlogin($users = [], &$referurl = '')
    {
        // 是否绑定了微站点，否则自动绑定
        if (isMobile() && isWeixin() && 0 === intval($users['thirdparty'])) {
            $open_id = model('ShopPublicHandle')->weChatauthorizeCookie($users['users_id']);
            if (empty($open_id)) {
                // $shopMicro = getUsersConfigData('shop.shop_micro');
                $weChatLoginConfig = getUsersConfigData('wechat.wechat_login_config');
                $weChatLoginConfig = !empty($weChatLoginConfig) ? unserialize($weChatLoginConfig) : [];
                if (!empty($weChatLoginConfig['appid']) && !empty($weChatLoginConfig['appsecret'])/* && !empty($shopMicro)*/) {
                    // $callBack = urlencode(url('user/Shop/weChatAuthorizeAction', ['action' => 'authorize'], true, true));
                    $callBack = urlencode(url('user/Users/auto_bind_wechat_info', '', true, true));
                    $referurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $weChatLoginConfig['appid'] . "&redirect_uri=" . $callBack . "&response_type=code&scope=snsapi_base&state=eyoucms&#wechat_redirect";
                }
            } else {
                // dump($open_id);exit;
            }

            // $shop_config = getUsersConfigData('shop');
            // $auto_bind_wechat_info = session('auto_bind_wechat_info');
            // if (empty($auto_bind_wechat_info)/* && !empty($shop_config['shop_micro'])*/) {
            //     $wxlogin_info = [];
            //     if (is_dir('./weapp/WxLogin/')) {
            //         $wxlogin_info = \think\Db::name("weapp_wxlogin")->where(['users_id'=>$users['users_id']])->find();
            //     }
            //     if (empty($users['open_id']) || (isset($wxlogin_info['openid']) && $users['open_id'] == $wxlogin_info['openid'])) {
            //         $wechat_login_config = getUsersConfigData('wechat.wechat_login_config');
            //         $WeChatLoginConfig = !empty($wechat_login_config) ? unserialize($wechat_login_config) : [];
            //         // 微信授权登陆
            //         if (!empty($WeChatLoginConfig['appid']) && !empty($WeChatLoginConfig['appsecret'])) {
            //             // 判断登陆成功跳转的链接，若为空则默认会员中心链接并存入session
            //             if (empty($referurl)) {
            //                 $referurl = url('user/Users/index', '', true, true);
            //                 session('eyou_referurl', $referurl);
            //             }
            //             // 获取微信配置授权登陆
            //             $appid     = $WeChatLoginConfig['appid'];
            //             $NewUrl    = urlencode(url('user/Users/auto_bind_wechat_info', '', true, true));
            //             $ReturnUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $NewUrl . "&response_type=code&scope=snsapi_base&state=eyoucms&#wechat_redirect";
            //             if (request()->isAjax()) {
            //                 $referurl = $ReturnUrl;
            //             } else {
            //                 header('Location: '.$ReturnUrl);
            //                 exit;
            //             }
            //         }
            //     }
            // }
        }
    }
}

if (!function_exists('getConsumObtainScores')) 
{
    /**
     * 消费获得积分
     * 参数说明：
     * $order       订单数据
     * $usersConfig 积分设置
     * $isReturn    是否返回赠送积分数
     * 返回说明：
     * return int
     */
    function getConsumObtainScores($order = [], $usersConfig = [], $isReturn = false)
    {
        // 如果开启消费送积分则执行
        $addScores = 0;
        if (!empty($usersConfig['score_consume_status']) && 1 === intval($usersConfig['score_consume_status'])) {
            // 可赠送积分的金额(订单商品实际金额)
            $addScoresMoney = !empty($order['order_total_amount']) ? floatval($order['order_total_amount']) : 0;
            // 消费一元获得多少个积分数
            $scoreConsumeMoney = !empty($usersConfig['score_consume_money']) ? intval($usersConfig['score_consume_money']) : 0;
            // 计算赠送的积分数
            $addScores = intval(floatval($addScoresMoney) * floatval($scoreConsumeMoney));
            if (empty($isReturn)) {
                // 待添加逻辑...
            }
        }
        if (!empty($isReturn)) return intval($addScores);
    }
}

if (!function_exists('addConsumObtainScores')) {
    /**
     * type  类型:1-提问,2-回答,3-最佳答案4-悬赏退回,5-每日签到,6-管理员编辑,
     *  7-问题悬赏/获得悬赏,8-消费赠送积分,9-积分兑换/退回,10-登录赠送积分
     * 11-积分商城订单支付  12-积分商城订单退回 13-抽奖 (99-用户定制积分兑换余额)
     * 积分赠送
     * $rule 默认2-增加  1-减少
     */
    function addConsumObtainScores($data = [], $rule = 2, $update = true)
    {
        $insert = [
            'type' => $data['type'], //必填
            'users_id' => $data['users_id'], //必填
            'score' => 2 === intval($rule) ? '+' . $data['score'] : '-' . $data['score'], //必填
            'info' => !empty($data['info']) ? $data['info'] : '',
            'remark' => !empty($data['remark']) ? $data['remark'] : '',
            'admin_id' => !empty($data['admin_id']) ? $data['admin_id'] : 0,
            'add_time' => getTime(),
            'update_time' => getTime()
        ];
        $id = Db::name('users_score')->insertGetId($insert);
        if (!empty($id)) {
            if (!empty($update)) {
                if (2 == $rule) {
                    //增加
                    $update_score = Db::Raw('scores + '.$data['score']);
                } else {
                    //减少
                    $update_score = Db::Raw('scores - '.$data['score']);
                }
                Db::name('users')->where('users_id', $data['users_id'])->update(['scores'=>$update_score,'update_time'=>getTime()]);
            }

            $current_score = Db::name('users')->where('users_id', $data['users_id'])->value('scores');
            Db::name('users_score')->where('id', $id)->update(['current_score' => $current_score, 'update_time' => getTime()]);
        }
    }
}

if (!function_exists('get_weixin_access_token')) 
{
    /**
     * 返回微信小程序 access_token
     * @param  string $appid     [description]
     * @return [type]            [description]
     */
    function get_weixin_access_token($resetToken = false, $applets = 'openSource')
    {
        if (is_dir('./weapp/DiyminiproMall/') && 'visualization' == $applets) {
            $where = [
                'name' => 'setting',
            ];
            $settingValue = \think\Db::name('weapp_diyminipro_mall_setting')->where($where)->getField('value');
            $settingValue = !empty($settingValue) ? json_decode($settingValue, true) : [];
            if (!empty($settingValue['appId']) && !empty($settingValue['appSecret'])) {
                $data = [
                    'appid' => $settingValue['appId'],
                    'appsecret' => $settingValue['appSecret']
                ];
            } else {
                return [
                    'code'  => 0,
                    'msg' => '101: 请先完成可视化微信商城小程序配置',
                ];
            }
        } else if ('openSource' == $applets) {
            $data = tpSetting("OpenMinicode.conf_weixin");
            $data = !empty($data) ? json_decode($data, true) : [];
            // 获取原生小程序插件配置
            if (empty($data['appid'])) $data = model('ShopPublicHandle')->getSpecifyAppletsConfig();
            // 如果没有存在配置则提示
            if (empty($data['appid'])) {
                return [
                    'code'  => 0,
                    'msg' => '102: 请先完成小程序API中的微信小程序配置',
                ];
            }
        } else{
            $data = \think\Db::name('weapp')->where(['code' => 'Logisticswx', 'status' => 1])->value('data');
            if (!empty($data)) {
                 $decodedData = unserialize($data);    
                if (isset($decodedData['appid'])) {
                      $data = [
                            'appid' => $decodedData['appid'],
                            'appsecret' => $decodedData['appsecret']
                        ];
                } else {
                    return [
                        'code'  => 0,
                        'msg' => '101: 请先完成设置微信商城小程序appid和密钥',
                    ];
                }
            }
        }

        if (false === $resetToken && !empty($data['access_token']) && !empty($data['expire_time']) && $data['expire_time'] > getTime()) {
            return [
                'code'  => 1,
                'access_token' => $data['access_token'],
            ];
        }

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$data['appid']."&secret=".$data['appsecret'];
        $response = httpRequest($url);
        $params = json_decode($response, true);
        if (!isset($params['access_token']) && 'openSource' == $applets) {
            get_weixin_access_token(true, 'visualization');
        } else if (isset($params['access_token'])) {
            $access_token = $params['access_token'];
            $data['access_token']  = $params['access_token'];
            $data['expire_time']  = getTime() + $params['expires_in'] - 1000;
            if (is_dir('./weapp/DiyminiproMall/') && 'visualization' == $applets) {
                // tpSetting('OpenMinicode', ['conf_weixin_mall' => json_encode($data)]);
            } else if ('openSource' == $applets) {
                tpSetting('OpenMinicode', ['conf_weixin' => json_encode($data)]);
            }
            return [
                'code'  => 1,
                'access_token' => $access_token,
                'appid' => !empty($data['appid']) ? $data['appid'] : '',
            ];
        }

        return [
            'code'  => 0,
            'msg' => !empty($params['errmsg']) ? $params['errmsg'] : '请检查小程序AppId和AppSecret是否正确',
        ];
    }
}

if (!function_exists('get_wechat_access_token')) 
{
    /**
     * 返回微信公众号 access_token
     * @param  string $appid     [description]
     * @return [type]            [description]
     */
    function get_wechat_access_token($resetToken = false)
    {
        $data = tpSetting("OpenMinicode.conf_wechat");
        $data = !empty($data) ? json_decode($data, true) : [];
        if (empty($data['appid'])) {
            return [
                'code'  => 0,
                'msg' => '请先完成微信公众号配置',
            ];
        }
        $setting_info = tpSetting(md5($data['appid']));
        if (!empty($setting_info)) {
            $data = array_merge($data, $setting_info);
            $data['appsecret'] = $data['secret'];
        }
        if (false === $resetToken && !empty($data['access_token']) && !empty($data['expire_time']) && $data['expire_time'] > getTime()) {
            return [
                'code'  => 1,
                'access_token' => $data['access_token'],
                'appid' => $data['appid'],
                'appsecret' => $data['appsecret'],
            ];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$data['appid']."&secret=".$data['appsecret'];
        $response = httpRequest($url);
        $params = json_decode($response, true);
        if (isset($params['access_token'])) {
            $access_token = $params['access_token'];
            $data['access_token']  = $params['access_token'];
            $data['expire_time']  = getTime() + $params['expires_in'] - 1000;
            tpSetting('OpenMinicode', ['conf_wechat' => json_encode($data)]);

            $setting_info = [
                'appid' => $data['appid'],
                'secret' => $data['appsecret'],
                'access_token' => $data['access_token'],
                'expires_time' => $data['expire_time'] //提前200s过期
            ];
            tpSetting(md5($data['appid']), $setting_info);
            return [
                'code'  => 1,
                'access_token' => $access_token,
                'appid' => $data['appid'],
                'appsecret' => $data['appsecret'],
            ];
        }

        return [
            'code'  => 0,
            'errcode' => !empty($params['errcode']) ? $params['errcode'] : 0,
            'msg' => !empty($params['errmsg']) ? $params['errmsg'] : '请检查微信公众号AppId和AppSecret是否正确',
        ];
    }
}

if (!function_exists('eyou_send_notice')) 
{
    /**
     * 发送消息通知
     * @return [type] [description]
     */
    function eyou_send_notice($send_scene, $params = [])
    {
        // 引入模型
        $apiModel = new \app\api\model\v1\Api;
        // 表主表id值，可以是订单id、积分明细的id等，通过这个id可以查到整条记录
        $result_id = !empty($params['result_id']) ? $params['result_id'] : 0;
        // 会员ID
        $users_id = !empty($params['users_id']) ? intval($params['users_id']) : 0;
        // 订单付款通知
        if ($send_scene == 9) {
            // 公众号通知
            $admin_list = Db::name('admin')->where(['wechat_followed'=>1])->select();
            foreach ($admin_list as $key => $admin_info) {
                $apiModel->sendWechatNotice($result_id, $send_scene, $admin_info);
            }
        }
        // 订单发货通知
        else if ($send_scene == 7) {
            // 小程序通知
            $data = [
                'users_id' => $users_id,
            ];
            $apiModel->sendAppletsNotice($result_id, $send_scene, $data);
        }
        // 留言表单通知
        else if ($send_scene == 1) {
            // 公众号通知
            $admin_list = Db::name('admin')->where(['wechat_followed'=>1])->select();
            foreach ($admin_list as $key => $admin_info) {
                $apiModel->sendWechatNotice($result_id, $send_scene, $admin_info);
            }
        }

        return ['code'=>1, 'msg'=>'success'];
    }
}

if (!function_exists('eyou_statistics_data')) {
    /**
     * 记录统计数据
     * $change  金额/数量的变化
     * 1-浏览量 2-订单 3-销售额(传money) 4-新增会员 5-充值金额 6-商品数
     * $action = inc 增加 dec 减少
     */
    function eyou_statistics_data($type = 1,$change = 0,$now_time='',$action = 'inc')
    {
        try {
            $lang = get_current_lang();
            //不传时间的话就默认改动今天的
            if (empty($now_time)){
                $now_time = date('Y-m-d');
                $now_time = strtotime($now_time);
            }
            //不是统计金额的情况默认数量给1
            if (!in_array($type,[3,5]) && empty($change)){
                $change = 1;
            }
            $is_have = Db::name('statistics_data')->where(['date'=>$now_time,'type'=>$type,'lang'=>$lang])->find();
            if (!empty($is_have)){
                if ('inc' == $action){ //增加
                    //已经存在
                    if (in_array($type,[3,5])){
                        Db::name('statistics_data')->where(['date'=>$now_time,'type'=>$type,'lang'=>$lang])->setInc('total',$change);
                    }else{
                        Db::name('statistics_data')->where(['date'=>$now_time,'type'=>$type,'lang'=>$lang])->setInc('num',$change);
                    }
                }else{//减少
                    if (in_array($type,[3,5])){
                        Db::name('statistics_data')->where(['date'=>$now_time,'type'=>$type,'lang'=>$lang])->setDec('total',$change);
                    }else{
                        Db::name('statistics_data')->where(['date'=>$now_time,'type'=>$type,'lang'=>$lang])->setDec('num',$change);
                    }
                }
            }else{
                if ('inc' == $action){ //增加
                    //不存在
                    $insert = [
                        'date'=>$now_time,
                        'type'=>$type,
                        'lang'=>$lang,
                    ];
                    if (in_array($type,[3,5])){
                        $insert['total'] = $change;
                    }else{
                        $insert['num'] = $change;
                    }
                    Db::name('statistics_data')->insert($insert);
                }
            }
        } catch (\Exception $e) {
            
        }
        
        return true;
    }
}

if (!function_exists('del_statistics_data')) 
{
    /**
     * 删除文档后减少统计数
     * @param  [type] $type    [description]
     * @param  string $del_aid [description]
     * @return [type]          [description]
     */
    function del_statistics_data($type, $del_aid = '')
    {
        $del_aid = is_array($del_aid) ? $del_aid : [$del_aid];
        if (!empty($type) && !empty($del_aid)) {
            $ystd_count = $td_count = 0;
            $today = strtotime(date('Y-m-d'));
            $yesterday = $today - 86400;
            $where = [
                'aid' => ['IN', $del_aid],
                'add_time' => ['egt', $yesterday],
            ];
            $list = \think\Db::name('archives')->field('aid,add_time')->where($where)->select();
            foreach ($list as $key => $val) {
                if ($val['add_time'] < $today) { // 昨天统计
                    $ystd_count++;
                } else if ($val['add_time'] >= $today) { // 今天统计
                    $td_count++;
                }
            }
            //写入统计 减去
            if ($td_count > 0){
                eyou_statistics_data($type, $td_count,'','dec');//今天的
            }
            if ($ystd_count > 0){
                $ystd = strtotime('-1 day');
                eyou_statistics_data($type, $ystd_count,$ystd,'dec');//昨天的
            }
        }
    }
}

if (!function_exists('equal_pop_login')) 
{
    /**
     * 等保测评助手，引入等保加密相关js
     */
    function equal_pop_login($type = 'default')
    {
        $str = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $str = $equalLogic->popLogin($type);
        }
        return $str;
    }
}

if (!function_exists('get_seo_description_length')) 
{
    /**
     * 获取文档seo描述截取的长度
     */
    function get_seo_description_length()
    {
        $foreign_is_status = tpSetting('foreign.foreign_is_status', '', 'cn');
        if (!empty($foreign_is_status)) {
            $seoConfig = tpCache('seo');
            if (isset($seoConfig['seo_description_length'])) {
                return intval($seoConfig['seo_description_length']);
            }
        }

        return config('global.arc_seo_description_length');
    }
}

if (!function_exists('is_template_opt'))
{
    /**
     * 判断是否有权限操作模板文件
     * @param  [type]  $ip [description]
     * @return boolean     [description]
     */
    function is_template_opt() {
        static $template_opt = null;
        if (null === $template_opt) {
            $file = DATA_PATH.'conf'.DS.'template_opt.txt';
            $template_opt = file_exists($file) ? true : false;
        }

        return $template_opt;
    }
}

/**
 * ------------- 此行代码请保持最底部 --------------
 */
if (!function_exists('function_1601946443')) 
{
    /**
     * 引入插件公共函数
     */
    function function_1601946443()
    {
        $file_1601946443 = glob('weapp/*/function.php');
        foreach ($file_1601946443 as $key => $val) {
            include_once ROOT_PATH.$val;
        }
    }
}
// function_1601946443();