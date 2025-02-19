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
namespace app\user\controller;

use think\Db;
use think\Config;
use think\Page;
use think\Verify;
use app\user\logic\SmtpmailLogic;

class Users extends Base
{
    public $smtpmailLogic;

    public function _initialize()
    {
        parent::_initialize();
        $this->smtpmailLogic      = new SmtpmailLogic;
        $this->users_db           = Db::name('users');      // 会员数据表
        $this->users_level_db     = Db::name('users_level'); // 会员等级表
        $this->users_parameter_db = Db::name('users_parameter'); // 会员属性表
        $this->users_list_db      = Db::name('users_list'); // 会员属性信息表
        $this->users_config_db    = Db::name('users_config');// 会员配置表
        $this->users_money_db     = Db::name('users_money');// 会员金额明细表
        $this->smtp_record_db     = Db::name('smtp_record');// 发送邮箱记录表
        $this->sms_log_db         = Db::name('sms_log');// 发送手机记录表
        // 微信配置信息
        $this->pay_wechat_config = unserialize(getUsersConfigData('pay.pay_wechat_config'));

        // 查询部分模型开启信息 下载 视频 问答
        $partChannel = [];
        $usersOrderUrl = '';
        if (in_array(ACTION_NAME, ['index', 'article_index', 'download_index', 'media_index'])) {
            $where = [
                'nid' => ['IN', ['media', 'article', 'download']]
            ];
            $partChannel = Db::name('channeltype')->where($where)->field('id, nid, ntitle, status, data')->order('id asc')->getAllWithIndex('nid');
            foreach ($partChannel as $key => $value) {
                $value['data'] = !empty($value['data']) ? json_decode($value['data'], true) : [];
                if ('media' == $key && !empty($this->eyou['global']['php_servicemeal']) && 1 < intval($this->eyou['global']['php_servicemeal'])) {
                    $value['data']['is_media_pay'] = 1;
                }
                if (empty($usersOrderUrl)) {
                    if (!empty($value['data']['is_article_pay']) && 1 === intval($value['data']['is_article_pay'])) {
                        $usersOrderUrl = url('user/Users/article_index');
                    } else if (!empty($value['data']['is_download_pay']) && 1 === intval($value['data']['is_download_pay'])) {
                        $usersOrderUrl = url('user/Users/download_index');
                    } else if (!empty($value['data']['is_media_pay']) && 1 === intval($value['data']['is_media_pay'])) {
                        $usersOrderUrl = url('user/Users/media_index');
                    }
                }
                $partChannel[$key] = $value;
            }
        }
        $this->assign('partChannel', $partChannel);
        $this->assign('usersOrderUrl', $usersOrderUrl);

        $isCount = Db::name('users_menu')->where([
            'mca'   => 'plugins/PointsShop/index',
        ])->count();
        if (empty($isCount)) {
            Db::name('users_menu')->add([
                'title'         => '积分商城',
                'mca'           => 'plugins/PointsShop/index',
                'is_userpage'   => 0,
                'sort_order'    => 100,
                'status'        => 1,
                'lang'          => 'cn',
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
        }
    }
    
    // 会员中心首页
    public function index()
    {
        if (1 == config('global.opencodetype')) {
            return action('user/Users/index2');
        }
        
        if ($this->usersTplVersion == 'v1') {
            return action('user/Users/info');
        }

        $result = [];
        // 资料信息
        $result['users_para'] = model('Users')->getDataParaList($this->users_id);
        $users_para_list = convert_arr_key($result['users_para'], 'para_id');
        $this->assign('users_para', $users_para_list);

        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);

        // 是否绑定了微站点，否则自动绑定
        $referurl = '';
        if (!empty($this->users_id)) {
            auto_bind_wechatlogin($this->users, $referurl);
            if (!empty($referurl)) {
                header('Location: '. $referurl);
                exit;
            }
        }

        //其他数据
        $others = array();
        $users_id = $this->users_id;
        //收藏数
        $others['collect_num'] = Db::name('users_collection')->where(['users_id'=>$users_id])->count("id");
        //足迹
        $others['footprint_num'] = Db::name('users_footprint')->where(['users_id'=>$users_id])->count('id');
        //今日签到信息
        $others['signin_conf'] = getUsersConfigData('score');
        if ($others['signin_conf'] && isset($others['signin_conf']['score_signin_status']) && $others['signin_conf']['score_signin_status'] == 1) {
            $now_time = time();
            $today_start = mktime(0,0,0,date("m",$now_time),date("d",$now_time),date("Y",$now_time));
            $today_end = mktime(23,59,59,date("m",$now_time),date("d",$now_time),date("Y",$now_time));
            $others['signin_info'] = Db::name('users_signin')->where(['users_id'=>$users_id,'add_time'=>['BETWEEN',[$today_start,$today_end]]])->value("id");
        }

        //查询插件信息
        $weapp_menu_info = Db::name('users_menu')->field("id,title,version,mca")->where(['version'=>'weapp','status'=>1])->select();
        $others['weapp_menu_info'] = [];
        if ($weapp_menu_info) {
            $weapp_row = Db::name('weapp')->field("code,name,config")->where(['status'=>1])->getAllWithIndex('code');
            foreach ($weapp_menu_info as $k=>$v) {
                preg_match_all('/\/(\w+)\//i', $v['mca'],$preg_res);
                if (!empty($preg_res[1])) {
                    $code_str = $preg_res[1][0];
                    $weapp_info = empty($weapp_row[$code_str]) ? [] : $weapp_row[$code_str];
                    if (empty($weapp_info)) {
                        unset($weapp_menu_info[$k]);
                        continue;
                    }
                    $weapp_menu_info[$k]['litpic'] = json_decode($weapp_info['config'],true)['litpic'];
                }
            }
            $others['weapp_menu_info'] = $weapp_menu_info;
        }
        $this->assign('others', $others);

        //查询部分模型开启信息  下载 视频 问答
        $part_channel = Db::name('channeltype')
            ->where('nid','in',['ask','download','media','article'])
            ->field('nid,status,data')
            ->getAllWithIndex('nid');
        if (!empty($part_channel['article']['data'])){
            $part_channel['article']['data'] = json_decode($part_channel['article']['data'], true);
        }
        if (!empty($part_channel['download']['data'])){
            $part_channel['download']['data'] = json_decode($part_channel['download']['data'], true);
        }
        $this->assign('part_channel', $part_channel);

        // 多语言
        $condition_bottom['a.status'] = array('eq', 1);
        $condition_bottom['a.display'] = array('eq', 1);
        $condition_bottom['a.lang'] = $this->home_lang;
        $bottom_menu_list = Db::name('users_bottom_menu')->field('a.*')
            ->alias('a')
            ->where($condition_bottom)
            ->order('a.sort_order asc, a.id asc')
            ->limit(5)
            ->select();
        static $request = null;
        if (null === $request) {
            $request = \think\Request::instance();
        }
        $root_dir = ROOT_DIR.'/';
        foreach ($bottom_menu_list as $k => $v){
            if ('xingxing' == $v['icon']){
                if (!is_http_url($v['mca'])){
                    $bottom_menu_list[$k]['mca'] = $request->domain().$root_dir.$v['mca'];
                }
            }
        }

        $this->assign('bottom_menu_list', $bottom_menu_list);

        // 问候语
        $hour = date('H');
        $greeting = foreign_lang('users48', $this->home_lang);
        if (0 < intval($hour) && intval($hour) < 12) {
            $greeting = foreign_lang('users49', $this->home_lang);;
        } else if (12 < intval($hour) && intval($hour) < 19) {
            $greeting = foreign_lang('users50', $this->home_lang);;
        }
        $this->assign('greeting', $greeting);

        // 积分兑换是否已在用
        $shopLogic = new \app\admin\logic\ShopLogic;
        $useFunc = $shopLogic->useFuncLogic();
        $this->assign('useFunc', $useFunc);

        $clear_session_url = $this->root_dir."/index.php?m=api&c=Ajax&a=clear_session";
        $replace = <<<EOF
    <script type="text/javascript">
        clear_session();
        function clear_session()
        {
            $.ajax({
                url: "{$clear_session_url}",
                type: 'post',
                dataType: 'JSON',
                data: {_ajax: 1},
                success: function(res){
                }
            });
        }
    </script>
</body>
EOF;
        $html = $this->fetch('users_welcome');
        $html = str_ireplace('</body>', $replace, $html);
        
        return $html;
    }

    // 个人信息
    public function info()
    {
        $result = [];
        // 资料信息
        $result['users_para'] = model('Users')->getDataParaList($this->users_id);
        $this->assign('users_para', $result['users_para']);

        // 邮箱发送限制时间
        $this->assign('email_send_time', config('global.email_send_time'));

        // 手机发送限制时间
        $this->assign('mobile_send_time', config('global.mobile_send_time'));

        // 菜单名称
        $result['title'] = Db::name('users_menu')->where([
            'mca'  => 'user/Users/index',
        ])->getField('title');

        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);

        $thirdparty = [];
        $loginApp = Db::name("weapp")->where(['code'=>['in',['QqLogin','WxLogin']]])->getAllWithIndex('code');
        //qq绑定信息
        if (!empty($loginApp['QqLogin']) && $loginApp['QqLogin']['status'] == 1){  //qq登陆插件安装且处于开启状态
            $qqlogin_config = json_decode($loginApp['QqLogin']['config'],true);
            if (!empty($qqlogin_config['version']) && $qqlogin_config['version'] >= 'v1.4.2'){
                try{
                    $thirdparty['qq'] = Db::name("weapp_qqlogin")->where(['users_id'=>$this->users_id])->find();
                    $thirdparty['is_qq'] = 1;
                }catch(\Exception $e){}
            }
        }
        //微信绑定信息
        if (!empty($loginApp['WxLogin']) && $loginApp['WxLogin']['status'] == 1){  //qq登陆插件安装且处于开启状态
            $wxlogin_config = json_decode($loginApp['WxLogin']['config'],true);
            if (!empty($wxlogin_config['version']) && $wxlogin_config['version'] >= 'v1.2.2'){
                try{
                    $thirdparty['wx'] = Db::name("weapp_wxlogin")->where(['users_id'=>$this->users_id])->find();
                    $thirdparty['is_wx'] = 1;
                }catch(\Exception $e){}
            }
        }
        $this->assign('thirdparty',$thirdparty);

        /*等保密码复杂度验证 start*/
        $pwdJsCode = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $pwdJsCode = $equalLogic->pwdJsCode();
        }
        if ('close' == $pwdJsCode) {
            $pwdJsCode = '';
        }
        $this->assign('pwdJsCode', $pwdJsCode);
        /*等保密码复杂度验证 end*/

        $html = $this->fetch('users_centre');

        // 会员模板版本号
        if ($this->usersTplVersion == 'v1') {
            /*第三方注册的用户，无需修改登录密码*/
            if (!empty($this->users['thirdparty'])) {
                $html = str_ireplace('onclick="ChangePwdMobile();"', 'onclick="ChangePwdMobile();" style="display: none;"', $html);
                $html = str_ireplace('onclick="ChangePwd();"', 'onclick="ChangePwd();" style="display: none;"', $html);
            }
            /*end*/

            // 美化昵称输入框
            $html = str_ireplace('type="text" name="nickname"', 'type="text" name="nickname" class="input-txt"', $html);
        }

        $token_input = token('__token_users_centre_update__');
        $replace =<<<EOF
    {$token_input}
</form>
EOF;
        $html = str_ireplace('</form>', $replace, $html);

        return $html;
    }

    // 会员选择登陆方式界面
    public function users_select_login()
    {
        // 若存在则调转至会员中心
        if ($this->users_id > 0) {
            $this->redirect('user/Users/centre');
            exit;
        }
        // 跳转链接
        $referurl = session('eyou_referurl');
        if (empty($referurl)) {
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url("user/Users/centre");
            $referurl = strip_tags($referurl);
            session('eyou_referurl', $referurl);
        }
        $this->assign('referurl', $referurl);

        // 拼装url
        $result = [
            'wechat_url'  => url("user/Users/ajax_wechat_login"),
            'website_url' => $this->root_dir . "/index.php?m=user&c=Users&a=login&website=website",
        ];

        // 若为微信端并且开启微商城模式则重定向
        if (isWeixin() && isMobile() && !empty($this->usersConfig['shop_micro'])) {
            $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
            if (!empty($WeChatLoginConfig)) {
                $this->redirect($result['wechat_url']);
            }
        } else if (isWeixin() && !isMobile()) {
            $this->redirect(url('user/Users/login'));
        }

        // 若后台功能设置-登录设置中，微信端本站登录为关闭状态，则直接跳转到微信授权页面
        if (isset($this->usersConfig['users_open_website_login']) && empty($this->usersConfig['users_open_website_login'])) {
            $this->redirect($result['wechat_url']);
            exit;
        }

        // 数据加载
        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);
        
        // 默认开启验证码
        $is_vertify          = 1;
        $users_login_captcha = config('captcha.users_login');
        if (!function_exists('imagettftext') || empty($users_login_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        return $this->fetch('users_select_login');
    }

    // 使用ajax微信授权登陆
    public function ajax_wechat_login()
    {
        $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
        // 微信授权登陆
        if (!empty($WeChatLoginConfig['appid']) && !empty($WeChatLoginConfig['appsecret'])) {
            if (isMobile() && isWeixin()) {
                // 判断登陆成功跳转的链接，若为空则默认会员中心链接并存入session
                $referurl = session('eyou_referurl');
                if (empty($referurl)) {
                    $referurl = url('user/Users/index', '', true, true);
                    session('eyou_referurl', $referurl);
                }

                // 获取微信配置授权登陆
                $appid     = $WeChatLoginConfig['appid'];
                $NewUrl    = urlencode(url('user/Users/get_wechat_info', '', true, true));
                $ReturnUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $NewUrl . "&response_type=code&scope=snsapi_userinfo&state=eyoucms&#wechat_redirect";

                if (isset($this->usersConfig['users_open_website_login']) && empty($this->usersConfig['users_open_website_login'])) {
                    $this->redirect($ReturnUrl);
                } else {
                    if (IS_AJAX_POST) {
                        $this->success('授权成功！', $ReturnUrl);
                    } else {
                        $this->redirect($ReturnUrl);
                    }
                }
            }
            $this->error('非手机端微信、小程序，不可以使用微信登陆，请选择本站登陆！');
        }
        $this->error('后台微信配置尚未配置AppSecret，不可以微信登陆，请选择本站登陆！');

    }

    // 在微信端，非微站点登录成功后，进行授权获取openid
    public function auto_bind_wechat_info()
    {
        $eyou_referurl = session('eyou_referurl');
        if (empty($eyou_referurl)) {
            $eyou_referurl = url('user/Users/index', '', true, true);
        }

        // 微信配置信息
        $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
        $appid  = $WeChatLoginConfig['appid'];
        $secret = $WeChatLoginConfig['appsecret'];
        $code   = input('param.code/s');

        // 获取到会员openid
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
        $result = json_decode(httpRequest($url), true);
        // 授权过期，请重新授权
        if (empty($result) || (!empty($result['errcode']) && !empty($result['errmsg']))) $this->error('微信授权过期，请重新授权');
        if (!empty($result['access_token'])) {
            $setting_info = tpSetting(md5($appid));
            if (!empty($setting_info['access_token'])){
                $setting_info['access_token'] = $result['access_token'];
                $setting_info['expires_time']  = getTime() + $result['expires_in'] - 1000;
            }
            tpSetting(md5($appid), $setting_info);
        }
        // 授权成功，记录授权信息并重定向回原页面
        if (!empty($result) && !empty($result['openid'])) {
            // 记录微信授权 cookie
            model('ShopPublicHandle')->weChatauthorizeCookie($this->users_id, 'set', ['openid' => $result['openid'], 'expire' => 86400]);
        }
        // 重定向回原页面
        $this->redirect($eyou_referurl);

        // // 获取到会员openid
        // $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
        // $data          = httpRequest($get_token_url);
        // $WeChatData    = json_decode($data, true);
        // if (empty($WeChatData) || (!empty($WeChatData['errcode']) && !empty($WeChatData['errmsg']))) {
        //     session('auto_bind_wechat_info', '-1');
        //     $this->redirect($eyou_referurl);
        //     exit;
        // }

        // // 获取会员信息
        // $get_userinfo = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $WeChatData["access_token"] . '&openid=' . $WeChatData["openid"] . '&lang=zh_CN';
        // $UserInfo     = httpRequest($get_userinfo);
        // $UserInfo     = json_decode($UserInfo, true);
        // if (empty($UserInfo['nickname']) && empty($UserInfo['headimgurl'])) {
        //     session('auto_bind_wechat_info', '-1');
        //     $this->redirect($eyou_referurl);
        //     exit;
        // }
        // $UserInfo['unionid'] = !empty($UserInfo['unionid']) ? $UserInfo['unionid'] : '';
        
        // $Users = $this->users_db->where(['users_id'=>$this->users_id])->find();
        // if (!empty($Users)) {
        //     if (empty($Users['union_id']) && !empty($UserInfo['unionid'])){
        //         $row = Db::name('users')->where(['union_id'=>$UserInfo['unionid']])->find();
        //         if (empty($row)) {
        //             $Users['union_id'] = $UserInfo['unionid'];
        //             $this->users_db->where('users_id', $Users['users_id'])->update(['union_id'=>$UserInfo['unionid'],'update_time'=>getTime()]);
        //         }
        //     }

        //     if (!empty($UserInfo['openid'])) {
        //         $wxlogin_info = [];
        //         if (is_dir('./weapp/WxLogin/')) {
        //             $wxlogin_info = Db::name("weapp_wxlogin")->where(['users_id'=>$Users['users_id']])->find();
        //         }
        //         if (empty($Users['open_id']) || (isset($wxlogin_info['openid']) && $Users['open_id'] == $wxlogin_info['openid'])) {
        //             $row = Db::name('users')->where(['union_id'=>$UserInfo['openid']])->find();
        //             if (empty($row)) {
        //                 $Users['open_id'] = $UserInfo['openid'];
        //                 $this->users_db->where('users_id', $Users['users_id'])->update(['open_id'=>$UserInfo['openid'],'update_time'=>getTime()]);
        //             }
        //         }
        //     }

        //     // 已注册
        //     session('users_id', $Users['users_id']);
        //     session('users', $Users);
        //     session('eyou_referurl', '');
        //     cookie('users_id', $Users['users_id']);
        //     $this->redirect($eyou_referurl);
        //     exit;
        // } else {
        //     session('auto_bind_wechat_info', '-1');
        //     $this->redirect($eyou_referurl);
        //     exit;
        // }
    }

    // 授权之后，获取会员信息
    public function get_wechat_info()
    {
        $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];

        // 微信配置信息
        $appid  = $WeChatLoginConfig['appid'];
        $secret = $WeChatLoginConfig['appsecret'];
        $code   = input('param.code/s');

        // 获取到会员openid
        $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
        $data          = httpRequest($get_token_url);
        $WeChatData    = json_decode($data, true);
        if (empty($WeChatData) || (!empty($WeChatData['errcode']) && !empty($WeChatData['errmsg']))) {
            $this->error('AppSecret错误或已过期', $this->root_dir.'/');
        }

        if (!empty($WeChatData['access_token'])) {
            $setting_info = tpSetting(md5($appid));
            if (!empty($setting_info['access_token'])){
                $setting_info['access_token'] = $WeChatData['access_token'];
                $setting_info['expires_time']  = getTime() + $result['expires_in'] - 1000;
            }
            tpSetting(md5($appid), $setting_info);
        }

        // 获取会员信息
        $get_userinfo = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $WeChatData["access_token"] . '&openid=' . $WeChatData["openid"] . '&lang=zh_CN';
        $UserInfo     = httpRequest($get_userinfo);
        $UserInfo     = json_decode($UserInfo, true);
        if (empty($UserInfo['nickname']) && empty($UserInfo['headimgurl'])) {
            $this->error('用户授权异常，建议清理手机缓存再进行登录', $this->root_dir.'/');
        }
        $UserInfo['unionid'] = !empty($UserInfo['unionid']) ? $UserInfo['unionid'] : '';
        
        $Users = [];
        if (!empty($UserInfo['unionid'])){
            // 查询这个unionid是否已注册
            $where = [
                'union_id' => $UserInfo['unionid'],
            ];
            $Users = $this->users_db->where($where)->find();
            if (empty($Users)){
                $Users = Db::name('wx_users')->where('unionid',$UserInfo['unionid'])->find();
            }
        }
        if (empty($Users)){
            //根据openid和空union_id查询是否为老用户
            $where = [
                'open_id' => $UserInfo['openid'],
            ];
            $Users = $this->users_db->where($where)->find();
            if (empty($Users)){
                $Users = Db::name('wx_users')->where('openid',$UserInfo['openid'])->find();
            }
        }
        if (!empty($Users)) {
            if (empty($Users['union_id']) && !empty($UserInfo['unionid'])){
                $Users['union_id'] = $UserInfo['unionid'];
                $this->users_db->where('users_id', $Users['users_id'])->update(['union_id'=>$UserInfo['unionid'],'update_time'=>getTime()]);
            }
            if (!empty($UserInfo['openid'])) {
                $wxlogin_info = [];
                if (is_dir('./weapp/WxLogin/')) {
                    $wxlogin_info = Db::name("weapp_wxlogin")->where(['users_id'=>$Users['users_id']])->find();
                }
                if (empty($Users['open_id']) || (isset($wxlogin_info['openid']) && $Users['open_id'] == $wxlogin_info['openid'])) {
                    $Users['open_id'] = $UserInfo['openid'];
                    $this->users_db->where('users_id', $Users['users_id'])->update(['open_id'=>$UserInfo['openid'],'update_time'=>getTime()]);
                }
            }
            // 已注册
            $eyou_referurl = session('eyou_referurl');
            if (empty($eyou_referurl)) {
                $eyou_referurl = url('user/Users/index', '', true, true);
            }
            // 登录后的业务逻辑
            session('eyou_referurl', '');
            model('EyouUsers')->loginAfter($Users);
            $this->redirect($eyou_referurl);
        } else {
            // 未注册
            $username = substr($WeChatData['openid'], 6, 8);
            // 查询用户名是否已存在
            $result = $this->users_db->where('username', $username)->count();
            if (!empty($result)) {
                $username = $username . rand('100,999');
            }
            // 新增会员和微信绑定
            $UsersData = [
                'username'       => $username,
                'nickname'       => filterNickname($UserInfo['nickname']),
                'open_id'        => $WeChatData['openid'],
                'password'       => '', // 密码默认为空
                'reg_time'       => getTime(),
                'last_ip'        => clientIP(),
                'last_login'     => getTime(),
                'is_activation'  => 1, // 微信注册会员，默认开启激活
                'register_place' => 2, // 前台微信注册会员
                'thirdparty'     => 5, // 微站点
                'login_count'    => Db::raw('login_count+1'),
                'head_pic'       => $UserInfo['headimgurl'],
                'union_id'       => $UserInfo['unionid'],
                'lang'           => $this->home_lang,
            ];
            //来源
            if (isMobile()){
                if (isWeixin()){
                    $UsersData['source'] = 3;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
                }else{
                    $UsersData['source'] = 2;//1-PC端 2-H5 3-微信公众号 4-微信小程序 5-百度小程序 6-抖音小程序
                }
            }

            // 查询默认会员级别，存入会员表
            $level_id           = $this->users_level_db->where([
                'is_system' => 1,
            ])->getField('level_id');
            $UsersData['level'] = $level_id;

            $users_id = $this->users_db->add($UsersData);
            if (!empty($users_id)) {
                if (6 > strlen($users_id)){
                    $users_id = sprintf("%06d",$users_id);//不足6位补0
                }
                $username = 'U'.$users_id;
                $username = rand_username($username, 'U', 3);
                $this->users_db->where('users_id', $users_id)->update(['username'=>$username,'update_time'=>getTime()]);
                // 新增成功，将会员信息存入session
                $eyou_referurl = session('eyou_referurl');
                if (empty($eyou_referurl)) {
                    $eyou_referurl = url('user/Users/index', '', true, true);
                }
                // 登录后的业务逻辑
                session('eyou_referurl', '');
                $GetUsers = $this->users_db->where('users_id', $users_id)->find();
                model('EyouUsers')->loginAfter($GetUsers);
                $this->redirect($eyou_referurl);
            } else {
                $this->error('未知错误，无法继续！');
            }
        }
    }

    // 登陆
    public function login()
    {
        // 若已登录则重定向
        if ($this->users_id > 0) $this->redirect('user/Users/centre');

        // 回跳路径
        $referurl = input('param.referurl/s', null, 'htmlspecialchars_decode,urldecode');
        if (empty($referurl)) {
            if (isset($_SERVER['HTTP_REFERER']) && stristr($_SERVER['HTTP_REFERER'], $this->request->host())) {
                $referurl = $_SERVER['HTTP_REFERER'];
            } else {
                $referurl = url("user/Users/centre");
            }
        }
        $referurl = strip_tags($referurl);

        // 切换账号后，在动态URL模式下登录404，与付费文档有关
        $referurl_2 = input('param.referurl/s', null, 'htmlspecialchars_decode');
        if (stristr($referurl_2, '&referurl=')) {
            parse_str($referurl_2, $parse);
            $referurl = str_replace("&referurl={$parse['referurl']}", "&referurl=".urlencode($parse['referurl']), $referurl);
        }

        session('eyou_referurl', $referurl);

        // 若为微信端并且开启微商城模式则重定向直接使用微信登录
        if (isWeixin() && isMobile() && !empty($this->usersConfig['shop_micro'])) {
            $WeChatLoginConfig = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
            if (!empty($WeChatLoginConfig) && !IS_AJAX) {
                $this->redirect('user/Users/ajax_wechat_login');
                exit;
            }
        }

        // 若为微信端并且没有开启微商城模式则重定向到登录选择页
        $website = input('param.website/s');
        if (isWeixin() && isMobile() && empty($website)) $this->redirect('user/Users/users_select_login');

        // 默认开启验证码
        $is_vertify          = 1;
        $users_login_captcha = config('captcha.users_login');
        if (!function_exists('imagettftext') || empty($users_login_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post             = input('post.');
            $post['username'] = trim($post['username']);

            if (empty($post['username'])) {
                $this->error(foreign_lang('users16', $this->home_lang), null, ['status' => 1]);
            } else if (!preg_match("/^[\x{4e00}-\x{9fa5}\w\-\_\.\@\#]{2,30}$/u", $post['username'])) {
                $this->error(foreign_lang('users17', $this->home_lang), null, ['status' => 1]);
            }

            if (empty($post['password']) || !trim($post['password'])) {
                $this->error(foreign_lang('users18', $this->home_lang), null, ['status' => 1]);
            }

            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error(foreign_lang('users19', $this->home_lang), null, ['status' => 1]);
                }
                $verify = new Verify();
                if (!$verify->check($post['vertify'], "users_login")) {
                    $this->error(foreign_lang('users20', $this->home_lang), null, ['status' => 'vertify']);
                }
            }

            $where = [];
            $rawStr = " username = '{$post['username']}' ";
            if (check_mobile($post['username'])) {
                $rawStr .= " OR (mobile = '{$post['username']}' AND is_mobile = 1)";
            }
            $where[] = Db::raw($rawStr);
            $users = $this->users_db->where($where)->find();
            if (!empty($users['is_del'])) $users = [];

            $uc_uid = 0;
            if (is_dir('./weapp/UCenter/')) {
                $ucenter = new \weapp\UCenter\logic\UCenterLogic();
                $uc_uid = $ucenter->uc_login_synlogin($post, $users);
            }

            if (!empty($users)) {
                if (!empty($users['admin_id'])) {
                    // 后台账号不允许在前台通过账号密码登录，只能后台登录时同步到前台
                    $this->error(foreign_lang('users21', $this->home_lang), null, ['status' => 'vertify']);
                }

                if (empty($users['is_activation'])) {
                    $this->error(foreign_lang('users22', $this->home_lang), null, ['status' => 'vertify']);
                }

                $users_id = $users['users_id'];

                /*等保密码复杂度验证 start*/
                if (is_dir('./weapp/Equal/')) {
                    $equal_privkey = input('post.equal_privkey/s');
                    $equalLogic = new \weapp\Equal\logic\EqualLogic;
                    $equalLogic->loginLogic($post['password'], $equal_privkey);
                }
                /*等保密码复杂度验证 end*/

                $encry_password = func_encrypt($post['password'], false, pwd_encry_type($users['password']));
                if ($uc_uid > 0 || strval($users['password']) === strval($encry_password)) {

                    // 判断是前台还是后台注册的会员，后台注册不受注册验证影响，1为后台注册，2为前台注册。
                    if (2 == $users['register_place']) {
                        $usersVerificationRow = M('users_config')->where([
                            'name' => 'users_verification',
                        ])->find();
                        if ($usersVerificationRow['update_time'] <= $users['reg_time']) {
                            // 判断是否需要后台审核
                            if ($usersVerificationRow['value'] == 1 && $users['is_activation'] == 0) {
                                $this->error(foreign_lang('users23', $this->home_lang), null, ['status' => 2]);
                            }
                        }
                    }

                    // 会员users_id存入session
                    model('EyouUsers')->loginAfter($users);
                    $users_config = getUsersConfigData('users');
                    if (!empty($users_config['users_login_jump_type']) && 1 == $users_config['users_login_jump_type']){
                        $referurl = ROOT_DIR."/";//跳到首页
                    }elseif (!empty($users_config['users_login_jump_type']) && 3 == $users_config['users_login_jump_type']){
                        $referurl = url('user/Users/centre');//跳到会员中心
                    }elseif (!empty($users_config['users_login_jump_type']) && 4 == $users_config['users_login_jump_type']){
                        $referurl = htmlspecialchars_decode($users_config['users_login_jump_url']);//跳到自定义URL
                        $referurl = strip_tags($referurl);
                    }
                    // 是否绑定了微站点，否则自动绑定
                    auto_bind_wechatlogin($users, $referurl);
                    $this->success(foreign_lang('users24', $this->home_lang), $referurl);
                } else {
                    $this->error(foreign_lang('users25', $this->home_lang), null, ['status' => 'vertify']);
                }
            } else {
                $this->error(foreign_lang('users26', $this->home_lang), null, ['status' => 'vertify']);
            }
        }

        /*微信登录插件 - 判断是否显示微信登录按钮*/
        $weapp_wxlogin = 0;
        if (is_dir('./weapp/WxLogin/')) {
            $wx = Db::name('weapp')->field('data, status, config')->where(['code' => 'WxLogin'])->find();
            if (!empty($wx)) {
                $wx['data'] = !empty($wx['data']) ? unserialize($wx['data']) : [];
                if ($wx['status'] == 1 && !empty($wx['data']['login_show']) && $wx['data']['login_show'] == 1) $weapp_wxlogin = 1;
                // 使用场景 0 PC+手机 1 手机 2 PC
                $wx['config'] = json_decode($wx['config'], true);
                if (isMobile() && !in_array($wx['config']['scene'], [0,1])) {
                    $weapp_wxlogin = 0;
                } else if (!isMobile() && !in_array($wx['config']['scene'], [0,2])) {
                    $weapp_wxlogin = 0;
                }
            }
        }
        $this->assign('weapp_wxlogin', $weapp_wxlogin);
        /*end*/
        
        /*谷歌登录插件 - 判断是否显示谷歌登录按钮*/
        $googledata = [];
        $weapp_googlelogin = 0;
        if (is_dir('./weapp/GoogleLogin/')) {
            $googledata         = Db::name('weapp')->field('data,status,config')->where(['code' => 'GoogleLogin'])->find();
            if (!empty($googledata)) {
                $googledata['data'] = !empty($googledata['data']) ? unserialize($googledata['data']) : [];
                if ($googledata['status'] == 1 && !empty($googledata['data']['login_show']) && $googledata['data']['login_show'] == 1) $weapp_googlelogin = 1;
                // 使用场景 0 PC+手机 1 手机 2 PC
                $googledata['config'] = json_decode($googledata['config'], true);
                if (isMobile() && !in_array($googledata['config']['scene'], [0,1])) {
                    $weapp_googlelogin = 0;
                } else if (!isMobile() && !in_array($googledata['config']['scene'], [0,2])) {
                    $weapp_googlelogin = 0;
                }
            }
        }
        $this->assign('googledata', $googledata);
        $this->assign('weapp_googlelogin', $weapp_googlelogin);
        /*end*/

        /*QQ登录插件 - 判断是否显示QQ登录按钮*/
        $weapp_qqlogin = 0;
        if (is_dir('./weapp/QqLogin/')) {
            $qq         = Db::name('weapp')->field('data,status,config')->where(['code' => 'QqLogin'])->find();
            if (!empty($qq)) {
                $qq['data'] = !empty($qq['data']) ? unserialize($qq['data']) : [];
                if ($qq['status'] == 1 && !empty($qq['data']['login_show']) && $qq['data']['login_show'] == 1) $weapp_qqlogin = 1;
                // 使用场景 0 PC+手机 1 手机 2 PC
                $qq['config'] = json_decode($qq['config'], true);
                if (isMobile() && !in_array($qq['config']['scene'], [0,1])) {
                    $weapp_qqlogin = 0;
                } else if (!isMobile() && !in_array($qq['config']['scene'], [0,2])) {
                    $weapp_qqlogin = 0;
                }
            }
        }
        $this->assign('weapp_qqlogin', $weapp_qqlogin);
        /*end*/

        /*微博插件 - 判断是否显示微博按钮*/
        $weapp_wblogin = 0;
        if (is_dir('./weapp/Wblogin/')) {
            $wb         = Db::name('weapp')->field('data,status,config')->where(['code' => 'Wblogin'])->find();
            if (!empty($wb)) {
                $wb['data'] = !empty($wb['data']) ? unserialize($wb['data']) : [];
                if ($wb['status'] == 1 && !empty($wb['data']['login_show']) && $wb['data']['login_show'] == 1) $weapp_wblogin = 1;
                // 使用场景 0 PC+手机 1 手机 2 PC
                $wb['config'] = json_decode($wb['config'], true);
                if (isMobile() && !in_array($wb['config']['scene'], [0,1])) {
                    $weapp_wblogin = 0;
                } else if (!isMobile() && !in_array($wb['config']['scene'], [0,2])) {
                    $weapp_wblogin = 0;
                }
            }
        }
        $this->assign('weapp_wblogin', $weapp_wblogin);
        /*end*/

        /*等保密码复杂度验证 start*/
        $pwdJsCode = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $pwdJsCode = $equalLogic->pwdJsCode();
        }
        if ('close' == $pwdJsCode) {
            $pwdJsCode = '';
        }
        $this->assign('pwdJsCode', $pwdJsCode);
        /*等保密码复杂度验证 end*/

        if (1 == config('global.opencodetype')) {
            $type = input('param.type/s');
            $this->assign('type', $type);
        }

        cookie('referurl', $referurl);
        $this->assign('referurl', $referurl);
        return $this->fetch('users_login');
    }
    
    // 手机号登陆
    public function mobile_login()
    {
        if (IS_AJAX_POST) {
            $post             = input('post.');
            if (empty($post['mobile'])){
                $this->error(foreign_lang('users28', $this->home_lang), null, ['status' => 1]);
            }
            if (!check_mobile($post['mobile'])){
                $this->error(foreign_lang('users29', $this->home_lang), null, ['status' => 1]);
            }

            if (empty($post['mobile_code'])) {
                $this->error(foreign_lang('users30', $this->home_lang), null, ['status' => 1]);
            }

            // 验证验证码
            $RecordWhere = [
                'source' => 2,
                'mobile' => $post['mobile'],
                'code' => $post['mobile_code'],
                'is_use' => 0,
                'lang'   => $this->home_lang
            ];
            $is_verify = $this->sms_log_db->where($RecordWhere)->find();
            if (!empty($is_verify)){
                $RecordData  = [
                    'is_use' => 1,
                    'update_time' => getTime()
                ];
                // 更新数据
                $this->sms_log_db->where($RecordWhere)->update($RecordData);
            }else{
                $this->error(foreign_lang('users31', $this->home_lang), null, ['status' => 1]);
            }

            $users = $this->users_db->where([
                'mobile' => $post['mobile'],
                'is_del'   => 0,
            ])->find();

            if (!empty($users)) {
                if (!empty($users['admin_id'])) {
                    // 后台账号不允许在前台通过账号密码登录，只能后台登录时同步到前台
                    $this->error(foreign_lang('users21', $this->home_lang), null, ['status' => 'vertify']);
                }

                if (empty($users['is_activation'])) {
                    $this->error(foreign_lang('users22', $this->home_lang), null, ['status' => 'vertify']);
                }

                // 判断是前台还是后台注册的会员，后台注册不受注册验证影响，1为后台注册，2为前台注册。
                if (2 == $users['register_place']) {
                    $usersVerificationRow = M('users_config')->where([
                        'name' => 'users_verification',
                    ])->find();
                    if ($usersVerificationRow['update_time'] <= $users['reg_time']) {
                        // 判断是否需要后台审核
                        if ($usersVerificationRow['value'] == 1 && $users['is_activation'] == 0) {
                            $this->error(foreign_lang('users23', $this->home_lang), null, ['status' => 2]);
                        }
                    }
                }

                // 会员users_id存入session
                model('EyouUsers')->loginAfter($users);

                $referurl = input('param.referurl/s', null, 'htmlspecialchars_decode,urldecode');
                if (empty($referurl)) {
                    if (isset($_SERVER['HTTP_REFERER']) && stristr($_SERVER['HTTP_REFERER'], $this->request->host())) {
                        $referurl = $_SERVER['HTTP_REFERER'];
                    } else {
                        $referurl = url("user/Users/centre");
                    }
                }
                $referurl = strip_tags($referurl);
                session('eyou_referurl', $referurl);
                $users_config = getUsersConfigData('users');
                if (!empty($users_config['users_login_jump_type']) && 1 == $users_config['users_login_jump_type']){
                    $referurl = ROOT_DIR."/";//跳到首页
                }elseif (!empty($users_config['users_login_jump_type']) && 3 == $users_config['users_login_jump_type']){
                    $referurl = url('user/Users/centre');//跳到会员中心
                }elseif (!empty($users_config['users_login_jump_type']) && 4 == $users_config['users_login_jump_type']){
                    $referurl = htmlspecialchars_decode($users_config['users_login_jump_url']);//跳到自定义URL
                    $referurl = strip_tags($referurl);
                }

                // 是否绑定了微站点，否则自动绑定
                auto_bind_wechatlogin($users, $referurl);

                $this->success(foreign_lang('users24', $this->home_lang), $referurl);

            } else {
                $this->error(foreign_lang('users26', $this->home_lang), null, ['status' => 'vertify']);
            }
        }
        $this->error(foreign_lang('system11', $this->home_lang), null, ['status' => 'vertify']);
    }

    // 会员注册
    public function reg()
    {
        if ($this->users_id > 0) {
            $url = url('user/Users/centre');
            $this->redirect($url);
            exit;
        }

        $is_vertify        = 1; // 默认开启验证码
        $users_reg_captcha = config('captcha.users_reg');
        if (!function_exists('imagettftext') || empty($users_reg_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post             = input('post.');

            if (isset($post['username'])) {
                $post['username'] = trim($post['username']);

                $users_reg_notallow = explode(',', getUsersConfigData('users.users_reg_notallow'));
                if (!empty($users_reg_notallow)) {
                    if (in_array($post['username'], $users_reg_notallow)) {
                        $this->error(foreign_lang('users33', $this->home_lang), null, ['status' => 1]);
                    }
                }

                if (empty($post['username'])) {
                    $this->error(foreign_lang('users16', $this->home_lang), null, ['status' => 1]);
                } else if (!preg_match("/^[\x{4e00}-\x{9fa5}\w\-\_\@\#]{2,30}$/u", $post['username'])) {
                    $this->error(foreign_lang('users34', $this->home_lang), null, ['status' => 1]);
                }
            }

            if (isset($post['password'])) {
                if (empty($post['password']) || !trim($post['password'])) {
                    $this->error(foreign_lang('users35', $this->home_lang), null, ['status' => 1]);
                }
                if (empty($post['password2']) || !trim($post['password2'])) {
                    $this->error(foreign_lang('users36', $this->home_lang), null, ['status' => 1]);
                }
                /*等保密码复杂度验证 start*/
                if (is_dir('./weapp/Equal/')) {
                    $equalLogic = new \weapp\Equal\logic\EqualLogic;
                    $eqData = $equalLogic->pwdValidate($post['password']);
                    if (isset($eqData['code']) && empty($eqData['code'])) {
                        $this->error($eqData['msg']);
                    }
                }
                /*等保密码复杂度验证 end*/
            }

            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error(foreign_lang('users19', $this->home_lang), null, ['status' => 1]);
                }
            }

            if (isset($post['username'])) {
                $count = $this->users_db->where([
                    'username' => $post['username'],
                ])->count();
                if (!empty($count)) {
                    $this->error(foreign_lang('users37', $this->home_lang), null, ['status' => 1]);
                }
            }

            if (isset($post['password'])) {
                $post['password'] = trim($post['password']);
                $post['password2'] = trim($post['password2']);
                if (empty($post['password']) && empty($post['password2'])) {
                    $this->error(foreign_lang('users35', $this->home_lang), null, ['status' => 1]);
                } else {
                    if ($post['password'] != $post['password2']) {
                        $this->error(foreign_lang('users38', $this->home_lang), null, ['status' => 1]);
                    }
                }
            }

            // 处理会员属性数据
            $ParaData = [];
            if (isset($post['users_'])) {
                if (is_array($post['users_'])) {
                    $ParaData = $post['users_'];
                }
                unset($post['users_']);
            }

            // 处理提交的会员属性中必填项是否为空
            // 必须传入提交的会员属性数组
            $EmptyData = model('Users')->isEmpty($ParaData, 'reg', 'array');
            if (!empty($EmptyData)) {
                if (is_array($EmptyData)) {
                    $this->error($EmptyData['msg'], null, ['status' => 1, 'field'=>$EmptyData['field']]);
                } else {
                    $this->error($EmptyData, null, ['status' => 1]);
                }
            }

            // 处理提交的会员属性中邮箱和手机是否已存在
            // IsRequired方法传入的参数有2个
            // 第一个必须传入提交的会员属性数组
            // 第二个users_id，注册时不需要传入，修改时需要传入。
            $RequiredData = model('Users')->isRequired($ParaData, '', 'reg');
            if (!empty($RequiredData) && !is_array($RequiredData)) {
                $this->error($RequiredData, null, ['status' => 1]);
            }

            // 处理判断验证码
            if (1 == $is_vertify) {
                $verify = new Verify();
                if (!$verify->check($post['vertify'], "users_reg")) {
                    $this->error(foreign_lang('users20', $this->home_lang), null, ['status' => 'vertify']);
                }
            }

            if (is_dir('./weapp/UCenter/')) {
                $ucenter = new \weapp\UCenter\logic\UCenterLogic();
                $ucenter->uc_reg_synlogin($post, $RequiredData);
            }

            if (!empty($RequiredData['email'])) {
                // 查询会员输入的邮箱并且为找回密码来源的所有验证码
                $RecordWhere = [
                    'source'   => 2,
                    'email'    => $RequiredData['email'],
                    'users_id' => 0,
                    'status'   => 0,
                    'lang'     => $this->home_lang,
                ];
                $RecordData  = [
                    'status'      => 1,
                    'update_time' => getTime(),
                ];
                // 更新数据
                $this->smtp_record_db->where($RecordWhere)->update($RecordData);
            }

            if (!empty($RequiredData['mobile'])) {
                // 查询会员输入的邮箱并且为找回密码来源的所有验证码
                $RecordWhere = [
                    'source' => 0,
                    'mobile' => $RequiredData['mobile'],
                    'is_use' => 0,
                    'lang'   => $this->home_lang
                ];
                $RecordData  = [
                    'is_use' => 1,
                    'update_time' => getTime()
                ];
                // 更新数据
                $this->sms_log_db->where($RecordWhere)->update($RecordData);
            }

            // 会员设置
            $users_verification = !empty($this->usersConfig['users_verification']) ? $this->usersConfig['users_verification'] : 0;

            // 处理判断是否为后台审核，verification=1为后台审核。
            if (1 == $users_verification) $data['is_activation'] = 0;

            // 添加会员到会员表
            $data['username']       = !empty($post['username']) ? trim($post['username']) : 'yun'.getTime().rand(0,100);
            $data['nickname']       = !empty($post['nickname']) ? $post['nickname'] : $data['username'];
            if (0 == config('global.opencodetype')) {
                $data['password']       = func_encrypt($post['password'], false, pwd_encry_type('bcrypt'));
            }
            $data['is_mobile']      = !empty($ParaData['mobile_1']) ? 1 : 0;
            $data['is_email']       = !empty($ParaData['email_2']) ? 1 : 0;
            $data['head_pic']       = ROOT_DIR . '/public/static/common/images/dfboy.png';
            $data['reg_time']       = getTime();
            $data['last_login']     = getTime();
            $data['last_ip']        = clientIP();
            $data['register_place'] = 2;  // 注册位置，后台注册不受注册验证影响，1为后台注册，2为前台注册。
            $data['lang']           = $this->home_lang;
            //来源
            if (isMobile()){
                if (isWeixin()){
                    $data['source'] = 3;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
                }else{
                    $data['source'] = 2;//1-PC端 2-H5 3-微信公众号 4-微信小程序 5-百度小程序 6-抖音小程序
                }
            }

            $level_id      = $this->users_level_db->where([
                'is_system' => 1,
            ])->getField('level_id');
            $data['level'] = $level_id;

            /*特定场景专用*/
            $opencodetype = config('global.opencodetype');
            if (1 == $opencodetype) {
                $origin_mid = cookie('origin_mid');
                if (!empty($origin_mid)) {
                    $data['origin_mid']          = intval($origin_mid);
                }
                $origin_type = cookie('origin_type');
                if (!empty($origin_type)) {
                    $data['origin_type']         = intval($origin_type);
                }
            }
            /*end*/

            $users_id = $this->users_db->insertGetId($data);

            // 判断会员是否添加成功
            if (!empty($users_id)) {
                $data['users_id'] = $users_id;
                // 批量添加会员属性到属性信息表
                if (!empty($ParaData)) {
                    $betchData    = [];
                    $usersparaRow = $this->users_parameter_db->where([
                        'is_hidden' => 0,
                    ])->getAllWithIndex('name');
                    foreach ($ParaData as $key => $value) {
                        if (preg_match('/(_code|_vertify)$/i', $key)) {
                            continue;
                        }elseif ('imgs' == $usersparaRow[$key]['dtype']){
                            $value = array_filter($value);
                        }

                        // 若为数组，则拆分成字符串
                        if (is_array($value)) $value = implode(',', $value);
                        
                        $para_id     = intval($usersparaRow[$key]['para_id']);
                        $betchData[] = [
                            'users_id' => $users_id,
                            'para_id'  => $para_id,
                            'info'     => $value,
                            'lang'     => $this->home_lang,
                            'add_time' => getTime(),
                        ];
                    }
                    $this->users_list_db->insertAll($betchData);
                }

                // 查询属性表的手机号码和邮箱地址,拼装数组$UsersListData
                $UsersListData                = model('Users')->getUsersListData('*', $users_id);
                $UsersListData['login_count'] = 1;
                $UsersListData['update_time'] = getTime();
                if (2 == $users_verification) {
                    // 若开启邮箱验证并且通过邮箱验证则绑定到会员
                    $UsersListData['is_email'] = 1;
                    if (!isset($post['username'])) {
                        $username = rand_username();
                        $UsersListData['username']       = $username;
                        $UsersListData['nickname']       = $username;
                    }
                } else if (3 == $users_verification) {
                    // 若开启手机验证并且通过手机验证则绑定到会员
                    $UsersListData['is_mobile'] = 1;
                    if (!isset($post['username'])) {
                        $new_username = 'yun'.substr($UsersListData['mobile'], -6);
                        $username = rand_username($new_username, 'yun', 2);
                        $UsersListData['username']       = $username;
                        $UsersListData['nickname']       = $username;
                    }
                }
                // 同步修改会员信息
                $this->users_db->where('users_id', $users_id)->update($UsersListData);

                // 回跳路径
                $referurl = input('post.referurl/s', null, 'htmlspecialchars_decode,urldecode');
                $referurl = strip_tags($referurl);

                if (1 == config('global.opencodetype')) {
                    cookie('origin_type', null);
                    cookie('origin_mid', null);
                }

                session('users_id', $users_id);
                if (session('users_id')) {
                    // 注册成功后的业务逻辑
                    model('EyouUsers')->regAfter($users_id);
                    if (empty($users_verification)) {
                        // 无需审核，直接登陆
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 3]);
                    } else if (1 == $users_verification) {
                        // 需要后台审核
                        session('users_id', null);
                        $url = url('user/Users/login');
                        $this->success(foreign_lang('users40', $this->home_lang), $url, ['status' => 2]);
                    } else if (2 == $users_verification) {
                        // 注册成功
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 0]);
                    } else if (3 == $users_verification) {
                        // 注册成功
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 0]);
                    }
                } else {
                    $url = url('user/Users/login');
                    $this->success(foreign_lang('users41', $this->home_lang), $url, ['status' => 2]);
                }
            }
            $this->error(foreign_lang('system11', $this->home_lang), null, ['status' => 4]);
        }

        // 会员属性资料信息
        $users_para = model('Users')->getDataPara('reg');
        $this->assign('users_para', $users_para);

        // 跳转链接
        $referurl = input('param.referurl/s');
        if (empty($referurl)) {
            if (isset($_SERVER['HTTP_REFERER']) && stristr($_SERVER['HTTP_REFERER'], $this->request->host())) {
                $referurl = $_SERVER['HTTP_REFERER'];
            } else {
                $referurl = url("user/Users/centre");
            }
        } else {
            $referurl = urldecode($referurl);
        }
        $referurl = strip_tags($referurl);
        cookie('referurl', $referurl);
        $this->assign('referurl', $referurl);

        /*微信登录插件 - 判断是否显示微信登录按钮*/
        $weapp_wxlogin = 0;
        if (is_dir('./weapp/WxLogin/')) {
            $wx         = Db::name('weapp')->field('data,status,config')->where(['code' => 'WxLogin'])->find();
            $wx['data'] = unserialize($wx['data']);
            if ($wx['status'] == 1 && $wx['data']['login_show'] == 1) {
                $weapp_wxlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wx['config'] = json_decode($wx['config'], true);
            if (isMobile() && !in_array($wx['config']['scene'], [0,1])) {
                $weapp_wxlogin = 0;
            } else if (!isMobile() && !in_array($wx['config']['scene'], [0,2])) {
                $weapp_wxlogin = 0;
            }
        }
        $this->assign('weapp_wxlogin', $weapp_wxlogin);
        /*end*/
        
        /*谷歌登录插件 - 判断是否显示谷歌登录按钮*/
        $googledata = [];
        $weapp_googlelogin = 0;
        if (is_dir('./weapp/GoogleLogin/')) {
            $googledata         = Db::name('weapp')->field('data,status,config')->where(['code' => 'GoogleLogin'])->find();
            if (!empty($googledata)) {
                $googledata['data'] = !empty($googledata['data']) ? unserialize($googledata['data']) : [];
                if ($googledata['status'] == 1 && !empty($googledata['data']['login_show']) && $googledata['data']['login_show'] == 1) $weapp_googlelogin = 1;
                // 使用场景 0 PC+手机 1 手机 2 PC
                $googledata['config'] = json_decode($googledata['config'], true);
                if (isMobile() && !in_array($googledata['config']['scene'], [0,1])) {
                    $weapp_googlelogin = 0;
                } else if (!isMobile() && !in_array($googledata['config']['scene'], [0,2])) {
                    $weapp_googlelogin = 0;
                }
            }
        }
        $this->assign('googledata', $googledata);
        $this->assign('weapp_googlelogin', $weapp_googlelogin);
        /*end*/

        /*QQ登录插件 - 判断是否显示QQ登录按钮*/
        $weapp_qqlogin = 0;
        if (is_dir('./weapp/QqLogin/')) {
            $qq         = Db::name('weapp')->field('data,status,config')->where(['code' => 'QqLogin'])->find();
            $qq['data'] = unserialize($qq['data']);
            if ($qq['status'] == 1 && $qq['data']['login_show'] == 1) {
                $weapp_qqlogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $qq['config'] = json_decode($qq['config'], true);
            if (isMobile() && !in_array($qq['config']['scene'], [0,1])) {
                $weapp_qqlogin = 0;
            } else if (!isMobile() && !in_array($qq['config']['scene'], [0,2])) {
                $weapp_qqlogin = 0;
            }
        }
        $this->assign('weapp_qqlogin', $weapp_qqlogin);
        /*end*/

        /*微博插件 - 判断是否显示微博按钮*/
        $weapp_wblogin = 0;
        if (is_dir('./weapp/Wblogin/')) {
            $wb         = Db::name('weapp')->field('data,status,config')->where(['code' => 'Wblogin'])->find();
            $wb['data'] = unserialize($wb['data']);
            if ($wb['status'] == 1 && $wb['data']['login_show'] == 1) {
                $weapp_wblogin = 1;
            }
            // 使用场景 0 PC+手机 1 手机 2 PC
            $wb['config'] = json_decode($wb['config'], true);
            if (isMobile() && !in_array($wb['config']['scene'], [0,1])) {
                $weapp_wblogin = 0;
            } else if (!isMobile() && !in_array($wb['config']['scene'], [0,2])) {
                $weapp_wblogin = 0;
            }
        }
        $this->assign('weapp_wblogin', $weapp_wblogin);
        /*end*/

        // $rand_token_name = '__token__reg_'.get_rand_str(32, 0, 1);
        // $rand_token_value = request()->token($rand_token_name);
        // session(md5('users_mobile_reg_token'), $rand_token_name);
        // $this->assign('rand_token_name', $rand_token_name);
        // $this->assign('rand_token_value', $rand_token_value);

        /*等保密码复杂度验证 start*/
        $pwdJsCode = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $pwdJsCode = $equalLogic->pwdJsCode();
        }
        if ('close' == $pwdJsCode) {
            $pwdJsCode = '';
        }
        $this->assign('pwdJsCode', $pwdJsCode);
        /*等保密码复杂度验证 end*/

        $html = $this->fetch('users_reg');

        if (!empty($pwdJsCode) && !stristr($html, "var password_value = $.trim(password.val());")) {
            $str = <<<EOF
{$pwdJsCode}
if (password2.val() != password.val()) {
EOF;
            $html = str_ireplace('if (password2.val() != password.val()) {', $str, $html);
        }

        if (isMobile()) {
            $str = <<<EOF
<div id="update_mobile_file" style="display: none;">
    <form id="form1" style="text-align: center;" >
        <input type="button" value="点击上传" onclick="up_f.click();" class="btn btn-primary form-control"/><br>
        <p><input type="file" id="up_f" name="up_f" onchange="MobileHeadPic();" style="display:none"/></p>
    </form>
</div>
</body>
EOF;
            $html = str_ireplace('</body>', $str, $html);
        }

        return $html;
    }

    // 会员手机注册
    public function mobile_reg()
    {
        if (IS_AJAX_POST) {
            $post             = input('post.');
            if (empty($post['mobile'])){
                $this->error(foreign_lang('users28', $this->home_lang), null, ['status' => 1]);
            }
            if (!check_mobile($post['mobile'])){
                $this->error(foreign_lang('users29', $this->home_lang), null, ['status' => 1]);
            }

            //查询手机号是否已经注册过
            $is_reg = Db::name('users')->where('mobile',$post['mobile'])->find();
            if (!empty($is_reg)){
                $this->error(foreign_lang('users32', $this->home_lang), null, ['status' => 1]);
            }

            if (empty($post['mobile_code'])) {
                $this->error(foreign_lang('users30', $this->home_lang), null, ['status' => 1]);
            }

            // 验证验证码
            $RecordWhere = [
                'source' => 0,
                'mobile' => $post['mobile'],
                'code' => $post['mobile_code'],
                'is_use' => 0,
                'lang'   => $this->home_lang
            ];
            $is_verify = $this->sms_log_db->where($RecordWhere)->find();
            if (!empty($is_verify)){
                $RecordData  = [
                    'is_use' => 1,
                    'update_time' => getTime()
                ];
                // 更新数据
                $this->sms_log_db->where($RecordWhere)->update($RecordData);
            }else{
                $this->error(foreign_lang('users31', $this->home_lang), null, ['status' => 1]);
            }

            // 会员设置
            $users_verification = !empty($this->usersConfig['users_verification']) ? $this->usersConfig['users_verification'] : 0;

            // 处理判断是否为后台审核，verification=1为后台审核。
            if (1 == $users_verification) $data['is_activation'] = 0;

            // 添加会员到会员表
            $data['username'] = rand_username('tel' . substr($post['mobile'], -6), 'tel');
            $data['nickname']       = $data['username'];
            $data['is_mobile']      = !empty($post['mobile']) ? 1 : 0;
            $data['mobile']         = $post['mobile'];
            $data['head_pic']       = ROOT_DIR . '/public/static/common/images/dfboy.png';
            $data['reg_time']       = getTime();
            $data['last_login']     = getTime();
            $data['last_ip']        = clientIP();
            $data['register_place'] = 2;  // 注册位置，后台注册不受注册验证影响，1为后台注册，2为前台注册。
            $data['lang']           = $this->home_lang;

            $level_id      = $this->users_level_db->where([
                'is_system' => 1,
            ])->getField('level_id');
            $data['level'] = $level_id;

            //来源
            if (isMobile()){
                if (isWeixin()){
                    $data['source'] = 3;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
                }else{
                    $data['source'] = 2;//1-PC端 2-H5 3-微信公众号 4-微信小程序 5-百度小程序 6-抖音小程序
                }
            }

            $users_id = $this->users_db->insertGetId($data);
            // 判断会员是否添加成功
            if (!empty($users_id)) {
                $data['users_id'] = $users_id;
                Db::name('users_list')->insert(['users_id'=>$users_id,'para_id'=>1,'info'=>$post['mobile'],'add_time'=>getTime(),'update_time'=>getTime()]);
                // 回跳路径
                $referurl = input('post.referurl/s', null, 'htmlspecialchars_decode,urldecode');
                $referurl = strip_tags($referurl);
                session('users_id', $users_id);

                if (session('users_id')) {
                    // 注册成功后的业务逻辑
                    model('EyouUsers')->regAfter($users_id);
                    if (empty($users_verification)) {
                        // 无需审核，直接登陆
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 3]);
                    } else if (1 == $users_verification) {
                        // 需要后台审核
                        session('users_id', null);
                        $url = url('user/Users/login');
                        $this->success(foreign_lang('users40', $this->home_lang), $url, ['status' => 2]);
                    } else if (2 == $users_verification) {
                        // 注册成功
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 0]);
                    } else if (3 == $users_verification) {
                        // 注册成功
                        $url = !empty($referurl) ? $referurl : url('user/Users/centre');
                        // 是否绑定了微站点，否则自动绑定
                        auto_bind_wechatlogin($data, $url);
                        $this->success(foreign_lang('users39', $this->home_lang), $url, ['status' => 0]);
                    }
                } else {
                    $url = url('user/Users/login');
                    $this->success(foreign_lang('users41', $this->home_lang), $url, ['status' => 2]);
                }
            }
            $this->error(foreign_lang('system11', $this->home_lang), null, ['status' => 4]);
        }
    }

    // 会员中心
    public function centre()
    {
        $mca = Db::name('users_menu')->where(['is_userpage' => 1])->getField('mca');
        $mca = !empty($mca) ? $mca : 'user/Users/index';
        $this->redirect($mca);
    }

    // 修改资料
    public function centre_update()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if ($this->usersTplVersion != 'v1') {
                if (!empty($post['password_edit']) && trim($post['password_edit'])) {
                    $password_new = func_encrypt($post['password_edit'], false, pwd_encry_type('bcrypt'));
                }
            }
            /*if (empty($this->users['password'])) {
                // 密码为空则表示第三方注册会员，强制设置密码
                if (empty($post['password'])) {
                    $this->error('第三方注册会员，为确保账号安全，请设置密码。');
                } else {
                    $password_new = func_encrypt($post['password'], false, pwd_encry_type('bcrypt'));
                }
            }*/

            if (!empty($password_new) && trim($password_new)) {
                /*等保密码复杂度验证 start*/
                if (is_dir('./weapp/Equal/')) {
                    $equalLogic = new \weapp\Equal\logic\EqualLogic;
                    $eqData = $equalLogic->pwdValidate($post['password_edit']);
                    if (isset($eqData['code']) && empty($eqData['code'])) {
                        $this->error($eqData['msg']);
                    }
                }
                /*等保密码复杂度验证 end*/
            }

            $nickname = trim($post['nickname']);
            if (!empty($post['nickname']) && empty($nickname)) {
                $this->error(foreign_lang('users42', $this->home_lang));
            }

            $ParaData = [];
            if (isset($post['users_'])){
                if (is_array($post['users_'])) {
                    $ParaData = $post['users_'];
                }
                unset($post['users_']);
            }
            // 处理提交的会员属性中必填项是否为空
            // 必须传入提交的会员属性数组
            $EmptyData = model('Users')->isEmpty($ParaData);
            if (!empty($EmptyData)) $this->error($EmptyData);

            // 处理提交的会员属性中邮箱和手机是否已存在
            // IsRequired方法传入的参数有2个
            // 第一个必须传入提交的会员属性数组
            // 第二个users_id，注册时不需要传入，修改时需要传入。
            $RequiredData = model('Users')->isRequired($ParaData, $this->users_id);
            if (!empty($RequiredData)) $this->error($RequiredData);

            // 处理数据验证
            $validata = ['users_id'=>$this->users_id, '__token_users_centre_update__'=>$post['__token_users_centre_update__']];
            $error = handleEyouDataValidate('users_id', '__token_users_centre_update__', $validata);
            if (!empty($error)) $this->error($error);

            /*处理属性表的数据修改添加*/
            $row2 = $this->users_parameter_db->field('para_id,name,dtype')->getAllWithIndex('name');
            if (!empty($row2)) {
                foreach ($ParaData as $key => $value) {
                    if (!isset($row2[$key]) || in_array($row2[$key]['dtype'], ['mobile','email'])) {
                        continue;
                    }elseif ('imgs' == $row2[$key]['dtype']){
                        $value = array_filter($value);
                    }

                    // 若为数组，则拆分成字符串
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    $data                = [];
                    $para_id             = intval($row2[$key]['para_id']);
                    $where               = [
                        'users_id' => $this->users_id,
                        'para_id'  => $para_id,
                    ];
                    if ('date' == $row2[$key]['dtype'] && !empty($value)){
                        $data['info']        = strtotime($value);
                    }else{
                        $data['info']        = $value;
                    }
                    $data['update_time'] = getTime();

                    // 若信息表中无数据则添加
                    $row = $this->users_list_db->where($where)->count();
                    if (empty($row)) {
                        $data['users_id'] = $this->users_id;
                        $data['para_id']  = $para_id;
                        $data['lang']     = $this->home_lang;
                        $data['add_time'] = getTime();
                        $this->users_list_db->add($data);
                    } else {
                        $this->users_list_db->where($where)->update($data);
                    }
                }
            }

            // 查询属性表的手机和邮箱信息，同步修改会员信息
            $usersData = model('Users')->getUsersListData('*', $this->users_id);
            $usersData['nickname'] = trim($post['nickname']);
            if (!empty($password_new) && trim($password_new)) $usersData['password'] = $password_new;
            if (!empty($post['head_pic']) && !empty($post['head_pic_edit'])) $usersData['head_pic'] = $post['head_pic'];
            $usersData['update_time'] = getTime();
            $return = $this->users_db->where('users_id', $this->users_id)->update($usersData);
            if ($return !== false) {
                \think\Cache::clear('users_list');
                $this->success(foreign_lang('system12', $this->home_lang));
            }
        }
        $this->error(foreign_lang('system11', $this->home_lang));
    }

    // 更改密码
    public function change_pwd()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['oldpassword']) || !trim($post['oldpassword'])) {
                $this->error(foreign_lang('users43', $this->home_lang));
            } else if (empty($post['password']) || !trim($post['password'])) {
                $this->error(foreign_lang('users44', $this->home_lang));
            } else if ($post['password'] != $post['password2']) {
                $this->error(show_foreign_lang('重复密码与新密码不一致！'));
            }

            $users = $this->users_db->field('password')->where([
                'users_id' => $this->users_id,
            ])->find();
            if (!empty($users)) {
                $encry_password = func_encrypt($post['oldpassword'], false, pwd_encry_type($users['password']));
                if (strval($users['password']) !== strval($encry_password)) {
                    $this->error(show_foreign_lang('原密码错误，请重新输入！'));
                }

                $r = $this->users_db->where([
                    'users_id' => $this->users_id,
                ])->update([
                    'password'    => func_encrypt($post['password'], false, pwd_encry_type('bcrypt')),
                    'update_time' => getTime(),
                ]);
                if ($r) {
                    $this->success(show_foreign_lang('操作成功'));
                }
                $this->error(show_foreign_lang('操作失败'));
            }
            $this->error(show_foreign_lang('登录失效，请重新登录！'));
        }

        return $this->fetch('users_change_pwd');
    }

    // 找回密码
    public function retrieve_password()
    {
        if ($this->users_id > 0) $this->redirect('user/Users/centre');

        if (!empty($this->usersConfig['users_retrieve_password']) && 2 == $this->usersConfig['users_retrieve_password']) {
            $this->redirect('user/Users/retrieve_password_mobile');
        }

        $is_vertify                 = 1; // 默认开启验证码
        $users_retrieve_pwd_captcha = config('captcha.users_retrieve_password');
        if (!function_exists('imagettftext') || empty($users_retrieve_pwd_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post = input('post.');
            // POST数据基础判断
            if (empty($post['email'])) {
                $this->error('邮箱地址不能为空！');
            }
            if (1 == $is_vertify) {
                if (empty($post['vertify'])) {
                    $this->error(foreign_lang('users19', $this->home_lang));
                }
            }
            if (empty($post['email_code'])) {
                $this->error('邮箱验证码不能为空！');
            }

            // 判断会员输入的邮箱是否存在
            $ListWhere = array(
                'info' => array('eq', $post['email']),
            );
            $ListData  = $this->users_list_db->where($ListWhere)->field('users_id')->find();
            if (empty($ListData)) {
                $this->error('邮箱不存在，不能找回密码！');
            }

            // 判断会员输入的邮箱是否已绑定
            $UsersWhere = array(
                'email' => array('eq', $post['email']),
            );
            $UsersData  = $this->users_db->where($UsersWhere)->field('is_email')->find();
            if (empty($UsersData['is_email'])) {
                $this->error('邮箱未绑定，不能找回密码！');
            }

            // 查询会员输入的邮箱验证码是否存在
            $RecordWhere = [
                'code' => $post['email_code'],
                'lang' => $this->home_lang,
                'email' => ['eq', $post['email']],
                'users_id' => $ListData['users_id'],
            ];
            $RecordData  = $this->smtp_record_db->where($RecordWhere)->field('status,add_time,email')->find();
            if (!empty($RecordData)) {
                // 邮箱验证码是否超时
                $time                   = getTime();
                $RecordData['add_time'] += Config::get('global.email_default_time_out');
                if ('1' == $RecordData['status'] || $RecordData['add_time'] <= $time) {
                    $this->error('邮箱验证码已被使用或超时，请重新发送！');
                } else {
                    // 图形验证码判断
                    if (1 == $is_vertify) {
                        $verify = new Verify();
                        if (!$verify->check($post['vertify'], "users_retrieve_password")) {
                            $this->error('图形验证码错误，请重新输入！');
                        }
                    }

                    session('users_retrieve_password_email', $post['email']); // 标识邮箱验证通过
                    $em  = rand(10, 99) . base64_encode($post['email']) . '/=';
                    $url = url('user/Users/reset_password', ['em' => base64_encode($em)]);
                    $this->success(foreign_lang('system12', $this->home_lang), $url);
                }

            } else {
                $this->error('邮箱验证码不正确，请重新输入！');
            }
        }

        session('users_retrieve_password_email', null); // 标识邮箱验证通过

        /*检测会员邮箱属性是否开启*/
        $usersparamRow = $this->users_parameter_db->where([
            'name'      => ['LIKE', 'email_%'],
            'is_hidden' => 1,
        ])->find();
        if (!empty($usersparamRow)) {
            $this->error('会员邮箱属性已关闭，请联系网站管理员 ！');
        }
        /*--end*/

        return $this->fetch();
    }

    // 重置密码
    public function reset_password()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['password']) || !trim($post['password'])) {
                $this->error(foreign_lang('users44', $this->home_lang));
            }
            if ($post['password'] != $post['password_']) {
                $this->error(foreign_lang('users38', $this->home_lang));
            }
            /*等保密码复杂度验证 start*/
            if (is_dir('./weapp/Equal/')) {
                $equalLogic = new \weapp\Equal\logic\EqualLogic;
                $eqData = $equalLogic->pwdValidate($post['password']);
                if (isset($eqData['code']) && empty($eqData['code'])) {
                    $this->error($eqData['msg']);
                }
            }
            /*等保密码复杂度验证 end*/

            $email = session('users_retrieve_password_email');
            if (!empty($email)) {
                // 处理数据验证
                $validata = ['email'=>$email, '__token_reset_password__'=>$post['__token_reset_password__']];
                $error = handleEyouDataValidate('email', '__token_reset_password__', $validata);
                if (!empty($error)) $this->error($error);

                $data   = [
                    'password'    => func_encrypt($post['password'], false, pwd_encry_type('bcrypt')),
                    'update_time' => getTime(),
                ];
                $return = $this->users_db->where([
                    'email' => $email,
                ])->update($data);
                if ($return) {
                    session('users_retrieve_password_email', null); // 标识邮箱验证通过
                    $url = url('user/Users/login');
                    $this->success(foreign_lang('system12', $this->home_lang), $url);
                }
            }
            $this->error(foreign_lang('system11', $this->home_lang));
        }

        // 没有传入邮箱，重定向至找回密码页面
        $em    = input('param.em/s');
        $em    = base64_decode(input('param.em/s'));
        $em    = base64_decode(msubstr($em, 2, -2));
        $email = session('users_retrieve_password_email');
        if (empty($email) || !check_email($em) || $em != $email) {
            $this->redirect('user/Users/retrieve_password');
            exit;
        }
        $users = $this->users_db->where([
            'email' => $email,
        ])->find();

        if (!empty($users)) {
            // 查询会员输入的邮箱并且为找回密码来源的所有验证码
            $RecordWhere = [
                'source'   => 4,
                'email'    => $email,
                'users_id' => 0,
                'status'   => 0,
                'lang'     => $this->home_lang,
            ];
            // 更新数据
            $RecordData = [
                'status'      => 1,
                'update_time' => getTime(),
            ];
            $this->smtp_record_db->where($RecordWhere)->update($RecordData);
        }
        $this->assign('users', $users);

        /*等保密码复杂度验证 start*/
        $pwdJsCode = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $pwdJsCode = $equalLogic->pwdJsCode();
        }
        if ('close' == $pwdJsCode) {
            $pwdJsCode = '';
        }
        $this->assign('pwdJsCode', $pwdJsCode);
        /*等保密码复杂度验证 end*/
        
        $html = $this->fetch();

        if (!empty($pwdJsCode) && !stristr($html, "var password_value = password;")) {
            $str = <<<EOF
{$pwdJsCode}
if(password != password_){
EOF;
            $html = str_ireplace('if(password != password_){', $str, $html);
        }

        $token_input = token('__token_reset_password__');
        $replace =<<<EOF
    {$token_input}
</form>
EOF;
        $html = str_ireplace('</form>', $replace, $html);

        return $html;
    }

    // 手机找回密码
    public function retrieve_password_mobile()
    {
        if ($this->users_id > 0) $this->redirect('user/Users/centre');

        if (!empty($this->usersConfig['users_retrieve_password']) && 1 == $this->usersConfig['users_retrieve_password']) {
            $this->redirect('user/Users/retrieve_password');
        }

        $is_vertify = 1; // 默认开启验证码
        $users_retrieve_pwd_captcha = config('captcha.users_retrieve_password');
        if (!function_exists('imagettftext') || empty($users_retrieve_pwd_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }
        $this->assign('is_vertify', $is_vertify);

        if (IS_AJAX_POST) {
            $post = input('post.');

            // POST数据基础判断
            if (empty($post['mobile'])) $this->error(foreign_lang('users28', $this->home_lang));
            if (empty($post['mobile_code'])) $this->error(foreign_lang('users30', $this->home_lang));
            if (1 == $is_vertify) {
                if (empty($post['vertify'])) $this->error(foreign_lang('users19', $this->home_lang));
            }

            // 判断会员输入的手机是否存在
            $ListWhere = array(
                'info' => array('eq', $post['mobile']),
            );
            $ListData  = $this->users_list_db->where($ListWhere)->field('users_id')->find();
            if (empty($ListData)) $this->error(foreign_lang('users45', $this->home_lang));

            // 判断会员输入的手机是否已绑定
            $UsersWhere = array(
                'mobile' => array('eq', $post['mobile']),
            );
            $UsersData  = $this->users_db->where($UsersWhere)->field('is_mobile')->find();
            if (empty($UsersData['is_mobile'])) $this->error(foreign_lang('users46', $this->home_lang));

            // 判断验证码是否存在并且是否可用
            $RecordWhere = [
                'mobile' => $post['mobile'],
                'code'   => $post['mobile_code'],
                'lang'   => $this->home_lang
            ];
            $RecordData = $this->sms_log_db->where($RecordWhere)->field('is_use, add_time')->order('id desc')->find();
            if (!empty($RecordData)) {
                // 验证码存在
                $time = getTime();
                $RecordData['add_time'] += Config::get('global.mobile_default_time_out');
                if (1 == $RecordData['is_use'] || $RecordData['add_time'] <= $time) {
                    $this->error(foreign_lang('users47', $this->home_lang));
                } else {
                    // 处理手机验证码
                    $RecordWhere = [
                        'source'   => 4,
                        'mobile'   => $post['mobile'],
                        'is_use'   => 0,
                        'lang'     => $this->home_lang
                    ];
                    // 更新数据
                    $RecordData = [
                        'is_use'      => 1,
                        'update_time' => $time
                    ];
                    $this->sms_log_db->where($RecordWhere)->update($RecordData);
                    session('users_retrieve_password_mobile', $post['mobile']);
                    $this->success('验证通过', url('user/Users/reset_password_mobile'));
                }
            } else {
                $this->error('手机验证码不正确，请重新输入！');
            }
        }

        session('users_retrieve_password_mobile', null);

        /*检测会员邮箱属性是否开启*/
        $usersparamRow = $this->users_parameter_db->where([
            'name'      => ['LIKE', 'mobile_%'],
            'is_hidden' => 1,
        ])->find();
        if (!empty($usersparamRow)) $this->error('会员手机属性已关闭，请联系网站管理员！');
        /*--end*/

        return $this->fetch();
    }

    public function reset_password_mobile()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['password']) || !trim($post['password'])) $this->error('请输入新密码');
            if (empty($post['password_']) || !trim($post['password_'])) $this->error('请输入确认新密码');
            if ($post['password'] != $post['password_']) $this->error(foreign_lang('users38', $this->home_lang));
            /*等保密码复杂度验证 start*/
            if (is_dir('./weapp/Equal/')) {
                $equalLogic = new \weapp\Equal\logic\EqualLogic;
                $eqData = $equalLogic->pwdValidate($post['password']);
                if (isset($eqData['code']) && empty($eqData['code'])) {
                    $this->error($eqData['msg']);
                }
            }
            /*等保密码复杂度验证 end*/

            $mobile = session('users_retrieve_password_mobile');
            if (!empty($mobile)) {
                // 处理数据验证
                $validata = ['mobile'=>$mobile, '__token_reset_password_mobile__'=>$post['__token_reset_password_mobile__']];
                $error = handleEyouDataValidate('mobile', '__token_reset_password_mobile__', $validata);
                if (!empty($error)) $this->error($error);
                
                $data   = [
                    'password'    => func_encrypt($post['password'], false, pwd_encry_type('bcrypt')),
                    'update_time' => getTime()
                ];
                $return = $this->users_db->where(['mobile'=>$mobile])->update($data);
                if ($return) {
                    session('users_retrieve_password_mobile', null);
                    $url = url('user/Users/login');
                    $this->success('重置成功！', $url);
                }
            }
            $this->error('重置失败！');
        }

        // 没有手机号则重定向至找回密码页面
        $mobile = session('users_retrieve_password_mobile');
        if (empty($mobile)) $this->redirect('user/Users/retrieve_password_mobile');
        
        // 查询会员信息
        $username = $this->users_db->where(['mobile'=>$mobile])->getField('username');
        $this->assign('username', $username);

        /*等保密码复杂度验证 start*/
        $pwdJsCode = '';
        if (is_dir('./weapp/Equal/')) {
            $equalLogic = new \weapp\Equal\logic\EqualLogic;
            $pwdJsCode = $equalLogic->pwdJsCode();
        }
        if ('close' == $pwdJsCode) {
            $pwdJsCode = '';
        }
        $this->assign('pwdJsCode', $pwdJsCode);
        /*等保密码复杂度验证 end*/
        
        $html = $this->fetch();

        if (!empty($pwdJsCode) && !stristr($html, "var password_value = password;")) {
            $str = <<<EOF
{$pwdJsCode}
if(password != password_){
EOF;
            $html = str_ireplace('if(password != password_){', $str, $html);
        }

        $token_input = token('__token_reset_password_mobile__');
        $replace =<<<EOF
    {$token_input}
</form>
EOF;
        $html = str_ireplace('</form>', $replace, $html);

        return $html;
    }

    /**
     * 上传头像
     * @return [type] [description]
     */
    public function edit_users_head_pic()
    {
        if (IS_AJAX_POST) {
            $head_pic_url = input('param.filename/s', '');
            if (!empty($head_pic_url) && preg_match('/^((https:|http:)?\/\/([\w\-\_\.]+))?\/([^.\\\]+)\.([a-zA-Z]+)$/i', $head_pic_url)) {
                if (is_http_url($head_pic_url)) {
                    $data = getWeappObjectBucket();
                    if (!empty($data['domain']) && !stristr($head_pic_url, "//{$data['domain']}/")) {
                        $this->error(show_foreign_lang('上传失败'));
                    }
                } else {
                    $head_pic = handle_subdir_pic($head_pic_url, 'img', false, true);
                    if (!is_http_url($head_pic) && !file_exists('.'.$head_pic)) {
                        $this->error(show_foreign_lang('上传失败'));
                    }
                }
                
                $old_head_pic = Db::name('users')->where(['users_id'=>$this->users_id])->value('head_pic');
                $usersData['head_pic']    = $head_pic_url;
                $usersData['update_time'] = getTime();
                $return                   = $this->users_db->where([
                    'users_id' => $this->users_id,
                ])->update($usersData);
                if (false !== $return) {
                    /*同步头像到管理员表对应的管理员*/
                    if (!empty($this->users['admin_id'])) {
                        Db::name('admin')->where(['admin_id'=>$this->users['admin_id']])->update([
                            'head_pic'  => $head_pic_url,
                            'update_time'   => getTime(),
                        ]);
                    }
                    /*end*/

                    /*删除之前的头像文件*/
                    if (!is_http_url($old_head_pic) && preg_match('/^\/([^.\\\]+)\.([a-zA-Z]+)$/i', $head_pic_url)){
                        if (stristr($old_head_pic, "/uploads/user/{$this->users_id}/allimg/")) {
                            @unlink('.'.handle_subdir_pic($old_head_pic, 'img', false, true));
                        }
                    }
                    /*end*/
                    
                    if (!is_http_url($head_pic_url)){
                        $head_pic_url = func_thumb_img($head_pic_url, 250, 250);
                    }

                    $this->success(show_foreign_lang('上传成功'), null, ['head_pic'=>$head_pic_url]);
                }
            }
        }
        $this->error(show_foreign_lang('上传失败'));
    }

    //绑定邮箱
    public function bind_email()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['email']) && !empty($post['email_code'])) {
                // 邮箱格式验证是否正确
                if (!check_email($post['email'])) $this->error('邮箱格式不正确');

                // 是否已存在相同邮箱地址
                $where = [
                    'info' => $post['email'],
                    'users_id' => ['NEQ', $this->users_id],
                ];
                $isCount = $this->users_list_db->where($where)->count();
                if (!empty($isCount)) $this->error('该邮箱已存在，不可绑定');

                // 判断验证码是否存在并且是否可用
                $where = [
                    'email'    => $post['email'],
                    'code'     => $post['email_code'],
                    'users_id' => $this->users_id,
                    'lang'     => $this->home_lang,
                ];
                $smtpRecord = $this->smtp_record_db->where($where)->field('record_id, email, status, add_time')->find();
                if (!empty($smtpRecord)) {
                    // 验证码存在
                    $time = getTime();
                    $smtpRecord['add_time'] += Config::get('global.email_default_time_out');
                    if (1 === intval($smtpRecord['status']) || $smtpRecord['add_time'] <= $time) {
                        // 验证码不可用
                        $this->error('邮箱验证码已被使用或超时，请重新发送');
                    } else {
                        // 查询会员输入的邮箱并且为绑定邮箱来源的所有验证码
                        $where = [
                            'source'   => 3,
                            'email'    => $smtpRecord['email'],
                            'users_id' => $this->users_id,
                            'status'   => 0,
                            'lang'     => $this->home_lang,
                        ];
                        $update = [
                            'status' => 1,
                            'update_time' => $time,
                        ];
                        $this->smtp_record_db->where($where)->update($update);

                        // 匹配查询邮箱
                        $where = [
                            'name' => ['LIKE', "email_%"],
                            'is_system' => 1,
                        ];
                        $paraID = $this->users_parameter_db->where($where)->getField('para_id');

                        // 修改会员属性表信息
                        $where = [
                            'users_id' => ['EQ', $this->users_id],
                            'para_id'  => $paraID,
                        ];
                        $isCount = $this->users_list_db->where($where)->count();
                        if (empty($isCount)) {
                            $insert = [
                                'users_id' => $this->users_id,
                                'para_id'  => $paraID,
                                'info'     => $post['email'],
                                'lang'     => $this->home_lang,
                                'add_time' => $time,
                            ];
                            $result = $this->users_list_db->insert($insert);
                        } else {
                            $update = [
                                'info' => $post['email'],
                                'update_time' => $time,
                            ];
                            $result = $this->users_list_db->where($where)->update($update);
                        }

                        if (!empty($result)) {
                            // 同步修改会员表邮箱地址，并绑定邮箱地址到会员账号
                            $update = [
                                'is_email'    => '1',
                                'email'       => $post['email'],
                                'update_time' => $time,
                            ];
                            $this->users_db->where(['users_id'=>$this->users_id])->update($update);
                            \think\Cache::clear('users_list');
                            $this->success(foreign_lang('system12', $this->home_lang));
                        } else {
                            $this->error('未知错误，邮箱地址修改失败，请重新获取验证码');
                        }
                    }
                } else {
                    $this->error('输入的邮箱地址和邮箱验证码不一致，请重新输入');
                }
            }
        }

        $title = input('param.title/s');
        $this->assign('title', $title);
        return $this->fetch();
    }

    // 绑定手机
    public function bind_mobile()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (!empty($post['mobile']) && !empty($post['mobile_code'])) {
                // 手机格式验证是否正确
                if (!check_mobile($post['mobile'])) $this->error('手机格式不正确');

                // 是否已存在相同手机号码
                $where = [
                    'info' => $post['mobile'],
                    'users_id' => ['NEQ', $this->users_id],
                ];
                $isCount = $this->users_list_db->where($where)->count();
                if (!empty($isCount)) $this->error('手机号码已存在，不可绑定');

                // 判断验证码是否存在并且是否可用
                $where = [
                    'mobile' => $post['mobile'],
                    'code'   => $post['mobile_code'],
                    'lang'   => $this->home_lang
                ];
                $smsLog = $this->sms_log_db->where($where)->field('is_use, add_time')->order('id desc')->find();
                if (!empty($smsLog)) {
                    // 验证码存在
                    $time = getTime();
                    $smsLog['add_time'] += Config::get('global.mobile_default_time_out');
                    if (1 === intval($smsLog['is_use']) || $smsLog['add_time'] <= $time) {
                        // 验证码不可用
                        $this->error(foreign_lang('users47', $this->home_lang));
                    } else {
                        // 查询会员输入的邮箱并且为绑定邮箱来源的所有验证码
                        $where = [
                            'source' => 1,
                            'mobile' => $post['mobile'],
                            'is_use' => 0,
                            'lang'   => $this->home_lang,
                        ];
                        $update = [
                            'is_use' => 1,
                            'update_time' => $time,
                        ];
                        $this->sms_log_db->where($where)->update($update);

                        // 匹配查询手机
                        $where = [
                            'name' => ['LIKE', "mobile_%"],
                            'is_system' => 1,
                        ];
                        $paraID = $this->users_parameter_db->where($where)->getField('para_id');

                        // 修改会员属性表信息
                        $where = [
                            'users_id' => ['EQ', $this->users_id],
                            'para_id'  => $paraID,
                        ];
                        $isCount = $this->users_list_db->where($where)->count();
                        if (empty($isCount)) {
                            // 后台新增会员，没有会员属性记录的情况
                            $insert = [
                                'users_id' => $this->users_id,
                                'para_id'  => $paraID,
                                'info'     => $post['mobile'],
                                'lang'     => $this->home_lang,
                                'add_time' => $time,
                            ];
                            $result = $this->users_list_db->insert($insert);
                        } else {
                            $where = [
                                'users_id' => $this->users_id,
                                'para_id'  => $paraID,
                            ];
                            $update  = [
                                'info' => $post['mobile'],
                                'update_time' => $time,
                            ];
                            $result = $this->users_list_db->where($where)->update($update);
                        }

                        if (!empty($result)) {
                            // 同步修改会员表邮箱地址，并绑定邮箱地址到会员账号
                            $update = [
                                'is_mobile'   => 1,
                                'mobile'      => $post['mobile'],
                                'update_time' => $time,
                            ];
                            $this->users_db->where(['users_id'=>$this->users_id])->update($update);
                            \think\Cache::clear('users_list');
                            $this->success(foreign_lang('system12', $this->home_lang));
                        } else {
                            $this->error('未知错误，手机号码修改失败，请重新获取验证码');
                        }
                    }
                } else {
                    $this->error('输入的手机号码和手机验证码不一致，请重新输入');
                }
            }
        }

        if (1 == config('global.opencodetype')) {
            $opt = input('param.opt/s');
            $this->assign('opt', $opt);
        }

        $title = input('param.title/s');
        $this->assign('title', $title);

        // $rand_token_name = '__token__bind_'.get_rand_str(32, 0, 1);
        // $rand_token_value = request()->token($rand_token_name);
        // session(md5('bind_token'), $rand_token_name);
        // $this->assign('rand_token_name', $rand_token_name);
        // $this->assign('rand_token_value', $rand_token_value);
        
        return $this->fetch();
    }

    // 退出登陆
    public function logout()
    {
        // 退出之前的业务逻辑
        model('EyouUsers')->logoutAfter($this->users_id);

        // 清除登录信息
        session('users_id', null);
        session('users', null);
        cookie('users_id', null);
        cookie('dealerParam', null);
        session('dealerParam', null);

        // 设置不重复执行生成游客标记，3秒后自动过期失效
        \think\Cookie::delete('doNotExecute');
        \think\Cookie::set('doNotExecute', '1', 3);

        // 跳转链接
        $gourl = input('param.gourl/s');
        $referurl = input('param.referurl/s', $gourl);
        if (empty($referurl)) {
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ROOT_DIR . '/';
        }

        // 开启微站点模式，强制退出到网站首页
        if (!empty($this->usersConfig['shop_micro']) && 1 == $this->usersConfig['shop_micro']) {
            $referurl = ROOT_DIR . '/';
        }

        // 只跳转本站链接
        $domain = request()->host(true);
        if (!stristr($referurl, "//{$domain}/")) {
            $referurl = ROOT_DIR . '/';
        }

        $referurl = strip_tags($referurl);
        $this->redirect($referurl);
    }

    /**
     * 我的足迹首页
     * @return mixed
     */
    public function footprint_index()
    {
        // 查询条件
        $where = [
            'c.aid' => ['GT', 0],
            'a.users_id' => $this->users_id,
            'c.lang' => $this->home_lang,
        ];

        // 关键字查询
        $keywords = input('keywords/s');
        if (!empty($keywords)) $where['a.title'] = ['LIKE', "%{$keywords}%"];

        // 所属模型
        $params = [];
        $allow_release_channel_list = $this->get_allow_release_channel_list();
        if (!empty($allow_release_channel_list)) {
            $channel_id = input('channel_id/d');
            if (!empty($channel_id)) $params['channel_id'] = $where['a.channel'] = $channel_id;
        }

        // 查询足迹内容
        $count = Db::name('users_footprint')->alias('a')->where($where)->join('__ARCHIVES__ c', 'a.aid = c.aid', 'LEFT')->count('id');
        $Page  = $pager = new Page($count, config('paginate.list_rows'));
        $result['data'] = Db::name('users_footprint')
            ->field('d.*, a.*, c.*, a.update_time as update_time')
            ->alias('a')
            ->join('__ARCTYPE__ d', 'a.typeid = d.id')
            ->join('__ARCHIVES__ c', 'a.aid = c.aid', 'LEFT')
            ->where($where)
            ->order('a.update_time desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        foreach ($result['data'] as $key => $value) {
            $value['litpic'] = get_default_pic($value['litpic']); // 支持子目录
            $value['arcurl'] = get_arcurl($value, false);
            $valueNew = $value;
            $valueNew['id'] = $valueNew['typeid'];
            $value['typeurl'] = get_typeurl($valueNew, false);
            
            $result['data'][$key] = $value;
        }

        $result['delurl'] = url('user/Users/footprint_del');
        $eyou = array(
            'field' => $result,
        );

        //导航栏url
        $params['barurl'] = MODULE_NAME.'/'.CONTROLLER_NAME.'/'.ACTION_NAME;

        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('eyou', $eyou);
        $this->assign('pager', $pager);
        $this->assign('params', $params);
        $this->assign('allow_release_channel_list', $allow_release_channel_list);
        return $this->fetch('users_footprint_index');
    }

    //获取启用的模型
    protected function get_allow_release_channel_list()
    {
        $allow_release_channel_list = cache('extra_global_channeltype');
        $res = array();
        if ($allow_release_channel_list) {
            foreach ($allow_release_channel_list as $item) {
                if ($item['status'] == 1) {
                    $value = array();
                    $value['id'] = $item['id'];
                    $value['nid'] = $item['nid'];
                    $value['title'] = $item['title'];
                    $value['ntitle'] = $item['ntitle'];
                    $res[] = $value;
                }
            }
        }
        return $res;
    }

    /**
     * 删除
     */
    public function footprint_del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if (!empty($id_arr)) {
                $r = Db::name('users_footprint')->where([
                    'id'       => ['IN', $id_arr],
                    'users_id' => $this->users_id,
                ])->delete();
                if (!empty($r)) {
                    $this->success('删除成功');
                }
            }
        }
        $this->error('删除失败');
    }

    /*
     * 收藏
     */
    public function collection_index()
    {
        // 查询条件
        $where = [
            'c.aid' => ['GT', 0],
            'a.users_id' => $this->users_id,
            'c.lang' => $this->home_lang,
        ];

        // 关键字查询
        $keywords = input('keywords/s');
        if (!empty($keywords)) $where['a.title'] = ['LIKE', "%{$keywords}%"];

        // 所属模型
        $params = [];
        $allow_release_channel_list = $this->get_allow_release_channel_list();
        if (!empty($allow_release_channel_list)) {
            $channel_id = input('channel_id/d');
            if (!empty($channel_id)) $params['channel_id'] = $where['a.channel'] = $channel_id;
        }

        // 查询收藏内容
        $count = Db::name('users_collection')->alias('a')->where($where)->join('__ARCHIVES__ c', 'a.aid = c.aid', 'LEFT')->count('id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));
        $result['data'] = Db::name('users_collection')
            ->field('d.*, a.*, c.*')
            ->alias('a')
            ->join('__ARCTYPE__ d', 'a.typeid = d.id', 'LEFT')
            ->join('__ARCHIVES__ c', 'a.aid = c.aid', 'LEFT')
            ->where($where)
            ->order('a.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        // 如果当前分页没有数据则去除分页参数重载
        if (empty($result['data']) && input('param.p/d', 0)) $this->redirect('user/Users/collection_index');
        foreach ($result['data'] as $key => $value) {
            $value['users_price'] = floatval($value['users_price']);
            $value['litpic'] = get_default_pic($value['litpic']); // 支持子目录
            $value['arcurl'] = get_arcurl($value, false);
            $valueNew = $value;
            $valueNew['id'] = $valueNew['typeid'];
            $value['typeurl'] = get_typeurl($valueNew, false);

            $result['data'][$key] = $value;
        }

        $result['delurl']  = url('user/Users/collection_del');
        $eyou = array(
            'field' => $result,
        );

        //导航栏url
        $params['barurl'] = MODULE_NAME.'/'.CONTROLLER_NAME.'/'.ACTION_NAME;

        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('eyou', $eyou);
        $this->assign('pager', $pager);
        $this->assign('params', $params);
        $this->assign('allow_release_channel_list', $allow_release_channel_list);
        return $this->fetch('users_collection_index');
    }

    /**
     * 删除收藏
     */
    public function collection_del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $r = Db::name('users_collection')->where([
                    'id'    => ['IN', $id_arr],
                    'users_id'  => $this->users_id,
                ])->delete();
                if(!empty($r)){
                    $this->success('删除成功');
                }
            }
        }
        $this->error('删除失败');
    }

    //我的视频
    public function media_index()
    {
        $keywords = input('keywords/s');

        $condition = array();
        $order_code = input('order_code/s');
        if (!empty($order_code)) $condition['a.order_code'] = ['LIKE', "%{$order_code}%"];

        $condition['a.users_id'] = $this->users_id;

        $order_status = input('order_status/s');
        $this->assign('order_status', $order_status);
        if (!empty($order_status)) {
            if (-1 == $order_status) $order_status = 0;
            $condition['a.order_status'] = $order_status;
        }else{
            $condition['a.order_status'] = 1;//默认查询已购买
        }

        $count = Db::name('media_order')->alias('a')->where($condition)->count('order_id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));

        $result['data'] = Db::name('media_order')->where($condition)
            ->field('a.*,c.aid,c.typeid,c.channel,d.*,a.add_time as order_add_time')
            ->alias('a')
            ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
            ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $array_new = get_archives_data($result['data'], 'product_id');
        foreach ($result['data'] as $key => $value) {
            $arcurl = '';
            $vars = !empty($array_new[$value['product_id']]) ? $array_new[$value['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Media/view', $vars));
            }
            $result['data'][$key]['arcurl']  = $arcurl;
        }

        $result['delurl']  = url('user/Users/collection_del');

        $eyou = array(
            'field' => $result,
        );
        $show = $Page->show();
        $this->assign('page',$show);
        // 数据
        $this->assign('eyou', $eyou);
        $this->assign('pager',$pager);

        // 会员订单数量查询 (文章、下载、视频)
        $this->usersOrderQuantityQuery();
        return $this->fetch('users_media_index');
    }

    // 视频订单详情页
    public function media_order_details()
    {
        $order_id = input('param.order_id');
        if (!empty($order_id)) {
            // 查询订单信息
            $OrderData = Db::name('media_order')
                ->field('a.*, product_id,c.aid,c.typeid,c.channel,d.*')
                ->alias('a')
                ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
                ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
                ->find($order_id);

            // 查询会员数据
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $arcurl = '';
            $vars = !empty($array_new[$OrderData['product_id']]) ? $array_new[$OrderData['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Media/view', $vars));
            }
            $OrderData['arcurl'] = $arcurl;
            // 支持子目录
            $OrderData['product_litpic'] = get_default_pic($OrderData['product_litpic']);
            // 加载数据
            $this->assign('OrderData', $OrderData);
            $this->assign('UsersData', $UsersData);
            return $this->fetch();
        } else {
            $this->error('非法访问！');
        }
    }

    /*
     * 积分明细
     */
    public function score_index()
    {
        //积分类型
        $score_type_arr = config('global.score_type');
        if (empty($score_type_arr)) {
            $score_type_arr = [1=>'提问',2=>'回答',3=>'最佳答案',4=>'悬赏退回',5=>'每日签到',6=>'后台操作',7=>'问答悬赏',8=>'消费赠送',9=>'积分兑换',10=>'登录赠送',11=>'积分兑换',12=>'订单退回'];
            config('global.score_type', $score_type_arr);
        }
        $this->assign('score_type_arr', $score_type_arr);

        $condition = [
            'a.users_id' => $this->users_id,
        ];

        $queryID = input('param.queryID/d', 0);
        $this->assign('queryID', $queryID);
        if (!empty($queryID)) {
            // 支出、收入
            if (in_array($queryID, [10, 20])) {
                $condition['a.score'] = 10 === intval($queryID) ? ['LT', 0] : ['GT', 0];
            }
            // 签到
            else if (in_array($queryID, [30])) {
                $condition['a.type'] = 5;
                $condition['a.admin_id'] = ['EQ', 0];
            }
            // 管理员操作
            else if (in_array($queryID, [40])) {
                $condition['a.type'] = 6;
                $condition['a.admin_id'] = ['GT', 0];
            }
            // 问答悬赏
            else if (in_array($queryID, [50])) {
                $condition['a.type'] = 7;
                $condition['a.ask_id'] = ['GT', 0];
            }
            // 兑换商品
            else if (in_array($queryID, [60])) {
                $condition['a.type'] = 9;
            }
        }

        // 积分类型筛选(0全部，1收入，2支出)
        $score_type = input('param.score_type/d', 0);
        $this->assign('score_type', $score_type);
        if (!empty($score_type) && 1 === intval($score_type)) {
            $condition[] = Db::raw('a.score > 0');
        } else if (!empty($score_type) && 2 === intval($score_type)) {
            $condition[] = Db::raw('a.score < 0');
        }

        $count = Db::name('users_score')->alias('a')->where($condition)->count('id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));

        $result['data'] = Db::name('users_score')
            ->field('a.*')
            ->alias('a')
            ->where($condition)
            ->order('a.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $eyou = array(
            'field' => $result,
        );

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('eyou', $eyou);
        $this->assign('pager',$pager);
        return $this->fetch('users_score_index');
    }

    //我的文章
    public function article_index()
    {
        $keywords = input('keywords/s');

        $condition = array();
        $order_code = input('order_code/s');
        if (!empty($order_code)) $condition['a.order_code'] = ['LIKE', "%{$order_code}%"];

        $condition['a.users_id'] = $this->users_id;

        $order_status = input('order_status/s');
        $this->assign('order_status', $order_status);
        if (!empty($order_status)) {
            if (-1 == $order_status) $order_status = 0;
            $condition['a.order_status'] = $order_status;
        }else{
            $condition['a.order_status'] = 1;//默认查询已购买
        }

        $count = Db::name('article_order')->alias('a')->where($condition)->count('order_id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));

        $result['data'] = Db::name('article_order')->where($condition)
            ->field('a.*,c.aid,c.typeid,c.channel,d.*,a.add_time as order_add_time')
            ->alias('a')
            ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
            ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $array_new = get_archives_data($result['data'], 'product_id');
        foreach ($result['data'] as $key => $value) {
            $arcurl = '';
            $vars = !empty($array_new[$value['product_id']]) ? $array_new[$value['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Article/view', $vars));
            }
            $result['data'][$key]['arcurl']  = $arcurl;
        }

        $result['delurl']  = url('user/Users/collection_del');

        $eyou = array(
            'field' => $result,
        );
        $show = $Page->show();
        $this->assign('page',$show);
        // 数据
        $this->assign('eyou', $eyou);
        $this->assign('pager',$pager);

        // 会员订单数量查询 (文章、下载、视频)
        $this->usersOrderQuantityQuery();
        return $this->fetch('users_article_index');
    }

    // 文章订单详情页
    public function article_order_details()
    {
        $order_id = input('param.order_id');
        if (!empty($order_id)) {
            // 查询订单信息
            $OrderData = Db::name('article_order')
                ->field('a.*, product_id,c.aid,c.typeid,c.channel,d.*')
                ->alias('a')
                ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
                ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
                ->find($order_id);

            // 查询会员数据
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $arcurl = '';
            $vars = !empty($array_new[$OrderData['product_id']]) ? $array_new[$OrderData['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Article/view', $vars));
            }
            $OrderData['arcurl'] = $arcurl;
            // 支持子目录
            $OrderData['product_litpic'] = get_default_pic($OrderData['product_litpic']);
            // 加载数据
            $this->assign('OrderData', $OrderData);
            $this->assign('UsersData', $UsersData);
            return $this->fetch();
        } else {
            $this->error('非法访问！');
        }
    }

    //我的下载
    public function download_index()
    {
        $keywords = input('keywords/s');

        $condition = array();
        $order_code = input('order_code/s');
        if (!empty($order_code)) $condition['a.order_code'] = ['LIKE', "%{$order_code}%"];

        $condition['a.users_id'] = $this->users_id;

        $order_status = input('order_status/s');
        $this->assign('order_status', $order_status);
        if (!empty($order_status)) {
            if (-1 == $order_status) $order_status = 0;
            $condition['a.order_status'] = $order_status;
        }else{
            $condition['a.order_status'] = 1;//默认查询已购买
        }

        $count = Db::name('download_order')->alias('a')->where($condition)->count('order_id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));

        $result['data'] = Db::name('download_order')->where($condition)
            ->field('a.*,c.aid,c.typeid,c.channel,d.*,a.add_time as order_add_time')
            ->alias('a')
            ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
            ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $array_new = get_archives_data($result['data'], 'product_id');
        foreach ($result['data'] as $key => $value) {
            $arcurl = '';
            $vars = !empty($array_new[$value['product_id']]) ? $array_new[$value['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Article/view', $vars));
            }
            $result['data'][$key]['arcurl']  = $arcurl;
        }

        $result['delurl']  = url('user/Users/collection_del');

        $eyou = array(
            'field' => $result,
        );
        $show = $Page->show();
        $this->assign('page',$show);
        // 数据
        $this->assign('eyou', $eyou);
        $this->assign('pager',$pager);

        // 会员订单数量查询 (文章、下载、视频)
        $this->usersOrderQuantityQuery();
        return $this->fetch('users_download_index');
    }

    // 下载订单详情页
    public function download_order_details()
    {
        $order_id = input('param.order_id');
        if (!empty($order_id)) {
            // 查询订单信息
            $OrderData = Db::name('download_order')
                ->field('a.*, product_id,c.aid,c.typeid,c.channel,d.*')
                ->alias('a')
                ->join('__ARCHIVES__ c', 'a.product_id = c.aid', 'LEFT')
                ->join('__ARCTYPE__ d', 'c.typeid = d.id', 'LEFT')
                ->find($order_id);

            // 查询会员数据
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $arcurl = '';
            $vars = !empty($array_new[$OrderData['product_id']]) ? $array_new[$OrderData['product_id']] : [];
            if (!empty($vars)) {
                $arcurl = urldecode(arcurl('home/Article/view', $vars));
            }
            $OrderData['arcurl'] = $arcurl;
            // 支持子目录
            $OrderData['product_litpic'] = get_default_pic($OrderData['product_litpic']);
            // 加载数据
            $this->assign('OrderData', $OrderData);
            $this->assign('UsersData', $UsersData);
            return $this->fetch('article_order_details');
        } else {
            $this->error('非法访问！');
        }
    }

    // 会员订单数量查询 (文章、下载、视频)
    private function usersOrderQuantityQuery()
    {
        $where = [
            'order_status' => 1,
            'users_id' => $this->users_id
        ];
        // 查询视频订单
        $mediaOrder = Db::name('media_order')->where($where)->count();
        $mediaOrder = !empty($mediaOrder) ? intval($mediaOrder) : 0;
        // 查询文章订单
        $articleOrder = Db::name('article_order')->where($where)->count();
        $articleOrder = !empty($articleOrder) ? intval($articleOrder) : 0;
        // 查询下载订单
        $downloadOrder = Db::name('download_order')->where($where)->count();
        $downloadOrder = !empty($downloadOrder) ? intval($downloadOrder) : 0;
        // 加载页面数据
        $this->assign('mediaOrder', $mediaOrder);
        $this->assign('articleOrder', $articleOrder);
        $this->assign('downloadOrder', $downloadOrder);
    }

    public function log_off()
    {
        $users_id = session('users_id');
        if (empty($users_id)) $this->error('请先登录');

        $users_open_log_off = getUsersConfigData('users.users_open_log_off','', 'cn'); // 开启注销
        if (empty($users_open_log_off)) $this->error('未开启会员注销');

        $users = Db::name('users')->where('users_id', $users_id)->find();
        if (empty($users)) $this->error('注销失败');

        $insert = [
            'users_id' => $users_id,
            'username' => $users['username'],
            'nickname' => $users['nickname'],
            'mobile' => $users['mobile'],
            'add_time' => getTime(),
            'update_time' => getTime(),
        ];
        $users_log_off_check = getUsersConfigData('users.users_log_off_check','', 'cn'); // 注销审核
        if (empty($users_log_off_check)) {
            //开启注销审核
            $insert['status'] = 0;
            $msg = '申请注销成功,请等待管理员审核';
        } else {
            //直接注销
            $insert['status'] = 1;
            $msg = '注销成功';
        }
        $r = Db::name('users_log_off')->insert($insert);
        if (false !== $r){
            if (!empty($insert['status'])){
                //直接删除
                Db::name('users')->where('users_id', $users_id)->delete();
                $memberModel = new \app\admin\model\Member();
                $memberModel->afterDel([$users_id]);
            }
            $this->success($msg);
        }
        $this->error('注销失败');
    }
}