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

class PtpDetModel extends RelationModel{
    protected $_auto = array (
            array('ptp_rop','covertStrToStandardNumberFormat', 3, 'callback'), 
            array('ptp_ismrp', 'covertStrToBoolean', 3, 'callback'),
    );
    protected $_link = array(
            'drpsMstr' => array(
                    'mapping_type'  => self::HAS_MANY,
                    'mapping_name'  => 'drps',
                    'foreign_key'   => "drps_part",
                    //'mapping_order' => 'ptp_part, ptp_site',
                    // 定义更多的关联属性
            )
    );

    protected $_validate = array(  
         array('ptp_site',array(1000,2000,3000,6000,6100),'区域值的范围不正确！',1,'in'),
    );

    function covertStrToStandardNumberFormat ($number)
    {
        return str_replace(",", "", $number);
    }
    
    function covertStrToBoolean ($boolstr)
    {
        return filter_var($boolstr, FILTER_VALIDATE_BOOLEAN);
    }
}