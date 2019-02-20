<?php
namespace Strawframework;
use Strawframework\Base\Container;
use Strawframework\Factory\RequestFactory;

/**
 *  straw framework base class
 *  2018.11
 *  Strawberry Team
 *
 */
class Straw {

    //配置项目
    public static $config = [];

    public function __construct() {

        if (null == self::$container)
            self::$container = new Container();
    }

    /**
     * Strawframework 版本
     * @return string
     */
    public static function version() : string {
        return '3.0';
    }


    /**
     *  读取配置文件
     */
    public function loadConfig(): Straw {


        if (!empty(self::$config)) {
            return $this;
        }

        //环境配置路径
        $configPath = PROTECTED_PATH . 'Config' . DS . strtolower($_ENV['APP_ENV']) . DS;

        //佛性加载器
        self::$config = @include ($configPath . 'config.php');
        if (false == self::$config)
            die(sprintf('Config file config.php in env %s not loaded.', $_ENV['APP_ENV']));

        ////加载扩展配置
        if (!empty(self::$config['ext'])){
            foreach(self::$config['ext'] as $conf){
                self::$config[$conf] = @include ($configPath . $conf. '.php');
                if (false == self::$config[$conf])
                    die(sprintf('Config file %s.php in env %s not loaded.', $conf, $_ENV['APP_ENV']));
            }
        }

        //默认db
        if (!empty(self::$config['database']))
            define('DEFAULT_DB', self::$config['database']);
        //默认 cache
        if (!empty(self::$config['cache'])) {
            define('DEFAULT_CACHE', self::$config['cache']);
            //默认缓存 时间
            define('DEFAULT_CACHEEXPIRE', self::$config['cache_expire'] ?: 0);
        }

        //当前环境语言
        $sysLang = current(explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]));
        @define('SYS_LANG', str_replace('-', '_', $sysLang));
        define('DEFAULT_LANG', self::$config['default_lang']);
        return $this;
    }


    //允许的方法
    const AVAILABLE_METHODS = ['get', 'post', 'put', 'delete'];


    /**
     * 入口
     * @throws \Exception
     */
    public function run(): void {
        \Strawframework\Base\Log::getInstance()->debug('Run on APP_ENV', $_ENV['APP_ENV'], 'APP_DEBUG = ' . APP_DEBUG);
        $rMethod = strtolower($_SERVER['REQUEST_METHOD']);
        if (!in_array($rMethod, self::AVAILABLE_METHODS))
            throw new \Exception(sprintf("%s method not invalid.", $rMethod));
        

        $_GET['_URI_'] = explode('/', key($_GET));

        //version
        $v = $_GET['_URI_'][1] ?: 'v1';
        //controller
        $c = ucfirst($_GET['_URI_'][2]) ?: 'Home';
        //router
        $a = lcfirst($_GET['_URI_'][3]) ?: '/';
        unset($_GET[key($_GET)], $_GET['_URI_']);

        //version = v0 is test
        if ('v0' == $v && FALSE == APP_DEBUG)
            throw new \Exception('This version just run on DEVELOPMENT environment.');

        $file = PROTECTED_PATH . 'Controller' . DS . $v . DS . $c . '.php';
        if (!file_exists($file)) {
            throw new \Exception(sprintf('Class %s not found.', $c));
        }

        $cname = sprintf("Controller\\%s\\%s", $v, $c);
        $reflection = new \ReflectionClass($cname);
        $classDoc = $reflection->getDocComment();
        preg_match('/@Ro\s*\(\s*name\s*=\s*[\'|\"]?(\w+)[\'|\"]?\s*\)/i', $classDoc, $roArr);
        $ro = $roArr[1];

        //Controller 有 Ro
        if (empty($ro))
            throw new \Exception(sprintf('The router %s Request Object can not found, or Ro config invalid in controller.', $a));

        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        //匹配整个类
        $requestDocs = [];
        foreach ($methods as $key => $method) {
            //方法的注释
            $requestDoc = $method->getDocComment();
            preg_match('/@Request\s*\(\s*uri\s*=\s*[\'|\"](\/?[\w|-]*)[\'|\"]\s*,\s*target\s*=\s*[\'|\"](' . implode('|', self::AVAILABLE_METHODS) . ')[\'|\"]\s*\)/i', $requestDoc, $requestRouter);
            preg_match('/@Required\s*\(\s*column\s*=\s*[\'|\"]([\w|-|\s|,]+)[\'|\"]\)\s*/i', $requestDoc, $requiredArr);
            list($requet, $action, $target) = $requestRouter;
            //有路由的配置
            if (!empty($action) && !empty($target)) {
                $requestDocs[$action][$target] = [
                    'name' => $method->getName(),
                ];
            }
            //必填项目需要
            if (!empty($requiredArr[1])) {
                $requestDocs[$action][$target]['required'] = array_map('trim', explode(',', $requiredArr[1]));
            }
        }

        \Strawframework\Base\Log::getInstance()->debug('ALL REQUEST DOCS', $requestDocs);
        //如果取不到值 加 / 兼容 list /list
        if (!$requestDocs[$a][$rMethod]){
            $a = '/' . $a;
        }

        if (FALSE == $requestDocs[$a][$rMethod]) {
            if (false == APP_DEBUG)
                $requestDocs = [];
            throw new \Exception(sprintf("Router error, can not found uri %s. Router requests: %s", $a, var_export($requestDocs)));
        }

        //设置当前  controller action name
        $this->setDefineName($c, $a, $v);

        //set requests
        $requestObj = RequestFactory::factory($v, $ro, $requestDocs[$a][$rMethod]['required'], $rMethod)->getRequest();
        //真实的 action name
        $doAction = $requestDocs[$a][$rMethod]['name'];
        //}else{
        //    //没有 Ro 的 Controller 走 Aciton name
        //    $doAction = $a;
        //}

        (new $cname())->setRequests($requestObj ?? null)->$doAction();
    }

    /**
     *  根据  router 设置当前的 controller action name 常量
     * @param string $c
     * @param string $a
     */
    private function setDefineName(string $c, string $a, string $v): void {
        if (!$c || !$a) {
            return;
        }

        if (!defined("CONTROLLER_NAME"))
            define("CONTROLLER_NAME", $c);
        if (!defined("ACTION_NAME"))
            define("ACTION_NAME", $a);
        if (!defined("VERSION_NAME"))
            define("VERSION_NAME", $v);
    }

    protected static $container = null;

    /**
     * 获取一个实例
     */
    protected function getSingleInstance($instance){

        if (!isset(self::$container->$instance))
            self::$container->$instance = new $instance;

        return self::$container->$instance;
    }
}

