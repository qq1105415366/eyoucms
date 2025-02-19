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

namespace app\admin\controller;

use think\Db;
use think\Cache;
use think\Page;
use app\admin\logic\DdosLogic;

class Security extends Base
{
    public $admin_info = array();
    public $admin_id = 0;
    public $ddosLogic;

    /**
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        $this->admin_info = session('admin_info');
        $this->admin_id = empty($this->admin_info) ? 0 : $this->admin_info['admin_id'];
        $this->ddosLogic = new DdosLogic;
    }

    public function index()
    {
        // 重置扫描范围
        $setdata = [
            'ddos_scan_range_files' => 1,
            'ddos_scan_range_attachment' => 0,
            'ddos_scan_range_uploads' => 0,
            'ddos_scan_is_finish' => 0,
            'ddos_scan_allscantotal' => 0,
        ];
        tpSetting('ddos', $setdata, 'cn');
        // 重置ddos_log表
        $this->ddosLogic->ddos_log_reset();
        $this->redirect(url('Security/ddos_kill', [], true, true));
        exit;

        // if (IS_POST) {
        //     $this->handleSave();
        // }

        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        //自定义后台路径名
        $baseFile = explode('/', $this->request->baseFile());
        $web_adminbasefile = end($baseFile);
        $adminbasefile = preg_replace('/^(.*)\.([^\.]+)$/i', '$1', $web_adminbasefile);
        $this->assign('adminbasefile', $adminbasefile);

        // 安全验证配置
        $security = tpSetting('security');
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 保存 - 后台安全中心
     * @return [type] [description]
     */
    public function handleSave1()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_expiretime' => $post['web_login_expiretime'],
                'login_expiretime_old' => $post['login_expiretime_old'],
                'web_login_lockopen'    => !empty($post['web_login_lockopen']) ? 1 : 0,
                // 'web_sqldatapath' => $post['web_sqldatapath'],
            ];
            // 开启锁定才修改相应的配置值
            if (!empty($param['web_login_lockopen'])) {
                $param['web_login_errtotal'] = $post['web_login_errtotal'];
                $param['web_login_errexpire'] = $post['web_login_errexpire'];
            }

            // 自定义后台路径名
            $adminbasefile = preg_replace('/([^\w\_\-])/i', '', trim($post['adminbasefile'])).'.php'; // 新的文件名
            $param['web_adminbasefile'] = $this->root_dir.'/'.$adminbasefile; // 支持子目录
            $baseFile = explode('/', $this->request->baseFile());
            $adminbasefile_old = end($baseFile); // 旧的文件名
            if ('index.php' == $adminbasefile) {
                $this->error("后台路径禁止使用index", null, '', 1);
            }

            // 数据库备份目录
            /*$web_sqldatapath_old = tpCache('global.web_sqldatapath');
            $param['web_sqldatapath'] = '/'.trim($param['web_sqldatapath'], '/');*/

            // 后台登录超时
            $web_login_expiretime = $param['web_login_expiretime'];
            $login_expiretime_old = $param['login_expiretime_old'];
            unset($param['login_expiretime_old']);
            if ($login_expiretime_old != $web_login_expiretime) {
                $web_login_expiretime = preg_replace('/^(\d{0,})(.*)$/i', '${1}', $web_login_expiretime);
                empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
                if ($web_login_expiretime > 2592000) {
                    $web_login_expiretime = 2592000; // 最多一个月
                }
                $param['web_login_expiretime'] = $web_login_expiretime;
                //前台登录超时时间
                $users_login_expiretime = getUsersConfigData('users.users_login_expiretime');
                //前台和后台谁设置的时间大就用谁的做session过期时间
                $max_login_expiretime = $web_login_expiretime;
                if ($web_login_expiretime < $users_login_expiretime){
                    $max_login_expiretime = $users_login_expiretime;
                }
            }
            // 编辑器防注入
            $param['web_xss_filter'] = intval($post['web_xss_filter']);
            // 网站防止被刷
            $param['web_anti_brushing'] = intval($post['web_anti_brushing']);
            // 存储文件
            $this->setWebXssFilter(['web_xss_filter'=>$param['web_xss_filter'], 'web_anti_brushing'=>$param['web_anti_brushing']]);
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $refresh = false;

            /*-------------------后台安全配置 start-------------------*/
            // 更改session会员设置 - session有效期（后台登录超时）
            if ($login_expiretime_old != $web_login_expiretime) {
                $session_conf = [];
                $session_file = APP_PATH.'admin/conf/session_conf.php';
                if (file_exists($session_file)) {
                    require_once($session_file);
                    $session_conf_tmp = EY_SESSION_CONF;
                    if (!empty($session_conf_tmp)) {
                        $session_conf_tmp = json_decode($session_conf_tmp, true);
                        if (!empty($session_conf_tmp) && is_array($session_conf_tmp)) {
                            $session_conf = $session_conf_tmp;
                        }
                    }
                }
                $session_conf['expire'] = $max_login_expiretime;
                $str_session_conf = '<?php'.PHP_EOL.'$session_1600593464 = json_encode('.var_export($session_conf,true).');'.PHP_EOL.'define(\'EY_SESSION_CONF\', $session_1600593464);';
                @file_put_contents(APP_PATH . 'admin/conf/session_conf.php', $str_session_conf);
            }

            // 更改自定义后台路径名 - 刷新整个后台
            $gourl = request()->domain().$this->root_dir.'/'.$adminbasefile; // 支持子目录
            if ($adminbasefile_old != $adminbasefile && eyPreventShell($adminbasefile_old)) {
                if (file_exists($adminbasefile_old)) {
                    if(rename($adminbasefile_old, $adminbasefile)) {
                        $refresh = true;
                    }
                } else {
                    $this->error("根目录{$adminbasefile_old}文件不存在！", null, '', 2);
                }
            }
            /*if ($web_sqldatapath_old != $param['web_sqldatapath'] && preg_match('/^\/data\/sqldata([^\/]*)$/i', $param['web_sqldatapath'])) {
                @rename(ROOT_PATH.ltrim($web_sqldatapath_old, '/'), ROOT_PATH.ltrim($param['web_sqldatapath'], '/'));
            }*/
            /*-------------------后台安全配置 end-------------------*/

            if ($refresh) {
                $this->success('操作成功', $gourl, '', 1, [], '_parent');
            }

            $this->success('操作成功', url('Security/index'));
        }
        $this->error('操作失败');
    }

    /**
     * 编辑器防注入是否开启与关闭
     */
    private function setWebXssFilter($data = [])
    {
        $content = json_encode($data);
        $tfile = webXssKeyFile();
        $fp = @fopen($tfile,'w');
        if(!$fp) {
            @file_put_contents($tfile, $content);
        }
        else {
            fwrite($fp, $content);
            fclose($fp);
        }
    }

    /**
     * 保存 - 安全验证中心
     * @return [type] [description]
     */
    public function handleSave2()
    {
        if (IS_POST) {
            $settingData = [];
            $post = input('post.');

            if (empty($post['security_ask_open'])) {
                $securityOld = tpSetting('security');
                if (!empty($securityOld['security_ask'])) {
                    $answer = empty($post['security_answer_old']) ? '' : trim($post['security_answer_old']);
                    if (empty($answer)) {
                        $this->error('请录入密保答案！');
                    } else {
                        $security_answer = empty($securityOld['security_answer']) ? '' : trim($securityOld['security_answer']);
                        $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                        if ($security_answer != $encrypt_answer) {
                            $this->error('密保答案不正确！');
                        }
                    }
                    $this->submit_answer_verify();
                }
            }

            /*-------------------二次安全验证 start-------------------*/
            $this->handleAskData($settingData, $post);
            /*-------------------二次安全验证 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $settingData, $val['mark']);
                }
            } else {
                tpSetting('security', $settingData);
            }
            /*--end*/

            // 设置问题答案后，自动验证通过
            $this->submit_answer_verify();

            $msg = "操作成功";
            $is_show_answer = 0;
            if (!empty($settingData['security_answer']) && !empty($settingData['security_ask_open'])) {
                $is_show_answer = 1;
                $securityData = tpSetting('security');
                $msg = "问题：{$securityData['security_ask']}<br/>答案：".mchStrCode($securityData['security_answer_bright'], 'DECODE'); 
            }
            $this->success($msg, url('Security/index'), ['is_show_answer'=>$is_show_answer,'security_ask_open'=>$settingData['security_ask_open']]);
        }
        $this->error('操作失败');
    }

    /**
     * 保存二次安全验证的数据处理
     * @param  array  &$settingData [description]
     * @param  array  &$post        [description]
     * @return [type]               [description]
     */
    private function handleAskData(&$settingData = [], &$post = [])
    {
        $securityOld = tpSetting('security');
        $security_ask = intval($post['security_ask']);
        $security_answer = trim($post['security_answer']);
        $is_ask_add_edit = empty($securityOld['security_ask']) ? 'add' : 'edit';
        if ('add' == $is_ask_add_edit) {
            if (empty($post['security_ask_open'])) {
                $this->success('操作成功', url('Security/index'), ['is_show_answer'=>0,'security_ask_open'=>0]);
            }
            if (0 > intval($security_ask)) {
                $this->error('请选择密保问题！');
            } else if ($security_answer === '') {
                $this->error('请设置密保答案！');
            }
            $encrypt_answer = func_encrypt($security_answer, true, pwd_encry_type('bcrypt'));
            $row = Db::name('admin')->where([
                    'admin_id'  => $this->admin_id,
                    'password'  => $encrypt_answer,
                ])->count();
            if (!empty($row)) {
                $this->error('密保答案不能与登录密码一致！');
            }
        } else {
            $security_answer_old = trim($post['security_answer_old']);
            if ($security_answer !== '' || 0 <= intval($security_ask)) {
                if ($security_answer_old === '') {
                    $this->error('密保答案不能为空！');
                } else {
                    if (0 <= intval($security_ask)) {
                        if ($security_answer === '') {
                            $this->error('请重置密保答案！');
                        } else if ($security_answer === $security_answer_old) {
                            $this->error('重置密保答案不能与原来的一致！');
                        }
                    }
                }
                $encrypt_answer_old = func_encrypt($security_answer_old, true, pwd_encry_type('bcrypt'));
                if ($encrypt_answer_old != $securityOld['security_answer']) {
                    $this->error('密保答案不正确！');
                }

                $encrypt_answer = func_encrypt($security_answer, true, pwd_encry_type('bcrypt'));
                $row = Db::name('admin')->where([
                        'admin_id'  => $this->admin_id,
                        'password'  => $encrypt_answer,
                    ])->count();
                if (!empty($row)) {
                    $this->error('重置密保答案不能与登录密码一致！');
                }
            } else {
                if ($security_answer_old !== '') {
                    $encrypt_answer_old = func_encrypt($security_answer_old, true, pwd_encry_type('bcrypt'));
                    if ($encrypt_answer_old != $securityOld['security_answer']) {
                        $this->error('密保答案不正确！');
                    }
                }
                unset($post['security_ask']);
                unset($post['security_answer']);
                unset($post['security_answer_old']);
            }
        }

        /**
         * 如果要关闭二次安全验证，必须要进行答案验证
         * 同IP不验证功能也会影响到这里的逻辑
         */
        // 问题列表
        $security_askanswer_list = empty($securityOld['security_askanswer_list']) ? config('global.security_askanswer_list') : json_decode($securityOld['security_askanswer_list'], true);
        // 当前管理员二次安全验证过的IP地址
        $security_answerverify_ip = !empty($securityOld['security_answerverify_ip']) ? $securityOld['security_answerverify_ip'] : '-1';
        // 1、问答要已设置；2、目前是开启；3、当前要关闭；
        if (!empty($securityOld['security_ask_open']) && empty($post['security_ask_open']) && !empty($securityOld['security_ask'])) {
            $admin_info = Db::name('admin')->field('*')->where(['admin_id'=>$this->admin_id])->find();
            // if (!empty($admin_info['parent_id']) || -1 != $admin_info['role_id']) {
            //     $this->error('创始人才能关闭安全验证功能！');
            // }
            if ($admin_info['last_ip'] != $security_answerverify_ip) {
                $this->error("<span style='display:none;'>__html__</span>出于安全考虑<br/>请勿非法越过密保答案验证", null, '', 3);
            }
        }
        $settingData['security_ask_open'] = intval($post['security_ask_open']);
        if (!empty($settingData['security_ask_open'])) {
            empty($post['security_verifyfunc']) && $post['security_verifyfunc'] = [];
            $ctl_act_arr = ['Filemanager@*','Arctype@ajax_newtpl','Archives@ajax_newtpl','Index@ajax_theme_tplfile_add','Index@ajax_theme_tplfile_edit'];
            $post['security_verifyfunc'] = array_merge($post['security_verifyfunc'], $ctl_act_arr);
            // $post['security_verifyfunc'][] = 'Security@*';
            $post['security_verifyfunc'] = array_unique($post['security_verifyfunc']);
            $settingData['security_verifyfunc'] = json_encode($post['security_verifyfunc']);
            $settingData['security_ask_ip_open'] = !empty($post['security_ask_ip_open']) ? intval($post['security_ask_ip_open']) : 0;
            if (isset($post['security_ask'])) {
                $settingData['security_ask'] = $security_askanswer_list[$post['security_ask']];
            }
            if (isset($post['security_answer'])) {
                $settingData['security_answer'] = func_encrypt($post['security_answer'], true, pwd_encry_type('bcrypt'));
                $settingData['security_answer_bright'] = mchStrCode($post['security_answer']);
            }
            if (empty($securityOld['security_askanswer_list'])) {
                $settingData['security_askanswer_list'] = json_encode($security_askanswer_list);
            }
        }
    }

    /*--------------------------------安全验证中心 start--------------------------*/

    /**
     * 设置二次安全验证的问题、答案
     */
    public function second_verify_add()
    {
        $security_askanswer_list = tpSetting('security.security_askanswer_list');
        $security_askanswer_list = json_decode($security_askanswer_list, true);
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }

        if (IS_POST) {
            // 修补越权的漏洞，在重设答案时，通过抓包改成新设答案
            if (!empty($this->globalConfig['security_ask'])) {
                $this->error('已设置过密保，请重新设置');
            }

            $ask = input('post.ask/d');
            $answer = input('post.answer/s');
            $answer = trim($answer);

            if (0 > $ask) {
                $this->error('请选择密保问题！');
            } else if (empty($answer)) {
                $this->error('密保答案不能为空！');
            }

            $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
            $row = Db::name('admin')->where([
                    'admin_id'  => $this->admin_id,
                    'password'  => $encrypt_answer,
                ])->count();
            if (!empty($row)) {
                $this->error('密保答案不能与登录密码一致！');
            }

            $data = [
                'security_ask_open'   => 1,
                'security_ask'   => $security_askanswer_list[$ask],
                'security_answer'   => $encrypt_answer,
                'security_answer_bright'   => mchStrCode($answer),
                'security_askanswer_list' => json_encode($security_askanswer_list),
            ];
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $data, $val['mark']);
                }
            } else {
                tpSetting('security', $data);
            }
            /*--end*/

            $this->success('操作成功', url('Security/index'));
        }

        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 修改二次安全验证的问题、答案
     */
    public function second_verify_edit()
    {
        $security_askanswer_list = tpSetting('security.security_askanswer_list');
        $security_askanswer_list = json_decode($security_askanswer_list, true);
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }

        if (IS_POST) {
            $post = input('post.');
            $answer_old = trim($post['answer_old']);
            $ask = intval($post['ask']);
            $answer = trim($post['answer']);

            if (empty($answer_old)) {
                $this->error('密保答案不能为空！');
            } else {
                if (0 <= $ask) {
                    if (empty($answer)) {
                        $this->error('重置密保答案不能为空！');
                    } else if ($answer == $answer_old) {
                        $this->error('重置密保答案不能与原来的一致！');
                    }
                } 
            }

            $security = tpSetting('security');
            $encrypt_answer_old = func_encrypt($answer_old, true, pwd_encry_type('bcrypt'));
            if ($encrypt_answer_old != $security['security_answer']) {
                $this->error('密保答案不正确！');
            }

            $data = [];
            if (0 <= $ask) {
                $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                $row = Db::name('admin')->where([
                        'admin_id'  => $this->admin_id,
                        'password'  => $encrypt_answer,
                    ])->count();
                if (!empty($row)) {
                    $this->error('重置密保答案不能与登录密码一致！');
                }
                $data['security_ask'] = $security_askanswer_list[$ask];
                $data['security_answer'] = $encrypt_answer;
                $data['security_answer_bright'] = mchStrCode($answer);
                $data['security_askanswer_list'] = json_encode($security_askanswer_list);
            }

            if (!empty($data)) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpSetting('security', $data, $val['mark']);
                    }
                } else {
                    tpSetting('security', $data);
                }
                /*--end*/
            }

            $this->success('操作成功', url('Security/index'));
        }

        $security = tpSetting('security');
        if (!empty($security)) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security', $security);
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 二次安全验证答案
     * @return [type] [description]
     */
    public function ajax_answer_verify()
    {
        if (IS_POST) {
            $answer = input('param.answer/s');
            $answer = trim($answer);
            if (empty($answer)) {
                $this->error('请录入密保答案！');
            } else {
                $security_answer = tpSetting('security.security_answer');
                $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                if ($security_answer != $encrypt_answer) {
                    $this->error('密保答案不正确！');
                }
            }
            $this->submit_answer_verify();
            $this->success('密保验证成功');
        }
    }

    /**
     * 二次安全验证答案-提交
     * @return [type] [description]
     */
    private function submit_answer_verify()
    {
        /*多语言*/
        $ip = clientIP();
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')
                ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                ->select();
            foreach ($langRow as $key => $val) {
                tpSetting('security', ['security_answerverify_ip'=>$ip], $val['mark']);
            }
        } else {
            tpSetting('security', ['security_answerverify_ip'=>$ip]);
        }
        /*--end*/

        // 解决个别用户安装后，登录后台没记录最后登录IP地址，导致一直弹出验证答案
        $admin_info = Db::name('admin')->field('admin_id,last_ip')->where(['admin_id'=>$this->admin_id])->find();
        Db::name('admin')->where(['admin_id'=>$admin_info['admin_id']])->save(['last_ip'=>$ip, 'update_time'=>getTime()]);
    }

    /**
     * 是否已验证了答案
     * @return [type] [description]
     */
    public function ajax_isverify_answer()
    {
        if (IS_POST) {
            $security = tpSetting('security');
            $security_answerverify_ip = !empty($security['security_answerverify_ip']) ? $security['security_answerverify_ip'] : '-1';
            $admin_info = Db::name('admin')->field('admin_id,last_ip')->where(['admin_id'=>$this->admin_id])->find();
            if ($admin_info['last_ip'] == $security_answerverify_ip) {
                $this->success('已验证');
            }
        }
        $this->error('未验证');
    }

    /**
     * 修改问题列表
     * @return [type] [description]
     */
    public function save_ask_list()
    {
        if (IS_POST) {
            $value = input('post.value/s');
            $value = str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $value);
            $arr = explode(PHP_EOL, $value);
            foreach ($arr as $key => $val) {
                $val = trim($val);
                if (empty($val)) {
                    unset($arr[$key]);
                } else {
                    $arr[$key] = $val;
                }
            }
            if (empty($arr)) {
                $this->error('问题列表不能为空！');
            }

            // 将已设置的问题加入列表中
            $security_ask = tpSetting('security.security_ask');
            $security_ask = trim($security_ask);
            if (!empty($security_ask) && !in_array($security_ask, $arr)) {
                $arr[] = $security_ask;
            }

            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', ['security_askanswer_list'=>json_encode($arr)], $val['mark']);
                }
            } else { // 单语言
                tpSetting('security', ['security_askanswer_list'=>json_encode($arr)]);
            }
            $value = implode(PHP_EOL, $arr);

            $this->success('操作成功', null, ['value'=>$value, 'security_askanswer_list'=>$arr]);
        }
    }

    /**
     * 独立弹窗的安全验证中心（用于点击入口模板管理）
     * @return [type] [description]
     */
    public function second_ask_init()
    {
        if (IS_POST) {
            $settingData = [];
            $post = input('post.');
            
            /*-------------------二次安全验证 start-------------------*/
            $this->handleAskData($settingData, $post);
            /*-------------------二次安全验证 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $settingData, $val['mark']);
                }
            } else {
                tpSetting('security', $settingData);
            }
            /*--end*/

            // 设置问题答案后，自动验证通过
            $this->submit_answer_verify();

            $is_show_answer = 0;
            if (empty($settingData['security_ask_open'])) {
                $gourl = "";
                $msg = "操作成功";
            } else {
                $gourl = input('param.gourl/s', '', null);
                if (empty($settingData['security_answer'])) {
                    $msg = "操作成功";
                } else {
                    $is_show_answer = 1;
                    $securityData = tpSetting('security');
                    $msg = "问题：{$securityData['security_ask']}<br/>答案：".mchStrCode($securityData['security_answer_bright'], 'DECODE'); 
                }
            }
            $this->success($msg, null, ['gourl'=>$gourl,'is_show_answer'=>$is_show_answer]);
        }

        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        // 安全验证配置
        $security = tpSetting('security');
        if (!isset($security['security_ask_open'])) {
            $security['security_ask_open'] = 1;
        }
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        $gourl = input('param.gourl/s');
        $this->assign('gourl', urldecode($gourl));
        // 点击来源
        $source = input('param.source/s');
        $this->assign('source', $source);

        return $this->fetch();
    }

    public function ajax_security_ask_open()
    {
        $data = tpSetting('security');
        $data['security_ask_open'] = empty($data['security_ask_open']) ? 0 : intval($data['security_ask_open']);
        $this->success('请求成功', null, $data);
    }

    /*-----------------------ddos攻击脚本查杀 start-----------------------*/

    /**
     * DDOS攻击脚本查杀
     * @return [type] [description]
     */
    public function ddos_kill()
    {
        $Prefix = config('database.prefix');
        $syn_admin_logic_1726216198 = tpSetting('syn.syn_admin_logic_1726216198', [], 'cn');
        if (empty($syn_admin_logic_1726216198)) {
            try {
                @Db::execute("DROP TABLE IF EXISTS `{$Prefix}ddos_log`");
                tpSetting('syn', ['syn_admin_logic_1726216198' => 1], 'cn');
            } catch (\Exception $e) {}
        }

        $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}ddos_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `md5key` varchar(50) DEFAULT '' COMMENT 'md5值',
  `file_name` text COMMENT '文件名',
  `file_num` int(10) DEFAULT '0' COMMENT '已扫描数',
  `file_total` int(10) DEFAULT '0' COMMENT '总文件数',
  `file_doubt_total` int(10) DEFAULT '0' COMMENT '可疑恶意文件数',
  `file_excess` int(5) DEFAULT '0' COMMENT '大于0表示多余，2表示官方目录的多余自行修复',
  `file_grade` int(10) DEFAULT '0' COMMENT '文件级别，0=正常，100=异常文件，200=疑似木马，970=低危，980=中危，990=高危',
  `html` longtext,
  `admin_id` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0' COMMENT '新增时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='ddos查杀进度记录表';
EOF;
        $r = @Db::execute($tableSql);
        if ($r !== false) {
            schemaTable('ddos_log');
        }

        $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}ddos_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '配置的key键名',
  `value` longtext,
  `inc_type` varchar(50) DEFAULT 'ddos',
  `admin_id` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0' COMMENT '新增时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='ddos业务存储表';
EOF;
        $r = @Db::execute($tableSql);
        if ($r !== false) {
            schemaTable('ddos_setting');
        }

        $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}ddos_whitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `md5key` varchar(50) DEFAULT '' COMMENT 'md5值',
  `file_name` text COMMENT '文件名',
  `admin_id` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0' COMMENT '新增时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='ddos扫描白名单列表';
EOF;
        $r = @Db::execute($tableSql);
        if ($r !== false) {
            schemaTable('ddos_whitelist');
        }

        // 病毒特征库
        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/ddos_feature_library.txt';
        $response = @httpRequest2($url, 'GET', [], [], 3);
        if (!strstr($response, '100001')) {
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $response = @file_get_contents($url, false, $context);
        }
        if (strstr($response, '100001')) {
            $path = DATA_PATH.'conf/ddos_feature_library.txt';
            if (!file_exists($path) || is_writeable($path)) {
                try {
                    $fp = fopen($path, "w+");
                    if (!empty($fp) && fwrite($fp, $response)) {
                        fclose($fp);
                    }
                } catch (\Exception $e) {}
            }
            $this->ddosLogic->ddos_setting('sys.feature_library', $response);
        } else {
            $response = getVersion('ddos_feature_library', '');
            if (empty($response)) {
                $response = $this->ddosLogic->ddos_setting('sys.feature_library');
            }
        }

        if (empty($response)) {
            $this->error('文件 data/conf/ddos_feature_library.txt 没有读写权限', null, '', 20);
        }
        $response = preg_replace("#[\r\n]{1,}#", "\n", $response);
        $feature_librarys = explode("\n", $response);
        $feature_librarys = array_filter($feature_librarys);

        $feature_pattern = [];
        $feature_imgpattern = [];
        $feature_msg = [];
        $feature_msg_grade = [];
        $feature_other = [];
        foreach ($feature_librarys as $key => $val) {
            if (!preg_match('/^#/i', $val)) {
                if (preg_match('/^pattern\|/i', $val)) {
                    $_k = preg_replace('/^pattern\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_pattern[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                }
                else if (preg_match('/^imgpattern\|/i', $val)) {
                    $_k = preg_replace('/^imgpattern\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_imgpattern[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                }
                else if (preg_match('/^msg\|/i', $val)) {
                    $_k = preg_replace('/^msg\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_msg[$_k]['value'] = preg_replace('/^(.*)\|value\|([^\|]+)\|(.*)$/i', '${2}', $val);
                    $_k2 = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $_k);
                    $feature_msg_grade[$_k2]['grade'] = $_k2;
                    $feature_msg_grade[$_k2]['value'] = preg_replace('/^msg\|(\d+)\|([^\|]+)\|(.*)$/i', '${2}', $val);
                    $opt = preg_replace('/^(.*)\|opt_([\w\-]*)\|(.*)$/i', '${2}', $val);
                    $feature_msg[$_k]['opt']['event'] = $opt;
                    $feature_msg[$_k]['opt']['value'] = preg_replace('/^(.*)\|opt_([\w\-]*)\|([^\|]+)\|(.*)$/i', '${3}', $val);
                }
                else if (preg_match('/^other\|/i', $val)) {
                    $_k = preg_replace('/^other\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_other[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                }
            }
        }
        $setData = [
            'ddos_feature_pattern' => base64_encode(json_encode($feature_pattern)),
            'ddos_feature_imgpattern' => base64_encode(json_encode($feature_imgpattern)),
            'ddos_feature_msg' => base64_encode(json_encode($feature_msg)),
            'ddos_feature_msg_grade' => base64_encode(json_encode($feature_msg_grade)),
            'ddos_feature_other' => base64_encode(json_encode($feature_other)),
        ];
        tpSetting('ddos', $setData, 'cn');

        $assign_data['ddosSetting'] = tpSetting('ddos', [], 'cn');
        $assign_data['root_path'] = ROOT_PATH;
        $assign_data['doubtdata'] = $this->ddosLogic->ddos_doubtdata();
        // 后台入口文件
        $assign_data['adminbasefile'] = $this->ddosLogic->getAdminbasefile(false);
        // 图片木马检测的开关
        $assign_data['check_illegal_open'] = tpCache('weapp.weapp_check_illegal_open');

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 整理文件列表
     * @return [type] [description]
     */
    public function ddos_arrange_files()
    {
        //防止超时/内存溢出
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');
        if (IS_POST) {
            // 清理缓存
            Cache::clear();
            delFile(RUNTIME_PATH, false, ['.htaccess']);
            delFile(DATA_PATH.'schema/', false, ['.htaccess']);
            delFile(DATA_PATH.'backup/', false, ['.htaccess']);
            // 重新生成数据表结构
            if (function_exists('schemaAllTable')) schemaAllTable();
            // 清除session过期文件
            if (function_exists('clear_session_file')) clear_session_file();
            // 生成语言包文件
            if (file_exists('application/common/model/ForeignPack.php')) model('ForeignPack')->updateLangFile();
            // 第一个先执行的范围，重置一些数据
            $init_runtype = input('param.init_runtype/s');
            if ('files' == $init_runtype) {
                // 重置ddos_log表
                $this->ddosLogic->ddos_log_reset();
            }

            // Win 环境
            if (IS_WIN) {
                $dir = APP_PATH.'../';
            }
            // 非 Win 环境
            else {
                $dir = ROOT_PATH;
            }
            if (!is_readable($dir)) {
                $dir = str_replace('\\', '/', $dir);
                $dir = rtrim($dir, '/').'/';
            }

            // 递归读取文件夹
            $list[] = '/';
            $this->ddosLogic->ddos_getDir($dir, '', $list, ['uploads','public/upload', 'upload']);
            // 存储读取后的文件夹列表
            $this->ddosLogic->ddos_setting('web.source_dirlist', json_encode($list));

            // 获取官方对应版本的文件列表
            $this->ddosLogic->ddos_eyou_source_files();

            $this->success("读取文件完成");
        }
    }

    /**
     * 整理附件列表
     * @return [type] [description]
     */
    public function ddos_arrange_attachment()
    {
        //防止超时/内存溢出
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');
        if (IS_POST) {
            // 第一个先执行的范围，重置一些数据
            $init_runtype = input('param.init_runtype/s');
            if ('attachment' == $init_runtype) {
                // 重置ddos_log表
                $this->ddosLogic->ddos_log_reset();
            }
            
            $list = [];
            // 递归读取包括子站点的上传图片文件夹
            $dirs = [];
            foreach (['uploads','public/upload', 'upload'] as $key => $val) {
                $xing_str = '';
                for ($i=0; $i < 5; $i++) { 
                    $dir_arr = glob("{$xing_str}{$val}", GLOB_ONLYDIR);
                    if (!empty($dir_arr)) {
                        $dirs = array_merge($dirs, $dir_arr);
                    }
                    $xing_str .= '*/';
                }
                $dirs = array_unique($dirs);
            }
            // 递归读取文件夹文件
            $this->ddosLogic->get_dir_list($dirs, $list);
            // 存储读取后的文件列表
            $this->ddosLogic->ddos_setting('web.uploads_dirlist', json_encode($list));

            $this->success("读取目录完成");
        }
    }

    public function ddos_scan_file()
    {
        @ini_set('memory_limit', '-1');
        function_exists('set_time_limit') && set_time_limit(0);

        if (IS_POST) {
            $achievepage = input("param.achieve/d", 0); // 已扫描文件/目录数
            $doubtotal = input("param.doubtotal/d", 0); // 已扫描出的异常文件数
            $achievefile = input("param.achievefile/d", 0); // 已扫描文件数
            $allscantotal = input("param.allscantotal/d", 0); // 已扫描所有范围的文件数
            $scan_range = input("param.scan_range/s", 'files');
            if ('files' == $scan_range) {
                $data = $this->ddosLogic->ddosHandelScanFiles($doubtotal, $achievepage, $achievefile, $allscantotal, true, 50);
            } else if ('attachment' == $scan_range) {
                $data = $this->ddosLogic->ddosHandelScanAttachment($doubtotal, $achievepage, $achievefile, $allscantotal, true, 50);
            }
            if (!empty($data[1])) {
                if ($data[1]['achievepage'] >= $data[1]['allpagetotal']) {
                    $setdata = [
                        'ddos_scan_is_finish' => 2, // 扫描完成
                        'ddos_scan_allscantotal' => $data[1]['allscantotal'],
                    ];
                    tpSetting('ddos', $setdata, 'cn');
                } else {
                    $setdata = [
                        'ddos_scan_is_finish' => 1, // 扫描中
                    ];
                    tpSetting('ddos', $setdata, 'cn');
                }
            }
            $this->success($data[0], null, $data[1]);
        }

        $range_files      = input("param.range_files/d", 0);
        $range_attachment      = input("param.range_attachment/d", 0);
        $range_uploads      = input("param.range_uploads/d", 0);
        $setdata = [
            'ddos_scan_range_files' => $range_files,
            'ddos_scan_range_attachment' => $range_attachment,
            'ddos_scan_range_uploads' => $range_uploads,
            'ddos_scan_last_time' => getTime(),
        ];
        tpSetting('ddos', $setdata, 'cn');

        $this->assign($setdata);
        return $this->fetch();
    }

    /**
     * 一键修复
     */
    public function ddos_one_click_repair()
    {
        $result = Db::name('ddos_log')->field('html', true)->where(['admin_id'=>$this->admin_id, 'file_grade'=>['gt', 0]])->order('file_grade desc')->select();
        if (empty($result)) {
            $this->success('操作成功', null, ['file_doubt_total'=>0]);
        }
        $list = [];
        foreach ($result as $key => $val) {
            if (1 == $val['file_excess'] || preg_match('/^install([\w\-]*)$/i', $val['file_name'])) {
                $list['del'][] = $val;
            } else if (0 == $val['file_excess']) {
                $list['replace'][] = $val;
            }
        }
        $msg = '';
        $errnum = 0;
        $md5keys = [];
        // 删除
        if (!empty($list['del'])) {
            $data = $this->ddosLogic->ddos_delfile_handle($list['del']);
            if (!empty($data['msg'])) {
                $msg = $data['msg'];
            }
            if (!empty($data['errnum'])) {
                $errnum += $data['errnum'];
            }
            if (!empty($data['md5keys'])) {
                $md5keys = array_merge($md5keys, $data['md5keys']);
            }
        }
        // 修复
        if (!empty($list['replace'])) {
            $data = $this->ddosLogic->ddos_replacefile_handle($list['replace']);
            if (!empty($data['msg'])) {
                $msg = $data['msg'];
            }
            if (!empty($data['errnum'])) {
                $errnum += $data['errnum'];
            }
            if (!empty($data['md5keys'])) {
                $md5keys = array_merge($md5keys, $data['md5keys']);
            }
        }

        if (!empty($md5keys)) {
            $redata = $this->ddosLogic->ddos_update_doubt_total();
            if ($data['code'] !== false) {
                $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
            } else {
                if (!empty($errnum)) {
                    $this->error('部分操作失败', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
                }
            }
        }
        empty($msg) && $msg = '操作失败';
        $this->error($msg);
    }

    /**
     * 删除扫描后的文件
     * @return [type] [description]
     */
    public function ddos_delfile($md5keys = [])
    {
        $msg = '';
        $errnum = 0;
        $md5keys = input('md5keys/a', $md5keys);
        foreach ($md5keys as $key => $val) {
            $md5keys[$key] = preg_replace('/([^a-z0-9]+)/i', '', $val);
        }
        if (!empty($md5keys)) {
            $result = Db::name('ddos_log')->field('html', true)->where(['md5key'=>['IN', $md5keys], 'file_grade'=>['gt', 0], 'admin_id'=>$this->admin_id])->select();
            if (empty($result)) {
                $this->success('操作成功', null, ['file_doubt_total'=>0]);
            }

            $data = $this->ddosLogic->ddos_delfile_handle($result);
            $msg = $data['msg'];
            $errnum = $data['errnum'];
            $md5keys = $data['md5keys'];

            $redata = $this->ddosLogic->ddos_update_doubt_total();
            if ($data['code'] !== false) {
                $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
            } else {
                if (!empty($errnum)) {
                    $this->error('部分操作失败', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
                }
            }
        }
        empty($msg) && $msg = '操作失败';
        $this->error($msg);
    }

    /**
     * 管理白名单
     * @return [type] [description]
     */
    public function ddos_whitelist_list()
    {
        $list = array();
        $param = input('param.');
        $keywords = input('keywords/s');
        $keywords = trim($keywords);
        $condition = [];
        // 应用搜索条件
        foreach (['keywords'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.file_name'] = array('LIKE', "%{$keywords}%");
                } else {
                    $condition['a.'.$key] = array('eq', trim($param[$key]));
                }
            }
        }

        $condition['a.admin_id'] = array('eq', $this->admin_id);

        $count = Db::name('ddos_whitelist')->alias("a")->where($condition)->count('a.id');// 查询满足要求的总记录数
        $Page = $pager = new Page($count, 50);// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('ddos_whitelist')->alias("a")->field('a.*')->where($condition)->order('a.id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        foreach ($list as $key => $val) {
            $file_all_name = !empty($val['file_name']) ? trim($val['file_name'], '/') : '';
            if (!file_exists($file_all_name)) {
                unset($list[$key]);
                continue;
            }
            $file_alias_name = preg_replace('/^(.*)(\/|\\\)([^\/\\\]+)$/i', '${3}', $file_all_name);
            $val['file_alias_name'] = $file_alias_name;
            $list[$key] = $val;
        }

        $show = $Page->show();// 分页显示输出
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页对象

        return $this->fetch();
    }

    /**
     * 加入白名单
     * @return [type] [description]
     */
    public function ddos_whitelist_add($md5keys = [])
    {
        $md5keys = input('md5keys/a', $md5keys);
        foreach ($md5keys as $key => $val) {
            $md5keys[$key] = preg_replace('/([^a-z0-9]+)/i', '', $val);
        }
        if (!empty($md5keys)) {
            $result = Db::name('ddos_log')->field('html', true)->where(['md5key'=>['IN', $md5keys], 'admin_id'=>$this->admin_id])->select();
            if (empty($result)) {
                $this->success('操作成功', null, ['file_doubt_total'=>0]);
            }

            $data = $this->ddosLogic->ddos_whitelist_add_handle($result);
            $md5keys = $data['md5keys'];

            $redata = $this->ddosLogic->ddos_update_doubt_total();
            if ($data['code'] !== false) {
                $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
            }
        }
        $this->error('操作失败');
    }

    /**
     * 移出白名单
     * @return [type] [description]
     */
    public function ddos_whitelist_del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $r = Db::name('ddos_whitelist')->where([
                        'id'    => ['IN', $id_arr],
                        'admin_id'  => $this->admin_id,
                    ])->delete();
                if($r !== false){
                    $this->success('移出成功');
                }
            }
        }
        $this->error('移出失败');
    }

    public function ddos_download_file()
    {
        $source = input('param.source/s');
        $md5key = input('param.md5key/s');
        $md5key = preg_replace('/([^\w]+)/i', '', $md5key);
        if ('whitelist' == $source) {
            $result = Db::name('ddos_whitelist')->field('html', true)->where(['md5key'=>$md5key, 'admin_id'=>$this->admin_id])->find();
        } else {
            $result = Db::name('ddos_log')->field('html', true)->where(['md5key'=>$md5key, 'file_grade'=>['gt', 0], 'admin_id'=>$this->admin_id])->find();
        }
        if (!empty($result)) {
            $file_all_name = !empty($result['file_name']) ? trim($result['file_name'], '/') : '';
            if (!empty($file_all_name)) {
                if (is_dir($file_all_name)) {
                    $this->error('不支持下载目录');
                }
                else if (is_file($file_all_name)) {
                    $filetype = preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $file_all_name); // pathinfo($file_all_name, PATHINFO_EXTENSION);
                    $file_alias_name = preg_replace('/^(.*)(\/|\\\)([^\/\\\]+)$/i', '${3}', $file_all_name);
                    if (in_array($filetype, ['zip','rar','gz'])) {
                        $url = request()->domain().ROOT_DIR.'/'.$file_all_name;
                        $this->redirect($url);
                    } else {
                        download_file('/'.$file_all_name, '', $file_alias_name);
                    }
                    exit; 
                }
            }
        }
        $this->error('下载失败');
    }

    /**
     * 修复文件
     * @return [type] [description]
     */
    public function ddos_replacefile($md5keys = [])
    {
        $msg = '';
        $md5keys = input('md5keys/a', $md5keys);
        foreach ($md5keys as $key => $val) {
            $md5keys[$key] = preg_replace('/([^a-z0-9]+)/i', '', $val);
        }
        if (!empty($md5keys)) {
            $result = Db::name('ddos_log')->field('html', true)->where(['md5key'=>['IN', $md5keys], 'file_grade'=>['gt', 0], 'admin_id'=>$this->admin_id])->select();
            foreach ($result as $key => $val) {
                if (preg_match('/^(template)\/(.*)$/i', $val['file_name']) && !preg_match('/^(template)\/(.*)\.js$/i', $val['file_name'])) {
                    unset($result[$key]);
                }
            }
            if (empty($result)) {
                $this->success('操作成功', null, ['file_doubt_total'=>0]);
            }

            $data = $this->ddosLogic->ddos_replacefile_handle($result);
            $msg = $data['msg'];
            $errnum = $data['errnum'];
            $md5keys = $data['md5keys'];

            $redata = $this->ddosLogic->ddos_update_doubt_total();
            if ($data['code'] !== false) {
                $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
            } else {
                if (!empty($errnum)) {
                    $this->error('部分操作失败', null, ['file_doubt_total'=>$redata['file_doubt_total'], 'md5keys'=>$md5keys]);
                }
            }
        }
        empty($msg) && $msg = '操作失败';
        $this->error($msg);
    }

    /*-----------------------ddos攻击脚本查杀 end-----------------------*/

    /**
     * 后台登录路径
     * @return [type] [description]
     */
    public function popup_adminbasefile()
    {
        if (IS_POST) {
            $post = input('post.');
            /*-------------------后台安全配置 start-------------------*/
            $param = [];
            // 自定义后台路径名
            $adminbasefile = preg_replace('/([^\w\_\-])/i', '', trim($post['adminbasefile'])).'.php'; // 新的文件名
            $param['web_adminbasefile'] = $this->root_dir.'/'.$adminbasefile; // 支持子目录
            $baseFile = explode('/', $this->request->baseFile());
            $adminbasefile_old = end($baseFile); // 旧的文件名
            if ('index.php' == $adminbasefile) {
                $this->error("后台路径禁止使用index", null, '', 1);
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $refresh = false;

            /*-------------------后台安全配置 start-------------------*/
            // 更改自定义后台路径名 - 刷新整个后台
            $gourl = request()->domain().$this->root_dir.'/'.$adminbasefile; // 支持子目录
            if ($adminbasefile_old != $adminbasefile && eyPreventShell($adminbasefile_old)) {
                if (file_exists($adminbasefile_old)) {
                    if(rename($adminbasefile_old, $adminbasefile)) {
                        $refresh = true;
                    }
                } else {
                    $this->error("根目录{$adminbasefile_old}文件不存在！", null, '', 2);
                }
            }
            /*-------------------后台安全配置 end-------------------*/

            if ($refresh) {
                $this->success('操作成功', $gourl, '', 1, [], '_parent');
            }

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        // 后台入口文件
        $adminbasefile = $this->ddosLogic->getAdminbasefile(false);
        $adminbasefile = preg_replace('/^(.*)\.([^\.]+)$/i', '$1', $adminbasefile);
        $this->assign('adminbasefile', $adminbasefile);

        return $this->fetch();
    }

    /**
     * 登录超时
     * @return [type] [description]
     */
    public function popup_login_expiretime()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_expiretime' => $post['web_login_expiretime'],
                'login_expiretime_old' => $post['login_expiretime_old'],
            ];
            // 后台登录超时
            $web_login_expiretime = $param['web_login_expiretime'];
            $login_expiretime_old = $param['login_expiretime_old'];
            unset($param['login_expiretime_old']);
            if ($login_expiretime_old != $web_login_expiretime) {
                $web_login_expiretime = preg_replace('/^(\d{0,})(.*)$/i', '${1}', $web_login_expiretime);
                empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
                if ($web_login_expiretime > 2592000) {
                    $web_login_expiretime = 2592000; // 最多一个月
                }
                $param['web_login_expiretime'] = $web_login_expiretime;
                //前台登录超时时间
                $users_login_expiretime = getUsersConfigData('users.users_login_expiretime');
                //前台和后台谁设置的时间大就用谁的做session过期时间
                $max_login_expiretime = $web_login_expiretime;
                if ($web_login_expiretime < $users_login_expiretime){
                    $max_login_expiretime = $users_login_expiretime;
                }
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            /*-------------------后台安全配置 start-------------------*/
            // 更改session会员设置 - session有效期（后台登录超时）
            if ($login_expiretime_old != $web_login_expiretime) {
                $session_conf = [];
                $session_file = APP_PATH.'admin/conf/session_conf.php';
                if (file_exists($session_file)) {
                    require_once($session_file);
                    $session_conf_tmp = EY_SESSION_CONF;
                    if (!empty($session_conf_tmp)) {
                        $session_conf_tmp = json_decode($session_conf_tmp, true);
                        if (!empty($session_conf_tmp) && is_array($session_conf_tmp)) {
                            $session_conf = $session_conf_tmp;
                        }
                    }
                }
                $session_conf['expire'] = $max_login_expiretime;
                $str_session_conf = '<?php'.PHP_EOL.'$session_1600593464 = json_encode('.var_export($session_conf,true).');'.PHP_EOL.'define(\'EY_SESSION_CONF\', $session_1600593464);';
                @file_put_contents(APP_PATH . 'admin/conf/session_conf.php', $str_session_conf);
            }
            /*-------------------后台安全配置 end-------------------*/

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        return $this->fetch();
    }

    /**
     * 登录防爆设置
     * @return [type] [description]
     */
    public function popup_flameproof()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_lockopen'    => !empty($post['web_login_lockopen']) ? 1 : 0,
            ];
            // 开启锁定才修改相应的配置值
            if (!empty($param['web_login_lockopen'])) {
                $param['web_login_errtotal'] = $post['web_login_errtotal'];
                $param['web_login_errexpire'] = $post['web_login_errexpire'];
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        // 后台防火墙设置
        $currentIp = clientIP();
        $ipStart = preg_replace('/\.\d{1,3}\.\d{1,3}$/i', '.0.1', $currentIp);
        $ipEnd = preg_replace('/\.\d{1,3}\.\d{1,3}$/i', '.255.255', $currentIp);
        $this->assign('ipSegment', "{$ipStart}-{$ipEnd}");
        $this->assign('currentIp', $currentIp);

        $firewallData = tpSetting('firewall');
        $firewallData['firewall_login_week'] = json_decode($firewallData['firewall_login_week'], true);
        $firewallData['firewall_login_hour'] = json_decode($firewallData['firewall_login_hour'], true);
        $firewallData['firewall_open_func'] = json_decode($firewallData['firewall_open_func'], true);
        $this->assign('firewallData', $firewallData);

        // 双因子设置
        $twofactorData = tpSetting('twofactor');
        $this->assign('twofactorData', $twofactorData);

        return $this->fetch();
    }
    
    /**
     * 跨站脚本攻击
     * @return [type] [description]
     */
    public function popup_antinjection()
    {
        if (IS_POST) {
            $post = input('post.', '', null);
            /*-------------------后台安全配置 start-------------------*/
            $param = [];
            // 编辑器防注入
            $param['web_xss_filter'] = intval($post['web_xss_filter']);
            $web_xss_words = htmlspecialchars_decode($post['web_xss_words']);
            $web_xss_words = explode(PHP_EOL, $web_xss_words);
            foreach ($web_xss_words as $key => $val) {
                $val = trim($val);
                if (!empty($val)) {
                    $web_xss_words[$key] = $val;
                } else {
                    unset($web_xss_words[$key]);
                }
            }
            $web_xss_words = implode(PHP_EOL, $web_xss_words);
            $param['web_xss_words'] = $web_xss_words;
            
            // 网站防止被刷
            $param['web_anti_brushing'] = intval($post['web_anti_brushing']);
            $web_anti_words = htmlspecialchars_decode($post['web_anti_words']);
            $web_anti_words = explode(PHP_EOL, $web_anti_words);
            foreach ($web_anti_words as $key => $val) {
                $val = trim($val);
                if (!empty($val)) {
                    $web_anti_words[$key] = $val;
                } else {
                    unset($web_anti_words[$key]);
                }
            }
            $web_anti_words = implode(PHP_EOL, $web_anti_words);
            $param['web_anti_words'] = $web_anti_words;
            /*-------------------后台安全配置 end-------------------*/

            $langRow = \think\Db::name('language')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('web', $param, $val['mark']);
            }

            // 存储文件
            $this->setWebXssFilter($param);

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        return $this->fetch();
    }

    /**
     * 后台防火墙设置
     * @return [type] [description]
     */
    public function popup_firewall()
    {
        if (IS_POST) {
            $param = input('post.');
            if (empty($param['firewall_open_func'])) {
                $param['firewall_open'] = 0;
            } else {
                $param['firewall_open'] = 1;
            }
            if (empty($param['firewall_open'])) {
                $param = [
                    'firewall_open'=>$param['firewall_open'],
                    'firewall_open_func' => json_encode([]),
                ];
            } else {
                $param['firewall_ip_whitelist'] = empty($param['firewall_ip_whitelist']) ? '' : $param['firewall_ip_whitelist'];
                $param['firewall_login_week'] = empty($param['firewall_login_week']) ? json_encode([]) : json_encode($param['firewall_login_week']);
                $param['firewall_login_hour'] = empty($param['firewall_login_hour']) ? json_encode([]) : json_encode($param['firewall_login_hour']);
                if (!in_array(1, $param['firewall_open_func'])) {
                    unset($param['firewall_ip_whitelist']);
                }
                if (!in_array(2, $param['firewall_open_func'])) {
                    unset($param['firewall_login_week']);
                    unset($param['firewall_login_hour']);
                }
                $param['firewall_open_func'] = empty($param['firewall_open_func']) ? json_encode([]) : json_encode($param['firewall_open_func']);

                $currentIp = clientIP();
                $rdata = $this->ddosLogic->firewall_blockip_check($currentIp, $param['firewall_ip_whitelist'], false);
                if (empty($rdata['blockip_check'])) {
                    $ipStart = preg_replace('/\.\d{1,3}\.\d{1,3}$/i', '.0.1', $currentIp);
                    $ipEnd = preg_replace('/\.\d{1,3}\.\d{1,3}$/i', '.255.255', $currentIp);
                    $this->error("当前IP（{$currentIp}）没在IP段白名单内，<br/>可添加固定单个IP，或可变的IP段范围，<br/>以下仅供参考（按需调整IP范围）:<br/>{$ipStart}-{$ipEnd}");
                }
            }

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('firewall', $param, $val['mark']);
                }
            } else {
                tpSetting('firewall', $param);
            }
            /*--end*/

            if (file_exists(DATA_PATH.'conf'.DS.'uneyousafe.txt')) {
                @unlink(DATA_PATH.'conf'.DS.'uneyousafe.txt');
            }
            $this->success('操作成功', url('Security/ddos_kill'));
        }
    }

    /**
     * 后台双因子设置
     * @return [type] [description]
     */
    public function popup_twofactor()
    {
        if (IS_POST) {
            $param = input('post.');
            if (-1 == $param['twofactor_check_type']) {
                $param['twofactor_open'] = 0;
                $param['twofactor_check_type'] = $param['twofactor_check_type_old'];
            } else {
                $param['twofactor_open'] = 1;
            }

            if (empty($param['twofactor_open'])) {
                $param = ['twofactor_open'=>$param['twofactor_open']];
            } else {
                $param['twofactor_check_type'] = empty($param['twofactor_check_type']) ? 0 : $param['twofactor_check_type'];
                if (1 == $param['twofactor_check_type']) {
                    $sms_config = tpCache('sms');
                    $sms_type = isset($sms_config['sms_type']) ? $sms_config['sms_type'] : 1;
                    // 是否填写手机短信配置
                    $is_conf = 1;
                    if (empty($sms_config['sms_test_mobile'])) {
                        $is_conf = 0;
                    } else {
                        $sms_arr = [];
                        foreach ($sms_config as $key => $val) {
                            $sms_arr[$key] = $val;
                        }
                        if (2 == $sms_type) {
                            foreach (['sms_appkey_tx','sms_appid_tx'] as $key => $val) {
                                if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                                    $is_conf = 0;
                                }
                            }
                        } else {
                            foreach (['sms_appkey','sms_secretkey'] as $key => $val) {
                                if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                                    $is_conf = 0;
                                }
                            }
                        }
                    }

                    $smsTemp = Db::name('sms_template')->where(["send_scene"=>30,"sms_type"=>$sms_type,'lang'=>get_admin_lang()])->find();
                    if (empty($is_conf) || empty($smsTemp['sms_sign']) || empty($smsTemp['sms_tpl_code']) || empty($smsTemp['tpl_content'])){
                        $this->error("未配置云短信接口或短信模板，请确认配置好再操作");
                    }
                    Db::name('sms_template')->where(["send_scene"=>$smsTemp['send_scene']])->update(['is_open'=>1]);
                }
                else if (2 == $param['twofactor_check_type']) {
                    $security_ask_open = tpSetting('security.security_ask_open');
                    if (empty($security_ask_open)) {
                        $this->error('未配置密保问题，请确认配置好再操作');
                    }
                }
                else {
                    $smtp_config = tpCache('smtp');
                    $smtpTemp = Db::name('smtp_tpl')->where(["send_scene"=>30,'lang'=>get_admin_lang()])->find();
                    if (empty($smtp_config['smtp_user']) || empty($smtp_config['smtp_from_eamil']) || empty($smtpTemp['tpl_title']) || empty($smtpTemp['tpl_content'])){
                        $this->error("未配置邮箱接口，请确认配置好再操作");
                    }
                    Db::name('smtp_tpl')->where(["send_scene"=>$smtpTemp['send_scene']])->update(['is_open'=>1]);
                }
            }

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('twofactor', $param, $val['mark']);
                }
            } else {
                tpSetting('twofactor', $param);
            }
            /*--end*/

            if (empty($param['twofactor_open'])) {
                if (file_exists(DATA_PATH.'conf'.DS.'twofactor_login_open.txt')) {
                    @unlink(DATA_PATH.'conf'.DS.'twofactor_login_open.txt');
                }
            }
            $this->success('操作成功', url('Security/ddos_kill'));
        }
    }

    /**
     * 登录双因子的验证方式检测情况
     * @return [type] [description]
     */
    public function popup_twofactor_checkopen()
    {
        $twofactor_check_type = input('param.twofactor_check_type/d');
        if (1 == $twofactor_check_type) {
            $sms_config = tpCache('sms');
            $sms_type = isset($sms_config['sms_type']) ? $sms_config['sms_type'] : 1;
            // 是否填写手机短信配置
            $is_conf = 1;
            if (empty($sms_config['sms_test_mobile'])) {
                $is_conf = 0;
            } else {
                $sms_arr = [];
                foreach ($sms_config as $key => $val) {
                    $sms_arr[$key] = $val;
                }
                if (2 == $sms_type) {
                    foreach (['sms_appkey_tx','sms_appid_tx'] as $key => $val) {
                        if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                            $is_conf = 0;
                        }
                    }
                } else {
                    foreach (['sms_appkey','sms_secretkey'] as $key => $val) {
                        if (isset($sms_arr[$val]) && empty($sms_arr[$val])) {
                            $is_conf = 0;
                        }
                    }
                }
            }

            $smsTemp = Db::name('sms_template')->where(["send_scene"=>30,"sms_type"=>$sms_type,'lang'=>get_admin_lang()])->find();
            if (empty($is_conf) || empty($smsTemp['sms_sign']) || empty($smsTemp['sms_tpl_code']) || empty($smsTemp['tpl_content'])){
                $this->error("未配置云短信接口或短信模板，请确认配置好再操作", url('System/sms'));
            } else {
                Db::name('sms_template')->where(["send_scene"=>$smsTemp['send_scene']])->update(['is_open'=>1]);
                $sms_test_mobile = $this->ddosLogic->get_first_test_mobile();
                $admin_mobile = trim($this->admin_info['mobile']);
                if (!check_mobile($admin_mobile)) {
                    $admin_mobile = '';
                }
                if (!empty($admin_mobile)) {
                    $sms_test_mobile = $admin_mobile;
                }
                // 测试发送短信
                $res = sendSms($smsTemp['send_scene'], $sms_test_mobile, array('content'=>mt_rand(100000, 999999)), 0, $sms_config);
                if (isset($res['status']) && ($res['status'] == 1 || preg_match('/(BUSINESS_LIMIT_CONTROL)/i', $res['msg']))) {
                    Db::name('sms_log')->where(['source'=>$smsTemp['send_scene'], 'mobile'=>$sms_test_mobile])->delete();
                    if (!empty($admin_mobile)) {
                        $this->success("二次验证短信将发送至管理员设置好的手机号");
                    } else {
                        $this->success("登录后台二次验证短信将发送至手机号：{$sms_test_mobile}<br/>如果每个管理员需要单独接收，请填写账号里的手机号");
                    }
                } else {
                    if (!empty($res['msg']) && preg_match('/(MOBILE_NUMBER_ILLEGAL)/i', $res['msg'])) {
                        $this->error("短信配置里的管理员手机号无效", url('System/sms'));
                    } else {
                        $this->error("未配置云短信接口或短信模板，请确认配置好再操作", url('System/sms'));
                    }
                }
            }
        }
        else if (2 == $twofactor_check_type) {
            $security_ask_open = tpSetting('security.security_ask_open');
            if (empty($security_ask_open)) {
                $this->error("未配置密保问题，请确认配置好再操作", url('Security/popup_second'));
            }
        }
        else {
            $smtp_config = tpCache('smtp');
            $smtpTemp = Db::name('smtp_tpl')->where(["send_scene"=>30,'lang'=>get_admin_lang()])->find();
            if (empty($smtp_config['smtp_user']) || empty($smtp_config['smtp_from_eamil']) || empty($smtpTemp['tpl_title']) || empty($smtpTemp['tpl_content'])){
                $this->error("未配置邮箱接口，请确认配置好再操作", url('System/smtp'));
            } else {
                Db::name('smtp_tpl')->where(["send_scene"=>$smtpTemp['send_scene']])->update(['is_open'=>1]);
                $smtp_from_eamil = $this->ddosLogic->get_first_test_email();
                $admin_email = trim($this->admin_info['email']);
                if (!check_email($admin_email)) {
                    $admin_email = '';
                }
                if (!empty($admin_email)) {
                    $smtp_from_eamil = $admin_email;
                }
                // 测试发送邮箱
                $title = '登录双因子-测试发送邮箱';
                $content = '测试发送成功，可以启用登录双因子功能';
                $res = send_email($smtp_from_eamil, $title, $content, 0, $smtp_config);
                if (isset($res['code']) && $res['code'] == 1) {
                    if (!empty($admin_email)) {
                        $this->success("二次验证邮件将发送至管理员设置好的邮箱");
                    } else {
                        $this->success("登录后台二次验证邮件将发送至邮箱：{$smtp_from_eamil}<br/>如果每个管理员需要单独接收，请填写账号里的邮箱地址");
                    }
                } else {
                    if (!empty($res['msg']) && preg_match('/(non-existent(\s+)account)/i', $res['msg'])) {
                        $this->error("邮箱配置里的管理员邮箱无效", url('System/smtp'));
                    } else {
                        $this->error("未配置邮箱接口，请确认配置好再操作", url('System/smtp'));
                    }
                }
            }
        }
        $this->success("检测通过");
    }

    /**
     * 密保问题设置
     * @return [type] [description]
     */
    public function popup_second()
    {
        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        // 安全验证配置
        $security = tpSetting('security');
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    // 图片木马的开关设置
    public function illegal_check_open()
    {
        $msg = "";
        $value = input('post.value/d');
        if (empty($value)) {
            $msg = "开启成功";
        } else {
            $msg = "关闭成功";
        }
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('weapp', ['weapp_check_illegal_open' => $value], $val['mark']);
            }
        } else { // 单语言
            tpCache('weapp', ['weapp_check_illegal_open' => $value]);
        }
        /*--end*/
        $this->success($msg);
    }
}