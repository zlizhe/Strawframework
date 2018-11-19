<?php
namespace Strawframework;
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
            throw new \Exception(sprintf('Config file config.php in env %s not loaded.', $_ENV['APP_ENV']));

        if (!empty(self::$config['database'])){
            self::$config['databases'] = @include ($configPath . 'databases.php');
            if (false == self::$config['databases'])
                throw new \Exception(sprintf('Config file databases.php in env %s not loaded.', $_ENV['APP_ENV']));
        }
        if (!empty(self::$config['cache'])){
            self::$config['caches'] = @require_once ($configPath . 'caches.php');
            if (false == self::$config['caches'])
                throw new \Exception(sprintf('Config file caches.php in env %s not loaded.', $_ENV['APP_ENV']));
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

    //入口
    public function run(): void {
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
        preg_match('/@Ro\s*\(name=[\'|\"](\w+)[\'|\"]\)/i', $classDoc, $roArr);
        $ro = $roArr[1];
        //Controller 有 Ro
        if (!empty($ro)) {
            //throw new \Exception(sprintf('The router %s Request Object can not found, or Ro config invalid in controller.', $a));
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            //匹配整个类
            $requestDocs = [];
            foreach ($methods as $key => $method) {
                //方法的注释
                $requestDoc = $method->getDocComment();
                preg_match('/@Request\s*\(uri=[\'|\"](\/?[\w|-]*)[\'|\"]\s*,\s*target=[\'|\"](' . implode('|', self::AVAILABLE_METHODS) . ')[\'|\"]\)/i', $requestDoc, $requestRouter);
                preg_match('/@Required\s*\(column=[\'|\"]([\w|-|\s|,]+)[\'|\"]\)/i', $requestDoc, $requiredArr);
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

            if (FALSE == $requestDocs[$a][$rMethod]) {
                throw new \Exception(sprintf("Router error, can not found uri %s.", $a));
            }

            //设置当前  controller action name
            $this->setDefineName($c, $a, $v);

            //set requests
            $requestObj = RequestFactory::factory($v, $ro, $requestDocs[$a][$rMethod]['required'], $rMethod)->getRequest();
            //真实的 action name
            $doAction = $requestDocs[$a][$rMethod]['name'];
        }else{
            //没有 Ro 的 Controller 走 Aciton name
            $doAction = $a;
        }

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

}

