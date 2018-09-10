<?php
if (TRUE == APP_DEBUG) {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
} else {
    //生产环境
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
}
/* 初始化设置 */
@ini_set('memory_limit', '128M');
//@ini_set('session.session.cookie_lifetime',3600);
@ini_set('session.cache_expire', 180);
@ini_set('session.use_cookies', 1);
@ini_set('session.auto_start', 0);
@ini_set('display_errors', APP_DEBUG);

//date_default_timezone_set('Asia/Shanghai');
ini_set('date.timezone', 'Asia/Shanghai');

header('Cache-control: private');
header('Content-type: text/html; charset=utf-8');

// 要求定义好 _PATH_ 和 SCRIPT_NAME ，及定义 DS 为路径分隔符
if (!defined('DS')) {
    die("Error : (" . realpath(__FILE__) . ") not defined 'DS' ");
}
if (!defined('ROOT_PATH')) {
    die("Error : (" . realpath(__FILE__) . ") not defined 'ROOT_PATH' ");
}
if (!defined('SCRIPT_NAME')) {
    die("Error : (" . realpath(__FILE__) . ") not defined 'SCRIPT_NAME' ");
}

//是否显示错误信息
define('SHOW_ERROR', APP_DEBUG);

//源码目录
define("PROTECTED_PATH", ROOT_PATH . 'protected' . DS);
//库目录
define("LIBRARY_PATH", ROOT_PATH . 'strawframework' . DS);
//公共方法目录
//define("COMMON_PATH", PROTECTED_PATH.'common'.DS);
//base
define("BASE_PATH", LIBRARY_PATH . 'base' . DS);
//静态资源目录
define("PUBLIC_PATH", ROOT_PATH . 'public' . DS);
//base mvc
define("CONTROLLERS_PATH", PROTECTED_PATH . 'controllers' . DS);
define("MODELS_PATH", PROTECTED_PATH . 'models' . DS);
//模板路径
define("TEMPLATES_PATH", PUBLIC_PATH . 'templates' . DS);
//第三方扩展目录
define("VENDORS_PATH", LIBRARY_PATH . 'vendors' . DS);

//配置信息目录,如数据库配置,缓存服务器配置
define("CONFIG_PATH", PROTECTED_PATH . 'config' . DS);
//logs path
define('LOGS_PATH', PUBLIC_PATH . 'logs' . DS);

define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
define('IS_GET', REQUEST_METHOD == 'GET' ? TRUE : FALSE);
define('IS_POST', REQUEST_METHOD == 'POST' ? TRUE : FALSE);
define('IS_PUT', REQUEST_METHOD == 'PUT' ? TRUE : FALSE);
define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? TRUE : FALSE);
define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) ? TRUE : FALSE);

// 系统函数
include(BASE_PATH . 'functions.php');

if (file_exists(PROTECTED_PATH . 'functions.php')) // 用户函数
    include(PROTECTED_PATH . 'functions.php');

include(LIBRARY_PATH . 'straw.php');


////允许携带 cookie
//header("Access-Control-Allow-Credentials: true");
//unset($siteDomain);
spl_autoload_register(function (string $class): void {
    // echo $class;
    // echo '_____';

    //可用的 namespace Path
    $classPath = [
        'strawframework\\base' => BASE_PATH,
        'strawframework\\cache' => LIBRARY_PATH . 'cache' . DS,
        'strawframework\\db' => LIBRARY_PATH . 'db' . DS, 
        'strawframework\\vendors' => VENDORS_PATH,
        'strawframework\\protocol' => LIBRARY_PATH . 'protocol' . DS,
        'controllers' => CONTROLLERS_PATH,
        'models' => MODELS_PATH,
        'views' => TEMPLATES_PATH,
    ];
    $cname = end(explode('\\', $class));
    $namespacePath = str_replace('\\' . $cname, '', $class);

    if (!in_array($namespacePath, array_keys($classPath)))
        ex(sprintf('%s path not availiable!', $class), '', '系统错误');

    $fileName = $classPath[$namespacePath] . strtolower($cname) . '.php';
    // echo $fileName;
    // echo "<br/>";
    if (is_file($fileName)) {
        //win平台检查一下 大小写是否一致
        if (FALSE == checkFileNameViaWin($fileName)) {
            ex(sprintf('%s 文件名称大小写不一致 !', $fileName), '', '系统错误');
        }
        require_once($fileName);
    } else {
        ex(sprintf('Path %s, file %s can not found !', $class, $fileName), '', '系统错误');
    }
});
session_start();
//set_exception_handler('error_handler');
