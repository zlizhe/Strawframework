<?php
namespace Strawframework\Base;
use MongoDB\BSON\Persistable;
use Strawframework\Common\Code;

/**
 * Class DataViewObject
 * @package Strawframework\Base
 */
class DataViewObject implements \JsonSerializable, Persistable {
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
                static::$dvoObject[$method->getName()] = ['name' => $name, 'type' => $type, 'propName' => $method->getName()];
            }
        }
    }

    /**
     * Rvo 是否有值
     * @return bool
     */
    public function isEmpty(): bool{

        return empty($this->getDvos()) ? true : false;
    }

    /**
     * 获取子类 所有 Object
     */
    public function getDvos():? array{

        $data = [];
        foreach ($this as $key => $value) {
            if ('_scenes' == $key)
                continue;

            if (!is_null($value)){

                //alias
                if ('_' == $key[0]){
                    $data[$key] = $value;
                }else{
                    //取普通值
                    $data[static::$dvoObject[$key]['name']] = $value;
                }
            }
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
     * @param $value
     *
     * @return DataViewObject
     * @throws \Exception
     */
    public function _setAlias($propName, $alias, $value): DataViewObject{

        if (!property_exists($this, $propName))
            throw new \Exception(sprintf("Alias's property %s not found.", $propName));

        $backUp = $this->{lcfirst($propName)};

        //写入原字段
        $this->{'set' . ucfirst($propName)}($value);
        //读原字段 写 alias 字段 _ 开头
        $this->{'_' . $alias} = $this->{lcfirst($propName)};

        //写回备份 至 原字段
        $this->{lcfirst($propName)} = $backUp;

        return $this;
    }

    /**
     * 写入别名(数组类型输入)
     * @param       $propName
     * @param       $alias
     * @param array $value
     *
     * @return DataViewObject
     * @throws \Exception
     */
    public function _setArrayAlias($propName, $alias, array $value): DataViewObject{
        if (!property_exists($this, $propName))
            throw new \Exception(sprintf("Alias's property %s not found.", $propName));

        $backUp = $this->{lcfirst($propName)};

        $aliasArr = [];
        //传入的是数组 原 age = int 传入  [1,2,3,4]
        foreach ($value as $v) {
            //即使传入数组 每个 value 的类型也必须与 原值一样
            $this->{'set' . ucfirst($propName)}($v);
            $aliasArr[] = $this->{lcfirst($propName)};
        }
        $this->{'_' . $alias} = $aliasArr;

        //写回备份 至 原字段
        $this->{lcfirst($propName)} = $backUp;

        return $this;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource.
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $list = [];
        foreach ($this as $key => $value) {
            if ('_scenes' == $key)
                continue;

            //输出时 使用字段名 做为 key
            if (!is_null($value))
                $list[static::$dvoObject[$key]['name']] = $this->{'get' . ucfirst($key)}();
        }
        return $list;
    }


    /**
     * 创建 Dvo 对象写入
     * @return array|null|object
     */
    public function bsonSerialize()
    {
        return $this->getDvos();
    }

    /**
     * 读取时 $data 写入 Dvo 对象
     * Constructs the object from a BSON array or document
     * Called during unserialization of the object from BSON.
     * The properties of the BSON array or document will be passed to the method as an array.
     * @link https://php.net/manual/en/mongodb-bson-unserializable.bsonunserialize.php
     *
     * @param array $data Properties within the BSON array or document.
     */
    public function bsonUnserialize(array $data)
    {

        $dvo = array_combine(
            array_column(static::$dvoObject, 'name'), static::$dvoObject);

        foreach ($data as $key => $value) {
            if ($dvo[$key]['propName'])
                $this->{$dvo[$key]['propName']} = $value;
        }
    }
}