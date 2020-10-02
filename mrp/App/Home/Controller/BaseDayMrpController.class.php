<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;


class BaseDayMrpController extends BasePeriodMrpController 
{
    static protected $baseConds = [
            "comp_pm_code" => "P",
            "comp_status" => 'AC',
            //"comp_ord_per" => ["ELT", 7],
    
    ];
    
    static protected $ptpBaseConds = [
            "ptp_pm_code" => "P",
            "ptp_status" => 'AC',
            //"ptp_ord_per" => ["ELT", 7],
    
    ];
    

    protected $_mrpModel;

    protected function getMrpModel()
    {
        if (empty($this->_mrpModel)) {
            $this->_mrpModel = M("basedaymrp");
        }
        
        return $this->_mrpModel;
    }
    
    protected function getMrpConds ()
    {
        $map = self::$baseConds;
    
        $map["drps_date"] = ["EGT", $this->getInitStockDate()];
        
        switch($_REQUEST["site"]) {
            case 1000:
                $map["comp_site"] = '1000';
                break;
            case 6000:
                $map["comp_site"] = '6000';
                break;
        }
        
    
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
    
    

    

    
    
    public function isCompsDayMrpOfVend ($vend)
    {
        $ptpModel = $this->getPtpModel();
        
        $map = $this->getPtpBaseConds();
        $map["ptp_vend"] = $vend;
        
        return $ptpModel->where($map)->max("ptp_isdmrp") ? true: false;
    }
    
    public function doCompsDayMrpOfVend ($vend)
    {   
        $ptpModel = $this->getPtpModel();
        $ptpMap = $this->getPtpBaseConds();
        $ptpMap["ptp_vend"] = $vend;
        $ptpModel->ptp_isdmrp = 0;
        $ptpModel->where($ptpMap)->save();
        
        
        $mrpModel = $this->getMrpModel();
        $map = $this->getMrpConds();
        $map['comp_vend'] = $vend;
        
        // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据，并进行mrp计算
        $activeNbr = $this->getActiveNbrOfVend($vend);
        $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
        $result = $mrpModel->where($map)->select();
        $compsInfo = self::getMrpCompsInfoFromDbResult($result);
        
        
        // 将计算后的结果保存至在途表中的对应物料号记录中，作为等待提交并审核的数据。
        // 千万不要设置tran_ispass为0，使用默认值null即可，这样才能避免影响按照tran_ispass的排序。
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
                $dates = [reset($this->getMrpDays())];
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
    
        $vends = $mrpModel->lock(true)->distinct(true)->where($map)->field("comp_vend")->getField("comp_vend", true);
    
        foreach ($vends as $vend) {
            if ($enforce || $this->isCompsDayMrpOfVend($vend)) {
                $this->doCompsDayMrpOfVend($vend);
            }
        }
    
    }
    
    
   static protected function getMrpCompsInfoFromDbResult($result)
   {
       $compsInfo = self::convertToCompsInfoFromDbResult($result);
       $compsInfo =  self::accumulateCompsDrpsQtys($compsInfo);
       $compsInfo = self::calculateCompsMrp($compsInfo);
       
       return $compsInfo;
   }
   
   public function test ()
   {

       
   }
   

   
   static protected function convertToCompsInfoFromDbResult($result)
   {
        // 获取所有可到货的单日日期、周周一日期、月第一周周一日期。
        $dDates = $wDates = $mDates = [];
        foreach ($result as $row) {
            $date = $row["drps_date"];
            if ($row["drps_type"] == 'd') {
                $dDates[$date] = $date;
            } else if ($row["drps_type"] == 'w') {
                $wDates[$date] = $date;
            } else if ($row["drps_type"] == 'm') {
                $mDates[$date] = $date;
            }
        }
        
        // 从所有单日日期中提取预测单日日期
        $pdayToMondayMap = [];
//         $pdays = self::getPdaysIn($dDates, $_REQUEST["site"]);
//         foreach ($pdays as $pday) {
//             $pdayToMondayMap[$pday] = self::getSameWeekMondayOf($pday);
//             // 将单日日期中的预测日期从单日日期列表中排除，并将所在的周一加入周周一日期列表
//             unset($dDates[$pday]);
//             $wDates[$pdayToMondayMap[$pday]] = $pdayToMondayMap[$pday];
//         }
        
        
        asort($dDates);
        asort($wDates);
        asort($mDates);
        
        $availDates = array_values($dDates + $wDates + $mDates);
        
        $lastDDate = end($dDates);

        
        // 为可到货的单日日期使用日期-星期几映射表
        $dateWeekdayMap =  self::getDateWeekdayMap($dDates);
   
       $compsInfo = [];
       foreach ($result as $row) {
           $uid = $row["comp_vend"] . "-" . $row["comp_part"]. "-" . $row["comp_site"];

           if (!isset($compsInfo[$uid])) {
               $compsInfo[$uid] = $row;
               $compsInfo[$uid]['in_qty_oh'] = round($row['in_qty_oh']);
               $compsInfo[$uid]["shop_day_calc"] = self::convertBinCalToWeekdayMap($row["shop_day"]);
               $compsInfo[$uid] += [
                       "pars" => [],
                       "qty_pers" => [],
                       "yld_pcts" => [],
                       "pars_drps" => [],
               ];
   
           }
   
           if (!isset( $compsInfo[$uid]["qty_pers"][$row["par_part"]] ) ) {
               $compsInfo[$uid]["qty_pers"][$row["par_part"]] = floatval($row["ps_qty_per"]);
               $compsInfo[$uid]["yld_pcts"][$row["par_part"]] = floatval($row["par_yld_pct"]);
               $compsInfo[$uid]["pars"][] = $row["par_part"];
           }
           
           $date = $row["drps_date"];
   
           if (!isset($pdayToMondayMap[$date])) {
               // 非预测的日计划数量直接使用
               if (!isset($compsInfo[$uid]["pars_drps"][$date][$row["par_part"]])) {
                   $compsInfo[$uid]["pars_drps"][$date][$row["par_part"]] = floatval($row["drps_qty"]);
               }
               
               if (!isset($compsInfo[$uid]["tran_qtys"][$date])) {
                   $compsInfo[$uid]["tran_qtys"][$date] = floatval($row["tran_qty"]);
                   $compsInfo[$uid]["tran_day_qtys"][$date] = floatval($row["tran_qty"]);
               }
           } else {
               // 预测的日计划数量作为数组项使用，待后续合并累加至所在周的周一的日计划中
               $monday = $pdayToMondayMap[$date];
               
               $compsInfo[$uid]["pars_drps"][$monday][$row["par_part"]][$date] = floatval($row["drps_qty"]);
               
               $compsInfo[$uid]["tran_qtys"][$monday][$date] = floatval($row["tran_qty"]);
               $compsInfo[$uid]["tran_day_qtys"][$monday][$date] = floatval($row["tran_qty"]);
           }
   
          
       }
       
       foreach ($compsInfo as &$compInfo) {
           foreach ($dDates as $date){
               $compInfo["dateTypes"][$date] = true;
           }
           if ($compInfo["comp_ord_per"] <= 7) {
               // 如果采购周期为7天内
           
               foreach ($dDates as $date){
                   // 对于单日日期，则根据日历表决定是否可到货
                   $wd = $dateWeekdayMap[$date];
                   if ($compInfo["shop_day_calc"][$wd]) {
                       $compInfo["is_shop_day"][$date] = true;
                   }
               }
           
               foreach (($wDates + $mDates) as $date) {
                   // 对于多日日期，总是允许到货
                   $compInfo["is_shop_day"][$date] = true;
               }
           
           
           } else {
               // 如果采购周期大于7天
           
               // 对于月日期，总是可到货
               foreach ($mDates as $date) {
                   $compInfo["is_shop_day"][$date] = true;
               }
           
               // 对于单日日期和周日期，只有周一的单日日期和周日期才允许到货
               foreach (($dDates + $wDates) as $date) {
                   if (self::isMonday($date)) {
                       switch ($compInfo["comp_site"]) {
                           case 1000:
                               // 此时，宁波只允许每隔一周的周一（暂定为奇数周）到货
                               if (self::isOfOddWeek($date)) {
                                   $compInfo["is_shop_day"][$date] = true;
                               }
                               break;
                           case 6000:
                               // 此时，重庆允许任何一周的周一到货
                           default:
                               $compInfo["is_shop_day"][$date] = true;
                               break;
                       }
                   }
               }
           }
           
           
           foreach ($compInfo['pars_drps'] as &$dp) {
               foreach ($dp as &$parDrps) {
                   if (is_array($parDrps)) {
                       $parDrps = array_sum($parDrps);
                   }
               }
           }
           
           foreach ($compInfo['tran_qtys'] as &$dq) {
               if (is_array($dq)) {
                   $dq = array_sum($dq);
               }
           }

           foreach ($compInfo['tran_day_qtys'] as &$daq) {
               if (is_array($daq)) {
                   $daq = array_sum($daq);
               }
           }
       }
       
       return $compsInfo;
   }


   static protected function accumulateCompsDrpsQtys ($compsInfo)
   {
       foreach ($compsInfo as $uid => &$compInfo) {
           foreach ($compInfo["pars_drps"] as $d => $rps) {
               $cqty = 0;
               foreach ($rps as $psPar => $parQty) {
                   //$cqty += $parQty * $compInfo["qty_pers"][$psPar] / ($compInfo["yld_pcts"][$psPar] / 100);
                   $cqty += $parQty * $compInfo["qty_pers"][$psPar];
               }
   
               $cqty = round($cqty);
               $compInfo["comps_drps"][$d] = $cqty;
           }
           $compInfo["dmnd_qtys"] = $compInfo["comps_drps"];
       }
   
       return $compsInfo;
   }
    
   static protected function calculateCompsMrp ($compsInfo)
   {
       foreach ($compsInfo as &$compInfo) {
           $compInfo["shop_dates"] = array_keys(array_filter($compInfo["is_shop_day"]));
           $demandDateMap = $transitDateMap = $shopDateMap = $stockDateMap  = [];
            
 

   
           // calculate predicted order amount
           $options = [
                   "demandDateMap" => $compInfo["dmnd_qtys"],
                   "transitDateMap" => $compInfo["tran_qtys"],
                   "shopDates" => $compInfo["shop_dates"],
                   "dateTypes" => $compInfo["dateTypes"],
                   "orderPer"  => $compInfo["comp_ord_per"],
                   "orgStock" => $compInfo["in_qty_oh"],
                   "saftyStock" => $compInfo["comp_rop"],
                   "baseOrderAmountForMultiple" => $compInfo["comp_ord_mult"],
                   "minOrderAmount" => $compInfo["comp_ord_min"]
           ];
   
   
           $oac = new \Home\Model\OrderAmountCalculatorModel($options);
           $oac->calculate();
           $compInfo["shop_qtys"] = $oac->getOrderDateMap();
           $compInfo["shop_day_qtys"] = $compInfo["shop_qtys"];
   
           $compInfo["stock_qtys"] = $oac->getStockDateMap($invalidStockStartDate);
           $compInfo["invalid_stock_start_date"] = $invalidStockStartDate;
   
           if ($invalidStockStartDate) {
               //throw new \Exception("the provided ord qty would break the safty stock!");
           }
   
       }
   
       return $compsInfo;
   }
   
   static public function getDateWeekdayMap ($dates)
   {
       // calculate date-weekday map
       $dateWeekdayMap =  [];
       foreach ($dates as $date) {
           $t = strtotime($date);
           $weekday = date('w', $t);
   
           $dateWeekdayMap[$date] = $weekday;
       }
       return $dateWeekdayMap;
   }
    
    
   
    
   protected static function convertBinCalToWeekdayMap ($bincal)
   {
       // 没有日历表存在的供应商日历视为每天都能送货
       //         if (empty($bincal)) {
       //             $bincal = 0b1111111;
       //         }
       //dump(str_split(str_pad(decbin($bincal), 7, '0', STR_PAD_LEFT )));
       return array_reverse(str_split(str_pad(decbin($bincal), 7, '0', STR_PAD_LEFT )));
   }
   
   static protected function getPdaysIn ($dates, $site)
   {
       sort($dates);
       $pdays = [];
       if ($site == 1000) {
           // 宁波生产计划的第三周日计划都认为是预测计划，需要合并至该周周一进行计算。
           $startDay = $dates[0];
           $startWeek = date("W", strtotime($startDay));
           foreach ($dates as $date) {
               if (date("W", strtotime($date)) - $startWeek > 1) {
                   $pdays[] = $date;
               }
           }
       }
        
       return $pdays;
   }
    
   static protected function getSameWeekMondayOf ($date)
   {
       $curWd = date('w', strtotime($date));
       if ($curWd == 0) {
           $curWd = 7;
       }
   
       return date('Y-m-d',strtotime($date) - ($curWd - 1) * 86400);
   }
   
   public  static function calculateFirstFdayMap ($startDate, $endDate, $fday)
   {
       if ($fday < 7) {
           $fday = 7;
       }
   
       $orgFriday = self::getStartFriday($startDate);
       $fdays = self::getFdaysFromFriday($orgFriday, $endDate, $fday, true);
   
       $days = self::getDaysBetween($orgFriday, $endDate);
       $fdayMaps = [];
       foreach ($days as $day) {
           if (in_array($day, $fdays)) {
               $fdayMaps[$day] = true;
           } else {
               $fdayMaps[$day] = false;
           }
       }
   
       return $fdayMaps;
   }
   
   
   
   /**
    * treat every Friday as fday start, Friday ~ Friday + day - 1 as fday interval
    * @param string $startDate
    * @param string $endDate
    * @param int $fday
    */
   public static function calculateFdayMap ($startDate, $endDate, $fday)
   {
       if ($fday < 7) {
           $fday = 7;
       }
   
       $orgFriday = self::getStartFriday($startDate);
       $fdays = self::getFdaysFromFriday($orgFriday, $endDate, $fday, false);
   
       $days = self::getDaysBetween($orgFriday, $endDate);
       $fdayMaps = [];
       foreach ($days as $day) {
           if (in_array($day, $fdays)) {
               $fdayMaps[$day] = true;
           } else {
               $fdayMaps[$day] = false;
           }
       }
   
       return $fdayMaps;
   }
   
   
   public static function getFdaysFromFriday ($startFriday, $endDate, $fday, $onlyFirstPart = false)
   {
       $curWd = date('w', strtotime($startFriday));
       if ($curWd != 5) {
           throw new \Exception("$startFriday is not Friday");
       }
        
   
       if ($fday - 1 >= (strtotime($endDate) - strtotime($startFriday)) / 86400  ) {
           return self::getDaysBetween($startFriday, $endDate);
       } else {
           $curPeriodEndDate = date('Y-m-d',strtotime($startFriday) + ($fday - 1) * 86400);
           $curPeriodFdays = self::getDaysBetween($startFriday, $curPeriodEndDate);
   
           if ($onlyFirstPart) {
               return $curPeriodFdays;
           }
   
           $nextFriday = self::getNextFriday($curPeriodEndDate);
           if (strtotime($nextFriday) > strtotime($endDate)) {
               return $curPeriodFdays;
           }
   
           return array_merge($curPeriodFdays, self::getFdaysFromFriday($nextFriday, $endDate, $fday));
       }
   
   }
   
   public static function isOfOddWeek ($date)
   {
       return date("W", strtotime($date)) % 2 == 0;
   }
   
   public static function isMonday ($date)
   {
       return date('w', strtotime($date)) == 1;
   }
   
   public static function getDaysCountBetween ($fromDate, $toDate)
   {
       return (strtotime($toDate) - strtotime($fromDate)) / 86400;
   }
   
   public static function getDaysBetween($fromDate, $toDate)
   {
       $dayCounts = (strtotime($toDate) - strtotime($fromDate)) / 86400;
       $dates = [];
       for ($i = 0; $i <= $dayCounts; $i++) {
           $dates[] = date('Y-m-d',strtotime($fromDate) + 86400 * $i);
       }
   
       return $dates;
   }
   
   /**
    * get the last Friday date of the specified date, including the date itself
    * @param string $date
    * @return string
    */
   public static function getStartFriday ($date)
   {
       $curWd = date('w', strtotime($date));
       if ($curWd == 0) {
           $curWd = 7;
       }
   
       if ($curWd >= 5) {
           return date('Y-m-d',strtotime($date) - ($curWd - 5) * 86400);
       } else {
           return date('Y-m-d',strtotime($date) - ($curWd + 2) * 86400);
       }
   }
   
   /**
    * get next Friday, not including itself
    * @param unknown $date
    * @return string
    */
   public static function getNextFriday ($date)
   {
       $curWd = date('w', strtotime($date));
       if ($curWd == 0) {
           $curWd = 7;
       }
   
       if ($curWd >= 5) {
           return date('Y-m-d',strtotime($date) + (5 + 7 - $curWd) * 86400);
       } else {
           return date('Y-m-d',strtotime($date) + (5 - $curWd) * 86400);
       }
   }
   
   
   

}