<?php

namespace app\home\behavior;

/**
 * 系统行为扩展：
 */
class ViewFilterBehavior {
    protected static $actionName;
    protected static $controllerName;
    protected static $moduleName;
    protected static $method;
    protected static $web_users_switch = null;

    /**
     * 构造方法
     * @param Request $request Request对象
     * @access public
     */
    public function __construct()
    {

    }

    // 行为扩展的执行入口必须是run
    public function run(&$params){
        self::$actionName = request()->action();
        self::$controllerName = request()->controller();
        self::$moduleName = request()->module();
        self::$method = request()->method();
        null === self::$web_users_switch && self::$web_users_switch = tpCache('web.web_users_switch');
        self::$web_users_switch = intval(self::$web_users_switch);
        $this->_initialize($params);
    }

    private function _initialize(&$params) {
        $this->eyGlobalJs($params); // 全局JS
        $this->thirdcode($params); // 自动加上第三方统计代码
        $this->AppEndJsCode($params); // 自动追加阅读权限JS事件
        $this->AppFootprintJsCode($params); //自动追加足迹js事件
        // 外贸助手的语言包
        model('ForeignPack')->appendForeignGlobalJs($params);
    }
    
    /**
     * 全局JS
     * @access public
     */
    private function eyGlobalJs(&$params)
    {
        if (!empty(self::$web_users_switch)) {
            $root_dir = ROOT_DIR;
            $version   = getCmsVersion();
            $srcurl = get_absolute_url("{$root_dir}/public/static/common/js/ey_global.js?v={$version}");
            $JsHtml = <<<EOF
<script type="text/javascript" src="{$srcurl}"></script>
EOF;
            // 追加替换JS
            $params = str_ireplace('</head>', htmlspecialchars_decode($JsHtml)."\n</head>", $params);
        }
    }

    /**
     * 给模板加上第三方统计代码
     * @access public
     */
    private function thirdcode(&$params)
    {
        // 排除小程序端，其他场景都显示统计代码和商桥代码
        if (!isWeixinApplets()) {
            if (function_exists('is_template_opt') && is_template_opt()) {
                $name = 'web_thirdcode_' . (isMobile() ? 'wap' : 'pc'); // PC端与手机端的变量名自适应，可彼此通用
                $web_thirdcode = tpCache('web.'.$name);
                if (!empty($web_thirdcode)) {
                    $params = str_ireplace('</body>', htmlspecialchars_decode($web_thirdcode)."\n</body>", $params);
                }
            }
        }
    }
    
    /**
     * 自动追加足迹js事件
     * @access public
     */
    private function AppFootprintJsCode(&$params)
    {
        $root_dir = ROOT_DIR;
        $version   = getCmsVersion();
        $web_users_switch = self::$web_users_switch;
        $aid = input('param.aid/d', 0);
        if (strstr($params, 'eyou_arcclick') || strstr($params, 'ey_v378141') || !empty($web_users_switch)) {
            $lang = get_home_lang();
            $lang_str = config('lang_switch_on') ? " var __lang__='{$lang}';" : '';
            $srcurl = get_absolute_url("{$root_dir}/public/static/common/js/ey_footer.js?v={$version}");
            $ey_footer_js = <<<EOF
<script type="text/javascript">var root_dir="{$root_dir}";var ey_aid={$aid};{$lang_str}</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
            $params = str_ireplace('</body>', $ey_footer_js."\n</body>", $params);
        }
    }

    /**
     * 自动追加阅读权限JS事件
     * @access public
     */
    private function AppEndJsCode(&$params)
    {
        $aid = request()->param('aid/d');
        $admin_id = request()->param('admin_id/d');
        $ca = self::$controllerName.'@'.self::$actionName;
        $is_appendJs = false;
        $data['ClosePage'] = 0;
        if (!empty($aid) && ('View@index' == $ca || 'view' == self::$actionName)) {
            if (!empty($admin_id)) {
                $data['ClosePage'] = 1;
            }
            $is_appendJs = true;
        } else if ('Buildhtml@uphtml' == $ca && 'view' == request()->param('type')) {
            $is_appendJs = true;
        } else if ('Buildhtml@buildarticle' == $ca) {
            $is_appendJs = true;
        }

        if (true === $is_appendJs) {
            // 加载JS需要的参数
            $archivesInfo = \think\Db::name('archives')->field('arcrank,channel,restric_type')->where(['aid'=>$aid])->find();
            if (-1 >= $archivesInfo['arcrank'] && !session('?users_id') && empty($admin_id)) {
                return true;
            }
            $get_url = ROOT_DIR . "/index.php?m=api&c=Ajax&a=get_arcrank&aid={$aid}";
            if (!empty($admin_id)) {
                $get_url .= "&admin_id={$admin_id}";
            }
            $data['get_url'] = $get_url;
            $data['buy_url'] = ROOT_DIR . "/index.php?m=user&c=Media&a=media_order_buy&_ajax=1";
            $data['VideoLogicUrl'] = ROOT_DIR . "/index.php?m=api&c=Ajax&a=video_logic&_ajax=1";
            $data['LevelCentreUrl'] = ROOT_DIR . "/index.php?m=user&c=Level&a=level_centre&aid=".$aid;
            $data['aid'] = $aid;
            $data_json = json_encode($data);
            $version   = getCmsVersion();
            $root_dir = ROOT_DIR;
            $srcurl = get_absolute_url("{$root_dir}/public/static/common/js/view_arcrank.js?v={$version}");
            $JsHtml = <<<EOF
<script type="text/javascript">var ey_1564127251 = {$data_json};</script>
<script type="text/javascript" src="{$srcurl}"></script>
EOF;
            if (5 == $archivesInfo['channel']) { // 只针对视频模型
                $type = 'sp2';
                if (strstr($params, 'VipFreeLearn20210201')) { // 易而优
                    $type = 'sp3';
                } else if (strstr($params, 'video-period-bottom')) {
                    $type = 'sp1';
                }
                $JsHtml2 = "<script type='text/javascript'>video_sp_1618221427 = '{$type}';</script>";
                $params = str_ireplace('</head>', $JsHtml2."\n</head>", $params);

                $JsHtml .= "<script type='text/javascript'>ey_1618221427('{$type}');</script>";
                $params = str_ireplace('</body>', htmlspecialchars_decode($JsHtml)."\n</body>", $params);
            } else {
                // if (!empty(self::$web_users_switch) && !empty($archivesInfo['arcrank'])) {
                //     $params = str_ireplace('</head>', htmlspecialchars_decode($JsHtml)."\n</head>", $params);
                // }
            }
        }
    }
}
