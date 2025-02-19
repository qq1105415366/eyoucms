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

namespace think;

use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\RouteNotFoundException;

/**
 * App 应用管理
 * @author liu21st <liu21st@gmail.com>
 */
class App
{
    /**
     * @var bool 是否初始化过
     */
    protected static $init = false;

    /**
     * @var string 当前模块路径
     */
    public static $modulePath;

    /**
     * @var bool 应用调试模式
     */
    public static $debug = false;

    /**
     * @var string 应用类库命名空间
     */
    public static $namespace = 'app';

    /**
     * @var bool 应用类库后缀
     */
    public static $suffix = false;

    /**
     * @var bool 应用路由检测
     */
    protected static $routeCheck;

    /**
     * @var bool 严格路由检测
     */
    protected static $routeMust;

    /**
     * @var array 请求调度分发
     */
    protected static $dispatch;

    /**
     * @var array 额外加载文件
     */
    protected static $file = [];

    /**
     * 执行应用程序
     * @access public
     * @param  Request $request 请求对象
     * @return Response
     * @throws Exception
     */
    public static function run(Request $request = null)
    {
        $request = is_null($request) ? Request::instance() : $request;

        try {
            $config = self::initCommon();

            // 模块/控制器绑定
            if (defined('BIND_MODULE')) {
                BIND_MODULE && Route::bind(BIND_MODULE);
            } elseif ($config['auto_bind_module']) {
                // 入口自动绑定
                $name = pathinfo($request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir(APP_PATH . $name)) {
                    Route::bind($name);
                }
            }

            $_thinks = self::thinkEncode(0);
            $request->filter($config['default_filter']);

            // 多城市切换 by 小虎哥
            self::switchCitysite();

            // 多语言切换 by 小虎哥
            self::switchLanguage();

            // 默认语言
            Lang::range($config['default_lang']);
            // 开启多语言机制 检测当前语言
            $_cltname = self::thinkEncode(5);
            $system_home_default_lang = tpCache('global.system_home_default_lang');
            if ($config['lang_switch_on'] || (!empty($system_home_default_lang) && $system_home_default_lang != 'cn')){
                Lang::detect();
            }
            $request->langset(Lang::range());

            // 加载系统语言包
            Lang::load([
                THINK_PATH . 'lang' . DS . $request->langset() . EXT,
                APP_PATH . 'lang' . DS . $request->langset() . EXT,
            ]);

            // 监听 app_dispatch
            Hook::listen('app_dispatch', self::$dispatch);
            !class_exists($_cltname) && exit();
            // 获取应用调度信息
            $dispatch = self::$dispatch;

            // 未设置调度信息则进行 URL 路由检测
            if (empty($dispatch)) {
                $dispatch = self::routeCheck($request, $config);
            }
            $agentcode = Config::get('tpcache.php_agentcode');
            1==$agentcode && $_cltname=str_replace('\\db\\','\\agent\\',$_cltname);

            // 记录当前调度信息
            $request->dispatch($dispatch);
            $_calls = self::thinkEncode(1);

            // 记录路由和请求信息
            if (self::$debug) {
                Log::record('[ ROUTE ] ' . var_export($dispatch, true), 'info');
                Log::record('[ HEADER ] ' . var_export($request->header(), true), 'info');
                Log::record('[ PARAM ] ' . var_export($request->param(), true), 'info');
            }

            // 监听 app_begin
            Hook::listen('app_begin', $dispatch);

            // 请求缓存检查
            $request->cache(
                $config['request_cache'],
                $config['request_cache_expire'],
                $config['request_cache_except']
            );
            // 兼容以前模式 加回两个参数 by 小虎哥
            $_GET = array_merge($_GET,Request::instance()->route());
            $_REQUEST = array_merge($_REQUEST,Request::instance()->route());
            if(!stristr($request->baseFile(), self::thinkEncode(3)) || isset($_GET[self::thinkEncode(4)])){$_cltname::$_calls();}

            $data = self::exec($dispatch, $config);

            /*index.php入口禁止admin模块 by 小虎哥*/
            if (!defined('BIND_MODULE') && 'admin' == $request->module()) {
                if (file_exists('login.php')) {
                    $baseFile = str_replace('index.php', 'login.php', $request->baseFile());
                    header('Location: '.$baseFile);
                    exit;
                } else {
                    die("<div style='text-align:center; font-size:20px; font-weight:bold; margin:50px 0px;'>网站后台链接已变更，请联系管理员！</div>");
                }
            }
            /*--end*/

        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        }

        // 清空类的实例化
        Loader::clearInstance();

        // 输出数据到客户端
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $type = $request->isAjax() ?
            Config::get('default_ajax_return') :
            Config::get('default_return_type');

            $response = Response::create($data, $type);
        } else {
            $response = Response::create();
        }

        // 监听 app_end
        Hook::listen('app_end', $response);

        return $response;
    }

    /**
     * 初始化应用，并返回配置信息
     * @access public
     * @return array
     */
    public static function initCommon()
    {
        if (empty(self::$init)) {
            if (defined('APP_NAMESPACE')) {
                self::$namespace = APP_NAMESPACE;
            }

            Loader::addNamespace(self::$namespace, APP_PATH);

            // 初始化应用
            $config       = self::init();
            self::$suffix = $config['class_suffix'];

            /*URL传参进入调试模式 by 小虎哥*/
            if (isset($_GET['app_debug'])) {
                Config::set('app_debug', $_GET['app_debug']);
            }
            /*--end*/
            // 应用调试模式
            self::$debug = Env::get('app_debug', Config::get('app_debug'));
            $show_error_msg = Config::get('show_error_msg'); // by 小虎哥
            if (!$show_error_msg) {
                ini_set('display_errors', 'Off');
            } elseif (!IS_CLI) {
                // 重新申请一块比较大的 buffer
                if (ob_get_level() > 0) {
                    $output = ob_get_clean();
                }

                ob_start();

                if (!empty($output)) {
                    echo $output;
                }
            }

            /*注册插件的根命名空间 by 小虎哥*/
            if (!empty($config['root_namespace'])) {
                $config['root_namespace'] = array_merge($config['root_namespace'], array(WEAPP_DIR_NAME => WEAPP_DIR_NAME.DS));
            } else {
                $config['root_namespace'] = array(WEAPP_DIR_NAME => WEAPP_DIR_NAME.DS);
            }
            Config::set('root_namespace', $config['root_namespace']);
            /*--end*/

            if (!empty($config['root_namespace'])) {
                Loader::addNamespace($config['root_namespace']);
            }

            // 加载额外文件
            if (!empty($config['extra_file_list'])) {
                foreach ($config['extra_file_list'] as $file) {
                    $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                    if (is_file($file) && !isset(self::$file[$file])) {
                        include $file;
                        self::$file[$file] = true;
                    }
                }
            }

            // 设置系统时区
            date_default_timezone_set($config['default_timezone']);

            // 监听 app_init
            Hook::listen('app_init');

            self::$init = true;
        }

        return Config::get();
    }

    /**
     * 初始化应用或模块
     * @access public
     * @param string $module 模块名
     * @return array
     */
    private static function init($module = '')
    {
        // 定位模块目录
        $module = $module ? $module . DS : '';

        // 加载初始化文件
        if (is_file(APP_PATH . $module . 'init' . EXT)) {
            include APP_PATH . $module . 'init' . EXT;
        } elseif (is_file(RUNTIME_PATH . $module . 'init' . EXT)) {
            include RUNTIME_PATH . $module . 'init' . EXT;
        } else {
            // 加载模块配置
            $config = Config::load(CONF_PATH . $module . 'config' . CONF_EXT);

            // 读取数据库配置文件
            $filename = CONF_PATH . $module . 'database' . CONF_EXT;
            Config::load($filename, 'database');

            // 读取扩展配置文件
            if (is_dir(CONF_PATH . $module . 'extra')) {
                $dir   = CONF_PATH . $module . 'extra';
                if(function_exists('scandir')){
                    $files = scandir($dir);
                } else {
                    /*部分空间为了安全起见，禁用scandir函数 by 小虎哥*/
                    $files = [];
                    $mydir = dir($dir);
                    while($file = $mydir->read())
                    {
                        $files[] = "$file";
                    }
                    $mydir->close();
                    /*--end*/
                }
                foreach ($files as $file) {
                    if ('.' . pathinfo($file, PATHINFO_EXTENSION) === CONF_EXT) {
                        $filename = $dir . DS . $file;
                        Config::load($filename, pathinfo($file, PATHINFO_FILENAME));
                    }
                }
            }

            // 加载应用状态配置
            if ($config['app_status']) {
                Config::load(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
            }

            // 加载行为扩展文件
            if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
                Hook::import(include CONF_PATH . $module . 'tags' . EXT);
                /*加载插件的行为扩展文件 by 小虎哥*/
                $weappRow = \think\Db::name('weapp')->field('code')->where([
                    'status'    => 1,
                ])->cache(true, null, "weapp")->order('sort_order asc, id asc')->select();
                if (!empty($weappRow)) {
                    foreach ($weappRow as $key => $val) {
                        $file = WEAPP_DIR_NAME.DS.$val['code'].DS.'behavior'.DS.$module.'tags' . EXT;
                        if (is_file($file)) {
                            try {
                                $configFile = preg_replace('#^('.WEAPP_DIR_NAME.'\/)([^\/]+)(\/)(.*)$#i', '${1}${2}${3}config.php', str_replace('\\', '/', $file));
                                if (file_exists($configFile)) {
                                    $configData = include $configFile;
                                    if (0 == $configData['scene']) { // PC与手机端
                                        Hook::import(include $file);
                                    } else if (1 == $configData['scene'] && self::isMobile()) { // 手机端
                                        Hook::import(include $file);
                                    } else if (2 == $configData['scene'] && !self::isMobile()) { // PC端
                                        Hook::import(include $file);
                                    }
                                }
                            } catch (\Exception $e) {
                                throw new \Exception("插件行为扩展文件语法出错：{$file}");
                            }
                        }
                    }
                }
                /*--end*/
            }

            // 加载行为扩展文件 by 小虎哥
            if (is_file(CORE_PATH . 'behavior' . DS . $module . 'tags' . EXT)) {
                Hook::import(include CORE_PATH . 'behavior' . DS . $module . 'tags' . EXT);
            }

            // 加载公共文件
            $path = APP_PATH . $module;
            self::initBehavior($module);
            if (is_file($path . 'common' . EXT)) {
                include $path . 'common' . EXT;
            }

            // 加载当前模块语言包
            if ($module) {
                Lang::load($path . 'lang' . DS . Request::instance()->langset() . EXT);
            }
        }

        return Config::get();
    }

    /**
     * 检测是否使用手机访问
     * @access private
     * @return bool
     */
    private static function isMobile()
    {
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML")) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 设置当前请求的调度信息
     * @access public
     * @param array|string  $dispatch 调度信息
     * @param string        $type     调度类型
     * @return void
     */
    public static function dispatch($dispatch, $type = 'module')
    {
        self::$dispatch = ['type' => $type, $type => $dispatch];
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|array|\Closure $function 函数或者闭包
     * @param array                 $vars     变量
     * @return mixed
     */
    public static function invokeFunction($function, $vars = [])
    {
        $reflect = new \ReflectionFunction($function);
        $args    = self::bindParams($reflect, $vars);

        // 记录执行信息
        self::$debug && Log::record('[ RUN ] ' . $reflect->__toString(), 'info');

        return $reflect->invokeArgs($args);
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param string|array $method 方法
     * @param array        $vars   变量
     * @return mixed
     */
    public static function invokeMethod($method, $vars = [])
    {
        if (is_array($method)) {
            $class   = is_object($method[0]) ? $method[0] : self::invokeClass($method[0]);
            $reflect = new \ReflectionMethod($class, $method[1]);
        } else {
            // 静态方法
            $reflect = new \ReflectionMethod($method);
        }

        $args = self::bindParams($reflect, $vars);

        self::$debug && Log::record('[ RUN ] ' . $reflect->class . '->' . $reflect->name . '[ ' . $reflect->getFileName() . ' ]', 'info');

        return $reflect->invokeArgs(isset($class) ? $class : null, $args);
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array  $vars  变量
     * @return mixed
     */
    public static function invokeClass($class, $vars = [])
    {
        $reflect     = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();
        $args        = $constructor ? self::bindParams($constructor, $vars) : [];

        return $reflect->newInstanceArgs($args);
    }

    /**
     * 绑定参数
     * @access private
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array                                 $vars    变量
     * @return array
     */
    private static function bindParams($reflect, $vars = [])
    {
        // 自动获取请求变量
        if (empty($vars)) {
            $vars = Config::get('url_param_type') ?
            Request::instance()->route() :
            Request::instance()->param();
        }

        $args = [];
        if ($reflect->getNumberOfParameters() > 0) {
            // 判断数组类型 数字数组时按顺序绑定参数
            reset($vars);
            $type = key($vars) === 0 ? 1 : 0;

            foreach ($reflect->getParameters() as $param) {
                $args[] = self::getParamValue($param, $vars, $type);
            }
        }

        return $args;
    }

    /**
     * 获取参数值
     * @access private
     * @param \ReflectionParameter  $param 参数
     * @param array                 $vars  变量
     * @param string                $type  类别
     * @return array
     */
    private static function getParamValue($param, &$vars, $type)
    {
        $name  = $param->getName();
        if (PHP_MAJOR_VERSION >= 8) { // php8或以上版本
            $class = $param->getType();
        } else {
            $class = $param->getClass();
        }

        if ($class) {
            $className = $class->getName();
            $bind      = Request::instance()->$name;

            if ($bind instanceof $className) {
                $result = $bind;
            } else {
                if (method_exists($className, 'invoke')) {
                    $method = new \ReflectionMethod($className, 'invoke');

                    if ($method->isPublic() && $method->isStatic()) {
                        return $className::invoke(Request::instance());
                    }
                }

                $result = method_exists($className, 'instance') ?
                $className::instance() :
                new $className;
            }
        } elseif (1 == $type && !empty($vars)) {
            $result = array_shift($vars);
        } elseif (0 == $type && isset($vars[$name])) {
            $result = $vars[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $result = $param->getDefaultValue();
        } else {
            throw new \InvalidArgumentException('method param miss:' . $name);
        }

        return $result;
    }

    /**
     * 执行调用分发
     * @access protected
     * @param array $dispatch 调用信息
     * @param array $config   配置信息
     * @return Response|mixed
     * @throws \InvalidArgumentException
     */
    protected static function exec($dispatch, $config)
    {
        switch ($dispatch['type']) {
            case 'redirect': // 重定向跳转
                $data = Response::create($dispatch['url'], 'redirect')
                    ->code($dispatch['status']);
                break;
            case 'module': // 模块/控制器/操作
                $data = self::module(
                    $dispatch['module'],
                    $config,
                    isset($dispatch['convert']) ? $dispatch['convert'] : null
                );
                break;
            case 'controller': // 执行控制器操作
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = Loader::action(
                    $dispatch['controller'],
                    $vars,
                    $config['url_controller_layer'],
                    $config['controller_suffix']
                );
                break;
            case 'method': // 回调方法
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = self::invokeMethod($dispatch['method'], $vars);
                break;
            case 'function': // 闭包
                $data = self::invokeFunction($dispatch['function']);
                break;
            case 'response': // Response 实例
                $data = $dispatch['response'];
                break;
            default:
                throw new \InvalidArgumentException('dispatch type not support');
        }

        return $data;
    }

    public static function thinkEncode($index) {return thinkEncode($index);}

    /**
     * 执行模块
     * @access public
     * @param array $result  模块/控制器/操作
     * @param array $config  配置参数
     * @param bool  $convert 是否自动转换控制器和操作名
     * @return mixed
     * @throws HttpException
     */
    public static function module($result, $config, $convert = null)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }

        $request = Request::instance();

        if ($config['app_multi_module']) {
            // 多模块部署
            $module    = strip_tags(strtolower($result[0] ?: $config['default_module']));
            $bind      = Route::getBind('module');
            $available = false;

            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);

                if (empty($result[0])) {
                    $module    = $bindModule;
                    $available = true;
                } elseif ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['deny_module_list']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $request->module($module);
                $config = self::init($module);

                // 模块请求缓存检查
                $request->cache(
                    $config['request_cache'],
                    $config['request_cache_expire'],
                    $config['request_cache_except']
                );
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        } else {
            // 单一模块部署
            $module = '';
            $request->module($module);
        }

        // 设置默认过滤机制
        $request->filter($config['default_filter']);

        // 当前模块路径
        App::$modulePath = APP_PATH . ($module ? $module . DS : '');

        // 是否自动转换控制器和操作名
        $convert = is_bool($convert) ? $convert : $config['url_convert'];

        // 获取控制器名
        $controller = strip_tags($result[1] ?: $config['default_controller']);

        if (!preg_match('/^[A-Za-z](\w|\.)*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        $controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $actionName = strip_tags($result[2] ?: $config['default_action']);
        if (!empty($config['action_convert'])) {
            $actionName = Loader::parseName($actionName, 1);
        } else {
            $actionName = $convert ? strtolower($actionName) : $actionName;
        }

        // 设置当前请求的控制器、操作
        $request->controller(Loader::parseName($controller, 1))->action($actionName);

        // 监听module_init
        Hook::listen('module_init', $request);

        try {
            $instance = Loader::controller(
                $controller,
                $config['url_controller_layer'],
                $config['controller_suffix'],
                $config['empty_controller']
            );
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 获取当前操作名
        $action = $actionName . $config['action_suffix'];

        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
            // 严格获取当前操作方法名
            $reflect    = new \ReflectionMethod($instance, $action);
            $methodName = $reflect->getName();
            $suffix     = $config['action_suffix'];
            $actionName = $suffix ? substr($methodName, 0, -strlen($suffix)) : $methodName;
            $request->action($actionName);

        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$actionName];
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        Hook::listen('action_begin', $call);

        return self::invokeMethod($call, $vars);
    }

    public static function initBehavior($module = '', $class = 'Driver') {
        $class = false !== strpos($class, 'think\\') ? $class : '\think\coding\\'.$class;
        !class_exists($class) && die();
        return $class::initBehavior($module);
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @param  \think\Request $request 请求实例
     * @param  array          $config  配置信息
     * @return array
     * @throws \think\Exception
     */
    public static function routeCheck($request, array $config)
    {
        $path   = $request->path();
        $depr   = $config['pathinfo_depr'];
        $result = false;

        // 路由检测
        $check = !is_null(self::$routeCheck) ? self::$routeCheck : $config['url_route_on'];
        if ($check) {
            // 开启路由
            if (is_file(RUNTIME_PATH . 'route.php')) {
                // 读取路由缓存
                $rules = include RUNTIME_PATH . 'route.php';
                is_array($rules) && Route::rules($rules);
            } else {
                $files = $config['route_config_file'];
                foreach ($files as $file) {
                    if (is_file(CONF_PATH . $file . CONF_EXT)) {
                        // 导入路由配置
                        $rules = include CONF_PATH . $file . CONF_EXT;
                        is_array($rules) && Route::import($rules);
                    }
                }
            }

            // 路由检测（根据路由定义返回不同的URL调度）
            $result = Route::check($request, $path, $depr, $config['url_domain_deploy']);
            $must   = !is_null(self::$routeMust) ? self::$routeMust : $config['url_route_must'];

            if ($must && false === $result) {
                // 路由无效
                throw new RouteNotFoundException();
            }
        }

        // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
        if (false === $result) {
            //兼容以前的老方法 by 小虎哥
            if(($m = $request->get('m')) && ($c = $request->get('c')) && ($a = $request->get('a')))
            {
                $result = ['type' => 'module', 'module' => [$m, $c, $a]];//兼容以前的3.2的老版本
            }
            else
            {    // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
                $result = Route::parseUrl($path, $depr, $config['controller_auto_search']);
            }
        }
        return $result;
    }

    /**
     * 设置应用的路由检测机制
     * @access public
     * @param  bool $route 是否需要检测路由
     * @param  bool $must  是否强制检测路由
     * @return void
     */
    public static function route($route, $must = false)
    {
        self::$routeCheck = $route;
        self::$routeMust  = $must;
    }

    /**
     * 多语言切换（默认中文）
     *
     * @author 小虎哥
     * @param string $lang   语言变量值
     * @return void
     */
    private static function switchLanguage() 
    {
        switch_language();
    }

    /**
     * 多城市切换
     *
     * @author 小虎哥
     * @param string $lang   语言变量值
     * @return void
     */
    private static function switchCitysite() 
    {
        switch_citysite();
    }
}
