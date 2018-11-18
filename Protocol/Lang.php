<?php
namespace Strawframework\Protocol;
/**
 * User: Zack Lee
 * Date: 2018/11/18
 * Time: 15:39
 */



interface Lang {

    //获取语言包 code/key => msg
    public function getMsgList();

    //获取单条语言
    public function getMsg($code);

    //获取自身类
    public static function getInstance();
}