<?php
/**
 * User: Zack Lee
 * Date: 2018/11/17
 * Time: 22:06
 */

namespace Strawframework\Base;


use Strawframework\Common\Code;

/**
 * Class DataViewObject
 * @package Strawframework\Base
 */
class DataViewObject {
    //类型
    const AVAILABLE_TYPE = ['int', 'float', 'string', 'bool', 'array', 'object'];

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
                throw new \Exception(sprintf('Get property %s not found.', $name), Code::FAIL);
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
                    throw new \Exception(sprintf('Dvo column %s type must be %s.', static::$dvoObject[$propertyName]['name'], static::$dvoObject[$propertyName]['type']), Code::NOT_ALLOW);
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
     *
     * @throws \Exception
     */
    public function __construct(? RequestObject $ro = null, $filedRelation = null, string $scenes = 'default') {
        $this->_scenes = $scenes;
        //子类必须有 $dvoDocs 供后期静态绑定
        if (!isset(static::$dvoObject)){
            throw new \Exception(sprintf('Dvo %s can not found static function dvoObject.', get_called_class()));
        }

        //分析 Dvo 属性
        if (empty(static::$dvoObject))
            $this->analysis();

        //转移Ro属性于Dvo
        if ($ro)
            $this->transferRo($ro, $filedRelation);
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

    /**
     * 获取子类 所有 Object
     */
    public function getDvos():? array{

        $data = [];
        foreach ($this as $key => $value) {
            if ('_scenes' == $key)
                continue;

            if (!is_null($value))
                $data[static::$dvoObject[$key]['name']] = $this->{lcfirst($key)};
        }
        return $data;
    }

    /**
     * 转移来自 Ro 的属性
     * 字段关系支持 string title,id 即 title 与 id 需要从 Ro 中转移至 Dvo 字段名称相同
     * array [title => dvTitle, id => id] 即 Ro title 转移至 Dvo dvTitle
     * @param RequestObject $ro
     * @param array|string|null    $filedRelation
     */
    private function transferRo(RequestObject $ro, $filedRelation = null): void{
        $dvoKeys = [];
        $roKeys = $ro->getRos();
        if ($filedRelation){
            //['rokey' => 'dvokey']
            if (is_array($filedRelation)){
                $dvoKeys = $filedRelation;
            }else{
                //key1,key2
                $filedArr = explode(',', $filedRelation);
                $dvoKeys = array_combine($filedArr, $filedArr);
            }
        }else{
            $dvoKeys = array_combine(array_keys($roKeys), array_keys($roKeys));
        }

        //设置 Dvo
        foreach ($roKeys as $key => $value) {
            if (property_exists($this, $dvoKeys[$key])){
                $this->{'set' . ucfirst($dvoKeys[$key])}($value);
            }
        }
    }


    /**
     * 为属性设置别名， 主要用于多个相同字段需要不同值时 设定该字段为别名
     * @param $propName
     * @param $alias
     */
    public function _setAlias($propName, $alias){

    }
}