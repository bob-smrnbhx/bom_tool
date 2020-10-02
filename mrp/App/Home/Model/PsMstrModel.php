<?php

/**
 *      我的去向模型
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Model;
use Think\Model\RelationModel;

class PsMstrModel extends RelationModel{
    protected $_auto = array (
    );


    protected $_validate = array(  
         array('ps_qty_per','is_numeric','每件需求量必须为数字值！',self::EXISTS_VALIDATE,'function',self::MODEL_BOTH),
    );

}