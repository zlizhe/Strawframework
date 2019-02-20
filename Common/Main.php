<?php
namespace Strawframework\Common;

use Strawframework\Base\Result;
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

        $_ENV['APP_ENV'] = getenv('APP_ENV');

        //有值则设定，否则生产环境
        if (!($_ENV['APP_ENV']))
            $_ENV['APP_ENV'] = $this->appEnv;

        return $this;
    }

    /**
     * configure Strawframework
     * @method Straw loadConfig()
     * @param string $boot
     *
     * @return Straw
     */
    public function configure(string $boot) : Straw {
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

        //生产环境 关闭 debug
        if ($this->appEnv == strtoupper($_ENV['APP_ENV'])) {
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
        //@ini_set('log_errors', 1);
        @ini_set('display_errors', APP_DEBUG);

        //date_default_timezone_set('Asia/Shanghai');
        @ini_set('date.timezone', 'Asia/Shanghai');

        //runtime
        define('RUNTIME_PATH', ROOT_PATH . 'Runtime' . DS);
        //logs path
        define('LOGS_PATH', RUNTIME_PATH . 'Logs' . DS);

        //define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
        //define('IS_GET', REQUEST_METHOD == 'GET' ? TRUE : FALSE);
        //define('IS_POST', REQUEST_METHOD == 'POST' ? TRUE : FALSE);
        //define('IS_PUT', REQUEST_METHOD == 'PUT' ? TRUE : FALSE);
        //define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? TRUE : FALSE);
        //define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) ? TRUE : FALSE);

        //composer autoload
        require_once(LIBRARY_PATH . 'vendor' . DS . 'autoload.php');
        //get config from .evn.
        //try{
        //    $dotenv = new \Dotenv\Dotenv(PROTECTED_PATH . 'Config' . DS . $_ENV['APP_ENV']);
        //    $dotenv->load();
        //}catch(\Exception $e){}

        require_once(LIBRARY_PATH . 'Straw.php');
        header(sprintf('X-Powered-By: Strawframework/%s', Straw::version()));

        spl_autoload_register(function (string $class): void {
             //echo $fileName;
             //echo "<br/>";
            Main::import($class);
        });

        //Fatal error 统一处理
        set_error_handler(function($errno, $errstr, $errfile, $errline){
            //Fatal error
            if (E_ERROR == $errno){
                \Strawframework\Base\Log::getInstance()->setType('mongodb')->error('SERVER ERROR', $errno, $errstr, $errfile, $errline);
                return new Result(Code::SERVER_ERROR, $errstr, [
                    '_error_level' => 'Server Error!',
                    '_error_file' => $errfile . '; Line: ' . $errline
                ]);
            }

            //warning
            //if (E_WARNING == $errno){
            //    \Strawframework\Base\Log::getInstance()->setType('mongodb')->warning('SERVER WARNING', $errno, $errstr, $errfile, $errline);
            //    return new Result(Code::SERVER_ERROR, $errstr, [
            //        '_error_level' => 'Server Warning!',
            //        '_error_file' => $errfile . '; Line: ' . $errline
            //    ]);
            //}
            //var_dump($errno, $errstr, $errfile);
            return true;
        }, E_ALL & ~ E_NOTICE);
        //throw error 错误统一处理
        set_exception_handler(function($exception){
            // System \Exception 不显示具体错误信息
            if (FALSE == APP_DEBUG && 0 == $exception->getCode()){
                return new Result(Code::FAIL, 'An error has occurred.');
            }
            $res = [
                'error_code' => $exception->getCode()
            ];
            if (true == APP_DEBUG){
                $res['_debug_throw'] = $exception->getFile() . '; Line:' . $exception->getLine();
                $res['_debug_trace'] = $exception->getTrace();
            }

            \Strawframework\Base\Log::getInstance()->debug('USER EXCEPTION', $exception->getMessage(), $res);
            return new Result(Code::FAIL, $exception->getMessage(), $res);
        });

        session_start();

        $boot = "\\Strawframework\\" . $boot;
        return (new $boot())->loadConfig();
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
