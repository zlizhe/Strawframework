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

    //当前用于 uri 的 path /controller/action  | /version/controller/action
    const URI_PATHS = [0, 1];

    public function __construct(string $method, array $requests) {
        //var_dump($requests, $this->getIntValue());
        $finalGet = [];
        foreach ($requests as $key => $request) {
            //用于 uri 的 name
            $isUriName = ('/' == substr($request['name'], 0, 1) ? true : false);
            if ($isUriName){
                $uriArr = explode('/', $request['name']);
                unset($uriArr[0]);
                $uriArr = array_reverse($uriArr);
                $uriArrSort = [];
                for ($i = 0; $i < count($uriArr); $i++) {
                    if ('{}' == $uriArr[$i] && $uriArr[$i + 1])
                        $uriArrSort[$uriArr[$i + 1]] = $uriArr[$i];
                }
                //to {} => id or {}
                $intValues = $this->getIntValue();
                if ($uriArrSort){
                    foreach ($intValues as $getKey => $getValue) {
                        //var_dump($uriArrSort[$getValue]);
                        if ('{}' == $uriArrSort[$getValue]){
                            $finalGet[$getValue] = $intValues[$getKey + 1];
                        }
                    }
                }else{
                    //仅一个 uri 时 允许 /123 取值
                    if (count($intValues) == 1)
                        $finalGet['{}'] = end($intValues);
                }
                //var_dump($finalGet);
                //if (count($finalGet) == 0)
                //    $finalGet = current($intValues);
            }else{
                //普通 k => v
            }
        }
        switch ($method) {
            case 'get':
                $this->firstArg = $_GET[2] ?: null;
                break;
            case 'post':
                break;
            case 'put':
                break;
            case 'delete':
                break;
            default:
                throw new \Exception('Method not invalid');
        }
        echo $method;
        var_dump($finalGet);die;
        parse_str(file_get_contents('php://input'), $data);
    }

    private static $getIntValue = [];
    /**
     * 获取 uri 参数  /id/123
     */
    private function getIntValue(): ? array{

        if (count(self::$getIntValue) > 0)
            return self::$getIntValue;

        $data = [];
        foreach ($_GET as $key => $value) {
            if (is_int($key))
                $data[] = $value;
        }
        foreach (self::URI_PATHS as $path) {
            unset($data[$path]);
        }
        self::$getIntValue = $data;
        return $data;
    }

    //首个参数
    private $firstArg;

    protected function getFirstArg(){
        return $this->firstArg;
    }

    /**
     * 写入参数列
     * @param string $method
     *
     * @throws \Exception
     */
    public function setRequest(){


    }
}
