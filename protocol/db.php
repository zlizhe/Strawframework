<?php
namespace strawframework\protocol;

/**
 *  db 库所有实现方法
 */
interface Db{

    /**
     *  写入新数据
     *  @param array $data 新数据数组
     */
    public function insert(array $data, array $args = []);


    /*
     * 根据条件查找一条
     * @param $query 查询条件
     * @param $data 绑定数据
     * @param $field 查询字段
     */
    public function getOne() : array;


    /**
     * 查找所有符合条件的行
     * @param $query 查询条件
     * @param $field 查询字段
     *
     */
    public function getAll() : array;

    /**
     * 执行完整 sql
     * @param $query
     * @param $type
     *
     * @return mixed
     */
    public function getQuery($query, $data);

    /**
     *  更新数据
     *  @param $data 新数据数组 array()
     *  @param $condition 更新条件 array()
     *
     */
    public function update($data, $condition);

    /*
     *  删除数据
     *  @param $condition 删除条件 array()
     * */
    public function delete($condition);
}
