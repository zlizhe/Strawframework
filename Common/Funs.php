<?php
/**
 * User: Zack Lee
 * Date: 2018/11/18
 * Time: 16:37
 */

namespace Strawframework\Common;


/**
 * 操作 Strawframework 的 常用方法
 * Class Funs
 * @package Strawframework\Common
 */
class Funs {
    private static $me;
    /**
     * 获取
     */
    public static function getInstance(){
        if (!self::$me)
            self::$me = new self();

        return self::$me;
    }
}