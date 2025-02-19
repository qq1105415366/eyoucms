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

// 模板错误提示
switch_exception();

if (!function_exists('apiAdminLog'))
{
    /**
     * 管理员操作记录
     * @param $log_url 操作URL
     * @param $log_info 记录信息
     */
    function apiAdminLog($log_info = ''){
        $admin_id = session('admin_id');
        $admin_id = !empty($admin_id) ? $admin_id : -2;
        $add['log_time'] = getTime();
        $add['admin_id'] = $admin_id;
        $add['log_info'] = $log_info;
        $add['log_ip'] = clientIP();
        $add['log_url'] = request()->baseUrl() ;
        M('admin_log')->add($add);
    }
}

if (!function_exists('push_bdminiproapi'))
{
    /**
     * 将新链接推送给百度
     * 将小程序资源 path 路径，提交到 API 接口中
     */
    function push_bdminiproapi($access_token = '',$type = '1', $aid = '', $typeid = '')
    {
        $aid = intval($aid);
        $typeid = intval($typeid);

        $urls = '';
        if (!empty($aid)){
            $nid = \think\Db::name('archives')->alias('a')->join('channeltype b','a.channel = b.id')->where('a.aid',$aid)->value('b.nid');
            if ('single' == $nid){
                $urls = 'pages/article/single?typeid='.$typeid;
            }else{
                $urls = 'pages/article/view?aid='.$aid;
            }
        }elseif (!empty($typeid)){
            $urls = 'pages/article/list?typeid='.$typeid;
        }

        $data['type'] = $type;
        $data['url_list'] = $urls;
        $url         = 'https://openapi.baidu.com/rest/2.0/smartapp/access/submitsitemap/api?access_token='.$access_token;
        $response   = httpRequest($url, 'POST', $data);
        $result    = json_decode($response, true);

        return $result;
    }
}