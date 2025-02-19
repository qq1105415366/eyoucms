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
use think\Page;

class Canal extends Base
{
    public function _initialize(){
        parent::_initialize();
    }

    public function conf_api()
    {
        if (IS_AJAX_POST) {
            $data = tpSetting("OpenMinicode.conf", [], $this->main_lang);
            $data = json_decode($data, true);
            empty($data) && $data = [];

            $post = input('post.');
            // if (isset($post['apikey'])) {
            //     unset($post['apikey']);
            // }
            if ($post['apikey'] != $post['old_apikey']) {
                $post['apikey_uptime'] = getTime();
            }
            $data = array_merge($data, $post);
            tpSetting('OpenMinicode', ['conf' => json_encode($data)], $this->main_lang);
            $this->success("操作成功");
        }

        // 同步微信配置
        if (is_dir('./weapp/OpenMinicode/')) {
            $admin_logic_1649404323 = tpSetting('syn.admin_logic_1649404323', [], 'cn');
            if (empty($admin_logic_1649404323)) {
                $minicode = Db::name('weapp')->where('code', 'OpenMinicode')->value('data');
                $minicode = json_decode($minicode, true);
                if (!empty($minicode['appid'])) {
                    $data = [
                        'appid'  => $minicode['appid'],
                        'appsecret' => $minicode['secret'],
                        'mchid'  => '',
                        'apikey' => '',
                    ];
                    tpSetting('OpenMinicode', ['conf_weixin' => json_encode($data)], $this->main_lang);
                }
                tpSetting('syn', ['admin_logic_1649404323'=>1], 'cn');
            }
        }

        $data = tpSetting("OpenMinicode.conf", [], $this->main_lang);
        if (empty($data)) {
            $data = [];
            $data['apiopen'] = 0;
            $data['apiverify'] = 0;
            $data['apikey'] = get_rand_str(32, 0, 1);
            tpSetting('OpenMinicode', ['conf' => json_encode($data)], $this->main_lang);
        } else {
            $data = json_decode($data, true);
        }
        $this->assign('data', $data);

        //微信信息
        $weixin_data = tpSetting("OpenMinicode.conf_weixin", [], $this->main_lang);
        $weixin_data = json_decode($weixin_data, true);
        $this->assign('weixin_data', $weixin_data);
        // 小程序码
        $weixin_qrcodeurl = "";
        if (!empty($weixin_data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20220515/wx-{$weixin_data['appid']}.png";
            if (is_file($filepath)) {
                $weixin_qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $this->assign('weixin_qrcodeurl', $weixin_qrcodeurl);
        //百度信息
        $baidu_data = tpSetting("OpenMinicode.conf_baidu", [], $this->main_lang);
        $baidu_data = json_decode($baidu_data, true);
        $this->assign('baidu_data', $baidu_data);
        // 小程序码
        $baidu_qrcodeurl = "";
        if (!empty($baidu_data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20220515/bd-{$baidu_data['appid']}.png";
            if (is_file($filepath)) {
                $baidu_qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $this->assign('baidu_qrcodeurl', $baidu_qrcodeurl);

        // 抖音信息
        $toutiao_data = tpSetting("OpenMinicode.conf_toutiao", [], $this->main_lang);
        $toutiao_data = !empty($toutiao_data) ? json_decode($toutiao_data, true) : [];
        $this->assign('toutiao_data', $toutiao_data);
        // 小程序码
        $toutiao_qrcodeurl = "";
        if (!empty($toutiao_data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20220515/tt-{$toutiao_data['appid']}.png";
            if (is_file($filepath)) {
                $toutiao_qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $this->assign('toutiao_qrcodeurl', $toutiao_qrcodeurl);

        return $this->fetch();
    }

    /**
     * 重置API接口密钥
     * @return [type] [description]
     */
    public function reset_apikey()
    {
        if (IS_AJAX_POST) {
            $data = tpSetting("OpenMinicode.conf", [], $this->main_lang);
            $data = json_decode($data, true);
            empty($data) && $data = [];
            $apikey = get_rand_str(32, 0, 1);
            $data['apikey'] = $apikey;
            // tpSetting('OpenMinicode', ['conf' => json_encode($data)], $this->main_lang);
            $this->success("重置成功", null, ['apikey'=>$apikey]);
        }
    }

    /**
     * 微信小程序配置
     * @return [type] [description]
     */
    public function conf_weixin()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $appid = !empty($post['appid']) ? trim($post['appid']) : trim($post['weixin_appid']);
            $appsecret = !empty($post['appsecret']) ? trim($post['appsecret']) : trim($post['weixin_appsecret']);
            $mchid = !empty($post['mchid']) ? trim($post['mchid']) : trim($post['weixin_mchid']);
            $apikey = !empty($post['apikey']) ? trim($post['apikey']) : trim($post['weixin_apikey']);

            if (!empty($appid) || !empty($appsecret)) {
                if (empty($appid)) {
                    $this->error('AppID不能为空！');
                }
                if (empty($appsecret)) {
                    $this->error('AppSecret不能为空！');
                }
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
                $response = httpRequest($url);
                $params = json_decode($response, true);
                if (!empty($params['errcode'])) {
                    if ($params['errcode'] == 40164) {
                        preg_match_all('#(\d{1,3}\.){3}\d{1,3}#i', $params['errmsg'], $matches);
                        $ip = !empty($matches[0][0]) ? $matches[0][0] : '';
                        $ipTips = "请将IP：{$ip} 加入微信小程序的<font color='red'>IP白名单</font>里！";
                        $this->error($ipTips);
                    } else {
                        $this->error($params['errmsg']);
                    }
                }

                if (!empty($appid) && !empty($mchid) && !empty($apikey)) {
                    $logic = new \app\api\logic\v1\ApiLogic;
                    $returnData = $logic->GetWechatAppletsPay($appid, $mchid, $apikey);
                    if (empty($returnData['code'])) {
                        $this->error($returnData['msg']);
                    }
                }
            }

            $data = [
                'appid'  => $appid,
                'appsecret' => $appsecret,
                'mchid'  => $mchid,
                'apikey' => $apikey,
            ];
            tpSetting('OpenMinicode', ['conf_weixin' => json_encode($data)], $this->main_lang);
            $this->success("操作成功");
        }

        $data = tpSetting("OpenMinicode.conf_weixin", [], $this->main_lang);
        $data = json_decode($data, true);
        $this->assign('data', $data);

        // 小程序码
        $qrcodeurl = "";
        if (!empty($data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20220515/wx-{$data['appid']}.png";
            if (is_file($filepath)) {
                $qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $this->assign('qrcodeurl', $qrcodeurl);

        return $this->fetch();
    }

    /**
     * 获取微信小程序码
     * @return [type] [description]
     */
    public function ajax_get_weixin_qrcode()
    {
        $data = tpSetting("OpenMinicode.conf_weixin", [], $this->main_lang);
        $data = json_decode($data, true);
        $appid = !empty($data['appid']) ? $data['appid'] : '';
        $appsecret = !empty($data['appsecret']) ? $data['appsecret'] : '';
        if (!empty($appid) && !empty($appsecret)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
            $response = httpRequest($url);
            $params = json_decode($response, true);
            if (isset($params['access_token'])) {
                $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$params['access_token'];
                $post_data = array(
                    "scene" => 'test',
                    "width" => 280,
                );
                $response = httpRequest($url, 'POST', json_encode($post_data, JSON_UNESCAPED_UNICODE));
                $params = json_decode($response,true);
                if (is_array($params) || $response === false) {
                    $msg = !empty($params['errmsg']) ? $params['errmsg'] : '可能没发布小程序';
                    $this->error($msg);
                } else {
                    $qrcodeurl = UPLOAD_PATH.'allimg/20220515';
                    tp_mkdir($qrcodeurl);
                    $qrcodeurl = $qrcodeurl."/wx-{$appid}.png";
                    if (@file_put_contents($qrcodeurl, $response)){
                        $qrcodeurl = $this->root_dir.'/'.$qrcodeurl;
                        $this->success('生成小程序码成功', null, ['qrcodeurl'=>$qrcodeurl]);
                    } else {
                        $this->error('生成小程序码失败');
                    }
                }
            }
        }

        $this->error('不存在信息');
    }

    /**
     * 百度小程序配置
     * @return [type] [description]
     */
    public function conf_baidu()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 百度登录配置
            $config['appid'] = !empty($post['appid']) ? trim($post['appid']) : trim($post['baidu_appid']);
            $config['appkey'] = !empty($post['appkey']) ? trim($post['appkey']) : trim($post['baidu_appkey']);
            $config['appsecret'] = !empty($post['appsecret']) ? trim($post['appsecret']) : trim($post['baidu_appsecret']);
            if (empty($config['appid'])) $this->error('百度登录App ID不允许为空');
            if (empty($config['appkey'])) $this->error('百度登录App Key不允许为空');
            if (empty($config['appsecret'])) $this->error('百度登录App Secret不允许为空');

            // 百度支付配置
            $config['payAppid'] = !empty($post['payAppid']) ? trim($post['payAppid']) : trim($post['baidu_payAppid']);
            $config['payAppkey'] = !empty($post['payAppkey']) ? trim($post['payAppkey']) : trim($post['baidu_payAppkey']);
            $config['payDealId'] = !empty($post['payDealId']) ? trim($post['payDealId']) : trim($post['baidu_payDealId']);
            $config['paySecret'] = !empty($post['paySecret']) ? trim($post['paySecret']) : trim($post['baidu_paySecret']);

            // 调用百度支付模型
            $baiduPayModel = new \app\common\model\BaiduPay($config, true);
            // 验证登录配置
            if (!empty($config['appid']) && !empty($config['appkey']) && !empty($config['appsecret'])) $config = $baiduPayModel->getBaiduAccessToken();
            // 验证支付配置
            if (!empty($config['payAppid']) && !empty($config['payAppkey']) && !empty($config['payDealId'])  && !empty($config['paySecret'])) $config = $baiduPayModel->queryOrderPayResult();

            // 保存配置信息
            tpSetting('OpenMinicode', ['conf_baidu' => json_encode($config)], $this->main_lang);
            $this->success("操作成功");
        }

        $data = tpSetting("OpenMinicode.conf_baidu", [], $this->main_lang);
        $data = json_decode($data, true);
        $this->assign('data', $data);

        // 小程序码
        $qrcodeurl = "";
        if (!empty($data['appid'])) {
            $filepath = UPLOAD_PATH."allimg/20220515/bd-{$data['appid']}.png";
            if (is_file($filepath)) {
                $qrcodeurl = "{$this->root_dir}/".$filepath;
            }
        }
        $this->assign('qrcodeurl', $qrcodeurl);

        return $this->fetch();
    }

    /**
     * 获取百度小程序码
     * @return [type] [description]
     */
    public function ajax_get_baidu_qrcode()
    {
        $data = tpSetting("OpenMinicode.conf_baidu", [], $this->main_lang);
        $data = json_decode($data, true);
        $appid = !empty($data['appid']) ? $data['appid'] : '';
        $appkey = !empty($data['appkey']) ? $data['appkey'] : '';
        $appsecret = !empty($data['appsecret']) ? $data['appsecret'] : '';
        if (!empty($appkey) && !empty($appsecret)) {
            $url = "https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id={$appkey}&client_secret={$appsecret}&scope=smartapp_snsapi_base";
            $response = httpRequest($url);
            $params = json_decode($response, true);
            if (isset($params['access_token'])) {
                $url = "https://openapi.baidu.com/rest/2.0/smartapp/qrcode/getv2";
                $post_data = array(
                    "access_token" => $params['access_token'],
                );
                $response = httpRequest($url, 'POST', $post_data);
                $params = json_decode($response,true);
                if (!isset($params['data']['base64_str'])) {
                    $msg = !empty($params['errmsg']) ? $params['errmsg'] : '可能没发布小程序';
                    $this->error($msg);
                } else {
                    $qrcodeurl = UPLOAD_PATH.'allimg/20220515';
                    tp_mkdir($qrcodeurl);
                    $qrcodeurl = $qrcodeurl."/bd-{$appid}.png";
                    if (@file_put_contents($qrcodeurl, base64_decode($params['data']['base64_str']))){
                        $qrcodeurl = $this->root_dir.'/'.$qrcodeurl;
                        $this->success('生成小程序码成功', null, ['qrcodeurl'=>$qrcodeurl]);
                    } else {
                        $this->error('生成小程序码失败');
                    }
                }
            }
        }
        $this->error('不存在信息');
    }

    /**
     * 微信公众号配置
     * @return [type] [description]
     */
    public function conf_wechat()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $appid = trim($post['appid']);
            $appsecret = trim($post['appsecret']);

            if (!empty($appid) || !empty($appsecret)) {
                if (empty($appid)) {
                    $this->error('AppID不能为空！');
                }
                if (empty($appsecret)) {
                    $this->error('AppSecret不能为空！');
                }

                /*$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
                $response = httpRequest($url);
                $params = json_decode($response, true);
                if (!isset($params['access_token'])) {
                    $wechat_code = config('error_code.wechat');
                    $msg = !empty($wechat_code[$params['errcode']]) ? $wechat_code[$params['errcode']] : $params['errmsg'];
                    $this->error($msg);
                }*/
            }

            $data = [
                'appid'  => $appid,
                'appsecret' => $appsecret,
            ];

            // 兼容老数据的功能，同步保存一份到以前配置里
            $wechat_login_config = $this->usersConfig['wechat_login_config'];
            $login_config = unserialize($wechat_login_config);
            $data['wechat_name'] = !empty($login_config['wechat_name']) ? trim($login_config['wechat_name']) : '';
            $data['wechat_pic'] = !empty($login_config['wechat_pic']) ? trim($login_config['wechat_pic']) : '';
            getUsersConfigData('wechat', ['wechat_login_config'=>serialize($data)]);

            tpSetting('OpenMinicode', ['conf_wechat' => json_encode($data)], $this->main_lang);

            $this->success("操作成功");
        }

        $data = tpSetting("OpenMinicode.conf_wechat", [], $this->main_lang);
        if (empty($data)) {
            $wechat_login_config = getUsersConfigData('wechat.wechat_login_config');
            $login_config = unserialize($wechat_login_config);
            if (!empty($login_config)) {
                $data = [];
                $data['appid'] = !empty($login_config['appid']) ? trim($login_config['appid']) : '';
                $data['appsecret'] = !empty($login_config['appsecret']) ? trim($login_config['appsecret']) : '';
                $data['wechat_name'] = !empty($login_config['wechat_name']) ? trim($login_config['wechat_name']) : '';
                $data['wechat_pic'] = !empty($login_config['wechat_pic']) ? trim($login_config['wechat_pic']) : '';
                tpSetting('OpenMinicode', ['conf_wechat' => json_encode($data)], $this->main_lang);
            }
        } else {
            $data = json_decode($data, true);
        }
        $this->assign('data', $data);
        /*微站点配置*/
        $login = !empty($this->usersConfig['wechat_login_config']) ? unserialize($this->usersConfig['wechat_login_config']) : [];
        $this->assign('login', $login);
        /* END */

        /*验证IP是否加入白名单中*/
        $ipTips = '';
        if (!empty($data['appid']) && !empty($data['appsecret'])) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$data['appid']}&secret={$data['appsecret']}";
            $res = json_decode(httpRequest($url), true);
            if (!empty($res['errcode'])) {
                if ($res['errcode'] == 40164) {
                    preg_match_all('#(\d{1,3}\.){3}\d{1,3}#i', $res['errmsg'], $matches);
                    $ip = !empty($matches[0][0]) ? $matches[0][0] : '';
                    $ipTips = "请将IP：<font color='red'>{$ip} </font>加入微信公众号的<font color='red'>IP白名单</font>里，具体点击<a href=\"JavaScript:void(0);\" onclick=\"click_to_eyou_1575506523('https://www.eyoucms.com/plus/view.php?aid=9432&origin_eycms=1','IP白名单配置教程')\">查看教程</a>！";
                } else {
                    $ipTips = "<font color='red'>{$res['errmsg']}</font>";
                }
            } else if (isset($res['access_token'])) {
                $setting_info = [
                    'appid' => $data['appid'],
                    'secret' => $data['appsecret'],
                    'access_token' => $res['access_token'],
                    'expires_time' => getTime() + $res['expires_in'] - 1000, //提前200s过期
                ];
                tpSetting(md5($data['appid']), $setting_info);
            }
        }
        $this->assign('ipTips', $ipTips);
        /*--end*/

        return $this->fetch();
    }

    // 头条(抖音)配置
    public function conf_toutiao()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $salt = !empty($post['salt']) ? trim($post['salt']) : trim($post['toutiao_salt']);
            $appid = !empty($post['appid']) ? trim($post['appid']) : trim($post['toutiao_appid']);
            $secret = !empty($post['appsecret']) ? trim($post['appsecret']) : trim($post['toutiao_appsecret']);
            if (empty($appid)) $this->error('AppID不能为空！');
            if (empty($secret)) $this->error('AppSecret不能为空！');
            $params = getToutiaoAccessToken($appid, $secret, $salt, true);
            if (empty($params['access_token'])) $this->error('AppID或AppSecret不正确！');
            $this->success("操作成功");
        }
        $this->error('操作失败，请刷新重试');
    }

    /**
     * 获取头条(抖音)小程序码
     * @return [type] [description]
     */
    public function ajax_get_toutiao_qrcode()
    {
        $data = tpSetting("OpenMinicode.conf_toutiao", [], $this->main_lang);
        $data = json_decode($data, true);
        $appid = !empty($data['appid']) ? $data['appid'] : '';
        $secret = !empty($data['secret']) ? $data['secret'] : '';
        $salt = !empty($data['salt']) ? $data['salt'] : '';
        if (!empty($appid) && !empty($secret)) {
            $url = "https://open.douyin.com/oauth/client_token/";
            $post_data = array(
                "client_key" => $appid,
                "client_secret" => $secret,
                "grant_type" => "client_credential",
            );
            $headers = ["content-type: application/json"];
            $response = httpRequest($url, 'POST', json_encode($post_data, JSON_UNESCAPED_UNICODE), $headers);
            $params = json_decode($response,true);
            if (isset($params['data']['access_token'])) {
                $url = "https://open.douyin.com/api/apps/v1/qrcode/create/";
                $post_data = array(
                    "appid" => $appid,
                    "is_circle_code" => true,
                );
                $headers = ["content-type: application/json", "access-token: {$params['data']['access_token']}"];
                $response = httpRequest($url, 'POST', json_encode($post_data, JSON_UNESCAPED_UNICODE), $headers);
                $params = json_decode($response,true);
                if (empty($params['data']['img'])) {
                    $msg = !empty($params['err_msg']) ? $params['err_msg'] : '可能没发布小程序';
                    $this->error($msg);
                } else {
                    $qrcodeurl = UPLOAD_PATH.'allimg/20220515';
                    tp_mkdir($qrcodeurl);
                    $qrcodeurl = $qrcodeurl."/tt-{$appid}.png";
                    if (@file_put_contents($qrcodeurl, base64_decode($params['data']['img']))){
                        $qrcodeurl = $this->root_dir.'/'.$qrcodeurl;
                        $this->success('生成小程序码成功', null, ['qrcodeurl'=>$qrcodeurl]);
                    } else {
                        $this->error('生成小程序码失败');
                    }
                }
            }
        }

        $this->error('不存在信息');
    }

    /**
     * 启用、关闭 开放API
     * @return [type] [description]
     */
    public function ajax_save_apiopen()
    {
        if (IS_AJAX_POST) {
            $apiopen = input('post.open_value/d');
            $data = tpSetting("OpenMinicode.conf", [], $this->main_lang);
            $data = json_decode($data, true);
            empty($data) && $data = [];
            $data['apiopen'] = $apiopen;
            tpSetting('OpenMinicode', ['conf' => json_encode($data)], $this->main_lang);
            $this->success('操作成功');
        }
    }

    /**
     * 推送微信公众号消息
     * @return [type] [description]
     */
    public function wechat_push_notice()
    {
        $assign_data = [];
        $notice_wechat_tpl = [
            1 => ['id' => '51345','keyword_name'=>['需求项目','需求时间']], // 留言表单成功通知
            9 => ['id' => '51617','keyword_name'=>['订单编号','产品名称','订单金额','支付时间']], // 订单支付成功通知
        ];

        if (IS_POST) {
            // 是否填写微信公众号配置
            $wechat_data = tpSetting("OpenMinicode.conf_wechat");
            $wechat_data = !empty($wechat_data) ? json_decode($wechat_data, true) : [];
            if (empty($wechat_data['appid'])) {
                $this->error("请先完善公众号配置");
            }

            $post = input('post.');
            foreach ($post as $key => $val) {
                if (in_array($key, ['wechat']) && is_array($val)) {
                    tpCache($key, $val);
                }
            }

            $send_scene_arr = input('post.send_scene_arr/a');
            if (empty($send_scene_arr)) {
                Db::name('wechat_template')->where(['tpl_id'=>['gt', 0]])->update(['is_open'=>0]);
                $this->success('操作成功');
            } else {
                foreach ($send_scene_arr as $_key => $send_scene) {
                    $where = [
                        'lang' => $this->main_lang,
                        'send_scene' => $send_scene
                    ];
                    $info = Db::name('wechat_template')->where($where)->find();
                    if (empty($info['template_code']) || $notice_wechat_tpl[$send_scene]['id'] != $info['template_code']) {
                        $info['tpl_data'] = empty($info['tpl_data']) ? [] : json_decode($info['tpl_data'], true);
                        $tokenData = get_wechat_access_token();
                        if (!empty($tokenData['code'])) {
                            // 添加消息模板
                            $url = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token=".$tokenData['access_token'];
                            $post_data = array(
                                'template_id_short' => $notice_wechat_tpl[$send_scene]['id'],
                                'keyword_name_list' => $notice_wechat_tpl[$send_scene]['keyword_name'],
                            );
                            $response = httpRequest($url, 'POST', json_encode($post_data, JSON_UNESCAPED_UNICODE));
                            $params = json_decode($response, true);
                            if (!empty($params['template_id'])) {
                                $update = [
                                    'template_code' => $notice_wechat_tpl[$send_scene]['id'],
                                    'template_id' => $params['template_id'],
                                ];
                                // 获取模板的关键词列表 start
                                /*$url = "https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token=".$tokenData['access_token'];
                                $response = httpRequest($url);
                                $params2 = json_decode($response,true);
                                if (isset($params2['template_list'])) {
                                    $params2['template_list'] = convert_arr_key($params2['template_list'], 'template_id');
                                    $template_data = !empty($params2['template_list'][$params['template_id']]) ? $params2['template_list'][$params['template_id']] : [];
                                    $keywordsList = [];
                                    if (!empty($template_data)) {
                                        $update['template_title'] = $template_data['title'];
                                        $template_data['content'] = str_replace(["\n\r","\r\n"], '|', $template_data['content']);
                                        $template_data['content'] = str_replace(["\n","\r"], '|', $template_data['content']);
                                        $arr = explode('|', $template_data['content']);
                                        foreach ($arr as $_k => $_v) {
                                            $_v = trim($_v);
                                            if (!stristr($_v, '{{')) {
                                                unset($arr[$_k]);
                                            }
                                        }
                                        $template_data['content'] = $arr;
                                        $template_data['example'] = str_replace(["\n\r","\r\n"], '|', $template_data['example']);
                                        $template_data['example'] = str_replace(["\n","\r"], '|', $template_data['example']);
                                        $arr = explode('|', $template_data['example']);
                                        foreach ($arr as $_k => $_v) {
                                            $_v = trim($_v);
                                            if (!stristr($_v, ':') && !stristr($_v, '：')) {
                                                unset($arr[$_k]);
                                            }
                                        }
                                        $template_data['example'] = $arr;
                                        foreach ($template_data['content'] as $key => $val) {
                                            if (stristr($val, '{{first.')) {
                                                !empty($template_data['example'][$key]) && $update['template_title'] = trim($template_data['example'][$key]);
                                                $update['template_title'] = '您有一笔退货订单，买家已发货';
                                            } else if (stristr($val, '{{remark.')) {
                                                $keywordsList[$key]['name'] = '备注';
                                                $keywordsList[$key]['example'] = !empty($template_data['example'][$key]) ? $template_data['example'][$key] : '';
                                                $keywordsList[$key]['example'] = '请注意查收，记得及时处理哦！';
                                                $keywordsList[$key]['rule'] = 'remark';
                                            } else {
                                                $example_name = preg_replace('/^(.*)(\:|\：)(.*)$/i', '${1}', $val);
                                                $example_val = empty($template_data['example'][$key]) ? '' : $template_data['example'][$key];
                                                $example_val = preg_replace('/^'.str_replace('/', '\/', $example_name).'(:|：)?/i', '', $template_data['example'][$key]);
                                                $keywordsList[$key] = [
                                                    'name' => $example_name,
                                                    'example' => $example_val,
                                                    'rule' =>preg_replace('/^(.*)\{\{([^\.]+)(.*)$/i', '${2}', $val),
                                                ];
                                            }
                                        }
                                    }
                                    $info['tpl_data']['keywordsList'] = $keywordsList;
                                    $update['tpl_data'] = json_encode($info['tpl_data']);
                                } else {
                                    // 删除新增失败的消息模板
                                    $url = "https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token=".$tokenData['access_token'];
                                    $post_data = array(
                                        "template_id" => $params['template_id'],
                                    );
                                    httpRequest($url, 'POST', $post_data);
                                }*/
                                // 获取模板的关键词列表 end
        
                                Db::name('wechat_template')->where(['send_scene'=>$send_scene])->update($update);

                                // 删除旧的消息模板
                                $url = "https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token=".$tokenData['access_token'];
                                $post_data = array(
                                    "template_id" => $info['template_id'],
                                );
                                httpRequest($url, 'POST', $post_data);
                            } else {
                                $msg = !empty($params['errmsg']) ? $params['errmsg'] : '保存失败';
                                $this->error($msg);
                            }
                        } else {
                            $this->error($tokenData['msg']);
                        }
                    }
                }
                Db::name('wechat_template')->where(['send_scene'=>['NOTIN', $send_scene_arr]])->update(['is_open'=>0]);
                Db::name('wechat_template')->where(['send_scene'=>['IN', $send_scene_arr]])->update(['is_open'=>1]);
                $this->success('操作成功');
            }
        }

        $assign_data['admin_id'] = session('admin_id');
        $assign_data['notice_wechat_tpl'] = $notice_wechat_tpl;

        $list = Db::name('wechat_template')->where(['lang'=>$this->admin_lang])->select();
        foreach ($list as $key => $val) {
            $val['tpl_data'] = !empty($val['tpl_data']) ? json_decode($val['tpl_data'], true) : [];
            $list[$key] = $val;
        }
        $assign_data['list'] = $list;

        // 已有N人绑定并关注公众号
        $admin_list = Db::name('admin')->where(['wechat_followed'=>1])->getAllWithIndex('admin_id');
        $assign_data['adminBindNum'] = count($admin_list);
        // 当前管理员是否绑定
        $admin_id = (int)session('admin_id');
        $assign_data['cur_admin_bind'] = empty($admin_list[$admin_id]) ? 0 : 1;
        // 是否填写微信公众号配置
        $wechat_data = tpSetting("OpenMinicode.conf_wechat");
        $wechat_data = !empty($wechat_data) ? json_decode($wechat_data, true) : [];
        $assign_data['wechat_data'] = $wechat_data;

        $this->assign($assign_data);
        return $this->fetch();
    }

}