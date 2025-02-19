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

$admin_ey_config = [
    'seo_pseudo'    => 1, // 默认纯动态URL模式，兼容不支持pathinfo环境
    'seo_dynamic_format'    => 1, // 1=兼容模式的URL，2=伪动态
    'seo_rewrite_format'    => config('ey_config.seo_rewrite_format'),
    'system_sql_mode'   => config('ey_config.system_sql_mode'), // 数据库模式
    'seo_inlet' => config('ey_config.seo_inlet'), // 0=保留入口文件，1=隐藏入口文件
];
$ey_config = array_merge(config('ey_config'), $admin_ey_config);

// 分页数
$system_paginate_pagesize = input('param.pagesize/d', 0);
if (empty($system_paginate_pagesize)) {
    $system_paginate_pagesize = config('tpcache.system_paginate_pagesize');
    $system_paginate_pagesize = !empty($system_paginate_pagesize) ? intval($system_paginate_pagesize) : 20;
}

$admin_config = array(
    'ey_config' => $ey_config,
    //分页配置
    'paginate'      => array(
        'list_rows' => $system_paginate_pagesize,
    ),
    // 默认全局过滤方法 用逗号分隔多个
    // 'default_filter'         => 'htmlspecialchars',
    // 登录有效期，单位秒
    'login_expire' => 3600,
    // 登录错误最大次数
    'login_errtotal'   => 5,
    // 登录错误超过次数之后，锁定用户名有效时间，单位秒
    'login_errexpire'   => 600,
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    // 默认成功跳转对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    // 默认错误跳转对应的模板文件
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件 
    //'exception_tmpl'         => ROOT_PATH.'public/errpage/404.html',
    // errorpage 错误页面
    //'error_tmpl'         => ROOT_PATH.'public/errpage/404.html',

    /**假设这个访问地址是 www.xxxxx.dev/home/goods/goodsInfo/id/1.html 
     *就保存名字为 home_goods_goodsinfo_1.html     
     *配置成这样, 指定 模块 控制器 方法名 参数名
     */
    'HTML_CACHE_STATUS' => false,
    
    // 控制器与操作名之间的连接符
    'POWER_OPERATOR' => '@',

    // 数据管理
    'DATA_BACKUP_PATH' => '/data/sqldata', //数据库备份根路径
    'DATA_BACKUP_PART_SIZE' => 524288000, //数据库备份卷大小 50M
    'DATA_BACKUP_COMPRESS' => 0, //数据库备份文件是否启用压缩
    'DATA_BACKUP_COMPRESS_LEVEL' => 9, //数据库备份文件压缩级别

    // 过滤不需要登录的操作
    'filter_login_action' => array(
        'Admin@login',
        'Admin@logout',
        'Admin@vertify',
        'Admin@wechat_login', // 扫码微信应用登录
        'Admin@wechat_callback', // 扫码微信应用回调
        'Admin@mp_getqrcode', // 获取微信公众号二维码
        'Admin@mp_bingwxgzhopenid', // 绑定微信公众号二维码
        'Admin@ajax_admin_wechat_login', // 后台管理插件（手机版）--微信公众号登录
        'Admin@get_admin_wechat_users', // 后台管理插件（手机版）--获取微信登录用户信息
    ),
    
    // 无需验证权限的操作
    'uneed_check_action' => array(
        'Base@*',
        'Index@*',
        'Ajax@*',
        'Ueditor@*',
        'Uploadify@*',
        'Uploadimgnew@*',
    ),
);

$html_config = include_once 'html.php';
return array_merge($admin_config, $html_config);
?>