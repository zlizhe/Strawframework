<?php
namespace Strawframework\Base;

use MongoDB\Client;
use Monolog\Formatter\ElasticsearchFormatter;
use Monolog\Formatter\MongoDBFormatter;
use Monolog\Handler\ElasticSearchHandler;
use Monolog\Handler\MongoDBHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SwiftMailerHandler;
use Strawframework\Straw;
use Monolog\Logger;
/**
 * User: xiuhao
 * Date: 2018/11/20
 * Time: 14:01
 * 日志基类
 */
class Log{
    static public $instance;
    private  $logger;
    private $type; //日志写入类型
    private $level; //日志等级

    private function __construct(){
        $this->logger = new Logger(Straw::$config['site_name']);
    }

    static public function getInstance(){
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 设置日志类型 实际上设置的是 这个 key 下面的 type
     *
     * 如配置
     * logs = [
     *  'file1' => ['type' => 'file']
     * ]
     * setType('file1') 则是指定使用 file1 来写日志 类型是 file
     */
    public function setType($type){
        $this->type[] = strtolower($type);
        return $this;
    }

    /**
     * 从上到下 错误级别依次提升
     * @param       $msg
     * @param mixed ...$content
     *
     * @return mixed
     */
    public function debug($msg, ...$content){
        $this->level = 'debug';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function info($msg, ...$content){
        $this->level = 'info';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function notice($msg, ...$content){
        $this->level = 'notice';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function warning($msg, ...$content){
        $this->level = 'warning';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function error($msg, ...$content){
        $this->level = 'error';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function critical($msg, ...$content){
        $this->level = 'critical';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function alert($msg, ...$content){
        $this->level = 'alert';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    public function emergency($msg, ...$content){
        $this->level = 'emergency';
        return call_user_func_array([$this, 'set'], [$msg, $content]);
    }

    ////设置日志等级
    //public function setLevel($level){
    //    $this->level = strtolower($level);
    //    return $this;
    //}

    /**
     * Log::set('logtitle', 'msg1', 'msg2' , ['array1','arraykey' => 'value'], ['array2'])
     *
     * @param       $msg
     * @param mixed ...$context
     *
     * @return bool
     * @throws \Exception
     */
    private function set($msg, ...$context){
        //$logger = self::$container->{md5(__CLASS__)};
        //if (!$logger){
        //    $logger = new Logger(Straw::$config['site_name']);
        //    self::$container->{md5(__CLASS__)} = $logger;
        //}

        if (!$this->type)
            $this->type[] = strtoupper(Straw::$config['log']);

        //if (!$this->type)
        //    throw new \Exception('Default log type must be set.');

        //save type
        $this->getTypeConfig();

        //save level
        //if (!$this->level)
        //    $this->level = 'info';

        if (!method_exists($this->logger, $this->level))
            throw new \Exception(sprintf('Log level %s can not support.', $this->level));

        //总是包含的必要信息
        array_unshift($context, sprintf('%s /%s/%s/%s', $_SERVER['REQUEST_METHOD'], VERSION_NAME ?? null, CONTROLLER_NAME ?? null, ACTION_NAME ?? null));

        return call_user_func_array([$this->logger, $this->level], [$msg, $context]);
    }

    /**
     * 获取该日志类型的 句柄
     * @return mixed
     * @throws \Exception
     */
    private function getTypeConfig(): void{

        foreach ($this->type as $type) {
            $config = Straw::$config['logs'][$type];

            if (!$config)
                throw new \Exception(sprintf('Log config %s can not found.', $config['type']));

            $methodName = 'getType' . ucfirst(strtolower($config['type']));
            if (!method_exists($this, $methodName))
                throw new \Exception(sprintf('Can not support %s log type.', $config['type']));

            //默认 Line
            if (!$config['formatter'])
                $config['formatter'] = 'Line';

            $formatterCls = sprintf('\\Monolog\\Formatter\\%sFormatter', ucfirst(strtolower($config['formatter'])));
            if (!class_exists($formatterCls))
                throw new \Exception(sprintf('Log formatter %s can not support.', $config['formatter']));
            $this->logger->pushHandler(call_user_func([$this, $methodName], $config, $formatterCls));
        }
    }

    /**
     * 写文件日志
     * @param $config
     *
     * @return RotatingFileHandler
     */
    private function getTypeFile($config, $formatter){

        $handler = new RotatingFileHandler($config['saveSrc'], $config['maxFiles'] ?? 30, True == APP_DEBUG ? Logger::DEBUG : Logger::INFO);
        $handler->setFormatter(new $formatter());
        $handler->setFilenameFormat('{date}' . $config['fileName'], $config['srcFormat']);
        return $handler;
    }

    /**
     * 发邮件日志内容
     * @param $config
     * @param $formatter
     *
     * @return SwiftMailerHandler
     */
    private function getTypeEmail($config, $formatter){

        $transport = (new \Swift_SmtpTransport($config['smtpHost'], $config['smtpPort']))
            ->setUsername($config['sender'])
            ->setPassword($config['password'])
        ;

        $message = (new \Swift_Message($config['subject']))
            ->setFrom([$config['sender'] => $config['senderName']])
            ->setTo($config['receiver'])
        ;
        $handler = new SwiftMailerHandler(new \Swift_Mailer($transport), $message, Logger::ERROR);
        $handler->setFormatter(new $formatter());
        return $handler;
    }

    /**
     * 写入 mongodb
     * @param $config
     * @param $formatter
     *
     * @return MongoDBHandler
     */
    private function getTypeMongodb($config, $formatter){

        $handler = new MongoDBHandler(new Client($config['mongoConnect']), $config['dbName'], $config['collectionName'], True == APP_DEBUG ? Logger::DEBUG : Logger::INFO);
        $handler->setFormatter(new MongoDBFormatter()); //不允许指定
        return $handler;
    }

    private function getTypeElastic($config, $formatter){

        $client = \Elasticsearch\ClientBuilder::create()->setHosts(is_array($config['host']) ? $config['host'] : [$config['host']])->build();
        $handler = new ElasticSearchHandler($client, [], True == APP_DEBUG ? Logger::DEBUG : Logger::INFO);
        $handler->setFormatter(new ElasticsearchFormatter());
        //$handler->setFormatter(new ElasticaFormatter());
        return $handler;
    }
}