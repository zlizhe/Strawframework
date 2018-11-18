<?php
namespace Strawframework\Base;
/**
 * User: Zack Lee
 * Date: 2018/11/18
 * Time: 14:28
 */

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
            return parent::__construct($msgKeyAndValue, $this->code . '00', null);
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
        $langPath = PROTECTED_PATH . 'Lang' . DS . VERSION_NAME . DS . 'zh_CN' . DS . ucfirst($lang) . '.php';
        $errorMsg = require_once ($langPath);
        if (empty($errorMsg))
            throw new \Exception(sprintf('Error lang not found in %s.', $langPath));

        if (!$msgKey)
            throw new \Exception(sprintf('Throw error key %s not found.', $msgKey));

        //内容
        $msgText = $errorMsg[$msgKey];
        $code = $this->code . $this->errorCode[$msgKey];

        //设置占位内容
        if (!empty($msgList))
            $msgText = sprintf($msgText, implode(',', $msgList));

        return parent::__construct($msgText, $code, null);
    }

}