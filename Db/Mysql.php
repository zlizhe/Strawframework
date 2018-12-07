<?php
namespace Strawframework\Db;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Strawframework\Base\DataViewObject;
use Strawframework\Straw;

class Mysql extends Straw implements \Strawframework\Protocol\Db {

    //model 中执行的 sql
    private $sqlQuery = '';

    //数据库对象
    /*
     * @var Capsule
     */
    private $db;

    //操作表
    /**
     * @var Builder
     */
    private $table;

    public function __construct($config) {
        parent::__construct();

        $capsule = self::$container->{__CLASS__ . json_encode($config)};

        if (!$capsule) {

            $capsule = new Capsule;

            $capsule->addConnection([
                                        'driver'    => 'mysql',
                                        'host'      => $config['host'],
                                        'database'  => $config['dbname'],
                                        'username'  => $config['username'],
                                        'password'  => $config['password'],
                                        'charset'   => $config['charset'],
                                        'collation' => $config['collation'] ?? 'utf8_unicode_ci',
                                        'prefix'    => $config['prefix'],
                                    ]);

            // Set the event dispatcher used by Eloquent models... (optional)
            $capsule->setEventDispatcher(new Dispatcher(new Container));

            // Make this Capsule instance available globally via static methods... (optional)
            $capsule->setAsGlobal();

            // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
            $capsule->bootEloquent();
            self::$container->{__CLASS__ . json_encode($config)} = $capsule;
        }

        $this->db = $capsule;
    }

    public function __destruct() {
        $this->pdo = null;
        unset($this->sqlQuery, $this->table);
    }

    /**
     *  选择表
     */
    public function setTable($table) {
        $this->table = $this->db->table($table);
    }

    //设置最后执行的sql
    private function _setLastSql($sql) {
        $this->sqlQuery = $sql;
    }

    //取最后一次执行的 sql
    public function getLastSql() {
        return $this->sqlQuery;
    }

    /**
     * 解析 DVO
     * @param      $dvos
     * @param bool $genId 需要生成 _id
     *
     * @return array
     * @throws \Exception
     */
    private function parseDVO($dvos, ? array $dataQuery = []): array {

        $dvoArr = [];
        if (!is_array($dvos))
            $dvoArr[0] = $dvos;
        else{
            $dvoArr = $dvos;
        }

        $data = [];
        foreach ($dvoArr as $key => $dvo) {

            if (!($dvo instanceof DataViewObject))
                throw new \Exception(sprintf('Data %s must instance of DVO.', var_export($dvo, true)));

            $data[$key] = $dvo->getDvos();

            //绑定 data
            if (!empty($dataQuery))
                $data[$key] = $this->bindQuery($dataQuery, $data[$key]);
        }
        return is_array($dvos) ? $data : current($data);
    }

    /**
     * 开始绑定 :column
     * @param array $query
     * @param array $data
     *
     * @return array
     * @throws \Exception
     */
    private function bindQuery(array $query, array $data): array{

        $bindedData = [];

        foreach ($query as $key => $value) {

            if (is_array($value)){

                $bindedData[$key] = $this->bindQuery($value, $data);
            }else{
                //是待绑定数据
                if (':' == $value[0]){
                    $k = substr($value, 1);
                    if (!key_exists($k, $data))
                        throw new \Exception(sprintf('Bind key %s not found in DVO.', $k));
                    $bindedData[$key] = $data[$k];
                }
            }

        }
        return $bindedData;
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
    public function getQuery() {
        //return $this->doQuery($sql, $data, 'all', $type);
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
     * 插入数据
     * @param array $data
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function insert($data, $options = []) {
        try {
            if (!$this->table)
                throw new \Exception('Please set table first.');

            $allData = $this->parseDVO($data, null);
            //$res = new \stdClass();
            //有选项返回最后插入的 id
            if (true == $options['lastid']){
                $res = $this->table->insertGetId($allData);
            }else{
                $res = $this->table->insert($allData);
            }
            return $res;
        } catch (QueryException $e) {
            throw new \Exception(sprintf("Mysql insert error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     * 更新数据
     * @param       $setData
     * @param       $condition
     * @param array $data
     *
     * @return int|mixed
     * @throws \Exception
     */
    public function update($setData, $condition, $data = []) {

        try{
            if (!$this->table)
                throw new \Exception('Please set table first.');

            //不允许条件为空 防止全表更新
            if (!$condition)
                throw new \Exception('Condition can not empty.');

            $condition = $this->parseDVO($condition, $data);

            $res = $this->table->where($condition)->update($setData);
            return $res;
        } catch (QueryException $e) {
            throw new \Exception(sprintf("Mysql update error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
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
