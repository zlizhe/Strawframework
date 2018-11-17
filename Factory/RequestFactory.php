<?php

namespace Strawframework\Factory;

/**
 * User: zack lee
 * Date: 2018/11/15
 * Time: 15:24
 */

use Strawframework\Base\Request;

/** Request
 * Class RequestFactory
 * @package strawframework\factory
 */
class RequestFactory{

    /**
     * 构建 Requests
     * @param string $request
     * @param string $fromMethod
     *
     * @return mixed
     * @throws \Exception
     */
    public static function factory(string $request, string $fromMethod){

        $class = 'Requests\\' . $request;

        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getProperties(\ReflectionMethod::IS_PROTECTED);

        $requestDocs = [];
        foreach($methods as $key => $method){
            //方法的注释
            //@todo 缓存已知类
            $requestDoc = $method->getDocComment();
            preg_match('/@Column\s*\(name=[\'|\"](.*)[\'|\"]\s*,\s*type=[\'|\"]('.implode('|', Request::AVAILABLE_TYPE).')[\'|\"]\)/i', $requestDoc, $requestRouter);

            //参数 名称 / 类型
            list($requet, $name, $type) = $requestRouter;
            if (empty($name) || empty($type))
                throw new \Exception(sprintf('Requests %s, %s invalid.', $request, $method->getName()));
            else{
                $requestDocs[$method->getName()] = ['name' => $name, 'type' => $type];
            }
        }

        return new $class($fromMethod, $requestDocs);
    }

}
