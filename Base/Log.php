<?php
namespace Strawframework\Base;
use Strawframework\Straw;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
/**
 * User: xiuhao
 * Date: 2018/11/20
 * Time: 14:01
 * 日志基类
 */
class Log {
    static public $instance;
    private  $logger;

    private function __construct(){
        $this->logger = new Logger('mylog');
    }

    static public function getInstance(){
        if (!self::$instance instanceof self) {
             self::$instance = new self();
         }
        return self::$instance;
    }

    public function info($msg, $context){
        $logger = $this->logger;
        $log = $this->getConfig('INFO');

        if ($log && $log['type'] == 'FILE'){
            $path = $log['config']['saveSrc'].'my_app.log';
        }
//        $formatter = $log['config']['formatter'];
        $stream_handler = new StreamHandler($path, Logger::INFO); // 过滤级别
        $stream_handler->setFormatter(new LineFormatter());

        $logger->pushHandler($stream_handler);

//        return $logger->info($msg);
        return $logger->addInfo($msg, $context);
    }

    public function getConfig($level){
        switch (strtoupper($level)) {
            case "INFO":
                $type = Straw::$config['log']['level']['INFO'];
                break;
            case "WARNING":
                $type = Straw::$config['log']['level']['WARNING'];
                break;
            default:
        }
        return ['type'  => $type,
                'config'=> Straw::$config['log']['type'][$type]
                ];
    }

    public static function warning($msg){
        return 'warning';
    }
}