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
        $rMethod = strtolower(REQUEST_METHOD);
        if (!in_array($rMethod, self::AVAILABLE_METHODS))
            ex(sprintf("%s method not invalid.", $rMethod));
        

        $_GET['_URI_'] = explode('/', key($_GET));

        //version
        $v = $_GET['_URI_'][0] ?? 'v1';
        //controller
        $c = ucfirst($_GET['_URI_'][1]) ?? 'Home';
        //router
        $a = lcfirst($_GET['_URI_'][2]) ?? 'main';
        unset($_GET[key($_GET)], $_GET['_URI_']);

        //设置当前  controller action name
        $this->setControllerActionName($c, $a);

        $file = PROTECTED_PATH . 'Controllers' . DS . $v . DS . $c . '.php';
        if (!file_exists($file)) {
            ex($c . ' Class Not Found!');
        }

        $cname = sprintf("Controllers\\%s\\%s", $v, $c);
        $reflection = new \ReflectionClass($cname);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $requestDocs = [];
        foreach($methods as $key => $method){
            //方法的注释
            $requestDoc = $method->getDocComment();
            preg_match('/@Request\s*\(uri=[\'|\"]\/?(.*)[\'|\"]\s*,\s*target=[\'|\"]('.implode('|', self::AVAILABLE_METHODS).')[\'|\"]\)/i', $requestDoc, $requestRouter);  
            list($requet, $action, $target) = $requestRouter;
            if (!empty($action) && !empty($target)){
                $requestDocs[$action][$target] = [
                    'name' => $method->getName(),
                ];
            }
        }

        if (!in_array($a, array_keys($requestDocs)))
            ex(sprintf("Router error, can not found uri %s", $a));

        //真实的 action name
        $doAction = $requestDocs[$a][$rMethod]['name'];
        $requestObj = RequestFactory::factory($a, $rMethod)->getRequest();

        // if (!method_exists($obj, $a)) {
        //     // __call 映射
        //     if (!method_exists($obj, '_call')) {
        //         ex($a . ' Action Not Found!');
        //     } else {
        //         $a = '_call';
        //     }
        // }

        $res = (new $cname())->setRequests($requestObj)->$doAction();
    }

    /**
     *  根据  router 设置当前的 controller action name 常量
     */
    private function setControllerActionName(string $c, string $a): void {
        if (!$c || !$a) {
            return;
        }

        define("CONTROLLER_NAME", $c);
        define("ACTION_NAME", $a);
    }

}

