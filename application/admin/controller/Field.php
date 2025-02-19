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
use app\admin\logic\FieldLogic;

/**
 * 模型字段控制器
 */
class Field extends Base
{
    public $fieldLogic;
    public $arctype_channel_id;

    public function _initialize()
    {
        parent::_initialize();
        if (!in_array(ACTION_NAME, ['ajax_attribute_show'])) {
            $this->language_access(); // 多语言功能操作权限
        }
        $this->fieldLogic         = new FieldLogic();
        $this->arctype_channel_id = config('global.arctype_channel_id');
    }

    /**
     * 模型字段管理
     */
    public function channel_index()
    {
        /*同步栏目绑定的自定义字段*/
        $this->syn_channelfield_bind();
        /*--end*/

        $channel_id  = input('param.channel_id/d', 0);
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $param = input('param.');

        /*同步更新附加表字段到自定义模型字段表中*/
        if (empty($param['searchopt']) && !empty($channel_id)) {
            $this->fieldLogic->synChannelTableColumns($channel_id);
        }
        /*--end*/

        // 应用搜索条件
        foreach (['keywords'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.name|a.title'] = array('LIKE', "%{$param[$key]}%");
                    // 过滤指定字段
                    // $banFields = ['id'];
                    // $condition['a.name'] = array(
                    //     array('LIKE', "%{$param[$key]}%"),
                    //     array('notin', $banFields),
                    // );
                } else {
                    $condition['a.' . $key] = array('eq', $param[$key]);
                }
            }
        }

        /*显示主表与附加表*/
        $condition['a.channel_id'] = !empty($channel_id) ? array('IN', [$channel_id]) : ['GT', 0];

        // 排序
        $orderby = 'a.sort_order asc, a.ifmain asc, a.ifcontrol asc, a.id desc';
        if (empty($channel_id)) $orderby = 'a.id desc';

        // 总的字段管理 -- 只读自定义字段
        if (empty($channel_id)) $condition['a.ifsystem'] = 0;

        /*模型列表*/
        $channeltype_list                = model('Channeltype')->getAll('*', [], 'id');
        $assign_data['channeltype_list'] = $channeltype_list;
        /*--end*/

        $condition['a.ifcontrol'] = 0;
        $cfieldM = Db::name('channelfield');
        $count   = $cfieldM->alias('a')->where($condition)->count('a.id');// 查询满足要求的总记录数
        $Page    = $pager = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list    = $cfieldM->field('a.*')
            ->alias('a')
            ->where($condition)
            ->order($orderby)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $show                 = $Page->show();// 分页显示输出
        $assign_data['page']  = $show; // 赋值分页输出
        $assign_data['list']  = $list; // 赋值数据集
        $assign_data['pager'] = $Page; // 赋值分页对象

        /*字段类型列表*/
        $assign_data['fieldtypeList'] = Db::name('field_type')->field('name,title')->getAllWithIndex('name');
        /*--end*/

        // 模型信息
        $assign_data['channeltype_row'] = \think\Cache::get('extra_global_channeltype');

        /*模型ID*/
        $assign_data['channel_id'] = $channel_id;
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 同步栏目绑定的自定义字段
     */
    private function syn_channelfield_bind()
    {
        $totalRow = Db::name('channelfield_bind')->count();
        if (empty($totalRow)) {
            $field_ids = Db::name('channelfield')->where([
                'ifmain'     => 0,
                'channel_id' => ['NEQ', -99],
            ])->column('id');
            if (!empty($field_ids)) {
                $sveData = [];
                foreach ($field_ids as $key => $val) {
                    $sveData[] = [
                        'typeid'      => 0,
                        'field_id'    => $val,
                        'add_time'    => getTime(),
                        'update_time' => getTime(),
                    ];
                }
                model('ChannelfieldBind')->saveAll($sveData);
            }
        }
    }

    /**
     * 新增-模型字段
     */
    public function channel_add()
    {
        $channel_id = input('param.channel_id/d', 0);
        // if (empty($channel_id)) {
        //     $this->error('参数有误！');
        // }

        if (IS_POST) {
            if (empty($channel_id)) $this->error("请选择所属模型");
            
            $post = input('post.', '', 'trim');
            $post['title'] = htmlspecialchars($post['title']);
            // 判断是否存在|杠
            $IsData = strstr($post['dfvalue'], '|');
            if (!empty($IsData)) {
                $this->error("不可输入 | 杠");
            }
            if (empty($post['dtype']) || empty($post['title']) || empty($post['name'])) {
                $this->error("缺少必填信息！");
            }

            if (!preg_match('/^(\w)+$/', $post['name']) || 1 == preg_match('/^([_]+|[0-9]+)$/', $post['name'])) {
                $this->error("字段名称格式不正确！");
            } else if (preg_match('/^type/', $post['name'])) {
                $this->error("字段名称不允许以type开头！");
            } else if (preg_match('/^ey_/', $post['name'])) {
                $this->error("字段名称不允许以 ey_ 开头！");
            }
            
            if (!is_string($post['dtype'])) {
                $this->error("数据类型不正确！");
            }

            // 字段类型是否具备筛选功能
            if (empty($post['IsScreening_status'])) {
                $post['is_screening'] = 0;
            }

            /*去除中文逗号，过滤左右空格与空值、以及单双引号*/
            $dfvalue    = str_replace('，', ',', $post['dfvalue']);
            if (in_array($post['dtype'], ['radio','checkbox','select','region'])) {
                $pattern    = ['"', '\'', ';', '&', '?', '='];
                $dfvalue    = func_preg_replace($pattern, '', $dfvalue);
            }
            $dfvalueArr = explode(',', $dfvalue);
            foreach ($dfvalueArr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($dfvalueArr[$key]);
                    continue;
                }
                $dfvalueArr[$key] = $tmp_val;
            }
            $dfvalueArr = array_unique($dfvalueArr);
            $dfvalue = implode(',', $dfvalueArr);
            /*--end*/

            if ('region' == $post['dtype']) {
                if (!empty($post['region_data'])) {
                    $post['region_data'] = [
                        'region_id' => preg_replace('/([^\d\,]+)/i', '', $post['region_data']['region_id']),
                        'region_ids' => preg_replace('/([^\d\,]+)/i', '', $post['region_data']['region_ids']),
                        'region_names' => preg_replace("/([^\x{4e00}-\x{9fa5}\,\，]+)/u", '', $post['region_data']['region_names']),
                    ];
                    $post['dfvalue']     = $post['region_data']['region_id'];
                    $post['region_data'] = serialize($post['region_data']);
                } else {
                    $this->error("请选择区域范围！");
                }
            } else {
                /*默认值必填字段*/
                $fieldtype_list = model('Field')->getFieldTypeAll('name,title,ifoption', 'name');
                if (isset($fieldtype_list[$post['dtype']]) && 1 == $fieldtype_list[$post['dtype']]['ifoption']) {
                    if (empty($dfvalue)) {
                        $this->error("你设定了字段为【" . $fieldtype_list[$post['dtype']]['title'] . "】类型，默认值不能为空！ ");
                    }
                }
                /*--end*/
                unset($post['region_data']);
            }

            /*当前模型对应的数据表*/
            $table = Db::name('channeltype')->where('id', $channel_id)->getField('table');
            $table = PREFIX . $table . '_content';
            /*--end*/

            /*检测字段是否存在于主表与附加表中*/
            if (true == $this->fieldLogic->checkChannelFieldList($table, $post['name'], $channel_id)) {
                $this->error("字段名称 " . $post['name'] . " 与系统字段冲突！");
            }
            /*--end*/

            if (empty($post['typeids'])) {
                $this->error('请选择可见栏目！');
            }
            if ("checkbox" == $post['dtype']){
                $dfvalue = explode(',', $dfvalue);
                if (64 < count($dfvalue)){
                    $dfvalue = array_slice($dfvalue, 0, 64);
                }
                $dfvalue = implode(',', $dfvalue);
            }
            /*组装完整的SQL语句，并执行新增字段*/
            $fieldinfos = $this->fieldLogic->GetFieldMake($post['dtype'], $post['name'], $dfvalue, $post['title']);
            $ntabsql    = $fieldinfos[0];
            $buideType  = $fieldinfos[1];
            $maxlength  = $fieldinfos[2];
            $sql        = " ALTER TABLE `$table` ADD  $ntabsql ";
            if (false !== Db::execute($sql)) {
                if (is_dir('./weapp/CompressContent/')) {                    
                    $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;  
                    $compressLogic->copydata(4,$channel_id,'',$table,$sql);
                }                              
                if (!empty($post['region_data'])) {
                    $dfvalue = $post['region_data'];
                    unset($post['region_data']);
                }
                /*保存新增字段的记录*/
                $newData = array(
                    'dfvalue'     => $dfvalue,
                    'maxlength'   => $maxlength,
                    'define'      => $buideType,
                    'ifcontrol'   => 0,
                    'sort_order'  => 100,
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                );
                $data    = array_merge($post, $newData);
                Db::name('channelfield')->save($data);
                $field_id = Db::name('channelfield')->getLastInsID();
                /*--end*/

                /*保存栏目与字段绑定的记录*/
                $typeids = $post['typeids'];
                if (!empty($typeids)) {
                    /*多语言*/
                    if (is_language()) {
                        $attr_name_arr = [];
                        foreach ($typeids as $key => $val) {
                            $attr_name_arr[] = 'tid' . $val;
                        }
                        $new_typeid_arr = Db::name('language_attr')->where([
                            'attr_name'  => ['IN', $attr_name_arr],
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        !empty($new_typeid_arr) && $typeids = $new_typeid_arr;
                    }
                    /*--end*/
                    $addData = [];
                    foreach ($typeids as $key => $val) {
                        if (1 < count($typeids) && empty($val)) {
                            continue;
                        }
                        $addData[] = [
                            'typeid'      => $val,
                            'field_id'    => $field_id,
                            'add_time'    => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }
                /*--end*/

                /*重新生成数据表字段缓存文件*/
                try {
                    schemaTable($table);
                } catch (\Exception $e) {}
                /*--end*/

                \think\Cache::clear('channelfield');
                $this->success("操作成功！", url('Field/channel_index', array('channel_id' => $channel_id)));
            }
            $this->error('操作失败');
        }

        /*字段类型列表*/
        $assign_data['fieldtype_list'] = model('Field')->getFieldTypeAll('name,title,ifoption');
        /*--end*/

        /*允许发布文档列表的栏目*/
        $select_html = allow_release_arctype(0, [$channel_id]);
        $this->assign('select_html', $select_html);
        /*--end*/

        /*模型ID*/
        $assign_data['channel_id'] = $channel_id;
        /*--end*/

        // 地区选择
        $China[]                 = [
            'id'   => 0,
            'name' => '全国',
        ];
        $Province                = get_province_list();
        $assign_data['Province'] = array_merge($China, $Province);

        // 模型列表
        $assign_data['type'] = input('param.type/s', '');
        $assign_data['channeltype_list'] = model('Channeltype')->getAll('*', ['id'=>['NOTIN', [8,51]]], 'id');

        $this->assign($assign_data);
        return $this->fetch();
    }

    // 联动地址获取
    public function ajax_get_region_data()
    {
        $parent_id = input('param.parent_id/d');
        // 获取指定区域ID下的城市并判断是否需要处理特殊市返回值
        $RegionData = $this->SpecialCityDealWith($parent_id);
        // 处理数据
        $region_html = $region_names = $region_ids = '';
        if ($RegionData) {
            // 拼装下拉选项
            foreach ($RegionData as $key => $value) {
                $region_html .= "<option value='{$value['id']}'>{$value['name']}</option>";
                if ($key > '0') {
                    $region_names .= '，';
                    $region_ids   .= ',';
                }
                $region_names .= $value['name'];
                $region_ids   .= $value['id'];
            }
        }
        $return = [
            'region_html'  => $region_html,
            'region_names' => $region_names,
            'region_ids'   => $region_ids,
            'parent_array' => config('global.field_region_all_type'),
        ];
        echo json_encode($return);
    }

    // 获取指定区域ID下的城市并判断是否需要处理特殊市返回值
    // 特殊市：北京市，上海市，天津市，重庆市
    private function SpecialCityDealWith($parent_id = 0)
    {
        empty($parent_id) && $parent_id = 0;

        /*parent_id在特殊范围内则执行*/
        // 处理北京市，上海市，天津市，重庆市逻辑
        $RegionData   = Db::name('region')->where("parent_id", $parent_id)->select();
        $parent_array = config('global.field_region_type');
        if (in_array($parent_id, $parent_array)) {
            $region_ids = get_arr_column($RegionData, 'id');
            $RegionData = Db::name('region')->where('parent_id', 'IN', $region_ids)->select();
        }
        /*结束*/
        return $RegionData;
    }

    /**
     * 编辑-模型字段
     */
    public function channel_edit()
    {
        $channel_id = input('param.channel_id/d', 0);
        // if (empty($channel_id)) {
        //     $this->error('参数有误！');
        // }

        if (IS_POST) {
            if (empty($channel_id)) $this->error("请选择所属模型");

            $post = input('post.', '', 'trim');
            $post['id'] = intval($post['id']);
            $post['title'] = htmlspecialchars($post['title']);

            if ('checkbox' == $post['old_dtype'] && in_array($post['dtype'], ['radio', 'select'])) {
                $fieldtype_list = model('Field')->getFieldTypeAll('name,title', 'name');
                $this->error("{$fieldtype_list['checkbox']['title']}不能更改为{$fieldtype_list[$post['dtype']]['title']}！");
            }

            if (empty($post['dtype']) || empty($post['title']) || empty($post['name'])) {
                $this->error("缺少必填信息！");
            }

            if (!preg_match('/^(\w)+$/', $post['name']) || 1 == preg_match('/^([_]+|[0-9]+)$/', $post['name'])) {
                $this->error("字段名称格式不正确！");
            } else if (preg_match('/^type/', $post['name'])) {
                $this->error("字段名称不允许以type开头！");
            } else if (preg_match('/^ey_/', $post['name'])) {
                $this->error("字段名称不允许以 ey_ 开头！");
            }
            
            if (!is_string($post['dtype'])) {
                $this->error("数据类型不正确！");
            }

            $info = model('Channelfield')->getInfo($post['id'], 'ifsystem');
            if (!empty($info['ifsystem'])) {
                $this->error('系统字段不允许更改！');
            }

            // 字段类型是否具备筛选功能
            if (empty($post['IsScreening_status'])) {
                $post['is_screening'] = 0;
            }

            $old_name = $post['old_name'];
            /*去除中文逗号，过滤左右空格与空值*/
            $dfvalue    = str_replace('，', ',', $post['dfvalue']);
            if (in_array($post['dtype'], ['radio','checkbox','select','region'])) {
                $pattern    = ['"', '\'', ';', '&', '?', '='];
                $dfvalue    = func_preg_replace($pattern, '', $dfvalue);
            }
            $dfvalueArr = explode(',', $dfvalue);
            foreach ($dfvalueArr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($dfvalueArr[$key]);
                    continue;
                }
                $dfvalueArr[$key] = $tmp_val;
            }
            $dfvalueArr = array_unique($dfvalueArr);
            $dfvalue = implode(',', $dfvalueArr);
            /*--end*/

            if ('region' == $post['dtype']) {
                if (!empty($post['region_data'])) {
                    $post['region_data'] = [
                        'region_id' => preg_replace('/([^\d\,]+)/i', '', $post['region_data']['region_id']),
                        'region_ids' => preg_replace('/([^\d\,]+)/i', '', $post['region_data']['region_ids']),
                        'region_names' => preg_replace("/([^\x{4e00}-\x{9fa5}\,\，]+)/u", '', $post['region_data']['region_names']),
                    ];
                    $post['dfvalue']     = $post['region_data']['region_id'];
                    $post['region_data'] = serialize($post['region_data']);
                } else {
                    $this->error("请选择区域范围！");
                }
            } else {
                /*默认值必填字段*/
                $fieldtype_list = model('Field')->getFieldTypeAll('name,title,ifoption', 'name');
                if (isset($fieldtype_list[$post['dtype']]) && 1 == $fieldtype_list[$post['dtype']]['ifoption']) {
                    if (empty($dfvalue)) {
                        $this->error("你设定了字段为【" . $fieldtype_list[$post['dtype']]['title'] . "】类型，默认值不能为空！ ");
                    }
                }
                /*--end*/
                unset($post['region_data']);
            }

            /*当前模型对应的数据表*/
            $table = Db::name('channeltype')->where('id', $post['channel_id'])->getField('table');
            $tableName = $table . '_content';
            $table = PREFIX . $tableName;
            /*--end*/

            /*检测字段是否存在于主表与附加表中*/
            if (true == $this->fieldLogic->checkChannelFieldList($table, $post['name'], $channel_id, array($old_name))) {
                $this->error("字段名称 " . $post['name'] . " 与系统字段冲突！");
            }
            /*--end*/

            if (empty($post['typeids'])) {
                $this->error('请选择可见栏目！');
            }

            /*针对单选项、多选项、下拉框：修改之前，将该字段不存在的值都更新为默认值第一个*/
            if (in_array($post['old_dtype'], ['radio', 'select', 'checkbox']) && in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                $whereArr = [];
                $dfvalueArr = explode(',', $dfvalue);
                foreach($dfvalueArr as $key => $val){
                    $whereArr[] = "CONCAT(',', `{$post['name']}` ,',') NOT LIKE '%,{$val},%'";
                }
                $whereStr = implode(' AND ', $whereArr);
                if (in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                    if (!empty($dfvalueArr[0])) {
                        $new_dfvalue = $dfvalueArr[0];
                        $old_dfvalue_arr = explode(',', $post['old_dfvalue']);
                        if (!in_array($new_dfvalue, $old_dfvalue_arr)) {
                            $new_dfvalue = NULL;
                        }
                    } else {
                        $new_dfvalue = NULL;
                    }
                } else {
                    $new_dfvalue = '';
                }
                Db::name($tableName)->where($whereStr)->update([$post['name']=>$new_dfvalue]);
            }
            /*end*/
            if ("checkbox" == $post['dtype']){
                $dfvalue = explode(',', $dfvalue);
                if (64 < count($dfvalue)){
                    $dfvalue = array_slice($dfvalue, 0, 64);
                }
                $dfvalue = implode(',', $dfvalue);
            }
            /*组装完整的SQL语句，并执行编辑字段*/
            $fieldinfos = $this->fieldLogic->GetFieldMake($post['dtype'], $post['name'], $dfvalue, $post['title']);
            $ntabsql    = $fieldinfos[0];
            $buideType  = $fieldinfos[1];
            $maxlength  = $fieldinfos[2];
            $sql        = " ALTER TABLE `$table` CHANGE COLUMN `{$old_name}` $ntabsql ";
            try {
                if (is_dir('./weapp/CompressContent/')) {                                            
                   $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                    
                   $compressLogic->copydata(4,$channel_id,'',$table,  $sql);        
                }                  
                $r = @Db::execute($sql);
            } catch (\Exception $e) {
                $this->error('该数据类型不支持切换');
            }
            if (false !== $r) {

                /*针对单选项、多选项、下拉框：修改之前，将该字段不存在的值都更新为默认值第一个*/
                if (in_array($post['old_dtype'], ['radio', 'select', 'checkbox']) && in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                    $whereArr = [];
                    $new_dfvalue = '';
                    $dfvalueArr = explode(',', $dfvalue);
                    foreach($dfvalueArr as $key => $val){
                        if ($key == 0) {
                            $new_dfvalue = $val;
                        }
                        $whereArr[] = "CONCAT(',', `{$post['name']}` ,',') NOT LIKE '%,{$val},%'";
                    }
                    $whereArr[] = "(`{$post['name']}` is NULL OR `{$post['name']}` = '')";
                    $whereStr = implode(' AND ', $whereArr);
                    Db::name($tableName)->where($whereStr)->update([$post['name']=>$new_dfvalue]);
                }
                /*end*/

                /*保存更新字段的记录*/
                if (!empty($post['region_data'])) {
                    $dfvalue = $post['region_data'];
                    unset($post['region_data']);
                }
                $newData = array(
                    'dfvalue'     => $dfvalue,
                    'maxlength'   => $maxlength,
                    'define'      => $buideType,
                    'update_time' => getTime(),
                );
                $data    = array_merge($post, $newData);
                Db::name('channelfield')->where(['id'=>$post['id'],'channel_id'=>$channel_id])->cache(true, null, "channelfield")->save($data);
                /*--end*/

                /*保存栏目与字段绑定的记录*/
                $field_id = $post['id'];
                model('ChannelfieldBind')->where(['field_id' => $field_id])->delete();
                $typeids = $post['typeids'];
                if (!empty($typeids)) {
                    /*多语言*/
                    if (is_language()) {
                        $attr_name_arr = [];
                        foreach ($typeids as $key => $val) {
                            $attr_name_arr[] = 'tid' . $val;
                        }
                        $new_typeid_arr = Db::name('language_attr')->where([
                            'attr_name'  => ['IN', $attr_name_arr],
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        !empty($new_typeid_arr) && $typeids = $new_typeid_arr;
                    }
                    /*--end*/
                    $addData = [];
                    foreach ($typeids as $key => $val) {
                        if (1 < count($typeids) && empty($val)) {
                            continue;
                        }
                        $addData[] = [
                            'typeid'      => $val,
                            'field_id'    => $field_id,
                            'add_time'    => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }
                /*--end*/

                /*重新生成数据表字段缓存文件*/
                try {
                    schemaTable($table);
                } catch (\Exception $e) {}
                /*--end*/

                $this->success("操作成功！", url('Field/channel_index', array('channel_id' => $post['channel_id'])));
            } else {
                $sql = " ALTER TABLE `$table` ADD  $ntabsql ";
                if (false === Db::execute($sql)) {
                    $this->error('操作失败！');
                }
            }
        }

        $id   = input('param.id/d', 0);
        $info = model('Channelfield')->getInfoByWhere(['id'=>$id,'channel_id'=>$channel_id]);
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        if (!empty($info['ifsystem'])) {
            $this->error('系统字段不允许更改！');
        }
        /*字段类型列表*/
        $assign_data['fieldtype_list'] = model('Field')->getFieldTypeAll('name,title,ifoption');
        /*--end*/

        /*允许发布文档列表的栏目*/
        $typeids     = Db::name('channelfield_bind')->where(['field_id' => $id])->column('typeid');
        $select_html = allow_release_arctype($typeids, [$channel_id]);
        $this->assign('select_html', $select_html);
        $this->assign('typeids', $typeids);
        /*--end*/

        /*模型ID*/
        $assign_data['channel_id'] = $channel_id;
        /*--end*/

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
        if ('region' == $info['dtype']) {
            // 反序列化默认值参数
            if (!preg_match('/^([\x{4e00}-\x{9fa5}\w\"\:\{\}\;\，\,]*)$/u', $info['dfvalue'])) {
                $info['dfvalue'] = '';
            }
            $dfvalue = unserialize($info['dfvalue']);
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
            unset($info['dfvalue']);

            // 区域选择时，指定不出现下级地区列表
            $assign_data['parent_array'] = convert_js_array(config('global.field_region_all_type'));
        }
        /*区域字段处理结束*/
        $assign_data['info'] = $info;

        // 模型列表
        $assign_data['type'] = input('param.type/s', '');
        $assign_data['channeltype_list'] = model('Channeltype')->getAll('*', ['id'=>['NOTIN', [8,51]]], 'id');

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除-模型字段
     */
    public function channel_del()
    {
        $channel_id = input('channel_id/d', 0);
        $id         = input('del_id/d', 0);
        if (!empty($id)) {
            /*删除表字段*/
            $row = $this->fieldLogic->delChannelField($id);
            /*--end*/
            if (0 < $row['code']) {
                $map       = array(
                    'id'         => array('eq', $id),
                    'channel_id' => $channel_id,
                );
                $result    = Db::name('channelfield')->field('channel_id,name')->where($map)->select();
                $name_list = get_arr_column($result, 'name');
                /*删除字段的记录*/
                Db::name('channelfield')->where($map)->delete();
                /*--end*/
                /*删除栏目与字段绑定的记录*/
                model('ChannelfieldBind')->where(['field_id' => $id])->delete();
                /*--end*/

                /*获取模型标题*/
                $channel_title = '';
                if (!empty($channel_id)) {
                    $channel_title = Db::name('channeltype')->where('id', $channel_id)->getField('title');
                }
                /*--end*/
                adminLog('删除' . $channel_title . '字段：' . implode(',', $name_list));
                $this->success('删除成功');
            }

            \think\Cache::clear('channelfield');
            respose(array('status' => 0, 'msg' => $row['msg']));

        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 栏目字段 - 删除多图字段的图集
     */
    public function del_arctypeimgs()
    {
        $typeid = input('typeid/d', '0');
        if (!empty($typeid)) {
            $path      = input('param.filename/s', ''); // 图片路径
            $fieldid = input('param.fieldid/d'); // 多图字段
            $fieldname = Db::name('channelfield')->where(['id'=>$fieldid])->value('name');
            if (!empty($fieldname)) {
                /*除去多图字段值中的图片*/
                $info     = Db::name('arctype')->field("{$fieldname}")->where("id", $typeid)->find();
                $valueArr = explode(',', $info[$fieldname]);
                foreach ($valueArr as $key => $val) {
                    if ($path == $val) {
                        unset($valueArr[$key]);
                    }
                }
                $value = implode(',', $valueArr);
                Db::name('arctype')->where('id', $typeid)->update(array($fieldname => $value, 'update_time' => getTime()));
                /*--end*/
            }
        }
    }

    /**
     * 模型字段 - 删除多图字段的图集
     */
    public function del_channelimgs()
    {
        $aid     = input('aid/d', '0');
        $channel = input('channel/d', ''); // 模型ID
        if (!empty($aid) && !empty($channel)) {
            $path      = input('param.filename/s', ''); // 图片路径
            $fieldid = input('param.fieldid/d'); // 多图字段
            $fieldname = Db::name('channelfield')->where(['id'=>$fieldid])->value('name');
            if (!empty($fieldname)) {
                /*模型附加表*/
                $table    = Db::name('channeltype')->where('id', $channel)->getField('table');
                $tableExt = $table . '_content';
                /*--end*/

                /*除去多图字段值中的图片*/
                $info     = Db::name($tableExt)->field("{$fieldname}")->where("aid", $aid)->find();
                $valueArr = explode(',', $info[$fieldname]);
                foreach ($valueArr as $key => $val) {
                    if ($path == $val) {
                        unset($valueArr[$key]);
                    }
                }
                $value = implode(',', $valueArr);
                Db::name($tableExt)->where('aid', $aid)->update(array($fieldname => $value, 'update_time' => getTime()));
                /*--end*/
            }
        }
    }

    /**
     * 显示与隐藏
     */
    public function ajax_channel_show()
    {
        if (IS_POST) {
            $id         = input('id/d');
            $ifeditable = input('ifeditable/d');
            if (!empty($id)) {
                $row = Db::name('channelfield')->where([
                    'id' => $id,
                ])->find();
                if (!empty($row) && 1 == intval($row['ifcontrol'])) {
                    $this->error('系统内置表单，禁止操作！');
                }
                $r = Db::name('channelfield')->where([
                    'id' => $id,
                ])->update([
                    'ifeditable'  => $ifeditable,
                    'update_time' => getTime(),
                ]);
                if ($r) {
                    adminLog('操作自定义模型表单：' . $row['name']);
                    $this->success('操作成功');
                } else {
                    $this->error('操作失败');
                }
            } else {
                $this->error('参数有误');
            }
        }
        $this->error('非法访问');
    }

    public function channel_select()
    {
        if (IS_AJAX_POST) {
            $channel_id = input('param.channel_id/d', 0);
            empty($channel_id) && $this->error('请选择模型');
            $select_html = allow_release_arctype(0, [$channel_id]);
            $this->success('查询成功', null, $select_html);
        }
    }

    /**
     * 栏目字段管理
     */
    public function arctype_index()
    {
        $channel_id  = $this->arctype_channel_id;
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $param = input('param.');

        /*同步更新栏目主表字段到自定义字段表中*/
        if (empty($param['searchopt'])) {
            $this->fieldLogic->synArctypeTableColumns($channel_id);
        }
        /*--end*/

        // 应用搜索条件
        foreach (['keywords'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['name|title'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition[$key] = array('eq', $param[$key]);
                }
            }
        }

        // 模型ID
        $condition['channel_id'] = array('eq', $channel_id);
        $condition['ifsystem']   = array('neq', 1);

        $cfieldM = Db::name('channelfield');
        $count   = $cfieldM->where($condition)->count('id');// 查询满足要求的总记录数
        $Page    = $pager = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list    = $cfieldM->where($condition)->order('sort_order asc, ifsystem asc, id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show                 = $Page->show();// 分页显示输出
        $assign_data['page']  = $show; // 赋值分页输出
        $assign_data['list']  = $list; // 赋值数据集
        $assign_data['pager'] = $Page; // 赋值分页对象

        /*字段类型列表*/
        $assign_data['fieldtypeList'] = Db::name('field_type')->field('name,title')->getAllWithIndex('name');
        /*--end*/

        $assign_data['channel_id'] = $channel_id;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 新增-栏目字段
     */
    public function arctype_add()
    {
        $channel_id = $this->arctype_channel_id;
        if (empty($channel_id)) {
            $this->error('参数有误！');
        }

        if (IS_POST) {
            $post = input('post.', '', 'trim');

            if (empty($post['dtype']) || empty($post['title']) || empty($post['name'])) {
                $this->error("缺少必填信息！");
            }

            if (!preg_match('/^(\w)+$/', $post['name']) || 1 == preg_match('/^([_]+|[0-9]+)$/', $post['name'])) {
                $this->error("字段名称格式不正确！");
            } else if (preg_match('/^ey_/', $post['name'])) {
                $this->error("字段名称不允许以 ey_ 开头！");
            }
            
            if (!is_string($post['dtype'])) {
                $this->error("数据类型不正确！");
            }

            /*去除中文逗号，过滤左右空格与空值*/
            $dfvalue    = str_replace('，', ',', $post['dfvalue']);
            if (in_array($post['dtype'], ['radio','checkbox','select','region'])) {
                $pattern    = ['"', '\'', ';', '&', '?', '='];
                $dfvalue    = func_preg_replace($pattern, '', $dfvalue);
            }
            $dfvalueArr = explode(',', $dfvalue);
            foreach ($dfvalueArr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($dfvalueArr[$key]);
                    continue;
                }
                $dfvalueArr[$key] = $tmp_val;
            }
            $dfvalueArr = array_unique($dfvalueArr);
            $dfvalue = implode(',', $dfvalueArr);
            /*--end*/

            /*默认值必填字段*/
            $fieldtype_list = model('Field')->getFieldTypeAll('name,title,ifoption', 'name');
            if (isset($fieldtype_list[$post['dtype']]) && 1 == $fieldtype_list[$post['dtype']]['ifoption']) {
                if (empty($dfvalue)) {
                    $this->error("你设定了字段为【" . $fieldtype_list[$post['dtype']]['title'] . "】类型，默认值不能为空！ ");
                }
            }
            /*--end*/

            /*栏目对应的单页表*/
            $tableExt = PREFIX . 'single_content';
            /*--end*/

            /*检测字段是否存在于主表与附加表中*/
            if (true == $this->fieldLogic->checkChannelFieldList($tableExt, $post['name'], 6)) {
                $this->error("字段名称 " . $post['name'] . " 与系统字段冲突！");
            }
            /*--end*/
            if ("checkbox" == $post['dtype']){
                $dfvalue = explode(',', $dfvalue);
                if (64 < count($dfvalue)){
                    $dfvalue = array_slice($dfvalue, 0, 64);
                }
                $dfvalue = implode(',', $dfvalue);
            }
            /*组装完整的SQL语句，并执行新增字段*/
            $fieldinfos = $this->fieldLogic->GetFieldMake($post['dtype'], $post['name'], $dfvalue, $post['title']);
            $ntabsql    = $fieldinfos[0];
            $buideType  = $fieldinfos[1];
            $maxlength  = $fieldinfos[2];
            $table      = PREFIX . 'arctype';
            $sql        = " ALTER TABLE `$table` ADD  $ntabsql ";
            if (false !== Db::execute($sql)) {
                /*保存新增字段的记录*/
                $newData = array(
                    'dfvalue'     => $dfvalue,
                    'maxlength'   => $maxlength,
                    'define'      => $buideType,
                    'ifmain'      => 1,
                    'ifsystem'    => 0,
                    'sort_order'  => 100,
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                );
                $data    = array_merge($post, $newData);
                $field_id = Db::name('channelfield')->insertGetId($data);
                /*--end*/

                /*保存栏目与字段绑定的记录*/
                $typeids = $post['typeids'];
                if (!empty($typeids)) {
                    /*多语言*/
                    if (is_language()) {
                        $attr_name_arr = [];
                        foreach ($typeids as $key => $val) {
                            $attr_name_arr[] = 'tid' . $val;
                        }
                        $new_typeid_arr = Db::name('language_attr')->where([
                            'attr_name' => ['IN', $attr_name_arr],
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        !empty($new_typeid_arr) && $typeids = $new_typeid_arr;
                    }
                    /*--end*/
                    $addData = [];
                    foreach ($typeids as $key => $val) {
                        if (1 < count($typeids) && empty($val)) {
                            continue;
                        }
                        $addData[] = [
                            'typeid' => $val,
                            'field_id' => $field_id,
                            'add_time' => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }

                /*重新生成数据表字段缓存文件*/
                try {
                    schemaTable($table);
                } catch (\Exception $e) {}
                /*--end*/

                \think\Cache::clear('channelfield');
                \think\Cache::clear("arctype");
                $this->success("操作成功！", url('Field/arctype_index'));
            }
            $this->error('操作失败');
        }

        /*字段类型列表*/
        $fieldtype_list = [];
        $fieldtype_list_tmp = model('Field')->getFieldTypeAll('name,title,ifoption');
        foreach ($fieldtype_list_tmp as $key => $val) {
            if (!in_array($val['name'], ['file','media','region'])) {
                $fieldtype_list[] = $val;
            }
        }
        $assign_data['fieldtype_list'] = $fieldtype_list;
        /*--end*/

        /*模型ID*/
        $assign_data['channel_id'] = $channel_id;
        /*--end*/

        /*允许编辑的栏目*/
        $allow_release_channel = Db::name('channeltype')->column('id');
        $select_html = allow_release_arctype(0, $allow_release_channel);
        $this->assign('select_html', $select_html);
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 编辑-栏目字段
     */
    public function arctype_edit()
    {
        $channel_id = $this->arctype_channel_id;
        if (empty($channel_id)) {
            $this->error('参数有误！');
        }

        if (IS_POST) {
            $post = input('post.', '', 'trim');
            $post['id'] = intval($post['id']);

            if ('checkbox' == $post['old_dtype'] && in_array($post['dtype'], ['radio', 'select'])) {
                $fieldtype_list = model('Field')->getFieldTypeAll('name,title', 'name');
                $this->error("{$fieldtype_list['checkbox']['title']}不能更改为{$fieldtype_list[$post['dtype']]['title']}！");
            }

            if (empty($post['dtype']) || empty($post['title']) || empty($post['name'])) {
                $this->error("缺少必填信息！");
            }

            if (!preg_match('/^(\w)+$/', $post['name']) || 1 == preg_match('/^([_]+|[0-9]+)$/', $post['name'])) {
                $this->error("字段名称格式不正确！");
            } else if (preg_match('/^ey_/', $post['name'])) {
                $this->error("字段名称不允许以 ey_ 开头！");
            }
            
            if (!is_string($post['dtype'])) {
                $this->error("数据类型不正确！");
            }

            $info = model('Channelfield')->getInfoByWhere(['id'=>$post['id'],'channel_id'=>$channel_id], 'ifsystem');
            if (!empty($info['ifsystem'])) {
                $this->error('系统字段不允许更改！');
            }

            $old_name = $post['old_name'];
            /*去除中文逗号，过滤左右空格与空值*/
            $dfvalue    = str_replace('，', ',', $post['dfvalue']);
            if (in_array($post['dtype'], ['radio','checkbox','select','region'])) {
                $pattern    = ['"', '\'', ';', '&', '?', '='];
                $dfvalue    = func_preg_replace($pattern, '', $dfvalue);
            }
            $dfvalueArr = explode(',', $dfvalue);
            foreach ($dfvalueArr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($dfvalueArr[$key]);
                    continue;
                }
                $dfvalueArr[$key] = $tmp_val;
            }
            $dfvalueArr = array_unique($dfvalueArr);
            $dfvalue = implode(',', $dfvalueArr);
            /*--end*/

            /*默认值必填字段*/
            $fieldtype_list = model('Field')->getFieldTypeAll('name,title,ifoption', 'name');
            if (isset($fieldtype_list[$post['dtype']]) && 1 == $fieldtype_list[$post['dtype']]['ifoption']) {
                if (empty($dfvalue)) {
                    $this->error("你设定了字段为【" . $fieldtype_list[$post['dtype']]['title'] . "】类型，默认值不能为空！ ");
                }
            }
            /*--end*/

            /*栏目对应的单页表*/
            $tableExt = PREFIX . 'single_content';
            /*--end*/

            /*检测字段是否存在于主表与附加表中*/
            if (true == $this->fieldLogic->checkChannelFieldList($tableExt, $post['name'], 6, array($old_name))) {
                $this->error("字段名称 " . $post['name'] . " 与系统字段冲突！");
            }
            /*--end*/

            /*针对单选项、多选项、下拉框：修改之前，将该字段不存在的值都更新为默认值第一个*/
            if (in_array($post['old_dtype'], ['radio', 'select', 'checkbox']) && in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                $whereArr = [];
                $dfvalueArr = explode(',', $dfvalue);
                foreach($dfvalueArr as $key => $val){
                    $whereArr[] = "CONCAT(',', `{$post['name']}` ,',') NOT LIKE '%,{$val},%'";
                }
                $whereStr = implode(' OR ', $whereArr);
                if (in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                    if (!empty($dfvalueArr[0])) {
                        $new_dfvalue = $dfvalueArr[0];
                        $old_dfvalue_arr = explode(',', $post['old_dfvalue']);
                        if (!in_array($new_dfvalue, $old_dfvalue_arr)) {
                            $new_dfvalue = NULL;
                        }
                    } else {
                        $new_dfvalue = NULL;
                    }
                } else {
                    $new_dfvalue = '';
                }
                Db::name('single_content')->where($whereStr)->update([$post['name']=>$new_dfvalue]);
            }
            /*end*/
            if ("checkbox" == $post['dtype']){
                $dfvalue = explode(',', $dfvalue);
                if (64 < count($dfvalue)){
                    $dfvalue = array_slice($dfvalue, 0, 64);
                }
                $dfvalue = implode(',', $dfvalue);
            }
            /*组装完整的SQL语句，并执行编辑字段*/
            $fieldinfos = $this->fieldLogic->GetFieldMake($post['dtype'], $post['name'], $dfvalue, $post['title']);
            $ntabsql    = $fieldinfos[0];
            $buideType  = $fieldinfos[1];
            $maxlength  = $fieldinfos[2];
            $table      = PREFIX . 'arctype';
            $sql        = " ALTER TABLE `$table` CHANGE COLUMN `{$old_name}` $ntabsql ";
            if (false !== Db::execute($sql)) {

                /*针对单选项、多选项、下拉框：修改之前，将该字段不存在的值都更新为默认值第一个*/
                if (in_array($post['old_dtype'], ['radio', 'select', 'checkbox']) && in_array($post['dtype'], ['radio', 'select', 'checkbox'])) {
                    $whereArr = [];
                    $new_dfvalue = '';
                    $dfvalueArr = explode(',', $dfvalue);
                    foreach($dfvalueArr as $key => $val){
                        if ($key == 0) {
                            $new_dfvalue = $val;
                        }
                        $whereArr[] = "CONCAT(',', `{$post['name']}` ,',') NOT LIKE '%,{$val},%'";
                    }
                    $whereArr[] = "`{$post['name']}` is NULL";
                    $whereArr[] = "`{$post['name']}` = ''";
                    $whereStr = implode(' OR ', $whereArr);
                    Db::name('single_content')->where($whereStr)->update([$post['name']=>$new_dfvalue]);
                }
                /*end*/

                /*保存更新字段的记录*/
                $newData = array(
                    'dfvalue'     => $dfvalue,
                    'maxlength'   => $maxlength,
                    'define'      => $buideType,
                    'ifmain'      => 1,
                    'ifsystem'    => 0,
                    'update_time' => getTime(),
                );
                $data    = array_merge($post, $newData);
                Db::name('channelfield')->where(['id'=>$post['id'],'channel_id'=>$channel_id])->cache(true, null, "channelfield")->save($data);
                /*--end*/

                /*保存栏目与字段绑定的记录*/
                $field_id = $post['id'];
                model('ChannelfieldBind')->where(['field_id' => $field_id])->delete();
                $typeids = $post['typeids'];
                if (!empty($typeids)) {
                    /*多语言*/
                    if (is_language()) {
                        $attr_name_arr = [];
                        foreach ($typeids as $key => $val) {
                            $attr_name_arr[] = 'tid' . $val;
                        }
                        $new_typeid_arr = Db::name('language_attr')->where([
                            'attr_name'  => ['IN', $attr_name_arr],
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        !empty($new_typeid_arr) && $typeids = $new_typeid_arr;
                    }
                    /*--end*/
                    $addData = [];
                    foreach ($typeids as $key => $val) {
                        if (1 < count($typeids) && empty($val)) {
                            continue;
                        }
                        $addData[] = [
                            'typeid'      => $val,
                            'field_id'    => $field_id,
                            'add_time'    => getTime(),
                            'update_time' => getTime(),
                        ];
                    }
                    !empty($addData) && model('ChannelfieldBind')->saveAll($addData);
                }
                /*--end*/

                /*重新生成数据表字段缓存文件*/
                try {
                    schemaTable($table);
                } catch (\Exception $e) {}
                /*--end*/

                \think\Cache::clear("arctype");
                $this->success("操作成功！", url('Field/arctype_index'));
            } else {
                $sql = " ALTER TABLE `$table` ADD  $ntabsql ";
                if (false === Db::execute($sql)) {
                    $this->error('操作失败！');
                }
            }
        }

        $id   = input('param.id/d', 0);
        $info = model('Channelfield')->getInfoByWhere(['id'=>$id,'channel_id'=>$channel_id]);
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        if (!empty($info['ifsystem'])) {
            $this->error('系统字段不允许更改！');
        }
        $assign_data['info'] = $info;

        /*字段类型列表*/
        $fieldtype_list = [];
        $fieldtype_list_tmp = model('Field')->getFieldTypeAll('name,title,ifoption');
        foreach ($fieldtype_list_tmp as $key => $val) {
            if (!in_array($val['name'], ['file','media','region'])) {
                $fieldtype_list[] = $val;
            }
        }
        $assign_data['fieldtype_list'] = $fieldtype_list;
        /*--end*/

        $assign_data['channel_id'] = $channel_id; //模型ID

        /*允许编辑的栏目*/
        $typeids     = Db::name('channelfield_bind')->where(['field_id' => $id])->column('typeid');
        $allow_release_channel = Db::name('channeltype')->column('id');
        $select_html = allow_release_arctype($typeids, $allow_release_channel);
        $this->assign('select_html', $select_html);
        $this->assign('typeids', $typeids);
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除-栏目字段
     */
    public function arctype_del()
    {
        $channel_id = $this->arctype_channel_id;
        $id         = input('del_id/d', 0);
        if (!empty($id)) {
            /*删除表字段*/
            $row = $this->fieldLogic->delArctypeField($id);
            /*--end*/
            if (0 < $row['code']) {
                $map       = array(
                    'id'         => array('eq', $id),
                    'channel_id' => $channel_id,
                );
                $result    = Db::name('channelfield')->field('channel_id,name')->where($map)->select();
                $name_list = get_arr_column($result, 'name');
                Db::name('channelfield')->where($map)->delete();//删除字段的记录
                Db::name('channelfield_bind')->where('field_id',$id)->delete();//删除字段绑定记录

                adminLog('删除栏目字段：' . implode(',', $name_list));
                $this->success('删除成功');
            }

            \think\Cache::clear('channelfield');
            \think\Cache::clear("arctype");
            respose(array('status' => 0, 'msg' => $row['msg']));

        } else {
            $this->error('参数有误');
        }
    }

    //留言表单表单列表
    public function attribute_index()
    {
        $assign_data = array();
        $condition   = array();
        $get    = input('get.');
        $typeid = input('typeid/d');

        foreach (['keywords', 'typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$get[$key]}%");
                } else if ($key == 'typeid') {
                    $typeids               = model('Arctype')->getHasChildren($get[$key]);
                    $condition['a.typeid'] = array('IN', array_keys($typeids));
                } else {
                    $condition['a.' . $key] = array('eq', $get[$key]);
                }
            }
        }

        $condition['a.form_type'] = 0;
        $condition['b.id']     = ['gt', 0];
        $condition['a.is_del'] = 0;
        $condition['a.lang'] = $this->admin_lang;

        $count = Db::name('guestbook_attribute')->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->count();
        $Page  = new Page($count, config('paginate.list_rows'));
        $list  = Db::name('guestbook_attribute')
            ->field("a.attr_id")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.typeid desc, a.sort_order asc, a.attr_id asc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('attr_id');

        if ($list) {
            $attr_ida = array_keys($list);
            $fields   = "b.*, a.*";
            $row      = Db::name('guestbook_attribute')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.attr_id', 'in', $attr_ida)
                ->getAllWithIndex('attr_id');

            //获取多语言关联绑定的值
            $row = model('LanguageAttr')->getBindValue($row, 'guestbook_attribute', $this->main_lang);

            foreach ($row as $key => $val) {
                $val['fieldname'] = 'attr_' . $val['attr_id'];
                $row[$key]        = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key] = $row[$val['attr_id']];
            }
        }
        $show                 = $Page->show();
        $assign_data['page']  = $show;
        $assign_data['list']  = $list;
        $assign_data['pager'] = $Page;

        //获取当前模型栏目
        $select_html = allow_release_arctype($typeid, array(8));
        $typeidNum   = substr_count($select_html, '</option>');
        $this->assign('select_html', $select_html);
        $this->assign('typeidNum', $typeidNum);

        $assign_data['typeid'] = $typeid;
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        $assign_data['attrInputTypeArr'] = config('global.guestbook_attr_input_type'); // 表单类型

        //留言模型的栏目数量
        $assign_data['arctypeCount'] = Db::name('arctype')->where([
                'current_channel'   => 8,
                'is_del'    => 0,
                'lang'  => $this->admin_lang,
            ])->count();

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

        $this->language_access(); // 多语言功能操作权限

        if (IS_AJAX && IS_POST)//ajax提交验证
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
                'sort_order'      => 100,
                'is_showlist'     => $post_data['is_showlist'],
                'required'        => $post_data['required'],
                'real_validate'   => $post_data['real_validate'],
                'validate_type'   => $validate_type,
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
            if (!$validate->batch()->check($savedata)) {
                $error      = $validate->getError();
                $error_msg  = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata, true);// 收集数据
                $model->save(); // 写入数据到数据库

                $insertId = $model->getLastInsID();

                /*同步留言属性ID到多语言的模板变量里*/
                model('GuestbookAttribute')->syn_add_language_attribute($insertId);
                /*--end*/

                adminLog('新增留言表单：' . $savedata['attr_name']);

                $url = url('Field/attribute_index', array('typeid' => $post_data['typeid']));
                $this->success('操作成功', null, ['url'=>$url]);
            }
        }

        $typeid = input('param.typeid/d', 0);
        if ($typeid > 0) {
            $select_html = Db::name('arctype')->where('id', $typeid)->getField('typename');
            $select_html = !empty($select_html) ? $select_html : '该栏目不存在';
        } else {
            $select_html = allow_release_arctype($typeid, array(8));
        }
        $assign_data['select_html'] = $select_html; // 
        $assign_data['typeid']      = $typeid; // 栏目ID

        $assign_data['attrInputTypeArr'] = config('global.guestbook_attr_input_type'); // 表单类型
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
        if (IS_AJAX && IS_POST)//ajax提交验证
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

            $post_data                = input('post.');
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
            if (!$validate->batch()->check($savedata)) {
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
                    'lang'    => $this->admin_lang,
                ])->save(); // 写入数据到数据库

                adminLog('编辑留言表单：' . $savedata['attr_name']);

                $url = url('Field/attribute_index', array('typeid' => intval($post_data['typeid'])));
                $this->success('操作成功', null, ['url'=>$url]);
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
            'lang'    => $this->admin_lang,
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

        $assign_data['attrInputTypeArr'] = config('global.guestbook_attr_input_type'); // 表单类型
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
        $this->language_access(); // 多语言功能操作权限

        $id_arr = input('del_id/a');
        $thorough = input('thorough/d');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            //多语言
            if (is_language()) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    $attr_name_arr[] = 'attr_' . $val;
                }
                $new_id_arr = Db::name('language_attr')->where([
                    'attr_name'  => ['IN', $attr_name_arr],
                    'attr_group' => 'guestbook_attribute',
                ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            if (1 == $thorough){
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
                    'update_time' => getTime(),
                ]);
            }
            if ($r) {
                adminLog('删除留言表单-id：' . implode(',', $id_arr));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 检测列表显示字段数量是都超过4个
     */
    public function ajax_attribute_show()
    {
        if (IS_AJAX_POST) {
            $typeid  = input('post.typeid/d');
            $is_showlist  = input('post.is_showlist/d');
            if ($is_showlist == 1){
                $count = Db::name('guestbook_attribute')->where([
                        'typeid' => $typeid,
                        'is_showlist' => $is_showlist,
                        'is_del'   => 0,
                        'lang'   => $this->admin_lang,
                    ])->count();
                if ($count >= 4) {
                    $this->error('所属栏目的列表字段显示数量已达4个');
                } else {
                    $this->success('正常');
                }
            }
            $this->success('正常');
        }
        $this->error('非法访问');
    }
}