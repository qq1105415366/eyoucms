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

namespace app\home\controller;
use think\Db;
use think\Cache;
use think\template\driver\File;
use app\common\logic\BuildhtmlLogic;

class Buildhtml extends Base
{
    public $php_sessid;
    public $buildhtmlLogic;

    public function _initialize()
    {
        parent::_initialize();
        @ini_set('memory_limit','-1');
        $this->php_sessid = !empty($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '';
        if (!session('?admin_id')) {
            $this->error("只允许后台管理员操作！");
        } else {
            $seo_pseudo = tpCache('seo.seo_pseudo');
            if (2 != $seo_pseudo) {
                $this->error('当前不是静态页面模式！');
            }
        }
        $this->buildhtmlLogic = new BuildhtmlLogic;
    }

    /*
     * 清理缓存
     */
    private function clearCache()
    {
        Cache::clear('buildhtml');
        cache("channel_page_total_serialize".$this->php_sessid, null);
        cache("channel_info_serialize".$this->php_sessid, null);
        cache("has_children_Row_serialize".$this->php_sessid, null);
        cache("aid_arr_serialize".$this->php_sessid, null);
        cache("channel_arr_serialize".$this->php_sessid, null);
        cache("article_info_serialize".$this->php_sessid, null);
        cache("article_page_total_serialize".$this->php_sessid, null);
        cache("article_tags_serialize".$this->php_sessid, null);
        cache("article_attr_info_serialize".$this->php_sessid, null);
        cache("article_children_row_serialize".$this->php_sessid, null);
    }

    /*
     * 获取全站生成时，需要生成的页面的个数
     */
    public function buildIndexAll()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $this->clearCache();

        $uphtmltype = input('param.uphtmltype/d');
        if (!empty($uphtmltype)) { // 指定文档后全部生成
            $this->buildAppointAll($uphtmltype);
        }
        else { // 更新全部
            $channelData  = $this->getChannelData(0);
            $archivesArr = getAllArchivesAid(0, $this->home_lang);
            $articleData_pagetotal   = count($archivesArr['aid_arr']);
            $allpagetotal = 1 + $channelData['pagetotal'] + $articleData_pagetotal;
            $redata          = $this->handleBuildIndex();
            $msg             = empty($redata['msg']) ? '' : $redata['msg'];

            $data = [
                'achievepage'   => 1,
                'channelpagetotal'  => $channelData['pagetotal'],
                'articlepagetotal'  => $articleData_pagetotal,
                'allpagetotal'  => $allpagetotal,
            ];
            $this->success($msg, null, $data);
        }
    }

    /*
     * 指定文档生成全部(涉及栏目、首页)
     */
    private function buildAppointAll($uphtmltype = 0)
    {
        if (1 == $uphtmltype) { // 指定时间的文档更新
            $seo_start_time = $this->eyou['global']['seo_start_time'];
            $seo_start_time = !empty($seo_start_time) ? strtotime($seo_start_time) : 0;
            $startid = Db::name('archives')->where([
                    'add_time'  => ['egt', $seo_start_time],
                ])->order('aid asc')->limit(1)->value('aid');
            if (empty($startid)) {
                $startid = Db::name('archives')->max('aid');
                $startid += 1;
            }
        }
        else if (2 == $uphtmltype) { // 指定ID文档的全部更新
            $startid = $this->eyou['global']['seo_startid2'];
        }
        $archivesArr = getAllArchivesAid(0, $this->home_lang, $startid);
        $articleData_pagetotal   = count($archivesArr['aid_arr']);
        $channelpagetotal = 0;
        $typeid_arr = [];
        $counts_arr = [];
        if (!empty($articleData_pagetotal)) {
            $typeid_arr = $archivesArr['typeid_arr'];
            foreach ($archivesArr['typeid_arr'] as $key => $val) {
                // 包含所有的上级栏目
                $allParentRow = model('Arctype')->getAllPid($val);
                $typeid_arr = array_merge($typeid_arr, get_arr_column($allParentRow, 'id'));
            }
            $typeid_arr = array_unique($typeid_arr);
            foreach ($typeid_arr as $val){
                $channelData  = $this->getChannelData($val, false);
                $temp_c = intval($channelData['pagetotal']);
                $counts_arr[] = $temp_c;
                $channelpagetotal += $temp_c;
            }
        }

        $allpagetotal = 1 + $channelpagetotal + $articleData_pagetotal;
        $redata          = $this->handleBuildIndex();
        $msg             = empty($redata['msg']) ? '' : $redata['msg'];
        $data = [
            'achievepage'   => 1,
            'channelpagetotal'  => $channelpagetotal,
            'articlepagetotal'  => $articleData_pagetotal,
            'allpagetotal'  => $allpagetotal,
            'startid'   => $startid,
            'typeids'   => implode(',', $typeid_arr),
            'counts'   => implode(',',$counts_arr),
        ];
        $this->success($msg, null, $data);
    }

    /*
     * 生成首页静态页面
     */
    public function buildIndex()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制

        $param = input('param.');
        if (isset($param['seo_showmod'])) { // 是否要保存生成静态的配置
            $this->eyou['global']['seo_showmod'] = $param['seo_showmod'];
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('seo', ['seo_showmod'=>$param['seo_showmod']], $val['mark']);
                }
            } else {
                tpCache('seo', ['seo_showmod'=>$param['seo_showmod']]);
            }
            /*--end*/
        }

        $redata = $this->handleBuildIndex();
        $msg = empty($redata['msg']) ? '' : $redata['msg'];
        $this->success($msg);
    }

    /*
     * 处理生成首页
     */
    private function handleBuildIndex()
    {
        delFile(HTML_ROOT.'index');
        $msg = '';
        $indexurl = $this->request->domain().$this->root_dir;
        // 保存的文件名
        $seo_html_position_arr = explode('/', $this->eyou['global']['seo_html_position']);
        if (!empty($seo_html_position_arr)) {
            $savefilename = end($seo_html_position_arr);
        } else {
            $savefilename = 'index.html';
        }

        if (!empty($this->eyou['global']['seo_showmod'])){
            $seo_html_position   = !empty($this->eyou['global']['seo_html_position']) ? $this->eyou['global']['seo_html_position'] : '';
            if ($seo_html_position) {
                $seo_html_position = preg_replace('/^\.{1,}([\\\\\/]+)/i', '', $seo_html_position);
                $seo_html_position = ltrim($seo_html_position, '/');
                $seo_html_position = ROOT_PATH.$seo_html_position;
                $seo_html_position_path = dirname($seo_html_position);
                tp_mkdir($seo_html_position_path);
                clearstatcache(); // 清除文件夹权限缓存
                if (!is_writeable($seo_html_position_path)) {
                    $msg = "目录 {$seo_html_position_path} 没有权限写入，生成失败";
                    return ['code'=>0, 'msg'=>$msg];
                }
            }
            $result['pageurl'] = $this->request->domain() . ROOT_DIR; // 获取当前页面URL
            $result['pageurl_m'] = pc_to_mobile_url($result['pageurl']); // 获取当前页面对应的移动端URL
            // 移动端域名
            $result['mobile_domain'] = '';
            if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
                $result['mobile_domain'] = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain();
            }
            $eyou       = array(
                'field' => $result,
            );
            $this->eyou = array_merge($this->eyou, $eyou);
            $this->assign('eyou', $this->eyou);
            try {
                $savepath = './'.$savefilename;
                $tpl      = 'index';
                $seo_html_templet   = !empty($this->eyou['global']['seo_html_templet']) ? $this->eyou['global']['seo_html_templet'] : '';
                $seo_html_templet_arr = explode('.',$seo_html_templet);
                if(!empty($seo_html_templet_arr[0])){
                    $tpl = $seo_html_templet_arr[0];
                }
                $this->request->get(['m' => 'Index']); // 首页焦点
                $this->filePutContents($savepath, $tpl, 'pc', 0, '/', 0, 1, $result);
                if ($seo_html_position){
                    @copy($savepath, $seo_html_position);
                    @copy($savepath, './index.html');
                    $msg .= "成功更新主页HTML：<a href='".$indexurl."' target='_blank' onclick='layer.closeAll();'>浏览...</a><br />";
                }else{
                    $msg .= "成功更新主页HTML：".$savepath."<br /><a href='$savepath' target='_blank' onclick='layer.closeAll();'>浏览...</a><br />";
                }
            } catch (\Exception $e) {
                $msg .= '<span>index.html生成失败！' . $e->getMessage() . '</span><br>';
            }
        }else{
            @unlink('index.html');
            @unlink($savefilename);
            $msg .= "采用动态浏览模式：<a href='".$indexurl."' target='_blank' onclick='layer.closeAll();'>浏览...</a><br />";
        }

        return ['code'=>1, 'msg'=>$msg];
    }

    /*
     * 写入静态页面
     */
    private function filePutContents($savepath, $tpl, $model = 'pc', $pages = 0, $dir = '/', $tid = 0, $top = 1, $result = [])
    {
        ob_start();
        static $templateConfig = null;
        null === $templateConfig && $templateConfig = \think\Config::get('template');
        $templateConfig['view_path'] = "./template/".TPL_THEME."pc/";
        $template                    = "./template/".TPL_THEME."{$model}/{$tpl}.{$templateConfig['view_suffix']}";
        $content                     = $this->fetch($template, [], [], $templateConfig);

        /*解决模板里没有设置编码的情况*/
        if (!stristr($content, 'utf-8')) {
            $content = str_ireplace('<head>', "<head>\n<meta charset='utf-8'>", $content);
        }
        /*end*/

        if ($pages > 0) {
            $page = "/<a(.*?)href(\s*)=(\s*)[\'|\"](.*?)page=([0-9]*)(.*?)data-ey_fc35fdc=[\'|\"]html[\'|\"](.*?)>/i";
            preg_match_all($page, $content, $matchpage);

            $dir = trim($dir, '.');
            $seo_html_listname = $this->eyou['global']['seo_html_listname'];
            foreach ($matchpage[0] as $key1 => $value1) {
                if ($matchpage[5][$key1] == 1) {
                    if ($top == 1) {
                        $url = $dir;
                    } elseif ($top == 2) {
                        $url = $dir . '/lists_' . $tid . '.html';
                    } else {
                        $url = $dir . '/lists_' . $tid . '.html';
                    }
                } else {
                    if ($seo_html_listname == 4) {
                        if (!empty($result['rulelist'])) {
                            if (!preg_match('/{page}/i', $result['rulelist'])) { // 没有分页变量的情况
                                $rulelist_filename = '';
                            } else {
                                $rulelist = trim($result['rulelist'], '/');
                                $rulelist_filename = preg_replace('/^((.*)\/)?([^\/]+)$/i', '${3}', $rulelist);
                                $rulelist_filename = str_replace("{tid}", $tid, $rulelist_filename);
                                $rulelist_filename = str_replace("{page}", $matchpage[5][$key1], $rulelist_filename);
                            }
                            $url = $dir;
                            if (!empty($rulelist_filename)) {
                                $url .= '/' . $rulelist_filename;
                            }
                        }else{
                            $url = $dir . '/list_' . $tid . '_' . $matchpage[5][$key1] . '.html';
                        }
                    } else {
                        $url = $dir . '/lists_' . $tid . '_' . $matchpage[5][$key1] . '.html';
                    }
                }
                $url        = ROOT_DIR . '/' . trim($url, '/');
                if (!preg_match('/^(.*)\.([^\/\.]+)$/i', $url)) {
                    $url .= '/';
                }
                $value1_new = preg_replace('/href(\s*)=(\s*)[\'|\"]([^\'\"]*)[\'|\"]/i', '', $value1);
                $value1_new = str_replace('data-ey_fc35fdc=', " href=\"{$url}\" data-ey_fc35fdc=", $value1_new);
                $content    = str_ireplace($value1, $value1_new, $content);
            }
        }
        $content = $this->pc_to_mobile_js($content, $result); // 生成静态模式下，自动加上PC端跳转移动端的JS代码
        echo $content;
        $_cache = ob_get_contents();
        ob_end_clean();
        static $File = null;
        null === $File && $File = new File;
        $savepath = $this->transcoding($savepath);  //转码
        $File->fwrite($savepath, $_cache);
    }

    /**
     *文件名含有中文才转码 
     *@param savepath 文件名
     */
    private function transcoding($savepath)
    {
        if (preg_match('/[\x{4e00}-\x{9fa5}]+/u', $savepath))
        {
            $encoding = mb_detect_encoding($savepath,['UTF-8','GBK','BIG5','CP936']);
            if (DIRECTORY_SEPARATOR == '/'){    //linux
                $savepath = iconv($encoding,'UTF-8',$savepath);
            }else{  //win
                $savepath = iconv($encoding,'GBK',$savepath);
            }
        }
        return $savepath;
    }

    /*
     * 生成文档静态页面
     */
    public function buildArticle()
    {
        function_exists('set_time_limit') && set_time_limit(0);
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $typeid      = input("param.id/d", 0); // 栏目ID
        $findex         = input("param.findex/d", 0);
        $achievepage = input("param.achieve/d", 0); // 已完成文档数
        if (empty($findex) && empty($achievepage)){
            $this->clearCache();
        }
        $data = $this->handelBuildArticleList($typeid, $findex, $achievepage,true,20);

        $this->success($data[0], null, $data[1]);
    }
    /*
     * 批量生成文档静态页面时候生成
     ** $typeid      栏目id
     * $aid         内容页id
     * $achievepage 已完成文档数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     * type         执行类型，0：aid指定的文档页，1：上一篇，2：下一篇
     */
    private function handelBuildArticleList($typeid, $nextid = 0, $achievepage = 0, $batch = true, $limit = 20)
    {
        !empty($this->eyou['global']['seo_pagesize']) && $limit = $this->eyou['global']['seo_pagesize'];
        $startid  = 0;
        $endid  = 0;
        $uphtmltype = input('param.uphtmltype/d');
        if (!empty($uphtmltype)) { // 更新选项/指定时间或者文档ID，最后都是转为以文档起始ID
            $startid = input('param.startid/d');
            empty($startid) && $startid  = !empty($this->eyou['global']['seo_startid']) ? intval($this->eyou['global']['seo_startid']) : 0;
            $endid  = !empty($this->eyou['global']['seo_endid']) ? intval($this->eyou['global']['seo_endid']) : 0;
        }
        $msg                  = "";
        $globalConfig         = $this->eyou['global'];
        $result               = $this->getArticleAidData($typeid,$startid,$endid);
        $aid_arr              = $result['aid_arr'];
        $channel_arr          = $result['channel_arr'];
        $allAttrInfo          = getAllAttrInfo($channel_arr);
        $allTags              = $result['allTags'];
        $has_children_Row     = $result['has_children_Row'];

        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['pagetotal']    = 0;
        if ($batch && $pagetotal > $achievepage) {
            while ($limit && isset($aid_arr[$nextid])) {
                $archives = getAllArchives($this->home_lang, 0, $aid_arr[$nextid]);
                try{
                    $row = $archives['info'][0];
                    $arctypeRow = $archives['arctypeRow'];
                    $attrInfo = getOneAttrInfo($allAttrInfo, $aid_arr[$nextid]);
                    $msg                 .= $msg_temp = $this->createArticle($row, $globalConfig, $arctypeRow, $allTags, $has_children_Row, $attrInfo);
                }catch (\Exception $e){}
                $data['achievepage'] += 1;
                $limit--;
                $nextid++;
            }
            $data['findex'] = $nextid;
        } else if (!$batch) {
            foreach ($aid_arr as $key => $val) {
                $archives = getAllArchives($this->home_lang, 0, $val);
                $row = $archives['info'][0];
                $arctypeRow = $archives['arctypeRow'];
                $attrInfo = getOneAttrInfo($allAttrInfo, $val);
                $msg                 .= $msg_temp = $this->createArticle($row, $globalConfig, $arctypeRow, $allTags, $has_children_Row, $attrInfo);
                $data['achievepage'] += 1;
                $data['findex']         = $key;
            }
        }
        if ($data['allpagetotal'] == $data['achievepage']) {  //生成完全部页面，删除缓存
            cache("aid_arr_serialize".$this->php_sessid, null);
            cache("channel_arr_serialize".$this->php_sessid, null);
            cache("article_page_total_serialize".$this->php_sessid, null);
            cache("article_tags_serialize".$this->php_sessid, null);
            cache("article_attr_info_serialize".$this->php_sessid, null);
            cache("article_children_row_serialize".$this->php_sessid, null);
        }

        return [$msg, $data];
    }
    /*
     * 获取所有需要生成静态的文档页面aid集合及相关信息
     * $typeid 栏目id，0：表示生成全部
     * $startid 起始ID（空或0表示从头开始）
     * $endid   结束ID（空或0表示直到结束ID）
     */
    private function getArticleAidData($typeid = 0,$startid = 0,$endid = 0){
        $aid_arr_serialize = cache("aid_arr_serialize".$this->php_sessid);
        if (empty($aid_arr_serialize)){
            $archivesArr = getAllArchivesAid($typeid, $this->home_lang,$startid,$endid);
            $aid_arr = $archivesArr['aid_arr'];
            $channel_arr = $archivesArr['channel_arr'];
            $pagetotal   = count($aid_arr);
            $allTags     = getAllTags();
            /*获取所有栏目是否有子栏目的数组*/
            $has_children_Row = Db::name('Arctype')->field('parent_id, count(id) AS total')->where([
                'current_channel'=>['neq', 51], // 过滤问答模型
                'is_del'    => 0,
            ])->group('parent_id')->getAllWithIndex('parent_id');
            cache("aid_arr_serialize".$this->php_sessid, serialize($aid_arr), null, 'buildhtml');
            cache("channel_arr_serialize".$this->php_sessid, serialize($channel_arr), null, 'buildhtml');
            cache("article_page_total_serialize".$this->php_sessid, $pagetotal, null, 'buildhtml');
            cache("article_tags_serialize".$this->php_sessid, serialize($allTags), null, 'buildhtml');
            cache("article_children_row_serialize".$this->php_sessid, serialize($has_children_Row), null, 'buildhtml');
        }else{
            $aid_arr          = unserialize($aid_arr_serialize);
            $channel_arr      = cache("channel_arr_serialize".$this->php_sessid);
            $channel_arr      = unserialize($channel_arr);
            $pagetotal        = cache("article_page_total_serialize".$this->php_sessid);
            $allTags          = unserialize(cache("article_tags_serialize".$this->php_sessid));
            $has_children_Row = unserialize(cache("article_children_row_serialize".$this->php_sessid));
        }

        return [
            'aid_arr' => $aid_arr,
            'channel_arr'   => $channel_arr,
            'pagetotal' => $pagetotal,
            'allTags' => $allTags,
            'has_children_Row' => $has_children_Row
        ];
    }

    /**
     * 获取所有详情页数据
     * $typeid      栏目id
     * $aid     文章id
     * $type    类型，0：aid指定的内容，1：上一篇，2：下一篇
     */
    private function getArticleData($typeid, $aid, $type = 0)
    {
        $info_serialize = cache("article_info_serialize".$this->php_sessid);
        if (empty($info_serialize)) {
            if ($type == 0) {
                $data = getAllArchives($this->home_lang, $typeid, $aid);
            } else if ($type == 1) {
                $data = getPreviousArchives($this->home_lang, $typeid, $aid);
            } else if ($type == 2) {
                $data = getNextArchives($this->home_lang, $typeid, $aid);
            }
            $info        = $data['info'];
            $aid_arr = $typeid_arr = $channel_arr = [];
            foreach ($info as $key => $val) {
                $aid_arr[] = $val['aid'];
                $typeid_arr[] = $val['typeid'];
                $channel_arr[$val['channel']][] = $val['aid'];
            }
            $pagetotal   = count($info);
            $allTags     = getAllTags($aid_arr);
            $allAttrInfo = getAllAttrInfo($channel_arr);
            /*获取所有栏目是否有子栏目的数组*/
            $has_children_Row = model('Arctype')->hasChildren($typeid_arr);
            cache("article_info_serialize".$this->php_sessid, serialize($data), null, 'buildhtml');
            cache("article_page_total_serialize".$this->php_sessid, $pagetotal, null, 'buildhtml');
            cache("article_tags_serialize".$this->php_sessid, serialize($allTags), null, 'buildhtml');
            cache("article_attr_info_serialize".$this->php_sessid, serialize($allAttrInfo), null, 'buildhtml');
            cache("article_children_row_serialize".$this->php_sessid, serialize($has_children_Row), null, 'buildhtml');
        } else {
            $data             = unserialize($info_serialize);
            $pagetotal        = cache("article_page_total_serialize".$this->php_sessid);
            $allTags          = unserialize(cache("article_tags_serialize".$this->php_sessid));
            $allAttrInfo      = unserialize(cache("article_attr_info_serialize".$this->php_sessid));
            $has_children_Row = unserialize(cache("article_children_row_serialize".$this->php_sessid));
        }

        return ['data' => $data, 'pagetotal' => $pagetotal, 'allTags' => $allTags, 'allAttrInfo' => $allAttrInfo, 'has_children_Row' => $has_children_Row];
    }

    /**
     * 更新文档内容时候生成处理生成内容页
     * $typeid      栏目id
     * $aid         内容页id
     * $findex         下一次执行栏目id
     * $achievepage 已完成文档数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     * type         执行类型，0：aid指定的文档页，1：上一篇，2：下一篇
     *
     */
    private function handelBuildArticle($typeid, $aid = 0, $nextid = 0, $achievepage = 0, $batch = true, $limit = 20, $type = 0)
    {
        $msg                  = "";
        $globalConfig         = $this->eyou['global'];
        $result               = $this->getArticleData($typeid, $aid, $type);
        $info                 = $result['data']['info'];
        $arctypeRow           = $result['data']['arctypeRow'];
        $allTags              = $result['allTags'];
        $has_children_Row     = $result['has_children_Row'];
        $allAttrInfo          = $result['allAttrInfo'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['pagetotal']    = 0;

        if ($batch && $pagetotal > $achievepage) {
            while ($limit && isset($info[$nextid])) {
                $row                 = $info[$nextid];
                $msg                 .= $msg_temp = $this->createArticle($row, $globalConfig, $arctypeRow, $allTags, $has_children_Row, $allAttrInfo);
                $data['achievepage'] += 1;
                $limit--;
                $nextid++;
            }
            $data['findex'] = $nextid;
        } else if (!$batch) {
            foreach ($info as $key => $row) {
                $msg                 .= $msg_temp = $this->createArticle($row, $globalConfig, $arctypeRow, $allTags, $has_children_Row, $allAttrInfo);
                $data['achievepage'] += 1;
                $data['findex']         = $key;
            }
        }
        if ($data['allpagetotal'] == $data['achievepage']) {  //生成完全部页面，删除缓存
            cache("article_info_serialize".$this->php_sessid, null);
            cache("article_page_total_serialize".$this->php_sessid, null);
            cache("article_tags_serialize".$this->php_sessid, null);
            cache("article_attr_info_serialize".$this->php_sessid, null);
            cache("article_children_row_serialize".$this->php_sessid, null);
        }

        return [$msg, $data];
    }

    /*
     * 生成详情页静态页面
     */
    private function createArticle($result, $globalConfig, $arctypeRow, $allTags, $has_children_Row, $allAttrInfo)
    {
        $msg = "";
        $aid = $result['aid'];
        static $arc_seo_description_length = null;
        null === $arc_seo_description_length && $arc_seo_description_length = get_seo_description_length();
        $this->request->get(['aid' => $aid]); // post
        $this->request->get(['tid' => $result['typeid']]); // post

        $arctypeInfo = $arctypeRow[$result['typeid']];
        /*排除文档模型与栏目模型对不上的文档 \ 问答模型 \ 外部链接跳转 | 阅读权限限制的文档*/
        if (empty($result) || $arctypeInfo['current_channel'] != $result['channel'] || 51 == $result['channel'] || (!empty($result['arcrank']) && $result['arcrank'] >0) || (!empty($arctypeInfo['typearcrank']) && $arctypeInfo['typearcrank'] > 0)) {
            return false;
        }
        /*--end*/
        $arctypeInfo = model('Arctype')->parentAndTopInfo($result['typeid'], $arctypeInfo);
        /*自定义字段的数据格式处理*/
        $arctypeInfo = $this->fieldLogic->getTableFieldList($arctypeInfo, config('global.arctype_channel_id'));
        /*是否有子栏目，用于标记【全部】选中状态*/
        $arctypeInfo['has_children'] = !empty($has_children_Row[$result['typeid']]) ? 1 : 0;
        /*--end*/
        // 文档模板文件，不指定文档模板，默认以栏目设置的为主
        empty($result['tempview']) && $result['tempview'] = $arctypeInfo['tempview'];

        /*给没有type前缀的字段新增一个带前缀的字段，并赋予相同的值*/
        foreach ($arctypeInfo as $key => $val) {
            if (!preg_match('/^type/i', $key)) {
                $key_new = 'type' . $key;
                !array_key_exists($key_new, $arctypeInfo) && $arctypeInfo[$key_new] = $val;
            }
        }
        /*--end*/

        $result = array_merge($arctypeInfo, $result);

        // 获取当前页面URL
        $result['arcurl'] = $result['pageurl'] = $result['pageurl_m'] = '';
        if ($result['is_jump'] != 1) {
            $result['arcurl'] = $result['pageurl'] = arcurl('home/View/index', $result, true, true);
            $result['pageurl_m'] = pc_to_mobile_url($result['pageurl'], $result['typeid'], $result['aid']); // 获取当前页面对应的移动端URL
        }
        /*--end*/

        // 移动端域名
        $result['mobile_domain'] = '';
        if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
            $result['mobile_domain'] = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain(); 
        }
        
        $result['seo_title']       = set_arcseotitle($result['title'], $result['seo_title'], $result['typename'], $result['typeid'], $this->eyou['site']);
        $result['seo_description'] = checkStrHtml($result['seo_description']);
        $result['tags'] = empty($allTags[$aid]) ? '' : implode(',', $allTags[$aid]);
        $result['litpic'] = handle_subdir_pic($result['litpic']); // 支持子目录
        $result = view_logic($aid, $result['channel'], $result, $allAttrInfo); // 模型对应逻辑
        $result = $this->fieldLogic->getChannelFieldList($result, $result['channel']); // 自定义字段的数据格式处理

        if (!empty($result['users_id'])){
            $users_where['a.users_id'] = $result['users_id'];
        }elseif (!empty($result['admin_id'])){
            $users_where['a.admin_id'] = $result['admin_id'];
        }else {
            $users_where['a.admin_id'] = ['>',0];
        }
        $users = Db::name('users')->alias('a')->field('a.username,a.nickname,a.head_pic,b.level_name,b.level_value')->where($users_where)->join('users_level b','a.level = b.level_id','left')->find();
        if (!empty($users)) {
            $users['head_pic']  = get_default_pic($users['head_pic']);
            empty($users['nickname']) && $users['nickname'] = $users['username'];
        }

        $eyou       = array(
            'type'  => $arctypeInfo,
            'field' => $result,
            'users' => $users,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);

        // 模板文件
        $tpl = '';
        if (!empty($result['tempview']) && file_exists("./template/".TPL_THEME."pc/{$result['tempview']}")) {
            $tpl = str_replace('.' . $this->view_suffix, '', $result['tempview']);
        } else {
            $tpl = 'view_' . $result['nid'];
        }

        $dir = $this->buildhtmlLogic->getArticleDir($result);
        if (!empty($result['htmlfilename'])) {
            $aid = $result['htmlfilename'];
        }
        if (4 == $this->eyou['global']['seo_html_pagename']) {
            if (!empty($result['ruleview'])) {
                $savepath = $dir;
            }else{
                $savepath = $dir . '/' . $aid . '.html';
            }
        } else {
            $savepath = $dir . '/' . $aid . '.html';
        }

        try {
            $this->filePutContents($savepath, $tpl, 'pc', 0, '/', 0, 1, $result);
        } catch (\Exception $e) {
            $msg .= '<span>' . $savepath . '生成失败！' . $e->getMessage() . '</span><br>';
        }

        return $msg;
    }

    /*
     * 生成栏目静态页面
     * $id  tpyeid  栏目id
     * $findex         下一次执行栏目id
     * $achievepage 已完成页数
     *$batch        是否分批次执行，true：分批，false：不分批
     *
     */
    public function buildChannel()
    {
        function_exists('set_time_limit') && set_time_limit(0);
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $id          = input("param.id/d", 0); // 选中栏目ID（需要生成静态的栏目，需要生成全部时候为0）
        $findex         = input("param.findex/d", 0);   //栏目下标
        $index = input("param.index/d", 0);   //本栏目本次执行下标
        $achievepage = input("param.achieve/d", 0);  //已经执行完成的条数
        $type_index = input("param.type_index/d", 0);   //指定时间或者指定ID更新模式下，栏目集合的下标
        $parent = true;
        if (empty($findex) && empty($achievepage)){
            $this->clearCache();
        }
        // 指定文档后，生成的相关栏目
        $uphtmltype = input('param.uphtmltype/d');
        if (!empty($uphtmltype)) {
            $typeids = input("param.typeids/s");
            $counts = input("param.counts/s");
            $typeid_arr = explode(',', $typeids);
            $count_arr = explode(',', $counts);
            $id = $typeid_arr[$type_index];
            $count = $count_arr[$type_index];    //预计本栏目的页数
            $findex = 0;
            $parent = false;
        }
        $data = $this->handleBuildChannelList($id, $findex, $achievepage,true,$parent,$index);
        $result = $data[1];
        if (!empty($uphtmltype)){
            $result['type_index'] = $type_index;
            $result['achievepage'] = 0;
            if ($result['findex'] > 0){  //上一个栏目执行完成，执行下一个
                $result['type_index'] += 1;
                $result['achievepage'] = intval($count);
            }
            $result['uphtmltype'] = $uphtmltype;
        }


        $this->success($data[0], null,$result);
    }

    /*
     * 获取栏目数据
     * $id      栏目id
     * $parent        是否获取下级栏目    true：获取，false：不获取
     */
    private function getChannelData($id, $parent = true, $aid = 0)
    {
        $info_serialize = cache("channel_info_serialize".$this->php_sessid);
        if (empty($info_serialize)) {
            $result           = getAllArctype($this->home_lang, $id, $this->view_suffix, $parent, $aid);

            $info             = $result["info"];
            $pagetotal        = intval($result["pagetotal"]);
            $has_children_Row = model('Arctype')->hasChildren(get_arr_column($info, 'typeid'));
            /***********2020 05 19 过滤并删除外部链接生成的静态页面 本操作内容已经放置到common=>getAllArctypeCount方法处理 *************/
//            $seo_upnext = !empty($this->eyou['global']['seo_upnext']) ? true : false; // 是否更新子栏目
//            foreach ($info as $k => $v) {
//                if (empty($seo_upnext)) {
//                    if (!empty($id) && $id != $v['typeid']) { // 指定栏目ID生成
//                        unset($info[$k]);
//                        continue;
//                    } else if (empty($id) && !empty($v['parent_id'])) { // 全部生成栏目
//                        unset($info[$k]);
//                        continue;
//                    }
//                }
//                if ($v['is_part'] == 1 || $v['nid'] == 'ask') {//外部链接或问答模型
//                    unset($info[$k]);//从数组里移除
//                    $dir = ROOT_PATH . trim($v['dirpath'], '/');
//                    if (!empty($v['dirpath']) && true == is_dir($dir)) {//判断是否生成过文件夹,文件夹存在则删除
//                        $this->deldir($dir);
//                    }
//                }
//            }
//            $info = array_values($info);//重组数组
            /***********2020 05 19 新增 e*************/
            cache("channel_page_total_serialize".$this->php_sessid, $pagetotal, null, 'buildhtml');
            cache("channel_info_serialize".$this->php_sessid, serialize($info), null, 'buildhtml');
            cache("has_children_Row_serialize".$this->php_sessid, serialize($has_children_Row), null, 'buildhtml');
        } else {
            $info             = unserialize($info_serialize);
            $pagetotal        = cache("channel_page_total_serialize".$this->php_sessid);
            $has_children_Row = unserialize(cache("has_children_Row_serialize".$this->php_sessid));
        }

        return ['info' => $info, 'pagetotal' => $pagetotal, 'has_children_Row' => $has_children_Row];
    }
    /*
     * 处理生成栏目页
     * $id           栏目id
     * $findex        本次次执行栏目id
     * $achievepage   已完成页数
     * $batch        是否分批次执行，true：分批，false：不分批
     * $parent        是否获取下级栏目    true：获取，false：不获取
     * $index         本栏目本次执行第一条下标
     * $limit         单个栏目一次执行最多生成页数
     */
    private function handleBuildChannelList($id, $findex = 0, $achievepage = 0, $batch = true, $parent = true,$index = 0, $limit = 50){
        !empty($this->eyou['global']['seo_maxpagesize']) && $limit = $this->eyou['global']['seo_maxpagesize'];
        $msg                  = '';
        $result               = $this->getChannelData($id, $parent);
        $info                 = $result['info'];
        $has_children_Row     = $result['has_children_Row'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['index']  = 0;
        $data['findex']         = $findex;
        $data['pagetotal']   = 1;
        $data['typeid']      = 0;
        $data['typename'] = "";
        $info = array_values($info);//重组数组
        if ($batch && $data['allpagetotal'] > $data['achievepage']) {
            $row                 = !empty($info[$findex]) ? $info[$findex] : [];
            if (!empty($row)){
                list($msg_temp,$return_data)   = $this->createChannelList($row, $has_children_Row,$index,$limit);
                $msg .= $msg_temp;
                $data['achievepage'] += !empty($return_data['achieve']) ? $return_data['achieve'] : 1;
                $data['index']  = !empty($return_data['index']) ? $return_data['index'] : 0;
                if (empty($return_data['index'])){
                    $data['findex']         = $findex + 1;
                }else{
                    $data['findex']         = $findex ;
                }
            }else{
                $data['findex']         = $findex + 1;
            }
            $data['pagetotal']   = !empty($row['pagetotal']) ? $row['pagetotal'] : 1;
            $data['typeid']      = !empty($row['typeid']) ? $row['typeid'] : 0;
            $data['typename'] = !empty($row['typename']) ? $row['typename'] :"";
        } else if (!$batch) {
            foreach ($info as $key => $row) {
                $msg                 .= $this->createChannel($row, $has_children_Row);
                $data['pagetotal']   = $row['pagetotal'];
                $data['achievepage'] += $row['pagetotal'];
                $data['findex']         = $key;
                $data['typeid']   = !empty($row['typeid']) ? $row['typeid'] : 0;
                $data['typename'] = !empty($row['typename']) ? $row['typename'] :"";
            }
        }
        if ($data['allpagetotal'] == $data['achievepage']) {  //生成完全部页面，删除缓存
            cache("channel_page_total_serialize".$this->php_sessid, null);
            cache("channel_info_serialize".$this->php_sessid, null);
            cache("has_children_Row_serialize".$this->php_sessid, null);
        }

        return [$msg, $data];
    }
    /*
     * 处理生成栏目页
     * $id           typeid
     * $findex         下一次执行栏目id
     * $achievepage 已完成页数
     * $batch        是否分批次执行，true：分批，false：不分批
     * $parent        是否获取下级栏目    true：获取，false：不获取
     * $aid           文章页id，不等于0时，表示只获取文章页所在的列表页重新生成静态(在添加或者编辑文档内容时使用)
     */
    private function handleBuildChannel($id, $findex = 0, $achievepage = 0, $batch = true, $parent = true, $aid = 0)
    {
        $msg                  = '';
        $result               = $this->getChannelData($id, $parent, $aid);
        $info                 = $result['info'];
        $has_children_Row     = $result['has_children_Row'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        /***********2020 05 19 过滤并删除外部链接生成的静态页面 s*************/
       // foreach ($info as $k => $v) {
       //     if ($v['is_part'] == 1 || $v['nid'] == 'ask') {//外部链接或问答模型
       //         unset($info[$k]);//从数组里移除
       //         $dir = ROOT_PATH . trim($v['dirpath'], '/');
       //         if (!empty($v['dirpath']) && true == is_dir($dir)) {//判断是否生成过文件夹,文件夹存在则删除
       //             $this->deldir($dir);
       //         }
       //     }
       // }
       //  $info = array_values($info);//重组数组
        /***********2020 05 19 新增 e*************/
        if ($batch && $data['allpagetotal'] > $data['achievepage']) {
            $row                 = !empty($info[$findex]) ? $info[$findex] : [];
            !empty($row) && $msg .= $msg_temp = $this->createChannel($row, $has_children_Row);
            $data['pagetotal']   = !empty($row['pagetotal']) ? $row['pagetotal'] : 1;
            $data['achievepage'] += !empty($row['pagetotal']) ? $row['pagetotal'] : 1;
            $data['findex']         = $findex + 1;
            $data['typeid']      = !empty($row['typeid']) ? $row['typeid'] : 0;
        } else if (!$batch) {
            foreach ($info as $key => $row) {
                $msg                 .= $msg_temp = $this->createChannel($row, $has_children_Row, $aid);
                $data['pagetotal']   = $row['pagetotal'];
                $data['achievepage'] += $row['pagetotal'];
                $data['findex']         = $key;
                $data['typeid']      = !empty($row['typeid']) ? $row['typeid'] : 0;
            }
        }
        if ($data['allpagetotal'] == $data['achievepage']) {  //生成完全部页面，删除缓存
            cache("channel_page_total_serialize".$this->php_sessid, null);
            cache("channel_info_serialize".$this->php_sessid, null);
            cache("has_children_Row_serialize".$this->php_sessid, null);
        }

        return [$msg, $data];
    }

    /*
     * 分批生成栏目页面
     * $index   当前执行的页码下标
     * $limit   每次最多生成个数
     */
    private function createChannelList($row, $has_children_Row,$index = 0, $limit = 10)
    {
        $msg               = "";
        $data = [
            'achieve' => 0,
            'index' => 0
        ];
        $seo_html_listname = $this->eyou['global']['seo_html_listname'];
        $seo_html_arcdir   = $this->eyou['global']['seo_html_arcdir'];
        $tid               = $row['typeid'];
        $this->request->get(['tid' => $tid]); // post
        $row        = $this->lists_logic($row, $has_children_Row);  // 模型对应逻辑
        $eyou       = array(
            'field' => $row,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);
        // 模板文件
        $tpl = '';
        if (!empty($row['templist']) && file_exists("./template/".TPL_THEME."pc/{$row['templist']}")) {
            $tpl = str_replace('.' . $this->view_suffix, '', $row['templist']);
        } else {
            $tpl = 'lists_' . $row['nid'];
        }
        if (in_array($row['current_channel'], [6, 8])) {   //留言模型或单页模型，不存在多页
            $this->request->get(['page' => '']);
            $dirpath     = explode('/', $eyou['field']['dirpath']);
            $dirpath_end = end($dirpath);
            if ($seo_html_listname == 1) {  //存放顶级目录
                $savepath = '.' . $seo_html_arcdir . '/' . $dirpath[1] . "/lists_" . $eyou['field']['typeid'] . ".html";
            } else if ($seo_html_listname == 3) { //存放子级目录
                $savepath = '.' . $seo_html_arcdir . '/' . $dirpath_end . "/lists_" . $eyou['field']['typeid'] . ".html";
            } else if ($seo_html_listname == 4) { //自定义存放目录
                $savepath = '.' . $seo_html_arcdir;
                $diy_dirpath = !empty($eyou['field']['diy_dirpath']) ? $eyou['field']['diy_dirpath'] : '';
                if (!empty($eyou['field']['rulelist'])) {
                    $rulelist = ltrim($eyou['field']['rulelist'], '/');
                    $rulelist = str_replace("{tid}", $eyou['field']['typeid'], $rulelist);
                    $rulelist = str_replace("{page}", '', $rulelist);
                    $rulelist = preg_replace('/{(栏目目录|typedir)}(\/?)/i', $diy_dirpath.'/', $rulelist);
                    $rulelist = '/'.ltrim($rulelist, '/');
                    $rulelist = preg_replace('/([\/]+)/i', '/', $rulelist);
                    $savepath .= $rulelist;
                }else{
                    $eyou['field']['rulelist'] = '{栏目目录}/list_{tid}_{page}.html';
                    $savepath .= $diy_dirpath . '/' . 'list_' . $eyou['field']['typeid'] . ".html";
                }
            } else {
                $savepath = '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/' . 'lists_' . $eyou['field']['typeid'] . ".html";
            }
            try {
                $this->filePutContents($savepath, $tpl, 'pc', 0, '/', 0, 1, $row);
                if ($seo_html_listname == 3) {
                    @copy($savepath, '.' . $seo_html_arcdir . '/' . $dirpath_end . '/index.html');
                    @unlink($savepath);
                } else if ($seo_html_listname == 4) {
                    if (preg_match('/^{(栏目目录|typedir)}\/list_{tid}_{page}\.html$/i', $eyou['field']['rulelist'])) {
                        $dst_savepath = preg_replace('/\/([^\/]+)$/i', '/index.html', $savepath);
                        @copy($savepath, $dst_savepath);
                        @unlink($savepath);
                    }
                } else if ($seo_html_listname == 2 || count($dirpath) < 3) {
                    @copy($savepath, '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/index.html');
                    @unlink($savepath);
                }
            } catch (\Exception $e) {
                $msg .= '<span>' . $savepath . '生成失败！' . $e->getMessage() . '</span><br>';
            }
            $data['achieve'] += 1;
        }else {    //多条信息的栏目
            $totalpage = $row['pagetotal'];
            $lastPage = cache("eyou-TagList-lastPage_".md5("{$tid}_{$this->php_sessid}"));  //本栏目真实条数
            $differ = 0;  //实际页数和预计页数的差集
            while ($limit && $totalpage > $index) {
                $msg .= $this->createMultipageChannel($index+1, $tid, $row, $has_children_Row, $seo_html_listname, $seo_html_arcdir, $tpl);
                $limit--;
                $index++;
                $data['achieve'] += 1;
                if (!empty($lastPage)) {
                    if ($totalpage > $lastPage) {
                        $differ = $totalpage - $lastPage;
                        $totalpage = $lastPage;
                    }
                }
            }
            if ($totalpage <= $index){   //已经执行完成本栏目
                $data['index'] = 0;
                $data['achieve'] += $differ;
            }else{
                $data['index'] = $index;
            }
        }

        return [$msg,$data];
    }
    /*
     * 生成栏目页面
     */
    private function createChannel($row, $has_children_Row, $aid = 0)
    {
        $msg               = "";
        $seo_html_listname = $this->eyou['global']['seo_html_listname'];
        $seo_html_arcdir   = $this->eyou['global']['seo_html_arcdir'];
        $tid               = $row['typeid'];
        $this->request->get(['tid' => $tid]); // post

        $row        = $this->lists_logic($row, $has_children_Row);  // 模型对应逻辑
        $eyou       = array(
            'field' => $row,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);

        // 模板文件
        $tpl = '';
        if (!empty($row['templist']) && file_exists("./template/".TPL_THEME."pc/{$row['templist']}")) {
            $tpl = str_replace('.' . $this->view_suffix, '', $row['templist']);
        } else {
            $tpl = 'lists_' . $row['nid'];
        }

        if (in_array($row['current_channel'], [6, 8])) {   //留言模型或单页模型，不存在多页
            $this->request->get(['page' => '']);
            $dirpath     = explode('/', $eyou['field']['dirpath']);
            $dirpath_end = end($dirpath);
            if ($seo_html_listname == 1) {  //存放顶级目录
                $savepath = '.' . $seo_html_arcdir . '/' . $dirpath[1] . "/lists_" . $eyou['field']['typeid'] . ".html";
            } else if ($seo_html_listname == 3) { //存放子级目录
                $savepath = '.' . $seo_html_arcdir . '/' . $dirpath_end . "/lists_" . $eyou['field']['typeid'] . ".html";
            } else if ($seo_html_listname == 4) { //自定义存放目录
                $savepath = '.' . $seo_html_arcdir;
                $diy_dirpath = !empty($eyou['field']['diy_dirpath']) ? $eyou['field']['diy_dirpath'] : '';
                if (!empty($eyou['field']['rulelist'])) {
                    $rulelist = ltrim($eyou['field']['rulelist'], '/');
                    $rulelist = str_replace("{tid}", $eyou['field']['typeid'], $rulelist);
                    $rulelist = str_replace("{page}", '', $rulelist);
                    $rulelist = preg_replace('/{(栏目目录|typedir)}(\/?)/i', $diy_dirpath.'/', $rulelist);
                    $rulelist = '/'.ltrim($rulelist, '/');
                    $rulelist = preg_replace('/([\/]+)/i', '/', $rulelist);
                    $savepath .= $rulelist;
                }else{
                    $eyou['field']['rulelist'] = '{栏目目录}/lists_{tid}_{page}.html';
                    $savepath .= $diy_dirpath . '/' . 'list_' . $eyou['field']['typeid'] . ".html";
                }
            } else {
                $savepath = '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/' . 'lists_' . $eyou['field']['typeid'] . ".html";
            }
            try {
                $this->filePutContents($savepath, $tpl, 'pc', 0, '/', 0, 1, $row);
                if ($seo_html_listname == 3) {
                    @copy($savepath, '.' . $seo_html_arcdir . '/' . $dirpath_end . '/index.html');
                    @unlink($savepath);
                } else if ($seo_html_listname == 4) {
                    if (preg_match('/^{(栏目目录|typedir)}\/list_{tid}_{page}\.html$/i', $eyou['field']['rulelist'])) {
                        $dst_savepath = preg_replace('/\/([^\/]+)$/i', '/index.html', $savepath);
                        @copy($savepath, $dst_savepath);
                        @unlink($savepath);
                    }
                } else if ($seo_html_listname == 2 || count($dirpath) < 3) {
                    @copy($savepath, '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/index.html');
                    @unlink($savepath);
                }
            } catch (\Exception $e) {
                $msg .= '<span>' . $savepath . '生成失败！' . $e->getMessage() . '</span><br>';
            }
        } else if (!empty($aid)) {     //只更新aid所在的栏目页码
            $orderby = getOrderBy($row['orderby'], $row['ordermode']);
            $limit   = getLocationPages($tid, $aid, $orderby);
            $i       = !empty($limit) ? ceil($limit / $row['pagesize']) : 1;
            $msg     .= $this->createMultipageChannel($i, $tid, $row, $has_children_Row, $seo_html_listname, $seo_html_arcdir, $tpl);

        } else {    //多条信息的栏目
            $totalpage = $row['pagetotal'];
            for ($i = 1; $i <= $totalpage; $i++) {
                $msg .= $this->createMultipageChannel($i, $tid, $row, $has_children_Row, $seo_html_listname, $seo_html_arcdir, $tpl);
                $lastPage = cache("eyou-TagList-lastPage_".md5("{$tid}_{$this->php_sessid}"));
                if (!empty($lastPage)) {
                    $totalpage = $lastPage;
                }
            }
        }

        return $msg;
    }

    /*
     * 创建有文档列表模型的静态栏目页面
     */
    private function createMultipageChannel($i, $tid, $row, $has_children_Row, $seo_html_listname, $seo_html_arcdir, $tpl)
    {
        $msg = "";
        $this->request->get(['page' => $i]);
        $row['seo_title'] = set_typeseotitle($row['typename'], $row['seo_title_tmp'], $this->eyou['site']);
        $row['seo_keywords'] = site_seo_handle($row['seo_keywords'], $this->eyou['site']);
        $row['seo_description'] = site_seo_handle($row['seo_description'], $this->eyou['site']);
        // $row        = $this->lists_logic($row, $has_children_Row);  // 模型对应逻辑
        $row['pageurl'] = get_list_only_pageurl($row['pageurl'], $row['typeid'], $row['rulelist'], $i);
        $eyou       = array(
            'field' => $row,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);
        $dirpath     = explode('/', $eyou['field']['dirpath']);
        $dirpath_end = end($dirpath);
        if ($seo_html_listname == 1) {  //存放顶级目录
            $dir      = '.' . $seo_html_arcdir . '/' . $dirpath[1];
            $savepath = '.' . $seo_html_arcdir . '/' . $dirpath[1] . "/lists_" . $eyou['field']['typeid'];
        } else if ($seo_html_listname == 3) { //存放子级目录
            $dir      = '.' . $seo_html_arcdir . '/' . $dirpath_end;
            $savepath = '.' . $seo_html_arcdir . '/' . $dirpath_end . "/lists_" . $eyou['field']['typeid'];
        } else if ($seo_html_listname == 4) { //自定义存放目录
            $dir      =  $savepath = '.' . $seo_html_arcdir;
            $diy_dirpath = !empty($eyou['field']['diy_dirpath']) ? $eyou['field']['diy_dirpath'] : '';
            if (!empty($eyou['field']['rulelist'])) {
                $rulelist = ltrim($eyou['field']['rulelist'], '/');
                $rulelist = str_replace("{tid}", $eyou['field']['typeid'], $rulelist);
                $rulelist = str_replace("{page}", $i, $rulelist);
                $rulelist = preg_replace('/{(栏目目录|typedir)}(\/?)/i', $diy_dirpath.'/', $rulelist);
                $rulelist = '/'.ltrim($rulelist, '/');
                $dir .= preg_replace('/\/([\/]*)([^\/]*)$/i', '', $rulelist);
                $savepath .= $rulelist;
            }else{
                $dir .= $diy_dirpath;
                $savepath .= $diy_dirpath . '/' . 'list_' . $eyou['field']['typeid'];
            }
        } else {
            $dir      = '.' . $seo_html_arcdir . $eyou['field']['dirpath'];
            $savepath = '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/' . 'lists_' . $eyou['field']['typeid'];
        }

        if ($seo_html_listname != 4 || empty($eyou['field']['rulelist'])) {
            if ($i > 1) {
                $savepath .= '_' . $i . '.html';
            } else {
                $savepath .= '.html';
            }
        }

        $top = 1;
        if ($i > 1 && $seo_html_listname == 1 && count($dirpath) > 2) {
            $top = 2;
        } else if ($i > 1 && $seo_html_listname == 3) {
            $top = 1;
        } else if ($i > 1 && $seo_html_listname == 4) {
            $top = 1;
        }
        try {
            $this->filePutContents($savepath, $tpl, 'pc', $i, $dir, $tid, $top, $row);
            if ($i == 1 && $seo_html_listname == 3) {
                @copy($savepath, '.' . $seo_html_arcdir . '/' . $dirpath_end . '/index.html');
                @unlink($savepath);
            } else if ($seo_html_listname == 4) {
                if ($i == 1) {
                    $dst_savepath = preg_replace('/\/([^\/]+)$/i', '/index.html', $savepath);
                    @copy($savepath, $dst_savepath);
                    @unlink($savepath);
                } else if ($i > 1) {
                    if (!empty($eyou['field']['rulelist']) && !preg_match('/{page}/i', $eyou['field']['rulelist'])) { // 没有分页变量的情况
                        @unlink($savepath);
                    }
                }
            } else if ($i == 1 && ($seo_html_listname == 2 || count($dirpath) < 3)) {
                @copy($savepath, '.' . $seo_html_arcdir . $eyou['field']['dirpath'] . '/index.html');
                @unlink($savepath);
            }
        } catch (\Exception $e) {
            $msg .= '<span>' . $savepath . '生成失败！' . $e->getMessage() . '</span><br>';
        }

        return $msg;
    }

    /**
     * 更新静态生成页
     * @param int $aid 文章id
     * @param int $typeid 栏目id
     * @return boolean
     * $del_ids       删除的文章数组
     */
    public function upHtml()
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $type    = input('param.type/s');
        $aid     = input("param.id/d");
        $typeid  = input("param.t_id/d");
        $del_ids = input('param.del_ids/a');
        $lang    = input("param.lang/s", 'cn');
        $seo_uphtml_after_pernext  = input("param.seo_uphtml_after_pernext/d");
        $param = input('param.');
        $this->php_sessid .= 'upHtml'.json_encode($param);

        /*由于全站共用删除JS代码，这里排除不能发布文档的模型的控制器*/
        if ('index' != $type) {
            $ctl_name       = input("param.ctl_name/s");
            $channeltypeRow = Db::name('channeltype')
                ->where('nid', 'NOT IN', ['guestbook', 'single'])
                ->column('ctl_name');
            array_push($channeltypeRow, 'Archives', 'Arctype', 'Custom');
            if (!in_array($ctl_name, $channeltypeRow)) {
                $this->error("排除非发布文档的模型");
            }
        }
        /*end*/

        $seo_pseudo = $this->eyou['global']['seo_pseudo'];
        $seo_html_pagename = $this->eyou['global']['seo_html_pagename'];
        $this->clearCache();
        if ($seo_pseudo != 2) {
            $this->error("当前非静态模式，不做静态处理");
        }
        Cache::clear('archives');
        if (!empty($del_ids)) {    //删除文章页面
            $this->buildhtmlLogic->delViewHtml($del_ids, $this->eyou['global']);
        } else if (!empty($aid) && !empty($typeid)) {   //变更文档信息，更新文档页及相关的栏目页
            if ('view' == $type) {
                $this->handelBuildArticle($typeid, $aid, 0, 0, false, 1, 0);
                if (1 == $seo_uphtml_after_pernext) {
                    $this->handelBuildArticle($typeid, $aid, 0, 0, false, 1, 1); // 更新上篇
                    $this->handelBuildArticle($typeid, $aid, 0, 0, false, 1, 2); // 更新下篇
                }
            } else if ('lists' == $type) {
                $this->handleBuildChannel($typeid, 0, 0, false, false);
            } else {
                $this->handleBuildChannel($typeid, 0, 0, false, false, $aid);
                $this->handelBuildArticle($typeid, $aid, 0, 0, false);
            }
        } else if (!empty($typeid)) {     //变更栏目信息，更新栏目页
            $this->handleBuildIndex();
            $data = $this->handleBuildChannel($typeid, 0, 0, false, false);
        } else if ('index' == $type) {
            $this->handleBuildIndex();
        }

        $this->success("静态页面生成完成");
    }

    /*
     * 拓展页面相关信息
     */
    private function lists_logic($result = [], $has_children_Row = [])
    {
        if (empty($result)) {
            return [];
        }

        $tid = $result['typeid'];

        switch ($result['current_channel']) {
            case '6': // 单页模型
                {
                    $arctype_info = model('Arctype')->parentAndTopInfo($tid, $result);
                    if ($arctype_info) {
                        // 读取当前栏目的内容，否则读取每一级第一个子栏目的内容，直到有内容或者最后一级栏目为止。
                        $archivesModel = new \app\home\model\Archives();
                        $result_new = $archivesModel->readContentFirst($tid);
                        // 阅读权限 或 外部链接跳转
                        if ($result_new['arcrank'] == -1 || $result_new['is_part'] == 1) {
                            return false;
                        }
                        /*自定义字段的数据格式处理*/
                        $result_new = $this->fieldLogic->getChannelFieldList($result_new, $result_new['current_channel']);
                        /*--end*/

                        $result = array_merge($arctype_info, $result_new);

                        $result['templist'] = !empty($arctype_info['templist']) ? $arctype_info['templist'] : 'lists_' . $arctype_info['nid'];
                        $result['dirpath']  = $arctype_info['dirpath'];
                        $result['diy_dirpath']  = $arctype_info['diy_dirpath'];
                        $result['typeid']   = $arctype_info['typeid'];
                        $result['rulelist']  = $arctype_info['rulelist'];
                    }
                    break;
                }

            default:
                {
                    $result = model('Arctype')->parentAndTopInfo($tid, $result);
                    break;
                }
        }

        if (!empty($result)) {
            /*自定义字段的数据格式处理*/
            $result = $this->fieldLogic->getTableFieldList($result, config('global.arctype_channel_id'));
            /*--end*/
        }

        /*是否有子栏目，用于标记【全部】选中状态*/
        $result['has_children'] = !empty($has_children_Row[$tid]) ? 1 : 0;
        /*--end*/

        // seo
        if (!isset($result['seo_title_tmp'])) {
            $result['seo_title_tmp'] = $result['seo_title'];
        }
        $result['seo_title'] = set_typeseotitle($result['typename'], $result['seo_title_tmp'], $this->eyou['site']);
        $result['seo_keywords'] = site_seo_handle($result['seo_keywords'], $this->eyou['site']);
        $result['seo_description'] = site_seo_handle($result['seo_description'], $this->eyou['site']);
        $result['pageurl'] = $result['typeurl']; // 获取当前页面URL
        $result['pageurl_m'] = pc_to_mobile_url($result['pageurl'], $result['typeid']); // 获取当前页面对应的移动端URL
        // 移动端域名
        $result['mobile_domain'] = '';
        if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
            $result['mobile_domain'] = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain(); 
        }

        /*给没有type前缀的字段新增一个带前缀的字段，并赋予相同的值*/
        foreach ($result as $key => $val) {
            if (!preg_match('/^type/i', $key)) {
                $key_new = 'type' . $key;
                !array_key_exists($key_new, $result) && $result[$key_new] = $val;
            }
        }
        /*--end*/

        return $result;
    }

    /**
     * 生成静态模式下且PC和移动端模板分离，就自动给PC端加上跳转移动端的JS代码
     * @access public
     */
    private function pc_to_mobile_js($html = '', $result = [])
    {
        static $other_pcwapjs = null;
        null === $other_pcwapjs && $other_pcwapjs = tpCache('other.other_pcwapjs');
        if (!empty($other_pcwapjs)) {
            return $html;
        }

        if (file_exists('./template/'.TPL_THEME.'mobile')) { // 分离式模板

            /*是否开启手机站域名，并且配置*/
            if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
                $domain = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain();
            } else {
                $domain = true;
            }
            /*end*/

            $aid = input('param.aid/d');
            $tid = input('param.tid/d');
            if (!empty($aid)) { // 内容页
                $url = url('home/View/index', ['aid' => $aid], true, $domain, 1, 1, 0);
            } else if (!empty($tid)) { // 列表页
                $url = url('home/Lists/index', ['tid' => $tid], true, $domain, 1, 1, 0);
            } else { // 首页
                $url = $this->request->scheme().'://'. $this->request->host(true) . ROOT_DIR . '/index.php';
            }

            $jsStr = <<<EOF
    <meta http-equiv="mobile-agent" content="format=xhtml;url={$url}">
    <script type="text/javascript">if(window.location.toString().indexOf('pref=padindex') != -1){}else{if(/applewebkit.*mobile/i.test(navigator.userAgent.toLowerCase()) || (/midp|symbianos|nokia|samsung|lg|nec|tcl|alcatel|bird|dbtel|dopod|philips|haier|lenovo|mot-|nokia|sonyericsson|sie-|amoi|zte/.test(navigator.userAgent.toLowerCase()))){try{if(/android|windows phone|webos|iphone|ipod|blackberry/i.test(navigator.userAgent.toLowerCase())){window.location.href="{$url}";}else if(/ipad/i.test(navigator.userAgent.toLowerCase())){}else{}}catch(e){}}}</script>
EOF;
            $html  = str_ireplace('</head>', $jsStr . "\n</head>", $html);
        } else { // 响应式模板
            // 开启手机站域名，且配置
            if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
                if (empty($result['pageurl'])) {
                    $url = $this->request->subDomain($this->eyou['global']['web_mobile_domain']) . ROOT_DIR . '/index.php';
                } else {
                    $url = !preg_match('/^(http(s?):)?\/\/(.*)$/i', $result['pageurl']) ? $this->request->domain() . $result['pageurl'] : $result['pageurl'];
                    $url = preg_replace('/^(.*)(\/\/)([^\/]*)(\.?)(' . $this->request->rootDomain() . ')(.*)$/i', '${1}${2}' . $this->eyou['global']['web_mobile_domain'] . '.${5}${6}', $url);
                }

                $mobileDomain = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain();
                $jsStr        = <<<EOF
    <meta http-equiv="mobile-agent" content="format=xhtml;url={$url}">
    <script type="text/javascript">if(window.location.toString().indexOf('pref=padindex') != -1){}else{if(/applewebkit.*mobile/i.test(navigator.userAgent.toLowerCase()) || (/midp|symbianos|nokia|samsung|lg|nec|tcl|alcatel|bird|dbtel|dopod|philips|haier|lenovo|mot-|nokia|sonyericsson|sie-|amoi|zte/.test(navigator.userAgent.toLowerCase()))){try{if(/android|windows phone|webos|iphone|ipod|blackberry/i.test(navigator.userAgent.toLowerCase())){if(window.location.toString().indexOf('{$mobileDomain}') == -1){window.location.href="{$url}";}}else if(/ipad/i.test(navigator.userAgent.toLowerCase())){}else{}}catch(e){}}}</script>
EOF;
                $html         = str_ireplace('</head>', $jsStr . "\n</head>", $html);
            }
        }

        return $html;
    }

    /**
     * 删除文件夹
     * @param $dir
     * @return bool
     */
    private function deldir($dir)
    {
        //先删除目录下的文件：
        $fileArr = glob($dir.'/*.html');
        if (!empty($fileArr)) {
            foreach ($fileArr as $key => $val) {
                !empty($val) && @unlink($val);
            }
        }

        $fileArr = glob($dir.'/*');
        if(empty($fileArr)){ //目录为空
            rmdir($dir); // 删除空目录
        }
        return true;
    }
}