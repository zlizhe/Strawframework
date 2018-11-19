<?php
// Functions Ver 0.24
/**
 * 公用 function
 * User: zl
 * Date: 2015/11/10
 * Time: 15:38
 */


//系统执行时间
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());

    return ((float)$usec + (float)$sec);
}

// 系统执行时间
function microtime_run() {
    $StartTime = (empty($GLOBALS['StartTime'])) ? microtime_float() : $GLOBALS['StartTime'];
    $EndTime = microtime_float();
    $RunTime = $EndTime - $StartTime;

    return $RunTime;
}



//异常
function ex(string $message, ?string $debugInfo = '', ?string $humanShow = '404 Not Found'): void {

    //记录日志至 db
    //\Home\Model\LogModel::setOp(['msg' => $message, 'info' => $debugInfo], 'User Error', \Home\Model\LogModel::OP_SYSERROR, '-', \Home\Controller\IndexController::$ccid, \Home\Model\LogModel::OP_ERROR);
    if (false == APP_DEBUG && 'DB ERROR' == trim(strtoupper($humanShow))) {
        if (!$_SESSION['restart_db']) {
            //尝试重启
            //exec("sudo /usr/bin/service mongod restart", $output);
            @exec("/usr/bin/sudo /usr/bin/service mongod restart", $output);
            echo json_encode($output, JSON_UNESCAPED_UNICODE);
            $_SESSION['restart_db'] = 1;
            redirect('/', 5);
        }
    }

    //记录日志至文件
    setLog($message . ' [Error] ' . $debugInfo);
    if (TRUE == APP_DEBUG) {
        // if (!IS_GET) {
        //     echo \strawframework\base\Controller::encodeAjax([
        //                                     'code' => $humanShow,
        //                                     'msg'  => $message,
        //                                     'info' => $debugInfo
        //                                 ]);
        //     exit();
        // }
        //header("HTTP/1.1 " . $humanShow);
        // $message = $message.x
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<table border="0" width="500" cellspacing="1" bgcolor="red" style="text-align:center;margin:0 auto;"><tr><td bgcolor="red"><b style="color:white;">STRAW WARNING !</b></td></tr><tr><td bgcolor="#cccccc" style="padding:10px">';
        echo $message;
        echo "<pre>";
        echo nl2br($debugInfo);
        echo "</pre>";
        echo '</td></tr></table>';
    } else {
        // if (!IS_GET) {
        //     echo \strawframework\base\Controller::encodeAjax([
        //                                     'code' => $humanShow,
        //                                     'msg'  => $message,
        //                                 ]);
        //     exit();
        // }
        //header("HTTP/1.1 " . $humanShow);
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<table border="0" width="500" cellspacing="1" bgcolor="orange" style="text-align:center;margin:0 auto;"><tr><td bgcolor="orange"><b style="color:white;">服务器遇到了一个错误 !</b></td></tr><tr><td bgcolor="#f5f5f5" style="padding:10px">';
        echo '<h1>' . $humanShow . '</h1>';
        echo $message;
        echo '</td></tr></table>';
    }
    exit;
}


function setLog(...$logs) {
    return true;
    //path url / to -
    $data = sprintf('[%s/%s] -- %s -- [Info] ', CONTROLLER_NAME, ACTION_NAME, date("Y/m/d H:i:s"));

    if ($GLOBALS['_log_name']) {
        $data .= sprintf(' -- [%s]', $GLOBALS['_log_name']);
    }
    foreach ($logs as $log) {
        if (is_array($log)) {
            $log = json_encode($log, JSON_UNESCAPED_UNICODE);
        }
        $data .= ' - ' . $log;
    }

    $r = $_REQUEST;
    unset($r['_sign']);
    $data .= ' -- [Request] ' . json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    @file_put_contents(LOGS_PATH . date('Ymd') . '.log', $data, FILE_APPEND);

    return TRUE;
}



