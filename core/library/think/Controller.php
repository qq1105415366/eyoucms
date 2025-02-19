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

use think\exception\ValidateException;
use traits\controller\Jump;

Loader::import('controller/Jump', TRAIT_PATH, EXT);

class Controller
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
     * 模板风格
     */
    public $tpl_theme = null;

    /**
     * 是否访问手机版
     */
    public $is_mobile = 0;

    /**
     * 前台当前站点
     */
    public $home_site = '';

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

        // 运营状态下，检验阅读权限
        $aid = input('param.aid/s', '');
        if (Config::get('app_debug') != true && !empty($aid) && $this->request->module() == 'home' && ($this->request->controller() == 'View' || $this->request->action() == 'view') ) {
            $map = [];
            if (!is_numeric($aid) || strval(intval($aid)) !== strval($aid)) {
                $map = array('a.htmlfilename' => $aid);
            } else {
                $map = array('a.aid' => intval($aid));
            }
            $map['a.is_del'] = 0; // 回收站功能
            $field = 'a.aid,a.arcrank,a.users_id, a.typeid, a.channel, c.typearcrank,c.dirname,c.page_limit';
            $archivesInfo = Db::name('archives')->field($field)
                ->alias('a')
                ->join('arctype c', 'a.typeid = c.id', 'LEFT')
                ->where($map)
                ->find();
            $users_id = (int)session('users_id');
            $archivesInfo['page_limit'] = empty($archivesInfo['page_limit']) ? [] : explode(',', $archivesInfo['page_limit']);
            // 若需要会员权限则执行
            if (($archivesInfo['arcrank'] > 0 || ( $archivesInfo['typearcrank'] > 0 && in_array(2,$archivesInfo['page_limit']) ) ) && empty($users_id)) {
                $url = url('user/Users/login');
                $arcurl = $this->request->domain().$this->root_dir."/index.php?m=home&c=Ajax&a=toView&aid={$aid}";
                if (stristr($url, '?')) {
                    $url = $url."&referurl=".urlencode($arcurl);
                } else {
                    $url = $url."?referurl=".urlencode($arcurl);
                }
                $this->redirect($url);
                exit;
            } else if ($archivesInfo['arcrank'] < 0) {
                $clear = input('param.clear/d');
                $admin_id = input('param.admin_id/d');
                if (empty($admin_id) && empty($clear)) {
                    abort(404,'页面不存在');
                }
            }
        }


        /*多语言*/
        $this->home_lang = get_home_lang();
        $this->admin_lang = get_admin_lang();
        $this->main_lang = get_main_lang();
        /*多城市*/
        if (config('city_switch_on')) {
            $this->home_site = get_home_site();
        }
        null === $this->version && $this->version = getCmsVersion();

        $returnData = $this->pc_to_mobile($this->request);
        $this->is_mobile = $returnData['is_mobile'];

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

        // 前台模板目录切换
        null === $this->tpl_theme && $this->tpl_theme = config('ey_config.web_tpl_theme');
        if (empty($this->tpl_theme)) {
            if (file_exists(ROOT_PATH.'template/default')) {
                $this->tpl_theme = 'default/';
            } else {
                $this->tpl_theme = '';
            }
        } else {
            if ('default' == $this->tpl_theme && !file_exists(ROOT_PATH.'template/default')) {
                $this->tpl_theme = '';
            } else if ('default' != $this->tpl_theme && !file_exists(ROOT_PATH.'template/'.$this->tpl_theme)) {
                if (in_array($this->request->module(), ['home','user'])) {
                    $this->error("模板目录【{$this->tpl_theme}】不存在！");
                }
            } else {
                $this->tpl_theme .= '/';
            }
        }
        !defined('TPL_THEME') && define('TPL_THEME', $this->tpl_theme); // 模板目录

        $param = input('param.');
        if (isset($param['uiset']) && !session('?admin_id')) {
            if (!file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/uiset.txt') && !file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/uiset.txt')) {
                abort(404,'页面不存在');
            }
        }
        
        !defined('MODULE_NAME') && define('MODULE_NAME',$this->request->module());  // 当前模块名称是
        !defined('CONTROLLER_NAME') && define('CONTROLLER_NAME',$this->request->controller()); // 当前控制器名称
        !defined('ACTION_NAME') && define('ACTION_NAME',$this->request->action()); // 当前操作名称是
        !defined('PREFIX') && define('PREFIX',Config::get('database.prefix')); // 数据库表前缀
        !defined('SYSTEM_ADMIN_LANG') && define('SYSTEM_ADMIN_LANG', Config::get('global.admin_lang')); // 后台语言变量
        !defined('SYSTEM_HOME_LANG') && define('SYSTEM_HOME_LANG', Config::get('global.home_lang')); // 前台语言变量

        if (stristr($this->request->url(), '?')) {
            $module = $this->request->param('m');
            if (!empty($module)) {
                $module_dir = glob(APP_PATH.'*', GLOB_ONLYDIR);
                foreach ($module_dir as $key => $val) {
                    $val = str_replace('\\', '/', $val);
                    $module_dir[$key] = preg_replace('/^(.*)\/([^\/]+)$/i', '${2}', $val);
                }
                if (!in_array($module, $module_dir)) {
                    abort(404,'页面不存在');
                }
            }
        }

        $is_notify = $this->request->param('is_notify');
        if (!empty($is_notify) && 1 === intval($is_notify)) {
            // 支付异步回调....
        } else {
            $web_anti_brushing = config('tpcache.web_anti_brushing');
            if (!empty($web_anti_brushing)) {
                if ('home' == MODULE_NAME && 'Index' == CONTROLLER_NAME && 'index' == ACTION_NAME) {
                    $params = $this->request->param();
                    if (empty($params['a']) || !in_array($params['a'], ['wechat_return','alipay_return','Express100','ey_agent'])) {
                        $varsarr=[];
                        if (file_exists(webXssKeyFile())) {
                            $content = @file_get_contents(webXssKeyFile());
                            $data = json_decode($content, true);
                        }
                        $web_anti_words = empty($data['web_anti_words']) ? [] : explode(PHP_EOL, $data['web_anti_words']);
                        if (!empty($web_anti_words)) {
                            foreach ($web_anti_words as $val) {
                                $varsarr[] = trim($val);
                            }
                        }
                        $whiteList = [];
                        if (1 == config('ey_config.seo_pseudo') || isset($params['uiset'])) {
                            $whiteList = array_merge($whiteList, ['m','c','a']);
                        }
                        if (1 == config('tpcache.php_agentcode')) {
                            $whiteList = array_merge($whiteList, ['aid']);
                        }
                        foreach ($varsarr as $key => $val) {
                            if (in_array($val, $whiteList)) {
                                unset($varsarr[$key]);
                            }
                        }
                        foreach ($params as $key => $val) {
                            if ('clear' == $key) {
                                if ($val != 1) {
                                    abort(404,'页面不存在');
                                }
                            } else if ('lang' == $key) {
                                if ((isset($params['uiset']) || is_language()) && 2 == strlen($val)) {

                                } else {
                                    abort(404,'页面不存在');
                                }
                            } else if (in_array($key, $varsarr)) {
                                abort(404,'页面不存在');
                            } else {
                                if (!empty($varsarr)) {
                                    foreach ($varsarr as $_k => $_v) {
                                        if (preg_match('/^(\/|\?|\/\?)?'.$_v.'(([^\w\-]+)(.*))?$/i', $key)) {
                                            abort(404,'页面不存在');
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    if ((stristr($this->request->url(true), '/home/View/index/') || stristr($this->request->url(true), '/home/Lists/index/')) && 'Buildhtml' != CONTROLLER_NAME) {
                        abort(404,'页面不存在');
                    }
                }
            }
        }

        // 自动判断手机端和PC，以及PC/手机自适应模板 by 小虎哥 2018-05-10
        $v = I('param.v/s', 'pc');
        $v = trim($v, '/');
        if ($v == 'mobile') {
            $this->is_mobile = 1;
        }

        if($this->is_mobile == 1 && file_exists(ROOT_PATH.'template/'.$this->tpl_theme.'mobile/index.htm')) {
            !defined('THEME_STYLE') && define('THEME_STYLE', 'mobile'); // 手机端标识
            !defined('THEME_STYLE_PATH') && define('THEME_STYLE_PATH', $this->tpl_theme.THEME_STYLE); // 手机端模板根目录
        } else {
            !defined('THEME_STYLE') && define('THEME_STYLE', 'pc'); // pc端标识
            !defined('THEME_STYLE_PATH') && define('THEME_STYLE_PATH', $this->tpl_theme.THEME_STYLE); // PC端模板根目录
        }

        if (in_array($this->request->module(), ['home','user'])) {
            Config::set('template.view_path', './template/'.THEME_STYLE_PATH.'/');
        } else if (in_array($this->request->module(), array('admin'))) {
            if ('weapp' == strtolower($this->request->controller()) && 'execute' == strtolower($this->request->action())) {
                Config::set('template.view_path', '.'.ROOT_DIR.'/'.WEAPP_DIR_NAME.'/'.$this->request->param('sm').'/template/');
            }
        }
        // -------end

        $this->view    = View::instance(Config::get('template'), Config::get('view_replace_str'));

        $this->assign('home_lang', $this->home_lang);
        $this->assign('admin_lang', $this->admin_lang);
        $this->assign('main_lang', $this->main_lang);
        $this->assign('version', $this->version);
        $this->assign('is_mobile', $this->is_mobile);
        $this->assign('tpl_theme', $this->tpl_theme);
        $this->assign('home_site', $this->home_site);
        $this->assign('website_host', $this->website_host);
        
        $param = $this->request->param();
        if ( (CONTROLLER_NAME == 'Lists' && isset($param['sort'])) || isset($param['clear']) || Config::get('app_debug') === true) {

        } else {
            $read_cache = true;

            // 支付异步回调不走页面缓存，避免导致异步回调失败
            $inputXml = file_get_contents("php://input");
            if (!empty($inputXml)) {
                // 解析参数
                $jsonXml = json_encode(simplexml_load_string($inputXml, 'SimpleXMLElement', LIBXML_NOCDATA));
                // 转换数组
                $jsonArr = json_decode($jsonXml, true);
                // 是否与支付成功
                if (!empty($jsonArr['attach'])) {
                    // 解析判断参数是否为微信支付
                    $attach = explode('|,|', $jsonArr['attach']);
                    if (!empty($attach[1]) && 'is_notify' == $attach[1]) {
                        $read_cache = false;
                    }
                }
            }

            if ($read_cache) {
                // 其他支付的异步回调不走页面缓存
                if (isset($param['is_notify'])) {
                    $read_cache = false;
                }
            }

            if ($read_cache) {
                read_html_cache(); // 尝试从缓存中读取
            }
        }

        // 控制器初始化
        $this->_initialize();

        if ('admin' == MODULE_NAME) {
            $assignName2 = $this->arrJoinStr(['cGhwX3Nlcn','ZpY2VtZWFs']);
            $assignValue2 = tpCache('php.'.$assignName2);
            $this->assign($assignName2, $assignValue2);
        }

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                $this->beforeAction($options) :
                $this->beforeAction($method, $options);
            }
        }
        // 逻辑化
        $this->coding();
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        static $request = null;
        if (null === $request) {
            $request = request();
        }
        $searchformhidden = '';
        /*纯动态URL模式下，必须要传参的分组、控制器、操作名*/
        if (1 == config('ey_config.seo_pseudo') && 1 == config('ey_config.seo_dynamic_format')) {
            $searchformhidden .= '<input type="hidden" name="m" value="'.MODULE_NAME.'">';
            $searchformhidden .= '<input type="hidden" name="c" value="'.CONTROLLER_NAME.'">';
            $searchformhidden .= '<input type="hidden" name="a" value="'.ACTION_NAME.'">';
            if ('Weapp' == $request->get('c') && 'execute' == $request->get('a')) { // 插件的搜索
                $searchformhidden .= '<input type="hidden" name="sm" value="'.$request->get('sm').'">';
                $searchformhidden .= '<input type="hidden" name="sc" value="'.$request->get('sc').'">';
                $searchformhidden .= '<input type="hidden" name="sa" value="'.$request->get('sa').'">';
                if ('Mbackend' == $request->get('sm')) {
                    $searchformhidden .= '<input type="hidden" name="isMobile" value="1">';
                }
            }
            /*多语言*/
            $lang = $request->param('lang/s');
            empty($lang) && $lang = get_main_lang();
            $searchformhidden .= '<input type="hidden" name="lang" value="'.$lang.'">';
            /*--end*/
        }
        /*--end*/
        $searchform['hidden'] = $searchformhidden;
        $this->assign('searchform', $searchform);

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

    /**
     * 手机端访问自动跳到手机独立域名
     * @access public
     */
    private function pc_to_mobile($request = null)
    {
        $data = [
            'is_mobile' => 0,
        ];

        if (is_null($request)) {
            $request = Request::instance();
        }

        $web_mobile_domain_open = config('tpcache.web_mobile_domain_open'); // 是否开启手机域名访问
        if (empty($web_mobile_domain_open) || in_array($request->module(), ['admin']) || $request->isAjax()) {
            $data['is_mobile'] = isMobile() ? 1 : 0;
            return $data;
        }

        $mobileurl = '';
        $subDomain = $request->subDomain();
        /*
         *  域名为4段的时候，会照成手机端前缀和跳转后的前缀不一致而无限跳转造成死循环，
         *  例如：域名www.eyou.com.cn  手机端为m
         *  $request->subDomain() 的值为 "m.eyou" != 'm' , 导致下面判断不断得跳转 header('Location: '.$mobileurl)
         *  死循环
         */
        $subDomain_arr = explode('.',$subDomain);
        $subDomain = !empty($subDomain_arr[0]) ? $subDomain_arr[0] : '';
        $web_mobile_domain = config('tpcache.web_mobile_domain');
        if (!isMobile()) { // 浏览器PC模式访问
            $goto = input('param.goto/s');
            $goto = trim($goto, '/');
            // 本地IP或者localhost访问处理
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $request->host(true)) || 'localhost' == $request->host(true)) {
                if (!empty($goto)) {
                    $data['is_mobile'] = 1;
                }
            } else {
                // 子域名和手机域名相同，或者URL参数goto值为m，就表示访问手机端模板
                if ('m' == $goto || (!empty($subDomain) && $subDomain == $web_mobile_domain)) {
                    $data['is_mobile'] = 1;
                }
            }
        } else { // 浏览器手机模式访问
            $data['is_mobile'] = 1;
            $responseType = config('ey_config.response_type'); // 0 = 响应式模板，1 = 分离式模板

            /*辨识IP访问，还是域名访问，如果是IP访问，将会与PC端的URL一致*/
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $request->host(true)) || 'localhost' == $request->host(true)) { // 响应式，且是IP访问，保持URL域名不变
                if (0 == $responseType) { // 响应式
                    return $data;
                } else { // PC与移动分离
                    $goto = input('param.goto/s');

                    /*处理首页链接最后带斜杆，进行301跳转*/
                    if ('m/' == $goto && preg_match("/\?goto=m\/$/i", $request->url())) {
                        $mobileurl = $request->domain().$request->url();
                        $mobileurl = trim($mobileurl, '/');
                        header('HTTP/1.1 301 Moved Permanently');
                        header('Location: '.$mobileurl);
                        exit;
                    }
                    /*end*/

                    $goto = trim($goto, '/');
                    if (!empty($goto)) {
                        return $data;
                    } else {
                        return $data;
                        
                        $mobileurl = '';
                        // PC首页跳转到手机端URL
                        if ($request->module() == 'home' && $request->controller() == 'Index' && $request->action() == 'index') {
                            $mobileurl = $request->domain().$this->root_dir;
                            /*去掉小尾巴*/
                            $seo_inlet = config('ey_config.seo_inlet');
                            if (1 == $seo_inlet && 2 != tpCache('seo.seo_pseudo')) {
                                $mobileurl .= "/";
                            } else {
                                $mobileurl .= "/index.php";
                            }
                            /*end*/
                            $mobileurl .= "?goto=m";
                            if ($this->main_lang != $this->home_lang) {
                                $mobileurl .= "&lang=".$this->home_lang;
                            }
                        } 
                        // PC动态URL跳转到手机端URL
                        else if (preg_match("#\?m=(\w+)&c=(\w+)&a=(\w+)#i", $request->url())) {
                            $data['is_mobile'] = 0;
                        }
                        // PC伪静态URL不做跳转，在手机端可以正常访问且显示PC模板
                        // 在没有配置手机域名情况下，手机端URL不可能存在伪静态URL，所以这个URL是来自PC端，就必须在手机端显示PC模板。
                        else if (!preg_match("#\?m=(\w+)&c=(\w+)&a=(\w+)#i", $request->url())) {
                            $data['is_mobile'] = 0;
                        }
                    }
                }
            } else { // 域名访问

                /*获取当前配置下，手机端带协议的域名URL，要么是与主域名一致，要么是独立二级域名*/
                $mobileDomainURL = $request->domain();
                if (!empty($web_mobile_domain)) {
                    $mobileDomainURL = preg_replace('/^(.*)(\/\/)([^\/]*)(\.?)('.$request->rootDomain().')(.*)$/i', '${1}${2}'.$web_mobile_domain.'.${5}${6}', $mobileDomainURL);
                }
                /*end*/

                if (0 == $responseType) { // 响应式模板
                    if (!empty($web_mobile_domain) && $subDomain != $web_mobile_domain) { // 配置手机域名
                        $mobileurl = $mobileDomainURL.$request->url();
                    } else {
                        return $data;
                    }
                }
                else { // 分离式模板
                    $goto = input('param.goto/s');

                    if ($subDomain != $web_mobile_domain || (empty($subDomain) && empty($goto))) {
                        if (!empty($web_mobile_domain)) { // 手机域名不为空
                            $mobileurl = $mobileDomainURL.$request->url();
                        } else {

                            /*处理首页链接最后带斜杆，进行301跳转*/
                            if ('m/' == $goto && preg_match("/\?goto=m\/$/i", $request->url())) {
                                $mobileurl = $request->domain().$request->url();
                                $mobileurl = trim($mobileurl, '/');
                                header('HTTP/1.1 301 Moved Permanently');
                                header('Location: '.$mobileurl);
                                exit;
                            }
                            /*end*/

                            $goto = trim($goto, '/');
                            if (!empty($goto)) {
                                return $data;
                            } else {
                                return $data;

                                // PC首页跳转到手机端URL
                                if ($request->module() == 'home' && $request->controller() == 'Index' && $request->action() == 'index') {
                                    $mobileurl = $request->domain().$this->root_dir;
                                    /*去掉小尾巴*/
                                    $seo_inlet = config('ey_config.seo_inlet');
                                    if (1 == $seo_inlet && 2 != tpCache('seo.seo_pseudo')) {
                                        $mobileurl .= "/";
                                    } else {
                                        $mobileurl .= "/index.php";
                                    }
                                    /*end*/
                                    $mobileurl .= "?goto=m";
                                    if ($this->main_lang != $this->home_lang) {
                                        $mobileurl .= "&lang=".$this->home_lang;
                                    }
                                }
                                // PC动态URL跳转到手机端URL
                                else if (preg_match("#\?m=(\w+)&c=(\w+)&a=(\w+)#i", $request->url())) {
                                    $data['is_mobile'] = 0;
                                }
                                // PC伪静态URL不做跳转，在手机端可以正常访问且显示PC模板
                                // 在没有配置手机域名情况下，手机端URL不可能存在伪静态URL，所以这个URL是来自PC端，就必须在手机端显示PC模板。
                                else if (!preg_match("#\?m=(\w+)&c=(\w+)&a=(\w+)#i", $request->url())) {
                                    $data['is_mobile'] = 0;
                                }
                            }
                        }
                    }
                }
            }
            /*end*/

            if (!empty($mobileurl)) {
                header('Location: '.$mobileurl);
                exit;
            }
        }

        return $data;
    }

    public function _empty($name)
    {
        abort(404);
    }

    /**
     * 加工处理
     * @access protected
     */
    protected function coding()
    {
        \think\Coding::checksd();
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
        if (in_array($this->request->module(), array('admin')) && 'Weapp' == $this->request->controller() && 'execute' == $this->request->action()) {
            /*插件的前置操作*/
            $sm = $this->request->param('sm');
            $sc = $this->request->param('sc');
            $sa = $this->request->param('sa');
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
        $html = $this->view->fetch($template, $vars, $replace, $config);
        /*尝试写入静态缓存*/
        $param = $this->request->param();
        if (!isset($param['clear']) || false === config('app_debug')) {
            write_html_cache($html);
        }
        /*--end*/

        return $html;
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
}
