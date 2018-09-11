<?php

namespace strawframework\base;

use \strawframework\Straw;

/**
 *  base CONTROLLER  
 */
class Controller extends Straw {
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

    //快速 new  model
    public function __get(string $name): object {
        //包含 Model 名称 的变量 自动创建 Model 对象并返回
        if (stripos(strtolower($name), 'model')) {
            //创建 Model 对象
            return $this->loadM(str_ireplace('Model', '', $name));
        }
    }

    //验证 csrf
    protected $_csrfToken;

    public function __construct(bool $isView = TRUE) {
        parent::__construct();

        //当前程序版本
        $this->_Gver = version();

        //read from config
        $this->_availableModules = parent::$config['modules'];
        if (!$this->_availableModules) {
            ex('API available can not set');
        }

        //配置
        $this->_Gset['site_domain'] = parent::$config['config']['site_domain'];
        //本 module name
        $this->_Gset['module_name'] = parent::$config['config']['module_name'];


        //是否加载模板
        if (TRUE == $isView) {
            //实例化模板类
            $this->view = new \strawframework\base\View();

            $this->assign('availableModules', $this->_availableModules);

            //站点设置
            $this->assign("_Gset", $this->_Gset);
            //程序版本
            $this->assign('_Gver', $this->_Gver);
        }

        $this->_csrfToken = $this->_getCsrfToken();

        //验证 csrf_token
        if ($_REQUEST['_csrf_token'] && $_REQUEST['_csrf_token'] !== $this->_csrfToken) {
            ex('Access Denied');
        }

        //默认的证书
        //if (parent::$config['config']['sign_key'] && parent::$config['config']['rsa_sign']){
        //    define('SIGN_KEY', strtolower(parent::$config['config']['sign_key']));
        //}
    }

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
     * 从配置文件中 获取 Gset 相关 给 static
     */
    private function _getGset() {

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
     * 检查数组中的空信息
     * @return bool
     */
    protected function _emptyError(array $needArr, array $data, bool $moreError = FALSE): bool {
        if (!is_array($needArr)) {
            return FALSE;
        }

        if (!$data) {
            return FALSE;
        }

        //错误信息
        $error = '';
        foreach ($needArr as $key => $value) {
            if (empty($data[$value]) && '0' != $data[$value]) {
                $error .= $key . "不能为空<br/>";
                //不允许多个 error
                if ($moreError != FALSE) {
                    break;
                }
            }
        }

        return $error ? $this->_error($error) : TRUE;
    }

    /**
     * 操作成功 ajax
     *
     * @param string $msg
     */
    protected function _success(string $msg = '操作成功', array $data = [], string $ref = NULL): void {
        $returnData = [
            'code' => self::SUCCESS,
            'msg'  => $msg,
            'ref'  => $ref
        ];
        if ($data && is_array($data)) {
            $returnData = array_merge($data, $returnData);
        }
        $this->ajaxReturn($returnData);
    }

    /**
     * 错误信息 ajax
     *
     * @param string $msg
     * @param int    $code
     */
    protected function _error(string $msg = '一个错误发生了', int $code = self::FAIL): void {
        $this->ajaxReturn([
                              'code' => $code,
                              'msg'  => $msg
                          ]);
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

        $class = '\models\\' . $mname;
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

    /**
     * 直接显示页面
     *
     * @param $tpl
     */
    public function show(string $tpl, bool $return = FALSE): void {

        if (is_null($this->view)) {
            $this->view = new View();
        }

        $this->view->show($tpl ?: CONTROLLER_NAME . DS . ACTION_NAME, $return);
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

    //return json decode
    public function ajaxReturn(array $arr = []): void {

        //如果定义了可用的 ajax_return 使用该方法，否则使用 json
        if ('JSONP' == strtoupper($_REQUEST['_ajax_return'])) {

            echo ($_REQUEST['callback'] ?: 'callback') . '(' . json_encode($arr) . ');';
            exit();
        }
        //默认方法
        $data = self::encodeAjax($arr);
        echo $data;
        exit();
    }


    public static $purviewArr = [];

    /**
     * 获取该操作是否有权限
     *
     * @param $action
     *
     * @return int
     */
    public function isPurview(string $action = ''): bool {
        if (!$action) {
            $action .= $this->_Gset['module_name'];
            $action .= '/' . CONTROLLER_NAME . '/';
            $action .= $_GET[2] ? $_GET[2] : ACTION_NAME;
        }

        $re = FALSE;
        //获取登录信息
        $token = $_REQUEST['token'];
        if (!$token) {
            $this->_error('请登录后在试', self::NOT_LOGIN);
        }

        $userArr = json_decode(getUrl($this->_availableModules['passportapi'] . '/user/get_user_info', 'POST', ['token' => $token]), TRUE)['data'];

        if (!$userArr['gid']) {
            $this->_error('请登录后在试', self::NOT_LOGIN);
        }

        //获取登录者所有权限
        if (self::$purviewArr) {
            $purviewArr = self::$purviewArr;
        } else {
//            print_r(getUrl($this->_availableModules['passportapi'].'/group/get_via_gid', 'POST', ['gid' => $userArr['gid']]));die;
            $purviewArr = json_decode(getUrl($this->_availableModules['passportapi'] . '/group/get_via_gid', 'POST', ['gid' => $userArr['gid']]), TRUE)['fun'];
            self::$purviewArr = $purviewArr;
        }

        // 允许操作
        if (in_array(strtoupper(trim($action)), $purviewArr)) {
            return TRUE;
        } else {
            //不允许操作如何返回
            $this->_error('您没有权限执行本次操作', -11);
        }
    }

}
