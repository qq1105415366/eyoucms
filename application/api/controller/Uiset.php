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

namespace app\api\controller;

use think\Controller;
use think\Db;
// use think\Session;

class Uiset extends Controller
{
    public $uipath = '';
    public $theme_style = '';
    public $theme_style_path = '';
    public $v = '';
    private $urltypeid = 0;
    private $urlaid = 0;
    private $idcode = '';

    /**
     * 析构函数
     */
    function __construct() 
    {
        header("Cache-control: private");  // history.back返回后输入框值丢失问题
        parent::__construct();
        $this->theme_style = THEME_STYLE;
        $this->theme_style_path = THEME_STYLE_PATH;
        $this->uipath = RUNTIME_PATH.'ui/'.$this->theme_style_path.'/';
        if (!file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/uiset.txt') && !file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/uiset.txt')) {
            abort(404,'页面不存在');
        }
    }
    
    /*
     * 初始化操作
     */
    public function _initialize() 
    {
        //过滤不需要登陆的行为
        $ctl_act = CONTROLLER_NAME.'@'.ACTION_NAME;
        $ctl_all = CONTROLLER_NAME.'@*';
        $filter_login_action = config('filter_login_action');
        if (in_array($ctl_act, $filter_login_action) || in_array($ctl_all, $filter_login_action)) {
            //return;
        }else{
            if(!session('?admin_id')){
                $this->error('请先登录后台！');
                exit;
            }
        }

        /*电脑版与手机版的切换*/
        $this->v = input('param.v/s', '');
        $this->v = trim($this->v, '/');
        $this->assign('v', $this->v);
        /*--end*/

        $this->idcode = input('param.idcode/s');
        $this->urltypeid = input('param.urltypeid/d');
        $this->urlaid = input('param.urlaid/d');
        if (!empty($this->urlaid)) {
            $this->idcode = md5("{$this->v}_view_{$this->urlaid}");
        } else if (!empty($this->urltypeid)) {
            $this->idcode = md5("{$this->v}_lists_{$this->urltypeid}");
        }
    }

    public function submit()
    {
        if (is_adminlogin()) {
            $post = input('post.');
            $type = $post['type'];
            $id = $post['id'];
            $page = $post['page'];
            $content = isset($post['content']) ? $post['content'] : '';

            // 同步外观调试的变量值到config，前提是变量名在config是存在
            $this->synConfigVars($id, $content, $type);

            switch ($type) {
                case 'text':
                    $this->textHandle($id, $page, $post);
                    break;
                    
                case 'html':
                    $this->htmlHandle($id, $page, $post);
                    break;
                    
                case 'type':
                    $this->typeHandle($id, $page, $post);
                    break;
                    
                case 'arclist':
                    $this->arclistHandle($id, $page, $post);
                    break;
                    
                case 'channel':
                    $this->channelHandle($id, $page, $post);
                    break;
                    
                case 'upload':
                    $this->uploadHandle($id, $page, $post);
                    break;
                    
                case 'map':
                    $this->mapHandle($id, $page, $post);
                    break;
                    
                case 'code':
                    $this->codeHandle($id, $page, $post);
                    break;
                    
                case 'background':
                    $this->backgroundHandle($id, $page, $post);
                    break;

                default:
                    $this->error('不存在的可编辑区域');
                    exit;
                    break;
            }

        }

        $this->error('请先登录后台！');
        exit;
    }

    /**
     * 同步外观调试的变量值到config，前提是变量名在config是存在
     */
    private function synConfigVars($name, $value = '', $type = '')
    {
        if (in_array($type, array('text', 'html')) && !in_array($name, ['image_type','file_type','media_type'])) {
            $count = M('config')->where([
                'name'  => $name,
                'lang'  => $this->home_lang,
            ])->count('id');
            if ($count > 0) {
                if ($name == binaryJoinChar(config('binary.0'), 13)) {
                    $value = preg_replace('#<a([^>]*)>(\s*)'.binaryJoinChar(config('binary.1'), 18).'<(\s*)\/a>#i', '', htmlspecialchars_decode($value));
                    $value = htmlspecialchars($value);
                }
                $nameArr = explode('_', $name);
                M('config')->where([
                    'name'  => $name,
                    'lang'  => $this->home_lang,
                ])->cache(true,EYOUCMS_CACHE_TIME,'config')->update(array('value'=>$value));

                /*多语言*/
                if (is_language()) {
                    $langRow = Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpCache($nameArr[0], [$name=>$value], $val['mark']);
                    }
                } else { // 单语言
                    tpCache($nameArr[0], [$name=>$value]);
                }
                /*--end*/

                $this->success('操作成功');
                exit;
            }  
        }
    }

    /**
     * 纯文本编辑
     */
    public function text($id, $page)
    {
        $type = 'text';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $info = $data['info'];
            $type = $data['type'];
        }

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'   => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'text_m';
        } else {
            $viewfile = 'text';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 纯文本编辑处理
     */
    private function textHandle($id, $page, $post = array())
    {
        $type = 'text';
        $lang = $post['lang'];
        $content = !empty($post['content']) ? $post['content'] : '';
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'value'    => $content,
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 带html的富文本处理
     */
    public function html($id, $page)
    {
        $type = 'html';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $info = $data['info'];
            $type = $data['type'];
        }

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'   => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'html_m';
        } else {
            $viewfile = 'html';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 富文本编辑器处理
     */
    private function htmlHandle($id, $page, $post = array())
    {
        $type = 'html';
        $lang = $post['lang'];
        $content = !empty($post['content']) ? $post['content'] : '';
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'value'    => $content,
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 栏目编辑
     */
    public function type($id, $page)
    {
        $type = 'type';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $typeid = 0;
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $typeid = $data['typeid'];
            $type = $data['type'];
            $info = $data['info'];
        }

        /*所有栏目列表*/
        $map = array(
            'is_del'    => 0, // 回收站功能
            'status'   => 1,
        );
        $arctype_html = model('Arctype')->getList(0, $typeid, true, $map);
        $this->assign('arctype_html', $arctype_html);
        /*--end*/

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'typeid'   => $typeid,
            'info'  => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'type_m';
        } else {
            $viewfile = 'type';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 栏目编辑处理
     */
    private function typeHandle($id, $page, $post = array())
    {
        $type = 'type';
        $lang = $post['lang'];
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'typeid' => $post['typeid'],
                'info'   => $post,
                'lang'  => $lang,
                'idcode' => $this->idcode,
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 栏目文章编辑
     */
    public function arclist($id, $page)
    {
        $type = 'arclist';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $typeid = 0;
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $typeid = $data['typeid'];
            $type = $data['type'];
            $info = $data['info'];
        }

        /*允许发布文档列表的栏目*/
        $selected = $typeid;
        $arctype_html = allow_release_arctype($selected);
        $this->assign('arctype_html', $arctype_html);
        /*--end*/

        /*不允许发布文档的模型ID，用于JS判断*/
        $allow_release_channel = config('global.allow_release_channel');
        $js_allow_channel_arr = '[';
        foreach ($allow_release_channel as $key => $val) {
            if ($key > 0) {
                $js_allow_channel_arr .= ',';
            }
            $js_allow_channel_arr .= $val;
        }
        $js_allow_channel_arr = $js_allow_channel_arr.']';
        $this->assign('js_allow_channel_arr', $js_allow_channel_arr);
        /*--end*/

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'typeid'   => $typeid,
            'info'  => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'arclist_m';
        } else {
            $viewfile = 'arclist';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 栏目文章编辑处理
     */
    private function arclistHandle($id, $page, $post = array())
    {
        $type = 'arclist';
        $lang = $post['lang'];
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'typeid' => $post['typeid'],
                'info'   => $post,
                'lang'  => $lang,
                'idcode' => $this->idcode,
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 栏目列表编辑
     */
    public function channel($id, $page)
    {
        $type = 'channel';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $typeid = 0;
        $info = array();
        // $type = input('param.type/s');

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $typeid = $data['typeid'];
            $type = $data['type'];
            $info = $data['info'];
        }

        /*所有栏目列表*/
        $map = array(
            'is_del'    => 0, // 回收站功能
            'status'   => 1,
        );
        $arctype_html = model('Arctype')->getList(0, $typeid, true, $map);
        $this->assign('arctype_html', $arctype_html);
        /*--end*/

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'typeid'   => $typeid,
            'info'  => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'channel_m';
        } else {
            $viewfile = 'channel';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 栏目列表编辑处理
     */
    private function channelHandle($id, $page, $post = array())
    {
        $type = 'channel';
        $lang = $post['lang'];
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'typeid' => $post['typeid'],
                'info'   => $post,
                'lang'  => $lang,
                'idcode' => $this->idcode,
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 图片编辑
     */
    public function upload($id, $page)
    {
        $type = 'upload';
        $param = input('param.');
        $id = $param['id'];
        $page = $param['page'];
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $typeid = 0;
        $info = array();
        // $type = input('param.type/s');

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $type = $data['type'];
            $info = $data['info'];
        }

        if (!empty($info['value']) && is_http_url($info['value'])) {
            $is_remote = 1;
        } else {
            $is_remote = 0;
        }

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'  => $info,
            'lang'  => $lang,
            'is_remote' => $is_remote,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'upload_m';
        } else {
            $viewfile = 'upload';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 图片编辑处理
     */
    private function uploadHandle($id, $page, $post = array())
    {
        $type = 'upload';
        $lang = $post['lang'];

        $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
        $litpic = '';
        if ($is_remote == 1) {
            $litpic = $post['litpic_remote'];
        } else {
            $uplaod_data = func_common('litpic_local');
            if ($uplaod_data['errcode'] > 0) {
                $this->error($uplaod_data['errmsg']);
            }
            $litpic = handle_subdir_pic($uplaod_data['img_url']);
        }
        $oldhtml = urldecode($post['oldhtml']);
        $html = img_replace_url($oldhtml, $litpic);
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'value'    => htmlspecialchars($html),
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $data = [
                'imgsrc' => $litpic,
                'html'  => urlencode($html),
            ];
            $this->success('操作成功', null, $data);
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 背景图片编辑
     */
    public function background($id, $page)
    {
        $type = 'background';
        $param = input('param.');
        $id = $param['id'];
        $page = $param['page'];
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $typeid = 0;
        $info = array();
        // $type = input('param.type/s');

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $type = $data['type'];
            $info = $data['info'];
        }

        if (!empty($info['value']) && is_http_url($info['value'])) {
            $is_remote = 1;
        } else {
            $is_remote = 0;
        }

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'  => $info,
            'lang'  => $lang,
            'is_remote' => $is_remote,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'background_m';
        } else {
            $viewfile = 'background';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 背景图片编辑处理
     */
    private function backgroundHandle($id, $page, $post = array())
    {
        $type = 'background';
        $lang = $post['lang'];

        $is_remote = !empty($post['is_remote']) ? $post['is_remote'] : 0;
        $litpic = '';
        if ($is_remote == 1) {
            $litpic = $post['litpic_remote'];
        } else {
            $uplaod_data = func_common('litpic_local');
            if ($uplaod_data['errcode'] > 0) {
                $this->error($uplaod_data['errmsg']);
            }
            $litpic = handle_subdir_pic($uplaod_data['img_url']);
        }
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'value'    => $litpic,
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $data = [
                'imgsrc' => $litpic,
            ];
            $this->success('操作成功', null, $data);
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 百度地图
     */
    public function map($id, $page)
    {
        $type = 'map';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $keyword =  input('param.keyword/s');
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $info = $data['info'];
            $type = $data['type'];
        }

        $lng = 110.34678620675;
        $lat = 20.001944329655;
        $coordinate = !empty($info['value']) ? trim($info['value']) : '';
        if($coordinate && strpos($coordinate,',') !== false)
        {
            $map = explode(',',$coordinate);
            $lng = $map[0];
            $lat = isset($map[1]) ? $map[1] : 0;
        }

        $zoom = !empty($info['zoom']) ? intval($info['zoom']) : 13;

        $mapConf    = [
            'lng'   => $lng,
            'lat'   => $lat,
            'zoom'  => $zoom,
            'ak'   => base64_decode(config('global.baidu_map_ak')),
            'keyword'   => $keyword,
        ];

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'   => $info,
            'lang'  => $lang,
            'mapConf'   => $mapConf,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'map_m';
        } else {
            $viewfile = 'map';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 百度地图搜索
     */
    public function mapGetLocationByAddress()
    {
        $address =  input('param.address/s');
        $ak      = base64_decode(config('global.baidu_map_ak'));
        $url = $this->request->scheme()."://api.map.baidu.com/geocoder/v2/?address={$address}&city=&output=json&ak={$ak}";
        $result = httpRequest($url);
        $result = json_decode($result, true);
        if (!empty($result) && $result['status'] == 0) {
            $data['lng'] = $result['result']['location']['lng']; // 经度
            $data['lat'] = $result['result']['location']['lat']; // 纬度
            $data['map'] = $data['lng'].','.$data['lat'];
            $this->success('请求成功', null, $data);
        }

        $this->error('请求失败');
    }

    /**
     * 百度地图处理
     */
    private function mapHandle($id, $page, $post = array())
    {
        $type = 'map';
        $lang = $post['lang'];
        $zoom = !empty($post['zoom']) ? intval($post['zoom']) : 13;
        $location = !empty($post['location']) ? trim($post['location']) : '';
        if (empty($location)) {
            $this->error('请选定具体位置！');
        }
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'zoom'    => $zoom,
                    'value'    => $location,
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 源代码编辑处理
     */
    public function code($id, $page)
    {
        $type = 'code';
        $id = input('param.id/s');
        $page = input('param.page/s');
        $lang = input('param.lang/s', get_main_lang());
        $inckey = "{$lang}_{$type}_{$id}";
        $info = array();

        $filename = $this->uipath."{$page}.inc.php";
        $inc = ui_read_bidden_inc($filename);
        if ($inc && !empty($inc[$inckey])) {
            $data = json_decode($inc[$inckey], true);
            $info = $data['info'];
            $type = $data['type'];
        }

        $assign = array(
            'id'    => $id,
            'type'  => $type,
            'page'  => $page,
            'info'   => $info,
            'lang'  => $lang,
            'idcode' => $this->idcode,
        );
        $this->assign('field', $assign);

        $iframe = input('param.iframe/d');
        if ($iframe == 1) {
            $viewfile = 'code_m';
        } else {
            $viewfile = 'code';
        }

        return $this->fetch($viewfile);
    }

    /**
     * 源代码编辑处理
     */
    private function codeHandle($id, $page, $post = array())
    {
        $type = 'code';
        $lang = $post['lang'];
        $content = !empty($post['content']) ? $post['content'] : '';
        $content = trim($content);
        $arr = array(
            "{$lang}_{$type}_{$id}" => json_encode(array(
                'id'    => $id,
                'type'  => $type,
                'page'  => $page,
                'lang'  => $lang,
                'idcode' => $this->idcode,
                'info'   => array(
                    'value'    => $content,
                ),
            )),
        );
        $filename = $this->uipath."{$page}.inc.php";
        if (ui_write_bidden_inc($arr, $filename, true)) {
            $this->success('操作成功');
            exit;
        } else {
            $this->error('写入失败');
            exit;
        }
    }

    /**
     * 清除页面数据
     */
    public function clear_data()
    {
        $type = input('param.type/s');
        if (IS_POST && !empty($type) && !empty($this->v)) {
            $where = [
                'idcode'    => $this->idcode,
                'lang'      => $this->home_lang,
            ];
            if ($type != 'all') {
                $where['type'] = $type;
            }
            $result = Db::name('ui_config')->where($where)->select();
            $r = Db::name('ui_config')->where($where)->delete();
            if ($r !== false) {
                \think\Cache::clear('ui_config');
                foreach ($result as $key => $val) {
                    $filename = RUNTIME_PATH.'ui/'.TPL_THEME.$this->v."/{$val['page']}.inc.php";
                    @unlink($filename);
                }
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    public function mobileTpl()
    {
        $assign_data = [];
        $gourl = input('param.gourl/s');
        $assign_data['murl'] = base64_decode($gourl).'&iframe=1';

        $webConfig = tpCache('web');
        $web_adminbasefile = !empty($webConfig['web_adminbasefile']) ? $webConfig['web_adminbasefile'] : $this->root_dir.'/login.php'; // 后台入口文件路径
        $assign_data['web_adminbasefile'] = $web_adminbasefile;

        $tid = input('param.tid/d');
        $assign_data['tid'] = $tid;

        $aid = input('param.aid/d');
        $assign_data['aid'] = $aid;
        $assign_data['lang'] = $this->home_lang;

        $iframe = input('param.iframe/d');
        $assign_data['iframe'] = $iframe;

        $this->assign($assign_data);

        return $this->fetch();
    }
}