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

if (!function_exists('unifyPriceHandle')) {
    /**
     * 显示价格统一处理
     * @param string $price 金额
     * @param bool $decimal 是否保留小数，true保留，false不保留
     * @return string
     */
    function unifyPriceHandle($price = 0, $decimal = false)
    {
        // 为空则返回 0
        if (empty($price)) return 0;

        // 保留两位小数
        $price = sprintf("%.2f", $price);

        // 不保留小数，强制转换浮点类型
        if (empty($decimal)) $price = floatval($price);

        // 返回结果
        return $price;
    }
}

if (!function_exists('removeJsTagCode')) {
    /**
     * 去除JS标签代码
     */
    function removeJsTagCode($param = [])
    {
        // 处理去除
        foreach ($param as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $param[$key] = htmlspecialchars(preg_replace('/<script([^\>]*)>([\s\S]+)<\/script>/i', '', htmlspecialchars_decode($value)));
            }
        }
        // 返回内容
        return $param;
    }
}

if (!function_exists('convert_arr_key')) {
    /**
     * 将数据库中查出的列表以指定的 id 作为数组的键名
     *
     * @param array $arr 数组
     * @param string $key_name 数组键名
     * @return array
     */
    function convert_arr_key($arr, $key_name)
    {
        if (function_exists('array_column')) {
            return array_column($arr, null, $key_name);
        }

        $arr2 = array();
        foreach ($arr as $key => $val) {
            $arr2[$val[$key_name]] = $val;
        }
        return $arr2;
    }
}

if (!function_exists('reform_keys')) {
    /**
     * 重置数组索引，兼容多维数组
     *
     * @param array $arr 数组
     * @param string $key_name 数组键名
     * @return array
     */
    function reform_keys($array){
        if(!is_array($array)){
            return $array;
        }
        $keys = implode('', array_keys($array));
        if(is_numeric($keys)){
            $array = array_values($array);
        }
        $array = array_map('reform_keys', $array);
        return $array;
    }
}

if (!function_exists('func_encrypt')) {
    /**
     * md5加密
     *
     * @param string $str 字符串
     * @return array
     */
    function func_encrypt($pwd, $is_admin = false, $type = 'bcrypt', $auth_code = '')
    {
        $pwd = trim($pwd);
        empty($auth_code) && $auth_code = get_auth_code($type);
        if ('bcrypt' == $type) {
            $encry_pwd = crypt($pwd, $auth_code);
        } else {
            $encry_pwd = md5($auth_code . $pwd);
        }

        return $encry_pwd;
    }
}

if (!function_exists('pwd_encry_type')) 
{
    /**
     * 获取密码加密方式
     * @param  string $encry_pwd 
     * @return [type]            [description]
     */
    function pwd_encry_type($encry_pwd = '') {
        $entry = 'md5';
        if (32 != strlen($encry_pwd)) {
            if (defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1) {
                $entry = 'bcrypt';
            }
        }

        return $entry;
    }
}

if (!function_exists('get_auth_code')) {
    /**
     * 密码加密盐值
     */
    function get_auth_code($type = 'bcrypt')
    {
        static $auth_code = null;
        if (null === $auth_code) {
            if ($type == 'bcrypt') { // crypt加密方式
                $lang = get_current_lang(true);
                $auth_code = \think\Db::name('config')->where(['name'=>'system_crypt_auth_code','inc_type'=>'system','lang'=>$lang])->value('value');
                if (empty($auth_code)) {
                    $rand_str = md5(uniqid(rand(), true));
                    $rand_str = substr($rand_str, 0, 23);
                    $auth_code = '$2y$11$'.$rand_str;  //30位盐
                    /*多语言*/
                    if (is_language()) {
                        $langRow = \think\Db::name('language')->order('id asc')->select();
                        foreach ($langRow as $key => $val) {
                            tpCache('system', ['system_crypt_auth_code' => $auth_code], $val['mark']);
                        }
                    } else { // 单语言
                        tpCache('system', ['system_crypt_auth_code' => $auth_code]);
                    }
                    /*--end*/
                }
            } else { // md5加密方式
                $lang = get_current_lang(true);
                $auth_code = \think\Db::name('config')->where(['name'=>'system_auth_code','inc_type'=>'system','lang'=>$lang])->value('value');
                if (empty($auth_code)) {
                    $auth_code = \think\Config::get('AUTH_CODE');
                    /*多语言*/
                    if (is_language()) {
                        $langRow = \think\Db::name('language')->order('id asc')->select();
                        foreach ($langRow as $key => $val) {
                            tpCache('system', ['system_auth_code' => $auth_code], $val['mark']);
                        }
                    } else { // 单语言
                        tpCache('system', ['system_auth_code' => $auth_code]);
                    }
                    /*--end*/
                }
            }
        }

        return empty($auth_code) ? '' : $auth_code;
    }
}

if (!function_exists('get_arr_column')) {
    /**
     * 获取数组中的某一列
     *
     * @param array $arr 数组
     * @param string $key_name 列名
     * @return array  返回那一列的数组
     */
    function get_arr_column($arr, $key_name)
    {
        if (function_exists('array_column')) {
            return array_column($arr, $key_name);
        }

        $arr2 = array();
        foreach ($arr as $key => $val) {
            $arr2[] = $val[$key_name];
        }
        return $arr2;
    }
}

if (!function_exists('parse_url_param')) {
    /**
     * 获取url 中的各个参数  类似于 pay_code=alipay&bank_code=ICBC-DEBIT
     * @param string $url
     * @return type
     */
    function parse_url_param($url = '')
    {
        $url       = rtrim($url, '/');
        $data      = array();
        $str       = explode('?', $url);
        $str       = end($str);
        $parameter = explode('&', $str);
        foreach ($parameter as $val) {
            if (!empty($val)) {
                $tmp           = explode('=', $val);
                $data[$tmp[0]] = $tmp[1];
            }
        }
        return $data;
    }
}

if (!function_exists('getClientIP')) {
    /**
     * 客户端IP(新)
     */
    function getClientIP()
    {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
    }
}

if (!function_exists('clientIP')) {
    /**
     * 客户端IP(旧，目前存在未知异常，获取的IP有不准的情况)
     */
    function clientIP()
    {
        $ip = request()->ip();
        if (request()->isValidIP($ip)) {
            return $ip;
        }
        else {
            return '';
        }
    }
}

if (!function_exists('serverIP')) {
    /**
     * 服务器端IP
     */
    function serverIP()
    {
        // 会因为解析问题导致后台卡
        if (!empty($_SERVER['SERVER_ADDR']) && !preg_match('/127\.0\.\d{1,3}\.\d{1,3}/i', $_SERVER['SERVER_ADDR']) && !preg_match('/192\.168\.\d{1,3}\.\d{1,3}/i', $_SERVER['SERVER_ADDR'])) {
            $serviceIp = $_SERVER['SERVER_ADDR'];
        } else {
            $serviceIp = @gethostbyname($_SERVER["SERVER_NAME"]);
        }
        return $serviceIp;
    }
}

if (!function_exists('recurse_copy')) {
    /**
     * 自定义函数递归的复制带有多级子目录的目录
     * 递归复制文件夹
     *
     * @param type $src 原目录
     * @param type $dst 复制到的目录
     */
    //参数说明：            
    //自定义函数递归的复制带有多级子目录的目录
    function recurse_copy($src, $dst)
    {
        $now = getTime();
        $dir = opendir($src);
        @mkdir($dst);
        if ('Weapp' == CONTROLLER_NAME && stristr(ACTION_NAME, 'install') && !is_writeable(ROOT_PATH.'weapp')) {
            return '请检查以下网站目录权限是否可写<br/>（application|template|weapp）';
        }
        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    recurse_copy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    if (file_exists($dst . DIRECTORY_SEPARATOR . $file)) {
                        if (!is_writeable($dst . DIRECTORY_SEPARATOR . $file)) {
                            // exit($dst . DIRECTORY_SEPARATOR . $file . '不可写');
                            return '网站目录没有写入权限，请调整权限';
                        }
                        // @unlink($dst . DIRECTORY_SEPARATOR . $file);
                    }
                    // if (file_exists($dst . DIRECTORY_SEPARATOR . $file)) {
                    //     @unlink($dst . DIRECTORY_SEPARATOR . $file);
                    // }
                    $copyrt = @copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                    if (!$copyrt) {
                        // echo 'copy ' . $dst . DIRECTORY_SEPARATOR . $file . ' failed';
                        return '网站目录没有写入权限，请调整权限';
                    }
                }
            }
        }
        closedir($dir);

        return true;
    }
}

if (!function_exists('delFile')) {
    /**
     * 递归删除文件夹
     *
     * @param string $path 目录路径
     * @param boolean $delDir 是否删除空目录
     * @param array $ignore_files 忽略删除的文件列表 $ignore_files = ['.htaccess'];
     * @return boolean
     */
    function delFile($path, $delDir = false, $ignore_files = [])
    {
        if (preg_match('/^(.+)\/$/i', $path)) {
            $path = rtrim($path, '/');
        }

        if (!is_dir($path)) {return false;}
        
        $handle = @opendir($path);
        if ($handle) {
            while (false !== ($item = readdir($handle))) {
                if (!in_array($item, ['.','..'])) {
                    if (is_dir("$path/$item")) {
                        delFile("$path/$item", $delDir);
                    } else {
                        if (!in_array($item, $ignore_files)) {
                            @unlink("$path/$item");
                        }
                    }
                }
            }
            closedir($handle);
            if ($delDir) {
                return @rmdir($path);
            }
        } else {
            if (file_exists($path)) {
                $is_del = true;
                foreach ($ignore_files as $key => $val) {
                    $path_tmp = str_replace('\\', '/', $path);
                    $val_arr = explode('/', $path_tmp);
                    $filename = end($val_arr);
                    if (in_array($filename, $ignore_files)) {
                        $is_del = false;
                    }
                }
                if ($is_del) {
                    return @unlink($path);
                }
            }
            return false;
        }
    }
}

if (!function_exists('getDirFile')) {
    /**
     * 递归读取文件夹文件
     *
     * @param string $directory 目录路径
     * @param string $dir_name 显示的目录前缀路径
     * @param array $arr_file 是否删除空目录
     * @return boolean
     */
    function getDirFile($directory, $dir_name = '', &$arr_file = array(), &$total = 0, $ignore_dirs = [])
    {
        if (!file_exists($directory)) {
            return false;
        }

        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if (!in_array($file, ['.','..']) && is_dir("$directory/$file")) {
                if ($dir_name) {
                    $dir_name_tmp = "$dir_name/$file";
                } else {
                    $dir_name_tmp = $file;
                }
                if (!in_array($dir_name_tmp, $ignore_dirs)) {
                    getDirFile("$directory/$file", $dir_name_tmp, $arr_file, $total, $ignore_dirs);
                }

            } else if (!in_array($file, ['.','..'])) {
                $total +=1;
                if ($dir_name) {
                    $file_tmp = "$dir_name/$file";
                } else {
                    $file_tmp = "$file";
                }
                $arr_file[] = $file_tmp;
            }
        }
        $mydir->close();

        return $arr_file;
    }
}

if (!function_exists('ey_scandir')) {
    /**
     * 部分空间为了安全起见，禁用scandir函数
     *
     * @param string $dir 路径
     * @return array
     */
    function ey_scandir($dir, $type = 'all')
    {
        if (function_exists('scandir')) {
            $files = scandir($dir);
        } else {
            $files = [];
            $mydir = dir($dir);
            while ($file = $mydir->read()) {
                $files[] = "$file";
            }
            $mydir->close();
        }
        $arr_file = [];
        foreach ($files as $key => $val) {
            if (($val != ".") AND ($val != "..")) {
                if ('all' == $type) {
                    $arr_file[] = "$val";
                } else if ('file' == $type && is_file($val)) {
                    $arr_file[] = "$val";
                } else if ('dir' == $type && is_dir($val)) {
                    $arr_file[] = "$val";
                }
            }
        }

        return $arr_file;
    }
}

if (!function_exists('group_same_key')) {
    /**
     * 将二维数组以元素的某个值作为键，并归类数组
     *
     * array( array('name'=>'aa','type'=>'pay'), array('name'=>'cc','type'=>'pay') )
     * array('pay'=>array( array('name'=>'aa','type'=>'pay') , array('name'=>'cc','type'=>'pay') ))
     * @param $arr 数组
     * @param $key 分组值的key
     * @return array
     */
    function group_same_key($arr, $key)
    {
        $new_arr = array();
        foreach ($arr as $k => $v) {
            $new_arr[$v[$key]][] = $v;
        }
        return $new_arr;
    }
}

if (!function_exists('get_rand_str')) {
    /**
     * 获取随机字符串
     * @param int $randLength 长度
     * @param int $addtime 是否加入当前时间戳
     * @param int $includenumber 是否包含数字
     * @return string
     */
    function get_rand_str($randLength = 6, $addtime = 1, $includenumber = 0)
    {
        if (1 == $includenumber) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
        } else if (2 == $includenumber) {
            $chars = '123456789';
        } else if (3 == $includenumber) {
            $chars = 'ABCDEFGHJKLMNPQEST123456789';
        } else {
            $chars = 'abcdefghijklmnopqrstuvwxyz';
        }
        $len     = strlen($chars);
        $randStr = '';
        for ($i = 0; $i < $randLength; $i++) {
            $randStr .= $chars[rand(0, $len - 1)];
        }
        $tokenvalue = $randStr;
        if ($addtime) {
            $tokenvalue = $randStr . getTime();
        }
        return $tokenvalue;
    }
}

if (!function_exists('httpRequest')) {
    /**
     * CURL请求
     *
     * @param $url 请求url地址
     * @param $method 请求方法 get post
     * @param null $postfields post数据数组
     * @param array $headers 请求header信息
     * @param bool|false $debug 调试开启 默认false
     * @return mixed
     */
    function httpRequest($url, $method = "GET", $postfields = null, $headers = array(), $timeout = 30, $debug = false)
    {
        $method = strtoupper($method);
        $ci     = curl_init();
        /* Curl settings */
        // curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); // 使用哪个版本
        curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
        // curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_TIMEOUT, $timeout); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case "POST":
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    if (is_string($postfields) && preg_match('/^([\w\-]+=[\w\-]+(&[\w\-]+=[\w\-]+)*)$/', $postfields)) {
                        parse_str($postfields, $output);
                        $postfields = $output;
                    }
                    if (is_array($postfields)) {
                        if (isset($postfields['domain']) && false !== filter_var($postfields['domain'], FILTER_VALIDATE_IP)) {
                            $web_basehost = request()->host(true);
                            if (false !== filter_var($web_basehost, FILTER_VALIDATE_IP) || file_exists('./data/conf/multidomain.txt') || preg_match('/\.(my3w\.com)$/i', $web_basehost)) {
                                $web_basehost = tpCache('web.web_basehost');
                            }
                            $web_basehost = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
                            $postfields['domain'] = $web_basehost;
                        }
                        $tmpdatastr = http_build_query($postfields);
                    } else {
                        $tmpdatastr = $postfields;
                    }
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            default:
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
                break;
        }

        if (stristr($url, 'http://plugins.eyoucms.com')) {
            if (stristr('https://', request()->scheme().':')) {
                $url = str_replace('http://', 'https://', $url);
            }
        }
        $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
        curl_setopt($ci, CURLOPT_URL, $url);
        if ($ssl) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
            // curl_setopt($ci, CURLOPT_SSLVERSION, 4);   //因为之前的POODLE 病毒爆发，许多网站禁用了sslv3（nginx默认是禁用的，ssl_protocols 默认值为TLSv1 TLSv1.1 TLSv1.2;），最新使用sslv4
        }
        //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
        }
        curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);
        /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
        $response    = curl_exec($ci);
        $requestinfo = curl_getinfo($ci);
        $http_code   = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        if ($debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====info===== \r\n";
            print_r($requestinfo);
            echo "=====response=====\r\n";
            print_r($response);
        }
        curl_close($ci);
        return $response;
        //return array($http_code, $response,$requestinfo);
    }
}

if (!function_exists('httpRequest2')) {
    /**
     * CURL请求
     *
     * @param $url 请求url地址
     * @param $method 请求方法 get post
     * @param null $postfields post数据数组
     * @param array $headers 请求header信息
     * @param bool|false $debug 调试开启 默认false
     * @return mixed
     */
    function httpRequest2($url, $method = "GET", $postfields = null, $headers = array(), $timeout = 30, $debug = false)
    {
        $method = strtoupper($method);
        $ci     = curl_init();
        /* Curl settings */
        // curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); // 使用哪个版本
        curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
        // curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_TIMEOUT, $timeout); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case "POST":
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    if (is_string($postfields) && preg_match('/^([\w\-]+=[\w\-]+(&[\w\-]+=[\w\-]+)*)$/', $postfields)) {
                        parse_str($postfields, $output);
                        $postfields = $output;
                    }
                    if (is_array($postfields)) {
                        $web_basehost = request()->host(true);
                        if (false !== filter_var($web_basehost, FILTER_VALIDATE_IP) || file_exists('./data/conf/multidomain.txt') || preg_match('/\.(my3w\.com)$/i', $web_basehost)) {
                            $web_basehost = tpCache('web.web_basehost');
                        }
                        $web_basehost = preg_replace('/^(http(s)?:)?(\/\/)?([^\/\:]*)(.*)$/i', '${4}', $web_basehost);
                        $postfields['domain'] = $web_basehost;
                        $postfields['ip'] = serverIP();
                        $tmpdatastr = http_build_query($postfields);
                    } else {
                        $tmpdatastr = $postfields;
                    }
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            default:
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
                break;
        }

        if (stristr($url, 'http://plugins.eyoucms.com')) {
            if (stristr('https://', request()->scheme().':')) {
                $url = str_replace('http://', 'https://', $url);
            }
        }
        $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
        curl_setopt($ci, CURLOPT_URL, $url);
        if ($ssl) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
            // curl_setopt($ci, CURLOPT_SSLVERSION, 4);   //因为之前的POODLE 病毒爆发，许多网站禁用了sslv3（nginx默认是禁用的，ssl_protocols 默认值为TLSv1 TLSv1.1 TLSv1.2;），最新使用sslv4
        }
        //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
        }
        curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);
        /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
        $response    = curl_exec($ci);
        // $http_code   = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        if ($debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====info===== \r\n";
            $requestinfo = curl_getinfo($ci);
            print_r($requestinfo);
            echo "=====response=====\r\n";
            print_r($response);
            echo "=====error=====\r\n";
            print_r(curl_error($ci));
        }
        curl_close($ci);
        return $response;
        //return array($http_code, $response,$requestinfo);
    }
}

if (!function_exists('check_mobile')) {
    /**
     * 检查手机号码格式
     *
     * @param $mobile 手机号码
     */
    function check_mobile($mobile)
    {
        if (preg_match('/1\d{10}$/', $mobile))
            return true;
        return false;
    }
}

if (!function_exists('check_telephone')) {
    /**
     * 检查固定电话
     *
     * @param $mobile
     * @return bool
     */
    function check_telephone($mobile)
    {
        if (preg_match('/^([0-9]{3,4}-)?[0-9]{7,8}$/', $mobile))
            return true;
        return false;
    }
}

if (!function_exists('check_email')) {
    /**
     * 检查邮箱地址格式
     *
     * @param $email 邮箱地址
     */
    function check_email($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL))
            return true;
        return false;
    }
}

if (!function_exists('check_domain'))
{
    /**
     *  校验域名格式
     *
     * @access    public
     * @param     string  $operation  操作
     * @return    string
     */
    function check_domain($domain = ''){
        $str="/^(?:[-A-za-z0-9_\x{4e00}-\x{9fa5}]+\.)+[-A-za-z0-9]{2,}$/u";
        if (!preg_match($str,$domain)){
            return false;
        }else{
            return true;
        }
    }
}

if (!function_exists('getSubstr')) {
    /**
     * 实现中文字串截取无乱码的方法
     *
     * @param string $string 字符串
     * @param intval $start 起始位置
     * @param intval $length 截取长度
     * @return string
     */
    function getSubstr($string = '', $start = 0, $length = NULL)
    {
        if (mb_strlen($string, 'utf-8') > $length) {
            $str = msubstr($string, $start, $length, true, 'utf-8');
            return $str;
        } else {
            return $string;
        }
    }
}

if (!function_exists('msubstr')) {
    /**
     * 字符串截取，支持中文和其他编码
     *
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string
     */
    function msubstr($str = '', $start = 0, $length = NULL, $suffix = false, $charset = "utf-8")
    {
        if (function_exists("mb_substr")) {
            $slice = mb_substr($str, $start, $length, $charset);
        }
        elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
            if (false === $slice) {
                $slice = '';
            }
        }
        else {
            $re['utf-8']  = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }

        $str_len   = strlen($str); // 原字符串长度
        $slice_len = strlen($slice); // 截取字符串的长度
        if ($slice_len < $str_len) {
            $slice = $suffix ? $slice . '...' : $slice;
        }

        return $slice;
    }
}

if (!function_exists('html_msubstr')) {
    /**
     * 截取内容清除html之后的字符串长度，支持中文和其他编码
     *
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string
     */
    function html_msubstr($str = '', $start = 0, $length = NULL, $suffix = false, $charset = "utf-8")
    {
        if (is_language() && 'cn' != get_current_lang()) {
            $length = $length * 2;
        }
        $str = eyou_htmlspecialchars_decode($str);
        $str = checkStrHtml($str);
        return msubstr($str, $start, $length, $suffix, $charset);
    }
}

if (!function_exists('text_msubstr')) {
    /**
     * 针对多语言截取，其他语言的截取是中文语言的2倍长度
     *
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string
     */
    function text_msubstr($str = '', $start = 0, $length = NULL, $suffix = false, $charset = "utf-8")
    {
        if (is_language() && 'cn' != get_current_lang()) {
            $length = $length * 2;
        }
        return msubstr($str, $start, $length, $suffix, $charset);
    }
}

if (!function_exists('eyou_htmlspecialchars_decode')) {
    /**
     * 自定义只针对htmlspecialchars编码过的字符串进行解码
     *
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $suffix 截断显示字符
     * @param string $charset 编码格式
     * @return string
     */
    function eyou_htmlspecialchars_decode($str = '')
    {
        if (is_string($str) && stripos($str, '&lt;') !== false && stripos($str, '&gt;') !== false) {
            $str = htmlspecialchars_decode($str);
        }
        return $str;
    }
}

if (!function_exists('isMobile')) {
    /**
     * 判断当前访问的用户是  PC端  还是 手机端  返回true 为手机端  false 为PC 端
     * 是否移动端访问
     *
     * @return boolean
     */
    function isMobile()
    {
        static $is_mobile = null;
        null === $is_mobile && $is_mobile = request()->isMobile();
        return $is_mobile;

        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
            return true;

        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset ($_SERVER['HTTP_VIA'])) {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
                return true;
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('isWeixin')) {
    /**
     * 是否微信端访问
     *
     * @return boolean
     */
    function isWeixin()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isWeixinApplets')) {
    /**
     * 是否微信端小程序访问
     *
     * @return boolean
     */
    function isWeixinApplets()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'miniProgram') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isQq')) {
    /**
     * 是否QQ端访问
     *
     * @return boolean
     */
    function isQq()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'QQ') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isAlipay')) {
    /**
     * 是否支付端访问
     *
     * @return boolean
     */
    function isAlipay()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('getFirstCharter')) {
    /**
     * php获取中文字符拼音首字母
     *
     * @param string $str 中文
     * @return boolean
     */
    function getFirstCharter($str)
    {
        if (empty($str)) {
            return '';
        }
        $fchar=ord($str[0]);
        if($fchar>=ord('A')&&$fchar<=ord('z')) return strtoupper($str[0]);
        $s1  = @iconv('UTF-8', 'gb2312', $str);
        $s2  = @iconv('gb2312', 'UTF-8', $s1);
        $s=$s2==$str?$s1:$str;
        $asc=ord($s[0])*256+ord($s[1])-65536;
        if ($asc >= -20319 && $asc <= -20284) return 'A';
        if ($asc >= -20283 && $asc <= -19776) return 'B';
        if ($asc >= -19775 && $asc <= -19219) return 'C';
        if ($asc >= -19218 && $asc <= -18711) return 'D';
        if ($asc >= -18710 && $asc <= -18527) return 'E';
        if ($asc >= -18526 && $asc <= -18240) return 'F';
        if ($asc >= -18239 && $asc <= -17923) return 'G';
        if ($asc >= -17922 && $asc <= -17418) return 'H';
        if ($asc >= -17417 && $asc <= -16475) return 'J';
        if ($asc >= -16474 && $asc <= -16213) return 'K';
        if ($asc >= -16212 && $asc <= -15641) return 'L';
        if ($asc >= -15640 && $asc <= -15166) return 'M';
        if ($asc >= -15165 && $asc <= -14923) return 'N';
        if ($asc >= -14922 && $asc <= -14915) return 'O';
        if ($asc >= -14914 && $asc <= -14631) return 'P';
        if ($asc >= -14630 && $asc <= -14150) return 'Q';
        if ($asc >= -14149 && $asc <= -14091) return 'R';
        if ($asc >= -14090 && $asc <= -13319) return 'S';
        if ($asc >= -13318 && $asc <= -12839) return 'T';
        if ($asc >= -12838 && $asc <= -12557) return 'W';
        if ($asc >= -12556 && $asc <= -11848) return 'X';
        if ($asc >= -11847 && $asc <= -11056) return 'Y';
        if ($asc >= -11055 && $asc <= -10247) return 'Z';
        return 'Z';
    }
}

if (!function_exists('pinyin_long')) {
    /**
     * 获取整条字符串汉字拼音首字母
     *
     * @param $zh
     * @return string
     */
    function pinyin_long($zh)
    {
        $ret = "";
        $s1  = iconv("UTF-8", "gb2312", $zh);
        $s2  = iconv("gb2312", "UTF-8", $s1);
        if ($s2 == $zh) {
            $zh = $s1;
        }
        for ($i = 0; $i < strlen($zh); $i++) {
            $s1 = substr($zh, $i, 1);
            $p  = ord($s1);
            if ($p > 160) {
                $s2  = substr($zh, $i++, 2);
                $ret .= getFirstCharter($s2);
            } else {
                $ret .= $s1;
            }
        }
        return $ret;
    }
}

if (!function_exists('respose')) {
    /**
     * 参数 is_jsonp 为true，表示跨域ajax请求的返回值
     *
     * @param string $res 数组
     * @param bool $is_jsonp 是否跨域
     * @return string
     */
    function respose($res, $is_jsonp = false)
    {
        if (true === $is_jsonp) {
            exit(input('callback') . "(" . json_encode($res) . ")");
        } else {
            exit(json_encode($res));
        }
    }
}

if (!function_exists('urlsafe_b64encode')) {
    function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }
}

if (!function_exists('getTime')) {
    /**
     * 获取当前时间戳
     *
     */
    function getTime()
    {
        return time();
    }
}

if (!function_exists('getMsectime')) {
    /**
     * 获取当前时间戳（精确到毫秒）
     *
     */
    function getMsectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
}

if (!function_exists('trim_space')) {
    /**
     * 过滤前后空格等多种字符
     *
     * @param string $str 字符串
     * @param array $arr 特殊字符的数组集合
     * @return string
     */
    function trim_space($str, $arr = array())
    {
        if (empty($arr)) {
            $arr = array(' ', '　');
        }
        foreach ($arr as $key => $val) {
            $str = preg_replace('/(^' . $val . ')|(' . $val . '$)/', '', $str);
        }

        return $str;
    }
}

if (!function_exists('func_preg_replace')) {
    /**
     * 替换指定的符号
     *
     * @param array $arr 特殊字符的数组集合
     * @param string $replacement 符号
     * @param string $str 字符串
     * @return string
     */
    function func_preg_replace($arr = array(), $replacement = ',', $str = '')
    {
        if (empty($arr)) {
            $arr = array('，');
        }
        foreach ($arr as $key => $val) {
            if (is_array($replacement)) {
                $replacevalue = isset($replacement[$key]) ? $replacement[$key] : current($replacement);
            } else {
                $replacevalue = $replacement;
            }
            $val = str_replace('/', '\/', $val);
            $str = preg_replace('/(' . $val . ')/', $replacevalue, $str);
        }

        return $str;
    }
}

if (!function_exists('db_create_in')) {
    /**
     * 创建像这样的查询: "IN('a','b')";
     *
     * @param    mixed $item_list 列表数组或字符串,如果为字符串时,字符串只接受数字串
     * @param    string $field_name 字段名称
     * @return   string
     */
    function db_create_in($item_list, $field_name = '')
    {
        if (empty($item_list)) {
            return $field_name . " IN ('') ";
        } else {
            if (!is_array($item_list)) {
                $item_list = explode(',', $item_list);
                foreach ($item_list as $k => $v) {
                    $item_list[$k] = intval($v);
                }
            }

            $item_list     = array_unique($item_list);
            $item_list_tmp = '';
            foreach ($item_list AS $item) {
                if ($item !== '') {
                    $item_list_tmp .= $item_list_tmp ? ",'$item'" : "'$item'";
                }
            }
            if (empty($item_list_tmp)) {
                return $field_name . " IN ('') ";
            } else {
                return $field_name . ' IN (' . $item_list_tmp . ') ';
            }
        }
    }
}

if (!function_exists('static_version')) {
    /**
     * 给静态文件追加版本号，实时刷新浏览器缓存
     *
     * @param    string $file 为远程文件
     * @return   string
     */
    function static_version($file)
    {
        static $request = null;
        null == $request && $request = \think\Request::instance();
        // ---判断本地文件是否存在，否则返回false，以免@get_headers方法导致崩溃
        if (is_http_url($file)) { // 判断http路径
            if (preg_match('/^http(s?):\/\/' . $request->host(true) . '/i', $file)) { // 判断当前域名的本地服务器文件(这仅用于单台服务器，多台稍作修改便可)
                // $pattern = '/^http(s?):\/\/([^.]+)\.([^.]+)\.([^\/]+)\/(.*)$/';
                $pattern = '/^http(s?):\/\/([^\/]+)(.*)$/';
                preg_match_all($pattern, $file, $matches);//正则表达式
                if (!empty($matches)) {
                    $filename = $matches[count($matches) - 1][0];
                    if (!file_exists(realpath(ltrim($filename, '/')))) {
                        return false;
                    }
                    $file = $request->domain() . $filename;
                }
            }

            $update_time = getTime();

        } else {
            if (!file_exists(realpath(ltrim($file, '/')))) {
                return false;
            }

            try{
                if ($request->controller() == 'Buildhtml') {
                    $update_time = getTime();
                } else {
                    $fileStat = stat(ROOT_PATH . ltrim($file, '/'));
                    $update_time = !empty($fileStat['mtime']) ? $fileStat['mtime'] : getTime();
                }
            } catch (\Exception $e) {
                $update_time = getTime();
            }
        }
        // -------------end---------------

        $parseStr        = '';
        $file = get_absolute_url(ROOT_DIR.$file); // 支持子目录
        $update_time_str = !empty($update_time) ? '?t='.$update_time : '';
        $type            = strtolower(substr(strrchr($file, '.'), 1));
        switch ($type) {
            case 'js':
                $parseStr .= '<script language="javascript" type="text/javascript" src="' . $file . $update_time_str . '"></script>';
                break;
            case 'css':
                $parseStr .= '<link href="' . $file . $update_time_str . '" rel="stylesheet" media="screen" type="text/css" />';
                break;
            case 'ico':
                $parseStr .= '<link rel="shortcut icon" href="' . $file . $update_time_str . '" />';
                break;
        }

        return $parseStr;
    }
}

if (!function_exists('tp_mkdir')) {
    /**
     * 递归创建目录
     *
     * @param string $path 目录路径，不带反斜杠
     * @param intval $purview 目录权限码
     * @return boolean
     */
    function tp_mkdir($path, $purview = 0777)
    {
        if (!is_dir($path)) {
            tp_mkdir(dirname($path), $purview);
            if (!mkdir($path, $purview)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * 格式化字节大小
     *
     * @param  number $size 字节数
     * @param  string $delimiter 数字和单位分隔符
     * @return string            格式化后的带单位的大小
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
        return round($size, 2) . $delimiter . $units[$i];
    }
}

if (!function_exists('unformat_bytes')) {
    /**
     * 反格式化字节大小
     *
     * @param  number $size 格式化带单位的大小
     */
    function unformat_bytes($formatSize)
    {
        $size = 0;
        if (preg_match('/^\d+P/i', $formatSize)) {
            $size = intval($formatSize) * 1024 * 1024 * 1024 * 1024 * 1024;
        } else if (preg_match('/^\d+T/i', $formatSize)) {
            $size = intval($formatSize) * 1024 * 1024 * 1024 * 1024;
        } else if (preg_match('/^\d+G/i', $formatSize)) {
            $size = intval($formatSize) * 1024 * 1024 * 1024;
        } else if (preg_match('/^\d+M/i', $formatSize)) {
            $size = intval($formatSize) * 1024 * 1024;
        } else if (preg_match('/^\d+K/i', $formatSize)) {
            $size = intval($formatSize) * 1024;
        } else if (preg_match('/^\d+B/i', $formatSize)) {
            $size = intval($formatSize);
        }

        $size = strval($size);

        return $size;
    }
}

if (!function_exists('is_http_url')) {
    /**
     * 判断url是否完整的链接
     *
     * @param  string $url 网址
     * @return boolean
     */
    function is_http_url($url)
    {
        // preg_match("/^(http:|https:|ftp:|svn:)?(\/\/).*$/", $url, $match);
        preg_match("/^((\w)*:)?(\/\/).*$/", $url, $match);
        if (empty($match)) {
            return false;
        } else {
            return true;
        }
    }
}

if (!function_exists('get_html_first_imgurl')) {
    /**
     * 获取文章内容html中第一张图片地址
     *
     * @param  string $html html代码
     * @return boolean
     */
    function get_html_first_imgurl($html)
    {
        $pattern = '~<img [^>]*[\s]?[\/]?[\s]?>~';
        preg_match_all($pattern, $html, $matches);//正则表达式把图片的整个都获取出来了
        $img_arr       = $matches[0];//图片
        $first_img_url = "";
        if (!empty($img_arr)) {
            $first_img = $img_arr[0];
            $p         = "#src=('|\")(.*)('|\")#isU";//正则表达式
            preg_match_all($p, $first_img, $img_val);
            if (isset($img_val[2][0])) {
                $first_img_url = $img_val[2][0]; //获取第一张图片地址
            }
        }

        return $first_img_url;
    }
}

if (!function_exists('checkStrHtml')) {
    /**
     * 过滤Html标签
     *
     * @param     string $string 内容
     * @return    string
     */
    function checkStrHtml($string, $replaces = [])
    {
        $string = str_replace("&nbsp;", " ", $string);
        $string = trim_space($string);
        $string = trim($string);

        if (is_numeric($string)) return $string;
        if (!isset($string) or empty($string)) return '';

        $string = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', '', $string);
        $string = ($string);

        $string = strip_tags($string, ""); //清除HTML如<br />等代码
        if (!empty($replaces)) {
            $string = str_replace($replaces, "", $string);
        }
        $string = str_replace("\n", "", $string);//去掉空格和换行
        $string = str_replace("\t", "", $string); //去掉制表符号
        $string = str_replace(PHP_EOL, "", $string); //去掉回车换行符号
        $string = str_replace("\r", "", $string); //去掉回车
        $string = str_replace("'", "‘", $string); //替换单引号
        $string = str_replace("&amp;", "&", $string);
        $string = str_replace("=★", "", $string);
        $string = str_replace("★=", "", $string);
        $string = str_replace("★", "", $string);
        $string = str_replace("☆", "", $string);
        $string = str_replace("√", "", $string);
        $string = str_replace("±", "", $string);
        $string = str_replace("‖", "", $string);
        $string = str_replace("×", "", $string);
        $string = str_replace("∏", "", $string);
        $string = str_replace("∷", "", $string);
        $string = str_replace("⊥", "", $string);
        $string = str_replace("∠", "", $string);
        $string = str_replace("⊙", "", $string);
        $string = str_replace("≈", "", $string);
        $string = str_replace("≤", "", $string);
        $string = str_replace("≥", "", $string);
        $string = str_replace("∞", "", $string);
        $string = str_replace("∵", "", $string);
        $string = str_replace("♂", "", $string);
        $string = str_replace("♀", "", $string);
        $string = str_replace("°", "", $string);
        $string = str_replace("¤", "", $string);
        $string = str_replace("◎", "", $string);
        $string = str_replace("◇", "", $string);
        $string = str_replace("◆", "", $string);
        $string = str_replace("→", "", $string);
        $string = str_replace("←", "", $string);
        $string = str_replace("↑", "", $string);
        $string = str_replace("↓", "", $string);
        $string = str_replace("▲", "", $string);
        $string = str_replace("▼", "", $string);

        // --过滤微信表情
        $string = preg_replace_callback('/[\xf0-\xf7].{3}/', function ($r) {
            return '';
        }, $string);

        return $string;
    }
}

if (!function_exists('saveRemote')) {
    /**
     * 抓取远程图片
     *
     * @param     string $fieldName 远程图片url
     * @param     string $savePath 存储在public/upload的子目录
     * @return    string
     */
    function saveRemote($fieldName, $savePath = 'temp/', $is_water = 1)
    {
        $image_ext = config('global.image_ext');
        $allowFiles = explode(',', '.'.str_replace(',', ',.', $image_ext));
        $web_basehost = tpCache('web.web_basehost');
        $parse_arr    = parse_url($web_basehost);
        $host  = request()->host(true);

        $imgUrl = htmlspecialchars($fieldName);
        $imgUrl = str_replace("&amp;", "&", $imgUrl);
        $imgUrl = preg_replace('/#/', '', $imgUrl);

        // 插件列表
        static $weappList = null;
        if (null === $weappList) {
            $weappList = \think\Db::name('weapp')->where([
                    'status'    => 1,
                ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                ->getAllWithIndex('code');
        }
        $storageDomain = ''; // 第三方存储的域名
        if (!empty($weappList['Qiniuyun']['data'])) {
            $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
            if (!empty($qnyData['domain'])) {
                $storageDomain = $qnyData['domain'];
            }
        } else if (!empty($weappList['AliyunOss']['data'])) {
            $ossData = json_decode($weappList['AliyunOss']['data'], true);
            if (!empty($ossData['domain'])) {
                $storageDomain = $ossData['domain'];
            }
        } else if (!empty($weappList['Cos']['data'])) {
            $cosData = json_decode($weappList['Cos']['data'], true);
            if (!empty($cosData['domain'])) {
                $storageDomain = $cosData['domain'];
            }
        } else if (!empty($weappList['AwsOss']['data'])) {
            $awsData = json_decode($weappList['AwsOss']['data'], true);
            if (!empty($awsData['domain'])) {
                $storageDomain = $awsData['domain'];
            }
        }
        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
            $data = array(
                'state' => '链接不是http链接',
            );
            return json_encode($data);
        }
        //获取请求头并检测死链
        $heads = @get_headers($imgUrl, 1);
        if (empty($heads) || !(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
            $data = array(
                'state' => '链接不可用',
            );
            return json_encode($data);
        }
        // 图片扩展名
        $fileType = substr($heads['Content-Type'], -4, 4);
        if (!preg_match("#\.(".str_replace(',', '|', $image_ext).")#i", $fileType)) {
            $filext = str_ireplace('image/', '', $heads['Content-Type']);
            if ($fileType == 'image/gif' || $filext == 'gif') {
                $fileType = ".gif";
            } else if ($fileType == 'image/png' || $filext == 'png') {
                $fileType = ".png";
            } else if ($fileType == 'image/x-icon' || $filext == 'x-icon') {
                $fileType = ".ico";
            } else if ($fileType == 'image/bmp' || $filext == 'bmp') {
                $fileType = ".bmp";
            }  else if ($fileType == 'image/webp' || $filext == 'webp') {
                $fileType = ".webp";
            } else if ($heads['Content-Type'] == 'image/svg+xml' || $filext == 'svg+xml') {
                $fileType = ".svg";
            } else {
                $fileType = '.jpg';
            }
        }
        $fileType = strtolower($fileType);
        //格式验证(扩展名验证和Content-Type验证)，链接contentType是否正确
        $is_weixin_img = false;
        if (preg_match("/^http(s?):\/\/(mmbiz.qpic.cn|thirdwx.qlogo.cn)\/(.*)/", $imgUrl) != 1) {
            if (!in_array($fileType, $allowFiles) || (isset($heads['Content-Type']) && !stristr($heads['Content-Type'], "image/"))) {
                $data = array(
                    'state' => '链接contentType不正确',
                );
                return json_encode($data);
            }
        } else {
            $is_weixin_img = true;
        }

        /*验证图片一句话木马*/
        if (false === check_illegal($imgUrl,true,trim($fileType, '.'))) {
            $data = array(
                'state' => '疑似木马图片！',
            );
            return json_encode($data);
        }
        /*--end*/
        // 本站图片 / 根网址图片 / 第三方存储插件的图片
        $preg_root_dir = str_replace('/', '\/', ROOT_DIR);
        if (preg_match("/\/\/({$host}{$preg_root_dir}|{$storageDomain}|{$parse_arr['host']}{$preg_root_dir})\//i", $imgUrl)) {
            $arr = explode('/', $imgUrl);
            $data = array(
                'state'    => 'SUCCESS',
                'url'      => $imgUrl,
                'title'    => end($arr),
                'original' => '',
                'type'     => strtolower(strrchr($imgUrl, '.')),
                'size'     => 0,
                'width' => 0,
                'height' => 0,
                'mime' => '',
            );
            return json_encode($data);
        }


        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $dirname          = './' . UPLOAD_PATH . 'allimg/' . date('Ymd') . '/';
        $file['oriName']  = $m ? $m[1] : "";
        $file['filesize'] = strlen($img);
        $file['ext']      = $fileType;
        $users_id         = 1;
        if (session('?users_id')) {
            $users_id = session('users_id');
        } else if (session('?admin_id')) {
            $users_id = session('admin_id');
        }
        $file['name']     = $users_id . '-' . dd2char(date("ymdHis") . mt_rand(100, 999)) . $file['ext'];
        $file['fullName'] = $dirname . $file['name'];
        $fullName         = $file['fullName'];

        //检查文件大小是否超出限制
        if ($file['filesize'] >= 10240000) {
            $data = array(
                'state' => '文件大小超出网站限制',
            );
            return json_encode($data);
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $data = array(
                'state' => '目录创建失败',
            );
            return json_encode($data);
        } else if (!is_writeable($dirname)) {
            $data = array(
                'state' => '目录没有写权限',
            );
            return json_encode($data);
        }


        //移动文件
        if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
            $data = array(
                'state' => '写入文件内容错误',
            );
            return json_encode($data);
        } else { //移动成功
            $imgurl = substr($file['fullName'], 1);
            $realfilepath = realpath('.'.$imgurl);

            // 检查文件的真实 MIME 类型
            if (!check_image_mime_type($realfilepath)) {
                @unlink($realfilepath);
                $data = array(
                    'state' => '疑似木马图片！',
                );
                return json_encode($data);
            }

            // 检查文件头部信息
            if (!check_image_header($realfilepath)) {
                @unlink($realfilepath);
                $data = array(
                    'state' => '疑似木马图片！',
                );
                return json_encode($data);
            }

            $imageInfo = @getimagesize('.'.$imgurl);
            $data = array(
                'state'    => 'SUCCESS',
                'url'      => ROOT_DIR.$imgurl,
                'title'    => $file['name'],
                'original' => $file['oriName'],
                'type'     => $file['ext'],
                'size'     => $file['filesize'],
                'width' => !empty($imageInfo[0]) ? intval($imageInfo[0]) : 0,
                'height' => !empty($imageInfo[1]) ? intval($imageInfo[1]) : 0,
                'mime' => !empty($imageInfo['mime']) ? $imageInfo['mime'] : '',
            );

            try {
                if (1 == $is_water) {
                    print_water($data['url']); // 添加水印
                }
                /*同步到第三方对象存储空间*/
                $bucket_data = SynImageObjectBucket($data['url'], $weappList);
                if (!empty($bucket_data['url']) && is_string($bucket_data['url'])) {
                    $data['url'] = $bucket_data['url'];
                }
                /*end*/
            } catch (\Exception $e) {}

            // 添加图片进数据库
            $addData = [
                'aid'         => 0,
                'type_id'     => 0,
                'image_url'   => $data['url'],
                'title'       => '',
                'intro'       => '',
                'width'       => $data['width'],
                'height'      => $data['height'],
                'filesize'    => $data['size'],
                'mime'        => $data['mime'],
                'users_id'    => (int)session('admin_info.syn_users_id'),
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            // \think\Db::name('uploads')->add($addData);
        }
        return json_encode($data);
    }
}

if (!function_exists('func_common')) {
    /**
     * 自定义上传
     *
     * @param     string $fileElementId 上传表单的ID
     * @param     string $path 存储在public/upload的子目录
     * @param     string $file_type 图片后缀名
     * @return    string
     */
    function func_common($fileElementId = 'uploadImage', $path = 'allimg', $file_type = "", $postFiles = [],$compressConf = [])
    {
        $lang = get_current_lang();

        if (is_dir('./weapp/Safe/')) {
            $safeModel = new \weapp\Safe\model\SafeModel;
            $rdata = $safeModel->is_pass_blockip();
            if (empty($rdata['code'])) {
                $data = $rdata['data'];
                $is_prohibit = false;
                if (!empty($data['prohibit_upload'])) {
                    $is_prohibit = true;
                }

                if (true === $is_prohibit) {
                    if ($lang == 'cn') {
                        $errmsg = '已禁止上传功能';
                    } else if ($lang == 'zh') {
                        $errmsg = '已禁止上傳功能';
                    } else {
                        $errmsg = 'The upload function has been disabled';
                    }
                    return ['errcode' => 1, 'errmsg' => $errmsg];
                }
            }
        }

        $file = !empty($postFiles) ? $postFiles : request()->file($fileElementId);
        if (empty($file)) {
            if ($lang == 'cn') {
                $errmsg = '请选择上传图片';
            } else if ($lang == 'zh') {
                $errmsg = '請選擇上傳圖片';
            } else {
                $errmsg = 'Please select upload picture';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg];
        }

        $validate = array();
        // 文件大小限制
        $validate['size'] = intval(tpCache('basic.file_size') * 1024 * 1024);
        /*文件扩展名限制*/
        $validate_ext = !empty($file_type) ? str_replace(',', '|', $file_type) : tpCache('basic.image_type');
        $validate_ext = str_replace('|', ',', $validate_ext);
        $validate['ext'] = explode(',', $validate_ext);
        /*--end*/

        //拓展名
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        if (!in_array($ext, $validate['ext'])) {
            if ($lang == 'cn') {
                $errmsg = '上传图片后缀名必须为';
            } else if ($lang == 'zh') {
                $errmsg = '上傳圖片后綴名必須為';
            } else {
                $errmsg = 'Upload image suffix must be ';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg . $validate_ext];
        }
        /*拓展名验证end*/

        /*上传文件验证*/
        if (!empty($validate)) {
            $is_validate = $file->check($validate);
            if ($is_validate === false) {
                return ['errcode' => 1, 'errmsg' => $file->getError()];
            }
        }
        /*--end*/

        /*验证图片一句话木马*/
        if (false === check_illegal($file->getInfo('tmp_name'), false, $ext)) {
            if ($lang == 'cn') {
                $errmsg = '疑似木马图片';
            } else if ($lang == 'zh') {
                $errmsg = '疑似木馬圖片';
            } else {
                $errmsg = 'Suspected Trojan images';
            }
            return ['errcode'=>1,'errmsg'=>$errmsg];
        }
        /*--end*/

        $fileName = $file->getInfo('name');
        // 提取文件名后缀
        $file_ext = pathinfo($fileName, PATHINFO_EXTENSION);
        // 提取出文件名，不包括扩展名
        $newfileName = preg_replace('/\.([^\.]+)$/', '', $fileName);
        // 过滤文件名.\/的特殊字符，防止利用上传漏洞
        $newfileName = preg_replace('#(\\\|\/|\.)#i', '', $newfileName);
        // 过滤后的新文件名
        $fileName = $newfileName . '.' . $file_ext;

        if (session('?users_id') && 'admin' != request()->module()) {
            $users_id = session('users_id');
            $savePath = UPLOAD_PATH.'user/'.session('users_id').'/';
        } else {
            $savePath = UPLOAD_PATH;
        }
        $savePath   .= $path . '/' . date('Ymd/');

        $return_url = "";
        $info = $file->rule(function ($file) {
            $users_id_tmp = 1;
            if (session('?users_id') && 'admin' != request()->module()) {
                $users_id_tmp = session('users_id');
            } else if (session('?admin_id')) {
                $users_id_tmp = session('admin_id');
            }
            return $users_id_tmp . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
        })->move($savePath);
        if ($info) {
            $return_url = '/' . $savePath . $info->getSaveName();

            // 重新制作一张图片，抹去任何可能有危害的数据
            // $image       = \think\Image::open('.'.$return_url);
            // $image->save('.'.$return_url, null, 100);
        }

        // 是否要压缩图片
        if (!empty($compressConf[0])) {
            $return_url = func_thumb_img($return_url, $compressConf[0], $compressConf[1]);
        }

        if ($return_url) {
            if ($lang == 'cn') {
                $errmsg = '上传成功';
            } else if ($lang == 'zh') {
                $errmsg = '上傳成功';
            } else {
                $errmsg = 'Upload succeeded';
            }
            return ['errcode' => 0, 'errmsg' => $errmsg, 'img_url' => $return_url];
        }
        else {
            if ($lang == 'cn') {
                $errmsg = '上传失败';
            } else if ($lang == 'zh') {
                $errmsg = '上傳失敗';
            } else {
                $errmsg = 'Upload failed';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg];
        }
    }
}

if (!function_exists('func_common_doc')) {
    /**
     * 自定义上传
     *
     * @param     string $fileElementId 上传表单的ID
     * @param     string $path 存储在public/upload的子目录
     * @param     string $file_type 文件后缀名
     * @return    string
     */
    function func_common_doc($fileElementId = 'uploadFile', $path = 'soft', $file_type = "", $postFiles = [])
    {
        $lang = get_current_lang();

        if (is_dir('./weapp/Safe/')) {
            $safeModel = new \weapp\Safe\model\SafeModel;
            $rdata = $safeModel->is_pass_blockip();
            if (empty($rdata['code'])) {
                $data = $rdata['data'];
                $is_prohibit = false;
                if (!empty($data['prohibit_upload'])) {
                    $is_prohibit = true;
                }

                if (true === $is_prohibit) {
                    if ($lang == 'cn') {
                        $errmsg = '已禁止上传功能';
                    } else if ($lang == 'zh') {
                        $errmsg = '已禁止上傳功能';
                    } else {
                        $errmsg = 'The upload function has been disabled';
                    }
                    return ['errcode' => 1, 'errmsg' => $errmsg];
                }
            }
        }

        $file = !empty($postFiles) ? $postFiles : request()->file($fileElementId);
        if (empty($file)) {
            if ($lang == 'cn') {
                $errmsg = '请选择上传文件';
            } else if ($lang == 'zh') {
                $errmsg = '請選擇上傳文件';
            } else {
                $errmsg = 'Please select upload file';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg];
        }

        $validate = array();
        // 文件大小限制
        $validate['size'] = intval(tpCache('basic.file_size') * 1024 * 1024);
        /*文件扩展名限制*/
        $validate_ext = !empty($file_type) ? str_replace(',', '|', $file_type) : tpCache('basic.file_type');
        $validate_ext = str_replace('|', ',', $validate_ext);
        $validate['ext'] = explode(',', $validate_ext);
        /*--end*/

        //拓展名
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        if (!in_array($ext, $validate['ext'])) {
            if ($lang == 'cn') {
                $errmsg = '上传文件后缀名必须为';
            } else if ($lang == 'zh') {
                $errmsg = '上傳文件后綴名必須為';
            } else {
                $errmsg = 'Upload file suffix must be ';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg . $validate_ext];
        }
        /*拓展名验证end*/

        /*上传文件验证*/
        if (!empty($validate)) {
            $is_validate = $file->check($validate);
            if ($is_validate === false) {
                return ['errcode' => 1, 'errmsg' => $file->getError()];
            }
        }
        /*--end*/

        $fileName = $file->getInfo('name');
        // 提取文件名后缀
        $file_ext = pathinfo($fileName, PATHINFO_EXTENSION);
        // 提取出文件名，不包括扩展名
        $newfileName = preg_replace('/\.([^\.]+)$/', '', $fileName);
        // 过滤文件名.\/的特殊字符，防止利用上传漏洞
        $newfileName = preg_replace('#(\\\|\/|\.)#i', '', $newfileName);
        // 过滤后的新文件名
        $fileName = $newfileName . '.' . $file_ext;
        // 中文转码
//        $newfileName = iconv("utf-8", "gb2312//IGNORE", $newfileName);

        $savePath   = $path . '/' . date('Ymd/');
        $return_url = "";

        $info = $file->rule(function ($file) {
            // return md5(mt_rand());
            $users_id = 1;
            if (session('?users_id') && 'admin' != request()->module()) {
                $users_id = session('users_id');
            } else if (session('?admin_id')) {
                $users_id = session('admin_id');
            }
            return $users_id . '-' . dd2char(date("ymdHis") . mt_rand(100, 999));
        })->move(UPLOAD_PATH . $savePath);
        if ($info) {
            $return_url = '/' . UPLOAD_PATH . $savePath . $info->getSaveName();
        }

        if ($return_url) {
            if ($lang == 'cn') {
                $errmsg = '上传成功';
            } else if ($lang == 'zh') {
                $errmsg = '上傳成功';
            } else {
                $errmsg = 'Upload succeeded';
            }
            return ['errcode' => 0, 'errmsg' => $errmsg, 'img_url' => $return_url];
        }
        else {
            if ($lang == 'cn') {
                $errmsg = '上传失败';
            } else if ($lang == 'zh') {
                $errmsg = '上傳失敗';
            } else {
                $errmsg = 'Upload failed';
            }
            return ['errcode' => 1, 'errmsg' => $errmsg];
        }
    }
}

if (!function_exists('func_substr_replace')) {
    /**
     * 隐藏部分字符串
     *
     * @param     string $str 字符串
     * @param     string $replacement 替换显示的字符
     * @param     intval $start 起始位置
     * @param     intval $length 隐藏长度
     * @return    string
     */
    function func_substr_replace($str, $replacement = '*', $start = 1, $length = 3)
    {
        $len = mb_strlen($str, 'utf-8');
        if ($len > intval($start + $length)) {
            $str1 = msubstr($str, 0, $start);
            $str2 = msubstr($str, intval($start + $length), NULL);
        } else {
            $str1   = msubstr($str, 0, 1);
            $str2   = msubstr($str, $len - 1, 1);
            $length = $len - 2;
        }
        $new_str = $str1;
        for ($i = 0; $i < $length; $i++) {
            $new_str .= $replacement;
        }
        $new_str .= $str2;

        return $new_str;
    }
}

if (!function_exists('func_authcode')) {
    /**
     * 字符串加密解密
     *
     * @param unknown $string 明文或密文
     * @param string $operation DECODE表示解密,其它表示加密
     * @param string $key 密匙
     * @param number $expiry 密文有效期
     * @return string
     */
    function func_authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;
        $key         = md5($key != '' ? $key : 'zxsdcrtkbrecxm');
        $keya        = md5(substr($key, 0, 16));
        $keyb        = md5(substr($key, 16, 16));
        $keyc        = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey   = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box    = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result  .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }

    }
}

if (!function_exists('get_pinyin')) {
    /**
     *  获取拼音以gbk编码为准
     *
     * @param     string $str 字符串信息
     * @param     int $ishead 是否取头字母
     * @param     int $isclose 是否关闭字符串资源
     * @return    string
     */
    function get_pinyin($str, $ishead = 0, $isclose = 1)
    {
        try{
            $s1 = iconv("UTF-8", "gb2312", $str);
            $s2 = iconv("gb2312", "UTF-8", $s1);
            if ($s2 == $str) {
                $str = $s1;
            }

            static $pinyins = null;
            $restr   = '';
            $str     = trim($str);
            $slen    = strlen($str);
            if ($slen < 2) {
                return $str;
            }
            if (null === $pinyins) {
                $pinyins = [];
                $fp = fopen(DATA_PATH . 'conf/pinyin.dat', 'r');
                while (!feof($fp)) {
                    $line                         = trim(fgets($fp));
                    $pinyins[$line[0] . $line[1]] = substr($line, 3, strlen($line) - 3);
                }
                fclose($fp);
            }
            for ($i = 0; $i < $slen; $i++) {
                if (ord($str[$i]) > 0x80) {
                    $c = $str[$i] . $str[$i + 1];
                    $i++;
                    if (isset($pinyins[$c])) {
                        if ($ishead == 0) {
                            $restr .= $pinyins[$c];
                        } else {
                            $restr .= $pinyins[$c][0];
                        }
                    } else {
                        $restr .= "-";
                    }
                } else if (preg_match("/[a-z0-9]/i", $str[$i])) {
                    $restr .= $str[$i];
                } else {
                    $restr .= "-";
                }
            }
            if ($isclose == 0) {
                unset($pinyins);
            }
            return strtolower($restr);
        }catch (\Exception $e){
            return "";
        }
    }
}

if (!function_exists('filter_line_return')) {
    /**
     *  过滤换行回车符
     *
     * @param     string $str 字符串信息
     * @return    string
     */
    function filter_line_return($str = '', $replace = '')
    {
        return str_replace(PHP_EOL, $replace, $str);
    }
}

if (!function_exists('MyDate')) {
    /**
     *  时间转化日期格式
     *
     * @param     string $format 日期格式
     * @param     intval $t 时间戳
     * @return    string
     */
    function MyDate($format = 'Y-m-d', $t = '')
    {
        if (!empty($t)) {
            $t = date($format, $t);
        }
        return $t;
    }
}

if (!function_exists('arctype_options')) {
    /**
     * 过滤和排序所有文章栏目，返回一个带有缩进级别的数组
     *
     * @param   int $id 上级栏目ID
     * @param   array $arr 含有所有栏目的数组
     * @param   string $id_alias id键名
     * @param   string $pid_alias 父id键名
     * @return  void
     */
    function arctype_options($spec_id, $arr, $id_alias, $pid_alias)
    {
        $cat_options = array();

        if (isset($cat_options[$spec_id])) {
            return $cat_options[$spec_id];
        }

        if (!isset($cat_options[0])) {
            $level   = $last_id = 0;
            $options = $id_array = $level_array = array();
            while (!empty($arr)) {
                foreach ($arr AS $key => $value) {
                    $id = $value[$id_alias];
                    if ($level == 0 && $last_id == 0) {
                        if ($value[$pid_alias] > 0) {
                            break;
                        }

                        $options[$id]            = $value;
                        $options[$id]['level']   = $level;
                        $options[$id][$id_alias] = $id;
                        // $options[$id]['typename']  = $value['typename'];
                        unset($arr[$key]);

                        if ($value['has_children'] == 0) {
                            continue;
                        }
                        $last_id               = $id;
                        $id_array              = array($id);
                        $level_array[$last_id] = ++$level;
                        continue;
                    }

                    if ($value[$pid_alias] == $last_id) {
                        $options[$id]            = $value;
                        $options[$id]['level']   = $level;
                        $options[$id][$id_alias] = $id;
                        // $options[$id]['typename']  = $value['typename'];
                        unset($arr[$key]);

                        if ($value['has_children'] > 0) {
                            if (end($id_array) != $last_id) {
                                $id_array[] = $last_id;
                            }
                            $last_id               = $id;
                            $id_array[]            = $id;
                            $level_array[$last_id] = ++$level;
                        }
                    } elseif ($value[$pid_alias] > $last_id) {
                        break;
                    }
                }

                $count = count($id_array);
                if ($count > 1) {
                    $last_id = array_pop($id_array);
                } elseif ($count == 1) {
                    if ($last_id != end($id_array)) {
                        $last_id = end($id_array);
                    } else {
                        $level    = 0;
                        $last_id  = 0;
                        $id_array = array();
                        continue;
                    }
                }

                if ($last_id && isset($level_array[$last_id])) {
                    $level = $level_array[$last_id];
                } else {
                    $level = 0;
                    break;
                }
            }
            $cat_options[0] = $options;
        } else {
            $options = $cat_options[0];
        }

        if (!$spec_id) {
            return $options;
        } else {
            if (empty($options[$spec_id])) {
                return array();
            }

            $spec_id_level = $options[$spec_id]['level'];

            foreach ($options AS $key => $value) {
                if ($key != $spec_id) {
                    unset($options[$key]);
                } else {
                    break;
                }
            }

            $spec_id_array = array();
            foreach ($options AS $key => $value) {
                if (($spec_id_level == $value['level'] && $value[$id_alias] != $spec_id) ||
                    ($spec_id_level > $value['level'])) {
                    break;
                } else {
                    $spec_id_array[$key] = $value;
                }
            }
            $cat_options[$spec_id] = $spec_id_array;

            return $spec_id_array;
        }
    }
}

if (!function_exists('img_replace_url')) {
    /**
     * 内容图片地址替换成带有http地址
     *
     * @param string $content 内容
     * @param string $imgurl 远程图片url
     * @return string
     */
    function img_replace_url($content = '', $imgurl = '')
    {
        $pregRule = "/<img(.*?)src(\s*)=(\s*)[\'|\"](.*?(?:[\.jpg|\.jpeg|\.png|\.gif|\.bmp|\.ico|\.webp]))[\'|\"](.*?)[\/]?(\s*)>/i";
        $content  = preg_replace($pregRule, '<img ${1} src="' . $imgurl . '" ${5} />', $content);

        return $content;
    }
}

if (!function_exists('getCmsVersion')) {
    /**
     * 获取当前CMS版本号
     *
     * @return string
     */
    function getCmsVersion()
    {
        $ver              = 'v1.1.8';
        $version_txt_path = ROOT_PATH . 'data/conf/version.txt';
        if (file_exists($version_txt_path)) {
            $fp      = fopen($version_txt_path, 'r');
            $content = fread($fp, filesize($version_txt_path));
            fclose($fp);
            $ver = $content ? $content : $ver;
        } else {
            $r = tp_mkdir(dirname($version_txt_path));
            if ($r) {
                $fp = fopen($version_txt_path, "w+") or die("请设置" . $version_txt_path . "的权限为777");
                $web_version = tpCache('system.system_version');
                $ver         = !empty($web_version) ? $web_version : $ver;
                if (fwrite($fp, $ver)) {
                    fclose($fp);
                }
            }
        }

        return file_exists('./data/conf/develop.txt') ? 'v9'.getTime() : $ver;
    }
}

if (!function_exists('getVersion')) {
    /**
     * 获取当前各种版本号
     *
     * @return string
     */
    function getVersion($filename = 'version', $ver = 'v1.0.0', $is_write = false)
    {
        $version_txt_path = ROOT_PATH . 'data/conf/' . $filename . '.txt';
        if (file_exists($version_txt_path) && false === $is_write) {
            $fp      = fopen($version_txt_path, 'r');
            $content = fread($fp, filesize($version_txt_path));
            fclose($fp);
            $ver = $content ? $content : $ver;
        } else if (!file_exists($version_txt_path) || true === $is_write) {
            $r = tp_mkdir(dirname($version_txt_path));
            if ($r) {
                $fp = fopen($version_txt_path, "w+") or die("请设置" . $version_txt_path . "的权限为777");
                if (fwrite($fp, $ver)) {
                    fclose($fp);
                }
            }
        }

        return file_exists('./data/conf/develop.txt') ? 'v9'.getTime() : $ver;
    }
}

if (!function_exists('getWeappVersion')) {
    /**
     * 获取当前插件版本号
     *
     * @param string $ocde 插件标识
     * @return string
     */
    function getWeappVersion($code)
    {
        $ver         = 'v1.0';
        $config_path = WEAPP_DIR_NAME . DS . $code . DS . 'config.php';
        if (file_exists($config_path)) {
            $config = include $config_path;
            $ver    = !empty($config['version']) ? $config['version'] : $ver;
        } else {
            die($code . "插件缺少" . $config_path . "配置文件");
        }
        return $ver;
    }
}

if (!function_exists('webXssKeyFile'))
{
    /**
     * xss和网站防止被刷TXT
     * @param  string $str [description]
     * @return [type]      [description]
     */
    function webXssKeyFile() {
        return DATA_PATH.'conf'.DS.'web_xss_filter.txt';
    }
}

if (!function_exists('strip_sql')) {
    /**
     * 转换SQL关键字
     *
     * @param unknown_type $string
     * @return unknown
     */
    function strip_sql($string)
    {
        static $data = null;
        if (null === $data) {
            if (file_exists(webXssKeyFile())) {
                $content = @file_get_contents(webXssKeyFile());
                $data = json_decode($content, true);
            }
        }
        $web_xss_filter = empty($data['web_xss_filter']) ? 0 : intval($data['web_xss_filter']);
        $web_xss_words = empty($data['web_xss_words']) ? [] : explode(PHP_EOL, $data['web_xss_words']);
        if ((empty($web_xss_filter) && is_numeric($web_xss_filter)) || empty($web_xss_words)) {
            return $string;
        }

        foreach ($web_xss_words as $words) {
            if($words=='eval'){
                $pattern_arr[] = "/\@(\s*)\b{$words}\b/i";
                $replace_arr[] = '＠' . convertStrType($words, 'TODBC');
            }else if($words=='phar'){
                $pattern_arr[] = "/\b{$words}\b\:/i";
                $replace_arr[] = convertStrType($words, 'TODBC');
            }
            $pattern_arr[] = "/\b{$words}\b/i";
            $replace_arr[] = convertStrType($words, 'TODBC');
        }

        return is_array($string) ? array_map('strip_sql', $string) : preg_replace($pattern_arr, $replace_arr, $string);
    }
}

if (!function_exists('get_weapp_class')) {
    /**
     * 获取插件类的类名
     *
     * @param strng $name 插件名
     * @param strng $controller 控制器
     * @return class
     */
    function get_weapp_class($name, $controller = '')
    {
        $controller = !empty($controller) ? $controller : $name;
        $class      = WEAPP_DIR_NAME . "\\{$name}\\controller\\{$controller}";
        return $class;
    }
}

if (!function_exists('convertStrType')) {
    /**
     * Notes:全角 半角相互转化
     * @param $str
     * @param $type string  TODBC 半角到全角 | TOSBC 全角到半角
     */
    function convertStrType($str, $type = 'TOSBC')
    {
        $dbc = array(
            '０', '１', '２', '３', '４',
            '５', '６', '７', '８', '９',
            'Ａ', 'Ｂ', 'Ｃ', 'Ｄ', 'Ｅ',
            'Ｆ', 'Ｇ', 'Ｈ', 'Ｉ', 'Ｊ',
            'Ｋ', 'Ｌ', 'Ｍ', 'Ｎ', 'Ｏ',
            'Ｐ', 'Ｑ', 'Ｒ', 'Ｓ', 'Ｔ',
            'Ｕ', 'Ｖ', 'Ｗ', 'Ｘ', 'Ｙ',
            'Ｚ', 'ａ', 'ｂ', 'ｃ', 'ｄ',
            'ｅ', 'ｆ', 'ｇ', 'ｈ', 'ｉ',
            'ｊ', 'ｋ', 'ｌ', 'ｍ', 'ｎ',
            'ｏ', 'ｐ', 'ｑ', 'ｒ', 'ｓ',
            'ｔ', 'ｕ', 'ｖ', 'ｗ', 'ｘ',
            'ｙ', 'ｚ', '－', '　', '：',
            '．', '，', '／', '％', '＃',
            '！', '＠', '＆', '（', '）',
            '＜', '＞', '＂', '＇', '？',
            '［', '］', '｛', '｝', '＼',
            '｜', '＋', '＝', '＿', '＾',
            '￥', '￣', '｀'
        );
        $sbc = array( //半角
            '0', '1', '2', '3', '4',
            '5', '6', '7', '8', '9',
            'A', 'B', 'C', 'D', 'E',
            'F', 'G', 'H', 'I', 'J',
            'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y',
            'Z', 'a', 'b', 'c', 'd',
            'e', 'f', 'g', 'h', 'i',
            'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x',
            'y', 'z', '-', ' ', ':',
            '.', ',', '/', '%', ' #',
            '!', '@', '&', '(', ')',
            '<', '>', '"', '\'', '?',
            '[', ']', '{', '}', '\\',
            '|', '+', '=', '_', '^',
            '￥', '~', '`'
        );
        if($type == 'TODBC'){ //半角到全角
            return str_replace( $sbc, $dbc, $str );
        }elseif($type == 'TOSBC'){ //全角到半角
            return str_replace( $dbc, $sbc, $str );
        }else{
            return $str;
        }
    }
}

if (!function_exists('view_logic')) {
    /**
     * 模型对应逻辑
     * @param intval $aid 文档ID
     * @param intval $channel 栏目ID
     * @param intval $result 数组
     * @param mix $allAttrInfo 附加表数据
     * @return array
     */
    function view_logic($aid, $channel, $result = array(), $allAttrInfo = array())
    {

        $allAttrInfo_bool     = $allAttrInfo;
        $result['image_list'] = $result['attr_list'] = $result['file_list'] = array();
        switch ($channel) {
            case '2': // 产品模型
                {
                    /*产品相册*/
                    if (true === $allAttrInfo_bool) {
                        $allAttrInfo                = [];
                        $productImgModel = new \app\home\model\ProductImg;
                        $allAttrInfo['product_img'] = $productImgModel->getProImg([$aid]);
                    }
                    $image_list = !empty($allAttrInfo['product_img'][$aid]) ? $allAttrInfo['product_img'][$aid] : [];

                    // 支持子目录
                    foreach ($image_list as $k1 => $v1) {
                        $image_list[$k1]['image_url'] = handle_subdir_pic($v1['image_url']);
                        isset($v1['intro']) && $image_list[$k1]['intro'] = htmlspecialchars_decode($v1['intro']);
                    }

                    $result['image_list'] = $image_list;
                    /*--end*/

                    /*产品参数*/
                    if (true === $allAttrInfo_bool) {
                        $allAttrInfo                 = [];
                        $productAttrModel = new \app\home\model\ProductAttr;
                        $allAttrInfo['product_attr'] = $productAttrModel->getProAttr([$aid]);
                    }
                    $attr_list           = !empty($allAttrInfo['product_attr'][$aid]) ? $allAttrInfo['product_attr'][$aid] : [];
                    $attr_list           = model('LanguageAttr')->getBindValue($attr_list, 'product_attribute', get_main_lang()); // 获取多语言关联绑定的值
                    $result['attr_list'] = $attr_list;
                    /*--end*/
                    break;
                }

            case '3': // 图集模型
                {
                    /*图集相册*/
                    if (true === $allAttrInfo_bool) {
                        $allAttrInfo                  = [];
                        $imagesUploadModel = new \app\home\model\ImagesUpload;
                        $allAttrInfo['images_upload'] = $imagesUploadModel->getImgUpload([$aid]);
                    }
                    $image_list = !empty($allAttrInfo['images_upload'][$aid]) ? $allAttrInfo['images_upload'][$aid] : [];

                    // 支持子目录
                    foreach ($image_list as $k1 => $v1) {
                        $image_list[$k1]['image_url'] = handle_subdir_pic($v1['image_url']);
                        isset($v1['intro']) && $image_list[$k1]['intro'] = htmlspecialchars_decode($v1['intro']);
                    }

                    $result['image_list'] = $image_list;
                    /*--end*/
                    break;
                }

            case '4': // 下载模型
                {
                    /*下载资料列表*/
                    if (true === $allAttrInfo_bool) {
                        $allAttrInfo                  = [];
                        $downloadFileModel = new \app\home\model\DownloadFile;
                        $allAttrInfo['download_file'] = $downloadFileModel->getDownFile([$aid]);
                    }
                    $file_list = !empty($allAttrInfo['download_file'][$aid]) ? $allAttrInfo['download_file'][$aid] : [];

                    // 支持子目录
                    foreach ($file_list as $k1 => $v1) {
                        $file_list[$k1]['file_url'] = handle_subdir_pic($v1['file_url']);
                        if (empty($v1['file_size'])) {
                            $file_list[$k1]['file_size'] = '';
                        } else {
                            $file_list[$k1]['file_size'] = format_bytes($v1['file_size']);
                        }
                    }

                    $result['file_list'] = $file_list;
                    /*--end*/
                    
                    /*下载权限*/
                    $arc_level_id = intval($result['arc_level_id']);
                    if (empty($arc_level_id)) {
                        $result['arc_level_name'] = '不限会员';
                    } else {
                        $result['arc_level_name'] = \think\Db::name('users_level')->where(['level_id'=>$arc_level_id])->getField('level_name');
                    }
                    /*--end*/

                    break;
                }

            case '5': // 视频模型
                {
                    $result['courseware'] = !empty($result['courseware']) ? url('home/View/download_media_file',['aid'=>$result['aid']]) : '';

                    if (true === $allAttrInfo_bool) {
                        $allAttrInfo                  = [];
                        $mediaFileModel = new \app\home\model\MediaFile;
                        $allAttrInfo['video_file'] = $mediaFileModel->getMediaFile($aid);
                    }
                    $result['file_list'] = !empty($allAttrInfo['video_file']) ? $allAttrInfo['video_file'] : [];
                    //自定义视频字段
                    $channel_field = model('Channelfield')->getListByWhere(['channel_id'=>5,'dtype'=>'media']);
                    if (!empty($channel_field)) {
                        $request = request();
                        foreach ($channel_field as $key => $val) {
                            if (!empty($result[$key])) {
                                $result[$key] = json_decode($result[$key], true);
                                foreach ($result[$key] as $k => $v) {
                                    if (!empty($v['file_url'])) {
                                        $v['file_url'] = handle_subdir_pic($v['file_url'], 'media');
                                        if (!is_http_url($v['file_url'])){
                                            $v['file_url'] = $request->domain() .$v['file_url'];
                                        }
                                    }
                                    $result[$key][$k] = $v;
                                }
                            }
                        }
                    }
                    isset($result['total_duration']) && $result['total_duration'] = gmSecondFormat($result['total_duration'], ':');
                    /*--end*/
                    
                    /*播放权限*/
                    $arc_level_id = intval($result['arc_level_id']);
                    if (empty($arc_level_id)) {
                        $result['arc_level_name'] = '不限会员';
                    } else {
                        $result['arc_level_name'] = \think\Db::name('users_level')->where(['level_id'=>$arc_level_id])->getField('level_name');
                    }
                    /*--end*/
                    break;
                }

            default:
                {
                    break;
                }
        }

        return $result;
    }
}

if (!function_exists('uncamelize')) {
    /**
     * 驼峰命名转下划线命名
     * 思路:
     * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     */
    function uncamelize($camelCaps, $separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }
}

if (!function_exists('read_bidden_inc')) {
    /**
     * 读取被禁止外部访问的配置文件
     *
     */
    function read_bidden_inc($phpfilepath = '')
    {
        $data = @file($phpfilepath);
        if ($data) {
            $data = !empty($data[1]) ? json_decode($data[1]) : [];
        }
        return $data;
    }
}

if (!function_exists('write_bidden_inc')) {
    /**
     * 写入被禁止外部访问的配置文件
     */
    function write_bidden_inc($arr = array(), $phpfilepath = '')
    {
        $r = tp_mkdir(dirname($phpfilepath));
        if ($r) {
            $setting = "<?php die('forbidden'); ?>\n";
            $setting .= json_encode($arr);
            $setting = str_replace("\/", "/", $setting);
            $incFile = fopen($phpfilepath, "w+");
            if (fwrite($incFile, $setting)) {
                fclose($incFile);
                return true;
            } else {
                return false;
            }
        }
    }
}

if (!function_exists('convert_js_array')) {
    /**
     * 将PHP数组转换成JS数组。
     */
    function convert_js_array($arr = array())
    {
        if (empty($arr)) {
            return false;
        }

        $convert_js_array = "['";
        foreach ($arr as $key => $val) {
            if ($key > 0) {
                $convert_js_array .= "','";
            }
            $convert_js_array .= $val;
        }
        $convert_js_array = $convert_js_array . "']";

        return $convert_js_array;
    }
}

if (!function_exists('GetUrlToDomain')) {
    /**
     * 取得根域名
     * @param type $domain 域名
     * @return string 返回根域名
     */
    function GetUrlToDomain($domain = '')
    {
        static $request = null;
        null == $request && $request = \think\Request::instance();
        $root = $request->rootDomain($domain);

        return $root;
    }
}

if (!function_exists('check_fix_pathinfo')) {
    /**
     * 判断支持pathinfo模式的路由，即是否支持伪静态
     * @return boolean 布尔值
     */
    function check_fix_pathinfo()
    {
        static $is_fix_pathinfo = null;
        if (null === $is_fix_pathinfo) {
            $fix_pathinfo = ini_get('cgi.fix_pathinfo');
            if (stristr(request()->host(), '.mylightsite.com') || ('' != $fix_pathinfo && 0 === $fix_pathinfo)) {
                $is_fix_pathinfo = false;
            } else {
                $is_fix_pathinfo = true;
            }
        }

        return $is_fix_pathinfo;
    }
}

/**
 *  生成一个随机字符
 *
 * @access    public
 * @param     string $ddnum
 * @return    string
 */
if (!function_exists('dd2char')) {
    function dd2char($ddnum)
    {
        $ddnum = strval($ddnum);
        $slen  = strlen($ddnum);
        $okdd  = '';
        $nn    = '';
        for ($i = 0; $i < $slen; $i++) {
            if (isset($ddnum[$i + 1])) {
                $n = $ddnum[$i] . $ddnum[$i + 1];
                if (($n > 96 && $n < 123) || ($n > 64 && $n < 91)) {
                    $okdd .= chr($n);
                    $i++;
                } else {
                    $okdd .= $ddnum[$i];
                }
            } else {
                $okdd .= $ddnum[$i];
            }
        }
        return $okdd;
    }
}

if (!function_exists('friend_date')) {
    /**
     * 友好时间显示
     * @param $time
     * @return bool|string
     */
    function friend_date($time)
    {
        if (!$time)
            return false;
        $fdate = '';
        $d     = time() - intval($time);
        $ld    = $time - mktime(0, 0, 0, 0, 0, date('Y')); //得出年
        $md    = $time - mktime(0, 0, 0, date('m'), 0, date('Y')); //得出月
        $byd   = $time - mktime(0, 0, 0, date('m'), date('d') - 2, date('Y')); //前天
        $yd    = $time - mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')); //昨天
        $dd    = $time - mktime(0, 0, 0, date('m'), date('d') + 0, date('Y')); //今天
        $td    = $time - mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')); //明天
        $atd   = $time - mktime(0, 0, 0, date('m'), date('d') + 2, date('Y')); //后天
        if ($d == 0) {
            $fdate = '刚刚';
        } else {
            switch ($d) {
                case $d < $atd:
                    $fdate = date('Y年m月d日', $time);
                    break;
                case $d < $td:
                    $fdate = '后天' . date('H:i', $time);
                    break;
                case $d < 0:
                    $fdate = '明天' . date('H:i', $time);
                    break;
                case $d < 60:
                    $fdate = $d . '秒前';
                    break;
                case $d < 3600:
                    $fdate = floor($d / 60) . '分钟前';
                    break;
                case $d < $dd:
                    $fdate = floor($d / 3600) . '小时前';
                    break;
                case $d < $yd:
                    $fdate = '昨天' . date('H:i', $time);
                    break;
                case $d < $byd:
                    $fdate = '前天' . date('H:i', $time);
                    break;
                case $d < $md:
                    $fdate = date('m月d日 H:i', $time);
                    break;
                case $d < $ld:
                    $fdate = date('m月d日', $time);
                    break;
                default:
                    $fdate = date('Y年m月d日', $time);
                    break;
            }
        }
        return $fdate;
    }
}

/**
 *  检查验证是否最新的模板
 * @param   $Url    查询的模板路径
 * @param   $String 查询是否存在的字符串
 * @return  返回错误的字符串
 */
if (!function_exists('VerifyLatestTemplate')) {
    function VerifyLatestTemplate($Url = null, $String = [])
    {
        // 查询的模板路径
        $Url = !empty($Url) ? $Url : './template/' . THEME_STYLE_PATH . '/view_product.htm';

        // 查询是否存在的字符串
        $String = !empty($String) ? $String : ['ReturnData', 'spec_name', 'spec_value', 'SpecClass', 'SpecData'];

        // 获取出文件内容
        $GetHtml = @file_get_contents($Url);

        // 查询是否匹配
        $ResultData = [];
        foreach ($String as $value) {
            if (strpos($GetHtml, $value) === false) {
                array_push($ResultData, $value);
            }
        }

        // 返回结果
        return $ResultData;
    }
}

/**
 *  获取区域子域名URL
 *
 * @access    public
 * @param     string $subDomain
 * @return    string
 */
if (!function_exists('getRegionDomainUrl')) {
    function getRegionDomainUrl($subDomain = '', $root_dir = true)
    {
        $domain = request()->host(true);
        if (!empty($subDomain) && !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $domain)) {
            $domain = request()->subDomain($subDomain);
        }
        true === $root_dir && $domain .= ROOT_DIR;

        return $domain;
    }
}

/**
 *  获取URl子域名，忽略IP地址
 *
 * @access    public
 * @param     string $subDomain
 * @return    string
 */
if (!function_exists('getSubDomain')) {
    function getSubDomain($root_dir = true)
    {
        $domain = request()->host(true);
        if (!empty($subDomain) && !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $domain)) {
            $rootDomain = request()->rootDomain();
            $domain     = $subDomain . '.' . $rootDomain;
        }
        true === $root_dir && $domain .= ROOT_DIR;

        return $domain;
    }
}

if (!function_exists('get_split_word')) {
    /**
     *  自动获取关键字
     *
     * @access    public
     * @param     string $title 标题
     * @param     array $body 内容
     * @return    string
     */
    function get_split_word($title = '', $body = '')
    {
        vendor('splitword.autoload');
        $keywords = '';
        $kw       = new keywords();
        $keywords = $kw->GetSplitWord($title, $body);

        return $keywords;
    }
}

if (!function_exists('remote_file_to_local')) {
    /**
     *  远程文件本地化
     *
     * @access    public
     * @param     string $body 内容
     * @return    string
     */
    function remote_file_to_local($url = '')
    {
        $image_ext = config('global.image_ext');
        $web_basehost = tpCache('web.web_basehost');
        $parse_arr    = parse_url($web_basehost);
        $host  = request()->host(true);

        $dirname = UPLOAD_PATH . 'soft/' . date('Ymd/');

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            return $url;
        } else if (!is_writeable($dirname)) {
            return $url;
        }
        // 插件列表
        static $weappList = null;
        if (null === $weappList) {
            $weappList = \think\Db::name('weapp')->where([
                'status'    => 1,
            ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                ->getAllWithIndex('code');
        }
        $storageDomain = ''; // 第三方存储的域名
        if (!empty($weappList['Qiniuyun']['data'])) {
            $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
            if (!empty($qnyData['domain'])) {
                $storageDomain = $qnyData['domain'];
            }
        } else if (!empty($weappList['AliyunOss']['data'])) {
            $ossData = json_decode($weappList['AliyunOss']['data'], true);
            if (!empty($ossData['domain'])) {
                $storageDomain = $ossData['domain'];
            }
        } else if (!empty($weappList['Cos']['data'])) {
            $cosData = json_decode($weappList['Cos']['data'], true);
            if (!empty($cosData['domain'])) {
                $storageDomain = $cosData['domain'];
            }
        }
        $img_array = [$url];
        $preg_root_dir = str_replace('/', '\/', ROOT_DIR);
        foreach ($img_array as $key => $value) {
            $imgUrl = trim($value);
            $imgUrl = preg_replace('/#/', '', $imgUrl);
            // 本站图片 / 根网址图片 / 第三方存储插件的图片
            if (!empty($parse_arr['host'])){
                if (preg_match("/\/\/({$host}{$preg_root_dir}|{$storageDomain}|{$parse_arr['host']}{$preg_root_dir})\//i", $imgUrl)) {
                    continue;
                }
            }else{
                if (preg_match("/\/\/({$host}{$preg_root_dir}|{$storageDomain})\//i", $imgUrl)) {
                    continue;
                }
            }
            // 不是合法链接
            if (!preg_match("#^http(s?):\/\/#i", $imgUrl)) {
                continue;
            }

            $heads = @get_headers($imgUrl, 1);

            // 获取请求头并检测死链
            if (empty($heads) || !(stristr($heads[0], "200") && !stristr($heads[0], "304"))) {
                continue;
            }
            // 扩展名
            $url_arr = explode('.',$value);
            $count = count($url_arr);
            $ext = $url_arr[$count-1];
            $fileType = '.'.$ext;

            //打开输出缓冲区并获取远程图片
            ob_start();
            $context = stream_context_create(
                array('http' => array(
                    'follow_location' => false // don't follow redirects
                ))
            );
            readfile($imgUrl, false, $context);
            $img = ob_get_contents();
            ob_end_clean();
            preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

            $file['name']     = session('admin_id') . '-' . dd2char(date("ymdHis") . mt_rand(100, 999)) . $fileType;
            $url= $dirname . $file['name'];

            //检查文件大小是否超出限制
            if ($file['filesize'] >= 20480000) {
                continue;
            }

            //移动文件
            if (!(file_put_contents($url, $img) && file_exists($url))) { //移动失败
                continue;
            }

            $realfilepath = realpath('./'.$url);
            $url = ROOT_DIR . '/'.$url;

            try {
                /*同步到第三方对象存储空间*/
                if (preg_match('/^(.*)\.('.str_replace(',', '|', $image_ext).')$/i', $url)) {

                    // 检查文件的真实 MIME 类型
                    if (!check_image_mime_type($realfilepath)) {
                        @unlink($realfilepath);
                        continue;
                    }

                    // 检查文件头部信息
                    if (!check_image_header($realfilepath)) {
                        @unlink($realfilepath);
                        continue;
                    }

                    $bucket_data = SynImageObjectBucket($url, $weappList);
                    if (!empty($bucket_data['url']) && is_string($bucket_data['url'])) {
                        $url = $bucket_data['url'];
                    }
                }
                /*end*/
            } catch (\Exception $e) {}
        }

        return $url;
    }
}

if (!function_exists('remote_to_local')) {
    /**
     *  远程图片本地化
     *
     * @access    public
     * @param     string $body 内容
     * @return    string
     */
    function remote_to_local($body = '', $is_force = false)
    {
        $image_ext = config('global.image_ext');
        $web_basehost = tpCache('web.web_basehost');
        $parse_arr    = parse_url($web_basehost);
        $host  = request()->host(true);
        $img_array = array();
        preg_match_all("/src=[\"|'|\s]([^\"|^\'|^\s]*?)/isU", $body, $img_array);

        $img_array = array_unique($img_array[1]);
        foreach ($img_array as $key => $val) {
            if (false === $is_force && preg_match("/^http(s?):\/\/(mmbiz.qpic.cn|thirdwx.qlogo.cn)\/(.*)\?wx_fmt=(\w+)&/", $val) == 1) {
                // unset($img_array[$key]);
            }
        }

        $dirname = './' . UPLOAD_PATH . 'allimg/' . date('Ymd/');

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            return $body;
        } else if (!is_writeable($dirname)) {
            return $body;
        }

        // 插件列表
        static $weappList = null;
        if (null === $weappList) {
            $weappList = \think\Db::name('weapp')->where([
                    'status'    => 1,
                ])->cache(true, EYOUCMS_CACHE_TIME, 'weapp')
                ->getAllWithIndex('code');
        }
        $storageDomain = ''; // 第三方存储的域名
        if (!empty($weappList['Qiniuyun']['data'])) {
            $qnyData = json_decode($weappList['Qiniuyun']['data'], true);
            if (!empty($qnyData['domain'])) {
                $storageDomain = $qnyData['domain'];
            }
        } else if (!empty($weappList['AliyunOss']['data'])) {
            $ossData = json_decode($weappList['AliyunOss']['data'], true);
            if (!empty($ossData['domain'])) {
                $storageDomain = $ossData['domain'];
            }
        } else if (!empty($weappList['Cos']['data'])) {
            $cosData = json_decode($weappList['Cos']['data'], true);
            if (!empty($cosData['domain'])) {
                $storageDomain = $cosData['domain'];
            }
        } else if (!empty($weappList['AwsOss']['data'])) {
            $awsData = json_decode($weappList['AwsOss']['data'], true);
            if (!empty($awsData['domain'])) {
                $storageDomain = $awsData['domain'];
            }
        }

        $preg_root_dir = str_replace('/', '\/', ROOT_DIR);
        foreach ($img_array as $key => $value) {
            $imgUrl = trim($value);
            $imgUrl = preg_replace('/#/', '', $imgUrl);
            // 本站图片 / 根网址图片 / 第三方存储插件的图片
            if (!empty($parse_arr['host'])){
                if (preg_match("/\/\/({$host}{$preg_root_dir}|{$storageDomain}|{$parse_arr['host']}{$preg_root_dir})\//i", $imgUrl)) {
                    continue;
                }
            }else{
                if (preg_match("/\/\/({$host}{$preg_root_dir}|{$storageDomain})\//i", $imgUrl)) {
                    continue;
                }
            }
            // 不是合法链接
            if (!preg_match("#^http(s?):\/\/#i", $imgUrl)) {
                continue;
            }

            $heads = @get_headers($imgUrl, 1);

            // 获取请求头并检测死链
            if (empty($heads) || !(stristr($heads[0], "200") && !stristr($heads[0], "304"))) {
                continue;
            }
            // 图片扩展名
            $fileType = substr($heads['Content-Type'], -4, 4);
            if (!preg_match("#\.(".str_replace(',', '|', $image_ext).")#i", $fileType)) {
                $filext = str_ireplace('image/', '', $heads['Content-Type']);
                if ($fileType == 'image/gif' || $filext == 'gif') {
                    $fileType = ".gif";
                } else if ($fileType == 'image/png' || $filext == 'png') {
                    $fileType = ".png";
                } else if ($fileType == 'image/x-icon' || $filext == 'x-icon') {
                    $fileType = ".ico";
                } else if ($fileType == 'image/bmp' || $filext == 'bmp') {
                    $fileType = ".bmp";
                }  else if ($fileType == 'image/webp' || $filext == 'webp') {
                    $fileType = ".webp";
                } else if ($heads['Content-Type'] == 'image/svg+xml' || $filext == 'svg+xml') {
                    $fileType = ".svg";
                } else {
                    $fileType = '.jpg';
                }
            }
            $fileType = strtolower($fileType);
            //格式验证(扩展名验证和Content-Type验证)，链接contentType是否正确
            $is_weixin_img = false;
            if (preg_match("/^http(s?):\/\/(mmbiz.qpic.cn|thirdwx.qlogo.cn)\/(.*)/", $imgUrl) != 1) {
                $allowFiles = explode(',', '.'.str_replace(',', ',.', $image_ext));
                if (!in_array($fileType, $allowFiles) || (isset($heads['Content-Type']) && !stristr($heads['Content-Type'], "image/"))) {
                    continue;
                }
            } else {
                $is_weixin_img = true;
                // $imgUrl_vars = preg_replace('/^(.*)(\?wx_fmt=(\w+)\&(.*))$/i', '${2}', $value);
            }

            /*验证图片一句话木马*/
            if (false === check_illegal($imgUrl,true,trim($fileType, '.'))) {
                continue;
            }
            /*--end*/

            //打开输出缓冲区并获取远程图片
            ob_start();
            $context = stream_context_create(
                array('http' => array(
                    'follow_location' => false // don't follow redirects
                ))
            );
            readfile($imgUrl, false, $context);
            $img = ob_get_contents();
            ob_end_clean();
            preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

            $file             = [];
            $file['oriName']  = $m ? $m[1] : "";
            $file['filesize'] = strlen($img);
            $file['ext']      = $fileType;
            $file['name']     = session('admin_id') . '-' . dd2char(date("ymdHis") . mt_rand(100, 999)) . $file['ext'];
            $file['fullName'] = $dirname . $file['name'];
            $fullName         = $file['fullName'];

            //检查文件大小是否超出限制
            if ($file['filesize'] >= 20480000) {
                continue;
            }

            //移动文件
            if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
                continue;
            }

            $filepath = substr($file['fullName'], 1);
            $realfilepath = realpath('.'.$filepath);

            // 检查文件的真实 MIME 类型
            if (!check_image_mime_type($realfilepath)) {
                @unlink($realfilepath);
                continue;
            }

            // 检查文件头部信息
            if (!check_image_header($realfilepath)) {
                @unlink($realfilepath);
                continue;
            }

            $fileurl = ROOT_DIR . $filepath;

            // 添加水印
            try {
                print_water($fileurl);
                /*同步到第三方对象存储空间*/
                $bucket_data = SynImageObjectBucket($fileurl, $weappList);
                if (!empty($bucket_data['url']) && is_string($bucket_data['url'])) {
                    $fileurl = $bucket_data['url'];
                }
                /*end*/
            } catch (\Exception $e) {}

            $body = str_replace($imgUrl, $fileurl, $body);

            // 添加图片进数据库
            $img_info = @getimagesize('.'.substr($file['fullName'], 1));
            $width = isset($img_info[0]) ? $img_info[0] : 0;
            $height = isset($img_info[1]) ? $img_info[1] : 0;
            $mime = isset($img_info['mime']) ? $img_info['mime'] : '';
            $addData[] = [
                'aid'         => 0,
                'type_id'     => 0,
                'image_url'   => $fileurl,
                'title'       => '',
                'intro'       => '',
                'width'       => $width,
                'height'      => $height,
                'filesize'    => $file['filesize'],
                'mime'        => $mime,
                'users_id'    => (int)session('admin_info.syn_users_id'),
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
        }

        // 添加图片进数据库
        // !empty($addData) && \think\Db::name('uploads')->insertAll($addData);

        return $body;
    }
}

if (!function_exists('check_image_mime_type')) {
    /**
     * 检查文件扩展名和 MIME 类型
     * @param  [type] $filePath [description]
     * @return [type]           [description]
     */
    function check_image_mime_type($filePath)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // 允许的图片 MIME 类型
        $allowedMimeTypes = [
            'image/jpeg',
            'image/gif',
            'image/bmp',
            'image/x-icon',
            'image/png',
            'image/webp',
            'image/svg+xml',
        ];
        return in_array($mimeType, $allowedMimeTypes);
    }
}

if (!function_exists('check_image_header')) {
    /**
     * 检查文件头部信息
     * @param  [type] $filePath [description]
     * @return [type]           [description]
     */
    function check_image_header($filePath)
    {
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        // 允许的文件头部信息
        $validHeaders = [
            "\xFF\xD8" => 'jpeg', // JPEG
            "\x47\x49\x46\x38\x37\x61" => 'gif', // GIF87a
            "\x47\x49\x46\x38\x39\x61" => 'gif', // GIF89a
            "\x42\x4D" => 'bmp', // BMP
            "\x00\x00\x01\x00" => 'ico', // ICO
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'png', // PNG
            "RIFF" => 'webp', // WebP
            '<?xml' => 'svg', // SVG
            '<svg' => 'svg',
        ];
        foreach ($validHeaders as $validHeader => $format) {
            if (strpos($header, $validHeader) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('replace_links')) {
    /**
     *  清除非站内链接
     *
     * @access    public
     * @param     string $body 内容
     * @param     array $allow_urls 允许的超链接
     * @return    string
     */
    function replace_links($body, $allow_urls = array())
    {
        // 读取允许的超链接设置
        $host = request()->host(true);
        $rootDomain = request()->rootDomain();
        if (!empty($allow_urls)) {
            $allow_urls = array_merge([$host,$rootDomain], $allow_urls);
        } else {
            $basic_body_allowurls = tpCache('basic.basic_body_allowurls');
            if (!empty($basic_body_allowurls)) {
                $allowurls  = explode(PHP_EOL, $basic_body_allowurls);
                $allow_urls = array_merge([$host,$rootDomain], $allowurls);
            } else {
                $allow_urls = [$host,$rootDomain];
            }
        }
        $web_basehost = tpCache('web.web_basehost');
        $parse_arr    = parse_url($web_basehost);
        if (!empty($parse_arr['host'])) {
            array_push($allow_urls, $parse_arr['host']);
        }

        $host_rule = join('|', $allow_urls);
        $host_rule = preg_replace("#[\n\r]#", '', $host_rule);
        $host_rule = str_replace('.', "\\.", $host_rule);
        $host_rule = str_replace('/', "\\/", $host_rule);
        $arr       = '';
        preg_match_all("#<a([^>]*)>(.*)<\/a>#iU", $body, $arr);
        if (is_array($arr[0])) {
            $rparr = array();
            $tgarr = array();
            foreach ($arr[0] as $i => $v) {
                if ( ($host_rule != '' && preg_match('#' . $host_rule . '#i', $arr[1][$i])) || !preg_match('/(\s+)href(\s*)=(\s*)([\'|\"]?)((\w)*:)?(\/\/)/i', $arr[1][$i]) ) {
                    continue;
                } else {
                    $rparr[] = $v;
                    $tgarr[] = $arr[2][$i];
                }
            }
            if (!empty($rparr)) {
                $body = str_replace($rparr, $tgarr, $body);
            }
        }
        $arr = $rparr = $tgarr = '';
        return $body;
    }
}

if (!function_exists('print_water')) {
    /**
     *  给图片增加水印
     *
     * @access    public
     * @param     string $imgpath 不带子目录的图片路径
     * @return    string
     */
    function print_water($imgpath = '')
    {
        try {
            static $water = null;
            null === $water && $water = tpCache('water');
            if (empty($imgpath) || $water['is_mark'] != 1) {
                return $imgpath;
            }

            $imgpath     = handle_subdir_pic($imgpath, 'img', false, true); // 支持子目录
            $imgresource = "." . $imgpath;
            $image       = \think\Image::open($imgresource);
            if ($image->width() > $water['mark_width'] && $image->height() > $water['mark_height']) {
                if ($water['mark_type'] == 'text') {
                    //$image->text($water['mark_txt'],ROOT_PATH.'public/static/common/font/hgzb.ttf',20,'#000000',9)->save($imgresource);
                    $ttf = ROOT_PATH . 'public/static/common/font/hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size  = $water['mark_txt_size'] ? $water['mark_txt_size'] : 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127 / 100));
                        $color        .= dechex($transparency);
                        $image->text($water['mark_txt'], $ttf, $size, $color, $water['mark_sel'])->save($imgresource);
                    }
                } else {
                    $water['mark_img'] = preg_replace('/\?(.*)$/i', '', $water['mark_img']);
                    $water['mark_img'] = handle_subdir_pic($water['mark_img'], 'img', false, true); // 支持子目录
                    //$image->water(".".$water['mark_img'],9,$water['mark_degree'])->save($imgresource);
                    $waterPath = "." . $water['mark_img'];
                    if (eyPreventShell($waterPath) && file_exists($waterPath)) {
                        $waterImgInfo = @getimagesize($waterPath);
                        $waterImgW = !empty($waterImgInfo[0]) ? $waterImgInfo[0] : 1000000;
                        $waterImgH = !empty($waterImgInfo[1]) ? $waterImgInfo[1] : 1000000;
                        if ($image->width() > $waterImgW && $image->height() > $waterImgH) {
                            $quality       = $water['mark_quality'] ? $water['mark_quality'] : 80;
                            $waterTempPath = dirname($waterPath) . '/temp_' . basename($waterPath);
                            $image->open($waterPath)->save($waterTempPath, null, $quality);
                            $image->water($waterTempPath, $water['mark_sel'], $water['mark_degree'])->save($imgresource);
                            @unlink($waterTempPath);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }
}

if (!function_exists('filterNickname')) {
    // 过滤微信表情符号
    function filterNickname($nickname = '')
    {
        $nickname = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $nickname);
        $nickname = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $nickname);
        $nickname = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $nickname);
        $nickname = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $nickname);
        $nickname = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $nickname);
        $nickname = str_replace(array('"', '\''), '', $nickname);
        return addslashes(trim($nickname));
    }
}

if (!function_exists('mchStrCode')) {
    /**
     *  加密函数
     *
     * @access    public
     * @param     string $string 字符串
     * @param     string $operation 操作
     * @return    string
     */
    function mchStrCode($string, $operation = 'ENCODE', $auth_code = '')
    {
        if (empty($auth_code)) {
            $auth_code = get_auth_code();
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

if (!function_exists('html_httpimgurl')) {
    /**
     * html内容里的图片地址替换成http路径
     * @param string $content 内容
     * @return    string
     */
    function html_httpimgurl($content = '', $timeVersion = false)
    {
        if (!empty($content)) {
            $t = '';
            if (true === $timeVersion) {
                $t = '?t='.getTime();
            }
            
            $pregRule = "/<img(.*?)src(\s*)=(\s*)[\'|\"]\/(.*?(?:[\.jpg|\.jpeg|\.png|\.gif|\.bmp|\.ico|\.webp]))[\'|\"](.*?)[\/]?(\s*)>/i";
            $content  = preg_replace($pregRule, '<img ${1} src="' . request()->domain() . '/${4}'.$t.'" ${5} />', $content);
        }

        return $content;
    }
}

if (!function_exists('getCityLocation')) {
    /**
     * 根据IP获取地区
     * @param  string $ip [description]
     * @return [type]     [description]
     */
    function getCityLocation($ip = '')
    {
        if (preg_match('/127\.0\.\d{1,3}\.\d{1,3}/i', $ip) || preg_match('/192\.168\.\d{1,3}\.\d{1,3}/i', $ip) || 'localhost' == $ip) {
            return ['location'=>'本地局域网'];
        } else {
            try {
                $res1 = httpRequest("https://sp0.baidu.com/8aQDcjqpAAV3otqbppnN2DJv/api.php?query={$ip}&resource_id=6006&t=" . getMsectime());
                $res1 = iconv('GB2312', 'UTF-8', $res1);
                $res1 = json_decode($res1, true);
                if ($res1 && $res1['status'] == '0') {
                    $data = current($res1['data']);
                    \think\Cookie::set("city_localtion", $data);
                    return $data;
                }

                /*            $res1 = httpRequest("http://ip.taobao.com/service/getIpInfo.php?ip=" .$ip);
                            $res1 = json_decode($res1,true);
                            if($res1 && $res1['code'] == '0'){
                                \think\Cookie::set("city_localtion", $res1['data']);
                                return $res1['data'];
                            }*/
            } catch (\Exception $e) {}
        }

        return false;
    }
}


if (!function_exists('Convert_GCJ02_To_BD09')) {
    /**
     * 中国正常GCJ02坐标---->百度地图BD09坐标
     * 腾讯地图用的也是GCJ02坐标
     * @param double $lat 纬度
     * @param double $lng 经度
     */
    function Convert_GCJ02_To_BD09($lat = 0, $lng = 0)
    {
        $x_pi  = M_PI;
        $x_pi  = $x_pi * 3000.0 / 180.0;
        $x     = $lng;
        $y     = $lat;
        $z     = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);
        $lng   = $z * cos($theta) + 0.0065;
        $lat   = $z * sin($theta) + 0.006;

        return ['lng' => $lng, 'lat' => $lat];
    }
}

if (!function_exists('Convert_BD09_To_GCJ02')) {
    /*
     * 百度地图BD09坐标---->中国正常GCJ02坐标
     * 腾讯地图用的也是GCJ02坐标
     * @param double $lat 纬度
     * @param double $lng 经度
     * @return array();
     */
    function Convert_BD09_To_GCJ02($lat = 0, $lng = 0)
    {
        $x_pi  = M_PI;
        $x_pi  = $x_pi * 3000.0 / 180.0;
        $x     = $lng - 0.0065;
        $y     = $lat - 0.006;
        $z     = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
        $lng   = $z * cos($theta);
        $lat   = $z * sin($theta);

        return ['lat' => $lat, 'lng' => $lng];
    }
}

if (!function_exists('get_filename')) {
    /**
     * 上传附件类型前台处理url获得文件名称(带拓展名)
     * @param string $value
     * @return mixed
     */
    function get_filename($value='')
    {
        $value_arr = explode('/',$value);
        $str = end($value_arr);
        return $str;
    }
}

if (!function_exists('gmSecondFormat')) {
    /**
     * 将秒数转为时间格式
     * @param intval $seconds  秒数
     * @param string $separator 分隔符
     * @return mixed
     */
    function gmSecondFormat($seconds = 0, $separator = '')
    {
        $timeStr = '';
        if (empty($seconds)) {
            if (empty($separator)) {
                $timeStr = "00小时00分钟00秒";
            } else {
                $timeStr = "00{$separator}00{$separator}00";
            }
        } else {
            $seconds = intval($seconds);
            $hours = intval($seconds/3600);
            if ($hours < 10) {
                $hours = '0'.$hours;
            }
            if (empty($separator)) {
                $timeStr = $hours."小时".gmdate('i分钟s秒', $seconds);
            } else {
                $timeStr = $hours.$separator.gmdate("i{$separator}s", $seconds);
            }
        }
        
        return $timeStr;
    }
}

if (!function_exists('checkAuthRule')) {
    /**
     * 验证代理贴牌的功能权限
     * @return mixed
     */
    function checkAuthRule($id)
    {
        $admin_id = session('admin_id');
        $info = \think\Db::name('admin')->where('admin_id',$admin_id)->field('parent_id,role_id')->find();
        if (!empty($info) && empty($info['parent_id']) && $info['role_id'] == -1){
            //创始人拥有无上权限
            return true;
        }

        $php_auth_function = tpCache('php.php_auth_function');
        $auth_function = !empty($php_auth_function) ? explode(',', $php_auth_function) : [];
        if (!empty($auth_function) && !in_array($id, $auth_function)){
            return false;
        }else{
            return true;
        }
    }
}

if (!function_exists('check_illegal')) {
    /**
     * 检测上传图片是否包含有非法代码
     * @return mixed
     */
    function check_illegal($image = '', $is_force = false, $file_ext = '')
    {
        $weapp_check_illegal_open = tpCache('weapp.weapp_check_illegal_open');
        if ($is_force || (is_numeric($weapp_check_illegal_open) && strval($weapp_check_illegal_open) === '0')) {
            try {
                $hexCode = file_get_contents($image);
                $patterns = [
                    '/file_put_contents/i',
                    '/__HALT_COMPILER\(\)/i',
                    '/\/script>/i',
                    '/<([^?]*)\?php/i',
                    '/<\?\=(\s+)/i',
                    '/(\s+)language(\s*)=(\s*)("|\')?php("|\')?/i',
                ];
                if ('svg' == trim($file_ext, '.')) {
                    $patterns[] = '/(onload|onerror)=/i'; // 针对 SVG 可能存在的恶意事件
                }
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $hexCode)) {
                        fclose($handle);
                        return false;
                    }
                }
//                if (file_exists($image)) {
//                    $resource = fopen($image, 'rb');
//                    $fileSize = filesize($image);
//                    fseek($resource, 0);
//                    $hexCode = fread($resource, $fileSize);
//                    fclose($resource);
//                    if (preg_match('#file_put_contents#i', $hexCode) || preg_match('#__HALT_COMPILER()#i', $hexCode) || preg_match('#/script>#i', $hexCode) || preg_match('#<([^?]*)\?php#i', $hexCode) || preg_match('#<\?\=(\s+)#i', $hexCode)) {
//                        return false;
//                    }
//                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('getUsersTplVersion')) {
    /**
     * 获取当前会员模板的版本号
     *
     * @return string
     */
    function getUsersTplVersion()
    {
        $ver = 'v1';
        $web_users_tpl_theme = tpCache('web.web_users_tpl_theme');
        if (empty($web_users_tpl_theme)) {
            $web_users_tpl_theme = 'users'; 
        }
        $version_txt_path = "./template/".THEME_STYLE_PATH."/{$web_users_tpl_theme}/version.txt";
        if (file_exists(realpath($version_txt_path))) {
            $fp      = fopen($version_txt_path, 'r');
            $content = fread($fp, filesize($version_txt_path));
            fclose($fp);
            $ver = $content ? $content : $ver;
        }
        return $ver;
    }
}

if (!function_exists('getUsersTpl2xVersion')) {
    /**
     * 获取当前V2会员模板的版本号
     *
     * @return string
     */
    function getUsersTpl2xVersion()
    {
        $ver = '';
        $web_users_tpl_theme = tpCache('web.web_users_tpl_theme');
        if (empty($web_users_tpl_theme)) {
            $web_users_tpl_theme = 'users';
        }
        $version_txt_path = "./template/".THEME_STYLE_PATH."/{$web_users_tpl_theme}/version_2x.txt";
        if (file_exists(realpath($version_txt_path))) {
            $fp      = fopen($version_txt_path, 'r');
            $content = fread($fp, filesize($version_txt_path));
            fclose($fp);
            $ver = $content ? $content : $ver;
        }
        return $ver;
    }
}

if (!function_exists('hex2rgba')) {
    /**
     * 16进制颜色代码转换为rgba,rgb格式
     *
     * @return string
     */
   function hex2rgba($color, $opacity = false, $raw = false) {
        $default = 'rgb(0,0,0)';
        //Return default if no color provided
        if(empty($color))
              return $default; 
        //Sanitize $color if "#" is provided 
        if ($color[0] == '#' ) {
            $color = substr( $color, 1 );
        }
        //Check if color has 6 or 3 characters and get values
        if (strlen($color) == 6) {
                $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
        } elseif ( strlen( $color ) == 3 ) {
                $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
        } else {
                return $default;
        }
 
        //Convert hexadec to rgb
        $rgb =  array_map('hexdec', $hex);
 
        if($raw){
            if($opacity){
                if(abs($opacity) > 1) $opacity = 1.0;
                array_push($rgb, $opacity);
            }
            $output = $rgb;
        }else{
            //Check if opacity is set(rgba or rgb)
            if($opacity){
                if(abs($opacity) > 1)
                    $opacity = 1.0;
                $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
            } else {
                $output = 'rgb('.implode(",",$rgb).')';
            }
        }
 
        //Return rgb(a) color string
        return $output;
    }
}

if (!function_exists('is_local_ip')) 
{
    /**
     * 简单判断当前访问站点是否本地
     * @param string $domain 不带协议的域名
     * @return boolean
     */
    function is_local_ip($domain = '')
    {
        $is_local = false;
        $sip = serverIP();
        $domain = !empty($domain) ? $domain : request()->host();
        if (preg_match('/127\.0\.\d{1,3}\.\d{1,3}/i', $domain) || preg_match('/192\.168\.\d{1,3}\.\d{1,3}/i', $domain) || 'localhost' == $domain || '127.0.0.1' == $sip) {
            $is_local = true;
        }

        return $is_local;
    }
}

if (!function_exists('upload_max_filesize'))
{
    /**
     * 获取当前可上传文件大小
     * @return boolean
     */
    function upload_max_filesize()
    {
        $file_size           = tpCache('basic.file_size');
        $postsize            = @ini_get('file_uploads') ? ini_get('post_max_size') : -1;
        $fileupload          = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : -1;
        $min_size            = intval($file_size) < intval($postsize) ? $file_size : $postsize;
        $min_size            = intval($min_size) < intval($fileupload) ? $min_size : $fileupload;
        $upload_max_filesize = intval($min_size) * 1024 * 1024;
        return $upload_max_filesize;
    }
}

if (!function_exists('image_accept_arr'))
{
    /**
     * 上传图片扩展名对应的accept，应用于选择上传图片时，系统自带的选择框里只列出指定图片扩展名的图片文件
     * @return boolean
     */
    function image_accept_arr($image_type = '')
    {
        $accept = '';
        if (!empty($image_type)) {
            if (is_string($image_type)) {
                $image_type_arr = explode(',', $image_type);
            } else {
                $image_type_arr = $image_type;
            }
            foreach ($image_type_arr as $key => $val) {
                if ($key > 0) $accept .= ',';
                if ('icon' == $val) {
                    $accept .= "image/x-icon";
                } else if ('svg' == $val) {
                    $accept .= "image/svg+xml";
                } else {
                    $accept .= "image/{$val}";
                }
            }
        } else {
            $accept = 'image/gif,image/jpg,image/jpeg,image/png,image/bmp,image/x-icon,image/webp,image/svg+xml';
        }

        return $accept;
    }
}

if (!function_exists('filename_preg_match'))
{
    /**
     * 正则判断文件名、文件路径的字符串是否合法
     * @return boolean
     */
    function filename_preg_match($filename = '')
    {
        if (preg_match('/(<|>|\"|\*|\:|\?|\|)/i', $filename)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('getPayApiConfig'))
{
    /**
     * 获取支付API配置信息
     */
    function getPayApiConfig($pay_id = 0, $pay_mark = '')
    {
        $payConfig = [];
        if (!empty($pay_id) && !empty($pay_mark)) {
            $where = [
                'pay_id' => intval($pay_id),
                'pay_mark' => trim($pay_mark)
            ];
            $payConfig = \think\Db::name('pay_api_config')->where($where)->getField('pay_info');
        }
        return !empty($payConfig) ? unserialize($payConfig) : [];
    }
}

if (!function_exists('updatePayApiConfig'))
{
    /**
     * 获取支付API配置信息
     */
    function updatePayApiConfig($pay_id = 0, $pay_mark = '', $pay_info = [])
    {
        $result = false;
        if (!empty($pay_id) && !empty($pay_mark)) {
            $where = [
                'pay_id' => intval($pay_id),
                'pay_mark' => trim($pay_mark)
            ];
            $update = [
                'pay_info' => serialize($pay_info),
                'update_time' => getTime(),
            ];
            $result = \think\Db::name('pay_api_config')->where($where)->update($update);
        }
        return !empty($result) ? true : false;
    }
}

if (!function_exists('getOpenMiniCodeConfig'))
{
    /**
     * 获取小程序配置信息
     */
    function getOpenMiniCodeConfig($get_type = '')
    {
        $payConfig = [];
        if (!empty($get_type)) $payConfig = tpSetting("OpenMinicode.conf_" . $get_type);
        if (empty($payConfig)) {
            $result = model('ShopPublicHandle')->getWeappInfo('Suibian');
            if (!empty($result)) {
                $payConfig = \think\Db::name('weapp_applets_config_list')->where(['applets_type'=>1, 'applets_mark'=>'weixin'])->getField('applets_config');
                return !empty($payConfig) ? unserialize($payConfig) : [];
            }
        } else {
            return !empty($payConfig) ? json_decode($payConfig, true) : [];
        }
    }
}

if (!function_exists('isIPv4OrIPv6'))
{
    /**
     * 判断是否属于ipv6
     * @param  [type]  $ip [description]
     * @return boolean     [description]
     */
    function isIPv4OrIPv6($ip) {
        if (isIPv4($ip)) {
            return 'ipv4';
        } else if (isIPv6($ip)) {
            return 'ipv6';
        } else {
            return false;
        }
    }
}

if (!function_exists('isIPv6'))
{
    /**
     * 判断是否属于ipv6
     * @param  [type]  $ip [description]
     * @return boolean     [description]
     */
    function isIPv6($ip) {
        return request()->isValidIP($ip, 'ipv6');
    }
}

if (!function_exists('isIPv4'))
{
    /**
     * 判断是否属于ipv4
     * @param  [type]  $ip [description]
     * @return boolean     [description]
     */
    function isIPv4($ip) {
        return request()->isValidIP($ip, 'ipv4');
    }
}

if (!function_exists('ipv6InRange'))
{
    /**
     * 判断ip是否属于某个ipv6范围
     * @param  [type] $ip         [description]
     * @param  [type] $rangeStart [description]
     * @param  [type] $rangeEnd   [description]
     * @return [type]             [description]
     * 测试
     *   $ipv6 = "2001:0db8:85a3:0000:0000:8a2e:0370:7334";
     *   $rangeStart = "2001:0db8:85a3:0000:0000:8a2e:0000:0000";
     *   $rangeEnd = "2001:0db8:85a3:0000:0000:8a2e:ffff:ffff";
     */
    function ipv6InRange($ip, $rangeStart, $rangeEnd) {
        // 将IPv6地址转换为二进制字符串
        $ipBinary = inet_pton($ip);
        $startBinary = inet_pton($rangeStart);
        $endBinary = inet_pton($rangeEnd);

        // 比较二进制字符串来确定IP是否在范围内
        return ($ipBinary >= $startBinary && $ipBinary <= $endBinary);
    }
}

if (!function_exists('containsFullwidthLetter'))
{
    /**
     * 判断字符串是否含有全角字母
     * @param  string $str [description]
     * @return [type]      [description]
     */
    function containsFullwidthLetter($str = '') {
        return preg_match('/[\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}]/u', $str);
    }
}
