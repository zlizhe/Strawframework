<?php
namespace Strawframework\Db;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\DeleteResult;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\Operation\DeleteMany;
use MongoDB\Operation\DeleteOne;
use MongoDB\Operation\InsertMany;
use MongoDB\Operation\InsertOne;
use MongoDB\Operation\UpdateMany;
use MongoDB\Operation\UpdateOne;
use MongoDB\UpdateResult;
use Strawframework\Base\DataViewObject;
use Strawframework\Protocol\Db;
use Strawframework\Straw;

/**
 * mongodb php Library
 * pecl install mongodb
 * http://php.net/mongodb
 */

class Mongodb extends Straw implements Db {

    //db obj
    private $db;

    /**
     * Mongodb Connect object
     * @var \MongoDB\Client
     */
    private $connect;

    /**
     * Mongodb 表对象
     * @var \MongoDB\Collection
     */
    private $collection = null;

	//model 中执行的 sql
	private $sqlQuery = '';

    /**
     * 连接 mongodb
     * 选择 test -> user
     * Mongodb constructor.
     *
     * @param $config
     *
     * @throws \Exception
     */
    public function __construct($config){

        if (!extension_loaded("mongodb"))
            throw new \Exception('Mongodb extend mongodb not found.');

        $mongoHandle = self::$container->{__CLASS__ . json_encode($config)};

        if (!$mongoHandle) {
            try {
                //mongo connect link
                if ($config['username']) {
                    $mongoConnect = sprintf('mongodb://%s:%s@%s:%d', $config['username'], $config['password'], $config['host'], $config['port']);
                } else {
                    $mongoConnect = sprintf('mongodb://%s:%d', $config['host'], $config['port']);
                }
                /**
                 * http://php.net/manual/zh/mongodb-driver-manager.construct.php
                 * uriOptions and driverOptions
                 */
                $mongoHandle = new \MongoDB\Client($mongoConnect);
            } catch (\MongoConnectionException | \Exception $e) {
                throw new \Exception(sprintf("Mongodb connect error : ", $e->getMessage()));
            }

            unset($mongoConnect);
            self::$container->{__CLASS__ . json_encode($config)} = $mongoHandle;
        }

        $this->connect = $mongoHandle;

        //连接 current db
        $this->db = $config['dbname'];
        //每次都重新选择表
        $this->collection = null;
        //清空当前 查询语句
        $this->sqlQuery = '';
    }

    public function __destruct(){
        unset($this->collection, $this->db, $this->sqlQuery);
    }

    /**
     * 选择待操作表对象
     * @param $collection
     *
     * @throws \Exception
     */
    public function setTable($collection){
        try{

            $this->collection = $this->connect->{$this->db}->{$collection};
        }catch (\MongoException $e){
            throw new \Exception(sprintf('Mongodb select collection error : ', $e->getMessage()));
        }
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
    private function parseDVO($dvos, ? array $dataQuery = [], bool $genId = false): array {

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
            //_id 不存在时 手动写入
            if (true == $genId && !key_exists('_id', $data[$key])){
                $data[$key]['_id'] = new ObjectId();
            }

            preg_match('/(\b_\w+)/', json_encode($data[$key]), $matches);

            //绑定 data
            if (!empty($dataQuery)){
                $data[$key] = $this->bindQuery($dataQuery, $data[$key]);
            }else if (!empty($matches)){
                throw new \Exception(sprintf('Data %s with DVO Alias must bind from ->data method.', json_encode($data[$key])));
            }

            //解析特殊字
            $data[$key] = $this->parseQuery($data[$key]);
        }
        return is_array($dvos) ? $data : current($data);
    }


    //https://docs.mongodb.com/php-library/master/tutorial/crud/#insert-one-document
    const INSERT_TYPE_ONE = 'insertOne';
    //https://docs.mongodb.com/php-library/master/tutorial/crud/#insert-many-documents
    const INSERT_TYPE_MANY = 'insertMany';


    /**
     * 写入 data
     * https://docs.mongodb.com/php-library/master/reference/method/MongoDBCollection-insertOne/#phpmethod.MongoDB\Collection::insertOne
     * @param       $data
     * @param array $options
     *
     * @return InsertOneResult | InsertManyResult
     * @throws \Exception
     */
    public function insert($data, $options = []){

        try {
            if (!$this->collection)
                throw new \Exception('Please set table first.');

            //非 DVO
            if (is_array($data)){
                //实现方式
                $insertType = self::INSERT_TYPE_MANY;
            }else{
                $insertType = self::INSERT_TYPE_ONE;
            }
            //var_dump($data);die;
            //var_dump(toJSON(fromPHP($this)));die;

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $allData = $this->parseDVO($data, null, false);
                $this->sqlQuery .= $this->collection . '.'.$insertType.'(';
                $this->sqlQuery .= $allData ? json_encode($allData) : '{}';
                $this->sqlQuery .= ')';
            }


            /* @var InsertMany | InsertOne */
            $insertData = $this->collection->{$insertType}($data, $options);
            return $insertData;
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb insert error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     *  根据查询条件返回一条结果
     * @param string $query
     * @param array  $field
     * @param array  $data
     *
     * @return object|null
     * @throws \Exception
     */
    public function getOne($query = '', $field = [], $data = [], $options = []) :? object {

        try{

            if (!$this->collection)
                throw new \Exception('Please set table first.');

            if (!empty($query))
                $query = $this->parseDVO($query, $data);

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery = $this->collection . '.findOne(';
                $this->sqlQuery .= $query ? json_encode($query) : '{}';
            }

            $fieldOptions = $this->parseOptions($field);
            if (!empty($options))
                $options = array_merge($options, $fieldOptions);
            else{
                $options = $fieldOptions;
            }


            $res = $this->collection->findOne($query, $options ?? []);
            return $res;
        } catch (\Exception $e){
            throw new \Exception(sprintf("Mongodb getOne error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     * 查询所有结果 根据条件分类
     * @param array $query
     * @param array $field
     * @param array $sort
     * @param int   $skip
     * @param int   $limit
     * @param array $data
     *
     * @return array
     * @throws \Exception
     */
    public function getAll($query = [], $field = [], $sort = [], $skip = 0, $limit = 0, $data = [], $options = []): ? array {
        try{

            if (!$this->collection)
                throw new \Exception('Please set table first.');

            //兼容 mysql 空字符串 转 空数组
            if ($query == '')
                $query = [];

            if (!empty($query))
                $query = $this->parseDVO($query, $data);

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery = $this->collection . '.find(';
                $this->sqlQuery .= $query ? json_encode($query) : '{}';
            }

            $fieldOptions = $this->parseOptions($field, $limit, $sort, $skip);
            if (!empty($options))
                $options = array_merge($options, $fieldOptions);
            else{
                $options = $fieldOptions;
            }

            $res = $this->collection->find($query, $options ?? []);

            //echo (json_encode(($res->toArray())));die;
            return $res->toArray();
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb getAll error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));

        }
    }

    /**
     * 统计文档数量 (统计其他字段转发至 aggregate)
     * @param array  $query
     * @param string $countField
     * @param array  $data
     *
     * @return int
     * @throws \Exception
     */
    public function count($query = [], $countField = '', $data = [], $options = []): int{

        try{
            if (!$this->collection)
                throw new \Exception('Please set table first.');

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery = $this->db.'.'.$this->collection . '.find(';
                $this->sqlQuery .= $query ? json_encode($query) : '';
                $this->sqlQuery .= ').count()';
            }

            if (!empty($query))
                $query = $this->parseDVO($query, $data);


            $res = $this->collection->countDocuments($query, $options);

            return $res;
        } catch (\Exception $e){
            throw new \Exception(sprintf("Mongodb count error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }


    //https://docs.mongodb.com/php-library/master/tutorial/crud/#update-one-document
    const UPDATE_TYPE_ONE = 'updateOne';
    //https://docs.mongodb.com/php-library/master/tutorial/crud/#find-many-documents
    const UPDATE_TYPE_MANY = 'updateMany';

    /**
     *  更新数据
     * @param       $setData
     * @param       $condition
     * @param array $data
     * @param array $options
     *
     * @return UpdateResult
     * @throws \Exception
     */
    public function update($setData, $condition, $data = [], $options = []){
        try {
            if (!$this->collection)
                throw new \Exception('Please set table first.');

            //不允许条件为空 防止全表更新
            if (!$condition)
                throw new \Exception('Condition can not empty.');

            $condition = $this->parseDVO($condition, $data);

            //默认贪婪更新
            if (true == $options['multi'] || !isset($options['multi'])){
                //实现方式
                $updateType = self::UPDATE_TYPE_MANY;
            }else{
                $updateType = self::UPDATE_TYPE_ONE;
            }

            //没有设置 操作符号 默认设置为 $set
            if ($setData instanceof DataViewObject){
                $setData = ['$set' => $setData];
            }

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $allData = $this->parseDVO($setData, null, false);
                $this->sqlQuery .= $this->collection . '.' . $updateType . '(';
                $this->sqlQuery .= $condition ? json_encode($condition) : '{}';
                $this->sqlQuery .= ',' . json_encode($allData) . ', ' . json_encode($options) . ')';
            }

            /* @var UpdateMany | UpdateOne */
            $res = $this->collection->{$updateType}($condition, $setData, $options);

            return $res;
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb update error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }


    //https://docs.mongodb.com/php-library/master/tutorial/crud/#delete-one-document
    const DELETE_TYPE_ONE = 'deleteOne';
    //https://docs.mongodb.com/php-library/master/tutorial/crud/#delete-many-documents
    const DELETE_TYPE_MANY = 'deleteMany';

    /**
     *  删除数据
     * @param $condition
     *
     * @return DeleteResult
     * @throws \Exception
     */
    public function delete($condition, $data = [], $options = []){
        try {
            if (!$this->collection)
                throw new \Exception('Please set table first.');

            //不允许条件为空 防止全表删除
            if (!$condition)
                throw new \Exception('Condition can not empty.');

            //默认贪婪
            if (true == $options['multi'] || !isset($options['multi'])){
                //实现方式
                $deleteType = self::DELETE_TYPE_MANY;
            }else{
                $deleteType = self::DELETE_TYPE_ONE;
            }
            $condition = $this->parseDVO($condition, $data);

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery .= $this->collection . '.' . $deleteType . '('.json_encode($condition).')';
            }

            /* @var DeleteOne | DeleteMany */
            $res = $this->collection->{$deleteType}($condition, $options);

            return $res->getDeletedCount();
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb delete error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }


    /**
     * 执行其他方法 若方法不存在允许调用 collection 下的方法，其他的方法不走此方法
     * @param       $query
     * @param array $data
     * @param array $options
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function getQuery($query = [], $data = [], $options = []) {

        if (!$this->collection) {
            throw new \Exception('Please set table first.');
        }

        //不允许条件为空 防止全表删除
        if (!$query) {
            throw new \Exception('Query can not empty.');
        }

        if (!$options['method']) {
            throw new \Exception('Mongodb must set method for options to run collection\'s method.');
        }


        try {
            if (method_exists($this, $options['method'])) {
                unset($options['method']);
                $this->{$options['method']}($query, $data, $options);
            } else {
                unset($options['method']);
                $this->collection->{$options['method']}($query, $options);
            }
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb getQuery error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     * Mongodb 复杂查询
     * @param array $query
     * @param array $data
     * @param array $options
     *
     * @return mixed
     * @throws \Exception
     */
    private function aggregate($query, $data = [], $options = []){

        try{
            //必须含有 group
            if (!key_exists('$group', $query)){
                throw new \Exception('Can not find $group in query.');
            }

            //match 数据执行绑定与过滤
            if ($query['$match']){
                $query['$match'] = $this->parseDVO($query['$match'], $data);
            }

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery = $this->db.'.'.$this->collection . '.aggregate(';
                $this->sqlQuery .= json_encode($query);
                $this->sqlQuery .= ')';
            }

            $res = $this->collection->aggregate($query, $options);
            return $res;
        } catch (\Exception $e){
            throw new \Exception(sprintf("Mongodb aggregate error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     * 解析Mongodb Options
     * @param array $field
     * @param int   $limit
     * @param array $sort
     * @param int   $skip
     *
     * @return array
     */
    private function parseOptions($field = [], $limit = 0, $sort = [], $skip = 0){
        //兼容 mysql * to everything
        if ('*' == $field)
            $field = [];

        if ($field && !is_array($field))
            $field = [$field];

        $options = [];
        if (!empty($field)){
            $newField = [];
            $falseField = [];
            foreach($field as $key => $value){
                if (false != $value){
                    $newField[$key] = 1;
                }else{
                    //如果有 false
                    $falseField[$key] = 0;
                }
            }
            //if (empty($falseField)){
            //    $options = $newField;
            //}else{
            //    $options = $falseField;
            //}
            $options['projection'] = array_merge($newField, $falseField);

            //field 语句记录
            if(!empty($options['projection']) && TRUE == APP_DEBUG) {
                $this->sqlQuery  .=  $field ? ', '.json_encode(array_values($options['projection'])) : ', {}';
            }
        }

        if (TRUE == APP_DEBUG)
            $this->sqlQuery  .=  ')';

        //如果设置了 order => direction 搜索值 desc / asc to  -1 / 1
        if (!empty($sort)){
            foreach ($sort as $key => $value) {
                $sort[$key] = (int)str_ireplace(['DESC', 'ASC'], [-1, 1], $value);
            }
        }
        if ($sort){
            $options['sort'] = $sort;
            if (TRUE == APP_DEBUG)
                $this->sqlQuery .= '.sort('.json_encode($sort).')';
        }

        if ($skip){
            $options['skip'] = $skip;
            if (TRUE == APP_DEBUG)
                $this->sqlQuery .= '.skip('.intval($skip).')';
        }

        if ($limit){
            $options['limit'] = $limit;
            if (TRUE == APP_DEBUG)
                $this->sqlQuery .= '.limit('.intval($limit).')';
        }

//        echo "<pre>";
//        print_r($options);die;
        return $options;
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
        //
        ////绑定字段
        //$data = (preg_replace_callback(
        //    '[\":\w+\"]',
        //    function ($matches) use ($data) {
        //        $k = substr($matches[0], 2, -1); //取到引号，让 replace 时 json 类型正确
        //        if (!key_exists($k, $data))
        //            throw new \Exception(sprintf('Bind key %s not found in DVO.', $k));
        //        return RequestObject::convert($data[$k], $data[]);
        //    },
        //    serialize($query)
        //));
        //var_dump($data);die;


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
     * 解析 query 中的关键词  like 与 _id
     * @param $data
     *
     * @return mixed
     * @throws \Exception
     */
    private function parseQuery(array $data){

        foreach ($data as $key => $value) {

            // 查询字段的安全过滤
            if(!preg_match('/^[A-Z_\|\&\-.a-z0-9|$]+$/',trim($key)))
                throw new \Exception(sprintf("Query column %s invalid.", $key));

            //包含 _id 不是 mongoid 对象 也不是数组 处理成 mongoid对象
            if (strtolower($key) == '_id' && (!($value instanceof ObjectId) && !is_array($value))) {
                $data[$key] = new ObjectId($value);
            }

            if (is_array($value) && !empty($value)){
                //包含 like
                if (array_key_exists('$like', $value)){
                    $data[$key] = new Regex($value['$like'], 'i');
                }else{
                    //还是数组 继续查找
                    $data[$key] = $this->parseQuery($value);
                }
            }
        }
        return $data;
    }

    /**
     * 获取所有表字段
     * @throws \Exception
     */
    public function getAllField(){
        throw new \Exception('Mongodb can not provide this method.');
    }
}
