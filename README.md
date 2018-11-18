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

- [ ] Mongodb Library

- [ ] Data View Object

- [ ] Input Output 分别支持 JSON TEXT XML 支持配置


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

**必填** 最终访问 URL GET */version/controller/article*

> 支持 get post put delete

```
@Required(column='id,title')
```

**可选** 本访问必填字段申明, 逗号分割多个字段, 字段名应为 **Ro 处申明的名称, 非实际传入字段名** (非常重要, 如字段名下划线分割(first_name) Ro 字段名驼峰法表示(firstName), 在这里必须使用 Ro 字段名(firstName) 来申明为必填字段)

### 获取传入值 Ro

```
$this->getRequests(); //含有所有可用并已传入值的 Request Object 
```

```
$this->getRequests()->getColumnName(); //指定名称获取每个字段值
$this->getRequests()->getFirstName(); //获取上例中的 first_name 传入字段
```
>仅 Controller 内可获取


## Ro (Request Object)

文件位于 Ro/VERSION/NAME.php

继承 Strawframework\Base\RequestObject

将所有 @Ro(name='Article') Controller 中所有 Action 所需要的传入字段 以 **protected** 申明, 并且必须携带注释 @Column, name 为传入值名, type 为传入值类型

```
@Column (name='id', type='int')
```

## Error 拦截器

创建任意 Error 拦截器 于 Protected/Error/, 每个 Error 拦截器可定义一个 Error code (11), 每个错误可以定义一个 Error code (01), 最终的错误显示 code 为 1101, 为区分不同业务的来源应为不同业务(Controller)创建不同的 Error 拦截器

Error 需要继承 \Strawframework\Base\Error

```
protected $code = '11';
```
$code 为当前业务的 Error code, code = 10 为 Strawframework 保留 code

```
//占位符 => 错误码
protected $errorCode = [
    'ID_INVALID' => '01'
];
```

申明 $errorCode 对应错误占位符 与 错误码

```
public function __construct(string ...$msgKeyAndValue) {
    //第二个参数为语言包 
    parent::__construct($msgKeyAndValue, 'ArticleError');
}
```

> 调用父类方法时传入语言包名称, 加载语言包 Protected/Lang/VERSION/LANG/ArticleError.php

### 调用方法

```
throw new \Error\Article('ID_INVALID', 'id'); //ID_INVALID 为占位符应在 ArticleError -> $errorCode 与 语言包 ArticleError 中存在
```

### 其他异常
```
throw new Strawframework\Base\Error(ERROR_MSG); //该方法抛出 Strawframework 内部的可显示异常或错误

throw new \Exception(ERROR_MSG); //该方法抛出系统错误的异常(若要使用户看见信息请使用上一个方法), 在生产环境不显示详细信息, 请勿传入第二参数
```

## 语言包 (未全部完成)

语言包与配置文件一样，创建于 Protected/Lang/VERSION/LANG/ArticleError.php

```
return [
    'ID_INVALID' => 'Article id %s invalid.',
];
```

直接 Return 数组, key 为占位符, value 为该语言(LANG)消息内容, 可继续含有占位符。如(%s, %d)
