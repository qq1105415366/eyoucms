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
use think\Page;
use think\Cache;

class Tags extends Base
{
    public function index()
    {
        $list = array();
        $param = input('param.');
        $keywords = input('keywords/s');
        $keywords = trim($keywords);
        $condition = [];
        // 应用搜索条件
        foreach (['keywords'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['tag'] = array('LIKE', "%{$keywords}%");
                } else {
                    $condition[$key] = array('eq', trim($param[$key]));
                }
            }
        }
        $condition['lang'] = array('eq', $this->admin_lang);

        $tagsM =  Db::name('tagindex');
        $count = $tagsM->where($condition)->count('id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('pager', $pager);

        // 查询ID数组，用于纠正本页TAG文档书
        $IndexID = $tagsM->where($condition)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->column('id');

        // 纠正tags标签的文档数
        $this->correct($IndexID);
    
        $list = $tagsM->where($condition)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list', $list);

        $source = input('param.source/s');
        $this->assign('source', $source);

        // 清理过期的tag记录文件
        $this->clear_tag_relation_file();

        return $this->fetch();
    }
 
    public function tag_list()
    {
        $condition['lang'] = array('eq', $this->admin_lang);
        $tagsM =  Db::name('tagindex');
        $count = $tagsM->where($condition)->count('id');
        $Page = $pager = new Page($count, 100);
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('pager', $pager);

        // 查询ID数组，用于纠正本页TAG文档书
        $IndexID = $tagsM->where($condition)->order($order)->limit($Page->firstRow.','.$Page->listRows)->column('id');

        // 纠正tags标签的文档数
        $this->correct($IndexID);

        $order = 'total desc, id desc, monthcc desc, weekcc desc';
        $list = $tagsM->where($condition)->order($order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function edit()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post['id'])) {
                $post['id'] = intval($post['id']);
                $tag = !empty($post['tag_tag']) ? trim($post['tag_tag']) : '';
                if (empty($tag)) {
                    $this->error('标签名称不能为空！');
                } else {
                    $row = Db::name('tagindex')->where([
                            'tag'   => $tag,
                            'id'    => ['NEQ', $post['id']],
                            'lang'  => $this->admin_lang,
                        ])->find();
                    if (!empty($row)) {
                        $this->error('标签名称已存在，请更改！');
                    }
                }

                $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
                $litpic = '';
                if ($is_remote == 1) {
                    $litpic = $post['litpic_remote'];
                } else {
                    $litpic = $post['litpic_local'];
                }
                
                $updata = [
                    'tag' => $tag,
                    'litpic' => $litpic,
                    'seo_title' => !empty($post['tag_seo_title']) ? trim($post['tag_seo_title']) : '',
                    'seo_keywords' => !empty($post['tag_seo_keywords']) ? trim($post['tag_seo_keywords']) : '',
                    'seo_description' => !empty($post['tag_seo_description']) ? trim($post['tag_seo_description']) : '',
                    'sort_order' => !empty($post['tag_sort_order']) ? trim($post['tag_sort_order']) : '100',
                    'update_time' => getTime(),
                ];
                $ResultID = Db::name('tagindex')->where('id', $post['id'])->update($updata);
                if (false !== $ResultID) {
                    if (trim($post['tag_tag']) != trim($post['tag_tagold'])) {
                        Db::name('taglist')->where([
                                'tid'   => $post['id'],
                            ])->update([
                                'tag'   => trim($post['tag_tag']),
                                'update_time'   => getTime(),
                            ]);
                        \think\Cache::clear('taglist');
                    }
                    $this->success('操作成功');
                }
            }
            $this->error('操作失败');
        }

        $id = input('id/d');
        $Result = Db::name('tagindex')->where('id', $id)->find();
        if (empty($Result)) $this->error('操作异常');
        if (is_http_url($Result['litpic'])) {
            $Result['is_remote'] = 1;
            $Result['litpic_remote'] = handle_subdir_pic($Result['litpic']);
        } else {
            $Result['is_remote'] = 0;
            $Result['litpic_local'] = handle_subdir_pic($Result['litpic']);
        }
        $this->assign('tag', $Result);

        $this->assign('backurl', url('Tags/index'));
        return $this->fetch();
    }

    public function del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $result = Db::name('tagindex')->field('tag')
                    ->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                    ])->select();
                $title_list = get_arr_column($result, 'tag');

                $r = Db::name('tagindex')->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                    ])->delete();
                if($r){
                    Db::name('taglist')->where([
                        'tid'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                    ])->delete();
                    \think\Cache::clear('taglist');
                    adminLog('删除Tags标签：'.implode(',', $title_list));
                    $this->success('删除成功');
                }
            }
        }
        $this->error('删除失败');
    }
    
    public function clearall()
    {
        $r = Db::name('tagindex')->where([
                'lang'  => $this->admin_lang,
            ])->delete();
        if(false !== $r){
            Db::name('taglist')->where([
                'lang'  => $this->admin_lang,
            ])->delete();
            \think\Cache::clear('taglist');
            adminLog('清空Tags标签');
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 纠正tags文档数
     */
    private function correct($IndexID = [])
    {
        if (!empty($IndexID)) {
            $where = [
                'tid' => ['IN', $IndexID],
                'lang' => $this->admin_lang
            ];
            $taglistRow = Db::name('taglist')->field('count(tid) as total, tid')
                ->where($where)
                ->group('tid')
                ->getAllWithIndex('tid');
            $updateData = [];
            $weekup = getTime();
            foreach ($taglistRow as $key => $val) {
                $updateData[] = [
                    'id'    => $val['tid'],
                    'total' => $val['total'],
                    'weekup'    => $weekup,
                ];
            }
            if (!empty($updateData)) {
                $r = model('Tagindex')->saveAll($updateData);
                if (false !== $r) {
                    // Db::name('tagindex')->where(['weekup'=>['lt', $weekup],'lang'=>$this->admin_lang])->delete();
                }
            }
        }
    }

    /**
     * 获取常用标签列表
     */
    public function get_common_list()
    {
        if (IS_AJAX) {
            $tags = input('tags/s');
            $type = input('type/d');
            if (!empty($tags)){
                $tagsArr = explode(',',$tags);
                $tags  = trim(end($tagsArr));
            }

            /*发布最新文档的tag里前3个*/
            $newTagList = [];
            $newtids = [];
            if (empty($tags)){
                $taglistRow = Db::name('taglist')->field('tid,tag')->where(['lang'=>$this->admin_lang])->order('aid desc')->limit(20)->select();
                foreach ($taglistRow as $key => $val) {
                    if (3 <= count($newTagList)) {
                        break;
                    }
                    if (in_array($val['tid'], $newtids)) {
                        continue;
                    }
                    array_push($newTagList, $val);
                    array_push($newtids, $val['tid']);
                }
            }
            $list = $newTagList;
            /*end*/

            /*常用标签*/
            $where = [];
            $where['is_common'] = 1;
            !empty($newtids) && $where['id'] = ['NOTIN', $newtids];
            $where['lang'] = $this->admin_lang;
            if (!empty($tags)){
                $where['tag'] = ['like','%'.$tags."%"];
            }
            $num = 20 - count($list);
            $row = Db::name('tagindex')->field('id as tid,tag')->where($where)
                ->order('total desc, id desc')
                ->limit($num)
                ->select();
            if (is_array($list) && is_array($row)) {
                $list = array_merge($list, $row);
            }
            /*end*/

            // 不够数量进行补充
            $surplusNum = $num - count($list);
            if (0 < $surplusNum) {
                $ids = get_arr_column($list, 'tid');
                $condition['lang'] = $this->admin_lang;
                $condition['id'] = ['NOT IN', $ids];
                if (!empty($tags)){
                    $condition['tag'] = ['like','%'.$tags."%"];
                }
                $row2 = Db::name('tagindex')->field('id as tid,tag')->where($condition)
                    ->order('total desc, id desc')
                    ->limit($surplusNum)
                    ->select();
                if (is_array($list) && is_array($row2)) {
                    $list = array_merge($list, $row2);
                }
            }
            /*end*/

            $html = "";
            $data = [];
            if (!empty($list)) {
                $tags = input('param.tags/s');
                $tags = str_replace('，', ',', $tags);
                $tagArr = explode(',', $tags);
                foreach ($tagArr as $key => $val) {
                    $tagArr[$key] = trim($val);
                }

                foreach ($list as $_k1 => $_v1) {
                    if (!empty($type)){
                        if (in_array($_v1['tag'], $tagArr)) {
                            $html .= "<a class='cur' href='javascript:void(0);' onclick='selectArchivesTagInput(this);'>{$_v1['tag']}</a>";
                        } else {
                            $html .= "<a href='javascript:void(0);' onclick='selectArchivesTagInput(this);'>{$_v1['tag']}</a>";
                        }
                    }else{
                        if (in_array($_v1['tag'], $tagArr)) {
                            $html .= "<a class='cur' href='javascript:void(0);' onclick='selectArchivesTag(this);'>{$_v1['tag']}</a>";
                        } else {
                            $html .= "<a href='javascript:void(0);' onclick='selectArchivesTag(this);'>{$_v1['tag']}</a>";
                        }
                    }
                }
            }

            $is_click = input('param.is_click/d');
            if (!empty($is_click)) {
                if (empty($html)) {
                    $html .= "没有找到记录";
                }
                // $html .= "<a href='javascript:void(0);' onclick='tags_list_1610411887(this);' style='float: right;'>[设置]</a>";
            }
            $data['html']   = $html;

            if (!empty($type) && empty($tags)){
                $data = [];
            }

            $this->success('请求成功', null, $data);
        }
        $this->error('请求失败');
    }

    public function batch_add()
    {
        if (IS_POST) {
            $post = input('post.');

            $tags = trim($post['tags']);
            if (empty($tags)) {
                $this->error('Tag列表不能为空！');
            }

            $tagsArr = explode("\r\n", $tags);
            $tagsArr = array_filter($tagsArr);//去除数组空值
            $tagsArr = array_unique($tagsArr); //去重
            foreach ($tagsArr as $key => $val) {
                $tagsArr[$key] = trim($val);
            }

            $addData = [];
            $tagsList = Db::name('tagindex')->where([
                    'tag'  => ['IN', $tagsArr],
                    'lang'      => $this->admin_lang,
                ])->column('tag');
            foreach ($tagsArr as $key => $val) {
                if(empty($val) || in_array($val, $tagsList)) continue;

                $addData[] = [
                    'tag'               => $val,
                    'typeid'            => 0,
                    'seo_description'   => '',
                    'lang'              => $this->admin_lang,
                    'add_time'          => getTime(),
                    'update_time'       => getTime(),
                ];
            }
            if (!empty($addData)) {
                $r = model('Tagindex')->saveAll($addData);
                if ($r !== false) {
                    eyou_statistics_data(8, count($addData)); // 统计tags数
                    Cache::clear('taglist');
                    adminLog('批量新增Tag标签：'.get_arr_column($addData, 'tag'));
                    $this->success('操作成功！');
                } else {
                    $this->error('操作失败');
                }
            } else {
                $this->success('操作成功！');
            }
        }
        return $this->fetch();
    }

    public function edit_index_seo()
    {
        if (IS_POST) {
            $post = input('post.');
            tpCache('tag', $post);
            $this->success('操作成功');
        }

        $data = tpCache('tag');
        $this->assign('data', $data);

        return $this->fetch();
    }

    private function clear_tag_relation_file()
    {
        $time = strtotime('-1 day');
        $files = glob(ROOT_PATH . 'data/conf/tagaids/*.txt');
        foreach ($files as $key => $val) {
            $ftime = (int)preg_replace('/^(.*)\/(\d+)\-(\w+)\.txt$/i', '$2', $val);
            if (!empty($ftime) && $ftime < $time) {
                @unlink($val);
            }
        }
    }

    public function relation_archives()
    {
        $assign_data = array();
        $condition = array();
        // 获取到所有URL参数
        $param = input('param.');
        $typeid = input('param.typeid/d');
        $channels = input('param.channel/s');

        // 应用搜索条件
        foreach (['keywords','typeid','channel'] as $key) {
            if ($key == 'keywords' && !empty($param[$key])) {
                $param[$key] = trim($param[$key]);
                $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
            } else if ($key == 'typeid' && !empty($param[$key])) {
                $typeid = $param[$key];
                $hasRow = model('Arctype')->getHasChildren($typeid);
                $typeids = get_arr_column($hasRow, 'id');
                /*权限控制 by 小虎哥*/
                $admin_info = session('admin_info');
                if (0 < intval($admin_info['role_id'])) {
                    $auth_role_info = $admin_info['auth_role_info'];
                    if(! empty($auth_role_info)){
                        if(isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']){
                            $condition['a.admin_id'] = $admin_info['admin_id'];
                        }
                        if(! empty($auth_role_info['permission']['arctype'])){
                            if (!empty($typeid)) {
                                $typeids = array_intersect($typeids, $auth_role_info['permission']['arctype']);
                            }
                        }
                    }
                }
                /*--end*/
                $condition['a.typeid'] = array('IN', $typeids);
            } else if ($key == 'channel') {
                if (empty($param[$key])) {
                    $allow_release_channel = config('global.allow_release_channel');
                    $key_tmp = array_search('7', $allow_release_channel);
                    if (is_numeric($key_tmp) && 0 <= $key_tmp) {
                        unset($allow_release_channel[$key_tmp]);
                    }
                    $param[$key] = implode(',', $allow_release_channel);
                }
                $condition['a.'.$key] = array('in', explode(',', $param[$key]));
            } else if (!empty($param[$key])) {
                $condition['a.'.$key] = array('eq', $param[$key]);
            }
        }

        $condition['a.arcrank'] = array('gt', -1);
        $condition['a.lang'] = array('eq', $this->admin_lang);
        $condition['a.is_del'] = array('eq', 0);

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = Db::name('archives')->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $Page = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('archives')
            ->field("a.aid")
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.aid desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $aids = array_keys($list);
            $fields = "b.*, a.*, a.aid as aid";
            $row = Db::name('archives')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.aid', 'in', $aids)
                ->getAllWithIndex('aid');
            foreach ($list as $key => $val) {
                $list[$key] = $row[$val['aid']];
            }
        }
        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;

        /*允许发布文档列表的栏目*/
        $allow_release_channel = !empty($channels) ? explode(',', $channels) : config('global.allow_release_channel');
        $key_tmp = array_search('7', $allow_release_channel);
        if (is_numeric($key_tmp) && 0 <= $key_tmp) {
            unset($allow_release_channel[$key_tmp]);
        }
        $assign_data['arctype_html'] = allow_release_arctype($typeid, $allow_release_channel);
        /*--end*/
        
        // 模型列表
        $channeltype_list = getChanneltypeList();
        $this->assign('channeltype_list', $channeltype_list);

        // 当前页已关联的文档
        $tid = input('param.tid/d');
        $tagaids_str = $this->readTagaidsFile();
        if (empty($tagaids_str)) {
            $tagaids = Db::name('taglist')->where(['tid'=>$tid])->column('aid');
            $tagaids_str = implode(',', $tagaids);
            $this->writeTagaidsFile($tagaids_str);
        }
        $assign_data['tid'] = $tid;

        $this->assign($assign_data);
        
        return $this->fetch();
    }

    public function relation_archives_save()
    {
        if (IS_POST) {
            $tid = input('param.tid/d');
            $tagaids = input('post.tagaids/s');
            if (empty($tagaids)) {
                $tagaids = $this->readTagaidsFile();
            }
            $tagaids = trim($tagaids, ',');
            $aids_new = [];
            if (!empty($tagaids)) {
                $aids_new = explode(',', $tagaids);
            }
            if (!empty($tid)) {
                $tag = Db::name('tagindex')->where(['id'=>$tid])->value('tag');
                $aids_old = Db::name('taglist')->where(['tid'=>$tid])->column('aid');
                empty($aids_old) && $aids_old = [];

                // 取消关联文档
                $delaids = array_diff($aids_old, $aids_new);
                if (!empty($delaids)) {
                    Db::name('taglist')->where(['aid'=>['IN', $delaids]])->delete();
                }
                // 追加关联的文档ID
                $addaids = array_diff($aids_new, $aids_old);
                if (!empty($addaids)) {
                    $archivesList = Db::name('archives')->field('aid,typeid,arcrank')->where(['aid'=>['in', $addaids]])->select();
                    $saveData = [];
                    foreach ($archivesList as $key => $val) {
                        $saveData[] = [
                            'tid'   => $tid,
                            'aid'   => $val['aid'],
                            'typeid'=> $val['typeid'],
                            'tag'   => $tag,
                            'arcrank'=> $val['arcrank'],
                            'lang'  => $this->admin_lang,
                            'add_time'=> getTime(),
                            'update_time'=> getTime(),
                        ];
                    }
                    !empty($saveData) && model('Taglist')->saveAll($saveData);
                }
                // 更新文档总数
                Db::name('tagindex')->where(['id'=>$tid])->update([
                    'total' => count($aids_new),
                    'update_time'   => getTime(),
                ]);
                Cache::clear('taglist');
                adminLog('Tag关联文档：'.$tagaids);
                $this->success('操作成功', url('Tags/relation_archives', ['tid'=>$tid]));
            }
            $this->error('操作失败');
        }
    }

    /**
     * 用于Tag关联文档的逻辑
     * @return [type] [description]
     */
    public function ajax_recordfile()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        if (IS_AJAX) {
            $filename = session('admin-tag-relation-filename');
            if (empty($filename)) {
                session('admin-tag-relation-filename', getTime().'-'.mt_rand(1000,9999).'.txt');
            }
            $opt = input('param.opt/s');
            $value = input('param.value/s');
            if ('set' == $opt) {
                $redata = $this->writeTagaidsFile($value);
                if (true !== $redata) {
                    $this->error($redata);
                }
                $this->success('写入成功！');
            }
            else if ('get' == $opt) {
                $tagaids = $this->readTagaidsFile();
                $this->success('读取成功！', null, $tagaids);
            }
        }
    }

    /**
     * 读取关联tagaids文件 - 应用于tag关联文档
     * @return [type] [description]
     */
    private function readTagaidsFile()
    {
        $tagaids = '';
        $filename = session('admin-tag-relation-filename');
        if (empty($filename)) {
            $filename = 'default.txt';
        }
        $filepath = ROOT_PATH . 'data/conf/tagaids/'.$filename;
        if (file_exists($filepath)) {
            $len     = filesize($filepath);
            if (!empty($len) && $len > 0) {
                $fp      = fopen($filepath, 'r');
                $tagaids = fread($fp, $len);
                fclose($fp);
                $tagaids = $tagaids ? $tagaids : '';
            }
        }
        return $tagaids;
    }

    /**
     * 写入关联tagaids文件 - 应用于tag关联文档
     * @return [type] [description]
     */
    private function writeTagaidsFile($value = '')
    {
        $filename = session('admin-tag-relation-filename');
        if (empty($filename)) {
            $filename = 'default.txt';
        }
        $filepath = ROOT_PATH . 'data/conf/tagaids/'.$filename;
        if (!file_exists($filepath)) tp_mkdir(dirname($filepath));
        $fp = fopen($filepath, "w+");
        if (empty($fp)) {
            return "请设置" . $filepath . "的权限为744";
        } else {
            if (fwrite($fp, $value)) {
                fclose($fp);
            }
        }
        return true;
    }
}