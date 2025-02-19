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

namespace app\admin\logic;

use think\Model;
use think\Db;
use think\Page;
use think\Request;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
load_trait('controller/Jump');
class EyouCmsLogic extends Model
{
    use \traits\controller\Jump;

    public $request = null; // 当前Request对象实例
    public $main_lang = 'cn'; // 后台多语言标识
    public $admin_lang = 'cn'; // 后台多语言标识
    public $root_dir = ROOT_DIR;

    /**
     * 析构函数
     */
    function  __construct() {
        null === $this->request && $this->request = Request::instance();
        $this->main_lang = get_main_lang();
        $this->admin_lang = get_admin_lang();
    }
   
    public function welcome_default(&$assign_data = [], $globalConfig = [], $usersConfig = [])
    {
        // 同步导航与内容统计的状态
        $this->syn_open_quickmenu($globalConfig, $usersConfig);

        // 快捷导航
        $quickMenu = Db::name('quickentry')->where([
                'type'      => 1,
                'checked'   => 1,
                'status'    => 1,
            ])->order('sort_order asc, id asc')->select();
        $web_recycle_switch = tpCache('web.web_recycle_switch');
        foreach ($quickMenu as $key => $val) {
            if ($globalConfig['php_servicemeal'] <= 1 && $val['controller'] == 'Shop' && $val['action'] == 'index') {
                unset($quickMenu[$key]);
                continue;
            }
            if (!empty($web_recycle_switch) && $val['controller'] == 'RecycleBin' && $val['action'] == 'archives_index'){
                unset($quickMenu[$key]);
                continue;
            }
            if (is_language() && $this->main_lang != $this->admin_lang) {
                $controllerArr = ['Weapp','Filemanager','Sitemap','Admin','Member','Seo','Channeltype','Tools'];
                if (empty($globalConfig['language_split'])) {
                    $controllerArr[] = 'RecycleBin';
                }
                $ctlActArr = ['System@water','System@thumb','System@api_conf'];
                if (in_array($val['controller'], $controllerArr) || in_array($val['controller'].'@'.$val['action'], $ctlActArr)) {
                    unset($quickMenu[$key]);
                    continue;
                }
            }
            $quickMenu[$key]['vars'] = !empty($val['vars']) ? $val['vars']."&lang=".$this->admin_lang : "lang=".$this->admin_lang;
        }
        $assign_data['quickMenu'] = $quickMenu;

        // 内容统计
        $assign_data['contentTotal'] = $this->contentTotalList();
        // 服务器信息
        $assign_data['sys_info'] = $this->get_sys_info($globalConfig);
    }

    /**
     * 同步受开关控制的导航和内容统计
     */
    private function syn_open_quickmenu($globalConfig = [], $usersConfig = [])
    {
        /*商城中心 - 受本身开关和会员中心开关控制*/
        if (!empty($globalConfig['web_users_switch']) && !empty($usersConfig['shop_open'])) {
            $shop_open = 1;
        } else {
            $shop_open = 0;
        }
        /*end*/

        $saveData = [
            [
                'id'    => 31,
                'status'    => !empty($globalConfig['web_users_switch']) ? 1 : 0,
                'update_time'   => getTime(),
            ],
            [
                'id'    => 32,
                'status'    => (1 == $globalConfig['web_weapp_switch']) ? 1 : 0,
                'update_time'   => getTime(),
            ],
            [
                'id'    => 33,
                'status'    => !empty($globalConfig['web_users_switch']) ? 1 : 0,
                'update_time'   => getTime(),
            ],
            [
                'id'    => 34,
                'status'    => $shop_open,
                'update_time'   => getTime(),
            ],
            [
                'id'    => 35,
                'status'    => $shop_open,
                'update_time'   => getTime(),
            ],
        ];
        model('Quickentry')->saveAll($saveData);

        /*处理模型导航和统计*/
        $channeltypeRow = Db::name('channeltype')->cache(true,EYOUCMS_CACHE_TIME,"channeltype")->select();
        foreach ($channeltypeRow as $key => $val) {
            $updateData = [
                'groups'    => 1,
                'vars'  => 'channel='.$val['id'],
                'status'    => $val['status'],
                'update_time'   => getTime(),
            ];
            Db::name('quickentry')->where([
                    'vars' => 'channel='.$val['id']
                ])->update($updateData);
        }

        /*end*/
    }

    /**
     * 内容统计 - 数量处理
     */
    private function contentTotalList()
    {
        $shop_open = getUsersConfigData('shop.shop_open');
        $archivesTotalRow = null;
        $quickentryList = Db::name('quickentry')->where([
                'type'      => 2,
                'checked'   => 1,
                'status'    => 1,
            ])->order('sort_order asc, id asc')->select();
        foreach ($quickentryList as $key => $val) {
            $code = $val['controller'].'@'.$val['action'].'@'.$val['vars'];
            $quickentryList[$key]['vars'] = !empty($val['vars']) ? $val['vars']."&lang=".$this->admin_lang : "lang=".$this->admin_lang;
            if ($code == 'Form@index@') // 留言列表
            {
                $map = [
                    'lang'    => $this->admin_lang,
                ];
                $quickentryList[$key]['total'] = Db::name('guestbook')->where($map)->count();
            }
            else if (1 == $val['groups']) // 模型内容统计
            {
                if ('Product' == $val['controller']) {
                    if (1 == $shop_open) {
                        $val['controller'] = 'ShopProduct';
                    }
                }
                parse_str($val['vars'], $vars);
                $admin_info = session('admin_info');
                $auth_role_info = !empty($admin_info['auth_role_info']) ? $admin_info['auth_role_info'] : [];
                if (!empty($auth_role_info['permission']['arctype'])) {
                    /*权限控制 by 小虎哥*/
                    if (0 < intval($admin_info['role_id'])) {
                        $arctype_channels = Db::name('arctype')->field('current_channel')->where([
                                'id'    => ['IN', $auth_role_info['permission']['arctype']],
                            ])->group('current_channel')->column('current_channel');
                        if (!in_array($vars['channel'], $arctype_channels)) { // 移除该模型没有分配栏目权限的模块
                            if (isset($quickentryList[$key])) {
                                unset($quickentryList[$key]);
                                continue;
                            }
                        }
                    }
                    /*--end*/
                } else {
                    /*权限控制 by 小虎哥*/
                    if (0 < intval($admin_info['role_id'])) {
                        if (isset($quickentryList[$key])) {
                            unset($quickentryList[$key]);
                            continue;
                        }
                    }
                    /*--end*/
                }
                if (null === $archivesTotalRow) {
                    $map = [
                        'lang'    => $this->admin_lang,
                        'status'    => 1,
                        'is_del'    => 0,
                    ];
                    $mapNew = "(users_id = 0 OR (users_id > 0 AND arcrank >= 0))";

                    /*权限控制 by 小虎哥*/
                    if (0 < intval($admin_info['role_id'])) {
                        if(! empty($auth_role_info)){
                            if(isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']){
                                $map['admin_id'] = $admin_info['admin_id'];
                            }
                        }
                    }
                    /*--end*/
                    $SqlQuery = Db::name('archives')->field('channel, count(aid) as total')->where($map)->where($mapNew)->group('channel')->select(false);
                    $SqlResult = Db::name('sql_cache_table')->where(['sql_md5'=>md5($SqlQuery)])->getField('sql_result');
                    if (!empty($SqlResult)) {
                        $archivesTotalRow = json_decode($SqlResult, true);
                    } else {
                        $archivesTotalRow = Db::name('archives')->field('channel, count(aid) as total')->where($map)->where($mapNew)->group('channel')->getAllWithIndex('channel');
                        /*添加查询执行语句到mysql缓存表*/
                        $SqlCacheTable = [
                            'sql_name' => '|model|all|count|',
                            'sql_result' => json_encode($archivesTotalRow),
                            'sql_md5' => md5($SqlQuery),
                            'sql_query' => $SqlQuery,
                            'add_time' => getTime(),
                            'update_time' => getTime(),
                        ];
                        Db::name('sql_cache_table')->insertGetId($SqlCacheTable);
                        /*END*/
                    }
                }
                $val['total'] = !empty($archivesTotalRow[$vars['channel']]['total']) ? intval($archivesTotalRow[$vars['channel']]['total']) : 0;
                $quickentryList[$key] = $val;
            }
            else if ($code == 'AdPosition@index@') // 广告
            {
                $map = [
                    'lang'    => $this->admin_lang,
                    'is_del'    => 0,
                ];
                $quickentryList[$key]['total'] = Db::name('ad_position')->where($map)->count();
            }
            else if ($code == 'Links@index@') // 友情链接
            {
                $map = [
                    'lang'    => $this->admin_lang,
                ];
                $quickentryList[$key]['total'] = Db::name('links')->where($map)->count();
            }
            else if ($code == 'Tags@index@') // Tags标签
            {
                $map = [
                    'lang'    => $this->admin_lang,
                ];
                $quickentryList[$key]['total'] = Db::name('tagindex')->where($map)->count();
            }
            else if ($code == 'Member@users_index@') // 会员
            {
                $map = [
                    'lang'    => $this->admin_lang,
                    'is_del'    => 0,
                ];
                $quickentryList[$key]['total'] = Db::name('users')->where($map)->count();
            }
            else if ($code == 'Shop@index@') // 订单
            {
                $map = [
                    'lang'    => $this->admin_lang,
                ];
                $quickentryList[$key]['total'] = Db::name('shop_order')->where($map)->count();
            }
        }

        return $quickentryList;
    }

    /**
     * 服务器信息
     */
    private function get_sys_info($globalConfig = [])
    {
        $sys_info['os']             = PHP_OS;
        $sys_info['zlib']           = function_exists('gzclose') ? 'YES' : '<font color="red">NO（请开启 php.ini 中的php-zlib扩展）</font>';//zlib
        $sys_info['safe_mode']      = (boolean) ini_get('safe_mode') ? 'YES' : 'NO';//safe_mode = Off       
        $sys_info['timezone']       = function_exists("date_default_timezone_get") ? date_default_timezone_get() : "no_timezone";
        $sys_info['curl']           = function_exists('curl_init') ? 'YES' : '<font color="red">NO（请开启 php.ini 中的php-curl扩展）</font>';  
        $web_server                 = $_SERVER['SERVER_SOFTWARE'];
        if (stristr($web_server, 'apache')) {
            $web_server = 'apache';
        } else if (stristr($web_server, 'nginx')) {
            $web_server = 'nginx';
        } else if (stristr($web_server, 'iis')) {
            $web_server = 'iis';
        }
        $sys_info['web_server']     = $web_server;
        $sys_info['phpv']           = phpversion();
        $sys_info['ip']             = serverIP();
        $sys_info['postsize']       = @ini_get('file_uploads') ? ini_get('post_max_size') :'未知';
        $sys_info['fileupload']     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') :'未开启';
        $sys_info['max_ex_time']    = @ini_get("max_execution_time").'s'; //脚本最大执行时间
        $sys_info['set_time_limit'] = function_exists("set_time_limit") ? true : false;
        $sys_info['domain']         = request()->host();
        $sys_info['memory_limit']   = ini_get('memory_limit');
        $sys_info['version']        = file_get_contents(DATA_PATH.'conf/version.txt');
        if (config('database.type') == 'dm') { //达梦优化
            $mysqlinfo = Db::query('select * from "v$version"');
            $sys_info['mysql_version']  = $mysqlinfo[0]['BANNER'];
            $sys_info['dbtype'] ='Dm(达梦)';
        } else {
            $mysqlinfo = Db::query("SELECT VERSION() as version");
            $sys_info['mysql_version']  = $mysqlinfo[0]['version'];
            $sys_info['dbtype'] ='Mysql';
        }
        if(function_exists("gd_info")){
            $gd = gd_info();
            $sys_info['gdinfo']     = $gd['GD Version'];
        }else {
            $sys_info['gdinfo']     = "未知";
        }
        if (extension_loaded('zip')) {
            $sys_info['zip']     = "YES";
        } else {
            $sys_info['zip']     = '<font color="red">NO（请开启 php.ini 中的php-zip扩展）</font>';
        }
        $sys_info['curent_version'] = getCmsVersion(); //当前程序版本
        $sys_info['web_name'] = empty($globalConfig['web_name']) ? '' : $globalConfig['web_name'];

        return $sys_info;
    }

    /**
     * 内置商城欢迎页主题
     * @return [type] [description]
     */
    public function welcome_shop(&$assign_data = [], $globalConfig = [], $usersConfig = [])
    {
        // 快捷导航
        $quickMenu = Db::name('quickentry')->where([
                'type'      => 11,
                'checked'   => 1,
                'status'    => 1,
            ])->order('sort_order asc, id asc')->select();
        foreach ($quickMenu as $key => $val) {
            if (empty($val['litpic'])) {
                unset($quickMenu[$key]);
                continue;
            }
            $quickMenu[$key]['vars'] = !empty($val['vars']) ? $val['vars'] : '';
            $quickMenu[$key]['litpic'] = get_default_pic($val['litpic']);
        }
        $assign_data['quickMenu'] = $quickMenu;

        //插件快捷导航
        $weappMenuList = Db::name('weapp')->where([
            'status'    => 1,
            'checked'   => 1,
        ])->order('quick_sort asc, id asc')->select();
        foreach ($weappMenuList as $key => $val) {
            if (!empty($val['config'])){
                $val['config'] = json_decode($val['config'],true);
                if (0 == $val['is_system']){
                    $val['config']['litpic'] = get_default_pic($val['config']['litpic']);
                }
                if (empty($val['config']['management']['href'])) {
                    if (!empty($val['config']['management']['controller']) && !empty($val['config']['management']['action'])) {
                        $val['config']['management']['href'] = url($val['config']['management']['controller'].'/'.$val['config']['management']['action'], $val['config']['management']['param']);
                    } else {
                        $val['config']['management']['href'] = url('Weapp/execute',array('sm'=>$val['config']['code'],'sc'=>$val['config']['code'],'sa'=>'index'));
                    }
                }
                $weappMenuList[$key] = $val;
            }
        }
        $assign_data['weappMenuList'] = $weappMenuList;

        //实时概况
        $surveyQuickMenu = Db::name('quickentry')->where([
            'type'      => 21,
            'checked'   => 1,
            'status'    => 1,
        ])->order('sort_order asc, id asc')->select();
        $statistics_type = get_arr_column($surveyQuickMenu,'statistics_type');
        $now_date = date('Y-m-d');
        $now_time = strtotime($now_date);
        $yesterday = $now_time - 86400;
        //统计 今日/昨日
        $statistics_where = $today_statistics_data = $yesterday_statistics_data = [];
        $statistics_where['type'] = ['in',$statistics_type];
        $statistics_where['date'] = ['egt', $yesterday];
        $statistics_where['lang'] = $this->admin_lang;
        $statistics_data = Db::name('statistics_data')->where($statistics_where)->select();
        foreach ($statistics_data as $key => $val) {
            if ($val['date'] < $now_time) { // 昨天统计
                $yesterday_statistics_data[$val['type']] = $val;
            } else if ($val['date'] >= $now_time) { // 今天统计
                $today_statistics_data[$val['type']] = $val;
            }
        }

        $total_statistics_data = [];
        $archives_data = [];
        if (in_array(1,$statistics_type) || in_array(6,$statistics_type)) {
            $archives_data = Db::name('archives')->field('sum(click) as click, count(aid) as total')->where(['is_del'=>0,'lang'=>$this->admin_lang])->find();
        }
        if (in_array(1,$statistics_type)){
            $total_statistics_data[1] = empty($archives_data['click']) ? 0 : $archives_data['click'];
        }
        if (in_array(6,$statistics_type)){
            $total_statistics_data[6] = empty($archives_data['total']) ? 0 : $archives_data['total'];
        }

        $shop_order_data = [];
        if (in_array(2,$statistics_type) || in_array(3,$statistics_type)) {
            $shop_order_data = Db::name('shop_order')->field('sum(order_amount) as order_amount, count(order_id) as total')->where(['order_status' => ['IN', [1, 2, 3]],'lang'=>$this->admin_lang])->find();
        }
        if (in_array(2,$statistics_type)){
            $total_statistics_data[2] = empty($shop_order_data['total']) ? 0 : $shop_order_data['total'];
        }
        if (in_array(3,$statistics_type)){
            $total_statistics_data[3] = empty($shop_order_data['order_amount']) ? 0 : $shop_order_data['order_amount'];
        }

        if (in_array(4,$statistics_type)){
            $total_statistics_data[4] = Db::name('users')->where(['is_del'=>0,'lang'=>$this->admin_lang])->count('users_id');
        }
        if (in_array(5,$statistics_type)){
            $total_statistics_data[5] = Db::name('users_money')->where(['cause_type'=>1,'status' => ['IN', [2, 3]],'lang'=>$this->admin_lang])->sum('money');
        }
        foreach ($surveyQuickMenu as $k => $v) {
            $v['data'] = [
                'today' => empty($today_statistics_data[$v['statistics_type']]) ? [] : $today_statistics_data[$v['statistics_type']],
                'yesterday' => empty($yesterday_statistics_data[$v['statistics_type']]) ? [] : $yesterday_statistics_data[$v['statistics_type']],
                'total' => empty($total_statistics_data[$v['statistics_type']]) ? 0 : $total_statistics_data[$v['statistics_type']],
            ];
            $surveyQuickMenu[$k] = $v;
        }
        $assign_data['surveyQuickMenu'] = $surveyQuickMenu;
        $assign_data['current_time'] = date('Y-m-d H:i:s');

        $lineChartData = $this->GetLineChartData();
        $assign_data['DealNum'] = $lineChartData['num'];
        $assign_data['DealAmount'] = $lineChartData['amount'];

        //待办事项统计
        $toDoList = [];
        $toDoList['undelivery'] = Db::name('shop_order')->where(['order_status'=>1,'lang'=>$this->admin_lang])->count('order_id');
        $toDoList['service'] = Db::name('shop_order_service')->where(['status'=>1,'lang'=>$this->admin_lang])->count('service_id');
        $where = [
            'a.is_del' => 0,
            'a.channel' => 2,
            'a.stock_count|b.spec_stock' => ['elt', $usersConfig['goods_stock_warning']],
            'a.lang' => $this->admin_lang,
        ];
        $toDoList['warning'] =  Db::name('archives')->alias('a')->join('product_spec_value b', 'a.aid = b.aid', 'LEFT')->where($where)->group('a.aid')->count('a.aid');
        $assign_data['toDoList'] = $toDoList;

        //pc端前台入口链接
        $assign_data['home_url'] = $this->shouye($globalConfig);

        //小程序码
        $weixin_data = tpSetting("OpenMinicode.conf_weixin");
        $weixin_data = json_decode($weixin_data, true);
        $weixin_qrcodeurl = "";
        if (!empty($weixin_data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20230505/weixin-".md5($weixin_data['appid']).".png";
            tp_mkdir(dirname($filepath));
            if (file_exists($filepath)) {
                $weixin_qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $assign_data['weixin_data'] = $weixin_data;
        $assign_data['weixin_qrcodeurl'] = $weixin_qrcodeurl;

        // h5二维码
        vendor('wechatpay.phpqrcode.phpqrcode');
        $pngurl = $this->request->domain().ROOT_DIR;
        $h5_qrcodeurl = "";
        $filepath = UPLOAD_PATH."allimg/20230505/h5-".md5($pngurl).".png";
        tp_mkdir(dirname($filepath));
        if (file_exists($filepath)) {
            $h5_qrcodeurl = "{$this->root_dir}/".$filepath;
        } else {
            $qrcode = new \QRcode;
            $qrcode->png($pngurl, $filepath, 0, 3, 1);
            $h5_qrcodeurl = "{$this->root_dir}/".$filepath;
        }
        $assign_data['h5_qrcodeurl'] = $h5_qrcodeurl;
    }

    // 近七日成交量成交额折线图数据
    private function GetLineChartData()
    {
        $now_day = strtotime(date("Y-m-d"));
        $min_time = $now_day - (6*86400);
        $dataNum = $dataAmount = [];
        $statistics_data = Db::name('statistics_data')->field('type,date,num,total')->where([
                'type'=>['IN', [2,3]],
                'date'=>['egt', $min_time],
                'lang'=>$this->admin_lang,
            ])->order('type asc, date desc')->select();
        $arr = [];
        foreach ($statistics_data as $key => $val) {
            $arr[$val['type']][$val['date']] = $val;
        }
        $statistics_data = $arr;
        for ($i = 0;$i<7;$i++){
            $time = $now_day - $i*86400;
            //成交量
            $dataNum[$i] = empty($statistics_data[2][$time]) ? 0 : $statistics_data[2][$time]['num'];
            //成交额
            $dataAmount[$i] = empty($statistics_data[3][$time]) ? 0 : $statistics_data[3][$time]['total'];
        }
        $dataNum = array_reverse($dataNum);
        $dataAmount = array_reverse($dataAmount);

        return ['num'=>$dataNum,'amount'=>$dataAmount];
    }

    /**
     * 301跳转首页
     */
    public function shouye($globalConfig = [])
    {
        $inletStr = '/index.php';
        $seo_inlet = config('ey_config.seo_inlet');
        1 == intval($seo_inlet) && $inletStr = '';

        $web_basehost = preg_replace('/^(([^\:\.]+):)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', config('tpcache.web_basehost'));
        if (!empty($web_basehost)) {
            $host_port = !stristr($website_host, ':') ? '' : $this->request->port();
            $website_host = $web_basehost;
            if (!empty($host_port) && !stristr($website_host, ':')) {
                $website_host .= ":{$host_port}";
            }
            $website_host = $this->request->scheme() . '://' . $website_host;
        } else {
            $website_host = $this->request->domain();
        }
        $home_url = $website_host.ROOT_DIR.'/index.php';  // 支持子目录
        $home_default_lang = config('ey_config.system_home_default_lang');
        //默认前台语言，链接中不需要带语言参数；非默认前台语言，链接中需要带语言参数
        if ($home_default_lang != $this->admin_lang) {
            $home_url = Db::name('language')->where(['mark'=>$this->admin_lang])->getField('url');
            if (empty($home_url)) {
                $seo_pseudo = !empty($globalConfig['seo_pseudo']) ? $globalConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
                if (1 == $seo_pseudo) {
                    $home_url = $website_host.ROOT_DIR.$inletStr; // 支持子目录
                    if (!empty($inletStr)) {
                        $home_url .= '?';
                    } else {
                        $home_url .= '/?';
                    }
                    $home_url .= http_build_query(['lang'=>$this->admin_lang]);
                } else {
                    $home_url = $website_host.ROOT_DIR.$inletStr.'/'.$this->admin_lang; // 支持子目录
                }
            }
        }
        if (stristr($home_url, '?')) {
            $home_url .= '&clear=1';
        } else {
            $home_url .= '?clear=1';
        }
        
        return $home_url;
    }

    /**
     * 任务流版欢迎页主题
     * @param  array  &$assign_data [description]
     * @param  array  $globalConfig [description]
     * @param  array  $usersConfig  [description]
     * @return [type]               [description]
     */
    public function welcome_taskflow(&$assign_data = [], $globalConfig = [], $usersConfig = [])
    {
        // 服务器信息
        $assign_data['sys_info'] = $this->get_sys_info($globalConfig);

        $weappList = model('weapp')->getWeappList();
        $assign_data['weappList'] = $weappList;
        // 安装【工作任务流】插件才可用
        if (!empty($weappList['TaskFlow']) && 1 == $weappList['TaskFlow']['status']) {
            $Prefix = config('database.prefix');
            $taskflowTableInfo = Db::query("SHOW COLUMNS FROM {$Prefix}weapp_task_flow");
            $taskflowTableInfo = get_arr_column($taskflowTableInfo, 'Field');
            if (!empty($taskflowTableInfo) && !in_array('helper_id', $taskflowTableInfo)){
                $sql = "ALTER TABLE `{$Prefix}weapp_task_flow` ADD COLUMN `helper_id`  int(11) NOT NULL DEFAULT 0 COMMENT '协助问题的负责人ID' AFTER `auditors_id`;";
                $r = @Db::execute($sql);
                if ($r !== false) {
                    schemaTable('weapp_task_flow');
                }
            }

            // 当前管理员
            $admin_info = getAdminInfo(session('admin_id'));
            $assign_data['admin_info'] = $admin_info;
            // 指定查看的管理员id
            $admin_id = input('admin_id/d', $admin_info['admin_id']);
            $assign_data['admin_id'] = $admin_id;
            // 小组成员列表
            $admin_list = Db::name('admin')
                ->alias('a')
                ->field('a.*, b.name as role_name')
                ->join('auth_role b','a.role_id = b.id','LEFT')
                ->where([
                    'a.status'  => 1,
                ])
                ->select();
            $NewAdmin = [];
            foreach ($admin_list as $k =>$v) {
                if (0 >= intval($v['role_id'])) {
                    $v['role_name'] = !empty($v['parent_id']) ? '超级管理员' : '创始人';
                }
                $v['pen_name'] = !empty($v['true_name']) ? $v['true_name'] : $v['pen_name'];
                $v['pen_name'] = !empty($v['pen_name']) ? $v['pen_name'] : $v['user_name'];
                $admin_list[$k] = $v;

                $NewAdmin[$k]['name'] = $v['pen_name'];
                $NewAdmin[$k]['value'] = $v['admin_id'];
            }
            $assign_data['admin_list'] = $admin_list;
            $assign_data['NewAdmin'] = $NewAdmin;

            $taskFlowData = $this->taskFlowData();
            $assign_data['taskFlowData'] = $taskFlowData;

            //任务流统计
            $row = Db::name('weapp_task_flow')->field('initiator_id,handler_id,auditors_id,helper_id,task_status,is_draft')->where([
                    'initiator_id|handler_id|auditors_id|helper_id' => $admin_id,
                ])->order('task_status asc, task_id desc')->select();
            $countRow = ['all'=>0, 'my'=>0, 'test'=>0, 'done'=>0, 'draft'=>0];
            foreach ($row as $key => $val) {
                if (1 == $val['is_draft']) {
                    if (in_array($admin_id, [$val['initiator_id']])) {
                        $countRow['draft']++;
                    }
                } else {
                    if ($admin_id == $val['initiator_id']) { //发起任务
                        $countRow['my']++;
                    }
                    // if (4 == $val['task_status'] && in_array($admin_id, [$val['initiator_id'], $val['handler_id'], $val['auditors_id']])) { //待测试
                    //     $countRow['test']++;
                    // }
                    if (5 == $val['task_status']) { //已完成
                        if (in_array($admin_id, [$val['handler_id'], $val['auditors_id']])) {
                            $countRow['done']++;
                        }
                    } else { //我的任务
                        if ( in_array($admin_id, [$val['handler_id']]) || (4 == $val['task_status'] && in_array($admin_id, [$val['auditors_id']])) || (2 == $val['task_status'] && in_array($admin_id, [$val['helper_id']])) ) {
                            $countRow['all']++; //我的任务
                        }
                    }
                }
            }
            $assign_data['countRow'] = $countRow;

            //任务流 默认是当前管理员的处理人任务
            $task_where = [
                'a.is_draft'    => 0,
                'a.task_status' => ['NOTIN', [5]],
            ];
            $task_where[] = Db::raw(" (a.handler_id = '{$admin_id}' OR (a.auditors_id = '{$admin_id}' AND a.task_status = 4) OR (a.helper_id = '{$admin_id}' AND a.task_status = 2)) ");
            $task_list = Db::name('weapp_task_flow')
                ->field('a.*')
                ->alias('a')
                ->where($task_where)
                ->orderRaw("FIND_IN_SET(a.task_status, '2,6,1,3,4,5') ASC, a.helper_id desc, a.task_id desc")
                ->limit(8)->select();
            $task_admin_ids = [];
            foreach ($task_list as $key => $val) {
                array_push($task_admin_ids, $val['initiator_id']);
                array_push($task_admin_ids, $val['handler_id']);
                array_push($task_admin_ids, $val['auditors_id']);
                array_push($task_admin_ids, $val['helper_id']);
            }
            $week_time = mktime(0, 0 , 0,date("m"),date("d")-date("w")+1,date("Y"));
            $task_admin_list = Db::name('admin')->field('admin_id,user_name,pen_name,true_name,head_pic')->where(['admin_id'=>['IN',$task_admin_ids]])->getAllWithIndex('admin_id');
            foreach ($task_list as $key => $val) {
                if (!empty($val['project_id']) && !empty($taskFlowData['project_list'][$val['project_id']]['name'])) {
                    $val['task_title'] = '【'.$taskFlowData['project_list'][$val['project_id']]['name'].'】'.$val['task_title'];
                }else {
                    $val['task_title'] = '【其他】' . $val['task_title'];
                }
                $val['status_name'] = $this->taskFlowStatus($val['task_status'], 'text', $val['helper_id']);
                $val['status_bg'] = $this->taskFlowStatus($val['task_status'], 'bg', $val['helper_id']);
                $val['level_name'] = $this->taskFlowLevel($val['task_level']);
                $val['level_bg'] = $this->taskFlowLevel($val['task_level'], 'bg');

                if ($val['task_status'] == 3) {
                    $new_time = $val['update_time'];
                } else if ($val['task_status'] == 4) {
                    $new_time = $val['update_time'];
                } else if ($val['task_status'] == 5) {
                    $new_time = $val['update_time'];
                } else {
                    $new_time = $val['add_time'];
                }

                if ($new_time > $week_time) {
                    $new_time = '本周';
                } else {
                    $new_time = MyDate('Y-m-d', $new_time);
                }
                $val['new_time'] = $new_time;

                if (!empty($val['initiator_id'])) {
                    $val['initiator_info'] = !empty($task_admin_list[$val['initiator_id']]) ? $task_admin_list[$val['initiator_id']] : $task_admin_list[1];
                }
                if (!empty($val['handler_id'])) {
                    $val['handler_info'] = !empty($task_admin_list[$val['handler_id']]) ? $task_admin_list[$val['handler_id']] : $task_admin_list[1];
                }
                if (!empty($val['auditors_id'])) {
                    $val['auditors_info'] = !empty($task_admin_list[$val['auditors_id']]) ? $task_admin_list[$val['auditors_id']] : $task_admin_list[1];
                }
                if (!empty($val['helper_id'])) {
                    $val['helper_info'] = !empty($task_admin_list[$val['helper_id']]) ? $task_admin_list[$val['helper_id']] : $task_admin_list[1];
                    if (2 == $val['task_status']) {
                        $val['handler_info'] = $val['helper_info'];
                    }
                }
                $task_list[$key] = $val;
            }
            $assign_data['task_list'] = $task_list;
        }

        /*----------------业务统计 start-------------*/
        $surveyQuickMenu = Db::name('quickentry')->where([
            'type'      => 31,
            'checked'   => 1,
            'status'    => 1,
        ])->order('sort_order asc, id asc')->select();
        $statistics_type = get_arr_column($surveyQuickMenu,'statistics_type');
        $now_date = date('Y-m-d');
        $now_time = strtotime($now_date);
        $yesterday = $now_time - 86400;
        //统计 今日/昨日
        $statistics_where = $today_statistics_data = $yesterday_statistics_data = [];
        $statistics_where['type'] = ['in',$statistics_type];
        $statistics_where['date'] = ['egt', $yesterday];
        $statistics_where['lang'] = $this->admin_lang;
        $statistics_data = Db::name('statistics_data')->where($statistics_where)->select();
        foreach ($statistics_data as $key => $val) {
            if ($val['date'] < $now_time) { // 昨天统计
                $yesterday_statistics_data[$val['type']] = $val;
            } else if ($val['date'] >= $now_time) { // 今天统计
                $today_statistics_data[$val['type']] = $val;
            }
        }

        $total_statistics_data = [];
        $archives_data = [];
        if (in_array(1,$statistics_type) || in_array(6,$statistics_type)) {
            $archives_data = Db::name('archives')->field('sum(click) as click, count(aid) as total')->where(['is_del'=>0,'lang'=>$this->admin_lang])->find();
        }
        if (in_array(1,$statistics_type)){
            $total_statistics_data[1] = empty($archives_data['click']) ? 0 : $archives_data['click'];
        }
        if (in_array(6,$statistics_type)){
            $total_statistics_data[6] = empty($archives_data['total']) ? 0 : $archives_data['total'];
        }

        $shop_order_data = [];
        if (in_array(2,$statistics_type) || in_array(3,$statistics_type)) {
            $shop_order_data = Db::name('shop_order')->field('sum(order_amount) as order_amount, count(order_id) as total')->where(['order_status' => ['IN', [1, 2, 3]],'lang'=>$this->admin_lang])->find();
        }
        if (in_array(2,$statistics_type)){
            $total_statistics_data[2] = empty($shop_order_data['total']) ? 0 : $shop_order_data['total'];
        }
        if (in_array(3,$statistics_type)){
            $total_statistics_data[3] = empty($shop_order_data['order_amount']) ? 0 : $shop_order_data['order_amount'];
        }

        if (in_array(4,$statistics_type)){
            $total_statistics_data[4] = Db::name('users')->where(['is_del'=>0,'lang'=>$this->admin_lang])->count('users_id');
        }
        if (in_array(5,$statistics_type)){
            $total_statistics_data[5] = Db::name('users_money')->where(['cause_type'=>1,'status' => ['IN', [2, 3]],'lang'=>$this->admin_lang])->sum('money');
        }
        if (in_array(7,$statistics_type)){ // 发布文章总数
            $total_statistics_data[7] = Db::name('archives')->where(['arcrank'=>['egt', 0],'status' => 1,'is_del' => 0,'lang'=>$this->admin_lang])->count();
        }
        if (in_array(8,$statistics_type)){ // tag总数 / 今日数
            $total_statistics_data[8] = Db::name('tagindex')->where(['id'=>['gt',0],'lang'=>$this->admin_lang])->count();
            $today_statistics_data[8]['total'] = Db::name('tagindex')->whereTime('add_time', 'today')->where(['lang'=>$this->admin_lang])->count();
        }
        if (in_array(9,$statistics_type)){ // 待审文档总数
            $total_statistics_data[9] = Db::name('archives')->where(['arcrank'=>['eq', -1],'status' => 1,'is_del' => 0,'lang'=>$this->admin_lang])->count();
            $today_statistics_data[9]['total'] = Db::name('archives')->whereTime('add_time', 'today')->where(['arcrank'=>['eq', -1],'status' => 1,'is_del' => 0,'lang'=>$this->admin_lang])->count();
        }
        foreach ($surveyQuickMenu as $k => $v) {
            $v['data'] = [
                'today' => empty($today_statistics_data[$v['statistics_type']]) ? [] : $today_statistics_data[$v['statistics_type']],
                'yesterday' => empty($yesterday_statistics_data[$v['statistics_type']]) ? [] : $yesterday_statistics_data[$v['statistics_type']],
                'total' => empty($total_statistics_data[$v['statistics_type']]) ? [] : $total_statistics_data[$v['statistics_type']],
            ];
            $v['url'] = empty($v['url']) ? url($v['controller'].'/'.$v['action'], [$v['vars']]) : $v['url'];
            $surveyQuickMenu[$k] = $v;
        }
        $assign_data['surveyQuickMenu'] = $surveyQuickMenu;
        $assign_data['current_time'] = date('Y-m-d H:i:s');
        /*----------------业务统计 end-------------*/
    }

    //ajax获取任务流数据
    public function get_task_list()
    {
        if (IS_AJAX_POST){
            $admin_id = input('post.admin_id/d', session('admin_id'));
            $task_status = input('post.task_status/s');

            //任务流统计
            $row = Db::name('weapp_task_flow')->field('initiator_id,handler_id,auditors_id,helper_id,task_status,is_draft')->where([
                    'initiator_id|handler_id|auditors_id|helper_id' => $admin_id,
                ])->order('task_status asc, task_id desc')->select();
            $countRow = ['all'=>0, 'my'=>0, 'test'=>0, 'done'=>0, 'draft'=>0];
            foreach ($row as $key => $val) {
                if (1 == $val['is_draft']) {
                    if (in_array($admin_id, [$val['initiator_id']])) {
                        $countRow['draft']++;
                    }
                } else {
                    if ($admin_id == $val['initiator_id']) { //发起任务
                        $countRow['my']++;
                    }
                    // if (4 == $val['task_status'] && in_array($admin_id, [$val['initiator_id'], $val['handler_id'], $val['auditors_id']])) { //待测试
                    //     $countRow['test']++;
                    // }
                    if (5 == $val['task_status']) { //已完成
                        if (in_array($admin_id, [$val['handler_id'], $val['auditors_id']])) {
                            $countRow['done']++;
                        }
                    } else { //我的任务
                        if ( in_array($admin_id, [$val['handler_id']]) || (4 == $val['task_status'] && in_array($admin_id, [$val['auditors_id']])) || (2 == $val['task_status'] && in_array($admin_id, [$val['helper_id']])) ) {
                            $countRow['all']++; //我的任务
                        }
                    }
                }
            }

            //任务流列表
            $task_where = [];
            $task_where['a.is_draft'] = 0;
            if ('my' == $task_status) {
                $task_where['a.initiator_id'] = $admin_id;
                $orderby = "FIND_IN_SET(a.task_status, '2,6,1,3,4,5') ASC, a.helper_id desc, a.task_id desc";
            }elseif ('test' == $task_status) {
                $task_where['a.auditors_id'] = $admin_id;
                $task_where['a.task_status'] = 4;
                $orderby = "a.task_id desc";
            }elseif ('done' == $task_status) {
                $task_where['a.handler_id|a.auditors_id'] = $admin_id;
                $task_where['a.task_status'] = 5;
                $orderby = "a.update_time desc";
            }elseif ('draft' == $task_status) {
                $task_where['a.initiator_id'] = $admin_id;
                $task_where['a.is_draft'] = 1;
                $orderby = "a.task_id desc";
            }else{
                $task_where['a.handler_id|a.auditors_id|a.helper_id'] = $admin_id;
                $task_where['a.task_status'] = ['NOTIN', [5]];
                $task_where[] = Db::raw(" (a.handler_id = '{$admin_id}' OR (a.auditors_id = '{$admin_id}' AND a.task_status = 4) OR (a.helper_id = '{$admin_id}' AND a.task_status = 2)) ");
                $orderby = "FIND_IN_SET(a.task_status, '2,6,1,3,4,5') ASC, a.helper_id desc, a.task_id desc";
            }

            $count = Db::name('weapp_task_flow')->alias('a')->where($task_where)->count('a.task_id');
            $pager = new Page($count, 8);// 实例化分页类 传入总记录数和每页显示的记录数
            $task_list = Db::name('weapp_task_flow')
                ->field('a.*')
                ->alias('a')
                ->where($task_where)
                ->orderRaw($orderby)
                ->limit($pager->firstRow.','.$pager->listRows)
                ->select();
            $task_admin_ids = [];
            foreach ($task_list as $key => $val) {
                array_push($task_admin_ids, $val['initiator_id']);
                array_push($task_admin_ids, $val['handler_id']);
                array_push($task_admin_ids, $val['auditors_id']);
                array_push($task_admin_ids, $val['helper_id']);
            }
            $task_admin_list = Db::name('admin')->field('admin_id,user_name,pen_name,true_name,head_pic')->where(['admin_id'=>['IN',$task_admin_ids]])->getAllWithIndex('admin_id');
            $taskFlowData = $this->taskFlowData();
            foreach ($task_list as $key => $val) {
                if (!empty($val['project_id']) && !empty($taskFlowData['project_list'][$val['project_id']]['name'])) {
                    $val['task_title'] = '【'.$taskFlowData['project_list'][$val['project_id']]['name'].'】'.$val['task_title'];
                }else {
                    $val['task_title'] = '【其他】' . $val['task_title'];
                }
                $val['status_name'] = $this->taskFlowStatus($val['task_status'], 'text', $val['helper_id']);
                $val['status_bg'] = $this->taskFlowStatus($val['task_status'], 'bg', $val['helper_id']);
                $val['level_name'] = $this->taskFlowLevel($val['task_level']);
                $val['level_bg'] = $this->taskFlowLevel($val['task_level'], 'bg');

                if (!empty($val['initiator_id'])) {
                    $val['initiator_info'] = !empty($task_admin_list[$val['initiator_id']]) ? $task_admin_list[$val['initiator_id']] : $task_admin_list[1];
                    $val['initiator_info']['head_pic'] = get_head_pic($val['initiator_info']['head_pic'], true);
                    $val['initiator_info']['pen_name'] = !empty($val['initiator_info']['true_name']) ? $val['initiator_info']['true_name'] : $val['initiator_info']['pen_name'];
                    empty($val['initiator_info']['pen_name']) && $val['initiator_info']['pen_name'] = $val['initiator_info']['user_name'];
                }
                if (!empty($val['handler_id'])) {
                    $val['handler_info'] = !empty($task_admin_list[$val['handler_id']]) ? $task_admin_list[$val['handler_id']] : $task_admin_list[1];
                    $val['handler_info']['head_pic'] = get_head_pic($val['handler_info']['head_pic'], true);
                    $val['handler_info']['pen_name'] = !empty($val['handler_info']['true_name']) ? $val['handler_info']['true_name'] : $val['handler_info']['pen_name'];
                    empty($val['handler_info']['pen_name']) && $val['handler_info']['pen_name'] = $val['handler_info']['user_name'];
                }
                if (!empty($val['auditors_id'])) {
                    $val['auditors_info'] = !empty($task_admin_list[$val['auditors_id']]) ? $task_admin_list[$val['auditors_id']] : $task_admin_list[1];
                    $val['auditors_info']['head_pic'] = get_head_pic($val['auditors_info']['head_pic'], true);
                    $val['auditors_info']['pen_name'] = !empty($val['auditors_info']['true_name']) ? $val['auditors_info']['true_name'] : $val['auditors_info']['pen_name'];
                    empty($val['auditors_info']['pen_name']) && $val['auditors_info']['pen_name'] = $val['auditors_info']['user_name'];
                }
                if (!empty($val['helper_id'])) {
                    $val['helper_info'] = !empty($task_admin_list[$val['helper_id']]) ? $task_admin_list[$val['helper_id']] : $task_admin_list[1];
                    $val['helper_info']['head_pic'] = get_head_pic($val['helper_info']['head_pic'], true);
                    $val['helper_info']['pen_name'] = !empty($val['helper_info']['true_name']) ? $val['helper_info']['true_name'] : $val['helper_info']['pen_name'];
                    empty($val['helper_info']['pen_name']) && $val['helper_info']['pen_name'] = $val['helper_info']['user_name'];
                    if (2 == $val['task_status']) {
                        $val['handler_info'] = $val['helper_info'];
                    }
                }
                $task_list[$key] = $val;
            }

            $html = '';
            $week_time = mktime(0, 0 , 0,date("m"),date("d")-date("w")+1,date("Y"));
            foreach ($task_list as $key => $val) {
                if ($val['task_status'] == 3) {
                    $new_time = $val['update_time'];
                } else if ($val['task_status'] == 4) {
                    $new_time = $val['update_time'];
                } else if ($val['task_status'] == 5) {
                    $new_time = $val['update_time'];
                } else {
                    $new_time = $val['add_time'];
                }

                if ($new_time > $week_time) {
                    $new_time = '本周';
                } else {
                    $new_time = MyDate('Y-m-d', $new_time);
                }
                $html .=<<<EOF
<div class="flex-dir-row flex-a-center">
    <div style="width: 100px; padding-left: 10px;">
        <div class="table-center-one task_status_{$val['task_id']}">
            <div class="{$val['status_bg']}-bg">{$val['status_name']}</div>
        </div>
    </div>
    <div class="table-center-ellipsis" style="width: 59%;white-space: nowrap;text-overflow: ellipsis;overflow: hidden;">
        <div class="table-center-two">
            <span class="task_title curpoin task_title_{$val['task_id']}" onclick="GetTaskDetails({$val['task_id']});">{$val['task_title']}</span>
            <span class="task_level_{$val['task_id']}">
                <label class="{$val['level_bg']}-bt">{$val['level_name']}</label>
            </span>
        </div>
    </div>
    <div style="width: 10%; margin-left: 38.5px;">
        <div class="flex-dir-row flex-a-center" style="margin-top: -5px;">
            <img class="table-center-image" src="{$val['initiator_info']['head_pic']}">{$val['initiator_info']['pen_name']}
        </div>
    </div>
    <div style="width: 10%;">
        <div class="flex-dir-row flex-a-center" style="margin-top: -5px;">
            <img class="table-center-image" src="{$val['handler_info']['head_pic']}">{$val['handler_info']['pen_name']}
        </div>
    </div>
    <div style="width: 10%;">
        <div class="flex-dir-row flex-a-center" style="margin-top: -5px;">
            <img class="table-center-image" src="{$val['auditors_info']['head_pic']}">{$val['auditors_info']['pen_name']}
        </div>
    </div>
    <div style="width: 6%; margin-right: 30.2px">
        <div class="flex-dir-row flex-a-center" style="margin-top: -5px;">
            {$new_time}
        </div>
    </div>
</div>
EOF;
            }

            $hasMorePage = $pager->nowPage >= $pager->totalPages ? 0 : 1; // 是否有下一页
            $nextpage = $pager->nowPage + 1;
            if ($nextpage > $pager->totalPages) {
                $nextpage = $pager->totalPages;
            }
            $this->success('success', null, ['countRow'=>$countRow,'html'=>$html,'nextpage'=>$nextpage,'hasMorePage'=>$hasMorePage]);
        }
        $this->error('请求错误!');
    }

    // 任务流配置
    private function taskFlowData(){
        $data = Db::name('weapp')->where('code', 'TaskFlow')->getField('data');
        $data = json_decode($data, true);
        return $data;
    }

    //任务流任务状态
    private function taskFlowStatus($value = 1, $type = 'text', $helper_id = 0){
        if ('text' == $type) {
            $arr = [
                '1' => '待处理',
                '2' => '处理中',
                '3' => '驳回处理',
                '4' => '验收中',
                '5' => '已完成',
                '6' => '暂停中',
            ];
            $str = $arr[$value];
            if (2 == $value && !empty($helper_id)) {
                $str = '协助中';
            }
        } else if ('bg' == $type) {
            $arr = [
                '1' => 'blue',
                '2' => 'orange',
                '3' => 'red',
                '4' => 'yellow',
                '5' => 'green',
                '6' => 'suspend',
            ];
            $str = $arr[$value];
            if (2 == $value && !empty($helper_id)) {
                $str = 'helper';
            }
        }
        return $str;
    }

    //任务流任务等级
    private function taskFlowLevel($value= 1, $type = 'text'){
        if ('text' == $type) {
            $arr = [
                '1' => '不重要',
                '2' => '严重',
                '3' => '主要',
                '4' => '次要',
                '5' => '不重要',
            ];
        } else if ('bg' == $type) {
            $arr = [
                '1' => 'green',
                '2' => 'red',
                '3' => 'blue',
                '4' => 'teal',
                '5' => 'green',
            ];
        }
        return $arr[$value];
    }
}
