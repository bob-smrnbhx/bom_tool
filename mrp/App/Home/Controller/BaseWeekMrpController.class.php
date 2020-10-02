<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;


class BaseWeekMrpController extends BasePeriodMrpController 
{
    static protected $baseConds = [
            "comp_pm_code" => "P",
            "comp_status" => 'AC',
            "comp_ord_per" => ["GT", 7],
    
    ];
    
    static protected $ptpBaseConds = [
            "ptp_pm_code" => "P",
            "ptp_status" => 'AC',
            "ptp_ord_per" => ["GT", 7],
    ];
    
    protected $_mrpModel;
    
    protected function getMrpModel()
    {
        if (empty($this->_mrpModel)) {
            $this->_mrpModel = M("baseweekmrp");
        }
        
        return $this->_mrpModel;
    }
    

    protected function getMrpConds ()
    {
        $map = self::$baseConds;
    
        $map["drps_date"] = ["EGT", $this->getInitStockDate()];
    
        return $map;
    }
    
    
 
    
    protected function getVendTranNbrConds ()
    {
        $map = self::$baseConds;
        $map["tran_date"] = ["EGT", $this->getInitStockDate()];
        
        return $map;
    }
    
    
    
    protected function getPtpBaseConds()
    {
        return self::$ptpBaseConds;
    }
    
    
    public function getMrpDays ()
    {
        $map = ['drps_date' => ["EGT", $this->getInitStockDate()]];
        if (isset($_REQUEST["site"])) {
            $map["drps_site"] = $_REQUEST["site"];
        }
        $daysResult = M("drps_mstr")->distinct(true)->field("drps_date")->where($map)->select();
        $days = [];
        foreach ($daysResult as $row) {
            $days[] = $row["drps_date"];
        }
        sort($days);
        
        return $days;
    }
    


    
    public function isCompsWeekMrpOfVend ($vend)
    {
        $ptpModel = $this->getPtpModel();
        
        $map = $this->getPtpBaseConds();
        $map["ptp_vend"] = $vend;
        
        return $ptpModel->where($map)->max("ptp_iswmrp") ? true: false;
    }
    
    public function doCompsWeekMrpOfVend ($vend)
    {   
        $ptpModel = $this->getPtpModel();
        $ptpMap = $this->getPtpBaseConds();
        $ptpMap["ptp_vend"] = $vend;
        $ptpModel->ptp_iswmrp = 0;
        $ptpModel->where($ptpMap)->save();
 
        $mrpModel = $this->getMrpModel();
        $map = $this->getMrpConds();
        $map['comp_vend'] = $vend;
    
        // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据，并进行mrp计算
        $activeNbr = $this->getActiveNbrOfVend($vend);   
        $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
        $result = $mrpModel->where($map)->select();
        $compsInfo = static::getMrpCompsInfoFromDbResult($result);

    
        // 将计算后的结果保存至在途表中的对应物料号记录中，作为等待提交并审核的数据。
        $tranModel = $this->getTranModel();
        foreach ($compsInfo as $compInfo) {
            $where = [
                    "tran_nbr" => ["exp", "is null"], // 只更新或插入null版本号
            ];  
            $where['tran_part'] = ':tran_part';
            $where['tran_site'] = ':tran_site';
            $where['tran_vend'] = ':tran_vend';
    
            $bind = [];
            $bind[':tran_part'] = array($compInfo["comp_part"],\PDO::PARAM_STR);
            $bind[':tran_site'] = array($compInfo["comp_site"],\PDO::PARAM_STR);
            $bind[':tran_vend'] = array($compInfo["comp_vend"],\PDO::PARAM_STR);
    
            $arr = array_merge($compInfo["shop_day_qtys"] , $compInfo["tran_day_qtys"]);
            $dates = array_keys($arr);
            if (empty($dates)) {
                // 至少要存在一个有效的在途日期记录 ，从而让空活动版本出现
                 $dates = [reset(self::getWeekPeriodDatesMapByWeekStrs($this->getMrpWeeks()))[0]];
 
            }
            foreach ($dates as $date) {
                $where['tran_date'] = ':tran_date';
                $bind[':tran_date'] = array($date,\PDO::PARAM_STR);
    
    
                if ($tranModel->where($where)->bind($bind)->count() != 0) {
                    $tranModel->tran_ord_qty = isset($compInfo["shop_day_qtys"][$date]) ? $compInfo["shop_day_qtys"][$date] : 0;
    
                    // clear other field in case of records submitted but not confirmed
                    $tranModel->tran_mtime = null;
                    $tranModel->tran_name = null;
                    $tranModel->tran_ctime = null;
                    $tranModel->tran_ispass = 0;
                    $tranModel->where($where)->bind($bind)->save();
                } else {
                    $tranModel->tran_ord_qty = isset($compInfo["shop_day_qtys"][$date]) ? $compInfo["shop_day_qtys"][$date] : 0;
                    $tranModel->tran_qty = isset($compInfo["tran_day_qtys"][$date]) ? $compInfo["tran_day_qtys"][$date] : 0;
                    $tranModel->tran_part = $compInfo["comp_part"];
                    $tranModel->tran_site = $compInfo["comp_site"];
                    $tranModel->tran_vend = $compInfo["comp_vend"];
                    $tranModel->tran_date = $date;
                    $tranModel->add();
                }

            }
        }
    
        
    }
    
    
    public function doAllCompsMrp ($enforce = false)
    {
        set_time_limit(300);
    
        $mrpModel = $this->getMrpModel();
    
        $map = $this->getMrpConds();
        // 务必确保一个供应商只能由一个采购员负责
        if (self::getUserType() == 'P') {
            $map["comp_buyer"] = self::getCurrentBuyer();
        }
    
        $vends = $mrpModel->lock(true)->distinct(true)->where($map)->field("comp_vend")->select();
    
    
        foreach ($vends as &$vend) {
            $vend = $vend["comp_vend"];
        }
        unset($vend);
    
        foreach ($vends as $vend) {
            if ($enforce || $this->isCompsWeekMrpOfVend($vend)) {
                $this->doCompsWeekMrpOfVend($vend);
            }
        }
    
    }
    

   static protected function getMrpCompsInfoFromDbResult($result)
   {
       $compsInfo = self::convertToCompsInfoFromDbResult($result);
       $compsInfo =  self::accumulateCompsQtys($compsInfo);
       $compsInfo = self::calculateCompsMrp($compsInfo);
       
       return $compsInfo;
   }
    
    static protected function convertToCompsInfoFromDbResult($result)
    {
        $weeks = [];
        foreach ($result as $row) {
            $week = $row["wrps_week"] ;
            if (!isset($weeks[$week])) {
                $weeks[$week] = $week;
            }
        } 
        $weeks = array_values($weeks);
        sort($weeks);
        
        $week_day_qty_empty_arr = array_flip($weeks);
        array_walk($week_day_qty_empty_arr, function(&$val) {
            $val = [];
        });
        
        $weekStrNumberMap = [];
        foreach ($weeks as $weekStr) {
            $weekNumber = self::getWeekNumberByWeekStr($weekStr);
            $weekStrNumberMap[$weekStr] = $weekNumber;
        }
 
        $compsInfo = [];
        $wshop_weeks = range(1, 53, 2);
        $wshop_week_cal = [];
        foreach ($wshop_weeks as $week) {
            $wshop_week_cal[$week] = true;
        }
        
        $wdMap = self::getWeekPeriodDatesMapByWeekStrs($weeks);
 
        foreach ($result as $row) {
            $uid = $row["comp_vend"] . "-" . $row["comp_part"]. "-" . $row["comp_site"];
            if (!isset($compsInfo[$uid])) {
                $compsInfo[$uid] = $row;
                //$compsInfo[$uid]["shop_week_calc"] = self::convertCommaSeperatedWeekNumbersToWeekMap($row["wshop_week"]);
                $compsInfo[$uid]["shop_week_calc"] = $wshop_week_cal;
                $compsInfo[$uid] += [
                        "qty_pers" => [],
                        "pars_wrps" => [],
                        'tran_week_day_qtys' => $week_day_qty_empty_arr,
                        'shop_week_day_qtys' => $week_day_qty_empty_arr,
                        'tran_qtys' => [],
                        'tran_day_qtys' => [],
                ];
            }
    
            if (!isset( $compsInfo[$uid]["qty_pers"][$row["par_part"]] ) ) {
                $compsInfo[$uid]["qty_pers"][$row["par_part"]] = floatval($row["ps_qty_per"]);;
                $compsInfo[$uid]["pars"][] = $row["par_part"];
            }
   
            
            if (!isset($compsInfo[$uid]["pars_wrps"][$row["wrps_week"]][$row["par_part"]])) {
                $compsInfo[$uid]["pars_wrps"][$row["wrps_week"]][$row["par_part"]] = floatval($row["wrps_qty"]);
            }
    
            if (!is_null($row["tran_date"]) && !isset($compsInfo[$uid]["tran_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]])) {
                if ($row["tran_date"] >= $wdMap[$row["wrps_week"]][0]  && $row["tran_date"] <= $wdMap[$row["wrps_week"]][1] ) {
                    $compsInfo[$uid]["tran_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]] = floatval($row["tran_qty"]);
                }
            }
            
            if (!is_null($row["tran_date"]) && !isset($compsInfo[$uid]["tran_day_qtys"][$row["tran_date"]])) {
                $compsInfo[$uid]["tran_day_qtys"][$row["tran_date"]] = floatval($row["tran_qty"]);
            }
            
            if (!is_null($row["tran_date"]) && !isset($compsInfo[$uid]["shop_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]])) {
                if ($row["tran_date"] >= $wdMap[$row["wrps_week"]][0]  && $row["tran_date"] <= $wdMap[$row["wrps_week"]][1] ) {
                    $compsInfo[$uid]["shop_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]] = floatval($row["tran_ord_qty"]);
                }
            }

            if (!isset($compsInfo[$uid]["is_shop_week"][$row["wrps_week"]])) {
                $wn = $weekStrNumberMap[$row["wrps_week"]];
                $compsInfo[$uid]["is_shop_day"][$row["wrps_week"]] =  isset($compsInfo[$uid]["shop_week_calc"][$wn]) && $compsInfo[$uid]["shop_week_calc"][$wn] ?  true: false;
            }
            

        }

        return $compsInfo;
    }
    
    
    static protected function accumulateCompsQtys ($compsInfo)
    {
        foreach ($compsInfo as $uid => &$compInfo) {
            $compInfo["comps_wrps"] = [];
            foreach ($compInfo["pars_wrps"] as $w => $rps) {
                $cqty = 0;
                foreach ($rps as $psPar => $parQty) {
                    $cqty += $parQty * $compInfo["qty_pers"][$psPar];
                }
    
                $compInfo["comps_wrps"][$w] = $cqty;
            }
            $compInfo["dmnd_qtys"] = $compInfo["comps_wrps"];
            
            $compInfo["tran_qtys"] = [];
            foreach ($compInfo["tran_week_day_qtys"] as $w => $qtys) {
                $cqty = 0;
                foreach ($qtys as $date => $qty) {
                    $cqty += $qty;
                }
                $compInfo["tran_qtys"][$w] = $cqty;
            }
            
            $compInfo["shop_qtys"] = [];
            foreach ($compInfo["shop_week_day_qtys"] as $w => $qtys) {
                $cqty = 0;
                foreach ($qtys as $date => $qty) {
                    $cqty += $qty;
                }
                $compInfo["shop_qtys"][$w] = $cqty;
            }
        }
        
        return $compsInfo;
    }
    
    static protected function calculateCompsMrp ($compsInfo)
    { 
        foreach ($compsInfo as &$compInfo) {
            $compInfo["shop_weeks"] = array_keys(array_filter($compInfo["is_shop_day"]));
            $demandDateMap = $transitDateMap = $shopDateMap = $stockDateMap  = [];
        
            // calculate predicted order amount
            $options = [
                    "demandWeekMap" => $compInfo["dmnd_qtys"],
                    "transitWeekMap" => $compInfo["tran_qtys"],
                    "shopWeeks" => $compInfo["shop_weeks"],
                    "orgStock" => $compInfo["in_qty_oh"],
                    "saftyStock" => $compInfo["comp_rop"],
                    "baseOrderAmountForMultiple" => $compInfo["comp_ord_mult"],
            ];
        
            $oac = new \Home\Model\OrderWeekAmountCalculatorModel($options);
            $oac->calculate();
            $compInfo["shop_qtys"] = $oac->getOrderWeekMap();
            // store corresponding date qty 
            foreach ($compInfo["shop_qtys"] as $weekStr => $qty) {
                $mondayDate = self::getStartDateOfWeekStr($weekStr);
                $compInfo["shop_day_qtys"][$mondayDate] = $qty;
            }
 
            
            $compInfo["stock_qtys"] = $oac->getStockWeekMap($invalidStockStartWeek);
            $compInfo["invalid_stock_start_week"] = $invalidStockStartWeek;
 
            if ($invalidStockStartWeek) {
                //throw new \Exception("the provided ord qty would break the safty stock!");
            }
            
        
        }
        
        return $compsInfo;
    }
    
 
    


//     protected static function getWeekPeriodDatesMrpConds($weekStrs)
//     {
//         $sqlConds = [];
//         $wdMap = self::getWeekPeriodDatesMapByWeekStrs($weekStrs);
//         foreach ($wdMap as $weekStr => $item) {
//             $fromDate = $item[0];
//             $toDate = $item[1];
//             $sqlConds[] = "(wrps_week = '$weekStr' AND ( (tran_date >= '$fromDate' AND tran_date <= '$toDate') OR tran_date is null) )";
//         }
//         $sqlCondStr = implode(" OR ", $sqlConds);
    
//         return $sqlCondStr;
//     }
    
    protected static function getWeekPeriodDatesTranConds($weekStrs)
    {
        $sqlConds = [];
        $wdMap = self::getWeekPeriodDatesMapByWeekStrs($weekStrs);
        foreach ($wdMap as $weekStr => $item) {
            $fromDate = $item[0];
            $toDate = $item[1];
            $sqlConds[] = " ( (tran_date >= '$fromDate' AND tran_date <= '$toDate') OR tran_date is null ) ";
        }
        $sqlCondStr = implode(" OR ", $sqlConds);
    
        return $sqlCondStr;
    }
    
    protected static function getWeekNumberByWeekStr($weekStr, &$yearNumber = null)
    {
        if (!preg_match('/(\d{2})年(\d{1,2})周/', $weekStr, $matches)) {
            throw new \Exception("invalid week str format provided: $weekStr");
        }
        $yearNumber = "20" . $matches[1];
        $weekNumber = intval($matches[2]);
    
        return $weekNumber;
    }
    
    protected static function getWeekPeriodDatesMapByWeekStrs($weekStrs)
    {
        $startWeek = min($weekStrs);
        $weekCount = count($weekStrs);
    
        if (!preg_match('/(\d{2})年(\d{1,2})周/', $startWeek, $matches)) {
            throw new \Exception("invalid start week str format provided: $startWeek");
        }
    
        $yearNumber = "20" . $matches[1];
        $weekNumber = $matches[2];
        $startDate = new \DateTime(self::getStartDateOfWeek($weekNumber, $yearNumber));
 
        $wdMap = [];
        $datePeriods = new \DatePeriod($startDate, new \DateInterval("P1W"), ($weekCount - 1));
        $i = 0;
        foreach ($datePeriods as $date) {
            $weekStartDateStr = $date->format("Y-m-d");
            $weekEndDateStr = $date->add(new \DateInterval("P6D"))->format("Y-m-d");
            $wdMap[$weekStrs[$i]] = [$weekStartDateStr, $weekEndDateStr];
            $i++;
        }
    
        return $wdMap;
    }
    
    protected static function getStartDateOfWeekStr ($weekStr)
    {
        $yearNumber = null;
        $weekNumber = self::getWeekNumberByWeekStr($weekStr, $yearNumber);
        return self::getStartDateOfWeek($weekNumber, $yearNumber);
    }
    
    protected static function getStartDateOfWeek($week, $year = null)
    {
        if (empty($year)) {
            $year = date("Y");
        }
        $yearStartDay = "$year-1-1";
        $yearStartWeekday = date("w", strtotime($yearStartDay));
    
        switch ($yearStartWeekday) {
            case 1:
                $yearStartMonday = "$year-1-1";
                break;
            case 0:
                $yearStartWeekday = 7;
            default:
                $yearStartMonday = "$year-1-" . (8 - $yearStartWeekday + 1);
        }
    
        $yearStartMondayDate = new \DateTime($yearStartMonday);
        $startMondayDate = $yearStartMondayDate->add(new \DateInterval("P" . ($week - 1) ."W"));
        return $startMondayDate->format("Y-m-d");
    }
    
    protected static function convertCommaSeperatedWeekNumbersToWeekMap ($weeksStr)
    {
        $weeksArr = explode(",", $weeksStr);
        $map = [];
        foreach ($weeksArr as $week) {
            $map[$week] = true;
        }
        return $map;
    }
   
    public function test()
    {
        echo self::getStartDateOfWeek(1,2017);
        echo self::getStartDateOfWeek(52,2016);
        echo self::getStartDateOfWeek(53,2016);
    }
 
    
    
 
    
 
    
 
 

}