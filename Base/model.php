<?php

namespace Strawframework\Base;

use Strawframework\Straw;

/**
 * User: zack lee
 * Date: 2015/11/9
 * Time: 15:24
 */

/**
 * 模型基类
 * 初始化数据库配置
 * Class Model
 * @package library
 */
class Model extends Straw implements \strawframework\protocol\Db {

    //当前数据库对象
    protected $db = NULL;

    //数据库名
    protected $dbName = '';

    //数据库连接别名
    protected $dbtag = '';

    //当前操作表
    protected $tbl = '';

    //当前表前缀
    protected $pre = '';

    //当前配置 -> database
    protected static $dbArr = [];

    //缓存 obj
    private $cache = NULL;

    //设置 $_id 自动取一条数据
    protected $_value = NULL;

    // 设置更新的字段名称
    protected $_field = 'id';

    //快速更新值
    public function __set(string $name, string $value): bool {

        if (!is_null($this->_value)) {
            return $this->update([$name => $value], [$this->_field => $this->_value]) ? TRUE : FALSE;
        }

    }

    //如果设置了 $_id 自动取 该数据值
    public function __get(string $name = '_ALL_') {

        if (!is_null($this->_value)) {

            $data = $this->query([$this->_field => $this->_value])->getOne();

            if ($name == '_ALL_') {
                return $data ?? null;
            } else {
                return $data[$name] ?? null;
            }
        }
    }


    /**
     * 构造函数
     */
    public function __construct(?string $table = '', ?string $pre = '', ?string $dbtag = DEFAULT_DB) {
        parent::__construct();
        // 获取数据库操作对象
        if (!self::$dbArr[$dbtag]) {
            self::$dbArr[$dbtag] = parent::$config['db'][$dbtag];
        }

        $this->dbtag = $dbtag;
        //设置当前使用的db
        $this->dbName = self::$dbArr[$dbtag]['DB_NAME'];

        //表前缀设置
        if (is_null($pre)) {
            $pre = self::$dbArr[$dbtag]['DB_PREFIX'] ?: '';
        }

        if (!$table) {
            $table = strtolower(str_replace('Model', '', get_class($this)));
        }

        if ($table) {
            if ($pre && strpos($table, $pre) === 0) {
                $this->tbl = $table;
            } else {
                $this->tbl = $pre . $table;
            }
        }
        $this->pre = $pre;

        //连接
//        $this->_getConnect();
    }

    //主从数据库
    private $dbArray = [];

    /**
     * 连接 数据库
     */
    private function _getConnect(?string $type = 'read'): void {

        //引入 对应 db 的库文件
        $dbClass = self::$dbArr[$this->dbtag]['DB_TYPE'];

        //驱动是否存在
        // db namespace
        $dbClass = '\Strawframework\\Db\\' . $dbClass;
        if (FALSE === class_exists($dbClass)) {
            ex("Database Driver " . $dbClass . " Not Found\t");
        }

        /**
         * @2017.3.6
         * 支持分布式数据库配置
         * 1台读服务器 + 多台写服务器配置
         */
        $hostArray = explode(',', self::$dbArr[$this->dbtag]['DB_HOST']);
        //超过1个数据库集
        if (count($hostArray) > 1) {
            $portArray = explode(',', self::$dbArr[$this->dbtag]['DB_PORT']);
            $userArray = explode(',', self::$dbArr[$this->dbtag]['DB_USER']);
            $pwdArray = explode(',', self::$dbArr[$this->dbtag]['DB_PWD']);
            foreach ($hostArray as $key => $value) {
                $this->dbArray[] = [
                    'host'     => $value,
                    'port'     => $portArray[$key] ?: $portArray[0],
                    'username' => $userArray[$key] ?: $userArray[0],
                    'password' => $pwdArray[$key] ?: $pwdArray[0],
                    'dbname'   => $this->dbName,
                    'charset'  => self::$dbArr[$this->dbtag]['DB_CHARSET']
                ];
            }
            unset($portArray, $userArray, $pwdArray);

            //读写分离
            if (TRUE == self::$dbArr[$this->dbtag]['WRITE_MASTER']) {

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
                                         'host'     => self::$dbArr[$this->dbtag]['DB_HOST'],
                                         'port'     => self::$dbArr[$this->dbtag]['DB_PORT'],
                                         'username' => self::$dbArr[$this->dbtag]['DB_USER'],
                                         'password' => self::$dbArr[$this->dbtag]['DB_PWD'],
                                         'dbname'   => $this->dbName,
                                         'charset'  => self::$dbArr[$this->dbtag]['DB_CHARSET'],
                                     ]);
        }
        //操作表
        $this->db->setTable($this->tbl);
    }

    //查询条件使用 连贯操作
    //连贯操作 数组
    private $_modelData = [];

    // 连贯操作 where 语句查询
    // * @param string|array $query  查询条件
    public function query($where = ''): Model {

        $this->_modelData['query'] = $where;

        return $this;
    }

    // 连贯操作 绑定数据
    public function data(array $data): Model {

        if ($data && is_array($data)) {
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

    // 连贯操作  cache key
    public function cache(string $cacheKey, ?int $exp = DEFAULT_CACHEEXPIRE): Model {

        //缓存时间为 0 或没有缓存 key 时不缓存内容
        if ($exp) {

            $this->_modelData['exp'] = (int)$exp;
            if ($cacheKey) {
                $this->_modelData['cacheKey'] = $cacheKey;
            }
        }

        return $this;
    }

    //写入新数据 $data
    public function insert(array $data, array $args = []) {
        $this->_getConnect('write');

        return $this->db->insert($data, $args);
    }

    //为可空字段赋值
    private function _setCanEmpty(array $data): void {

        foreach ($data as $key => $value) {
            if (!isset($this->_modelData[$key])) {
                $this->_modelData[$key] = $value ?: '';
            }
        }
    }

    // public function field($field='*'){

    // $this->find = $this->find;
    // }

    //根据条件查找一条
    public function getOne(): array {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => '', 'field' => '', 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE]);

        if (!$this->_modelData['query']) {
            ex('Query can not set empty');
        }

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->tbl . __METHOD__ . json_encode($this->_modelData['query']) . json_encode($this->_modelData['field']));
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
     * 快速查询 FINDALL
     *
     * @param        $table
     * @param string $field
     * @param string $map 查询条件数组 或 字符串
     * @param string $order
     * @param null   $offset
     * @param null   $limit
     */
    public function getAll() : array {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => '', 'field' => '', 'order' => '', 'offset' => NULL, 'limit' => NULL, 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->tbl . __METHOD__ . json_encode($this->_modelData));
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
     * 快速计算 count 值
     *
     * @param        $query
     * @param string $countField
     */
    public function count($countField = '*') {
        $this->_getConnect('read');
        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->tbl . __METHOD__ . json_encode($this->_modelData['query']) . json_encode($countField));
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
     * 通过sql 获取一个 col
     *
     * @param        $query
     * @param string $cacheKey
     * @param int    $exp
     *
     * @return mixed
     */
    public function getCol($col = 0) {
        $this->_getConnect('read');
        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->tbl . __METHOD__ . json_encode($this->_modelData['query']) . $col);
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

        $result = $this->db->getCol($this->_modelData['query'], $col, $this->_modelData['data']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }


    /**
     * 执行 sql
     *
     * @param        $sql
     * @param string $cacheKey
     * @param int    $exp
     */
    public function getQuery($query, $data, $cacheKey = '', $exp = DEFAULT_CACHEEXPIRE) {
        $this->_getConnect('read');
        //自动生成 cachekey
        if (TRUE === $cacheKey) {
            $cacheKey = md5($this->tbl . __METHOD__ . json_encode($query));
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
     *
     */
    public function update($data, $condition, $args = [], $cacheKey = '') {
        $this->_getConnect('write');

        //有缓存 要删除
        if ($cacheKey) {
            Cache::del($cacheKey);
        }

        return $this->db->update($data, $condition, $args);
    }

    /*
     *  删除数据
     * */
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
            $table = $this->tbl;
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
        if (FALSE == APP_DEBUG) {
            Cache::set($cacheKey, json_encode($result), 60 * 60 * 24);
        }

        return $result;
    }


    /**
     * 移除不安全字段
     *
     * @param $fieldArr
     */
    public static function removeUnsafeField($fieldArr, $saveFieldArr) {
        if (!is_array($saveFieldArr)) {
            $saveFieldArr = explode(',', $saveFieldArr);
        }
        foreach ($fieldArr as $key => $value) {
            //移除 $ 符号 mongodb 安全
            $key = str_ireplace('$', '', $key);
            if (!in_array($key, $saveFieldArr)) {
                unset($fieldArr[$key]);
            }
        }

        return $fieldArr;
    }

    //获取查询语句调试
    public function getLastSql() {
        return $this->db->getLastSql();
    }

    //update 后 修改的行数
    public function getModifiedCount() {
        return $this->db->modifiedCount();
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
}
