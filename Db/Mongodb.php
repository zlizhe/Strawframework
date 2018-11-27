<?php
namespace Strawframework\Db;

use function MongoDB\BSON\fromPHP;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Persistable;
use MongoDB\BSON\Regex;
use function MongoDB\BSON\toJSON;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\InsertMany;
use MongoDB\Operation\InsertOne;
use Strawframework\Base\DataViewObject;

/**
 * mongodb php Library
 * pecl install mongodb
 * http://php.net/mongodb
 */

class Mongodb {

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
            throw new \Exception('Mongodb extend not found.');

        try {
            //mongo connect link
            if ($config['username']){
                $mongoConnect = sprintf('mongodb://%s:%s@%s:%d', $config['username'], $config['password'], $config['host'], $config['port']);
            }else{
                $mongoConnect = sprintf('mongodb://%s:%d', $config['host'], $config['port']);
            }
            /**
             * http://php.net/manual/zh/mongodb-driver-manager.construct.php
             * uriOptions and driverOptions
             */
            $this->connect = new \MongoDB\Client($mongoConnect);
        } catch (\MongoConnectionException | \Exception $e) {
            throw new \Exception(sprintf("Mongodb connect error : ", $e->getMessage()));
        }
        //连接 current db 
        $this->db = $config['dbname'];

        unset($mongoConnect, $config);
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

            //绑定 data
            if (!empty($dataQuery))
                $data[$key] = $this->bindQuery($dataQuery, $data[$key]);

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
    public function insert($data, array $options = []){

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
    public function getOne($query = '', $field = [], $data = []) :? object {

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

            $options = $this->parseOptions($field);

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
     * @return mixed
     * @throws \Exception
     */
    public function getAll($query = [], $field = [], $sort = [], $skip = 0, $limit = 0, $data = []): ? array {
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

            $options = $this->parseOptions($field, $limit, $sort, $skip);

            $res = $this->collection->find($query, $options);

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
    public function count($query = [], $countField = '', $data = []): int{

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


            $res = $this->collection->countDocuments($query);

            return $res;
        } catch (\Exception $e){
            throw new \Exception(sprintf("Mongodb count error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
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
                $this->sqlQuery  .=  $field ? ', '.json_encode(array_values($options)[0]) : ', {}';
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
     * 兼容 mysql sql 方法 mongo 同 getAll
     * @param $query
     * @param $type
     */
    public function getQuery($query, $data = '') {
        return $this->getAll($query);
    }

    /**
     *  聚合操作
     * @param $query 条件
     * @param $group 聚合字段
     * @param $fun 聚合方法 sum avg min max push addToSet first last
     */
    public function aggregate($query = [], $group, $fun = 'sum', $param = 1){

        $pipeline = [];
        if (!empty($query)){
            $query = $this->parseQuery($query);

            array_push($pipeline, ['$match' => $query]);
        }
        array_push($pipeline, ['$group' => ['_id' => '$'.$group.'', 'n' => ['$'.$fun.'' => $param == 1 ? 1 : '$'.$param.'']]]);

        //记录查询语句
        if (TRUE == APP_DEBUG){
            $this->sqlQuery = $this->db.'.'.$this->collection . '.aggregate(';
            $this->sqlQuery .= json_encode($pipeline);
            $this->sqlQuery .= ')';
        }

        try{

            $commands = [
                'aggregate' => $this->collection,
                'pipeline' => $pipeline
            ];
            $result = $this->connect->executeCommand($this->db, new \MongoDB\Driver\Command($commands))->toArray();

            return $result[0]->result;
        } catch (\Exception $e){
            ex("Mongodb Run Aggregate Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
        }
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


    //更新的行数
    public $modifiedCount = 0;

    /**
     *  更新数据
     *  @param $data 新数据数组 array()
     *  @param $condition 更新条件 array()
     *
     */
    public function update($data, $condition, $args = []){
        $this->modifiedCount = 0;
        if (!$this->collection)
            ex('Collection not found');

        if (!$condition)
            ex('Update Conditions can not empty !');

        if (!empty($condition))
            $condition = $this->parseQuery($condition);
        
        try {
            $bulk = new \MongoDB\Driver\BulkWrite;

            //传入非数组
            if (!is_array($data))
                throw new \Exception((sprintf("Update value must to be array : %s", var_export($data, true))));

            $allData = [];
            if(count($data) == count($data, COUNT_RECURSIVE)){
                $allData[0] = $data;
            }else {
                $allData = $data;
            }

            $updateQuery = [];
            $br = false;
            //多维数组
            foreach ($allData as $value) {
                //传入 ['bulk' => true] 时批量更新
                if (!$args['bulk']){
                    $value = $data;
                    $br = true;
                }
                //like ['set' => '$addToSet'] or ['set' => '$pull'] ['set' => '$push'] e.g.
                //new data add $set 如果没有设置 set 则 '$set' => $value 如果 设置了 set 则 'set内容如 $pull' => $value
                $value = [$args['set'] ?: '$set' => $value];
                //$condition 中 可传入参数 $upsert = true 没有更新则新增数据
                $bulk->update($condition, $value, ['multi' => true, 'upsert' => $args['upsert'] ? true : false]);

                $updateQuery = array_merge($updateQuery, $value);

                //含子数组 并且 value 不是数组的 只有一行数据需要更新
                if (true == $br)
                    break;
            }

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery .= $this->db.'.'.$this->collection . '.updateMany(';
                $this->sqlQuery .= $condition ? json_encode($condition) : '{}';
                $this->sqlQuery .= ',' . json_encode($updateQuery) . ', ' . json_encode(['multi' => true, 'upsert' => $args['upsert'] ? true : false]) . ')';
            }

            unset($allData, $condition, $data);
            $reData = $this->connect->executeBulkWrite($this->db.'.'.$this->collection, $bulk);
            //getModifiedCount 真正修改的行数
            $this->modifiedCount = $reData->getModifiedCount();

            if ($args['upsert'] == 1){
                // 没有值 时写入了多少数据
                return $reData->getUpsertedCount() ?: false;
            }else{
                // 匹配到需要更新的数据量
                return $reData->getMatchedCount() ?: false;
            }
        } catch (\Exception $e) {
            ex("Mongodb Update Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
        }
    }

    /*
     *  删除数据
     *  @param $condition 删除条件 array()
     * */
    public function delete($condition){
        if (!$this->collection)
            ex('Collection not found');

        if (!$condition)
            ex('Delete Conditions can not empty !');

        if (!empty($condition))
            $condition = $this->parseQuery($condition);

        try {
            $bulk = new \MongoDB\Driver\BulkWrite;
            $_id = $bulk->delete($condition);

            //记录查询语句
            if (TRUE == APP_DEBUG){
                $this->sqlQuery .= $this->db.'.'.$this->collection . '.remove('.json_encode($condition).')';
            }

            $data = $this->connect->executeBulkWrite($this->db.'.'.$this->collection, $bulk);
            return $data->getDeletedCount() ?: false;
        } catch (\Exception $e) {
            ex("Mongodb Delete Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
        }
    }

    /**
     * 调试使用
     */
    //public function __debugInfo()
    //{
    //    return ['lastSql' => $this->getLastSql()];
    //}

}
