<?php
namespace Strawframework\Base;
use Strawframework\Straw;

/**
 * Logic base
 */



class Logic extends Straw {
    /**
     * 获取Model
     */
    protected function getModel(string $modelName): Model{
        return $this->getSingleInstance('\\Model\\' . ucfirst($modelName));
    }
}