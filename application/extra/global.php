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

$cacheKey = "extra_global_channeltype";
$channeltype_row = \think\Cache::get($cacheKey);
if (empty($channeltype_row)) {
    $channeltype_row = \think\Db::name('channeltype')->field('id,nid,ctl_name,title,ntitle,ifsystem,table,status')
        ->order('id asc')
        ->getAllWithIndex('id');
    \think\Cache::set($cacheKey, $channeltype_row, EYOUCMS_CACHE_TIME, "channeltype");
}

$channeltype_list = []; // 模型标识
$allow_release_channel = []; // 发布文档的模型ID
foreach ($channeltype_row as $key => $val) {
    $channeltype_list[$val['nid']] = $val['id'];
    if (!in_array($val['nid'], ['guestbook','single','ask'])) {
        array_push($allow_release_channel, $val['id']);
    }
}

// URL全局参数（比如：可视化uiset、多模板v、多语言lang）
$parse_url_param = [];
if (file_exists(ROOT_PATH.'template/pc/uiset.txt') || file_exists(ROOT_PATH.'template/mobile/uiset.txt')) {
    $parse_url_param[] = 'uiset';
    $parse_url_param[] = 'v';
} else {
    $uisetArr = @glob('template/*/*/uiset.txt');
    if (!empty($uisetArr)) {
        $parse_url_param[] = 'uiset';
        $parse_url_param[] = 'v';
    }
}
$lang_switch_on = \think\Config::get('lang_switch_on');
$lang_switch_on == true && $parse_url_param[] = 'lang';
$parse_url_param[] = 'goto';
$parse_url_param[] = 'site';

return array(
    // 小虎哥 
    'upgrade_dev'   => 0,
    // 特定场景专用
    'opencodetype'  => 0,
    // 模板引擎禁用函数
    'tpl_deny_func_list' => 'phpinfo,eval,exit,exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,file_put_contents,fsockopen,fopen,fwrite',
    // CMS根目录文件夹
    'wwwroot_dir' => ['application','core','data','extend','install','public','template','uploads','vendor','weapp'],
    // 禁用栏目的目录名称
    'disable_dirname' => ['application','core','data','extend','install','public','plugins','uploads','template','vendor','weapp','tags','search','user','users','member','reg','centre','login','cart'],
    // 发送邮箱默认有效时间，会员中心，邮箱验证时用到
    'email_default_time_out' => 3600,
    // 邮箱发送倒计时 2分钟
    'email_send_time' => 120,
    // 发送短信默认有效时间
    'mobile_default_time_out' => 1800,
    // 手机发送倒计时 2分钟 
    'mobile_send_time' => 120,
    // 充值订单默认有效时间，会员中心用到，2小时
    'get_order_validity' => 7200,
    // 支付订单默认有效时间，商城中心用到，2小时
    'get_shop_order_validity' => 7200,
    // 文档SEO描述截取长度，一个汉字等于两个字符，其余的等于一个字符
    'arc_seo_description_length' => 80,
    // 栏目最多级别
    'arctype_max_level' => 10,
    // 模型标识
    'channeltype_list' => $channeltype_list,
    // 发布文档的模型ID
    'allow_release_channel' => $allow_release_channel,
    // 广告类型
    'ad_media_type' => array(
        1   => '图片',
        // 2   => 'flash',
        // 3   => '文字',
    ),
    // 仅用于产品参数
    'attr_input_type_arr' => array(
        0   => '单行文本',
        2   => '多行文本',
        1   => '下拉框',
    ),
    // 仅用于留言属性
    'guestbook_attr_input_type' => array(
        0   => '单行文本',
        2   => '多行文本',
        1   => '下拉框',
        3   => '单选框',
        4   => '多选框',
        5   => '单张图',
        6   => '手机号码',
        7   => 'Email邮箱',
        8   => '附件类型',
        9   => '区域联动',
        10  => '时间类型',
        11  => '多张图',
    ),
    //留言属性正则规则管理（仅用于留言属性）
    'validate_type_list' => [
        6 => [
            'name' => '手机号码',
            'value' => '/^1\d{10}$/'
        ],
        7 => [
            'name' => 'Email邮箱',
            'value' => '/^(.+)@([^\@]+)\.([^\.]+)$/'
        ],
    ],
    //表单类型列表
    "form_field_type_list" => [
        'text'      => '单行文本',
        'radio'     => '单选项',
        'select'    => '下拉框',
        'checkbox'  => '多选项',
        'multitext' => '多行文本',
        'datetime'  => '日期时间',
        'img'   => '单张图',
        'mobile'   => '手机号码',
        'email'   => 'Email邮箱',
        'file'   => '附件类型',
        'region'   => '区域联动',
    ],
    //表单属性正则
    'form_field_grep_list' => [
        'mobile' => [
            'name' => '手机号码',
            'value' => '/^1\d{10}$/'
        ],
        'email' => [
            'name' => 'Email邮箱',
            'value' => '/^(.+)@([^\@]+)\.([^\.]+)$/'
        ],
    ],
    // 栏目自定义字段的channel_id值
    'arctype_channel_id' => -99,
    // 栏目表内置字段
    'arctype_table_fields' => array('id','channeltype','current_channel','parent_id','topid','typename','dirname','dirpath','diy_dirpath','rulelist','ruleview','englist_name','grade','typelink','litpic','templist','tempview','seo_title','seo_keywords','seo_description','sort_order','is_hidden','is_part','admin_id','is_del','del_method','status','is_release','weapp_code','lang','add_time','update_time','target','nofollow','typearcrank','empty_logic','page_limit','total_arc'),
    // 网络图片扩展名
    'image_ext' => 'jpg,jpeg,gif,bmp,ico,png,webp', // svg
    // 网络多媒体扩展名
    'media_ext' => 'ra,ram,vqf,wma,mp3,mid,cd,wave,aiff,au,mpeg-4,midi,vqf,amr,wav,mp4,swf,mov,rm,dat,mpeg,mpg,avi,wmv,rmvb,mp4,asf,3gp,mkv,flv,f4v,webm,ogg,ogv,m4a,m3u8',
    // 后台语言Cookie变量
    'admin_lang' => 'admin_lang',
    // 前台语言Cookie变量
    'home_lang' => 'home_lang',
    // 多城市最多级别
    'citysite_max_level' => 3,
    // URL全局参数（比如：可视化uiset、多模板v、多语言lang）
    'parse_url_param'   => $parse_url_param,
    // 会员金额明细类型
    'pay_cause_type_arr' => array(
        0   => '升级消费',
        1   => '账户充值',
        2   => '订单退款',
        3   => '订单支付',
        4   => '后台操作', // 添加
        5   => '后台操作', // 减少
        6   => '问答悬赏',
        7   => '问答获得悬赏',
        8   => '分销商提现',
        9   => '抽奖',
        // 10   => '后续添加',
    ),
    // 充值状态
    'pay_status_arr' => array(
        // 0   => '失败',
        1   => '未付款',
        2   => '已完成',
        3   => '已充值',
        4   => '订单取消',
        // 5   => '后续添加',
    ),
    // 支付方式
    'pay_method_arr' => array(
        'wechat'     => '微信支付',
        'alipay'     => '支付宝支付',
        'artificial' => '手工充值',
        'balance'    => '余额支付',
        'admin_pay'  => '后台支付',
        'delivery_pay' => '货到付款',
        'Paypal'     => 'paypal支付',
        'UnionPay'   => '银联支付',
        'noNeedPay'  => '无需支付',
        'tikTokPay'  => '抖音支付',
        'baiduPay'   => '百度支付',
        'Hupijiaopay' => '虎皮椒支付',
        'PersonPay' => '支付宝',
        'aliPay'     => '支付宝支付',
    ),
    // 缩略图默认宽高度
    'thumb' => [
        'open'  => 0,
        'mode'  => 2,
        'color' => '#FFFFFF',
        'width' => 300,
        'height' => 300,
    ],
    // 订单状态
    'order_status_arr' => array(
        -1  => '已关闭',
        0   => '待付款',
        1   => '待发货',
        2   => '待收货',
        3   => '已完成',
        4   => '订单过期',
        // 5   => '后续添加',
    ),
    // 订单状态，后台使用
    'admin_order_status_arr' => array(
        -1  => '订单关闭',
        0   => '未付款',
        1   => '待发货',
        2   => '已发货',
        3   => '已完成',
        4   => '订单过期',
    ),
    // 特殊地区(中国四个省直辖市)，目前在自定义字段控制器中使用
    'field_region_type' => ['1','338','10543','31929'],
    // 选择指定区域ID处理其他操作，目前在自定义字段控制器中使用
    'field_region_all_type' => ['-1','0','1','338','10543','31929'],
    // URL中筛选标识变量
    'url_screen_var' => 'ZXljbXM',
    //百度地图ak值
    'baidu_map_ak'  => 'RVRMWGdDeElvVml4Z2dIY0FrNm1LcE1k',
    // 提示
    'authori_tips' => '5LuF6ZmQ5LqO5LiT5Lia54mI5ZWG5Lia5o6I5p2D5L2/55So77yB',
    // 会员投稿发布的文章状态，前台使用
    'home_article_arcrank' => array(
        -1  => '未审核',
        0   => '审核通过',
    ),
    // 二次安全验证的问题列表
    'security_askanswer_list' => [
        '您常用的手机号码是？',
        '您常用的电子邮箱是？',
        '您真实的姓名是？',
        '您初中学校名是？',
        '您的出生地名是？',
        '您配偶的姓名是？',
        '您的身份证号后八位是？',
        '您高中班主任的名字是？',
        '您初中班主任的名字是？',
        '您最喜欢的明星名字是？',
        '对您影响最大的人名字是？',
    ],
    // 会员期限，后台使用
    'admin_member_limit_arr' => array(
        1 => array(
            'limit_id'   => 1,
            'limit_name' => '一周',
            'maturity_days'  => 7,
        ),
        2 => array(
            'limit_id'   => 2,
            'limit_name' => '一个月',
            'maturity_days'  => 30,
        ),
        3 => array(
            'limit_id'   => 3,
            'limit_name' => '三个月',
            'maturity_days'  => 90,
        ),
        4 => array(
            'limit_id'   => 4,
            'limit_name' => '半年',
            'maturity_days'  => 183,
        ),
        5 => array(
            'limit_id'   => 5,
            'limit_name' => '一年',
            'maturity_days'  => 366,
        ),
        6 => array(
            'limit_id'   => 6,
            'limit_name' => '终身',
            'maturity_days'  => 36600,
        ),
    ),
    // 清理文件时，需要查询的数据表和字段
    'get_tablearray' => array(
        0 => array(
            'table' => 'ad',
            'field' => 'litpic',
        ),
        1 => array(
            'table' => 'archives',
            'field' => 'litpic',
        ),
        2 => array(
            'table' => 'arctype',
            'field' => 'litpic',
        ),
        3 => array(
            'table' => 'images_upload',
            'field' => 'image_url',
        ),
        4 => array(
            'table' => 'links',
            'field' => 'logo',
        ),
        5 => array(
            'table' => 'product_img',
            'field' => 'image_url',
        ),
        6 => array(
            'table' => 'ad',
            'field' => 'intro',
        ),
        7 => array(
            'table' => 'article_content',
            'field' => 'content',
        ),
        8 => array(
            'table' => 'download_content',
            'field' => 'content',
        ),
        9 => array(
            'table' => 'images_content',
            'field' => 'content',
        ),
        10 => array(
            'table' => 'product_content',
            'field' => 'content',
        ),
        11 => array(
            'table' => 'single_content',
            'field' => 'content',
        ),
        12 => array(
            'table' => 'config',
            'field' => 'value',
        ),
        13 => array(
            'table' => 'ui_config',
            'field' => 'value',
        ),
        14 => array(
            'table' => 'download_file',
            'field' => 'file_url',
        ),
        15 => array(
            'table' => 'users',
            'field' => 'head_pic',
        ),
        16 => array(
            'table' => 'shop_order_details',
            'field' => 'litpic',
        ),
        17 => array(
            'table' => 'admin',
            'field' => 'head_pic',
        ),
        18 => array(
            'table' => 'media_file',
            'field' => 'file_url',
        ),
        // 后续可持续添加数据表和字段，格式参照以上
    ),

    // 足迹记录条数限制 20
    'user_footprint_limit' => 20,

    // 手机端会员中心底部菜单配置选项
    'mobile_user_bottom_menu_config' => array(
        1 => array(
            'id'   => 1,
            'title' => '首页',
            'mca'  => 'home/Index/index',
            'icon'  => 'shouye',
        ),
        2 => array(
            'id'   => 2,
            'title' => '消息',
            'mca'  => 'user/UsersNotice/index',
            'icon'  => 'xinxi',
        ),
        3 => array(
            'id'   => 3,
            'title' => '会员升级',
            'mca'  => 'user/Level/level_centre',
            'icon'  => 'huiyuanshengji',
        ),
        4 => array(
            'id'   => 4,
            'title' => '账户充值',
            'mca'  => 'user/Pay/pay_account_recharge',
            'icon'  => 'yue',
        ),
        5 => array(
            'id'   => 5,
            'title' => '订单',
            'mca'  => 'user/Shop/shop_centre',
            'icon'  => 'dingdan',
        ),
        6 => array(
            'id'   => 6,
            'title' => '购物车',
            'mca'  => 'user/Shop/shop_cart_list',
            'icon'  => 'shopping-cart-full',
        ),
        7 => array(
            'id'   => 7,
            'title' => '发布',
            'mca'  => 'user/UsersRelease/article_add',
            'icon'  => 'fabu',
        ),
        8 => array(
            'id'   => 8,
            'title' => '下载',
            'mca'  => 'user/Download/index',
            'icon'  => 'xiazai',
        ),
        9 => array(
            'id'   => 9,
            'title' => '收藏',
            'mca'  => 'user/Users/collection_index',
            'icon'  => 'shoucang',
        ),
        10 => array(
            'id'   => 10,
            'title' => '我的',
            'mca'  => 'user/Users/centre',
            'icon'  => 'geren',
        ),
        11 => array(
            'id'   => 11,
            'title' => '自定义',
            'custom' => 1,
            'mca'  => '',
            'icon'  => 'xingxing',
        ),
    ),
    // 前台PC会员中心左侧菜单配置选项
    'pc_user_left_menu_config' => array(
        1 => array(
            'id'   => 1,
            'title' => '个人中心',
            'mca'  => 'user/Users/index',
            'active_url'  => 'user/Users/index',
        ),
        2 => array(
            'id'   => 2,
            'title' => '我的信息',
            'mca'  => 'user/Users/info',
            'active_url'  => 'user/Users/info',
        ),
        3 => array(
            'id'   => 3,
            'title' => '我的收藏',
            'mca'  => 'user/Users/collection_index',
            'active_url'  => 'user/Users/collection_index',
        ),
        4 => array(
            'id'   => 4,
            'title' => '财务明细',
            'mca'  => 'user/Pay/pay_consumer_details',
            'active_url'  => 'user/Pay/pay_consumer_details|user/Users/score_index',
        ),
        5 => array(
            'id'   => 5,
            'title' => '账户充值',
            'mca'  => 'user/Pay/pay_account_recharge',
            'active_url'  => 'user/Pay/pay_account_recharge',
        ),
        6 => array(
            'id'   => 6,
            'title' => '会员升级',
            'mca'  => 'user/Level/level_centre',
            'active_url'  => 'user/Level/level_centre',
        ),
        7 => array(
            'id'   => 7,
            'title' => '自定义',
            'mca'  => '',
        ),
    ),

    // 订单退换货服务状态 -- 陈风任
    'order_service_status' => array(
        1 => '审核中',
        2 => '审核通过',
        3 => '审核不通过',
        4 => '会员已发货',
        5 => '商家已收货',
        6 => '换货完成',
        7 => '退款完成',
        8 => '已关闭'
    ),
    // 订单退换货服务类型 -- 陈风任
    'order_service_type' => array(
        1 => '换货',
        2 => '退货',
        3 => '维修'
    ),
    // 商品评价评分 -- 陈风任
    'order_total_score' => array(
        1 => '差评',
        2 => '差评',
        3 => '中评',
        4 => '好评',
        5 => '好评'
    ),
    // 用户限制模式
    'users_lock_model' => [
        0   => [
            'name'  => '正常用户',
            'msg'   => '正常用户',
        ],
        -1   => [
            'name'  => '禁止发言',
            'msg'   => '禁止操作，你已被禁止发言！',
        ],
        -99   => [
            'name'  => '永久黑名单',
            'msg'   => '禁止操作，你已被加入黑名单！',
        ],
    ],
);
