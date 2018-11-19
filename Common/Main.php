<?php
namespace Strawframework\Common;

use Strawframework\Straw;

final class Main{

    private static $me;
    /**
     * 获取
     */
    public static function getInstance(){
        if (!self::$me)
            self::$me = new self();

        return self::$me;
    }

    //默认 ENV 生产环境
    private $appEnv = 'PRODUCTION';

    public function getEnv(): Main{
        //源码目录
        define("PROTECTED_PATH", ROOT_PATH . 'Protected' . DS);
        //库目录
        define("LIBRARY_PATH", ROOT_PATH . 'Strawframework' . DS);
        //静态资源目录
        define("PUBLIC_PATH", ROOT_PATH . 'Public' . DS);
        //模板路径
        define("TEMPLATES_PATH", PUBLIC_PATH . 'Templates' . DS);
        //第三方扩展目录
        //define("VENDORS_PATH", PROTECTED_PATH . 'Vendors' . DS);

        //composer autoload
        require_once(LIBRARY_PATH . 'vendor' . DS . 'autoload.php');
        //load env from Public/.env
        try{
            $dotenv = new \Dotenv\Dotenv(PUBLIC_PATH);
            $dotenv->load();
        }catch(\Exception $e){}

        //有值则设定，否则生产环境
        if (!isset($_ENV['APP_ENV']))
            $_ENV['APP_ENV'] = $this->appEnv;

        return $this;
    }

    //configure Strawframework
    public function configure(string $boot) : Straw {

        //生产环境 关闭 debug
        if ('PRODUCTION' == strtoupper($_ENV['APP_ENV'])) {
            define('APP_DEBUG', FALSE);
        } else {
            define('APP_DEBUG', TRUE);
        }

        if (TRUE == APP_DEBUG) {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
        } else {
            //生产环境
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
        }
        /* 初始化设置 */
        //@ini_set('memory_limit', '128M');
        //@ini_set('session.session.cookie_lifetime',3600);
        //@ini_set('session.cache_expire', 180);
        //@ini_set('session.use_cookies', 1);
        //@ini_set('session.auto_start', 0);
        @ini_set('display_errors', APP_DEBUG);

        //date_default_timezone_set('Asia/Shanghai');
        @ini_set('date.timezone', 'Asia/Shanghai');

        //header('Cache-control: private');
        //header('Content-type: text/html; charset=utf-8');


        //配置信息目录,如数据库配置,缓存服务器配置
        define("CONFIG_PATH", PROTECTED_PATH . 'Config' . DS);
        //logs path
        define('LOGS_PATH', PUBLIC_PATH . 'Logs' . DS);
        //runtime
        define('RUNTIME_PATH', PROTECTED_PATH . 'Runtime' . DS);

        //define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
        //define('IS_GET', REQUEST_METHOD == 'GET' ? TRUE : FALSE);
        //define('IS_POST', REQUEST_METHOD == 'POST' ? TRUE : FALSE);
        //define('IS_PUT', REQUEST_METHOD == 'PUT' ? TRUE : FALSE);
        //define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? TRUE : FALSE);
        //define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) ? TRUE : FALSE);

        // 系统函数
        include(LIBRARY_PATH . 'Base' . DS . 'functions.php');
        //if (file_exists(PROTECTED_PATH . 'functions.php')) // 用户函数
        //    include(PROTECTED_PATH . 'functions.php');

        require_once(LIBRARY_PATH . 'Straw.php');


        ////允许携带 cookie
        //header("Access-Control-Allow-Credentials: true");
        //unset($siteDomain);
        spl_autoload_register(function (string $class): void {
             //echo $fileName;
             //echo "<br/>";
            Main::import($class, [
                'Controller', 'Error', 'Lang', 'Result', 'Ro'
            ]);
        });

        //throw error 错误统一处理
        set_exception_handler(function($exception){
            // System \Exception 不显示具体错误信息
            //if (0 == $exception->getCode()){
            //
            //}else{
            //
            //}
            //
            //echo "<pre>";
            //print_r($exception->getCode());die;
            ex($exception);
        });
        session_start();

        $boot = "\\Strawframework\\" . $boot;
        return new $boot();
    }

    /**
     * 导入包
     * @param string $class
     * @param array  $availablePath
     *
     * @throws \Exception
     */
    public static function import(string $class, $availablePath = []): void{
        $classArr = explode('\\', $class);
        $cname = end($classArr);
        array_pop($classArr); //pop Controller name
        //Library path
        if ('Strawframework' == current($classArr)){
            array_shift($classArr); //首个元素 Strawframework
            $path = LIBRARY_PATH . implode(DS, $classArr);
        }else{
            //设置白名单 非名单的不加载
            if (!empty($availablePath) && !in_array(current($classArr), $availablePath))
                throw new \Exception(sprintf('Can not load class %s.', $class));
            //Protected path
            $path = PROTECTED_PATH . implode(DS, $classArr);
        }

        $fileName = $path . DS . ucfirst($cname) . '.php';

        if (is_file($fileName)) {
            //win平台检查一下 大小写是否一致
            if (FALSE == Main::getInstance()->checkFileNameViaWin($fileName)) {
                throw new \Exception(sprintf('%s 文件名称大小写不一致 !', $fileName));
            }
            require_once($fileName);
        } else {
            throw new \Exception(sprintf('File %s can not found !', $fileName));
        }
    }

    /**
     * 开发环境 WIN 平台检查一下 大小写是否一致
     * @param $fileName
     *
     * @return bool
     */
    public function checkFileNameViaWin($fileName) {
        if (TRUE == APP_DEBUG) {
            if (basename(realpath($fileName)) != basename($fileName)) {
                return FALSE;
            } else {
                return TRUE;
            }
        }

        return TRUE;
    }
}
