<?php

namespace Strawframework\Factory;

/**
 * User: zack lee
 * Date: 2018/11/15
 * Time: 15:24
 */

use Strawframework\Base\RequestObject;

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
    public static function factory(string $v, string $request, ? array $required, string $fromMethod){

        $class = sprintf('Ro\\%s\\%s', $v, ucfirst($request));

        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getProperties(\ReflectionMethod::IS_PROTECTED);

        $requestDocs = [];
        foreach($methods as $key => $method){
            //方法的注释
            //@todo 缓存已知类
            $requestDoc = $method->getDocComment();
            preg_match('/@Column\s*\(\s*name\s*=\s*[\'|\"](.*)[\'|\"]\s*,\s*type\s*=\s*[\'|\"]('.implode('|', RequestObject::AVAILABLE_TYPE).')[\'|\"]\s*\)/i', $requestDoc, $requestRouter);

            //参数 名称 / 类型
            list($req, $name, $type) = $requestRouter;
            if (empty($name) || empty($type))
                throw new \Exception(sprintf('Requests %s, %s invalid.', $req, $method->getName()));
            else{
                $requestDocs[$method->getName()] = ['name' => $name, 'type' => $type];
            }
        }

        \Strawframework\Base\Log::getInstance()->debug("RO DOCS", $requestDocs);
        return (new $class())->setRequired($required)->setRequests($fromMethod, $requestDocs);
    }

}
