<?php
namespace Strawframework\Db;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Strawframework\Base\DataViewObject;
use Strawframework\Straw;

class Mysql extends Straw implements \Strawframework\Protocol\Db {

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

    //操作表名
    private $tableName;

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
        unset($this->table, $this->db, $this->tableName);
    }

    /**
     *  选择表
     */
    public function setTable($table) {
        $this->tableName = $table;
        $this->table = $this->db->table($table);
    }

    //取最后一次执行的 sql
    public function getLastSql() {
        return $this->table->toSql();
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

            preg_match('/_/', json_encode($data), $matchs);
            //绑定 data
            if (!empty($dataQuery)){
                $data[$key] = $this->bindQuery($dataQuery, $data[$key]);
            }else if (!empty($matchs)){
                throw new \Exception(sprintf('Data %s with DVO Alias must bind from ->data method.', var_dump($dvo, true)));
            }
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


    //db 待执行方法
    private static $dbFuns = [];
    /**
     * return db object
     * @return Builder
     */
    public function db($name, $argments = []): void{
        if (method_exists($this->table, $name)){
            self::$dbFuns[] = [$name => $argments];
        }else{
            throw new \Exception(sprintf('Method %s not found in db: %s', $name, $this->tableName));
        }
    }

    /**
     * 解析 model data
     * @param $modelData
     *
     */
    private function parseModelData($modelData){
        extract($modelData);

        if (!$field) {
            $field = $this->getAllField();
        }

        $this->table->select($field);

        //sql
        if (!empty($query)) {
            //dvo to array
            if ($query instanceof DataViewObject){
                $query = $this->parseDVO($query, $data);
                foreach ($query as $column => $value) {
                    if (is_array($value)){
                        foreach ($value as $operator => $v) {
                            $operator = substr($operator, 1);
                            $this->table->$operator($column, $v);
                        }
                    }else{
                        $this->table->where($column, $value);
                    }
                }
            }else{
                //pdo bind
                $this->table->whereRaw($query, $data);
            }
        }

        if ($order) {
            if (is_string($order)) {
                // created_at desc, id asc
                $orderList = explode(',', $order);
                foreach ($orderList as $orderOne) {
                    list($column, $direct) = preg_split('/\s+/', trim($orderOne));
                    $this->table->orderBy($column, $direct);
                }
            } else {
                // ['created_at' => 'desc', 'id' => 'asc']
                foreach ($order as $column => $direct) {
                    $this->table->orderBy($column, $direct);
                }
            }
        }

        if ($offset) {
            $this->table->offset($offset);
        }

        if ($limit) {
            $this->table->limit($limit);
        }
    }

    /**
     * 获取数据表所有字段
     */
    public function getAllField($table = '') {

        if (!$table) {
            $table = $this->tableName;
        }

        try{
            $field = Capsule::schema()->getColumnListing($table);
        }catch (\PDOException $e){
            $field = '*';
        }finally{
            return $field;
        }
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
     * @return Builder
     */
    public function getAll($query = '', $field = [], $order = '', $offset = NULL, $limit = NULL, $data = []) {

        try {

            $this->parseModelData(compact('query', 'field', 'order', 'offset', 'limit', 'data'));

            if (!empty(self::$dbFuns)){
                foreach (self::$dbFuns as $fun) {
                    foreach ($fun as $k => $f) {
                        call_user_func_array([$this->table, $k], $f);
                    }
                }
                self::$dbFuns = [];
            }

            $res = $this->table->get();

            return $res;
        }catch (\Exception $e){
            throw new \Exception(sprintf("Mysql getAll error %s.", $e->getMessage()));
        }
    }


    //获取行数
    public function count($query = '', $countField = '*', $data = []){
        return $this->getOne($query, "count($countField) AS count", $data)['count'];
    }

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
    public function getQuery($query = '', $data = [], $options = []) {
        return DB::select(DB::raw($query), $data);
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
        } catch (\Exception | QueryException $e) {
            throw new \Exception(sprintf("Mysql insert error %s.", $e->getMessage()));
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
