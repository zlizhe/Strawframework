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


/**
 * 压缩html : 清除换行符,清除製表符,去掉注释标记
 *
 * @param $string
 *
 * @return 压缩后的$string
 * */
function compress_html($string) {
    $string = str_replace("\r\n", '', $string); //清除换行符
    $string = str_replace("\n", '', $string); //清除换行符
    $string = str_replace("\t", '', $string); //清除製表符
    $pattern = array("/> *([^ ]*) *</", //去掉注释标记
                     "/[\s]+/", "/<!--[^!]*-->/", "/\" /", "/ \"/", "'/\*[^*]*\*/'");
    $replace = array(">\\1<", " ", "", "\"", "\"", "");

    return preg_replace($pattern, $replace, $string);
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


//根据 colums 对 inputArr 对应栏目做 类型转换
function convertArrTo($inputArr, $colums, $fileter = 'trim') {

    foreach ($inputArr as $key => $value) {
        if ($colums[$key]) {
            if (is_array($value) && 'array' != $colums[$key]) {
                // 值是数组 继续查找 数组内元素是否需要转换
                $inputArr[$key] = convertArrTo($value, $colums[$key], $fileter);
            } else {
                $inputArr[$key] = convertTo($colums[$key], $value, $fileter);
            }
        } else {
            //没有找到需要转换的类型，不转换该值
            $inputArr[$key] = $value;
        }
    }

    return $inputArr;
}

//强制转换类型 并过滤数据
function convertTo($type, $data, $fileter = 'trim') {
    if (!in_array($type, ['int', 'string', 'array', 'double', 'bool'])) {
        return FALSE;
    }

    $fun = 'conv2' . $type;

    return $fun($data, $fileter);
}

//强制转 double
function conv2double($data, $filter = 'trim') {
    if (is_double($data)) {
        return $data;
    }

    return (double)filterData($data, $filter);
}

//强制转 int
function conv2int($data, $filter = 'trim') {
    if (is_int($data)) {
        return $data;
    }

    return (int)filterData($data, $filter);
}

//强制转 string
function conv2string($data, $filter = 'trim') {
    if (is_string($data)) {
        return $data;
    }

    return (string)filterData($data, $filter);
}

//强制转 array
function conv2array($data, $filter) {
    if (is_array($data)) {
        return $data;
    }

    return (array)$data;
}

//转 bool
function conv2bool($data, $filter) {
    if (is_bool($data)) {
        return $data;
    }

    return (bool)$data;
}

//转 objectid
function conv2objectid($data) {
    return new \MongoDB\BSON\ObjectId($data);
}

//通过 . 获取扩展名称
function getExt($string) {
    return strtolower(end(explode('.', $string)));
}

//通过扩展名或者文件类型
function ext2FileType($ext) {

    $typeArr = [
        'doc'  => 'doc',
        'docx' => 'doc',
        'xls'  => 'doc',
        'xlsx' => 'doc',
        'ppt'  => 'doc',
        'pptx' => 'doc',
        'pdf'  => 'doc',
        'mp4'  => 'video',
        'avi'  => 'video',
        'mkv'  => 'video',
        'mpeg' => 'video',
        'rm'   => 'video',
        'rmvb' => 'video',
        '3gp'  => 'video',
        'mpg'  => 'video',
        'mov'  => 'video',
        'mts'  => 'video',
        'wmv'  => 'video',
        'mp3'  => 'audio',
        'jpg'  => 'image',
        'jpeg' => 'image',
        'jpe'  => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'bmp'  => 'image'
    ];

    return strtolower($typeArr[$ext]) ?: 'other';
}

/**
 * 过滤数据
 *
 * @param $data
 * @param $filter
 */
function filterData($data, $filter) {
    if (is_array($filter)) {
        foreach ($filter as $value) {
            $data = $value($data);
        }
    } else {
        $data = $filter($data);
    }

    return $data;
}


//截取中文 UFT8使用
function cutstr($string, $length, $dot = ' ...') {
    if (strlen($string) <= $length) {
        return $string;
    }

    $pre = chr(1);
    $end = chr(1);
    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array($pre . '&' . $end, $pre . '"' . $end, $pre . '<' . $end, $pre . '>' . $end), $string);

    $strcut = '';


    $n = $tn = $noc = 0;
    while ($n < strlen($string)) {

        $t = ord($string[$n]);
        if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1;
            $n++;
            $noc++;
        } elseif (194 <= $t && $t <= 223) {
            $tn = 2;
            $n += 2;
            $noc += 2;
        } elseif (224 <= $t && $t <= 239) {
            $tn = 3;
            $n += 3;
            $noc += 2;
        } elseif (240 <= $t && $t <= 247) {
            $tn = 4;
            $n += 4;
            $noc += 2;
        } elseif (248 <= $t && $t <= 251) {
            $tn = 5;
            $n += 5;
            $noc += 2;
        } elseif ($t == 252 || $t == 253) {
            $tn = 6;
            $n += 6;
            $noc += 2;
        } else {
            $n++;
        }

        if ($noc >= $length) {
            break;
        }

    }
    if ($noc > $length) {
        $n -= $tn;
    }

    $strcut = substr($string, 0, $n);


    $strcut = str_replace(array($pre . '&' . $end, $pre . '"' . $end, $pre . '<' . $end, $pre . '>' . $end), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);

    $pos = strrpos($strcut, chr(1));
    if ($pos !== FALSE) {
        $strcut = substr($strcut, 0, $pos);
    }

    return $strcut . $dot;
}


//创建文件夹
function creatDir($dir) {
    if (!file_exists($dir)) {
        $isdir = mkdir($dir, 0777, TRUE);
        @chmod($dir, 511);//0777 八进制
    }

    return $isdir;
}

/**
 * 无限分类 树状格式化
 *
 * @param  [type] $data [description]
 *
 * @return [type]       [description]
 */
function formartTree($data, $value = 'value', $upid = 'up_id', $id = 'id') {

    $tree = new vendors\FormartTree();
    $tree->value = $value;
    $tree->upid = $upid;
    $tree->id = $id;
    $tree->tree($data);
    $results = $tree->getArray();

    //    print_r($results);die;
    return $results;
}

/**
 * 汉字转拼音
 *
 * @param $word
 *
 * @return string
 */
function word2py($word) {

    $py = new vendors\Word2Py();
    $wordArr = foo($word);

    $res = '';
    foreach ($wordArr as $value) {
        $res .= $py->Pinyin($value, 'UTF8');
    }

    return $res;
}


/**
 * 中文切单字
 *
 * @param $str
 *
 * @return array
 */
function chinese2simple($str) {
    $array = array();
    if (!$str) {
        return FALSE;
    }
    $len = strlen($str);

    $a = chr(0xC0);
    $b = chr(0x80);
    $i = 0;
    $t = $str[$i];
    while ((++$i) < $len) {
        if (($str[$i] & $a) !== $b) {
            $array[] = $t;
            $t = $str[$i];
        } else {
            $t .= $str[$i];
        }
    }
    $array[] = $t;

    return $array;
}


//BBCODE TO HTML
function bb2html($text) {
    $bbcode = array(
        "/\[i\](.*?)\[\/i\]/is",
        "/\[b\](.*?)\[\/b\]/is",
        "/\[u\](.*?)\[\/u\]/is",
        "/\[img\](.*?)\[\/img\]/is",
        //"/\[url=(.*?)\](.*?)\[\/url\]/is",
        "/\[size=(.*?)\](.*?)\[\/size\]/is",
        "/\[size=(.*?)\](.*?)\[\/size\]/is",
        "/\[color=(.*?)\](.*?)\[\/color\]/is",
        "/\[color=(.*?)\](.*?)\[\/color\]/is",
        "/\[font=(.*?)\](.*?)\[\/font\]/is",
        //"/\\r\n/is",
    );
    $html = array(
        "<i>$1</i>",
        "<b>$1</b>",
        "<u>$1</u>",
        "<img src=\"$1\" />",
        //"<a href=\"".SITE_URL."/index/go?url=$1\" target=\"_blank\">$2</a>",
        '<font size=$1>$2</font>',
        '<font size=$1>$2</font>',
        '<font color=$1>$2</font>',
        '<font color=$1>$2</font>',
        '<font face=$1>$2</font>',
        //'<br />',
    );
    $newtext = nl2br(preg_replace($bbcode, $html, $text));

    return $newtext;
}


//时间 人性化处理
function humDate($date, $type = 'Y-m-d H:i') {
    //return date($type, $date);
    //分钟
    $second = date('YmdHi', $date);
    $nsecond = date('YmdHi', getTime());
    //60分钟内
    $difSecond = $nsecond - $second;
    if ($difSecond < 2) {
        return "刚刚";
    }
    if ($difSecond < 60) {
        return $difSecond . " 分钟前";
    }

    //小时
    $hour = date('YmdH', $date);
    $nhour = date('YmdH', getTime());
    //24小时内
    $difHour = $nhour - $hour;
    if ($difHour < 24) {
        return $difHour . " 小时前";
    }

    //天
    $day = date('Ymd', $date);
    $nday = date('Ymd', getTime());
    //15天内
    $difDay = $nday - $day;
    if ($difDay < 15) {
        return $difDay . " 天前";
    }

    //返回格式
    return date($type, $date);

}

/**
 * 获取客户端IP地址
 *
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv  是否进行高级模式获取（有可能被伪装）
 *
 * @return mixed
 */
function getClientIp($type = 0, $adv = FALSE) {
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) {
        return $ip[$type];
    }
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (FALSE !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);

    return $ip[$type];
}

/**
 * 通过IP取地区
 *
 * @param  [type] $ip [description]
 *
 * @return [type]     [description]
 */
function getLocation($getip) {
    if ($getip == '127.0.0.1') {
        return '火星';
    }
    $ip = new vendors\IpLocation(); // 实例化类 参数表示IP地址库文件
    $area = $ip->getlocation($getip); // 获取某个IP地址所在的位置
    //    print_r($area);die;
    return $area['country'] . ' ' . $area['area'];
}

//多页码分页
function html_multi($page, $howpage, $url = "", $adjacents = 4) {

    $repage = "<li><a href=\"" . $url . ($page - 1) . "\">&laquo;</a></li>";
    $nxpage = "<li><a href=\"" . $url . ($page + 1) . "\">下一页 &raquo;</a></li>";
    if ($page <= 1) {
        $repage = "";
    }
    if ($page >= $howpage) {
        $nxpage = "";
    }
    if ($page > ($adjacents + 1)) {
        $first = "<li><a href=\"" . $url . '1' . "\">1 ...</a></li>";
    } else {
        $first = '';
    }
    if ($page < ($howpage - $adjacents)) {
        $last = "<li><a href=\"" . $url . $howpage . "\">... $howpage</a></li>";
    } else {
        $last = '';
    }

    $multipage = "";
    $multipage = $first;
    $multipage .= $repage;

    //页数过多时
    /*if (($howpage - $page) >= 10){
        $howpage = 10;
    }*/
    for ($i = 1; $i <= $howpage; $i++) {  //页码
        if ($page == $i) {
            $multipage .= "<li class=\"active\"><a href=\"#\">$i</a></li>";
        } elseif ($page > ($i + $adjacents) || $page < ($i - $adjacents)) {

        } else {
            $multipage .= "<li><a href=\"" . $url . $i . "\">$i</a></li>";
        }
    }
    $multipage .= $last;
    $multipage .= $nxpage;

    return $multipage;
}


/**
 * 发送邮件
 *
 * @param  收件地址 $address [description]
 * @param  标题   $title   [description]
 * @param  内容   $content [description]
 * @param  发件人  $from    [description]
 *
 * @return [type]          [description]
 */
function sendMail($address, $title, $content, $from = NULL, $fromName = NULL) {
    //网站配置
    $setArr = getSiteSet();

    //发件人为空时
    $from = $from ?: $setArr['MAIL_USERNAME'];
    $fromName = $fromName ?: $setArr['SITE_NAME'];

    $mail = new SendMail();
    //配置文件修改
    $mail->setServer($setArr['MAIL_SERVER'], $setArr['MAIL_USERNAME'], $setArr['MAIL_PASSWORD']);
    $mail->setFrom($from, $fromName);
    $mail->setReceiver($address);
    $mail->setMailInfo($title, $content);
    $mail->sendMail();

    return TRUE;
}


//取页面REF
function getRef() {

    return rawurlencode($_SERVER["HTTP_HOST"]);
}

//验证 ref 是否存在于 avaiableModule
function validRef($siteDomain, $defaultDomain = '') {

    $ref = urldecode($_GET['ref'] ?: $_POST['ref']);

    if ($ref) {
        if (TRUE == validDomain($ref, $siteDomain)) {
            return $ref;
        }
    }

    return $defaultDomain ?: '';
}

//检查域是否合法
function validDomain($url, $domain) {
    if (is_array($domain)) {
        $domainArr = [];
        foreach ($domain as $value) {
            preg_match("/([^(http(s):)\/\/][0-9a-zA-Z\-\\_\.]+)/i", strtolower($value), $matches);
            preg_match("/[^\.\/]+\.[^\.\/]+$/", $matches[1], $matches);
            array_push($domainArr, $matches[0]);
        }
        unset($domain);
        $domain = $domainArr;
    } else {
        $domain = (array)$domain;
    }

    //主域名 包括无 // 有 http:// 有 https://
    preg_match("/([^(http(s):)\/\/][0-9a-zA-Z\-\\_\.]+)/i", strtolower($url), $matches);
//    print_r($url);
//    print_r($matches);die;
    //主域下最后的 . 根域
    preg_match("/[^\.\/]+\.[^\.\/]+$/", $matches[1], $matches);
    // like xwg.cc
//    print_r($matches);die;

    if (in_array($matches[0], $domain)) {
        return TRUE;
    } else {
        return FALSE;
    }
}

//清空缓存文件
function getRmCache($dirname = '', $root = '') {
    //文件夹
    if (!$dirname && $root) {
        $cache = getcwd() . '/../Application/' . $root;
        $dirname = $cache;
    }

    if (file_exists($dirname)) {//首先判断目录是否有效
        $dir = opendir($dirname);//用opendir打开目录
        while ($filename = readdir($dir)) {//使用readdir循环读取目录里的内容
            if ($filename != "." && $filename != "..") {//排除"."和".."这两个特殊的目录
                $file = $dirname . "/" . $filename;
                if (is_dir($file)) {//判断是否是目录，如果是则调用自身
                    getRmCache($file); //使用递归删除子目录
                } else {
                    @unlink($file);//删除文件
                }
            }
        }
        closedir($dir);//关闭文件操作句柄
        $isrm = rmdir($dirname);//删除目录
    }

    //结束了
    if ($dirname == $cache) {
        return TRUE;
    } else {
        return FALSE;
    }

    // //是否成功
    // if ($isrm) {
    //     return true;
    // }else{
    //     return false;
    // }

}

function clearApplicationS($dirname) {

    //echo $dirname;die;
    if (file_exists($dirname)) {//首先判断目录是否有效
        $dir = opendir($dirname);//用opendir打开目录
        while ($filename = readdir($dir)) {//使用readdir循环读取目录里的内容
            if ($filename != "." && $filename != "..") {//排除"."和".."这两个特殊的目录
                $file = $dirname . "/" . $filename;
                //print_r($file);die;
                if (is_dir($file)) {//判断是否是目录，如果是则调用自身
                    clearApplicationS($file); //使用递归删除子目录
                } else {
                    $files = glob($dirname . '/*.php');

                    //print_r($files);die;
                    foreach ($files as $value) {
                        //file_put_contents($value, strip_whitespace(file_get_contents($value)));
                        file_put_contents($value, php_strip_whitespace($value));
                    }
                }
            }
        }
        closedir($dir);//关闭文件操作句柄
    }
}

/**
 * 是否为手机访问
 * @return bool
 */
function isMobile() {
    $useragent = $_SERVER['HTTP_USER_AGENT'];
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) {
        return TRUE;
    } else {
        return FALSE;
    }
}


/**
 * curl get json
 *
 * @param string $url
 * @param string $method
 * @param string $data
 * @param array  $harr
 *
 * @return bool|mixed
 */
function getUrl($url = '', $method = "GET", $data = '', $harr = [], $timeout = 60) {
    if (is_array($data)) {
        $postdata = http_build_query($data);
    } else {
        $postdata = $data;
    }

    $ch = curl_init();
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    }
    if (strtoupper($method) == 'GET' && $data) {
        $url .= '?' . $postdata;
    } elseif (strtoupper($method) == 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }
    if (!empty($harr)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $harr);
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        ex($url, curl_errno($ch) . ' : ' . curl_error($ch) . ' [data] ' . $postdata, 511);

        return FALSE;
    } else {
        curl_close($ch);
    }

    //    var_dump($response);die;
    return $response;
}

//跳转
function redirect($url, $time = 0, $msg = '') {
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


function crypt_encode($val, string $key): string {
    if (is_array($val)) {
        $val = json_encode($val);
    }

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $key), 0, 16);

    return bin2hex(openssl_encrypt($val, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv));
}

function crypt_decode($val, $key) {
    $iv = substr(hash('sha256', $key), 0, 16);

    return openssl_decrypt(hex2bin($val), "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}


/**
 * 输出xml字符
 **/
function encodeXml($data, $encoding = 'UTF-8', $mainname = 'StrawFramework') {
    if (!is_array($data)
        || count($data) <= 0) {
        ex("数组数据异常！");
    }

    $xml = "<?xml version=\"1.0\" encoding=\"$encoding\" ?>";
    $xml .= "<$mainname>";
    foreach ($data as $key => $val) {
        if (is_numeric($val)) {
            $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
        } else {
            $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
        }
    }
    $xml .= "</$mainname>";

    return $xml;
}


/**
 * 将xml转为array
 *
 * @param string $xml
 */
function decodeXml($xml) {
    if (!$xml || !xml_parse(xml_parser_create(), $xml, TRUE)) {
        return FALSE;
    }
    //将XML转为array
    //禁止引用外部xml实体
    libxml_disable_entity_loader(TRUE);

    return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), TRUE);
}
