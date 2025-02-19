<?php
 
namespace think\db\connector;
 
use PDO;
use think\db\Connection;
 
/**
 * Dm数据库驱动(达梦)
 */
class Dm extends Connection
{
        // 字段属性大小写
    protected $attrCase = PDO::CASE_LOWER;
    protected $builder = '\\think\\db\\builder\\Dm';
 
    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        $dsn = 'dm:';
        if (!empty($config['hostname'])) {
            $dsn .=  $config['hostname'] . ($config['hostport'] ? ':' . $config['hostport'] : '') . '/';
        }
        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        } 		
        return $dsn;
    }
 
    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    public function getFields($tableName)
    {        
        if (strpos($tableName, '.') !== false) {
            $dmarr = explode('.', $tableName); 
            $tableName = trim($dmarr[1]);
            $owner = trim($dmarr[0]);
            $sql             = "select a.column_name,data_type,DECODE (nullable, 'Y', 0, 1) notnull,data_default, DECODE (A .column_name,b.column_name,1,0) pk from all_tab_columns a,(select column_name from all_constraints c, all_cons_columns col where c.constraint_name = col.constraint_name and c.constraint_type = 'P' and c.table_name = '" . $tableName . "' ) b where table_name = '" . $tableName . "' and a.column_name = b.column_name (+) AND OWNER = '".$owner."'";
        }else{
            $sql             = "select a.column_name,data_type,DECODE (nullable, 'Y', 0, 1) notnull,data_default, DECODE (A .column_name,b.column_name,1,0) pk from all_tab_columns a,(select column_name from all_constraints c, all_cons_columns col where c.constraint_name = col.constraint_name and c.constraint_type = 'P' and c.table_name = '" . $tableName . "' ) b where table_name = '" . $tableName . "' and a.column_name = b.column_name (+)";
        }        
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];    
        if ($result) {
            foreach ($result as $key => $val) {
                $val                       = array_change_key_case($val);
                $info[$val['column_name']] = [
                    'name'    => $val['column_name'],
                    'type'    => $val['data_type'],
                    'notnull' => $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'],
                    'autoinc' => $val['pk'],
                ];
            }
        }

        return $this->fieldCase($info);
    }
 
    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access   public
     * @param string $dbName
     * @return array
     */
    public function getTables($dbName = '')
    {        
        $sql    = 'select table_name from all_tables';
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];
 
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
 
        return $info;
    }
 
    /**
     * SQL性能分析
     * @access protected
     * @param string $sql
     * @return array
     */
    protected function getExplain($sql)
    {
        return [];
    }
 
    protected function supportSavepoint()
    {
        return true;
    }
}