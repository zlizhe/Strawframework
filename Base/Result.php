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
    const CONTENT_TYPES = ['json', 'xml', 'html'];

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
    public function __construct(int $code, ? string $msg = null, array $res = []) {

        Straw::$config['output_type'] = strtolower(Straw::$config['output_type']);
        if (!in_array(Straw::$config['output_type'], self::CONTENT_TYPES))
            die(sprintf("Output type %s invalid.", Straw::$config['output_type']));


        header(sprintf('Content-type:%s/%s;charset=utf-8', Straw::$config['output_type'] == 'html' ? 'text' : 'application', Straw::$config['output_type']));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        http_response_code($code); //send http code

        if (null != $msg)
            $res['msg'] = $msg;

        if ('html' == Straw::$config['output_type'])
            return $this->getHtmlReturn($code, $res);

        if ('json' == Straw::$config['output_type']){
            echo json_encode($res);
        }
        if ('xml' == Straw::$config['output_type']){
            echo Funs::getInstance()->encodeXml($res, 'UTF-8');
        }
        exit();
    }

    //文件下载类型

    //JSONP 返回

    /**
     * txt 返回
     */
    private function getHtmlReturn($code, $res){
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<table border="0" width="500" cellspacing="1" bgcolor="lightgrey" style="text-align:center;margin:0 auto;word-wrap: break-word;"><tr><td bgcolor="#5bb6ff"><b style="color:white;">'.$res['msg'].'</b></td></tr>';
        //echo $res['msg'];
        //echo "</td></tr>";
        unset($res['msg']);
        if (!empty($res)){
            echo "<tr><td bgcolor='#f6f6f6' style='padding:10px;text-align: left;'>";
            //echo "<hr />";
            foreach ($res as $k => $v) {
                echo $k." - ";
                echo json_encode($v)."<br/>";
            }
            //echo "<pre>";
            //var_export($res);
            //echo json_encode($res, JSON_UNESCAPED_UNICODE);
            //echo "</pre>";
            echo "</td>";
        }
        echo '</tr>';
        echo '<tr><td bgcolor="#afc6ff"><b style="color:white;">'.$code.'</b></td></tr>';
        echo '</table>';
        exit();
    }
}