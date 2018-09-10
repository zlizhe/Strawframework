<?php
namespace controllers;
use \strawframework\base\Controller;
/**
 * AGA 使用 AGA 自动生成一个 API模块
 * Class AgaController
 */
class Agamodule extends Controller{

    public function __construct(){

        //加载模板
        parent::__construct($isView=true);

        //仅本机可用
        if ('127.0.0.1' !== getClientIp() || !APP_DEBUG){
            ex('AGA 模块仅限开发环境使用, 并确保 APP_DEBUG 为 true');
        }
    }

    // aga 版本
    public $ver = '0.5';

    /**
     *  默认首页
     */
    public function index(){
        if (!is_writable(CONTROLLERS_PATH)){
            $this->assign('controllerNotWrite', '1');
        }
        if (!is_writable(MODELS_PATH)){
            $this->assign('modelNotWrite', '1');
        }

        $this->assign('dbName', parent::$config['db'][DEFAULT_DB]['DB_NAME']);
        $this->display('', false);
    }


    private $dbtag;
    //成功信息
    private $successMsg = '';
    public function newModule(){

        $name = trim($_POST['name']);
        $dbtag = trim($_POST['dbtag']);
        $tables = trim($_POST['tables']);

        $name = str_ireplace('controller', '', $name);
        if (!$name && !$tables)
            ex('API名称 或者 数据表名称至少填写一个');
//        if (!$name)
//            ex('必须输入API名称');

//        if ($tables && !$dbtag)
//            ex('必须输入数据库配置名');

        $this->dbtag = $dbtag ?: DEFAULT_DB;
        //name 不能重复
        $file = CONTROLLERS_PATH . $name . '.php';
        if( file_exists( $file ) )
            ex($name . ' Controller 已经存在与本服务!');

        //如果需要处理 表
        if ($tables){
            str_replace('，', ',', $tables);
            $tableArr = explode(',', $tables);
            foreach ($tableArr as $key => $value) {
                $value = str_ireplace('model', '', $value);
                $this->existTable($value);
                $this->newModel($value);
                $this->successMsg .= $value . 'Model : '. MODELS_PATH . lcfirst($value) . '.php' . ' 已经生成成功! <br/>';
            }
            $firstModel = $tableArr[0];
        }

        if ($name){
            //处理 controller
            $this->newController($name, $firstModel ?: '@TABLE_NAME');
            $this->successMsg .= $name . 'Controller : '. CONTROLLERS_PATH . lcfirst($name) . '.php' . ' 已经生成成功! <br/>';
        }

        $this->assign('successMsg', $this->successMsg);
        $this->display('', false);
    }

    /**
     * 创建 新的 controller
     * @param $name
     */
    private function newController($name, $model){
        $tmpContent = file_get_contents(LIBRARY_PATH . 'tmp' . DS . 'controller.tmp');
        //生成后的最终内容
        $endContent = str_replace(
            ['{{version}}', '{{time}}', '{{name}}', '{{pkField}}', '{{model}}'],
            [$this->ver, date('Y-m-d'), ucfirst($name), $this->keyCol, ucfirst($model)],
            $tmpContent);

        $saveFile = file_put_contents(CONTROLLERS_PATH . lcfirst($name) . '.php', $endContent);
        if (false == $saveFile)
            ex(CONTROLLERS_PATH . '没有可写权限, 文件写入失败');

        return true;
    }

    //表是否存在
    private function existTable($table){
        //首先检查是否有重复
        if( file_exists( MODELS_PATH . $table . '.php') )
            ex($table . ' Model 已经存在与本服务!', $this->successMsg);

        $model = new Model($table, null, $this->dbtag);
//        var_dump($model);die;
        $data = $model->getQuery(sprintf('SHOW TABLES LIKE \'%%%s%%\';', $table), '');
        if (false == $data){
            ex($table . ' 表不存在于数据库配置 ' . $this->dbtag , $this->dbtag . ' 配置 数据库名称为: ' . parent::$config['db'][$this->dbtag]['DB_NAME']);
        }else{
            return true;
        }
    }

    private $keyCol = 'id';
    //创建 model
    private function newModel($table){
        $model = new Model($table, null, $this->dbtag);
        $cols = $model->getQuery(sprintf('SHOW COLUMNS FROM `%s`', addslashes($table)), '');
        $fields = [];
        $types = [];
        $nulls = [];
        foreach ($cols as $key => $value) {
            if ($value['Key'] == 'PRI'){
                //只取主键
                $this->keyCol = $value['Field'];
//                break;
            }
            $fields[$value['Field']] = $this->validType($value['Type']);
        }
        $fields = str_replace(['{', '}', ':'], ['', '', '=>'], json_encode($fields));
//        echo $fields;die;
//        var_dump($fields);die;
        $tmpContent = file_get_contents(LIBRARY_PATH . 'tmp' . DS . 'model.tmp');
        //生成后的最终内容
        $endContent = str_replace(
            ['{{dbtag}}', '{{version}}', '{{time}}', '{{name}}', '{{tableName}}', '{{pkField}}', '{{fields}}'],
            [$this->dbtag, $this->ver, date('Y-m-d'), ucfirst($table), strtolower($table), $this->keyCol, $fields],
            $tmpContent);

        $saveFile = file_put_contents(MODELS_PATH . lcfirst($table) . '.php', $endContent);
        if (false == $saveFile)
            ex(MODELS_PATH . '没有可写权限, 文件写入失败');

        return true;
    }

    /**
     * 返回为 标准类型名称
     * int and string
     * @param $value
     */
    private function validType($value){
        if (strripos($value, 'int') !== false){
            return 'int';
        }
        if (strripos($value, 'double') !== false){
            return 'int';
        }
        if (strripos($value, 'float') !== false){
            return 'int';
        }
        if (strripos($value, 'decimal') !== false){
            return 'int';
        }
        return 'string';
    }
}
