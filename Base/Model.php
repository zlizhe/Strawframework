<?php
namespace Strawframework\Base;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use Strawframework\Db\Mongodb;
use Strawframework\Db\Mysql;
use Strawframework\Straw, Strawframework\Protocol\Db;

/**
 * 模型基类
 * 初始化数据库配置
 * Class Model
 * @package library
 */
class Model extends Straw implements Db{

    /**
     * 当前数据库对象
     * @var Mongodb Mysql
     */
    protected $db;

    //数据库名
    protected $dbName;

    //数据库连接别名
    protected $dbTag;

    //当前操作表
    protected $table;

    //当前表前缀
    protected $pre;

    //当前配置 -> database
    protected static $dbArr = [];

    ////设置 $_id 自动取一条数据
    //protected $_value = NULL;
    //
    //// 设置更新的字段名称
    //protected $_field = 'id';

    ////快速更新值
    //public function __set(string $name, string $value): bool {
    //
    //    if (!is_null($this->_value)) {
    //        return $this->update([$name => $value], [$this->_field => $this->_value]) ? TRUE : FALSE;
    //    }
    //
    //}
    //
    ////如果设置了 $_id 自动取 该数据值
    //public function __get(string $name = '_ALL_') {
    //
    //    if (!is_null($this->_value)) {
    //
    //        $data = $this->query([$this->_field => $this->_value])->getOne();
    //
    //        if ($name == '_ALL_') {
    //            return $data ?? null;
    //        } else {
    //            return $data[$name] ?? null;
    //        }
    //    }
    //}


    /**
     * 配置数据库 懒连接
     * Model constructor.
     *
     * @param null|string $dbTag
     */
    public function __construct(? string $dbTag = DEFAULT_DB) {
        parent::__construct();

        // 获取数据库操作对象
        if (!self::$dbArr[$dbTag]) {
            self::$dbArr[$dbTag] = Straw::$config['databases'][$dbTag];
        }

        $this->dbTag = $dbTag;
        //设置当前使用的db
        $this->dbName = Straw::$config['databases'][$dbTag]['DB_NAME'];

        //表前缀设置
        if (is_null($this->pre)) {
            $pre = self::$dbArr[$dbTag]['DB_PREFIX'] ?: '';
        }else{
            $pre = $this->pre;
        }

        //表名
        if (is_null($this->table)) {
            $this->table = strtolower(end(explode('\\', get_called_class())));
        }

        //设置最终表名
        if ($pre && strpos($this->table, $pre) !== 0) {
            $this->table = $pre . $this->table;
        }
    }

    //主从数据库
    private $dbArray = [];

    /**
     * 连接 数据库
     * @param null|string $type read | write
     *
     * @throws \Exception
     */
    private function _getConnect(?string $type = 'read'): void {

        //引入 对应 db 的库文件
        $dbClass = self::$dbArr[$this->dbTag]['DB_TYPE'];

        //驱动是否存在
        // db namespace
        $dbClass = '\Strawframework\\Db\\' . $dbClass;
        if (FALSE === class_exists($dbClass))
            throw new \Exception(sprintf('Database driver %s not found.', $dbClass));

        /**
         * 支持分布式数据库配置
         * 1台读服务器 + 多台写服务器配置
         */
        $hostArray = explode(',', self::$dbArr[$this->dbTag]['DB_HOST']);
        //超过1个数据库集
        if (count($hostArray) > 1) {
            $portArray = explode(',', self::$dbArr[$this->dbTag]['DB_PORT']);
            $userArray = explode(',', self::$dbArr[$this->dbTag]['DB_USER']);
            $pwdArray = explode(',', self::$dbArr[$this->dbTag]['DB_PWD']);
            foreach ($hostArray as $key => $value) {
                $this->dbArray[] = [
                    'host'     => $value,
                    'port'     => $portArray[$key] ?: $portArray[0],
                    'username' => $userArray[$key] ?: $userArray[0],
                    'password' => $pwdArray[$key] ?: $pwdArray[0],
                    'dbname'   => $this->dbName,
                    'charset'  => self::$dbArr[$this->dbTag]['DB_CHARSET']
                ];
            }
            unset($portArray, $userArray, $pwdArray);

            //读写分离
            if (TRUE == self::$dbArr[$this->dbTag]['WRITE_MASTER']) {

                //第一台数据库为 写 其他为读
                if ('write' == $type) {
                    $this->db = new $dbClass($this->dbArray[0]);
                } else {
                    $readArray = $this->dbArray;
                    //去除写数据库
                    unset($readArray[0]);
                    //打乱所有读数据库集
                    shuffle($readArray);
                    $this->db = new $dbClass($readArray[0]);
                }
            } else {

                //随机读写
                $randDb = shuffle($this->dbArray);
                $this->db = new $dbClass($randDb[0]);
            }


        } else {

            //单个数据库集
            //db obj
            $this->db = new $dbClass([
                                         'host'     => self::$dbArr[$this->dbTag]['DB_HOST'],
                                         'port'     => self::$dbArr[$this->dbTag]['DB_PORT'],
                                         'username' => self::$dbArr[$this->dbTag]['DB_USER'],
                                         'password' => self::$dbArr[$this->dbTag]['DB_PWD'],
                                         'dbname'   => $this->dbName,
                                         'charset'  => self::$dbArr[$this->dbTag]['DB_CHARSET'],
                                     ]);
        }
        //选择待操作表
        $this->db->setTable($this->table);
    }

    //查询条件使用 连贯操作
    //连贯操作 数组
    private $_modelData = [];

    // 连贯操作 where 语句查询
    // * @param string|array|object $query  查询条件
    public function query($where = []): Model {

        $this->_modelData['query'] = $where;

        return $this;
    }

    // 连贯操作 绑定数据
    // 开始绑定属性至 :propName
    public function data(array $data): Model {

        if (!empty($data)) {
            $this->_modelData['data'] = $data;
        }

        return $this;
    }

    // 连贯操作 需要查询的字段
    // * @param string|array $field  需要查询的字段,字符串或者数组
    public function field($field = ''): Model {

        $this->_modelData['field'] = $field;

        return $this;
    }

    // 连贯操作 order 条件
    // * @param string|array $order  排序条件
    public function order($order): Model {

        if ($order) {
            $this->_modelData['order'] = $order;
        }

        return $this;
    }

    // 连贯操作  offset 量
    // * @param int          $offset 数据偏移量
    public function offset(int $offset): Model {
        if ($offset) {
            $this->_modelData['offset'] = $offset;
        }

        return $this;
    }

    // 连贯操作  limit 量
    public function limit(int $limit): Model {
        if ($limit) {
            $this->_modelData['limit'] = $limit;
        }

        return $this;
    }

    //添加其他选项
    public function options($options): Model{
        if (!empty($options))
            $this->_modelData['options'] = $options;
        return $this;
    }

    // 连贯操作  cache key
    public function cache($cacheKey, ?int $exp = DEFAULT_CACHEEXPIRE ?? null): Model {

        //缓存时间为 0 或没有缓存 key 时不缓存内容
        if ($exp) {

            $this->_modelData['exp'] = (int)$exp;
            if ($cacheKey) {
                $this->_modelData['cacheKey'] = $cacheKey;
            }
        }

        return $this;
    }


    /**
     * 写入新数据 $data
     * @param array | object $data
     * @param array $args
     *
     * @return InsertOneResult | InsertManyResult
     * @throws \Exception
     */
    public function insert($data, array $args = []) {
        $this->_getConnect('write');

        return $this->db->insert($data, $args);
    }

    //为可空字段赋值
    private function _setCanEmpty(array $data): void {

        foreach ($data as $key => $value) {
            if (!isset($this->_modelData[$key])) {
                $this->_modelData[$key] = $value ?? '';
            }
        }
    }

    /**
     * 根据条件查找一条
     * @return array
     */
    public function getOne() {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'field' => '', 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        if (!$this->_modelData['query'])
            throw new \Exception('Query is empty.');

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData['query']) . json_encode($this->_modelData['field']));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->getOne($this->_modelData['query'], $this->_modelData['field'], $this->_modelData['data']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * 查询全部
     * @var Mongodb | Mysql
     */
    public function getAll() {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'field' => '', 'order' => '', 'offset' => NULL, 'limit' => NULL, 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->getAll($this->_modelData['query'], $this->_modelData['field'], $this->_modelData['order'], $this->_modelData['offset'], $this->_modelData['limit'], $this->_modelData['data']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * 取条数
     * @var Mongodb | Mysql
     * @param string $countField
     *
     * @return int
     * @throws \Exception
     */
    public function count($countField = '*') {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData['query']) . json_encode($countField));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->count($this->_modelData['query'], $countField, $this->_modelData['data']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }


    /**
     * 执行 SQL
     * @todo 重新实现需要兼容 Mongodb Aggregate
     * @param          $query
     * @param          $data
     * @param string   $cacheKey
     * @param int|null $exp
     *
     * @return mixed
     */
    public function getQuery($query, $data, $cacheKey = '', $exp = DEFAULT_CACHEEXPIRE ?? null) {
        $this->_getConnect('read');

        //自动生成 cachekey
        if (TRUE === $cacheKey) {
            $cacheKey = md5($this->table . __METHOD__ . json_encode($query));
        }
        if ($cacheKey) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($cacheKey), TRUE);
            if ($cacheRes) {
                return $cacheRes;
            }
        }

        $result = $this->db->getQuery($query, $data);

        if ($cacheKey) {
            Cache::set($cacheKey, json_encode($result), $exp);
        }

        return $result;
    }

    /**
     *  更新数据
     * @param \Strawframework\Protocol\新数据数组 $data
     * @param \Strawframework\Protocol\更新条件  $condition
     * @param array                          $args
     * @param string                         $cacheKey
     *
     * @return mixed
     */
    public function update($data, $condition, $args = [], $cacheKey = '') {
        $this->_getConnect('write');

        //有缓存 要删除
        if ($cacheKey) {
            Cache::del($cacheKey);
        }

        return $this->db->update($data, $condition, $args);
    }

    /**
     *  删除数据
     * @param        $condition
     * @param string $cacheKey
     *
     * @return mixed
     */
    public function delete($condition, $cacheKey = '') {
        $this->_getConnect('write');
        //有缓存 要删除
        if ($cacheKey) {
            Cache::del($cacheKey);
        }

        return $this->db->delete($condition);
    }

    /**
     * 获取本表中的所有字段
     *
     * @param string $table
     *
     * @return mixed
     */
    protected function getAllField($table = '') {

        if (!$table) {
            $table = $this->table;
        }

        // filed 专用前缀
        $cacheKey = '_Field_table_' . $table;

        //有缓存 数据优先使用
        $cacheRes = json_decode(Cache::get($cacheKey), TRUE);
        if ($cacheRes) {
            return $cacheRes;
        }

        $result = $this->db->getAllField($table);

        //APP_DEBUG 关闭后  设置永久缓存
        //@todo 写入缓存至 php 文件
        if (FALSE == APP_DEBUG) {
            Cache::set($cacheKey, json_encode($result), 60 * 60 * 24);
        }

        return $result;
    }


    //获取查询语句调试
    public function getLastSql() {
        return $this->db->getLastSql();
    }


    /**
     * mongodb 专用
     *
     * @param $group
     */
    public function aggregate($group, $fun, $params = 1) {
        //mysql do not use this
        if ('mysql' == self::$dbArr[$this->dbtag]['DB_TYPE']) {
            ex('Aggregate only used on Mongodb');
        }

        $this->_getConnect('read');
        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->tbl . __METHOD__ . json_encode($this->_modelData['query']) . $group . $fun . $params);
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->aggregate($this->_modelData['query'], $group, $fun, $params);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * @param $obj
     */
    public static function toArray($obj){
        if (!is_array($obj) && !is_object($obj))
            return $obj;

        $data = [];

        //if (!($obj instanceof BSONDocument)){
        //
        //
        //}

        //$list = (array)$obj;
        foreach ($obj as $key => $value) {
            if (is_object($value)){
                switch ($value){
                    case $value instanceof ObjectId:
                        $data[$key] = (string)$value;
                        break;
                    //@todo 配置 时间项目
                    case $value instanceof UTCDateTime:
                        $data[$key] = $value->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
                        break;
                    default:
                        $data[$key] = self::toArray($value);
                        break;
                }
            }else{
                $data[$key] = $value;
            }
        }
        return $data;
    }

}
