<?php
namespace strawframework;
/**
 *  straw framework base class
 *  2018.8.28
 *  Zack Lee
 *
 *  zlizhe.com
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
        return '2.0';
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


    //入口
    public function run(): void {

        //如果启用 path_info
        if (strtolower(self::$config['config']['router']) == 'path_info') {
            $this->router();
            //default controller
            if (empty($_GET[0]) || $_GET[0] == 'index.php') {
                $_GET[0] = SCRIPT_NAME;
            }

            //default action
            if (empty($_GET[1])) {
                $_GET[1] = 'index';
            }

            $c = $_GET[0];
            $a = $_GET[1];
        } else {
            //默认方式
            //controller
            $c = $_GET['c'] ?: 'index';
            //action
            $a = $_GET['a'] ?: 'index';
        }

        //设置当前  controller action name
        $this->setControllerActionName($c, $a);

        $file = CONTROLLERS_PATH . $c . '.php';
        if (!file_exists($file)) {
            ex($c . ' Class Not Found!');
        }

        $cname = "\controllers\\" . lcfirst($c);
        $obj = new $cname();
        if (!method_exists($obj, $a)) {
            // __call 映射
            if (!method_exists($obj, '_call')) {
                ex($a . ' Action Not Found!');
            } else {
                $a = '_call';
            }
        }

        $obj->$a();
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

    //路由
    private function router(): void {
        if (PHP_SAPI === 'cli') {
            // Command line requires a bit of hacking
            if (isset($_SERVER['argv'][1])) {
                $current_uri = $_SERVER['argv'][1];

                // Remove GET string from segments
                if (($query = strpos($current_uri, '?')) !== FALSE) {
                    list($current_uri, $query) = explode('?', $current_uri, 2);

                    // Parse the query string into $_GET
                    parse_str($query, $_GET);
                }
            }
        } elseif (current($_GET) === '' && substr($_SERVER['QUERY_STRING'], -1) !== '=') {
            // The URI is the array key, eg: ?this/is/the/uri
            $current_uri = key($_GET);
            // Remove the URI from $_GET
            unset($_GET[$current_uri]);
            // Remove the URI from $_SERVER['QUERY_STRING']
            $_SERVER['QUERY_STRING'] = ltrim(substr($_SERVER['QUERY_STRING'], strlen($current_uri)), '/&');
        } elseif (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) {
            //去掉后面的query_string
            $current_uri = strstr($_SERVER['REQUEST_URI'], '?', TRUE);
        } elseif (isset($_SERVER['ORIG_PATH_INFO']) && $_SERVER['ORIG_PATH_INFO']) {
            $current_uri = $_SERVER['ORIG_PATH_INFO'];
        } elseif (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF']) {
            $current_uri = $_SERVER['PHP_SELF'];
        }

        $current_uri = preg_replace('#\.[\s./]*/#', '', $current_uri);

        $current_uri = trim($current_uri, '/');

        $segments = explode('/', $current_uri);
        $spc = defined('RPARAM_CHR') ? RPARAM_CHR : '-';
        //支持以'/'作为参数分隔符,第一个和第二个参数固定,分别表示controller和action
        if ($spc == '/') {
            for ($i = 0, $plen = count($segments); $i < $plen; $i++) {
                if ($i < 2) {
                    $_GET[$i] = $segments[$i];
                } else {
                    $_GET[$segments[$i]] = $segments[++$i];
                }
            }
        } else {
            $ri = 0;
            foreach ($segments as $key => $val) {
                if ($val !== '') {
                    //取参数
                    if (strpos($val, $spc) !== FALSE) {
                        list($kk, $vv) = explode($spc, $val, 2);
                        $_REQUEST[$kk] = $_GET[$kk] = urldecode(trim($vv));
                    } else {
                        $_GET[$ri++] = $val;
                    }
                }
            }
        }
    }

    //生成可用的 url
    public static function createUrl($ca, $param = []) {
        if (!$ca) {
            return FALSE;
        }

        list($c, $a) = explode('/', $ca);
        if ($param && !is_array($param)) {
            return FALSE;
        }

        $params = '';
        //array to string
        if ($param) {
            foreach ($param as $key => $value) {
                $params .= '&' . addslashes($key) . '=' . addslashes($value);
            }
        }
        unset($param);

        if (strtolower(self::$config['config']['router']) == 'path_info') {
            //pathinfo 地址
            $url = '';
//            if ($module)
//                $url .= '/' . addslashes($module);
            if ($c) {
                $url .= '/' . addslashes($c);
            }
            if ($a) {
                $url .= '/' . addslashes($a);
            }
            if ($params) {
                $url .= '?' . substr($params, 1, -1);
            }

            return $url;
        } else {
            //普通地址
            $url = '/index.php';
            if ($c) {
                $url .= '?c=' . addslashes($c);
                if ($a) {
                    $url .= '&a=' . addslashes($a);
                }
//                if ($module)
//                    $url .= '&m='.addslashes($module);
                if ($params) {
                    $url .= substr($params, 1, -1);
                }
            }

            return $url;
        }
    }
}

