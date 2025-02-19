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

class Search extends Base
{
    private $searchword_db;

    public function _initialize() {
        parent::_initialize();
        $this->searchword_db = Db::name('search_word');
    }

    /**
     * 搜索主页
     */
    public function index()
    {
        $result = [];

        $result = $param = input('param.');

        $result['pageurl'] = request()->url(true); // 获取当前页面URL
        $result['pageurl_m'] = pc_to_mobile_url($result['pageurl']); // 获取当前页面对应的移动端URL
        // 移动端域名
        $result['mobile_domain'] = '';
        if (!empty($this->eyou['global']['web_mobile_domain_open']) && !empty($this->eyou['global']['web_mobile_domain'])) {
            $result['mobile_domain'] = $this->eyou['global']['web_mobile_domain'] . '.' . $this->request->rootDomain(); 
        }
        
        !isset($result['keywords']) && $result['keywords'] = '';
        $eyou = array(
            'field' => $result,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);

        $viewfile = 'index_search';

        if (config('city_switch_on') && !empty($this->home_site)) { // 多站点内置模板文件名
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile.".".$this->view_suffix;
            if (!file_exists($viewfilepath)) {
                $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$this->home_site;
                $viewfilepath2 = TEMPLATE_PATH.$this->theme_style_path.DS.'city'.DS.$this->home_site;
                if (file_exists($viewfilepath2) && !empty($this->eyou['global']['site_template'])) {
                    $viewfile = "city/{$this->home_site}/{$viewfile}";
                } else if (file_exists($viewfilepath) && !empty($this->eyou['global']['site_template'])) {
                    $viewfile = "{$this->home_site}/{$viewfile}";
                } else {
                    return $this->lists();
                }
            }
        } else if (config('lang_switch_on') && !empty($this->home_lang)) { // 多语言内置模板文件名
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile.".".$this->view_suffix;
            if (!file_exists($viewfilepath)) {
                $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile."_{$this->home_lang}.".$this->view_suffix;
                if (file_exists($viewfilepath)) {
                    $viewfile .= "_{$this->home_lang}";
                } else {
                    return $this->lists();
                }
            }
        } else {
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile.".".$this->view_suffix;
            if (!file_exists($viewfilepath)) {
                return $this->lists();
            }
        }

        return $this->fetch(":{$viewfile}");
    }

    /**
     * 搜索列表
     */
    public function lists()
    {
        $param = input('param.');
        $users = session('?users') ? session('users') : [];
        $users_id = !empty($users['users_id']) ? intval($users['users_id']) : 0;
        $admin_id = !empty($users['admin_id']) ? intval($users['admin_id']) : 0;
        $nowTime = getTime();

        /*记录搜索词*/
        if (!isset($param['keywords'])) {
            die('标签调用错误：缺少属性 name="keywords"，请查看标签教程修正 <a href="https://www.eyoucms.com/plus/view.php?aid=521" target="_blank">前往查看</a>');
        }
        $word = $this->request->param('keywords');
        if(empty($word)){
            $this->error(foreign_lang('system15', $this->home_lang));
        }
        $page = $this->request->param('page');
        if(!empty($word) && 2 > $page)
        {
            $word_decode = htmlspecialchars_decode($word);

            $searchConf = tpCache('search');
            if (!isset($searchConf['search_tabu_words'])) {
                $searchConf['search_tabu_words'] = ['<','>','"',';',',','@','&','#','\\','*'];
            } else {
                $searchConf['search_tabu_words'] = explode(PHP_EOL, $searchConf['search_tabu_words']);
            }
            /*前台禁止搜索开始*/
            if (!empty($searchConf['search_tabu_words'])) {
                foreach ($searchConf['search_tabu_words'] as $key => $val) {
                    if (strstr($word_decode, $val)) {
                        $msg = sprintf(foreign_lang('system13', $this->home_lang), $val);
                        $this->error($msg);
                    }
                }
            }
            /*if (is_dir('./weapp/Wordfilter/')) {
                $wordfilterRow = Db::name('weapp')->where(['code'=>'Wordfilter', 'status'=>1])->find();
                if(!empty($wordfilterRow['data'])){
                    $wordfilterRow['data'] = json_decode($wordfilterRow['data'], true);
                    if ($wordfilterRow['data']['search'] == 3){
                        $wordfilter = Db::name('weapp_wordfilter')->where(['title'=>$word, 'status'=>1])->find();
                        if(!empty($wordfilter)){
                            $this->error('包含敏感关键词，禁止搜索！');
                        }
                    }
                }
            }*/
            /*前台禁止搜索结束*/

            // 如果tag标签有，默认跳到tag列表页
            /*$tagInfo = Db::name('tagindex')
                ->alias('a')
                ->field('a.id AS tagid')
                ->join('taglist b','a.id=b.tid','left')
                ->where(['a.tag'=>$word,'b.arcrank'=>['egt',0]])
                ->find();
            if (!empty($tagInfo)) {
                $city_switch_on = config('city_switch_on');
                $domain = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${1}${3}${4}', tpCache('web.web_basehost'));
                if (empty($city_switch_on)) {
                    $tagurl = tagurl('home/Tags/lists', array('tagid'=>$tagInfo['tagid']));
                } else {
                    $tagurl = tagurl('home/Tags/lists', array('tagid'=>$tagInfo['tagid']), true, $domain);
                }
                $this->redirect($tagurl);
                exit;
            }*/

            $word = addslashes($word);

            $method = input('param.method/d');
            if (!empty($method)) {
                /*搜索频率限制 start*/
                if (!isset($searchConf['search_second'])) {
                    $searchConf['search_second'] = 60;
                }
                if (!isset($searchConf['search_maxnum'])) {
                    $searchConf['search_maxnum'] = 5;
                }
                if (!isset($searchConf['search_locking'])) {
                    $searchConf['search_locking'] = 120;
                }
                if (empty($admin_id) && 0 < $searchConf['search_second']) {
                    $where = [];
                    if (!empty($users_id)) {
                        $where['users_id'] = $users_id;
                    } else {
                        $where['ip'] = clientIP();
                    }
                    $where2 = [
                        'update_time' => ['gt', $nowTime - $searchConf['search_second']],
                    ];
                    $searchTotal = Db::name('search_word')->where($where)->where($where2)->count();
                    $lockingInfo = Db::name('search_locking')->where($where)->find();
                    if ($searchTotal >= intval($searchConf['search_maxnum'])) {
                        if (empty($lockingInfo)) {
                            $lockingInfo = [
                                'users_id' => $users_id,
                                'ip' => clientIP(),
                                'locking_time' => $nowTime,
                                'add_time' => $nowTime,
                                'update_time' => $nowTime,
                            ];
                            $insertId = Db::name('search_locking')->insertGetId($lockingInfo);
                            $lockingInfo['id'] = $insertId;
                        } else {
                            if (($lockingInfo['locking_time'] + $searchConf['search_locking']) < $nowTime) {
                                Db::name('search_locking')->where(['id'=>$lockingInfo['id']])->update([
                                    'locking_time' => $nowTime,
                                    'update_time' => $nowTime,
                                ]);
                                $lockingInfo['locking_time'] = $nowTime;
                            }
                        }
                    }
                    if (!empty($lockingInfo)) {
                        $locking_time = !empty($lockingInfo['locking_time']) ? $lockingInfo['locking_time'] : 0;
                        $surplus_time = $locking_time + $searchConf['search_locking'] - $nowTime;
                        if ($surplus_time > 0) {
                            $minute = ceil($surplus_time/60);
                            $msg = sprintf(foreign_lang('system14', $this->home_lang), $minute);
                            $this->error($msg, null, [], $surplus_time);
                        }
                    }
                }
                /*搜索频率限制 end*/

                /*记录搜索词*/
                $row = $this->searchword_db->field('id')->where(['word'=>$word, 'lang'=>$this->home_lang])->find();
                if(empty($row))
                {
                    $this->searchword_db->insert([
                        'word'      => $word,
                        'sort_order'    => 100,
                        'users_id' => $users_id,
                        'ip' => clientIP(),
                        'lang'      => $this->home_lang,
                        'add_time'  => $nowTime,
                        'update_time'  => $nowTime,
                    ]);
                }else{
                    $this->searchword_db->where(['id'=>$row['id']])->update([
                        'searchNum'         =>  Db::raw('searchNum+1'),
                        'users_id' => $users_id,
                        'ip' => clientIP(),
                        'update_time'       => $nowTime,
                    ]);
                }
            }
        }
        /*--end*/

        $result = $param;
        !isset($result['keywords']) && $result['keywords'] = '';
        $eyou = array(
            'field' => $result,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);

        /*模板文件*/
        $viewfile = 'lists_search';
        $channelid = input('param.channelid/d');
        if (!empty($channelid)) {
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile."_{$channelid}.".$this->view_suffix;
            if (file_exists($viewfilepath)) {
                $viewfile .= "_{$channelid}";
            }
        }
        /*--end*/

        if (config('city_switch_on') && !empty($this->home_site)) { // 多站点内置模板文件名
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$this->home_site;
            $viewfilepath2 = TEMPLATE_PATH.$this->theme_style_path.DS.'city'.DS.$this->home_site;
            if (!empty($this->eyou['global']['site_template'])) {
                if (file_exists($viewfilepath2)) {
                    $viewfile = "city/{$this->home_site}/{$viewfile}";
                } else if (file_exists($viewfilepath)) {
                    $viewfile = "{$this->home_site}/{$viewfile}";
                }
            }
        } else if (config('lang_switch_on') && !empty($this->home_lang)) { // 多语言内置模板文件名
            $viewfilepath = TEMPLATE_PATH.$this->theme_style_path.DS.$viewfile."_{$this->home_lang}.".$this->view_suffix;
            if (file_exists($viewfilepath)) {
                $viewfile .= "_{$this->home_lang}";
            }
        }

        return $this->fetch(":{$viewfile}");
    }
}