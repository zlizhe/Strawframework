<?php

namespace Strawframework\Base;

/**
 * User: zack lee
 * Date: 2018/11/15
 * Time: 15:24
 */

/**
 * 请求对象
 * Class RequestObject
 * @package strawframework\base
 */
abstract class RequestObject{

    //请求可用的类型
    const AVAILABLE_TYPE = ['int', 'string', 'array'];

    //默认过滤器 不建议添加超过3个
    protected function defaultFilters(): array {
        return ['trim'];
    }

    /**
     * @var Request
     * @param $reqName
     * @param $param
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call($reqName, $param){

        //getField
        if (0 == strpos(strtolower($reqName), 'get')) {
            $name = substr($reqName, 3);
            $propertyName = lcfirst($name);
            if (!property_exists($this, $propertyName)){
                throw new \Exception(sprintf('Property not found %s', $name));
            }else{
                return $this->{$propertyName};
            }
        }
    }

    //当前用于 uri 的 path /controller/action  | /version/controller/action
    // const URI_PATHS = [0, 1];

    public function __construct() {

        //return $this;
    }

    private static $requiredColumns = null;

    /**
     * 设置必填项目检查
     * @param array|null $columns
     *
     * @return $this
     */
    public function setRequired(?array $columns = []){

        self::$requiredColumns = count($columns) > 0 ? $columns : null;
        return $this;
    }

    /**
     * 写入 Requests 传值
     * @param string $method
     * @param array  $requests
     *
     * @return $this
     * @throws \Exception
     */
    public function setRequests(string $method, array $requests){
        //最终取值
        $params = [];
        switch ($method) {
            case 'get':
                $params = $_GET;
                break;
            case 'post':
                $params = $_POST;
                break;
            case 'put':
                parse_str(file_get_contents('php://input'), $params);
                $params = array_merge($_GET, $params);
                break;
            case 'delete':
                parse_str(file_get_contents('php://input'), $params);
                $params = array_merge($_GET, $params);
                break;
            default:
                throw new \Exception('Request method not invalid');
        }
        unset($_REQUEST);
        foreach ($this as $key => $column) {
            $tmpValue = $params[$requests[$key]['name']];
        //}
        //foreach ($requests as $key => $request) {
        //    $tmpValue = $params[$request['name']];
            //必填检查
            if (self::$requiredColumns && in_array($key, self::$requiredColumns)){
                if (empty($tmpValue) && '0' != $tmpValue)
                    throw new \Exception(sprintf('Column %s can not be null.', $requests[$key]['name']));
            }
            if (!empty($tmpValue) || '0' == $tmpValue){
                $setFilter = 'set' . ucfirst($key);
                //自定义过滤器
                if (method_exists($this, $setFilter)){
                    $tmpValue = $this->$setFilter($tmpValue);
                }else{
                    foreach ($this->defaultFilters() as $filter){
                        $tmpValue = $filter($tmpValue);
                    }
                }
                //类型检查
                try{
                    $tmpValue = RequestObject::convert($tmpValue, $requests[$key]['type']);
                }catch (\TypeError $e){
                    //value type error
                    throw new \Exception(sprintf('Request %s type must be %s.', $requests[$key]['name'], $requests[$key]['type']));
                }
                $this->{$key} = $tmpValue;
            }
        }
        return $this;
    }

    /**
     * 类型转换
     */
    public static function convert($v, string $type){
        $int = function($v): int {
            return $v;
        };

        $string = function($v): string{
            return $v;
        };

        $bool = function($v): bool{
            return $v;
        };

        $array = function($v): array{
            return $v;
        };

        $object = function($v): object{
            return $v;
        };

        $objectid = function($v): \MongoDB\BSON\ObjectId{
            return new \MongoDB\BSON\ObjectId($v);
        };

        return $$type($v);
    }

    // private static $getIntValue = [];
    // /**
    //  * 获取 uri 参数  /id/123
    //  */
    // private function getIntValue(): ? array{

    //     if (count(self::$getIntValue) > 0)
    //         return self::$getIntValue;

    //     $data = [];
    //     foreach ($_GET as $key => $value) {
    //         if (is_int($key))
    //             $data[] = $value;
    //     }
    //     foreach (self::URI_PATHS as $path) {
    //         unset($data[$path]);
    //     }
    //     self::$getIntValue = $data;
    //     return $data;
    // }

    //首个参数
    // private $firstArg;

    // protected function getFirstArg(){
    //     return $this->firstArg;
    // }

    /**
     * 返回当前 Request object
     *
     * @return $this
     */
    public function getRequest(){
        return $this;
    }
}
