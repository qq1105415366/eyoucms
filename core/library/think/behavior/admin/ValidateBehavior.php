<?php

namespace think\behavior\admin;

/**
 * 系统行为扩展：校验
 */
class ValidateBehavior {
    protected static $actionName;
    protected static $controllerName;
    protected static $moduleName;

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
        if ('GET' == request()->method()) {
            // 初始化
            $this->_initialize();
        }
    }

    protected function _initialize() {
        $this->corecall('Y29kZV92YWxpZGF0ZQ==');
    }

    /**
     * @access protected
     */
    protected function corecall($str = '') {
        $var = base64_decode($str);
        $var();
    }
}
