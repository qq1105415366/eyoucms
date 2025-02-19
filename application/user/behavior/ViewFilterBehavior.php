<?php

namespace app\user\behavior;

/**
 * 系统行为扩展：
 */
class ViewFilterBehavior {
    protected static $actionName;
    protected static $controllerName;
    protected static $moduleName;
    protected static $method;

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
        $this->_initialize($params);
    }

    private function _initialize(&$params) {
        $this->eyGlobalJs($params); // 全局JS
        // 外贸助手的语言包
        model('ForeignPack')->appendForeignGlobalJs($params);
    }
    
    /**
     * 全局JS
     * @access public
     */
    private function eyGlobalJs(&$params)
    {
        $root_dir = ROOT_DIR;
        $version   = getCmsVersion();
        $srcurl = get_absolute_url("{$root_dir}/public/static/common/js/ey_footer.js?v={$version}");
        if (isMobile()) {
            $usersGlobalJs = "<script type='text/javascript' src='{$root_dir}/public/static/common/js/mobile_global.js?t={$version}'></script>";
        } else {
            $usersGlobalJs = "<script type='text/javascript' src='{$root_dir}/public/static/common/js/tag_global.js?t={$version}'></script>";
        }
        $JsHtml = <<<EOF
<script type="text/javascript" src="{$root_dir}/public/static/common/js/ey_global.js?t={$version}"></script>
{$usersGlobalJs}
<script type="text/javascript">var root_dir="{$root_dir}"; var ey_aid=0;</script>
<script type="text/javascript" src="{$srcurl}"></script>
EOF;
        // 追加替换JS
        $params = str_ireplace('</body>', htmlspecialchars_decode($JsHtml)."\n</body>", $params);
    }
}
