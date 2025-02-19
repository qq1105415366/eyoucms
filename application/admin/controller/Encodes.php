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

namespace app\admin\controller;

use think\Db;
use app\common\logic\FunctionLogic;

/**
 * @package common\Logic
 */
class Encodes extends Base {

    public $functionLogic;

    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        $this->functionLogic = new FunctionLogic;
    }

    public function ajax_system_1610425892()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $this->check_author_ization();
    }

    /**
     * @access public
     */
    private function check_author_ization()
    {
        session('isset_author', 1);

        $globalConfig = tpCache('global');
        if (!empty($globalConfig['php_atqueryrequest'])) {
            $atqueryrequest = json_decode(base64_decode($globalConfig['php_atqueryrequest']), true);
            $atvalue = !isset($globalConfig['php_servicemeal']) ? 0 : $globalConfig['php_servicemeal'];
            $atdata = empty($atqueryrequest[$atvalue]) ? '' : $atqueryrequest[$atvalue];
            $atqueryrequest_time = empty($globalConfig['php_atqueryrequest_time2']) ? 0 : floatval($globalConfig['php_atqueryrequest_time2']);
            if (!empty($atdata) && !empty($atqueryrequest_time)) {
                if (getTime() < ($atqueryrequest_time + floatval($atdata['expire_time']))) {
                    return false;
                }
            }
        }

        $web_basehost = $this->request->host(true);
        if (false !== filter_var($web_basehost, FILTER_VALIDATE_IP) || $web_basehost == 'localhost' || file_exists('./data/conf/multidomain.txt') || preg_match('/\.(my3w\.com)$/i', $web_basehost)) {
            $web_basehost = empty($globalConfig['web_basehost']) ? '' : $globalConfig['web_basehost'];
        }
        $web_basehost = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);

        $values = array(
            'client_domain' => urldecode($web_basehost),
            'ip'    => serverIP(),
            'curent_version' => getCmsVersion(),
        );
        $php_eyou_auth_info = empty($globalConfig['php_eyou_auth_info']) ? '' : $globalConfig['php_eyou_auth_info'];
        if (!empty($php_eyou_auth_info)) {
            $values['agentcode'] = 1;
            $values['mid'] = $php_eyou_auth_info;
        }
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        $upgradeLogic->GetKeyData($values);
        $url = $upgradeLogic->getServiceUrl(true).'/index.php?m=api&c=Service&a=get_authortoken';
        $response = @httpRequest($url, 'POST', $values, [], 3);
        if (empty($response)) {
            $url = $url.'&'.http_build_query($values);
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $response = @file_get_contents($url, false, $context);
        }
        $params = json_decode($response,true);
        $php_websensitive = !empty($params['info']['websensitive']) ? $params['info']['websensitive'] : '';
        $atqueryrequest = !empty($params['info']['atqueryrequest']) ? $params['info']['atqueryrequest'] : '';
        session('ddcb67037bb834e0c456514b', 0); // 是
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('web', ['web_is_authortoken'=>0], $val['mark']); // 是
                $cdata = ['php_eyou_blacklist'=>'','php_weapp_plugin_open'=>1,'php_servicemeal'=>2,'php_websensitive'=>$php_websensitive];
                !empty($atqueryrequest) && $cdata['php_atqueryrequest'] = $atqueryrequest;
                tpCache('php', $cdata, $val['mark']); // 是
            }
        } else { // 单语言
            tpCache('web', ['web_is_authortoken'=>0]); // 是
            $cdata = ['php_eyou_blacklist'=>'','php_weapp_plugin_open'=>1,'php_servicemeal'=>2,'php_websensitive'=>$php_websensitive];
            !empty($atqueryrequest) && $cdata['php_atqueryrequest'] = $atqueryrequest;
            tpCache('php', $cdata); // 是
        }
        /*--end*/

        if (is_array($params) && $params['errcode'] == 0) {

            if (!empty($params['info'])) {
                if (!empty($params['info']['code'])) {
                    $file = "./data/conf/{$params['info']['code']}.txt";
                    if (2 <= $params['info']['pid'] && !file_exists($file)) {
                        $params['info']['pid'] = 1;
                        /*多语言*/
                        if (is_language()) {
                            $langRow = Db::name('language')->order('id asc')->select();
                            foreach ($langRow as $key => $val) {
                                tpCache('php', ['php_servicemeal'=>$params['info']['pid']], $val['mark']);
                            }
                        } else { // 单语言
                            tpCache('php', ['php_servicemeal'=>$params['info']['pid']]);
                        }
                        /*--end*/
                    }
                }

                $tpCacheData = [];
                $tpCacheData['php_serviceinfo'] = mchStrCode(json_encode($params['info']));
                $tpCacheData['php_servicemeal'] = !empty($params['info']['pid']) ? $params['info']['pid'] : 0;
                isset($params['info']['weapp_plugin_open']) && $tpCacheData['php_weapp_plugin_open'] = $params['info']['weapp_plugin_open'];
                isset($params['info']['php_allow_service_os']) && $tpCacheData['php_allow_service_os'] = $params['info']['php_allow_service_os'];
                isset($params['info']['php_upgradeList']) && $tpCacheData['php_upgradeList'] = $params['info']['php_upgradeList'];
                $tpCacheData['php_atqueryrequest_time2'] = getTime();
                if (!empty($params['info']['code'])) {
                    $tpCacheData['php_servicecode'] = $params['info']['code'];
                } else {
                    $tpCacheData['php_servicecode'] = '';
                    $tpCacheData['php_servicemeal'] = 0;
                }
                // 代理
                isset($params['info']['auth_info']) && $tpCacheData['php_auth_function'] = $params['info']['auth_info'];
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('php', $tpCacheData, $val['mark']); // 否
                    }
                } else { // 单语言
                    tpCache('php', $tpCacheData); // 否
                }
                /*--end*/
                
                $file = "./data/conf/{$tpCacheData['php_servicecode']}.txt";
                if (empty($tpCacheData['php_servicemeal'])) {
                    getUsersConfigData('shop', ['shop_open'=>0]);
                } else if (2 <= $tpCacheData['php_servicemeal'] && !file_exists($file)) {
                    $fp = fopen($file, "w+");
                    if (!empty($fp)) {
                        fwrite($fp, $tpCacheData['php_servicecode']);
                    }
                    fclose($fp);
                }

                // 云插件库开关
                $file = "./data/conf/weapp_plugin_open.txt";
                $fp = fopen($file, "w+");
                if (!empty($fp)) {
                    fwrite($fp, $tpCacheData['php_weapp_plugin_open']);
                }
                fclose($fp);
            }

            if (empty($params['info']['code'])) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('web', ['web_is_authortoken'=>-1], $val['mark']); // 否
                    }
                } else { // 单语言
                    tpCache('web', ['web_is_authortoken'=>-1]); // 否
                }
                /*--end*/
                session('ddcb67037bb834e0c456514b', -1); // 只在Base用
                $this->success('ok');
            }
        } else {
            try {
                $version = getVersion();
                if (preg_match('/^v(\d+)\.(\d+)\.(\d+)_(.*)$/i', $version)) {
                    $paginate_type = str_replace(['jsonpR','turn'], ['','y_'], config('default_jsonp_handler'));
                    $filename = strtoupper(md5($paginate_type.$version));
                    $file = "./data/conf/{$filename}.txt";
                    $tmpMealValue = file_exists($file) ? 2 : 0;
                    $is_authortoken = !empty($tmpMealValue) ? 0 : -1;
                    $serviceinfo = mchStrCode(json_encode(['pid'=>$tmpMealValue,'code'=>$filename]));
                    /*多语言*/
                    if (is_language()) {
                        $langRow = \think\Db::name('language')->order('id asc')->select();
                        foreach ($langRow as $key => $val) {
                            tpCache('php', ['php_servicemeal'=>$tmpMealValue, 'php_serviceinfo'=>$serviceinfo], $val['mark']);
                            tpCache('web', ['web_is_authortoken'=>$is_authortoken], $val['mark']);
                        }
                    } else { // 单语言
                        tpCache('php', ['php_servicemeal'=>$tmpMealValue, 'php_serviceinfo'=>$serviceinfo]);
                        tpCache('web', ['web_is_authortoken'=>$is_authortoken]);
                    }
                    /*--end*/
                }

            } catch (\Exception $e) {}
        }
        if (is_array($params) && $params['errcode'] == 10002) {
            $ctl_act_list = array();
            $ctl_act_str = strtolower($this->request->controller()).'_'.strtolower($this->request->action());
            if(in_array($ctl_act_str, $ctl_act_list))  
            {

            } else {
                session('isset_author', null);

                /*多语言*/
                $tmpval = 'EL+#$JK'.base64_encode($params['errmsg']).'WENXHSK#0m3s';
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('php', ['php_eyou_blacklist'=>$tmpval], $val['mark']); // 是
                    }
                } else { // 单语言
                    tpCache('php', ['php_eyou_blacklist'=>$tmpval]); // 是
                }
                /*--end*/
            }
        }

        $this->success('ok');
    }

    // 批量新增Tag标签
    public function ajax_tags_batch_add()
    {
        if (IS_POST) {
            $post = input('post.');

            $tags = trim($post['tags']);
            if (empty($tags)) {
                $this->error('Tag列表不能为空！');
            }

            $tagsArr = explode("\r\n", $tags);
            $tagsArr = array_filter($tagsArr);//去除数组空值
            $tagsArr = array_unique($tagsArr); //去重
            foreach ($tagsArr as $key => $val) {
                $tagsArr[$key] = trim($val);
            }

            $addData = [];
            $tagsList = Db::name('tagindex')->where([
                    'tag'  => ['IN', $tagsArr],
                    'lang'      => $this->admin_lang,
                ])->column('tag');
            foreach ($tagsArr as $key => $val) {
                if(empty($val) || in_array($val, $tagsList)) continue;

                $addData[] = [
                    'tag'               => $val,
                    'typeid'            => 0,
                    'seo_description'   => '',
                    'lang'              => $this->admin_lang,
                    'add_time'          => getTime(),
                    'update_time'       => getTime(),
                ];
            }
            if (!empty($addData)) {
                $r = model('Tagindex')->saveAll($addData);
                if (!empty($r)) {
                    adminLog('批量新增Tag标签：'.get_arr_column($addData, 'tag'));
                    $this->success('操作成功！');
                } else {
                    $this->error('操作失败');
                }
            } else {
                $this->success('操作成功！');
            }
        }
    }
}