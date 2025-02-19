<?php

namespace app\admin\behavior;

use think\Config;

/**
 * 系统行为扩展：
 */
class ModuleInitBehavior {
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
        $this->_initialize();
    }

    private function _initialize() {
        if (file_exists('application/admin/logic/DdosLogic.php')) {
            $ddosLogic = new \app\admin\logic\DdosLogic;
            if (method_exists($ddosLogic, 'firewall_login_check')) {
                $ddosLogic->firewall_login_check();
            }
        }
        $this->vertifyCode();
    }

    /**
     * 登录 - 验证码
     * @param array $params 传入参数
     * @access public
     */
    private function vertifyCode()
    {
        /*只有相应的控制器和操作名才执行，以便提高性能*/
        $ctlActArr = array(
            'Admin@login',
            'Admin@vertify',
        );
        $ctlActStr = self::$controllerName.'@'.self::$actionName;
        if (in_array($ctlActStr, $ctlActArr)) {
            $row = tpSetting('system.system_vertify');
            // 获取验证码配置信息
            $row = json_decode($row, true);
            $baseConfig = Config::get("captcha");
            if (!empty($row)) {
                foreach ($row['captcha'] as $key => $val) {
                    if ('default' == $key) {
                        $baseConfig[$key] = array_merge($baseConfig[$key], $val);
                    } else {
                        $baseConfig[$key]['is_on'] = $val['is_on'];
                        $baseConfig[$key]['config'] = array_merge($baseConfig['default'], $val['config']);
                    }
                }
            }

            Config::set('captcha', $baseConfig);
        }
    }
}
