<?php
namespace Strawframework\Base;

use Strawframework\Straw;

/**
 *  base CONTROLLER  
 */
class Controller extends Straw {

    /**
     * 当前参数列表
     */
    private $requests;

    /**
     * 当前版本号
     * @var string
     */
    protected $_Gver = '';

    /**
     * 网站设置
     * 网站自身的设置相关 与配置等
     * @var array
     */
    protected $_Gset = [];

    /**
     * 用户相关
     * 用户自己登录的数组 为登录为 NULL
     * @var array
     */
    protected $_Guser = [];

    /**
     * API 连接使用的 module urls
     */
    protected $_availableModules = [];

    //模板 页面
    private $view = NULL;

    // API 常量
    // 默认成功
    CONST SUCCESS = 0;
    // 默认失败
    CONST FAIL = 1;
    // 数据为空
    CONST ISEMPTY = 2;
    //需要登录
    CONST NOT_LOGIN = -10;

    public function __construct(bool $isView = TRUE) {
        parent::__construct();
        //
        ////当前程序版本
        //$this->_Gver = '';//@todo version();
        //
        ////read from config
        //$this->_availableModules = parent::$config['modules'];
        //if (!$this->_availableModules) {
        //    ex('API available can not set');
        //}
        //
        ////配置
        //$this->_Gset['site_domain'] = parent::$config['config']['site_domain'];
        ////本 module name
        //$this->_Gset['module_name'] = parent::$config['config']['module_name'];
        //
        //
        ////是否加载模板
        //if (TRUE == $isView) {
        //    //实例化模板类
        //    $this->view = new \Strawframework\Base\View();
        //
        //    $this->assign('availableModules', $this->_availableModules);
        //
        //    //站点设置
        //    $this->assign("_Gset", $this->_Gset);
        //    //程序版本
        //    $this->assign('_Gver', $this->_Gver);
        //}
        //
        //$this->_csrfToken = $this->_getCsrfToken();
        //
        ////验证 csrf_token
        //if ($_REQUEST['_csrf_token'] && $_REQUEST['_csrf_token'] !== $this->_csrfToken) {
        //    ex('Access Denied');
        //}

        //默认的证书
        //if (parent::$config['config']['sign_key'] && parent::$config['config']['rsa_sign']){
        //    define('SIGN_KEY', strtolower(parent::$config['config']['sign_key']));
        //}
    }

    /**
     * 设置请求参数 来源于 Request
     */
    public function setRequests(?RequestObject $requests): Controller{
        $this->requests = $requests;
        return $this;
    }

    /**
     * 返回当前请求的参数 Request object
     */
    public function getRequests(): ? RequestObject{
        return $this->requests;
    }

    /**
     * 页面跳转
     * @param string $url
     * @param int    $time
     * @param string $msg
     */
    protected function redirect(string $url, int $time = 0, string $msg = '') {
        //多行URL地址支持
        $url = str_replace(array("\n", "\r"), '', $url);
        if (empty($msg)) {
            $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
        }
        if (!headers_sent()) {
            // redirect
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo($msg);
            }
            exit();
        } else {
            $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            if ($time != 0) {
                $str .= $msg;
            }
            exit($str);
        }
    }


    //验证 csrf
    protected $_csrfToken;
    //为本会话生成新的 csrf token
    protected function _getCsrfToken(): string {

        //是否已经存在 token
        $cookieCsrfToken = trim($_COOKIE['_csrf_token']);

        if ($cookieCsrfToken) {
            return $cookieCsrfToken;
        } else {
            //生成新token
            $csrfToekn = dechex(mt_rand(0x000000, 0xFFFFFF));
//            $csrfToekn = md5(uniqid(mt_rand()));
            setcookie('_csrf_token', $csrfToekn, NULL, '/');

            return $csrfToekn;
        }
    }

    /**
     * 获取 form 表单中的所有数据
     * @return array | false
     */
    protected function _getFormData(string $key = 'data'): string {

        $data = $_GET['data'] ?: $_POST['data'];
        //将 javascript serialize
        parse_str($data, $value);

        return $value[$key] ?? NULL;
    }

    /**
     * 获取服务
     */
    protected function getService(string $serviceName): Service{
        return $this->getSingleInstance('\Service\\' . ucfirst($serviceName));
    }

    /**
     * 载入model类
     *
     * @param string $mname model类名称,如果是空字符串,则需要有table参数,以产生一个model基类,对指定table做操作,如果mname非空,通常table和pre都应该为null
     * @param string $table 对应的数据表名称
     * @param string $pre   对应的数据表前置
     * @param string $dbtag 对应的数据库配置标签
     *
     * @return object
     */
    public function loadM(string $mname, string $table = '', string $pre = '', string $dbtag = DEFAULT_DB): object {
        static $models = array();
        $mname = ucfirst($mname);
        if ($mname === '') {
            ex($mname . ' Model is not found');
        }
        if (isset($models[$mname])) {
            if (!$this->$mname) {
                $this->$mname = $models[$mname];
            }

            return $models[$mname];
        }

        $class = '\Models\\' . $mname;
        $model = new $class($table, $pre, $dbtag);
        $this->$mname = $model;
        $models[$mname] = $model;

        return $model;
    }

    //给模板赋值
    public function assign($data, $value): void {

        $this->view->assign($data, $value);
    }

    //渲染页面或者  json
    public function display(string $tpl = '', bool $hasHeaderFooter = TRUE): void {

        $this->view->display($tpl ?: CONTROLLER_NAME . DS . ACTION_NAME, $hasHeaderFooter);
    }


    //解密 api
    public static function encodeAjax(array $arr = []): string {

        //aes or rsa
        if (parent::$config['config']['api_key'] || parent::$config['config']['rsa_sign']) {

            //需要加密 没有 csrf_token
            if (!$_REQUEST['_csrf_token']) {

                //aes 加密输出结果
                if (parent::$config['config']['api_key']) {
                    return crypt_encode($arr, parent::$config['config']['api_key']);
                }
                //附加 rsa 签名
                if (parent::$config['config']['rsa_sign'] && defined('SIGN_KEY')) {
                    $arr['_sign'] = RsaModel::sign($arr, SIGN_KEY);
                    //密钥平台标识
                    if (TRUE == APP_DEBUG) {
                        $arr['_sk'] = SIGN_KEY;
                    }
                }
            }
        }

        return json_encode($arr);
    }


}
