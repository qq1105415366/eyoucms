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

use think\Page;
use think\Db;
use think\Cache;
use app\common\logic\ArctypeLogic;
// use app\admin\logic\RecycleBinLogic;

/**
 * 模型字段控制器
 */
class RecycleBin extends Base
{
    public $recyclebinLogic;
    public $arctype_channel_id;
    public $arctypeLogic;

    public function _initialize() {
        parent::_initialize();
        if (empty($this->globalConfig['language_split'])) {
            if (!preg_match('/^(arctype|archives|gbookattr)_/i', ACTION_NAME)) {
                $this->language_access(); // 多语言功能操作权限
            }
        }
        $this->arctypeLogic = new ArctypeLogic(); 
        // $this->recyclebinLogic = new RecycleBinLogic();
        
        $this->arctype  = Db::name('arctype');  // 栏目数据表
        $this->archives = Db::name('archives'); // 内容数据表
        $this->config   = Db::name('config');   // 配置数据表
        $this->config_attribute     = Db::name('config_attribute');     // 自定义变量数据表
        $this->product_attribute    = Db::name('product_attribute');    // 产品属性数据表
        $this->product_attr         = Db::name('product_attr');         // 产品属性内容表
        $this->guestbook_attribute  = Db::name('guestbook_attribute');  // 留言表单数据表
        $this->guestbook_attr         = Db::name('guestbook_attr');         // 留言表单内容表
        $this->arctype_channel_id = config('global.arctype_channel_id');

        /*产品参数 - 新旧兼容*/
        $is_old_product_attr = tpSetting('system.system_old_product_attr', [], 'cn');
        $this->assign('is_old_product_attr',$is_old_product_attr);
        /*--end*/

        $menu = input('param.menu/d', 0);
        $this->assign('menu',$menu);
    }

    /**
     * 回收站管理 - 栏目列表
     */
    public function arctype_index()
    {
        $list = array();
        $keywords = input('keywords/s');

        $condition = array();
        // 应用搜索条件
        if (!empty($keywords)) {
            $condition['a.typename'] = array('LIKE', "%{$keywords}%");
        }

        $condition['a.is_del'] = 1;
        $condition['a.lang']    = $this->admin_lang;

        $count = $this->arctype->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $this->arctype->alias('a')
            ->field('a.id, a.typename, a.current_channel, a.update_time')
            ->where($condition)
            ->order('a.update_time desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $pageStr = $pageObj->show();// 分页显示输出
        $this->assign('page',$pageStr);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pageObj);// 赋值分页对象

        // 模型列表
        $channeltype_list = getChanneltypeList();
        $this->assign('channeltype_list', $channeltype_list);

        return $this->fetch();
    }

    /**
     * 回收站管理 - 栏目还原
     */
    public function arctype_recovery()
    {
        if (IS_POST) {
            $del_id = input('post.del_id/d', 0);
            if(!empty($del_id)){
                // 当前栏目信息
                $row = $this->arctype->field('id, parent_id, current_channel, typename')
                    ->where([
                        'id'    => $del_id,
                        'is_del'=> 1,
                    ])
                    ->find();

                if ($row) {
                    $id_arr = [$row['id']];
                    // 多语言处理逻辑
                    $attr_name_arr = 'tid'.$row['id'];
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $id_arr = Db::name('language_attr')->where([
                                'attr_name'  => $attr_name_arr,
                                'attr_group' => 'arctype',
                            ])->column('attr_value');
                        $list = $this->arctype->field('id,del_method,parent_id')
                            ->where([
                                'id' => ['IN', $id_arr],
                            ])
                            ->whereOr([
                                'parent_id' => ['IN', $id_arr],
                            ])->select();
                    }else{
                        $list = $this->arctype->field('id,del_method,parent_id')
                        ->where([
                            'parent_id' => ['IN', $id_arr],
                        ])
                        ->select();
                    }
                }else{
                    $this->error('参数错误');
                }
                
                // 需要更新的数据
                $data['is_del']     = 0; // is_del=0为正常数据
                $data['update_time']= getTime(); // 更新修改时间
                $data['del_method'] = 0; // 恢复删除方式为默认

                // 还原数据条件逻辑
                // 栏目逻辑
                $map = 'is_del=1';
                // 多语言处理逻辑
                if (is_language() && empty($this->globalConfig['language_split'])) {
                    $where = $map.' and (';
                    $ids = get_arr_column($list, 'id');
                    !empty($ids) && $where .= 'id IN ('.implode(',', $ids).') OR parent_id IN ('.implode(',', $ids).')';
                }else{
                    $where  = $map.' and (id='.$del_id.' or parent_id='.$del_id;
                    if (0 == intval($row['parent_id'])) {
                        foreach ($list as $value) {
                            if (2 == intval($value['del_method'])) {
                                $where .= ' or parent_id='.$value['id'];
                            }
                        }
                    }
                }

                $where .= ')';
                // 文章逻辑
                $where1 = $map.' and typeid in (';
                // 栏目数据更新
                $arctype = $this->arctype->where($where)->select();
                foreach ($arctype as $key => $value) {
                    $where = 'is_del=1 and ';

                    if (0 == intval($value['parent_id'])) {
                        $where .= 'id='.$value['id'];
                    }else if(0 < intval($value['parent_id'])){
                        $where .= '(id='.$value['id'].' or id='.$value['parent_id'].')';
                    }

                    if (!in_array($value['id'], $id_arr)) {
                        $where .= ' and del_method=2'; // 不是当前栏目或对应的多语言栏目，则只还原被动删除栏目
                    }

                    $this->arctype->where($where)->update($data);

                    // 还原父级栏目，不还原主动删除的子栏目下的文档
                    if (in_array($value['id'], $id_arr) || 2 == intval($value['del_method'])) {
                        $where1 .= $value['id'].',';
                    }
                }
                $where1 = rtrim($where1,',');
                $where1 .= ') and del_method=2';

                // 还原三级栏目时需要一并还原顶级栏目
                // 多语言处理逻辑
                if (is_language() && empty($this->globalConfig['language_split'])) {
                    foreach ($list as $key => $value) {
                        $parent_id = intval($value['parent_id']);
                        if (0 < $parent_id) {
                            $where = 'id='.$parent_id;
                            $r1 = $this->arctype->where($where)->find();
                            $parent_id = intval($r1['parent_id']);
                            if (0 < $parent_id) {
                                $where = 'is_del=1 and id='.$parent_id;
                                $this->arctype->where($where)->update($data);
                            }
                        }
                    }
                }else{
                    $parent_id = intval($arctype['0']['parent_id']);
                    if (0 < $parent_id) {
                        $where = 'id='.$parent_id;
                        $r1 = $this->arctype->where($where)->find();
                        $parent_id = intval($r1['parent_id']);
                        if (0 < $parent_id) {
                            $where = 'is_del=1 and id='.$parent_id;
                            $this->arctype->where($where)->update($data);
                        }
                    }
                }

                // 内容数据更新 -  还原父级栏目，不还原主动删除的子栏目下的文档
                $r = $this->archives->where($where1)->update($data);

                if (false !== $r) {
                    // 还原多语言栏目关联绑定
                    if (!empty($attr_name_arr)) {
                        Db::name('language_attribute')->where([
                                'attr_name'     => ['IN',$attr_name_arr],
                                'attr_group'    => 'arctype',
                            ])->update([
                                'is_del'    => 0,
                                'update_time'   => getTime(),
                            ]);
                    }
                    /*--end*/
                    Cache::clear();
                    adminLog('还原栏目：'.$row['typename']);
                    /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
                    Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
                    model('SqlCacheTable')->InsertSqlCacheTable(true);
                    /* END */
                    model('Arctype')->hand_type_count(['typeid'=>[$del_id]]);//统计栏目文档数量

                    $this->success('操作成功');
                }
            }
            $this->error('操作失败');
        }
        $this->error('非法访问');
    }

    /**
     * 回收站管理 - 栏目批量还原
     */
    public function batch_arctype_recovery()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!isset($post['del_id']) || empty($post['del_id'])) $this->error('未选择栏目');
            $post['del_id'] = eyIntval($post['del_id']);
            $typename = '';
            foreach($post['del_id'] as $k=>$v){
                // 当前栏目信息
                $row = $this->arctype->field('id, parent_id, current_channel, typename')
                    ->where([
                        'id'    => $v,
//                        'is_del'=> 1,
                    ])
                    ->find();

                if ($row) {
                    $id_arr = [$row['id']];
                    // 多语言处理逻辑
                    $attr_name_arr = 'tid'.$row['id'];
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $id_arr = Db::name('language_attr')->where([
                            'attr_name'  => $attr_name_arr,
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        $list = $this->arctype->field('id,del_method,parent_id')
                            ->where([
                                'id' => ['IN', $id_arr],
                            ])
                            ->whereOr([
                                'parent_id' => ['IN', $id_arr],
                            ])->select();
                    }else{
                        $list = $this->arctype->field('id,del_method,parent_id')
                            ->where([
                                'parent_id' => ['IN', $id_arr],
                            ])
                            ->select();
                    }
                }else{
                    $this->error('参数错误');
                }

                // 需要更新的数据
                $data['is_del']     = 0; // is_del=0为正常数据
                $data['update_time']= getTime(); // 更新修改时间
                $data['del_method'] = 0; // 恢复删除方式为默认

                // 还原数据条件逻辑
                // 栏目逻辑
                //$map = 'is_del=1';
                $map = '1=1';
                // 多语言处理逻辑
                if (is_language() && empty($this->globalConfig['language_split'])) {
                    $where = $map.' and (';
                    $ids = get_arr_column($list, 'id');
                    !empty($ids) && $where .= 'id IN ('.implode(',', $ids).') OR parent_id IN ('.implode(',', $ids).')';
                }else{
                    $where  = $map.' and (id='.$v.' or parent_id='.$v;
                    if (0 == intval($row['parent_id'])) {
                        foreach ($list as $value) {
                            if (2 == intval($value['del_method'])) {
                                $where .= ' or parent_id='.$value['id'];
                            }
                        }
                    }
                }

                $where .= ')';
                // 文章逻辑
                $where1 = $map.' and typeid in (';
                // 栏目数据更新
                $arctype = $this->arctype->where($where)->select();

                foreach ($arctype as $key => $value) {
//                    $where = 'is_del=1 and ';
                    $where = '1=1 and ';

                    if (0 == intval($value['parent_id'])) {
                        $where .= 'id='.$value['id'];
                    }else if(0 < intval($value['parent_id'])){
                        $where .= '(id='.$value['id'].' or id='.$value['parent_id'].')';
                    }

                    if (!in_array($value['id'], $id_arr)) {
                        $where .= ' and del_method=2'; // 不是当前栏目或对应的多语言栏目，则只还原被动删除栏目
                    }

                    $this->arctype->where($where)->update($data);

                    // 还原父级栏目，不还原主动删除的子栏目下的文档
                    if (in_array($value['id'], $id_arr) || 2 == intval($value['del_method'])) {
                        $where1 .= $value['id'].',';
                    }
                }
                $where1 = rtrim($where1,',');
                $where1 .= ') and del_method=2';

                // 还原三级栏目时需要一并还原顶级栏目
                // 多语言处理逻辑
                if (is_language() && empty($this->globalConfig['language_split'])) {
                    foreach ($list as $key => $value) {
                        $parent_id = intval($value['parent_id']);
                        if (0 < $parent_id) {
                            $where = 'id='.$parent_id;
                            $r1 = $this->arctype->where($where)->find();
                            $parent_id = intval($r1['parent_id']);
                            if (0 < $parent_id) {
                                $where = '1=1 and id='.$parent_id;
                                $this->arctype->where($where)->update($data);
                            }
                        }
                    }
                }else{
                    $parent_id = intval($arctype['0']['parent_id']);
                    if (0 < $parent_id) {
                        $where = 'id='.$parent_id;
                        $r1 = $this->arctype->where($where)->find();
                        $parent_id = intval($r1['parent_id']);
                        if (0 < $parent_id) {
                            $where = '1=1 and id='.$parent_id;
                            $this->arctype->where($where)->update($data);
                        }
                    }
                }

                // 内容数据更新 -  还原父级栏目，不还原主动删除的子栏目下的文档
                $r = $this->archives->where($where1)->update($data);

                if (false !== $r) {
                    // 还原多语言栏目关联绑定
                    if (!empty($attr_name_arr)) {
                        Db::name('language_attribute')->where([
                            'attr_name'     => ['IN',$attr_name_arr],
                            'attr_group'    => 'arctype',
                        ])->update([
                            'is_del'    => 0,
                            'update_time'   => getTime(),
                        ]);
                    }
                    /*--end*/
                    Cache::clear();
                }
                $typename .= $row['typename'].',';
            }
            adminLog('还原栏目：'.trim($typename,','));
            /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
            /* END */
            model('Arctype')->hand_type_count(['typeid'=>$post['del_id']]);//统计栏目文档数量

            $this->success('操作成功');
        }
        $this->error('非法访问');
    }

    /**
     * 回收站管理 - 栏目删除
     */
    public function arctype_del()
    {
        if (IS_POST) {
            $del_id = input('post.del_id/d', 0);
            if(!empty($del_id)){
                // 当前栏目信息
                $row = $this->arctype->field('id, parent_id, current_channel, typename')
                    ->where([
                        'id'    => $del_id,
                        'is_del'=> 1,
                    ])
                    ->find();
                if ($row) {
                    $id_arr = $row['id'];
                    $attr_name_arr = [];
                    $attr_name_arr[] = 'tid'.$row['id'];
                    // 多语言处理逻辑
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $id_arr_tmp = Db::name('language_attr')->where([
                                'attr_name'  => ['IN',$attr_name_arr],
                                'attr_group' => 'arctype',
                            ])->column('attr_value');
                        if (!empty($id_arr_tmp)) {
                            $id_arr = $id_arr_tmp;
                        }
                        $list = $this->arctype->field('id,del_method')
                            ->where([
                                'id' => ['IN', $id_arr],
                            ])
                            ->whereOr([
                                'parent_id' => ['IN', $id_arr],
                            ])->select();
                    }else{
                        $list = $this->arctype->field('id,del_method')
                        ->where([
                            'parent_id' => ['IN', $id_arr],
                        ])
                        ->select();
                    }
                }else{
                    $this->error('参数错误');
                }

                // 删除条件逻辑
                // 栏目逻辑
                $map = 'is_del=1';
                // 多语言处理逻辑
                if (is_language() && empty($this->globalConfig['language_split'])) {
                    $where = $map.' and (';
                    $ids = get_arr_column($list, 'id');
                    !empty($ids) && $where .= 'id IN ('.implode(',', $ids).') OR parent_id IN ('.implode(',', $ids).')';
                }else{
                    $ids = [$del_id];
                    $where  = $map.' and (id='.$del_id.' or parent_id='.$del_id;
                    if (0 == intval($row['parent_id'])) {
                        foreach ($list as $value) {
                            if (2 == intval($value['del_method'])) {
                                $where .= ' or parent_id='.$value['id'];
                            }
                        }
                    }
                }
                $where .= ')';

                // 文章逻辑
                $where1 = $map.' and typeid in (';

                // 查询栏目回收站数据并拼装删除文章逻辑
                $arctype  = $this->arctype->field('id')->where($where)->select();
                foreach ($arctype as $key => $value) {
                    $where1 .= $value['id'].',';
                }
                $where1 = rtrim($where1,',');
                $where1 .= ')';
                
                // 栏目数据删除
                $r = $this->arctype->where($where)->delete();
                if($r !== false){
                    model('Archives')->del($ids); // 删除文档
                    // 删除多语言栏目关联绑定
                    if (!empty($attr_name_arr)) {
                        if (get_admin_lang() == get_main_lang()) {
                            Db::name('language_attribute')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'arctype',
                                ])->delete();
                        }
                        if (empty($this->globalConfig['language_split'])) {
                            Db::name('language_attr')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'arctype',
                                ])->delete();
                        } else {
                            Db::name('language_attr')->where([
                                    'attr_value' => ['IN', $ids],
                                    'attr_group'    => 'arctype',
                                ])->delete();
                        }
                    }
                    /*--end*/
                    \think\Cache::clear('taglist');
                    \think\Cache::clear('archives');
                    \think\Cache::clear('arctype');
                    adminLog('删除栏目：'.$row['typename']);
                    $this->success("操作成功");
                }
            }
        }
        $this->error('操作失败');
    }

    /**
     * 回收站管理 - 栏目批量删除
     */
    public function batch_arctype_del()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!isset($post['del_id']) || empty($post['del_id'])) $this->error('未选择栏目');
            $post['del_id'] = eyIntval($post['del_id']);
            $typename = '';
            foreach($post['del_id'] as $k=>$v){
                // 当前栏目信息
                $row = $this->arctype->field('id, parent_id, current_channel, typename')
                    ->where([
                        'id'    => $v,
//                        'is_del'=> 1,
                    ])
                    ->find();
                if ($row) {
                    $id_arr = $row['id'];
                    $attr_name_arr = [];
                    $attr_name_arr[] = 'tid'.$row['id'];
                    // 多语言处理逻辑
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $id_arr_tmp = Db::name('language_attr')->where([
                            'attr_name'  => $attr_name_arr,
                            'attr_group' => 'arctype',
                        ])->column('attr_value');
                        if (!empty($id_arr_tmp)) {
                            $id_arr = $id_arr_tmp;
                        }
                        $list = $this->arctype->field('id,del_method')
                            ->where([
                                'id' => ['IN', $id_arr],
                            ])
                            ->whereOr([
                                'parent_id' => ['IN', $id_arr],
                            ])->select();
                    }else{
                        $list = $this->arctype->field('id,del_method')
                            ->where([
                                'parent_id' => ['IN', $id_arr],
                            ])
                            ->select();
                    }

                    // 删除条件逻辑
                    // 栏目逻辑
//                    $map = 'is_del=1';
                    $map = '1=1';
                    // 多语言处理逻辑
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $where = $map.' and (';
                        $ids = get_arr_column($list, 'id');
                        !empty($ids) && $where .= 'id IN ('.implode(',', $ids).') OR parent_id IN ('.implode(',', $ids).')';
                    }else{
                        $ids = [$v];
                        $where  = $map.' and (id='.$v.' or parent_id='.$v;
                        if (0 == intval($row['parent_id'])) {
                            foreach ($list as $value) {
                                if (2 == intval($value['del_method'])) {
                                    $where .= ' or parent_id='.$value['id'];
                                }
                            }
                        }
                    }
                    $where .= ')';

                    // 文章逻辑
                    $where1 = $map.' and typeid in (';

                    // 查询栏目回收站数据并拼装删除文章逻辑
                    $arctype  = $this->arctype->field('id')->where($where)->select();
                    foreach ($arctype as $key => $value) {
                        $where1 .= $value['id'].',';
                    }
                    $where1 = rtrim($where1,',');
                    $where1 .= ')';

                    // 栏目数据删除
                    $r = $this->arctype->where($where)->delete();
                    if($r !== false){
                        // Tag标签删除
                        Db::name('taglist')->where([
                            'typeid'    => ['IN', $ids],
                        ])->delete();
                        // 内容数据删除
                        $this->archives->where($where1)->delete();
                        // 删除多语言栏目关联绑定
                        if (!empty($attr_name_arr)) {
                            if (get_admin_lang() == get_main_lang()) {
                                Db::name('language_attribute')->where([
                                        'attr_name' => ['IN', $attr_name_arr],
                                        'attr_group'    => 'arctype',
                                    ])->delete();
                            }
                            if (empty($this->globalConfig['language_split'])) {
                                Db::name('language_attr')->where([
                                        'attr_name' => ['IN', $attr_name_arr],
                                        'attr_group'    => 'arctype',
                                    ])->delete();
                            } else {
                                Db::name('language_attr')->where([
                                        'attr_value' => ['IN', $ids],
                                        'attr_group'    => 'arctype',
                                    ])->delete();
                            }
                        }
                        /*--end*/
                    }
                    $typename .= $row['typename'].',';
                }
            }
            \think\Cache::clear('taglist');
            \think\Cache::clear('archives');
            \think\Cache::clear('arctype');
            adminLog('删除栏目：'.trim($typename,','));
            $this->success('操作成功');
        }
        $this->error('非法访问');
    }
    
    /**
     * 回收站管理 - 内容列表
     */
    public function archives_index()
    {
        $assign_data = array();
        $condition = array();
        // 获取到所有URL参数
        $param = input('param.');

        // 应用搜索条件
        foreach (['keywords','typeid'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        $condition['a.channel'] = array('neq', 6); // 排除单页模型

        /*多语言*/
        $condition['a.lang'] = array('eq', $this->admin_lang);
        /*--end*/

        $condition['a.is_del'] = array('eq', 1); // 回收站功能

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = $this->archives->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $this->archives->field("a.aid,a.channel")
            ->alias('a')
            ->where($condition)
            ->order('a.aid desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $aids = array_keys($list);
            $fields = "a.aid, a.title, a.typeid, a.litpic, a.update_time, b.typename";
            $row = DB::name('archives')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');

            foreach ($list as $key => $val) {
                $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']); // 支持子目录
                $list[$key] = $row[$val['aid']];
            }
        }
        $pageStr = $pageObj->show(); // 分页显示输出
        $assign_data['page'] = $pageStr; // 赋值分页输出
        $assign_data['list'] = $list; // 赋值数据集
        $assign_data['pager'] = $pageObj; // 赋值分页对象

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 回收站管理 - 内容还原
     */
    public function archives_recovery()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)) {
            // 当前文档信息
            $row = $this->archives->field('aid, title, typeid')
                ->where([
                    'aid'   => ['IN', $id_arr],
                    'is_del'    => 1,
                    'lang'  => $this->admin_lang,
                ])
                ->select();
            if (!empty($row)) {
                $id_arr = get_arr_column($row, 'aid');
                // 关联的栏目ID集合
                $typeids = [];
                $typeidArr = get_arr_column($row, 'typeid');
                $typeidArr = array_unique($typeidArr);
                foreach ($typeidArr as $key => $val) {
                    $pidArr = model('Arctype')->getAllPid($val, true);
                    $typeids = array_merge($typeids, get_arr_column($pidArr, 'id'));
                }
                $typeids = array_unique($typeids);

                if (!empty($typeids)) {
                    // 多语言处理逻辑
                    if (is_language() && empty($this->globalConfig['language_split'])) {
                        $attr_name_arr = [];
                        foreach ($typeids as $key => $val) {
                            array_push($attr_name_arr, 'tid'.$val);
                        }
                        $attr_value = Db::name('language_attr')->where([
                                'attr_name'  => ['IN', $attr_name_arr],
                                'attr_group' => 'arctype',
                            ])->column('attr_value');
                        $attr_value && $typeids = $attr_value;
                    }

                    // 还原数据
                    $r = $this->arctype->where([
                            'id'    => ['IN', $typeids],
                        ])
                        ->update([
                            'is_del'    => 0,
                            'del_method'    => 0,
                            'update_time'   => getTime(),
                        ]);
                    if ($r) {
                        $r2 = $this->archives->where([
                                'aid'   => ['IN', $id_arr],
                            ])
                            ->update([
                                'is_del'    => 0,
                                'del_method'    => 0,
                                'update_time'   => getTime(),
                            ]);
                        if ($r2) {
                            $_POST['aids'] = $id_arr;
                            Cache::clear();
                            adminLog('还原文档：'.implode('|', get_arr_column($row, 'title')));
                            /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
                            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
                            model('SqlCacheTable')->InsertSqlCacheTable(true);

                            model('Arctype')->hand_type_count(['aid'=>$id_arr]);//统计栏目文档数量

                            /* END */
                            $this->success('操作成功');
                        } else {
                            $this->success('关联栏目还原成功，文档还原失败！');
                        }
                    }
                    $this->error('操作失败');
                }
            }
            $this->error('参数有误');
        }
        $this->error('非法访问');
    }

    /**
     * 回收站管理 - 内容删除
     */
    public function archives_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            if (is_dir('./weapp/CompressContent/')) {                                            
                $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                
            }   
            // 当前文档信息
            $row = $this->archives->field('aid, title, channel')
                ->where([
                    'aid'   => ['IN', $id_arr],
                    'is_del'    => 1,
                    'lang'  => $this->admin_lang,
                ])
                ->select();
            if (!empty($row)) {
                $id_arr = get_arr_column($row, 'aid');

                // 内容数据删除
                $r = $this->archives->where([
                        'aid'   => ['IN', $id_arr],
                    ])
                    ->delete();
                if($r){
                    /*按模型分组，然后进行分组删除*/
                    $row2Group = group_same_key($row, 'channel');
                    if (!empty($row2Group)) {
                        $channelids = array_keys($row2Group);
                        $channeltypeRow = Db::name('channeltype')->field('id,table,ifsystem')
                            ->where([
                                'id'    => ['IN', $channelids],
                            ])->getAllWithIndex('id');
                        foreach ($row2Group as $key => $val) {
                            $table = $channeltypeRow[$key]['table'];
                            $aidarr_tmp = get_arr_column($val, 'aid');
                            if (!empty($compressLogic)) {
                                try {                                
                                    $compressLogic->copydata(3,'','',$table, $id_arr);
                                } catch (Exception $e) {
                                }                
                            }                                
                            if (!empty($channeltypeRow[$key]['ifsystem'])){
                                model($table)->afterDel($id_arr);
                            }else{
                                model("Custom")->afterDel($id_arr,$table);
                            }

                        }
                    }
                    /*--end*/

                    adminLog('删除文档：'.implode('|', get_arr_column($row, 'title')));
                    $this->success("操作成功!");
                }
                $this->error("操作失败!");
            }
        }
        $this->error("参数有误!");
    }

    /**
     * 回收站管理 - 自定义变量列表
     */
    public function customvar_index()
    {
        $list = array();
        $keywords = input('keywords/s');

        $condition = array();
        // 应用搜索条件
        if (!empty($keywords)) {
            $condition['a.attr_name'] = array('LIKE', "%{$keywords}%");
        }

        $attr_var_names = Db::name('config')->field('name')
            ->where([
                'is_del'    => 1,
                'lang'  => $this->admin_lang,
            ])->getAllWithIndex('name');
        $condition['a.attr_var_name'] = array('IN', array_keys($attr_var_names));
        $condition['a.lang']    = $this->admin_lang;

        $count = Db::name('config_attribute')->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('config_attribute')->alias('a')
            ->field('a.*, b.id')
            ->join('__CONFIG__ b', 'b.name = a.attr_var_name AND a.lang = b.lang', 'LEFT')
            ->where($condition)
            ->order('a.update_time desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $pageStr = $pageObj->show();// 分页显示输出
        $this->assign('page',$pageStr);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pageObj);// 赋值分页对象

        return $this->fetch();
    }

    /**
     * 回收站管理 - 自定义变量还原
     */
    public function customvar_recovery()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $attr_var_name = $this->config->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                        'is_del'    => 1,
                    ])->column('name');

                $r = $this->config->where('name', 'IN', $attr_var_name)->update([
                        'is_del'    => 0,
                        'update_time'   => getTime(),
                    ]);
                if($r){
                    delFile(CACHE_PATH, true);
                    adminLog('还原自定义变量：'.implode(',', $attr_var_name));
                    $this->success("操作成功!");
                }else{
                    $this->error("操作失败!");
                }
            }
        }
        $this->error("参数有误!");
    }

    /**
     * 回收站管理 - 自定义变量删除
     */
    public function customvar_del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $attr_var_name = $this->config->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                        'is_del'    => 1,
                    ])->column('name');

                $r = $this->config->where('name', 'IN', $attr_var_name)->delete();
                if($r){
                    // 同时删除
                    $this->config_attribute->where('attr_var_name', 'IN', $attr_var_name)->delete();
                    adminLog('彻底删除自定义变量：'.implode(',', $attr_var_name));
                    $this->success("操作成功!");
                }else{
                    $this->error("操作失败!");
                }
            }
        }
        $this->error("参数有误!");
    }

    /**
     * 回收站管理 - 产品属性列表
     */
    public function proattr_index()
    {
        $list = array();
        $condition = array();
        // 获取到所有URL参数
        $param = input('param.');

        // 应用搜索条件
        foreach (['keywords','typeid'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        /*多语言*/
        $condition['a.lang'] = $this->admin_lang;
        /*--end*/

        $condition['a.is_del'] = array('eq', 1); // 回收站功能

        $count = $this->product_attribute->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $this->product_attribute->alias('a')
            ->field('a.*, b.typename')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.update_time desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $pageStr = $pageObj->show();// 分页显示输出
        $this->assign('page',$pageStr);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pageObj);// 赋值分页对象

        return $this->fetch();
    }

    /**
     * 回收站管理 - 产品属性还原
     */
    public function proattr_recovery()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){

            /*检测关联栏目是否已被伪删除*/
            $row1 = $this->product_attribute->field('attr_id, typeid')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $row2 = $this->arctype->field('typename')
                ->where([
                    'id'    => ['IN', get_arr_column($row1, 'typeid')],
                    'is_del'    => 1,
                ])
                ->select();
            if (!empty($row2)) {
                $this->error('请先还原关联栏目：<font color="red">'.implode(' , ', get_arr_column($row2, 'typename')).'</font>');
            }
            /*--end*/
            
            // 多语言处理逻辑
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    array_push($attr_name_arr, 'attr_'.$val);
                }
                $id_arr = Db::name('language_attr')->where([
                        'attr_name'  => ['IN', $attr_name_arr],
                        'attr_group' => 'product_attribute',
                    ])->column('attr_value');
            }

            $row = $this->product_attribute->field('attr_id, attr_name')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $id_arr = get_arr_column($row, 'attr_id');

            // 更新数据
            $r = $this->product_attribute->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->update([
                    'is_del'    => 0,
                    'update_time'   => getTime(),
                ]);
            if($r){
                adminLog('还原产品参数：'.implode(',', get_arr_column($row, 'attr_name')));
                $this->success("操作成功!");
            }
            $this->error("操作失败!");
        }
        $this->error("参数有误!");
    }

    /**
     * 回收站管理 - 产品属性删除
     */
    public function proattr_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            // 多语言处理逻辑
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    array_push($attr_name_arr, 'attr_'.$val);
                }
                $id_arr_tmp = Db::name('language_attr')->where([
                        'attr_name'  => ['IN', $attr_name_arr],
                        'attr_group' => 'product_attribute',
                    ])->column('attr_value');
                if (!empty($id_arr_tmp)) {
                    $id_arr = $id_arr_tmp;
                }
            }

            $row = $this->product_attribute->field('attr_id, attr_name')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $id_arr = get_arr_column($row, 'attr_id');

            // 产品属性删除
            $r = $this->product_attribute->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->delete();
            if($r){
                // 同时删除
                $this->product_attr->where([
                        'attr_id'   => ['IN', $id_arr],
                    ])->delete();
                adminLog('删除产品参数：'.implode(',', get_arr_column($row, 'attr_name')));
                $this->success("操作成功!");
            }
            $this->error("操作失败!");
        }
        $this->error("参数有误!");
    }

    /**
     * 回收站管理 - 留言属性列表
     */
    public function gbookattr_index()
    {
        $list = array();
        $condition = array();
        // 获取到所有URL参数
        $param = input('param.');

        // 应用搜索条件
        foreach (['keywords','typeid'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$param[$key]}%");
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        /*多语言*/
        $condition['a.lang'] = $this->admin_lang;
        /*--end*/

        $condition['a.is_del'] = array('eq', 1); // 回收站功能

        $count = $this->guestbook_attribute->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $this->guestbook_attribute->alias('a')
            ->field('a.*, b.typename')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.update_time desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();

        $pageStr = $pageObj->show();// 分页显示输出
        $this->assign('page',$pageStr);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pageObj);// 赋值分页对象

        return $this->fetch();
    }

    /**
     * 回收站管理 - 留言属性还原
     */
    public function gbookattr_recovery()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){

            /*检测关联栏目是否已被伪删除*/
            $row1 = $this->guestbook_attribute->field('attr_id, typeid')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $row2 = $this->arctype->field('typename')
                ->where([
                    'id'    => ['IN', get_arr_column($row1, 'typeid')],
                    'is_del'    => 1,
                ])
                ->select();
            if (!empty($row2)) {
                $this->error('请先还原关联栏目：<font color="red">'.implode(' , ', get_arr_column($row2, 'typename')).'</font>');
            }
            /*--end*/

            // 多语言处理逻辑
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    array_push($attr_name_arr, 'attr_'.$val);
                }
                $id_arr = Db::name('language_attr')->where([
                        'attr_name'  => ['IN', $attr_name_arr],
                        'attr_group' => 'guestbook_attribute',
                    ])->column('attr_value');
            }

            $row = $this->guestbook_attribute->field('attr_id, attr_name')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $id_arr = get_arr_column($row, 'attr_id');

            // 更新数据
            $r = $this->guestbook_attribute->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->update([
                    'is_showlist'    => 0,
                    'is_del'    => 0,
                    'update_time'   => getTime(),
                ]);
            if($r){
                adminLog('还原留言表单：'.implode(',', get_arr_column($row, 'attr_name')));
                $this->success("操作成功!");
            }
        }
        $this->error("操作失败!");
    }

    /**
     * 回收站管理 - 留言属性删除
     */
    public function gbookattr_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            // 多语言处理逻辑
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    array_push($attr_name_arr, 'attr_'.$val);
                }
                $id_arr_tmp = Db::name('language_attr')->where([
                        'attr_name'  => ['IN', $attr_name_arr],
                        'attr_group' => 'guestbook_attribute',
                    ])->column('attr_value');
                if (!empty($id_arr_tmp)) {
                    $id_arr = $id_arr_tmp;
                }
            }

            $row = $this->guestbook_attribute->field('attr_id, attr_name')
                ->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->select();
            $id_arr = get_arr_column($row, 'attr_id');

            // 产品属性删除
            $r = $this->guestbook_attribute->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->delete();
            if($r){
                // 同时删除
                $this->guestbook_attr->where([
                        'attr_id'   => ['IN', $id_arr],
                    ])->delete();
                adminLog('删除留言表单：'.implode(',', get_arr_column($row, 'attr_name')));
                $this->success("操作成功!");
            }
        }
        $this->error("操作失败!");
    }

    /**
     * 回收站开关设置
     */
    public function set_web_recycle_switch()
    {
        if (IS_POST) {
            $value = input('param.value/d', 0);
            $recycle_is_clear = input('param.recycle_is_clear/d', 0);
            // 清空回收站
            if (!empty($value) && !empty($recycle_is_clear)) {
                model('RecycleBin')->clear('all');
            }
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', ['web_recycle_switch'=>$value], $val['mark']);
                }
            } else {
                tpCache('web', ['web_recycle_switch'=>$value]);
            }
            /*--end*/
            $this->success('操作成功');
        }
        $this->error('操作失败');
    }
}