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

$home_rewrite = [];
$route = $default_route = [
    '__pattern__' => [
        'tid' => '[\-\w]+',
        'dirname' => '[\-\w]+',
        'aid' => '(.*)',
    ],
    '__alias__' => [],
    '__domain__' => [],
];
$__pattern__ = $route['__pattern__'];

$globalTpCache = tpCache('global');
config('tpcache', $globalTpCache);

$goto = input('param.goto/s');
$goto = trim($goto, '/');
// 会员中心模板风格
$web_users_tpl_theme = !empty($globalTpCache['web_users_tpl_theme']) ? $globalTpCache['web_users_tpl_theme'] : config('ey_config.web_users_tpl_theme');
config('ey_config.web_users_tpl_theme', $web_users_tpl_theme);

// 前台模板风格
$web_tpl_theme = !empty($globalTpCache['web_tpl_theme']) ? $globalTpCache['web_tpl_theme'] : config('ey_config.web_tpl_theme');
if (empty($web_tpl_theme)) {
    if (file_exists(ROOT_PATH.'template/default')) {
        $web_tpl_theme = 'default';
    } else {
        $web_tpl_theme = '';
    }
} else {
    if ('default' == $web_tpl_theme && !file_exists(ROOT_PATH.'template/default')) {
        $web_tpl_theme = '';
    }
}
config('ey_config.web_tpl_theme', $web_tpl_theme);
!empty($web_tpl_theme) && $web_tpl_theme .= '/';

/*辨识是否代码适配，还是PC与移动的分离模板*/
$num = 0;
$response_type = 0; // 默认是代码适配
$tpldirList = ["template/{$web_tpl_theme}pc/index.htm","template/{$web_tpl_theme}mobile/index.htm"];
foreach ($tpldirList as $key => $val) {
    if (file_exists($val)) {
        $num++;
        if ($num >= 2) {
            $response_type = 1; // PC与移动端分离
        }
    }
}
// 分离式模板的手机端以动态URL访问
$separate_mobile = 0;
if (1 == $response_type && empty($globalTpCache['web_mobile_domain']) && isMobile()) {
    $separate_mobile = 1;
}
config('ey_config.response_type', $response_type);
config('ey_config.separate_mobile', $separate_mobile);
/*end*/

// mysql的sql-mode模式参数
$system_sql_mode = !empty($globalTpCache['system_sql_mode']) ? $globalTpCache['system_sql_mode'] : config('ey_config.system_sql_mode');
config('ey_config.system_sql_mode', $system_sql_mode);
// 多语言数量
$system_langnum = !empty($globalTpCache['system_langnum']) ? intval($globalTpCache['system_langnum']) : config('ey_config.system_langnum');
config('ey_config.system_langnum', $system_langnum);
// 前台默认语言
$system_home_default_lang = !empty($globalTpCache['system_home_default_lang']) ? $globalTpCache['system_home_default_lang'] : config('ey_config.system_home_default_lang');
config('ey_config.system_home_default_lang', $system_home_default_lang);
// 是否https链接
$is_https = !empty($globalTpCache['web_is_https']) ? true : config('is_https');
config('is_https', $is_https);
// 前台默认区域
// $site_default_home = !empty($globalTpCache['site_default_home']) ? $globalTpCache['site_default_home'] : config('ey_config.site_default_home');
// config('ey_config.site_default_home', $site_default_home);

// 是否存在问答插件
$is_ask_weapp = false;
if (is_dir('./weapp/Ask/')) {
    $is_ask_weapp = true;
}
//是否存在tags插件
$is_tags_weapp = false;
if (is_dir('./weapp/Tags/')) {
    $weappList = \think\Db::name('weapp')->where([
        'status'    => 1,
    ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
    ->getAllWithIndex('code');
    if (!empty($weappList['Tags'])) {
        $is_tags_weapp = true;
    }
}
$uiset = input('param.uiset/s', 'off');
if ('on' == trim($uiset, '/')) { // 可视化页面必须是兼容模式的URL
    config('ey_config.seo_inlet', 0);
    config('ey_config.seo_pseudo', 1);
    config('ey_config.seo_dynamic_format', 1);
} else {
    // URL模式
    $seo_pseudo = !empty($globalTpCache['seo_pseudo']) ? intval($globalTpCache['seo_pseudo']) : config('ey_config.seo_pseudo');
    $seo_dynamic_format = !empty($globalTpCache['seo_dynamic_format']) ? intval($globalTpCache['seo_dynamic_format']) : config('ey_config.seo_dynamic_format');
    // 分离式的手机端以动态URL模式访问
    if (1 == $separate_mobile) {
        // 当前配置是动态或者静态模式
        if (in_array($seo_pseudo, [1,2])) {
            $seo_pseudo = 1;
            $seo_dynamic_format = 1;
        }
    }
    // URL格式
    config('ey_config.seo_pseudo', $seo_pseudo);
    config('ey_config.seo_dynamic_format', $seo_dynamic_format);
    // 伪静态格式
    $seo_rewrite_format = !empty($globalTpCache['seo_rewrite_format']) ? intval($globalTpCache['seo_rewrite_format']) : config('ey_config.seo_rewrite_format');
    config('ey_config.seo_rewrite_format', $seo_rewrite_format); 
    // 是否隐藏入口文件
    $seo_inlet = !empty($globalTpCache['seo_inlet']) ? $globalTpCache['seo_inlet'] : config('ey_config.seo_inlet');
    config('ey_config.seo_inlet', $seo_inlet);

    if (3 == $seo_pseudo) {
        $request = request();
        $lang_rewrite = $site_rewrite = [];
        $lang_rewrite_str = $site_rewrite_str = '';
        if (config('city_switch_on')) { // 多城市与多语言只能开启一个，多城市优先级高于多语言
            if (stristr($request->baseFile(), 'index.php')) {
                $site = input('param.site/s');
                if (!empty($site) && $request->subDomain() != $site) {
                    $site_rewrite_str .= '<site>/';
                }
            }

            if (!empty($site_rewrite_str)) {
                $site_rewrite = [
                    // 首页
                    $site_rewrite_str.'$' => [
                        'home/Index/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                ];
            }
            $lang_rewrite_str = $site_rewrite_str;
        }
        else { // 多语言
            $lang = input('param.lang/s');
            if (is_language()) {
                if (!stristr($request->baseFile(), 'index.php')) {
                    if (!empty($lang) && $lang != $system_home_default_lang) {
                        $lang_rewrite_str = '<lang>/';
                    }
                } else {
                    if (get_current_lang() != get_default_lang()) {
                        $lang_rewrite_str .= '<lang>/';
                    }
                }
            }

            if (!empty($lang_rewrite_str)) {
                $lang_rewrite = [
                    // 首页
                    $lang_rewrite_str.'$' => [
                        'home/Index/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                ];
            }
        }

        if (in_array($seo_rewrite_format, [1,3,4])) { // 精简伪静态
            $home_rewrite = [
                // 会员中心
                $lang_rewrite_str.'user$' => [
                    'user/Users/login',
                    ['ext' => ''],
                    'cache'=>1
                ],
                $lang_rewrite_str.'reg$' => [
                    'user/Users/reg',
                    ['ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'centre$' => [
                    'user/Users/centre',
                    ['ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'user/index$' => [
                    'user/Users/index',
                    ['ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'cart$' => [
                    'user/Shop/shop_cart_list',
                    ['ext' => ''], 
                    'cache'=>1
                ],

                // 搜索伪静态
                $lang_rewrite_str.'sindex$' => [
                    'home/Search/index',
                    ['method' => 'get', 'ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'search$' => [
                    'home/Search/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    'cache'=>1
                ],
            ];
            if (config('city_switch_on')) {
                $lang_rewrite_str_1 = str_replace('<site>/', '', $lang_rewrite_str);
                $home_rewrite += [
                    // 多城市站点主页
                    $lang_rewrite_str_1.'citysite$' => [
                        'home/Citysite/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                ];
            }
            if (false === $is_tags_weapp){
                $lang_rewrite_str_1 = str_replace('<site>/', '', $lang_rewrite_str);
                $home_rewrite += [
                    // 标签伪静态
                    $lang_rewrite_str_1.'tags$' => [
                        'home/Tags/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                    $lang_rewrite_str_1.'tags/<tagid>_<page>$' => [
                        'home/Tags/lists',
                        ['method' => 'get', 'ext' => 'html'],
                        ['tagid' => '[\d]+', 'page' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str_1.'tags/<tagid>$' => [
                        'home/Tags/lists',
                        ['method' => 'get', 'ext' => 'html'],
                        ['tagid' => '[\d]+'],
                        'cache'=>1
                    ],
                ];
            }
            if (false === $is_ask_weapp) {
                // 问答模型
                $home_rewrite += [
                    $lang_rewrite_str.'ask/list_<type_id>_p<p>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['type_id' => '[\d]+', 'p' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/list_<is_recom>_<type_id>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['is_recom' => '[\d]+', 'type_id' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/list_<type_id>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['type_id' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/view_<ask_id>$' => [
                        'home/Ask/details',
                        ['method' => 'get', 'ext' => 'html'], 
                        ['ask_id' => '[\d]+'],
                        'cache'=>1
                    ],
                ];
            }
            $home_rewrite += [
                // 列表页 - 分页
                $lang_rewrite_str.'<tid>/list_<typeid>_<page>$' => [
                    'home/Lists/index',
                    ['method' => 'get', 'ext' => ''], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                // 列表页
                $lang_rewrite_str.'<tid>$' => [
                    'home/Lists/index',
                    ['method' => 'get', 'ext' => ''], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                // 内容页
                $lang_rewrite_str.'<dirname>/<aid>$' => [
                    'home/View/index',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
            ];
            if ($seo_rewrite_format == 4) { // 父目录/当前目录/
                $arctype_list = get_all_arctype();
                foreach ($arctype_list as $key=>$val){
                    $parent_dir = ""; //$val['dirname']."/";
                    if (!empty($val['parent_id'])) {
                        $parent_dir = get_all_parent_dirpath($val['parent_id'],$arctype_list,$parent_dir);
                    } else {
                        $parent_dir = get_all_parent_dirpath($val['id'],$arctype_list,$parent_dir);
                    }
                    $dirname = trim(str_ireplace("/", "-", $parent_dir),"-");
                    if(!empty($dirname)){
                        $home_rewrite += [
                            // 列表页 - 分页
                            $lang_rewrite_str.$parent_dir.'<tid>/list_<typeid>_<page>$' => [
                                'home/Lists/index?'.$dirname,
                                ['method' => 'get', 'ext' => ''],
                                ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'],
                                'cache'=>1
                            ],
                            // 列表页
                            $lang_rewrite_str.$parent_dir.'<tid>$' => [
                                'home/Lists/index?'.$dirname,
                                ['method' => 'get', 'ext' => ''],
                                ['tid' => $__pattern__['tid']],
                                'cache'=>1
                            ],
                            // 内容页
                            $lang_rewrite_str.$parent_dir.'<dirname>/<aid>$' => [
                                'home/View/index?'.$dirname,
                                ['method' => 'get', 'ext' => 'html'],
                                ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                                'cache'=>1
                            ],
                        ];
                    }
                }
            }
        } else {
            $home_rewrite = [
                // 会员中心
                $lang_rewrite_str.'Users/login$' => [
                    'user/Users/login',
                    ['ext' => 'html'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'Users/reg$' => [
                    'user/Users/reg',
                    ['ext' => 'html'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'Users/centre$' => [
                    'user/Users/centre',
                    ['ext' => 'html'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'Users/index$' => [
                    'user/Users/index',
                    ['ext' => 'html'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'Users/cart$' => [
                    'user/Shop/shop_cart_list',
                    ['ext' => 'html'], 
                    'cache'=>1
                ],
                // 文章模型伪静态
                $lang_rewrite_str.'article/<tid>/list_<typeid>_<page>$' => [
                    'home/Article/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'article/<tid>$' => [
                    'home/Article/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'article/<dirname>/<aid>$' => [
                    'home/Article/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 产品模型伪静态
                $lang_rewrite_str.'product/<tid>/list_<typeid>_<page>$' => [
                    'home/Product/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'product/<tid>$' => [
                    'home/Product/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'product/<dirname>/<aid>$' => [
                    'home/Product/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 图集模型伪静态
                $lang_rewrite_str.'images/<tid>/list_<typeid>_<page>$' => [
                    'home/Images/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'images/<tid>$' => [
                    'home/Images/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'images/<dirname>/<aid>$' => [
                    'home/Images/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 下载模型伪静态
                $lang_rewrite_str.'download/<tid>/list_<typeid>_<page>$' => [
                    'home/Download/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'download/<tid>$' => [
                    'home/Download/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'download/<dirname>/<aid>$' => [
                    'home/Download/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 视频模型伪静态
                $lang_rewrite_str.'media/<tid>/list_<typeid>_<page>$' => [
                    'home/Media/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'media/<tid>$' => [
                    'home/Media/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'media/<dirname>/<aid>$' => [
                    'home/Media/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 专题模型伪静态
                $lang_rewrite_str.'special/<tid>/list_<typeid>_<page>$' => [
                    'home/Special/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'special/<tid>$' => [
                    'home/Special/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'special/<dirname>/<aid>$' => [
                    'home/Special/view',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                    'cache'=>1
                ],
                // 单页模型伪静态
                $lang_rewrite_str.'single/<tid>$' => [
                    'home/Single/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
                // 搜索伪静态
                $lang_rewrite_str.'sindex$' => [
                    'home/Search/index',
                    ['method' => 'get', 'ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'search$' => [
                    'home/Search/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    'cache'=>1
                ],
                // 留言模型
                $lang_rewrite_str.'guestbook/<tid>$' => [
                    'home/Guestbook/lists',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['tid' => $__pattern__['tid']], 
                    'cache'=>1
                ],
            ];
            if (config('city_switch_on')) {
                $lang_rewrite_str_1 = str_replace('<site>/', '', $lang_rewrite_str);
                $home_rewrite += [
                    // 多城市站点主页
                    $lang_rewrite_str_1.'citysite$' => [
                        'home/Citysite/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                ];
            }
            if (false === $is_tags_weapp){
                $lang_rewrite_str_1 = str_replace('<site>/', '', $lang_rewrite_str);
                $home_rewrite += [
                    // 标签伪静态
                    $lang_rewrite_str_1.'tags$' => [
                        'home/Tags/index',
                        ['method' => 'get', 'ext' => ''],
                        'cache'=>1
                    ],
                    $lang_rewrite_str_1.'tags/<tagid>_<page>$' => [
                        'home/Tags/lists',
                        ['method' => 'get', 'ext' => 'html'],
                        ['tagid' => '[\d]+', 'page' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str_1.'tags/<tagid>$' => [
                        'home/Tags/lists',
                        ['method' => 'get', 'ext' => 'html'],
                        ['tagid' => '[\d]+'],
                        'cache'=>1
                    ],
                ];
            }
            if (false === $is_ask_weapp) {
                // 问答模型
                $home_rewrite += [
                    $lang_rewrite_str.'ask/list_<type_id>_p<p>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['type_id' => '[\d]+', 'p' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/list_<is_recom>_<type_id>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['is_recom' => '[\d]+', 'type_id' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/list_<type_id>$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        ['type_id' => '[\d]+'],
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask$' => [
                        'home/Ask/index',
                        ['method' => 'get', 'ext' => ''], 
                        'cache'=>1
                    ],
                    $lang_rewrite_str.'ask/view_<ask_id>$' => [
                        'home/Ask/details',
                        ['method' => 'get', 'ext' => 'html'], 
                        ['ask_id' => '[\d]+'],
                        'cache'=>1
                    ],
                ];
            }

            /*自定义模型*/
            $cacheKey = "application_route_channeltype";
            $channeltype_row = \think\Cache::get($cacheKey);
            if (empty($channeltype_row)) {
                $channeltype_row = \think\Db::name('channeltype')->field('nid,ctl_name')
                    ->where([
                        'ifsystem' => 0,
                    ])
                    ->select();
                \think\Cache::set($cacheKey, $channeltype_row, EYOUCMS_CACHE_TIME, "channeltype");
            }
            foreach ($channeltype_row as $value) {
                $home_rewrite += [
                    $lang_rewrite_str.$value['nid'].'/<tid>/list_<typeid>_<page>$' => [
                        'home/'.$value['ctl_name'].'/lists',
                        ['method' => 'get', 'ext' => 'html'], 
                        ['tid' => $__pattern__['tid'], 'typeid' => '[\d]+', 'page' => '[\d]+'], 
                        'cache'=>1
                    ],
                    $lang_rewrite_str.$value['nid'].'/<tid>$' => [
                        'home/'.$value['ctl_name'].'/lists',
                        ['method' => 'get', 'ext' => 'html'], 
                        ['tid' => $__pattern__['tid']], 
                        'cache'=>1
                    ],
                    $lang_rewrite_str.$value['nid'].'/<dirname>/<aid>$' => [
                        'home/'.$value['ctl_name'].'/view',
                        ['method' => 'get', 'ext' => 'html'], 
                        ['dirname' => $__pattern__['dirname'], 'aid' => $__pattern__['aid']],
                        'cache'=>1
                    ],
                ];
            }
            /*--end*/
        }
        $home_rewrite = array_merge($lang_rewrite, $site_rewrite, $home_rewrite);
    }
    else if (2 == $seo_pseudo) {
        $lang_rewrite_str = $site_rewrite_str = '';
        $home_rewrite = [];
        if (false === $is_ask_weapp) {
            // 问答模型
            $home_rewrite += [
                $lang_rewrite_str.'ask/list_<type_id>_p<p>$' => [
                    'home/Ask/index',
                    ['method' => 'get', 'ext' => ''], 
                    ['type_id' => '[\d]+', 'p' => '[\d]+'],
                    'cache'=>1
                ],
                $lang_rewrite_str.'ask/list_<is_recom>_<type_id>$' => [
                    'home/Ask/index',
                    ['method' => 'get', 'ext' => ''], 
                    ['is_recom' => '[\d]+', 'type_id' => '[\d]+'],
                    'cache'=>1
                ],
                $lang_rewrite_str.'ask/list_<type_id>$' => [
                    'home/Ask/index',
                    ['method' => 'get', 'ext' => ''], 
                    ['type_id' => '[\d]+'],
                    'cache'=>1
                ],
                $lang_rewrite_str.'ask$' => [
                    'home/Ask/index',
                    ['method' => 'get', 'ext' => ''], 
                    'cache'=>1
                ],
                $lang_rewrite_str.'ask/view_<ask_id>$' => [
                    'home/Ask/details',
                    ['method' => 'get', 'ext' => 'html'], 
                    ['ask_id' => '[\d]+'],
                    'cache'=>1
                ],
            ];
        }
    }
    /*插件模块路由*/
    $weapp_route_file = 'plugins/route.php';
    if (file_exists(APP_PATH.$weapp_route_file)) {
        $weapp_route = include_once $weapp_route_file;
        $route = array_merge($weapp_route, $route);
    }
    /*--end*/
}

$route = array_merge($route, $home_rewrite);

// 命令行执行的php脚本，不需要走路由规则
if (IS_CLI) {
    if (is_dir('./weapp/Im/')) {
        // Win 环境
        if (IS_WIN) {
            if (!empty($_SERVER['argv'][0]) && preg_match('/weapp(\/|\\\)Im(\/|\\\)/', $_SERVER['argv'][0])) {
                $route = $default_route;
            }
        }
        // 非 Win 环境
        else {
            if (!empty($_SERVER['argv'][1]) && 'Im' == $_SERVER['argv'][1]) {
                $route = $default_route;
            }
        }
    }
}

return $route;
