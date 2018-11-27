<?php
namespace Strawframework\Common;
use Strawframework\Base\Error;


/**
 * 操作 Strawframework 的 常用方法
 * Class Funs
 * @package Strawframework\Common
 */
class Funs {
    private static $instance;

    /**
     * 获取
     */
    public static function getInstance(){
        if (!self::$instance)
            self::$instance = new self();

        return self::$instance;
    }

    /**
     * curl get data
     * @param string $url
     * @param string $method
     * @param string $data
     * @param array  $harr
     * @param int    $timeout
     *
     * @return string
     * @throws Error
     */
    public function getUrl(string $url = '', string $method = "GET", $data = '', array $harr = [], $timeout = 60): string {
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
            throw new Error($url . curl_errno($ch) . ' : ' . curl_error($ch) . ' [data] ' . $postdata);
        } else {
            curl_close($ch);
        }

        //    var_dump($response);die;
        return $response;
    }


    /**
     * openssl 加密
     * @param        $val
     * @param string $key
     *
     * @return string
     */
    public function crypt_encode($val, string $key): string {
        if (is_array($val)) {
            $val = json_encode($val);
        }

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $key), 0, 16);

        return bin2hex(openssl_encrypt($val, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv));
    }

    /**
     * openssl 解密
     * @param string $val
     * @param string $key
     *
     * @return string
     */
    public function crypt_decode(string $val, string $key): string {
        $iv = substr(hash('sha256', $key), 0, 16);

        return openssl_decrypt(hex2bin($val), "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    }


    /**
     * 构建 xml 子类
     * @param $data
     *
     * @return string
     */
    private function encodeXmlChild($data){
        $xml = '';
        foreach ($data as $key => $val) {
            if (is_array($val)){
                $xml .= "<" . $key . ">" . $this->encodeXmlChild($val) . "</" . $key . ">";
            }else{
                if (is_numeric($val)) {
                    $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                } else {
                    $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                }
            }
        }
        return $xml;
    }

    /**
     * 构建 xml
     * @param        $data
     * @param string $encoding
     * @param string $mainname
     *
     * @return string
     * @throws \Error
     */
    public function encodeXml($data, string $encoding = 'UTF-8', string $mainname = 'StrawFramework'): string {
        if (!is_array($data) || count($data) <= 0) {
            throw new \Error(sprintf('Data error %s', json_encode($data, JSON_UNESCAPED_UNICODE)));
        }

        $xml = "<?xml version=\"1.0\" encoding=\"$encoding\" ?>";
        $xml .= "<$mainname>";
        $xml .= $this->encodeXmlChild($data);
        $xml .= "</$mainname>";

        return $xml;
    }


    /**
     * 解析 xml to array
     * @param string $xml
     *
     * @return bool|mixed
     */
    public function decodeXml(string $xml) {
        if (!$xml || !xml_parse(xml_parser_create(), $xml, TRUE)) {
            return FALSE;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(TRUE);

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), TRUE);
    }


    /**
     * 时间人性化显示
     * @param string $date
     * @param string $type
     *
     * @return false|string
     */
    public function humDate(string $date, string $type = 'Y-m-d H:i') {
        //return date($type, $date);
        //分钟
        $second = date('YmdHi', $date);
        $nsecond = date('YmdHi', time());
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
        $nhour = date('YmdH', time());
        //24小时内
        $difHour = $nhour - $hour;
        if ($difHour < 24) {
            return $difHour . " 小时前";
        }

        //天
        $day = date('Ymd', $date);
        $nday = date('Ymd', time());
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
     * @param int $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param bool $adv  是否进行高级模式获取（有可能被伪装）
     *
     * @return mixed
     */
    public function clientIp(int $type = 0, bool $adv = FALSE): string {
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
     * 通过 IP 获取地区/国家信息
     * @param string $getIp
     *
     * @return string
     */
    public function ip2Location(string $getIp): string {
        if ($getIp == '127.0.0.1') {
            return '火星';
        }
        $ip = new vendors\IpLocation(); // 实例化类 参数表示IP地址库文件
        $area = $ip->getlocation($getIp); // 获取某个IP地址所在的位置
        //    print_r($area);die;
        return $area['country'] . ' ' . $area['area'];
    }
}