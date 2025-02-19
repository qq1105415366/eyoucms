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
use think\Cache;

class AdPosition extends Base
{
    private $times = 0; // 当前时间戳
    private $ad_position_system_id = array(); // 系统默认位置ID，不可删除

    public function _initialize() {
        parent::_initialize();
        $this->times = getTime();
    }

    public function index()
    {
        $list = array();
        $get = input('get.');
        $keywords = input('keywords/s');
        $condition = [];
        // 应用搜索条件
        foreach (['keywords', 'type'] as $key) {
            $get[$key] = addslashes(trim($get[$key]));
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.title'] = array('LIKE', "%{$get[$key]}%");
                } else {
                    $tmp_key = 'a.'.$key;
                    $condition[$tmp_key] = array('eq', $get[$key]);
                }
            }
        }

        // 多语言
        $condition['a.lang'] = array('eq', $this->admin_lang);

        $adPositionM =  Db::name('ad_position');
        $count = $adPositionM->alias('a')->where($condition)->count();// 查询满足要求的总记录数
        $Page = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $adPositionM->alias('a')->where($condition)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->getAllWithIndex('id');

        // 每组获取三张图片
        $pids = get_arr_column($list, 'id');
        $ad = Db::name('ad')->where(['pid' => ['IN', $pids], 'lang' => $this->admin_lang])->order('pid asc, id asc')->select();
        foreach ($list as $k => $v) {
            if (1 == $v['type']) {
                // 图片封面图片
                $v['ad'] = [];
                foreach ($ad as $m => $n) {
                    if ($v['id'] == $n['pid']) {
                        $n['litpic'] = get_default_pic($n['litpic']); // 支持子目录
                        $v['ad'][] = $n;
                        unset($ad[$m]);
                    } else {
                        continue;
                    }
                }
                // 若没有内容则显示默认图片
                if (empty($v['ad'])) {
                    $v['ad_count'] = 0;
                    $v['ad'][]['litpic'] = ROOT_DIR . '/public/static/common/images/not_adv.jpg';
                } else {
                    $v['ad_count'] = count($v['ad']);
                }
                // 广告类型
                $v['type_name'] = '图片';
            } else if (2 == $v['type']) {
                // 多媒体封面图片
                $v['ad'][]['litpic'] = ROOT_DIR . '/public/static/admin/images/ad_type_media.png';
                // 广告类型
                $v['type_name'] = '多媒体';
            } else if (3 == $v['type']) {
                // HTML代码封面图片
                $v['ad'][]['litpic'] = ROOT_DIR . '/public/static/admin/images/ad_type_html.png';
                // 广告类型
                $v['type_name'] = 'HTML代码';
            }
            $list[$k] = $v;
        }

        $show = $Page->show();// 分页显示输出
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$Page);// 赋值分页对象

        /*多语言模式下，广告位ID显示主体语言的ID和属性title名称*/
        $main_adv_list = [];
        if ($this->admin_lang != $this->main_lang && empty($this->globalConfig['language_split'])) {
            $attr_values = get_arr_column($list, 'id');
            $languageAttrRow = Db::name('language_attr')->field('attr_name,attr_value')->where([
                    'attr_value'    => ['IN', $attr_values],
                    'attr_group'    => 'ad_position',
                    'lang'          => $this->admin_lang,
                ])->getAllWithIndex('attr_value');
            $ids = [];
            foreach ($languageAttrRow as $key => $val) {
                $tid_tmp = str_replace('adp', '', $val['attr_name']);
                array_push($ids, intval($tid_tmp));
            }
            $main_advRow = Db::name('ad_position')->field("id,title,CONCAT('adp', id) AS attr_name")
                ->where([
                    'id'    => ['IN', $ids],
                    'lang'  => $this->main_lang,
                ])->getAllWithIndex('attr_name');
            foreach ($list as $key => $val) {
                $key_tmp = !empty($languageAttrRow[$val['id']]['attr_name']) ? $languageAttrRow[$val['id']]['attr_name'] : '';
                $main_adv_list[$val['id']] = [
                    'id'        => !empty($main_advRow[$key_tmp]['id']) ? $main_advRow[$key_tmp]['id'] : '',
                    'title'  => !empty($main_advRow[$key_tmp]['title']) ? $main_advRow[$key_tmp]['title'] : '',
                ];
            }
        }
        $this->assign('main_adv_list', $main_adv_list);
        /*end*/

        return $this->fetch();
    }
    
    /**
     * 新增
     */
    public function add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        if (IS_POST) {
            $post = input('post.');
            $map = array(
                'title' => trim($post['title']),
                'lang'  => $this->admin_lang,
            );
            if(Db::name('ad_position')->where($map)->count() > 0){
                $this->error('该广告名称已存在，请检查', url('AdPosition/index'));
            }

            // 添加广告位置表信息
            $data = array(
                'title'       => trim($post['title']),
                'type'        => $post['type'],
                'intro'       => $post['intro'],
                'admin_id'    => session('admin_id'),
                'lang'        => $this->admin_lang,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            );
            $insertID = Db::name('ad_position')->insertGetId($data);

            if (!empty($insertID)) {
                // 同步广告位置ID到多语言的模板变量里，添加多语言广告位
                $this->syn_add_language_attribute($insertID);

                // 读取组合广告位的图片及信息
                $AdData = [];
                if (1 == $post['type'] && !empty($post['img_litpic'])) { // 图片类型
                    $i = 1;
                    foreach ($post['img_litpic'] as $key => $value) {
                        if (!empty($value)) {
                            // 去掉http:
                            $value = str_replace("http:", "", $value);
                            // 去掉https:
                            $value = str_replace("https:", "", $value);
                            // 主要参数
                            $AdData['litpic'] = $value;
                            $AdData['pid']    = $insertID;
                            $AdData['title']  = trim($post['img_title'][$key]);
                            $AdData['links']  = $post['img_links'][$key];
                            $AdData['intro']  = $post['img_intro'][$key];
                            $target = !empty($post['img_target'][$key]) ? 1 : 0;
                            $AdData['target'] = $target;
                            // 其他参数
                            $AdData['media_type']  = 1;
                            $AdData['admin_id']    = session('admin_id');
                            $AdData['lang']        = $this->admin_lang;
                            $AdData['sort_order']  = $i++;
                            $AdData['add_time']    = getTime();
                            $AdData['update_time'] = getTime();
                            // 添加到广告图表
                            $ad_id = Db::name('ad')->add($AdData);
                            // 同步多语言
                            $this->syn_add_ad_language_attribute($ad_id);
                        }
                    }

                } else if (2 == $post['type'] && !empty($post['video_litpic'])) { // 媒体类型
                    // 去掉http:
                    $video_litpic = str_replace("http:", "", $post['video_litpic']);
                    // 去掉https:
                    $video_litpic = str_replace("https:", "", $post['video_litpic']);
                    // 主要参数
                    $AdData['litpic'] = $video_litpic;
                    $AdData['pid']    = $insertID;
                    $AdData['title']  = trim($post['title']);
                    // 其他参数
                    $AdData['intro']       = '';
                    $AdData['links']       = '';
                    $AdData['target']      = 0;
                    $AdData['media_type']  = 2;
                    $AdData['admin_id']    = session('admin_id');
                    $AdData['lang']        = $this->admin_lang;
                    $AdData['sort_order']  = 1;
                    $AdData['add_time']    = getTime();
                    $AdData['update_time'] = getTime();
                    // 添加到广告图表
                    $ad_id = Db::name('ad')->add($AdData);
                    // 同步多语言
                    $this->syn_add_ad_language_attribute($ad_id);

                } else if (3 == $post['type'] && !empty($post['html_intro'])) { // HTML代码
                    // 主要参数
                    $AdData['pid']   = $insertID;
                    $AdData['title'] = trim($post['title']);
                    $AdData['intro'] = $post['html_intro'];
                    // 其他参数
                    $AdData['litpic']      = '';
                    $AdData['links']       = '';
                    $AdData['target']      = 0;
                    $AdData['media_type']  = 3;
                    $AdData['admin_id']    = session('admin_id');
                    $AdData['lang']        = $this->admin_lang;
                    $AdData['sort_order']  = 1;
                    $AdData['add_time']    = getTime();
                    $AdData['update_time'] = getTime();
                    // 添加到广告图表
                    $ad_id = Db::name('ad')->add($AdData);
                    // 同步多语言
                    $this->syn_add_ad_language_attribute($ad_id);

                }
                Cache::clear('ad');
                adminLog('新增广告：'.$post['title']);
                $this->success("操作成功", url('AdPosition/index'));
            } else {
                $this->error("操作失败", url('AdPosition/index'));
            }
        }

        // 上传通道
        $WeappConfig = Db::name('weapp')->field('code, status')->where('code', 'IN', ['Qiniuyun', 'AliyunOss', 'Cos', 'AwsOss'])->select();
        $WeappOpen = [];
        foreach ($WeappConfig as $value) {
            if ('Qiniuyun' == $value['code']) {
                $WeappOpen['qny_open'] = $value['status'];
            } else if ('AliyunOss' == $value['code']) {
                $WeappOpen['oss_open'] = $value['status'];
            } else if ('Cos' == $value['code']) {
                $WeappOpen['cos_open'] = $value['status'];
            } else if ('AwsOss' == $value['code']) {
                $WeappOpen['aws_open'] = $value['status'];
            }
        }
        $this->assign('WeappOpen', $WeappOpen);

        // 系统最大上传视频的大小
        $upload_max_filesize = upload_max_filesize();
        $this->assign('upload_max_filesize', $upload_max_filesize);

        // 视频类型
        $media_type = !empty($this->globalConfig['media_type']) ? $this->globalConfig['media_type'] : config('global.media_ext');
        $media_type = str_replace(",", "|", $media_type);
        $assign_data['media_type'] = $media_type;
        $file_upload_media_type = '.'.str_replace("|", ",.", $media_type);
        $assign_data['file_upload_media_type'] = $file_upload_media_type;

        $this->assign($assign_data);
        return $this->fetch();
    }

    
    /**
     * 编辑
     */
    public function edit()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post['id'])) {
                $post['title'] = trim($post['title']);
                $post['id'] = intval($post['id']);
                if (array_key_exists($post['id'], $this->ad_position_system_id)) {
                    $this->error("不可更改系统预定义位置", url('AdPosition/edit',array('id'=>$post['id'])));
                }

                /* 判断除自身外是否还有相同广告名称已存在 */
                $map = array(
                    'id'    => array('NEQ', $post['id']),
                    'title' => $post['title'],
                    'lang'  => $this->admin_lang,
                );
                if (Db::name('ad_position')->where($map)->count() > 0) $this->error('该广告名称已存在，请检查');
                /* END */

                /* 判断广告是否切换广告类型 */
                // $where = [
                //     'id'   => $post['id'],
                //     'type' => $post['type'],
                //     'lang' => $this->admin_lang
                // ];
                // if (Db::name('ad_position')->where($where)->count() == 0) {
                //     // 已切换广告类型，清除广告中的广告内容
                //     $where = [
                //         'pid'  => $post['id'],
                //         'lang' => $this->admin_lang
                //     ];
                //     Db::name('ad')->where($where)->delete();
                // }
                /* END */

                /* 修改广告主体信息 */
                $data = array(
                    'id'          => $post['id'],
                    'title'       => trim($post['title']),
                    'type'        => $post['type'],
                    'intro'       => $post['intro'],
                    'update_time' => getTime(),
                );
                $resultID = Db::name('ad_position')->update($data);
                /* END */
            }

            if (!empty($resultID)) {
                $ad_db = Db::name('ad');
                if (1 == $post['type'] && !empty($post['img_litpic'])) { // 图片类型
                    // 读取组合广告位的图片及信息
                    $i = 1;
                    foreach ($post['img_litpic'] as $key => $value) {
                        if (!empty($value)) {
                            // 去掉http:
                            $value = str_replace("http:", "", $value);
                            // 去掉https:
                            $value = str_replace("https:", "", $value);
                            // 是否新窗口打开
                            $target = !empty($post['img_target'][$key]) ? 1 : 0;
                            // 广告位ID，为空则表示添加
                            $ad_id = $post['img_id'][$key];
                            if (!empty($ad_id)) {
                                // 查询更新条件
                                $where = [
                                    'id'   => $ad_id,
                                    'lang' => $this->admin_lang,
                                ];
                                if ($ad_db->where($where)->count() > 0) {
                                    // 主要参数
                                    $AdData['litpic']      = $value;
                                    $AdData['title']       = $post['img_title'][$key];
                                    $AdData['links']       = $post['img_links'][$key];
                                    $AdData['intro']       = $post['img_intro'][$key];
                                    $AdData['target']      = $target;
                                    // 其他参数
                                    $AdData['sort_order']  = $i++;
                                    $AdData['update_time'] = getTime();
                                    // 更新，不需要同步多语言
                                    $ad_db->where($where)->update($AdData);
                                } else {
                                    // 主要参数
                                    $AdData['litpic']      = $value;
                                    $AdData['pid']         = $post['id'];
                                    $AdData['title']       = $post['img_title'][$key];
                                    $AdData['links']       = $post['img_links'][$key];
                                    $AdData['intro']       = $post['img_intro'][$key];
                                    $AdData['target']      = $target;
                                    // 其他参数
                                    $AdData['media_type']  = 1;
                                    $AdData['admin_id']    = session('admin_id');
                                    $AdData['lang']        = $this->admin_lang;
                                    $AdData['sort_order']  = $i++;
                                    $AdData['add_time']    = getTime();
                                    $AdData['update_time'] = getTime();
                                    $ad_id = $ad_db->add($AdData);
                                    // 同步多语言
                                    $this->syn_add_ad_language_attribute($ad_id);
                                }
                            } else {
                                // 主要参数
                                $AdData['litpic']      = $value;
                                $AdData['pid']         = $post['id'];
                                $AdData['title']       = $post['img_title'][$key];
                                $AdData['links']       = $post['img_links'][$key];
                                $AdData['intro']       = $post['img_intro'][$key];
                                $AdData['target']      = $target;
                                // 其他参数
                                $AdData['media_type']  = 1;
                                $AdData['admin_id']    = session('admin_id');
                                $AdData['lang']        = $this->admin_lang;
                                $AdData['sort_order']  = $i++;
                                $AdData['add_time']    = getTime();
                                $AdData['update_time'] = getTime();
                                $ad_id = $ad_db->add($AdData);
                                // 同步多语言
                                $this->syn_add_ad_language_attribute($ad_id);
                            }
                        }
                    }

                } else if (2 == $post['type'] && !empty($post['video_litpic'])) { // 媒体类型
                    // 去掉http:
                    $video_litpic = str_replace("http:", "", $post['video_litpic']);
                    // 去掉https:
                    $video_litpic = str_replace("https:", "", $post['video_litpic']);
                    if (!empty($post['video_id'])) {
                        // 更新广告内容
                        $AdData['litpic']      = $video_litpic;
                        $AdData['title']       = trim($post['title']);
                        $AdData['media_type']  = 2;
                        $AdData['update_time'] = getTime();
                        $ad_db->where('id', $post['video_id'])->update($AdData);
                    } else {
                        // 新增广告内容
                        $AdData['litpic']      = $video_litpic;
                        $AdData['pid']         = $post['id'];
                        $AdData['title']       = trim($post['title']);
                        $AdData['links']       = '';
                        $AdData['intro']       = '';
                        $AdData['target']      = 0;
                        $AdData['media_type']  = 2;
                        $AdData['admin_id']    = session('admin_id');
                        $AdData['lang']        = $this->admin_lang;
                        $AdData['sort_order']  = 1;
                        $AdData['add_time']    = getTime();
                        $AdData['update_time'] = getTime();
                        $ad_id = $ad_db->add($AdData);
                        // 同步多语言
                        $this->syn_add_ad_language_attribute($ad_id);
                    }
                    
                } else if (3 == $post['type'] && !empty($post['html_intro'])) { // HTML代码
                    if (!empty($post['html_id'])) {
                        // 更新广告内容
                        $AdData['title']       = trim($post['title']);
                        $AdData['intro']       = $post['html_intro'];
                        $AdData['media_type']  = 3;
                        $AdData['update_time'] = getTime();
                        $ad_db->where('id', $post['html_id'])->update($AdData);
                    } else {
                        // 新增广告内容
                        $AdData['litpic']      = '';
                        $AdData['pid']         = $post['id'];
                        $AdData['title']       = trim($post['title']);
                        $AdData['intro']       = $post['html_intro'];
                        $AdData['links']       = '';
                        $AdData['target']      = 0;
                        $AdData['media_type']  = 3;
                        $AdData['admin_id']    = session('admin_id');
                        $AdData['lang']        = $this->admin_lang;
                        $AdData['sort_order']  = 1;
                        $AdData['add_time']    = getTime();
                        $AdData['update_time'] = getTime();
                        $ad_id = $ad_db->add($AdData);
                        // 同步多语言
                        $this->syn_add_ad_language_attribute($ad_id);
                    }
                    
                }
                Cache::clear('ad');
                adminLog('编辑广告：'.$post['title']);
                $this->success("操作成功", url('AdPosition/index'));
            } else {
                $this->error("操作失败");
            }
        }

        $assign_data = [];

        $id = input('id/d');
        $field = Db::name('ad_position')->field('a.*')->alias('a')->where(array('a.id'=>$id))->find();
        if (empty($field)) $this->error('广告不存在，请联系管理员！');
        switch ($field['type']) {
            case '1':
                $field['type_name'] = '图片';
                break;
            case '2':
                $field['type_name'] = '多媒体';
                break;
            case '3':
                $field['type_name'] = 'HTML代码';
                break;
        }
        $assign_data['field'] = $field;

        // 广告
        $ad_data = Db::name('ad')->where(array('pid'=>$field['id']))->order('sort_order asc')->select();
        foreach ($ad_data as $key => $val) {
            if (1 == $val['media_type']) {
                $ad_data[$key]['litpic'] = get_default_pic($val['litpic']); // 支持子目录
            }
        }
        $assign_data['ad_data'] = $ad_data;

        // 上传通道
        $WeappConfig = Db::name('weapp')->field('code, status')->where('code', 'IN', ['Qiniuyun', 'AliyunOss', 'Cos', 'AwsOss'])->select();
        $WeappOpen = [];
        foreach ($WeappConfig as $value) {
            if ('Qiniuyun' == $value['code']) {
                $WeappOpen['qny_open'] = $value['status'];
            } else if ('AliyunOss' == $value['code']) {
                $WeappOpen['oss_open'] = $value['status'];
            } else if ('Cos' == $value['code']) {
                $WeappOpen['cos_open'] = $value['status'];
            } else if ('AwsOss' == $value['code']) {
                $WeappOpen['aws_open'] = $value['status'];
            }
        }
        $this->assign('WeappOpen', $WeappOpen);

        // 系统最大上传视频的大小
        $file_size  = empty($this->globalConfig['file_size']) ? 0 : $this->globalConfig['file_size'];
        $postsize   = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size   = strval($file_size) < strval($postsize) ? $file_size : $postsize;
        $min_size   = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
        $upload_max_filesize = intval($min_size) * 1024 * 1024;
        $assign_data['upload_max_filesize'] = $upload_max_filesize;

        // 视频类型
        $media_type = !empty($this->globalConfig['media_type']) ? $this->globalConfig['media_type'] : config('global.media_ext');
        $media_type = str_replace(",", "|", $media_type);
        $assign_data['media_type'] = $media_type;
        $file_upload_media_type = '.'.str_replace("|", ",.", $media_type);
        $assign_data['file_upload_media_type'] = $file_upload_media_type;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除广告图片
     */
    public function del_imgupload()
    {
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            /*多语言*/
            $attr_name_arr = [];
            if (empty($this->globalConfig['language_split'])) {
                foreach ($id_arr as $key => $val) {
                    $attr_name_arr[] = 'ad'.$val;
                }
                if (is_language()) {
                    $new_id_arr = Db::name('language_attr')->where([
                            'attr_name'  => ['IN', $attr_name_arr],
                            'attr_group'    => 'ad',
                        ])->column('attr_value');
                    !empty($new_id_arr) && $id_arr = $new_id_arr;
                }
            } else {
                if (get_admin_lang() == get_main_lang()) {
                    foreach ($id_arr as $key => $val) {
                        $attr_name_arr[] = 'ad'.$val;
                    }
                }
            }
            /*--end*/

            $r = Db::name('ad')->where([
                    'id' => ['IN', $id_arr],
                ])->delete();
            if ($r !== false) {
                /*多语言*/
                if (!empty($attr_name_arr)) {
                    Db::name('language_attr')->where([
                            'attr_name' => ['IN', $attr_name_arr],
                            'attr_group'    => 'ad',
                        ])->delete();

                    Db::name('language_attribute')->where([
                            'attr_name' => ['IN', $attr_name_arr],
                            'attr_group'    => 'ad',
                        ])->delete();
                }
                /*--end*/
                Cache::clear('ad');
                adminLog('删除广告-id：'.implode(',', $id_arr));
            }
        }
    }

    /**
     * 删除
     */
    public function del()
    {
        if (is_language() && empty($this->globalConfig['language_split'])) {
            $this->language_access(); // 多语言功能操作权限
        }

        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){
            foreach ($id_arr as $key => $val) {
                if(array_key_exists($val, $this->ad_position_system_id)){
                    $this->error('系统预定义，不能删除');
                }
            }

            /*多语言*/
            $attr_name_arr = [];
            foreach ($id_arr as $key => $val) {
                $attr_name_arr[] = 'adp'.$val;
            }
            if (is_language() && empty($this->globalConfig['language_split'])) {
                $new_id_arr = Db::name('language_attr')->where([
                        'attr_name' => ['IN', $attr_name_arr],
                        'attr_group'    => 'ad_position',
                    ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            /*--end*/
            $r = Db::name('ad_position')->where('id','IN',$id_arr)->delete();
            if ($r !== false) {

                /*多语言*/
                if (!empty($attr_name_arr)) {
                    if (get_admin_lang() == get_main_lang()) {
                        Db::name('language_attribute')->where([
                                'attr_name' => ['IN', $attr_name_arr],
                                'attr_group'    => 'ad_position',
                            ])->delete();
                    }
                    if (empty($this->globalConfig['language_split'])) {
                        Db::name('language_attr')->where([
                                'attr_name' => ['IN', $attr_name_arr],
                                'attr_group'    => 'ad_position',
                            ])->delete();
                    } else {
                        Db::name('language_attr')->where([
                                'attr_value' => ['IN', $id_arr],
                                'attr_group'    => 'ad_position',
                            ])->delete();
                    }
                }
                /*--end*/

                $ad_ids = Db::name('ad')->where(['pid'=>['IN', $id_arr]])->column('id');
                $attr_name_arr = [];
                foreach ($ad_ids as $key => $val) {
                    $attr_name_arr[] = "ad{$val}";
                }
                $r1 = Db::name('ad')->where('pid','IN',$id_arr)->delete();
                if ($r1 !== false) {
                    /*多语言*/
                    if (!empty($attr_name_arr)) {
                        if (get_admin_lang() == get_main_lang()) {
                            Db::name('language_attribute')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'ad',
                                ])->delete();
                        }
                        if (empty($this->globalConfig['language_split'])) {
                            Db::name('language_attr')->where([
                                    'attr_name' => ['IN', $attr_name_arr],
                                    'attr_group'    => 'ad',
                                ])->delete();
                        } else {
                            Db::name('language_attr')->where([
                                    'attr_value' => ['IN', $ad_ids],
                                    'attr_group'    => 'ad',
                                ])->delete();
                        }
                    }
                    /*--end*/
                }

                Cache::clear('ad');
                adminLog('删除广告-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    /**
     * 打开预览视频
     */
    public function open_preview_video()
    {
        $post = input('post.');
        $video_litpic = $post['video_litpic'];
        if (!is_http_url($video_litpic)) {
            $video_litpic = request()->domain() . handle_subdir_pic($video_litpic, 'media');
        }
        $this->success('执行成功', $video_litpic);
    }

    /**
     * 检测广告名称是否存在重复
     */
    public function detection_title_repeat()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $where = [
                'id'    => ['NEQ', $post['id']],
                'title' => trim($post['title']),
                'lang'  => $this->admin_lang,
            ];
            $count = Db::name('ad_position')->where($where)->count();
            if (empty($count)) {
                $this->success('检测通过');
            } else {
                $this->error('该广告名称已存在，请检查');
            }
        }
    }

    /**
     * 同步新增广告位置ID到多语言的模板变量里
     */
    private function syn_add_language_attribute($adp_id)
    {
        /*单语言情况下不执行多语言代码*/
        if (!is_language() || tpCache('language.language_split')) {
            return true;
        }
        /*--end*/

        $attr_group = 'ad_position';
        $admin_lang = $this->admin_lang;
        $main_lang = $this->main_lang;
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $ad_position_db = Db::name('ad_position');
            $result = $ad_position_db->find($adp_id);
            $attr_name = 'adp'.$adp_id;
            $r = Db::name('language_attribute')->save([
                'attr_title'    => $result['title'],
                'attr_name'     => $attr_name,
                'attr_group'    => $attr_group,
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
            if (false !== $r) {
                $data = [];
                foreach ($languageRow as $key => $val) {
                    /*同步新广告位置到其他语言广告位置列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $result;
                        $addsaveData['lang']  = $val['mark'];
                        $addsaveData['title'] = $val['mark'].$addsaveData['title'];
                        unset($addsaveData['id']);
                        $adp_id = $ad_position_db->insertGetId($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name' => $attr_name,
                        'attr_value'    => $adp_id,
                        'lang'  => $val['mark'],
                        'attr_group'    => $attr_group,
                        'add_time'      => getTime(),
                        'update_time'   => getTime(),
                    ];
                    /*--end*/
                }
                if (!empty($data)) {
                    model('LanguageAttr')->saveAll($data);
                }
            }
        }
    }

    /**
     * 同步新增广告ID到多语言的模板变量里
     */
   private function syn_add_ad_language_attribute($ad_id)
    {
        /*单语言情况下不执行多语言代码*/
        if (!is_language() || tpCache('language.language_split')) {
            return true;
        }
        /*--end*/

        $attr_group = 'ad';
        $admin_lang = $this->admin_lang;
        $main_lang = get_main_lang();
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $ad_db = Db::name('ad');
            $result = $ad_db->find($ad_id);
            $attr_name = 'ad'.$ad_id;
            $r = Db::name('language_attribute')->save([
                'attr_title'    => $result['title'],
                'attr_name'     => $attr_name,
                'attr_group'    => $attr_group,
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
            if (false !== $r) {
                $data = [];
                foreach ($languageRow as $key => $val) {
                    /*同步新广告到其他语言广告列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $result;
                        $addsaveData['lang'] = $val['mark'];
                        $newPid = Db::name('language_attr')->where([
                                'attr_name' => 'adp'.$result['pid'],
                                'attr_group'    => 'ad_position',
                                'lang'  => $val['mark'],
                            ])->getField('attr_value');
                        $addsaveData['pid']   = $newPid;
                        $addsaveData['title'] = $val['mark'].$addsaveData['title'];
                        unset($addsaveData['id']);
                        $ad_id = $ad_db->insertGetId($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name'   => $attr_name,
                        'attr_value'  => $ad_id,
                        'lang'        => $val['mark'],
                        'attr_group'  => $attr_group,
                        'add_time'    => getTime(),
                        'update_time' => getTime(),
                    ];
                    /*--end*/
                }
                if (!empty($data)) {
                    model('LanguageAttr')->saveAll($data);
                }
            }
        }
    }
}