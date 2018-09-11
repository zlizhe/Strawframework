<?php
namespace strawframework\db;
/**
 * mongodb php 新扩展
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
     */
    public function __construct($config = []){

        if (!extension_loaded("mongodb"))
            ex("Mongodb Extend Not Support!");

        try {
            //mongo connect link
            if ($config['username']){
                $mongoConnect = sprintf('mongodb://%s:%s@%s:%d', $config['username'], $config['password'], $config['host'], $config['port']);
            }else{
                $mongoConnect = sprintf('mongodb://%s:%d', $config['host'], $config['port']);
            }
            //@todo https://github.com/mongodb/mongo-php-driver/issues/374#issuecomment-323404660 fix some bug ['ssl' => false] 2017/11/14
            $this->connect = new \MongoDB\Driver\Manager($mongoConnect, ['ssl' => false]);
            //$mongoConnect = sprintf('mongodb://%s:%d', $config['host'], $config['port']);
            //$this->connect = new \MongoDB\Driver\Manager($mongoConnect, $config['username'] ? ['username' => $config['username'], 'password' => $config['password'], 'ssl' => false] : ['ssl' => false]);
        } catch (\Exception $e) {
            ex("Mongodb Connect Error", $e->getMessage());
        }
        //连接 current db 
        $this->db = $config['dbname'];
        //$this->db = $mongo->executeCommand($config['dbname'], new \MongoDB\Driver\Command(["dbstats" => 1]));


        unset($mongoConnect, $config);
        //每次都重新选择表
        $this->collection = null;
        //清空当前 查询语句
        $this->sqlQuery = '';
    }

    public function __destruct(){
        unset($this->collection, $this->db); 
    }    
    /**
     * 选择表
     * @param [type] $collection [description]
     */
    public function setTable($collection){
        $this->collection = $collection;
    }

    //取最后一次执行的 sql
    public function getLastSql() {
        return $this->sqlQuery;
    }

    //写入 data
    public function insert(array $data, array $args = []){
        if (!$this->collection)
            ex('Collection not found');


        try {
            $bulk = new \MongoDB\Driver\BulkWrite;

            //传入非数组
            if (!is_array($data))
                throw new \Exception((sprintf("Insert value must to be array : %s", var_export($data, true))));

            $allData = [];
            if(count($data) == count($data, COUNT_RECURSIVE)){
                $allData[0] = $data;
            }else{
                $allData = $data;
            }

            //记录查询语句
            $this->sqlQuery .= $this->db.'.'.$this->collection . '.insertMany(';
            $this->sqlQuery .= $allData ? json_encode($allData) : '{}';
            $this->sqlQuery .= ')';

            //多维数组
            $_id = [];
            $br = false;
            foreach ($allData as $key => $value) {
                //传入 ['bulk' => true] 时批量插入
                if (!$args['bulk']){
                    $value = $data;
                    $br = true;
                }
                $_id[$key] = (string)$bulk->insert($value);

                //含有子数组 并 value 不为数组时 只有一行数据需要写入
                if (true == $br)
                    break;
            }


            $reData = $this->connect->executeBulkWrite($this->db.'.'.$this->collection, $bulk);
            //写入失败了
            if ($writeConcernError = $reData->getWriteConcernError()) {
                throw new \Exception(sprintf("%s (%d): %s", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true)));
            }
            //批量更新
            if ($args['bulk']){
                //全部成功
                if ($reData->getInsertedCount() == count($allData)){
                    //返回所有插入成功的 id
                    return $_id;
                }else{
                    return false;
                }
            }else{
                //一条数据写入
                return current($_id);
            }
            //\MongoDB\Driver\Exception\BulkWriteException
        } catch (\Exception $e) {
            ex("Mongodb Insert Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
        }
    }


    /**
     *  根据查询条件返回一条结果
     */
    public function getOne($query = '', $field=[], $data = '') : array {

        if (!$this->collection)
            ex('Collection not found');

        if (!empty($query))
            $query = $this->parseQuery($query);

        //记录查询语句
        $this->sqlQuery = $this->db.'.'.$this->collection . '.find(';
        $this->sqlQuery .= $query ? json_encode($query) : '{}';

        try{

            $res = $this->connect->executeQuery(
                $this->db.'.'.$this->collection,
                new \Mongodb\Driver\Query($query, $this->getOptions($field, $limt=1))
            )->toArray();

            if ($res){
                $res = current($res);
                $res->_id = (string)$res->_id;
            }
            return (array)$res;
        } catch (\Exception $e){
            ex("Mongodb Find Error", sprintf("%s ".PHP_EOL.'Last Query : %s', $e->getMessage(), $this->getLastSql()), 'DB Error');
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

    private function getOptions($field = [], $limit = 0, $sort = [], $skip = 0){
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
            if (empty($falseField)){
                $options = $newField;
            }else{
                $options = $falseField;
            }

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
     * 解析 query 中的关键词  like _id
     */
    private function parseQuery($arr){

        foreach ($arr as $key => $value) {

            // 查询字段的安全过滤
            if(!preg_match('/^[A-Z_\|\&\-.a-z0-9|$]+$/',trim($key))){
                ex(sprintf("不合法的查询名称 : %s", $key));
            }

            //包含 _id 不是 mongoid 对象 也不是数组 处理成 mongoid对象
            if (strtolower($key) == '_id' && (!is_object($value) && !is_array($value))){
                $arr[$key] = new \MongoDB\BSON\ObjectId($value);
            }

            if (is_array($value) && count($value)){
                //包含 like
                if (array_key_exists('$like', $value)){
                    $arr[$key] = new \Mongodb\BSON\Regex($value['$like'], 'i');
                }else{
                    //还是数组 继续查找
                    $arr[$key] = $this->parseQuery($value);
                }
            }
        }
        return $arr;
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

