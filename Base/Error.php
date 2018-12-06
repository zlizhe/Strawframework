<?php
namespace Strawframework\Base;

use Strawframework\Common\Funs;

/**
 * 异常处理
 * Class Error
 * @package Strawframework\Base
 */
class Error extends \Exception {

    //当前错误类错误码 最终错误码 当前类错误码+ERRORCODE 例 1001
    protected $code = '10';


    /**
     * Error constructor.
     *
     * @param string|array      $msgKeyAndValue
     * @param null|string $lang
     *
     * @throws \Exception
     */
    public function __construct($msgKeyAndValue, ? string $lang = null) {

        //不使用语言包
        if (!$lang){
            return parent::__construct($msgKeyAndValue, RequestObject::convert($this->code . '00', 'int'), null);
        }
        //设置 占位符 与 占位消息
        $msgKey = '';
        $msgList = [];
        foreach ($msgKeyAndValue as $k => $v) {
            if (0 == $k)
                $msgKey = $v;
            else{
                $msgList[] = trim($v);
            }
        }
        //加载语言包
        $sysLangPath = PROTECTED_PATH . 'Lang' . DS . VERSION_NAME . DS . SYS_LANG . DS . ucfirst($lang) . '.php';
        $langPath = '';
        if (file_exists($sysLangPath)){
            $langPath = $sysLangPath;
        }
        $defaultLangPath = PROTECTED_PATH . 'Lang' . DS . VERSION_NAME . DS . DEFAULT_LANG . DS . ucfirst($lang) . '.php';
        if (file_exists($defaultLangPath)){
            $langPath = $defaultLangPath;
        }
        $errorMsg = @include ($langPath);
        if (FALSE == $errorMsg)
            throw new \Exception('Error config file not found or system language file not found.');
        if (empty($errorMsg))
            throw new \Exception(sprintf('Error lang not found in %s.', $langPath));
        if (!$msgKey)
            throw new \Exception(sprintf('Throw error key %s not found.', $msgKey));

        //内容
        $msgText = $errorMsg[$msgKey];
        $code = $this->code . $this->errorCode[$msgKey];

        //设置占位内容
        if (!empty($msgList))
            $msgText = vsprintf($msgText, $msgList);

        return parent::__construct($msgText, RequestObject::convert($code, 'int'), null);
    }

}