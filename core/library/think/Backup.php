<?php

namespace think;

use think\Db;

//数据导出模型
class Backup{
    /**
     * 文件指针
     * @var resource
     */
    private $fp;

    /**
     * 备份文件信息 part - 卷号，name - 文件名
     * @var array
     */
    private $file;

    /**
     * 当前打开文件大小
     * @var integer
     */
    private $size = 0;

    /**
     * 备份配置
     * @var integer
     */
    private $config;

    /**
     * 数据库备份构造方法
     * @param array  $file   备份或还原的文件信息
     * @param array  $config 备份配置信息
     * @param string $type   执行类型，export - 备份数据， import - 还原数据
     */
    public function __construct($file, $config, $type = 'export'){
        $this->file   = $file;
        $this->config = $config;
    }

    /**
     * 打开一个卷，用于写入数据
     * @param  integer $size 写入数据的大小
     */
    private function open($size){
        if($this->fp){
            $this->size += $size;
            if($this->size > $this->config['part']){
                $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
                $this->fp = null;
                $this->file['part']++;
                if ('install' != $this->file['name']) {
                    session('backup_file', $this->file);
                }
                $this->create();
            }
        } else {
            $backuppath = $this->config['path'];
            if ('install' == $this->file['name']) {
                $filename   = "{$backuppath}eyoucms.sql";
            } else {
                $filename   = "{$backuppath}{$this->file['name']}-{$this->file['part']}-{$this->file['version']}.sql";
            }
            if($this->config['compress']){
                $filename = "{$filename}.gz";
                $this->fp = @gzopen($filename, "a{$this->config['level']}");
            } else {
                $this->fp = @fopen($filename, 'a');
            }
            $this->size = filesize($filename) + $size;
        }
    }

    /**
     * 写入初始数据
     * @return boolean true - 写入成功，false - 写入失败
     */
    public function create(){
        $mysqlinfo = Db::query("SELECT VERSION() as version");
        $mysql_version  = $mysqlinfo[0]['version'];

        $sql  = "-- ----------------------------------------\n";
        $sql .= "-- EyouCms MySQL Data Transfer \n";
        $sql .= "-- \n";
        $sql .= "-- Server         : " . config('database.hostname').'_'.config('database.hostport') . "\n";
        $sql .= "-- Server Version : " . $mysql_version . "\n";
        $sql .= "-- Host           : " . config('database.hostname').':'.config('database.hostport') . "\n";
        $sql .= "-- Database       : " . config('database.database') . "\n";
        $sql .= "-- \n";
        $sql .= "-- Part : #{$this->file['part']}\n";
        $sql .= "-- Version : #{$this->file['version']}\n";
        $sql .= "-- Date : " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- -----------------------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        return $this->write($sql);
    }

    /**
     * 写入SQL语句
     * @param  string $sql 要写入的SQL语句
     * @return boolean     true - 写入成功，false - 写入失败！
     */
    private function write($sql){
        $size = strlen($sql);
        //由于压缩原因，无法计算出压缩后的长度，这里假设压缩率为50%，
        //一般情况压缩率都会高于50%；
        $size = $this->config['compress'] ? $size / 2 : $size;

        $this->open($size);
        return $this->config['compress'] ? @gzwrite($this->fp, $sql) : @fwrite($this->fp, $sql);
    }

    /**
     * 备份表结构
     * @param  string  $table 表名
     * @param  integer $start 起始行数
     * @return boolean        false - 备份失败
     */
    public function backup($table, $start){
        $des_table = $table;
        if ('install' == $this->file['name']) {
            $ey_prefix = config('database.prefix');
            if ('ey_' != $ey_prefix) {
                $des_table = preg_replace("/^{$ey_prefix}/", "ey_", $des_table);
            }
        }
        //备份表结构
        if(0 == $start){
            $result = Db::query("SHOW CREATE TABLE `{$table}`");
            $sql  = "\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "-- Table structure for `{$des_table}`\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$des_table}`;\n";
            $create_table = trim($result[0]['Create Table']);
            if ('install' == $this->file['name']) {
                if ('ey_' != $ey_prefix) {
                    $create_table = str_replace("`{$ey_prefix}", "`ey_", $create_table);
                }
            }
            $sql .= $create_table . ";\n\n";
            if(false === $this->write($sql)){
                return false;
            }
        }

        //数据总数
        $result = Db::query("SELECT COUNT(*) AS count FROM `{$table}`");
        $count  = $result['0']['count'];

        //备份表数据
        if($count){
            //写入数据注释
            if(0 == $start){
                $sql  = "-- -----------------------------\n";
                $sql .= "-- Records of `{$des_table}`\n";
                $sql .= "-- -----------------------------\n";
                $this->write($sql);
            }

            //备份数据记录
            $result = Db::query("SELECT * FROM `{$table}` LIMIT {$start}, 1000");
            foreach ($result as $row) {
                $row = array_map('addslashes', $row);
                $sql = "INSERT INTO `{$des_table}` VALUES ('" . str_replace(array("\r","\n"),array('\r','\n'),implode("', '", $row)) . "');\n";
                if(false === $this->write($sql)){
                    return false;
                }
            }

            //还有更多数据
            if($count > $start + 1000){
                return array($start + 1000, $count);
            }
        }

        //备份下一表
        return 0;
    }

    public function import($start){
        //还原数据
        if($this->config['compress']){
            $gz   = gzopen($this->file[1], 'r');
            $size = 0;
        } else {
            $size = filesize($this->file[1]);
            $gz   = fopen($this->file[1], 'r');
        }

        $sql  = '';
        if($start){
            $this->config['compress'] ? gzseek($gz, $start) : fseek($gz, $start);
        }

        $totalsize = 0;
        // for($i = 0; $i < 1000; $i++){
        //     $sql .= $this->config['compress'] ? gzgets($gz) : fgets($gz);
        //     if(preg_match('/.*;$/', trim($sql))){
        //         /**
        //          * 统计SQL文件字符长度
        //          */
        //         $sqlFormat = $this->sql_split($sql, config('database.prefix'));
        //         $counts = count($sqlFormat);
        //         for ($j = 0; $j < $counts; $j++) {
        //             $tmp_sql = trim($sqlFormat[$j]);
        //             if (strstr($tmp_sql, 'CREATE TABLE') || !empty($tmp_sql)) {
        //                 $totalsize += strlen($tmp_sql);
        //             }
        //         }
        //     }
        //     $sql = '';
        // }

        $sql  = '';
        for($i = 0; $i < 1000; $i++){
            $sql .= $this->config['compress'] ? gzgets($gz) : fgets($gz);
            if(preg_match('/.*;$/', trim($sql))){
                /**
                 * 执行SQL语句
                 */
                $sqlFormat = $this->sql_split($sql, config('database.prefix'));
                $counts = count($sqlFormat);
                for ($j = 0; $j < $counts; $j++) {
                    $tmp_sql = trim($sqlFormat[$j]);
                    if (strstr($tmp_sql, 'CREATE TABLE')) {
                        if(false !== Db::execute($tmp_sql)){
                            $start += strlen($tmp_sql);
                        } else {
                            return false;
                        }
                    } else {
                        if(trim($tmp_sql) == '')
                           continue;
                        if(false !== Db::execute($tmp_sql)){
                            $start += strlen($tmp_sql);
                        } else {
                            return false;
                        }
                    }
                }
                $sql = '';
            } elseif ($this->config['compress'] ? gzeof($gz) : feof($gz)) {
                return 0;
            }
        }

        return array($start, $size, $totalsize);
    }

    /**
     * 析构方法，用于关闭文件资源
     */
    public function __destruct(){
        $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
    }

    /**
     * 解析
     * @access public
     * @param mixed $filename
     * @return mixed
     */
    public static function parseSql($filename){

        /*获取所有数据表名*/
        $tableList = [];
        $dbtables = Db::query('SHOW TABLE STATUS');
        foreach ($dbtables as $k => $v) {
            if (preg_match('/^'.config('database.prefix').'/i', $v['Name'])) {
                $tableList[] = $v['Name'];
            }
        }
        /*--end*/

        $lines=file($filename);
        $lines[0]=str_replace(chr(239).chr(187).chr(191),"",$lines[0]);//去除BOM头
        $flage = true;
        $sqls = array();
        $sql="";
        foreach($lines as $line)
        {
            $line=trim($line);
            $char=substr($line,0,1);
            if($char!='#' && strlen($line)>0)
            {
                $prefix=substr($line,0,2);
                switch($prefix)
                {
                    case '/*':
                    {
                        $flage=(substr($line,-3)=='*/;'||substr($line,-2)=='*/')?true:false;
                        break 1;
                    }
                    case '--': break 1;
                    default :
                    {
                        if($flage)
                        {
                            $sql.=$line;
                            if(substr($line,-1)==";")
                            {
                                /*处理安装sql与备份sql里表前缀的兼容性*/
                                $ey_prefix = 'ey_';
                                foreach ($tableList as $k2 => $v2) {
                                    if (stristr($sql, '`'.$v2.'`')) {
                                        $ey_prefix = config('database.prefix');
                                        break;
                                    }
                                }
                                /*--end*/
                                if ('ey_' != config('database.prefix')) {
                                    $sql = str_replace("`{$ey_prefix}", '`'.config('database.prefix'), $sql);
                                }
                                $sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);
                                $sqls[]=$sql;
                                $sql="";
                            }
                        }
                        if(!$flage)$flage=(substr($line,-3)=='*/;'||substr($line,-2)=='*/')?true:false;
                    }
                }
            }
        }
        return $sqls;
    }

    /**
     * 安装
     * @access public
     * @param mixed $sqls
     * @return mixed
     */
    public static function install($sqls){
        $flag = true;
        if(is_array($sqls))
        {
            foreach($sqls as $sql)
            {
                if(Db::execute($sql) === false){ $flag = false;}
            }
        }
        return $flag;
    }

    public function sql_split($sql, $tablepre) {

        if ($tablepre != "ey_")
            $sql = str_replace("`ey_", '`'.$tablepre, $sql);
              
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
}
