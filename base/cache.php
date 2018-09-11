<?php

namespace strawframework\base;

use strawframework\Straw;

/**
 * User: zl
 * Date: 2015/11/10
 * Time: 16:13
 */

/**
 * cache 基类
 * Class Cache
 */
class Cache extends Straw {

    //cache obj
    protected static $cache = NULL;

    //cache config name
    private static $cacheName = '';

    //配置
    protected static $cacheArr = [];

    //prefix for cache key 
    private static $pre = '';

    //读取 redis 配置
    private function _getConfig(): void {

        self::$cacheName = DEFAULT_CACHE;
        self::$cacheArr = parent::$config['cache'][self::$cacheName];
    }

    //连接服务
    private function _getConnect(): void {

        $cacheClass = "cache\\" . self::$cacheArr['CACHE_TYPE'];

        //驱动是否存在
        if (FALSE === class_exists($cacheClass)) {
            ex("Cache Driver " . $cacheClass . " Not Found\t");
        }

        self::$cache = new $cacheClass([
                                           'host' => self::$cacheArr['CACHE_HOST'],
                                           'port' => self::$cacheArr['CACHE_PORT'],
                                           'auth' => self::$cacheArr['CACHE_AUTH'],
                                       ]);
    }

    //初始化 cache
    public function __construct() {
        parent::__construct();
        //初始化数据库配置
        if (!self::$cacheArr) {
            $this->_getConfig();
        }

        //加载数据库对象
        if (!self::$cache) {
            $this->_getConnect();
        }

        //key 前缀
        self::$pre = self::$cacheArr['CACHE_PREFIX'] ?: '';
    }

    /**
     * 设置缓存
     */
    public static function set(string $key, string $value, int $expire = 0): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->set(self::$pre . $key, $value, $expire);
    }


    /**
     *  取缓存值
     */
    public static function get(string $key): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->get(self::$pre . $key);
    }

    /**
     * 根据  key 删除  cache
     */
    public static function del(string $key): object {

        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->del(self::$pre . $key);
    }

    /**
     * 对 key + 1
     *
     * @param $key
     *
     * @return mixed
     */
    public static function incr(string $key): object {

        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->incr(self::$pre . $key);
    }

    /**
     * 对 key + float increment
     *
     * @param $key
     * @param $increment
     *
     * @return mixed
     */
    public static function incrByFloat(string $key, float $increment): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->incrByFloat(self::$pre . $key, $increment);
    }

    /**
     * 对 key + value
     *
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public static function incrBy(string $key, int $value): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->incrBy(self::$pre . $key, $value);
    }

    /**
     * 对 key 值 -1
     *
     * @param $key
     *
     * @return mixed
     */
    public static function decr(string $key): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->decr(self::$pre . $key);
    }


    /**
     * 对 key 值 - value
     *
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public static function decrBy(string $key, int $value): object {
        if (is_null(self::$cache)) {
            new self();
        }

        return self::$cache->decrBy(self::$pre . $key, $value);
    }
}
