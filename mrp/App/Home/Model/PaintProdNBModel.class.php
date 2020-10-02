<?php

namespace Home\Model;


class PaintProdNBModel extends WeldProdModel
{
    protected $baseConds = [
            'comp_pm_code' => 'L',
            'comp_status'  => 'AC',
            'comp_promo'   => 'CD539',
            'in_type'      => 'a'
    ];
    
    protected $_line;
    
    protected $subProjs = [];
    
    protected $availProjDirDailyCaps = [];
    protected $groupedPartsInfo = [];
    


    
    
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('paint_dmd_nb');
        }
    
        return $this->_dmdModel;
    }
    
  
    
    
    protected function getOrderBy ()
    {
        //return "pgp_ord, paint_color, comp_part";
    }
    
    public function __construct($site = '', $line = '')
    {
        $this->_proj = 'cd539';
        
        if ($site) {
            $site = strval($site);
            
            $this->_site = $site;
            
            $this->addConds([
                    "par_site" => $this->_site
            ]);
            
            if (empty($line)) {
                $line = $site == 1000 ? "P00001" : "P60001";
            }
            
            $this->_line = $line;
            
            $this->addConds([
                    "comp_line" => $this->_line
            ]);
        }
        

        
 
        $this->getActiveDates();
    
        $cwMap = self::convertBinToWeekdayMap($this->WeekdayWorkRules);
        $cwMap[7] = $cwMap[0];
        unset($cwMap[0]);
        foreach ($cwMap as $key => $is) {
            $cwMap[$key] = (bool)$is;
        }
        $this->isWeekdayWorkableMaps = $cwMap;
    
 
        $this->getAvailProjDirDailyCaps();
        $this->getPartsInfo();
    
        
        
        foreach ($this->activeDates as $date) {
            $this->availDayCapacities[$date] = 5500;
            
            foreach ($this->partsInfo as $part => $partInfo) {
                $this->totalDayDmds[$date] += $partInfo["dmds"][$date];
            }
        }
    }
    

    
    
    /**
     * 按照预定规则对油漆件进行排序
     * @see \Home\Model\WeldProdModel::getPartsInfo()
     */
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
                            'proj'     => $row["comp_promo"],
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
                
                if (!isset($this->partsInfo[$comp]["dir"])) {
                    $this->partsInfo[$comp]["dir"] = stripos($row["comp_desc1"], "左") ? "L" : "R";
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

        }
        
        return $this->partsInfo;
    }
    
    public function getSubProjs()
    {
        return array_values($this->subProjs);
    }
    
    public function getAvailProjDirDailyCaps()
    {
        if (empty($this->availProjDirDailyCaps)) {
            $adapter = new \Home\Model\PaintProjModel($this->_site);
            $adapter->setDailyStartDate($this->today);
            $this->availProjDirDailyCaps = $adapter->getProjDirDailyCaps();
        }

        return $this->availProjDirDailyCaps;
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
                    $this->partsInfo[$part]['qfdProds'][$curDate] = round($this->partsInfo[$part]['prods'][$curDate] * $this->partsInfo[$part]['yldPct']);
                } else {
                    // 830计划一般不被导入，这时候直接算出来
                    if (empty($this->partsInfo[$part]["prods"][$curDate])) {
                        $this->partsInfo[$part]["qfdProds"][$curDate] =  $this->partsInfo[$part]["dmds"][$curDate];
                        $this->partsInfo[$part]["prods"][$curDate] = round($this->partsInfo[$part]["qfdProds"][$curDate] / $this->partsInfo[$part]['yldPct']);
                    } else {
                        $this->partsInfo[$part]['qfdProds'][$curDate] = round($this->partsInfo[$part]['prods'][$curDate] * $this->partsInfo[$part]['yldPct']);
                    }
                }
                
                
            }
    
            foreach ($unmrpedparts as $part) {
                if ($this->isDayDate($curDate)) {
                    if ($this->isWorkday($curDate)) {
                        $prevDate = \DateHelper::getDateBefore($curDate);
                        $nextDate = \DateHelper::getDateAfter($curDate);
                        $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
                        $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                        $curProd = $this->partsInfo[$part]["qfdProds"][$curDate];
                        $rop = $this->partsInfo[$part]["rop"];
            
                        $curNetDmd = $curDmd + $rop - $curProd - $prevStock ;

                        if ($curNetDmd > 0) {
                            $curNetDmd = round($curNetDmd / $this->partsInfo[$part]['yldPct']);
                            $this->arrangeProduction($part, $curDate, $curNetDmd);
                        }
                    }
                    $this->calculatePartStocksBetween($part, $pastDates);
                } else {
                    $this->partsInfo[$part]["qfdProds"][$curDate] = $this->partsInfo[$part]["dmds"][$curDate];
                
                }
                
                $this->partsInfo[$part]["prods"][$curDate] = round($this->partsInfo[$part]["qfdProds"][$curDate] / $this->partsInfo[$part]['yldPct']);
            }
            
            
            $this->complementDayCapacity($curDate);
            
 
            
            
            foreach ($allParts as $part) {
                $this->calculatePartStockOfDate($part, $curDate);
            }
        }
        
        //$this->calculateAllPartsStock();
        
 
        
    }
    
    protected function addPartProdOfDate ($part, $date, $netProd)
    {
        $this->partsInfo[$part]["prods"][$date] += $netProd;
        $this->partsInfo[$part]["qfdProds"][$date] = round($this->partsInfo[$part]["prods"][$date] * $this->partsInfo[$part]['yldPct']);
        $this->availDayCapacities[$date] -= $netProd;
    }
    
    
    protected function calculatePartStockOfDate ($part, $date)
    {
        if ($this->isDayDate($date)) {
            $prevDate = \DateHelper::getDateBefore($date);
        } else {
            $prevDate = self::getPrevArrayElement($this->activeDates, $date);
        }
    
    
    
        $pOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
    
    
        $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $this->partsInfo[$part]["qfdProds"][$date] - $this->partsInfo[$part]["dmds"][$date];
    
    }
    
    
    public function arrangeProduction($part, $prodDate, $curNetDmd)
    {
        $proj = $this->partsInfo[$part]["proj"];
        $dir = $this->partsInfo[$part]["dir"];
        $leftCapacity = $this->availProjDirDailyCaps[$proj][$dir][$prodDate];
    
    
        if ($leftCapacity >= $curNetDmd) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmd;
            $this->availProjDirDailyCaps[$proj][$dir][$prodDate] -= $curNetDmd;
            $this->partsInfo[$part]['qfdProds'][$prodDate] = round($this->partsInfo[$part]['prods'][$prodDate] * $this->partsInfo[$part]['yldPct']);
        } else if ($prodDate == $this->getStartActiveDate()) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmd;
            $this->availProjDirDailyCaps[$proj][$dir][$prodDate] -= $curNetDmd;
            $this->partsInfo[$part]['qfdProds'][$prodDate] = round($this->partsInfo[$part]['prods'][$prodDate] * $this->partsInfo[$part]['yldPct']);
        } else  {
            if ($leftCapacity > 0) {
                $prodAmount = $leftCapacity;
                $this->partsInfo[$part]["prods"][$prodDate] += $prodAmount;
                $this->availProjDirDailyCaps[$proj][$dir][$prodDate] = 0;
                $this->partsInfo[$part]['qfdProds'][$prodDate] = round($this->partsInfo[$part]['prods'][$prodDate] * $this->partsInfo[$part]['yldPct']);
                $curNetDmd -= $prodAmount;
            }
    
    
    
            // 然后，还需将可能残存的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
            $this->arrangeProduction($part, $preProdDate, $curNetDmd);
    
    
        }
    }
    
//     public function doProcessMrp ()
//     {
//         $parts = $this->getPartsInfo();
//         if (empty($parts)) {
//             return;
//         }
    
    
//         $allParts = array_keys($parts);
//         $mrpedParts = $this->getMrpedParts();
//         $unmrpedparts = $this->getUnmrpedParts();
    
//         foreach ($this->activeDates as $curDate) {
//             foreach ($mrpedParts as $part) {
//                 $this->availDayCapacities[$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
//             }
    
//             if ($this->isDayDate($curDate)) {
//                 if ($this->isWorkday($curDate)) {
//                     $prevDate = \DateHelper::getDateBefore($curDate);
//                     $nextDate = \DateHelper::getDateAfter($curDate);
//                     foreach ($unmrpedparts as $part) {
//                         $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
//                         $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
//                         $curProd = $this->partsInfo[$part]["prods"][$curDate];
//                         $relatedDmd = $this->getDmdAfterLeadedDay($part, $curDate);
//                         $rop = $this->partsInfo[$part]["rop"];
    
//                         $curNetDmd = $curDmd + $relatedDmd + $rop - $curProd - $prevStock ;
                        
                        
//                         if ($curNetDmd > 0) {
//                             $this->arrangeLeadDayProduction($part, $curDate, $curNetDmd);
//                         }
//                     }
//                 }
//             } else {
//                 foreach ($unmrpedparts as $part) {
//                     $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $this->partsInfo[$part]["ordMin"]) * $this->partsInfo[$part]["ordMin"];
//                     //var_dump($part, $curDate, $this->partsInfo[$part]["prods"][$curDate]);
//                 }
    
//             }
    
//             foreach ($allParts as $part) {
//                 $this->calculatePartStockOfDate($part, $curDate);
//             }
    
//             $this->calculateCurrentTotalPartsProdOfDate($curDate);
//         }
//     }
    
    
    
    
}