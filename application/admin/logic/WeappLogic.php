<?php
/**
 * eyoucms
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
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
 
class WeappLogic extends Model
{
    public $root_path;
    public $weapp_path;
    public $data_path;
    // public $config_path;
    // public $curent_version;    
    public $upgrade_url;
    public $service_ey;
    // public $code;
    public $cms_version;
    public $upgrade_postdata = [];
    
    /**
     * 析构函数
     */
    function  __construct() {
        // $this->code = input('param.code/s', '');
        // $this->curent_version = getWeappVersion($this->code);
        $this->cms_version = getCmsVersion();
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        $this->service_ey = $upgradeLogic->getServiceUrl(true, 'weapp');
        $this->root_path = ROOT_PATH; // 
        $this->weapp_path = WEAPP_DIR_NAME.DS; // 
        $this->data_path = DATA_PATH; // 
        // $this->config_path = $this->weapp_path.$this->code.DS.'config.php'; // 版本配置文件路径
        // api_Weapp_checkVersion
        $this->upgrade_url = $this->service_ey.'/index.php?m=api&c=Weapp&a=checkVersion';
        $this->upgrade_postdata = [
            'domain' => request()->host(true),
            'cms_version' => $this->cms_version,
            'ip' => serverIP(),
        ];
    }

    /**
     * 更新插件到数据库
     * @param $weapp_list array 本地插件数组
     */
    public function insertWeapp()
    {
        $row        = Db::name('weapp')->field('*')->getAllWithIndex('code'); // 数据库
        $new_arr    = array(); // 本地
        $addData    = array(); // 数据存储变量
        $updateData = array(); // 数据存储变量
        $weapp_list = $this->scanWeapp();
        //  本地对比数据库
        foreach($weapp_list as $k=>$v){
            $code = isset($v['code']) ? $v['code'] : 'error_'.date('Ymd');
            /*初步过滤不规范插件*/
            if ($k != $code) {
                continue;
            }
            /*--end*/
            $new_arr[] = $code;
            // 对比数据库 本地有 数据库没有
            $data = array(
                'code'          => $code,
                'name'          => isset($v['name']) ? $v['name'] : '配置信息不完善',
                'config'        => empty($v) ? '' : json_encode($v),
                'position'      => isset($v['position']) ? $v['position'] : 'default',
                'sort_order'    => 100,
            );
            if(empty($row[$code])){ // 新增插件
                $data['add_time'] = getTime();
                $addData[] = $data;
            } else { // 更新插件
                if ($row[$code]['config'] != json_encode($v)) {
                    $data['id'] = $row[$code]['id'];
                    $data['update_time'] = getTime();
                    $updateData[] = $data;
                }
            }
        }
        if (!empty($addData)) {
            model('weapp')->saveAll($addData);
        }
        if (!empty($updateData)) {
            model('weapp')->saveAll($updateData);
        }
        //数据库有 本地没有
        foreach($row as $k => $v){
            if (!in_array($v['code'], $new_arr) && isset($v['is_buy']) && $v['is_buy'] < 1) {//is_buy  0->本地安装,1-线上购买
                Db::name('weapp')->where($v)->cache(true, null, 'weapp')->delete();
            }
        }

        \think\Cache::clear('weapp');
    }

    /**
     * 插件目录扫描
     * @return array 返回目录数组
     */
    private function scanWeapp(){
        $dir = WEAPP_DIR_NAME;
        $weapp_list = $this->dirscan($dir);
        foreach($weapp_list as $k=>$v){
            if (!is_dir(WEAPP_DIR_NAME.DS.$v) || !file_exists(WEAPP_DIR_NAME.DS.$v.'/config.php')) {
                unset($weapp_list[$k]);
            }
            else
            {
                $weapp_list[$v] = include(WEAPP_DIR_NAME.DS.$v.'/config.php');
                unset($weapp_list[$k]);                    
            }
        }
        return $weapp_list;
    }

    /**
     * 获取插件目录列表
     * @param $dir
     * @return array
     */
    private function dirscan($dir){
        $dirArray = array();
        if (false != ($handle = opendir($dir))) {
            $i = 0;
            while ( false !== ($file = readdir ($handle)) ) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".." && !strpos($file,".")) {
                    $dirArray[$i] = $file;
                    $i++;
                }
            }
            //关闭句柄
            closedir($handle);
        }
        return $dirArray;
    }

    /**
     * 插件基类构造方法
     * sm：module        插件模块
     * sc：controller    插件控制器
     * sa：action        插件操作
     */
    public function checkInstall()
    {
        $msg = true;
        if(!array_key_exists("sm", request()->param())){
            $msg = '无效插件URL！';
        } else {
            $module = request()->param('sm');
            $module = $module ?: request()->param('sc');
            $row = Db::name('Weapp')->field('code, name, status')
                ->where(array('code'=>$module))
                ->find();
            if (empty($row)) {
                $msg = "插件【{$row['name']}】不存在";
            } else {
                if ($row['status'] == -1) {
                    $msg = "请先启用插件【{$row['name']}】";
                } else if (intval($row['status']) == 0) {
                    $msg = "请先安装插件【{$row['name']}】";
                }
            }
        }

        return $msg;
    }

    /**
     * 检查是否有更新包
     * @return type 提示语
     */
    public function checkVersion($code, $serviceVersionList = false) {
        error_reporting(0);//关闭所有错误报告
        $lastupgrade = array();

        if (false === $serviceVersionList) {
            $this->upgrade_postdata['code'] = $code;
            $this->upgrade_postdata['v'] = getWeappVersion($code);
            $upgradeLogic = new \app\admin\logic\UpgradeLogic;
            $upgradeLogic->GetKeyData($this->upgrade_postdata);
            $serviceVersionList = @httpRequest($this->upgrade_url, 'POST', $this->upgrade_postdata, [], 5);
            if (false === $serviceVersionList) {
                $url = $this->upgrade_url.'&'.http_build_query($this->upgrade_postdata);
                $context = stream_context_set_default(array('http' => array('timeout' => 5,'method'=>'GET')));
                $serviceVersionList = @file_get_contents($url, false, $context);
            }
            if (false === $serviceVersionList) {
                return ['code' => 0, 'msg' => "无法连接远程升级服务器！"];
            } else {
                $serviceVersionList = json_decode($serviceVersionList,true);
                if (isset($serviceVersionList['code']) && empty($serviceVersionList['code'])) {
                    $msg = empty($serviceVersionList['msg']) ? 'API请求超时' : $serviceVersionList['msg'];
                    return ['code' => 0, 'msg' => "<font color='red'>{$msg}</font>"];
                } 
            }
        }

        if(!empty($serviceVersionList))
        {
            $upgradeArr = array();
            $introStr = '';
            $upgradeStr = '';
            foreach ($serviceVersionList as $key => $val) {
                $upgrade = !empty($val['upgrade']) ? $val['upgrade'] : array();
                $upgradeArr = array_merge($upgradeArr, $upgrade);
                $introStr .= '<br/>'.filter_line_return($val['intro'], '<br/>');
            }
            $upgradeArr = array_unique($upgradeArr);
            $upgradeStr = implode('<br/>', $upgradeArr); // 升级提示需要覆盖哪些文件

            $introArr = explode('<br/>', $introStr);
            $introStr = '更新日志：';
            foreach ($introArr as $key => $val) {
                if (empty($val)) {
                    continue;
                }
                $introStr .= "<br/>{$key}、".$val;
            }

            $lastupgrade = $serviceVersionList[count($serviceVersionList) - 1];
            if (!empty($lastupgrade['upgrade_title'])) {
                $introStr .= '<br/>'.$lastupgrade['upgrade_title'];
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
     * 批量检查是否有更新包
     * @return type 提示语
     */
    public function checkBatchVersion($upgradeArr) 
    {
        $result = array();
        if (is_array($upgradeArr) && !empty($upgradeArr)) {
            $langRow = \think\Db::name('language')->order('id asc')->select();
            foreach ($upgradeArr as $key => $upgrade) {
                if ($key == 'Sample') {
                    foreach ($langRow as $_k => $_v) {
                        tpCache('system', ['system_usecodelist'=>$upgradeArr['Sample']], $_v['mark']);
                    }
                } else {
                    $result[$key] = $this->checkVersion($key, $upgrade);
                }
            }
        }
        return $result;
    }

    /**
     * 一键更新
     */
    public function OneKeyUpgrade($code){
        error_reporting(0);//关闭所有错误报告
        if (empty($code)) {
            return ['code' => 0, 'msg' => "URL传参错误，缺少插件标识参数值！"];
        }

        $allow_url_fopen = ini_get('allow_url_fopen');
        if (!$allow_url_fopen) {
            return ['code' => 0, 'msg' => "请联系空间商，设置 php.ini 中参数 allow_url_fopen = 1"];
        }     
               
        if (!extension_loaded('zip')) {
            return ['code' => 0, 'msg' => "请联系空间商，开启 php.ini 中的php-zip扩展"];
        }

        $curent_version = getWeappVersion($code);
        $this->upgrade_postdata['code'] = $code;
        $this->upgrade_postdata['dev'] = config('global.upgrade_dev');
        $this->upgrade_postdata['v'] = $curent_version;
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        $upgradeLogic->GetKeyData($this->upgrade_postdata);
        $url = $this->service_ey."/index.php?m=api&c=Weapp&a=upgradeVersion";
        $serviceVersionList = @httpRequest($url, 'POST', $this->upgrade_postdata, [], 5);
        if (false === $serviceVersionList) {
            $url = $url.'&'.http_build_query($this->upgrade_postdata);
            $context = stream_context_set_default(array('http' => array('timeout' => 5,'method'=>'GET')));
            $serviceVersionList = @file_get_contents($url, false, $context);
        }
        if (false === $serviceVersionList) {
            return ['code' => 0, 'msg' => "无法连接远程升级服务器！"];
        } else {
            $serviceVersionList = json_decode($serviceVersionList,true);
            if (isset($serviceVersionList['code']) && empty($serviceVersionList['code'])) {
                $msg = empty($serviceVersionList['msg']) ? 'API请求超时' : $serviceVersionList['msg'];
                return ['code' => 0, 'msg' => $msg];
            }
        }
        if (empty($serviceVersionList)) {
            return ['code' => 0, 'msg' => "当前没有可升级的版本！"];
        }

        clearstatcache(); // 清除文件夹权限缓存
        $config_path = $this->weapp_path.$code.DS.'config.php'; // 版本配置文件路径
        if(!is_writeable($config_path)) {
            return ['code' => 0, 'msg' => '文件'.$config_path.' 不可写，不能升级!!!'];
        }
        /*最新更新版本信息*/
        $lastServiceVersion = $serviceVersionList[count($serviceVersionList) - 1];
        /*--end*/
        /*批量下载更新包*/
        $upgradeArr = array(); // 更新的文件列表
        $sqlfileArr = array(); // 更新SQL文件列表
        $folderName = $code.'-'.$lastServiceVersion['key_num'];
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
                $folderName = $code.'-'.str_replace(".zip", "", $lastDownFileName);  // 文件夹
                /*--end*/

                /*解压之前，删除已重复的文件夹*/
                delFile($this->data_path.'backup'.DS.$folderName);
                /*--end*/
            }
            /*--end*/

            $downFileName = explode('/', $val['down_url']);    
            $downFileName = end($downFileName);

            /*解压文件*/
            $zip = new \ZipArchive();//新建一个ZipArchive的对象
            if($zip->open($this->data_path.'backup'.DS.$downFileName) != true) {
                return ['code' => 0, 'msg' => "升级包读取失败!"];
            }
            $zip->extractTo($this->data_path.'backup'.DS.$folderName.DS);//假设解压缩到在当前路径下backup文件夹内
            $zip->close();//关闭处理的zip文件
            /*--end*/

            if(!file_exists($this->data_path.'backup'.DS.$folderName.DS.'www'.DS.'weapp'.DS.$code.DS.'config.php')) {
                return ['code' => 0, 'msg' => $code."插件目录缺少config.php文件,请联系客服"];
            }

            /*更新的文件列表*/
            $upgrade = !empty($val['upgrade']) ? $val['upgrade'] : array();
            $upgradeArr = array_merge($upgradeArr, $upgrade);
            /*--end*/

            /*更新的SQL文件列表*/
            $sql_file = !empty($val['sql_file']) ? $val['sql_file'] : array();
            $sqlfileArr = array_merge($sqlfileArr, $sql_file);
            /*--end*/
        }
        /*--end*/

        /*将多个更新包重新组建一个新的完全更新包*/
        $upgradeArr = array_unique($upgradeArr); // 移除文件列表里重复的文件
        $sqlfileArr = array_unique($sqlfileArr); // 移除文件列表里重复的文件
        $serviceVersion = $lastServiceVersion;
        $serviceVersion['upgrade'] = $upgradeArr;
        $serviceVersion['sql_file'] = $sqlfileArr;
        /*--end*/

        /*升级之前，备份涉及的源文件*/
        $upgrade = $serviceVersion['upgrade'];
        if (!empty($upgrade) && is_array($upgrade)) {
            foreach ($upgrade as $key => $val) {
                $source_file = $this->root_path.$val;
                if (file_exists($source_file)) {
                    $destination_file = $this->data_path.'backup'.DS.$code.'-'.$curent_version.'_www'.DS.$val;
                    tp_mkdir(dirname($destination_file));
                    $copy_bool = @copy($source_file, $destination_file);
                    if (false == $copy_bool) {
                        return ['code' => 0, 'msg' => "更新前备份文件失败，请检查所有目录是否有读写权限"];
                    }
                }
            }
        }
        /*--end*/

        /*升级的 sql文件*/
        if(!empty($serviceVersion['sql_file']))
        {
            foreach($serviceVersion['sql_file'] as $key => $val)
            {
                //读取数据文件
                $sqlpath = $this->data_path.'backup'.DS.$folderName.DS.'sql'.DS.trim($val);
                $execute_sql = file_get_contents($sqlpath);
                $sqlFormat = $this->sql_split($execute_sql, PREFIX);
                /**
                 * 执行SQL语句
                 */
                try {
                    $counts = count($sqlFormat);

                    for ($i = 0; $i < $counts; $i++) {
                        $sql = trim($sqlFormat[$i]);

                        if (stristr($sql, 'CREATE TABLE')) {
                            Db::execute($sql);
                        } else {
                            if(trim($sql) == '')
                               continue;
                            Db::execute($sql);
                        }
                    }
                } catch (\Exception $e) {
                    return ['code' => 0, 'msg' => "数据库执行中途失败，请第一时间请求技术支持，否则将影响该插件后续的版本升级！"];
                }
            }
        }
        /*--end*/

        // 递归复制文件夹
        $copy_data = $this->recurse_copy($this->data_path.'backup'.DS.$folderName.DS.'www', rtrim($this->root_path, DS), $folderName);

        // 清空缓存
        delFile(RUNTIME_PATH.'cache');
        delFile(RUNTIME_PATH.'temp');
        tpCache('global');

        /*删除下载的升级包*/
        $ziplist = glob($this->data_path.'backup'.DS.'*.zip');
        @array_map('unlink', $ziplist);
        /*--end*/

        // 推送回服务器  记录升级成功
        $this->UpgradeLog($code, $serviceVersion['key_num']);
        
        return ['code' => $copy_data['code'], 'msg' => "升级成功{$copy_data['msg']}"];
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
        tp_mkdir($dst);
        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurse_copy($src . '/' . $file, $dst . '/' . $file, $folderName);
                }
                else {
                    if (file_exists($src . DIRECTORY_SEPARATOR . $file)) {
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
            $msg = "，其中失败 <font color='red'>{$badcp}</font> 个文件，<br />请从升级包目录[<font color='red'>data/backup/{$folderName}/www</font>]中的取出全部文件覆盖到根目录，完成手工升级。";
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

    public function sql_split($sql, $tablepre) {

        $sql = str_replace("`#@__", '`'.$tablepre, $sql);
              
        $sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);
        
        $sql = str_replace("\r", "\n", $sql);
        $ret = array();
        $num = 0;
        $queriesarray = explode(";\n", trim($sql));
        unset($sql);
        foreach ($queriesarray as $query) {
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            $queries = array_filter($queries);
            foreach ($queries as $query) {
                $str1 = substr($query, 0, 1);
                if ($str1 != '#' && $str1 != '-')
                    $ret[$num] .= $query;
            }
            $num++;
        }
        return $ret;
    }
 
    /**     
     * @param type $fileUrl 下载文件地址
     * @param type $md5File 文件MD5 加密值 用于对比下载是否完整
     * @return string 错误或成功提示
     */
    private function downloadFile($fileUrl,$md5File)
    {
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        return $upgradeLogic->downloadFile($fileUrl, $md5File, 'weapp');
    }            
    
    // 升级记录 log 日志
    private  function UpgradeLog($code, $to_key_num){
        $serial_number = DEFAULT_SERIALNUMBER;

        $constsant_path = APP_PATH.MODULE_NAME.'/conf/constant.php';
        if (file_exists($constsant_path)) {
            require_once($constsant_path);
            defined('SERIALNUMBER') && $serial_number = SERIALNUMBER;
        }
        $mysqlinfo = \think\Db::query("SELECT VERSION() as version");
        $mysql_version  = $mysqlinfo[0]['version'];
        $values = array(
            'domain'=>request()->host(), //用户域名  
            'code'  => $code, // 插件标识
            'key_num'=>getWeappVersion($code), // 用户版本号
            'to_key_num'=>$to_key_num, // 用户要升级的版本号                
            'add_time'=>time(), // 升级时间
            'serial_number'=>$serial_number,
            'ip'    => GetHostByName($_SERVER['SERVER_NAME']),
            'phpv'  => phpversion(),
            'mysql_version' => $mysql_version,
            'web_server'    => $_SERVER['SERVER_SOFTWARE'],
        );
        // api_Weapp_upgradeLog
        $upgradeLogic = new \app\admin\logic\UpgradeLogic;
        $upgradeLogic->GetKeyData($values);
        $url = $this->service_ey.'/index.php?m=api&c=Weapp&a=upgradeLog';
        httpRequest($url, 'POST', $values, [], 5);
    }

    /**
     * 兼容早期版本，1.4.5或以下版本
     * @param  array  &$assign_data [description]
     * @return [type]               [description]
     */
    public function installpwd_145(&$assign_data = [])
    {
        $main_lang = get_main_lang();
        /*插件安装密码设置*/
        $weapp_installpwd = tpCache('weapp.weapp_installpwd', [], $main_lang);
        if (!empty($weapp_installpwd)) {
            $weapp_installpwd = 1;
        } else {
            $weapp_installpwd = 0;
        }
        $assign_data['weapp_installpwd'] = $weapp_installpwd;
        /*--end*/

        $admin_info = session('admin_info');
        /*是否创始人*/
        $isFounder = 0;
        if (empty($admin_info['parent_id']) && -1 == $admin_info['role_id']) {
            $isFounder = 1;
        }
        $assign_data['isFounder'] = $isFounder;
        /*--end*/

        /*登录第一次输入插件安装密码之后，在退出之前安装所有插件都不再输入安装密码*/
        $is_weapp_installpwd = 0;
        $weapp_installpwd = tpCache('weapp.weapp_installpwd', [], $main_lang);
        $firstInstallpwd = empty($admin_info['weapp_info']['firstInstallpwd']) ? '' : $admin_info['weapp_info']['firstInstallpwd'];
        if (!empty($firstInstallpwd) && $firstInstallpwd == $weapp_installpwd) {
            $is_weapp_installpwd = 1;
        }
        $assign_data['is_weapp_installpwd'] = $is_weapp_installpwd;
        /*--end*/
    }

    /**
     *  加密函数
     *
     * @access    public
     * @param     string $string 字符串
     * @param     string $operation 操作
     * @return    string
     */
    public function mchStrCode($string, $operation = 'ENCODE', $auth_code = '')
    {
        if (empty($auth_code)) {
            if (function_exists('get_auth_code')) {
                $auth_code = get_auth_code();
            } else {
                $auth_code = tpCache('system.system_auth_code');
                if (empty($auth_code)) {
                    $auth_code = \think\Config::get('AUTH_CODE');
                    /*多语言*/
                    static $langRow = null;
                    null === $langRow && $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('system', ['system_auth_code'=>$auth_code], $val['mark']);
                    }
                    /*--end*/
                }
            }
        }
        
        if (function_exists('mchStrCode')) {
            return mchStrCode($string, $operation, $auth_code);
        }

        $key_length = 4;
        $expiry     = 0;
        $key        = md5($auth_code);
        $fixedkey   = md5($key);
        $egiskeys   = md5(substr($fixedkey, 16, 16));
        $runtokey   = $key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($string, 0, $key_length)) : '';
        $keys       = md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
        $string     = $operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $egiskeys), 0, 16) . $string : base64_decode(substr($string, $key_length));

        $i             = 0;
        $result        = '';
        $string_length = strlen($string);
        for ($i = 0; $i < $string_length; $i++) {
            $result .= chr(ord($string[$i]) ^ ord($keys[$i % 32]));
        }
        if ($operation == 'ENCODE') {
            return $runtokey . str_replace('=', '', base64_encode($result));
        } else {
            $str1 = substr($result, 0, 10);
            if(version_compare(PHP_VERSION,'8.0.0','>')) {
                $str1 = !empty($str1) ? $str1 : 0;
            }

            if (($str1 == 0 || intval($str1) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $egiskeys), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        }
    }
} 
?>