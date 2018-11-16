<?php

namespace strawframework\base;

use strawframework\Straw;

/**
 * User: zack lee
 * Date: 2018/11/15
 * Time: 15:24
 */

/**
 * 请求对象
 * Class Request
 * @package strawframework\base
 */
abstract class Request{

    //请求可用的类型
    const AVAILABLE_TYPE = ['int', 'string', 'array'];

    //默认过滤器 不建议添加超过3个
    protected function defaultFilters(): array {
        return ['trim'];
    }

    public function __get($key){

        echo $key;
    }

    //当前用于 uri 的 path /controller/action  | /version/controller/action
    // const URI_PATHS = [0, 1];

    public function __construct(string $method, array $requests) {
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
                throw new \Exception('Method not invalid');
        }
        unset($_REQUEST);
        foreach ($requests as $key => $request) {
            $tmpValue = $params[$request['name']];
            //类型检查
            $tmpValue = Request::convert($tmpValue, $request['type']);

            $setFilter = 'set' . ucfirst($key);
            //自定义过滤器
            if (method_exists($this, $setFilter)){
                $tmpValue = $this->$setFilter($tmpValue);
            }else{
                foreach ($this->defaultFilters() as $filter){
                    $tmpValue = $filter($tmpValue);
                }
            }
            $this->{$key} = $tmpValue;
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
     * 写入参数列
     * @param string $method
     *
     * @throws \Exception
     */
    public function setRequest(){


    }
}
