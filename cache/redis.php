<?php
namespace cache;

/**
 * User: zl
 * Date: 2015/11/10
 * Time: 16:12
 */

/**
 * Redis cache 基类
 * Class Redis
 * @package cache
 */
class Redis{

    //cache 实例
    private $cache = NULL;

    //初始化 redis
    public function __construct($config = ''){

        if (!class_exists("redis"))
            ex("Redis Extend Not Support!");

        $redis = new \Redis();
        $cache = $redis->connect($config['host'], $config['port'], 1);
        if ($config['auth']){
            try {
                $cache = $redis->auth($config['auth']);
            } catch (\Exception $e) {
                ex("Redis connect error", $e->getMessage(), "Cache Error");
            }
        }

//        if (false === $cache)
//            ex("Redis Connect Error!\t");

        $this->cache = $redis;
    }

    /**
     * 设置缓存
     */
    public function set($key, $value, $expire=0)
    {
        if ((int)$expire > 0){
            return $this->cache->setex($key, $expire, $value);
        }else{
            return $this->cache->set($key, $value);
        }
    }


    /**
     *  取缓存值
     */
    public function get($key)
    {
        return $this->cache->get($key);
    }


    /**
     *  删除 redis cache
     */
    public function del($key){
        return $this->cache->del($key);
    }

    /**
     * key 是否存在
     * @param $key
     */
    public function exists($key){
        return $this->cache->exists($key);
    }

    /**
     * $key + 1
     * @param $key
     *
     * @return int
     */
    public function incr($key){
        return $this->cache->incr($key);
    }

    /**
     * Increment the float value of a key by the given amount
     * @param $key
     * @param $increment
     *
     * @return float
     */
    public function incrByFloat($key, $increment){
        return $this->cache->incrByFloat($key, $increment);
    }

    /**
     * Increment the number stored at key by one. If the second argument is filled, it will be used as the integer
     * + value
     * @param $key
     * @param $value
     *
     * @return int
     */
    public function incrBy($key, $value){
        return $this->cache->incrBy($key, $value);
    }

    /**
     * -1
     * @param $key
     *
     * @return int
     */
    public function decr($key){
        return $this->cache->decr($key);
    }

    /**
     * - value
     * @param $key
     * @param $value
     *
     * @return int
     */
    public function decrBy($key, $value){
        return $this->cache->decrBy($key, $value);
    }
}
