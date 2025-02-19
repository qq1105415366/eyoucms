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

class Uploadify extends Base
{
    public $image_type = '';
    private $imageExt = '';
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
    }

    /**
     * 通用的上传图片
     */
    public function upload()
    {
        // 基础配置 - 附件配置
        $basicConfig = tpCache('basic');
        $func = input('param.func/s', 'undefined');
        $path = input('path','allimg');
        $num  = input('num/d', 1);
        $is_water  = input('is_water/d', 1);
        $default_size = intval($basicConfig['file_size'] * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $info = array(
            'num'      => $num,
            'title'    => '',          
            'upload'   => url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images','is_water'=>$is_water)),
            'fileList' => url('Uploadify/fileList',array('path'=>$path)),
            'size'     => $size,
            'type'     => $this->image_type,
            'input'    => input('input'),
            'func'     => empty($func) ? 'undefined' : $func,
            'path'     => $path,
        );
        $this->assign('info',$info);
        return $this->fetch();
    }

    /**
     * 图库在线管理 - 左侧树形目录结构
     */
    public function picture_folder()
    {   
        $func = input('func');
        $path = input('path','allimg');
        $num  = input('num/d', '1');
        $default_size = intval(tpCache('basic.file_size') * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $info = array(
            'num'      => $num,
            'title'    => '',          
            'upload'   => url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
            'fileList' => url('Uploadify/fileList',array('path'=>$path)),
            'size'     => $size,
            'type'     => $this->image_type,
            'input'    => input('input'),
            'func'     => empty($func) ? 'undefined' : $func,
            'path'     => $path,
        );
        $this->assign('info',$info);

        // 侧边栏目录栏目
        $dirArr  = $this->getDir('uploads');
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
            $dirfileArr2 = glob("{$val['dirpath']}/*.*"); // 文件数量
            $countFile = count($dirfileArr2);
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
        $this->assign('zNodes', $zNodes);
        return $this->fetch();
    }

    /**
     * 图库在线管理 - 图片列表显示
     */
    public function get_images_path($images_path = 'uploads')
    {
       if ('uploads' != $images_path && !preg_match('#^(uploads)/(.*)$#i', $images_path)) {
            $this->error('非法访问！');
        }

        $func = input('func/s');
        $num  = input('num/d', '1');
        $info = array(
            'num'  => $num,
            'func' => empty($func) ? 'undefined' : $func,
        );
        $this->assign('info',$info);

        // 常用图片
        $common_pic = [];
        $arr1 = explode('/', $images_path);
        if (1 >= count($arr1)) { // 只有一级目录才显示常用图片
            $where = [
                'lang' => $this->admin_lang,
            ];
            $common_pic = Db::name('common_pic')->where($where)->order('id desc')->limit(6)->field('pic_path')->select();
        }
        $this->assign('common_pic', $common_pic);

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
                    $list[$key]['path'] = ROOT_DIR.'/'.$file;
                    $list[$key]['time'] = @filemtime($file);
                }
            }
        }
        
        // 图片选择的时间从大到小排序
        $list_time = get_arr_column($list,'time');
        array_multisort($list_time,SORT_DESC,$list);
        // 返回数据
        $this->assign('list', $list);

        $this->assign('path_directory', $images_path);

        return $this->fetch();
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
     * 在弹出窗里的上传图片
     */
    public function upload_frame()
    {
        $func = input('func');
        $path = input('path','allimg');
        $num = input('num/d', '1');
        $default_size = intval(tpCache('basic.file_size') * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $info = array(
            'num'=> $num,
            'title' => '',          
            'upload' =>url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
            'fileList'=>url('Uploadify/fileList',array('path'=>$path)),
            'size' => $size,
            'type' => $this->image_type,
            'input' => input('input'),
            'func' => empty($func) ? 'undefined' : $func,
            'path'     => $path,
        );
        $this->assign('info',$info);
        return $this->fetch();
    }

    /**
     * 后台（产品）专用
     */
    public function upload_product()
    {
        $aid = input('aid/d');
        $func = input('func');
        $path = input('path','allimg');
        $num = input('num/d', '1');
        $default_size = intval(tpCache('basic.file_size') * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $field = array(
            'aid' => $aid,
            'num' => $num,
            'title' => '',          
            'upload' => url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
            'fileList'=> url('Uploadify/fileList',array('path'=>$path)),
            'size' => $size,
            'type' => $this->image_type,
            'input' => input('input'),
            'func' => empty($func) ? 'undefined' : $func,
            'path'     => $path,
        );
        $this->assign('field',$field);
        return $this->fetch();
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
            'title' => '',
            'upload' =>url('Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
            'fileList'=>url('Uploadify/fileList',array('path'=>$path)),
            'size' => $size,
            'type' => $this->image_type,
            'input' => preg_replace('/([^\w\-]*)/i', '', input('input')),
            'func' => empty($func) ? 'undefined' : preg_replace('/([^\w\-]*)/i', '', $func),
            'path'     => $path,
            'width'     => $width,
            'height'     => $height,
        );
        $assign_data['info'] = $info;

        $this->assign($assign_data);
        return $this->fetch();
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
     * 提取上传图片目录下的所有图片
     *
     * @param string $directory 目录路径
     * @param string $dir_name 显示的目录前缀路径
     * @param array $arr_file 是否删除空目录
     * @param num $num 数量
     */
    private function getDir($directory, &$arr_file = array(), &$num = 0) {
        $mydir = glob($directory.'/*', GLOB_ONLYDIR);
        $param = input('param.');
        if (0 <= $num) {
            $dirpathArr = explode('/', $directory);
            $level = count($dirpathArr);
            $open = (1 >= $level) ? true : false;
            $fileList = glob($directory.'/*');
            $total = count($fileList); // 目录是否存在任意文件，否则删除该目录
            if (!empty($total)) {
                $isExistPic = $this->isExistPic($directory);
                if (!empty($isExistPic)) {
                    $arr_file[] = [
                        'id'        => $num,
                        'url'       => url('Uploadify/get_images_path',['num'=>$param['num'],'func'=>$param['func'],'lang'=>$param['lang'],'images_path'=>$directory]),
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
                if (stristr("$dir/", 'uploads/soft_tmp/') || stristr("$dir/", 'uploads/tmp/')) {
                    continue;
                }
                $num++;
                $dirname = str_replace('\\', '/', $dir);
                $dirArr  = explode('/', $dirname);
                $dir     = end($dirArr);
                $mydir2  = glob("$directory/$dir/*", GLOB_ONLYDIR);
                if(!empty($mydir2) AND ($dir != ".") AND ($dir != ".."))
                {
                    $this->getDir("$directory/$dir", $arr_file, $num);
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
                                'url'       => url('Uploadify/get_images_path',['num'=>$param['num'],'func'=>$param['func'],'lang'=>$param['lang'],'images_path'=>"$directory/$dir"]),
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
     * 未开启同步本地功能，并删除本地图片
     * @return [type] [description]
     */
    public function del_local()
    {
        if (IS_AJAX_POST) {
            \think\Session::pause(); // 暂停session，防止session阻塞机制
            $post = input('post.');
            $filename = '';
            if (!empty($post['filename'])) {
                if (is_array($post['filename'])) {
                    $filename = $post['filename'][0];
                } else {
                    $filename = $post['filename'];
                }
            }
            $filename = strstr($filename, "/uploads/");
            if (!empty($filename)){
                $weappList = Db::name('weapp')->where([
                    'status'    => 1,
                ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                ->getAllWithIndex('code');

                if (!empty($weappList['Qiniuyun']) && 1 == $weappList['Qiniuyun']['status']) {
                    $weappConfig = json_decode($weappList['Qiniuyun']['config'], true);
                    if (!empty($weappConfig['version']) && 'v1.0.9' <= $weappConfig['version']) {
                        $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
                        if (!empty($qnyData['local_save']) && $qnyData['local_save'] == 1) {
                            $qiniuyunOssModel = new \weapp\Qiniuyun\model\QiniuyunModel;
                            $qiniuyunOssModel->del_local($filename);
                        }
                    }
                } else if (!empty($weappList['AliyunOss']) && 1 == $weappList['AliyunOss']['status']) {
                    $weappConfig = json_decode($weappList['AliyunOss']['config'], true);
                    if (!empty($weappConfig['version']) && 'v1.0.2' <= $weappConfig['version']) {
                        $ossData = json_decode($weappList['AliyunOss']['data'], true);
                        if (!empty($ossData['local_save']) && $ossData['local_save'] == 1) {
                            $aliyunOssModel = new \weapp\AliyunOss\model\AliyunOssModel;
                            $aliyunOssModel->del_local($filename);
                        }
                    }
                } else if (!empty($weappList['Cos']) && 1 == $weappList['Cos']['status']) {
                    $cosData = json_decode($weappList['Cos']['data'], true);
                    if (!empty($cosData['local_save']) && $cosData['local_save'] == 1) {
                        $cosModel = new \weapp\Cos\model\CosModel;
                        $cosModel->del_local($filename);
                    }
                } else if (!empty($weappList['AwsOss']) && 1 == $weappList['AwsOss']['status']) {
                    $awsData = json_decode($weappList['AwsOss']['data'], true);
                    if (!empty($awsData['local_save']) && $awsData['local_save'] == 1) {
                        $awsModel = new \weapp\AwsOss\model\AwsOssModel;
                        $awsModel->del_local($filename);
                    }
                }
            }
        }
    }
}