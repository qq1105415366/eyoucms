<?php

namespace think;

use think\Session;
use think\exception\ValidateException;
use traits\controller\Jump;

Loader::import('controller/Jump', TRAIT_PATH, EXT);

class WeappController
{
    use Jump;

    /**
     * @var \think\View 视图类实例
     */
    protected $view;

    /**
     * @var \think\Request Request 实例
     */
    protected $request;

    /**
     * @var bool 验证失败是否抛出异常
     */
    protected $failException = false;

    /**
     * @var bool 是否批量验证
     */
    protected $batchValidate = false;

    /**
     * @var array 前置操作方法列表
     */
    protected $beforeActionList = [];

    /**
     * @var array 当前子插件根目录
     */
    protected $weapp_path          =   '';

    /**
     * @var array 当前子插件配置文件路径
     */
    protected $config_file         =   '';

    /**
     * @var array 当前子插件模块分组
     */
    protected $weapp_module_name       =   '';

    /**
     * @var array 当前子插件控制器
     */
    protected $weapp_controller_name       =   '';

    /**
     * @var array 当前子插件操作名
     */
    protected $weapp_action_name       =   '';

    /**
     * 主体语言（语言列表中最早一条）
     */
    public $main_lang = 'cn';

    /**
     * 后台当前语言
     */
    public $admin_lang = 'cn';

    /**
     * 前台当前语言
     */
    public $home_lang = 'cn';

    /**
     * 子目录路径
     */
    public $root_dir = '';

    /**
     * CMS版本号
     */
    public $version = null;

    /**
     * 是否访问手机版
     */
    public $is_mobile = 0;

    /**
     * 站点域名，带端口号
     */
    public $website_host = '';

    /**
     * 构造方法
     * @access public
     * @param Request $request Request 对象
     */
    public function __construct(Request $request = null)
    {
        if (!session_id()) {
            Session::start();
        }
        header("Cache-control: private");  // history.back返回后输入框值丢失问题

        if (is_null($request)) {
            $request = Request::instance();
        }
        $this->request = $request;
        $this->root_dir = ROOT_DIR; // 子目录
        $this->website_host = $this->request->host();
        if (stristr($this->website_host, 'localhost')) {
            $web_basehost = preg_replace('/^(([^\:\.]+):)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', config('tpcache.web_basehost'));
            if (!empty($web_basehost)) {
                $host_port = !stristr($this->website_host, ':') ? '' : $request->port();
                $this->website_host = $web_basehost;
                if (!empty($host_port) && !stristr($this->website_host, ':')) {
                    $this->website_host .= ":{$host_port}";
                }
            }
        }

        /*多语言*/
        $this->home_lang = get_home_lang();
        $this->admin_lang = get_admin_lang();
        $this->main_lang = get_main_lang();
        // 系统版本号
        null === $this->version && $this->version = getCmsVersion();
        null === $this->is_mobile && $this->is_mobile = isMobile() ? 1 : 0;

        if (!defined('IS_AJAX')) {
            $this->request->isAjax() ? define('IS_AJAX',true) : define('IS_AJAX',false);  // 
        }
        if (!defined('IS_GET')) {
            ($this->request->method() == 'GET') ? define('IS_GET',true) : define('IS_GET',false);  // 
        }
        if (!defined('IS_POST')) {
            ($this->request->method() == 'POST') ? define('IS_POST',true) : define('IS_POST',false);  // 
        }
        if (!defined('IS_AJAX_POST')) {
            ($this->request->isAjax() && $this->request->method() == 'POST') ? define('IS_AJAX_POST',true) : define('IS_AJAX_POST',false);  // 
        }

        $class = get_class($this); // 返回对象的类名
        $wmcArr = explode('\\', $class);
        $this->weapp_module_name = $this->request->param('sm') ? $this->request->param('sm') : $wmcArr[1]; // 当前插件模块名称
        $this->weapp_controller_name = $this->request->param('sc') ? $this->request->param('sc') : $wmcArr[3]; // 当前插件控制器名称
        $this->weapp_action_name = $this->request->param('sa') ? $this->request->param('sa') : 'index'; // 当前插件操作名称是
        !defined('WEAPP_MODULE_NAME') && define('WEAPP_MODULE_NAME', $this->weapp_module_name);  // 当前模块名称是
        !defined('WEAPP_CONTROLLER_NAME') && define('WEAPP_CONTROLLER_NAME', $this->weapp_controller_name); // 当前控制器名称
        !defined('WEAPP_ACTION_NAME') && define('WEAPP_ACTION_NAME', $this->weapp_action_name); // 当前操作名称是
        !defined('PREFIX') && define('PREFIX',Config::get('database.prefix')); // 数据库表前缀
        !defined('SYSTEM_ADMIN_LANG') && define('SYSTEM_ADMIN_LANG', Config::get('global.admin_lang')); // 后台语言变量
        !defined('SYSTEM_HOME_LANG') && define('SYSTEM_HOME_LANG', Config::get('global.home_lang')); // 前台语言变量

        // 模板路径
        $template = Config::get('template');
        $template['view_path'] = './'.WEAPP_DIR_NAME.'/'.$this->weapp_module_name.'/template/';
        Config::set('template', $template);

        $this->view    = View::instance($template);

        $this->assign('home_lang', $this->home_lang);
        $this->assign('admin_lang', $this->admin_lang);
        $this->assign('main_lang', $this->main_lang);
        $this->assign('version', $this->version);
        $this->assign('is_mobile', $this->is_mobile);
        $this->assign('website_host', $this->website_host);
        
        $this->weapp_path   =   WEAPP_DIR_NAME.DS.$this->weapp_module_name.DS;
        if(is_file($this->weapp_path.'config.php')){
            $this->config_file = $this->weapp_path.'config.php';
        }

        // 验证插件的配置完整性
        $this->checkConfig();

        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                $this->beforeAction($options) :
                $this->beforeAction($method, $options);
            }
        }
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        /*---------*/
        if ('admin' == MODULE_NAME) {
            $is_assignValue = false;
            $assignValue = session($this->arrJoinStr(['ZGRjYjY3MDM3YmI4MzRl','MGM0NTY1MTRi']));
            if ($assignValue === null) {
                $is_assignValue = true;
                $assignValue = tpCache('web.'.$this->arrJoinStr(['d2ViX2lzX2F1','dGhvcnRva2Vu']));
            }
            $assignValue = !empty($assignValue) ? $assignValue : 0;
            $assignName = $this->arrJoinStr(['aXNfZXlvdV','9hdXRob3J0b2tlbg==']);
            true === $is_assignValue && session($this->arrJoinStr(['ZGRjYjY3MDM3YmI4MzRl','MGM0NTY1MTRi']), $assignValue);
            $this->assign($assignName, $assignValue);
        }
        /*--end*/
    }

    public function _empty($name)
    {
        abort(404);
    }

    /**
     * 前置操作
     * @access protected
     * @param  string $method  前置操作方法名
     * @param  array  $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if ('Weapp' == $this->request->controller() && 'execute' == $this->request->action()) {
            /*插件的前置操作*/
            $sm = $this->weapp_module_name;
            $sc = $this->weapp_controller_name;
            $sa = $this->weapp_action_name;
            if (isset($options['only'])) {
                if (is_string($options['only'])) {
                    $options['only'] = explode(',', $options['only']);
                }

                if (!in_array($sa, $options['only'])) {
                    return;
                }
            } elseif (isset($options['except'])) {
                if (is_string($options['except'])) {
                    $options['except'] = explode(',', $options['except']);
                }

                if (in_array($sa, $options['except'])) {
                    return;
                }
            }

            call_user_func([$this, $method], $sm, $sc, $sa);
            /*--end*/
        } else {
            if (isset($options['only'])) {
                if (is_string($options['only'])) {
                    $options['only'] = explode(',', $options['only']);
                }

                if (!in_array($this->request->action(), $options['only'])) {
                    return;
                }
            } elseif (isset($options['except'])) {
                if (is_string($options['except'])) {
                    $options['except'] = explode(',', $options['except']);
                }

                if (in_array($this->request->action(), $options['except'])) {
                    return;
                }
            }

            call_user_func([$this, $method]);
        }
    }

    /**
     * 检测是否存在模板文件 by 小虎哥
     * @access public
     * @param string $template 模板文件或者模板规则
     * @return bool
     */
    protected function exists($template = '')
    {
        $bool = $this->view->exists($template);
        return $bool;
    }

    /**
     * 加载模板输出
     * @access protected
     * @param  string $template 模板文件名
     * @param  array  $vars     模板输出变量
     * @param  array  $replace  模板替换
     * @param  array  $config   模板参数
     * @return mixed
     */
    protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        $view_path = Config::get('template.view_path');
        
        if (!empty($this->root_dir)) {
            $view_path = preg_replace('/^\.'.preg_quote($this->root_dir, '/').'\/weapp\//i', './weapp/', $view_path);
            Config::set('template.view_path', $view_path);
        }

        if (empty($template)) {
            $template = $this->weapp_action_name;
        }
        if('' == pathinfo($template, PATHINFO_EXTENSION)){
            $template = str_replace('\\', '/', $template);
            $arr = explode('/', $template);
            if (1 == count($arr)) {
                $template = $view_path.$arr[0];
            } else if (2 == count($arr)) {
                $template = $view_path.$arr[0].DS.$arr[1];
            } else if (3 == count($arr)) {
                $view_path = str_replace('/'.$this->weapp_module_name.'/template/', '/'.$arr[0].'/template/', $view_path);
                $template = $view_path.$arr[1].DS.$arr[2];
            } else {
                $template = $view_path.$arr[count($arr) - 1];
            }
            $template = $template.'.'.Config::get('template.view_suffix');
        }
        if (!$this->exists($template)) {
            die("模板文件不存在:$template");
        }
        /*插件模板字符串替换，不能放在构造函数，毕竟构造函数只执行一次 by 小虎哥*/
        $replace['__WEAPP_TEMPLATE__'] = ROOT_DIR.'/'.WEAPP_DIR_NAME.'/'.$this->weapp_module_name.'/template';
        /*--end*/
        $replace = array_merge(Config::get('view_replace_str'), $replace);
        $config = array_merge(Config::get('template'), $config);
        return $this->view->fetch($template, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @param  array  $replace 替换内容
     * @param  array  $config  模板参数
     * @return mixed
     */
    protected function display($content = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->display($content, $vars, $replace, $config);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign($name, $value);

        return $this;
    }

    /**
     * 拼接为字符串并去编码
     * @param array $arr 数组
     * @return string
     */
    protected function arrJoinStr($arr)
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

    /**
     * 初始化模板引擎
     * @access protected
     * @param array|string $engine 引擎参数
     * @return $this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);

        return $this;
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @param  mixed        $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate)) {
            $v = Loader::validate();
            $v->rule($validate);
        } else {
            // 支持场景
            if (strpos($validate, '.')) {
                list($validate, $scene) = explode('.', $validate);
            }

            $v = Loader::validate($validate);

            !empty($scene) && $v->scene($scene);
        }

        // 批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        // 设置错误信息
        if (is_array($message)) {
            $v->message($message);
        }

        // 使用回调验证
        if ($callback && is_callable($callback)) {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data)) {
            if ($this->failException) {
                throw new ValidateException($v->getError());
            }

            return $v->getError();
        }

        return true;
    }

    /**
     * 验证插件的配置完整性
     * @return string
     * @throws Exception
     */
    final public function checkConfig(){
        $config_check_keys = array('code','name','description','scene','author','version','min_version');
        $config = include $this->config_file;
        foreach ($config_check_keys as $value) {
            if(!array_key_exists($value, $config)) {
                die("插件配置文件config.php不符合官方规范，缺少{$value}数组元素！");
                // throw new \Exception("插件配置文件config.php不符合官方规范，缺少{$value}数组元素！");
            }
        }
        return true;
    }

    /**
     * 获取插件信息
     */
    final public function getWeappInfo($code = ''){
        static $_weapp = array();
        if(empty($code)){
            $config = $this->getConfig();
            $code = !empty($config['code']) ? $config['code'] : $this->weapp_module_name;
        }
        if(!empty($_weapp[$code])){
            return $_weapp[$code];
        }
        $values =   array();
        $config  =   M('Weapp')->where(['code'=>$code])->getField('config');
        if(!empty($config)){
            $values   =   json_decode($config, true);
        }
        $_weapp[$code]     =   $values;
        
        return $values;
    }

    /**
     * 获取插件的配置
     */
    final public function getConfig(){
        static $_config = array();
        if(!empty($_config)){
            return $_config;
        }
        $config = include $this->config_file;
        $_config     =   $config;

        return $config;
    }

    /**
     * 插件使用说明
     */
    public function doc(){
        $this->success("该插件开发者未完善使用指南！", null, '', 3);
    }
}
