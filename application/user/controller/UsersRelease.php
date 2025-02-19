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
namespace app\user\controller;

use think\Db;
use think\Config;
use think\Verify;
use think\Page;
use think\Request;

/**
 * 会员投稿发布
 */
class UsersRelease extends Base
{
    public function _initialize() {
        parent::_initialize();
        $this->archives_db = Db::name('archives');
        $this->users_menu_db = Db::name('users_menu');
        $this->users_level_db = Db::name('users_level');
        $this->channeltype_db = Db::name('channeltype');

        // 会员投稿功能已关闭
        if (empty($this->usersConfig['users_open_release'])) {
            $this->error('会员投稿功能已关闭', url('user/Users/index'));
        }

        // 菜单名称
        $this->MenuTitle = $this->users_menu_db->where([
                'mca' => 'user/UsersRelease/release_centre',
                'lang' => $this->home_lang,
            ])->getField('title');
        $this->assign('MenuTitle', $this->MenuTitle);

        // 获取传入的模型ID
        $typeid = input('param.typeid/d', 0);
        $this->channelID = input('param.channel/d', 0);
        if (empty($this->channelID) && !empty($typeid)) $this->channelID = Db::name('arctype')->where(['id'=>$typeid])->getField('current_channel');
        $this->channelData = $this->channeltype_db->where('id', $this->channelID)->field('id, table, is_release, is_litpic_users_release')->find();
        if (!empty($this->channelData)) {
            $this->assign('is_release', $this->channelData['is_release']);
            $this->assign('is_litpic_users_release', $this->channelData['is_litpic_users_release']);
        }

        // 当前访问的方法名
        $Method = request()->action();
        $this->assign('Method', $Method);
        $list = input('param.list/d', 0);
        if ('release_centre' == $Method && !empty($this->channelData['is_release']) && empty($list)) $this->redirect('user/UsersRelease/article_add');

        // 会员投稿配置
        $this->assign('UsersConfig', $this->usersConfig);
    }

    // 会员投稿--文章首页
    public function release_centre()
    {
        $result = [];
        $condition = [
            'a.users_id' => $this->users_id,
            'a.lang'     => $this->home_lang,
            'a.is_del'   => 0,
        ];

        $typeid = input('typeid/d', 0); // 栏目ID，暂无用，预留
        if (!empty($typeid)) $condition['a.typeid'] = $typeid;
        
        // 文章标题搜索
        $keywords = input('keywords/s', '');
        $this->assign('keywords', $keywords);
        if (!empty($keywords)) $condition['title'] = ['LIKE', "%{$keywords}%"];

        // 全部、未审核、已审核筛选
        $audited = input('audited/d', 0);
        $this->assign('audited', $audited);
        if (1 === intval($audited)) {
            $condition['arcrank'] = ['EQ', -1];
        } else if (2 === intval($audited)) {
            $condition['arcrank'] = ['GT', -1];
        }

        $SqlQuery = $this->archives_db->alias('a')->where($condition)->fetchSql()->count('aid');
        $count = Db::name('sql_cache_table')->where(['sql_md5'=>md5($SqlQuery)])->getField('sql_result');
        if (!empty($count) && 0 > $count) {
            Db::name('sql_cache_table')->where(['sql_md5'=>md5($SqlQuery)])->delete();
            unset($count);
        }
        if (!isset($count)) {
            $count = $this->archives_db->alias('a')->where($condition)->count('aid');
            // 添加查询执行语句到mysql缓存表
            $SqlCacheTable = [
                'sql_name' => '|users_release|' . $this->users_id . '|all|',
                'sql_result' => $count,
                'sql_md5' => md5($SqlQuery),
                'sql_query' => $SqlQuery,
                'add_time' => getTime(),
                'update_time' => getTime()
            ];
            if (!empty($typeid)) $SqlCacheTable['sql_name'] = '|users_release|' . $this->users_id . '|' . $typeid . '|';
            Db::name('sql_cache_table')->insertGetId($SqlCacheTable);
        }

        $Page = new Page($count, config('paginate.list_rows'));
        $result['data'] = [];
        if (!empty($count)) {
            $limit = $count > config('paginate.list_rows') ? $Page->firstRow.','.$Page->listRows : $count;
            // 数据查询
            $result['data'] = $this->archives_db
                ->field('t.*, a.*')
                ->alias('a')
                ->join('__ARCTYPE__ t', 'a.typeid = t.id', 'LEFT')
                ->where($condition)
                ->order('a.aid desc')
                ->limit($limit)
                ->select();
            // 如果当前分页没有数据则去除分页参数重载
            if (empty($result['data']) && input('param.p/d', 0)) $this->redirect('user/UsersRelease/release_centre', ['list'=>1]);
            $seo_pseudo = tpCache('seo.seo_pseudo');
            foreach ($result['data'] as $key => $value) {
                $value['arcurl'] = get_arcurl($value, false);
                $value['typeurl'] = get_typeurl($value, false);
                $value['editurl'] = url('user/UsersRelease/article_edit', array('aid'=>$value['aid']));
                $value['litpic'] = get_default_pic($value['litpic']); // 支持子目录

                $result['data'][$key] = $value;
            }
        }

        $result['delurl']  = url('user/UsersRelease/article_del');
        $eyou = array(
            'field' => $result,
        );
        $this->assign('eyou', $eyou);
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        // 会员投稿发布的文章状态
        $home_article_arcrank = Config::get('global.home_article_arcrank');
        $this->assign('home_article_arcrank', $home_article_arcrank);
        // 查询会员投稿数据
        $where = [
            'is_del' => 0,
            'lang' => $this->home_lang,
            'users_id' => intval($this->users_id),
        ];
        $all = $isAudited = $notAudited = 0;
        $archives = $this->archives_db->field('aid, arcrank')->where($where)->select();
        $all = !empty($archives) ? count($archives) : 0;
        foreach ($archives as $key => $value) {
            if (-1 === intval($value['arcrank'])) {
                $notAudited++;
            } else {
                $isAudited++;
            }
        }
        $this->assign('all', $all);
        $this->assign('isAudited', $isAudited);
        $this->assign('notAudited', $notAudited);
        return $this->fetch('users/release_centre');
    }

    public function release_select()
    {
        $typeid = 0;
        $ids = [1, 3, 4, 5];
        $where = [
            'is_release' => 1,
            'lang' => $this->home_lang,
            'current_channel' => ['IN', $ids]
        ];
        $release_typeids = Db::name('arctype')->where($where)->column('id');

        // 生成栏目选择下拉列表
        $onchange = "onchange='releaseJump(this);' data-url='" . url('user/UsersRelease/article_add') . "' ";
        $arctype_html = allow_release_arctype($typeid, $ids, true, $release_typeids, true);
        $arctype_html = str_ireplace('data-current_channel=', 'data-channel=', $arctype_html);
        $arctype_html = "<select name='typeid' id='typeid' style='width: 300px;height: 400px;border: 1px solid #eee;padding: 2px;' size='25' " .  $onchange. " >{$arctype_html}</select>";

        $this->assign('arctype_html', $arctype_html);

        return $this->fetch('users/release_select');
    }

    // 会员投稿--文章添加
    public function article_add()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 文档标题处理
            $post['title'] = !empty($post['title']) ? handleEyouFilterStr($post['title']) : '';
            // 文档tags处理
            if (!empty($post['tags'])) $post['tags'] = str_replace("，", ',', $post['tags']);
            // 会员投稿次数开启，查询会员投稿次数是否超过级别设置次数
            if (!empty($this->usersConfig['is_open_posts_count'])) {
                $where = [
                    'level_id'    => $this->users['level'],
                    'posts_count' => ['<=', $this->GetOneDayReleaseCount()],
                ];
                $result = $this->users_level_db->where($where)->count();
                if (!empty($result)) $this->error('投稿次数超过会员级别限制，请先升级！');
            }

            // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
            $this->PostParameCheck($post);

            // POST参数处理后返回
            $post = $this->PostParameDealWith($post);
            if (!empty($post['arc_level_id'])){
                $post['restric_type'] = 2;
            }

            // 拼装会员ID及用户名
            $post['users_id'] = $this->users['users_id'];
            $post['author']   = empty($this->users['nickname']) ? $this->users['username'] : $this->users['nickname'];

            $newData = array(
                'typeid'      => empty($post['typeid']) ? 0 : $post['typeid'],
                'origin'      => empty($post['origin']) ? '网络' : $post['origin'],
                'lang'        => $this->home_lang,
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            );
            $data = array_merge($post, $newData);
            $aid = $this->archives_db->insertGetId($data);
            if (!empty($aid)) {
                $_POST['aid'] = $aid;
                model('UsersRelease')->afterSave($aid, $data, 'add', $this->channelData['table']);
                // 添加查询执行语句到mysql缓存表
                model('SqlCacheTable')->InsertSqlCacheTable();
                model('Arctype')->hand_type_count(['aid'=>[$aid]]);//统计栏目文档数量

                // 发送站内信给后台
                SendNotifyMessage($data, 20, 1, 0);
                // 邮箱发送
                $returnData['email'] = GetEamilSendData(tpCache('smtp'), $this->users, $data, 3);
                // 手机发送
                $returnData['mobile'] = GetMobileSendData(tpCache('sms'), $this->users, $data, 3);
                // 返回结束
                $this->success("投稿成功！", url('user/UsersRelease/release_centre', ['list' => 1]), $returnData);
            }
            $this->error("投稿失败！");
        }

        $typeid = input('param.typeid/d', 0);
        empty($typeid) && $typeid = getUsersConfigData('users.release_default_id') ? intval(getUsersConfigData('users.release_default_id')) : 0;

        // 自定义字段、栏目选项、Token验证
        $assign_data = $this->GetAssignData($this->channelID, null, ['typeid'=>$typeid]);
        $assign_data['channel_id'] = $this->channelID;

        //视频模型 需要数据 开始
        // 系统最大上传视频的大小
        $assign_data['upload_max_filesize'] = upload_max_filesize();

        //视频类型
        $media_type = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? $media_type : config('global.media_ext');
        $assign_data['media_type'] = $media_type;
        //文件类型
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['file_type'] = $file_type;

        // 视频模型配置信息
        $channelRow = Db::name('channeltype')->where('id', 5)->find();
        $channelRow['data'] = json_decode($channelRow['data'], true);
        $assign_data['channelRow'] = $channelRow;

        // 会员等级信息
        $assign_data['users_level'] = model('UsersLevel')->getList('level_id, level_name, level_value');
        //视频模型 需要数据 结束

        // 加载数据
        $this->assign($assign_data);
        $html = $this->fetch('users/article_add');

        $web_xss_filter = empty($this->eyou['global']['web_xss_filter']) ? 0 : intval($this->eyou['global']['web_xss_filter']);
        $replace = <<<EOF
    <script type="text/javascript">
        var __web_xss_filter__ = {$web_xss_filter};
        var __is_mobile__ = {$this->is_mobile};
    </script>
</head>
EOF;
        $html = str_ireplace('</head>', $replace, $html);

        return $html;
    }

    // 会员投稿--文章编辑
    public function article_edit()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $post['aid'] = intval($post['aid']);
            // 文档标题处理
            $post['title'] = !empty($post['title']) ? handleEyouFilterStr($post['title']) : '';
            // 文档tags处理
            if (!empty($post['tags'])) $post['tags'] = str_replace("，", ',', $post['tags']);
            
            // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
            $this->PostParameCheck($post);

            // POST参数处理后返回
            $post = $this->PostParameDealWith($post, 'edit');

            // 更新数据
            $newData = array(
                'typeid'      => empty($post['typeid']) ? 0 : $post['typeid'],
                'update_time' => getTime(),
            );

            if (!empty($post['arc_level_id'])){
                $post['restric_type'] = 2;
            }

            $data = array_merge($post, $newData);
            // 更新条件
            $where = [
                'aid'      => $data['aid'],
                'lang'     => $this->home_lang,
                'users_id' => $this->users_id,
            ];
            $ResultID = $this->archives_db->where($where)->update($data);
            if (!empty($ResultID)) {
                /*后置操作*/
                $data['attr']['typeid'] = $data['old_typeid'];
                model('UsersRelease')->afterSave($data['aid'], $data, 'edit', $this->channelData['table']);
                model('Arctype')->hand_type_count(['aid'=>[$data['aid']]]);//统计栏目文档数量
                /* END */
                $url = url('user/UsersRelease/release_centre',['list'=>1]);
                $this->success("编辑成功！", $url);
            }
            $this->error("编辑失败！");
        }

        // 文章ID
        $aid = input('param.aid/d');
        $channel_id = Db::name('archives')->where('aid', $aid)->getField('channel');
        $info = model('UsersRelease')->getInfo($aid, null, false);
        if (!empty($info['users_id']) && intval($info['users_id']) !== intval($this->users_id)) {
            $this->error('文档不存在！');
        }

        $is_remote_file = 0;
        $imgupload_list = $downfile_list = [];
        $video_list = '';
        // 图集模型
        if ($channel_id == 3) {
            $imgupload_list = model('UsersRelease')->getImgUpload($aid);
            foreach ($imgupload_list as $key => $val) {
                $imgupload_list[$key]['image_url'] = handle_subdir_pic($val['image_url']);
            }
        }else if ($channel_id == 4) {
            // 下载模型
            $downfile_list = model('DownloadFile')->getDownFile($aid);
            // 下载文件中是否存在远程链接
            foreach ($downfile_list as $key => $value) {
                if (1 == $value['is_remote']) {
                    $is_remote_file = 1;
                    break;
                }
            }
        }else if ($channel_id == 5){
            $video_list = Db::name('media_file')
                ->where('aid', $aid)
                ->order('file_id asc')
                ->select();
            $video_list = json_encode($video_list);
        }

        // 自定义字段、栏目选项、Token验证
        $assign_data = $this->GetAssignData($channel_id, $aid, $info);
        // 文章列表
        $assign_data['ArchivesData'] = $info;
        // 图集列表
        $assign_data['imgupload_list'] = $imgupload_list;
        // 是否远程链接
        $assign_data['is_remote_file'] = $is_remote_file;
        // 下载列表
        $assign_data['downfile_list'] = !empty($downfile_list) ? json_encode($downfile_list) : '';
        //视频模型视频列表
        $assign_data['video_list'] = $video_list;
        // 加载数据
        $assign_data['aid'] = $aid;
        $assign_data['channel_id'] = $channel_id;
        //视频模型 需要数据 开始
        // 系统最大上传视频的大小
        $assign_data['upload_max_filesize'] = upload_max_filesize();

        //视频类型
        $media_type = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? $media_type : config('global.media_ext');
        $assign_data['media_type'] = $media_type;
        //文件类型
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
        $assign_data['file_type'] = $file_type;

        // 视频模型配置信息
        $channelRow = Db::name('channeltype')->where('id', 5)->find();
        $channelRow['data'] = json_decode($channelRow['data'], true);
        $assign_data['channelRow'] = $channelRow;

        // 会员等级信息
        $assign_data['users_level'] = model('UsersLevel')->getList('level_id, level_name, level_value');
        //视频模型 需要数据 结束

        $this->assign($assign_data);
        $html = $this->fetch('users/article_edit');

        $web_xss_filter = empty($this->eyou['global']['web_xss_filter']) ? 0 : intval($this->eyou['global']['web_xss_filter']);
        $replace = <<<EOF
    <script type="text/javascript">
        var __web_xss_filter__ = {$web_xss_filter};
        var __is_mobile__ = {$this->is_mobile};
    </script>
</head>
EOF;
        $html = str_ireplace('</head>', $replace, $html);

        return $html;
    }

    // 会员投稿--文章删除
    public function article_del()
    {
        if(IS_AJAX_POST){
            $del_id = input('del_id/a');
            $aids   = eyIntval($del_id);
            if(!empty($del_id)){
                $Where = [
                    'aid'  => ['IN', $aids],
                    'lang' => $this->home_lang,
                    'users_id' => $this->users_id,
                ];
                $field = 'a.aid, a.typeid, a.channel, a.arcrank, a.is_recom, a.is_special, a.is_b, a.is_head, a.is_litpic, a.is_jump, a.is_slide, a.is_roll, a.is_diyattr, a.users_id, b.table';
                $archives = $this->archives_db
                    ->alias('a')
                    ->field($field)
                    ->join('__CHANNELTYPE__ b', 'a.channel = b.id', 'LEFT')
                    ->where($Where)
                    ->select();
                $typeids = [];
                $del_content = [];
                foreach ($archives as $key => $value) {
                    $del_content[$value['table']][] = $value['aid'];
                    if (-1 === $value['arcrank']) {
                        array_push($typeids, $value['typeid']);
                        unset($archives[$key]);
                    }
                }
                $return = $this->archives_db->where($Where)->delete();
                if (!empty($return)) {
                    foreach ($del_content as $k => $v){
                        Db::name($k.'_content')->where('aid','in',$v)->delete();
                        if ('media' == $k){
                            model('MediaFile')->delVideoFile($v);
                        }elseif ('download' == $k){
                            model('DownloadFile')->delDownFile($v);
                        }elseif ('images' == $k){
                            $image_url = Db::name('images_upload')->where('aid','in',$v)->column('image_url');
                            Db::name('images_upload')->where('aid','in',$v)->delete();
                            foreach ($image_url as $key => $val) {
                                $file_url_tmp = preg_replace('#^(/[/\w\-]+)?(/uploads/)#i', '.$2', $val);
                                if (!is_http_url($val) && file_exists($file_url_tmp)) {
                                    @unlink($file_url_tmp);
                                }
                            }
                        }
                    }
                    if (!empty($typeids)) model('SqlCacheTable')->UpdateDraftSqlCacheTable($typeids, 'del');
                    if (!empty($archives)) model('SqlCacheTable')->UpdateSqlCacheTable($archives, 'del', '', true);
                    $this->success('删除成功');
                } else {
                    $this->error('删除失败');
                }
            }
        }
    }

    public function get_addonextitem()
    {
        $typeid = input('post.typeid/d', 0);
        if (!empty($typeid)) {
            // 模型ID
            $channel_id = !empty($this->channelID) ? $this->channelID : Db::name('arctype')->where('id', $typeid)->getField('current_channel');

            // 获取加载到页面的数据
            $aid = input('post.aid/d', 0);
            $info['typeid'] = $typeid;
            $assign_data = $this->GetAssignData($channel_id, $aid, $info, true);
            $assign_data['channel_id'] = $channel_id;
            $this->assign($assign_data);

            // 加载模板处理
            $filename = 'users_release_field';
            if (isMobile()) $filename .= '_m';

            // 模板版本号处理
            $usersTpl2xVersion = getUsersTpl2xVersion();
            if ($usersTpl2xVersion == 'v2.x' || in_array($this->usersTplVersion, ['v5'])) {
                $web_users_tpl_theme = tpCache('web.web_users_tpl_theme') ? tpCache('web.web_users_tpl_theme') : 'users';
                if (isMobile()) {
                    if (is_dir('./template/mobile/')) {
                        $html = $this->fetch("./template/".TPL_THEME."mobile/{$web_users_tpl_theme}/{$filename}.htm");
                    } else {
                        $html = $this->fetch("./template/".TPL_THEME."pc/{$web_users_tpl_theme}/wap/{$filename}.htm");
                    }
                } else {
                    $html = $this->fetch("./template/".TPL_THEME."pc/{$web_users_tpl_theme}/{$filename}.htm");
                }
            } else {
                $web_users_tpl_theme = 'users';
                if ($this->usersTplVersion != 'v1') $web_users_tpl_theme .= '_' . $this->usersTplVersion;
                $html = $this->fetch("./public/static/template/{$web_users_tpl_theme}/{$filename}.htm");
            }
        } else {
            $html = '';
            $assign_data['htmltextField'] = '';
        }

        // 返回页面数据
        $result = [
            'html' => $html,
            'htmltextField' => $assign_data['htmltextField'],
            'is_litpic_users_release' => $this->channelData['is_litpic_users_release']
        ];
        $channel_id = !empty($channel_id) ? $channel_id : 0;
        $channel_data = $this->channel_addonextitem($channel_id);
        if (!empty($channel_data)) $result = array_merge($result, $channel_data);
        // sleep(1);
        $this->success('请求成功', null, $result);
    }

    //get_addonextitem一些模型后续处理
    public function channel_addonextitem($channel=0)
    {
        $data = [];
        if (4 == $channel) {
            $data['download']['users_level'] = model('UsersLevel')->getList('level_id, level_name');
            //下载模型自定义属性字段
            $attr_field = Db::name('download_attr_field')->select();
            $servername_use = 0;
            if ($attr_field) {
                $servername_info = [];
                for ($i = 0; $i < count($attr_field); $i++) {
                    if ($attr_field[$i]['field_name'] == 'server_name') {
                        if ($attr_field[$i]['field_use'] == 1) {
                            $servername_use = 1;
                        }
                        $servername_info = $attr_field[$i];
                        break;
                    }
                }
                $data['download']['servername_info'] = $servername_info;
            }
            $data['download']['attr_field'] = $attr_field;
            $data['download']['servername_use'] = $servername_use;

            $servername_arr = unserialize(tpCache('download.download_select_servername'));
            $data['download']['default_servername'] = $servername_arr?$servername_arr[0]:'立即下载';
            $weapp = Db::name('weapp')->where('code','in',['Qiniuyun','AliyunOss','Cos','AwsOss'])->where('status',1)->getAllWithIndex('code');
            $config = Db::name('channeltype')->where('nid','download')->value('data');
            $config = json_decode($config,true);
            $upload_flag_name = '';
            if (!empty($config['qiniuyun_open']) && '1' == $config['qiniuyun_open'] && !empty($weapp['Qiniuyun'])) {
                $upload_flag = 'qny';
                $upload_flag_name = '七牛云';
            } else if (!empty($config['oss_open']) && '1' == $config['oss_open'] && !empty($weapp['AliyunOss'])) {
                $upload_flag = 'oss';
                $upload_flag_name = '阿里云';
            } else if (!empty($config['cos_open']) && '1' == $config['cos_open'] && !empty($weapp['Cos'])) {
                $upload_flag = 'cos';
                $upload_flag_name = '腾讯云';
            } else if (!empty($config['aws_open']) && '1' == $config['aws_open'] && !empty($weapp['AwsOss'])) {
                $upload_flag = 'aws';
                $upload_flag_name = '亚马逊S3';
            }else{
                $upload_flag = 'local';
            }
            $data['download']['upload_flag'] = $upload_flag;
            $data['download']['upload_flag_name'] = $upload_flag_name;
            // 系统最大上传大小  限制类型
            $file_size = tpCache('basic.file_size');
            $postsize       = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
            $fileupload     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
            $min_size = strval($file_size) < strval($postsize) ? $file_size : $postsize;
            $min_size = strval($min_size) < strval($fileupload) ? $min_size : $fileupload;
            $basic['file_size'] = intval($min_size) * 1024 * 1024;
            $file_type = tpCache('basic.file_type');
            $basic['file_type'] = !empty($file_type) ? $file_type : "zip|gz|rar|iso|doc|xls|ppt|wps";
            $data['download']['basic'] = $basic;
        }
        return $data;
    }

    // 处理加载到页面的数据
    private function GetAssignData($channel_id = null, $aid = null, $info = array(), $is_if = false)
    {
        /* 自定义字段 */
        if (!empty($aid) && !empty($info) && !empty($channel_id)) {
            $addonFieldExtList = model('UsersRelease')->GetUsersReleaseData($channel_id, $info['typeid'], $aid, 'edit');
        } else if (!empty($channel_id) && !empty($info)){
            $addonFieldExtList = model('UsersRelease')->GetUsersReleaseData($channel_id, $info['typeid']);
        } else {
            $addonFieldExtList = model('UsersRelease')->GetUsersReleaseData($channel_id);
        }

        $controller_name = '';
        if (!empty($channel_id)) {
            $channeltypeInfo = Db::name('channeltype')->where(['id'=>$channel_id])->find();
            if (!empty($channeltypeInfo['ifsystem'])) {
                $controller_name = $channeltypeInfo['ctl_name'];
            } else {
                $controller_name = 'Custom';
            }
        }

        // 匹配显示的自定义字段
        $htmltextField = []; // 富文本的字段名
        $first_html = '';
        foreach ($addonFieldExtList as $key => $val) {
            if ($val['dtype'] == 'htmltext') {
                if (empty($first_html) && $val['name'] != 'content_ey_m'){
                    if ('content' == $val['name'] || 'Custom' == $controller_name) {
                        $addonFieldExtList[$key]['first'] = 1;
                        $first_html = 1;
                    }
                }
                array_push($htmltextField, $val['name']);
            }
        }

        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        $assign_data['htmltextField'] = $htmltextField;
        /* END */

        if (empty($is_if)) {
            /*允许发布文档列表的栏目*/
            $typeid = 0;
            if (!empty($info['typeid'])) $typeid = $info['typeid'];
            $arctype_html = $this->allow_release_arctype($typeid);
            $assign_data['arctype_html'] = $arctype_html;
            /* END */

            /*封装表单验证隐藏域*/
            static $request = null;
            if (null == $request) { $request = Request::instance(); }  
            $token = $request->token();
            $assign_data['TokenValue'] = " <input type='hidden' name='__token__' value='{$token}'/> ";
            /* END */
        }

        return $assign_data;
    }

    // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
    private function PostParameCheck($post = array(), $channel_id = 1)
    {
        if (empty($post)) $this->error('提交错误！', null, 'title');

        // 判断文章标题是否为空
        if (empty($post['title'])) $this->error('请填写文章标题！', null, 'title');

        // 判断所属栏目是否为空
        if (empty($post['typeid'])) $this->error('请选择所属栏目！', null, 'typeid');

        // 如果模型不允许重复标题则执行
        $is_repeat_title = $this->channeltype_db->where('id', $channel_id)->getField('is_repeat_title');
        if (empty($is_repeat_title)) {
            $where = [
                'title' => $post['title'],
                // 'channel' => $channel_id,
            ];
            if (!empty($post['aid'])) $where['aid'] = ['NOT IN', $post['aid']];
            $count = $this->archives_db->where($where)->count();
            if(!empty($count)) $this->error('文档标题不允许重复！', null, 'title');
        }

        // 数据验证
        $rule = [
            'title' => 'require|token',
        ];
        $message = [
            'title.require' => '不可为空！',
        ];
        $validate = new \think\Validate($rule, $message);
        if(!$validate->check($post)) $this->error('不允许连续提交！');
    }

    // POST参数处理后返回
    private function PostParameDealWith($post = array(), $type = 'add')
    {
        // 内容详情
        $content = input('post.addonFieldExt.content', '', null);

        // 自动获取内容第一张图片作为封面图
        $post['litpic'] = !empty($post['litpic_inpiut']) ? $post['litpic_inpiut'] : get_html_first_imgurl($content);

        // 是否有封面图
        $post['is_litpic'] = !empty($post['litpic']) ? 1 : 0;

        // SEO描述
        if ('add' == $type && !empty($content)) {
            $post['seo_description']= @msubstr(checkStrHtml($content), 0, get_seo_description_length(), false);
        }

        $post['addonFieldExt']['content'] = htmlspecialchars(strip_sql($content));

        if (empty($this->usersConfig['is_automatic_review'])) {
            // 自动审核关闭，文章默认待审核
            $post['arcrank']  = -1; // 待审核
        } else {
            // 自动审核开启，文章默认已审核
            $post['arcrank']  = 0;  // 已审核
        }

        return $post;
    }

    // 计算获取一天内的投稿文档合计次数
    private function GetOneDayReleaseCount()
    {
        $time1 = strtotime(date('Y-m-d', time()));
        $time2 = $time1 + 86399;
        $where = [
            'lang'     => $this->home_lang,
            'users_id' => $this->users_id,
            'add_time' => ['between time', [$time1, $time2]],
        ];
        $AidCount = $this->archives_db->where($where)->count();
        return $AidCount;
    }

    /**
     * 允许会员投稿发布的栏目列表
     */
    private function allow_release_arctype($typeid = 0)
    {
        // 查询会员投稿设置的投稿栏目
        $ids = [1, 3, 4, 5];
        $where = [
            'is_release' => 1,
            'lang' => $this->home_lang,
            'current_channel' => ['IN', $ids]
        ];
        $release_typeids = Db::name('arctype')->where($where)->column('id');

        // 生成栏目选择下拉列表
        $arctype_html = allow_release_arctype($typeid, $ids, true, $release_typeids, true);
        $arctype_html = str_ireplace('data-current_channel=', 'data-channel=', $arctype_html);
        $arctype_html = "<select name='typeid' id='typeid'><option value='0'>请选择栏目…</option>{$arctype_html}</select>";
        return $arctype_html;
    }

    /**
     * 模型字段 - 删除多图字段的图集
     */
    public function del_channelimgs()
    {
        if (IS_AJAX_POST) {
            $aid     = input('aid/d', '0');
            $channel = input('channel/d', ''); // 模型ID
            if (!empty($aid) && !empty($channel)) {
                $path      = input('param.filename/s', ''); // 图片路径
                $fieldid = input('param.fieldid/d'); // 多图字段
                $fieldname = Db::name('channelfield')->where(['id'=>$fieldid])->value('name');
                if (!empty($fieldname)) {
                    /*模型附加表*/
                    $table    = M('channeltype')->where('id', $channel)->getField('table');
                    $tableExt = $table . '_content';
                    /*--end*/

                    /*除去多图字段值中的图片*/
                    $info     = M($tableExt)->field("{$fieldname}")->where("aid", $aid)->find();
                    $valueArr = explode(',', $info[$fieldname]);
                    foreach ($valueArr as $key => $val) {
                        if ($path == $val) {
                            unset($valueArr[$key]);
                        }
                    }
                    $value = implode(',', $valueArr);
                    M($tableExt)->where('aid', $aid)->update(array($fieldname => $value, 'update_time' => getTime()));
                    /*--end*/
                }
            }
        }
    }
}