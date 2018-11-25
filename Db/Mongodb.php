<?php
namespace Strawframework\Db;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\Model\BSONDocument;
use Strawframework\Base\DataViewObject;

/**
 * mongodb php Library
 * pecl install mongodb
 * http://php.net/mongodb
 */

class Mongodb{

    //db obj
    private $db;

    //mongodb connect obj
    private $connect;

    //操作表
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
    private function parseDVO($dvos, bool $genId = false): array {

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
            if (true == $genId && !key_exists('_id', $data[$key]))
                $data[$key]['_id'] = new ObjectId();

            $data[$key] = $this->parseQuery($data[$key]);
        }
        return is_array($dvos) ? $data : current($data);
    }

    //https://docs.mongodb.com/php-library/master/tutorial/crud/#insert-one-document
    const INSERT_TYPE_ONe = 'insertOne';
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
                $insertType = self::INSERT_TYPE_ONe;
            }
            $allData = $this->parseDVO($data, true);

            //记录查询语句
            $this->sqlQuery .= $this->collection . '.'.$insertType.'(';
            $this->sqlQuery .= $allData ? json_encode($allData) : '{}';
            $this->sqlQuery .= ')';


            $insertData = $this->collection->{$insertType}($allData, $options);
            return $insertData;
        } catch (\Exception $e) {
            throw new \Exception(sprintf("Mongodb insert error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }


    /**
     *  根据查询条件返回一条结果
     */
    public function getOne($query = '', $field = []) {

        try{

            if (!$this->collection)
                throw new \Exception('Please set table first.');

            if (!empty($query))
                $query = $this->parseDVO($query);

            //记录查询语句
            $this->sqlQuery = $this->collection . '.findOne(';
            $this->sqlQuery .= $query ? json_encode($query) : '{}';

            if (!empty($field))
                $options = $this->parseOptions($field);

            $res = $this->collection->findOne($query, $options ?? []);
            return $res;
        } catch (\Exception $e){
            throw new \Exception(sprintf("Mongodb getOne error %s. - Last Query: %s", $e->getMessage(), $this->getLastSql()));
        }
    }

    /**
     *  获取一行 一个字段
     */
    public function getCol($query, $col, $data = ''){
        return $this->getOne($query, $col)[$col];
    }

    /**
     * 统计数量
     * @param       $query
     * @param array $field
     */
    public function count($query, $countField='', $data = ''){

        if (!$this->collection)
            ex('Collection not found');

        //记录查询语句
        $this->sqlQuery = $this->db.'.'.$this->collection . '.find(';
        $this->sqlQuery .= $query ? json_encode($query) : '';
        $this->sqlQuery .= ').count()';

        if (!empty($query))
            $query = $this->parseQuery($query);

        try{

            $commands = [
                'count' => $this->collection,
                'query' => $query
            ];
            $result = $this->connect->executeCommand($this->db, new \MongoDB\Driver\Command($commands))->toArray();

            return $result[0]->n ?: 0;
        } catch (\Exception $e){
            ex("Mongodb Count Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
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

        if (!is_array($field))
            $field = [$field];

        $options = [];
        if (!empty($field)){
            $newField = [];
            $falseField = [];
            foreach($field as $key => $value){
                if (false != $value){
                    $newField['projection'][$value] = 1;
                }else{
                    //如果有 false
                    $falseField['projection'][$key] = 0;
                }
            }
            //if (empty($falseField)){
            //    $options = $newField;
            //}else{
            //    $options = $falseField;
            //}
            $options['projection'] = array_merge($newField['projection'], $falseField['projection']);

            //field 语句记录
            if(count($newField)) {
                $this->sqlQuery  .=  $field ? ', '.json_encode(array_values($options)[0]) : ', {}';
            }
        }

        $this->sqlQuery  .=  ')';

        //如果设置了 order => direction 搜索值 desc / asc to  -1 / 1
        if (!empty($sort)){
            foreach ($sort as $key => $value) {
                $sort[$key] = (int)str_ireplace(['DESC', 'ASC'], [-1, 1], $value);
            }
        }
        if ($sort){
            $options['sort'] = $sort;
            $this->sqlQuery .= '.sort('.json_encode($sort).')';
        }

        if ($skip){
            $options['skip'] = $skip;
            $this->sqlQuery .= '.skip('.intval($skip).')';
        }

        if ($limit){
            $options['limit'] = $limit;
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
        $this->sqlQuery = $this->db.'.'.$this->collection . '.aggregate(';
        $this->sqlQuery .= json_encode($pipeline);
        $this->sqlQuery .= ')';

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
     * 解析 query 中的关键词  like 与 _id
     * @param $data
     *
     * @return mixed
     * @throws \Exception
     */
    private function parseQuery($data){

        foreach ($data as $key => $value) {

            // 查询字段的安全过滤
            if(!preg_match('/^[A-Z_\|\&\-.a-z0-9|$]+$/',trim($key)))
                throw new \Exception(sprintf("Query column %s invalid.", $key));

            //包含 _id 不是 mongoid 对象 也不是数组 处理成 mongoid对象
            if (strtolower($key) == '_id' && (!($value instanceof ObjectId) && !is_array($value))) {
                $data[$key] = new ObjectId($value);
            }

            if (is_array($value) && count($value)){
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
     * 查询所有结果 根据条件分类
     */
    public function getAll($query = [], $field = [], $sort = [], $skip = 0, $limit = 0, $data = '') : array {
        if (!$this->collection)
            ex('Collection not found');

        //兼容 mysql 空字符串 转 空数组
        if ($query == '')
            $query = [];

        if (!empty($query))
            $query = $this->parseQuery($query);

        //记录查询语句
        $this->sqlQuery = $this->db.'.'.$this->collection . '.find(';
        $this->sqlQuery .= $query ? json_encode($query) : '{}';

//        var_dump($this->getOptions($field, $limit, $sort, $skip));die;
        try{
            $res = $this->connect->executeQuery(
                $this->db.'.'.$this->collection,
                new \Mongodb\Driver\Query($query, $this->getOptions($field, $limit, $sort, $skip))
            )->toArray();
            foreach ($res as $key => $value) {
                $value->_id = (string)$value->_id;
                $res[$key] = (array)$value;
            }

            return $res;
        } catch (\Exception $e) {
            ex("Mongodb Find Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');

        }
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
            $this->sqlQuery .= $this->db.'.'.$this->collection . '.updateMany(';
            $this->sqlQuery .= $condition ? json_encode($condition) : '{}';
            $this->sqlQuery .= ',' . json_encode($updateQuery) . ', ' . json_encode(['multi' => true, 'upsert' => $args['upsert'] ? true : false]) . ')';

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
            $this->sqlQuery .= $this->db.'.'.$this->collection . '.remove('.json_encode($condition).')';

            $data = $this->connect->executeBulkWrite($this->db.'.'.$this->collection, $bulk);
            return $data->getDeletedCount() ?: false;
        } catch (\Exception $e) {
            ex("Mongodb Delete Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
        }
    }
}

