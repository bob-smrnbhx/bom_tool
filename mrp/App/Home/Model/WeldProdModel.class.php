<?php

namespace Home\Model;



class WeldProdModel extends AssyProdModel
{
    protected $_dmdModel;
    
    protected $baseConds = [
            'comp_pm_code' => 'L',
            'comp_status'  => 'AC',
            'in_type'      => 'a',
    ];
    protected $leadDayLen = 2;
    protected $WeekdayWorkRules = 0b0111111;
    protected $capacity = 5500;
    
    protected $isWeekdayWorkableMaps;
    
    protected $dmdDates = [];
    protected $availDates = [];
    
    protected $activeDates = [];
    protected $dateTypeMap = [];
    
    
    protected $partsInfo = [];
    
    protected $totalDayDmds = [];
    
    protected $_sodModel;
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('sub_dmd');
        }
    
        return $this->_dmdModel;
    }
    
    protected function getSodModel ()
    {
        if (empty($this->_sodModel)) {
            $this->_sodModel = M('sub_sod');
        }
    
        return $this->_sodModel;
    }
    
    protected function getBaseConds ()
    {
        return $this->baseConds;
    }
    
    
    protected function getOrderBy ()
    {
        return "comp_desc1";
    }
    
    public function __construct($site = '')
    {
 
        $this->_proj = 'cd539';
        if ($site) {
            $site = strval($site);
            $this->_site = $site;
            $this->addConds([
                    "par_site"  => $site
            ]);
            
            switch ($this->_site) {
                case 1000:
                    $this->addConds([
                        "comp_line"  => 'A00003',
                        "comp_promo"  => 'cd539'
                    ]);
                    $this->capacity = 2000;
                    break;
                case 6000:
                    $this->addConds([
                        "comp_line"  => 'A60003'
                    ]);
                    $this->capacity = 5500;
                    break;
            }
        }
        

        
        $this->getActiveDates();
        
        $cwMap = self::convertBinToWeekdayMap($this->WeekdayWorkRules);
        $cwMap[7] = $cwMap[0];
        unset($cwMap[0]);
        foreach ($cwMap as $key => $is) {
            $cwMap[$key] = (bool)$is;
        }
        $this->isWeekdayWorkableMaps = $cwMap;
        
        
        $this->getPartsInfo();
        
        foreach ($this->activeDates as $date) {
            if ($this->isDayDate($date) && $this->isWorkday($date)) {
                $this->dayCapacities[$date] = $this->availDayCapacities[$date] = $this->capacity;
            }
        
        

            foreach ($this->partsInfo as $part => $partInfo) {
        
                $this->totalDayDmds[$date] += $partInfo["dmds"][$date];
        
            }
        }
    }
    
    protected function isWorkday ($date)
    {
        if (!$this->isDayDate($date)) {
            return true;
        }
    
        $wd = date("N", strtotime($date));
        return $this->isWeekdayWorkableMaps[$wd] != 0;
    }
    
    
    
    
    

    
    
    public function getFormattedDatesMap ()
    {
        $map = [];
        foreach ($this->activeDates as $date) {
            $formattedDHeader = \DateHelper::getFormattedDate($date, $this->dateTypeMap[$date]);
            $map[$date] = $formattedDHeader;
        }
    
        return $map;
    }
    
    

    
    
    public function getPartsInfo ()
    {
        if (empty($this->partsInfo)) {
            $this->setStartDate($this->getStartActiveDate());
            $conds = $this->getConds();


            
            $model = $this->getDmdModel();
            $orgDate = $this->getOrgDate();

            $result = $this->getDmdModel()->where($conds)->order($this->getOrderBy())->select();

            $compsInfo = [];
            foreach ($result as $row) {
                $comp = $row["comp_part"];
                $par  = $row["par_part"];
                $dmdDate = $row["dmd_date"];
                $planDate = $row["plan_date"];  // 根据视图的设计，这两个日期采用了非匹配读取，因为，可能有父物料需求日期而不存在子物料计划日期，也可能存在子物料计划日期而不存在父物料需求日期
                if (!isset($this->partsInfo[$comp])) {
                    $this->partsInfo[$comp] = $row;
                    $this->partsInfo[$comp] += [
                            'isMrp'    => (bool)$row["comp_isdmrp"],
                            'part'     => $row["comp_part"],
                            'site'     => $row["comp_site"],
                            'desc1'    => $row["comp_desc1"],
                            'desc2'    => $row["comp_desc2"],
                            'buyer'    => $row["comp_buyer"],
                            'line'     => $row["comp_line"],
                            'rop'      => floatval($row["comp_rop"]),
                            'yldPct'   => floatval($row["comp_yld_pct"] / 100),
                            'yldPctText' => floatval($row["comp_yld_pct"]) . '%',
                            'ordMin'   => floatval($row["comp_ord_min"]),
                            'parDmds'  => [],
                            'parYldPcts'  => [],
                            'qtyPers'  => [],
                            'dmds'     => [],
                            'stocks'   => []
                    ];
                }
    
                // 如果子自制件不需要重新进行mrp，直接读取对应的生产计划数据即可
                if (!$this->partsInfo[$comp]["isMrp"]) {
                    // 已有某日期的计划数据时才进行记录
                    if ($planDate != null && !empty($row['plan_qty']) && !isset($this->partsInfo[$comp]['prods'][$planDate])) {
                        $this->partsInfo[$comp]['prods'][$planDate] =  floatval($row['plan_qty']);
                    }
                }
    
                if (!isset($this->partsInfo[$comp]["stocks"][$orgDate]) && $row["in_qty_oh"] && $row["in_date"] == $this->getStartActiveDate()) {
                    $this->partsInfo[$comp]["stocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
    
                if (!isset($this->partsInfo[$comp]["parDmds"][$dmdDate][$par]) && floatval($row["dmd_qty"])) {
                    $this->partsInfo[$comp]["parDmds"][$dmdDate][$par] = floatval($row["dmd_qty"]);
                }
    
//                 if (!isset($this->partsInfo[$comp]["parYldPcts"][$par])) {
//                     $this->partsInfo[$comp]["parYldPcts"][$par] = floatval($row["par_yld_pct"]) / 100;
//                 }
    
                if (!isset($this->partsInfo[$comp]["qtyPers"][$par])) {
                    $this->partsInfo[$comp]["qtyPers"][$par] = floatval($row["ps_qty_per"]);
                }
    
    
            }
            
            
            
            foreach ($this->partsInfo as $part => &$partInfo) {
                foreach ($this->getActiveDates() as $date) {
                    foreach ($partInfo['parDmds'][$date] as $par => $pdmd) {
                        //$partInfo['dmds'][$date] += ceil($pdmd / $partInfo['parYldPcts'][$par] * $partInfo['qtyPers'][$par]);
                        $partInfo['dmds'][$date] += $pdmd * $partInfo['qtyPers'][$par];
                    }
                }

            }
    
    

            
//             $result = $this->getSodModel()->where($conds)->order($this->getOrderBy())->select();
//             $compsInfo = [];

//             foreach ($result as $row) {
//                 $comp = $row["comp_part"];
//                 $par  = $row["par_part"];
//                 $allowedmach = $row["mold_mach"];
//                 $dmdDate = $row["dmd_date"];
//                 $planDate = $row["plan_date"];  // 根据视图的设计，这两个日期采用了非匹配读取，因为，可能有父物料需求日期而不存在子物料计划日期，也可能存在子物料计划日期而不存在父物料需求日期
//                 $planMach = $row["plan_mach"];
//                 $proj = $row["mold_proj"];
            
//                 if (!isset($this->machCaps[$allowedmach])) {
//                     $this->machCaps[$allowedmach] = floatval($row["mold_cap"]);
//                 }
            
//                 if (!isset($this->partsInfo[$comp])) {
//                     $this->partsInfo[$comp] = [
//                             'isMrp'    => (bool)$row["comp_isdmrp"],
//                             'part'     => $row["comp_part"],
//                             'site'     => $row["comp_site"],
//                             'desc1'    => $row["comp_desc1"],
//                             'desc2'    => $row["comp_desc2"],
//                             'buyer'    => $row["comp_buyer"],
//                             'line'     => $row["comp_line"],
//                             'rop'      => floatval($row["comp_rop"]),
//                             'ordMin'   => floatval($row["comp_ord_min"]),
            
//                             'allowedMachines' => [],
//                             'projs'    => [],
//                             'dir'      => $row["mold_lr"],
            
//                             'sodDmds'     => [],
//                             'stocks'   => []
//                     ];
            
            
//                 }
            
//                 // 如果子自制件不需要重新进行mrp，直接读取对应的生产计划数据即可
//                 if (!$this->partsInfo[$comp]["isMrp"]) {
//                     // 已有某日期的计划数据时才进行记录
//                     if ($planDate !== null && !isset($this->partsInfo[$comp]['prods'][$planDate])) {
//                         $this->partsInfo[$comp]['prods'][$planDate] =  floatval($row['plan_qty']);
//                     }
//                     if ($planMach !== null && !isset($this->partsInfo[$comp]['planMach'])) {
//                         $this->partsInfo[$comp]['planMach'] =  $planMach;
//                     }
//                 }
            
//                 if (!isset($this->partsInfo[$comp]["allowedMachines"][$allowedmach])) {
//                     $this->partsInfo[$comp]["allowedMachines"][$allowedmach] = $allowedmach;
//                 }
            
//                 if (!isset($this->partsInfo[$comp]["projs"][$proj])) {
//                     $this->partsInfo[$comp]["projs"][$proj] = $proj;
//                 }
            
//                 if (!isset($this->partsInfo[$comp]["stocks"][$orgDate]) && $row["in_qty_oh"] && $row["in_date"] == $this->getStartActiveDate()) {
//                     $this->partsInfo[$comp]["stocks"][$orgDate] = floatval($row["in_qty_oh"]);
//                 }
            
//                 if (!isset($this->partsInfo[$comp]["sodDmds"][$dmdDate]) && floatval($row["dmd_qty"])) {
//                     $this->partsInfo[$comp]["sodDmds"][$dmdDate] = floatval($row["dmd_qty"]);
//                 }
            
//             }
            
            
//             // 叠加上级物料分解和直接订单数量
//             foreach ($this->partsInfo as $part => &$partInfo) {
//                 foreach ($this->getActiveDates() as $date) {
//                     if ($partInfo["sodDmds"][$date]) {
//                         $partInfo["dmds"][$date] += $partInfo["sodDmds"][$date];
//                     }
//                 }
//             }

            
        }
         
    
        return $this->partsInfo;
    }
    
 
    
 
    
    
    public function doProcessMrp ()
    {
        $parts = $this->getPartsInfo();
        if (empty($parts)) {
            return;
        }
    
    
        $allParts = array_keys($parts);
        $mrpedParts = $this->getMrpedParts();
        $unmrpedparts = $this->getUnmrpedParts();
    
        foreach ($this->activeDates as $curDate) {
            $curIndex = array_keys($this->activeDayDates, $curDate)[0];
            $pastDates = array_slice($this->activeDayDates, 0, $curIndex + 1);
            
            foreach ($mrpedParts as $part) {
                if ($this->isDayDate($curDate)) {
                    $this->availDayCapacities[$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
                } else {
                    // 830计划一般不被导入，这时候直接算出来
                    if (empty($this->partsInfo[$part]["prods"][$curDate])) {
                        $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $this->partsInfo[$part]["ordMin"]) * $this->partsInfo[$part]["ordMin"];
                    }
                }
            }
            

            
            if ($this->isDayDate($curDate)) {
                if ($this->isWorkday($curDate)) {
                    $prevDate = \DateHelper::getDateBefore($curDate);
                    foreach ($unmrpedparts as $part) {
                        $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
                        $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                        $curProd = $this->partsInfo[$part]["prods"][$curDate];
                        $relatedDmd = $this->getDmdAfterLeadedDay($part, $curDate);
                    
                        $curNetDmd = $curDmd + $relatedDmd - $curProd - $prevStock ;
                    
                        if ($curNetDmd > 0) {
                            $this->arrangeLeadDayProduction($part, $curDate, $curNetDmd, false);
                        }
                        
                        $this->calculatePartStocksBetween($part, $pastDates);
                    }
                    
                    if ($this->useComplement) {
                        $this->complementDayCapacity($curDate);
                        
                        foreach ($unmrpedparts as $part) {
                            $this->calculatePartStockOfDate($part, $curDate);
                        }
                    }
                }
            } else {
                foreach ($unmrpedparts as $part) {
                    $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $this->partsInfo[$part]["ordMin"]) * $this->partsInfo[$part]["ordMin"];
                    //var_dump($part, $curDate, $this->partsInfo[$part]["prods"][$curDate]);   
                }

            }
            
            
            foreach ($allParts as $part) {
                $this->calculatePartStockOfDate($part, $curDate);
            }
            
            $this->calculateCurrentTotalPartsProdOfDate($curDate);
        }
    }
    
//     public function arrangeLeadDayProduction($part, $prodDate, $curNetDmd)
//     {
//         $ordMin = $this->partsInfo[$part]["ordMin"];
//         $curNetDmdForPallets = ceil($curNetDmd / $ordMin) * $ordMin;
    
//         $leftCapacity = $this->availDayCapacities[$prodDate];
    
    
    
//         if ($leftCapacity >= $curNetDmdForPallets) {
//             $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
//             $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
//         } else if ($prodDate == $this->getStartActiveDate()) {
//             $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
//             $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
//         } else  {
    
    
//             if ($leftCapacity >= $ordMin) {
//                 $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
//                 $this->partsInfo[$part]["prods"][$prodDate] += $prodAmount;
//                 $this->availDayCapacities[$prodDate] -= $prodAmount;
    
//                 $curNetDmd -= $prodAmount;
//             }
    
//             // 然后，还需将可能残存的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
//             $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
//             // 当某零件进行当日的额外最小量回溯排产，设置回溯标志为true,这部分回溯的需求量，是强制性的。
    
//             $this->arrangeLeadDayProduction($part, $preProdDate, $curNetDmd);
    
    
//         }
//     }
    
    protected function calculatePartStockOfDate ($part, $date)
    {
        if ($this->isDayDate($date)) {
            $prevDate = \DateHelper::getDateBefore($date);
        } else {
            $prevDate = self::getPrevArrayElement($this->activeDates, $date);
        }
        

        
        $pOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
    
        
        $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $this->partsInfo[$part]["prods"][$date] - $this->partsInfo[$part]["dmds"][$date];
 
    }
    
 
 
    
 
    
    
}