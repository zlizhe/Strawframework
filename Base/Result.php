<?php
namespace Strawframework\Base;
use Strawframework\Common\Funs;
use Strawframework\Straw;

/**
 * User: Zack Lee
 * Date: 2018/11/18
 * Time: 9:34
 */



class Result implements \Strawframework\Protocol\Result {

    //返回类型
    const CONTENT_TYPES = ['json', 'xml', 'html', 'jsonp'];

    //输出类型
    private $contentType = 'application/json';

    /**
     * 输出头
     */
    private function getHeader(){

        header(sprintf('Content-type:%s;charset=UTF-8', $this->contentType));
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    /**
     * 输出消息
     * @var array
     */
    private $res;

    /**
     * 返回
     * Result constructor.
     *
     * @param int         $code
     * @param null|string $msg
     * @param array       $res
     *
     * @throws \Exception
     */
    public function __construct(int $code, ? string $msg = null, ? array $res = [], $doReturn = false) {

        Straw::$config['output_type'] = strtolower(Straw::$config['output_type']);
        if (!in_array(Straw::$config['output_type'], self::CONTENT_TYPES))
            die(sprintf("Output type %s invalid.", Straw::$config['output_type']));

        http_response_code($code); //send http code

        $reRes = [];
        if (null != $msg)
            $reRes['msg'] = $msg;

        if (!empty($res))
            $reRes['data'] = $res;

        $this->res = $reRes;

        if (true == $doReturn)
            return $this;

        return $this->{'to' . ucfirst(Straw::$config['output_type'])}();
    }

    /**
     * 输出 json
     * @param $res
     */
    public function toJson(){
        $this->contentType = 'application/json';
        $this->getHeader();
        echo json_encode($this->res);
        exit();
    }

    /**
     * 输出  xml
     * @param $res
     */
    public function toXml(){
        $this->contentType = 'application/xml';
        $this->getHeader();
        echo Funs::encodeXml($this->res, 'UTF-8');
        exit();
    }

    /**
     * 输出 jsonp
     */
    public function toJsonp($fromParam = 'callback'){
        $fun = RequestObject::$call[$fromParam] ?: 'callback';
        $this->contentType = 'application/javascript';
        $this->getHeader();
        echo $fun . '(' . json_encode($this->res) . ');';
        exit();
    }

    //文件下载类型

    //JSONP 返回

    /**
     * 输出html
     * @param $res
     */
    public function toHtml(){
        $this->contentType = 'text/html';
        $this->getHeader();
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<table border="0" width="500" cellspacing="1" bgcolor="lightgrey" style="text-align:center;margin:0 auto;word-wrap: break-word;"><tr><td bgcolor="#5bb6ff"><b style="color:white;">'.Straw::$config['site_name'].'</b></td></tr>';
        echo '<tr><td bgcolor="">';
        echo $this->res['msg'];
        echo "</td></tr>";
        if (!empty($this->res['data'])){
            echo "<tr><td bgcolor='#f6f6f6' style='padding:10px;text-align: left;'>";
            //echo "<hr />";
            foreach ($this->res['data'] as $k => $v) {
                echo $k." - ";
                echo json_encode($v)."<br/>";
            }
            //echo "<pre>";
            //var_export($res);
            //echo json_encode($res, JSON_UNESCAPED_UNICODE);
            //echo "</pre>";
            echo "</td>";
            echo '</tr>';
        }
        if ($this->res['data']['error_code']){
            echo '<tr><td bgcolor="#afc6ff"><b style="color:white;">Error Code: '.$this->res['data']['error_code'].'</b></td></tr>';
        }
        echo '</table>';
        exit();
    }
}