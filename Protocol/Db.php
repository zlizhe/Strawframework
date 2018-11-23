<?php
namespace Strawframework\Protocol;

/**
 *  db 库所有实现方法
 */
interface Db{

    /**
     *  写入新数据
     * @param array $data
     * @param array $args
     *
     * @return mixed
     */
    public function insert(array $data, array $args = []);

    /**
     * 根据条件查找一条
     * @return array
     */
    public function getOne();


    /**
     * 查找所有符合条件的行
     *
     */
    public function getAll();

    /**
     * 执行完整 sql
     * @param $query
     * @param $data
     *
     * @return mixed
     */
    public function getQuery($query, $data);

    /**
     *  更新数据
     * @param $data
     * @param $condition
     *
     * @return mixed
     */
    public function update($data, $condition);

    /*
     *  删除数据
     *  @param $condition 删除条件 array()
     * */
    public function delete($condition);
}
