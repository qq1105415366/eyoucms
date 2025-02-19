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

use think\Page;
use think\Db;
use think\Config;
use app\admin\logic\MemberLogic;

class Member extends Base {

    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
        $this->language_access(); // 多语言功能操作权限
        /*会员中心数据表*/
        $this->users_db        = Db::name('users');         // 会员信息表
        $this->users_list_db   = Db::name('users_list');    // 会员资料表
        $this->users_level_db  = Db::name('users_level');   // 会员等级表
        $this->users_config_db = Db::name('users_config');  // 会员配置表
        $this->users_money_db  = Db::name('users_money');   // 会员充值表
        $this->field_type_db   = Db::name('field_type');    // 字段属性表
        $this->users_parameter_db = Db::name('users_parameter'); // 会员属性表
        $this->users_type_manage_db = Db::name('users_type_manage'); // 会员属性表
        /*结束*/

        /*订单中心数据表*/
        $this->shop_address_db   = Db::name('shop_address');    // 会员地址表
        $this->shop_cart_db      = Db::name('shop_cart');       // 会员购物车表
        $this->shop_order_db     = Db::name('shop_order');      // 会员订单主表
        $this->shop_order_log_db = Db::name('shop_order_log');  // 会员订单操作记录表
        $this->shop_order_details_db = Db::name('shop_order_details');  // 会员订单副表
        /*结束*/

        // 模型是否开启
        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        $this->assign('channeltype_row', $channeltype_row);
    }

    // 会员列表
    public function users_index()
    {
        $list = array();

        $param = input('param.');
        $condition = array();
        // 应用搜索条件
        foreach (['keywords','level','users_id'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.username|a.nickname|a.mobile|a.email|a.users_id'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        if (!empty($param['source'])) {
            $condition['a.source'] = $param['source'];
        }
        if (isset($param['is_lock']) && '' !== $param['is_lock']) {
            $condition['a.is_lock'] = $param['is_lock'];
        }

        // 注册时间查询
        if (!empty($param['add_time'])) {
            $add_time = explode('~', $param['add_time']);
            $start = strtotime(rtrim($add_time[0]));
            $finish = strtotime(rtrim($add_time[1]).' 23:59:59');
            $condition['a.reg_time'] = ['between', "$start, $finish"];
        }

        $condition['a.is_del'] = 0;

        // 自定义排序
        $orderby = input('param.orderby/s');
        $orderway = input('param.orderway/s');
        if (!empty($orderby) && !empty($orderway)) {
            $orderby = "a.{$orderby} {$orderway}, a.users_id desc";
        } else {
            $orderby = "a.users_id desc";
        }

        $count = $this->users_db->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = $this->users_db->field('a.*,b.level_name')
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

        $LevelData = model('UsersLevel')->getList('level_id, level_name', [], 'level_id');
        $this->assign('level', $LevelData);

        /*纠正数据*/
        $web_is_authortoken = tpCache('global.web_is_authortoken');
        if (is_realdomain() && !empty($web_is_authortoken)) {
            getUsersConfigData('shop', ['shop_open'=>0]);
        }

        //计算会员人数
        /*$levelCountList = [
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
        $this->assign('levelCountList', $levelCountList);*/

        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);
        // 如果安装手机端后台管理插件并且在手机端访问时执行
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            $mbPage = input('param.p/d', 1);
            $nullShow = intval($Page->totalPages) === intval($mbPage) ? 1 : 0;
            $this->assign('nullShow', $nullShow);
            if ($mbPage >= 2) {
                return $this->display('member/users_list');
            } else {
                return $this->display('member/users_index');
            }
        } else {
            return $this->fetch();
        }
    }

    // 会员批量新增
    public function users_batch_add()
    {
        if (IS_POST) {
            $post = input('post.');

            $username = trim($post['username']);
            if (empty($username)) {
                $this->error('用户名不能为空！');
            }

            if (empty($post['password']) || !trim($post['password'])) {
                $this->error('登录密码不能为空！');
            } else {
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

            if (!empty($this->usersConfig['level_member_upgrade']) && 1 == $this->usersConfig['level_member_upgrade']) {
                if (1 != $post['level'] && !preg_match("/^([0-9]+)$/i", $post['level_maturity_days'])) {
                    $this->error('请填写会员有效期天数！');
                }
            }

            // 处理数据验证
            $error = handleEyouDataValidate('username', '__token_users_batch_add__', $post, '用户名不能为空！');
            if (!empty($error)) $this->error($error);
            
            $post['level_maturity_days'] = intval($post['level_maturity_days']);

            $password = func_encrypt($post['password'], false, pwd_encry_type('bcrypt'));

            $usernameArr = explode("\r\n", $username);
            $usernameArr = array_filter($usernameArr);//去除数组空值
            $usernameArr = array_unique($usernameArr); //去重

            $addData = [];
            $usernameList = $this->users_db->where([
                    'username'  => ['IN', $usernameArr],
                ])->column('username');
            foreach ($usernameArr as $key => $val) {
                if(trim($val) == '' || empty($val) || in_array($val, $usernameList) || !preg_match("/^[\x{4e00}-\x{9fa5}\w\-\_\@\#]{2,30}$/u", $val))
                {
                    continue;
                }

                $addData[] = [
                    'username'       => $val,
                    'nickname'       => $val,
                    'password'       => $password,
                    'level'          => $post['level'],
                    'register_place' => 1,
                    'level_maturity_days'   => $post['level_maturity_days'],
                    'open_level_time'   => getTime(),
                    'reg_time'       => getTime(),
                    'head_pic'       => ROOT_DIR . '/public/static/common/images/dfboy.png',
                    'lang'           => $this->admin_lang,
                ];
            }
            if (!empty($addData)) {
                $rdata = model('Member')->saveAll($addData);
                if (false !== $rdata) {
                    eyou_statistics_data(4, count($addData)); // 统计新增会员数
                    adminLog('批量新增会员：'.implode(',', get_arr_column($addData, 'username')));
                    $this->success('操作成功！', url('Member/users_index'));
                }
                $this->error('操作失败');
            } else {
                $this->success('操作成功！', url('Member/users_index'));
            }
        }

        $user_level = model('UsersLevel')->getList('level_id, level_name');
        $this->assign('user_level',$user_level);

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

        return $this->fetch();
    }

    // 会员编辑
    public function users_edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['users_id'] = intval($post['users_id']);
            $users_id = $post['users_id'];

//            if (!empty($this->usersConfig['level_member_upgrade']) && 1 == $this->usersConfig['level_member_upgrade']) {
                /*会员级别到期天数*/
                if (1 != $post['level'] && !preg_match("/^([0-9]+)$/i", $post['level_maturity_days_up'])) {
                    $this->error('请填写会员有效期天数！');
                }
                $post['level_maturity_days_up'] = intval($post['level_maturity_days_up']);
                $post['level_maturity_days_old'] = intval($post['level_maturity_days_old']);
                if (0 >= $post['level_maturity_days_up']) {
                    $days_new = 0;
                }else{
                    if ($post['level_maturity_days_new'] >= $post['level_maturity_days_up']) {
                        $days_new = $post['level_maturity_days_new'] - $post['level_maturity_days_up'];
                        $days_new = $post['level_maturity_days_old'] - $days_new;
                    }else{
                        $days_new = $post['level_maturity_days_up'] - $post['level_maturity_days_new'];
                        $days_new = $post['level_maturity_days_old'] + $days_new;
                    }
                }
                $days_new = (99999999 < $days_new) ? 99999999 : $days_new;
                $post['level_maturity_days'] = $days_new;
//            }
            /*end*/

            $post['head_pic'] = htmlspecialchars_decode($post['head_pic']);
            
            if (isset($post['users_money'])) {
                $users_money = input('post.users_money/f');
                $post['users_money'] = (99999999 < $users_money) ? 99999999 : $users_money;
            }

            if (!empty($post['password']) && trim($post['password'])) {
                /*等保密码复杂度验证 start*/
                if (is_dir('./weapp/Equal/')) {
                    $equalLogic = new \weapp\Equal\logic\EqualLogic;
                    $eqData = $equalLogic->pwdValidate($post['password']);
                    if (isset($eqData['code']) && empty($eqData['code'])) {
                        $this->error($eqData['msg']);
                    }
                }
                /*等保密码复杂度验证 end*/

                if (!empty($this->globalConfig['security_verifyfunc']) && in_array('edit_pwd', $this->globalConfig['security_verifyfunc'])) {
                    if (true !== security_answer_verify()) {
                        $this->error("请先密保答案验证");
                    }
                }
                $post['password'] = func_encrypt($post['password'], false, pwd_encry_type('bcrypt'));
            } else {
                unset($post['password']);
            }

            $ParaData = [];
            if (is_array($post['users_'])) {
                $ParaData = $post['users_'];
            }
            unset($post['users_']);

            // 处理提交的会员属性中邮箱和手机是否已存在
            // isRequired方法传入的参数有2个
            // 第一个必须传入提交的会员属性数组
            // 第二个users_id，注册时不需要传入，修改时需要传入。
            $RequiredData = model('Member')->isRequired($ParaData,$users_id);
            if ($RequiredData) {
                $this->error($RequiredData);
            }

            // 处理数据验证
            $error = handleEyouDataValidate('users_id', '__token_users_edit__', $post);
            if (!empty($error)) $this->error($error);

            $users_where = [
                'users_id' => $users_id,
            ];
            $userinfo = $this->users_db->where($users_where)->find();

            $post['update_time'] = getTime();

            /*会员级别到期天数*/
            if(isset($post['level_maturity_days']) && !empty($post['level_maturity_days'])){
                if (empty($userinfo['open_level_time'])) {
                    $post['open_level_time'] = getTime();
                }
            }else if (empty($post['level_maturity_days'])) {
                $post['open_level_time'] = '';
                $level_id = 1;
                $post['level'] = $level_id;
            }
            /*end*/

            unset($post['username']);
            $r = $this->users_db->where($users_where)->update($post);
            if ($r !== false) {
                $row2 = model('UsersParameter')->getList('para_id,name,dtype', [], 'name');
                //兼容多选字段选择为空时保存失败
                foreach ($row2 as $k => $v) {
                    if ($v['dtype'] == 'checkbox' && empty($ParaData[$v['name']])){
                        $ParaData[$v['name']] = '';
                    }
                }
                foreach ($ParaData as $key => $value) {
                    $data    = [];
                    $para_id = intval($row2[$key]['para_id']);
                    $where   = [
                        'users_id' => $post['users_id'],
                        'para_id'  => $para_id,
                    ];
                    if ('checkbox' == $row2[$key]['dtype']) {
                        if (!empty($value)) {
                            $data['info'] = implode(",", $value);
                        } else {
                            $data['info'] = '';
                        }
                    } elseif ('imgs' == $row2[$key]['dtype']) {
                        $value = array_filter($value);
                        if (!empty($value)) {
                            $data['info'] = implode(",", $value);
                        } else {
                            $data['info'] = '';
                        }
                    } else {
                        $data['info'] = $value;
                    }
                    $data['update_time'] = getTime();

                    // 若信息表中无数据则添加
                    $row = $this->users_list_db->where($where)->count();
                    if (empty($row)) {
                        $data['users_id'] = $post['users_id'];
                        $data['para_id']  = $para_id;
                        $data['lang']     = $this->admin_lang;
                        $data['add_time'] = getTime();
                        $this->users_list_db->add($data);
                    } else {
                        $this->users_list_db->where($where)->update($data);
                    }
                }

                // 查询属性表的手机号码和邮箱地址，同步修改会员信息。
                $UsersListData = model('Member')->getUsersListData('*',$users_id);
                $UsersListData['update_time'] = getTime();
                $this->users_db->where($users_where)->update($UsersListData);

                /*同步头像到管理员表对应的管理员*/
                $syn_admin_id = $this->users_db->where(['users_id'=>$post['users_id']])->getField('admin_id');
                if (!empty($syn_admin_id)) {
                    Db::name('admin')->where(['admin_id'=>$syn_admin_id])->update([
                        'head_pic'  => $post['head_pic'],
                        'update_time'   => getTime(),
                    ]);
                }
                /*end*/

                \think\Cache::clear('users_list');

                adminLog('编辑会员'.$users_id.'：'.$userinfo['username']);                
                $this->success('操作成功', url('Member/users_index'));
            } else {
                $this->error('操作失败');
            }
        }

        $assign_data = [];
        $users_id = input('param.id/d');
        
        // 会员信息
        $info = $this->users_db->where([
                'users_id'  => $users_id,
            ])->find();

        // 计算剩余天数
        $days = intval($info['open_level_time']) + (intval($info['level_maturity_days']) * 86400);
        // 取整
        $days = ceil(($days - getTime()) / 86400);
        if (0 >= $days) {
            $info['level_maturity_days_new'] = '0';
        }else{
            $info['level_maturity_days_new'] = $days;
        }
        $assign_data['info'] = $info;

        /*微信登录插件*/
        if (is_dir('./weapp/WxLogin/')) {
            $assign_data['info']['wxlogin'] = Db::name('weapp_wxlogin')->where(['users_id'=>$users_id])->find();
        }
        /*end*/

        /*QQ登录插件*/
        if (is_dir('./weapp/QqLogin/')) {
            $assign_data['info']['qqlogin'] = Db::name('weapp_qqlogin')->where(['users_id'=>$users_id])->find();
        }
        /*end*/

        /*微博登录插件*/
        if (is_dir('./weapp/Wblogin/')) {
            $assign_data['info']['wblogin'] = Db::name('weapp_wblogin')->where(['users_id'=>$users_id])->find();
        }
        /*end*/

        // 等级信息
        $assign_data['level'] = model('UsersLevel')->getList('level_id, level_name');

        // 属性信息
        $assign_data['users_para'] = model('Member')->getDataParaList($users_id);
        // 积分
        $assign_data['scoreCofing'] = getUsersConfigData('score');

        // 上一个页面来源
        $from = input('param.from/s');
        if ('money_index' == $from) {
            $backurl = url('Member/money_index');
        } else {
            $backurl = url('Member/users_index');
        }
        $assign_data['backurl'] = $backurl;

        // 是否弹窗打开
        $iframe = input('param.iframe/d',0);
        $assign_data['iframe'] = $iframe;

        $assign_data['users_lock_model'] = config('global.users_lock_model');

        //订单信息
        $assign_data['order_count'] = Db::name('shop_order')->field('count(*) as count,sum(order_amount) as sum')->where(['order_status'=>3,'users_id'=>$users_id])->select();
        $assign_data['refund_count'] = Db::name('shop_order_service')
            ->field('count(*) as count,sum(refund_price) as sum')
            ->where(['users_id'=>$users_id, 'service_type'=>2,'status'=>7])
            ->select();
        $this->assign($assign_data);

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

        // 如果安装手机端后台管理插件并且在手机端访问时执行
        $isMobile = input('param.isMobile/d', 0);
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            return $this->display('member/users_edit');
        } else {
            return $this->fetch();
        }
    }

    public function query_level_days()
    {
        $level_id = input('level_id/d', 0);
        if (IS_AJAX_POST && !empty($level_id)) {
            $maturity_days = 0;
            // 查询会员级别的天数
            $where = [
                'level_id' => intval($level_id)
            ];
            $limitID = Db::name('users_type_manage')->where($where)->order('limit_id desc')->getField('limit_id');
            if (!empty($limitID)) {
                $adminMemberLimitArr = config('global.admin_member_limit_arr');
                $maturity_days = !empty($adminMemberLimitArr[$limitID]['maturity_days']) ? intval($adminMemberLimitArr[$limitID]['maturity_days']) : 0;
            }
            $this->success('查询正确', null, $maturity_days);
        }
    }

    // 会员删除
    public function users_del()
    {
        $users_id = input('del_id/a');
        $users_id = eyIntval($users_id);
        if (IS_AJAX_POST && !empty($users_id)) {
            // 删除统一条件
            $Where = [
                'users_id'  => ['IN', $users_id],
            ];

            $result = $this->users_db->field('username,users_id')->where($Where)->select();
            $username_list = $users_ids = [];
            foreach ($result as $key => $val) {
                $username_list[] = $val['username'];
                $users_ids[] = $val['users_id'];
            }

            $return = $this->users_db->where($Where)->delete();
            if (false !== $return) {
                \think\Cache::clear('users_list');
                adminLog('删除会员：'.implode(',', $username_list));
                // 如果安装了分销插件则执行
                if (is_dir('./weapp/DealerPlugin/')) {
                    // 开启分销插件则执行
                    $data = model('Weapp')->getWeappList('DealerPlugin');
                    if (!empty($data['status']) && 1 == $data['status']) {
                        // 调用分销逻辑层方法
                        $dealerCommonLogic = new \weapp\DealerPlugin\logic\DealerCommonLogic;
                        $dealerCommonLogic->dealerSynchronizeDelete($users_ids);
                    }
                }
                model('Member')->afterDel($users_ids);
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    // 级别列表
    public function level_index()
    {
        // 级别列表
        $this->getUsersLevelList(false);

        /*是否安装启用下载次数限制插件*/
        $isShowDownCount = 0;
        if (is_dir('./weapp/Downloads/')) {
            $downloadsRow = model('Weapp')->getWeappList('Downloads');
            if (!empty($downloadsRow['status']) && 1 == $downloadsRow['status']) $isShowDownCount = 1;
        }
        $this->assign('isShowDownCount', $isShowDownCount);
        /*end*/

        return $this->fetch();
    }

    // 会员级别状态
    public function level_status()
    {
        if (IS_AJAX_POST) {
            // 保存会员级别信息
            $result = model('UsersLevel')->updateUsersLevelStatus();
            if (!empty($result)) $this->usersLevelSuccess('更新成功');
        }
        $this->error('操作失败');
    }

    // 级别 - 新增
    public function level_add()
    {   
        if (IS_AJAX_POST) {
            // 保存会员级别信息
            $result = model('UsersLevel')->saveUsersLevelDetails();
            if (!empty($result)) $this->usersLevelSuccess('保存成功');
            $this->error('操作失败');
        }

        return $this->fetch();
    }

    // 级别 - 编辑
    public function level_edit()
    {
        if (IS_AJAX_POST) {
            // 保存会员级别信息
            $result = model('UsersLevel')->saveUsersLevelDetails('update');
            if (!empty($result)) $this->usersLevelSuccess('保存成功');
            $this->error('操作失败');
        }

        // 查询会员级别信息
        $usersLevel = model('UsersLevel')->getUsersLevelDetails();
        $this->assign('usersLevel', $usersLevel);

        return $this->fetch();
    }

    // 级别 - 删除
    public function level_del()
    {
        if (IS_AJAX_POST) {
            // 保存会员级别信息
            $result = model('UsersLevel')->delUsersLevelDetails();
            if (!empty($result)) $this->usersLevelSuccess('删除成功');
        }
        $this->error('操作失败，刷新重试');
    }

    // 会员级别成功返回
    private function usersLevelSuccess($msg = '操作成功', $url = null, $result = [])
    {
        // 获取会员级别列表
        $result['html'] = $this->getUsersLevelList();
        $this->success($msg, $url, $result);
    }

    // 级别列表
    private function getUsersLevelList($return = true)
    {
        // 获取会员级别列表
        $result = model('UsersLevel')->getUsersLevelList();
        $this->assign($result);

        // 返回页面内容
        if (!empty($return)) return $this->fetch('member/level_list');
    }


    // 属性列表
    public function attr_index()
    {
        //属性数据
        $info = $this->users_parameter_db->field('a.*,a.title,b.title as dtypetitle')
            ->alias('a')
            ->join('__FIELD_TYPE__ b', 'a.dtype = b.name', 'LEFT')
            ->order('a.is_system desc,a.sort_order asc,a.para_id desc')
            ->select();
        foreach ($info as $key => $value) {
            if ('email' == $value['dtype']) {
                $info[$key]['dtypetitle'] = '邮箱地址';
            } else if ('mobile' == $value['dtype']) {
                $info[$key]['dtypetitle'] = '手机号码';
            }else if ('datetime' == $value['dtype']) {
                $info[$key]['dtypetitle'] = '日期和时间';
            }
        }
        $this->assign('info',$info);
        return $this->fetch();
    }

    // 属性添加
    public function attr_add()
    {   
        if (IS_POST) {
            $post = input('post.');
            $post['title'] = trim($post['title']);

            if (empty($post['title'])) {
                $this->error('属性标题不能为空！');
            }
            if (empty($post['dtype'])) {
                $this->error('请选择属性类型！');
            }

            $count = $this->users_parameter_db->where([
                    'title'=>$post['title']
                ])->count();
            if (!empty($count)) {
                $this->error('属性标题已存在！');
            }

            $post['dfvalue']     = str_replace('，', ',', $post['dfvalue']);
            $post['dfvalue'] = trim($post['dfvalue'], ',');

            /*判断默认值是否含有重复值*/
            if (in_array($post['dtype'], ['radio','checkbox','select'])) {
                if (!empty($post['dfvalue'])){
                    $dfvalue_arr = [];
                    $dfvalue_arr = explode(',', $post['dfvalue']);
                    foreach ($dfvalue_arr as &$v) {
                        $v = trim($v);
                    }
                    if (count($dfvalue_arr) != count(array_unique($dfvalue_arr))) {
                        $this->error('默认值不能含有相同的值！');
                    }
                }
            }
            /*end*/

            $post['add_time'] = getTime();
            $post['lang']        = $this->admin_lang;
            $post['sort_order'] = '100';
            $para_id = $this->users_parameter_db->insertGetId($post);
            if (!empty($para_id)) {
                $name = 'para_'.$para_id;
                $return = $this->users_parameter_db->where('para_id',$para_id)
                    ->update([
                        'name'  => $name,
                        'update_time'   => getTime(),
                    ]);
                if ($return !== false) {
                    \think\Cache::clear('users_parameter');
                    \think\Cache::clear('users_list');
                    adminLog('新增会员属性：'.$post['title']);
                    $this->success('操作成功',url('Member/attr_index'));
                }
            }
            $this->error('操作失败');
        }

        $field = $this->field_type_db->field('name,title,ifoption')
            ->where([
                'name'  => ['IN', ['text','checkbox','multitext','radio','select','img','file','datetime','imgs']]
            ])
            ->select();
        $this->assign('field',$field);
        return $this->fetch();
    }

    // 属性修改
    public function attr_edit()
    {
        $para_id = input('param.id/d');

        if (IS_POST && !empty($para_id)) {
            $post = input('post.');
            $post['title'] = trim($post['title']);

            if (empty($post['title'])) {
                $this->error('属性标题不能为空！');
            }
            if (empty($post['dtype'])) {
                $this->error('请选择属性类型！');
            }

            $count = $this->users_parameter_db->where([
                    'title'     => $post['title'],
                    'para_id'   => ['NEQ', $para_id],
                ])->count();
            if ($count) {
                $this->error('属性标题已存在！');
            }

            $post['dfvalue'] = str_replace('，', ',', $post['dfvalue']);
            $post['dfvalue'] = trim($post['dfvalue'], ',');

            /*判断默认值是否含有重复值*/
            if (in_array($post['dtype'], ['radio','checkbox','select'])) {
                if (!empty($post['dfvalue'])){
                    $dfvalue_arr = [];
                    $dfvalue_arr = explode(',', $post['dfvalue']);
                    foreach ($dfvalue_arr as &$v) {
                        $v = trim($v);
                    }
                    if (count($dfvalue_arr) != count(array_unique($dfvalue_arr))) {
                        $this->error('默认值不能含有相同的值！');
                    }
                }
            }
            /*end*/

            $post['update_time'] = getTime();
            $return = $this->users_parameter_db->where([
                    'para_id'   => $para_id,
                ])->update($post);
            if ($return !== false) {
                \think\Cache::clear('users_parameter');
                \think\Cache::clear('users_list');
                adminLog('编辑会员属性：'.$post['title']);
                $this->success('操作成功',url('Member/attr_index'));
            }else{
                $this->error('操作失败');
            }
        }

        $info = $this->users_parameter_db->where([
                'para_id'   => $para_id,
            ])->find();
        $this->assign('info',$info);

        $field = $this->field_type_db->field('name,title,ifoption')
            ->where([
                'name'  => ['IN', ['text','checkbox','multitext','radio','select','img','file','datetime','imgs']]
            ])
            ->select();
        $this->assign('field',$field);

        return $this->fetch();
    }

    // 属性删除
    public function attr_del()
    {
        $para_id = input('del_id/a');
        $para_id = eyIntval($para_id);

        if (IS_AJAX_POST && !empty($para_id)) {
            $result = $this->users_parameter_db->field('title')
                ->where([
                    'para_id'  => ['IN', $para_id],
                ])
                ->select();
            $title_list = get_arr_column($result, 'title');

            // 删除会员属性表数据
            $return = $this->users_parameter_db->where([
                    'para_id'  => ['IN', $para_id],
                ])->delete();

            if ($return !== false) {
                // 删除会员属性信息表数据
                $this->users_list_db->where([
                        'para_id'  => ['IN', $para_id],
                    ])->delete();
                \think\Cache::clear('users_parameter');
                \think\Cache::clear('users_list');
                adminLog('删除会员属性：'.implode(',', $title_list));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    // 功能设置
    public function users_config()
    {
        if (IS_POST) {
            $post = input('post.');

            // 邮件验证的检测
            if (2 == $post['users']['users_verification']) {
                $users_config_email = $this->users_config_email();
                if (!empty($users_config_email)) {
                   $this->error($users_config_email);
                }
            }
            // 第三方登录
            if (!empty($post['oauth']['oauth_open']) && 1 == $post['oauth']['oauth_open']) {
                empty($post['oauth']['oauth_qq']) && $post['oauth']['oauth_qq'] = 0;
                empty($post['oauth']['oauth_weixin']) && $post['oauth']['oauth_weixin'] = 0;
                empty($post['oauth']['oauth_weibo']) && $post['oauth']['oauth_weibo'] = 0;
            }

            /*前台登录超时*/
            $users_login_expiretime = $post['users']['users_login_expiretime'];
            $login_expiretime_old = $post['users']['login_expiretime_old'];
            unset($post['users']['login_expiretime_old']);
            if ($login_expiretime_old != $users_login_expiretime) {
                $users_login_expiretime = preg_replace('/^(\d{0,})(.*)$/i', '${1}', $users_login_expiretime);
                empty($users_login_expiretime) && $users_login_expiretime = config('login_expire');
                if ($users_login_expiretime > 2592000) {
                    $users_login_expiretime = 2592000; // 最多一个月
                }
                $post['users']['users_login_expiretime'] = $users_login_expiretime;
                //后台登录超时时间
                $web_login_expiretime = tpCache('global.web_login_expiretime');
                //前台和后台谁设置的时间大就用谁的做session过期时间
                $max_login_expiretime = $web_login_expiretime;
                if ($web_login_expiretime < $users_login_expiretime){
                    $max_login_expiretime = $users_login_expiretime;
                }
            }
            /*--end*/

            // 会员投稿设置
            if (!empty($this->usersConfig['users_open_release'])) {
                unset($post['release_typeids']);
                unset($post['users']['is_automatic_review']);
                unset($post['users']['is_open_posts_count']);
            }

            // 会员模板切换/前台会员登录过期时间
            tpCache('web', $post['web']);

            foreach ($post as $key => $val) {
                if ('web' == $key) {
                    continue;
                }
                getUsersConfigData($key, $val);
            }
            // 主题色切换
            getUsersConfigData('theme', ['users_theme_style_uptime'=>getTime()]);
            $ajaxLogic = new \app\admin\logic\AjaxLogic;
            $ajaxLogic->users_update_theme_css();

            /*更改session会员设置 - session有效期（前台登录超时）*/
            if ($login_expiretime_old != $users_login_expiretime) {
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
            /*--end*/

            \think\Cache::clear();
            delFile(RUNTIME_PATH);
            $this->success('操作成功');
        }

        // 左侧菜单
        $this->assign('usersTplVersion', getUsersTplVersion());

        /*允许发布文档列表的栏目*/
        $current_channel = [1,3,4,5]; // 允许投稿的模型
        $arctype = Db::name('arctype')->where([
            'current_channel' => ['in',$current_channel],
            'is_release' => 1,
            'lang' => $this->admin_lang,
        ])->field('id')->select();
        $arctype = get_arr_column($arctype,'id');
        $release_select_html = allow_release_arctype($arctype, $current_channel);
        if (empty($current_channel)){
            $release_select_html = [];
        }
        $this->assign('release_select_html',$release_select_html);
        /*--end*/

        /*模板风格列表*/
        $web_tpl_theme = config('ey_config.web_tpl_theme');
        !empty($web_tpl_theme) && $web_tpl_theme .= '/';
        $tpl_theme_list = glob('./template/'.$web_tpl_theme.'pc/users*', GLOB_ONLYDIR);
        foreach ($tpl_theme_list as $key => &$val) {
            $val = str_replace('\\', '/', $val);
            $val = preg_replace('/^(.*)\/([^\/]*)$/i', '${2}', $val);
        }
        $this->assign('tpl_theme_list', $tpl_theme_list);
        /*end*/

        return $this->fetch();
    }

    // 邮件验证的检测
    public function ajax_users_config_email()
    {   
        if (IS_AJAX) {
            // 邮件验证的检测
            $users_config_email = $this->users_config_email();
            if (!empty($users_config_email)) $this->error($users_config_email);
            $this->success('检验通过');
        }
        $this->error('参数有误');
    }
        
    private function users_config_email(){
        // 会员属性信息
        $where = array(
            'name'      => ['LIKE', "email_%"],
            'is_system' => 1,
        );
        // 是否要为必填项
        $param = $this->users_parameter_db->where($where)->field('title,is_hidden')->find();
        if (empty($param) || 1 == $param['is_hidden']) {
            return "请先把会员字段的<font color='red'>{$param['title']}</font>设置为显示，且为必填项！";
        }

        $param = $this->users_parameter_db->where($where)->field('title,is_required')->find();
        if (empty($param) || 0 == $param['is_required']) {
            return "请先把会员字段的<font color='red'>{$param['title']}</font>设置为必填项！";
        }

        // 是否开启邮箱发送扩展
        $openssl_funcs = get_extension_funcs('openssl');
        if (!$openssl_funcs) {
            return "请联系空间商，开启php的 <font color='red'>openssl</font> 扩展！";
        }

        $send_email_scene = config('send_email_scene');
        $scene = $send_email_scene[2]['scene'];

        // 自动启用注册邮件模板
        Db::name('smtp_tpl')->where([
                'send_scene'    => $scene,
                'lang'          => $this->admin_lang,
            ])->update([
                'is_open'       => 1,
                'update_time'   => getTime(),
            ]);

        // 是否填写邮件配置
        $globalConfig = tpCache('global');
        if (empty($globalConfig['smtp_user']) || empty($globalConfig['smtp_pwd'])) {
            return "请先完善<font color='red'>(邮件配置)</font>，具体步骤【基本信息】->【接口配置】->【邮件配置】";
        }

        return false;
    }

    // 手机验证的检测
    public function ajax_users_config_mobile()
    {   
        if (IS_AJAX) {
            // 邮件验证的检测
            $users_config_mobile = $this->users_config_mobile();
            if (!empty($users_config_mobile)) $this->error($users_config_mobile);
            $this->success('检验通过');
        }
        $this->error('参数有误');
    }
        
    private function users_config_mobile(){
        // 会员属性信息
        $where = array(
            'name'      => ['LIKE', "mobile_%"],
            'is_system' => 1
        );

        // 是否要为必填项
        $param = $this->users_parameter_db->where($where)->field('title, is_hidden')->find();
        if (empty($param) || 1 == $param['is_hidden']) {
            return "请先把会员字段的<font color='red'>{$param['title']}</font>设置为显示，且为必填项！";
        }

        $param = $this->users_parameter_db->where($where)->field('title, is_required')->find();
        if (empty($param) || 0 == $param['is_required']) {
            return "请先把会员字段的<font color='red'>{$param['title']}</font>设置为必填项！";
        }

        // 自动启用注册手机模板
        Db::name('sms_template')->where([
                'send_scene'  => 0,
                'lang'        => $this->admin_lang,
            ])->update([
                'is_open'     => 1,
                'update_time' => getTime()
            ]);

        // 是否填写手机短信配置
        $globalConfig = tpCache('global');
        if ( (1 == $globalConfig['sms_type'] && (empty($globalConfig['sms_appkey']) || empty($globalConfig['sms_secretkey'])))
            || (2 == $globalConfig['sms_type'] && (empty($globalConfig['sms_appid_tx']) || empty($globalConfig['sms_appkey_tx'])))
        ) {
            return "请先完善<font color='red'>(短信配置)</font>，具体步骤【基本信息】->【接口配置】->【短信配置】";
        }

        return false;
    }

    // 充值记录列表
    public function money_index()
    {
        $list = array();

        // 查询条件
        $condition = [
            // 'a.cause_type' => 1,
        ];

        // 来源/用途
        $cause_type_str = input('param.cause_type_str/s');
        if (!empty($cause_type_str)) {
            $condition['a.cause_type'] = ['IN', explode('_', $cause_type_str)]; 
        }

        // 应用搜索条件
        $keywords = input('keywords/s');
        if (!empty($keywords)) $condition['a.order_number|b.username'] = array('LIKE', "%{$keywords}%");

        // 支付方式查询
        $pay_method = input('pay_method/s');
        if (!empty($pay_method)) $condition['a.pay_method'] = $pay_method;

        // 会员级别查询
        $level = input('level/s');
        if (!empty($level)) $condition['b.level'] = $level;

        // 订单状态搜索
        // $order_status = input('param.status/d');
        // if ($order_status) $condition['a.status'] = in_array($order_status, [2, 3]) ? ['IN', [2, 3]] : $order_status; 

        // 时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 分页查询
        $count = $this->users_money_db->alias('a')->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = $this->users_money_db->field('a.*, b.head_pic, b.username, b.nickname, b.level')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->where($condition) 
            ->order('a.add_time desc, a.moneyid desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $order_number_arr = [];
        foreach ($list as $key => $value) {
            if (in_array($value['status'],[2,3])  && 'wechat' == $value['pay_method']) $order_number_arr[] = $value['order_number'];
            $value['username'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];
            $value['head_pic'] = get_head_pic($value['head_pic']);
            $list[$key] = $value;
        }

        if (!empty($order_number_arr)){
            //处理微信推送数据
            $wsi_where['order_code'] = ['in',$order_number_arr];
            $wsi_where['order_source'] = ['in',[1,20]];
            $wx_push_arr = Db::name('wx_shipping_info')->where($wsi_where)->getAllWithIndex('order_code');
            if(!empty($wx_push_arr)){
                foreach ($list as $key => $value) {
                    if (!empty($wx_push_arr[$value['order_number']])) $list[$key]['wx_shipping_info'] = $wx_push_arr[$value['order_number']];
                }
            }
        }

        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);

        // 会员等级列表
        $usersLevel = model('UsersLevel')->getList('level_id, level_name', [], 'level_id');
        $this->assign('usersLevel', $usersLevel);

        // 充值状态
        $pay_status_arr = config('global.pay_status_arr');
        $this->assign('pay_status_arr', $pay_status_arr);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        return $this->fetch();
    }

    // 充值记录编辑
    public function money_edit()
    {   
        $param = input('param.');
        $MoneyData = $this->users_money_db->find($param['moneyid']);
        $this->assign('MoneyData',$MoneyData);
        $UsersData = $this->users_db->find($MoneyData['users_id']);
        $this->assign('UsersData',$UsersData);
        
        // 支付宝查询订单
        if ('alipay' == $MoneyData['pay_method']) {
            $return = $this->check_alipay_order($MoneyData['order_number']);
            $this->assign('return',$return);
        }

        // 微信查询订单
        if ('wechat' == $MoneyData['pay_method']) {
            $return = $this->check_wechat_order($MoneyData['order_number']);
            $this->assign('return',$return);
        }

        // 人为处理订单
        if ('artificial' == $MoneyData['pay_method']) {
            $return = '人为处理';
            $this->assign('return',$return);
        }

        // 获取订单状态
        $pay_status_arr = Config::get('global.pay_status_arr');
        $this->assign('pay_status_arr',$pay_status_arr);

        // 支付方式
        $pay_method_arr = config('global.pay_method_arr');
        $this->assign('pay_method_arr',$pay_method_arr);

        return $this->fetch();
    }
    
    /**
     * 删除充值记录
     */
    public function money_del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $result = Db::name('users_money')->field('order_number')
                    ->where([
                        'moneyid'    => ['IN', $id_arr],
                    ])->select();
                $order_number_list = get_arr_column($result, 'order_number');

                $r = Db::name('users_money')->where([
                        'moneyid'    => ['IN', $id_arr],
                    ])
                    ->cache(true, null, "users_money")
                    ->delete();
                if($r !== false){
                    adminLog('删除充值记录：'.implode(',', $order_number_list));
                    $this->success('删除成功');
                }
            }
            $this->error('删除失败');
        }
        $this->error('非法访问');
    }

    // 标记订单逻辑
    public function money_mark_order()
    {
        if (IS_POST) {
            $moneyid     = input('param.moneyid/d');

            // 查询订单信息
            $MoneyData = $this->users_money_db->where([
                'moneyid'     => $moneyid,
            ])->find();

            // 处理订单逻辑
            if (in_array($MoneyData['status'], [1,3])) {

                $users_id = $MoneyData['users_id'];
                $order_number = $MoneyData['order_number'];
                $return = '';
                if ('alipay' == $MoneyData['pay_method']) { // 支付宝查询订单
                    $return = $this->check_alipay_order($order_number);
                } else if ('wechat' == $MoneyData['pay_method']) { // 微信查询订单
                    $return = $this->check_wechat_order($order_number);
                } else if ('artificial' == $MoneyData['pay_method']) { // 手工充值订单
                    $return = '手工充值';
                }
                
                $result = [
                    'users_id'    => $users_id,
                    'order_number'=> $order_number,
                    'status'      => '手动标记为已充值订单',
                    'details'     => '充值详情：'.$return,
                    'pay_method'  => 'artificial', //人为处理
                    'money'       => $MoneyData['money'],
                    'users_money' => $MoneyData['users_money'],
                ];

                // 标记为未付款
                if (3 == $MoneyData['status']) {
                    $result['status'] = '手动标记为未付款订单';
                } else if (1 == $MoneyData['status']) {
                    $result['status'] = '手动标记为已充值订单';
                }

                // 修改会员充值明细表对应的订单数据，存入返回的数据，订单标记为已付款
                $Where = [
                    'moneyid'  => $MoneyData['moneyid'],
                    'users_id'  => $users_id,
                ];
                
                $UpdateData = [
                    'pay_details'   => serialize($result),
                    'update_time'   => getTime(),
                ];

                // 标记为未付款时则状态更新为1
                if (3 == $MoneyData['status']) {
                    $UpdateData['status'] = 1;
                } else if (1 == $MoneyData['status']) {
                    $UpdateData['status'] = 3;
                    $UpdateData['pay_method'] = 'admin_pay';
                    $UpdateData['wechat_pay_type'] = '';
                }

                $IsMoney = $this->users_money_db->where($Where)->update($UpdateData);

                if (!empty($IsMoney)) {
                    // 同步修改会员的金额
                    $UsersData = [
                        'update_time' => getTime(),
                    ];

                    // 标记为未付款时则减去金额
                    if (3 == $MoneyData['status']) {
                        $UsersData = $this->users_db->field('users_money')->find($users_id);
                        if ($UsersData['users_money'] <= $MoneyData['money']) {
                            $UsersData['users_money'] = 0;
                        }else{
                            $UsersData['users_money'] = Db::raw('users_money-'.$MoneyData['money']);
                        }
                    } else if (1 == $MoneyData['status']) {
                        $UsersData['users_money'] = Db::raw('users_money+'.$MoneyData['money']);
                    }

                    $IsUsers = $this->users_db->where('users_id',$users_id)->update($UsersData);
                    if (!empty($IsUsers)) {
                        $this->success('操作成功');
                    }
                }
            }
            $this->error('操作失败');
        }
    }

    // 查询订单付款状态(微信)
    private function check_wechat_order($order_number)
    {
        if (!empty($order_number)) {
            // 引入文件
            vendor('wechatpay.lib.WxPayApi');
            vendor('wechatpay.lib.WxPayConfig');
            // 实例化加载订单号
            $input  = new \WxPayOrderQuery;
            $input->SetOut_trade_no($order_number);

            // 处理微信配置数据
            $pay_wechat_config = !empty($this->usersConfig['pay_wechat_config']) ? $this->usersConfig['pay_wechat_config'] : '';
            $pay_wechat_config = unserialize($pay_wechat_config);
            $config_data['app_id'] = $pay_wechat_config['appid'];
            $config_data['mch_id'] = $pay_wechat_config['mchid'];
            $config_data['key']    = $pay_wechat_config['key'];

            // 实例化微信配置
            $config = new \WxPayConfig($config_data);
            $wxpayapi = new \WxPayApi;

            // 返回结果
            $result = $wxpayapi->orderQuery($config, $input);

            // 判断结果
            if ('ORDERNOTEXIST' == $result['err_code'] && 'FAIL' == $result['result_code']) {
                return '订单在微信中不存在！';
            }else if ('NOTPAY' == $result['trade_state'] && 'SUCCESS' == $result['result_code']) {
                return '订单在微信中生成，但并未支付完成！';
            }else if ('SUCCESS' == $result['trade_state'] && 'SUCCESS' == $result['result_code']) {
                return '订单已使用'.$result['attach'].'完成！';
            }
        }else{
            return false;
        }
    }

    // 查询订单付款状态(支付宝)
    private function check_alipay_order($order_number,$admin_pay='',$alipay='')
    {
        if (!empty($order_number)) {
            // 引入文件
            vendor('alipay.pagepay.service.AlipayTradeService');
            vendor('alipay.pagepay.buildermodel.AlipayTradeQueryContentBuilder');

            // 实例化加载订单号
            $RequestBuilder = new \AlipayTradeQueryContentBuilder;
            $out_trade_no   = trim($order_number);
            $RequestBuilder->setOutTradeNo($out_trade_no);

            // 处理支付宝配置数据
            if (empty($alipay)) {
                $pay_alipay_config = !empty($this->usersConfig['pay_alipay_config']) ? $this->usersConfig['pay_alipay_config'] : '';
                if (empty($pay_alipay_config)) {
                    return false;
                }
                $alipay = unserialize($pay_alipay_config);
            }
            $config['app_id']     = $alipay['app_id'];
            $config['merchant_private_key'] = $alipay['merchant_private_key'];
            $config['charset']    = 'UTF-8';
            $config['sign_type']  = 'RSA2';
            $config['gatewayUrl'] = 'https://openapi.alipay.com/gateway.do';
            $config['alipay_public_key'] = $alipay['alipay_public_key'];

            // 实例化支付宝配置
            $aop = new \AlipayTradeService($config);

            // 返回结果
            if (!empty($admin_pay)) {
                $result = $aop->IsQuery($RequestBuilder,$admin_pay);
            }else{
                $result = $aop->Query($RequestBuilder);
            }

            $result = json_decode(json_encode($result),true);

            // 判断结果
            if ('40004' == $result['code'] && 'Business Failed' == $result['msg']) {
                // 用于支付宝支付配置验证
                if (!empty($admin_pay)) { return 'ok'; }
                // 用于订单查询
                return '订单在支付宝中不存在！';
            }else if ('10000' == $result['code'] && 'WAIT_BUYER_PAY' == $result['trade_status']) {
                return '订单在支付宝中生成，但并未支付完成！';
            }else if ('10000' == $result['code'] && 'TRADE_SUCCESS' == $result['trade_status']) {
                return '订单已使用支付宝支付完成！';
            }

            // 用于支付宝支付配置验证
            if (!empty($admin_pay) && !empty($result)) {
                if ('40001' == $result['code'] && 'Missing Required Arguments' == $result['msg']) {
                    return '商户私钥错误！';
                }
                if (!is_array($result)) {
                    return $result;
                }
            }
        }
    }

    /**
     * 版本检测更新弹窗
     */
    public function ajax_check_upgrade_version()
    {
        $memberLogic = new MemberLogic;
        $upgradeMsg = $memberLogic->checkVersion(); // 升级包消息
        $this->success('检测成功', null, $upgradeMsg);  
    }

    /**
    * 一键升级
    */
    public function OneKeyUpgrade(){
        header('Content-Type:application/json; charset=utf-8');
        function_exists('set_time_limit') && set_time_limit(0);

        /*权限控制 by 小虎哥*/
        $auth_role_info = session('admin_info.auth_role_info');
        if(0 < intval(session('admin_info.role_id')) && ! empty($auth_role_info) && intval($auth_role_info['online_update']) <= 0){
            $this->error('您没有操作权限，请联系超级管理员分配权限');
        }
        /*--end*/

        $memberLogic = new MemberLogic;
        $data = $memberLogic->OneKeyUpgrade(); //升级包消息
        if (1 <= intval($data['code'])) {
            $this->success($data['msg'], null, ['code'=>$data['code']]);
        } else {
            $msg = '模板升级异常，请排查问题！';
            if (is_array($data)) {
                $msg = $data['msg'];
            }
            $this->error($msg);
        }
    }

    /**
    * 检测目录权限
    */
    public function check_authority()
    {
        $filelist = input('param.filelist/s');
        $memberLogic = new MemberLogic;
        $data = $memberLogic->checkAuthority($filelist); //检测目录权限
        if (is_array($data)) {
            if (1 == $data['code']) {
                $this->success($data['msg']);
            } else {
                $this->error($data['msg'], null, $data['data']);
            }
        } else {
            $this->error('检测模板失败', null, ['code'=>1]);
        }
    }

    // 前台会员左侧菜单
    public function ajax_menu_index()
    {
        $list = array();
        $condition = array();

        $usersTplVersion = getUsersTplVersion();
        if (in_array($usersTplVersion, ['v2','v5'])) {
            $condition['a.version'] = array('EQ', $usersTplVersion);
        } else {
            $condition['a.version'] = array('IN', ['weapp', $usersTplVersion]);
        }

        $count = Db::name('users_menu')->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $row = Db::name('users_menu')->field('a.*')
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $list = [];
        foreach ($row as $key => $val) {
            $list[] = $val;
        }

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);

        $pc_user_left_menu_config = Config::get('global.pc_user_left_menu_config');
        $this->assign('menu_list',$pc_user_left_menu_config);

        return $this->fetch();
    }

    //保存会员中心左侧菜单
    public function save_users_menu()
    {
        if (IS_AJAX_POST){
            $post = input('post.');
            $usersTplVersion = getUsersTplVersion();
            $pc_user_left_menu_config = Config::get('global.pc_user_left_menu_config');
            if (!empty($post['data'])){
                $insert = [];
                foreach ($post['data'] as $k => $v){
                    if (empty($v['title'])) continue;
                    $v['title'] = htmlspecialchars($v['title']);
                    if (!empty($v['id'])){
                        $v['update_time'] = getTime();
                        Db::name('users_menu')->update($v);
                    }else{
                        unset($v['id']);
                        if (7 != $v['type']) {
                            $v['mca'] = $pc_user_left_menu_config[$v['type']]['mca'];
                            $v['active_url'] = $pc_user_left_menu_config[$v['type']]['active_url'];
                        }else{
                            $v['active_url'] = $v['mca'];
                        }
                        $v['version'] = $usersTplVersion;
                        $v['add_time'] = getTime();
                        $v['update_time'] = getTime();
                        $insert[] = $v;
                    }
                }
                if (!empty($insert)){
                    Db::name('users_menu')->insertAll($insert);
                }
            }
            $this->success('保存成功!');
        }
        $this->error('请求失败!');
    }

    //删除会员中心左侧菜单
    public function del_users_menu()
    {
        $menu_id = input('del_id/a');
        $menu_id = eyIntval($menu_id);

        if (IS_AJAX_POST && !empty($menu_id)) {
            $title_list = Db::name('users_menu')->where('id','in',$menu_id)->column('title');
            $return = Db::name('users_menu')->where('id','in',$menu_id)->delete();
            if ($return) {
                \think\Cache::clear('users_menu');
                adminLog('删除会员中心左侧菜单：'.implode(',', $title_list));
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }


    // 前台会员手机端底部菜单
    public function ajax_bottom_menu_index()
    {
        $list = array();
        $condition = array();
        $condition['a.lang'] = $this->main_lang;

        /**
         * 数据查询
         */
        $count = Db::name('users_bottom_menu')->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = Db::name('users_bottom_menu')->field('a.*')
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        // 手机端会员中心底部菜单配置选项
        $mobile_user_bottom_menu_config = Config::get('global.mobile_user_bottom_menu_config');

        if ($mobile_user_bottom_menu_config) {
            foreach ($mobile_user_bottom_menu_config as $key=>$v) {
                switch ($v['mca']) {
                    case 'user/Level/level_centre':
                        $is_open = getUsersConfigData('level.level_member_upgrade');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/Pay/pay_account_recharge':
                        $is_open = getUsersConfigData('level.level_member_upgrade');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/Shop/shop_centre':
                        $is_open = getUsersConfigData('shop.shop_open');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/Shop/shop_cart_list':
                        $is_open = getUsersConfigData('shop.shop_open');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/UsersRelease/article_add':
                        $is_open = getUsersConfigData('users.users_open_release');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/UsersRelease/release_centre':
                        $is_open = getUsersConfigData('users.users_open_release');
                        if (!$is_open || $is_open != 1) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                    case 'user/Download/index':
                        $is_open = Db::name("channeltype")->where(['nid'=>'download','status'=>1])->value("id");
                        if (!$is_open) unset($mobile_user_bottom_menu_config[$key]);
                        break;
                }
            }
        }

        $this->assign('mobile_user_bottom_menu_config',$mobile_user_bottom_menu_config);

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);

        return $this->fetch();
    }

    /**
     * 登录会员面板
     */
    public function syn_users_login($users_id = 0, $mca = '', $vars = [])
    {
        if (!empty($users_id)) {
            $users = Db::name('users')->field('a.*,b.level_name,b.level_value,b.discount as level_discount')
                ->alias('a')
                ->join('__USERS_LEVEL__ b', 'a.level = b.level_id', 'LEFT')
                ->where([
                    'a.users_id' => $users_id,
                    'a.is_del' => 0,
                ])->find();
            if (!empty($users)) {
                cookie('dealerParam', null);
                session('dealerParam', null);
                session('users_id', $users_id);
                session('users_login_expire', getTime()); // 登录有效期
                $url = get_homeurl($mca, $vars);
                $this->redirect($url);             
            } else {
                $this->error('该用户不存在！');
            }
        }
    }

    // --------------------------视频订单------------------------------ //

    // 视频订单列表页
    public function media_index()
    {   
        // 定义数组
        $condition = [];

        // 订单状态搜索
        $order_status = input('param.order_status/d', 0);
        if (!empty($order_status)) $condition['a.order_status'] = intval($order_status) === 1 ? intval($order_status) : 0;

        // 订单号或用户名搜索
        $keywords = input('keywords/s', '');
        if (!empty($keywords)) $condition['a.order_code|b.username'] = ['LIKE', "%{$keywords}%"];

        // 支付方式查询
        $pay_name = input('pay_name/s', '');
        if (!empty($pay_name)) $condition['a.pay_name'] = $pay_name;

        // 时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 分页查询
        $count = Db::name('media_order')->alias('a')->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = Db::name('media_order')->where($condition)
            ->field('a.*, b.head_pic, b.username, b.nickname')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $order_code_arr = [];
        foreach ($list as $key => $value) {
            if (1 == $value['order_status'] && 'wechat' == $value['pay_name']) $order_code_arr[] = $value['order_code'];
            $value['username'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];
            $value['head_pic'] = get_head_pic($value['head_pic']);
            $list[$key] = $value;
        }
        if (!empty($order_code_arr)){
            //处理微信推送数据
            $wsi_where['order_code'] = ['in',$order_code_arr];
            $wsi_where['order_source'] = 8;
            $wx_push_arr = Db::name('wx_shipping_info')->where($wsi_where)->getAllWithIndex('order_code');
            if(!empty($wx_push_arr)){
                foreach ($list as $key => $value) {
                    if (!empty($wx_push_arr[$value['order_code']])) $list[$key]['wx_shipping_info'] = $wx_push_arr[$value['order_code']];
                }
            }
        }
        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        return $this->fetch();
    }

    // 视频订单详情页
    public function media_order_details()
    {
        $order_id = input('param.order_id/d');
        if (!empty($order_id)) {
            // 查询订单信息
            $OrderData = Db::name('media_order')->field('*, product_id as aid')->find($order_id);
            // 查询会员数据
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $OrderData['arcurl'] = get_arcurl($array_new[$OrderData['product_id']]);
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

    // 视频订单批量删除
    public function media_order_del()
    {
        $order_id = input('del_id/a');
        $order_id = eyIntval($order_id);
        if (IS_AJAX_POST && !empty($order_id)) {
            // 条件数组
            $Where = [
                'order_id'  => ['IN', $order_id],
            ];
            $result = Db::name('media_order')->field('order_code')->where($Where)->select();
            $order_code_list = get_arr_column($result, 'order_code');
            // 删除订单列表数据
            $return = Db::name('media_order')->where($Where)->delete();
            if ($return) {
                adminLog('删除订单：'.implode(',', $order_code_list));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    // --------------------------视频订单------------------------------ //

    // 文章订单列表页
    public function article_index()
    {
        // 定义数组
        $condition = [];

        // 订单状态搜索
        $order_status = input('param.order_status/d', 0);
        if (!empty($order_status)) $condition['a.order_status'] = intval($order_status) === 1 ? intval($order_status) : 0;

        // 订单号或用户名搜索
        $keywords = input('keywords/s', '');
        if (!empty($keywords)) $condition['a.order_code|b.username'] = ['LIKE', "%{$keywords}%"];

        // 支付方式查询
        $pay_name = input('pay_name/s', '');
        if (!empty($pay_name)) $condition['a.pay_name'] = $pay_name;

        // 时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 分页查询
        $count = Db::name('article_order')->alias('a')->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = Db::name('article_order')->where($condition)
            ->field('a.*, b.head_pic, b.username, b.nickname')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $order_code_arr = [];
        foreach ($list as $key => $value) {
            if (1 == $value['order_status'] && 'wechat' == $value['pay_name']) $order_code_arr[] = $value['order_code'];
            $value['username'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];
            $value['head_pic'] = get_head_pic($value['head_pic']);
            $list[$key] = $value;
        }
        if (!empty($order_code_arr)){
            //处理微信推送数据
            $wsi_where['order_code'] = ['in',$order_code_arr];
            $wsi_where['order_source'] = 9;
            $wx_push_arr = Db::name('wx_shipping_info')->where($wsi_where)->getAllWithIndex('order_code');
            if(!empty($wx_push_arr)){
                foreach ($list as $key => $value) {
                    if (!empty($wx_push_arr[$value['order_code']])) $list[$key]['wx_shipping_info'] = $wx_push_arr[$value['order_code']];
                }
            }
        }

        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        return $this->fetch();
    }

    // 文章订单详情页
    public function article_order_details()
    {
        $order_id = input('param.order_id/d');
        if (!empty($order_id)) {
            $OrderData = Db::name('article_order')->field('*, product_id as aid')->find($order_id);
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $OrderData['arcurl'] = get_arcurl($array_new[$OrderData['product_id']]);
            // 支持子目录
            $OrderData['product_litpic'] = get_default_pic($OrderData['product_litpic']);
            $this->assign('OrderData', $OrderData);
            $this->assign('UsersData', $UsersData);
            return $this->fetch();
        } else {
            $this->error('非法访问！');
        }
    }

    // 文章订单批量删除
    public function article_order_del()
    {
        $order_id = input('del_id/a');
        $order_id = eyIntval($order_id);
        if (IS_AJAX_POST && !empty($order_id)) {
            $Where = [
                'order_id'  => ['IN', $order_id],
            ];
            $result = Db::name('article_order')->field('order_code')->where($Where)->select();
            $order_code_list = get_arr_column($result, 'order_code');
            // 删除订单列表数据
            $return = Db::name('article_order')->where($Where)->delete();
            if ($return) {
                adminLog('删除文章订单：'.implode(',', $order_code_list));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    //积分明细
    public function users_score_detail()
    {
        $condition = [];
        $users_id = input('param.users_id/d');
        $condition['a.users_id'] = $users_id;

        $pagesize = 10;
        $count = Db::name('users_score')->alias('a')->where($condition)->count();
        $Page = new Page($count, $pagesize);
        $list = Db::name('users_score')
            ->alias('a')
            ->field('a.*,b.user_name,c.nickname')
            ->where($condition)
            ->join('admin b','a.admin_id = b.admin_id','left')
            ->join('users c','c.users_id = a.users_id','left')
            ->order('a.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);

        return $this->fetch('member/edit/users_score_detail');
    }

    //余额明细
    public function users_money_detail()
    {
        $users_id = input('param.users_id/d');
        $condition = [
            'a.users_id' => $users_id,
            'a.status' => ['IN', [2, 3]],
        ];
        $count = Db::name('users_money')->alias('a')->where($condition)->count();
        $Page = new Page($count, 10);
        $list = Db::name('users_money')
            ->alias('a')
            ->field('a.*,b.user_name,c.nickname')
            ->where($condition)
            ->join('admin b','a.admin_id = b.admin_id','left')
            ->join('users c','c.users_id = a.users_id','left')
            ->order('a.update_time desc, a.moneyid desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        foreach ($list as $k => $v) {
            $v['money'] = floatval($v['money']);
            $v['users_money'] = floatval($v['users_money']);
            if (isset($v['cause_type']) && 0 == $v['cause_type']) $v['cause'] = unserialize($v['cause']);

            $list[$k] = $v;
        }

        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        $this->assign('increase_type', [0, 3, 5, 6]);
        $this->assign('pay_cause_type_arr', Config::get('global.pay_cause_type_arr'));
        return $this->fetch('member/edit/users_money_detail');
    }

    // 余额充值
    public function users_edit_money()
    {
        $post = input('param.');
        $users_id = $post['users_id'] = intval($post['users_id']);
        $users = $this->users_db->where('users_id', $users_id)->find();
        if (IS_POST) {
            if (empty($post['type'])){
                $this->error('请选择变化！');
            }
            if (empty($post['money']) && in_array($post['type'], [1,2])){
                $this->error('变动数值不能为空或0');
            } else if (!empty($post['money']) && !preg_match('/^([0-9\.]+)$/i', $post['money'])) {
                $this->error('变动数值格式不正确！');
            }

            // 处理数据验证
            $error = handleEyouDataValidate('users_id', '__token_users_edit_money__', $post);
            if (!empty($error)) $this->error($error);

            $times = getTime();
            // 添加会员余额记录
            $insert = [
                'users_id' => $users_id,
                'money' => floatval($post['money']),
                'users_money' => floatval($users['users_money']) + floatval($post['money']),
            ];
            // 更新会员主表余额
            $update = [
                'update_time' => $times,
            ];
            // 4-管理员添加 5-管理员减少
            $cause_type = 4;
            if (1 === intval($post['type'])) {
                //增加
                $update['users_money'] = Db::raw('users_money+'.floatval($post['money']));
            } else if (2 === intval($post['type'])) {
                //减少
                $cause_type = 5;
                $update['users_money'] = Db::raw('users_money-'.floatval($post['money']));
                $insert['users_money'] = floatval($users['users_money']) - floatval($post['money']);
            } else if (3 === intval($post['type'])) {
                $insert['users_money'] = floatval($post['money']);
                $update['users_money'] = floatval($post['money']);
                // 变化余额 大于 会员余额则执行
                if (floatval($post['money']) > floatval($users['users_money'])) {
                    $cause_type = 4;
                    $insert['money'] = floatval($post['money']) - floatval($users['users_money']);
                }
                // 变化余额 小于 会员余额则执行
                else {
                    $cause_type = 5;
                    $insert['money'] = floatval($users['users_money']) - floatval($post['money']);
                }
            }
            // 更新会员主表余额
            $where = [
                'users_id' => $users_id,
            ];
            $r = $this->users_db->where($where)->update($update);
            if ($r !== false) {
                // 添加会员余额记录
                $pay_cause_type_arr = config('global.pay_cause_type_arr');
                $order_number = date('Ymd') . getTime() . rand(10,100); //订单生成规则
                $insert['order_number'] = $order_number;
                $insert['users_id'] = $users_id;
                $insert['admin_id'] = session('admin_id');
                $insert['cause'] = $pay_cause_type_arr[$cause_type];
                $insert['cause_type'] = $cause_type;
                $insert['pay_method'] = 'admin_pay';
                $insert['pay_details'] = '';
                $insert['status'] = 3;
                $insert['add_time'] = $times;
                $insert['update_time'] = $times;
                $this->users_money_db->insert($insert);

                adminLog('编辑 '.$users['nickname'].' 的会员余额');
                $this->success('操作成功');
            }
            $this->error('操作失败');
        }

        $this->assign('users', $users);
        return $this->fetch('member/edit/users_edit_money');
    }

    // 积分充值
    public function users_edit_score()
    {
        $post = input('param.');
        $post['users_id'] = intval($post['users_id']);
        $users_id = $post['users_id'];
        $users = Db::name('users')->where('users_id',$users_id)->find();
        if (IS_POST){
            if (empty($post['type'])){
                $this->error('请选择变化！');
            }
            if (empty($post['money']) && in_array($post['type'], [1,2])){
                $this->error('变动数值不能为空或0');
            } else if (!empty($post['money']) && !preg_match('/^([0-9\.]+)$/i', $post['money'])) {
                $this->error('变动数值格式不正确！');
            }

            // 处理数据验证
            $error = handleEyouDataValidate('users_id', '__token_users_edit_score__', $post);
            if (!empty($error)) $this->error($error);

            $insert['type'] = 6;
            $insert['users_id'] = $users_id;
            $insert['current_score'] = $users['scores'];
            $insert['lang'] = $this->admin_lang;
            $insert['devote'] = $insert['current_devote'] = 0;
            $insert['add_time'] = $insert['update_time'] = $update['update_time'] = getTime();

            // 增加
            if (1 == $post['type']) {
                $update['scores'] = Db::raw('scores + '.$post['money']);
                $insert['score'] = '+' . $post['money'];
                $insert['current_score'] += $post['money'];
            }
            // 减少
            else if (2 == $post['type']) {
                $update['scores'] = Db::raw('scores - '.$post['money']);
                $insert['score'] = '-' . $post['money'];
                $insert['current_score'] -= $post['money'];
            }
            // 根据变动数据计算是增加还是减少
            else if (3 == $post['type']) {
                // 增加
                if ($post['money'] > $users['scores']) {
                    $insert['score'] = '+' . ($post['money'] - $users['scores']);
                }
                // 减少
                else {
                    $insert['score'] = '-' . ($users['scores'] - $post['money']);
                }
                $update['scores'] = $post['money'];
                $insert['current_score'] = $post['money'];
            }

            // 更新会员积分
            $where = [
                'users_id' => $users_id,
            ];
            $result = $this->users_db->where($where)->update($update);
            if (!empty($result)) {
                // 增加会员积分变动记录
                if (!empty($post['remark'])) $insert['remark'] = $post['remark'];
                $insert['admin_id'] = session('admin_id');
                Db::name('users_score')->insert($insert);

                \think\Cache::clear('users_list');
                adminLog('编辑会员'.$users_id.'积分');
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
            $this->success('操作成功');
        }

        $this->assign('users',$users);
        return $this->fetch('member/edit/users_edit_score');
    }

    // 下载订单列表页
    public function download_index()
    {
        // 定义数组
        $condition = [];

        // 订单状态搜索
        $order_status = input('param.order_status/d', 0);
        if (!empty($order_status)) $condition['a.order_status'] = intval($order_status) === 1 ? intval($order_status) : 0;

        // 订单号或用户名搜索
        $keywords = input('keywords/s', '');
        if (!empty($keywords)) $condition['a.order_code|b.username'] = ['LIKE', "%{$keywords}%"];

        // 支付方式查询
        $pay_name = input('pay_name/s', '');
        if (!empty($pay_name)) $condition['a.pay_name'] = $pay_name;

        // 时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 分页查询
        $count = Db::name('download_order')->alias('a')->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = Db::name('download_order')->where($condition)
            ->field('a.*, b.head_pic, b.username, b.nickname')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('a.order_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $order_code_arr = [];
        foreach ($list as $key => $value) {
            if (1 == $value['order_status'] && 'wechat' == $value['pay_name']) $order_code_arr[] = $value['order_code'];
            $value['username'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];
            $value['head_pic'] = get_head_pic($value['head_pic']);
            $list[$key] = $value;
        }
        if (!empty($order_code_arr)){
            //处理微信推送数据
            $wsi_where['order_code'] = ['in',$order_code_arr];
            $wsi_where['order_source'] = 10;
            $wx_push_arr = Db::name('wx_shipping_info')->where($wsi_where)->getAllWithIndex('order_code');
            if(!empty($wx_push_arr)){
                foreach ($list as $key => $value) {
                    if (!empty($wx_push_arr[$value['order_code']])) $list[$key]['wx_shipping_info'] = $wx_push_arr[$value['order_code']];
                }
            }
        }

        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        return $this->fetch();
    }

    // 文章订单详情页
    public function download_order_details()
    {
        $order_id = input('param.order_id/d');
        if (!empty($order_id)) {
            $OrderData = Db::name('download_order')->field('*, product_id as aid')->find($order_id);
            $UsersData = $this->users_db->find($OrderData['users_id']);
            // 用于点击视频文档跳转到前台
            $array_new = get_archives_data([$OrderData], 'product_id');
            // 内页地址
            $OrderData['arcurl'] = get_arcurl($array_new[$OrderData['product_id']]);
            // 支持子目录
            $OrderData['product_litpic'] = get_default_pic($OrderData['product_litpic']);
            $this->assign('OrderData', $OrderData);
            $this->assign('UsersData', $UsersData);
            return $this->fetch('article_order_details');
        } else {
            $this->error('非法访问！');
        }
    }

    // 文章订单批量删除
    public function download_order_del()
    {
        $order_id = input('del_id/a');
        $order_id = eyIntval($order_id);
        if (IS_AJAX_POST && !empty($order_id)) {
            $Where = [
                'order_id'  => ['IN', $order_id],
            ];
            $result = Db::name('download_order')->field('order_code')->where($Where)->select();
            $order_code_list = get_arr_column($result, 'order_code');
            // 删除订单列表数据
            $return = Db::name('download_order')->where($Where)->delete();
            if ($return) {
                adminLog('删除下载订单：'.implode(',', $order_code_list));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    // AJAX导出搜索关键词Excel文档
    public function ajax_excel_export()
    {
        // 设置最大内存
        ini_set("memory_limit", "-1");

        // 防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if (file_exists('./vendor/PHPExcel.zip') && !is_dir('./vendor/PHPExcel/')) {
            $zip = new \ZipArchive();//新建一个ZipArchive的对象
            if ($zip->open(ROOT_PATH.'vendor'.DS.'PHPExcel.zip') === true) {
                $zip->extractTo(ROOT_PATH.'vendor'.DS.'PHPExcel'.DS);
                $zip->close();//关闭处理的zip文件
                if (is_dir('./vendor/PHPExcel/')) {
                    @unlink('./vendor/PHPExcel.zip');
                }
            }
        }

        // 执行操作
        if (IS_AJAX_POST) {
            $condition['is_del'] = 0;
            $orderby = "users_id desc";

            $list = $this->users_db->field('*')->where($condition)->order($orderby)->select();
            if (empty($list)) $this->error('没有导出的数据');
            $level = Db::name('users_level')->field('level_id, level_name')->cache(true, EYOUCMS_CACHE_TIME, "users_level")->getAllWithIndex('level_id');

            //成交订单数
            $order_count = Db::name('shop_order')->where('order_status',3)
                ->field('count(*) as count,sum(order_amount) as sum,users_id')
                ->group('users_id')->getAllWithIndex('users_id');

            // 处理订单导出数据
            $ExcelData = [];
            foreach ($list as $key => $value) {
                // 拼装追加数据
                $PushData = [
                    // 订单信息
                    'users_id'   => $value['users_id'],
                    'username'    => $value['username'],
                    'nickname'    => $value['nickname'],
                    'mobile'    => $value['mobile'],
                    'email'    => $value['email'],
                    'level'    => $level[$value['level']]['level_name'],
                    'level_maturity_days'    => $value['level_maturity_days'],
                    'scores'    => $value['scores'],
                    'users_money'    => $value['users_money'],
                    'order_count'    => !empty($order_count[$value['users_id']]['count']) ? $order_count[$value['users_id']]['count'] : 0,
                    'order_money_count'    => !empty($order_count[$value['users_id']]['sum']) ? $order_count[$value['users_id']]['sum'] : 0,
                    'is_lock' => !empty($value['is_lock']) ? '是' : '否',
                    'reg_time' => date('Y-m-d H:i:s', $value['reg_time']),
                    'last_login' => !empty($value['last_login']) ? date('Y-m-d H:i:s', $value['last_login']) : '',
                ];
                // 追加数据，用于导出
                array_push($ExcelData, $PushData);
            }
            // 导出字段设置
            $ExcelField = ['users_id', 'username', 'nickname', 'mobile', 'email', 'level','level_maturity_days', 'scores', 'users_money', 'order_count', 'order_money_count', 'is_lock', 'reg_time', 'last_login'];
            // 导出标题设置
            $ExcelTitle = ['会员ID', '用户名', '会员昵称', '手机号', '电子邮箱', '会员等级','会员天数', '积分', '余额', '成交订单数', '成交金额', '黑名单', '注册时间', '最后登录'];

            $ResultUrl = $this->PerformExport($ExcelData, $ExcelField, $ExcelTitle);
        }

        if (!empty($ResultUrl)) {
            $this->success('正在下载', $ResultUrl);
        } else {
            $this->error('导出失败');
        }
    }

    private function PerformExport($ExcelData = [], $ExcelField = [], $ExcelTitle = [])
    {
        // 引入SDK
        vendor("PHPExcel.Classes.PHPExcel");

        // Excel表格坐标
        $cell_arr = ['A','B','C','D','E','F','G','H','I','J','K','L','M', 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        // 执行导出
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getDefaultStyle()->getFont()->setName('Arial')->setSize(12);

        // 设置表格标题栏长度
        $objActSheet = $objPHPExcel->getActiveSheet();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(18);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(18);
        // 设置导出表格名称
        $FileName = 'users-'.date("YmdHis");

        // 循环设置表格标题栏数据
        $startRow = 1;
        if(!empty($ExcelTitle) || count($ExcelTitle) > 0) {
            foreach($ExcelTitle as $k => $v) {
                $objActSheet->setCellValue($cell_arr[$k] . $startRow, $v);
            }
            $startRow = 2;
        }

        // 循环设置表格字段内容数据
        foreach($ExcelData as $v) {
            foreach($ExcelField as $key => $value) {
                $objActSheet->setCellValue($cell_arr[$key] . $startRow, $v[$value]."\t");
            }
            $startRow++;
        }

        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $FileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

        // 文件目录
        $ExcelPath = UPLOAD_PATH . 'excel/';
        // 保存前清空删除原先的excel
        delFile(UPLOAD_PATH . 'excel/', true);
        // 创建文件夹
        @mkdir($ExcelPath, 0777, true);
        // excel文件路径
        $filePath = $ExcelPath . $FileName . '.xlsx';
        // 保存excel文件
        $objWriter->save($filePath);
        // 返回excel文件路径到AJAX下载
        return request()->domain() . ROOT_DIR . '/' . $filePath;
    }

    // 充值套餐列表
    public function recharge_pack_list()
    {
        // 搜索套餐名称
        $where = [];
        $packNames = input('param.pack_names/s', '');
        $this->assign('packNames', $packNames);
        if (!empty($packNames)) $where['pack_names'] = ['LIKE', "%{$packNames}%"];

        // 查询分页数据
        $count = Db::name('users_recharge_pack')->where($where)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = Db::name('users_recharge_pack')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('add_time desc, pack_id desc')->select();

        // 数据虚拟排序处理
        $count = intval($Page->nowPage) > 1 ? intval(++$count) - intval($Page->listRows) : intval(++$count);
        foreach ($list as $key => $value) {
            // 数据虚拟排序字段
            $list[$key]['virtual_id'] = --$count;
        }
        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    // 充值套餐添加
    public function recharge_pack_add()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 添加限制
            if (empty($post['pack_names'])) $this->error('请输入套餐名称');
            if (empty($post['pack_face_value'])) $this->error('请输入实际入账余额');
            if (empty($post['pack_pay_prices'])) $this->error('请输入实际支付价格');
            // 添加数据
            $times = getTime();
            $insert = [
                'pack_names' => strval($post['pack_names']),
                'pack_face_value' => unifyPriceHandle($post['pack_face_value']),
                'pack_pay_prices' => unifyPriceHandle($post['pack_pay_prices']),
                'add_time' => $times,
                'update_time' => $times,
            ];
            $insertID = Db::name('users_recharge_pack')->insertGetId($insert);
            // 返回结束
            if (!empty($insertID)) $this->success('添加成功');
            $this->error('添加失败');
        }

        return $this->fetch();
    }

    // 充值套餐编辑
    public function recharge_pack_edit()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 编辑限制
            if (empty($post['pack_id'])) $this->error('请选择要编辑套餐');
            if (empty($post['pack_names'])) $this->error('请输入套餐名称');
            if (empty($post['pack_face_value'])) $this->error('请输入实际入账余额');
            if (empty($post['pack_pay_prices'])) $this->error('请输入实际支付价格');
            // 编辑数据
            $times = getTime();
            $update = [
                'pack_id' => intval($post['pack_id']),
                'pack_names' => strval($post['pack_names']),
                'pack_face_value' => unifyPriceHandle($post['pack_face_value']),
                'pack_pay_prices' => unifyPriceHandle($post['pack_pay_prices']),
                'update_time' => $times,
            ];
            $insertID = Db::name('users_recharge_pack')->update($update);
            // 返回结束
            if (!empty($insertID)) $this->success('编辑成功');
            $this->error('编辑失败');
        }

        $pack_id = input('param.pack_id/d', 0);
        $where = [
            'pack_id' => intval($pack_id)
        ];
        $pack = Db::name('users_recharge_pack')->where($where)->find();
        $this->assign('pack', $pack);

        return $this->fetch();
    }

    // 充值套餐删除
    public function recharge_pack_del()
    {
        if (IS_AJAX_POST) {
            $pack_ids = input('del_id/a');
            $pack_ids = eyIntval($pack_ids);
            $where = [
                'pack_id' => ['IN', $pack_ids]
            ];
            $result = Db::name('users_recharge_pack')->where($where)->delete(true);
            if (!empty($result)) $this->success('删除成功');
        }

        $this->error('删除失败');
    }

    // 充值套餐领取记录
    public function recharge_pack_log()
    {
        // 搜索套餐名称
        $where = [
            'a.order_status' => ['IN', [2, 3]]
        ];
        $keywords = input('param.keywords/s', '');
        $this->assign('keywords', $keywords);
        if (!empty($keywords)) $where['b.username|b.nickname'] = ['LIKE', "%{$keywords}%"];

        // 时间检索条件
        $begin = strtotime(input('param.add_time_begin/s'));
        $end = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end = strtotime($end);
        // 时间检索
        if ($begin > 0 && $end > 0) {
            $where['a.order_pay_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $where['a.order_pay_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $where['a.order_pay_time'] = array('elt', $end);
        }

        // 查询分页数据
        $count = Db::name('users_recharge_pack_order')->alias('a')->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')->where($where)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $field = 'a.*, b.username, b.nickname, b.head_pic';
        $list = Db::name('users_recharge_pack_order')->alias('a')
            ->field($field)
            ->where($where)
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('add_time desc, order_id desc')
            ->select();

        // 数据虚拟排序处理
        $count = intval($Page->nowPage) > 1 ? intval(++$count) - intval($Page->listRows) : intval(++$count);
        foreach ($list as $key => $value) {
            // 数据虚拟排序字段
            $list[$key]['virtual_id'] = --$count;
            $list[$key]['head_pic'] = get_head_pic($value['head_pic']);
            $list[$key]['nickname'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];
        }
        // dump($list);exit;
        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    //会员注销列表
    public function log_off_index()
    {
        $count = Db::name('users_log_off')->count('id');
        $pageObj = new Page($count, 10);
        $list = Db::name('users_log_off')
            ->order('id desc')
            ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
            ->select();
        $pageStr = $pageObj->show();
        $this->assign('list', $list);
        $this->assign('pageStr', $pageStr);
        $this->assign('pageObj', $pageObj);
        $cur_key = $pageObj->totalRows - ($pageObj->nowPage - 1) * ($pageObj->listRows);
        $this->assign('cur_key', $cur_key);

        return $this->fetch('member/log_off/log_off_index');
    }

    //会员注销配置
    public function log_off_set()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            getUsersConfigData('users', ['users_open_log_off' => $post['data']['users_open_log_off']], 'cn'); // 开启注销
            getUsersConfigData('users', ['users_log_off_check' => $post['data']['users_log_off_check']], 'cn'); // 注销审核

            $this->success('保存成功');
        }
        $data = getUsersConfigData('users', '', 'cn');
        $this->assign('data', $data);

        return $this->fetch('member/log_off/log_off_set');
    }

    //会员注销列表详情查看
    public function log_off_see()
    {
        $id = input('param.id/d');
        if (empty($id)) $this->error('缺少必要参数');
        $info = Db::name('users_log_off')
            ->alias('a')
            ->field('a.*,b.user_name')
            ->join('admin b', 'a.admin_id = b.admin_id', 'left')
            ->where('a.id', $id)
            ->find();
        $this->assign('info', $info);
        return $this->fetch('member/log_off/log_off_see');
    }

    //会员注销审核
    public function handle_log_off()
    {
        $param = input('param.');
        $admin_id = session('admin_id');
        if (empty($admin_id)) $this->error('操作失败');
        $users_id = Db::name('users_log_off')->where('id', $param['id'])->value('users_id');
        if (1 == $param['status']) {
            $data = getUsersConfigData('users', '', 'cn');
            if (empty($data['users_open_log_off'])) $this->error('未开启会员注销');

            $users = Db::name('users')->where('users_id', $users_id)->find();
            if (empty($users)) $this->error('会员不存在');

            $update = [
                'status' => 1,
                'admin_id' => $admin_id,
                'handle_time' => getTime(),
                'update_time' => getTime(),
            ];
            $r = Db::name('users_log_off')->where('id', $param['id'])->update($update);
            if (false !== $r) {
                //直接删除
                Db::name('users')->where('users_id', $users_id)->delete();
                model('Member')->afterDel([$users_id]);
                $this->success('注销成功');
            }
        } elseif (2 == $param['status']) {
            //拒绝注销
            $update = [
                'status' => 2,
                'admin_id' => $admin_id,
                'refuse_reason' => $param['refuse_reason'],
                'handle_time' => getTime(),
                'update_time' => getTime(),
            ];
            $r = Db::name('users_log_off')->where('id', $param['id'])->update($update);
            if (false !== $r) {
                $this->success('拒绝注销成功');
            }
        }
        $this->error('操作失败');
    }
  
}