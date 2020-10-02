<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdMoldNBController extends ProdmdWeldCQController 
{
 
    
    protected function getProdAdapter()
    {
        if (empty($this->adapter)) {
            $this->adapter = new \Home\Model\MoldProdModel(1000);
        }
    
        return $this->adapter;
    }
    
    public function exportBalanceExcel ()
    {
        $adapter = new \Home\Model\MoldProdModel(1000);
    
        $adapter->exportBalanceExcel("注塑");
    }
    
    public function dmdGrid()
    {
        $adapter = $this->getProdAdapter();

        $adapter->doEasyMrp();
 

        
        $this->assign("orgDate", $adapter->getOrgDate());
        $this->assign("dates", $adapter->getActiveDates());
        $this->assign("dateTypeMap", $adapter->getDateTypeMap());
        $this->assign("fmdates", $adapter->getFormattedDates());
        
        $this->assign("isPeriodDateMap", $adapter->getAssyIsPeriodDateMap());
        $this->assign("isWorkdayDateMap", $adapter->getIsWorkdayDateMap());

        $this->assign("totalDmds", $adapter->getTotalDayDmds());
        $this->assign("totalProds", $adapter->getTotalDayProds());
        
        $this->assign("partsInfo", $adapter->getPartsInfo());
        $this->assign("machCaps", $adapter->getMachCaps());
 
        $this->display();
    
    }
    
    public function planGrid()
    {
        $adapter = $this->getProdAdapter();

        
        $this->assign("orgDate", $adapter->getOrgDate());
        $this->assign("dates", $adapter->getActiveDates());
        $this->assign("dateTypeMap", $adapter->getDateTypeMap());
        $this->assign("fmdates", $adapter->getFormattedDates());
        
        $this->assign("isPeriodDateMap", $adapter->getAssyIsPeriodDateMap());
        $this->assign("isWorkdayDateMap", $adapter->getIsWorkdayDateMap());
        
        $this->assign("totalDmds", $adapter->getTotalDayDmds());
        $this->assign("totalProds", $adapter->getTotalDayProds());
        
        $this->assign("partsInfo", $adapter->getPartsInfo());
        $this->assign("machCaps", $adapter->getMachCaps());
        
        $this->display();
    }
    
    
    public function updatePlans()
    {
        $rpsInfo = [];
        $ptpsInfo = [];
    
        

        foreach ($_REQUEST as $key => $val) {
            if (stripos($key, "rps#") === 0) {
                list($r, $rpart, $rline, $rsite, $rdate, $rtype) = explode("#", $key);

                
                $machKey = "mach#$rpart#$rline#$rsite";
                if (isset($_REQUEST[$machKey])) {
                    $mach = $_REQUEST[$machKey];
                }
                
                $rpart=str_replace('_', '.', $rpart);
                
                // 所有活动日期的计划日程数据，哪怕为0，都应该更新，从而可以覆盖之前可能已存在的计划数据值。
                $data = [
                        "drps_part" => $rpart,
                        "drps_line" => $rline,
                        "drps_site" => $rsite,
                        "drps_date" => $rdate,
                        "drps_qty"  => floatval($val),
                        "drps_type" => $rtype
                ];
                
                if (isset($_REQUEST[$machKey])) {
                    $data['drps_mach'] = $mach;
                }
                
                $rpsInfo[] = $data;
                

                
                // 对应的地点-物料数据的mrp标志必须在更新后设置为0，表示已运行过mrp，不再需要重新运行。
                $ptpsInfo[$rpart . $rsite] = [
                        "ptp_site" => $rsite,
                        "ptp_part" => $rpart,
                        "ptp_isdmrp" => 0
                ];
            }
        }
 
    
        $err = false;
        $msg = '';
        try {
            $drp = M("drps_mstr");
            $ptp = M("ptp_det");
            $drp->startTrans();
    
    
            foreach ($rpsInfo as $rpInfo) {
                $where = $bind = [];
                $where['drps_part'] = ':drps_part';
                $where['drps_line'] = ':drps_line';
                $where['drps_site'] = ':drps_site';
                $where['drps_date'] = ':drps_date';
    
                $bind[':drps_part']    =  array($rpInfo["drps_part"],\PDO::PARAM_STR);
                $bind[':drps_line']    =  array($rpInfo["drps_line"],\PDO::PARAM_STR);
                $bind[':drps_site']    =  array($rpInfo["drps_site"],\PDO::PARAM_STR);
                $bind[':drps_date']    =  array($rpInfo["drps_date"],\PDO::PARAM_STR);
    
                if ($drp->where($where)->bind($bind)->count() != 0) {
                    $drp->where($where)->bind($bind)->save($rpInfo);
                } else {
                    $drp->add($rpInfo);
                }
            }
    
            foreach ($ptpsInfo as $ptpInfo) {
                $where = $bind = [];
                $where['ptp_part'] = ':ptp_part';
                $where['ptp_site'] = ':ptp_site';
    
                $bind[':ptp_part']    =  array($ptpInfo["ptp_part"],\PDO::PARAM_STR);
                $bind[':ptp_site']    =  array($ptpInfo["ptp_site"],\PDO::PARAM_STR);
    
                $ptp->where($where)->bind($bind)->save($ptpInfo);
            }
    
    
            $drp->commit();
            $msg = "生产计划更新成功";
        } catch (\Exception $e) {
            $drp->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
    
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
}