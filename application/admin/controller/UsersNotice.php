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

class UsersNotice extends Base
{
    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();

        $this->language_access(); // 多语言功能操作权限
    }

    /**
     * 站内通知 - 列表
     */
    public function index()
    {
        $list = array();
        $keywords = input('keywords/s');

        $map = array();
        if (!empty($keywords)) {
            $map['title'] = array('LIKE', "%{$keywords}%");
        }

        $count = Db::name('users_notice')->where($map)->count('id');// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('users_notice')->where($map)->order('id desc')->limit($pageObj->firstRow.','.$pageObj->listRows)->select();
        if ($list) {
            foreach ($list as $k=>$v) {
                $usernames_str = '';
                if ($v['users_id']) {
                    $usernames_arr = explode(',', $v['users_id']);
                    if (count($usernames_arr) > 3) {
                        for ($i = 0; $i < 3; $i++) {
                            $usernames_str .= $usernames_arr[$i] . ',';
                        }
                        $usernames_str .= ' ...';
                        $list[$k]['usernames'] = $usernames_str;
                    }else{
                        $list[$k]['usernames'] =  $v['users_id'];
                    }
                }else{
                    $list[$k]['usernames'] = '全站会员';
                }
            }
        }

        $pageStr = $pageObj->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $pageStr); // 赋值分页输出
        $this->assign('pager', $pageObj); // 赋值分页对象

        return $this->fetch();
    }

    /**
     * 站内通知 - 新增
     */
    public function add()
    {
        $functionLogic = new \app\common\logic\FunctionLogic;
        $functionLogic->validate_authorfile(1);

        if (IS_POST) {
            $post = input('post.');

            /*组装存储数据*/
            $nowData = array(
                'add_time'    => getTime(),
                'update_time'    => getTime(),
            );
            empty($post['usernames']) && $post['usernames'] = "";
            $saveData = array_merge($post, $nowData);
            /*--end*/
            $insertId = Db::name('users_notice')->insertGetId($saveData);

            if ($insertId) {
                //未读消息数+1
                if (!empty($post['users_id'])){
                    Db::name('users')->where(['users_id' => ['IN', $post['users_id']]])->setInc('unread_notice_num');
                }else{
                    Db::name('users')->where(['users_id' => ['gt', 0]])->setInc('unread_notice_num');
                }
                
                if (!empty($post['users_id'])){
                    adminLog('新增站内通知：通知会员id为：'.$post['users_id'].' ,新增站内通知：通知会员名为：'.$post['usernames']); // 写入操作日志
                }else{
                    adminLog('新增全站通知'); // 写入操作日志
                }
                $this->success("操作成功", url('UsersNotice/index'));
            }else{
                $this->error("操作失败");
            }
            exit;
        }

        $listname = Db::name('users')->order('users_id desc')->field('users_id,username')->select();
        $this->assign('listname', $listname);
        return $this->fetch();
    }

    /**
     * 站内通知 - 编辑
     */
    public function edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['id'] = intval($post['id']);
            if (isset($post['usernames'])) unset($post['usernames']);
            if (isset($post['users_id'])) unset($post['users_id']);

            $post['id'] = eyIntval($post['id']);
            if(!empty($post['id'])){
                $post['update_time'] = getTime();
                $r = Db::name('users_notice')->where(['id'=>$post['id']])->update($post);
                if ($r) {
                    adminLog('编辑站内通知：通知id为'.$post['id']); // 写入操作日志
                    $this->success("操作成功!", url('UsersNotice/index'));
                }
            }
            $this->error("操作失败!");
        }

        $id = input('id/d', 0);
        $row = Db::name('users_notice')->find($id);
        if (empty($row)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }

        // 转化换行格式，适应输出
        $row['remark'] = str_replace("<br/>", "\n", $row['remark']);

        $listname = Db::name('users')->order('users_id desc')->field('users_id,username')->select();
        $this->assign('listname', $listname);

        $this->assign('row',$row);
        return $this->fetch();
    }

    /**
     * 站内通知 - 删除
     */
    public function del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(!empty($id_arr) && IS_POST){
            $result = Db::name('users_notice')->where("id",'IN',$id_arr)->select();
            $r = Db::name('users_notice')->where("id",'IN',$id_arr)->delete();
            if($r !== false){
                $usersTplVersion = getUsersTplVersion();
                if ($usersTplVersion != 'v1') {
                    //未读消息数-1
                    foreach ($result as $item) {
                        if ($item['users_id']) {
                            $users_id_arr_new = explode(",", $item['users_id']);
                            Db::name('users')->where(['users_id' => ['IN', $users_id_arr_new], 'unread_notice_num'=>['gt', 0]])->setDec('unread_notice_num');
                        }else{
                            //通知的是全站会员
                            Db::name('users')->where(['unread_notice_num'=>['gt', 0]])->setDec('unread_notice_num');
                        }
                    }
                }
                Db::name('users_notice_read')->where("notice_id",'IN',$id_arr)->delete();
                adminLog('删除站内通知：'.implode(',', $id_arr));
                $this->success("删除成功!");
            }
        }
        $this->error("删除失败!");
    }

    /**
     * 站内通知 - 管理员接收通知列表
     */
    public function admin_notice_index()
    {
        $list = array();
        $keywords = input('keywords/s');

        $map = array();
        if (!empty($keywords)) {
            $map['a.content_title'] = array('LIKE', "%{$keywords}%");
        }

        $count = Db::name('users_notice_tpl_content')->alias('a')->where($map)->count('content_id');
        $pageObj = new Page($count, config('paginate.list_rows'));
        $list = Db::name('users_notice_tpl_content')
            ->field('a.*, b.tpl_name')
            ->alias('a')
            ->join('__USERS_NOTICE_TPL__ b', 'a.source = b.send_scene', 'LEFT')
            ->where($map)
            ->order('content_id desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $pageStr = $pageObj->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $pageStr); // 赋值分页输出
        $this->assign('pager', $pageObj); // 赋值分页对象

        return $this->fetch();
    }

    /**
     * 站内通知 - 编辑管理员接收通知
     */
    public function admin_notice_edit()
    {
        $content_id = input('content_id/d', 0);
        $Find = Db::name('users_notice_tpl_content')->field('a.*, b.tpl_name')->alias('a')->join('__USERS_NOTICE_TPL__ b', 'a.source = b.send_scene', 'LEFT')->find($content_id);
        if (empty($Find)) $this->error('数据不存在，请联系管理员！');

        // 更新通知为已查看
        if (empty($Find['is_read'])) {
            $update = [
                'content_id'  => $Find['content_id'],
                'is_read'     => 1,
                'update_time' => getTime()
            ];
           $r =  Db::name('users_notice_tpl_content')->update($update);
           if (false !== $r){
               if (!empty($Find['aid'])) Db::name('guestbook')->where('aid',$Find['aid'])->update(['is_read'=>1,'update_time'=>getTime()]);
           }
        }

        $this->assign('find', $Find);
        return $this->fetch();
    }

    /**
     * 站内通知 - 删除管理员接收通知
     */
    public function admin_notice_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(!empty($id_arr) && IS_POST) {
            // 查询要删除的通知信息
            $result = Db::name('users_notice_tpl_content')->field('content_id')->where("content_id", 'IN', $id_arr)->select();
            // 获取ID列表
            $id_list = get_arr_column($result, 'content_id');
            // 执行删除
            $DeleteID = Db::name('users_notice_tpl_content')->where("content_id", 'IN', $id_arr)->delete();
            // 添加操作日志，返回结束
            if (!empty($DeleteID)) {
                adminLog('删除接收的站内通知：' . implode(',', $id_list));
                $this->success("删除成功");
            } else {
                $this->error("删除失败");
            }
        } else {
            $this->error("参数有误");
        }
    }

    /**
     * 全部标记已读
     */
    public function sign_admin_allread()
    {
        if (IS_AJAX_POST) {
            $update = [
                'is_read'     => 1,
                'update_time' => getTime()
            ];
            $r = Db::name('users_notice_tpl_content')->where(['admin_id'=>['gt', 0]])->update($update);
            if ($r !== false) {
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    public function select_users()
    {
        $list = array();

        $param = input('param.');
        $condition = array();
        // 应用搜索条件
        foreach (['keywords','origin_type','level'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.username|a.nickname|a.mobile|a.email|a.users_id'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        $condition['a.is_del'] = 0;
        $condition['a.lang'] = array('eq', $this->admin_lang);
        $orderby = "a.users_id desc";

        $users_db = Db::name('users');

        $count = $users_db->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = $users_db->field('a.*,b.level_name')
            ->alias('a')
            ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
            ->where($condition)
            ->order($orderby)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $users_ids = [];
        foreach ($list as $key => $val) {
            $users_ids[] = $val['users_id'];
        }

        /*微信登录插件*/
        $wxlogin = [];
        if (is_dir('./weapp/WxLogin/')) {
            $wxlogin = Db::name('weapp_wxlogin')->where(['users_id'=>['IN', $users_ids]])->getAllWithIndex('users_id');
        }
        $this->assign('wxlogin',$wxlogin);
        /*end*/

        /*QQ登录插件*/
        $qqlogin = [];
        if (is_dir('./weapp/QqLogin/')) {
            $qqlogin = Db::name('weapp_qqlogin')->where(['users_id'=>['IN', $users_ids]])->getAllWithIndex('users_id');
        }
        $this->assign('qqlogin',$qqlogin);
        /*end*/

        /*微博登录插件*/
        $wblogin = [];
        if (is_dir('./weapp/Wblogin/')) {
            $wblogin = Db::name('weapp_wblogin')->where(['users_id'=>['IN', $users_ids]])->getAllWithIndex('users_id');
        }
        $this->assign('wblogin',$wblogin);
        /*end*/

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);

        //计算会员人数
        $levelCountList = [
            'all' => [
                'level_id'      => 0,
                'level_name'    => '全部会员',
                'level_count'   => 0,
            ],
        ];
        $LevelData = model('UsersLevel')->getList('level_id, level_name', [], 'level_id');
        $levelCountRow = Db::name('users')->field('count(users_id) as num, level')->order('level asc')->group('level')->getAllWithIndex('level');
        foreach ($LevelData as $key => $val) {
            $level_num = empty($levelCountRow[$val['level_id']]) ? 0 : $levelCountRow[$val['level_id']]['num'];
            $levelCountList[$val['level_id']] = [
                'level_id'      => $val['level_id'],
                'level_name'    => $val['level_name'],
                'level_count'   => $level_num,
            ];
            $levelCountList['all']['level_count'] += $level_num;
        }
        $this->assign('levelCountList', $levelCountList);

        return $this->fetch('users_notice/select_users');
    }
}