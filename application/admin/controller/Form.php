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
 * Date: 2023-3-8
 */
namespace app\admin\controller;

use think\Page;
use think\Db;
use think\Cache;
use app\admin\logic\FormLogic;

class Form extends Base
{
    // 表单类型
    public $attrInputTypeArr = array();

    public function _initialize()
    {
        parent::_initialize();
        $this->attrInputTypeArr = config('global.guestbook_attr_input_type');
        // 数据表
        $this->form_db = Db::name('form');
        // 业务层
        $this->formLogic = new FormLogic;
        // 模型层
        $this->form_model = model('Form');
    }

    /**
     * 留言列表 - 栏目关联、表单的留言
     */
    public function index()
    {
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $param    = input('param.');
        $typeid = $param['typeid'] = empty($param['typeid']) ? '' : intval($param['typeid']);
        $form_type = $param['form_type'] = empty($param['form_type']) ? '' : intval($param['form_type']);
        $source = $param['source'] = empty($param['source']) ? 1 : intval($param['source']);
        $count_type = empty($param['count_type']) ? 'all' : $param['count_type'];
        $begin    = strtotime(input('param.add_time_begin/s'));
        $end    = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end    = strtotime($end);

        // 应用搜索条件
        foreach (['keywords', 'typeid', 'count_type','source'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $attr_row           = Db::name('guestbook_attr')->field('aid')->where(array('attr_value' => array('LIKE', "%{$param[$key]}%")))->group('aid')->getAllWithIndex('aid');
                    $aids               = array_keys($attr_row);
                    $condition['a.aid'] = array('IN', $aids);
                } else if ($key == 'count_type') {
                    if ('unread' == $count_type) {
                        $condition['a.is_read'] = 0;
                    } else if ('read' == $count_type) {
                        $condition['a.is_read'] = 1;
                    } else if ('star' == $count_type) {
                        $condition['a.is_star'] = 1;
                    }
                } else {
                    $condition['a.' . $key] = array('eq', $param[$key]);
                }
            }
        }

        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between',"$begin,$end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        if (empty($typeid)) {
            /*权限控制 by 小虎哥*/
            $admin_info = session('admin_info');
            if (0 < intval($admin_info['role_id'])) {
                $auth_role_info = $admin_info['auth_role_info'];
                if(! empty($auth_role_info)){
                    $guestbookTypeids = [];
                    $permission_arctype = !empty($auth_role_info['permission']['arctype']) ? $auth_role_info['permission']['arctype'] : [];
                    if(!empty($permission_arctype)){
                        $typeids_tmp = Db::name('arctype')->where(['current_channel'=>8,'lang'=>$this->admin_lang])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->column('id');
                        $typeids_tmp = !empty($typeids_tmp) ? $typeids_tmp : [];
                        $typeids_tmp1 = array_intersect($typeids_tmp, $auth_role_info['permission']['arctype']);
                        if (!empty($typeids_tmp1)) {
                            $guestbookTypeids = implode(',', $typeids_tmp1);
                            $rawstr = " (a.typeid IN ({$guestbookTypeids}) AND a.form_type = 0) ";
                            $formids_tmp1 = Db::name('form')->where(['lang'=>$this->admin_lang])->cache(true, EYOUCMS_CACHE_TIME, 'form')->column('form_id');
                            if (!empty($formids_tmp1)) {
                                $formids_tmp2 = implode(',', $formids_tmp1);
                                $rawstr .= " OR (a.typeid IN ({$formids_tmp2}) AND a.form_type = 1) ";
                            }
                            $condition[] = Db::raw("({$rawstr})");
                        }
                    }
                    if (empty($guestbookTypeids)) {
                        $condition['a.form_type'] = 1;
                    }
                }
            }
            /*--end*/
        }

        // 多语言
        $condition['a.lang'] = array('eq', $this->admin_lang);

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = Db::name('guestbook')->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $Page  = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list  = Db::name('guestbook')
            ->field("a.*")
            ->alias('a')
            ->where($condition)
            ->order('a.is_read asc, a.add_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        $aids = $typeids = $formids = [];
        if ($list) {
            foreach ($list as $key => $val) {
                $aids[] = $val['aid'];
                if (1 == $val['form_type']) {
                    $formids[] = $val['typeid'];
                } else {
                    $typeids[] = $val['typeid'];
                }
            }

            $where = [
                'b.aid'     => ['IN', $aids],
                'a.is_showlist' => 1,
                'a.is_del'  => 0,
            ];
            $row       = Db::name('guestbook_attribute')
                ->field('a.attr_name, a.typeid, b.attr_value, b.aid, b.attr_id,a.attr_input_type')
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
                }else if(in_array($val['attr_input_type'], [5,11])){
                    $val['attr_value'] = str_replace(['|',PHP_EOL], ',', $val['attr_value']);
                    $attr_values = explode(',', $val['attr_value']);
                    foreach ($attr_values as $_k => $_v) {
                        $_v = handle_subdir_pic($_v);
                        $_v = "<i class='fa fa-picture-o color_z curpoin' onclick=\"Images('{$_v}', 900, 600);\"></i>";
                        $attr_values[$_k] = $_v;
                    }
                    $val['attr_value'] = implode('&nbsp;', $attr_values);
                }else if(8 == $val['attr_input_type']){
                    $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                    $val['attr_value'] = "<img src='{$this->root_dir}/public/static/admin/images/addon.gif' width='14' /><a href='{$val['attr_value']}' target='_blank'>下载附件</a>";
                }
                $attr_list[$val['aid']][] = $val;
            }
            $formList = Db::name('form')->field('form_id,form_name,open_reply,open_examine')->where(['form_id'=>['IN',$formids]])->getAllWithIndex('form_id');
            $arctypeList = Db::name('arctype')->field('id,typename')->where(['id'=>['IN',$typeids]])->getAllWithIndex('id');
            foreach ($list as $key => $val) {
                $val['open_reply'] = !empty($formList[$val['typeid']]['open_reply']) ? $formList[$val['typeid']]['open_reply'] : 0;
                $val['open_examine'] = !empty($formList[$val['typeid']]['open_examine']) ? $formList[$val['typeid']]['open_examine'] : 0;
                if (1 == $val['form_type']) {
                    $val['form_name'] = empty($formList[$val['typeid']]) ? '' : $formList[$val['typeid']]['form_name'];
                } else {
                    $val['form_name'] = empty($arctypeList[$val['typeid']]) ? '' : $arctypeList[$val['typeid']]['typename'];
                }
                $val['attr_list'] = isset($attr_list[$val['aid']]) ? $attr_list[$val['aid']] : array();
                $list[$key] = $val;
            }
        }
        $typeids_arr = array_merge($typeids, $formids);
        $tab_list = Db::name('guestbook_attribute')->where([
                'typeid' => ['IN', $typeids_arr],
                'is_showlist' => 1,
                'is_del'    => 0,
            ])->order('typeid asc, sort_order asc, attr_id asc')->select();
        $tab_list = group_same_key($tab_list, 'typeid');
        $assign_data['tab_list']    = $tab_list;
        $show                    = $Page->show(); // 分页显示输出
        $assign_data['page']     = $show; // 赋值分页输出
        $assign_data['list']     = $list; // 赋值数据集
        $assign_data['pager']    = $Page; // 赋值分页对象
        $assign_data['typeid'] = $typeid; // 栏目/表单的ID
        $assign_data['form_type'] = $form_type;
        $assign_data['count_type'] = $count_type;
        $assign_data['source'] = $source;
        $assign_data['iframe'] = input('param.iframe/d', 0);

        //计算留言数量
        $gbCountList = [
            'all' => [
                'type'    => 'all',
                'name'    => '全部',
                'count'   => 0,
            ],
        ];
        if (isset($condition['a.is_read'])) {
            unset($condition['a.is_read']);
        }
        if (isset($condition['a.is_star'])) {
            unset($condition['a.is_star']);
        }
        $condition['lang'] = $this->admin_lang;
        $gbCountRow = Db::name('guestbook')->alias('a')->field('is_read,count(aid) as num')->where($condition)->group('is_read')->order('is_read asc')->select();
        if (empty($gbCountRow)) {
            $gbCountRow = [
                ['is_read'=>0,'num'=>0],
                ['is_read'=>1,'num'=>0],
            ];
        }
        foreach ($gbCountRow as $key => $val) {
            $type = 'unread';
            $name = '未读';
            if (!empty($val['is_read'])) {
                $type = 'read';
                $name = '已读';
            }
            $gbCountList[$type] = [
                'type'    => $type,
                'name'    => $name,
                'count'   => $val['num'],
            ];
            $gbCountList['all']['count'] += $val['num'];
        }
        if (isset($condition['a.is_read'])) {
            unset($condition['a.is_read']);
        }
        $condition['is_star'] = 1;
        $star_num = Db::name('guestbook')->alias('a')->where($condition)->count();
        $gbCountList['star'] = [
            'type'    => 'star',
            'name'    => '星标',
            'count'   => intval($star_num),
        ];
        $assign_data['gbCountList'] = $gbCountList;

        // 获取留言类型(系统留言、可视化百度小程序留言、可视化微信小程序留言)
        $gbTypeList = $this->getGbTypeList(1);
        $assign_data['gbTypeList'] = $gbTypeList;

        // 加载数据
        $this->assign($assign_data);

        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);
        // 如果安装手机端后台管理插件并且在手机端访问时执行
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            $mbPage = input('param.p/d', 1);
            $nullShow = intval($Page->totalPages) === intval($mbPage) ? 1 : 0;
            $this->assign('nullShow', $nullShow);
            if ($mbPage >= 2) {
                return $this->display('form/form_list');
            } else {
                return $this->display('form/index');
            }
        } else {
            return $this->fetch();
        }
    }

    /*可视化百度小程序*/
    // 可视化百度小程序留言列表
    public function baidu_diyminipro_index()
    {
        // 查询当前小程序 mini_id
        $result = $this->getBaiduDiyminiproInfo();

        // 查询条件
        $where = [
            'a.lang' => $result['lang'],
            'a.mini_id' => $result['mini_id']
        ];

        // 全部、已读、未读查询条件
        $count_type = input('param.count_type/s', 'all');
        if ('unread' == $count_type) {
            $where['a.is_read'] = 0;
        } else if ('read' == $count_type) {
            $where['a.is_read'] = 1;
        }
        $assign_data['count_type'] = $count_type;

        // 模糊查询
        $keywords = input('param.keywords/s', 'all');
        if (!empty($keywords)) {
            $list_ids = Db::name('weapp_bd_diyminipro_form_value')->where(['field_value' => ['LIKE', "%{$keywords}%"]])->column('list_id');
            if (!empty($list_ids)) $where['a.list_id'] = ['IN', array_unique($list_ids)];
        }

        // 如果有表单ID则指定查询
        $form_id = input('param.form_id/d', 0);
        if (!empty($form_id)) $where['a.form_id'] = $form_id;

        // 分页查询
        $count = Db::name('weapp_bd_diyminipro_form_list')->alias('a')->where($where)->join('weapp_bd_diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = Db::name('weapp_bd_diyminipro_form_list')
            ->field('a.*, b.form_name')
            ->alias('a')
            ->where($where)
            ->join('weapp_bd_diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')
            ->order('a.update_time desc, a.list_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        // 内容查询
        $result = Db::name('weapp_bd_diyminipro_form_field')
            ->field('b.*, a.field_name')
            ->alias('a')
            ->join('weapp_bd_diyminipro_form_value b', 'b.field_id = a.field_id', 'LEFT')
            ->where(['b.list_id' => ['IN', get_arr_column($list, 'list_id')]])
            ->order('b.value_id asc')
            ->select();
        $field_list = [];
        foreach ($result as $key => $value) {
            if ('checkbox' == $value['field_type'] && !empty($value['field_value'])) {
                $value['field_value'] = str_replace(',', '] [', '['.$value['field_value'].']');
            } else if ('datetime' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = date('Y-m-d H:i:s', $value['field_value']);
            } else if ('location' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = htmlspecialchars_decode($value['field_value']);
                $location_value = json_decode($value['field_value'], true);
                $value['field_value'] = $location_value['address']. $location_value['number'];
            }
            $field_list[$value['list_id']][] = $value;
        }

        // 数据处理
        foreach ($list as $key => $value) {
            $value['field_list'] = isset($field_list[$value['list_id']]) ? $field_list[$value['list_id']] : [];
            $list[$key] = $value;
        }

        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['page'] = $Page->show();

        // 查询所有留言
        unset($where['a.is_read']);
        $formCountRow = Db::name('weapp_bd_diyminipro_form_list')->alias('a')->where($where)->select();
        // 计算(全部、已读、未读)留言数量
        $all = $read = $unread = 0;
        foreach ($formCountRow as $value) {
            $all++;
            if (!empty($value['is_read'])) {
                $read++;
            } else {
                $unread++;
            }
        }
        $formCountList = [
            'all' => [
                'type'  => 'all',
                'name'  => '全部',
                'count' => intval($all),
            ],
            'read' => [
                'type'  => 'read',
                'name'  => '已读',
                'count' => intval($read),
            ],
            'unread' => [
                'type'  => 'unread',
                'name'  => '未读',
                'count' => intval($unread),
            ],
        ];
        $assign_data['formCountList'] = $formCountList;

        // 获取留言类型(系统留言、可视化百度小程序留言、可视化微信小程序留言)
        $gbTypeList = $this->getGbTypeList(2);
        $assign_data['gbTypeList'] = $gbTypeList;

        // 加载数据
        $this->assign($assign_data);
        return $this->fetch();
    }

    // 可视化百度小程序留言详情
    public function baidu_diyminipro_details()
    {
        $list_id = input('list_id/d', 0);
        $form_id = input('form_id/d', 0);
        if (empty($list_id) || empty($form_id)) $this->error('参数有误');

        // 查询当前小程序 mini_id
        $result = $this->getBaiduDiyminiproInfo();

        // 查询条件
        $where = [
            'a.list_id' => $list_id,
            'a.form_id' => $form_id,
            'a.lang' => $result['lang'],
            'a.mini_id' => $result['mini_id']
        ];

        // 更新为已读
        $update = [
            'is_read' => 1,
            'update_time' => getTime()
        ];
        Db::name('weapp_bd_diyminipro_form_list')->alias('a')->where($where)->update($update);

        // 执行查询
        $info = Db::name('weapp_bd_diyminipro_form_list')
            ->field('a.*, b.form_name')
            ->alias('a')
            ->where($where)
            ->join('weapp_bd_diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')
            ->find();
        $assign_data['info'] = $info;

        // 执行查询
        $value_list = Db::name('weapp_bd_diyminipro_form_value')
            ->field('a.*, b.field_name')
            ->alias('a')
            ->where($where)
            ->join('weapp_bd_diyminipro_form_field b', 'a.field_id = b.field_id', 'LEFT')
            ->select();
        foreach ($value_list as $key => $value) {
            if ('checkbox' == $value['field_type'] && !empty($value['field_value'])) {
                $value_list[$key]['field_value'] = str_replace(',', '] [', '['.$value['field_value'].']');
            } else if ('datetime' == $value['field_type'] && !empty($value['field_value'])){
                $value_list[$key]['field_value'] = date('Y-m-d H:i:s', $value['field_value']);
            } else if ('location' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = htmlspecialchars_decode($value['field_value']);
                $location_value = json_decode($value['field_value'], true);
                $value_list[$key]['field_value'] = $location_value['address']. $location_value['number'];
            }
        }
        $assign_data['value_list'] = $value_list;

        $this->assign($assign_data);
        return $this->fetch();
    }

    // 删除可视化百度小程序留言
    public function baidu_diyminipro_del()
    {
        if (IS_AJAX_POST) {
            $list_id = input('post.list_id/d', 0);
            if (empty($list_id)) $this->error('参数有误');

            // 查询当前小程序 mini_id
            $result = $this->getBaiduDiyminiproInfo();

            // 执行条件
            $where = [
                'list_id' => $list_id,
                'lang' => $result['lang'],
                'mini_id' => $result['mini_id']
            ];

            // 删除表单列表数据
            $resultID = Db::name('weapp_bd_diyminipro_form_list')->where($where)->delete(true);
            if (!empty($resultID)) {
                // 同步删除表单下的字段
                Db::name('weapp_bd_diyminipro_form_value')->where($where)->delete(true);
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
    }

    // 查询 查询可视化百度小程序 当前使用的 mini_id
    private function getBaiduDiyminiproInfo()
    {
        return Db::name('weapp_bd_diyminipro')->where(['is_del'=> 0])->order('mini_id desc')->find();
    }
    /*end*/

    /*可视化微信小程序*/
    // 可视化微信小程序留言列表
    public function wechat_diyminipro_index()
    {
        // 查询当前小程序 mini_id
        $result = $this->getWechatDiyminiproInfo();

        // 查询条件
        $where = [
            'a.lang' => $result['lang'],
            'a.mini_id' => $result['mini_id']
        ];

        // 全部、已读、未读查询条件
        $count_type = input('param.count_type/s', 'all');
        if ('unread' == $count_type) {
            $where['a.is_read'] = 0;
        } else if ('read' == $count_type) {
            $where['a.is_read'] = 1;
        }
        $assign_data['count_type'] = $count_type;

        // 模糊查询
        $keywords = input('param.keywords/s', 'all');
        if (!empty($keywords)) {
            $list_ids = Db::name('diyminipro_form_value')->where(['field_value' => ['LIKE', "%{$keywords}%"]])->column('list_id');
            if (!empty($list_ids)) $where['a.list_id'] = ['IN', array_unique($list_ids)];
        }

        // 如果有表单ID则指定查询
        $form_id = input('param.form_id/d', 0);
        if (!empty($form_id)) $where['a.form_id'] = $form_id;

        // 分页查询
        $count = Db::name('diyminipro_form_list')->alias('a')->where($where)->join('diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')->count();
        $Page = new Page($count, config('paginate.list_rows'));

        // 数据查询
        $list = Db::name('diyminipro_form_list')
            ->field('a.*, b.form_name')
            ->alias('a')
            ->where($where)
            ->join('diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')
            ->order('a.update_time desc, a.list_id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        // 内容查询
        $result = Db::name('weapp_bd_diyminipro_form_field')
            ->field('b.*, a.field_name')
            ->alias('a')
            ->join('diyminipro_form_value b', 'b.field_id = a.field_id', 'LEFT')
            ->where(['b.list_id' => ['IN', get_arr_column($list, 'list_id')]])
            ->order('b.value_id asc')
            ->select();
        $field_list = [];
        foreach ($result as $key => $value) {
            if ('checkbox' == $value['field_type'] && !empty($value['field_value'])) {
                $value['field_value'] = str_replace(',', '] [', '['.$value['field_value'].']');
            } else if ('datetime' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = date('Y-m-d H:i:s', $value['field_value']);
            } else if ('location' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = htmlspecialchars_decode($value['field_value']);
                $location_value = json_decode($value['field_value'], true);
                $value['field_value'] = $location_value['address']. $location_value['number'];
            }
            $field_list[$value['list_id']][] = $value;
        }

        // 数据处理
        foreach ($list as $key => $value) {
            $value['field_list'] = isset($field_list[$value['list_id']]) ? $field_list[$value['list_id']] : [];
            $list[$key] = $value;
        }

        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['page'] = $Page->show();

        // 查询所有留言
        unset($where['a.is_read']);
        $formCountRow = Db::name('diyminipro_form_list')->alias('a')->where($where)->select();
        // 计算(全部、已读、未读)留言数量
        $all = $read = $unread = 0;
        foreach ($formCountRow as $value) {
            $all++;
            if (!empty($value['is_read'])) {
                $read++;
            } else {
                $unread++;
            }
        }
        $formCountList = [
            'all' => [
                'type'  => 'all',
                'name'  => '全部',
                'count' => intval($all),
            ],
            'read' => [
                'type'  => 'read',
                'name'  => '已读',
                'count' => intval($read),
            ],
            'unread' => [
                'type'  => 'unread',
                'name'  => '未读',
                'count' => intval($unread),
            ],
        ];
        $assign_data['formCountList'] = $formCountList;

        // 获取留言类型(系统留言、可视化百度小程序留言、可视化微信小程序留言)
        $gbTypeList = $this->getGbTypeList(3);
        $assign_data['gbTypeList'] = $gbTypeList;

        // 加载数据
        $this->assign($assign_data);
        return $this->fetch();
    }

    // 可视化微信小程序留言详情
    public function wechat_diyminipro_details()
    {
        $list_id = input('list_id/d', 0);
        $form_id = input('form_id/d', 0);
        if (empty($list_id) || empty($form_id)) $this->error('参数有误');

        // 查询当前小程序 mini_id
        $result = $this->getWechatDiyminiproInfo();

        // 查询条件
        $where = [
            'a.list_id' => $list_id,
            'a.form_id' => $form_id,
            'a.lang' => $result['lang'],
            'a.mini_id' => $result['mini_id']
        ];

        // 更新为已读
        $update = [
            'is_read' => 1,
            'update_time' => getTime()
        ];
        Db::name('diyminipro_form_list')->alias('a')->where($where)->update($update);

        // 执行查询
        $info = Db::name('diyminipro_form_list')
            ->field('a.*, b.form_name')
            ->alias('a')
            ->where($where)
            ->join('diyminipro_form b', 'a.form_id = b.form_id', 'LEFT')
            ->find();
        $assign_data['info'] = $info;

        // 执行查询
        $value_list = Db::name('diyminipro_form_value')
            ->field('a.*, b.field_name')
            ->alias('a')
            ->where($where)
            ->join('diyminipro_form_field b', 'a.field_id = b.field_id', 'LEFT')
            ->select();
        foreach ($value_list as $key => $value) {
            if ('checkbox' == $value['field_type'] && !empty($value['field_value'])) {
                $value_list[$key]['field_value'] = str_replace(',', '] [', '['.$value['field_value'].']');
            } else if ('datetime' == $value['field_type'] && !empty($value['field_value'])){
                $value_list[$key]['field_value'] = date('Y-m-d H:i:s', $value['field_value']);
            } else if ('location' == $value['field_type'] && !empty($value['field_value'])){
                $value['field_value'] = htmlspecialchars_decode($value['field_value']);
                $location_value = json_decode($value['field_value'], true);
                $value_list[$key]['field_value'] = $location_value['address']. $location_value['number'];
            }
        }
        $assign_data['value_list'] = $value_list;

        $this->assign($assign_data);
        return $this->fetch();
    }

    // 删除可视化微信小程序留言
    public function wechat_diyminipro_del()
    {
        if (IS_AJAX_POST) {
            $list_id = input('post.list_id/d', 0);
            if (empty($list_id)) $this->error('参数有误');

            // 查询当前小程序 mini_id
            $result = $this->getWechatDiyminiproInfo();

            // 执行条件
            $where = [
                'list_id' => $list_id,
                'lang' => $result['lang'],
                'mini_id' => $result['mini_id']
            ];

            // 删除表单列表数据
            $resultID = Db::name('diyminipro_form_list')->where($where)->delete(true);
            if (!empty($resultID)) {
                // 同步删除表单下的字段
                Db::name('diyminipro_form_value')->where($where)->delete(true);
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
    }

    // 查询 可视化微信小程序 当前使用的 mini_id
    private function getWechatDiyminiproInfo()
    {
        return Db::name('diyminipro')->where(['is_del'=> 0])->order('mini_id desc')->find();
    }
    /*end*/

    // 获取留言类型(系统留言、可视化百度小程序留言、可视化微信小程序留言)
    private function getGbTypeList($type = 1)
    {
        $source = input('param.source/d', 1);
        // 系统留言
        $result['pc'] = [
            'url' => url('Form/index', ['source'=>1]),
            'name' => '电脑端',
            'count' => Db::name('guestbook')->where(['source'=>1, 'lang'=>$this->admin_lang])->count(),
            'class' => 1 === intval($type) && 1 === intval($source) ? 'cur' : '',
        ];
        $result['mobile'] = [
            'url' => url('Form/index', ['source'=>2]),
            'name' => '手机端',
            'count' => Db::name('guestbook')->where(['source'=>2, 'lang'=>$this->admin_lang])->count(),
            'class' => 1 === intval($type) && 2 === intval($source) ? 'cur' : '',
        ];

        // 如果安装了可视化百度小程序插件则执行
        if (is_dir('./weapp/BdDiyminipro/')) {
            // 开启可视化百度小程序插件则执行
            $data = model('Weapp')->getWeappList('BdDiyminipro');
            if (!empty($data['status']) && 1 == $data['status']) {
                $result['baidu'] = [
                    'url' => url('Form/baidu_diyminipro_index'),
                    'name' => '可视化百度小程序',
                    'count' => Db::name('weapp_bd_diyminipro_form_list')->count(),
                    'class' => 2 === intval($type) ? 'cur' : '',
                ];
            }
        }

        // 如果安装了可视化微信小程序插件则执行
        if (is_dir('./weapp/Diyminipro/')) {
            // 开启可视化微信小程序插件则执行
            $data = model('Weapp')->getWeappList('Diyminipro');
            if (!empty($data['status']) && 1 == $data['status']) {
                $result['wechat'] = [
                    'url' => url('Form/wechat_diyminipro_index'),
                    'name' => '可视化微信小程序',
                    'count' => Db::name('diyminipro_form_list')->count(),
                    'class' => 3 === intval($type) ? 'cur' : '',
                ];
            }
        }

        // 如果安装了可视化微信小程序插件则执行
        if (is_dir('./weapp/Suibian/')) {
            // 开启可视化微信小程序插件则执行
            $data = model('Weapp')->getWeappList('Suibian');
            if (!empty($data['status']) && 1 == $data['status']) {
                $result['uniapp'] = [
                    'url' => url('Form/index', ['source'=>3]),
                    'name' => '开源小程序',
                    'count' => Db::name('guestbook')->where(['source'=>3, 'lang'=>$this->admin_lang])->count(),
                    'class' => 1 === intval($type) && 3 === intval($source) ? 'cur' : '',
                ];
            }
        }

        return $result;
    }

    public function field()
    {
        $assign_data = [];
        // 查询条件
        $condition = [
            'lang' => $this->admin_lang,
        ];
        // 应用搜索条件
        $keywords = input('keywords/s');
        $keywords = trim($keywords);
        if (!empty($keywords)) $condition['form_name'] = array('LIKE', "%{$keywords}%");

        // 分页查询
        $count = $this->form_db->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['pager'] = $Page;

        // 数据查询
        $list = $this->form_db
            ->where($condition)
            ->order('form_id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $assign_data['list'] = $list;
        $form_ids = get_arr_column($list, 'form_id');

        // 查询表单填写数量
        $assign_data['form_list_count'] = $this->form_model->GetFormListCount($form_ids);

        /*多语言模式下，表单ID显示主体语言的ID*/
        $main_form_list = [];
        if ($this->admin_lang != $this->main_lang && empty($this->globalConfig['language_split'])) {
            $attr_values = get_arr_column($list, 'form_id');
            $languageAttrRow = Db::name('language_attr')->field('attr_name,attr_value')->where([
                    'attr_value'    => ['IN', $attr_values],
                    'attr_group'    => 'form',
                    'lang'          => $this->admin_lang,
                ])->getAllWithIndex('attr_value');
            $groupids = [];
            foreach ($languageAttrRow as $key => $val) {
                $gid_tmp = str_replace('form', '', $val['attr_name']);
                array_push($groupids, intval($gid_tmp));
            }
            $main_FormRow = Db::name('form')->field("form_id,CONCAT('form', form_id) AS attr_name")
                ->where([
                    'form_id'    => ['IN', $groupids],
                    'lang'  => $this->main_lang,
                ])->getAllWithIndex('attr_name');
            foreach ($list as $key => $val) {
                $key_tmp = !empty($languageAttrRow[$val['form_id']]['attr_name']) ? $languageAttrRow[$val['form_id']]['attr_name'] : '';
                $main_form_list[$val['form_id']] = [
                    'form_id'        => !empty($main_FormRow[$key_tmp]['form_id']) ? $main_FormRow[$key_tmp]['form_id'] : 0,
                ];
            }
        }
        $this->assign('main_form_list', $main_form_list);
        /*end*/

        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 新增表单
     */
    public function field_add()
    {
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        if (IS_POST) {
            $post = input('post.');
            $post['form_name'] = trim($post['form_name']);
            if (empty($post['form_name'])) {
                $this->error('表单名称不能为空！');
            }
            $data = array(
                'form_name'   => $post['form_name'],
                'intro'       => '',
                'status'      => 1,
                'attr_auto'     => intval($post['attr_auto']),
                'lang'        => $this->admin_lang,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            );
            $insertID = Db::name('form')->insertGetId($data);
            if (!empty($insertID)) {
                // 同步表单ID到多语言的模板变量里，添加多语言表单
                $this->formLogic->syn_add_language_form($insertID);

                Cache::clear('form');
                adminLog('新增表单：'.$data['form_name']);
                $this->success("操作成功", url('Form/field'));
            }
            $this->error("操作失败", url('Form/field'));
        }

        return $this->fetch();
    }
    
    /**
     * 编辑表单
     */
    public function field_edit()
    {
        if (IS_POST) {
            $post = input('post.');
            $post['form_id'] = intval($post['form_id']);
            if (!empty($post['form_id'])) {
                $post['form_name'] = trim($post['form_name']);
                if (empty($post['form_name'])) {
                    $this->error('表单名称不能为空！');
                }
                $data = array(
                    'form_name'     => $post['form_name'],
                    'attr_auto'     => intval($post['attr_auto']),
                    'open_reply'         => intval($post['open_reply']),
                    'open_examine'         => intval($post['open_examine']),
                    'update_time'   => getTime(),
                );
                $resultID = Db::name('form')->where(['form_id'=>$post['form_id']])->cache(true,null,'form')->update($data);
                if ($resultID !== false) {
                    adminLog('编辑表单：'.$data['form_name']);
                    $this->success("操作成功", url('Form/field'));
                }
            }
            $this->error("操作失败", url('Form/field'));
        }

        $id = input('id/d');
        $info = Db::name('form')->where([
                'form_id'    => $id,
            ])->find();
        if (empty($info)) {
            $this->error('表单不存在，请联系管理员！');
            exit;
        }

        $assign_data = array();
        $assign_data['info'] = $info;
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除表单
     */
    public function field_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (IS_POST && !empty($id_arr)) {
            /*多语言*/
            $attr_name_arr = [];
            foreach ($id_arr as $key => $val) {
                $attr_name_arr[] = 'form'.$val;
            }
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $new_id_arr = Db::name('language_attr')->where([
                        'attr_name' => ['IN', $attr_name_arr],
                        'attr_group'    => 'form',
                    ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            /*--end*/

            $form_name_list = $this->form_db->where([
                    'form_id'    => ['IN', $id_arr],
                ])->column('form_name');

            $r = $this->form_db->where([
                    'form_id'    => ['IN', $id_arr],
                ])->delete();
            if($r !== false){
                $aid_arr = Db::name('guestbook')->where([
                        'typeid'    => ['IN', $id_arr],
                        'form_type' => 1,
                    ])->column('aid');
                if (!empty($aid_arr)) {
                    Db::name('guestbook')->where([
                            'aid'    => ['IN', $aid_arr],
                        ])->delete();
                    Db::name('guestbook_attr')->where([
                            'aid'    => ['IN', $aid_arr],
                        ])->delete();
                    Db::name('guestbook_attribute')->where([
                            'typeid'    => ['IN', $id_arr],
                            'form_type' => 1,
                        ])->delete();
                }
                Cache::clear('form');
                Cache::clear('guestbook');
                adminLog('删除表单：'.implode(',', $form_name_list));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    //留言表单列表
    public function attribute_index()
    {
        $assign_data = array();
        $condition = array();
        $get = input('get.');
        $typeid = input('typeid/d');
        foreach (['keywords','typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$get[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $get[$key]);
                }
            }
        }

        $condition['a.form_type'] = 1;
        $condition['b.form_id'] = ['gt', 0];
        $condition['a.is_del'] = 0;
        $condition['a.lang'] = $this->admin_lang;

        $count = Db::name('guestbook_attribute')->alias('a')
            ->join('__FORM__ b', 'a.typeid = b.form_id', 'LEFT')
            ->where($condition)
            ->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = Db::name('guestbook_attribute')
            ->field("a.attr_id")
            ->alias('a')
            ->join('__FORM__ b', 'a.typeid = b.form_id', 'LEFT')
            ->where($condition)
            ->order('a.typeid desc, a.sort_order asc, a.attr_id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->getAllWithIndex('attr_id');

        if ($list) {
            $attr_ida = array_keys($list);
            $fields = "b.*, a.*,a.attr_id as orgin_attr_id";
            $row = Db::name('guestbook_attribute')
                ->field($fields)
                ->alias('a')
                ->join('__FORM__ b', 'a.typeid = b.form_id', 'LEFT')
                ->where('a.attr_id', 'in', $attr_ida)
                ->getAllWithIndex('attr_id');
            $row = model('LanguageAttr')->getBindValue($row, 'form_attribute', $this->main_lang); // 获取多语言关联绑定的值
            foreach ($row as $key => $val) {
                $val['fieldname'] = 'attr_'.$val['attr_id'];
                $row[$key] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key] = $row[$val['attr_id']];
            }
        }
        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['typeid'] = $typeid;

        // 表单列表
        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 新增表单属性
     */
    public function attribute_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('GuestbookAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values = trim($attr_values);

            /*过滤重复值*/
            $attr_values_arr = explode(PHP_EOL, $attr_values);
            foreach ($attr_values_arr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($attr_values_arr[$key]);
                    continue;
                }
                $attr_values_arr[$key] = $tmp_val;
            }
            $attr_values_arr = array_unique($attr_values_arr);
            $attr_values = implode(PHP_EOL, $attr_values_arr);
            /*end*/
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
            $attr_input_type = isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : 0;

            /*前台输入是否JS验证*/
            $validate_type = 0;
            $validate_type_list = config("global.validate_type_list"); // 前台输入验证类型
            if (!empty($validate_type_list[$attr_input_type])) {
                $validate_type = $attr_input_type;
            }
            /*end*/
            if (9 == $post_data['attr_input_type']) {
                if (!empty($post_data['region_data'])) {
                    $post_data['attr_values']     = serialize($post_data['region_data']);
                } else {
                    $this->error("请选择区域范围！");
                }
            }
            $savedata = array(
                'attr_name'       => $post_data['attr_name'],
                'typeid'          => $post_data['typeid'],
                'form_type'       => 1,
                'attr_input_type' => $attr_input_type,
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'is_showlist'     => $post_data['is_showlist'],
                'required'        => $post_data['required'],
                'real_validate'   => $post_data['real_validate'],
                'validate_type'   => $validate_type,
                'sort_order'      => 100,
                'lang'            => $this->admin_lang,
                'add_time'        => getTime(),
                'update_time'     => getTime(),
            );

            // 如果是添加手机号码类型则执行
            if (!empty($savedata['typeid']) && 6 === intval($savedata['attr_input_type']) && 1 === intval($savedata['real_validate'])) {
                // 查询是否已添加需要真实验证的手机号码类型
                $where = [
                    'typeid' => $savedata['typeid'],
                    'form_type' => 1,
                    'real_validate' => $savedata['real_validate'],
                    'attr_input_type' => $savedata['attr_input_type']
                ];
                $realValidate = $model->get($where);
                if (!empty($realValidate)) $this->error('只能设置一个需要真实验证的手机号码类型');
            }

            // 数据验证            
            $validate = \think\Loader::validate('GuestbookAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata,true); // 收集数据
                $model->save(); // 写入数据到数据库
                $insertId = $model->getLastInsID();

                // 同步表单ID到多语言的模板变量里，添加多语言表单
                $this->formLogic->syn_add_language_attribute($insertId);

                $return_arr = array(
                     'status' => 1,
                     'msg'   => '操作成功',                        
                     'data'  => array('url'=>url('Form/attribute_index', array('typeid'=>$post_data['typeid']))),
                );
                adminLog('新增表单：'.$savedata['attr_name']);
                respose($return_arr);
            }
        }

        $typeid = input('param.typeid/d', 0);
        if ($typeid > 0) {
            $formdata = $this->form_db->where(['form_id'=>$typeid])->find();
        } else {
            $formdata = $this->form_db->where(['lang'=>$this->admin_lang,'status'=>1])->select();
        }
        $assign_data['formdata'] = $formdata; //
        $assign_data['typeid']      = $typeid; // 表单ID

        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        //区域
        $China[]                 = [
            'id'   => 0,
            'name' => '全国',
        ];
        $Province                = get_province_list();
        $assign_data['Province'] = array_merge($China, $Province);
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 编辑表单属性
     */
    public function attribute_edit()
    {
        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('GuestbookAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values = trim($attr_values);

            /*过滤重复值*/
            $attr_values_arr = explode(PHP_EOL, $attr_values);
            foreach ($attr_values_arr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($attr_values_arr[$key]);
                    continue;
                }
                $attr_values_arr[$key] = $tmp_val;
            }
            $attr_values_arr = array_unique($attr_values_arr);
            $attr_values = implode(PHP_EOL, $attr_values_arr);
            /*end*/
            
            $post_data = input('post.');
            $post_data['attr_id'] = intval($post_data['attr_id']);
            $post_data['attr_values'] = $attr_values;
            $attr_input_type = isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : 0;

            /*前台输入是否JS验证*/
            $validate_type = 0;
            $validate_type_list = config("global.validate_type_list"); // 前台输入验证类型
            if (!empty($validate_type_list[$attr_input_type])) {
                $validate_type = $attr_input_type;
            }
            /*end*/
            if (9 == $post_data['attr_input_type']) {
                if (!empty($post_data['region_data'])) {
                    $post_data['attr_values']     = serialize($post_data['region_data']);
                } else {
                    $this->error("请选择区域范围！");
                }
            }
            $savedata = array(
                'attr_id'         => $post_data['attr_id'],
                'attr_name'       => $post_data['attr_name'],
                'typeid'          => $post_data['typeid'],
                'form_type'       => 1,
                'attr_input_type' => $attr_input_type,
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'is_showlist'     => $post_data['is_showlist'],
                'required'        => $post_data['required'],
                'real_validate'   => $post_data['real_validate'],
                'validate_type'   => $validate_type,
                'sort_order'      => 100,
                'update_time'     => getTime(),
            );

            // 如果是添加手机号码类型则执行
            if (!empty($savedata['typeid']) && 6 === intval($savedata['attr_input_type']) && 1 === intval($savedata['real_validate'])) {
                // 查询是否已添加需要真实验证的手机号码类型
                $where = [
                    'typeid' => $savedata['typeid'],
                    'form_type'       => 1,
                    'attr_id' => ['NEQ', $savedata['attr_id']],
                    'real_validate' => $savedata['real_validate'],
                    'attr_input_type' => $savedata['attr_input_type']
                ];
                $realValidate = $model->get($where);
                if (!empty($realValidate)) $this->error('只能设置一个需要真实验证的手机号码类型');
            }
            
            // 数据验证            
            $validate = \think\Loader::validate('GuestbookAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error      = $validate->getError();
                $error_msg  = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata, true); // 收集数据
                $model->isUpdate(true, [
                    'attr_id' => $post_data['attr_id'],
                ])->save(); // 写入数据到数据库
                $return_arr = array(
                    'status' => 1,
                    'msg'    => '操作成功',
                    'data'   => array('url' => url('Form/attribute_index', array('typeid' => intval($post_data['typeid'])))),
                );
                adminLog('编辑表单：' . $savedata['attr_name']);
                respose($return_arr);
            }
        }

        $assign_data = array();

        $id = input('id/d');
        /*获取多语言关联绑定的值*/
        $new_id = model('LanguageAttr')->getBindValue($id, 'form_attribute'); // 多语言
        !empty($new_id) && $id = $new_id;
        /*--end*/
        $info = Db::name('GuestbookAttribute')->where([
            'attr_id' => $id,
            'form_type'       => 1,
        ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        $assign_data['field'] = $info;

        // 所在表单
        $formdata                = $this->form_db->where('form_id', $info['typeid'])->find();
        $assign_data['formdata'] = $formdata;

        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        /*区域字段处理*/
        // 初始化参数
        $assign_data['region'] = [
            'parent_id'    => '-1',
            'region_id'    => '-1',
            'region_names' => '',
            'region_ids'   => '',
        ];
        // 定义全国参数
        $China[] = [
            'id'   => 0,
            'name' => '全国',
        ];
        // 查询省份信息并且拼装上$China数组
        $Province                = get_province_list();
        $assign_data['Province'] = array_merge($China, $Province);
        // 区域选择时，指定不出现下级地区列表
        $assign_data['parent_array'] = "[]";
        // 如果是区域类型则执行
        if (9 == $info['attr_input_type']) {
            // 反序列化默认值参数
            if (!preg_match('/^([\x{4e00}-\x{9fa5}\w\"\:\{\}\;\，\,]*)$/u', $info['attr_values'])) {
                $info['attr_values'] = '';
            }
            $dfvalue = unserialize($info['attr_values']);
            if (is_array($dfvalue)) {
                $dfvalue['region_id'] = preg_replace('/([^\d\,]+)/i', '', $dfvalue['region_id']);
                $dfvalue['region_ids'] = preg_replace('/([^\d\,]+)/i', '', $dfvalue['region_ids']);
                $dfvalue['region_names'] = preg_replace("/([^\x{4e00}-\x{9fa5}\,\，]+)/u", '', $dfvalue['region_names']);

                if (0 == $dfvalue['region_id']) {
                    $parent_id = $dfvalue['region_id'];
                } else {
                    // 查询当前选中的区域父级ID
                    $parent_id = Db::name('region')->where("id", $dfvalue['region_id'])->getField('parent_id');
                    if (0 == $parent_id) {
                        $parent_id = $dfvalue['region_id'];
                    }
                }

                // 查询市\区\县信息
                $assign_data['City'] = Db::name('region')->where("parent_id", $parent_id)->select();
                // 加载数据到模板
                $assign_data['region'] = [
                    'parent_id'    => $parent_id,
                    'region_id'    => $dfvalue['region_id'],
                    'region_names' => $dfvalue['region_names'],
                    'region_ids'   => $dfvalue['region_ids'],
                ];
            }

            // 删除默认值,防止切换其他类型时使用到
            unset($info['attr_values']);

            // 区域选择时，指定不出现下级地区列表
            $assign_data['parent_array'] = convert_js_array(config('global.field_region_all_type'));
        }
        /*区域字段处理结束*/
        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 删除表单属性
     */
    public function attribute_del()
    {
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }
        
        $thorough = input('thorough/d');
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            //多语言
            $attr_name_arr = [];
            foreach ($id_arr as $key => $val) {
                $attr_name_arr[] = 'attr_' . $val;
            }
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $new_id_arr = Db::name('language_attr')->where([
                    'attr_name'  => ['IN', $attr_name_arr],
                    'attr_group' => 'form_attribute',
                ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            if (1 == $thorough){//彻底删除
                $r = Db::name('GuestbookAttribute')->where([
                    'attr_id' => ['IN', $id_arr],
                    'form_type' => 1,
                ])->delete();
            }else{
                $r = Db::name('GuestbookAttribute')->where([
                    'attr_id' => ['IN', $id_arr],
                    'form_type' => 1,
                ])->update([
                    'is_del'      => 1,
                    'update_time'   => getTime(),
                ]);
            }
            if($r !== false){
                // 删除多语言表单属性关联绑定
                if (1 == $thorough){//彻底删除
                    if (!empty($attr_name_arr)) {
                        if (get_admin_lang() == get_main_lang()) {
                            Db::name('language_attribute')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'form_attribute',
                                ])->delete();
                        }
                        if (empty($this->globalConfig['language_split'])) {
                            Db::name('language_attr')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'form_attribute',
                                ])->delete();
                        } else {
                            Db::name('language_attr')->where([
                                    'attr_value' => ['IN', $id_arr],
                                    'attr_group'    => 'form_attribute',
                                ])->delete();
                        }
                    }
                }
                /*--end*/
                adminLog('删除表单属性-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    //标签调用
    public function label_call(){
        $form_id = input('form_id/d',0);
        if (!empty($form_id)) {
            $form = model('form')->where(['form_id'=>$form_id])->find();
            $content =<<<EOF
{eyou:form type="auto" formid="{$form['form_id']}" id="field"}
<form method="POST" {\$field.formhidden} action="{\$field.action}" onsubmit="{\$field.submit}">
    {eyou:volist name="\$field.attrlist" id="attr"}
        {\$attr.attr_name}：{\$attr.attr_html}
    {/eyou:volist}
    <div class='eyou_form_attr'>
        <input class="eyou_form_btn" type="submit" value="提交"/>
    </div>
    {\$field.hidden}
</form>
{/eyou:form}
EOF;
            $assign_data = [
                'content' => htmlspecialchars($content)
            ];
            $this->assign($assign_data);

            return $this->fetch();
        }
        $this->error('数据不存在！');
    }

    //回复留言
    public function editReply()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['aid'])) {
                $this->error('缺少必要条件!');
            }
            $post['aid'] = intval($post['aid']);
            $admin_id = session('admin_id');
            $update = [
                'reply' => $post['reply'],
                'admin_id' => $admin_id,
                'update_time' => getTime(),
                'reply_time' => getTime()
            ];
            $r = Db::name('guestbook')->where('aid', $post['aid'])->update($update);
            if ($r !== false) {
                $this->success('留言回复成功!');
            } else {
                $this->error('留言回复失败!');
            }
        }
        $this->error('请求失败!');
    }

    public function to_examine()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['aid'])) {
                $this->error('缺少必要条件!');
            }
            $post['aid'] = intval($post['aid']);
            $update = [
                'examine' => $post['examine'],
                'update_time' => getTime(),
            ];
            $r = Db::name('guestbook')->where('aid', $post['aid'])->update($update);
            if ($r !== false) {
                $this->success('操作成功!');
            } else {
                $this->error('操作失败!');
            }
        }
        $this->error('请求失败!');

    }
}