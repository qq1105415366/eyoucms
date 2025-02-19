<?php

namespace app\common\behavior;

class AppInitBehavior {
    protected static $method;

    public function __construct()
    {

    }

    // 行为扩展的执行入口必须是run
    public function run(&$params){
        self::$method = request()->method();
        $this->_initialize();
    }

    private function _initialize() {
        $this->saveSqlmode();
    }

    /**
     * 保存mysql的sql-mode模式参数
     */
    private function saveSqlmode(){
        /*在后台模块才执行，以便提高性能*/
        if (!stristr(request()->baseFile(), 'index.php')) {
            if ('GET' == self::$method) {
                $key = 'isset_saveSqlmode';
                $sessvalue = session($key);
                if(!empty($sessvalue))
                    return true;
                session($key, 1);

                if (config('database.type') == 'dm') { // 达梦优化
                    $system_sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
                } else {
                    $sql_mode = \think\Db::query("SELECT @@global.sql_mode AS sql_mode");
                    $system_sql_mode = isset($sql_mode[0]['sql_mode']) ? $sql_mode[0]['sql_mode'] : '';
                }
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('system', ['system_sql_mode'=>$system_sql_mode], $val['mark']);
                    }
                } else {
                    tpCache('system', ['system_sql_mode'=>$system_sql_mode]);
                }
                /*--end*/
            }
        }
        /*--end*/
    }
}
