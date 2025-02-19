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
use app\common\logic\ArctypeLogic;

class Guestbook extends Base
{
    // 模型标识
    public $nid = 'guestbook';
    // 模型ID
    public $channeltype = '';
    // 表单类型
    public $attrInputTypeArr = array();

    public function _initialize()
    {
        parent::_initialize();
        $channeltype_list       = config('global.channeltype_list');
        $this->channeltype      = $channeltype_list[$this->nid];
        $this->attrInputTypeArr = config('global.guestbook_attr_input_type');
    }

    /**
     * 留言列表 - 仅是栏目关联的留言
     */
    public function index()
    {
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $get    = input('get.');
        $typeid = input('typeid/d');
        $begin    = strtotime(input('param.add_time_begin/s'));
        $end    = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end    = strtotime($end);

        // 应用搜索条件
        foreach (['keywords', 'typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $attr_row           = Db::name('guestbook_attr')->field('aid')->where(array('attr_value' => array('LIKE', "%{$get[$key]}%"),'form_type'=>0))->group('aid')->getAllWithIndex('aid');
                    $aids               = array_keys($attr_row);
                    $condition['a.aid'] = array('IN', $aids);
                } else if ($key == 'typeid') {
                    $condition['a.typeid'] = array('eq', $get[$key]);
                } else {
                    $condition['a.' . $key] = array('eq', $get[$key]);
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
                    $is_notaccess = false;
                    $permission_arctype = !empty($auth_role_info['permission']['arctype']) ? $auth_role_info['permission']['arctype'] : [];
                    if(! empty($permission_arctype)){
                        $typeids_tmp = Db::name('arctype')->where(['current_channel'=>8,'lang'=>$this->admin_lang])->cache(true, EYOUCMS_CACHE_TIME, 'arctype')->column('id');
                        $typeids_tmp = !empty($typeids_tmp) ? $typeids_tmp : [];
                        $typeids_tmp2 = array_intersect($typeids_tmp, $auth_role_info['permission']['arctype']);
                        if (!empty($typeids_tmp2)) {
                            $condition['a.typeid'] = ['IN', $typeids_tmp2];
                            $is_notaccess = true;
                        }
                    }
                    if (false === $is_notaccess) {
                        $this->error('您没有操作权限，请联系超级管理员分配权限');
                    }
                }
            }
            /*--end*/
        }

        $condition['a.form_type'] = 0;
        // 多语言
        $condition['a.lang'] = array('eq', $this->admin_lang);

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = Db::name('guestbook')->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $Page  = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list  = Db::name('guestbook')
            ->field("b.*, a.*")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.is_read asc, a.add_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        $typeids = [];
        if ($list) {
            $where = [
                'b.aid'     => ['IN', array_keys($list)],
                'a.is_showlist' => 1,
                'a.lang'    => $this->admin_lang,
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
                $typeids[] = $val['typeid'];
                if (9 == $val['attr_input_type']){
                    //如果是区域类型,转换名称
                    $val['attr_value'] = Db::name('region')->where('id','in',$val['attr_value'])->column('name');
                    $val['attr_value'] = implode('',$val['attr_value']);
                }else if(10 == $val['attr_input_type']){
                    $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
                } else if(11 == $val['attr_input_type']) {
                    $attrValueArr = !empty($val['attr_value']) ? explode(',', $val['attr_value']) : [];
                    $val['attr_value'] = '';
                    foreach ($attrValueArr as $_k => $_v) {
                        if ($_k >= 2) {
                            break;
                        }
                        if (preg_match('/(\.('.tpCache('global.image_type').'))$/i', $_v)) {
                            if (!stristr($_v, '|')) {
                                $_v = handle_subdir_pic($_v);
                                $val['attr_value'] .= "<img src='{$_v}' width='60' height='60' style='float: unset; cursor: pointer;margin: 0px 3px;' onclick=\"Images('{$_v}', 650, 350);\" />";
                            }
                        } elseif (preg_match('/(\.('.tpCache('global.file_type').'))$/i', $_v)) {
                            if (!stristr($_v, '|')) {
                                $_v = handle_subdir_pic($_v);
                                $val['attr_value'] .= "<a href='{$_v}' download='".time()."'><img src=\"".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width: 16px; height: 16px;\">文件下载</a>";
                            }
                        }
                    }
                }
                if (preg_match('/(\.('.tpCache('global.image_type').'))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' onclick=\"Images('{$val['attr_value']}', 650, 350);\" />";
                    }
                }elseif (preg_match('/(\.('.tpCache('global.file_type').'))$/i', $val['attr_value'])){
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $download_name = preg_replace('/^(.*)\/([^\/]+)\.(\w+)$/i', '${2}', $val['attr_value']);
                        $val['attr_value'] = "<a href='{$val['attr_value']}' download='".$download_name."'><img src=\"".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width:16px;height:16px;float:unset;\">&nbsp;点击下载</a>";
                    }
                } else {
                    $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
                }
                $attr_list[$val['aid']][] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key]['attr_list'] = isset($attr_list[$val['aid']]) ? $attr_list[$val['aid']] : array();
            }
        }
        $tab_list = Db::name('guestbook_attribute')->where([
                'typeid' => ['IN', $typeids],
                'form_type'       => 0,
                'is_showlist' => 1,
                'lang'   => $this->admin_lang,
                'is_del'    => 0,
            ])->order('typeid asc, sort_order asc, attr_id asc')->select();
        $tab_list = group_same_key($tab_list, 'typeid');
        if (!empty($typeid)) {
            $tab_list = empty($tab_list[$typeid]) ? [] : $tab_list[$typeid];
        }
        $assign_data['tab_list']    = $tab_list;
        $show                    = $Page->show(); // 分页显示输出
        $assign_data['page']     = $show; // 赋值分页输出
        $assign_data['list']     = $list; // 赋值数据集
        $assign_data['pager']    = $Page; // 赋值分页对象

        // 栏目ID
        $assign_data['typeid'] = $typeid; // 栏目ID
        /*当前栏目信息*/
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        /*--end*/

        /*选项卡*/
        $tab                = input('param.tab/d', 3);
        $assign_data['tab'] = $tab;
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除
     */
    public function del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        $form_type = input('param.form_type/d', 0);
        if (!empty($id_arr)) {
            $r = Db::name('guestbook')->where([
                'aid'  => ['IN', $id_arr],
                'form_type' => $form_type,
                'lang' => $this->admin_lang,
            ])->delete();
            if ($r !== false) {
                // ---------后置操作
                model('Guestbook')->afterDel($id_arr, $form_type);
                model('Arctype')->hand_type_count(['aid'=>$id_arr]);//统计栏目文档数量
                // ---------end
                adminLog('删除留言-id：' . implode(',', $id_arr));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    //留言表单表单列表
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
                } else if ($key == 'typeid') {
                    $typeids = model('Arctype')->getHasChildren($get[$key]);
                    $condition['a.typeid'] = array('IN', array_keys($typeids));
                } else {
                    $condition['a.'.$key] = array('eq', $get[$key]);
                }
            }
        }

        $condition['a.form_type'] = 0;
        $condition['b.id'] = ['gt', 0];
        $condition['a.is_del'] = 0;
        $condition['a.lang'] = $this->admin_lang;

        $count = Db::name('guestbook_attribute')->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = Db::name('guestbook_attribute')
            ->field("a.attr_id")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
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
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.attr_id', 'in', $attr_ida)
                ->getAllWithIndex('attr_id');
            $row = model('LanguageAttr')->getBindValue($row, 'guestbook_attribute', $this->main_lang); // 获取多语言关联绑定的值
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

        //获取当前模型栏目
        $select_html = allow_release_arctype($typeid, array($this->channeltype));
        $typeidNum = substr_count($select_html, '</option>');
        $this->assign('select_html',$select_html);
        $this->assign('typeidNum',$typeidNum);
        $assign_data['typeid'] = $typeid;
        //当前栏目信息
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        $tab                = input('param.tab/d', 3);//选项卡
        $assign_data['tab'] = $tab;
        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 新增留言表单
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
                'form_type'       => 0,
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
                    'form_type'       => 0,
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

                /*同步留言属性ID到多语言的模板变量里*/
                model('GuestbookAttribute')->syn_add_language_attribute($insertId);
                /*--end*/

                $return_arr = array(
                     'status' => 1,
                     'msg'   => '操作成功',                        
                     'data'  => array('url'=>url('Guestbook/attribute_index', array('typeid'=>$post_data['typeid']))),
                );
                adminLog('新增留言表单：'.$savedata['attr_name']);
                respose($return_arr);
            }
        }

        $typeid = input('param.typeid/d', 0);
        if ($typeid > 0) {
            $select_html = Db::name('arctype')->where('id', $typeid)->getField('typename');
            $select_html = !empty($select_html) ? $select_html : '该栏目不存在';
        } else {
            $arctypeLogic      = new ArctypeLogic();
            $map               = array(
                'channeltype' => $this->channeltype,
            );
            $arctype_max_level = intval(config('global.arctype_max_level'));
            $select_html       = $arctypeLogic->arctype_list(0, $typeid, true, $arctype_max_level, $map);
        }
        $assign_data['select_html'] = $select_html; //
        $assign_data['typeid']      = $typeid; // 栏目ID

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
     * 编辑留言表单
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
                'form_type'       => 0,
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
                    'form_type'       => 0,
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
                    'data'   => array('url' => url('Guestbook/attribute_index', array('typeid' => intval($post_data['typeid'])))),
                );
                adminLog('编辑留言表单：' . $savedata['attr_name']);
                respose($return_arr);
            }
        }

        $assign_data = array();

        $id = input('id/d');
        /*获取多语言关联绑定的值*/
        $new_id = model('LanguageAttr')->getBindValue($id, 'guestbook_attribute'); // 多语言
        !empty($new_id) && $id = $new_id;
        /*--end*/
        $info = Db::name('GuestbookAttribute')->where([
            'attr_id' => $id,
            'form_type' => 0,
        ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        $assign_data['field'] = $info;

        // 所在栏目
        $select_html                = Db::name('arctype')->where('id', $info['typeid'])->getField('typename');
        $select_html                = !empty($select_html) ? $select_html : '该栏目不存在';
        $assign_data['select_html'] = $select_html;

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
     * 删除留言表单
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
                        'attr_group' => 'guestbook_attribute',
                    ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            if (1 == $thorough){//彻底删除
                $r = Db::name('GuestbookAttribute')->where([
                    'attr_id' => ['IN', $id_arr],
                    'form_type' => 0,
                ])->delete();
            }else{
                $r = Db::name('GuestbookAttribute')->where([
                    'attr_id' => ['IN', $id_arr],
                    'form_type' => 0,
                ])->update([
                    'is_del'      => 1,
                    'update_time'   => getTime(),
                ]);
            }
            if($r !== false){
                // 删除多语言留言属性关联绑定
                if (1 == $thorough){//彻底删除
                    if (!empty($attr_name_arr)) {
                        if (get_admin_lang() == get_main_lang()) {
                            Db::name('language_attribute')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'guestbook_attribute',
                                ])->delete();
                        }
                        if (empty($this->globalConfig['language_split'])) {
                            Db::name('language_attr')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'guestbook_attribute',
                                ])->delete();
                        } else {
                            Db::name('language_attr')->where([
                                    'attr_value' => ['IN', $id_arr],
                                    'attr_group'    => 'guestbook_attribute',
                                ])->delete();
                        }
                    }
                }
                /*--end*/
                adminLog('删除留言表单-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    /**
     * 查看详情
     */
    public function details()
    {
        $aid = input('param.aid/d');
        $form_type = input('param.form_type/d', 0);

        // 标记为已读和IP地区
        if (1 == $form_type) {
            $row = Db::name('guestbook')->field('a.*, b.form_name')
                ->alias('a')
                ->join('form b','a.typeid = b.form_id','left')
                ->where(['a.aid'=>$aid, 'a.form_type'=>$form_type])
                ->find();
        } else {
            $row = Db::name('guestbook')->field('a.*, b.typename as form_name')
                ->alias('a')
                ->join('arctype b','a.typeid = b.id','left')
                ->where(['a.aid'=>$aid, 'a.form_type'=>$form_type])
                ->find();
        }
        // 标记为已读
        if (empty($row['is_read'])) {
            $row['is_read'] = 1;
            $row['update_time'] = getTime();
            Db::name('guestbook')->where(['aid'=>$aid])->update([
                    'is_read'   => $row['is_read'],
                    'update_time'   => $row['update_time'],
                ]);
        }
        //消息通知有的话也标记为已读
        Db::name('users_notice_tpl_content')->where(['aid'=>$aid])->update([
            'is_read'   => 1,
            'update_time'   => getTime(),
        ]);
        $this->assign('row', $row);

        // 留言属性
        $attr_list = Db::name('guestbook_attribute')->where(['typeid'=>$row['typeid'],'form_type'=>$form_type])->order('attr_id asc')->select();
        $attr_values = Db::name('guestbook_attr')->field('attr_id,attr_value')->where(['aid'=>$aid,'form_type'=>$form_type])->getAllWithIndex('attr_id');
        foreach ($attr_list as $key => $val) {
            $val['attr_value'] = empty($attr_values[$val['attr_id']]) ? '' : $attr_values[$val['attr_id']]['attr_value'];
            $attr_list[$key] = $val;
        }
        foreach ($attr_list as $key => &$val) {
            if ($val['attr_input_type'] == 9) {
                $val['attr_value'] = Db::name('region')->where('id','in',$val['attr_value'])->column('name');
                $val['attr_value'] = implode('',$val['attr_value']);
            } else if ($val['attr_input_type'] == 4) {
                $val['attr_value'] = filter_line_return($val['attr_value'], '、');
            } else if(10 == $val['attr_input_type'] && !empty($val['attr_value'])){
                $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
            } else if(11 == $val['attr_input_type']) {
                $attrValueArr = !empty($val['attr_value']) ? explode(',', $val['attr_value']) : [];
                $val['attr_value'] = '';
                foreach ($attrValueArr as $value) {
                    if (preg_match('/(\.('.tpCache('global.image_type').'))$/i', $value)) {
                        if (!stristr($value, '|')) {
                            $value = handle_subdir_pic($value);
                            $val['attr_value'] .= "<a class='guest-pic' href='{$value}' target='_blank'><img src='{$value}' width='60' height='60' style='float: unset; cursor: pointer;'/></a>";
                        }
                    } elseif (preg_match('/(\.('.tpCache('global.file_type').'))$/i', $value)) {
                        if (!stristr($value, '|')) {
                            $value = handle_subdir_pic($value);
                            $val['attr_value'] .= "<a href='{$value}' download='".time()."'><img src=\"".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width: 16px; height: 16px;\">文件下载</a>";
                        }
                    }
                }
            } else {
                if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<a href='{$val['attr_value']}' target='_blank'><img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' /></a>";
                    }
                }elseif (preg_match('/(\.('.tpCache('global.file_type').'))$/i', $val['attr_value'])){
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<a href='{$val['attr_value']}' download='".time()."'><img src=\"".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width: 16px;height:  16px;\">点击下载</a>";
                    }
                }
            }
        }

        $this->assign('attr_list', $attr_list);

        // 如果安装手机端后台管理插件并且在手机端访问时执行
        $isMobile = input('param.isMobile/d', 0);
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            return $this->display('form/details');
        } else {
            return $this->fetch();
        }
    }

    /**    
     * excel导出
     */
    public function ajax_excel_export()
    {
        $id_arr          = input('aid/s');
        if (!empty($id_arr)) {
            $id_arr          = explode(',', $id_arr);
            $id_arr          = eyIntval($id_arr);
        }
        $typeid          = input('typeid/d');
        $start_time      = input('start_time/s');
        $end_time        = input('end_time/s');
        $form_type = input('param.form_type/s', 0);

        $strTable        = '<table width="500" border="1">';
        $where           = [];
        if (!empty($typeid)) $where['typeid'] = $typeid;
        if ('all' != $form_type) $where['form_type'] = intval($form_type);
        $where['lang']   = $this->admin_lang;
        $order           = 'add_time asc';
        //没有指定ID就导出全部
        if (!empty($id_arr)) {
            $where['aid'] = ['IN', $id_arr];
        }
        //根据日期导出
        if (!empty($start_time) && !empty($end_time)) {
            $start_time        = strtotime($start_time);
            $end_time          = strtotime("+1 day", strtotime($end_time)) - 1;
            $where['add_time'] = ['between', [$start_time, $end_time]];
        } elseif (!empty($start_time) && empty($end_time)) {
            $start_time        = strtotime($start_time);
            $where['add_time'] = ['>=', $start_time];
        } elseif (empty($start_time) && !empty($end_time)) {
            $end_time          = strtotime("+1 day", strtotime($end_time)) - 1;
            $where['add_time'] = ['<=', $end_time];
        }
        $row = Db::name('guestbook')->where($where)->order($order)->select();

        $map = [];
        if (!empty($typeid)) $map['typeid'] = $typeid;
        if ('all' != $form_type) $map['form_type'] = intval($form_type);
        $map['lang']   = $this->admin_lang;
        $map['is_del'] = 0;
        $title = Db::name('guestbook_attribute')->where($map)->order('sort_order asc, attr_id asc')->select();

        if ($row && $title) {
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">ID</td>';
            foreach ($title as &$key) {
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $key['attr_name'] . '</td>';
            }
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">新增时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">更新时间</td>';
            $strTable .= '</tr>';

            foreach ($row as &$val) {
                $attr_value = Db::name('guestbook_attr')
                    ->alias('a')
                    ->field('a.*,b.attr_input_type')
                    ->where(['a.aid' => $val['aid'], 'a.lang' => $this->admin_lang])
                    ->join('guestbook_attribute b','a.attr_id = b.attr_id')
                    ->getAllWithIndex('attr_id');
                foreach ($attr_value as $k => $v){
                    if ($v['attr_input_type'] == 9){
                        $v['attr_value'] = Db::name('region')->where('id','in',$v['attr_value'])->column('name');
                        $attr_value[$k]['attr_value'] = implode('',$v['attr_value']);
                    }else if(10 == $v['attr_input_type']){
                        $attr_value[$k]['attr_value'] =  date('Y-m-d H:i:s',$v['attr_value']);
                    }else if(in_array($v['attr_input_type'],[5,8])){     //单张图、附件
                        if (!stristr($val['attr_value'], '|')){
                            $attr_value[$k]['attr_value'] = handle_subdir_pic($v['attr_value'],'img',true);
                        }
                    }else if(11 == $v['attr_input_type']){  //多张图
                        $attr_value_arr = explode(",",$v['attr_value']);
                        foreach ($attr_value_arr as $attr_value_k => $attr_value_v){
                            $attr_value_arr[$attr_value_k] = handle_subdir_pic($attr_value_v,'img',true);
                        }
                        $attr_value[$k]['attr_value'] = implode(PHP_EOL,$attr_value_arr);
                    }
                }

                $strTable   .= '<tr>';
                $strTable   .= '<td style="text-align:center;font-size:12px;">' . $val['aid'] . '</td>';
                foreach ($title as &$key) {
                    $strTable .= '<td style="text-align:center;font-size:12px;" style=\'vnd.ms-excel.numberformat:@\' width="*">' . $attr_value[$key['attr_id']]['attr_value'] . '</td>';
                }
                $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['add_time']) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['update_time']) . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        if ('all' === $form_type) {
            downloadExcel($strTable, 'allwords');
        } else if (1 == $form_type) {
            downloadExcel($strTable, 'form');
        } else {
            downloadExcel($strTable, 'guestbook');
        }
        exit();
    }

    /**
     * 设置/取消星标
     * @return [type] [description]
     */
    public function ajax_set_star()
    {
        $aid = input('param.aid/d');
        $is_star = input('param.is_star/d');
        if (IS_AJAX && !empty($aid)) {
            $r = Db::name('guestbook')->where(['aid'=>$aid])->update([
                    'is_star' => $is_star,
                ]);
            if ($r !== false) {
                if ($is_star == 1) {
                    $this->success('星标成功');
                } else {
                    $this->success('取消成功');
                }
            }
        }
        $this->error('操作失败');
    }
}