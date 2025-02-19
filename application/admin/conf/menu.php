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

$main_lang= get_main_lang();
$admin_lang = get_admin_lang();
$domain = request()->domain();
/*PC端可视编辑URl*/
$uiset_pc_arr = [];
if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/uiset.txt')) {
    $uiset_pc_arr = array(
        'url' => url('Uiset/pc', array(), true, $domain),
        'is_menu' => 1,
    );
}
/*--end*/

/*手机端可视编辑URl*/
$uiset_mobile_arr = [];
if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/uiset.txt')) {
    $uiset_mobile_arr = array(
        'url' => url('Uiset/mobile', array(), true, $domain),
        'is_menu' => 1,
    );
}
/*--end*/

/*清理数据URl*/
$uiset_data_url = '';
if (!empty($uiset_pc_arr) || !empty($uiset_mobile_arr)) {
    $uiset_data_url = url('Uiset/ui_index', array(), true, $domain);
}
/*--end*/

/*可视编辑URL*/
$uiset_index_arr = array();
if (!empty($uiset_pc_arr) || !empty($uiset_mobile_arr)) {
    $uiset_index_arr = array(
        'url' => url('Uiset/ui_index', array(), true, $domain),
        'is_menu' => 1,
    );
}
/*--end*/

/*基本信息*/
$ctlactArr = [
    'System@web',
    'System@web2',
    'System@basic',
    'System@water',
    'System@api_conf',
    'PayApi@pay_api_index',
];
$system_index_arr = array();
foreach ($ctlactArr as $key => $val) {
    if (is_check_access($val)) {
        $arr = explode('@', $val);
        $system_index_arr = array(
            'controller' => !empty($arr[0]) ? $arr[0] : '',
            'action'     => !empty($arr[1]) ? $arr[1] : '',
        );
        break;
    }
}
/*--end*/

/*SEO优化URl*/
$seo_index_arr = array();
if ($main_lang != $admin_lang) {
    $seo_index_arr = array(
        'controller' => 'Links',
        'action'     => 'index',
    );
} else {
    $ctlactArr = [
        'Seo@seo',
        'Sitemap@index',
        'Links@index',
    ];
    foreach ($ctlactArr as $key => $val) {
        if (is_check_access($val)) {
            $arr = explode('@', $val);
            $seo_index_arr = array(
                'controller' => !empty($arr[0]) ? $arr[0] : '',
                'action'     => !empty($arr[1]) ? $arr[1] : '',
            );
            break;
        }
    }
}
/*--end*/

/*备份还原URl*/
$tools_index_arr = array();
if ($main_lang == $admin_lang) {
    $tools_index_arr = array(
        'is_menu' => 1,
    );
}
/*--end*/

/*频道模型URl*/
$channeltype_index_arr = array();
if ($main_lang == $admin_lang) {
    $channeltype_index_arr = array(
        'is_menu' => 1,
    );
}
/*--end*/

/*回收站URl*/
$recyclebin_index_arr = array(
    'is_menu' => 1,
);
/*--end*/

/*插件应用URl*/
$weapp_index_arr = array();
if ($main_lang == $admin_lang && 1 == tpCache('global.web_weapp_switch') && file_exists(ROOT_PATH.'weapp')) {
    //功能限制
    $auth_function = true;
    if (function_exists('checkAuthRule')) {
        $auth_function = checkAuthRule(2005);
    }
    //2005 菜单id
    if ($auth_function){
        $weapp_index_arr = array(
            'is_menu' => 1,
        );
    }else{
        $weapp_index_arr = array(
            'is_menu' => 0,
        );
    }

}
/*--end*/

/*会员中心URl*/
$users_index_arr = array();
if (1 == tpCache('global.web_users_switch') && $main_lang == $admin_lang) {
    $users_index_arr = array(
        'is_menu' => 1,
        'is_modules' => 1,
    );
}
/*--end*/

/*商城中心URl*/
$shop_index_arr = array();
$shopServicemeal = array_join_string(array('cGhwLnBocF9zZXJ2aWNlbWVhbA=='));
if (1 == tpCache('global.web_users_switch') && 1 == getUsersConfigData('shop.shop_open') && $main_lang == $admin_lang && 1.5 <= tpCache($shopServicemeal)) {
    $shop_index_arr = array(
        'is_menu' => 0,
        'is_modules' => 1,
    );
}
/*--end*/

/*订单管理URl*/
$order_index_arr = array();
if (!empty($users_index_arr['is_modules']) || !empty($shop_index_arr['is_modules'])) {
    $order_index_arr = array(
        'is_menu' => 1,
        'is_modules' => 1,
    );
}
/*--end*/

/*待审文档URl*/
$archives_index_draft_arr = array();
if (1 == tpCache('global.web_users_switch') && 1 == getUsersConfigData('users.users_open_release') && $main_lang == $admin_lang) {
    $archives_index_draft_arr = array(
        'is_menu' => 1,
        'is_modules' => 1,
    );
}
/*--end*/

/*退回文档URl*/
$archives_index_reback_arr = array();
if ($main_lang == $admin_lang) {
    $archives_index_reback_arr = array(
        'is_menu' => 1,
        'is_modules' => 1,
    );
}
/*--end*/

/*小程序*/
$diyminipro_index_arr = array();
if (is_dir('./weapp/Diyminipro/') && 1 == tpCache('global.web_diyminipro_switch') && $main_lang == $admin_lang) {
    $diyminipro_index_arr = array(
        'is_modules' => 1,
    );
}
/*--end*/

/*更多功能*/
$index_switch_map_arr = array(
    'is_menu' => 1,
    'is_modules' => 1,
);
/*--end*/

/**
 * 权限模块属性说明
 * array
 *      id  主键ID
 *      parent_id   父ID
 *      name    模块名称
 *      controller  控制器
 *      action  操作名
 *      url     跳转链接(控制器与操作名为空时，才使用url)
 *      target  打开窗口方式
 *      icon    菜单图标
 *      grade   层级
 *      is_menu 是否显示菜单
 *      is_modules  是否显示权限模块分组
 *      is_subshowmenu  子模块是否有显示的模块
 *      child   子模块
 */
return  array(
    '1000'=>array(
        'id'=>1000,
        'parent_id'=>0,
        'name'=>'',
        'controller'=>'',
        'action'=>'',
        'url'=>'',
        'target'=>'workspace',
        'grade'=>0,
        'is_menu'=>1,
        'is_modules'=>1,
        'is_subshowmenu'=>1,
        'child'=>array(
            '1005' => [
                'id'=>1005,
                'parent_id'=>1000,
                'name' => '欢迎页',
                'controller'=>'Index',
                'action'=>'welcome',
                'url'=>'',
                'target'=>'workspace',
                'icon'=>'fa fa-user',
                'grade'=>1,
                'is_menu'=>0,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child' => [
                    '1005001' => [
                        'id'=>1005001,
                        'parent_id'=>1005,
                        'name' => '快捷导航',
                        'controller'=>'Index',
                        'action'=>'ajax_quickmenu',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => [],
                    ],
                    '1005002' => [
                        'id'=>1005002,
                        'parent_id'=>1005,
                        'name' => '内容统计',
                        'controller'=>'Index',
                        'action'=>'ajax_content_total',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => [],
                    ],
                ],
            ],
            '1001' => array(
                'id'=>1001,
                'parent_id'=>1000,
                'name' => '栏目管理',
                'controller'=>'Arctype',
                'action'=>'index',
                'param' => '|mt20|1',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-lanmuguanli',
                'grade'=>1,
                'is_menu'=>1,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),
            '1002' => array(
                'id'=>1002,
                'parent_id'=>1000,
                'name' => '内容管理',
                'controller'=>'Archives',
                'action'=>'index',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-neirongwendang',
                'grade'=>1,
                'is_menu'=>1,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),
            '1004' => array(
                'id'=>1004,
                'parent_id'=>1000,
                'name' => '待审文档',
                'controller'=>'Archives',
                'action'=>'index_draft',
                'param' => '|menu|1',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-tougao',
                'grade'=>1,
                'is_menu'=>isset($archives_index_draft_arr['is_menu']) ? $archives_index_draft_arr['is_menu'] : 0,
                'is_modules'=>isset($archives_index_draft_arr['is_modules']) ? $archives_index_draft_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),
            /*'1006' => array(
                'id'=>1006,
                'parent_id'=>1000,
                'name' => '退回文档',
                'controller'=>'Archives',
                'action'=>'index_reback',
                'param' => '|menu|1',
                'url'=>'',
                'target'=>'workspace',
                'icon'=>'iconfont e-tougao',
                'grade'=>1,
                'is_menu'=>isset($archives_index_reback_arr['is_menu']) ? $archives_index_reback_arr['is_menu'] : 0,
                'is_modules'=>isset($archives_index_reback_arr['is_modules']) ? $archives_index_reback_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),*/
            '1003' => array(
                'id'=>1003,
                'parent_id'=>1000,
                'name' => '广告管理',
                'controller'=>'AdPosition',
                'action'=>'index',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-guanggao',
                'grade'=>1,
                'is_menu'=>1,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),
        ),
    ),
        
    '2000'=>array(
        'id'=>2000,
        'parent_id'=>0,
        'name'=>'设置',
        'controller'=>'',
        'action'=>'',
        'url'=>'', 
        'target'=>'workspace',
        'grade'=>0,
        'is_menu'=>1,
        'is_modules'=>1,
        'is_subshowmenu'=>1,
        'child'=>array(
            '2001' => array(
                'id'=>2001,
                'parent_id'=>2000,
                'name' => '基本信息',
                'controller'=>isset($system_index_arr['controller']) ? $system_index_arr['controller'] : 'System',
                'action'=>isset($system_index_arr['action']) ? $system_index_arr['action'] : 'index',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-shezhi',
                'grade'=>1,
                'is_menu'=>1,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child' => array(
                    '2001001' => array(
                        'id'=>2001001,
                        'parent_id'=>2001,
                        'name' => '网站信息',
                        'controller'=>'System',
                        'action'=>'web',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2001002' => array(
                        'id'=>2001002,
                        'parent_id'=>2001,
                        'name' => '核心设置',
                        'controller'=>'System',
                        'action'=>'web2',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2001003' => array(
                        'id'=>2001003,
                        'parent_id'=>2001,
                        'name' => '附件扩展',
                        'controller'=>'System',
                        'action'=>'basic',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2001005' => array(
                        'id'=>2001005,
                        'parent_id'=>2001,
                        'name' => '接口API',
                        'controller'=>'System',
                        'action'=>'api_conf',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2001006' => array(
                        'id'=>2001006,
                        'parent_id'=>2001,
                        'name' => '自定义变量',
                        'controller'=>'System',
                        'action'=>'customvar_index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                ),
            ),
            '2002' => array(
                'id'=>2002,
                'parent_id'=>2000,
                'name' => '可视编辑',
                'controller'=>'Uiset',
                'action'=>'ui_index',
                'url'=>isset($uiset_index_arr['url']) ? $uiset_index_arr['url'] : '',
                'target'=>'workspace',
                'icon'=>'iconfont e-keshihuabianji',
                'grade'=>1,
                'is_menu'=>isset($uiset_index_arr['is_menu']) ? $uiset_index_arr['is_menu'] : 0,
                'is_modules'=>1,
                'is_subshowmenu'=>1,
                'child'=>array(
                    '2002001' => array(
                        'id'=>2002001,
                        'parent_id'=>2002,
                        'name' => '电脑端',
                        'controller'=>'Uiset',
                        'action'=>'pc',
                        'url'=>isset($uiset_pc_arr['url']) ? $uiset_pc_arr['url'] : '',
                        'target'=>'_blank',
                        'icon'=>'iconfont e-pc',
                        'grade'=>2,
                        'is_menu'=>isset($uiset_pc_arr['is_menu']) ? $uiset_pc_arr['is_menu'] : 0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2002002' => array(
                        'id'=>2002002,
                        'parent_id'=>2002,
                        'name' => '手机端',
                        'controller'=>'Uiset',
                        'action'=>'mobile',
                        'url'=>isset($uiset_mobile_arr['url']) ? $uiset_mobile_arr['url'] : '',
                        'target'=>'_blank',
                        'icon'=>'fa fa-mobile',
                        'grade'=>2,
                        'is_menu'=>isset($uiset_mobile_arr['is_menu']) ? $uiset_mobile_arr['is_menu'] : 0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2002003' => array(
                        'id'=>2002003,
                        'parent_id'=>2002,
                        'name' => '数据清理',
                        'controller'=>'Uiset',
                        'action'=>'ui_index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-qingli',
                        'grade'=>2,
                        'is_menu'=>1,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                ),
            ),
            '2003' => array(
                'id'=>2003,
                'parent_id'=>2000,
                'name' => 'SEO模块',
                'controller'=>isset($seo_index_arr['controller']) ? $seo_index_arr['controller'] : 'Seo',
                'action'=>isset($seo_index_arr['action']) ? $seo_index_arr['action'] : 'seo',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-seo',
                'grade'=>1,
                'is_menu'=>1,
                'is_modules'=>1,
                'is_subshowmenu'=>0,
                'child'=>array(
                    '2003001' => array(
                        'id'=>2003001,
                        'parent_id'=>2003,
                        'name' => 'SEO设置',
                        'controller'=>'Seo',
                        'action'=>'seo',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-URLpeizhi1',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2003002' => array(
                        'id'=>2003002,
                        'parent_id'=>2003,
                        'name' => 'Sitemap', 
                        'controller'=>'Sitemap',
                        'action'=>'index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-Sitemap',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2003003' => array(
                        'id'=>2003003,
                        'parent_id'=>2003,
                        'name' => '友情链接', 
                        'controller'=>'Links',
                        'action'=>'index', 
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-youqinglianjie1',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    // '2003004' => array(
                    //     'id'=>2003004,
                    //     'parent_id'=>2003,
                    //     'name' => 'html生成',
                    //     'controller'=>'Seo',
                    //     'action'=>'build',
                    //     'url'=>'',
                    //     'target'=>'workspace',
                    //     'icon'=>'iconfont e-jingtaishengcheng',
                    //     'grade'=>2,
                    //     'is_menu'=>0,
                    //     'is_modules'=>1,
                    //     'is_subshowmenu'=>0,
                    //     'child' => array(),
                    // ),
                ),
            ),
            '2004' => array(
                'id'=>2004,
                'parent_id'=>2000,
                'name' => '功能地图',
                'controller'=>'Index',
                'action'=>'switch_map',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'iconfont e-caidangongneng',
                'grade'=>1,
                'is_menu'=>isset($index_switch_map_arr['is_menu']) ? $index_switch_map_arr['is_menu'] : 0,
                'is_modules'=>isset($index_switch_map_arr['is_modules']) ? $index_switch_map_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(
                    '2004001' => array(
                        'id'=>2004001,
                        'parent_id'=>2004,
                        'name' => '管理员', 
                        'controller'=>'Admin',
                        'action'=>'index', 
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-guanliyuan',
                        'grade'=>2,
                        'is_menu'=>1,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004002' => array(
                        'id'=>2004002,
                        'parent_id'=>2004,
                        'name' => '备份还原', 
                        'controller'=>'Tools',
                        'action'=>'index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-beifenhuanyuan',
                        'grade'=>2,
                        'is_menu'=>isset($tools_index_arr['is_menu']) ? $tools_index_arr['is_menu'] : 0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004003' => array(
                        'id'=>2004003,
                        'parent_id'=>2004,
                        'name' => '模板管理', 
                        'controller'=>'Filemanager',
                        'action'=>'index', 
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-mobanguanli',
                        'grade'=>2,
                        'is_menu'=>1,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004004' => array(
                        'id'=>2004004,
                        'parent_id'=>2004,
                        'name' => '栏目字段', 
                        'controller'=>'Field',
                        'action'=>'arctype_index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-lanmuziduan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    // '2004005' => array(
                    //     'id'=>2004005,
                    //     'parent_id'=>2004,
                    //     'name' => '清除缓存',
                    //     'controller'=>'System',
                    //     'action'=>'clear_cache', 
                    //     'url'=>'', 
                    //     'target'=>'workspace',
                    //     'icon'=>'fa fa-undo',
                    //     'grade'=>2,
                    //     'is_menu'=>1,
                    //     'is_modules'=>0,
                    //     'is_subshowmenu'=>0,
                    //     'child' => array(),
                    // ),
                    '2004006' => array(
                        'id'=>2004006,
                        'parent_id'=>2004,
                        'name' => '回收站',
                        'controller'=>'RecycleBin',
                        'action'=>'archives_index',
                        'param' => '|mt20|1|menu|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-huishouzhan',
                        'grade'=>2,
                        'is_menu'=>isset($recyclebin_index_arr['is_menu']) ? $recyclebin_index_arr['is_menu'] : 0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004007' => array(
                        'id'=>2004007,
                        'parent_id'=>2004,
                        'name' => '频道模型',
                        'controller'=>'Channeltype',
                        'action'=>'index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-pindaomoxing',
                        'grade'=>2,
                        'is_menu'=>isset($channeltype_index_arr['is_menu']) ? $channeltype_index_arr['is_menu'] : 0,
                        'is_modules'=>0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004008' => array(
                        'id'=>2004008,
                        'parent_id'=>2004,
                        'name' => '文档属性',
                        'controller'=>'ArchivesFlag',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-wendangshuxing',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004009' => array(
                        'id'=>2004009,
                        'parent_id'=>2004,
                        'name' => '图片水印',
                        'controller'=>'System',
                        'action'=>'water',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont  e-shuiyinpeizhi',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004010' => array(
                        'id'=>2004010,
                        'parent_id'=>2004,
                        'name' => '缩略图设置',
                        'controller'=>'System',
                        'action'=>'thumb',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-suolvetupeizhi',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004011' => array(
                        'id'=>2004011,
                        'parent_id'=>2004,
                        'name' => 'TAG管理',
                        'controller'=>'Tags',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-TAGguanli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004012' => array(
                        'id'=>2004012,
                        'parent_id'=>2004,
                        'name' => '模块开关',
                        'controller'=>'Index',
                        'action'=>'switch_map_0',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004013' => array(
                        'id'=>2004013,
                        'parent_id'=>2004,
                        'name' => '导航管理',
                        'controller'=>'Navigation',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-daohangguanli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004014' => array(
                        'id'=>2004014,
                        'parent_id'=>2004,
                        'name' => '站内通知',
                        'controller'=>'UsersNotice',
                        'action'=>'admin_notice_index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004015' => array(
                        'id'=>2004015,
                        'parent_id'=>2004,
                        'name' => '验证码管理',
                        'controller'=>'Vertify',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-yanzhengmaguanli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004016' => array(
                        'id'=>2004016,
                        'parent_id'=>2004,
                        'name' => 'html生成',
                        'controller'=>'Seo',
                        'action'=>'build',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-jingtaishengcheng',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004017' => array(
                        'id'=>2004017,
                        'parent_id'=>2004,
                        'name' => '安全中心',
                        'controller'=>'Security',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-anquan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004018' => array(
                        'id'=>2004018,
                        'parent_id'=>2004,
                        'name' => '留言中心',
                        'controller'=>'Form',
                        'action'=>'index',
                        'param' => '',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-biaodanguanli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004019' => array(
                        'id'=>2004019,
                        'parent_id'=>2004,
                        'name' => '城市分站',
                        'controller'=>'Citysite',
                        'action'=>'index',
                        'url'=>'', 
                        'target'=>'workspace',
                        'icon'=>'iconfont e-chengshifenzhan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004020' => array(
                        'id'=>2004020,
                        'parent_id'=>2004,
                        'name' => '当前导航',
                        'controller'=>'Index',
                        'action'=>'switch_map_1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'fa fa-undo',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004021' => array(
                        'id'=>2004021,
                        'parent_id'=>2004,
                        'name' => '订单管理',
                        'controller'=>'Order',
                        'action'=>'index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-dingdanguanli',
                        'grade'=>2,
                        'is_menu'=> isset($order_index_arr['is_menu']) ? $order_index_arr['is_menu'] : 0,
                        'is_modules'=> isset($order_index_arr['is_modules']) ? $order_index_arr['is_modules'] : 0,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004022' => array(
                        'id'=>2004022,
                        'parent_id'=>2004,
                        'name' => '搜索管理',
                        'controller'=>'Search',
                        'action'=>'index',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-soguanjianci',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004023' => array(
                        'id'=>2004023,
                        'parent_id'=>2004,
                        'name' => '积分兑换',
                        'controller'=>'Memgift',
                        'action'=>'gift_exchange_list',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-lipinduihuan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004025' => array(
                        'id'=>2004025,
                        'parent_id'=>2004,
                        'name' => '主题风格',
                        'controller'=>'Index',
                        'action'=>'theme_index',
                        'param' => '',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-zhutifengge',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2004026' => array(
                        'id'=>2004026,
                        'parent_id'=>2004,
                        'name' => '外贸助手',
                        'controller'=>'Foreign',
                        'action'=>'index',
                        'param' => '',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-zhutifengge',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                ),
            ),
            '2005' => array(
                'id'=>2005,
                'parent_id'=>2000,
                'name' => '插件应用',
                'controller'=>'Weapp',
                'action'=>'index',
                'url'=>'',
                'target'=>'workspace',
                'icon'=>'iconfont e-chajian',
                'grade'=>1,
                'is_menu'=>isset($weapp_index_arr['is_menu']) ? $weapp_index_arr['is_menu'] : 0,
                'is_modules'=>0,
                'is_subshowmenu'=>0,
                'child'=>array(),
            ),
            '2006' => array(
                'id'=>2006,
                'parent_id'=>2000,
                'name' => '会员中心',
                'controller'=>'Member',
                'action'=>'users_index',
                'url'=>'',
                'target'=>'workspace',
                'icon'=>'iconfont e-gerenzhongxin',
                'grade'=>1,
                'is_menu'=>isset($users_index_arr['is_menu']) ? $users_index_arr['is_menu'] : 0,
                'is_modules'=>isset($users_index_arr['is_modules']) ? $users_index_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(
                    '2006001' => array(
                        'id'=>2006001,
                        'parent_id'=>2006,
                        'name' => '余额充值',
                        'controller'=>'Member',
                        'action'=>'money_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-yuechongzhi',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2006002' => array(
                        'id'=>2006002,
                        'parent_id'=>2006,
                        'name' => '视频订单',
                        'controller'=>'Member',
                        'action'=>'media_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shipindingdan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2006003' => array(
                        'id'=>2006003,
                        'parent_id'=>2006,
                        'name' => '会员订单',
                        'controller'=>'Level',
                        'action'=>'upgrade_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-huiyuandingdan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2006004' => array(
                        'id'=>2006004,
                        'parent_id'=>2006,
                        'name' => '文章订单',
                        'controller'=>'Member',
                        'action'=>'article_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-wenzhangdingdan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                ),
            ),
            '2008' => array(
                'id'=>2008,
                'parent_id'=>2000,
                'name' => '商城中心',
                'controller'=>'Shop',
                'action'=>'home',
                'url'=>'',
                'target'=>'workspace',
                'icon'=>'iconfont e-shangcheng',
                'grade'=>1,
                'is_menu'=> isset($shop_index_arr['is_menu']) ? $shop_index_arr['is_menu'] : 0,
                'is_modules'=> isset($shop_index_arr['is_modules']) ? $shop_index_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(
                    '2008001' => array(
                        'id'=>2008001,
                        'parent_id'=>2008,
                        'name' => '数据统计',
                        'controller'=>'Statistics',
                        'action'=>'index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shujutongji',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008002' => array(
                        'id'=>2008002,
                        'parent_id'=>2008,
                        'name' => '商品管理',
                        'controller'=>'ShopProduct',
                        'action'=>'index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shangpinguanli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008003' => array(
                        'id'=>2008003,
                        'parent_id'=>2008,
                        'name' => '商品参数',
                        'controller'=>'ShopProduct',
                        'action'=>'attrlist_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shangpincanshu',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008004' => array(
                        'id'=>2008004,
                        'parent_id'=>2008,
                        'name' => '商城配置',
                        'controller'=>'Shop',
                        'action'=>'conf',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shangchengpeizhi',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008005' => array(
                        'id'=>2008005,
                        'parent_id'=>2008,
                        'name' => '营销功能',
                        'controller'=>'Shop',
                        'action'=>'market_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-yingxiaogongneng',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008006' => array(
                        'id'=>2008006,
                        'parent_id'=>2008,
                        'name' => '商城订单',
                        'controller'=>'Shop',
                        'action'=>'index',
                        'param' => '|order_status|10|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shangchengdingdan',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008007' => array(
                        'id'=>2008007,
                        'parent_id'=>2008,
                        'name' => '售后处理',
                        'controller'=>'ShopService',
                        'action'=>'after_service',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shouhouchuli',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                    '2008008' => array(
                        'id'=>2008008,
                        'parent_id'=>2008,
                        'name' => '商品规格',
                        'controller'=>'Shop',
                        'action'=>'spec_index',
                        'param' => '|conceal|1',
                        'url'=>'',
                        'target'=>'workspace',
                        'icon'=>'iconfont e-shangpinguige',
                        'grade'=>2,
                        'is_menu'=>0,
                        'is_modules'=>1,
                        'is_subshowmenu'=>0,
                        'child' => array(),
                    ),
                ),
            ),
            '2009' => array(
                'id'=>2009,
                'parent_id'=>2000,
                'name' => '可视化小程序',
                'controller'=>'Diyminipro',
                'action'=>'page_edit',
                'url'=>'', 
                'target'=>'workspace',
                'icon'=>'fa fa-code',
                'grade'=>1,
                'is_menu'=>0,
                'is_modules'=>isset($diyminipro_index_arr['is_modules']) ? $diyminipro_index_arr['is_modules'] : 0,
                'is_subshowmenu'=>0,
                'child' => array(),
            ),
        ),
    ),
);