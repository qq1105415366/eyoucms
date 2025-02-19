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
use think\Session;
use think\Config;
use app\admin\logic\AjaxLogic;

/**
 * 所有ajax请求或者不经过权限验证的方法全放在这里
 */
class Ajax extends Base {
    
    private $ajaxLogic;

    public function _initialize() {
        parent::_initialize();
        $this->ajaxLogic = new AjaxLogic;
    }

    /*
     * 移动重新排序
     */
    public function ajax_move_admin_menu(){
        $post = input("post.");
        $menu_id_arr = $post['menu_id'];
        try{
            foreach ($menu_id_arr as $key=>$val){
                if ($val != 2004){
                    Db::name("admin_menu")->where(['menu_id'=>$val])->setField('sort_order',$key);
                }
            }
        }catch (\Exception $e){
            die("修改失败");
        }
        $this->success("移动排序成功");
    }
    
    /*
     *  添加、删除左侧菜单栏目
     */
    public function update_admin_menu(){
        if (IS_AJAX_POST) {
            $post = input('post.');
            $post['title'] = empty($post['title']) ? '' : trim($post['title']);
            $post['original_title'] = empty($post['original_title']) ? '' : trim($post['original_title']);
            if (empty($post['title']) || empty($post['controller_name']) || empty($post['action_name']) || empty($post['menu_id']) || empty($post['type'])){
                $this->error('请传入正确参数');
            }
            $menu_info = Db::name("admin_menu")->where(['menu_id'=>$post['menu_id']])->find();
            $icon = !empty($post['icon']) ? $post['icon'] : 'fa fa-minus';
            if ($post['type'] == 1){   //添加目录
                if (!empty($post['target'])) {
                    $target = $post['target'];
                } else {
                    $all_menu_tree = getAllMenu();
                    $all_menu_list = tree_to_list($all_menu_tree,'child','id');
                    $target = empty($all_menu_list[$post['menu_id']]['target']) ? 'workspace' : $all_menu_list[$post['menu_id']]['target'];
                }
                $is_switch = isset($post['is_switch']) ? $post['is_switch'] : 1;
                if (!empty($menu_info)){
                    $update_data = ['title' => $post['title'], 'icon' => $icon,'is_menu'=>1, 'is_switch' => $is_switch,'sort_order'=>100,'update_time' => getTime()];
                    if(!empty($post['controller_name'])){
                        $update_data['controller_name'] = $post['controller_name'];
                    }
                    if(!empty($post['action_name'])){
                        $update_data['action_name'] = $post['action_name'];
                    }
                    if(!empty($post['param'])){
                        $update_data['param'] = $post['param'];
                    }
                    if(!empty($target)){
                        $update_data['target'] = $target;
                    }
                    $r = Db::name("admin_menu")->where(['menu_id'=>$menu_info['menu_id']])->update($update_data);
                }else{
                    $menu_info = [
                        'menu_id' => $post['menu_id'],
                        'title' => $post['title'],
                        'original_title' => $post['original_title'],
                        'controller_name' => $post['controller_name'],
                        'action_name' => $post['action_name'],
                        'param' => !empty($post['param']) ? $post['param'] : '',
                        'icon' => $icon,
                        'is_menu' => 1,
                        'is_switch' => $is_switch,
                        'target' => $target,
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                    Db::name("admin_menu")->where([ 'original_title' => $post['original_title'], 'controller_name' => $post['controller_name'],'action_name' => $post['action_name']])->delete();
                    $r = Db::name("admin_menu")->insert($menu_info);
                }
                if ($r !== false) {
                    $menu_info['url'] = url($post['controller_name']."/".$post['action_name']);
                    $this->success("添加成功",null,$menu_info);
                }
            }else{          //删除目录
                if (!empty($menu_info)){
                    $update_data = ['sort_order'=>100, 'is_menu'=>0, 'update_time' => getTime()];
                    $r = Db::name("admin_menu")->where(['menu_id'=>$menu_info['menu_id']])->update($update_data);
                    if ($r !== false) {
                        $this->success("删除成功");
                    }
                }
            }
        }

        $this->error('请求错误');
    }

    /**
     * 进入欢迎页面需要异步处理的业务
     */
    public function welcome_handle()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $this->ajaxLogic->welcome_handle();
    }

    /**
     * 隐藏后台欢迎页的系统提示
     */
    public function explanation_welcome()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $type = input('param.type/d', 0);
        $tpCacheKey = 'system_explanation_welcome';
        if (1 < $type) {
            $tpCacheKey .= '_'.$type;
        }
        
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('system', [$tpCacheKey=>1], $val['mark']);
            }
        } else { // 单语言
            tpCache('system', [$tpCacheKey=>1]);
        }
        /*--end*/
    }

    /**
     * 版本检测更新弹窗
     */
    public function check_upgrade_version()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if ($this->admin_lang != $this->main_lang) {
            $upgradeMsg = ['code' => 1, 'msg' => '已是最新版'];
        } else {
            if ($this->php_servicemeal > 0) {
                $upgradeLogic = new \app\admin\logic\UpgradeLogic;
                $security_patch = tpSetting('upgrade.upgrade_security_patch');
                if (!empty($security_patch) && 1 == $security_patch) {
                    $upgradeMsg = $upgradeLogic->checkSecurityVersion(); // 安全补丁包消息
                } else {
                    $upgradeMsg = $upgradeLogic->checkVersion(); // 升级包消息
                }
            } else {
                $cur_version = getCmsVersion();
                $file_url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/'.'pa'.'ck'.'ag'.'e/'.'ve'.'rs'.'io'.'n.'.'tx'.'t';
                $max_version = @file_get_contents($file_url);
                $max_version = empty($max_version) ? '' : $max_version;
                if (!empty($max_version) && $cur_version >= $max_version) {
                    $upgradeMsg = ['code' => 1, 'msg' => '已是最新版'];
                } else {
                    $data = [
                        'max_version' => $max_version,
                        'tips' => "检测到新版本[点击查看]",
                    ];
                    $upgradeMsg = ['code' => 99, 'msg' => "检测到新版本{$max_version}[点击查看]", 'data'=>$data];
                }
            }

            // 权限控制 by 小虎哥
            $admin_info = session('admin_info');
            if (0 < intval($admin_info['role_id'])) {
                $auth_role_info = $admin_info['auth_role_info'];
                if (isset($auth_role_info['online_update']) && 1 != $auth_role_info['online_update']) {
                    $upgradeMsg = ['code' => 1, 'msg' => '已是最新版'];
                }
            }
        }
        $this->success('检测成功', null, $upgradeMsg);  
    }

    /**
     * 更新stiemap.xml地图
     */
    public function update_sitemap($controller, $action)
    {
        if (IS_AJAX_POST) {
            \think\Session::pause(); // 暂停session，防止session阻塞机制
            $channeltype_row = \think\Cache::get("extra_global_channeltype");
            if (empty($channeltype_row)) {
                $ctlArr = \think\Db::name('channeltype')
                    ->where('id','NOTIN', [6,8])
                    ->column('ctl_name');
            } else {
                $ctlArr = array();
                foreach($channeltype_row as $key => $val){
                    if (!in_array($val['id'], [6,8])) {
                        $ctlArr[] = $val['ctl_name'];
                    }
                }
            }

            $systemCtl= ['Arctype','Archives'];
            $ctlArr = array_merge($systemCtl, $ctlArr);
            $actArr = ['add','edit','del'];
            if (in_array($controller, $ctlArr) && in_array($action, $actArr)) {
                Session::pause(); // 暂停session，防止session阻塞机制
                sitemap_auto();
                $this->success('更新sitemap成功！');
            }
        }

        $this->error('更新sitemap失败！');
    }

    // 开启\关闭余额支付
    public function BalancePayOpen()
    {
        if (IS_AJAX_POST) {
            $open_value = input('post.open_value/d');
            getUsersConfigData('pay', ['pay_balance_open' => $open_value]);
            $this->success('操作成功');
        }
    }

    /**
     * 跳转到前台内容页
     * @return [type] [description]
     */
    public function toHomeView()
    {
        $aid = input('param.aid/d');
        $archives = Db::name('archives')->alias('a')
            ->field('b.*, a.*')
            ->join('arctype b', 'a.typeid = b.id', 'LEFT')
            ->where(['a.aid'=>$aid])
            ->find();
        if (!empty($archives)) {
            if ($archives['arcrank'] >= 0) {
                $url = get_arcurl($archives, false);
            } else {
                $url = get_arcurl($archives, true);
            }
            header('Location: '.$url);
            exit;
        } else {
            abort(404);
        }
    }

    //处理多语言字段绑定兼容
    public function repair_language_data()
    {
        $admin_logic_1692067658 = tpSetting('syn.admin_logic_1692067658', [], 'cn');
        if (empty($admin_logic_1692067658)) {
            //判断有没有除了中文以外的
            $lang_count = Db::name('language')->where('mark','neq','cn')->count();
            if (!empty($lang_count)) {
                //有则执行兼容
                $channeltypeRow = Db::name('archives')
                    ->alias('a')
                    ->join('channeltype b','a.channel = b.id','left')
                    ->where(['a.lang' => 'cn', 'a.is_del' => 0, 'a.channel' => ['neq', 6]])
                    ->group('channel')
                    ->field('a.channel,b.id,b.nid,b.table')->getAllWithIndex('id');
                if (!empty($channeltypeRow)) {
                    $typeids_row = [];
                    $row = Db::name('archives')->field('typeid,channel')->where(['lang' => 'cn', 'is_del' => 0])->group('typeid')->select();
                    foreach ($row as $key => $val) {
                        $typeids_row[$val['channel']][] = $val['typeid'];
                    }
                    foreach ($channeltypeRow as $k => $v) {
                        $typeids = empty($typeids_row[$k]) ? [] : $typeids_row[$k];
                        $typeids_arr = [];
                        foreach ($typeids as $key => $val) {
                            $typeids_arr[] = 'tid' . $val;
                        }
                        //查出对应的多语言栏目字段
                        $lang_typeids = Db::name('language_attr')->where(['attr_group' => 'arctype', 'attr_name' => ['in', $typeids_arr], 'lang' => ['neq', 'cn']])->field('attr_value,attr_name')->getAllWithIndex('attr_name');
                        //查询栏目绑定
                        $channelfield_bind_row = Db::name('channelfield_bind')->field('*')->select();
                        $new_arr = array();
                        $bind_field_ids_arr = [];
                        foreach ($channelfield_bind_row as $k => $v) {
                            $new_arr[$v['typeid']][] = $v;
                            $bind_field_ids_arr[$v['typeid']][] = $v['field_id'];
                        }
                        $channelfield_bind_row = $new_arr;
                        foreach ($typeids as $key => $val) {
                            // 中文栏目ID
                            $typeid_old = $val;
                            //多语言栏目id
                            $typeid = $lang_typeids['tid' . $typeid_old]['attr_value'];

                            //查询栏目绑定
                            $channelfield_bind_list = empty($channelfield_bind_row[$typeid_old]) ? [] : $channelfield_bind_row[$typeid_old];
                            if (!empty($channelfield_bind_list)) {
                                $field_ids = get_arr_column($channelfield_bind_list, 'field_id');
                                //查询已经写入的
                                $bind_field_ids = [];
                                if (!empty($bind_field_ids_arr[$typeid])) {
                                    foreach ($bind_field_ids_arr[$typeid] as $_k1 => $_v1) {
                                        if (in_array($_v1, $field_ids)) {
                                            $bind_field_ids[] = $_v1;
                                        }
                                    }
                                }
                                $channelfield_bind_insert = [];
                                foreach ($channelfield_bind_list as $k => $v) {
                                    //已经写入的不在写入
                                    if (!in_array($v['field_id'], $bind_field_ids)) {
                                        $channelfield_bind_insert[] = [
                                            'typeid' => $typeid,
                                            'field_id' => $v['field_id'],
                                            'add_time' => getTime(),
                                            'update_time' => getTime()
                                        ];
                                    }
                                }
                                //写入绑定自定义字段
                                !empty($channelfield_bind_insert) && Db::name('channelfield_bind')->insertAll($channelfield_bind_insert);
                            }
                        }
                    }
                }
            }
            tpSetting('syn', ['admin_logic_1692067658'=>1], 'cn');
        }
    }

    public function get_ip_city_name()
    {
        $ip = input('param.ip/s');
        $city_name = '';
        $city_arr = getCityLocation($ip);
        if (!empty($city_arr)) {
            !empty($city_arr['location']) && $city_name .= $city_arr['location'];
        }
        $this->success('读取成功', null, ['city_name'=>$city_name]);
    }
}