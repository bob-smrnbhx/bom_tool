<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

abstract class BasePeriodMrpController extends BaseMrpController 
{
    static protected $ptpBaseConds = [
            "ptp_pm_code" => "P",
            "ptp_status" => 'AC',
    
    ];
    
    
    abstract protected function getMrpModel();
    abstract protected function getMrpConds ();
    
    abstract protected static function getMrpCompsInfoFromDbResult($result);
    
    protected function getPtpBaseConds()
    {
        return self::$ptpBaseConds;
    }
    
    protected $_tranModel;
    protected $_compTranModel;
    
    protected $_initStockDate;
    
    /**
     * 分地点考虑库存日期
     */
    protected function getInitStockDate()
    {
        $map = $this->getPtpBaseConds();
        switch($_REQUEST["site"]) {
            case 1000:
                $map["ptp_site"] = '1000';
                $map["ptp_pm_code"] = 'P';
                break;
            case 6000:
                $map["ptp_site"] = '6000';
                $map["ptp_pm_code"] = 'P';
                break;
        }
        
        if (empty($this->_initStockDate)) {
            $this->_initStockDate = M("ptp_stock")->where($map)->max("in_date");
        }
        
        return $this->_initStockDate;
    }
    
    protected function getTranModel()
    {
        if (empty($this->_tranModel)) {
            $this->_tranModel = M("tran_det");
        }
    
        return $this->_tranModel;
    }
    
    protected function getVendTranNbrModel ()
    {
        if (empty($this->_compTranModel)) {
            $this->_compTranModel = M("comp_tran");
        }
    
        return $this->_compTranModel;
    }
    

    abstract protected function getVendTranNbrConds ();
    
    protected function getCompsOfVend ($vend)
    {
        $ptpModel = $this->getPtpModel();
        $ptpMap = $this->getPtpBaseConds();
        $ptpMap["ptp_vend"] = $vend;
        $comps = $ptpModel->where($ptpMap)->getField("ptp_part", true);
    
        return $comps;
    }
    

    protected function getLatestNbrOfVend ($vend)
    {
        $map = $this->getVendTranNbrConds();
        $map["tran_vend"] = $vend;
        return $this->getVendTranNbrModel()->where($map)->max("tran_nbr");
    }
    
    protected function hasUncheckedNbrOfVend ($vend)
    {
        $map = $this->getVendTranNbrConds();
        $map["tran_vend"] = $vend;
        $map["tran_nbr"] = ["exp", "is null"];
        return $this->getVendTranNbrModel()->where($map)->count() != 0;
    }
    
    protected function getActiveNbrOfVend ($vend)
    {
        $latestNbr = $this->getLatestNbrOfVend($vend);
        if ($latestNbr) {
            if ($this->hasUncheckedNbrOfVend($vend)) {
                return null;
            } else {
                return $latestNbr;
            }
        } else {
            return null;
        }
    }
    
 
    


//     protected function doCompsDayMrpOfParParts($parParts, $mrpType)
//     {
//         set_time_limit(60);
//         if (is_string($parParts)) {
//             $parParts = array($parParts);
//         }
    
    
//         $mrpModel = $this->getMrpModel();
//         $map = $this->getMrpConds();
//         $vends = [];
//         foreach ($parParts as $parPart) {
//             $map["par_part"] = trim($parPart);
//             $vendsData = $mrpModel->lock(true)->distinct(true)->where($map)->field("comp_vend")->select();
//             foreach ($vendsData as $vendData) {
//                 $vends[] = $vendData["comp_vend"];
//             }
//         }
//         $vends = array_unique($vends);
    
//         // 逐个供应商进行最新版本数据的获取与计算保存。
//         foreach ($vends as $vend) {
//             if ($this->isCompsMrpOfVend($vend, $mrpType)) {
//                 $this->doCompsMrpOfVend($vend, $mrpType);
//             }
//         }
    
//     }
    
    
}

