<?php
namespace Strawframework\Base;

use Strawframework\Straw;

/**
 *  base CONTROLLER  
 */
class Controller extends Straw {

    /**
     * 当前参数列表
     * @var RequestObject
     */
    private $requests;

    /**
     * 模板 页面
     * @var View
     */
    private $view = NULL;

    public function __construct() {
        parent::__construct();
        //
        ////当前程序版本
        //$this->_Gver = '';//
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
     * @var Service
     */
    protected function getService(string $serviceName): Service{
        return $this->getSingleInstance('\\Service\\' . ucfirst($serviceName));
    }

    //给模板赋值
    public function assign($data, $value): void {
        if (!$this->view)
            $this->view = $this->getSingleInstance('\\Strawframework\\Base\\View');

        $this->view->assign($data, $value);
    }

    //渲染页面或者  json
    public function display(string $tpl = '', bool $hasHeaderFooter = TRUE): void {
        if (!$this->view)
            $this->view = $this->getSingleInstance('\\Strawframework\\Base\\View');

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
