<?php
/**
 * User: Zack Lee
 * Date: 2018/11/17
 * Time: 22:06
 */

namespace Strawframework\Base;



abstract class DataViewObject {
    //类型
    const AVAILABLE_TYPE = ['int', 'string', 'bool', 'array', 'object'];

    //默认过滤器 不建议添加超过3个
    protected function defaultFilters(): array {
        return ['trim'];
    }
    /**
     * @param $colName
     * @param $param
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call($colName, $param){

        //getField
        if (0 === strpos(strtolower($colName), 'get')) {
            $name = substr($colName, 3);
            $propertyName = lcfirst($name);
            if (!property_exists($this, $propertyName)){
                throw new \Exception(sprintf('Get property %s not found.', $name));
            }else{
                return $this->{$propertyName};
            }
        }

        if (0 === strpos(strtolower($colName), 'set')) {
            $name = substr($colName, 3);
            $propertyName = lcfirst($name);
            if (!property_exists($this, $propertyName)){
                throw new \Exception(sprintf('Set property %s not found.', $name));
            }else{
                $param = current($param);
                foreach ($this->defaultFilters() as $filter){
                    $param = $filter($param);
                }

                //类型检查
                try{
                    $param = RequestObject::convert($param, static::$dvoObject[$propertyName]['type']);
                }catch (\TypeError $e){
                    //value type error
                    throw new \Exception(sprintf('Dvo column %s type must be %s.', static::$dvoObject[$propertyName]['name'], static::$dvoObject[$propertyName]['type']));
                }
                $this->{$propertyName} = $param;
                return $this;
            }
        }
    }




    public $_scenes;
    /**
     * 设置场景
     * DataViewObject constructor.
     *
     * @param string $scenes
     */
    public function __construct($scenes = 'default') {
        $this->_scenes = $scenes;
        //子类必须有 $dvoDocs 供后期静态绑定
        if (!isset(static::$dvoObject)){
            throw new \Exception('Dvo child must has static dvoObject.');
        }
        if (empty(static::$dvoObject))
            $this->analysis();
    }


    public static $dvoObject;
    /**
     * 分析
     * @throws \Exception
     */
    private function analysis(){
        $class = get_called_class();

        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getProperties(\ReflectionMethod::IS_PROTECTED);

        static::$dvoObject = [];
        foreach($methods as $key => $method){
            //方法的注释
            //@todo 缓存已知类到 Runtime
            $dvoDoc = $method->getDocComment();
            preg_match('/@Column\s*\(name=[\'|\"](.*)[\'|\"]\s*,\s*type=[\'|\"](.*)[\'|\"]\)/i', $dvoDoc, $dvoColumn);

            //参数 名称 / 类型
            list($clo, $name, $type) = $dvoColumn;
            if (empty($name) || empty($type))
                throw new \Exception(sprintf('Dvo column %s, %s invalid.', $clo, $method->getName()));
            else{
                static::$dvoObject[$method->getName()] = ['name' => $name, 'type' => $type];
            }
        }
    }
}