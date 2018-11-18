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
    protected static $config = [];

    public function __construct() {

        //读取配置
        $this->loadConfig();

        //默认db
        define('DEFAULT_DB', self::$config['config']['database']);
        //默认 cache
        if (FALSE != self::$config['config']['cache']) {
            define('DEFAULT_CACHE', self::$config['config']['cache']);
            //默认缓存 时间
            define('DEFAULT_CACHEEXPIRE', self::$config['config']['cache_expire'] ?: 0);
        }
    }

    /**
     * strawframework 版本
     * @return string
     */
    public static function version() : string {
        return '3.0';
    }

    //读取 site config from json file
    private function loadSiteConfig(string $file): array {
        $filePath = self::$config['config_path'] . "/" . $file;

        return json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents($filePath)), TRUE);
    }

    /**
     *  读取配置文件
     */
    private function loadConfig(): void {


        if (isset(self::$config) && self::$config) {
            return;
        }

        //使用当前环境的配置文件
        $fileName = strtolower(APP_ENV);
        if (!APP_ENV) {
            $fileName = 'production';
        }
        if (is_file(CONFIG_PATH . $fileName . '.config.php')) {
            self::$config = include(CONFIG_PATH . $fileName . '.config.php');


            //是否需要读 sites.json
            if (self::$config['config_path']) {
                //读取 modules.json sites.json 配置信息 straw 内
                $modulesSetting = $this->loadSiteConfig('modules.json');
                $sitesSetting = $this->loadSiteConfig('sites.json');
                $siteArr = array_merge_recursive($modulesSetting, $sitesSetting);
                //赋值回 config
                self::$config['modules'] = $siteArr[strtolower(APP_ENV)];
            }
        } else {
            ex('Config file not found in ' . CONFIG_PATH . $fileName . '.config.php !');
        }
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
        $v = $_GET['_URI_'][0] ?: 'v1';
        //controller
        $c = ucfirst($_GET['_URI_'][1]) ?: 'Home';
        //router
        $a = lcfirst($_GET['_URI_'][2]) ?: '/';
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

