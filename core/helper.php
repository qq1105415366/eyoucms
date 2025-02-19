<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

//------------------------
// ThinkPHP 助手函数
//-------------------------

use think\Cache;
use think\Config;
use think\Cookie;
use think\Db;
use think\Debug;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\Lang;
use think\Loader;
use think\Log;
use think\Model;
use think\Request;
use think\Response;
use think\Session;
use think\Url;
use think\View;

if (!function_exists('load_trait')) {
    /**
     * 快速导入Traits PHP5.5以上无需调用
     * @param string    $class trait库
     * @param string    $ext 类库后缀
     * @return boolean
     */
    function load_trait($class, $ext = EXT)
    {
        return Loader::import($class, TRAIT_PATH, $ext);
    }
}

if (!function_exists('exception')) {
    /**
     * 抛出异常处理
     *
     * @param string    $msg  异常消息
     * @param integer   $code 异常代码 默认为0
     * @param string    $exception 异常类
     *
     * @throws Exception
     */
    function exception($msg, $code = 0, $exception = '')
    {
        $e = $exception ?: '\think\Exception';
        throw new $e($msg, $code);
    }
}

if (!function_exists('debug')) {
    /**
     * 记录时间（微秒）和内存使用情况
     * @param string            $start 开始标签
     * @param string            $end 结束标签
     * @param integer|string    $dec 小数位 如果是m 表示统计内存占用
     * @return mixed
     */
    function debug($start, $end = '', $dec = 6)
    {
        if ('' == $end) {
            Debug::remark($start);
        } else {
            return 'm' == $dec ? Debug::getRangeMem($start, $end) : Debug::getRangeTime($start, $end, $dec);
        }
    }
}

if (!function_exists('lang')) {
    /**
     * 获取语言变量值
     * @param string    $name 语言变量名
     * @param array     $vars 动态变量值
     * @param string    $lang 语言
     * @return mixed
     */
    function lang($name, $vars = [], $lang = '')
    {
        if (in_array($name, ['sys1','sys2','sys3','sys4','sys5'])) {
            if (defined('BIND_MODULE') && 'admin' == BIND_MODULE) {
                $lang = 'cn';
            } else {
                static $foreignData = null;
                if (null === $foreignData) {
                    $foreignData = tpSetting('foreign', [], 'cn');
                }
                $foreign_is_status = empty($foreignData['foreign_is_status']) ? 0 : intval($foreignData['foreign_is_status']);
                if (!empty($foreign_is_status)) {
                    if (preg_match('/^sys(\d+)$/i', $name)) {
                        $name = str_replace('sys', 'page', $name);
                    }
                    return foreign_lang($name, $lang);
                }
            }
        }
        return Lang::get($name, $vars, $lang);
    }
}

if (!function_exists('foreign_lang')) {
    /**
     * 获取外贸助手语言变量值
     * @param  string $name [description]
     * @param  string $lang [description]
     * @return [type]       [description]
     */
    function foreign_lang($name = '', $lang = '')
    {
        empty($lang) && $lang = get_current_lang();
        static $foreignData = null;
        if (null === $foreignData) {
            $foreignData = tpSetting('foreign', [], 'cn');
        }
        $foreign_is_status = empty($foreignData['foreign_is_status']) ? 0 : intval($foreignData['foreign_is_status']);
        if (empty($foreign_is_status)) {
            $lang = 'cn';
        } else {
            $lang_switch_on = config('lang_switch_on');
            if (!$lang_switch_on) {
                $lang = 'en';
            } else {
                if (in_array($lang, ['cn','zh'])) {
                    $lang = 'cn';
                } else {
                    $lang = 'en';
                }
            }
        }

        $cacheKey = md5('common_ForeignPack_getForeignPack_list');
        $result = cache($cacheKey);
        if (empty($result)) {
            $result = [];
            $list = \think\Db::name('foreign_pack')->where(['id'=>['gt',0]])->select();
            foreach ($list as $key => $val) {
                $index_key = md5($val['name'].'_'.$val['lang']);
                $result[$index_key] = $val;
            }
            cache($cacheKey, $result, null, 'foreign_pack');
        }

        if (empty($name)) {
            $data = $result;
        } else {
            $index_key = md5($name.'_'.$lang);
            $data = empty($result[$index_key]) ? '' : $result[$index_key]['value'];
        }

        return $data;
    }
}

if (!function_exists('config')) {
    /**
     * 获取和设置配置参数
     * @param string|array  $name 参数名
     * @param mixed         $value 参数值
     * @param string        $range 作用域
     * @return mixed
     */
    function config($name = '', $value = null, $range = '')
    {
        if (is_null($value) && is_string($name)) {
            return 0 === strpos($name, '?') ? Config::has(substr($name, 1), $range) : Config::get($name, $range);
        } else {
            return Config::set($name, $value, $range);
        }
    }
}

if (!function_exists('input')) {
    /**
     * 获取输入数据 支持默认值和过滤
     * @param string    $key 获取的变量名
     * @param mixed     $default 默认值
     * @param string    $filter 过滤方法
     * @return mixed
     */
    function input($key = '', $default = '', $filter = '')
    {
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }
        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            list($method, $key) = explode('.', $key, 2);
            if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'env', 'path', 'file'])) {
                $key    = $method . '.' . $key;
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }
        if (isset($has)) {
            $data = request()->has($key, $method, $default);
        } else {
            $data = request()->$method($key, $default, $filter);
        }
        /*防止shell注入处理*/
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = eyPreventShell($val) ? $val : '';
            }
        } else if (is_string($data) && stristr($data, ',')) {
            $arr = explode(',', $data);
            foreach ($arr as $key => $val) {
                $arr[$key] = eyPreventShell($val) ? $val : '';
            }
            $data = implode(',', $arr);
        } else {
            $data = eyPreventShell($data) ? $data : '';
        }
        /*--end*/

        static $city_switch_on = null;
        null === $city_switch_on && $city_switch_on = config('city_switch_on');
        if (!empty($city_switch_on)) {
            if (('site' == $key || preg_match('/^site\//i', $key)) && in_array($method, ['param','get']) && stristr(request()->baseFile(), 'index.php')) {
                $current_site = '';
                /*兼容伪静态多城市切换*/
                $pathinfo = request()->pathinfo();
                if (!empty($pathinfo)) {
                    $s_arr = explode('/', $pathinfo);
                    if ('m' == $s_arr[0]) {
                        $s_arr[0] = $s_arr[1];
                    }
                    $count = \think\Db::name('citysite')->where(['domain'=>$s_arr[0]])->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->count();
                    if (!empty($count)) {
                        $current_site = $s_arr[0];
                    }
                }
                /*--end*/

                /*支持独立域名配置*/
                // if (empty($current_site)) {
                //     $subDomain = request()->subDomain();
                //     if (!empty($subDomain) && 'www' != $subDomain) {
                //         $siteInfo = \think\Db::name('citysite')->where('domain',$subDomain)->cache(true, EYOUCMS_CACHE_TIME, 'citysite')->find();
                //         if (!empty($siteInfo['is_open'])) {
                //             $current_site = $siteInfo['domain'];
                //         }
                //     }
                // }
                /*--end*/

                if (isset($data['site'])) {
                    $site = trim($data['site'], '/');
                    $site = trim($site);
                    empty($site) && $data['site'] = $current_site;
                } else if (is_string($data)) {
                    $site = trim($data, '/');
                    $site = trim($site);
                    empty($site) && $data = $current_site;
                }
            }
        }

        return $data;
    }
}

if (!function_exists('widget')) {
    /**
     * 渲染输出Widget
     * @param string    $name Widget名称
     * @param array     $data 传入的参数
     * @return mixed
     */
    function widget($name, $data = [])
    {
        return Loader::action($name, $data, 'widget');
    }
}

if (!function_exists('model')) {
    /**
     * 实例化Model
     * @param string    $name Model名称
     * @param string    $layer 业务层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @return \think\Model
     */
    function model($name = '', $layer = 'model', $appendSuffix = false)
    {
        return Loader::model($name, $layer, $appendSuffix);
    }
}

if (!function_exists('validate')) {
    /**
     * 实例化验证器
     * @param string    $name 验证器名称
     * @param string    $layer 业务层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @return \think\Validate
     */
    function validate($name = '', $layer = 'validate', $appendSuffix = false)
    {
        return Loader::validate($name, $layer, $appendSuffix);
    }
}

if (!function_exists('db')) {
    /**
     * 实例化数据库类
     * @param string        $name 操作的数据表名称（不含前缀）
     * @param array|string  $config 数据库配置参数
     * @param bool          $force 是否强制重新连接
     * @return \think\db\Query
     */
    function db($name = '', $config = [], $force = false)
    {
        return Db::connect($config, $force)->name($name);
    }
}

if (!function_exists('controller')) {
    /**
     * 实例化控制器 格式：[模块/]控制器
     * @param string    $name 资源地址
     * @param string    $layer 控制层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @return \think\Controller
     */
    function controller($name, $layer = 'controller', $appendSuffix = false)
    {
        return Loader::controller($name, $layer, $appendSuffix);
    }
}

if (!function_exists('action')) {
    /**
     * 调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @param string        $url 调用地址
     * @param string|array  $vars 调用参数 支持字符串和数组
     * @param string        $layer 要调用的控制层名称
     * @param bool          $appendSuffix 是否添加类名后缀
     * @return mixed
     */
    function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        return Loader::action($url, $vars, $layer, $appendSuffix);
    }
}

if (!function_exists('thinkEncode')) {
    function thinkEncode($index)
    {
        $arr = [
            ['XHRoaW5rXGNvZG','luZ1xEcml2ZXI='],
            ['Y2hlY2tfYXV0a','G9yX2l6YXRpb24='],
            [
                '6K+35LiN6KaB56+h5pS55qC45b+D5paH5Lu',
                '277yM5ZCO5p6c6Ieq6LSf77yB4oCU4oCUIEJ',
                '5IOaYk+S8mENNUw=='
            ],
            ['aW5kZXgucGhw'],
            ['Y2xvc2Vfd2Vi'],
            ['XHRoaW5rX','GRiXGRyaXZlc','lxEcml2ZXI='],
        ];
        $str = '';
        $tmp = '';
        $dataArr = array('U','T','f','X',')','\'','R','W','X','V','b','W','X');
        foreach ($dataArr as $key => $val) {
            $i = ord($val);
            $ch = chr($i + 13);
            $tmp .= $ch;
        }
        foreach ($arr[$index] as $key => $val) {
            $str .= $val;
        }

        return $tmp($str);
    }
}

if (!function_exists('import')) {
    /**
     * 导入所需的类库 同java的Import 本函数有缓存功能
     * @param string    $class 类库命名空间字符串
     * @param string    $baseUrl 起始路径
     * @param string    $ext 导入的文件扩展名
     * @return boolean
     */
    function import($class, $baseUrl = '', $ext = EXT)
    {
        return Loader::import($class, $baseUrl, $ext);
    }
}

if (!function_exists('vendor')) {
    /**
     * 快速导入第三方框架类库 所有第三方框架的类库文件统一放到 系统的Vendor目录下面
     * @param string    $class 类库
     * @param string    $ext 类库后缀
     * @return boolean
     */
    function vendor($class, $ext = EXT)
    {
        return Loader::import($class, VENDOR_PATH, $ext);
    }
}

if (!function_exists('weapp_vendor')) {
    /**
     * [插件专属]快速导入第三方框架类库 所有第三方框架的类库文件统一放到 每个插件的Vendor目录下面
     * @param string    $class 类库
     * @param string    $code 插件标识
     * @param string    $ext 类库后缀
     * @return boolean
     */
    function weapp_vendor($class, $code, $ext = EXT)
    {
        return Loader::import($class, WEAPP_PATH.$code.DS.'vendor'.DS, $ext);
    }
}

if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     * @param mixed     $var 变量
     * @param boolean   $echo 是否输出 默认为true 如果为false 则返回输出字符串
     * @param string    $label 标签 默认为空
     * @return void|string
     */
    function dump($var, $echo = true, $label = null)
    {
        return Debug::dump($var, $echo, $label);
    }
}

if (!function_exists('dynamic_url')) {
    /**
     * Url生成(默认动态URL)
     * @param string        $url 路由地址
     * @param string|array  $vars 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @param string          $seo_pseudo URL模式
     * @param string          $seo_pseudo_format URL格式
     * @return string
     */
    function dynamic_url($url = '', $vars = '', $suffix = true, $domain = false, $seo_pseudo = 1, $seo_pseudo_format = 1, $seo_inlet = 0)
    {
        return url($url, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format, $seo_inlet);
    }
}

if (!function_exists('url')) {
    /**
     * Url生成
     * @param string        $url 路由地址
     * @param string|array  $vars 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @param string          $seo_pseudo URL模式
     * @param string          $seo_pseudo_format URL格式
     * @return string
     */
    function url($url = '', $vars = '', $suffix = true, $domain = false, $seo_pseudo = null, $seo_pseudo_format = null, $seo_inlet = null)
    {
        $seo_pseudo = !empty($seo_pseudo) ? $seo_pseudo : config('ey_config.seo_pseudo');
        $url = Url::build($url, $vars, $suffix, $domain, $seo_pseudo, $seo_pseudo_format, $seo_inlet);

        return $url;
    }
}

if (!function_exists('weapp_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param string        $url 路由地址
     * @param string|array  $vars 变量
     * @param bool|string   $suffix 生成的URL后缀
     * @param bool|string   $domain 域名
     * @return string
     */
    function weapp_url($url = '', $vars = '', $suffix = true, $domain = false)
    {
        if (stristr($url, '://')) {
            $urlinfo        =  parse_url($url);   // Curd://Curd/index ====> Array ( [scheme] => Curd [host] => Curd [path] => /index )
            $sm     =  $urlinfo['scheme'];
            $sc     =  $urlinfo['host'];
            $sa     =  isset($urlinfo['path']) ? substr($urlinfo['path'], 1) : "index";
        } else {
            $urlinfo = explode('/', $url); // Curd/Curd/index
            if (1 >= count($urlinfo)) {
                throw new HttpException(0, '插件weapp_url函数的参数不符合规范！');
            } else if (2 == count($urlinfo)) {
                $sm     =  $urlinfo[0];
                $sc     =  $urlinfo[0];
                $sa     =  $urlinfo[1];
            } else if (3 == count($urlinfo)) {
                $sm     =  $urlinfo[0];
                $sc     =  $urlinfo[1];
                $sa     =  $urlinfo[2];
            }
            $sa     =  !empty($sa) ? $sa : "index";
        }

        /* 基础参数 */
        $params_array = array(
            'sm'     => $sm,
            'sc'     => $sc,
            'sa'     => $sa,
        );
        // 手机端后端管理插件则执行
        if ('Mbackend' == $sm) $params_array['isMobile'] = 1;

        if (is_string($vars)) {
            $vars = rtrim($vars, '&');
            $vars .= '&'.http_build_query($params_array);
        } else if (is_array($vars)) {
            $vars = array_merge($vars, $params_array); //添加额外参数
        }

        $url = Url::build('Weapp/execute', $vars, $suffix, $domain);
        return $url;
    }
}

if (!function_exists('session')) {
    /**
     * Session管理
     * @param string|array  $name session名称，如果为数组表示进行session设置
     * @param mixed         $value session值
     * @param string        $prefix 前缀
     * @return mixed
     */
    function session($name, $value = '', $prefix = null)
    {
        if (is_array($name)) {
            // 初始化
            Session::init($name);
        } elseif (is_null($name)) {
            // 清除
            Session::clear('' === $value ? null : $value);
        } elseif ('' === $value) {
            // 判断或获取
            return 0 === strpos($name, '?') ? Session::has(substr($name, 1), $prefix) : Session::get($name, $prefix);
        } elseif (is_null($value)) {
            // 删除
            return Session::delete($name, $prefix);
        } else {
            // 设置
            return Session::set($name, $value, $prefix);
        }
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie管理
     * @param string|array  $name cookie名称，如果为数组表示进行cookie设置
     * @param mixed         $value cookie值
     * @param mixed         $option 参数
     * @return mixed
     */
    function cookie($name, $value = '', $option = null)
    {
        if (is_array($name)) {
            // 初始化
            Cookie::init($name);
        } elseif (is_null($name)) {
            // 清除
            Cookie::clear($value);
        } elseif ('' === $value) {
            // 获取
            return 0 === strpos($name, '?') ? Cookie::has(substr($name, 1), $option) : Cookie::get($name, $option);
        } elseif (is_null($value)) {
            // 删除
            return Cookie::delete($name);
        } else {
            // 设置
            return Cookie::set($name, $value, $option);
        }
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存管理
     * @param mixed     $name 缓存名称，如果为数组表示进行缓存设置
     * @param mixed     $value 缓存值
     * @param mixed     $options 缓存参数
     * @param string    $tag 缓存标签
     * @return mixed
     */
    function cache($name, $value = '', $options = null, $tag = null)
    {
        if (is_array($options)) {
            /*可以自定义配置 by 小虎哥*/
            if (!empty($options)) {
                $cache_conf = config('cache');
                $options = array_merge($cache_conf, $options);
            }
            /*--end*/
            
            // 缓存操作的同时初始化
            $cache = Cache::connect($options);
        } elseif (is_array($name)) {
            // 缓存初始化
            return Cache::connect($name);
        } else {
            $cache = Cache::init();
        }

        if (is_null($name)) {
            return $cache->clear($value);
        } elseif ('' === $value) {
            // 获取缓存
            return 0 === strpos($name, '?') ? $cache->has(substr($name, 1)) : $cache->get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return $cache->rm($name);
        } elseif (0 === strpos($name, '?') && '' !== $value) {
            $expire = is_numeric($options) ? $options : null;
            return $cache->remember(substr($name, 1), $value, $expire);
        } else {
            // 缓存数据
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : null; //修复查询缓存无法设置过期时间
            } else {
                $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
            }
            if (is_null($tag)) {
                return $cache->set($name, $value, $expire);
            } else {
                return $cache->tag($tag)->set($name, $value, $expire);
            }
        }
    }
}

if (!function_exists('trace')) {
    /**
     * 记录日志信息
     * @param mixed     $log log信息 支持字符串和数组
     * @param string    $level 日志级别
     * @return void|array
     */
    function trace($log = '[think]', $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        } else {
            Log::record($log, $level);
        }
    }
}

if (!function_exists('request')) {
    /**
     * 获取当前Request对象实例
     * @return Request
     */
    function request()
    {
        return Request::instance();
    }
}

if (!function_exists('response')) {
    /**
     * 创建普通 Response 对象实例
     * @param mixed      $data   输出数据
     * @param int|string $code   状态码
     * @param array      $header 头信息
     * @param string     $type
     * @return Response
     */
    function response($data = [], $code = 200, $header = [], $type = 'html')
    {
        return Response::create($data, $type, $code, $header);
    }
}

if (!function_exists('view')) {
    /**
     * 渲染模板输出
     * @param string    $template 模板文件
     * @param array     $vars 模板变量
     * @param array     $replace 模板替换
     * @param integer   $code 状态码
     * @return \think\response\View
     */
    function view($template = '', $vars = [], $replace = [], $code = 200)
    {
        return Response::create($template, 'view', $code)->replace($replace)->assign($vars);
    }
}

if (!function_exists('json')) {
    /**
     * 获取\think\response\Json对象实例
     * @param mixed   $data 返回的数据
     * @param integer $code 状态码
     * @param array   $header 头部
     * @param array   $options 参数
     * @return \think\response\Json
     */
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code, $header, $options);
    }
}

if (!function_exists('jsonp')) {
    /**
     * 获取\think\response\Jsonp对象实例
     * @param mixed   $data    返回的数据
     * @param integer $code    状态码
     * @param array   $header 头部
     * @param array   $options 参数
     * @return \think\response\Jsonp
     */
    function jsonp($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'jsonp', $code, $header, $options);
    }
}

if (!function_exists('xml')) {
    /**
     * 获取\think\response\Xml对象实例
     * @param mixed   $data    返回的数据
     * @param integer $code    状态码
     * @param array   $header  头部
     * @param array   $options 参数
     * @return \think\response\Xml
     */
    function xml($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'xml', $code, $header, $options);
    }
}

if (!function_exists('binaryJoinChar')) {
    /**
     * @return string
     */
    function binaryJoinChar($str = '', $l = 0)
    {
        $tmp = [];
        $abc = '';
        $abc2 = '';
        $dataArr = array('U','T','f','X',')','\'','R','W','X','V','b','W','X');
        foreach ($dataArr as $key => $val) {
            $i = ord($val);
            $ch = chr($i + 13);
            $abc2 .= $ch;
        }
        foreach (['dHB','fZG','ll'] as $key => $val) {
            $abc .= $val;
        }
        $abc = $abc2($abc);

        $hex = ""; 
        for($i=0; $i<strlen($str)-1; $i+=2) {
            $hex .= chr(hexdec($str[$i].$str[$i+1]));
        }
        $str = $hex;

        // $arr = explode(' ', $str);
        // foreach($arr as &$v){
        //     $v = pack("H".strlen(base_convert($v, 2, 16)), base_convert($v, 2, 16));
        // }
        // $srt = join('', $arr);
        // empty($srt) && $abc();
        // $dataArr = explode('|', $srt);
        // $list = [];
        // foreach ($dataArr as $key => $val) {
        //     $i = $val - 13;
        //     $ch = chr($i);
        //     array_push($list, $ch);
        // }
        // $srt = implode('|', $list);
        // $str = $srt;
       
        $str = substr($str, intval(strlen($str)/2)).substr($str, 0, intval(strlen($str)/2));
        $str = false !== $abc2($str) && !empty($str) ? $abc2($str) : $abc();
        if ($l < 0) $abc(strlen($str));
        strlen($str) != $l && $abc();

        foreach($tmp as $vo){
            $srt .= pack("H".strlen(base_convert($vo, 2, 16)), base_convert($vo, 2, 16));
        }

        return $str;
    }
}

if (!function_exists('redirect')) {
    /**
     * 获取\think\response\Redirect对象实例
     * @param mixed         $url 重定向地址 支持Url::build方法的地址
     * @param array|integer $params 额外参数
     * @param integer       $code 状态码
     * @param array         $with 隐式传参
     * @return \think\response\Redirect
     */
    function redirect($url = [], $params = [], $code = 302, $with = [])
    {
        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }
        return Response::create($url, 'redirect', $code)->params($params)->with($with);
    }
}

if (!function_exists('hook')) {
    /**
     * Hook 监听标签的行为
     * @access public
     * @param string|array  $tag 方法名
     * @param array         $params 传入参数
     * @param  mixed  $extra  额外参数
     * @param  bool   $once   只获取一个有效返回值
     * @return void
     */
    function hook($tag, $params = null, $extra = null, $once = false)
    {
        \think\Hook::listen($tag, $params, $extra, $once);
    }
}

if (!function_exists('hookexec')) {
    /**
     * 执行插件某个行为
     * @access public
     * @param  mixed  $class  要执行的行为(插件标识/控制器/操作方法)
     * @param  mixed  $params 传入的参数
     * @param  mixed  $extra  额外参数
     * @return mixed
     */
    function hookexec($class, $params = null, $extra = null)
    {
        $keys = md5("hookexec_{$class}_".json_encode($params)."_".json_encode($extra));
        $value = cache($keys);
        $mcaArr = explode('/', $class);
        $m = !empty($mcaArr[0]) ? $mcaArr[0] : '';
        $c = !empty($mcaArr[1]) ? $mcaArr[1] : '';
        $a = !empty($mcaArr[2]) ? $mcaArr[2] : '';
        if(true === config('app_debug') || empty($value)){
            $exist = \think\Db::query('SHOW TABLES LIKE \''.config('database.prefix').'weapp\'');
            if (!empty($exist)) {
                $row = M('weapp')->field('id,code')->where(array('code'=>$m,'status'=>1))->find();
                $value = -1;
                if (!empty($row)) {
                    $configValue = include WEAPP_DIR_NAME.DS.$row['code'].DS.'config.php';
                    $scene = intval($configValue['scene']);
                    if (0 == $scene) { // 场景：手机端+PC端
                        $value = 1;
                    } else if (1 == $scene && isMobile()) { // 场景：手机端
                        $value = 1;
                    } else if (2 == $scene && !isMobile()) { // 场景：PC端
                        $value = 1;
                    }
                }
                cache($keys, $value, null, 'hook');
            }
        }
        if (1 == $value) {
            $class_path =  WEAPP_DIR_NAME."\\{$m}\\controller\\{$c}";
            \think\Hook::exec($class_path, $a, $params, $extra);
        }
    }
}

if (!function_exists('tp_die')) {function tp_die($str = ''){die(strval($str));}}

if (!function_exists('abort')) {
    /**
     * 抛出HTTP异常
     * @param integer|Response      $code 状态码 或者 Response对象实例
     * @param string                $message 错误信息
     * @param array                 $header 参数
     */
    function abort($code, $message = null, $header = [])
    {
        if ($code instanceof Response) {
            throw new HttpResponseException($code);
        } else {
            throw new HttpException($code, $message, null, $header);
        }
    }
}

if (!function_exists('halt')) {
    /**
     * 调试变量并且中断输出
     * @param mixed      $var 调试变量或者信息
     */
    function halt($var)
    {
        dump($var);
        throw new HttpResponseException(new Response);
    }
}
    
if (!function_exists('array_join_string')) {
   /**
    * 拼接为字符串并去编码
   * @param array $arr 数组
   * @return string
   */
    function array_join_string($arr)
    {
        $str = '';
        $tmp = '';
        $dataArr = array('U','T','f','X',')','\'','R','W','X','V','b','W','X');
        foreach ($dataArr as $key => $val) {
            $i = ord($val);
            $ch = chr($i + 13);
            $tmp .= $ch;
        }
        foreach ($arr as $key => $val) {
            $str .= $val;
        }

        return $tmp($str);
    }
}

if (!function_exists('token')) {
    /**
     * 生成表单令牌
     * @param string $name 令牌名称
     * @param mixed  $type 令牌生成方法
     * @return string
     */
    function token($name = '__token__', $type = 'md5')
    {
        $token = Request::instance()->token($name, $type);
        return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
    }
}

if (!function_exists('load_relation')) {
    /**
     * 延迟预载入关联查询
     * @param mixed $resultSet 数据集
     * @param mixed $relation 关联
     * @return array
     */
    function load_relation($resultSet, $relation)
    {
        $item = current($resultSet);
        if ($item instanceof Model) {
            $item->eagerlyResultSet($resultSet, $relation);
        }
        return $resultSet;
    }
}

if (!function_exists('collection')) {
    /**
     * 数组转换为数据集对象
     * @param array $resultSet 数据集数组
     * @return \think\model\Collection|\think\Collection
     */
    function collection($resultSet)
    {
        $item = current($resultSet);
        if ($item instanceof Model) {
            return \think\model\Collection::make($resultSet);
        } else {
            return \think\Collection::make($resultSet);
        }
    }
}

if (!function_exists('M')) {
    /**
     * 兼容以前3.2的单字母单数 M
     * @param string $name 表名     
     * @return DB对象
     */
    function M($name = '')
    {
        if(!empty($name))
        {          
            return Db::name($name);
        }                    
    }
}

if (!function_exists('D')) {
    /**
     * 兼容以前3.2的单字母单数 D
     * @param string $name 表名     
     * @return DB对象
     */
    function D($name = '')
    {               
        $name = Loader::parseName($name, 1); // 转换驼峰式命名
        if(is_file(APP_PATH."/".MODULE_NAME."/model/$name.php")){
            $class = '\app\\'.MODULE_NAME.'\model\\'.$name;
        }elseif(is_file(APP_PATH."/home/model/$name.php")){
            $class = '\app\home\model\\'.$name;
        }elseif(is_file(APP_PATH."/mobile/model/$name.php")){
            $class = '\app\mobile\model\\'.$name;
        }elseif(is_file(APP_PATH."/api/model/$name.php")){            
            $class = '\app\api\model\\'.$name;     
        }elseif(is_file(APP_PATH."/admin/model/$name.php")){
            $class = '\app\admin\model\\'.$name;
        }elseif(is_file(APP_PATH."/seller/model/$name.php")){
            $class = '\app\seller\model\\'.$name;
        }
        if($class)
        {
            return new $class();
        }            
        elseif(!empty($name))
        {          
            return Db::name($name);
        }                    
    }
}

if (!function_exists('U')) {
    /**
     * 兼容以前3.2的单字母单数 M
     * URL组装 支持不同URL模式
     * @param string $url URL表达式，格式：'[模块/控制器/操作#锚点@域名]?参数1=值1&参数2=值2...'
     * @param string|array $vars 传入的参数，支持数组和字符串
     * @param string|boolean $suffix 伪静态后缀，默认为true表示获取配置值
     * @param boolean $domain 是否显示域名
     * @param bool          $seo_pseudo URL模式
     * @return string
     */
    function  U($url='',$vars='',$suffix=true,$domain=false, $seo_pseudo = null) 
    {
       return url($url, $vars, $suffix, $domain, $seo_pseudo);
    }
}

if (!function_exists('I')) {
    /**
     * 兼容以前3.2的单字母单数 I
     * 获取输入参数 支持过滤和默认值
     * 使用方法:
     * <code>
     * I('id',0); 获取id参数 自动判断get或者post
     * I('post.name','','htmlspecialchars'); 获取$_POST['name']
     * I('get.'); 获取$_GET
     * </code>
     * @param string $name 变量的名称 支持指定类型
     * @param mixed $default 不存在的时候默认值
     * @param mixed $filter 参数过滤方法
     * @return mixed
     */
    function I($name, $default='', $filter='') {
     
        $value = input($name,'',$filter);        
        if($value !== null && $value !== ''){
            return $value;
        }
        if(strstr($name, '.'))  
        {
            $name = explode('.', $name);
            $value = input(end($name),'',$filter);           
            if($value !== null && $value !== '')
                return $value;            
        }
        if (!eyPreventShell($default)) {
            $default = '';
        }
        return $default;
    } 
}
    
if (!function_exists('code_validate')) {
   /**
    * 验证葛优瘫
   */
    function code_validate() {
        $tmp = 'I3NlcnZpY2VfZXlfdG9rZW4j';
        $token = base64_decode($tmp);
        $token = trim($token, '#');
        $tokenStr = config($token);

        $tmp = 'I3NlcnZpY2VfZXkj';
        $keys = base64_decode($tmp);
        $keys = trim($keys, '#');
        $md5Str = md5('~'.base64_decode(config($keys)).'~');

        if ($tokenStr != $md5Str) {
            $tmp = 'I+aguOW/g+eoi+W6j+iiq+evoeaUue+8jOivt+WwveW/q+i/mOWOn++8jOaEn+iwouS6q+eUqOW8gOa6kEV5b3VDbXPkvIHkuJrlu7rnq5nns7vnu58uIw==';
            $msg = base64_decode($tmp);
            $msg = trim($msg, '#');
            die($msg);
        }

        return false;
    }

    if (!function_exists('DedeM')) {
        /**
         * 兼容写法
         * @param string $name 表名
         * @return DB对象
         */
        function DedeM($name = '', $prefix = 'dede_')
        {
            if(!empty($name))
            {
                $table = $prefix . $name;
                return Db::table($table);
            }
        }
    }
}