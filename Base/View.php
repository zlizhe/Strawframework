<?php
namespace Strawframework\Base;

/**
 *  输出视图
 */
class View{

    //输出数组
    public $data =  [];

    //视图模板
    private $template = '';

    private $templateDir = TEMPLATES_PATH;

    public function __construct(){

    }

    /**
     *  显示 mod html view
     */
    public function show($tpl, $return = false){

        if (!$tpl)
            return false;

        $tplFile = $this->templateDir.$tpl.'.html';
        if (!file_exists($tplFile))
            ex($tplFile." 模板文件不存在");

        $modTemp = file_get_contents($tplFile);

        if ($this->data)
            extract($this->data);
        //替换内容 输出
//        echo $modTemp;

        if (false == $return){
            echo $this->replaceAssign($modTemp);
            exit();
        }else{
            return $this->replaceAssign($modTemp);
        }
    }

    /**
     *  输出模板内容
     */
    public function display(string $tpl, bool $hasHeaderFooter): void{
    	$tplFile = $this->templateDir.$tpl.'.html';

        if (!file_exists($tplFile))
        	ex($tplFile." 模板文件不存在");

        //模板自动赋值
        // $this->put(get_object_vars($this));

        if ($this->data)
            extract($this->data);

        $headerFile = $footerFile = '';
        if (true == $hasHeaderFooter){
            $headerFile = $this->templateDir.'common'.DS.'header.html';
            $footerFile = $this->templateDir.'common'.DS.'footer.html';

            if (!file_exists($headerFile))
                ex($headerFile. ' 模板文件不存在');

            if (!file_exists($footerFile))
                ex($footerFile. ' 模板文件不存在');


            //引入 header
            include($headerFile);
        }


        //当前模板
        include($tplFile);

        if (true == $hasHeaderFooter)
            include($footerFile);


        unset($headerFile, $tplFile, $footerFile);
        exit();
    }

    /**
     *  赋值给 assign
     */
    private function put($data){

    }

    /**
     *  给模板赋值
     */
    public function assign($name, $value=''): void{

        //批量
        if (is_array($name)){
            foreach($name as $key => $value){
                $this->data[$key] = $value;
            }
        }
        else{
            //单个
            $this->data[$name] = $value;
        }
    }

    /**
     *  替换模板中的变量
     */
    private function replaceAssign($temp){
        
        //替换 assign
        $assignPlaceholder = '/{\$[_a-zA-Z][A-Za-z0-9_]*}/';
        preg_match_all($assignPlaceholder, $temp, $tempData);

        foreach ($tempData[0] as $value){
            //每个变量与 assign 中的 参数替换成 真实 
            $realParam = str_replace('{$', '', str_replace('}', '', $value));

            $temp = str_replace($value, $this->assign[$realParam] ?: '', $temp);
        }
        return $temp;
    }

}
