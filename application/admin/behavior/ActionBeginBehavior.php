<?php

namespace app\admin\behavior;

use think\Db;

/**
 * 系统行为扩展：新增/更新/删除之后的后置操作
 */
load_trait('controller/Jump');
class ActionBeginBehavior {
    use \traits\controller\Jump;
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
        $this->security_verify();
        if ('POST' == self::$method) {
            $this->clearWeapp();
            $this->instyes();
            $this->authori5();
        } else {
            $this->useWeapp();
            $this->unotice();
            $this->verifyfile();
        }
        $this->language_access();
    }

    private function useWeapp()
    {
        if (!request()->isAjax()) {
            $sa = input('param.sa/s');
            if ('Weapp@index' == self::$controllerName.'@'.self::$actionName) {
                $weapp_index_gourl = cookie('admin-weapp_index_gourl');
                if (!empty($weapp_index_gourl)) {
                    $this->redirect($weapp_index_gourl);
                }
            } else if ('Weapp@execute' != self::$controllerName.'@'.self::$actionName) {
                cookie('admin-weapp_index_gourl', null);
            }
        }
    }

    /**
     * 多语言功能操作权限
     * @return [type] [description]
     */
    private function language_access()
    {
        $controllerArr = ['Weapp','Filemanager','Sitemap','Member','Seo','Channeltype','Tools'];
        $ctlActArr = ['Admin@index','Admin@add','Admin@del','System@water','System@thumb','System@api_conf'];
        if (in_array(self::$controllerName, $controllerArr) || in_array(self::$controllerName.'@'.self::$actionName, $ctlActArr)) {
            $main_lang = get_main_lang();
            $admin_lang = get_admin_lang();
            if (is_language() && $main_lang != $admin_lang) {
                $lang_title = model('Language')->where('mark',$main_lang)->value('title');
                $this->error('当前语言没有此功能，请切换到【'.$lang_title.'】语言');
            }
        }
    }

    private function security_verify()
    {
        $ctl_act = self::$controllerName.'@'.self::$actionName;
        $ctl_act_arr = ['Arctype@ajax_newtpl','Archives@ajax_newtpl','Index@ajax_theme_tplfile_add','Index@ajax_theme_tplfile_edit'];
        if (in_array(self::$controllerName, ['Filemanager', 'Weapp']) || in_array($ctl_act, $ctl_act_arr)) {
            $security = tpSetting('security');

            /*---------强制必须开启密保问题认证 start----------*/
            if (in_array(self::$controllerName, ['Filemanager']) || in_array($ctl_act, $ctl_act_arr)) {
                if (empty($security['security_ask_open'])) {
                    $this->error("<span style='display:none;'>__html__</span>需要开启密保问题设置", url('Security/index'), '', 3);
                }
            }
            /*---------强制必须开启密保问题认证 end----------*/

            $nosubmit = input('param.nosubmit/d');
            if ('POST' == self::$method && empty($nosubmit)) {
                if (empty($security['security_ask_open']) || !security_verify_func($ctl_act)) {
                    return true;
                }
                $admin_id = session('?admin_id') ? (int)session('admin_id') : 0;
                $admin_info = Db::name('admin')->field('admin_id,last_ip')->where(['admin_id'=>$admin_id])->find();
                // 当前管理员密保问题验证过的IP地址
                $security_answerverify_ip = !empty($security['security_answerverify_ip']) ? $security['security_answerverify_ip'] : '-1';
                // 同IP不验证
                if ($admin_info['last_ip'] == $security_answerverify_ip) {
                    return true;
                }

                $this->error("<span style='display:none;'>__html__</span>出于安全考虑<br/>请勿非法越过密保答案验证", null, '', 3);
            }
        }
    }

    private function verifyfile()
    {
        $tmp1 = 'cGhwLnBocF9zZXJ2aW'.'NlaW5mbw==';
        $tmp1 = base64_decode($tmp1);
        $data = tpCache($tmp1);
        $data = mchStrCode($data, 'DECODE');
        $data = json_decode($data, true);
        if (empty($data['pid']) || 2 > $data['pid']) return true;
        $file = "./data/conf/{$data['code']}.txt";
        $tmp2 = 'cGhwX3NlcnZpY2VtZWFs';
        $tmp2 = base64_decode($tmp2);
        $iseyKey = binaryJoinChar(config('binary.9'), 20);
        $iseyKey = msubstr($iseyKey, 1, strlen($iseyKey) - 2);
        if (!file_exists($file)) {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$tmp2=>1], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$tmp2=>1]);
                tpCache('web', [$iseyKey=>0]);
            }
            /*--end*/
        } else {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$tmp2=>$data['pid']], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$tmp2=>$data['pid']]);
                tpCache('web', [$iseyKey=>0]);
            }
            /*--end*/
        }
    }

    private function unotice()
    {
        $str = 'VXNlcnNOb3RpY2U=';
        if (self::$controllerName == base64_decode($str)) {
            $functionLogic = new \app\common\logic\FunctionLogic;
            $functionLogic->validate_authorfile(1);
        }
    }

    /**
     * 插件每次post提交都清除插件相关缓存
     * @access private
     */
    private function clearWeapp()
    {
        /*只有相应的控制器和操作名才执行，以便提高性能*/
        $ctlActArr = array(
            'Weapp@*',
        );
        $ctlActStr = self::$controllerName.'@*';
        if (in_array($ctlActStr, $ctlActArr)) {
            \think\Cache::clear('hooks');
        }
        /*--end*/
    }

    private function authori5()
    {
        $smc_arr = [];
        $smc_arr[] = array_join_string(array('QWl','AQ','WlB','cm','Noa','XZl','cw=','='));
        $smc_arr[] = array_join_string(array('QW','lA','QW','lEc','mF3','aW','5n'));
        $smc_arr[] = array_join_string(array('Q','Wl','A','Q','WlG','YW5','5a','Q=','='));
        $sm = input('param.sm/s');
        $sc = input('param.sc/s');
        if (in_array($sm.'@'.$sc, $smc_arr)) {
            $functionLogic = new \app\common\logic\FunctionLogic;
            $functionLogic->validate_authorfile(5);
        }
    }

    /**
     * @access private
     */
    private function instyes()
    {
        $ca = md5(self::$actionName.'@'.self::$controllerName);
        if ('0e3e00da04fcf78cd9fd7dc763d956fc' == $ca) {
            $s = '5a6J'.'6KOF'.'5oiQ5'.'Yqf';
            if (1605110400 < getTime()) {
                sleep(5);
                $this->success(base64_decode($s));
            }
        }
    }
}
