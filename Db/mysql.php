<?php
namespace Strawframework\Db;

use \PDO;
use Strawframework\Protocol\Db;

class Mysql implements Db {

    //pdo obj
    private $pdo;

    //model 中执行的 sql
    private $sqlQuery = '';

    //操作表
    private $table = '';

    public function __construct($config = '') {

        $dsn = 'mysql:dbname=' . $config['dbname'] . ';host=' . $config['host'] . ';port=' . $config['port'];
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password']);
            $this->pdo->exec('SET CHARACTER SET ' . $config['charset']);
        } catch (\Exception $e) {
            ex($e->getMessage(), $e->getTraceAsString(), 'DB CONNECT ERROR!');
        }
    }

    public function __destruct() {
        $this->pdo = null;
        unset($this->sqlQuery, $this->table);
    }

    /**
     *  选择表
     */
    public function setTable($table) {
        $this->table = $table;
    }

    //设置最后执行的sql
    private function _setLastSql($sql) {
        $this->sqlQuery = $sql;
    }

    //取最后一次执行的 sql
    public function getLastSql() {
        return $this->sqlQuery;
    }

    //field array 形式 解成 字符串
    private function getFieldViaArr($arrayField){

        $newField = '';
        $falseField = [];
        foreach ($arrayField as $key => $value) {
            if (false != $value) {
                if ($newField) {
                    $newField .= ' , ';
                }
                $newField .= '`' . $value . '`';
            } else {
                //如果有 false 的 field 则显示其他所有 field
                $falseField[] = '`' . $key . '`,';
            }
        }
        return str_replace($falseField, '', $newField ?: $this->getAllField());
    }

    /**
     * 组合条件的数据查询
     *
     * @param string       $table  数据表
     * @param string|array $field  需要查询的字段,字符串或者数组
     * @param string|array $query  查询条件
     * @param string|array $order  排序条件
     * @param int          $offset 数据偏移量
     * @param int          $limit  取数据条数
     *
     * @return array
     */
    public function getAll($query = '', $field = '', $order = '', $offset = NULL, $limit = NULL, $data = '') {

        if (!$field) {
            $field = $this->getAllField();
        }

        if (is_array($field)) {
            $field = $this->getFieldViaArr($field);
        }

        $map = '';
        if (!empty($query)) {
            if (is_array($query)) {
                $data = [];
                $mapArr = array();
                foreach ($query as $key => $value) {
                    $mapArr[] = '`'.addslashes($key).'` = ?';
                    $data[] = $value;
                }
                $map = ' WHERE ' . implode(' AND ', $mapArr);
            } else {
                $map = ' ' . $query;
            }
        }

        $orderby = '';
        if ($order) {
            if (is_array($order)) {
//                $orderKey = [];
//                foreach ($order as $value) {
//                    $orderKey[] = '?';
////                    $data[] = $value;
//                }
                $orderby = ' ORDER BY ' . implode(',', $order);
            } else {
                $orderby = ' ORDER BY ' . $order;
//                $data[] = addslashes($order);
            }
        }
        $limitstr = '';
        if ($limit > 0) {
//            $limitstr = ' LIMIT '.abs(intval($offset)).', '.intval($limit);
            $limitstr = ' LIMIT ?, ?';
            $data[] = abs(intval($offset));
            $data[] = intval($limit);
        }


        $sql = "SELECT " . $field . " FROM `" . $this->table . '`' . $map . $orderby . $limitstr;

        return $this->doQuery($sql, $data, 'all', PDO::FETCH_ASSOC);
    }

    /**
     * 获取数据表所有字段
     */
    public function getAllField($table = '') {

        if (!$table) {
            $table = $this->table;
        }

        return '*';
        $field = array_column($this->doQuery(sprintf('SHOW COLUMNS FROM `%s`', addslashes($table))), 'Field');
//        $field = array_column($this->doQuery('SHOW COLUMNS FROM `'. $table .'`'), 'Field');
        $field = '`' . implode('`, `', $field) . '`';
        return $field;
    }

    //获取行数
    public function count($query = '', $countField = '*', $data = []){
        return $this->getOne($query, "count($countField) AS count", $data)['count'];
    }

//    //检查绑定数据是否完整
//    private function _bindInfo($query, $data){
//        //包含 ? 号的绑定
//        $wbindNum = substr_count($query, '?');
//        //包含 : 号的绑定
//        $mbindNum = substr_count($query, ':');
//        if (0 == $wbindNum && 0 == $mbindNum) {
//            ex('Mysql query with string must use prepare to binding data see : http://php.net/manual/zh/pdo.prepare.php');
//        }else{
//            $dataNum = count(array_values($data));
//            if ($dataNum != $wbindNum && $dataNum != $mbindNum){
//                ex('Binding data not equal to query point character');
//            }
//        }
//    }

    /**
     * 根据条件查询一条结果
     */
    public function getOne($query = '', $field = '', $data = []) {
        //获取所有字段
        if (!$field) {
            $field = $this->getAllField();
        }

        if (is_array($field)) {

            $field = $this->getFieldViaArr($field);
        }

        $sql = "SELECT " . $field . " FROM `" . $this->table . "`";

        //处理 query
        if (is_array($query)) {
            //无视 data 传值
            $data = [];
            $map = '';
            foreach ($query as $key => $value) {
                if ($map) {
                    $map .= ' AND ';
                }
                $map .= '`'.addslashes($key).'` = ?';
                $data[] = $value;
            }
            $sql .= " WHERE " . $map;
        } else if ($query) {
            $sql .= " " . $query;
        }

        return $this->doQuery($sql, $data, 'row', PDO::FETCH_ASSOC);
    }

    /**
     * 获取一行数据 一个col
     * @param     $sql
     * @param int $type
     *
     * @return mixed
     */
    public function getCol($sql, $col = 0, $data = []) {
        return $this->doQuery($sql, $data, 'row', PDO::FETCH_NUM)[$col];
    }

    /**
     * 通过完整 sql 查询查询
     *
     * @param        $sql
     * @param string $ftype
     * @param int    $type
     */
    public function getQuery($sql, $data = [], $type = PDO::FETCH_ASSOC) {
        return $this->doQuery($sql, $data, 'all', $type);
    }

    /**
     *执行查询操作
     *
     * @param string $sql   sql语句
     * @param string $ftype 执行类型,all:全部数据,one:一条记录
     *
     * @return mixed
     */
    private function doQuery($sql, $bindData = '', $ftype = 'all', $type = PDO::FETCH_ASSOC) {
        try {
            $this->_setLastSql($this->_interpolateQuery(trim($sql), $bindData));
            if ($bindData)
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pre = $this->pdo->prepare(trim($sql));
            if (!is_object($pre)) {
                throw new \Exception(sprintf('SQL语句预处理失败: %s', $this->getLastSql()));
            }

            if (!is_array($bindData))
                $bindData = (array) $bindData;
//            print_r($bindData);
            if (!$pre->execute($bindData)) {
                throw new \Exception(sprintf('SQL语句执行失败: %s', $this->getLastSql()));
            }

            $type = !empty($type) ? $type : PDO::FETCH_ASSOC;
            if (strtolower($ftype) == 'all') {
                $result = $pre->fetchAll($type);
            } else {
                $result = $pre->fetch($type);
            }
            /*
            if (FALSE === $result) {
                $error = $pre->errorInfo();
                throw new \Exception($error[2] . '<br/>LAST SQL = ' . $this->getLastSql() . $error[1]);
            }
             */
            return $result ?: false;
        } catch (\Exception $e) {
            ex($e->getMessage(), $e->getTraceAsString(), 'Db Error');
        }
    }


    /**
     * 为 pdo bind 数据赋值为最终执行语句
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     * @return string The interpolated query
     */
    private function _interpolateQuery($query, $params) {
        if (!$params)
            return $query;

        $keys = array();

        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }
        }

        @$beautifulQuery = preg_replace($keys, $params, $query, 1, $count);

        #trigger_error('replaced '.$count.' keys');

        return $beautifulQuery ?: $query;
    }

    /**
     * insert 执行 sql 示例
     *
     * @return [type] [description]
     */
    public function insert($data, $args = []) {
        if (!$this->table) {
            ex('table not found');
        }

        if (!$data || !is_array($data)){
            ex('Insert data must be an array');
        }

        $colName = [];
        $colValue = [];
        foreach ($data as $key => $value) {
            $colName[] = '`' . addslashes($key) . '`';
            $colValue[] = ':' . $key;
        }
        $sql = 'INSERT INTO `' . $this->table . '` (' . implode(',', $colName) . ') VALUES (' . implode(',', $colValue) . ')';
        try {
            $sth = $this->pdo->prepare($sql);
            $this->_setLastSql($this->_interpolateQuery($sql, $data));
            if (FALSE === $sth->execute($data)) {
                $error = $sth->errorInfo();
                throw new \Exception(sprintf("%s ".PHP_EOL."Last Sql: %s ".PHP_EOL."%s", $error[2], $this->getLastSql(), $error[1]));
            }
            return $this->getLastId();
        } catch (\Exception $e) {
            ex("Mysql Insert Error: ", $e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'DB ERROR');
        }
    }

    /**
     * 取最后一次 插入的 id
     * @return [type] [description]
     */
    public function getLastId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * 更新数据库
     *
     * @param  [type] $data      [description]
     * @param  [type] $condition [description]
     *
     * @return [type]            [description]
     */
    public function update($data, $condition, $args=[]) {

        if (!$this->table) {
            ex('table not found');
        }

        if (!$data)
            ex('Update data can not empty !');

        //防止更新全部数据
        if (!$condition) {
            ex('Update conditions can not empty !');
        }

        if (is_array($data)){
            $colName = [];
            foreach ($data as $key => $value) {
                $colName[] = '`' . addslashes($key) . '` = ?';
            }
            $set = implode(',', $colName);
        }else{
            $set = $data;
        }

        if (is_array($condition)) {
            $condName = [];
            foreach ($condition as $key => $value) {
                $condName[] = '`' . addslashes($key) . '` = ?';
            }
            $where = implode(' AND ', $condName);
        }else{
            $where = $condition;
        }
        $sql = 'UPDATE `' . $this->table . '` SET ' . $set . ' WHERE ' . $where . '';

        // print_r($sql);
        try {
            if (!is_array($condition)){
                $condition = [];
            }
            $bindData = array_merge(array_values($data), array_values($condition));
            $sth = $this->pdo->prepare($sql);
            $this->_setLastSql($this->_interpolateQuery($sql, $bindData));
            if (FALSE === $sth->execute($bindData)) {
                //print_r($sth->errorInfo());die;
                $error = $sth->errorInfo();
                throw new \Exception(sprintf("%s ". PHP_EOL . "Last Sql: %s " . PHP_EOL . "%s", $error[2], $this->getLastSql(), $error[1]));
            }
            return true;
        } catch (\Exception $e) {
            ex("Mysql Update Error: ", $e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'Db Error');
        }
    }

    /**
     *  删除数据
     */
    public function delete($condition) {
        if (!$this->table) {
            ex("Table not found !");
        }

        if (!$condition) {
            ex('Delete Conditions can not empty!');
        }

        if (is_array($condition)) {
            $condName = [];
            foreach ($condition as $key => $value) {
                $condName[] = '`' . addslashes($key) . '` = ?';
            }
            $where = implode(' AND ', $condName);
        }else{
            $where = $condition;
        }

        $sql = 'DELETE FROM `' . $this->table . '` WHERE ' . $where . '';

        // print_r($sql);
        try {
            $sth = $this->pdo->prepare($sql);
            $this->_setLastSql($sth->queryString);
            if (!is_array($condition)){
                $condition = [];
            }
            if (FALSE === $sth->execute(array_values($condition))) {
                //print_r($sth->errorInfo());die;
                $error = $sth->errorInfo();
                throw new \Exception(sprintf("%s " . PHP_EOL . "Last Sql : %s" . PHP_EOL . "%s", $error[2], $this->getLastSql(), $error[1]));
            }
            return true;
        } catch (\Exception $e) {
            ex('Mysql Delete Error', $e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'Db Error');
        }
    }

}
