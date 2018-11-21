<?php

namespace Strawframework\Base;

/**
 * 容器
 */


class Container {

    protected $_s = array();

    public function __set($k, $c) {
        $this->_s[$k] = $c;
    }

    public function __get($k) {
        return $this->_s[$k];
    }

}