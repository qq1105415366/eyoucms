<?php

namespace think\db\builder;

use think\db\Builder;
use think\db\Query;
use think\db\Expression;
use think\Exception;

/**
 * Dm数据库驱动(达梦)
 */
class Dm extends Builder
{
    //protected $selectSql = 'SELECT * FROM (SELECT thinkphp.*, rownum AS numrow FROM (SELECT  %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%) thinkphp ) %LIMIT%%COMMENT%';
    protected $selectSql = 'SELECT %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER% %LIMIT%%COMMENT%';
    protected $insertAllSql = '%INSERT% INTO %TABLE% (%FIELD%) VALUES %DATA% %COMMENT%';
    protected $updateSql    = 'UPDATE %TABLE% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';
    /**
     * limit分析
     * @access protected
     * @param  Query $query 查询对象
     * @param  mixed $limit
     * @return string
     */
    protected function parseLimit($limit)
    {
        $limitStr = '';
        if (!empty($limit)) {
            $limit = explode(',', $limit);
            if (count($limit) > 1) {
                // $limitStr = "(numrow>" . $limit[0] . ") AND (numrow<=" . ($limit[0] + $limit[1]) . ")";
                $limitStr .= ' LIMIT ' . $limit[1] . ' OFFSET ' . $limit[0] . ' ';
            } else {
                // $limitStr = "(numrow>0 AND numrow<=" . $limit[0] . ")";
                $limitStr .= ' LIMIT ' . $limit[0] . ' ';
            }
        }

        // return $limitStr ? ' WHERE ' . $limitStr : '';
        return $limitStr;
    }
    /**
     * 设置锁机制
     * @access protected
     * @param  Query      $query 查询对象
     * @param  bool|false $lock
     * @return string
     */
    protected function parseLock($lock = false)
    {
        if (!$lock) {
            return '';
        }

        return ' FOR UPDATE NOWAIT ';
    }
    /**
     * 字段和表名处理
     * @access public
     * @param  Query  $query  查询对象
     * @param  string $key
     * @param  string $strict
     * @return string
     */
    /*public function parseKey($key, $options = [], $strict = false)
    {
        print_r($key);
        if (strpos($key, '->') && false === strpos($key, '(')) {
            // JSON字段支持
            list($field, $name) = explode($key, '->');
            $key                = '"' . $field . '"' . '."' . $name . '"';
        }
        return $key;
    }*/
    protected function parseKey($key, $options = [], $strict = false)
    {        
        if (is_numeric($key)) {
            return $key;
        } elseif ($key instanceof Expression) {
            return $key->getValue();
        }        
        $key = trim($key);

        if (strpos($key, '$.') && false === strpos($key, '(')) {
            // JSON字段支持
            list($field, $name) = explode('$.', $key);
            return 'json_extract(' . $field . ', \'$.' . $name . '\')';
        } elseif (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]/', $key)) {
            list($table, $key) = explode('.', $key, 2);
            if ('__TABLE__' == $table) {
                $table = $this->query->getTable();
            }
            if (isset($options['alias'][$table])) {
                $table = $options['alias'][$table];
            }
        }                
        if ($strict && !preg_match('/^[\w\.\*]+$/', $key)) {
            throw new Exception('not support data:' . $key);
        }
        if ('*' != $key && ($strict || !preg_match('/[,\'\"\*\(\)`.\s]/', $key))) {
            $key = '"' . $key . '"';
        }
        if (isset($table)) {
            if (strpos($table, '.')) {
                $table = str_replace('.', '`.`', $table);
            }
            $key = '"' . $table . '".' . $key;
        }        
        return $key;
    }
    /**
     * table分析
     * @access protected
     * @param mixed $tables
     * @param array $options
     * @return string
     */
    protected function parseTable($tables, $options = [])
    {        
        $item = [];
        $database = $this->connection->getConfig('database');        
        foreach ((array) $tables as $key => $table) {
            if (!is_numeric($key)) {
                if (strpos($this->parseSqlTable($key), '.') !== false) {
                    $key  = $this->parseSqlTable($key);
                }else{
                    $key  = $this->connection->getConfig('database').'.'.$this->parseSqlTable($key);
                }                
                $aliasTable  = $this->parseKey($key) . ' ' . (isset($options['alias'][$table]) ? $this->parseKey($options['alias'][$table]) : $this->parseKey($table));
                // $item[] =  '"'.$database.'"'.'.'.$aliasTable;
            } else {
                $table = $this->parseSqlTable($table);
                if (isset($options['alias'][$table])) {
                   $aliasTable = $this->parseKey($table) . ' ' . $this->parseKey($options['alias'][$table]);
                } else {
                   $aliasTable = $this->parseKey($table);
                }
                // $item[] =  '"'.$database.'"'.'.'.$aliasTable;
            }
            $item[]  = $aliasTable;
        }                             
        return implode(',', $item);
    }
    /**
     * 生成insertall SQL
     * @access public
     * @param array     $dataSet 数据集
     * @param array     $options 表达式
     * @param bool      $replace 是否replace
     * @return string
     * @throws Exception
     */
    public function insertAll($dataSet, $options = [], $replace = false)
    {        
        // 获取合法的字段
        if ('*' == $options['field']) {
            $fields = array_keys($this->query->getFieldsType( $options['table']));
        } else {
            $fields = $options['field'];
        }

        foreach ($dataSet as $data) {
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields, true)) {
                    if ($options['strict']) {
                        throw new Exception('fields not exists:[' . $key . ']');
                    }
                    unset($data[$key]);
                } elseif (is_null($val)) {
                    $data[$key] = 'NULL';
                } elseif (is_scalar($val)) {
                    $data[$key] = $this->parseValue($val, $key);
                } elseif (is_object($val) && method_exists($val, '__toString')) {
                    // 对象数据写入
                    $data[$key] = $val->__toString();
                } else {
                    // 过滤掉非标量数据
                    unset($data[$key]);
                }
            }
            $value    = array_values($data);
            $values[] = '( ' . implode(',', $value) . ' )';

            if (!isset($insertFields)) {
                $insertFields = array_map([$this, 'parseKey'], array_keys($data));
            }
        }
        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($options['table'], $options),
                implode(' , ', $insertFields),
                implode(' , ', $values),
                $this->parseComment($options['comment']),
            ], $this->insertAllSql);
    }

    /**
     * 随机排序
     * @access protected
     * @param  Query $query 查询对象
     * @return string
     */
    protected function parseRand()
    {
        return 'DBMS_RANDOM.value';
    }

}
