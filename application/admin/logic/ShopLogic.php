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

namespace app\admin\logic;

use think\Model;
use think\Db;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class ShopLogic extends Model
{
    private $request = null;
    private $data_path;
    private $version_txt_path;
    private $version;    
    private $service_url;
    private $upgrade_url;
    private $service_ey;
    private $planPath_pc;
    private $planPath_m;

    /**
     * 析构函数
     */
    function  __construct() {
        $this->request = request();
        $this->service_ey = config('service_ey');
        $this->data_path = DATA_PATH; // 
        $this->version_txt_path = $this->data_path.'conf'.DS.'version_themeshop.txt'; // 版本文件路径
        $this->version = getVersion('version_themeshop');
        // api_Service_checkVersion
        $tmp_str = 'L2luZGV4LnBocD9tPWFwaSZjPVVwZ3JhZGUmYT1jaGVja1RoZW1lVmVyc2lvbg==';
        $this->service_url = base64_decode($this->service_ey).base64_decode($tmp_str);
        $this->upgrade_url = $this->service_url . '&domain='.request()->host(true).'&v=' . $this->version.'&type=theme_shop&cms_version='.getVersion().'&ip='.serverIP();
        $this->planPath_pc = 'template/'.TPL_THEME.'pc/';
        $this->planPath_m = 'template/'.TPL_THEME.'mobile/';
    }

    // 过期订单预处理
    public function OverdueOrderHandle()
    {
        $ShopOrder = Db::name('shop_order')->where('order_status', 4)->column('order_id');
        if (!empty($ShopOrder)) {
            // 删除条件
            $where['order_id'] = ['IN', $ShopOrder];
            // 删除订单主表
            Db::name('shop_order')->where($where)->delete();
            // 删除订单副表
            Db::name('shop_order_details')->where($where)->delete();
            // 删除订单操作记录表
            Db::name('shop_order_log')->where($where)->delete();
        }
    }

    /**
     * 检测并第一次从官方同步订单中心的前台模板
     */
    public function syn_theme_shop()
    {
        error_reporting(0);//关闭所有错误报告
        $web_users_tpl_theme = tpCache('web.web_users_tpl_theme');
        empty($web_users_tpl_theme) && $web_users_tpl_theme = 'users';
        $shop_tpl_list = glob("./{$this->planPath_pc}{$web_users_tpl_theme}/shop_*");
        if (empty($shop_tpl_list)) {
            return $this->OneKeyUpgrade();
        } else {
            return true;
        }
    }

    /**
     * 检测目录权限
     */
    public function checkAuthority($filelist = '')
    {
        /*------------------检测目录读写权限----------------------*/
        $filelist = htmlspecialchars_decode($filelist);
        $filelist = explode('<br>', $filelist);

        $dirs = array();
        $i = -1;
        foreach($filelist as $filename)
        {
            if (stristr($filename, $this->planPath_pc) && !file_exists($this->planPath_pc)) {
                continue;
            } else if (stristr($filename, $this->planPath_m) && !file_exists($this->planPath_m)) {
                continue;
            }

            $tfilename = $filename;
            $curdir = $this->GetDirName($tfilename);
            if (empty($curdir)) {
                continue;
            }
            if( !isset($dirs[$curdir]) ) 
            {
                $dirs[$curdir] = $this->TestIsFileDir($curdir);
            }
            if($dirs[$curdir]['isdir'] == FALSE) 
            {
                continue;
            }
            else {
                $dirs[$curdir] = $this->TestIsFileDir($curdir);
            }
            $i++;
        }

        $is_pass = true;
        $msg = '检测通过';
        if($i > -1)
        {
            $n = 0;
            $dirinfos = '';
            foreach($dirs as $curdir)
            {
                $dirinfos .= $curdir['name']."&nbsp;&nbsp;状态：";
                if ($curdir['writeable']) {
                    $dirinfos .= "[√正常]";
                } else {
                    $is_pass = false;
                    $n++;
                    $dirinfos .= "<font color='red'>[×不可写]</font>";
                }
                $dirinfos .= "<br />";
            }
            $title = "本次升级需要在下面文件夹写入更新文件，已检测站点有 <font color='red'>{$n}</font> 处没有写入权限：<br />";
            $title .= "<font color='red'>问题分析（如有问题，请咨询技术支持）：<br />";
            $title .= "1、检查站点目录的用户组与所有者，禁止是 root ;<br />";
            $title .= "2、检查站点目录的读写权限，一般权限值是 0755 ;<br />";
            $title .= "</font>涉及更新目录列表如下：<br />";
            $msg = $title . $dirinfos;
        }
        /*------------------end----------------------*/

        if (true === $is_pass) {
            return ['code'=>1, 'msg'=>$msg];
        } else {
            return ['code'=>0, 'msg'=>$msg, 'data'=>['code'=>1]];
        }
    }

    /**
     * 检查是否有更新包
     * @return type 提示语
     */
    public function checkVersion() {
        //error_reporting(0);//关闭所有错误报告     
        $allow_url_fopen = ini_get('allow_url_fopen');
        if (!$allow_url_fopen) {
            return ['code' => 1, 'msg' => "<font color='red'>请联系空间商（设置 php.ini 中参数 allow_url_fopen = 1）</font>"];
        }

        $url = $this->upgrade_url; 
        $serviceVersionList = @httpRequest($url);
        if (false === $serviceVersionList) {
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $serviceVersionList = @file_get_contents($url,false,$context); 
        }
        $serviceVersionList = json_decode($serviceVersionList,true);
        if(!empty($serviceVersionList))
        {
            $upgradeArr = array();
            $introStr = '';
            $upgradeStr = '';
            foreach ($serviceVersionList as $key => $val) {
                $upgrade = !empty($val['upgrade']) ? $val['upgrade'] : array();
                $upgradeArr = array_merge($upgradeArr, $upgrade);
                $introStr .= '<br>'.filter_line_return($val['intro'], '<br>');
            }
            $upgradeArr = array_unique($upgradeArr);
            foreach ($upgradeArr as $key => $val) {
                if (stristr($val, $this->planPath_pc) && !file_exists($this->planPath_pc)) {
                    unset($upgradeArr[$key]);
                } else if (stristr($val, $this->planPath_m) && !file_exists($this->planPath_m)) {
                    unset($upgradeArr[$key]);
                }
            }
            $upgradeStr = implode('<br>', $upgradeArr); // 升级提示需要覆盖哪些文件

            $introArr = explode('<br>', $introStr);
            $introStr = '更新日志：';
            foreach ($introArr as $key => $val) {
                if (empty($val)) {
                    continue;
                }
                $introStr .= "<br>{$key}、".$val;
            }

            $lastupgrade = $serviceVersionList[count($serviceVersionList) - 1];
            if (!empty($lastupgrade['upgrade_title'])) {
                $introStr .= '<br>'.$lastupgrade['upgrade_title'];
            }
            $lastupgrade['intro'] = htmlspecialchars_decode($introStr);
            $lastupgrade['upgrade'] = htmlspecialchars_decode($upgradeStr); // 升级提示需要覆盖哪些文件
            /*升级公告*/
            if (!empty($lastupgrade['notice'])) {
                $lastupgrade['notice'] = htmlspecialchars_decode($lastupgrade['notice']) . '<br>';
            }
            /*--end*/

            return ['code' => 2, 'msg' => $lastupgrade];
        }
        return ['code' => 1, 'msg' => '已是最新版'];
    }

    /**
     * 检查是否有更新包
     * @return type 提示语
     */
    public function OneKeyUpgrade() {
        $allow_url_fopen = ini_get('allow_url_fopen');
        if (!$allow_url_fopen) {
            return ['code' => 0, 'msg' => "请联系空间商，设置 php.ini 中参数 allow_url_fopen = 1"];
        }     
               
        if (!extension_loaded('zip')) {
            return ['code' => 0, 'msg' => "请联系空间商，开启 php.ini 中的php-zip扩展"];
        }

        $serviceVersionList = @httpRequest($this->upgrade_url);
        if (false === $serviceVersionList) {
            $serviceVersionList = @file_get_contents($this->upgrade_url); 
        }
        $serviceVersionList = json_decode($serviceVersionList,true);
        if (empty($serviceVersionList)) {
            if ('v1.0.1' > $this->version) {
                return ['code' => 0, 'msg' => "请求服务器失败，请检查是否网络故障！"];
            } else {
                return ['code' => 0, 'msg' => "没找到升级信息"];
            }
        } else if (isset($serviceVersionList['code']) && empty($serviceVersionList['code'])) {
            $icon = !empty($serviceVersionList['icon']) ? $serviceVersionList['icon'] : 2;
            return ['code' => 0, 'msg' => $serviceVersionList['msg'], 'icon'=>$icon];
        }
        
        clearstatcache(); // 清除文件夹权限缓存
        if (!is_writeable($this->version_txt_path)) {
            return ['code' => 0, 'msg' => '文件'.$this->version_txt_path.' 不可写，不能升级!!!'];
        }
        /*最新更新版本信息*/
        $lastServiceVersion = $serviceVersionList[count($serviceVersionList) - 1];
        /*--end*/
        /*批量下载更新包*/
        $upgradeArr = array(); // 更新的文件列表
        $folderName = 'shop-'.$lastServiceVersion['key_num'];
        foreach ($serviceVersionList as $key => $val) {
            // 下载更新包
            $result = $this->downloadFile($val['down_url'], $val['file_md5']);
            if (!isset($result['code']) || $result['code'] != 1) {
                return $result;
            }

            /*第一个循环执行的业务*/
            if ($key == 0) {
                /*解压到最后一个更新包的文件夹*/
                $lastDownFileName = explode('/', $lastServiceVersion['down_url']);    
                $lastDownFileName = end($lastDownFileName);
                $folderName = 'shop-'.str_replace(".zip", "", $lastDownFileName);  // 文件夹
                /*--end*/

                /*解压之前，删除已重复的文件夹*/
                delFile($this->data_path.'backup'.DS.'theme'.DS.$folderName);
                /*--end*/
            }
            /*--end*/

            $downFileName = explode('/', $val['down_url']);    
            $downFileName = 'shop-'.end($downFileName);

            /*解压文件*/
            $zip = new \ZipArchive();//新建一个ZipArchive的对象
            if ($zip->open($this->data_path.'backup'.DS.'theme'.DS.$downFileName) != true) {
                return ['code' => 0, 'msg' => "升级包读取失败!"];
            }
            $zip->extractTo($this->data_path.'backup'.DS.'theme'.DS.$folderName.DS);//假设解压缩到在当前路径下backup文件夹内
            $zip->close();//关闭处理的zip文件
            /*--end*/
            
            if (!file_exists($this->data_path.'backup'.DS.'theme'.DS.$folderName.DS.'data'.DS.'conf'.DS.'version_themeshop.txt')) {
                return ['code' => 0, 'msg' => "缺少version_themeshop.txt文件，请联系客服"];
            }

            /*更新的文件列表*/
            $upgrade = !empty($val['upgrade']) ? $val['upgrade'] : array();
            $upgradeArr = array_merge($upgradeArr, $upgrade);
            /*--end*/
        }
        /*--end*/

        /*将多个更新包重新组建一个新的完全更新包*/
        $upgradeArr = array_unique($upgradeArr); // 移除文件列表里重复的文件
        $serviceVersion = $lastServiceVersion;
        $serviceVersion['upgrade'] = $upgradeArr;
        /*--end*/

        /*升级之前，备份涉及的源文件*/
        $upgrade = $serviceVersion['upgrade'];
        if (!empty($upgrade) && is_array($upgrade)) {
            foreach ($upgrade as $key => $val) {
                $source_file = ROOT_PATH.$val;
                if (file_exists($source_file)) {
                    $destination_file = $this->data_path.'backup'.DS.'theme'.DS.$folderName.'_www'.DS.$val;
                    tp_mkdir(dirname($destination_file));
                    $copy_bool = @copy($source_file, $destination_file);
                    if (false == $copy_bool) {
                        return ['code' => 0, 'msg' => "更新前备份文件失败，请检查所有目录是否有读写权限"];
                    }
                }
            }
        }
        /*--end*/

        // 递归复制文件夹
        $copy_data = $this->recurse_copy($this->data_path.'backup'.DS.'theme'.DS.$folderName, rtrim(ROOT_PATH, DS), $folderName);

        /*删除下载的升级包*/
        $ziplist = glob($this->data_path.'backup'.DS.'theme'.DS.'shop-*.zip');
        @array_map('unlink', $ziplist);
        /*--end*/
        
        // 推送回服务器  记录升级成功
        $this->UpgradeLog($serviceVersion['key_num']);
        
        return ['code' => $copy_data['code'], 'msg' => "升级模板成功{$copy_data['msg']}"];
    }

    /**
     * 自定义函数递归的复制带有多级子目录的目录
     * 递归复制文件夹
     *
     * @param string $src 原目录
     * @param string $dst 复制到的目录
     * @param string $folderName 存放升级包目录名称
     * @return string
     */                        
    //参数说明：            
    //自定义函数递归的复制带有多级子目录的目录
    private function recurse_copy($src, $dst, $folderName)
    {
        static $badcp = 0; // 累计覆盖失败的文件总数
        static $n = 0; // 累计执行覆盖的文件总数
        static $total = 0; // 累计更新的文件总数

        $dir = opendir($src);

        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
        $dst_tmp = str_replace('\\', '/', $dst);
        $dst_tmp = rtrim($dst_tmp, '/').'/';
        if (stristr($dst_tmp, $this->planPath_pc) && file_exists($this->planPath_pc)) {
            tp_mkdir($dst);
        } else if (stristr($dst_tmp, $this->planPath_m) && file_exists($this->planPath_m)) {
            tp_mkdir($dst);
        }
        /*--end*/

        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $needle = '/template/'.TPL_THEME;
                    $needle = rtrim($needle, '/');
                    $dstfile = $dst . '/' . $file;
                    if (!stristr($dstfile, $needle)) {
                        $dstfile = str_replace('/template', $needle, $dstfile);
                    }
                    $this->recurse_copy($src . '/' . $file, $dstfile, $folderName);
                }
                else {
                    if (file_exists($src . DIRECTORY_SEPARATOR . $file)) {
                        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
                        $rs = true;
                        $src_tmp = str_replace('\\', '/', $src . DIRECTORY_SEPARATOR . $file);
                        if (stristr($src_tmp, $this->planPath_pc) && !file_exists($this->planPath_pc)) {
                            continue;
                        } else if (stristr($src_tmp, $this->planPath_m) && !file_exists($this->planPath_m)) {
                            continue;
                        }
                        /*--end*/
                        $rs = @copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                        if($rs) {
                            $n++;
                            @unlink($src . DIRECTORY_SEPARATOR . $file);
                        } else {
                            $n++;
                            $badcp++;
                        }
                    } else {
                        $n++;
                    }
                    $total++;
                }
            }
        }
        closedir($dir);

        $code = 1;
        $msg = '！';
        if($badcp > 0)
        {
            $code = 2;
            $msg = "，其中失败 <font color='red'>{$badcp}</font> 个文件，<br />请从升级包目录[<font color='red'>data/backup/theme/{$folderName}</font>]中的取出全部文件覆盖到根目录，完成手工升级。";
        }

        $this->copy_speed($n, $total);

        return ['code'=>$code, 'msg'=>$msg];
    }

    /**
     * 复制文件进度
     */
    private function copy_speed($n, $total)
    {
        $data = false;

        if ($n < $total) {
            $this->copy_speed($n, $total);
        } else {
            $data = true;
        }
        
        return $data;
    }
 
    /**     
     * @param type $fileUrl 下载文件地址
     * @param type $md5File 文件MD5 加密值 用于对比下载是否完整
     * @return string 错误或成功提示
     */
    private function downloadFile($fileUrl,$md5File)
    {                    
        $downFileName = explode('/', $fileUrl);    
        $downFileName = 'shop-'.end($downFileName);
        $saveDir = $this->data_path.'backup'.DS.'theme'.DS.$downFileName; // 保存目录
        tp_mkdir(dirname($saveDir));
        $content = @httpRequest($fileUrl);
        if (false === $content) {
            $content = @file_get_contents($fileUrl, 0, null, 0, 1);
        }

        if(!$content){
            return ['code' => 0, 'msg' => '官方升级包不存在']; // 文件存在直接退出
        }

        if (!stristr($fileUrl, 'https://service')) {
            $ch = curl_init($fileUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
            $file = curl_exec ($ch);
            curl_close ($ch);
        } else {
            $file = httpRequest($fileUrl);
        }

        if (preg_match('#__HALT_COMPILER()#i', $file)) {
            return ['code' => 0, 'msg' => '下载包损坏，请联系官方客服！'];
        }
 
        $fp = fopen($saveDir,'w');
        fwrite($fp, $file);
        fclose($fp);
        if(!eyPreventShell($saveDir) || !file_exists($saveDir) || $md5File != md5_file($saveDir))
        {
            return ['code' => 0, 'msg' => '下载升级包失败，请检查网站目录权限'];
        }
        return ['code' => 1, 'msg' => '下载成功'];
    }                
    
    // 升级记录 log 日志
    private  function UpgradeLog($to_key_num){
        $serial_number = DEFAULT_SERIALNUMBER;

        $constsant_path = APP_PATH.MODULE_NAME.'/conf/constant.php';
        if (file_exists($constsant_path)) {
            require_once($constsant_path);
            defined('SERIALNUMBER') && $serial_number = SERIALNUMBER;
        }
        $mysqlinfo = \think\Db::query("SELECT VERSION() as version");
        $mysql_version  = $mysqlinfo[0]['version'];
        $values = array(
            'type'  => 'theme_shop',
            'domain'=>request()->host(), //用户域名                
            'key_num'=>$this->version, // 用户版本号
            'to_key_num'=>$to_key_num, // 用户要升级的版本号                
            'add_time'=>time(), // 升级时间
            'serial_number'=>$serial_number,
            'ip'    => GetHostByName($_SERVER['SERVER_NAME']),
            'phpv'  => phpversion(),
            'mysql_version' => $mysql_version,
            'web_server'    => $_SERVER['SERVER_SOFTWARE'],
        );
        // api_Service_upgradeLog
        $tmp_str = 'L2luZGV4LnBocD9tPWFwaSZjPVVwZ3JhZGUmYT11cGdyYWRlTG9nJg==';
        $url = base64_decode($this->service_ey).base64_decode($tmp_str).http_build_query($values);
        @httpRequest($url);
    }

    /**
     * 获取文件的目录路径
     * @param string $filename 文件路径+文件名
     * @return string
     */
    private function GetDirName($filename)
    {
        $dirname = preg_replace("#[\\\\\/]{1,}#", '/', $filename);
        $dirname = preg_replace("#([^\/]*)$#", '', $dirname);
        return $dirname;
    }

    /**
     * 测试目录路径是否有读写权限
     * @param string $dirname 文件目录路径
     * @return array
     */
    private function TestIsFileDir($dirname)
    {
        $dirs = array('name'=>'', 'isdir'=>FALSE, 'writeable'=>FALSE);
        $dirs['name'] =  $dirname;
        tp_mkdir($dirname);
        if(is_dir($dirname))
        {
            $dirs['isdir'] = TRUE;
            $dirs['writeable'] = $this->TestWriteAble($dirname);
        }
        return $dirs;
    }

    /**
     * 测试目录路径是否有写入权限
     * @param string $d 目录路劲
     * @return boolean
     */
    private function TestWriteAble($d)
    {
        $tfile = '_eyout.txt';
        $fp = @fopen($d.$tfile,'w');
        if(!$fp) {
            return false;
        }
        else {
            fclose($fp);
            $rs = @unlink($d.$tfile);
            return true;
        }
    }

    /**
     * 列出营销功能里已使用的模块
     * @return [type] [description]
     */
    public function marketLogic()
    {
        // 列出已使用的功能模块
        $func = [];
        // 整点秒杀功能是否被用
        $sharpCount = Db::name('sharp_goods')->where(['sharp_goods_id'=>['gt',0]])->count();
        if (!empty($sharpCount)) {
            $func[] = 'sharp';
        }
        // 优惠券功能是否被用
        $couponCount = Db::name('shop_coupon')->where(['coupon_id'=>['gt',0]])->count();
        if (!empty($couponCount)) {
            $func[] = 'coupon';
        }
        // 返回值
        return $func;
    }

    /**
     * 列出功能地图里已使用的模块
     * @return [type] [description]
     */
    public function useFuncLogic()
    {
        // 列出已使用的功能模块
        $func = [];
        // 积分兑换功能是否被用 - 暂时不禁用了，先留着用
        $memgiftCount = Db::name('memgift')->where(['gift_id'=>['gt',0]])->count();
        if (true || !empty($memgiftCount)) {
            $func[] = 'memgift';
        }
        // 返回值
        return $func;
    }
}
