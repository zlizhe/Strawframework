# StrawFramework

> Version 2.0

php 7.2 / mysql 8.0 / mongodb 4.0 / namespace 支持计划

> Version 1.15

Mongodb insert/update 操作如需要进行批量新增/更新 需要加入 args 参数 ['bulk' => true], $this->update($data, $condition, $args=['bulk' => true]), 默认为单条新增/更新

> Version 1.13

Mongodb 扩展 新增 $this->getLastSql() 方法获取 mongodb 原生查询条件

_id 为 key 的字段查询不在需要 new ObjectId 兼容以前方法

新增 like 方法 ['name' => []'$like' => 'straw']] 不在需要 new Regex

所有查询条件 只允许 字母数字 $ 符号

Model::removeUnsafeField 方法现在会移除 $ 符号，防止 get 到外部的 $ 运算符号 (非法建议所有数据库操作之前进行该操作)

update 时 新增 $this->getModifiedCount() 返回真正被修改的行数

> Version 1.12

Mongodb 扩展 update 方法新增多行批量更新能力, insert、update 方法现在会在失败时显式返回 false

> Version 1.11

Mongodb 扩展新增 insert 多行数据能力， insert 传入二维数组 批量插入

> Version 1.10

Mongodb 主从 bug fixed

> Version 1.9

增加 Mongodb 扩展 aggregate 方法，该方法还可继续扩展

> Version 1.8

Config 中未设置 config_path 项，即不在读取 modules.json 配置

修改 Mongodb 扩展 count 的方法

> Version 1.7

新增 数据库 随机读写模式 配置 DB -> WRITE_MASTER = false 随机读写 true 主从读写

> Version 1.5

新增 权限检查项目 $this->isPurview() 仅针对带 token请求的 api (即将于 2.1 之后 版本弃用)

> Version 1.4

db / mysql 修正了 field 可能会带来的错误

> Version 1.3

增加 config_path 域 config_path 至 config

Config  modules 独立至 modules.json sites.json 

> Version 1.2

db & model 增加链式操作 所有查询语句使用链式操作完成

db / mysql 使用所有执行方法 使用 pdo 绑定数据完成 sql 执行

> Version 1.1

cache / redis 支持 auth 连接 并增加了一些新的方法 incr etc.

> Version 1.0

model / db 类下的方法名称全部重命名，统一命名规则。

db 类支持 分布式数据库 （1写N读）

controller 现在支持任意大小写的文件名称了

> Version 0.6

db/mongodb.php => sort bug fix

> Version 0.5

db/mongodb.php => update 方法允许传入非 $set 参数可自定义为 $addToSet $pullAll 等

> Verson 0.4

db/mongo.php => 对应正确的mongodb老扩展名称修改

db/mongodb.php => 针对新扩展修正了 findAll select find 方法返回的 ____id对象为 字符串，cursur对象为数组

base/model.php => 为mongodb新扩展带来了与mysql相同的快速取值方法 $this->__ALL__ (默认的___fild = id 还需要优化已适应不同的数据库) 

> Version 0.3

路由相关
