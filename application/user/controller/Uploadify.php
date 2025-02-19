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

class Uploadify extends Base {

    private $image_type = '';
    private $sub_name = '';
    private $imageExt = '';
    private $savePath = 'allimg/';
    private $upload_path = '';
    private $compressConf = [];
    
    /**
     * 析构函数
     */
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set("Asia/Shanghai");
        $this->savePath = input('savepath/s','allimg').'/';
        error_reporting(E_ERROR | E_WARNING);
        header("Content-Type: text/html; charset=utf-8");
        
        $this->sub_name = date('Ymd/');
        $this->imageExt = config('global.image_ext');
        $this->image_type = tpCache('basic.image_type');
        $this->image_type = !empty($this->image_type) ? str_replace('|', ',', $this->image_type) : $this->imageExt;
        $this->upload_path = UPLOAD_PATH.'user/'.$this->users_id.'/';
        $compress = input('param.compress/s');
        if (!empty($compress)) {
            $this->compressConf = explode('-', $compress);
            $this->compressConf[0] = !empty($this->compressConf[0]) ? intval($this->compressConf[0]) : 0;
            $this->compressConf[1] = !empty($this->compressConf[1]) ? intval($this->compressConf[1]) : 0;
            $this->compressConf[2] = !empty($this->compressConf[2]) ? intval($this->compressConf[2]) : 75;
        }
    }

    public function upload()
    {
        $func = input('func');
        $path = input('path','allimg');
        $num = input('num/d', '1');
        $default_size = intval(tpCache('basic.file_size') * 1024 * 1024); // 单位为b
        $size = input('size/d'); // 单位为kb
        $size = empty($size) ? $default_size : $size*1024;
        $resource = input('param.resource/s');
        $info = array(
            'num'=> $num,
            'title' => '',          
            'upload' =>url('Uploadify/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images','resource'=>$resource)),
            'fileList'=>url('Uploadify/fileList',array('path'=>$path,'resource'=>$resource)),
            'size' => $size,
            'type' => $this->image_type,
            'input' => input('input'),
            'func' => empty($func) ? 'undefined' : $func,
        );
        $this->assign('info',$info);
        return $this->fetch('./application/user/template/uploadify/upload.htm');
    }
    
    /*
     * 删除上传的图片
     */
    public function delupload()
    {
        echo 1;
        exit;
        
        if (IS_AJAX_POST) {
            $action = input('param.action/s','del');                
            $filename= input('param.filename/s');
            $filename= empty($filename) ? input('url') : $filename;
            $filename= str_replace(['(',')',',',' ','../','..','./'],'',$filename);
            $filename= trim($filename,'.');
            $filename = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $filename);
            if(eyPreventShell($filename) && $action=='del' && !empty($filename) && is_file('.'.$filename) && stristr($filename, 'uploads/')){
                $fileArr = explode('/', $filename);
                if ($fileArr[3] != $this->users_id) {
                    return false;
                }
                $filetype = preg_replace('/^(.*)\.(\w+)$/i', '$2', $filename);
                $phpfile = strtolower(strstr($filename,'.php'));  //排除PHP文件
                $size = getimagesize('.'.$filename);
                $fileInfo = explode('/',$size['mime']);
                if($fileInfo[0] != 'image' || $phpfile || !in_array($filetype, explode(',', config('global.image_ext')))){
                    exit;
                }
                if(@unlink('.'.$filename)){
                    echo 1;
                }else{
                    echo 0;
                }  
                exit;
            }

            echo 1;
            exit;
        }
    }

    //列出图片
    private function fileList($allowFiles,$listSize,$get){
        $dirname = './'.$this->upload_path;
        $allowFiles = substr(str_replace(".","|",join("",$allowFiles)),1);
        /* 获取参数 */
        $size = isset($get['size']) ? htmlspecialchars($get['size']) : $listSize;
        $start = isset($get['start']) ? htmlspecialchars($get['start']) : 0;
        $end = $start + $size;
        /* 获取文件列表 */
        $path = $dirname;
        $files = $this->getFiles($path,$allowFiles);
        if(empty($files)){
            return json_encode(array(
                "state" => "没有相关文件",
                "list" => array(),
                "start" => $start,
                "total" => count($files)
            ));
        }
        /* 获取指定范围的列表 */
        $len = count($files);
        for($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
            $list[] = $files[$i];
        }

        /* 返回数据 */
        $result = json_encode(array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files)
        ));

        return $result;
    }

    /*
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param array $files
     * @return array
    */
    private function getFiles($path,$allowFiles,&$files = array()){
        if(!is_dir($path)) return null;
        if(substr($path,strlen($path)-1) != '/') $path .= '/';
        $handle = opendir($path);
            
        while(false !== ($file = readdir($handle))){
            if($file != '.' && $file != '..'){
                $path2 = $path.$file;
                if(is_dir($path2)){
                    $this->getFiles($path2,$allowFiles,$files);
                }else{
                    if(preg_match("/\.(".$allowFiles.")$/i",$file)){
                        $files[] = array(
                            'url' => substr($path2,1),
                            'mtime' => filemtime($path2)
                        );
                    }
                }
            }
        }       
        return $files;
    }
    
    public function index(){
        $CONFIG2 = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents("./public/plugins/Ueditor/php/config.json")), true);
        
        /*---------------百度编辑器配置文件与后台设置关联 start----------------*/
        $CONFIG2['imageMaxSize'] = $CONFIG2['scrawlMaxSize'] = $CONFIG2['catcherMaxSize'] = $CONFIG2['videoMaxSize'] = $CONFIG2['fileMaxSize'] = $CONFIG2['fileMaxSize'] = intval(tpCache('basic.file_size') * 1024 * 1024);

        $media_type = tpCache('basic.media_type');
        $media_type = explode('|', $media_type);
        foreach ($media_type as $key => $val) {
            if (!in_array('.'.$val, $CONFIG2['videoAllowFiles'])) {
                array_push($CONFIG2['videoAllowFiles'], '.'.$val);
            }
            if (!in_array('.'.$val, $CONFIG2['fileAllowFiles'])) {
                array_push($CONFIG2['fileAllowFiles'], '.'.$val);
            }
        }

        $image_type = tpCache('basic.image_type');
        $image_type = explode('|', $image_type);
        foreach ($image_type as $key => $val) {
            if (!in_array('.'.$val, $CONFIG2['fileAllowFiles'])) {
                array_push($CONFIG2['fileAllowFiles'], '.'.$val);
            }
        }

        $file_type = tpCache('basic.file_type');
        $file_type = explode('|', $file_type);
        foreach ($file_type as $key => $val) {
            if (!in_array('.'.$val, $CONFIG2['fileAllowFiles'])) {
                array_push($CONFIG2['fileAllowFiles'], '.'.$val);
            }
        }
        /*---------------百度编辑器配置文件与系统设置关联 end----------------*/
        
        $action = $_GET['action'];
        switch ($action) {
            case 'config':
                $result =  json_encode($CONFIG2);
                break;
            /* 上传图片 */
            case 'uploadimage':
                $fieldName = $CONFIG2['imageFieldName'];
                $result = $this->imageUp();

                /*同步到第三方对象存储空间*/
                // $result = json_decode($result, true);
                // $bucket_data = SynImageObjectBucket($result['url']);
                // $result = array_merge($result, $bucket_data);
                // $result = json_encode($result);
                /*end*/

                break;
            /* 上传涂鸦 */
            case 'uploadscrawl':
                $config = array(
                    "pathFormat" => $CONFIG2['scrawlPathFormat'],
                    "maxSize" => $CONFIG2['scrawlMaxSize'],
                    "allowFiles" => $CONFIG2['scrawlAllowFiles'],
                    "oriName" => "scrawl.png"
                );
                $fieldName = $CONFIG2['scrawlFieldName'];
                $base64 = "base64";
                $result = $this->upBase64($config,$fieldName);
                break;
            /* 上传视频 */
            case 'uploadvideo':
                $fieldName = $CONFIG2['videoFieldName'];
                $result = $this->upFile($fieldName);
                break;
            /* 上传文件 */
            case 'uploadfile':
                $fieldName = $CONFIG2['fileFieldName'];
                $result = $this->upFile($fieldName);
                break;
            /* 列出图片 */
            case 'listimage':
                $allowFiles = $CONFIG2['imageManagerAllowFiles'];
                $listSize = $CONFIG2['imageManagerListSize'];
                $path = $CONFIG2['imageManagerListPath'];
                $get =$_GET;
                $result =$this->fileList($allowFiles,$listSize,$get);
                break;
            /* 列出文件 */
            case 'listfile':
                $allowFiles = $CONFIG2['fileManagerAllowFiles'];
                $listSize = $CONFIG2['fileManagerListSize'];
                $path = $CONFIG2['fileManagerListPath'];
                $get = $_GET;
                $result = $this->fileList($allowFiles,$listSize,$get);
                break;
            /* 抓取远程文件 */
            case 'catchimage':
                $config = array(
                    "pathFormat" => $CONFIG2['catcherPathFormat'],
                    "maxSize" => $CONFIG2['catcherMaxSize'],
                    "allowFiles" => $CONFIG2['catcherAllowFiles'],
                    "oriName" => "remote.png"
                );
                $fieldName = $CONFIG2['catcherFieldName'];
                /* 抓取远程图片 */
                $list = array();
                isset($_POST[$fieldName]) ? $source = $_POST[$fieldName] : $source = $_GET[$fieldName];
                
                /*编辑器七牛云/OSS等同步*/
                $weappList = Db::name('weapp')->where([
                    'status'    => 1,
                ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                ->getAllWithIndex('code');
                /* END */
                
                foreach($source as $imgUrl){
                    $info = json_decode($this->saveRemote($config,$imgUrl),true);

                    /*同步到第三方对象存储空间*/
                    $bucket_data = SynImageObjectBucket($info['url'], $weappList);
                    $info = array_merge($info, $bucket_data);
                    /*end*/

                    array_push($list, array(
                        "state" => $info["state"],
                        "url" => $info["url"],
                        "size" => $info["size"],
                        "title" => htmlspecialchars($info["title"]),
                        "original" => str_replace("&amp;", "&", htmlspecialchars($info["original"])),
                        // "source" => htmlspecialchars($imgUrl)
                        "source" => str_replace("&amp;", "&", htmlspecialchars($imgUrl))
                    ));
                }

                $result = json_encode(array(
                    'state' => !empty($list) ? 'SUCCESS':'ERROR',
                    'list' => $list
                ));
                break;
            default:
                $result = json_encode(array(
                    'state' => '请求地址出错'
                ));
                break;
        }

        /* 输出结果 */
        if(isset($_GET["callback"])){
            if(preg_match("/^[\w_]+$/", $_GET["callback"])){
                echo htmlspecialchars($_GET["callback"]).'('.$result.')';
            }else{
                echo json_encode(array(
                    'state' => 'callback参数不合法'
                ));
            }
        }else{
            echo $result;
        }
    }
    
    //上传文件
    private function upFile($fieldName) {
        if (!IS_POST) {
            return json_encode(['state' =>'非法上传']);
        }

        $file = request()->file($fieldName);
        if (empty($file)) $file = request()->file('upfile');
        if (empty($file)) $file = request()->file('upload');

        if(empty($file)){
            if (!@ini_get('file_uploads')) {
                return json_encode(['state' =>'请检查空间是否开启文件上传功能！']);
            } else {
                return json_encode(['state' =>'ERROR，请上传文件']);
            }
        }
        $error = $file->getError();
        if(!empty($error)){
            return json_encode(['state' =>$error]);
        }

        $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);
        $fileExt = '';
        $image_type = tpCache('basic.image_type');
        !empty($image_type) && $fileExt .= '|'.$image_type;
        $file_type = tpCache('basic.file_type');
        !empty($file_type) && $fileExt .= '|'.$file_type;
        $media_type = tpCache('basic.media_type');
        !empty($media_type) && $fileExt .= '|'.$media_type;
        $fileExt = !empty($fileExt) ? str_replace('||', '|', $fileExt) : config('global.image_ext');
        $fileExt = str_replace('|', ',', trim($fileExt, '|'));
        $result = $this->validate(
            ['file' => $file], 
            ['file'=>'fileSize:'.$max_file_size.'|fileExt:'.$fileExt],
            ['file.fileSize' => '上传文件过大','file.fileExt'=>'上传文件后缀名必须为'.$fileExt]
        );
        // 文件后缀名
        $file_ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);

        /*验证图片一句话木马*/
        if (in_array($file_ext, explode('|', $image_type))) {
            if (false === check_illegal($file->getInfo('tmp_name'), false, $file_ext)) {
                $result = '疑似木马图片！';
            }
        }
        /*--end*/

        if (true !== $result || empty($file)) {
            $state = "ERROR" . $result;
            return json_encode(['state' =>$state]);
        }

        // 移动到框架应用根目录/public/uploads/ 目录下
        $this->savePath = $this->savePath.date('Ymd/');
        // 使用自定义的文件保存规则
        $info = $file->rule(function ($file) {
            return $this->admin_id.'-'.dd2char(date("ymdHis").mt_rand(100,999));
        })->move(UPLOAD_PATH.$this->savePath);

        if (!empty($info)) {
            $return_url = '/'.UPLOAD_PATH.$this->savePath.$info->getSaveName();
            $data = array(
                'state' => 'SUCCESS',
                'url' => ROOT_DIR.$return_url,
                'title' => '',//$info->getSaveName(),
                'original' => $file->getInfo('name'),
                'time' => date("Y-m-d H:i:s"),
                'type' => '.' . $info->getExtension(),
                'size' => $info->getSize(),
            );

            //图片加水印
            $file_type = $file->getInfo('type');
            $fileextArr = explode(',', $this->image_type);
            if (stristr($file_type, 'image') && 'ico' != $file_ext) {
                print_water($data['url']);
                /*同步到第三方对象存储空间*/
                $bucket_data = SynImageObjectBucket($data['url']);
                $data = array_merge($data, $bucket_data);
                /*end*/
            }
            
            /*-------------------------保存上传图片记录 start-----------------------*/
            $img_info = [];
            if (in_array($file_ext, ['jpg','jpeg','png','bmp','gif','ico','webp'])) {
                $img_info = @getimagesize('.'.$return_url);
                $width = isset($img_info[0]) ? $img_info[0] : 0;
                $height = isset($img_info[1]) ? $img_info[1] : 0;
                $mime = '';
                if (!is_http_url($data['url'])) {
                    $mime = isset($img_info['mime']) ? $img_info['mime'] : $info->getMime();
                }

                $data['time'] = getTime();
                $data['width'] = $width;
                $data['height'] = $height;

                $addData = [
                    'aid'         =>0,
                    'type_id'     =>0,
                    'image_url'   =>$data['url'],
                    'title'       => $data['original'],
                    'intro'       => '',
                    'width'       => $width,
                    'height'      => $height,
                    'filesize'    => $data['size'],
                    'mime'        => $mime,
                    'users_id'    => (int)session('users_id'),
                    'sort_order'  => 100,
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                ];
                $img_id = Db::name('uploads')->insertGetId($addData);
                $data['img_id'] = $img_id;
            }
            /*-------------------------保存上传图片记录 end-----------------------*/
        } else {
            $data = array('state' => 'ERROR'.$info->getError());
        }
        
        return json_encode($data);
    }

    //抓取远程图片
    private function saveRemote($config,$fieldName){
        $imgUrl = htmlspecialchars($fieldName);
        $imgUrl = str_replace("&amp;","&",$imgUrl);
        $imgUrl = preg_replace('/#/', '', $imgUrl);

        //http开头验证
        if(strpos($imgUrl,"http") !== 0){
            $data=array(
                'state' => '链接不是http链接',
            );
            return json_encode($data);
        }
        //获取请求头并检测死链
        $heads = get_headers($imgUrl);
        if(!(stristr($heads[0],"200") && stristr($heads[0],"OK"))){
            $data=array(
                'state' => '链接不可用',
            );
            return json_encode($data);
        }
        //格式验证(扩展名验证和Content-Type验证)
        if(preg_match("/^http(s?):\/\/(mmbiz.qpic.cn|thirdwx.qlogo.cn)\/(.*)/", $imgUrl) != 1){
            $fileType = strtolower(strrchr($imgUrl,'.'));
            if(!in_array($fileType,$config['allowFiles']) || (isset($heads['Content-Type']) && stristr($heads['Content-Type'],"image"))){
                $data=array(
                    'state' => '链接contentType不正确',
                );
                return json_encode($data);
            }
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl,false,$context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/",$imgUrl,$m);

        $dirname = './'.$this->upload_path.'ueditor/'.$this->sub_name;
        $file['oriName'] = $m ? $m[1] : "";
        $file['filesize'] = strlen($img);
        $file['ext'] = strtolower(strrchr($config['oriName'],'.'));
        $file['name'] = uniqid().$file['ext'];
        $file['fullName'] = $dirname.$file['name'];
        $fullName = $file['fullName'];

        //检查文件大小是否超出限制
        if($file['filesize'] >= ($config["maxSize"])){
            $data=array(
                'state' => '文件大小超出网站限制',
            );
            return json_encode($data);
        }

        //创建目录失败
        if(!file_exists($dirname) && !mkdir($dirname,0777,true)){
            $data=array(
                'state' => '目录创建失败',
            );
            return json_encode($data);
        }else if(!is_writeable($dirname)){
            $data=array(
                'state' => '目录没有写权限',
            );
            return json_encode($data);
        }

        //移动文件
        if(!(file_put_contents($fullName, $img) && file_exists($fullName))){ //移动失败
            $data=array(
                'state' => '写入文件内容错误',
            );
            return json_encode($data);
        }else{ //移动成功
            $return_url = substr($file['fullName'],1);
            $data=array(
                'state' => 'SUCCESS',
                'url' => ROOT_DIR.$return_url, // 支持子目录
                'title' => $file['name'],
                'original' => $file['oriName'],
                'type' => $file['ext'],
                'size' => $file['filesize'],
            );
            
            /*-------------------------保存上传图片记录 start-----------------------*/
            $img_info = @getimagesize('.'.$return_url);
            $width = isset($img_info[0]) ? $img_info[0] : 0;
            $height = isset($img_info[1]) ? $img_info[1] : 0;
            $mime = isset($img_info['mime']) ? $img_info['mime'] : '';
            $data['time'] = getTime();
            $data['width'] = $width;
            $data['height'] = $height;

            $addData = [
                'aid'         =>0,
                'type_id'     =>0,
                'image_url'   =>$data['url'],
                'title'       => $data['original'],
                'intro'       => '',
                'width'       => $width,
                'height'      => $height,
                'filesize'    => $data['size'],
                'mime'        => $mime,
                'users_id'    => (int)session('users_id'),
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            $img_id = Db::name('uploads')->insertGetId($addData);
            $data['img_id'] = $img_id;
            /*-------------------------保存上传图片记录 end-----------------------*/

            print_water($data['url']); // 添加水印
        }
        return json_encode($data);
    }

    /**
     * @function imageUp
     */
    public function imageUp()
    {
        if (!IS_POST) {
            $return_data['state'] = '非法上传';
            respose($return_data,'json');
        }

        $max_file_size = input('param.max_file_size/d');
        if (empty($max_file_size)) {
            $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);
        }
        // 上传图片框中的描述表单名称，
        $pictitle = input('pictitle');
        $dir = input('dir');
        $title = htmlspecialchars($pictitle , ENT_QUOTES);        
        $path = htmlspecialchars($dir, ENT_QUOTES);
        //$input_file ['upfile'] = $info['Filedata'];  一个是上传插件里面来的, 另外一个是 文章编辑器里面来的
        // 获取表单上传文件
        $file = request()->file('file');
        if(empty($file)) {
            $file = request()->file('upfile');    
        }

        $original = $file->getInfo('name');
        // 图片后缀名
        $file_ext = pathinfo($original, PATHINFO_EXTENSION);
        // ico图片文件不进行验证
        if ($file_ext != 'ico') {
            if('webp' == $file_ext && version_compare(PHP_VERSION,'7.1.0','<')) {
                $result = "php7.1.0或以上版本才支持上传{$file_ext}";
            } else {
                $result = $this->validate(
                    ['file' => $file], 
                    ['file'=>'image|fileSize:'.$max_file_size.'|fileExt:'.$this->image_type],
                    [
                        'file.image' => '上传文件必须为图片',
                        'file.fileSize' => '上传图片不能超过'.format_bytes($max_file_size),
                        'file.fileExt'=>'上传图片后缀名必须为'.$this->image_type,
                    ]
                );
            }
        } else {
            $result = true;
        }

        /*验证图片一句话木马*/
        if (false === check_illegal($file->getInfo('tmp_name'), false, $file_ext)) {
            $result = '疑似木马图片！';
        }
        /*--end*/

        $return_url = '';
        if (true !== $result || empty($file)) {
            $return_data['state'] = "ERROR：" . $result;
        } else {
            if ('weapp/' == $this->savePath) {
                $savePath = UPLOAD_PATH . $this->savePath . 'user/' . $this->users_id .'/' . $this->sub_name;
            } else {
                $savePath = $this->upload_path.$this->savePath.$this->sub_name;
            }

            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->rule(function ($file) {
                return (int)session('users_id').'-'.dd2char(date("ymdHis").mt_rand(100,999));
            })->move($savePath);
            if (empty($info)) {
                $return_data['state'] = "ERROR" . $file->getError();
            } else {
                $state = "SUCCESS";
                $return_url = '/'.$savePath.$info->getSaveName();

                // 是否要压缩图片
                if (!empty($this->compressConf[0])) {
                    $return_url = func_thumb_img($return_url, $this->compressConf[0], $this->compressConf[1], $this->compressConf[2]);
                }

                $return_data['url'] = ROOT_DIR.$return_url; // 支持子目录
                
                /*-------------------------保存上传图片记录 start-----------------------*/
                $img_info = @getimagesize('.'.$return_url);
                $width = isset($img_info[0]) ? $img_info[0] : 0;
                $height = isset($img_info[1]) ? $img_info[1] : 0;
                $mime = isset($img_info['mime']) ? $img_info['mime'] : '';
                $filesize = $info->getSize();

                $return_data['time'] = getTime();
                $return_data['width'] = $width;
                $return_data['height'] = $height;
                /*-------------------------保存上传图片记录 end-----------------------*/

                if(pathinfo($original, PATHINFO_EXTENSION) != 'ico'){
                    $imgresource = ".".$return_url;
                    $image = \think\Image::open($imgresource);
                    $water = tpCache('water');
                    $return_data['mark_type'] = $water['mark_type'];
                    if($water['is_mark']==1 && $image->width()>$water['mark_width'] && $image->height()>$water['mark_height']){
                        if($water['mark_type'] == 'text'){
                            $ttf = ROOT_PATH.'public/static/common/font/hgzb.ttf';
                            if (file_exists($ttf)) {
                                $size = $water['mark_txt_size'] ? $water['mark_txt_size'] : 30;
                                $color = $water['mark_txt_color'] ?: '#000000';
                                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                                    $color = '#000000';
                                }
                                $transparency = intval((100 - $water['mark_degree']) * (127/100));
                                $color .= dechex($transparency);
                                $image->text($water['mark_txt'], $ttf, $size, $color, $water['mark_sel'])->save($imgresource);
                                $return_data['mark_txt'] = $water['mark_txt'];
                            }
                        }else{
                            /*支持子目录*/
                            $water['mark_img'] = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $water['mark_img']); // 支持子目录
                            /*--end*/
                            $waterPath = "." . $water['mark_img'];
                            if (eyPreventShell($waterPath) && file_exists($waterPath)) {
                                $quality = $water['mark_quality'] ? $water['mark_quality'] : 80;
                                $waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
                                $image->open($waterPath)->save($waterTempPath, null, $quality);
                                $image->water($waterTempPath, $water['mark_sel'], $water['mark_degree'])->save($imgresource);
                                @unlink($waterTempPath);
                            }
                        }
                    }
                }
                $return_data['title'] = $title;
                $return_data['original'] = $original; // 这里好像没啥用 暂时注释起来
                $return_data['state'] = $state;
                $return_data['path'] = $path;

                //同步到第三方对象存储空间
                $bucket_data = SynImageObjectBucket($return_url);
                if (!empty($bucket_data)) {
                    if (!empty($bucket_data['local_save']) && $bucket_data['local_save'] == 1) {
                        unset($info);//解除图片的进程占用
                        $this->del_local($return_url);
                    }
                    unset($bucket_data['local_save']);
                    $return_data = array_merge($return_data, $bucket_data);
                }

                /*-------------------------保存上传图片记录 start-----------------------*/
                $addData = [
                    'aid'         => 0,
                    'type_id'     => 0,
                    'image_url'   => $return_data['url'],
                    'title'       => $original,
                    'intro'       => '',
                    'width'       => $width,
                    'height'      => $height,
                    'filesize'    => $filesize,
                    'mime'        => $mime,
                    'users_id'    => (int)session('users_id'),
                    'sort_order'  => 100,
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                ];
                $img_id = Db::name('uploads')->insertGetId($addData);
                $return_data['img_id'] = $img_id;
                /*-------------------------保存上传图片记录 end-----------------------*/
            }
        }

        respose($return_data,'json');
    }

    /**
     * @function imageUp
     */
    public function DownloadUploadFileAjax()
    {
        if (!IS_POST) {
            $return_data['state'] = '非法上传';
            respose($return_data,'json');
        }
        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? str_replace('|', ',', $file_type) : 'zip,gz,rar,iso,doc,xls,ppt,wps,txt,docx';
        $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);

        $file = request()->file('file');
        // 定义文件名
        $fileName = $file->getInfo('name');
        //拓展名
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        // 提取出文件名，不包括扩展名
        $newfileName = preg_replace('/\.([^\.]+)$/', '', $fileName);
        // 过滤文件名.\/的特殊字符，防止利用上传漏洞
        $newfileName = preg_replace('#(\\\|\/|\.)#i', '', $newfileName);

        $result = $this->validate(
            ['file' => $file],
            ['file'=>'fileSize:'.$max_file_size.'|fileExt:'.$file_type],
            ['file.fileSize' => '上传文件过大','file.fileExt'=>'上传文件后缀名必须为'.$file_type]
        );

        $return_url = '';
        if (true !== $result || empty($file)) {
            $return_data['state'] = "ERROR：" . $result . '，需' . intval(tpCache('basic.file_size')) . 'MB内';
        } else {
            if ('weapp/' == $this->savePath) {
                $savePath = UPLOAD_PATH . $this->savePath . 'user/' . $this->users_id .'/' . $this->sub_name;
            } else {
                $savePath = $this->upload_path.$this->savePath.$this->sub_name;
            }

            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->rule(function ($file) {
                return (int)session('users_id').'-'.dd2char(date("ymdHis").mt_rand(100,999));
            })->move($savePath);
            if (empty($info)) {
                $return_data['state'] = "ERROR" . $file->getError();
            } else {
                $return_url = '/' . $savePath . $info->getSaveName();
                $return_data['url'] = ROOT_DIR . $return_url;// 支持子目录
                $return_data['state'] = "SUCCESS";

                // 同步到第三方对象存储空间
                $bucket_data = SynImageObjectBucket($return_url);
                if (!empty($bucket_data)) {
                    if (!empty($bucket_data['local_save']) && $bucket_data['local_save'] == 1) {
                        unset($info);
                        $this->del_local($return_url);//解除图片的进程占用
                    }
                    unset($bucket_data['local_save']);
                    $return_data = array_merge($return_data, $bucket_data);
                }
            }
        }

        respose($return_data, 'json');
    }

    // 上传多媒体
    public function AjaxUploadMedia()
    {
        if (!IS_POST) {
            return json_encode(['state' => '非法上传']);
        }

        $file     = request()->file('file');
        if (empty($file)) {
            if (!@ini_get('file_uploads')) {
                return json_encode(['state' => '请检查空间是否开启文件上传功能！']);
            } else {
                return json_encode(['state' => 'ERROR，空间限制上传大小！']);
            }
        }
        $error = $file->getError();
        if (!empty($error)) {
            return json_encode(['state' => $error]);
        }

        $media_type                 = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? str_replace('|', ',', $media_type) : config('global.media_ext');
        if (empty($media_type)) {
            return json_encode(['state' => 'ERROR，请设置上传多媒体文件类型！']);
        } else {
            $media_type = str_replace('|', ',', $media_type);
        }
        $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);
        $result  = $this->validate(
            ['file' => $file],
            ['file' => 'fileSize:' . $max_file_size . '|fileExt:' . $media_type],
            ['file.fileSize' => '上传视频过大', 'file.fileExt' => '上传视频后缀名必须为' . $media_type]
        );
        if (true !== $result || empty($file)) {
            $state = "ERROR" . $result;
            return json_encode(['state' => $state]);
        }

        $this->savePath = $this->savePath.date('Ymd/');
        $info = $file->rule(function ($file) {
            return session('admin_id') . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
        })->move(UPLOAD_PATH . $this->savePath);

        if ($info) {
            $file_path = UPLOAD_PATH.$this->savePath.$info->getSaveName();
            $data = array(
                'state'    => 'SUCCESS',
                'url'      => '/' . $file_path,
            );

            $data['url'] = ROOT_DIR . $data['url'];
        } else {
            $data = array('state' => 'ERROR' . $info->getError());
        }
        return $data;
    }

    //未开启同步本地功能，并删除本地图片
    public function del_local($filenames = '')
    {
        $arr = explode('?', $filenames);
        $filenames = current($arr);
        $arr = explode('/', $filenames);
        if (!empty($arr)) {
            $total = count($arr);
            $filename = $arr[$total - 1];
            $filename = str_replace('\\', '', $filename);
            unset($arr[$total - 1]);
            $filepath = implode('/', $arr);
            if (!preg_match('/^([\w\-\_\/]+)$/i', $filepath)) {
                return false;
            }
            $filename = $filepath.'/'.$filename;
            $filename= trim($filename,'.');
            $filename = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/|/public/static/admin/logo/)#i', '$2', $filename);
            if(eyPreventShell($filename) && !empty($filename) && @is_file('.'.$filename) && stristr($filename, 'uploads/')){
                $filename_new = trim($filename,'/');
                $filetype = preg_replace('/^(.*)\.(\w+)$/i', '$2', $filename);
                $phpfile = strtolower(strstr($filename,'.php'));  //排除PHP文件
                $size = getimagesize($filename_new);
                $fileInfo = explode('/',$size['mime']);
                if( $phpfile ){
                    return false;
                }

                if( @unlink('.'.$filename) ){
                    return true;
                }else{
                    return false;
                }
            }
        }

        return true;
    }

    //上传文件
    public function DownloadUploadFile(){
        if (!IS_POST) {
            echo json_encode(['msg' => '非法上传']);exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        // 获取定义的上传最大参数
        $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);
        // 获取上传的文件信息
        $files = request()->file();
        // 若获取不到则定义为空
        $file  = !empty($files['file']) ? $files['file'] : '';

        /*判断上传文件是否存在错误*/
        if(empty($file)){
            echo json_encode(['msg' => '文件过大或文件已损坏！']);exit;
        }
        $error = $file->getError();
        if(!empty($error)){
            echo json_encode(['msg' => $error]);exit;
        }

        $file_type = tpCache('basic.file_type');
        $file_type = !empty($file_type) ? str_replace('|', ',', $file_type) : 'zip,gz,rar,iso,doc,xls,ppt,wps,txt,docx';

        $result = $this->validate(
            ['file' => $file],
            ['file'=>'fileSize:'.$max_file_size.'|fileExt:'.$file_type],
            ['file.fileSize' => '上传文件超过'.tpCache('basic.file_size').'M','file.fileExt'=>'上传文件后缀名必须为'.$file_type]
        );
        if (true !== $result || empty($file)) {
            echo json_encode(['msg' => $result]);exit;
        }
        /*--end*/

        // 移动到框架应用根目录/public/uploads/ 目录下
        $this->savePath = $this->savePath.date('Ymd/');
        // 定义文件名
        $fileName    = $file->getInfo('name');
        // 提取文件名后缀
        $file_ext    = pathinfo($fileName, PATHINFO_EXTENSION);
        // 提取出文件名，不包括扩展名
        $newfileName = preg_replace('/\.([^\.]+)$/', '', $fileName);
        // 过滤文件名.\/的特殊字符，防止利用上传漏洞
        $newfileName = preg_replace('#(\\\|\/|\.)#i', '', $newfileName);
        // 过滤后的新文件名
        $fileName = $newfileName.'.'.$file_ext;
        // 中文转码
        $this->fileName = iconv("utf-8","gb2312//IGNORE",$fileName);

        // 使用自定义的文件保存规则
        $info = $file->rule(function ($file) {
            return  $this->fileName;
        })->move(UPLOAD_PATH.$this->savePath);
        if($info){
            // 拼装数据存入session
            $file_path = UPLOAD_PATH.$this->savePath.$info->getSaveName();
            $return = array(
                'code'      => 1,
                'msg'       => foreign_lang('system10'),
                'file_url'  => '/' . UPLOAD_PATH.$this->savePath.$fileName,
                'file_mime' => $file->getInfo('type'),
                'file_name' => $fileName,
                'file_ext'  => '.' . $file_ext,
                'file_size' => $info->getSize(),
                'uhash'     => $this->uhash($file_path),
                'md5file'   => md5_file($file_path),
            );
        }else{
            $return = array('msg' => $info->getError());
        }
        echo json_encode($return);
    }
    
    private function uhash( $file ) {
        $fragment = 65536;

        $rh = fopen($file, 'rb');
        $size = filesize($file);

        $part1 = fread( $rh, $fragment );
        fseek($rh, $size-$fragment);
        $part2 = fread( $rh, $fragment);
        fclose($rh);

        return md5( $part1.$part2 );
    }

    // 上传视频
    public function upVideo()
    {
        if (!IS_POST) {
            return json_encode(['state' => '非法上传']);
        }

        $file     = request()->file('file');
        if (empty($file)) {
            if (!@ini_get('file_uploads')) {
                return json_encode(['state' => '请检查空间是否开启文件上传功能！']);
            } else {
                return json_encode(['state' => 'ERROR，空间限制上传大小！']);
            }
        }
        $error = $file->getError();
        if (!empty($error)) {
            return json_encode(['state' => $error]);
        }

        $media_type                 = tpCache('basic.media_type');
        $media_type = !empty($media_type) ? str_replace('|', ',', $media_type) : config('global.media_ext');
        if (empty($media_type)) {
            return json_encode(['state' => 'ERROR，请设置上传多媒体文件类型！']);
        } else {
            $media_type = str_replace('|', ',', $media_type);
        }
        $max_file_size = intval(tpCache('basic.file_size') * 1024 * 1024);
        $result  = $this->validate(
            ['file' => $file],
            ['file' => 'fileSize:' . $max_file_size . '|fileExt:' . $media_type],
            ['file.fileSize' => '上传视频过大', 'file.fileExt' => '上传视频后缀名必须为' . $media_type]
        );
        if (true !== $result || empty($file)) {
            $state = "ERROR" . $result;
            return json_encode(['state' => $state]);
        }

        //获取视频时长start
        vendor('getid3.getid3');
        // 实例化
        $getID3       = new \getID3();  //实例化类
        $tmp_name = $file->getInfo('tmp_name');
        $ThisFileInfo = $getID3->analyze($tmp_name); //分析文件，$path为音频文件的地址
        $fileduration = empty($ThisFileInfo['playtime_seconds']) ? 0 : intval($ThisFileInfo['playtime_seconds']); //这个获得的便是音频文件的时长
        //获取视频时长end

        // 移动到框架应用根目录/public/uploads/ 目录下
        $this->savePath = $this->savePath.date('Ymd/');
        // 使用自定义的文件保存规则
        $info = $file->rule(function ($file) {
            return $this->users_id . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
        })->move(UPLOAD_PATH . $this->savePath);

        if ($info) {
            // 定义文件名
            $fileName    = $file->getInfo('name');
            // 提取出文件名，不包括扩展名
            $newfileName = preg_replace('/\.([^\.]+)$/', '', $fileName);
            // 过滤文件名.\/的特殊字符，防止利用上传漏洞
            $newfileName = preg_replace('#(\\\|\/|\.)#i', '', $newfileName);

            $file_path = UPLOAD_PATH.$this->savePath.$info->getSaveName();

            $file_size = $info->getSize();

            $data = array(
                'state'    => 'SUCCESS',
                'url'      => '/' . $file_path,
                'time'     => $fileduration,
                'title'    => $newfileName,
                'original' => $info->getSaveName(),
                'type'     => '.' . $info->getExtension(),
                'size'     => $file_size,
                'mime'     => $file->getInfo('type'),
            );

            $data['url'] = ROOT_DIR . $data['url']; // 支持子目录
        } else {
            $data = array('state' => 'ERROR' . $info->getError());
        }
        return $data;
    }

}