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
use Think\Model;

class WrpsMstrModel extends Model{
    protected $_auto = array (
            array('wrps_site','stripeLeadingZero', 3, 'callback'),
    );

    protected $_validate = array(  
        array('wrps_qty','is_numeric','周生产量必须为数字值！',self::EXISTS_VALIDATE,'function',self::MODEL_BOTH),
    );
    
    protected $updateFields = 'wrps_qty';
    
    function stripeLeadingZero ($str)
    {
        return ltrim($str, '0');
    }
}