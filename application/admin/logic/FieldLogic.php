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
 * 字段逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class FieldLogic extends Model
{
    /**
     * 获得字段创建信息
     *
     * @access    public
     * @param     string  $dtype  字段类型
     * @param     string  $fieldname  字段名称
     * @param     string  $dfvalue  默认值
     * @param     string  $fieldtitle  字段标题
     * @return    array
     */
    function GetFieldMake($dtype, $fieldname, $dfvalue, $fieldtitle)
    {
        $fields = array();
        if("int" == $dtype)
        {
            empty($dfvalue) && $dfvalue = 0;
            $default_sql = '';
            if(preg_match("#[0-9]+#", $dfvalue))
            {
                $default_sql = "DEFAULT '$dfvalue'";
            }
            $maxlen = 10;
            $fields[0] = " `$fieldname` int($maxlen) NOT NULL $default_sql COMMENT '$fieldtitle';";
            $fields[1] = "int($maxlen)";
            $fields[2] = $maxlen;
        }
        else if("datetime" == $dtype)
        {
            empty($dfvalue) && $dfvalue = 0;
            $default_sql = '';
            if(preg_match("#[0-9\-]+#", $dfvalue))
            {
                $dfvalue = strtotime($dfvalue);
                empty($dfvalue) && $dfvalue = 0;
                $default_sql = "DEFAULT '$dfvalue'";
            }
            $maxlen = 11;
            $fields[0] = " `$fieldname` int($maxlen) NOT NULL $default_sql COMMENT '$fieldtitle';";
            $fields[1] = "int($maxlen)";
            $fields[2] = $maxlen;
        }
        else if("switch" == $dtype)
        {
            if(empty($dfvalue) || preg_match("#[^0-9]+#", $dfvalue))
            {
                $dfvalue = 1;
            }
            $maxlen = 1;
            $fields[0] = " `$fieldname` tinyint($maxlen) NOT NULL DEFAULT '$dfvalue' COMMENT '$fieldtitle';";
            $fields[1] = "tinyint($maxlen)";
            $fields[2] = $maxlen;
        }
        else if("float" == $dtype)
        {
            empty($dfvalue) && $dfvalue = 0;
            $default_sql = '';
            if(preg_match("#[0-9\.]+#", $dfvalue))
            {
                $default_sql = "DEFAULT '$dfvalue'";
            }
            $maxlen = 9;
            $fields[0] = " `$fieldname` float($maxlen,2) NOT NULL $default_sql COMMENT '$fieldtitle';";
            $fields[1] = "float($maxlen,2)";
            $fields[2] = $maxlen;
        }
        else if("decimal" == $dtype)
        {
            empty($dfvalue) && $dfvalue = 0;
            $default_sql = '';
            if(preg_match("#[0-9\.]+#", $dfvalue))
            {
                $default_sql = "DEFAULT '$dfvalue'";
            }
            $maxlen = 20;
            $fields[0] = " `$fieldname` decimal($maxlen,2) NOT NULL $default_sql COMMENT '$fieldtitle';";
            $fields[1] = "decimal($maxlen,2)";
            $fields[2] = $maxlen;
        }
        else if("img" == $dtype)
        {
            if(empty($dfvalue)) {
                $dfvalue = '';
            }
            $maxlen = 500;
            $fields[0] = " `$fieldname` varchar($maxlen) NOT NULL DEFAULT '$dfvalue' COMMENT '$fieldtitle';";
            $fields[1] = "varchar($maxlen)";
            $fields[2] = $maxlen;
        }
        else if("imgs" == $dtype)
        {
            if(empty($dfvalue)) {
                $dfvalue = '';
            }
            $maxlen = 10001;
            $fields[0] = " `$fieldname` text COMMENT '$fieldtitle|{$maxlen}';";
            $fields[1] = "text";
            $fields[2] = $maxlen;
        }
        else if("media" == $dtype)
        {
            $maxlen = 500;
            $fields[0] = " `$fieldname` varchar($maxlen) NOT NULL DEFAULT '$dfvalue' COMMENT '$fieldtitle';";
            $fields[1] = "varchar($maxlen)";
            $fields[2] = $maxlen;
        }
        else if("files" == $dtype)
        {
            if(empty($dfvalue)) {
                $dfvalue = '';
            }
            $maxlen = 10002;
            $fields[0] = " `$fieldname` text COMMENT '$fieldtitle|{$maxlen}';";
            $fields[1] = "text";
            $fields[2] = $maxlen;
        }
        else if("multitext" == $dtype)
        {
            $maxlen = 0;
            $fields[0] = " `$fieldname` text COMMENT '$fieldtitle';";
            $fields[1] = "text";
            $fields[2] = $maxlen;
        }
        else if("htmltext" == $dtype)
        {
            $maxlen = 0;
            $fields[0] = " `$fieldname` longtext CHARACTER SET utf8 COMMENT '$fieldtitle';";
            $fields[1] = "longtext";
            $fields[2] = $maxlen;
        }
        else if("checkbox" == $dtype)
        {
            $maxlen = 0;
            $dfvalueArr = explode(',', $dfvalue);
            if (64 < count($dfvalueArr)){
                $dfvalueArr = array_slice($dfvalueArr, 0, 64);
                $dfvalue = implode(',', $dfvalueArr);
            }
            $default_value = '';
            // $default_value = !empty($dfvalueArr[0]) ? $dfvalueArr[0] : '';
            $dfvalue = str_replace(',', "','", $dfvalue);
            $dfvalue = "'".$dfvalue."'";
            $fields[0] = " `$fieldname` SET($dfvalue) NULL DEFAULT '{$default_value}' COMMENT '$fieldtitle';";
            $fields[1] = "SET($dfvalue)";
            $fields[2] = $maxlen;
        }
        else if("select" == $dtype || "radio" == $dtype)
        {
            $maxlen = 0;
            $dfvalueArr = explode(',', $dfvalue);
            $default_value = !empty($dfvalueArr[0]) ? $dfvalueArr[0] : '';
            $dfvalue = str_replace(',', "','", $dfvalue);
            $dfvalue = "'".$dfvalue."'";
            $fields[0] = " `$fieldname` enum($dfvalue) NULL DEFAULT '{$default_value}' COMMENT '$fieldtitle';";
            $fields[1] = "enum($dfvalue)";
            $fields[2] = $maxlen;
        }
        else if("checkboxs" == $dtype)
        {
            if(empty($dfvalue)) {
                $dfvalue = '';
            }
            $maxlen = 10003;
            $fields[0] = " `$fieldname` text COMMENT '$fieldtitle|{$maxlen}';";
            $fields[1] = "text";
            $fields[2] = $maxlen;
        }
        else
        {
            if(empty($dfvalue))
            {
                $dfvalue = '';
            }
            $maxlen = 500;
            $fields[0] = " `$fieldname` varchar($maxlen) NOT NULL DEFAULT '$dfvalue' COMMENT '$fieldtitle';";
            $fields[1] = "varchar($maxlen)";
            $fields[2] = $maxlen;
        }

        return $fields;
    }

    /**
     * 检测频道模型相关的表字段是否已存在，包括：主表和附加表
     *
     * @access    public
     * @param     string  $slave_table  附加表
     * @return    string $fieldname 字段名
     * @return    int $channel_id 模型ID
     * @param     array  $filter  过滤哪些字段
     */
    public function checkChannelFieldList($slave_table, $fieldname, $channel_id, $filter = array())
    {
        // 栏目表字段
        $arctypeFieldArr = Db::getTableFields(PREFIX.'arctype'); 
        foreach ($arctypeFieldArr as $key => $val) {
            if (!preg_match('/^type/i',$val)) {
                array_push($arctypeFieldArr, 'type'.$val);
            }
        }
        $masterFieldArr = Db::getTableFields(PREFIX.'archives'); // 文档主表字段
        $slaveFieldArr = Db::getTableFields($slave_table); // 文档附加表字段
        $addfields = ['pageurl','has_children','typelitpic','arcurl','typeurl']; // 额外与字段冲突的变量名
        $fieldArr = array_merge($slaveFieldArr, $masterFieldArr, $addfields, $arctypeFieldArr); // 合并字段
        if (!empty($fieldname)) {
            if (!empty($filter) && is_array($filter)) {
                foreach ($filter as $key => $val) {
                    $k = array_search($val, $fieldArr);
                    if (false !== $k) {
                        unset($fieldArr[$k]);
                    }
                }
            }
            return in_array($fieldname, $fieldArr);
        }

        return true;
    }

    /**
     * 检测指定表的字段是否已存在
     *
     * @access    public
     * @param     string  $table  数据表
     * @return    string $fieldname 字段名
     * @param     array  $filter  过滤哪些字段
     */
    public function checkTableFieldList($table, $fieldname, $filter = array())
    {
        $fieldArr = Db::getTableFields($table); // 表字段
        if (!empty($fieldname)) {
            if (!empty($filter) && is_array($filter)) {
                foreach ($filter as $key => $val) {
                    $k = array_search($val, $fieldArr);
                    if (false !== $k) {
                        unset($fieldArr[$k]);
                    }
                }
            }
            return in_array($fieldname, $fieldArr);
        }

        return true;
    }

    /**
     * 删除指定模型的表字段
     * @param int $id channelfield表ID
     * @return bool
     */
    public function delChannelField($id)
    {
        $code = 0;
        $msg = '参数有误！';
        if (!empty($id)) {
            $id = intval($id);
            $row = model('Channelfield')->getInfo($id, 'channel_id,name,ifsystem');
            if (!empty($row['ifsystem'])) {
                return array('code'=>0, 'msg'=>'禁止删除系统字段！');
            }
            $fieldname = $row['name'];
            $channel_id = $row['channel_id'];
            $table = Db::name('channeltype')->where('id',$channel_id)->getField('table');
            $table = PREFIX.$table.'_content';
            if ($this->checkChannelFieldList($table, $fieldname, $channel_id)) {
                $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$fieldname}`;";
                if(false !== Db::execute($sql)) {
                    if (is_dir('./weapp/CompressContent/')) {                    
                        $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;  
                        $compressLogic->copydata(4,$channel_id,'',$table,$sql);
                    }    
                    /*重新生成数据表字段缓存文件*/
                    try {
                        schemaTable($table);
                    } catch (\Exception $e) {}
                    /*--end*/
                    return array('code'=>1, 'msg'=>'删除成功！');
                } else {
                    $code = 0;
                    $msg = '删除失败！'; 
                }
            } else {
                $code = 2;
                $msg = '字段不存在！';
            }
        }

        return array('code'=>$code, 'msg'=>$msg);
    }

    /**
     * 删除栏目的表字段
     * @param int $id channelfield表ID
     * @return bool
     */
    public function delArctypeField($id)
    {
        $code = 0;
        $msg = '参数有误！';
        if (!empty($id)) {
            $id = intval($id);
            $row = model('Channelfield')->getInfo($id, 'name,ifsystem');
            if (!empty($row['ifsystem'])) {
                return array('code'=>0, 'msg'=>'禁止删除系统字段！');
            }
            $fieldname = $row['name'];
            $table = PREFIX.'arctype';
            if ($this->checkTableFieldList($table, $fieldname)) {
                $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$fieldname}`;";
                if(false !== Db::execute($sql)) {
                    /*重新生成数据表字段缓存文件*/
                    try {
                        schemaTable($table);
                    } catch (\Exception $e) {}
                    /*--end*/
                    return array('code'=>1, 'msg'=>'删除成功！');
                } else {
                    $code = 0;
                    $msg = '删除失败！'; 
                }
            } else {
                $code = 2;
                $msg = '字段不存在！';
            }
        }

        return array('code'=>$code, 'msg'=>$msg);
    }

    /**
     * 同步模型附加表的字段记录
     * @author 小虎哥 by 2018-4-16
     */
    public function synChannelTableColumns($channel_id)
    {
        $this->synArchivesTableColumns($channel_id);
        $channelfieldArr = Db::name('channelfield')->field('name,dtype')->where('channel_id',$channel_id)->getAllWithIndex('name');
        $new_arr = array(); // 表字段数组
        $addData = array(); // 数据存储变量

        $table = Db::name('channeltype')->where('id',$channel_id)->getField('table');
        $tableExt = PREFIX.$table.'_content';
        $rowExt = Db::query("SHOW FULL COLUMNS FROM {$tableExt}");
        foreach ($rowExt as $key => $val) {            
            $fieldname = $val['Field'];
            if (in_array($fieldname, array('id','add_time','update_time','aid','typeid'))) {
                continue;
            }
            $new_arr[] = $fieldname;
            // 对比字段记录 表字段有 字段新增记录没有
            if (empty($channelfieldArr[$fieldname])) {
                $ifcontrol = 0;
                $is_release = 0;
                $ifeditable = 1;
                $dtype = $this->toDtype($val['Type'], $val['Comment']);
                $dfvalue = $this->toDefault($val['Type'], $val['Default']);
                if (in_array($fieldname, array('content'))) {
                    $ifsystem = 1;
                } else {
                    $ifsystem = 0;
                }
                $maxlength = preg_replace('/^([^\(]+)\(([^\)]+)\)(.*)/i', '$2', $val['Type']);
                $maxlength = intval($maxlength);

                /*视频模型附加表内置的系统字段*/
                if (5 == $channel_id && in_array($fieldname, ['total_video','total_duration','courseware_free','courseware'])) {
                    $ifsystem = 1;
                    $ifcontrol = 1;
                    $is_release = 1;
                    $ifeditable = 0;
                } else if ($channel_id <= 8 || (50 <= $channel_id && $channel_id <= 100)) {
                    if ('content' == $fieldname) {
                        $is_release = 1;
                    }
                }
                /*end*/

                $addData[] = array(
                    'name'  => $fieldname,
                    'channel_id'  => $channel_id,
                    'title'  => !empty($val['Comment']) ? $val['Comment'] : $fieldname,
                    'dtype' => $dtype,
                    'define'    => $val['Type'],
                    'maxlength' => $maxlength,
                    'dfvalue'   => $dfvalue,
                    'is_release'    => $is_release,
                    'ifeditable'    => $ifeditable,
                    'ifsystem'  => $ifsystem,
                    'ifmain'    => 0,
                    'ifcontrol' => $ifcontrol,
                    'add_time'  => getTime(),
                    'update_time'  => getTime(),
                );
            }
        }
        if (!empty($addData)) {
            Db::name('channelfield')->insertAll($addData);
        }

        /*字段新增记录有，表字段没有*/
        foreach($channelfieldArr as $k => $v){
            if (!in_array($k, $new_arr)) {
                $map = array(
                    'channel_id'    => $channel_id,
                    'ifmain'    => 0,
                    'name'  => $v['name'],
                );
                Db::name('channelfield')->where($map)->delete();
            }
        }
        /*--end*/

        \think\Cache::clear('channelfield');
    }

    /**
     * 同步文档主表的字段记录到指定模型
     * @author 小虎哥 by 2018-4-16
     */
    public function synArchivesTableColumns($channel_id = '')
    {
        $channelfieldArr = Db::name('channelfield')->field('name,dtype')->where('channel_id',$channel_id)->getAllWithIndex('name');

        $new_arr = array(); // 表字段数组
        $addData = array(); // 数据存储变量

        $controlFields = ['litpic','author'];
        $channeltype_system_ids = Db::name('channeltype')->where([
                'ifsystem'  => 1,
            ])->column('id');

        $table = PREFIX.'archives';
        $row = Db::query("SHOW FULL COLUMNS FROM {$table}");
        $row = array_reverse($row);
        foreach ($row as $key => $val) {
            $fieldname = $val['Field'];
            $new_arr[] = $fieldname;
            // 对比字段记录 表字段有 字段新增记录没有
            if (empty($channelfieldArr[$fieldname])) {
                $dtype = $this->toDtype($val['Type'], $val['Comment']);
                $dfvalue = $this->toDefault($val['Type'], $val['Default']);
                if (in_array($fieldname, $controlFields) && !in_array($channel_id, $channeltype_system_ids)) {
                    $ifcontrol = 0;
                } else {
                    $ifcontrol = 1;
                }
                $maxlength = preg_replace('/^([^\(]+)\(([^\)]+)\)(.*)/i', '$2', $val['Type']);
                $maxlength = intval($maxlength);
                $addData[] = array(
                    'name'  => $fieldname,
                    'channel_id'  => $channel_id,
                    'title'  => !empty($val['Comment']) ? $val['Comment'] : $fieldname,
                    'dtype' => $dtype,
                    'define'    => $val['Type'],
                    'maxlength' => $maxlength,
                    'dfvalue'   => $dfvalue,
                    'ifeditable'    => 1,
                    'ifsystem' => 1,
                    'ifmain'    => 1,
                    'ifcontrol' => $ifcontrol,
                    'add_time'  => getTime(),
                    'update_time'  => getTime(),
                );
            }
        }
        if (!empty($addData)) {
            Db::name('channelfield')->insertAll($addData);
        }

        /*字段新增记录有，表字段没有*/
        foreach($channelfieldArr as $k => $v){
            if (!in_array($k, $new_arr)) {
                $map = array(
                    'channel_id'  => $channel_id,
                    'ifmain'    => 1,
                    'name'  => $v['name'],
                );
                Db::name('channelfield')->where($map)->delete();
            }
        }
        /*--end*/
    }

    /**
     * 同步栏目主表的字段记录
     * @author 小虎哥 by 2018-4-16
     */
    public function synArctypeTableColumns($channel_id = '')
    {
        $cacheKey = md5("admin-FieldLogic-synArctypeTableColumns-{$channel_id}");
        $cacheValue = cache($cacheKey);
        if (!empty($cacheValue)) {
            return true;
        }

        $channel_id = !empty($channel_id) ? $channel_id : config('global.arctype_channel_id');
        $channelfieldArr = Db::name('channelfield')->field('name,dtype')->where('channel_id',$channel_id)->getAllWithIndex('name');

        $new_arr = array(); // 表字段数组
        $addData = array(); // 数据存储变量

        $table = PREFIX.'arctype';
        $row = Db::query("SHOW FULL COLUMNS FROM {$table}");
        $row = array_reverse($row);
        $arctypeTableFields = config('global.arctype_table_fields');
        foreach ($row as $key => $val) {
            $fieldname = $val['Field'];
            $new_arr[] = $fieldname;
            // 对比字段记录 表字段有 字段新增记录没有
            if (empty($channelfieldArr[$fieldname])) {
                $dtype = $this->toDtype($val['Type'], $val['Comment']);
                $dfvalue = $this->toDefault($val['Type'], $val['Default']);
                if (in_array($fieldname, $arctypeTableFields)) {
                    $ifsystem = 1;
                } else {
                    $ifsystem = 0;
                }
                $maxlength = preg_replace('/^([^\(]+)\(([^\)]+)\)(.*)/i', '$2', $val['Type']);
                $maxlength = intval($maxlength);
                $addData[] = array(
                    'name'  => $fieldname,
                    'channel_id'  => $channel_id,
                    'title'  => !empty($val['Comment']) ? $val['Comment'] : $fieldname,
                    'dtype' => $dtype,
                    'define'    => $val['Type'],
                    'maxlength' => $maxlength,
                    'dfvalue'   => $dfvalue,
                    'ifeditable'    => 1,
                    'ifsystem' => $ifsystem,
                    'ifmain'    => 1,
                    'ifcontrol' => 1,
                    'add_time'  => getTime(),
                    'update_time'  => getTime(),
                );
            }
        }
        if (!empty($addData)) {
            Db::name('channelfield')->insertAll($addData);
        }

        /*字段新增记录有，表字段没有*/
        foreach($channelfieldArr as $k => $v){
            if (!in_array($k, $new_arr)) {
                $map = array(
                    'channel_id'  => $channel_id,
                    'name'  => $v['name'],
                );
                Db::name('channelfield')->where($map)->delete();
            }
        }
        /*--end*/

        /*修复v1.1.9版本的admin_id为系统字段*/
        Db::name('channelfield')->where('name','admin_id')->update(['ifsystem'=>1]);
        /*--end*/

        \think\Cache::clear('channelfield');
        \think\Cache::clear("arctype");

        cache($cacheKey, 1, null, 'channelfield');
    }

    /**
     * 表字段类型转为自定义字段类型
     * @author 小虎哥 by 2018-4-16
     */
    public function toDtype($fieldtype = '', $comment = '')
    {
        $commentArr = explode('|', $comment);
        if (preg_match('/^int/i', $fieldtype)) {
            $maxlen = preg_replace('/^int\((.*)\)/i', '$1', $fieldtype);
            if (11 == $maxlen) {
                $dtype = 'datetime';
            } else {
                $dtype = 'int';
            }
        } else if (preg_match('/^longtext/i', $fieldtype)) {
            $dtype = 'htmltext';
        } else if (preg_match('/^text/i', $fieldtype)) {
            $maxlen = end($commentArr);
            $maxlen = intval($maxlen);
            if (1001 == $maxlen || 10001 == $maxlen) {
                $dtype = 'imgs';
            } else if (1002 == $maxlen || 10002 == $maxlen) {
                $dtype = 'files';
            } else if (1003 == $maxlen || 10003 == $maxlen) {
                $dtype = 'checkboxs';
            } else {
                $dtype = 'multitext';
            }
        } else if (preg_match('/^enum/i', $fieldtype)) {
            $dtype = 'select';
        } else if (preg_match('/^set/i', $fieldtype)) {
            $dtype = 'checkbox';
        } else if (preg_match('/^float/i', $fieldtype)) {
            $dtype = 'float';
        } else if (preg_match('/^decimal/i', $fieldtype)) {
            $dtype = 'decimal';
        } else if (preg_match('/^tinyint/i', $fieldtype)) {
            $dtype = 'switch';
        } else if (preg_match('/^varchar/i', $fieldtype)) {
            $maxlen = preg_replace('/^varchar\((.*)\)/i', '$1', $fieldtype);
            if (250 == $maxlen) {
                $dtype = 'img';
            } else if (1001 == $maxlen || 10001 == $maxlen) {
                $dtype = 'imgs';
            } else if (1002 == $maxlen || 10002 == $maxlen) {
                $dtype = 'files';
            } else {
                $dtype = 'text';
            }
        } else {
            $dtype = 'text';
        }

        return $dtype;
    }

    /**
     * 表字段的默认值
     * @author 小虎哥 by 2018-4-16
     */
    public function toDefault($fieldtype, $dfvalue = '')
    {
        if (preg_match('/^(enum|set)/i', $fieldtype)) {
            $str = preg_replace('/^(enum|set)\((.*)\)/i', '$2', $fieldtype);
            $str = str_replace("'", "", $str);
        } else {
            $str = $dfvalue;
        }
        $str = ("" != $str) ? $str : '';

        return $str;
    }

    /**
     * 处理栏目自定义字段的值
     * @author 小虎哥 by 2018-4-16
     */
    public function handleAddonField($channel_id, $dataExt)
    {
        $nowDataExt = array();
        if (!empty($dataExt) && !empty($channel_id)) {
            $fieldTypeList = model('Channelfield')->getListByWhere(array('channel_id'=>$channel_id), 'name,dtype', 'name');
            foreach ($dataExt as $key => $val) {
                
                $key = preg_replace('/^(.*)(_eyou_is_remote|_eyou_remote|_eyou_local)$/', '$1', $key);
                $dtype = !empty($fieldTypeList[$key]) ? $fieldTypeList[$key]['dtype'] : '';
                switch ($dtype) {

                    case 'checkbox':
                    case 'checkboxs':
                    {
                        $val = implode(',', $val);
                        break;
                    }

                    case 'switch':
                    case 'int':
                    {
                        $val = intval($val);
                        break;
                    }

                    case 'img':
                    {
                        $is_remote = !empty($dataExt[$key.'_eyou_is_remote']) ? $dataExt[$key.'_eyou_is_remote'] : 0;
                        if (1 == $is_remote) {
                            $val = $dataExt[$key.'_eyou_remote'];
                        } else {
                            $val = $dataExt[$key.'_eyou_local'];
                        }
                        break;
                    }

                    case 'imgs':
                    {
                        $imgData = [];
                        $imgsIntroArr = !empty($dataExt[$key.'_eyou_intro']) ? $dataExt[$key.'_eyou_intro'] : [];
                        foreach ($val as $k2 => $v2) {
                            $v2 = trim($v2);
                            if (!empty($v2)) {
                                $imgData[] = [
                                    'image_url' => $v2,
                                    'intro'     => !empty($imgsIntroArr[$k2]) ? $imgsIntroArr[$k2] : '',
                                ];
                            }
                        }
                        $val = serialize($imgData);
                        break;
                    }

                    case 'files':
                    {
                        foreach ($val as $k2 => $v2) {
                            if (empty($v2)) {
                                unset($val[$k2]);
                                continue;
                            }
                            $val[$k2] = trim($v2);
                        }
                        $val = implode(',', $val);
                        break;
                    }

                    case 'datetime':
                    {
                        $val = !empty($val) ? strtotime($val) : getTime();
                        break;
                    }

                    case 'decimal':
                    {
                        $moneyArr = explode('.', $val);
                        $money1 = !empty($moneyArr[0]) ? intval($moneyArr[0]) : '0';
                        $money2 = !empty($moneyArr[1]) ? intval(msubstr($moneyArr[1], 0, 2)) : '00';
                        $val = $money1.'.'.$money2;
                        break;
                    }
                    
                    default:
                    {
                        if (is_array($val)) {
                            $new_val = [];
                            foreach ($val as $_k => $_v) {
                                $_v = trim($_v);
                                if (!empty($_v)) {
                                    $new_val[] = $_v;
                                }
                            }
                            $val = $new_val;
                        } else {
                            $val = trim($val);
                        }
                        break;
                    }
                }
                $nowDataExt[$key] = $val;
            }
        }

        return $nowDataExt;
    }
}
