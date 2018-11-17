<?php

namespace strawframework\base;

use strawframework\Straw;

/**
 * User: zack lee
 * Date: 2017/10/13
 * Time: 15:24
 */

/**
 * 逻辑模型基类
 * 初始化数据库配置
 * Class Model
 * @package library
 */
class Logicmodel extends Straw {

    public function __set(string $name, string $value) {

    }

    public function __get(string $name): object {
        //包含 Model 名称 的变量 自动创建 Model 对象并返回
        if (stripos(strtolower($name), 'model')) {
            //创建 Model 对象
            static $models = array();
            $mname = ucfirst($mname);
            if ($mname === '') {
                ex($mname . ' Model is not found');
            }
            if (isset($models[$mname])) {
                if (!$this->$mname) {
                    $this->$mname = $models[$mname];
                }
    
                return $models[$mname];
            }
    
            $class = '\models\\' . $mname;
            $model = new $class($table, $pre, $dbtag);
            $this->$mname = $model;
            $models[$mname] = $model;
    
            return $model;
        }
    }


}
