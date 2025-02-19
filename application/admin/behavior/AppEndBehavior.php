<?php

namespace app\admin\behavior;

use think\Db;

/**
 * 系统行为扩展：
 */
class AppEndBehavior {
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
        $this->resetAuthor(); // 临时处理授权问题
        $this->clearHtmlCache(); // 变动数据之后，清除页面缓存和数据
        $this->eyou_statistics_data(); // 商城主题欢迎页的数据统计
    }

    /**
     * 临时处理授权问题
     */
    private function resetAuthor()
    {
        /*在以下相应的控制器和操作名里执行，以便提高性能*/
        $ctlActArr = array(
            'Index@index',
        );
        $ctlActStr = self::$controllerName.'@'.self::$actionName;
        if (in_array($ctlActStr, $ctlActArr) && 'GET' == self::$method) {
            if(!empty($_SESSION['isset_resetAuthor']))
                return true;
            $_SESSION['isset_resetAuthor'] = 1;

            session('isset_author', null);
        }
        /*--end*/
    }

    /**
     * 数据变动之后，清理页面和数据缓存
     */
    private function clearHtmlCache()
    {
        /*在以下相应的控制器和操作名里执行，以便提高性能*/
        $actArr = ['add','edit','del','recovery','changeTableVal'];
        if ('POST' == self::$method) {
            foreach ($actArr as $key => $val) {
                if (preg_match('/^((.*)_)?('.$val.')$/i', self::$actionName) || preg_match('/^(ajax_)?'.$val.'(_(.*))?$/i', self::$actionName)) {
                    $aids = [];
                    if (!empty($_POST['aids'])) {
                        $aids = $_POST['aids'];
                    } else if (!empty($_POST['aid'])) {
                        $aids = [$_POST['aid']];
                    }

                    $typeids = [];
                    if (!empty($_POST['typeids'])) {
                        $typeids = $_POST['typeids'];
                    } else if (!empty($_POST['typeid'])) {
                        $typeids = [$_POST['typeid']];
                    }
                    clearHtmlCache($aids, $typeids);
                    // \think\Cache::clear();
                    // delFile(HTML_ROOT.'index');
                    break;
                }
            }
        }
    }

    /**
     * 商城主题欢迎页的数据统计写入
     * @return [type] [description]
     */
    private function eyou_statistics_data()
    {
        if ('POST' == self::$method) {
            if (in_array(self::$controllerName, ['Product','ShopProduct','Article']) && in_array(self::$actionName, ['add'])) { // 新增商品
                if (in_array(self::$controllerName, ['Article'])) {
                    eyou_statistics_data(7);
                } else if (in_array(self::$controllerName, ['Product','ShopProduct'])) {
                    eyou_statistics_data(6);
                }
            } else if (in_array(self::$controllerName, ['RecycleBin']) && in_array(self::$actionName, ['archives_recovery'])) { // 恢复商品
                //查一下删除的商品里有没有昨天和今天发布的 要在统计里减去
                $rec_aid = is_array($_POST) ? $_POST : [$_POST['del_id']];
                if (!empty($rec_aid)) {
                    $ystd_count = $td_count = 0;
                    $today = strtotime(date('Y-m-d'));
                    $yesterday = $today - 86400;
                    $where = [
                        'aid' => ['IN', $rec_aid],
                        'add_time' => ['egt', $yesterday],
                    ];
                    $list = Db::name('archives')->field('aid,add_time')->where($where)->select();
                    foreach ($list as $key => $val) {
                        if ($val['add_time'] < $today) { // 昨天统计
                            $ystd_count++;
                        } else if ($val['add_time'] >= $today) { // 今天统计
                            $td_count++;
                        }
                    }
                    if (in_array(self::$controllerName, ['Article'])) {
                        $this->del_statistics(7,$td_count,$ystd_count,'inc');
                    } else if (in_array(self::$controllerName, ['Product','ShopProduct'])) {
                        $this->del_statistics(6,$td_count,$ystd_count,'inc');
                    }
                }
            }
        }
    }

    /**
     * @param string $action inc 增加 dec 减少
     */
    private function del_statistics($type, $td_count = 0,$ystd_count = 0,$action = 'inc'){
        //写入统计 减去
        if ($td_count > 0){
            eyou_statistics_data($type,$td_count,'',$action);//今天的
        }
        if ($ystd_count > 0){
            $ystd = strtotime('-1 day');
            eyou_statistics_data($type,$ystd_count,$ystd,$action);//昨天的
        }
    }
}
