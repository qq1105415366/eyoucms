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

use think\Db;
use think\Page;

class Uploadimgnew extends Base
{
    public $image_type = '';
    private $imageExt = '';
    private $php_sessid = '';
    private $image_accept = '';

    /**
     * 析构函数
     */
    function __construct() 
    {
        parent::__construct();
        $this->imageExt = config('global.image_ext');
        $this->image_type = tpCache('basic.image_type');
        $this->image_type = !empty($this->image_type) ? str_replace('|', ',', $this->image_type) : $this->imageExt;
        $this->image_accept = image_accept_arr($this->image_type);
        $this->php_sessid = !empty($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '';
    }

    //获取上传目录列表
    public function get_type()
    {
        if (IS_AJAX){
            $type_list = Db::name('uploads_type')->field('id,upload_type')->select();
            if (empty($type_list)){
                $this->error('请先创建目录');
            }else{
                $this->success('success','',$type_list);
            }
        }
    }

    public function update_type_id()
    {
        $id_arr = input('img_id/a');
        $id_arr = eyIntval($id_arr);
        if (IS_AJAX && !empty($id_arr)) {
            $type_id = input('param.type_id/d', 0);
            $r = Db::name('uploads')->where('img_id','in',$id_arr)->update(['type_id'=>$type_id,'update_time'=>getTime()]);
            if ($r !== false) {
                $this->success('移动成功');
            }
        }
        $this->error('移动失败');
    }

    /**
     * 通用的上传图片
     */
    public function upload()
    {
        $assign_data = [];
        $type_id_selected = (int)tpSetting('system.system_uploads_type_id_selected', [], 'cn');
        $type_id = input('param.type_id/d', $type_id_selected);
        $assign_data['type_id'] = $type_id;
        // 基础配置 - 附件配置
        $basicConfig = tpCache('basic');
        $assign_data['basicConfig'] = $basicConfig;

        $func = input('param.func/s', 'undefined');
        $path = input('path','allimg');
        $num  = input('num/d', 1);
        $is_water  = input('is_water/d', 1);
        $open_source  = input('open_source/s', 'bendi');
        $default_size = intval($basicConfig['file_size'] * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $info = array(
            'num'      => $num,
            'size'     => $size,
            'input'    => input('input'),
            'func'     => $func,
            'path'     => $path,
            'is_water' => $is_water,
            'open_source' => preg_replace('/([^\w\-]+)/i', '', $open_source),
        );
        $assign_data['info'] = $info;
        $assign_data['default_upload_list_url'] = url('Uploadimgnew/get_upload_list', ['type_id'=>0, 'info'=>mchStrCode(json_encode($info), 'ENCODE')]);
        $assign_data['current_upload_list_url'] = url('Uploadimgnew/get_upload_list', ['type_id'=>$type_id, 'info'=>mchStrCode(json_encode($info), 'ENCODE')]);

        // 侧边栏我的分组
        $uploads_total_list = Db::name('uploads')->field('type_id, count(img_id) as total')->where(['is_del'=>0])->group('type_id')->getAllWithIndex('type_id');
        $uploads_type_list = Db::name('uploads_type')->order('id asc')->select();
        foreach ($uploads_type_list as $key => $val) {
            $val['total'] = !empty($uploads_total_list[$val['id']]['total']) ? $uploads_total_list[$val['id']]['total'] : 0;
            $val['url'] = url('Uploadimgnew/get_upload_list', ['type_id'=>$val['id'], 'info'=>mchStrCode(json_encode($info), 'ENCODE')]);
            $uploads_type_list[$key] = $val;
        }
        $assign_data['uploads_type_list'] = $uploads_type_list;
        $assign_data['uploads_total_list'] = $uploads_total_list;

        // 是否已经同步过
        $assign_data['admin_logic_1639031991'] = tpSetting('syn.admin_logic_1639031991', [], 'cn');

        $this->assign($assign_data);
        return $this->fetch('uploadimgnew/upload');
    }

    /**
     * 完整的上传模板展示
     */
    public function upload_full()
    {
        $func = input('func');
        $path = input('path','allimg');
        $path = preg_replace('/([^\w\-\/\\\]*)/i', '', $path);
        $num = input('num/d', '1');
        $default_size = intval(tpCache('basic.file_size') * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $width = input('width/d', 0);
        $height = input('height/d', 0);
        $info = array(
            'num'=> $num,
            'upload' =>url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
            'size' => $size,
            'type' => $this->image_type,
            'input' => preg_replace('/([^\w\-]*)/i', '', input('input')),
            'func' => empty($func) ? 'undefined' : preg_replace('/([^\w\-]*)/i', '', $func),
            'path'     => $path,
            'width'     => $width,
            'height'     => $height,
        );
        $assign_data['info'] = $info;

        // 图片列表
        $imglist = [];
        if (in_array($path, ['adminlogo','loginlogo','loginbgimg','ico'])) {
            $redata = $this->fileList('Images', $path);
            if ('SUCCESS' == $redata['state']) {
                $imglist = $redata['list'];
            }
            // 已选中的值
            $select_img = input('param.img_path/s');
            if (empty($select_img)) {
                if ('adminlogo' == $path) {
                    $select_img = $this->globalConfig['web_adminlogo'];
                } else if ('loginlogo' == $path) {
                    $select_img = $this->globalConfig['web_loginlogo'];
                } else if ('loginbgimg' == $path) {
                    $select_img = $this->globalConfig['web_loginbgimg'];
                } else if ('ico' == $path) {
                    $select_img = $this->globalConfig['web_adminico'];
                }
            }
            $assign_data['select_img'] = $select_img;
            cookie('uploadimgnew_select_img', $select_img);
        }
        $assign_data['imglist'] = $imglist;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 获取左侧树形的目录结构
     * @return [type] [description]
     */
    public function ajax_get_treedir()
    {
        // 侧边栏图片目录
        $dirArr  = $this->getDirImg('uploads');
        $dirArr2 = [];
        foreach ($dirArr as $key => $val) {
            $dirArr2[$val['id']] = $val['dirpath'];
        }
        foreach ($dirArr as $key => $val) {
            $dirfileArr = glob("{$val['dirpath']}/*");
            if (empty($dirfileArr)) {
                empty($dirfileArr) && @rmdir($val['dirpath']);
                $dirArr[$key] = [];
                continue;
            }
            /*图库显示数量*/
            $countFile = 0;
            $image_type_tmp = str_replace(',', '|', $this->image_type);
            foreach ($dirfileArr as $_k => $_v) {
                if (preg_match('/\.('.$image_type_tmp.')$/i', $_v)) {
                    $countFile++;
                }
            }
            /*end*/
            $dirname = preg_replace('/([^\/]+)$/i', '', $val['dirpath']);
            $arr_key = array_search(trim($dirname, '/'), $dirArr2);
            if (!empty($arr_key)) {
                $dirArr[$key]['pId'] = $arr_key;
            } else {
                $dirArr[$key]['pId'] = 0;
            }
            $dirArr[$key]['name'] = preg_replace('/^(.*)\/([^\/]+)$/i', '${2}', $val['dirpath']);
            !empty($countFile) && $dirArr[$key]['name'] .= "({$countFile})"; // 图库显示数量
        }
        $zNodes = json_encode($dirArr,true);
        $this->success('请求成功', null, ['zNodes'=>$zNodes]);
    }

    /**
     * 右侧上传图片记录的列表显示
     */
    public function get_upload_list()
    {
        $assign_data = [];

        $type_id = input('param.type_id/d', 0);
        tpSetting('system', ['system_uploads_type_id_selected'=>$type_id], 'cn');
        $assign_data['type_id'] = $type_id;

        $pageNum = input('param.p/d', 1);
        $assign_data['pageNum'] = $pageNum;

        $info = input('param.info/s');
        $info = json_decode(mchStrCode($info, 'DECODE'), true);
        $info['num'] = intval($info['num']);
        $info['size'] = preg_replace('/([^\d]*)/i', '', $info['size']);
        $info['input'] = preg_replace('/([^\w\-]*)/i', '', $info['input']);
        $info['func'] = preg_replace('/([^\w\-]*)/i', '', $info['func']);
        $info['path'] = preg_replace('/([^\w\-\/\\\]*)/i', '', $info['path']);
        $info['is_water'] = intval($info['is_water']);
        $info['open_source'] = preg_replace('/([^\w\-]+)/i', '', $info['open_source']);
        $info['upload'] = url('Ueditor/imageUp',array('savepath'=>$info['path'],'type_id'=>$type_id,'pictitle'=>'banner','dir'=>'images','is_water'=>$info['is_water']));
        $info['image_accept'] = $this->image_accept;
        $assign_data['info'] = $info;

        $Where = [];
        // 时间检索条件查询
        $post_eytime = input('eytime/s');
        $EyTime = !empty($post_eytime) ? $post_eytime : '';
        $assign_data['eytime'] = $EyTime;
        if (!empty($EyTime)) {
            $EyTime = explode(' - ', $EyTime);
            // 开始日期
            $Begin = 0;
            if (!empty($EyTime[0])) {
                if (stristr($EyTime[0], ':')) { // 包含了时分秒
                    $Begin = strtotime($EyTime[0]);
                } else {
                    $Begin = strtotime($EyTime[0]." 00:00:00");
                }
            }
            // 结束日期
            $End = 0;
            if (!empty($EyTime[1])) {
                if (stristr($EyTime[1], ':')) { // 包含了时分秒
                    $End = strtotime($EyTime[1]);
                } else {
                    $End = strtotime($EyTime[1]." 23:59:59");
                }
            }
            if ($Begin > 0 && $End > 0) {
                $Where['add_time'] = array('between', "$Begin, $End");
            } else if ($Begin > 0) {
                $Where['add_time'] = array('egt', $Begin);
            } else if ($End > 0) {
                $Where['add_time'] = array('elt', $End);
            }
        }
        $Where['type_id'] = $type_id;
        $Where['is_del'] = 0;
        $countimg = Db::name('uploads')->where($Where)->count('img_id');
        $pageObj = new Page($countimg, 10);

        // 列表显示
        $imglist = Db::name('uploads')
            ->where($Where)
            ->order('img_id desc')
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->select();
        foreach ($imglist as $key => $val) {
            $val['image_url'] = handle_subdir_pic($val['image_url']);
            empty($val['title']) && $val['title'] = preg_replace('/^(.*)\/([^\/]+)$/i', '${2}', $val['image_url']);
            $imglist[$key] = $val;
        }
        $assign_data['imglist'] = $imglist;
        $assign_data['pageStr'] = $pageObj->show();

        /*----------------------------第三方存储插件 start------------------------*/
        $storageTitle = '本地服务器';
        $weappList = Db::name('weapp')->where(['status' => 1])
            ->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
            ->getAllWithIndex('code');
        if (!empty($weappList['Qiniuyun']['data'])) {
            $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
            if (!empty($qnyData['bucket'])) {
                $storageTitle = '七牛云';
            }
        } else if (!empty($weappList['AliyunOss']['data'])) {
            $ossData = json_decode($weappList['AliyunOss']['data'], true);
            if (!empty($ossData['bucket'])) {
                $storageTitle = '阿里云OSS';
            }
        } else if (!empty($weappList['Cos']['data'])) {
            $cosData = json_decode($weappList['Cos']['data'], true);
            if (!empty($cosData['secretName'])) {
                $storageTitle = '腾讯云COS';
            }
        } else if (!empty($weappList['AwsOss']['data'])) {
            $awsData = json_decode($weappList['AwsOss']['data'], true);
            if (!empty($awsData['secretName'])) {
                $storageTitle = '亚马逊S3';
            }
        }
        $assign_data['storageTitle'] = $storageTitle;
        /*----------------------------第三方存储插件 start------------------------*/

        // 分组图片总数
        $assign_data['countimg'] = Db::name('uploads')->where(['type_id' => $type_id, 'is_del' => 0])->count('img_id');

        // 标记是否已经同步导入数据
        $assign_data['admin_logic_1639031991'] = tpSetting('syn.admin_logic_1639031991', [], 'cn');

        $this->assign($assign_data);

        return $this->fetch('uploadimgnew/get_upload_list');
    }

    /**
     * 右侧图片目录的图片列表显示
     */
    public function get_dir_imglist($images_path = 'uploads')
    {
        $assign_data = [];

        if ('uploads' != $images_path && !preg_match('#^(uploads)/(.*)$#i', $images_path)) {
            $this->error('禁止访问');
        }

        $num  = input('num/d', 1);
        $info = array(
            'num'  => $num,
        );
        $assign_data['info'] = $info;

        // 常用图片
        $common_pic = [];
        $arr1 = explode('/', $images_path);
        if (1 >= count($arr1)) { // 只有一级目录才显示常用图片
            $where = [
                'lang' => $this->admin_lang,
            ];
            $common_pic = Db::name('common_pic')->where($where)->order('id desc')->limit(10)->select();
            foreach ($common_pic as $key => $val) {
                $imageInfo = [];
                if (!is_http_url($val['pic_path'])) {
                    $pic_path = handle_subdir_pic($val['pic_path'], 'img', false, true);
                    if (!file_exists('.'.$pic_path)) {
                        unset($common_pic[$key]);
                        continue;
                    }
                    $imageInfo = @getimagesize('.'.$pic_path);
                    $val['pic_path'] = ROOT_DIR.$pic_path;
                }
                $val['title'] = preg_replace('/^(.*)\/([^\/]+)$/i', '${2}', $val['pic_path']);
                $val['width'] = !empty($imageInfo[0]) ? intval($imageInfo[0]) : 0;
                $val['height'] = !empty($imageInfo[1]) ? intval($imageInfo[1]) : 0;
                $common_pic[$key] = $val;
            }
        }
        $assign_data['common_pic'] = $common_pic;

        // 图片列表
        $list = [];
        $images_data = glob($images_path.'/*');
        if (!empty($images_data)) {
            // 图片类型数组
            $image_ext = explode(',', $this->imageExt);
            // 处理图片
            foreach ($images_data as $key => $file) {
                $fileArr = explode('.', $file);    
                $ext = end($fileArr);
                $ext = strtolower($ext);
                if (in_array($ext, $image_ext)) {
                    $info = [];
                    $imageInfo = @getimagesize('./'.$file);
                    $info['width'] = !empty($imageInfo[0]) ? intval($imageInfo[0]) : 0;
                    $info['height'] = !empty($imageInfo[1]) ? intval($imageInfo[1]) : 0;
                    $info['pic_path'] = ROOT_DIR.'/'.$file;
                    $info['add_time'] = @filemtime($file);
                    empty($info['add_time']) && $info['add_time'] = getTime();
                    $info['title'] = preg_replace('/^(.*)\/([^\/]+)$/i', '${2}', $file);
                    $list[] = $info;
                }
            }
            if (!empty($list)) {
                // 图片选择的时间从大到小排序
                $list_time = get_arr_column($list, 'add_time');
                array_multisort($list_time, SORT_DESC, $list);
            }
        }

        // 每页显示的图片
        $count = count($list); // 总条数
        $pagesize = 10; // 每页多少条
        $page = input('param.p/d', 1);
        $start = ($page - 1) * $pagesize; // 偏移量，当前页-1乘以每页显示条数
        $list = array_slice($list, $start, $pagesize);
        $assign_data['list'] = $list;

        // 分页码
        $pageObj = new Page($count, $pagesize);
        $assign_data['pageStr'] = $pageObj->show();

        $this->assign($assign_data);

        return $this->fetch('uploadimgnew/get_dir_imglist');
    }
    
    /*
     * 删除上传的图片
     */
    public function delupload()
    {
        echo 1;
        exit;
    }
    
    public function fileList($type = 'Images', $path = 'allimg'){
        /* 判断类型 */
        $type = input('type', $type);
        switch ($type){
            /* 列出图片 */
            case 'Images' : $allowFiles = str_replace(',', '|', $this->image_type);break;
        
            case 'Flash' : $allowFiles = 'flash|swf';break;
        
            /* 列出文件 */
            default : 
            {
                $file_type = tpCache('basic.file_type');
                $media_type = tpCache('basic.media_type');
                $allowFiles = $file_type.'|'.$media_type;
            }
        }

        $listSize = 102400000;
        
        $key = empty($_GET['key']) ? '' : $_GET['key'];
        
        /* 获取参数 */
        $size = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : $listSize;
        $start = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 0;
        $end = $start + $size;
        
        $path = input('path', $path);
        if (1 == preg_match('#\.#', $path)) {
            $res = array(
                "state" => "路径不符合规范",
                "list" => array(),
                "start" => $start,
                "total" => 0
            );
            if (IS_AJAX) {
                echo json_encode($res);
                exit;
            } else {
                return $res;
            }
        }
        if ('adminlogo' == $path) {
            $path = 'public/static/admin/logo';
        } else if ('loginlogo' == $path) {
            $path = 'public/static/admin/login';
        } else if ('loginbgimg' == $path) {
            $path = 'public/static/admin/loginbg';
        } else if ('ico' == $path) {
            $path = 'public/static/admin/ico';
        } else {
            $path = UPLOAD_PATH.$path;
        }

        /* 获取文件列表 */
        $files = $this->getfiles($path, $allowFiles, $key);
        if (empty($files)) {
            $res = array(
                "state" => "没有相关文件",
                "list" => array(),
                "start" => $start,
                "total" => count($files)
            );
            if (IS_AJAX) {
                echo json_encode($res);
                exit;
            } else {
                return $res;
            }
        }
        
        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
            $list[] = $files[$i];
        }
        
        /* 返回数据 */
        $res = array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files)
        );
        if (IS_AJAX) {
            echo json_encode($res);
            exit;
        } else {
            return $res;
        }
    }

    /**
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param array $files
     * @return array
     */
    private function getfiles($path, $allowFiles, $key, &$files = array()){
        if (!is_dir($path)) return null;
        if(substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $key, $files);
                } else {
                    if (preg_match("/\.(".$allowFiles.")$/i", $file) && preg_match("/.*". $key .".*/i", $file)) {
                        //获取图像信息
                        $info = @getimagesize(ROOT_PATH.'/'.$path2);
                        $files[] = array(
                            'id'=> mchStrCode($path2, 'ENCODE'),
                            'url'=> ROOT_DIR.'/'.$path2, // 支持子目录
                            'name'=> $file,
                            'mtime'=> filemtime($path2),
                            'width'=> empty($info[0]) ? 0 : $info[0],
                            'height'=> empty($info[1]) ? 0 : $info[1],
                            'mime'=> empty($info['mime']) ? '' : $info['mime'],
                        );
                    }
                }
            }
        }
        return $files;
    }

    /**
     * 检测指定目录是否存在图片
     *
     * @param string $directory 目录路径
     * @param string $dir_name 显示的目录前缀路径
     * @param array $arr_file 是否删除空目录
     * @return boolean
     */
    private function isExistPic($directory, $dir_name='', &$arr_file = [])
    {
        if (!file_exists($directory) ) {
            return false;
        }

        if (!empty($arr_file)) {
            return true;
        }

        // 图片类型数组
        $image_ext = explode(',', $this->imageExt);
        $mydir = dir($directory);
        while($file = $mydir->read())
        {
            if((is_dir("$directory/$file")) AND ($file != ".") AND ($file != ".."))
            {
                if ($dir_name) {
                    return $this->isExistPic("$directory/$file", "$dir_name/$file", $arr_file);
                } else {
                    return $this->isExistPic("$directory/$file", "$file", $arr_file);
                }
                
            }
            else if(($file != ".") AND ($file != ".."))
            {
                $fileArr = explode('.', $file);    
                $ext = end($fileArr);
                $ext = strtolower($ext);
                if (in_array($ext, $image_ext)) {
                    if ($dir_name) {
                        $arr_file[] = "$dir_name/$file";
                    } else {
                        $arr_file[] = "$file";
                    }
                    return true;
                }

            }
        }
        $mydir->close();

        return $arr_file;
    }

    /**
     * 记录常用图片
     */
    public function update_pic()
    {
        if(IS_AJAX_POST){
            $param = input('param.');
            if (!empty($param['images_array'])) {
                $images_array = $param['images_array'];
                $commonPic_db = Db::name('common_pic');
                $data  = [];
                foreach ($images_array as $key => $value) {
                    // 添加数组
                    $data[$key] = [
                        'pic_path'    => $value,
                        'lang'        => $this->admin_lang,
                        'add_time'    => getTime(),
                        'update_time' => getTime(),
                    ];
                }

                // 批量删除选中的图片
                $commonPic_db->where('pic_path','IN',$images_array)->delete();

                // 批量添加图片
                !empty($data) && $commonPic_db->insertAll($data);

                // 查询最后一条数据
                $row = $commonPic_db->order('id desc')->limit('20,1')->field('id')->select();
                if (!empty($row)) {
                    $id = $row[0]['id'];
                    // 删除ID往后的数据
                    $where_ = array(
                        'id'   => array('<',$id),
                        'lang' => $this->admin_lang,
                    );
                    $commonPic_db->where($where_)->delete();
                }
            }
        }
    }

    /**
     * 提取上传图片目录下的所有图片
     *
     * @param string $directory 目录路径
     * @param string $dir_name 显示的目录前缀路径
     * @param array $arr_file 是否删除空目录
     * @param num $num 数量
     */
    private function getDirImg($directory, &$arr_file = array(), &$num = 0) {
        $mydir = glob($directory.'/*', GLOB_ONLYDIR);
        $param = input('param.');
        if (0 <= $num) {
            $dirpathArr = explode('/', $directory);
            $level = count($dirpathArr);
            $open = (1 >= $level) ? true : false;
            $fileList = glob("$directory/*");
            $total = count($fileList); // 目录是否存在任意文件，否则删除该目录
            if (!empty($total)) {
                $isExistPic = $this->isExistPic($directory);
                if (!empty($isExistPic)) {
                    $arr_file[] = [
                        'id'        => $num,
                        'url'       => url('Uploadimgnew/get_dir_imglist',['num'=>$param['num'],'lang'=>$this->admin_lang,'images_path'=>$directory]),
                        'target'    => 'content_body',
                        'isParent'  => true,
                        'open'      => $open,
                        'dirpath'   => $directory,
                        'level'     => $level,
                        'total'     => $total,
                    ];
                }
            } else {
                @rmdir("$directory");
            }
        }
        if (!empty($mydir)) {
            foreach ($mydir as $key => $dir) {
                if (preg_match('/uploads\/(soft_tmp|tmp|media|soft)\//i', "$dir/")) {
                    continue;
                }
                $num++;
                $dirname = str_replace('\\', '/', $dir);
                $dirArr  = explode('/', $dirname);
                $dir     = end($dirArr);
                $mydir2  = glob("$directory/$dir/*", GLOB_ONLYDIR);
                if(!empty($mydir2) AND ($dir != ".") AND ($dir != ".."))
                {
                    $this->getDirImg("$directory/$dir", $arr_file, $num);
                }
                else if(($dir != ".") AND ($dir != ".."))
                {
                    $dirpathArr = explode('/', "$directory/$dir");
                    $level = count($dirpathArr);
                    $fileList = glob("$directory/$dir/*"); // 目录是否存在任意文件，否则删除该目录
                    $total = count($fileList);
                    if (!empty($total)) {
                         // 目录是否存在图片文件，否则删除该目录
                        $isExistPic = $this->isExistPic("$directory/$dir");
                        if (!empty($isExistPic)) {
                            $arr_file[] = [
                                'id'        => $num,
                                'url'       => url('Uploadimgnew/get_dir_imglist',['num'=>$param['num'],'lang'=>$this->admin_lang,'images_path'=>"$directory/$dir"]),
                                'target'    => 'content_body',
                                'isParent'  => false,
                                'open'      => false,
                                'dirpath'   => "$directory/$dir",
                                'level'     => $level,
                                'icon'      => 'public/plugins/ztree/css/zTreeStyle/img/dir_close.png',
                                'iconOpen'  => 'public/plugins/ztree/css/zTreeStyle/img/dir_open.png',
                                'iconClose' => 'public/plugins/ztree/css/zTreeStyle/img/dir_close.png',
                                'total'     => $total,
                            ];
                        }
                    } else {
                        @rmdir("$directory/$dir");
                    }
                }
            }
        }
        return $arr_file;
    }

    /**
     *  远程图片本地化
     * @access    public
     * @return    string
     */
    public function ajax_remote_to_imglocal()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $type_id = !empty($post['type_id']) ? $post['type_id'] : 0;
            $imgremoteurl = !empty($post['imgremoteurl']) ? trim($post['imgremoteurl']) : '';
            if (empty($imgremoteurl)) {
                $this->error("请输入图片地址！");
            } else if (!is_http_url($imgremoteurl)) {
                $this->error("请输入有效的图片地址！");
            }
            $reData = saveRemote($imgremoteurl);
            $reData = json_decode($reData, true);
            if ('SUCCESS' != $reData['state']) {
                $msg = !empty($reData['state']) ? $reData['state'] : '源网站图片可能已开启防盗链功能！';
                $this->error($msg);
            } else {
                if (is_http_url($reData['url'])) {
                    $image_url = $reData['url'];
                } else {
                    $image_url = handle_subdir_pic($reData['url']);
                }
            }

            // 添加图片进数据库
            $addData = [
                'aid'         => 0,
                'type_id'     => $type_id,
                'image_url'   => $image_url,
                'title'       => !empty($reData['title']) ? $reData['title'] : '',
                'intro'       => '',
                'width'       => $reData['width'],
                'height'      => $reData['height'],
                'filesize'    => $reData['size'],
                'mime'        => $reData['mime'],
                'users_id'    => (int)session('admin_info.syn_users_id'),
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            Db::name('uploads')->add($addData);
            $this->success('提取成功', null, $addData);
        }
        $this->error('提取失败');
    }

    /*------------------------------------新版上传图片带分组 start-----------------------------------*/
    //添加栏目
    public function Addtype()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $upload_type = trim($post['upload_type']);
            if (empty($upload_type)) $this->error("请输入分组名称");

            $data = [
                'upload_type' => $upload_type,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            $rs = Db::name('UploadsType')->add($data);
            if (!empty($rs)) {
                $this->success("分组添加成功");
            }
        }
        $this->error("分组添加失败");
    }

    // 编辑栏目
    public function EditType()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            $type_id = $post['type_id'];
            if (empty($type_id)) $this->error("请选择分组");

            $upload_type = trim($post['upload_type']);
            if (empty($upload_type)) $this->error("请输入分组名称");

            $data = [
                'upload_type' => $upload_type,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            $rs = Db::name('UploadsType')->where('id', $type_id)->update($data);
            if (!empty($rs)) {
                $this->success("分组编辑成功");
            }
        }
        $this->error("分组编辑失败");
    }

    // 删除栏目
    public function DelType()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            $type_id = $post['type_id'];
            if (empty($type_id)) $this->error("请选择分组");

            $rs = Db::name('UploadsType')->where('id', $type_id)->delete();
            if (!empty($rs)) {
                Db::name('uploads')->where('type_id', $type_id)->update(['type_id' => 0, 'update_time'=>getTime()]);
                $this->success("删除分组成功");
            }
        }
        $this->error("分组添加失败");
    }
    
    // 批量删除
    public function del_uploadsimg() 
    {
        $del_type = 'img_id';
        $img_ids = input('img_id/a');
        foreach ($img_ids as $key => $val) {
            if (!is_numeric($val)) {
                $del_type = 'image_url';
                $val = mchStrCode($val, 'DECODE');
                if (empty($val) || !preg_match("/^([\w\-\/\.]+)\.(".str_replace(',', '|', $this->image_type).")$/i", $val)) {
                    unset($img_ids[$key]);
                }
            } else {
                $val = intval($val);
            }
            $img_ids[$key] = $val;
        }
        if (IS_POST && !empty($img_ids)) {
            if ('image_url' == $del_type) {
                foreach ($img_ids as $key => $val) {
                    $rs = @unlink(ROOT_PATH.$val);
                }
            } else {
                $rs = Db::name('uploads')->where("img_id", 'IN', $img_ids)->delete();
                // $rs = Db::name('uploads')->where("img_id", 'IN', $img_ids)->update(['is_del' => 1, 'update_time'=>getTime()]);
            }
            if ($rs !== false) {
                $this->success("删除成功");
            }
        }
        $this->error("删除失败");
    }
    /*------------------------------------新版上传图片带分组 end-----------------------------------*/


    /*---------------------------------同步图片目录的图片到记录表 start 老许-------------------------*/
    //更新旧数据弹出框
    public function site(){
        $admin_logic_1639031991 = tpSetting('syn.admin_logic_1639031991', [], 'cn');
        if (!empty($admin_logic_1639031991)){
            $this->error("已经同步过，不能重复同步");
        }

        return $this->fetch();
    }

    //分批次更新旧数据入口
    public function build_site(){
        $admin_logic_1639031991 = tpSetting('syn.admin_logic_1639031991', [], 'cn');
        if (!empty($admin_logic_1639031991)){
            $this->error("已经同步过，不能重复同步");
        }
        $achievepage = input("param.achieve/d", 0); // 已完成文档数
        if (empty($findex) && empty($achievepage)){
            $this->clearCache();
        }

        list($msg,$data) = $this->handel_build_site($achievepage,50);

        $this->success($msg, null, $data);
    }

    //处理更新
    private function handel_build_site($achievepage = 0,  $limit = 50){
        $msg                  = "";
        list($files,$pagetotal)  = $this->get_files_data();
        $data['allpagetotal'] = $pagetotal;   //全部数据总数
        if ($pagetotal > $achievepage){
            $uploadsData = [];
            while ($limit && isset($files[$achievepage])) {
                $fileInfo = $files[$achievepage];
                $url = !empty($fileInfo['url']) ? $fileInfo['url'] : '';
                //获取文件其他信息（图片宽度、图片高度、文件大小、图片类型、图片上传时间）
                $url = handle_subdir_pic($url, 'img', false, true);
                $imageInfo = @getimagesize('.'.$url);
                $mtime = $this->get_mtime($fileInfo['mtime'],$fileInfo['url']);
                $uploadsData[] = [
                    'image_url' => $fileInfo['url'],
                    'filesize' => filesize('.'.$url),
                    'width' => !empty($imageInfo[0]) ? $imageInfo[0] : 0,
                    'height' => !empty($imageInfo[1]) ? $imageInfo[1] : 0,
                    'mime' => !empty($imageInfo['mime']) ? $imageInfo['mime'] : 0,
                    'add_time' => $mtime,
                    'update_time' => $mtime
                ];
                $limit--;
                $achievepage++;
            }
            if (!empty($uploadsData)){
                Db::name("uploads")->insertAll($uploadsData);
            }
        }
        $data['achievepage']  = $achievepage; //当前已完成总数（当前下标）
        if ($data['allpagetotal'] == $data['achievepage']){   //生成全部页面，清楚缓存，增加标识
            $this->clearCache();
            $admin_logic_1639031991 = tpSetting('syn.admin_logic_1639031991', [], 'cn');
            if (empty($admin_logic_1639031991)) {
                tpSetting('syn', ['admin_logic_1639031991'=>1], 'cn');
            }
        }

        return [$msg, $data];
    }

    /*
     * 获取绝对的图片添加时间
     * $mtime   文件添加时间
     * $url     文件路径
     */
    private function get_mtime($mtime,$url){
        if (!empty($mtime)){
            return $mtime;
        }
        if (!empty($url)){
            $dirArr = explode('/',$url);
            $length = count($dirArr);
            if ($length > 2){
                $dir = $dirArr[$length - 2];
                $mtime = strtotime($dir);
                if (!empty($mtime)){
                    return $mtime;
                }
            }
        }

        return time();
    }

    //获取缓存
    private function get_files_data(){
        $get_files_data_serialize = cache("get_files_data_serialize".$this->php_sessid);
        if (empty($get_files_data_serialize)){
            $allowFiles = str_replace(',', '|', $this->image_type);
            $files = $this->getfiles(UPLOAD_PATH, $allowFiles, '');
            $pagetotal = count($files);
            cache("get_files_data_serialize".$this->php_sessid, serialize($files));
            cache("get_files_total_serialize".$this->php_sessid, $pagetotal);

        }else{
            $files = unserialize($get_files_data_serialize);
            $pagetotal        = cache("get_files_total_serialize".$this->php_sessid);
        }
        return [$files,$pagetotal];

    }

    //清楚缓存
    private function clearCache(){
        cache("get_fiels_data_serialize".$this->php_sessid, null);
        cache("count_files_data_serialize".$this->php_sessid, null);
    }
    /*---------------------------------同步图片目录的图片到记录表 end 老许-------------------------*/
}
