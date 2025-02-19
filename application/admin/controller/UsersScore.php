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
use think\Page;
use think\Cache;

class UsersScore extends Base
{
    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();

        $functionLogic = new \app\common\logic\FunctionLogic;
        $functionLogic->validate_authorfile(1.5);

        $this->language_access(); // 多语言功能操作权限
    }

    public function index()
    {
        $list     = array();
        $keywords = input('keywords/s');

        $condition = [
            'type' => ['IN', [1, 2, 5, 8, 10]], // 1(提问)、2(回答)、5(签到)、8(消费赠送)、10(登录赠送积分)
        ];
        //时间检索条件
        $begin    = strtotime(input('param.add_time_begin/s'));
        $end    = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end    = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between',"$begin,$end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        if ($keywords)  $condition['b.username'] = array('LIKE', "%{$keywords}%");

        $condition['a.lang'] = $this->admin_lang;

        $count  = Db::name('users_score')->alias('a')->join('users b','a.users_id = b.users_id')->where($condition)->count('id');// 查询满足要求的总记录数
        $Page   = $pager = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list   = Db::name('users_score')
            ->alias('a')
            ->field('a.*,b.username,b.head_pic,b.nickname')
            ->join('users b','a.users_id = b.users_id')
            ->where($condition)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $show = $Page->show();// 分页显示输出
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('list', $list);// 赋值数据集
        $this->assign('pager', $pager);// 赋值分页对象
        return $this->fetch();
    }

    /**
     * 积分设置
     */
    public function conf()
    {
        if (IS_POST) {
            $post     = input('post.');
            $functionLogic = new \app\common\logic\FunctionLogic;
            $functionLogic->scoreConf($post);
            $this->success("操作成功", url('UsersScore/conf'));
        }
        $score = getUsersConfigData('score');
        $this->assign('score', $score);

        return $this->fetch();
    }
}