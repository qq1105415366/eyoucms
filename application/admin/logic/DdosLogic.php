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

namespace app\admin\logic;

use think\Db;
use think\Model;
use think\Cache;

load_trait('controller/Jump');
class DdosLogic extends Model
{
    use \traits\controller\Jump;
    public $admin_info = array();
    public $admin_id = 0;
    public $admin_lang = 'cn';
    public $times;
    public static $ddosData = null;
    const FEATURE_MSG_CODE_100 = 100; // 多余文件
    const FEATURE_MSG_CODE_101 = 101; // 多余文件
    const FEATURE_MSG_CODE_110 = 110; // 多余安装目录
    const FEATURE_MSG_CODE_111 = 111; // 多余目录
    const FEATURE_MSG_CODE_500 = 500; // 疑似篡改
    const FEATURE_MSG_CODE_600 = 600; // 疑似木马
    const FEATURE_MSG_CODE_601 = 601; // 疑似木马
    const FEATURE_MSG_CODE_602 = 602; // 疑似木马，不支持修复
    const FEATURE_MSG_CODE_990 = 990; // 高危漏洞
    const FEATURE_MSG_CODE_991 = 991; // 高危漏洞

    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();
        $this->admin_info = session('admin_info');
        $this->admin_id = empty($this->admin_info) ? 0 : $this->admin_info['admin_id'];
        $this->admin_lang = get_admin_lang();
        $this->times = getTime();
        if (preg_match('/^(ddos_)/i', request()->action()) && null === self::$ddosData) {
            $ddosData = tpSetting('ddos', [], 'cn');
            self::$ddosData['ddos_feature_pattern'] = json_decode(base64_decode($ddosData['ddos_feature_pattern']), true);
            self::$ddosData['ddos_feature_imgpattern'] = json_decode(base64_decode($ddosData['ddos_feature_imgpattern']), true);
            self::$ddosData['ddos_feature_msg'] = json_decode(base64_decode($ddosData['ddos_feature_msg']), true);
            self::$ddosData['ddos_feature_msg_grade'] = json_decode(base64_decode($ddosData['ddos_feature_msg_grade']), true);
            self::$ddosData['ddos_feature_other'] = json_decode(base64_decode($ddosData['ddos_feature_other']), true);
            foreach ([10010,10013,10030,10035,10070,10090] as $key => $val) {
                if (isset(self::$ddosData['ddos_feature_other'][$val])) {
                    self::$ddosData['ddos_feature_other'][$val]['value'] = explode(',', self::$ddosData['ddos_feature_other'][$val]['value']);
                    if (10035 == $val) {
                        $arr = [];
                        foreach (self::$ddosData['ddos_feature_other'][$val]['value'] as $_k => $_v) {
                            $_v_arr = explode('--pattern--', $_v);
                            $arr[$_v_arr[0]]['value'] = $_v_arr[0];
                            $arr[$_v_arr[0]]['pattern'] = $_v_arr[1];
                        }
                        self::$ddosData['ddos_feature_other'][$val] = $arr;
                    }
                }
            }
        }
    }

    /*-----------------------ddos攻击脚本查杀 start-----------------------*/
    /**
     * 处理扫描附件
     * $achievepage 已扫描文件数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     */
    public function ddosHandelScanAttachment($doubtotal, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $batch = true, $limit = 50)
    {
        if (empty($achievepage)) {
            // 初始化第一批要处理扫描附件的逻辑
        }

        $msg                  = "";
        $result               = $this->getScanAttachmentData($achievepage, $limit);
        $info                 = $result['info'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['achievefile']  = $achievefile;
        $data['doubtotal']    = $doubtotal;
        $data['allscantotal']    = $allscantotal;

        if ($batch && $pagetotal > $achievepage) {
            $redata = $this->ddosInspectAttachment($info, $data['achievepage'], $data['achievefile'], $data['allscantotal'], $data['doubtotal'], $data['allpagetotal']);
            if (!empty($redata['msg'])) {
                $msg .= $redata['msg'];
            }
            $data['doubtotal'] = $redata['doubtotal'];
            $data['allscantotal'] = $redata['allscantotal'];
            $data['doubthtml'] = $this->ddos_doubtdata('html');
            $data['achievefile'] = $redata['achievefile'];
            $data['achievepage'] += count($info);
        }

        return [$msg, $data];
    }

    /**
     * 获取要扫描目录的数据
     */
    private function getScanAttachmentData($offset = 0, $limit = 0)
    {
        empty($limit) && $limit = 50;
        $info = [];
        $uploads_dirlist = $this->ddos_setting('web.uploads_dirlist');
        $dirlist = json_decode($uploads_dirlist, true);
        $result = array_slice($dirlist, 0, $limit, true);
        $ext = self::$ddosData['ddos_feature_other'][10080]['value'];
        $ext_pattern = str_replace(',', '|', $ext);
        foreach ($result as $key=>$val){
            $files = [];
            if (function_exists('glob')) {
                $files = glob("{$val}/{.[!.],}*.{".$ext."}", GLOB_BRACE);
            } else {
                $handle = opendir($val);
                while (false !== ($file = readdir($handle))) {
                    $file_path = "{$val}/{$file}";
                    if (!in_array($file, ['.','..']) && is_file($file_path) && preg_match('/\.('.$ext_pattern.')$/i', $file)) {
                        $files[] = $file_path;
                    }
                }
                closedir($handle);
            }
            $info_value = [];
            $info_value['dir'] = $val;
            $info_value['files'] = $files;
            $info[] = $info_value;
        }
        if (0 == $offset) {
            // 总附件目录数
            $pagetotal = (int)count($dirlist);
            $this->ddos_setting('web.uploads_dirlist_total', $pagetotal);
        } else {
            $pagetotal = (int)$this->ddos_setting('web.uploads_dirlist_total');
        }
        // 重新存储剩余目录数据
        array_splice($dirlist, 0, $limit); // 从索引0开始删除$limit个元素
        // 存储读取后的文件夹列表
        $this->ddos_setting('web.uploads_dirlist', json_encode($dirlist));

        return ['info' => $info, 'pagetotal' => $pagetotal];
    }

    /*
     * 逐个检查扫描的附件
     */
    private function ddosInspectAttachment($result, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $doubtotal = 0, $allpagetotal = 0)
    {
        $return_data = [
            'msg' => "",
            'achievefile' => $achievefile,
            'achievepage' => $achievepage,
            'doubtotal' => $doubtotal,
            'allscantotal' => $allscantotal,
            'doubtlist' => [],
        ];
        $auth_code = tpCache('system.system_auth_code');
        foreach ($result as $key => $val) {
            $return_data['achievepage'] += 1;
            $insertData = [];
            foreach ($val['files'] as $_k => $_v) {
                $filepath = $_v;
                $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filepath));
                if ('html' == $filetype) {
                    $content_tmp = @file_get_contents($filepath);
                    if (false !== $content_tmp && (empty($content_tmp) || 'dir' == $content_tmp)) {
                        continue;
                    }
                }
                $return_data['achievefile'] += 1;
                $md5key = md5('attachment'.$filepath.$auth_code.$this->admin_id);
                $file_excess = 1; // 多余文件
                $file_grade = self::FEATURE_MSG_CODE_100; // 异常类型
                $suspicious_html = "";

                // 检查文件是否含有病毒特征，排除压缩包
                if (!in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) { // 文件
                    $fd = realpath($filepath);
                    $fp = fopen($fd, "r");
                    $i = 0;
                    $suspicious_html = "";
                    while ($buffer = fgets($fp, 4096)) {
                        $i++;
                        $redata = $this->ddos_checkCodeFeatures($i, $buffer, $filetype);
                        if (!empty($redata['bool'])) {
                            $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                            // $suspicious_html .= empty($redata['msg']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : $redata['msg'];
                            $suspicious_html .= htmlspecialchars($this->ddos_cut_str($buffer,120,0));
                            break;
                        }
                    }
                    fclose($fp);
                }

                if (!empty($file_grade)) {
                    $return_data['doubtotal']++;
                    $return_data['doubtlist'][] = $filepath;
                    $insertData[] = [
                        'md5key'    => $md5key,
                        'file_name'   => $filepath,
                        'file_num'    => $return_data['achievefile'] + $return_data['achievepage'],
                        'file_total'  => $allpagetotal,
                        'file_doubt_total'    => $return_data['doubtotal'],
                        'file_excess' => $file_excess,
                        'file_grade' => $file_grade,
                        'html'        => empty($suspicious_html) ? '' : htmlspecialchars($suspicious_html),
                        'admin_id' => $this->admin_id,
                        'add_time'      => getTime(),
                        'update_time'      => getTime(),
                    ];
                }
            }

            if (!empty($insertData)) {
                try {
                    Db::name('ddos_log')->insertAll($insertData);
                } catch (\Exception $e) {
                    $return_data['msg'] .= '<span>' . '扫描失败：' . $e->getMessage() . '</span><br>';
                }
            }

            $return_data['allscantotal'] = $allscantotal + $return_data['achievefile'] + $return_data['achievepage'];
            if ($return_data['achievepage'] >= $allpagetotal) {
                $log_id = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('id');
                if (!empty($log_id)) {
                    Db::name('ddos_log')->where(['id'=>$log_id])->update([
                        'file_num' => $return_data['allscantotal'],
                        'file_total' => $return_data['allscantotal'],
                    ]);
                }
            }
        }

        return $return_data;
    }

    /**
     * 是否在特征库里的高危文件
     * @param  string $buffer [description]
     * @return [type]         [description]
     */
    private function ddos_checkCodeFeatures($i = 0, $buffer = '', $filetype = '')
    {
        $bool = false;
        $msg = '';
        $file_grade = 0;
        if (!empty($buffer)) {
            if (!empty(self::$ddosData['ddos_feature_pattern'])) {
                $filetype = strtolower($filetype);
                foreach (self::$ddosData['ddos_feature_pattern'] as $key => $patterns) {
                    if ('js' == $filetype) {
                        if (preg_match(self::$ddosData['ddos_feature_other'][10041]['value'], $buffer) || $i > 5) {
                            continue;
                        }
                    } else {
                        if (990001 <= $key && $key <= 990010) {
                            continue;
                        }
                    }

                    if (!empty($patterns['value']) && preg_match($patterns['value'], $buffer)) {
                        $bool = true;
                        $file_grade = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $key);
                        if (stristr($buffer, 'select name="${3:$2}" id="${4:$2}"')) {
                            var_dump($buffer);
                            var_dump($patterns['value']);
                            exit;
                        }
                        if ('js' == $filetype) {
                            if (990001 <= $key && $key <= 990010) {
                                $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                            }
                        } else {
                            $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                        }
                        break;
                    }
                }
            }
        }

        return [
            'bool' => $bool,
            'msg'  => $msg,
            'file_grade' => $file_grade,
        ];
    }

    /**
     * 是否在特征库里的高危图片
     * @param  string $buffer [description]
     * @return [type]         [description]
     */
    private function ddos_checkImgFeatures($filepath = '', $filetype = '')
    {
        $bool = false;
        $msg = '';
        $file_grade = 0;
        if (!empty(self::$ddosData['ddos_feature_imgpattern']) && file_exists($filepath)) {
            $filetype = strtolower($filetype);
            $fd = realpath($filepath);
            $fp      = fopen($fd, 'r');
            $fsize = filesize($fd);
            if (false === $fsize) {
                $buffer = 'ZmlsZXNpemXov5Tlm57mlofku7blpKflsI/lrZfoioLmlbDkuLpmYWxzZQ==';
                $buffer = base64_decode($buffer);
            } else {
                if (0 == $fsize) {
                    $buffer = '';
                } else {
                    $buffer = fread($fp, $fsize);
                }
            }
            fclose($fp);
            if (!empty($buffer)) {
                foreach (self::$ddosData['ddos_feature_imgpattern'] as $key => $patterns) {
                    if (!empty($patterns['value']) && preg_match($patterns['value'], $buffer)) {
                        $bool = true;
                        $file_grade = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $key);
                        $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                        break;
                    }
                }
            }
        }

        return [
            'bool' => $bool,
            'msg'  => $msg,
            'file_grade' => $file_grade,
        ];
    }

    private function ddos_cut_str($string, $sublen, $start = 0, $code = 'UTF-8') 
    {
        if ($code == 'UTF-8') {
            $pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
            preg_match_all($pa, $string, $t_string);
            if (count($t_string[0]) - $start > $sublen) {
                return join('', array_slice($t_string[0], $start, $sublen)) . "...";
            }
            return join('', array_slice($t_string[0], $start, $sublen));
        } else {
            $start = $start * 2;
            $sublen = $sublen * 2;
            $strlen = strlen($string);
            $tmpstr = '';
            for($i = 0; $i < $strlen; $i++) {
                if ($i >= $start && $i < ($start + $sublen)) {
                    if (ord(substr($string, $i, 1)) > 129) {
                        $tmpstr .= substr($string, $i, 2);
                    } else {
                        $tmpstr .= substr($string, $i, 1);
                    } 
                } 
                if (ord(substr($string, $i, 1)) > 129) {
                    $i++;
                }
            } 
            if (strlen($tmpstr) < $strlen) {
                $tmpstr .= "...";
            }

            return $tmpstr;
        } 
    }

    /**
     * 静态模式下的存放html目录集合
     * @return [type] [description]
     */
    private function ddos_html_dir_list()
    {
        $html_dir_list = [];
        $seoConfig = tpCache("seo");
        if (2 == $seoConfig['seo_pseudo']) {
            $html_arcdir = $seoConfig['seo_html_arcdir']; // 检测页面保存目录
            if (!empty($html_arcdir)) {
                $arcdir = trim($html_arcdir, '/');
                $arcdirArr = explode('/', $arcdir);
                $arcdir_tmp = current($arcdirArr);
                if (!empty($arcdir_tmp)) {
                    $html_dir_list[] = $arcdir_tmp;
                }
            }
            $arctype_list = Db::name('arctype')->field('dirpath,diy_dirpath')->select();
            if (!empty($arctype_list)) {
                foreach ($arctype_list as $key => $val) {
                    $dirpath = trim($val['dirpath'], '/');
                    $dirpathArr = explode('/', $dirpath);
                    $dirpath_tmp = current($dirpathArr);
                    if (!empty($dirpath_tmp) && !in_array($dirpath_tmp, $html_dir_list)) {
                        $html_dir_list[] = $dirpath_tmp;
                    }

                    $diy_dirpath = trim($val['diy_dirpath'], '/');
                    $diy_dirpathArr = explode('/', $diy_dirpath);
                    $diy_dirpath_tmp = current($diy_dirpathArr);
                    if (!empty($diy_dirpath_tmp) && !in_array($diy_dirpath_tmp, $html_dir_list)) {
                        $html_dir_list[] = $diy_dirpath_tmp;
                    }
                }
            }
        }

        return $html_dir_list;
    }

    public function ddos_setting($setting_key, $value = null)
    {
        $param = explode('.', $setting_key);
        $inc_type = $param[0];
        $name = $param[0].'_'.$param[1];
        $where = ['name'=>$name, 'inc_type'=>$inc_type, 'admin_id'=>$this->admin_id];
        $cacheKey = md5("admin-DdosLogic-ddos_setting-".json_encode($where));
        if (null === $value) {
            $value = cache($cacheKey);
            if (empty($value)) {
                $value = Db::name('ddos_setting')->where($where)->value('value');
                cache($cacheKey, $value, null, 'ddos_setting');
            }
            return $value;

        } else {
            $id = (int)Db::name('ddos_setting')->where($where)->value('id');
            if (!empty($id)) {
                $r = Db::name('ddos_setting')->where(['id'=>$id])->update([
                        'value'=>$value,
                        'update_time'=>getTime(),
                    ]);
            } else {
                $r = Db::name('ddos_setting')->where($where)->insert([
                        'name' => $name,
                        'value' => $value,
                        'inc_type' => $inc_type,
                        'admin_id' => $this->admin_id,
                        'add_time'=>getTime(),
                        'update_time'=>getTime(),
                    ]);
            }
            if ($r !== false) {
                cache($cacheKey, $value, null, 'ddos_setting');
                return $value;
            }
        }

        return false;
    }

    /**
     * 获取对应版本的易优cms源码文件列表
     * @return [type] [description]
     */
    public function ddos_eyou_source_files()
    {
        $version = getVersion();
        $filename = 'filelist.txt';
        if (version_compare($version,'v1.7.1','<')) {
            $filename = 'filelist_1219.txt';
        }
        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/repair/'.$version.'/'.$filename;
        $response = @httpRequest2($url, 'GET', [], [], 3);
        if (!stristr($response, 'application/common.php')) {
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $response = @file_get_contents($url, false, $context);
        }
        if (stristr($response, 'application/common.php')) {
            $path = DATA_PATH.'conf/eyoufilelist.txt';
            if (!file_exists($path) || is_writeable($path)) {
                try {
                    $fp = fopen($path, "w+");
                    if (!empty($fp) && fwrite($fp, $response)) {
                        fclose($fp);
                    }
                } catch (\Exception $e) {}
            }
        } else {
            $response = getVersion('eyoufilelist', '');
        }

        if (empty($response)) {
            $this->error('文件 data/conf/eyoufilelist.txt 没有读写权限', null, '', 20);
        }
        $response = preg_replace("#[\r\n]{1,}#", "\n", $response);
        $filelist = explode("\n", $response);
        // 追加自定义模型文件
        $channeltypefiles = [];
        if (is_dir(ROOT_PATH.'data/model/application')) {
            $this->ddos_getDirFile('data/model/application', 'application', $channeltypefiles);
        }
        $row = Db::name('channeltype')->where(['ifsystem'=>0])->order('id asc')->select();
        foreach ($row as $key => $val) {
            foreach ($channeltypefiles as $_k => $_v) {
                $_v = str_replace('CustomModel', $val['ctl_name'], $_v);
                $_v = str_replace('custommodel', $val['nid'], $_v);
                $filelist[] = $_v.'|';
            }
        }
        // 追加多语言的语言包文件
        $row = Db::name('language')->order('id asc')->select();
        foreach ($row as $key => $val) {
            $filelist[] = "application/lang/{$val['mark']}.php|";
        }
        // 重新遍历数组，以文件路径作为键名
        foreach ($filelist as $key => $val) {
            if (empty($val)) {
                unset($filelist[$key]);
                continue;
            }
            if (preg_match('/^login\.php\|/i', $val)) {
                // 更新后台入口文件信息
                $val = preg_replace('/^login\.php/i', $this->getAdminbasefile(false), $val);
            }
            $filename = preg_replace('/^([^\|]+)\|(.*)$/i', '${1}', $val);
            $filelist[md5($filename)] = $val;
            unset($filelist[$key]);
        }

        $this->ddos_setting('sys.eyoufilelist', json_encode($filelist));

        return $filelist;
    }

    /**
     * 扫描后，追加可疑文件的页面html
     * @param  [type] $num_ky [description]
     * @param  [type] $fd     [description]
     * @param  [type] $rows_text      [description]
     * @param  [type] $buffer [description]
     * @param  [type] $md5key [description]
     * @return [type]         [description]
     */
    public function ddos_doubtdata($arr_attr = null)
    {
        $html = "";
        $file_total = $file_doubt_total = 0;
        $redata = [];
        $list = Db::name('ddos_log')->where(['admin_id' => $this->admin_id, 'file_grade'=>['gt',0]])->order('file_grade desc, file_excess asc')->select();
        if (!empty($list)) {
            $is_trojan_horse = 0; // 是否有挂马文件
            $down_url = url('Security/ddos_download_file');
            foreach ($list as $key => $val) {
                if ($file_total < $val['file_total']) {
                    $file_total = $val['file_total'];
                }
                if ($file_doubt_total < $val['file_doubt_total']) {
                    $file_doubt_total = $val['file_doubt_total'];
                }
                $file_grade = intval($val['file_grade']);
                $file_grade = $file_grade + intval($val['file_excess']);
                $feature_msg_value = self::$ddosData['ddos_feature_msg'][$file_grade]['value'];
                $val['html'] = htmlspecialchars_decode($val['html']);
                $val['html'] = $feature_msg_value . $val['html'];
                $file_all_name = !empty($val['file_name']) ? trim($val['file_name'], '/') : '';
                $file_alias_name = preg_replace('/^(.*)(\/|\\\)([^\/\\\]+)$/i', '${3}', $file_all_name);
                // $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $file_all_name)); // pathinfo($file_all_name, PATHINFO_EXTENSION);
                $grade_value = self::$ddosData['ddos_feature_msg_grade'][$file_grade]['value'];
                $operation_html = "";
                $opt_arr = self::$ddosData['ddos_feature_msg'][$file_grade]['opt'];
                if ('replace_tips' == $opt_arr['event']) {
                    if (preg_match('/^layer-(.*)$/i', $opt_arr['value'])) {
                        $tips = preg_replace('/^layer-(.*)$/i', '${1}', $opt_arr['value']);
                        $opt_arr['onclick'] = " onclick='showErrorAlert(\"{$tips}\", 5);' ";
                        $opt_arr['value'] = '查看';
                    }
                } else {
                    if (stristr($feature_msg_value, '修复')) {
                        $opt_arr['event'] = 'replace';
                        $opt_arr['value'] = '修复';
                    } else if (stristr($feature_msg_value, '删除')) {
                        $opt_arr['event'] = 'del';
                        $opt_arr['value'] = '删除';
                    }
                }

                if ('see' == $opt_arr['event']) {
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='whitelist_add(this);'>加入白名单</a><i></i><a href='{$opt_arr['value']}' target='_blank'>查看</a>";
                } else if ('replace_tips' == $opt_arr['event']) {
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='whitelist_add(this);'>加入白名单</a><i></i><a href='javascript:void(0);' {$opt_arr['onclick']}>{$opt_arr['value']}</a>";
                } else if ('replace' == $opt_arr['event']) {
                    $tips_title = '执行后，将从官方服务器下载对应版本文件进行覆盖修复';
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='whitelist_add(this);'>加入白名单</a><i></i><a href='javascript:void(0);' data-md5key='{$val['md5key']}' data-title='{$tips_title}' onclick='replacefile(this, \"single\");'>{$opt_arr['value']}</a>";
                } else if ('del' == $opt_arr['event']) {
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='whitelist_add(this);'>加入白名单</a><i></i><a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='delfile(this, \"single\");'>{$opt_arr['value']}</a>";
                }

                $grade_color_class = "";
                switch ($val['file_grade']) {
                    case self::FEATURE_MSG_CODE_990:
                    case self::FEATURE_MSG_CODE_991:
                        $grade_color_class = "red";
                        break;

                    case self::FEATURE_MSG_CODE_500:
                        $grade_color_class = "orange";
                        break;

                    case self::FEATURE_MSG_CODE_600:
                    case self::FEATURE_MSG_CODE_601:
                    case self::FEATURE_MSG_CODE_602:
                        $is_trojan_horse = 1;
                        $grade_color_class = "orange";
                        break;

                    case self::FEATURE_MSG_CODE_100:
                    case self::FEATURE_MSG_CODE_101:
                    case self::FEATURE_MSG_CODE_110:
                    case self::FEATURE_MSG_CODE_111:
                        $grade_color_class = "";
                        break;
                }
                $html .=<<<EOF
                    <li class="li_problem">
                        <span class="label {$grade_color_class}">{$grade_value}</span>
                        <div class="name">
                            <a href="{$down_url}&md5key={$val['md5key']}" target="_blank">{$file_alias_name}</a><em>|</em>{$val['html']}
                            <div class="ads">路径：根目录/{$file_all_name}</div>
                        </div>
                        <div class="operation">
                            {$operation_html}
                        </div>
                    </li>
EOF;
            }

            if (1 == $is_trojan_horse) {
                $msg = self::$ddosData['ddos_feature_msg'][1]['value'];
                $html .=<<<EOF
                    <li>
                        <div>
                            {$msg}
                        </div>
                    </li>
EOF;
            }
        }

        $redata['html'] = $html;
        $redata['file_total'] = $file_total;
        $redata['file_doubt_total'] = $file_doubt_total;

        if (null === $arr_attr) {
            return $redata;
        } else {
            return empty($redata[$arr_attr]) ? '' : $redata[$arr_attr];
        }
    }

    /**
     * ddos_log表清空、重置ID、修复表
     * @return [type] [description]
     */
    public function ddos_log_reset()
    {
        $Prefix = config('database.prefix');
        $isTable = Db::query('SHOW TABLES LIKE \''.$Prefix.'ddos_log\'');
        if (!empty($isTable)) {
            Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->delete(true);
            @Db::execute("ALTER TABLE `{$Prefix}ddos_log` AUTO_INCREMENT 1");
            @Db::query("REPAIR TABLE `{$Prefix}ddos_log`");
        }

        Db::name('ddos_setting')->where([
                'admin_id'=>$this->admin_id,
                'inc_type'=>['NOTIN', ['sys']],
            ])->delete(true);
        @Db::execute("ALTER TABLE `{$Prefix}ddos_setting` AUTO_INCREMENT 1");
        @Db::query("REPAIR TABLE `{$Prefix}ddos_setting`");
        Cache::clear('ddos_setting');
    }

    /**
     * 递归读取文件夹文件
     */
    public function ddos_getDirFile($directory, $dir_name = '', &$arr_file = array(), $ignore_dirs = [])
    {
        if (!file_exists($directory)) {
            return false;
        }
        $ignore_dirs_pattern = implode('|', $ignore_dirs);
        $ignore_dirs_pattern = str_replace('/', '\/', $ignore_dirs_pattern);

        $self = '';//'DdosLogic.php';
        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if (!in_array($file, ['.','..']) && is_dir("$directory/$file")) {
                if ($dir_name) {
                    $dir_name_tmp = "$dir_name/$file";
                } else {
                    $dir_name_tmp = $file;
                }
                if ($this->ddos_is_gb2312($dir_name_tmp) && function_exists('mb_convert_encoding')){
                    $dir_name_tmp = mb_convert_encoding($dir_name_tmp,'UTF-8','GBK');
                }
                if (!in_array($dir_name_tmp, $ignore_dirs)) {
                    $is_recursion = false;
                    if (!empty($ignore_dirs_pattern)) {
                        if (!preg_match('/\/('.$ignore_dirs_pattern.')\//i', "/{$dir_name_tmp}/")) {
                            $is_recursion = true;
                        }
                    } else {
                        $is_recursion = true;
                    }

                    if ($is_recursion === true) {
                        $arr_dir[] = $dir_name_tmp;
                        $this->ddos_getDirFile("$directory/$file", $dir_name_tmp, $arr_file, $ignore_dirs);
                    }
                }
            } else {
                if($file != $self){
                    if (!in_array($file, ['.','..']) && preg_match(self::$ddosData['ddos_feature_other'][10050]['value'], $file)) {
                        if ($dir_name) {
                            $file_tmp = "$dir_name/$file";
                        } else {
                            $file_tmp = "$file";
                        }

                        if ($this->ddos_is_gb2312($file_tmp) && function_exists('mb_convert_encoding')){
                            $file_tmp = mb_convert_encoding($file_tmp,'UTF-8','GBK');
                        }

                        $arr_file[] = $file_tmp;
                    } 
                }
            } 
        }
        $mydir->close();

        return $arr_file;
    }

    /**
     * 判断字符串是否gb2312编码
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    private function ddos_is_gb2312($str)
    {
        for($i=0; $i<strlen($str); $i++) {
            $v = ord( $str[$i] );
            if( $v > 127) {
                if( ($v >= 228) && ($v <= 233) )
                {
                    if( ($i+2) >= (strlen($str) - 1)) return true; // not enough characters
                    $v1 = ord( $str[$i+1] );
                    $v2 = ord( $str[$i+2] );
                    if( ($v1 >= 128) && ($v1 <=191) && ($v2 >=128) && ($v2 <= 191) ) // utf编码
                        return false;
                    else
                        return true;
                }
            }
        }
        return true;
    }

    /**
     * 获取指定目录的文件夹列表
     * @param  array  $dirs  [description]
     * @param  array  &$list [description]
     * @return [type]        [description]
     */
    public function get_dir_list($dirs = [], &$list = [])
    {
        foreach ($dirs as $key => $val) {
            $list[] = $val;
            if (is_dir(ROOT_PATH.$val)) {
                $this->ddos_getDir(ROOT_PATH.$val, $val, $list);
            }
        }

        return $list;
    }

    /**
     * 递归读取文件夹，返回文件夹列表
     */
    public function ddos_getDir($directory, $dir_name = '', &$arr_dir = array(), $ignore_dirs = [])
    {
        if (!file_exists($directory)) {
            return false;
        }
        $ignore_dirs_pattern = implode('|', $ignore_dirs);
        $ignore_dirs_pattern = str_replace('/', '\/', $ignore_dirs_pattern);

        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if (!in_array($file, ['.','..']) && is_dir("$directory/$file")) {
                if ($dir_name) {
                    $dir_name_tmp = "$dir_name/$file";
                } else {
                    $dir_name_tmp = $file;
                }
                if ($this->ddos_is_gb2312($dir_name_tmp) && function_exists('mb_convert_encoding')){
                    $dir_name_tmp = mb_convert_encoding($dir_name_tmp,'UTF-8','GBK');
                }
                if (!in_array($dir_name_tmp, $ignore_dirs)) {
                    $is_recursion = false;
                    if (!empty($ignore_dirs_pattern)) {
                        if (!preg_match('/\/('.$ignore_dirs_pattern.')\//i', "/{$dir_name_tmp}/")) {
                            $is_recursion = true;
                        }
                    } else {
                        $is_recursion = true;
                    }

                    if ($is_recursion === true) {
                        $arr_dir[] = $dir_name_tmp;
                        $this->ddos_getDir("$directory/$file", $dir_name_tmp, $arr_dir, $ignore_dirs);
                    }
                }
            } 
        }
        $mydir->close();

        return $arr_dir;
    }

    /**
     * 获取后台登录入口文件
     * @return [type] [description] 
     */
    public function getAdminbasefile($route = true)
    {
        $new_basefile = request()->baseFile();
        $web_adminbasefile = tpCache('global.web_adminbasefile');
        $web_adminbasefile = !empty($web_adminbasefile) ? $web_adminbasefile : ROOT_DIR.'/login.php';
        if (stristr($web_adminbasefile, 'index.php') || $new_basefile != $web_adminbasefile) {
            $web_adminbasefile = $new_basefile;
        }
        if (false === $route) {
            $arr = explode('/', $web_adminbasefile);
            $web_adminbasefile = end($arr);
        }

        return $web_adminbasefile;
    }

    /**
     * 处理扫描文件
     * $achievepage 已扫描文件数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     */
    public function ddosHandelScanFiles($doubtotal, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $batch = true, $limit = 50)
    {
        if (empty($achievepage)) {
            // 初始化第一批要处理扫描附件的逻辑
        }

        $msg                  = "";
        $result               = $this->getScanFilesData($achievepage, $limit);
        $info                 = $result['info'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['achievefile']  = $achievefile;
        $data['doubtotal']    = $doubtotal;
        $data['allscantotal']    = $allscantotal;

        if ($batch && $pagetotal > $achievepage) {
            $redata = $this->ddosInspectFiles($info, $data['achievepage'], $data['achievefile'], $data['allscantotal'], $data['doubtotal'], $data['allpagetotal']);
            if (!empty($redata['msg'])) {
                $msg .= $redata['msg'];
            }
            $data['doubtotal'] = $redata['doubtotal'];
            $data['allscantotal'] = $redata['allscantotal'];
            $data['doubthtml'] = $this->ddos_doubtdata('html');
            $data['achievefile'] = $redata['achievefile'];
            $data['achievepage'] += count($info);
        }

        return [$msg, $data];
    }

    /**
     * 获取要扫描目录的数据
     */
    private function getScanFilesData($offset = 0, $limit = 0)
    {
        empty($limit) && $limit = 50;
        $info = [];
        $source_dirlist = $this->ddos_setting('web.source_dirlist');
        $dirlist = json_decode($source_dirlist, true);
        $result = array_slice($dirlist, 0, $limit, true);
        $ext = self::$ddosData['ddos_feature_other'][10051]['value'];
        $ext_pattern = str_replace(',', '|', $ext);
        foreach ($result as $key=>$val){
            if ('/' == $val) {
                $files = [];
                if (function_exists('glob')) {
                    $files = glob("{.[!.],}*.{".$ext."}", GLOB_BRACE);
                } else {
                    $handle = opendir($val);
                    while (false !== ($file = readdir($handle))) {
                        $file_path = "{$file}";
                        if (!in_array($file, ['.','..']) && is_file($file_path) && preg_match('/\.('.$ext_pattern.')$/i', $file)) {
                            $files[] = $file_path;
                        }
                    }
                    closedir($handle);
                }
                $root_dirs = glob('*', GLOB_ONLYDIR);
                $files = array_merge($root_dirs, $files);
            } else {
                $files = [];
                if (function_exists('glob')) {
                    $files = glob("{$val}/{.[!.],}*.{".$ext."}", GLOB_BRACE);
                } else {
                    $handle = opendir($val);
                    while (false !== ($file = readdir($handle))) {
                        $file_path = "{$val}/{$file}";
                        if (!in_array($file, ['.','..']) && is_file($file_path) && preg_match('/\.('.$ext_pattern.')$/i', $file)) {
                            $files[] = $file_path;
                        }
                    }
                    closedir($handle);
                }
            }
            $info_value = [];
            $info_value['dir'] = $val;
            $info_value['files'] = $files;
            $info[] = $info_value;
        }
        if (0 == $offset) {
            // 总文件目录数
            $pagetotal = (int)count($dirlist);
            $this->ddos_setting('web.source_dirlist_total', $pagetotal);
        } else {
            $pagetotal = (int)$this->ddos_setting('web.source_dirlist_total');
        }
        // 重新存储剩余目录数据
        array_splice($dirlist, 0, $limit); // 从索引0开始删除$limit个元素
        // 存储读取后的文件夹列表
        $this->ddos_setting('web.source_dirlist', json_encode($dirlist));

        return ['info' => $info, 'pagetotal' => $pagetotal];
    }

    /*
     * 逐个检查扫描的文件
     */
    private function ddosInspectFiles($result, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $doubtotal = 0, $allpagetotal = 0)
    {
        $return_data = [
            'msg' => "",
            'achievefile' => $achievefile,
            'achievepage' => $achievepage,
            'doubtotal' => $doubtotal,
            'allscantotal' => $allscantotal,
            'doubtlist' => [],
        ];
        $auth_code = tpCache('system.system_auth_code');
        if (!empty($result)) {
            $html_dir_list = $this->ddos_html_dir_list();
            $dir_pattern = implode('|', $html_dir_list);
            // 把生成静态html目录，也算是官方目录名单里
            if (!empty($html_dir_list)) {
                self::$ddosData['ddos_feature_other'][10034]['value'] = str_replace('|追加特定目录|', "|{$dir_pattern}|", self::$ddosData['ddos_feature_other'][10034]['value']);
            }
        }
        // 后台入口文件
        $web_adminbasefile = $this->getAdminbasefile(false);
        // 官方对应版本的文件列表
        $eyoufilelist = json_decode($this->ddos_setting('sys.eyoufilelist'), true);
        empty($eyoufilelist) && $eyoufilelist = [];
        // 扫描白名单列表
        $whitelist = $this->ddos_whitelist_data();

        foreach ($result as $key => $val) {
            $return_data['achievepage'] += 1;
            $insertData = [];
            foreach ($val['files'] as $_k => $_v) {
                $filepath = $_v;
                $return_data['achievefile'] += 1;
                $md5key = md5('files'.$filepath.$auth_code.$this->admin_id);
                $file_excess = 0; // 是否多余文件/目录
                $file_excess_dir = 0; // 是否多余目录里的文件/目录
                $file_grade = 0; // 异常类型
                $is_eyoufile = true;
                $suspicious_html = "";
                if (is_dir($filepath)) {
                    // 在白名单列表里
                    if (in_array($filepath, $whitelist)) {
                        continue;
                    }
                    // 在官方目录名单里
                    if (preg_match(self::$ddosData['ddos_feature_other'][10034]['value'], "{$filepath}/")) {
                        if (preg_match('/^install(.*)$/i', "{$filepath}/")) {
                            $file_grade = self::FEATURE_MSG_CODE_110;
                        }
                    }
                    // 不在官方目录名单里
                    else {
                        $file_excess = 1;
                        $file_grade = self::FEATURE_MSG_CODE_110;
                    }
                }
                else {
                    $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filepath));

                    if (0 == $file_grade) {
                        // 在官方目录名单里
                        if (preg_match(self::$ddosData['ddos_feature_other'][10034]['value'], $filepath) || preg_match('/^([^\/\\\]+)$/i', $filepath)) {
                            // 在官方对应版本中，是否存在该文件
                            if (!empty($eyoufilelist)) {
                                $filepath_tmp = $filepath;
                                if (preg_match('/^(install|data\/(session|sqldata))(.*)$/i', $filepath_tmp)) {
                                    $filepath_tmp = preg_replace('/^(install|data\/(session|sqldata))([^\/]*)\/(.*)$/i', '${1}/${4}', $filepath_tmp);
                                }
                                if (!empty($filepath_tmp) && !isset($eyoufilelist[md5($filepath_tmp)])) {
                                    if (!preg_match(self::$ddosData['ddos_feature_other'][10033]['value'], $filepath_tmp)) { // 排除在特定目录/文件
                                        if (!preg_match(self::$ddosData['ddos_feature_other'][10031]['value'], $filepath_tmp) || preg_match(self::$ddosData['ddos_feature_other'][10032]['value'], $filepath_tmp)) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                            $file_excess = 1;
                                            $is_eyoufile = false;
                                        }
                                    }
                                }
                            }
                        } else {
                            $file_excess_dir = 1;
                        }

                        // 如果不是易优本身文件，在特定的目录中，不应该存在的类型文件
                        if (false === $is_eyoufile && 0 == $file_excess_dir) {
                            if (0 == $file_grade) {
                                if ('html' != $filetype) {
                                    // 生成静态目录里存在html以外的其他文件
                                    if (!empty($dir_pattern) && preg_match('/^('.$dir_pattern.')\//i', $filepath)) {
                                        $file_grade = self::FEATURE_MSG_CODE_100;
                                        $file_excess = 1;
                                    }
                                }
                            }

                            if (0 == $file_grade) {
                                // 指定扩展名的多余文件
                                if (!in_array($filepath, self::$ddosData['ddos_feature_other'][10030]['value'])) {
                                    // 图片文件，在指定目录如果存在，肯定是多余图片，有可疑行为。
                                    if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10070]['value'])) {
                                        if (1 == count(explode('/', $filepath))) {
                                            if (!in_array($filepath, ['favicon.ico'])) {
                                                $file_grade = self::FEATURE_MSG_CODE_100;
                                            }
                                        } else if (preg_match(self::$ddosData['ddos_feature_other'][10071]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10072]['value'], $filepath)) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                        }
                                    }
                                    // 压缩包文件，在指定目录如果存在，肯定是多余文件，有可疑行为。
                                    if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) {
                                        if (preg_match(self::$ddosData['ddos_feature_other'][10091]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10092]['value'], $filepath)) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                        }
                                    }
                                    // 其他，比如：根目录只能存在特定的php文件、特定目录不能存在php等动态语言文件
                                    if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10010]['value'])) {
                                        if (1 == count(explode('/', $filepath))) {
                                            if (!in_array($filepath, ['index.php',$web_adminbasefile])) {
                                                $file_grade = self::FEATURE_MSG_CODE_100;
                                            }
                                        } else if (preg_match(self::$ddosData['ddos_feature_other'][10011]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10012]['value'], $filepath)) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                        }
                                    }

                                    if (!empty($file_grade)) {
                                        $file_excess = 1;
                                    }
                                }
                            }
                        }
                    }

                    // 检查文件是否含有病毒特征，排除压缩包
                    if (in_array($filetype, self::$ddosData['ddos_feature_other'][10070]['value'])) { // 图片
                        $redata = $this->ddos_checkImgFeatures($filepath, $filetype);
                        if (!empty($redata['bool'])) {
                            $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                        }
                    }
                    else if (!in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) { // 文件
                        $fd = realpath($filepath);
                        $fp = fopen($fd, "r");
                        $i = 0;
                        $suspicious_html = "";
                        while ($buffer = fgets($fp, 4096)) {
                            $i++;
                            $redata = $this->ddos_checkCodeFeatures($i, $buffer, $filetype);
                            if (!empty($redata['bool'])) {
                                $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                                $suspicious_html .= htmlspecialchars($this->ddos_cut_str($buffer,120,0));
                                break;
                            }
                        }
                        fclose($fp);

                        if (!empty(self::$ddosData['ddos_feature_other'][10035][$filepath])) {
                            $buffer = @file_get_contents($fd);
                            if (0 == $file_grade && !preg_match(self::$ddosData['ddos_feature_other'][10035][$filepath]['pattern'], $buffer)) {
                                $file_grade = self::FEATURE_MSG_CODE_600;
                            }
                        }
                    }

                    // 如果是多余目录里的多余文件，而且是有异常
                    if (1 == $file_excess_dir && !empty($file_grade)) {
                        $file_excess = 1;
                    }
                    // 指定目录里的疑似木马文件，在列表中提示自行修复，不支持在线修复
                    else if (self::FEATURE_MSG_CODE_600 == $file_grade) {
                        if (!in_array($filetype, self::$ddosData['ddos_feature_other'][10010]['value']) && preg_match(self::$ddosData['ddos_feature_other'][10011]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10012]['value'], $filepath)) {
                            if (!empty(self::$ddosData['ddos_feature_other'][10013]['value']) && in_array($filetype, self::$ddosData['ddos_feature_other'][10013]['value'])) {
                                $file_excess = 1;
                            } else {
                                $file_excess = 2;
                            }
                        }
                    }
                    // 检测文件正常的后续逻辑
                    else {
                        // 检测有指纹码的文件是否被篡改
                        if (isset($eyoufilelist[md5($filepath)])) {
                            $fparr = explode('|', $eyoufilelist[md5($filepath)]);
                            if (!empty($fparr[1])) {
                                $fp = fopen($filepath, 'r');
                                $ct = fread($fp, filesize($filepath));
                                fclose($fp);
                                $ct = preg_replace("/\/\*\*[\r\n]{1,}(.*)[\r\n]{1,} \*\//sU", '', $ct);
                                $cthash = md5($ct);
                                if ($cthash != $fparr[1]) {
                                    $file_grade = self::FEATURE_MSG_CODE_500;
                                }
                            }
                        }
                    }

                    // 在白名单列表里
                    if (in_array($file_grade, [self::FEATURE_MSG_CODE_100,self::FEATURE_MSG_CODE_101,self::FEATURE_MSG_CODE_500]) && in_array($filepath, $whitelist)) {
                        continue;
                    }
                }

                if (!empty($file_grade)) {
                    $return_data['doubtotal']++;
                    $return_data['doubtlist'][] = $filepath;
                    $insertData[] = [
                        'md5key'    => $md5key,
                        'file_name'   => $filepath,
                        'file_num'    => $return_data['achievefile'],
                        'file_total'  => $allpagetotal,
                        'file_doubt_total'    => $return_data['doubtotal'],
                        'file_excess' => $file_excess,
                        'file_grade' => $file_grade,
                        'html'        => empty($suspicious_html) ? '' : htmlspecialchars($suspicious_html),
                        'admin_id' => $this->admin_id,
                        'add_time'      => getTime(),
                        'update_time'      => getTime(),
                    ];
                }
            }

            if (!empty($insertData)) {
                try {
                    Db::name('ddos_log')->insertAll($insertData);
                } catch (\Exception $e) {
                    $return_data['msg'] .= '<span>' . '扫描失败：' . $e->getMessage() . '</span><br>';
                }
            }

            $return_data['allscantotal'] = $allscantotal + $return_data['achievefile'];
            if ($return_data['achievepage'] >= $allpagetotal) {
                $log_id = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('id');
                if (!empty($log_id)) {
                    Db::name('ddos_log')->where(['id'=>$log_id])->update([
                        'file_num' => $return_data['allscantotal'],
                        'file_total' => $return_data['allscantotal'],
                    ]);
                }
            }
        }

        return $return_data;
    }

    /**
     * 获取扫描白名单列表
     * @return [type] [description]
     */
    public function ddos_whitelist_data()
    {
        $result = Db::name('ddos_whitelist')->where(['admin_id'=>$this->admin_id])->column('file_name');

        return empty($result) ? [] : $result;
    }

    /**
     * 业务处理 - 删除扫描后的文件
     * @return [type] [description]
     */
    public function ddos_delfile_handle($result = [])
    {
        $msg = '';
        $code = true;
        $errnum = 0;
        $md5keys = [];
        $feature_other = tpSetting('ddos.ddos_feature_other', [], 'cn');
        $feature_other_arr = json_decode(base64_decode($feature_other), true);
        foreach ($result as $key => $val) {
            $filename = !empty($val['file_name']) ? trim($val['file_name'], '/') : '';
            if (!empty($filename) && !preg_match('/^([\s\/\\\]+)$/i', $filename)) {
                if (is_dir($filename)) {
                    $filename = str_replace('\\', '/', $filename);
                    $filename = trim($filename, '/');
                    if (!preg_match('/^([\/\\\]*)$/i', $filename)) {
                        try {
                            $code = delFile(ROOT_PATH.$filename, true);
                        } catch (\Exception $e) {
                            $errnum++;
                            $code = false;
                            $msg = $e->getMessage();
                            break;
                        }
                        if ($code !== false) {
                            $del_ids = [$val['id']];
                            $md5keys[] = $val['md5key'];
                            // 删除目录下的所有文件记录
                            $row = Db::name('ddos_log')->field('id,md5key')->where([
                                    'file_name'=>['LIKE', "{$val['file_name']}/%"],
                                    'admin_id'=>$this->admin_id,
                                ])->select();
                            if (!empty($row)) {
                                foreach ($row as $_k => $_v) {
                                    $del_ids[] = $_v['id'];
                                    $md5keys[] = $_v['md5key'];
                                }
                            }
                            Db::name('ddos_log')->where(['id'=>['IN', $del_ids], 'admin_id'=>$this->admin_id])->delete();
                        }
                    }
                } else if (is_file($filename)) {
                    $filetype = preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filename);
                    $phpfile = strtolower(stristr($filename,'.php'));
                    if ($phpfile || '*' == $feature_other_arr[10060]['value'] || in_array($filetype, explode(',', $feature_other_arr[10060]['value']))) {
                        try {
                            $code = unlink('./'.$filename);
                        } catch (\Exception $e) {
                            $errnum++;
                            $code = false;
                            $msg = $e->getMessage();
                            break;
                        }
                        if ($code !== false) {
                            $md5keys[] = $val['md5key'];
                            Db::name('ddos_log')->where(['id'=>$val['id']])->delete();
                        }
                    }
                }
            }
        }
        $return_data = [
            'code' => $code,
            'msg' => $msg,
            'md5keys' => $md5keys,
            'errnum' => $errnum,
        ];
        return $return_data;
    }

    /**
     * 业务处理 - 替换扫描后的文件
     * @return [type] [description]
     */
    public function ddos_replacefile_handle($result = [])
    {
        $msg = '';
        $code = true;
        $errnum = 0;
        $md5keys = [];
        $version = getVersion();
        if (version_compare($version,'v1.6.9','<')) {
            $code = false;
            $msg = "系统版本过低，请升级到v1.6.9或更高版本";
        } else {
            foreach ($result as $key => $val) {
                $filename = !empty($val['file_name']) ? trim($val['file_name'], '/') : '';
                if (preg_match('/^(template)\/(.*)$/i', $filename) && !preg_match('/^(template)\/(.*)\.js$/i', $filename)) {
                    continue;
                }
                if (is_file($filename)) {
                    $local_file = ROOT_PATH."{$filename}"; // 本地路径 不存在可以自动创建
                    // tp_mkdir(dirname($local_file));
                    clearstatcache(); // 清除文件夹权限缓存
                    if (!is_writeable($local_file)) {
                        $code = false;
                        $msg = '没有写入权限，请检查以下问题：<br/>1、磁盘空间大小是否100%；<br/>2、文件或目录权限是否为755；<br/>3、文件或目录的权限，禁止用root:root ；';
                        break;
                    }
                    if (990 == $val['file_grade'] && preg_match('/^(.*)\.js$/i', $filename)) {
                        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/repair/'.$version.'/public/static/common/js/jquery.min.js';
                    } else {
                        if ($this->getAdminbasefile(false) == $val['file_name']) {
                            $filename = 'login.php';
                        }
                        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/repair/'.$version.'/'.$filename;
                        $arr_tmp = explode('/', $filename);
                        $file_alias_name = end($arr_tmp);
                        if ('.htaccess' == $file_alias_name) {
                            $url .= '.bak';
                        }
                    }
                    // 替换是否成功
                    $is_replace = true;
                    // 打开远程文件
                    $remote_file = @fopen($url, 'r');
                    if (empty($remote_file)) {
                        fclose($remote_file);
                        $is_replace = false;
                    } else {
                        // 打开本地文件
                        $fp = @fopen($local_file, 'w');
                        // 使用流下载文件内容
                        while (!feof($remote_file)) {
                            $content = @fread($remote_file, 1024);
                            if (empty($content)) {
                                $is_replace = false;
                                break;
                            }
                            fwrite($fp, $content);
                        }
                        // 关闭文件
                        fclose($fp);
                        fclose($remote_file);
                    }

                    if (false === $is_replace) {
                        $fp = @fopen($url, 'r');
                        if (!empty($fp)) {
                            if (@file_put_contents($local_file, $fp)) {
                                $is_replace = true;
                            }
                            fclose($fp);
                        } else {
                            $remote_file = '';
                        }
                    }

                    if (false !== $is_replace) {
                        $md5keys[] = $val['md5key'];
                        Db::name('ddos_log')->where(['id'=>$val['id']])->delete();
                    } else {
                        $code = false;
                        $msg = '修复失败，请检查以下问题：<br/>1、磁盘空间大小是否100%；<br/>2、文件或目录权限是否为755；<br/>3、文件或目录的权限，禁止用root:root ；';
                        if (empty($remote_file)) {
                            $msg .= '<br/>4、请求官方远程文件的网络失败；';
                        }
                        break;
                    }
                }
            }
        }
        $return_data = [
            'code' => $code,
            'msg' => $msg,
            'md5keys' => $md5keys,
            'errnum' => $errnum,
        ];
        return $return_data;
    }

    /**
     * 业务处理 - 加入白名单
     * @return [type] [description]
     */
    public function ddos_whitelist_add_handle($result = [])
    {
        $code = true;
        $del_ids = [];
        $md5keys = [];
        $time = getTime();
        $whitelist = Db::name('ddos_whitelist')->where(['admin_id'=>$this->admin_id])->getAllWithIndex('md5key');
        $insertData = [];
        foreach ($result as $key => $val) {
            $del_ids[] = $val['id'];
            $md5keys[] = $val['md5key'];
            if (empty($whitelist[$val['md5key']])) {
                $insertData[] = [
                    'md5key' => $val['md5key'],
                    'file_name' => $val['file_name'],
                    'admin_id' => $val['admin_id'],
                    'add_time' => $time,
                    'update_time' => $time,
                ];
            }
        }
        $r = true;
        if (!empty($insertData)) {
            $r = Db::name('ddos_whitelist')->insertAll($insertData);
        }
        if ($r !== false) {
            Db::name('ddos_log')->where(['id'=>['IN', $del_ids], 'admin_id'=>$this->admin_id])->delete();
        } else {
            $code = false;
        }
        
        $return_data = [
            'code' => $code,
            'md5keys' => $md5keys,
        ];
        return $return_data;
    }

    /**
     * 更新异常文件的总数
     * @param  array  $result [description]
     * @return [type]         [description]
     */
    public function ddos_update_doubt_total($result = [])
    {
        $max_file_num = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('file_num');
        if (!empty($result)) {
            Db::name('ddos_log')->where(['id'=>$result['id']])->delete();
        }
        // 重新统计异常等文件数
        $file_doubt_total = (int)Db::name('ddos_log')->where(['admin_id'=>$this->admin_id, 'file_grade'=>['gt',0]])->count();
        $update = [
            'file_num' => $max_file_num,
            'file_doubt_total'=>$file_doubt_total,
            'update_time'=>getTime(),
        ];
        Db::name('ddos_log')->where(['admin_id'=>$this->admin_id, 'file_doubt_total'=>['egt',$file_doubt_total]])->update($update);

        return [
            'file_doubt_total' => $file_doubt_total,
        ];
    }

    /*-----------------------ddos攻击脚本查杀 end-----------------------*/


    /*-----------------------登录防火墙 start-----------------------*/

    /**
     * 登录防火墙触发逻辑
     * @return [type] [description]
     */
    public function firewall_login_check()
    {
        $rdata = $this->firewall_is_pass();
        if (!empty($rdata) && empty($rdata['code'])) {
            session_unset();
            \think\Session::clear();
            $this->error('异常登录，不在防火墙设定范围内', ROOT_DIR.'/');
        }
    }

    /**
     * 当前访问是否通过了后台防火墙
     * @param  string  $currentIp [description]
     * @return boolean            [description]
     */
    private function firewall_is_pass($currentIp = '')
    {
        $firewallConfig = tpSetting('firewall');
        $controllerName = request()->controller();
        $actionName = strtolower(request()->action());
        $ctl_arr = ['Security','Ajax','Encodes'];
        $ctl_act_arr = ['Admin@admin_edit','Notify@count_unread_notify','Shop@shoporderprehandle','Diyminipro@ajax_syn_component_access'];
        if (empty($firewallConfig['firewall_open']) || in_array($controllerName, $ctl_arr) || in_array("{$controllerName}@{$actionName}", $ctl_act_arr)) {
            return ['code'=>1, 'msg'=>'不受限制'];
        }
        else {
            empty($currentIp) && $currentIp = clientIP();
            //预留测试IP
            //$currentIp = '220.194.58.240';//北京市
            //$currentIp = '59.39.145.178';//广东省 惠州市
            //$currentIp = '203.69.66.102';//台湾省
            //$currentIp = '169.235.24.133';//美国 加利福尼亚
            //$currentIp = '115.68.28.11';//韩国 首尔
            // $currentIp = '153.19.50.62';//波兰
            $firewall_open_func = json_decode($firewallConfig['firewall_open_func'], true);
            static $uneyousafe = null;
            if (null === $uneyousafe) {
                $file = DATA_PATH.'conf'.DS.'uneyousafe.txt';
                $uneyousafe = file_exists($file) ? true : false;
            }

            $restriction = false;
            if (true === $uneyousafe || empty($firewall_open_func)) {
                $restriction = $uneyousafe;
            } else {
                // IP限制
                if (in_array(1, $firewall_open_func)) {
                    // IP段白名单
                    $rdata = $this->firewall_blockip_check($currentIp, $firewallConfig['firewall_ip_whitelist'], false);
                    $restriction = $rdata['blockip_check'];
                } else {
                    $restriction = true;
                }
                // 时间限制
                if (in_array(2, $firewall_open_func)) {
                    // 允许登录的星期
                    if (true === $restriction) {
                        $week = (int)date('N', getTime());
                        $firewall_login_week = json_decode($firewallConfig['firewall_login_week'], true);
                        if (empty($firewall_login_week) || !in_array($week, $firewall_login_week)) {
                            $restriction = false;
                        }
                    }
                    // 允许登录的时间
                    if (true === $restriction) {
                        $hour = (int)date('H', getTime());
                        $firewall_login_hour = json_decode($firewallConfig['firewall_login_hour'], true);
                        if (empty($firewall_login_hour) || !in_array($hour, $firewall_login_hour)) {
                            $restriction = false;
                        }
                    }
                } else {
                    $restriction = true;
                }
            }

            if (true === $restriction) {
                return ['code'=>1, 'msg'=>'不受限制'];
            }
            return ['code'=>0, 'msg'=>'后台防火墙拦截成功'];
        }
    }

    /**
     * 检测ip是否在白名单范围内
     * @param  [type]  $currentIp     [description]
     * @param  string  $ip_whitelist  [description]
     * @param  boolean $blockip_check [description]
     * @return [type]                 [description]
     */
    public function firewall_blockip_check($currentIp, $ip_whitelist = '', $blockip_check = false)
    {
        $ip_whitelist = str_replace(["\r\n"], PHP_EOL, $ip_whitelist);
        $ip_whitelist = str_replace(["\r", "\n"], PHP_EOL, $ip_whitelist);
        $ip_whitelist = explode(PHP_EOL, $ip_whitelist);
        $ip_whitelist = array_unique($ip_whitelist);
        $ip_whitelist = array_filter($ip_whitelist);
        if (false === $blockip_check) {
            if (!empty($ip_whitelist)) {
                foreach ($ip_whitelist as $key => $value) {
                    $value = trim($value);
                    if (strstr($value, '-')) {
                        $valueArr = explode('-', $value);
                        $valueMin = !empty($valueArr[0]) ? trim($valueArr[0]) : '';
                        $valueMax = !empty($valueArr[1]) ? trim($valueArr[1]) : '';
                        if (ip2long($currentIp) >= ip2long($valueMin) && ip2long($currentIp) <= ip2long($valueMax)) {
                            $blockip_check = true;
                            break;
                        }
                    } else {
                        if (ip2long($currentIp) == ip2long($value)) {
                            $blockip_check = true;
                            break;
                        }
                    }
                }
            } else {
                $blockip_check = true;
            }
        }

        return [
            'ip_whitelist' => $ip_whitelist,
            'blockip_check' => $blockip_check,
        ];
    }

    /*-----------------------登录防火墙 end-----------------------*/


    /*-----------------------双因子 start-----------------------*/

    /**
     * 判断是否自动开启登录两步验证
     */
    public function twofactor_login_open()
    {
        $bool = false;
        $twofactorConfig = tpSetting('twofactor');
        if (!empty($twofactorConfig['twofactor_open']) && !file_exists('./data/conf/twofactor_login_open.txt')) {
            $bool = true;
            $twofactor_check_type = empty($twofactorConfig['twofactor_check_type']) ? 0 : $twofactorConfig['twofactor_check_type'];
            // 如果密保关闭，双因子也失效
            if (2 == $twofactor_check_type) {
                $securityConfig = tpSetting('security');
                if (empty($securityConfig['security_ask_open'])) {
                    $bool = false;
                }
            }
        }
        return $bool;
    }

    /**
     * 两步登录验证逻辑
     */
    public function twofactor_login_logic($type = '', $vars = [])
    {
        $redata = [];
        $twofactorConfig = tpSetting('twofactor');
        $twofactor_check_type = empty($twofactorConfig['twofactor_check_type']) ? 0 : $twofactorConfig['twofactor_check_type'];
        if ('login_view' == $type) {
            $twofactor_check = input('param.twofactor_check/d', 0);
            if (1 == $twofactor_check) {
                $admin_login_twofactor_check = session('admin_login_twofactor_check');
                $admin_login_twofactor_check = mchStrCode($admin_login_twofactor_check, 'DECODE');
                if (!empty($admin_login_twofactor_check)) {
                    $redata['code'] = 1;
                    $assign_data['twofactor_check'] = $twofactor_check;
                    if (1 == $twofactor_check_type) {
                        $sms_test_mobile = $this->get_first_test_mobile();
                        $admin_info = \think\Db::name('admin')->field('password', true)->where(['user_name'=>$admin_login_twofactor_check])->find();
                        if (empty($admin_info['mobile']) || !check_mobile($admin_info['mobile'])) {
                            $admin_info['mobile'] = $sms_test_mobile;
                        }
                        $admin_info['mobile_subhide'] = func_substr_replace($admin_info['mobile'], '*', 3, 6);
                        $assign_data['admin_info'] = $admin_info;
                        // if (empty($admin_info['mobile'])) {
                        //     $redata['code'] = 0;
                        //     $redata['msg'] = "当前管理员没有完善手机号码，请联系创始人";
                        // }
                        $redata['viewfile'] = 'admin/login_twofactor_mobile';
                    } else if (2 == $twofactor_check_type) {
                        $assign_data['security_ask'] = tpSetting('security.security_ask');
                        $redata['viewfile'] = 'admin/login_twofactor_ask';
                    } else {
                        $smtp_from_eamil = $this->get_first_test_email();
                        $admin_info = \think\Db::name('admin')->field('password', true)->where(['user_name'=>$admin_login_twofactor_check])->find();
                        if (empty($admin_info['email']) || !check_email($admin_info['email'])) {
                            $admin_info['email'] = $smtp_from_eamil;
                        }
                        $position = strpos($admin_info['email'], '@');
                        $start = 2;
                        $length = $position - $start - 2;
                        $admin_info['email_subhide'] = func_substr_replace($admin_info['email'], '*', $start, $length);
                        $assign_data['admin_info'] = $admin_info;
                        // if (empty($admin_info['email'])) {
                        //     $redata['code'] = 0;
                        //     $redata['msg'] = "当前管理员没有完善Email邮箱，请联系创始人";
                        // }
                        $redata['viewfile'] = 'admin/login_twofactor_email';
                    }
                    $redata['assign_data'] = $assign_data;
                }
            } else {
                session('admin_login_twofactor_check', null);
            }
        }
        else if ('login_action_begin' == $type) {
            if (1 == $twofactor_check_type) {
                $sms_test_mobile = $this->get_first_test_mobile();
                if (empty($vars['mobile']) || !check_mobile($vars['mobile'])) {
                    $vars['mobile'] = $sms_test_mobile;
                }
                if (empty($vars['mobile'])) {
                    $redata['code'] = 0;
                    $redata['msg'] = '拒绝登录，当前管理员没有手机号码！';
                }
            } else if (2 == $twofactor_check_type) {
                
            } else {
                $smtp_from_eamil = $this->get_first_test_email();
                if (empty($vars['email']) || !check_email($vars['email'])) {
                    $vars['email'] = $smtp_from_eamil;
                }
                if (empty($vars['email'])) {
                    $redata['code'] = 0;
                    $redata['msg'] = '拒绝登录，当前管理员没有Email邮箱！';
                }
            }
            
            if (isset($redata['code']) && 0 === $redata['code']) {
                
            } else {
                session('isset_author', null); // 内置勿动
                session('admin_login_twofactor_check', mchStrCode($vars['user_name'], 'ENCODE'));
                $redata['url'] = url('Admin/login', ['twofactor_check'=>1]);
            }
        }

        return $redata;
    }

    /**
     * 两步验证
     */
    public function twofactor_login_handle()
    {
        $twofactorConfig = tpSetting('twofactor');
        $twofactor_check_type = empty($twofactorConfig['twofactor_check_type']) ? 0 : $twofactorConfig['twofactor_check_type'];
        $post = input('post.');
        if (empty($post['twofactor_code'])) {
            if (1 == $twofactor_check_type) {
                $msg = '请输入短信验证码';
            } else if (2 == $twofactor_check_type) {
                $msg = '请输入密保答案';
            } else {
                $msg = '请输入邮箱验证码';
            }
            return ['code'=>0, 'msg'=>$msg];
        }
        $admin_login_twofactor_check = session('admin_login_twofactor_check');
        $admin_login_twofactor_check = mchStrCode($admin_login_twofactor_check, 'DECODE');
        $admin_info = \think\Db::name('admin')->where(['user_name'=>$admin_login_twofactor_check])->find();
        if (!empty($admin_info)) {
            if (1 == $twofactor_check_type) {
                $sms_test_mobile = $this->get_first_test_mobile();
                if (empty($admin_info['mobile']) || !check_mobile($admin_info['mobile'])) {
                    $admin_info['mobile'] = $sms_test_mobile;
                }
                // 验证短信验证码
                $RecordWhere = [
                    'source' => 30,
                    'mobile' => $admin_info['mobile'],
                    'code' => intval($post['twofactor_code']),
                    'is_use' => 0,
                    'lang'   => get_admin_lang(),
                ];
                $is_verify = \think\Db::name('sms_log')->where($RecordWhere)->find();
                if (!empty($is_verify)){
                    $RecordData  = [
                        'is_use' => 1,
                        'update_time' => getTime()
                    ];
                    // 更新数据
                    \think\Db::name('sms_log')->where($RecordWhere)->update($RecordData);
                }else{
                    return ['code'=>0, 'msg'=>'短信验证码已失效'];
                }
            }
            else if (2 == $twofactor_check_type) {
                // 验证密保答案
                $twofactor_code = func_encrypt(trim($post['twofactor_code']), true, pwd_encry_type('bcrypt'));
                if ($twofactor_code != tpSetting('security.security_answer')) {
                    return ['code'=>0, 'msg'=>'密保答案不正确'];
                }
            }
            else {
                $smtp_from_eamil = $this->get_first_test_email();
                if (empty($admin_info['email']) || !check_email($admin_info['email'])) {
                    $admin_info['email'] = $smtp_from_eamil;
                }
                // 验证邮箱验证码
                $RecordWhere = [
                    'source' => 30,
                    'email' => $admin_info['email'],
                    'code' => addslashes(trim($post['twofactor_code'])),
                    'status' => 0,
                    'lang'   => get_admin_lang(),
                ];
                $recordInfo = \think\Db::name('smtp_record')->where($RecordWhere)->find();
                if (!empty($recordInfo) && trim($post['twofactor_code']) == $recordInfo['code']){
                    $RecordData  = [
                        'status' => 1,
                        'update_time' => getTime()
                    ];
                    // 更新数据
                    \think\Db::name('smtp_record')->where($RecordWhere)->update($RecordData);
                }else{
                    return ['code'=>0, 'msg'=>'邮箱验证码已失效'];
                }
            }

            $admin_info = adminLoginAfter($admin_info['admin_id'], session_id());

            adminLog('后台登录(双因子验证)');
            session('admin_login_twofactor_check', null);
            $url = request()->baseFile();
            return ['code'=>1, 'msg'=>'登录成功', 'url'=>$url];
        } else {
            $url = request()->baseFile();
            return ['code'=>0, 'msg'=>'页面失效，返回登录', 'url'=>$url];
        }
    }

    /**
     * 获取云短信配置的第一个测试手机号
     * @return [type] [description]
     */
    public function get_first_test_mobile()
    {
        $sms_test_mobile = str_replace('，', ',', tpCache('sms.sms_test_mobile'));
        $mobile_arr = explode(',', $sms_test_mobile);
        $sms_test_mobile = current($mobile_arr);
        $sms_test_mobile = trim($sms_test_mobile);

        return $sms_test_mobile;
    }

    /**
     * 获取电子邮箱配置的第一个测试邮箱地址
     * @return [type] [description]
     */
    public function get_first_test_email()
    {
        $smtp_from_eamil = str_replace('，', ',', tpCache('smtp.smtp_from_eamil'));
        $eamil_arr = explode(',', $smtp_from_eamil);
        $smtp_from_eamil = current($eamil_arr);
        $smtp_from_eamil = trim($smtp_from_eamil);

        return $smtp_from_eamil;
    }
    /*-----------------------双因子 end-----------------------*/
}
