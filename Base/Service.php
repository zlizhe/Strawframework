<?php
namespace Strawframework\Base;
use Strawframework\Straw;

/**
 * Service base
 */



class Service extends Straw {
    /**
     * 获取逻辑
     * @var Logic
     */
    protected function getLogic(string $logicName): Logic{
        return $this->getSingleInstance('\Logic\\' . ucfirst($logicName));
    }

    /**
     * 获取Model
     */
    protected function getModel(string $modelName): Model{
        return $this->getSingleInstance('\Model\\' . ucfirst($modelName));
    }
}