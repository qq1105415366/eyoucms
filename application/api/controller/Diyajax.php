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

namespace app\api\controller;

use think\Db;

class Diyajax extends Base
{
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
    }

    /**
     * 检验会员登录
     */
    public function check_userinfo()
    {
        if (IS_AJAX) {
            \think\Session::pause(); // 暂停session，防止session阻塞机制
            $ajaxLogic = new \app\api\logic\AjaxLogic;
            $result = $ajaxLogic->check_userinfo();
            if (!empty($result['data']['ey_is_login'])) {
                $assignData = [
                    'users' => $result['users'],
                ];
                $this->assign($assignData);

                $viewfile = input('param.viewfile/s', '');
                empty($viewfile) && $viewfile = 'users_info.htm';
                $filename = "./template/".THEME_STYLE_PATH."/system/{$viewfile}";
                if (file_exists($filename)) {
                    $html = $this->fetch($filename); // 渲染模板标签语法
                } else {
                    $html = '缺少模板文件：'.ltrim($filename, '.');
                }
                $result['data']['html'] = $html;
            }
            respose(['code'=>1, 'msg'=>'请求成功', 'data'=>$result['data']]);
        }
        abort(404);
    }
}