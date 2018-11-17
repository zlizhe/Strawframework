# StrawFramework

Wiki (https://github.com/zlizhe/strawframework/wiki)

> Strawberry Team 使用的 Web 服务端开发框架

> PHP 7.2 +

> Mysql 5.5 +

> Mongodb 3.x +

## 一般约定

* url 参数使用下划线划分割
* 变量名, 方法名, 类名, 文件名, 驼峰法命名, 文件/文件夹名/ Namespace 首字母必须大写, 其他首字母小写
* 数据表字段名使用下划线分割
* 输出字段使用下划线分割

## On Do

- [x] Router

- [x] Request Object

- [ ] Service

- [ ] Logic

- [ ] Model

- [ ] Mysql ORM

- [ ] Mongodb Libray

- [ ] Data View Object

- [ ] Input Ouput 分别支持 JSON TEXT XML 支持配置


## Router Controller

文件位于 Controller/VERSION/NAME.php

继承 Strawframework\Base\Controller

* Controller 类必须携带注释 @Ro

```
@Ro(name='Article')
```

> 该 Class 下所有 Action 需使用的传入值于 Ro/VERSION/Article 处申明

* 每个 Function 若用于 Router Action 必须申明为 **public** 并携带 @request 注释

```
@Request(uri='/article', target='get')
```

[必填] 最终访问 URL GET */version/controller/article*

> 支持 get post put delete

```
@Required(column='id,title')
```

[可选] 本访问必填字段申明, 逗号分割多个字段, 字段为 **Ro 处申明的名称非实际传入字段名** (非常重要, 允许字段名如下划线分割 Ro 字段名驼峰法表示, 在这里必须使用 Ro 字段名)

* 获取传入值 Ro

```
$this->getRequests()
```

为所有已传入值的 Request Object 可使用 getColumnName() 方法获取每个字段值(仅 Controller 内获取)

## Ro (Request Object)

文件位于 Ro/VERSION/NAME.php

继承 Strawframework\Base\RequestObject

将所有 @Ro(name='Article') Controller 中所有 Action 所需要的传入字段 以 **protected** 申明, 并且必须携带注释 @Column, name 为传入值名, type 为传入值类型

```
@Column (name='id', type='int')
```