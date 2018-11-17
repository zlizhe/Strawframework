<?php
namespace vendors;


/**
 * 通用的树型类
 * @author yangyunzhou@foxmail.com
 * @date 2010年11月23日10:09:31
 */
class FormartTree{

    /**
     */
    protected $arr = array();

    /**
     * 生成树型结构所需修饰符号，可以换成图片
     * @var Array
     */
    protected $icon = array(' │ ',' ├ ',' └ ');

    public $value = 'value';
    public $upid = 'up_id';
    public $id = 'id';
    /**
    * @access private
    */
    protected $ret = '';


    public function tree($arr=array())
    {
       $this->arr = $arr;
       $this->ret = '';
       return is_array($arr);
    }

    /**
    * 得到父级数组
    * @param int
    * @return array
    */
    protected function get_parent($myid)
    {
        $newarr = array();
        if(!isset($this->arr[$myid])) return false;
        $pid = $this->arr[$myid][$this->upid];
        $pid = $this->arr[$pid][$this->upid];
        if(is_array($this->arr))
        {
            foreach($this->arr as $id => $a)
            {
                if($a[$this->upid] == $pid) $newarr[$id] = $a;
            }
        }
        return $newarr;
    }

    /**
    * 得到子级数组
    * @param int
    * @return array
    */
    protected function get_child($myid)
    {
        $a = $newarr = array();
        if(is_array($this->arr))
        {
            foreach($this->arr as $id => $a)
            {
//                print_r($this->arr);die;
                if($a[$this->upid] == $myid) $newarr[$a[$this->id]] = $a;
            }
        }
//        print_r($newarr);die;
        return $newarr ? $newarr : false;
    }

    /**
    * 得到当前位置数组
    * @param int
    * @return array
    */
    protected function get_pos($myid,&$newarr)
    {
        $a = array();
        if(!isset($this->arr[$myid])) return false;
        $newarr[] = $this->arr[$myid];
        $pid = $this->arr[$myid][$this->upid];
        if(isset($this->arr[$pid]))
        {
            $this->get_pos($pid,$newarr);
        }
        if(is_array($newarr))
        {
            krsort($newarr);
            foreach($newarr as $v)
            {
                $a[$v['id']] = $v;
            }
        }
        return $a;
    }

    /**
     * -------------------------------------
     *  得到树型结构
     * -------------------------------------
     * @param $myid 表示获得这个ID下的所有子级
     * @param $str 生成树形结构基本代码, 例如: "<option value=\$id \$select>\$spacer\$value</option>"
     * @param $sid 被选中的ID, 比如在做树形下拉框的时候需要用到
     * @param $adds
     * @param $str_group
     */
    public function get_tree($myid, $str, $sid = 0, $adds = '', $str_group = '')
    {
        $number=1;
        $child = $this->get_child($myid);
        if(is_array($child)) {
            $total = count($child);
            foreach($child as $id=>$a) {
                $j=$k='';
                if($number==$total) {
                    $j .= $this->icon[2];
                } else {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $spacer = $adds ? $adds.$j : '';
                $selected = $id==$sid ? 'selected' : '';
                @extract($a);
                $upid == 0 && $str_group ? eval("\$nstr = \"$str_group\";") : eval("\$nstr = \"$str\";");
                $this->ret .= $nstr;
                $this->get_tree($id, $str, $sid, $adds.$k.'&nbsp;',$str_group);
                $number++;
            }
        }
        return $this->ret;
    }

    /**
    * 同上一方法类似,但允许多选
    */
    public function get_tree_multi($myid, $str, $sid = 0, $adds = '')
    {
        $number=1;
        $child = $this->get_child($myid);
        if(is_array($child))
        {
            $total = count($child);
            foreach($child as $id=>$a)
            {
                $j=$k='';
                if($number==$total)
                {
                    $j .= $this->icon[2];
                }
                else
                {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $spacer = $adds ? $adds.$j : '';

                $selected = $this->have($sid,$id) ? 'selected' : '';
                @extract($a);
                eval("\$nstr = \"$str\";");
                $this->ret .= $nstr;
                $this->get_tree_multi($id, $str, $sid, $adds.$k.'&nbsp;');
                $number++;
            }
        }
        return $this->ret;
    }

    protected function have($list,$item){
        return(strpos(',,'.$list.',',','.$item.','));
    }

    /**
     * 格式化数组
     */
    public function getArray($myid=0, $sid=0, $adds='')
    {
        $number=1;
        $child = $this->get_child($myid);
        if(is_array($child)) {
            $total = count($child);
//            $childK = 0;
            foreach($child as $id=>$a) {
                $j=$k='';
                if($number==$total) {
                    $j .= $this->icon[2];
                } else {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $spacer = $adds ? $adds.$j : '';
                @extract($a);
                $a[$this->value] = $spacer.''.$a[$this->value];
                $this->ret[$a[$this->id]] = $a;
                $fd = $adds.$k.'&nbsp;&nbsp;';
//                $childK++;
                $this->getArray($id, $sid, $fd);
                $number++;
            }
        }

        return array_values($this->ret);
    }
}