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

//------------------------
// EyouCms 助手函数
//-------------------------

use think\Url;
use think\Config;

if (!function_exists('memcache')) 
{
    /**
     * 缓存管理
     * @param mixed     $name 缓存标识，具体查看./app/extra/admin_memcache.php
     * @param mixed     $value 缓存值
     * @return mixed
     */
    function memcache($name = null, $value = null, $options = false)
    {
        //暂时改用memcached
        return memcached($name, $value, $options);
        exit;


        //暂这么连接  后期更改
        static $memcache;
        // $module = strtolower(MODULE_NAME);
        $data = Config::get('memcache_key');

        // 关闭memcached时，自动改用cache方式
        if (Config::get('memcache.switch') == 0) {
            if (empty($name) || empty($data[$name])) {
                return false;
            }
            $expire = $data[$name]['expire'];
            return cache($name, $value, $expire);
        }

        if ($options === false) {
            $options = Config::get('memcache');
        }

        $memcache = new \think\cache\driver\Memcache($options);
        if (is_null($name) && is_null($value)) {
            return $memcache;
        }

        if (empty($name) || empty($data[$name])) {
            return false;
        }

        $key = md5(strtolower($name));
        $expire = $data[$name]['expire'];
        $tag = $data[$name]['tag'];

        if (is_null($value)) {
            // 获取缓存
            return true === $memcache->has($key) ? $memcache->get($key) : false;
        } elseif ('' === $value) {
            // 删除缓存
            return $memcache->rm($key);
        } else {
            // 缓存数据
            $expire = is_numeric($expire) ? $expire : null; //默认快捷缓存设置过期时间

            if (is_null($tag) || empty($tag)) {
                return $memcache->set($key, $value, $expire);
            } else {
                // $memcache->tag = $tag;
                return $memcache->set($key, $value, $expire);
            }
        }
    }
}

if (!function_exists('memcached')) 
{
    /**
     * 缓存管理
     * @param mixed     $name 缓存标识，具体查看./app/extra/admin_memcache.php
     * @param mixed     $value 缓存值
     * @return mixed
     */
    function memcached($name = null, $value = null, $options = false)
    {
        //暂这么连接  后期更改
        static $memcached;
        // $module = strtolower(MODULE_NAME);
        $data = Config::get('memcache_key');

        // 关闭memcached时，自动改用cache方式
        if (Config::get('memcache.switch') == 0) {
            if (empty($name) || empty($data[$name])) {
                return false;
            }
            $expire = $data[$name]['expire'];
            return cache($name, $value, $expire);
        }

        if ($options === false) {
            $options = Config::get('memcache');
        }

        $memcached = new \think\cache\driver\Memcached($options);
        if (is_null($name) && is_null($value)) {
            return $memcached;
        }

        if (empty($name) || empty($data[$name])) {
            return false;
        }

        $key = md5(strtolower($name));
        $expire = $data[$name]['expire'];
        $tag = $data[$name]['tag'];

        if (is_null($value)) {
            // 获取缓存
            return true === $memcached->has($key) ? $memcached->get($key) : false;
        } elseif ('' === $value) {
            // 删除缓存
            return $memcached->rm($key);
        } else {
            // 缓存数据
            $expire = is_numeric($expire) ? $expire : null; //默认快捷缓存设置过期时间

            if (is_null($tag) || empty($tag)) {
                return $memcached->set($key, $value, $expire);
            } else {
                // $memcached->tag = $tag;
                return $memcached->set($key, $value, $expire);
            }
        }
    }
}

if (!function_exists('extra_cache')) {
/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @param mixed $default 默认值
 * @return mixed
 */
    function extra_cache($name, $value = '', $expire = 0) {
        $request = think\Request::instance();
        $module = strtolower($request->module());
        $keys_list = config('extra_cache_key');

        $key = md5(strtolower($name));
        if (!isset($keys_list[$name])) {
            return false;
        }
        $options = $keys_list[$name]['options'];
        $cache_conf = config('cache');
        if ($expire > 0) {
            $cache_conf['expire'] = $expire;
        } else {
            if (!empty($options['expire'])) {
                $cache_conf['expire'] = $options['expire'];
            }
        }
        if (!empty($options['prefix'])) {
            $cache_conf['prefix'] = $options['prefix'];
        }

        $tag = $keys_list[$name]['tag'];
        if (empty($tag)) {
            $tag = $module;
        }

        return cache($key, $value, $cache_conf, $tag);
   }   
}

if (!function_exists('html_cache')) {
/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @param mixed $default 默认值
 * @return mixed
 */
    function html_cache($name, $value = '', $options = array()) {

        $new_conf = $options;

        if (!isset($options['path'])) {
            if (!stristr(request()->baseFile(), 'index.php')) {
                $lang = get_admin_lang();
            } else {
                $lang = get_home_lang();
            }
            if (isMobile()) {
                $path = HTML_PATH."other/{$lang}_mobile_cache/";
            } else {
                $path = HTML_PATH."other/{$lang}_pc_cache/";
            }
            $new_conf['path'] = $path;
        }

        if (is_numeric($options)) {
            $new_conf['expire'] = $options;
        }

        $cache_conf = config('cache');
        $cache_conf = array_merge($cache_conf, $new_conf);

        $tag = $cache_conf['prefix'];

        if (!is_array($name)) {
            $name = strtolower($name);
        } else {
            $name = array_merge($cache_conf, $name);
            return cache($name);
        }

        return cache($name, $value, $cache_conf, $tag);
   }   
}

if (!function_exists('typeurl')) {
    /**
     * 栏目Url生成
     * @param string        $url 路由地址
     * @param string|array  $param 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @param string          $seo_pseudo URL模式
     * @param string          $seo_pseudo_format URL格式
     * @return string
     */
    function typeurl($url = '', $param = '', $suffix = true, $domain = false, $seo_pseudo = null, $seo_pseudo_format = null)
    {
        // 解析参数 by 小虎哥
        if (is_string($param)) {
            parse_str($param, $param);
        }
        $root_dir = ROOT_DIR;
        $domain_old = $domain;
        if (!$domain){
            static $absolute_path_open = null;
            null === $absolute_path_open && $absolute_path_open = tpCache('web.absolute_path_open'); //是否开启绝对链接
            if ($absolute_path_open){
                $domain = true;
            }
        }

        $eyouUrl = '';
        static $uiset = null;
        null === $uiset && $uiset = input('param.uiset/s', 'off');
        $uiset = trim($uiset, '/');

        static $static_seo_pseudo = null;
        null === $static_seo_pseudo && $static_seo_pseudo = tpCache('seo.seo_pseudo');
        $seo_pseudo = !empty($seo_pseudo) ? $seo_pseudo : $static_seo_pseudo;

        //开启会员,查看权限限制，静态强制转动态
        static $web_users_switch = null;
        null === $web_users_switch && $web_users_switch = tpCache('web.web_users_switch');
        $param['page_limit'] = !empty($param['page_limit']) ? explode(',', $param['page_limit']) : [];
        if (!empty($web_users_switch) && !empty($param['typearcrank']) && $param['typearcrank'] > 0 && in_array(1,$param['page_limit']) && $seo_pseudo == 2){
            $seo_pseudo = 1;
        }

        if (empty($seo_pseudo_format)) {
            if (1 == $seo_pseudo) {
                $seo_pseudo_format = config('ey_config.seo_dynamic_format');
            }
        }

        // 多站点 - 静态模式下默认以动态URL访问
        static $web_basehost = null;
        null === $web_basehost && $web_basehost = tpCache('web.web_basehost');
        $full_domain = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
        static $city_switch_on = null;
        null === $city_switch_on && $city_switch_on = config('city_switch_on');
        if (true === $city_switch_on) {
            $domain = $domain_old;
            if ((1 == $seo_pseudo && 2 == $seo_pseudo_format) || (2 == $seo_pseudo)) {
                $seo_pseudo = 1;
                $seo_pseudo_format = 1;
            }
        }
        static $site_default_home = null;
        null === $site_default_home && $site_default_home = tpCache('site.site_default_home');
        $siteinfo = [];
        if (isset($param['site'])) {
            $siteinfo = $param['site'];
            unset($param['site']);
        }

        if ('on' != $uiset && 1 == $seo_pseudo && 2 == $seo_pseudo_format) {
            if (is_array($param)) {
                $vars = array(
                    'tid'   => $param['id'],
                );
            } else {
                parse_str($param, $vars);
            }

            /*城市站点的域名路径*/
            if (true === $city_switch_on && !empty($siteinfo['id'])) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($siteinfo['id']) && !empty($citysiteList[$siteinfo['id']])) {
                    if ($site_default_home != $siteinfo['id']) {
                        $vars['site'] = $citysiteList[$siteinfo['id']]['domain'];
                        $is_open_domain = $citysiteList[$siteinfo['id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    $vars['site'] = true;
                    $domain = $full_domain;
                } else {
                    $vars['site'] = true;
                }
            }
            /*--end*/

            $eyouUrl = url($url, array(), $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
            $urlParam = parse_url($eyouUrl);
            $query_str = isset($urlParam['query']) ? $urlParam['query'] : '';
            if (empty($query_str)) {
                $eyouUrl .= '?';
            } else {
                $eyouUrl .= '&';
            }
            $vars = http_build_query($vars);
            $eyouUrl .= $vars;

            if (true === $city_switch_on && !empty($siteinfo['id'])) {
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
        } elseif ('on' != $uiset && 2 == $seo_pseudo) { // 生成静态页面代码
            static $is_mobile = null;
            null === $is_mobile && $is_mobile = isMobile();
            static $response_type = null;
            null === $response_type && $response_type = config('ey_config.response_type', $response_type);
            if (!empty($response_type) && $is_mobile) { // 手机端访问非静态页面
                if (is_array($param)) {
                    $vars = array(
                        'tid'   => $param['id'],
                    );
                } else {
                    parse_str($param, $vars);
                }

                /*城市站点的域名路径*/
                if (true === $city_switch_on && !empty($siteinfo['id'])) {
                    static $subDomain = null;
                    if (null === $subDomain) {
                        $subDomain = request()->subDomain();
                    }
                    static $rootDomain = null;
                    if (null === $rootDomain) {
                        $rootDomain = request()->rootDomain();
                    }
                    static $citysiteList = null;
                    if (null === $citysiteList) {
                        $citysiteList = get_citysite_list();
                    }
                    $domain = $domain_old;
                    $is_open_domain = 0;
                    if (!empty($siteinfo['id']) && !empty($citysiteList[$siteinfo['id']])) {
                        if ($site_default_home != $siteinfo['id']) {
                            $vars['site'] = $citysiteList[$siteinfo['id']]['domain'];
                            $is_open_domain = $citysiteList[$siteinfo['id']]['is_open'];
                            if (!empty($is_open_domain)) {
                                $domain = $vars['site'].'.'.$rootDomain;
                                $vars['site'] = true;
                            }
                        }
                    } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                        $vars['site'] = true;
                        $domain = $full_domain;
                    } else {
                        $vars['site'] = true;
                    }
                }
                /*--end*/

                static $home_lang = null;
                null == $home_lang && $home_lang = get_home_lang(); // 前台语言 by 小虎哥
                static $main_lang = null;
                null == $main_lang && $main_lang = get_main_lang(); // 前台主体语言 by 小虎哥
                if ($home_lang != $main_lang) {
                    $vars['lang'] = get_home_lang();
                }
                $eyouUrl = url('home/Lists/index', $vars, true, false, 1);

                /*城市站点的域名路径*/
                if (true === $city_switch_on && !empty($siteinfo['id'])) { // 全国站显示城市文档时的URL处理
                    if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain) {
                        $eyouUrl .= "&site={$vars['site']}";
                    }
                    if (empty($is_open_domain)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                        } else {
                            $eyouUrl = rtrim($web_basehost, '/') . $eyouUrl;
                        }
                    }
                }
                /*--end*/
            }else{
                // PC端访问是静态页面
                static $seo_html_listname = null;
                null === $seo_html_listname && $seo_html_listname = tpCache('seo.seo_html_listname');
                static $seo_html_arcdir = null;
                null === $seo_html_arcdir && $seo_html_arcdir = tpCache('seo.seo_html_arcdir');

                if ($seo_html_listname == 1) {//存放顶级目录
                    $dirpath = explode('/',$param['dirpath']);
                    if($param['parent_id'] == 0){
                        $url = $seo_html_arcdir.'/'.$dirpath[1].'/';
                    }else{
                        $url = $seo_html_arcdir.'/'.$dirpath[1]."/lists_".$param['id'].'.html';
                    }
                } else if ($seo_html_listname == 3) { // 存放子级目录
                    $dirpath = explode('/',$param['dirpath']);
                    $url = $seo_html_arcdir.'/'.end($dirpath).'/';
                } else if ($seo_html_listname == 4) { // 自定义存放目录
                    $url = $seo_html_arcdir;
                    $diy_dirpath = !empty($param['diy_dirpath']) ? $param['diy_dirpath'] : '';
                    if (!empty($param['rulelist'])) {
                        $rulelist = ltrim($param['rulelist'], '/');
                        $rulelist = str_replace("{tid}", $param['id'], $rulelist);
                        $rulelist = str_replace("{page}", '', $rulelist);
                        $rulelist = preg_replace('/{(栏目目录|typedir)}(\/?)/i', $diy_dirpath.'/', $rulelist);
                        $rulelist = '/'.ltrim($rulelist, '/');
                        if (in_array($param['current_channel'], [6,8]) && !preg_match('/^{(栏目目录|typedir)}\/(list_{tid}_{page}|index)\.html$/i', $param['rulelist'])) {
                            $rulelist = preg_replace('/\/([\/]*)/i', '/', $rulelist);
                        } else {
                            $rulelist = preg_replace('/\/([\/]*)([^\/]*)$/i', '/', $rulelist);
                        }
                        $url .= $rulelist;
                    }else{
                        $url .= $diy_dirpath."/";
                    }
                } else {
                    $url = $seo_html_arcdir.$param['dirpath'].'/';
                }
                
                $eyouUrl = $root_dir.$url;
                if (false !== $domain) {
                    static $re_domain = null;
                    null === $re_domain && $re_domain = request()->domain();
                    if (true === $domain) {
                        $eyouUrl = $re_domain.$eyouUrl;
                    } else {
                        $eyouUrl = rtrim($domain, '/').$eyouUrl;
                    }
                }
            }

        } elseif ('on' != $uiset && 3 == $seo_pseudo) {
            if (is_array($param)) {
                $vars = array(
                    'tid'   => $param['dirname'],
                );
            } else {
                parse_str($param, $vars);
            }

            /*城市站点的域名路径*/
            if (true === $city_switch_on && !empty($siteinfo['id'])) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($siteinfo['id']) && !empty($citysiteList[$siteinfo['id']])) {
                    if ($site_default_home != $siteinfo['id']) {
                        $vars['site'] = $citysiteList[$siteinfo['id']]['domain'];
                        $is_open_domain = $citysiteList[$siteinfo['id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    $vars['site'] = true;
                    $domain = $full_domain;
                } else {
                    $vars['site'] = true;
                }
            }
            /*--end*/

            /*伪静态格式*/
            $seo_rewrite_format = config('ey_config.seo_rewrite_format');
            if (1 == intval($seo_rewrite_format)) {
                $eyouUrl = url('home/Lists/index', $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
                if (!strstr($eyouUrl, '.htm')){
                    $eyouUrl .= '/';
                }
            } else if (3 == intval($seo_rewrite_format)) {
                $eyouUrl = url('home/Lists/index', $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
                if (!strstr($eyouUrl, '.htm')){
                    $eyouUrl .= '/';
                }
            } else if (4 == intval($seo_rewrite_format)) {
                $dirname = str_replace($param['dirname'],"",$param['dirpath']);
                if (!empty($dirname)){
                    $dirname = str_ireplace("/", "-", $dirname);
                    if(!empty($dirname)){
                        $dirname = trim($dirname,"-");
                    }
                }
                if (!empty($dirname)){
                    $eyouUrl = url('home/Lists/index?'.$dirname, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
                }else{
                    $eyouUrl = url('home/Lists/index', $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
                }
                $eyouUrl = urldecode($eyouUrl);
                if (!strstr($eyouUrl, '.htm')){
                    $eyouUrl .= '/';
                }
            } else {
                $eyouUrl = url($url, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format); // 兼容v1.1.6之前被搜索引擎收录的URL
            }
            /*--end*/

            /*城市站点的域名路径*/
            if (true === $city_switch_on && !empty($siteinfo['id'])) { // 全国站显示城市文档时的URL处理
                if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain && !strstr($eyouUrl, $vars['site'])) {
                    if (stristr($eyouUrl, 'index.php')) {
                        $eyouUrl = str_ireplace('index.php', "index.php/{$vars['site']}", $eyouUrl);
                    } else if (!empty($root_dir)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/(\.([^\.\/]+))'.preg_quote($root_dir, '/').'/i', '${1}'.$root_dir.'/'.$vars['site'], $eyouUrl);
                        } else {
                            $eyouUrl = preg_replace('/^'.preg_quote($root_dir, '/').'/i', $root_dir.'/'.$vars['site'], $eyouUrl);
                        }
                    } else if (empty($root_dir)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/^(\.([^\.\/]+))\//i', '${1}/'.$vars['site'].'/', $eyouUrl);
                        } else {
                            $eyouUrl = preg_replace('/^\//i', '/'.$vars['site'].'/', $eyouUrl);
                        }
                    }
                }
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
            /*--end*/
        } else {
            if (is_array($param)) {
                $vars = array(
                    'tid'   => $param['id'],
                );
            } else {
                parse_str($param, $vars);
            }

            /*城市站点的域名路径*/
            if (true === $city_switch_on && !empty($siteinfo['id'])) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($siteinfo['id']) && !empty($citysiteList[$siteinfo['id']])) {
                    if ($site_default_home != $siteinfo['id']) {
                        $vars['site'] = $citysiteList[$siteinfo['id']]['domain'];
                        $is_open_domain = $citysiteList[$siteinfo['id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    $vars['site'] = true;
                    $domain = $full_domain;
                } else {
                    $vars['site'] = true;
                }
            }
            /*--end*/
            
            $eyouUrl = url('home/Lists/index', $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);

            /*城市站点的域名路径*/
            if (true === $city_switch_on && !empty($siteinfo['id'])) { // 全国站显示城市文档时的URL处理
                if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain) {
                    $eyouUrl .= "&site={$vars['site']}";
                }
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
            /*--end*/
        }

        return $eyouUrl;
    }
}

if (!function_exists('arcurl')) {
    /**
     * 文档Url生成
     * @param string        $url 路由地址
     * @param string|array  $param 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @param string          $seo_pseudo URL模式
     * @param string          $seo_pseudo_format URL格式
     * @return string
     */
    function arcurl($url = '', $param = '', $suffix = true, $domain = false, $seo_pseudo = '', $seo_pseudo_format = null)
    {
        static $globalConfig = null;
        null === $globalConfig && $globalConfig = tpCache('global');
        // 解析参数 by 小虎哥
        if (is_string($param)) {
            parse_str($param, $param);
        }
        $root_dir = ROOT_DIR;
        $domain_old = $domain;
        if (!$domain){
            if (!empty($globalConfig['absolute_path_open'])){ //是否开启绝对链接
                $domain = true;
            }
        }

        $eyouUrl = '';
        static $uiset = null;
        null === $uiset && $uiset = input('param.uiset/s', 'off');
        $uiset = trim($uiset, '/');
        
        $seo_pseudo = !empty($seo_pseudo) ? $seo_pseudo : intval($globalConfig['seo_pseudo']);

        //开启会员,查看权限限制，静态强制转动态
        if (!empty($globalConfig['web_users_switch']) && ((!empty($param['typearcrank']) && $param['typearcrank'] > 0) || (!empty($param['arcrank']) && $param['arcrank'] > 0)) && $seo_pseudo == 2){
            $seo_pseudo = 1;
        }

        if (empty($seo_pseudo_format)) {
            if (1 == $seo_pseudo) {
                $seo_pseudo_format = config('ey_config.seo_dynamic_format');
            }
        }
        // 多站点 - 静态模式下默认以动态URL访问
        $web_basehost = empty($globalConfig['web_basehost']) ? '' : $globalConfig['web_basehost'];
        $full_domain = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
        static $city_switch_on = null;
        null === $city_switch_on && $city_switch_on = config('city_switch_on');
        if (true === $city_switch_on) {
            if ((1 == $seo_pseudo && 2 == $seo_pseudo_format) || (2 == $seo_pseudo)) {
                $seo_pseudo = 1;
                $seo_pseudo_format = 1;
            }
        }
        $site_default_home = empty($globalConfig['site_default_home']) ? 0 : (int)$globalConfig['site_default_home'];
        $site_arcurl_showall = empty($globalConfig['site_arcurl_showall']) ? 0 : (int)$globalConfig['site_arcurl_showall'];
        if ('on' != $uiset && 1 == $seo_pseudo && 2 == $seo_pseudo_format) {
            if (is_array($param)) {
                $vars = array(
                    'aid'   => $param['aid'],
                );
            } else {
                parse_str($param, $vars);
            }
            /*城市站点的域名路径*/
            if (true === $city_switch_on) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($param['area_id']) && !empty($citysiteList[$param['area_id']])) {
                    if ($site_default_home != $param['area_id']) {
                        $vars['site'] = $citysiteList[$param['area_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['area_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['city_id']) && !empty($citysiteList[$param['city_id']])) {
                    if ($site_default_home != $param['city_id']) {
                        $vars['site'] = $citysiteList[$param['city_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['city_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['province_id']) && !empty($citysiteList[$param['province_id']])) {
                    if ($site_default_home != $param['province_id']) {
                        $vars['site'] = $citysiteList[$param['province_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['province_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    $vars['site'] = true;
                    $domain = $full_domain;
                } else {
                    $vars['site'] = true;
                }
            }
            /*--end*/
            $eyouUrl = url($url, array(), $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
            $urlParam = parse_url($eyouUrl);
            $query_str = isset($urlParam['query']) ? $urlParam['query'] : '';
            if (empty($query_str)) {
                $eyouUrl .= '?';
            } else {
                $eyouUrl .= '&';
            }
            $vars = http_build_query($vars);
            $eyouUrl .= $vars;

            if (true === $city_switch_on) {
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
        } elseif ($seo_pseudo == 2 && $uiset != 'on') { // 生成静态页面代码
            static $is_mobile = null;
            null === $is_mobile && $is_mobile = isMobile();
            static $response_type = null;
            null === $response_type && $response_type = config('ey_config.response_type', $response_type);
            if (!empty($response_type) && $is_mobile) { // 手机端访问非静态页面
                if (is_array($param)) {
                    $vars = array(
                        'aid'   => $param['aid'],
                    );
                } else {
                    parse_str($param, $vars);
                }
                /*城市站点的域名路径*/
                if (true === $city_switch_on) {
                    static $subDomain = null;
                    if (null === $subDomain) {
                        $subDomain = request()->subDomain();
                    }
                    static $rootDomain = null;
                    if (null === $rootDomain) {
                        $rootDomain = request()->rootDomain();
                    }
                    static $citysiteList = null;
                    if (null === $citysiteList) {
                        $citysiteList = get_citysite_list();
                    }
                    $domain = $domain_old;
                    $is_open_domain = 0;
                    if (!empty($param['area_id']) && !empty($citysiteList[$param['area_id']])) {
                        if ($site_default_home != $param['area_id']) {
                            $vars['site'] = $citysiteList[$param['area_id']]['domain'];
                            $is_open_domain = $citysiteList[$param['area_id']]['is_open'];
                            if (!empty($is_open_domain)) {
                                $domain = $vars['site'].'.'.$rootDomain;
                                $vars['site'] = true;
                            }
                        }
                    } else if (!empty($param['city_id']) && !empty($citysiteList[$param['city_id']])) {
                        if ($site_default_home != $param['city_id']) {
                            $vars['site'] = $citysiteList[$param['city_id']]['domain'];
                            $is_open_domain = $citysiteList[$param['city_id']]['is_open'];
                            if (!empty($is_open_domain)) {
                                $domain = $vars['site'].'.'.$rootDomain;
                                $vars['site'] = true;
                            }
                        }
                    } else if (!empty($param['province_id']) && !empty($citysiteList[$param['province_id']])) {
                        if ($site_default_home != $param['province_id']) {
                            $vars['site'] = $citysiteList[$param['province_id']]['domain'];
                            $is_open_domain = $citysiteList[$param['province_id']]['is_open'];
                            if (!empty($is_open_domain)) {
                                $domain = $vars['site'].'.'.$rootDomain;
                                $vars['site'] = true;
                            }
                        }
                    } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                        $vars['site'] = true;
                        $domain = $full_domain;
                    } else {
                        $vars['site'] = true;
                    }
                }
                /*--end*/
                static $home_lang = null;
                null == $home_lang && $home_lang = get_home_lang(); // 前台语言 by 小虎哥
                static $main_lang = null;
                null == $main_lang && $main_lang = get_main_lang(); // 前台主体语言 by 小虎哥
                if ($home_lang != $main_lang) {
                    $vars['lang'] = get_home_lang();
                }
                $eyouUrl = url('home/View/index', $vars, true, false, 1);

                /*城市站点的域名路径*/
                if (true === $city_switch_on) { // 全国站显示城市文档时的URL处理
                    if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain) {
                        $eyouUrl .= "&site={$vars['site']}";
                    }
                    if (empty($is_open_domain)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                        } else {
                            $eyouUrl = rtrim($web_basehost, '/') . $eyouUrl;
                        }
                    }
                }
                /*--end*/
            }
            else
            { // PC端访问是静态页面
                if (!empty($param['htmlfilename'])){
                    $aid = $param['htmlfilename'];
                }else{
                    $aid = $param['aid'];
                }
                $seo_html_pagename = empty($globalConfig['seo_html_pagename']) ? 0 : (int)$globalConfig['seo_html_pagename'];
                $seo_html_arcdir = empty($globalConfig['seo_html_arcdir']) ? '' : $globalConfig['seo_html_arcdir'];
                if($seo_html_pagename == 1){//存放顶级目录
                    $dirpath = explode('/',$param['dirpath']);
                    $url = $seo_html_arcdir.'/'.$dirpath[1].'/'.$aid.'.html';
                } else if ($seo_html_pagename == 3) { // 存放子级目录
                    $dirpath = explode('/',$param['dirpath']);
                    $url = $seo_html_arcdir.'/'.end($dirpath).'/'.$aid.'.html';
                } else if ($seo_html_pagename == 4) { // 自定义存放目录
                    $url = $seo_html_arcdir;
                    $diy_dirpath = !empty($param['diy_dirpath']) ? $param['diy_dirpath'] : '';
                    if (!empty($param['ruleview'])) {
                        $y = $m = $d = 1;
                        if (!empty($param['add_time'])){
                            $y = date('Y', $param['add_time']);
                            $m = date('m', $param['add_time']);
                            $d = date('d', $param['add_time']);
                        }
                        $ruleview = ltrim($param['ruleview'], '/');
                        $ruleview = str_ireplace("{aid}", $aid, $ruleview);
                        $ruleview = str_ireplace("{Y}", $y, $ruleview);
                        $ruleview = str_ireplace("{M}", $m, $ruleview);
                        $ruleview = str_ireplace("{D}", $d, $ruleview);
                        $ruleview = preg_replace('/{(栏目目录|typedir)}(\/?)/i', $diy_dirpath.'/', $ruleview);
                        $ruleview = '/'.ltrim($ruleview, '/');
                        $url .= $ruleview;
                    }else{
                        $url .= $diy_dirpath."/" . $aid.'.html';
                    }
                }else{
                    $url = $seo_html_arcdir.$param['dirpath'].'/'.$aid.'.html';
                }
                
                $eyouUrl = $root_dir.$url;
                if (false !== $domain) {
                    static $re_domain = null;
                    null === $re_domain && $re_domain = request()->domain();
                    if (true === $domain) {
                        $eyouUrl = $re_domain.$eyouUrl;
                    } else {
                        $eyouUrl = rtrim($domain, '/').$eyouUrl;
                    }
                }
            }

        } elseif ($seo_pseudo == 3 && $uiset != 'on') {
            /*伪静态格式*/
            $seo_rewrite_format = config('ey_config.seo_rewrite_format');
            if (1 == intval($seo_rewrite_format)) {
                $url = 'home/View/index';
                /*URL里第一层级固定是顶级栏目的目录名称*/
                static $tdirnameArr = null;
                null === $tdirnameArr && $tdirnameArr = every_top_dirname_list();
                if (!empty($param['dirname']) && isset($tdirnameArr[md5($param['dirname'])]['tdirname'])) {
                    $param['dirname'] = $tdirnameArr[md5($param['dirname'])]['tdirname'];
                }
                /*--end*/
            } else if (3 == intval($seo_rewrite_format)) {
                $url = 'home/View/index';
            }else if(4 == intval($seo_rewrite_format)){
                $dirname = str_replace($param['dirname'],"",$param['dirpath']);
                if (!empty($dirname)){
                    $dirname = str_ireplace("/", "-", $dirname);
                    if(!empty($dirname)){
                        $dirname = trim($dirname,"-");
                    }
                }
                if (!empty($dirname)){
                    $url = 'home/View/index?'.$dirname;
                }else{
                    $url = 'home/View/index';
                }
            }
            /*--end*/
            if (is_array($param)) {
                $vars = array(
                    'aid'   => !empty($param['htmlfilename']) ? $param['htmlfilename'] : $param['aid'],
                    'dirname'   => $param['dirname'],
                );
            } else {
                parse_str($param, $vars);
            }
            /*城市站点的域名路径*/
            if (true === $city_switch_on) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($param['area_id']) && !empty($citysiteList[$param['area_id']])) {
                    if ($site_default_home != $param['area_id']) {
                        $vars['site'] = $citysiteList[$param['area_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['area_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['city_id']) && !empty($citysiteList[$param['city_id']])) {
                    if ($site_default_home != $param['city_id']) {
                        $vars['site'] = $citysiteList[$param['city_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['city_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['province_id']) && !empty($citysiteList[$param['province_id']])) {
                    if ($site_default_home != $param['province_id']) {
                        $vars['site'] = $citysiteList[$param['province_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['province_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    if(isset($site_arcurl_showall)&&$site_arcurl_showall==1){  //20241119 是否跟随分站
                        $full_domain=$domain = get_home_site().'.'.$rootDomain;
                        $vars['site'] = true;
                    }else{                                        
                        $vars['site'] = true;
                        $domain = $full_domain;
                    } 
                } else {                            
                    if(isset($site_arcurl_showall)&&$site_arcurl_showall==1){
                         $vars['site'] = '';
                    }else{
                         $vars['site'] = true;
                    }
                }
            }
            /*--end*/
            $eyouUrl = url($url, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);

            /*城市站点的域名路径*/
            if (true === $city_switch_on) { // 全国站显示城市文档时的URL处理
                if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain && !strstr($eyouUrl, $vars['site'])) {
                    if (stristr($eyouUrl, 'index.php')) {
                        $eyouUrl = str_ireplace('index.php', "index.php/{$vars['site']}", $eyouUrl);
                    } else if (!empty($root_dir)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/(\.([^\.\/]+))'.preg_quote($root_dir, '/').'/i', '${1}'.$root_dir.'/'.$vars['site'], $eyouUrl);
                        } else {
                            $eyouUrl = preg_replace('/^'.preg_quote($root_dir, '/').'/i', $root_dir.'/'.$vars['site'], $eyouUrl);
                        }
                    } else if (empty($root_dir)) {
                        if (is_http_url($eyouUrl)) {
                            $eyouUrl = preg_replace('/^(\.([^\.\/]+))\//i', '${1}/'.$vars['site'].'/', $eyouUrl);
                        } else {
                            $eyouUrl = preg_replace('/^\//i', '/'.$vars['site'].'/', $eyouUrl);
                        }
                    }
                }
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
            /*--end*/
        } else {
            if (is_array($param)) {
                $vars = array(
                    'aid'   => $param['aid'],
                );
            } else {
                parse_str($param, $vars);
            }
            /*城市站点的域名路径*/
            if (true === $city_switch_on) {
                static $subDomain = null;
                if (null === $subDomain) {
                    $subDomain = request()->subDomain();
                }
                static $rootDomain = null;
                if (null === $rootDomain) {
                    $rootDomain = request()->rootDomain();
                }
                static $citysiteList = null;
                if (null === $citysiteList) {
                    $citysiteList = get_citysite_list();
                }
                $domain = $domain_old;
                $is_open_domain = 0;
                if (!empty($param['area_id']) && !empty($citysiteList[$param['area_id']])) {
                    if ($site_default_home != $param['area_id']) {
                        $vars['site'] = $citysiteList[$param['area_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['area_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['city_id']) && !empty($citysiteList[$param['city_id']])) {
                    if ($site_default_home != $param['city_id']) {
                        $vars['site'] = $citysiteList[$param['city_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['city_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($param['province_id']) && !empty($citysiteList[$param['province_id']])) {
                    if ($site_default_home != $param['province_id']) {
                        $vars['site'] = $citysiteList[$param['province_id']]['domain'];
                        $is_open_domain = $citysiteList[$param['province_id']]['is_open'];
                        if (!empty($is_open_domain)) {
                            $domain = $vars['site'].'.'.$rootDomain;
                            $vars['site'] = true;
                        }
                    }
                } else if (!empty($subDomain) && 'www' != $subDomain && $subDomain == get_home_site()) {
                    if(isset($site_arcurl_showall)&&$site_arcurl_showall==1){  //20241119 是否跟随分站
                        $full_domain=$domain = get_home_site().'.'.$rootDomain;
                        $vars['site'] = true;                         
                    }else{                        
                        $vars['site'] = true;
                        $domain = $full_domain;
                    }  
                } else {
                    $vars['site'] = true;
                }
            }
            /*--end*/
            $eyouUrl = url('home/View/index', $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);

            /*城市站点的域名路径*/
            if (true === $city_switch_on) { // 全国站显示城市文档时的URL处理
                if (true !== $vars['site'] && !empty($vars['site']) && $vars['site'] != $subDomain) {
                    $eyouUrl .= "&site={$vars['site']}";
                }
                if (empty($is_open_domain)) {
                    if (is_http_url($eyouUrl)) {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}'.$full_domain.'${5}', $eyouUrl);
                    } else {
                        $eyouUrl = preg_replace('/^(http(s)?:)?(\/\/)?([^\/]*)(.*)$/i', '${1}${3}${4}', $web_basehost) . $eyouUrl;
                    }
                }
            }
            /*--end*/
        }

        return $eyouUrl;
    }
}

if (!function_exists('tagurl')) {
    /**
     * Tag标签Url生成
     * @param string        $url 路由地址
     * @param string|array  $param 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @param string          $seo_pseudo URL模式
     * @param string          $seo_pseudo_format URL格式
     * @return string
     */
    function tagurl($url = '', $param = '', $suffix = true, $domain = false, $seo_pseudo = '', $seo_pseudo_format = null)
    {
        // 解析参数 by 小虎哥
        if (is_string($param)) {
            parse_str($param, $param);
        }
        if (!$domain){
            static $absolute_path_open = null;
            null === $absolute_path_open && $absolute_path_open = tpCache('web.absolute_path_open'); //是否开启绝对链接
            if ($absolute_path_open){
                $domain = true;
            }
        }
        
        $eyouUrl = '';
        static $is_plus_tags = null;
        if (null === $is_plus_tags) {
            if (is_dir('./weapp/Tags/')) {
                $is_plus_tags = 1;
            } else {
                $is_plus_tags = 0;
            }
        }

        static $tags_html = null;
        if (null === $tags_html && !empty($is_plus_tags)) {
            $tags_html = config('tpcache.plus_tags_html');    //tags插件是否开启，1：开启，0：关闭
        }
        static $tags_seo_pseudo = null;
        if (!empty($tags_html)){
            $tagsModel = new \weapp\Tags\model\TagsModel;
            $tagsConfData = $tagsModel->getWeappData();
            if (!empty($tagsConfData['data']['seo_pseudo'])){
                $tags_seo_pseudo = $tagsConfData['data']['seo_pseudo'];
            }
        }
        if (!empty($tags_html) && (empty($tags_seo_pseudo) || $tags_seo_pseudo == 2)) {   //插件静态模式
            static $tagsConf = null;
            null === $tagsConf && $tagsConf = tpCache('tags');
            if (!empty($param['tagid'])){   //内页
                $eyouUrl = ROOT_DIR."/tags/{$param['tagid']}.html";
                if (!empty($tagsConf['tags_mobile_dir']) && isMobile()){
                    $eyouUrl = ROOT_DIR."/{$tagsConf['tags_mobile_dir']}/{$param['tagid']}.html";
                }else if (!empty($tagsConf['tags_pc_dir']) && !isMobile()){
                    $eyouUrl = ROOT_DIR."/{$tagsConf['tags_pc_dir']}/{$param['tagid']}.html";
                }
            }else{      //列表页
                $eyouUrl = ROOT_DIR."/tags/";
                if (!empty($tagsConf['tags_mobile_dir']) && isMobile()){
                    $eyouUrl = ROOT_DIR."/{$tagsConf['tags_mobile_dir']}/";
                }else if (!empty($tagsConf['tags_pc_dir']) && !isMobile()){
                    $eyouUrl = ROOT_DIR."/{$tagsConf['tags_pc_dir']}/";
                }
            }

            if (false !== $domain) {
                static $re_domain = null;
                null === $re_domain && $re_domain = request()->domain();
                if (true === $domain) {
                    $eyouUrl = $re_domain.$eyouUrl;
                } else {
                    $host       = Config::get('app_host') ?: request()->host();
                    $rootDomain = substr_count($host, '.') > 1 ? substr(strstr($host, '.'), 1) : $host;
                    if (substr_count($domain, '.') < 2 && !strpos($domain, $rootDomain)) {
                        $domain .= '.' . $rootDomain;
                    }
                    if (false !== strpos($domain, '://')) {
                        $scheme = '';
                    } else {
                        $scheme = request()->isSsl() || Config::get('is_https') ? 'https://' : 'http://';
                    }
                    $eyouUrl = $scheme.rtrim($domain, '/').$eyouUrl;
                }
            }
        } else {
            if (is_array($param)) {
                $vars = array(
                    'tagid'   => $param['tagid'],
                );
                $vars = http_build_query($vars);
            } else {
                $vars = $param;
            }

            if (empty($seo_pseudo)) {
                if (!empty($tags_seo_pseudo)) { // tag静态化插件的伪静态和动态URL
                    $seo_pseudo = $tags_seo_pseudo;
                    $seo_pseudo_format = 1;
                } else {
                    static $seo_config = null;
                    null === $seo_config && $seo_config = tpCache('seo');
                    $seo_pseudo = !empty($seo_config['seo_pseudo']) ? $seo_config['seo_pseudo'] : config('ey_config.seo_pseudo');
                    $seo_dynamic_format = !empty($seo_config['seo_dynamic_format']) ? $seo_config['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
                    if (1 == $seo_pseudo) {
                        $seo_pseudo_format = $seo_dynamic_format;
                    }
                }
            } else if (empty($seo_pseudo_format)) {
                $seo_pseudo_format = config('ey_config.seo_dynamic_format');
            }
            $eyouUrl = url($url, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format);
            $eyouUrl = auto_hide_index($eyouUrl);
        }

        return $eyouUrl;
    }
}

if (!function_exists('askurl')) {
    /**
     * 问答模型Url生成
     * @param string $url 路由地址
     * @param string|array $param 变量
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @param string $seo_pseudo URL模式
     * @param string $seo_pseudo_format URL格式
     * @return string
     */
    function askurl($url = '', $param = '', $suffix = true, $domain = false, $seo_pseudo = '', $seo_pseudo_format = null, $seo_inlet = null)
    {
        // 解析参数 by 小虎哥
        if (is_string($param)) {
            parse_str($param, $param);
        }
        $eyouUrl    = '';
        static $static_seo_pseudo = null;
        null === $static_seo_pseudo && $static_seo_pseudo = tpCache('seo.seo_pseudo');
        $seo_pseudo = !empty($seo_pseudo) ? $seo_pseudo : $static_seo_pseudo;
        if (empty($seo_pseudo_format)) {
            if (1 == $seo_pseudo) {
                $seo_pseudo_format = config('ey_config.seo_dynamic_format');
            }
        }
        
        // 多站点 - 静态模式下默认以动态URL访问
        static $city_switch_on = null;
        null === $city_switch_on && $city_switch_on = config('city_switch_on');
        if (true === $city_switch_on) {
            if ((1 == $seo_pseudo && 2 == $seo_pseudo_format) || (2 == $seo_pseudo)) {
                $seo_pseudo = 1;
                $seo_pseudo_format = 1;
            }
        }

        if ($seo_pseudo == 3 || $seo_pseudo == 2) {
            if (is_array($param)) {
                $vars         = $param;
            } else {
                $vars = $param;
            }
            $eyouUrl = url($url, $vars, $suffix, $domain,3, $seo_pseudo_format, $seo_inlet);
            if (!strstr($eyouUrl, '.htm')){
                $eyouUrl .= '/';
            }
        } else {
            $eyouUrl = url($url, $param, $suffix, $domain, $seo_pseudo, $seo_pseudo_format, $seo_inlet);
        }

        return $eyouUrl;
    }
}

if (!function_exists('siteurl')) {
    /**
     * 多城市站点Url生成
     * @param string|array $siteinfo 城市站点信息
     * @return string
     */
    function siteurl($siteinfo = '')
    {
        if (is_array($siteinfo)) {
            $vars         = $siteinfo;
        } else {
            parse_str($siteinfo, $vars);
        }
        
        $eyouUrl = '';
        // 是否支持去掉index.php小尾巴
        static $seo_inlet = null;
        null === $seo_inlet && $seo_inlet = tpCache('seo.seo_inlet');
        // URL模式
        static $seo_pseudo = null;
        null === $seo_pseudo && $seo_pseudo = tpCache('seo.seo_pseudo');
        // http/https协议
        static $scheme = null;
        null === $scheme && $scheme = request()->scheme();
        // 网站根域名
        static $root_domain = null;
        null === $root_domain && $root_domain = request()->rootDomain();
        // 当前域名带端口
        static $host = null;
        null === $host && $host = request()->host();
        // 端口号
        static $port = null;
        null === $port && $port = request()->port();
        // 网站域名
        static $full_domain = null;
        if (null === $full_domain) {
            $web_basehost = tpCache('web.web_basehost');
            $full_domain = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
            $full_domain = $scheme.'://'.$full_domain;
            if (stristr($host, ':')) {
                $full_domain .= ":{$port}";
            }
        }

        // 多站点 - 静态模式下默认以动态URL访问
        static $city_switch_on = null;
        null === $city_switch_on && $city_switch_on = config('city_switch_on');
        if (true === $city_switch_on) {
            if (2 == $seo_pseudo) {
                $seo_pseudo = 1;
            }
        }

        /*去掉入口文件*/
        $inletStr = '/index.php';
        1 == intval($seo_inlet) && $inletStr = '';
        /*--end*/

        if (1 == $seo_pseudo) {
            if (empty($vars['is_open'])) {
                $url = $full_domain.ROOT_DIR.$inletStr;
                if (!empty($inletStr)) {
                    $url .= '?';
                } else {
                    $url .= '/?';
                }
                $eyouUrl = $url.http_build_query(['site'=>$vars['domain']]);
            } else {
                $url = $scheme.'://'.$vars['domain'].'.'.$root_domain.ROOT_DIR;
                $eyouUrl = $url;
            }
        } else {
            if (empty($vars['is_open'])) {
                $eyouUrl = $full_domain.ROOT_DIR.$inletStr.'/'.$vars['domain'].'/';
            } else {
                $eyouUrl = $scheme.'://'.$vars['domain'].'.'.$root_domain.ROOT_DIR.'/';
            }
        }

        return $eyouUrl;
    }
}

if (!function_exists('eyIntval')) {
    /**
     * 强制把数值转为整型
     * @param mixed        $data 任意数值
     * @return mixed
     */
    function eyIntval($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = intval($val);
            }
        } else if (is_string($data) && stristr($data, ',')) {
            $arr = explode(',', $data);
            foreach ($arr as $key => $val) {
                $arr[$key] = intval($val);
            }
            $data = implode(',', $arr);
        } else {
            $data = intval($data);
        }

        return $data;
    }
}

if (!function_exists('eyPreventShell')) {
    /**
     * 验证是否shell注入
     * @param mixed        $data 任意数值
     * @return mixed
     */
    function eyPreventShell($data = '')
    {
        $redata = true;
        if (!is_array($data) && (preg_match('/^phar:\/\//i', $data) || stristr($data, 'phar://'))) {
            $redata = false;
        }

        return $redata;
    }
}
