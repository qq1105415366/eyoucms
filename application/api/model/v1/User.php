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

namespace app\api\model\v1;

use think\Db;
use think\Cache;
use think\template\taglib\api\TagCommentlist;
/**
 * 微信小程序个人中心模型
 */
load_trait('controller/Jump');

class User extends UserBase
{
    use \traits\controller\Jump;
    private $token;

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /**
     * 获取用户信息
     * @return null|static
     * @throws \think\exception\DbException
     */
    public function getUser()
    {
        if (empty($this->session)) return false;
        $where = [
            'users_id' => intval($this->session['users_id'])
        ];
        $users_id = Db::name('wx_users')->where($where)->getField('users_id');
        // 输出查询结果，确认是否正确获取到 users_id
       
        if (empty($users_id)) {
            return false;
        } else {
            $result = GetUsersLatestData($users_id);
            $result['head_pic'] = handle_subdir_pic($result['head_pic'], 'img', true);
            $result['daily_check_in'] = Db::name('users_signin')->where('users_id',$users_id)->whereTime('add_time','today')->count();
            $address_default = []; // 默认收货地址
            $address = Db::name('shop_address')->where(['users_id' => $users_id])->order('is_default desc')->select(); // 收货地址列表
            if (!empty($address)) {
                foreach ($address as $key => $val) {
                    if ($val['is_default'] == 1) {
                        $address_default = $val;
                        continue;
                    }
                }
            }
            $result['address_1588820149'] = !empty($address) ? $address : [];
            $result['address_default_1588820149'] = $address_default;
        }
        return $result;
    }

    /**
     * 用户登录
     * @param array $post
     * @return string
     * @throws BaseException
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function login($post)
    {
        if (self::$provider == 'baidu') {
            // 百度登录 获取session_key
            $session = $this->bdlogin($post['code']);
            // 自动注册用户
            $userInfo = [
                'avatarUrl' => !empty($post['avatar_url']) ? $post['avatar_url'] : '',
                'gender' => !empty($post['gender']) ? $post['gender'] : '',
                'nickName' => !empty($post['nick_name']) ? $post['nick_name'] : '',
            ];
            // 自动注册用户
            $users_id = $this->register($session['openid'], $userInfo);
            $session['unionid'] = '';
        } elseif (self::$provider == 'toutiao') {
            $session = $this->ttlogin($post['code']);
            $userInfo = !empty($post['user_info']) ? json_decode($post['user_info'], true) : [];
            $users_id = $this->register($session['openid'], $userInfo);
            $session['unionid'] = '';
        } elseif (self::$provider == 'alipay') {
            $session = $this->alipaylogin($post['authCode']);
            $userInfo = !empty($post['user_info']) ? json_decode($post['user_info'], true) : [];
            $users_id = $this->register($session['open_id'], $userInfo);
            $session['unionid'] = '';
        } elseif (self::$provider == 'h5') {
            return $this->h5_login($post);
        } else {
            // 微信登录 获取session_key
            $session = $this->wxlogin($post['code']);
            $userInfo = !empty($post['user_info']) ? json_decode($post['user_info'], true) : [];
            $session['unionid'] = !empty($session['unionid']) ? $session['unionid'] : '';
            // 自动注册用户
            $users_id = $this->register($session['openid'], $userInfo, $session['unionid']);
        }

        if (!empty($users_id)) {
            // 更新会员信息
            $update = [
                'last_ip'     => clientIP(),
                'last_login'  => getTime(),
                'login_count' => Db::raw('login_count+1'),
                'update_time' => getTime(),
            ];
            // 查询用户信息
            $usersData = Db::name('users')->where(['users_id' => $users_id])->find();
            // 登录信息中有 unionid 且用户未记录 unionid 则执行
            if (!empty($session['unionid']) && empty($usersData['union_id'])) {
                $update['union_id'] = $session['unionid'];
            }

            // 如果有推荐注册的分销商ID信息则查询并绑定
            $parentUsersID = !empty($post['parent_users_id']) ? intval($post['parent_users_id']) : 0;
            $parentDealerID = !empty($post['parent_dealer_id']) ? intval($post['parent_dealer_id']) : 0;
            $parentDealer = [];
            if (!empty($parentUsersID) && !empty($parentDealerID)) {
                $where = [
                    'a.dealer_status' => 1,
                    'a.users_id' => $parentUsersID,
                    'a.dealer_id' => $parentDealerID,
                ];
                $field = 'a.*, b.is_dealer, b.parent_users_id, b.parent_dealer_id';
                $parentDealer = Db::name('weapp_dealer')->alias('a')->field($field)->where($where)->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->find();
                $dealerOpen = getUsersConfigData('dealer.dealer_open');
                if ((isset($dealerOpen) && 0 === intval($dealerOpen)) || $usersData['users_id'] == $parentDealer['users_id']) {
                    $parentDealer = [];
                }
            }
            if (!empty($parentDealer) && empty($usersData['is_dealer'])) {
                // 如果当前登录用户没有上级分销商则绑定分销商
                if (empty($usersData['parent_users_id']) && empty($usersData['parent_dealer_id'])) {
                    $update['parent_users_id'] = intval($parentDealer['users_id']);
                    $update['parent_dealer_id'] = intval($parentDealer['dealer_id']);
                    $update['bind_dealer_time'] = getTime();
                }
                // 如果当前用户绑定的上级分销商有上级分销商，有则绑定为顶级分销商
                if (!empty($parentDealer['parent_users_id']) && !empty($parentDealer['parent_dealer_id'])) {
                    $update['top_users_id'] = intval($parentDealer['parent_users_id']);
                    $update['top_dealer_id'] = intval($parentDealer['parent_dealer_id']);
                    if (empty($update['bind_dealer_time'])) $update['bind_dealer_time'] = getTime();
                }
            }
            Db::name('users')->where(['users_id' => $users_id])->update($update);
        }

    // 生成token (session3rd)
    $this->token = $this->token($session['unionid'], $session['session_key'], $users_id, $session['openid']);

    return $users_id;
}

/**
 * h5登录
 * @param  [type] $post [description]
 * @return [type]       [description]
 */
private function h5_login($post)
{
    $row = $this->wechatLogic->get_wechat_config();
   
    if (empty($row['appid']) || empty($row['appsecret'])) {
        $this->error("请先完善插件里 [公众号配置]");
    }

    // 微信配置信息
    $appid = $row['appid'];
    $secret = $row['appsecret'];
    $code = $post['code'];
    
    // 获取到会员openid
    $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
    $data = httpRequest($get_token_url);
    $WeChatData = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($WeChatData)) {
        $this->error('获取微信数据失败');
    }

    // 获取会员信息
    $get_userinfo_url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $WeChatData["access_token"] . '&openid=' . $WeChatData["openid"] . '&lang=zh_CN';
    $userInfo = httpRequest($get_userinfo_url);
    $userInfo = json_decode($userInfo, true);
    
    if (empty($userInfo['nickname']) && empty($userInfo['headimgurl'])) {
        $this->error('用户授权异常，建议清理手机缓存再进行登录');
    }

    $userInfo['unionid'] = !empty($userInfo['unionid']) ? $userInfo['unionid'] : '';

    if (!empty($userInfo['unionid'])) {
        $where['union_id'] = $userInfo['unionid'];
    } elseif (!empty($userInfo['openid'])) {
        $where['open_id'] = $userInfo['openid'];
    }

    $users_id = Db::name('users')->where($where)->value('users_id');   
  
     if (empty($users_id)) {
        $users_id = $this->h5_users_register($userInfo);
        $post['is_new'] = 1;
    } else {
        // 查询当前昵称
        $currentNickname = Db::name('users')->where(['users_id' => $users_id])->value('nickname');
     
        // 如果当前昵称是 "微信用户"，更新用户信息
        if ($currentNickname === '微信用户') {
            Db::name('users')->where(['users_id' => $users_id])->update([
                'nickname' => filterNickname($userInfo['nickname']),
                'head_pic' =>!empty($userInfo['headimgurl']) ? $userInfo['headimgurl'] : ROOT_DIR . '/public/static/common/images/dfboy.png',
                'update_time' => time() // 可选：记录更新时间
            ]);
        }
    }

    $this->token = $this->token('', '', $users_id, $userInfo['openid']);

    return $users_id;
}

    // h5 - 自动注册users表用户
    private function h5_users_setReg($userInfo)
    {
        // 生成用户名
        $username = rand_username('', 'U', 3);
        // 用户昵称
        $nickname = !empty($userInfo['nickname']) ? filterNickname($userInfo['nickname']) : '';
        // 创建用户账号
        $addData = [
            'username' => $username,//用户名-生成
            'nickname' => !empty($nickname) ? trim($nickname) : $username,//昵称，同微信用户名
            'level' => 1,
            'thirdparty' => 6,
            'source' => 3,
            'union_id' => !empty($userInfo['unionid']) ? $userInfo['unionid'] : '',
            'open_id' => !empty($userInfo['openid']) ? $userInfo['openid'] : '',
            'register_place' => 2,
            'open_level_time' => getTime(),
            'level_maturity_days' => 0,
            'reg_time' => getTime(),
            'head_pic' => !empty($userInfo['headimgurl']) ? $userInfo['headimgurl'] : ROOT_DIR . '/public/static/common/images/dfboy.png',
            'mobile' => !empty($userInfo['mobile']) ? $userInfo['mobile'] : '',
            'is_mobile' => !empty($userInfo['mobile']) ? 1 : 0,
        ];
        $users_id = Db::name('users')->insertGetId($addData);

        return $users_id;
    }

    // h5 - 自动注册用户
    private function h5_users_register($userInfo = [])
    {
        if (empty($userInfo['openid'])) {
            $this->error('openid 不能为空');
        }

        // 检查是否存在 unionid
        if (!empty($userInfo['unionid'])) {
            $u_where['union_id'] = $userInfo['unionid'];
            $users_id = Db::name('users')->where($u_where)->value('users_id');
            if (!empty($users_id)) {
                return $users_id;
            }
        }

        // 默认使用 openid 查询是否已存在
        $w_where = ['openid' => $userInfo['openid']];
        $we_user = Db::name('wx_users')->field('users_id')->where($w_where)->find();

        if (empty($we_user)) {
            // 用户不存在，需要注册
            $users_id = $this->h5_users_setReg($userInfo);
            if (!empty($users_id)) {
                // 插入到 wx_users 表
                $insertData = [
                    'users_id' => $users_id,
                    'openid' => $userInfo['openid'],
                    'union_id' => $userInfo['unionid'],
                    'nickname' => !empty($userInfo['nickname']) ? filterNickname($userInfo['nickname']) : '',
                    'headimgurl' => !empty($userInfo['headimgurl']) ? filterNickname($userInfo['headimgurl']) : '',
                    'provider' => 'gzh',
                    'add_time' => getTime(),
                ];

                // 启用事务确保数据一致性
                Db::startTrans();
                try {
                    $wxuser_id = Db::name('wx_users')->insertGetId($insertData);
                    if (!empty($wxuser_id)) {
                        Db::commit();  // 提交事务
                        return $users_id;
                    } else {
                        Db::rollback();  // 回滚事务
                        Db::name('users')->where(['users_id' => $users_id])->delete();  // 删除用户数据
                        $this->error('微信用户信息插入失败');
                    }
                } catch (\Exception $e) {
                    Db::rollback();  // 回滚事务
                    $this->error('注册过程中发生错误');
                }
            }
            $this->error('用户注册失败！');
        } else {
            // 如果用户已存在，更新微信用户信息
            $users = Db::name('users')->field('users_id')->where([
                'users_id' => $we_user['users_id'],
            ])->find();

            if (empty($users)) {
                // 用户数据已存在但未绑定，重新注册
                $users_id = $this->h5_users_setReg($userInfo);
                
                if (!empty($users_id)) {
                    Db::name('wx_users')->where($w_where)->update([
                        'users_id' => $users_id,
                        'update_time' => getTime(),
                    ]);
                    return $users_id;
                } else {
                    $this->error('用户注册失败！');
                }
            } else {
                return $we_user['users_id'];
            }
        }
    }


    //头条登录
    private function ttlogin($code)
    {
        // $inc = tpSetting("OpenMinicode.conf_toutiao");
        // $inc = !empty($inc) ? json_decode($inc, true) : [];
        $inc = model('ShopPublicHandle')->getSpecifyAppletsConfig();
        $inc = [
           'appid' => !empty($inc['appid']) ? $inc['appid'] : '',
           'secret' => !empty($inc['appsecret']) ? $inc['appsecret'] : '',
        ];
        if (empty($inc['appid']) || empty($inc['secret'])) $this->error('未填写抖音小程序配置');

        // 头条登录登录 (获取session_key)
        $session = $this->ttUserSessionKey($code, $inc);
        if (isset($session['errcode'])) $this->error($session['errmsg']);

        return $session;
    }
     //支付宝登录
    public function alipaylogin($authCode)
    {
        // 获取支付宝小程序配置
        $inc = model('ShopPublicHandle')->getSpecifyAppletsConfig();
        $inc = [
            'appid' => !empty($inc['appid']) ? $inc['appid'] : '',
            'appPrivateKey' => !empty($inc['appPrivateKey']) ? $inc['appPrivateKey'] : '',
            'alipayPublicKey' => !empty($inc['alipayPublicKey']) ? $inc['alipayPublicKey'] : '',
        ];
        if (empty($inc['appid']) || empty($inc['appPrivateKey'])) {
            $this->error('未填写支付宝小程序配置');
        }
    
        // 获取 session 信息
        $session = $this->alipayUserSessionKey($authCode, $inc);
        // 检查是否有错误
        if (isset($session['errcode'])) {
            $this->error($session['errmsg']);
        }
    
        // 返回 session 信息
        return $session;
    }
    /**
     * 获取支付宝登录的session_key
     * @param $code
     * @return array|mixed
     */
    private function alipayUserSessionKey($authCode, $inc)
    {
        $url = "https://openapi.alipay.com/gateway.do";
        // 构造请求参数
        $postData = [
            'app_id' => !empty($inc['appid']) ? $inc['appid'] : '',
            'method' => 'alipay.system.oauth.token',
            'format' => 'json',
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'grant_type' => 'authorization_code',
            'code' => $authCode,
        ];
       // 生成签名
        $postData['sign'] = $this->generateAlipaySign($postData, $inc['appPrivateKey']); 
        $response = httpRequest($url, 'POST', http_build_query($postData));
        $params = json_decode($response, true);
        if (isset($params['error_response'])) {
            // 返回错误信息
            return [
                'errcode' => $params['error_response']['code'] ?? "-1",
                'errmsg' => $params['error_response']['msg'] ?? "未知错误",
            ];
        } else {
            // 成功返回数据
            return $params['alipay_system_oauth_token_response'] ?? [];
        }
    }

    /**
     * 生成支付宝签名
     * @param array $data
     * @param string $privateKey
     * @return string
     */
  private function generateAlipaySign($data, $privateKey)
{
    // 按照键名升序排序
    ksort($data);
    
    $stringToSign = '';
    foreach ($data as $key => $value) {
        if ($value !== '' && !is_null($value) && $key !== 'sign') {
            $stringToSign .= "$key=$value&";
        }
    }

    // 去掉末尾多余的 &
    $stringToSign = rtrim($stringToSign, '&');
    
    // 加载私钥
    $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . implode("\n", explode("\n", $privateKey)) . "\n-----END RSA PRIVATE KEY-----";
    $privateKeyResource = openssl_pkey_get_private($privateKey);
    
    if (!$privateKeyResource) {
        die('私钥加载失败');
    }

    // 使用 RSA2 签名，采用 SHA256 算法
    openssl_sign($stringToSign, $sign, $privateKeyResource, OPENSSL_ALGO_SHA256);

    // 返回 Base64 编码后的签名
    return base64_encode($sign);
}

    /**
     * 获取抖音登录的session_key
     * @param $code
     * @return array|mixed
     */
    private function ttUserSessionKey($code, $inc)
    {
        $url = "https://developer.toutiao.com/api/apps/v2/jscode2session";
        $postData = [
            'appid' => !empty($inc['appid']) ? $inc['appid'] : '',
            'secret' => !empty($inc['secret']) ? $inc['secret'] : '',
            'anonymous_code' => "",
            'code' => $code,
        ];
        $headers = array("content-type: application/json");
        $response = httpRequest($url, 'POST', json_encode($postData), $headers);
        $params = json_decode($response, true);
        if (!empty($params['err_no']) && 'success' != $params['err_tips']) {
            $params = [
                'errcode' => "-1",
                'errmsg' => $params['err_no'] . '：' . $params['err_tips'],
            ];
            return $params;
        } else {
            return $params['data'];
        }
    }

    /**
     * 百度登录
     * @param $code
     * @return array|mixed
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    private function bdlogin($code)
    {
        // $inc = tpSetting("OpenMinicode.conf_baidu", [], self::$lang);
        // $inc = json_decode($inc, true);
        $inc = model('ShopPublicHandle')->getSpecifyAppletsConfig();
        if (empty($inc['appkey'])) {
            $this->error('该开源插件未填写百度小程序配置');
        }
        $inc = [
            'appkey'    => !empty($inc['appkey']) ? $inc['appkey'] : '',
            'appsecret'    => !empty($inc['appsecret']) ? $inc['appsecret'] : '',
        ];
        // 百度登录 (获取session_key)
        $session = $this->bdUserSessionKey($code, $inc);
        if (isset($session['errcode'])) {
            $this->error($session['errmsg']);
        }
        return $session;
    }

    /**
     * 获取百度登录的session_key
     * @param $code
     * @return array|mixed
     */
    private function bdUserSessionKey($code, $inc)
    {
        /**
         * code 换取 session_key
         * ​这是一个 HTTPS 接口，开发者服务器使用登录凭证 code 获取 session_key 和 openid。
         * 其中 session_key 是对用户数据进行加密签名的密钥。为了自身应用安全，session_key 不应该在网络上传输。
         */

        $url = "https://spapi.baidu.com/oauth/jscode2sessionkey";
        $post_data = [
            'code'  => $code,
            'client_id' => !empty($inc['appkey']) ? $inc['appkey'] : '',
            'sk' => !empty($inc['appsecret']) ? $inc['appsecret'] : '',
        ];
        $response = httpRequest($url, 'POST', $post_data);
        $params = json_decode($response, true);
        if (!empty($params['errno'])) {
            $params = [
                'errcode'   => "-1",
                'errmsg'    => $params['error'],
            ];
        }
        return $params;
    }

    /**
     * 获取token
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * 微信登录
     * @param $code
     * @return array|mixed
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    private function wxlogin($code)
    {
        // $inc = tpSetting("OpenMinicode.conf_weixin", [], self::$lang);
        // $inc = json_decode($inc, true);
        $inc = model('ShopPublicHandle')->getSpecifyAppletsConfig();
        if (empty($inc['appid'])) {
            $this->error('该开源插件未填写微信小程序配置');
        }
        // 微信登录 (获取session_key)
        $session = $this->wxUserSessionKey($code, $inc);
        if (isset($session['errcode'])) {
            $this->error($session['errmsg']);
        }
        return $session;
    }

    /**
     * 获取微信登录的session_key
     * @param $code
     * @return array|mixed
     */
    private function wxUserSessionKey($code, $inc)
    {
        /**
         * code 换取 session_key
         * ​这是一个 HTTPS 接口，开发者服务器使用登录凭证 code 获取 session_key 和 openid。
         * 其中 session_key 是对用户数据进行加密签名的密钥。为了自身应用安全，session_key 不应该在网络上传输。
         */
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$inc['appid']}&secret={$inc['appsecret']}&js_code={$code}&grant_type=authorization_code";
        $response = httpRequest($url);
        $params   = json_decode($response, true);
        if (empty($params)) {
            $params = [
                'errcode' => "-1",
                'errmsg'  => '系统繁忙',
            ];
        }
        return $params;
    }

    /**
     * 生成用户认证的token
     * @param $openid
     * @return string
     */
    private function token($unionid = '', $session_key = '', $users_id = '', $openid = 'openid')
    {
        // 随机串
        $randstr1 = get_rand_str(8, 0, 1);
        // 随机串
        $randstr2 = get_rand_str(8, 0, 0);
        // 自定义一个盐
        $salt = '_token_salt';
        $openid = !empty($openid) ? $openid : 'openid';//为手机号注册登录写的
        $session_key = !empty($session_key) ? $session_key : 'session_key';//为手机号注册登录写的
        // 用户认证的token
        $token = "{$randstr1}eyoucms{$users_id}eyoucms{$openid}eyoucms{$randstr2}eyoucms{$session_key}eyoucms{$salt}";

        return mchStrCode($token, 'ENCODE', '#!@diyminipro#!$');
    }

    /**
     * 自动注册用户
     * @param $openid
     * @param $userInfo
     * @return mixed
     * @throws \Exception
     * @throws \think\exception\DbException
     */
    private function register($openid = '', $userInfo = '', $unionid = '')
    {
        if (!empty($unionid)){
            $u_where['union_id'] = $w_where['unionid'] = $unionid;
            $users_id  = Db::name('users')->where($u_where)->value('users_id');
            if (!empty($users_id)) return $users_id;
        }else{
            $w_where['openid'] = $openid;
        }

        // 查询用户是否已存在
        $we_user = Db::name('wx_users')->field('users_id')->where($w_where)->find();
        $userInfo['unionid'] = $unionid;
        $userInfo['openid'] = $openid;
        if (empty($we_user)) {
            $users_id = $this->setReg($userInfo);
            if (!empty($users_id)) {
                 if(self::$provider  == 'alipay') {
                    $userInfo = $userInfo['response'];  // 修改这一行，正确访问数组元素
                    $nickname = !empty($userInfo['nickName']) ? filterNickname($userInfo['nickName']) : '';
                    $avatar =  !empty($userInfo['avatar']) ? filterNickname($userInfo['avatar']) : '';
                } else{ 
                    $nickname = !empty($userInfo['nickName']) ? filterNickname($userInfo['nickName']) : '';
                    $avatar = !empty($userInfo['avatarUrl']) ? filterNickname($userInfo['avatarUrl']) : '';
                }
               
                //微信用户信息存在表里
                $wxuser_id = Db::name('wx_users')->insertGetId([
                    'users_id'   => $users_id,
                    'openid'     => $openid,
                    'unionid'     => $unionid,
                    'nickname'   => $nickname,
                    'headimgurl' => $avatar,
                    'add_time'   => getTime(),
                ]);
                if (!empty($wxuser_id)) {
                    return $users_id;
                } else {
                    Db::name('users')->where(['users_id' => $users_id])->delete();
                }
            }
            $this->error('用户注册失败！');
        } else {
            $users = Db::name('users')->field('users_id')->where([
                'users_id' => $we_user['users_id'],
            ])->find();
            if (empty($users)) {
                $users_id = $this->setReg($userInfo);
                if (!empty($users_id)) {
                    Db::name('wx_users')->where($w_where)->update([
                        'users_id'    => $users_id,
                        'update_time' => getTime(),
                    ]);
                    return $users_id;
                } else {
                    $this->error('用户注册失败！');
                }
            } else {
                return $we_user['users_id'];
            }
        }
    }

    /**
     * 自动注册users表用户
     */
    private function setReg($userInfo)
    {
        // 生成用户名
        $username = rand_username('', 'U', 3);
        // 用户昵称
        $nickname = !empty($userInfo['nickName']) ? filterNickname($userInfo['nickName']) : '';
        if(self::$provider  == 'alipay') {
            $userInfo = $userInfo['response'];  // 修改这一行，正确访问数组元素
            $nickname = !empty($userInfo['nickName']) ? filterNickname($userInfo['nickName']) : '';
            $avatar = !empty($userInfo['avatar']) ? $userInfo['avatar'] : ROOT_DIR . '/public/static/common/images/dfboy.png' ;
        } else{ 
            $nickname = !empty($userInfo['nickName']) ? filterNickname($userInfo['nickName']) : '';
            $avatar = !empty($userInfo['avatarUrl']) ? $userInfo['avatarUrl'] : ROOT_DIR . '/public/static/common/images/dfboy.png';
        }
       
        $addData  = [
            'username'            => $username,//用户名-生成
            'nickname'            => !empty($nickname) ? trim($nickname) : $username,//昵称，同微信用户名
            'level'               => 1,
            'thirdparty'          => 3,
            'union_id'            => !empty($userInfo['unionid']) ? $userInfo['unionid'] : '',
            'register_place'      => 2,
            'open_level_time'     => getTime(),
            'level_maturity_days' => 0,
            'reg_time'            => getTime(),
            'head_pic'            => $avatar,
            'mobile'            => !empty($userInfo['mobile']) ? $userInfo['mobile'] : '',
            'is_mobile'            => !empty($userInfo['mobile']) ? 1 : 0,
            'lang'                => self::$lang,
        ];
        if (self::$provider == 'baidu') {
            $addData['source'] = 5;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
        } elseif (self::$provider == 'toutiao') {
            $addData['source'] = 6;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
        }  elseif (self::$provider == 'alipay') {
            $addData['source'] = 7;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序  7-支付宝小程序
        } else {
            $addData['source'] = 4;//1-PC端 2-H5 3-微信公众号/微站点 4-微信小程序 5-百度小程序 6-抖音小程序
        }
        if (0 == config('global.opencodetype') && !empty($userInfo['password'])) {
            $addData['password'] = func_encrypt($userInfo['password'], false, pwd_encry_type('bcrypt'));
        }
        $users_id = Db::name('users')->insertGetId($addData);

        return $users_id;
    }

    /**
     * 自动注册users表用户
     */
    private function setAccountReg($userInfo)
    {
        // 生成用户名
        $username = $userInfo["username"];

        // 创建用户账号
        $addData  = [
            "username"            => $username, // 用户名
            "nickname"            => $username, //昵称，同用户名
            "level"               => 1,
            "thirdparty"          => 0,
            "union_id"            => "",
            "register_place"      => 2,
            "open_level_time"     => getTime(),
            "level_maturity_days" => 0,
            "reg_time"            => getTime(),
            "head_pic"            => !empty($userInfo["avatarUrl"]) ? $userInfo["avatarUrl"] : ROOT_DIR . "/public/static/common/images/dfboy.png",
            "mobile"              => !empty($userInfo["mobile"]) ? $userInfo["mobile"] : "",
            "is_mobile"           => !empty($userInfo["mobile"]) ? 1 : 0,
            "lang"                => self::$lang,
        ];

        if (0 == config("global.opencodetype")) {
            $addData["password"]       = func_encrypt($userInfo["password"], false, pwd_encry_type("bcrypt"));
        }

        $users_id = Db::name("users")->insertGetId($addData);

        Db::name("wx_users")->insert([
            "users_id"      => $users_id,
            "openid"        => "openid",
            "nickname"      => $username,
            "headimgurl"    => !empty($userInfo["avatarUrl"]) ? $userInfo["avatarUrl"] : ROOT_DIR . "/public/static/common/images/dfboy.png",
            "add_time"      => getTime(),
        ]);

        return $users_id;
    }

    /**
     * 个人中心菜单列表
     * @return array
     */
    public function getMenus()
    {
        $menus = [
            // 'address' => [
            //     'name' => '收货地址',
            //     'url' => 'pages/address/index',
            //     'icon' => 'map'
            // ],
            // 'coupon' => [
            //     'name' => '领券中心',
            //     'url' => 'pages/coupon/coupon',
            //     'icon' => 'lingquan'
            // ],
            // 'my_coupon' => [
            //     'name' => '我的优惠券',
            //     'url' => 'pages/user/coupon/coupon',
            //     'icon' => 'youhuiquan'
            // ],
            // 'sharing_order' => [
            //     'name' => '拼团订单',
            //     'url' => 'pages/sharing/order/index',
            //     'icon' => 'pintuan'
            // ],
            // 'my_bargain' => [
            //     'name' => '我的砍价',
            //     'url' => 'pages/bargain/index/index?tab=1',
            //     'icon' => 'kanjia'
            // ],
            // 'dealer' => [
            //     'name' => '分销中心',
            //     'url' => 'pages/dealer/index/index',
            //     'icon' => 'fenxiaozhongxin'
            // ],
            // 'help' => [
            //     'name' => '我的帮助',
            //     'url' => 'pages/user/help/index',
            //     'icon' => 'help'
            // ],
        ];
        // 判断分销功能是否开启
        // if (DealerSettingModel::isOpen()) {
        //     $menus['dealer']['name'] = DealerSettingModel::getDealerTitle();
        // } else {
        //     unset($menus['dealer']);
        // }
        return $menus;
    }

    /**
     * 返回状态给微信服务器
     * @param boolean $returnCode
     * @param string $msg
     */
    public function returnCode($returnCode = true, $msg = null)
    {
        // 返回状态
        $return = [
            'return_code' => $returnCode ? 'SUCCESS' : 'FAIL',
            'return_msg'  => $msg ?: 'OK',
        ];

        // 记录日志
        // $value = [
        //     'describe' => '返回微信支付状态',
        //     'data'     => $return
        // ];
        // $msg   = is_string($value) ? $value : var_export($value, true);
        // \think\Log::record($msg, $type);

        die($this->toXml($return));
    }

    /**
     * 格式化参数格式化成url参数
     * @param $values
     * @return string
     */
    public function toUrlParams($values)
    {
        $buff = '';
        foreach ($values as $k => $v) {
            if ($k != 'sign' && $v != '' && !is_array($v)) {
                $buff .= $k . '=' . $v . '&';
            }
        }
        return trim($buff, '&');
    }

    /**
     * 输出xml字符
     * @param $values
     * @return bool|string
     */
    public function toXml($values)
    {
        if (!is_array($values)
            || count($values) <= 0
        ) {
            return false;
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param $xml
     * @return mixed
     */
    public function fromXml($xml)
    {
        // 禁止引用外部xml实体
        @libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * 生成签名
     * @param $values
     * @return string 本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function makeSign($values, $apikey = '')
    {
        if (empty($apikey)) {
            $minicode = Db::name('weapp')->where('code', self::$weapp_code)->value('data');
            $Setting = json_decode($minicode, true);
            $apikey         = $Setting['apikey'];
        }

        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = $this->toUrlParams($values);
        //签名步骤二：在string后加入KEY
        $string = $string . '&key=' . $apikey;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    public function onPaySuccess($order, $payType = 20, $payData = [])
    {
        if (empty($order)) {
            return [
                'code' => 0,
                'msg'  => '未找到该订单信息',
            ];
        }
        // 更新付款状态
        $status = $this->updatePayStatus($order, $payType, $payData);

        return $status;
    }

    /**
     * 更新付款状态
     * @param $payType
     * @param array $payData
     * @return bool
     */
    private function updatePayStatus($order, $payType, $payData = [])
    {
        // 验证余额支付时用户余额是否满足
        if ($payType == 10) {
            $users_money = Db::name('users')->where(['users_id' => $this->users_id])->getField('users_money');
            if (strval($users_money) < strval($order['order_amount'])) {
                return [
                    'code' => 0,
                    'msg'  => '用户余额不足，无法使用余额支付',
                ];
            }
        }
        // 更新订单状态
        $Result = $this->updateOrderInfo($order, $payType, $payData);

        return $Result;
    }

    /**
     * 更新订单记录
     * @param $payType
     * @param $payData
     * @return false|int
     * @throws \Exception
     */
    private function updateOrderInfo($order, $payType, $payData)
    {
        $Result = [];

        $OrderWhere = [
            'order_id'   => $order['order_id'],
            'order_code' => $payData['out_trade_no'],
        ];
        // 修改会员金额明细表中，对应的订单数据，存入返回的数据，订单已付款
        $OrderData = [
            'order_status' => 1,
            // 'pay_name'     => 'wechat', //微信支付
            'pay_details'  => serialize($payData),
            'pay_time'     => getTime(),
            'update_time'  => getTime(),
        ];
        $r         = Db::name('shop_order')->where($OrderWhere)->update($OrderData);

        if (!empty($r)) {

            // 添加订单操作记录
            AddOrderAction($order['order_id'], $order['users_id'], '0', '1', '0', '1', '支付成功！', '会员使用微信小程序完成支付！');

            // $users = Db::name('users')->find($order['users_id']);

            // 邮箱发送
            // $SmtpConfig = tpCache('smtp');
            // $Result['email'] = GetEamilSendData($SmtpConfig, $users, $order, 1, 'wechat');

            // 手机发送
            // $SmsConfig = tpCache('sms');
            // $Result['mobile'] = GetMobileSendData($SmsConfig, $users, $order, 1, 'wechat');

            $Result['status'] = 1;
        }

        return $Result;
    }

    //获取收藏/喜欢(点赞)列表
    public function GetMyCollectList($param = [])
    {
        $orderby  = !empty($orderby) ? $orderby : 'id desc';
        $page     = !empty($param['page']) ? intval($param['page']) : 1;
        $pagesize = empty($param['pagesize']) ? config('paginate.list_rows') : $param['pagesize'];
        $type = empty($param['type']) ? 'users_collection' : $param['type'];

        $paginate = ['page' => $page];
        $pages     = Db::name($type)
            ->alias('a')
            ->field('d.typename,c.*,a.*,c.add_time as arc_add_time')
            ->where('a.users_id', $this->users_id)
            ->where('a.channel', '>', 0)
            ->where('a.aid', '>', 0)
            ->join('archives c','a.aid = c.aid','left')
            ->join('arctype d', 'a.typeid = d.id','left')
            ->orderRaw($orderby)
            ->paginate($pagesize, false, $paginate);

        $result = $pages->toArray();

        $aids = get_arr_column($result['data'],'aid');
        if (!empty($result['data'])){
            $channel = get_arr_column($result['data'],'channel');
            $channelRow = Db::name('channeltype')->field('id,nid,table')
                ->where([
                    'id' => ['in',$channel],
                ])->getAllWithIndex('id');
            $content_arr = [];
            foreach($channelRow as $k => $v){
                $tableExt = $v['table'] . "_content";
                $channel_content_arr = Db::name($tableExt)->where('aid','in',$aids)->field('content_ey_m,content,aid')->getAllWithIndex('aid');
                $content_arr[$v['id']] = $channel_content_arr;
            }
            $count_arr = Db::name($type)->where('aid','in',$aids)->field('aid,count(id) as count')->group('aid')->getAllWithIndex('aid');
            $commentListObj = new TagCommentlist();
            foreach ($result['data'] as $key => $val) {
                $val['is_litpic'] = 0;
                if (!empty($val['litpic'])){
                    $val['is_litpic'] = 1;
                }
                $val['litpic'] = $this->get_default_pic($val['litpic']); // 默认封面图
                $val['add_time'] = date('Y-m-d H:i:s',$val['add_time']);
                $val['arc_add_time_format'] = $this->time_format($val['arc_add_time']);
                $val['arc_add_time'] = date('Y-m-d H:i:s',$val['arc_add_time']);
                $val['update_time'] = date('Y-m-d H:i:s',$val['update_time']);
                $commentsData = $commentListObj->getCommentlist([], $val['aid']);
          
                $val['comments'] = isset($commentsData['total']) ? $commentsData['total'] : 0; // 获取评论总数
            
                $val['count'] = !empty($count_arr[$val['aid']]) ? $count_arr[$val['aid']]['count'] : 0;
                // 内容扩展表数据
                $content_data = $content_arr[$val['channel']][$val['aid']];
                if (!empty($content_data['content_ey_m'])){
                    $content = $content_data['content_ey_m'];
                }else{
                    $content = $content_data['content'];
                }
                $val['img_list'] = [];
                if (!empty($content)){
                    $content = htmlspecialchars_decode($content);
                    $val['img_list'] = $this->get_content_img($content);
                }
                $result['data'][$key] = $val;
            }
        }

        return $result;
    }

    //获取内容里所有的图片
    private function get_content_img($content = '')
    {
        $arr = [];
        if (!empty($content)) {
            preg_match_all('/<img.*(\/)?>/iUs', $content, $imginfo);
            $imginfo = !empty($imginfo[0]) ? $imginfo[0] : [];
            if (!empty($imginfo)) {
                foreach ($imginfo as $key => $imgstr) {
                    $imgstrNew = $imgstr;
                    $url  = preg_replace("/<img(.*?)src(\s*)=(\s*)[\'|\"](.*?)([^\/\'\"]*)[\'|\"](.*?)[\/]?(\s*)>/i", '${4}${5}', $imgstrNew);
                    $url  =  handle_subdir_pic($url,'img',true);
                    $info = @getimagesize($url);
                    if ($info[0] >= 375 && $info[0] >= 250){
                        $arr[] = get_default_pic($url,true);
                    }
                    if (count($arr) == 3){
                        break;
                    }
                }
            }
        }
        return $arr;
    }

    /**
     * 获取小程序全局唯一后台接口调用凭据（access_token）
     * @param $code
     * @return array|mixed
     */
    private function getAccessToken()
    {
        // $inc = tpSetting("OpenMinicode.conf_weixin", [], self::$lang);
        // $inc = json_decode($inc, true);
        $inc = model('ShopPublicHandle')->getSpecifyAppletsConfig();
        if (empty($inc['appid'])) {
            $this->error('该开源插件未填写微信小程序配置');
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$inc['appid']}&secret={$inc['appsecret']}";
        $response = httpRequest($url);
        $params   = json_decode($response, true);
        if (!empty($params['errcode'])) {
            $this->error($params['errmsg']);
        }
        return $params['access_token'];
    }

    //code换取用户手机号
    public function getPhone($code)
    {
        $access_token = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token={$access_token}";
        $response = httpRequest($url,'POST',json_encode(['code'=>$code]));
        $params   = json_decode($response, true);
        if (!empty($params['errcode'])) {
            $this->error($params['errmsg']);
        }
        $this->success('获取成功','',$params['phone_info']);
    }

    //获取我的足迹列表
    public function GetMyFootprintList($param = [])
    {
        $page     = !empty($param['page']) ? intval($param['page']) : 1;
        $pagesize = empty($param['pagesize']) ? config('paginate.list_rows') : $param['pagesize'];
        $condition['a.users_id'] = $this->users_id;
        if (!empty($param['channel'])){
            $condition['a.channel'] = $param['channel'];
        }

        $paginate = ['page' => $page];
        $pages = Db::name('users_footprint')
            ->field('d.typename,c.*,a.*,c.add_time as arc_add_time')
            ->alias('a')
            ->join('arctype d', 'a.typeid = d.id','left')
            ->join('archives c', 'a.aid = c.aid','left')
            ->where($condition)
            ->order('a.update_time desc')
            ->paginate($pagesize, false, $paginate);

        $result = $pages->toArray();

        foreach ($result['data'] as $key => $val) {
            $val['is_litpic'] = 0;
            if (!empty($val['litpic'])){
                $val['is_litpic'] = 1;
            }
            $val['litpic'] = $this->get_default_pic($val['litpic']); // 默认封面图
            $val['add_time'] = date('Y-m-d H:i:s',$val['add_time']);
            $val['arc_add_time_format'] = $this->time_format($val['arc_add_time']);
            $val['arc_add_time'] = date('Y-m-d H:i:s',$val['arc_add_time']);
            $val['update_time'] = date('Y-m-d H:i:s',$val['update_time']);
            $result['data'][$key] = $val;
        }
        return $result;
    }

    public function guestbookList()
    {
        $param = input('param.');
        $page     = !empty($param['page']) ? intval($param['page']) : 1;
        $pagesize = empty($param['pagesize']) ? config('paginate.list_rows') : 10;
//        $typeid = !empty($param['typeid']) ? $param['typeid'] : 0;
        if (!empty($param['typeid'])){
            $condition['typeid'] = $param['typeid'] ;
        }

        $condition['users_id'] = $this->users_id;
        $condition['lang'] = self::$lang;

        $paginate = ['page' => $page];
        $pages = Db::name('guestbook')
            ->where($condition)
            ->order('add_time desc')
            ->paginate($pagesize, false, $paginate);
        $result = $pages->toArray();
        $list = $result['data'];

        if (!empty($list)) {
            $aid_arr = get_arr_column($result['data'],'aid');
            $where = [
                'b.aid'     => ['IN', $aid_arr],
                'a.lang'    => self::$lang,
                'a.is_del'  => 0,
            ];
            $row       = Db::name('guestbook_attribute')
                ->field('a.attr_name, b.attr_value, b.aid, b.attr_id,a.attr_input_type')
                ->alias('a')
                ->join('__GUESTBOOK_ATTR__ b', 'b.attr_id = a.attr_id', 'LEFT')
                ->where($where)
                ->order('b.aid desc, a.sort_order asc, a.attr_id asc')
                ->getAllWithIndex();
            $attr_list = array();
            foreach ($row as $key => $val) {
                if (9 == $val['attr_input_type']){
                    //如果是区域类型,转换名称
                    $val['attr_value'] = Db::name('region')->where('id','in',$val['attr_value'])->column('name');
                    $val['attr_value'] = implode('',$val['attr_value']);
                }else if(10 == $val['attr_input_type']){
                    $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
                }
                if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' onclick=\"Images('{$val['attr_value']}', 650, 350);\" />";
                    }
                } else {
                    $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
                }
                $attr_list[$val['aid']][] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key]['attr_list'] = isset($attr_list[$val['aid']]) ? $attr_list[$val['aid']] : array();
            }
            $result['data'] = $list;
        }
        return $result;
    }

    public function GetMyBookDetail($param)
    {
        $aid     = !empty($param['aid']) ? intval($param['aid']) : 0;
        if (empty($aid)){
            $this->error('缺少aid');
        }
        $data = Db::name('guestbook')
            ->where(['aid'=>$aid,'users_id'=>$this->users_id])
            ->order('add_time desc')
            ->find();
        $where = [
            'b.aid'     => $aid,
            'a.is_del'  => 0,
        ];
        $data['add_time'] = date('Y-m-d H:i:s');
        $row       = Db::name('guestbook_attribute')
            ->field('a.attr_name, b.attr_value, b.aid, b.attr_id,a.attr_input_type')
            ->alias('a')
            ->join('__GUESTBOOK_ATTR__ b', 'b.attr_id = a.attr_id', 'LEFT')
            ->where($where)
            ->order(' a.sort_order asc, a.attr_id asc')
            ->getAllWithIndex();
        foreach ($row as $key => $val) {
            if (9 == $val['attr_input_type']){
                //如果是区域类型,转换名称
                $val['attr_value'] = Db::name('region')->where('id','in',$val['attr_value'])->column('name');
                $val['attr_value'] = implode('',$val['attr_value']);
            }else if(10 == $val['attr_input_type']){
                $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
            }
            if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                if (!stristr($val['attr_value'], '|')) {
                    $val['attr_value'] = handle_subdir_pic($val['attr_value']);
//                    $val['attr_value'] = "<img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' onclick=\"Images('{$val['attr_value']}', 650, 350);\" />";
                }
            } else {
                $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
            }
            $row[$key] = $val;
        }
        return ['data'=>$data,'list'=>$row];
    }

    /**
     * 手机号注册
     * @param array $post
     * @return string
     * @throws BaseException
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function mobile_reg($post)
    {
        if (empty($post['mobile'])) $this->error('手机号不能为空!');
        if (!check_mobile($post['mobile'])) $this->error('手机号格式不正确!');
        //查询手机号是否已经注册过
        $is_reg = Db::name('users')->where(['mobile'=>$post['mobile'],'is_del'=>0])->find();
        if (!empty($is_reg)){
            $this->error('手机号码已经注册！');
        }

        if (!empty($post['have_password']) && 1 == $post['have_password']) {
            //手机号注册时需要填写密码但是不需要确认密码
            if (empty($post['password']) || !trim($post['password'])) $this->error('密码不能为空!');
        } elseif (!empty($post['have_password']) && 2 == $post['have_password']){
            //手机号注册时需要填写密码并确认密码
            if (empty($post['password']) || !trim($post['password'])) $this->error('密码不能为空!');
            if (empty($post['confirm_password']) || !trim($post['confirm_password'])) $this->error('确认密码不能为空!');
            if ($post['password'] != $post['confirm_password']) $this->error('两次输入密码不一致!');
        }

        if (empty($post['mobile_code'])) {
            $this->error('验证码不能为空！');
        }
        // 验证验证码
        $RecordWhere = [
            'source' => 0,
            'mobile' => $post['mobile'],
            'code' => $post['mobile_code'],
            'is_use' => 0,
            'lang'   => self::$lang
        ];
        $is_verify = Db::name('sms_log')->where($RecordWhere)->find();
        if (!empty($is_verify)){
            $RecordData  = [
                'is_use' => 1,
                'update_time' => getTime()
            ];
            // 更新数据
            Db::name('sms_log')->where($RecordWhere)->update($RecordData);
        }else{
            $this->error('验证码错误！');
        }
        $users_id = $this->setReg($post);
        if (!empty($users_id)) {
            Db::name('users_list')->insert(['users_id'=>$users_id,'para_id'=>1,'info'=>$post['mobile'],'lang'=>self::$lang,'add_time'=>getTime(),'update_time'=>getTime()]);

            // 更新会员信息
            $update = [
                'last_ip'     => clientIP(),
                'last_login'  => getTime(),
                'login_count' => Db::raw('login_count+1'),
                'update_time' => getTime(),
            ];
            // 查询用户信息
            $usersData = Db::name('users')->where(['users_id' => $users_id])->find();

            // 如果有推荐注册的分销商ID信息则查询并绑定
            $parentUsersID = !empty($post['parent_users_id']) ? intval($post['parent_users_id']) : 0;
            $parentDealerID = !empty($post['parent_dealer_id']) ? intval($post['parent_dealer_id']) : 0;
            $parentDealer = [];
            if (!empty($parentUsersID) && !empty($parentDealerID)) {
                $where = [
                    'a.dealer_status' => 1,
                    'a.users_id' => $parentUsersID,
                    'a.dealer_id' => $parentDealerID,
                ];
                $field = 'a.*, b.is_dealer, b.parent_users_id, b.parent_dealer_id';
                $parentDealer = Db::name('weapp_dealer')->alias('a')->field($field)->where($where)->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->find();
                $dealerOpen = getUsersConfigData('dealer.dealer_open');
                if ((isset($dealerOpen) && 0 === intval($dealerOpen)) || $usersData['users_id'] == $parentDealer['users_id']) {
                    $parentDealer = [];
                }
            }
            if (!empty($parentDealer) && empty($usersData['is_dealer'])) {
                // 如果当前登录用户没有上级分销商则绑定分销商
                if (empty($usersData['parent_users_id']) && empty($usersData['parent_dealer_id'])) {
                    $update['parent_users_id'] = intval($parentDealer['users_id']);
                    $update['parent_dealer_id'] = intval($parentDealer['dealer_id']);
                    $update['bind_dealer_time'] = getTime();
                }
                // 如果当前用户绑定的上级分销商有上级分销商，有则绑定为顶级分销商
                if (!empty($parentDealer['parent_users_id']) && !empty($parentDealer['parent_dealer_id'])) {
                    $update['top_users_id'] = intval($parentDealer['parent_users_id']);
                    $update['top_dealer_id'] = intval($parentDealer['parent_dealer_id']);
                    if (empty($update['bind_dealer_time'])) $update['bind_dealer_time'] = getTime();
                }
            }
            Db::name('users')->where(['users_id' => $users_id])->update($update);
        }

        // 生成token (session3rd)
        $this->token = $this->token('', 'session_key', $users_id, 'openid');

        return $users_id;
    }

    /**
     * 用户注册 用户名注册
     */
    public function account_reg($post)
    {
        if (empty($post["username"])) {
            $this->error("用户名不能为空!");
        }

        // 查询用户名是否已经注册过
        $is_reg = Db::name("users")->where(["username" => $post["username"], "is_del" => 0])->find();

        if (!empty($is_reg)){
            $this->error("用户名已经注册！");
        }

        if (!empty($post["have_password"]) && 1 == $post["have_password"]) {
            // 用户名注册时需要填写密码但是不需要确认密码
            if (empty($post["password"]) || !trim($post["password"])) {
                $this->error("密码不能为空!");
            }
        } elseif (!empty($post["have_password"]) && 2 == $post["have_password"]){
            // 用户名注册时需要填写密码并确认密码
            if (empty($post["password"]) || !trim($post["password"])) {
                $this->error("密码不能为空!");
            }

            if (empty($post["confirm_password"]) || !trim($post["confirm_password"])) {
                $this->error("确认密码不能为空!");
            }

            if ($post["password"] != $post["confirm_password"]) {
                $this->error("两次输入密码不一致!");
            }
        } else {
            // 用户名注册时需要提那些密码但是不需要填写重复密码
            if (empty($post["password"]) || !trim($post["password"])) {
                $this->error("密码不能为空!");
            }
        }

        $users_id = $this->setAccountReg($post);

        if(!empty($users_id)) {
            // 更新会员信息
            $update = [
                "last_ip"     => clientIP(),
                "last_login"  => getTime(),
                "login_count" => Db::raw("login_count+1"),
                "update_time" => getTime(),
            ];

            // 查询用户信息
            $usersData = Db::name("users")->where(["users_id" => $users_id])->find();

            // 如果有推荐注册的分销商ID信息则查询并绑定
            $parentDealer = [];
            $parentUsersID = !empty($post["parent_users_id"]) ? intval($post["parent_users_id"]) : 0;
            $parentDealerID = !empty($post["parent_dealer_id"]) ? intval($post["parent_dealer_id"]) : 0;

            if (!empty($parentUsersID) && !empty($parentDealerID)) {
                $where = [
                    "a.dealer_status" => 1,
                    "a.users_id" => $parentUsersID,
                    "a.dealer_id" => $parentDealerID,
                ];

                $field = "a.*, b.is_dealer, b.parent_users_id, b.parent_dealer_id";

                $parentDealer = Db::name("weapp_dealer")->alias("a")->field($field)->where($where)->join("__USERS__ b", "a.users_id = b.users_id", "LEFT")->find();

                $dealerOpen = getUsersConfigData("dealer.dealer_open");

                if ((isset($dealerOpen) && 0 === intval($dealerOpen)) || $usersData["users_id"] == $parentDealer["users_id"]) {
                    $parentDealer = [];
                }
            }

            if (!empty($parentDealer) && empty($usersData["is_dealer"])) {
                // 如果当前登录用户没有上级分销商则绑定分销商
                if (empty($usersData["parent_users_id"]) && empty($usersData["parent_dealer_id"])) {
                    $update["parent_users_id"] = intval($parentDealer["users_id"]);
                    $update["parent_dealer_id"] = intval($parentDealer["dealer_id"]);
                    $update["bind_dealer_time"] = getTime();
                }

                // 如果当前用户绑定的上级分销商有上级分销商，有则绑定为顶级分销商
                if (!empty($parentDealer["parent_users_id"]) && !empty($parentDealer["parent_dealer_id"])) {
                    $update["top_users_id"] = intval($parentDealer["parent_users_id"]);

                    $update["top_dealer_id"] = intval($parentDealer["parent_dealer_id"]);

                    if (empty($update["bind_dealer_time"])) {
                        $update["bind_dealer_time"] = getTime();
                    }
                }
            }

            Db::name("users")->where(["users_id" => $users_id])->update($update);
        }

        // 生成token (session3rd)
        $this->token = $this->token('', "session_key", $users_id, "openid");

        return $users_id;
    }

    /**
     * 用户登录 手机号验证码登录/手机号密码登录
     */
    public function mobile_login($post)
    {
        if (empty($post['mobile'])) $this->error('手机号不能为空!');
        if (!check_mobile($post['mobile'])) $this->error('手机号格式不正确!');
        $where['mobile'] = $post['mobile'];
        $where['is_mobile'] = 1;
        $where['is_del'] = 0;
        $users = Db::name('users')->where($where)->find();
        if (empty($users)) $this->error('用户不存在！');

        //验证码登录和密码登录取其一
        if (empty($post['password']) || !trim($post['password'])) {
            if (empty($post['mobile_code'])) {
                $this->error('验证码不能为空！');
            }
            // 验证验证码
            $RecordWhere = [
                'source' => 2,
                'mobile' => $post['mobile'],
                'code' => $post['mobile_code'],
                'is_use' => 0,
                'lang'   => self::$lang
            ];
            $is_verify = Db::name('sms_log')->where($RecordWhere)->find();
            if (!empty($is_verify)){
                $RecordData  = [
                    'is_use' => 1,
                    'update_time' => getTime()
                ];
                // 更新数据
                Db::name('sms_log')->where($RecordWhere)->update($RecordData);
            }else{
                $this->error('验证码错误！');
            }
            $users_id = $users['users_id'];
        } else{
            if (empty($post['password']) || !trim($post['password'])) {
                $this->error('密码不能为空！');
            }
            if (0 == config('global.opencodetype')) {
                $where['password']       = func_encrypt($post['password'], false, pwd_encry_type('bcrypt'));
            }

            $users_id = Db::name('users')->where($where)->value('users_id');
            if (empty($users_id)) $this->error('密码不正确');
        }

        if (!empty($users_id)) {
            // 更新会员信息
            $update = [
                'last_ip'     => clientIP(),
                'last_login'  => getTime(),
                'login_count' => Db::raw('login_count+1'),
                'update_time' => getTime(),
            ];
            Db::name('users')->where(['users_id' => $users_id])->update($update);
        }

        // 生成token (session3rd)
        $this->token = $this->token('', 'session_key', $users_id, 'openid');

        return $users_id;
    }

    
    /**
     * 用户登录 账号密码登录
     */
    public function account_login($post)
    {
        $post["username"] = trim($post["username"]);
        if (empty($post["username"])) {
            $this->error("用户名不能为空！");
        }
        $post["password"] = trim($post["password"]);
        if (empty($post["password"])) {
            $this->error("密码不能为空！");
        }

        $where = [];
        $where["username"] = $post["username"];
        $where["is_del"] = 0;
        $users = Db::name("users")->where($where)->find();
        if (empty($users)) {
            $this->error('该用户名不存在，请注册！');
        }
        if ($users["password"] != func_encrypt($post["password"], false, pwd_encry_type("bcrypt"))) {
            $this->error("密码不正确！");
        }
        if (!empty($users['admin_id'])) {
            // 后台账号不允许在前台通过账号密码登录，只能后台登录时同步到前台
            $this->error('前台禁止管理员登录！');
        }
        if (empty($users['is_activation'])) {
            $this->error('该会员尚未激活，请联系管理员！');
        }
        // 更新会员信息
        $update = [
            "last_ip"     => clientIP(),
            "last_login"  => getTime(),
            "login_count" => Db::raw("login_count + 1"),
            "update_time" => getTime(),
        ];
        Db::name("users")->where(["users_id" => $users['users_id']])->update($update);
        // 生成token (session3rd)
        $this->token = $this->token('', "session_key", $users['users_id'], "openid");

        return $users['users_id'];
    }
}