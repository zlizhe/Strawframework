<?php
/**
 * Created by PhpStorm.
 * User: Zjp
 * Date: 2018/11/30 0030
 * Time: 17:08
 */

namespace Strawframework\Cache;


use Strawframework\Base\Error;

class File
{
    //默认配置信息
    private static $defaultConfig = [
        'CACHE_PATH' => ROOT_PATH.'/Runtime/Cache/File/', // 文件缓存默认目录
        'CACHE_PREFIX' => 'straw_', //文件名前缀
        'CACHE_EXPIRE' => 0 //过期时间(秒) 0为永久存在
    ];

    private static $dataKey = 0; //实体数据储存键值
    private static $expireKey = 1; //过期时间储存键值

    //配置信息
    private static $config;

    /**
     * 初始化
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct($config = [])
    {
        if (empty($config))
            return new Error('File cache config error!');

        //初始化配置
        self::initConfig($config);
        //初始化操作
        self::init();
    }

    /**
     * 初始化操作
     *
     * @return bool|Error
     * @throws \Exception
     */
    private function init()
    {
        if (!is_dir(self::$config['CACHE_PATH'])){
            if (!mkdir(self::$config['CACHE_PATH'],0755,true)){
                return new Error('File cache path can not create!');
            }
            return true;
        }
        return false;
    }

    /**
     * 初始化配置
     *
     * @param $config
     */
    private function initConfig($config) : void
    {
        //初始化配置
        if (empty(self::$config)){
            self::$config = array_merge(self::$defaultConfig,$config);
            if (self::$config['CACHE_PATH'][strlen(self::$config['CACHE_PATH'])-1] != DS) {
                self::$config['CACHE_PATH'] = self::$config['CACHE_PATH'].DS;
            }
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key
     * @param $value
     * @param int $expire
     * @return bool
     * @throws \Exception
     */
    public function set(string $key, $value, $expire = null)
    {
        $path = $this->getPathByKey($key);
        return self::write($path,$value,$expire);
    }

    /**
     * 取缓存值
     *
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        $path = $this->getPathByKey($key);
        return ($this->read($path)[self::$dataKey]) ?? null;
    }

    /**
     * 删除缓存
     *
     * @param $key
     * @return bool
     */
    public function del($key)
    {
        $path = $this->getPathByKey($key);
        return self::unlink($path);
    }

    /**
     * 清空缓存
     *
     * @return array | bool
     */
    public function clear()
    {
        $list = glob(self::$config['CACHE_PATH'].'*');
        return !empty($list) ? array_map('unlink',$list) : true;
    }

    /**
     * key 是否存在
     *
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $path = $this->getPathByKey($key);
        return file_exists($path);
    }

    /**
     * 递增
     *
     * @param $key
     * @return bool
     * @throws \Exception
     */
    public function incr ($key)
    {
        return $this->incrBy($key,1);
    }

    /**
     * 递增指定的值
     *
     * @param $key
     * @param $increase
     * @return bool
     * @throws \Exception
     */
    public function incrBy($key,$increase)
    {
        $path = $this->getPathByKey($key);

        list($cache,$expire) = $this->read($path);
        if (is_numeric($cache)){
            return self::write($path,$cache + $increase,$expire);
        } else {
            return self::write($path,$increase,$expire);
        }
    }

    /**
     * 递减
     *
     * @param $key
     * @return bool
     * @throws \Exception
     */
    public function decr($key)
    {
        return $this->decrBy($key,1);
    }

    /**
     * 递减指定的值
     *
     * @param $key
     * @param int $decrease
     * @return bool
     * @throws \Exception
     */
    public function decrBy($key,$decrease = 1)
    {
        $path = $this->getPathByKey($key);

        list($cache,$expire) = $this->read($path);
        if (is_numeric($cache)){
            return self::write($path,$cache - $decrease,$expire);
        } else {
            return self::write($path,0 - $decrease,$expire);
        }
    }

    /**
     * @throws \Exception
     */
    public function decrByFloat()
    {
        throw new \Exception('File cache do not can use decrByFloat method!');
    }

    /**
     * @throws \Exception
     */
    public function incrByFloat()
    {
        throw new \Exception('File cache do not can use incrByFloat method!');
    }

    /**
     * 设置缓存过期时间
     *
     * @param $key
     * @param $ttl
     * @return bool
     * @throws \Exception
     */
    public function ttl($key,$ttl)
    {
        $path = $this->getPathByKey($key);

        //立即过期
        if ($ttl == -1) {
            return self::unlink($path);
        } else {
            $cache = $this->read($path);
            return $this->write($path,$cache[self::$dataKey],$ttl);
        }
    }

    /**
     * 序列化
     *
     * @param $data
     * @return string
     */
    private function serialize($data)
    {
        $data = serialize($data);
        //数据压缩
        return (function_exists('gzcompress')) ? gzcompress($data,3) : $data;
    }

    /**
     * 反序列化
     *
     * @param $data
     * @return mixed
     */
    private function unserialize($data)
    {
        $data = (function_exists('gzuncompress')) ? gzuncompress($data) : $data;
        return unserialize($data);
    }

    /**
     * 读取缓存
     *
     * @param string $path
     * @return null
     */
    private function read(string $path)
    {
        if (!is_file($path))
            return null;

        $cache = $this->unserialize(file_get_contents($path));
        if ($cache[self::$expireKey] > 0 && time() > $cache[self::$expireKey]) {
            self::unlink($path);
            return null;
        } else {
            return $cache;
        }
    }

    /**
     * 写入缓存
     *
     * @param string $path
     * @param $data
     * @param $expire
     * @return bool
     * @throws \Exception
     */
    private function write(string $path,$data,$expire)
    {
        $expire = (is_null($expire) || intval($expire) <=0 ) ? self::$config['CACHE_EXPIRE'] : intval($expire);
        $cache = [
            self::$dataKey => $data,
            self::$expireKey => ($expire >= 0) ? time() + $expire : 0
        ];

        if (file_put_contents($path,$this->serialize($cache))){
            clearstatcache();
            return true;
        }else{
            throw new \Exception('Cant write file cache!');
        }
    }

    /**
     * 根据key组装文件名称
     *
     * @param string $key
     * @return string
     */
    private function getPathByKey(string $key)
    {
        return self::$config['CACHE_PATH'].$this->getCacheKey($key).'.php';
    }

    /**
     * 组装文件名称
     *
     * @param string $key
     * @return string
     */
    private function getCacheKey(string $key)
    {
        $prefix = self::$config['CACHE_PREFIX'] ?? '';
        return $prefix.md5($key);
    }

    /**
     * 删除文件
     *
     * @param $path
     * @return bool
     */
    private static function unlink($path)
    {
        return is_file($path) && unlink($path);
    }
}