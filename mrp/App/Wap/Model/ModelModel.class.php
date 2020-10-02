<?php

namespace Wap\Model;
use Think\Model;

class ModelModel extends Model{
    protected $_validate = array(
        array('name','','名称已经存在！',0,'unique',1),
    );


  
}